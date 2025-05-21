<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

require '../../assets/php/conexao.php';

$idSelecionado = $_GET['id'] ?? '';

if (
  !isset($_SESSION['usuario_logado']) ||
  !isset($_SESSION['empresa_id']) ||
  !isset($_SESSION['tipo_empresa']) ||
  !isset($_SESSION['usuario_id']) ||
  !isset($_SESSION['nivel'])
) {
  header("Location: ../index.php?id=$idSelecionado");
  exit;
}

// Ícone da empresa
$iconeEmpresa = '../../assets/img/favicon/favicon.ico';
try {
  $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1");
  $stmt->execute([':id' => $idSelecionado]);
  $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!empty($empresa['imagem'])) {
    $iconeEmpresa = $empresa['imagem'];
  }
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar ícone: " . addslashes($e->getMessage()) . "');</script>";
}

// === Dados do usuário ===
$usuario_id = $_SESSION['usuario_id'];
$tipoSessao = $_SESSION['nivel'];
$cpf = '';
$nomeFuncionario = 'Desconhecido';

try {
  $sql = $tipoSessao === 'Admin'
    ? "SELECT usuario, cpf FROM contas_acesso WHERE id = :id"
    : "SELECT usuario, cpf FROM funcionarios_acesso WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':id' => $usuario_id]);
  $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($usuario) {
    $cpf = $usuario['cpf'];
  }
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar usuário: " . addslashes($e->getMessage()) . "');</script>";
  exit;
}

// Nome do funcionário
try {
  $stmt = $pdo->prepare("SELECT nome FROM funcionarios WHERE cpf = :cpf LIMIT 1");
  $stmt->execute([':cpf' => $cpf]);
  $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($funcionario) {
    $nomeFuncionario = $funcionario['nome'];
  }
} catch (PDOException $e) {
  echo "<script>alert('Erro ao buscar nome do funcionário: " . addslashes($e->getMessage()) . "');</script>";
}

// === Registro de ponto ===
$dataAtual = date('Y-m-d');
$horaAtual = date('H:i:s');
$horaAgoraTs = strtotime($horaAtual);

$diaSemanaIng = strtolower(date('l'));
$mapaDias = [
  'sunday' => 'domingo', 'monday' => 'segunda', 'tuesday' => 'terca',
  'wednesday' => 'quarta', 'thursday' => 'quinta', 'friday' => 'sexta',
  'saturday' => 'sabado'
];
$diaTraduzido = $mapaDias[$diaSemanaIng];

$stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE cpf = :cpf LIMIT 1");
$stmt->execute([':cpf' => $cpf]);
$func = $stmt->fetch(PDO::FETCH_ASSOC);

$exibirFormulario = false;
$mensagemTurno = '';
$horaEntradaRef = '';
$horaSaidaRef = '';
$horaEntradaToleran = '';
$registroPontoHoje = null;

if ($func) {
  $numDias = ['domingo' => 0, 'segunda' => 1, 'terca' => 2, 'quarta' => 3, 'quinta' => 4, 'sexta' => 5, 'sabado' => 6];
  $numHoje = $numDias[$diaTraduzido];
  $numInicio = $numDias[strtolower($func['dia_inicio'])];
  $numTermino = $numDias[strtolower($func['dia_termino'])];

  $trabalhaHoje = (
    ($numInicio <= $numTermino && $numHoje >= $numInicio && $numHoje <= $numTermino) ||
    ($numInicio > $numTermino && ($numHoje >= $numInicio || $numHoje <= $numTermino))
  );

  if ($trabalhaHoje) {
    $mEnt = $func['hora_entrada_primeiro_turno'];
    $mSai = $func['hora_saida_primeiro_turno'];
    $tEnt = $func['hora_entrada_segundo_turno'];
    $tSai = $func['hora_saida_segundo_turno'];

    // Converte para timestamp
    $tsAgora = strtotime($horaAtual);
    $turnos = [];

    if ($mEnt && $mSai) {
      $tsInicio = strtotime($mEnt);
      $tsFim = strtotime($mSai);
      if ($tsFim < $tsInicio) {
        $tsFim = strtotime('+1 day', $tsFim); // atravessa a meia-noite
      }
      $turnos[] = ['inicio' => $tsInicio, 'fim' => $tsFim, 'refEnt' => $mEnt, 'refSai' => $mSai];
    }

    if ($tEnt && $tSai) {
      $tsInicio = strtotime($tEnt);
      $tsFim = strtotime($tSai);
      if ($tsFim < $tsInicio) {
        $tsFim = strtotime('+1 day', $tsFim); // atravessa a meia-noite
      }
      $turnos[] = ['inicio' => $tsInicio, 'fim' => $tsFim, 'refEnt' => $tEnt, 'refSai' => $tSai];
    }

    foreach ($turnos as $turno) {
      if (
        $horaAgoraTs >= $turno['inicio'] ||
        ($turno['fim'] > $turno['inicio'] && $horaAgoraTs <= $turno['fim']) ||
        ($turno['fim'] < $turno['inicio'] && ($horaAgoraTs <= $turno['fim'] || $horaAgoraTs >= $turno['inicio']))
      ) {
        $horaEntradaRef = $turno['refEnt'];
        $horaSaidaRef = $turno['refSai'];
        break;
      }
    }

    // Define tolerância
    if ($horaEntradaRef) {
      $horaEntradaToleran = date('H:i:s', strtotime($horaEntradaRef) + 10 * 60);
    }

    // Sempre permite exibir o formulário, independentemente do status do ponto
    $mensagemTurno = "<div class='alert alert-warning text-center'>Você pode registrar ponto agora.</div>";
    $exibirFormulario = true;

  } else {
    $mensagemTurno = "<div class='alert alert-warning text-center'>Hoje não é dia de trabalho para este funcionário.</div>";
    $exibirFormulario = false;
  }
} else {
  $mensagemTurno = "<div class='alert alert-danger text-center'>Funcionário não encontrado com este CPF.</div>";
  $exibirFormulario = false;
}
?>



<!DOCTYPE html>
<html lang="pt-br" class="light-style customizer-hide" dir="ltr" data-theme="theme-default"
  data-assets-path="../../../assets/" data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <!-- Favicon da empresa carregado dinamicamente -->
  <link rel="icon" type="image/x-icon" href="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>" />

  <title>ERP - Registro de Ponto</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700&display=swap"
    rel="stylesheet" />

  <link rel="stylesheet" href="../../../assets/vendor/fonts/boxicons.css" />
  <link rel="stylesheet" href="../../../assets/vendor/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="../../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="../../../assets/css/demo.css" />
  <link rel="stylesheet" href="../../../assets/css/button-responsive.css" />
  <link rel="stylesheet" href="../../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="../../../assets/vendor/css/pages/page-auth.css" />

  <script src="../../../assets/vendor/js/helpers.js"></script>
  <script src="../../../assets/js/config.js"></script>
</head>

<body>

  <div class="container-xxl">
    <div class="authentication-wrapper authentication-basic container-p-y">
      <div class="authentication-inner">

        <!-- Card de Registro -->
        <div class="card">
          <div class="card-body">

            <!-- Título -->
            <div class="app-brand justify-content-center mb-3">
              <a href="#" class="app-brand-link gap-2">
                <span class="app-brand-text demo text-body fw-bolder">Registro de Ponto</span>
              </a>
            </div>

            <!-- Alerta de localização -->
            <div id="mensagemLocalizacao" class="alert alert-secondary text-center mb-4" role="alert"
              style="<?= $exibirFormulario ? '' : 'display:none;' ?>">
              Por favor, ative a localização do seu dispositivo.
            </div>

            <?php if ($exibirFormulario): ?>
              <form id="formRegistroPonto" action="../php/sistemaPonto/registrarPonto.php" method="POST" class="mb-3">
                <input type="hidden" name="id_selecionado" value="<?= htmlspecialchars($idSelecionado) ?>">
                <input type="hidden" name="cpf" value="<?= htmlspecialchars($cpf) ?>">
                <input type="hidden" name="data" value="<?= htmlspecialchars($dataAtual) ?>">
                <input type="hidden" id="hora_atual" name="hora_atual">
                <input type="hidden" id="fotoBase64" name="fotoBase64">
                <input type="hidden" id="inputLocalizacao" name="localizacao">

                <div class="mb-3">
                  <label class="form-label">Funcionário</label>
                  <input type="text" class="form-control" readonly value="<?= htmlspecialchars($nomeFuncionario) ?>" />
                </div>

                <div class="mb-3">
                  <label class="form-label">Hora Atual</label>
                  <input type="text" id="hora" class="form-control" readonly />
                </div>

                <div class="mb-3">
                  <label class="form-label">Hora de Entrada</label>
                  <input type="text" class="form-control" readonly value="<?= htmlspecialchars($horaEntradaReferencial) ?>" />
                </div>

                <div class="mb-3">
                  <label class="form-label">Entrada com Tolerância</label>
                  <input type="text" class="form-control" readonly value="<?= htmlspecialchars($horaEntradaTolerancia) ?>" />
                </div>

                <div class="mb-4">
                  <label class="form-label">Hora de Saída</label>
                  <input type="text" class="form-control" readonly value="<?= htmlspecialchars($horaSaidaReferencial) ?>" />
                </div>

                <!-- Modal de Preview da Foto -->
                <div id="modalPreview"
                  style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:#000000dd; justify-content:center; align-items:center; flex-direction:column; z-index:10000;">
                  <img id="previewImagem" style="max-width: 90%; border-radius: 10px; margin-bottom: 15px;" />
                  <div class="d-flex gap-2">
                    <button type="button" id="btnConfirmarPreview" class="btn btn-success">Confirmar</button>
                    <button type="button" id="btnRefazerFoto" class="btn btn-secondary">Tirar Novamente</button>
                  </div>
                </div>

                <!-- Mensagem de Sucesso da Foto -->
                <div id="mensagemSucesso" class="alert alert-success text-center mt-3" style="display:none;">
                  Imagem processada com sucesso!
                </div>

                <!-- Botões de Registro -->
                <div id="sumir" class="mb-3 text-center" style="display:none;">
                  <button type="submit" name="acao" value="entrada" class="btn btn-primary">Registrar Entrada</button>

                  <?php if ($registroPonto && $registroPonto['saida'] === NULL): ?>
                    <button type="submit" name="acao" value="saida" class="btn btn-warning" id="btnRegistrarSaida">Registrar Saída</button>
                  <?php else: ?>
                    <button type="submit" class="btn btn-warning" id="btnRegistrarSaida" disabled>Registrar Saída</button>
                  <?php endif; ?>
                </div>
              </form>

              <!-- Botão para Abrir Câmera -->
              <div id="butao" style="display:block;" class="text-center mb-4">
                <button id="abrirCameraBtn" type="button" class="btn btn-primary w-100">📷 Tirar Foto</button>
              </div>
            <?php endif; ?>


            <!-- Mensagem de turno -->
            <div class="text-center mb-3">
              <?= $mensagemTurno ?>
            </div>

            <!-- Link voltar -->
            <div class="text-center mt-3">
              <a href="./pontoRegistrado.php?id=<?= htmlspecialchars($idSelecionado) ?>"
                class="d-flex align-items-center justify-content-center">
                <i class="bx bx-chevron-left scaleX-n1-rtl bx-sm"></i> Voltar
              </a>
            </div>

            <!-- Modal da Câmera -->
            <div id="modalCamera"
              style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:#000000dd; justify-content:center; align-items:center; flex-direction:column; z-index:9999;">
              <video id="previewCamera" autoplay playsinline
                style="width: 90%; max-width: 400px; border-radius: 10px;"></video>
              <button id="capturarFoto" style="margin-top: 10px;" class="btn btn-primary">📸 Capturar</button>
            </div>

          </div>
        </div>
      </div>

    </div>
  </div>
  </div>


  <!-- JavaScript da câmera, localização e horário -->
  <script>
    const abrirCameraBtn = document.getElementById('abrirCameraBtn');
    const modalCamera = document.getElementById('modalCamera');
    const previewCamera = document.getElementById('previewCamera');
    const capturarFoto = document.getElementById('capturarFoto');
    const fotoBase64 = document.getElementById('fotoBase64');
    const sumir = document.getElementById('sumir');
    const butao = document.getElementById('butao');
    const mensagemSucesso = document.getElementById('mensagemSucesso');
    const modalPreview = document.getElementById('modalPreview');
    const btnConfirmarPreview = document.getElementById('btnConfirmarPreview');
    const btnRefazerFoto = document.getElementById('btnRefazerFoto');
    const inputLocalizacao = document.getElementById('inputLocalizacao');
    const mensagemLocalizacao = document.getElementById('mensagemLocalizacao');

    let stream;
    let localizacaoObtida = false;

    // Abrir câmera
    async function abrirCamera() {
      modalCamera.style.display = 'flex';
      try {
        stream = await navigator.mediaDevices.getUserMedia({
          video: true
        });
        previewCamera.srcObject = stream;
      } catch (error) {
        alert('Erro ao acessar a câmera: ' + error.message);
      }
    }

    // Capturar foto
    capturarFoto.addEventListener('click', () => {
      const canvas = document.createElement('canvas');
      canvas.width = previewCamera.videoWidth;
      canvas.height = previewCamera.videoHeight;
      canvas.getContext('2d').drawImage(previewCamera, 0, 0);
      const imageData = canvas.toDataURL('image/jpeg');

      // Salva imagem
      window.imagemCapturada = imageData;

      // Mostra preview
      document.getElementById('previewImagem').src = imageData;
      modalCamera.style.display = 'none';
      modalPreview.style.display = 'flex';

      // Para a câmera
      if (stream) stream.getTracks().forEach(track => track.stop());
    });

    // Confirmar foto (no preview)
    btnConfirmarPreview.addEventListener('click', () => {
      mensagemSucesso.style.display = 'block'; // Mostra mensagem
      modalPreview.style.display = 'none'; // Fecha modal de preview
      sumir.style.display = 'block'; // Mostra botão de registrar ponto
      butao.style.display = 'none'; // Oculta botão de tirar foto

      // Atualiza input hidden com base64 da imagem
      fotoBase64.value = window.imagemCapturada;
    });

    // Refazer foto (reabrir câmera)
    btnRefazerFoto.addEventListener('click', () => {
      modalPreview.style.display = 'none';
      abrirCamera();
    });

    // Botão principal para abrir a câmera
    abrirCameraBtn.addEventListener('click', abrirCamera);

    // Obter localização
    function obterLocalizacao() {
      if (!mensagemLocalizacao) return; // Evita erro se não existir elemento
      mensagemLocalizacao.className = 'alert alert-info text-center mb-3';
      mensagemLocalizacao.innerText = 'Aguardando localização...';

      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
          (position) => {
            const latitude = position.coords.latitude.toFixed(6);
            const longitude = position.coords.longitude.toFixed(6);
            inputLocalizacao.value = `${latitude},${longitude}`;
            localizacaoObtida = true;

            mensagemLocalizacao.className = 'alert alert-success text-center mb-3';
            mensagemLocalizacao.innerText = 'Localização capturada com sucesso.';
          },
          (error) => {
            mensagemLocalizacao.className = 'alert alert-danger text-center mb-3';
            mensagemLocalizacao.innerText = 'Erro: Ative a localização do seu dispositivo.';
            localizacaoObtida = false;
          }, {
            enableHighAccuracy: true,
            timeout: 10000
          }
        );
      } else {
        mensagemLocalizacao.className = 'alert alert-warning text-center mb-3';
        mensagemLocalizacao.innerText = 'Seu navegador não suporta geolocalização.';
        localizacaoObtida = false;
      }
    }

    // Atualizar hora nos campos 'hora' e 'hora_atual' a cada segundo
    function atualizarHora() {
      const agora = new Date();

      // Formato HH:MM:SS (usado no backend para salvar)
      const horaFormatada = agora.toTimeString().split(' ')[0]; // Ex: "08:41:15"

      // Formato legível para exibir no input 'hora'
      const horaLegivel = agora.toLocaleTimeString('pt-BR', {
        hour12: false
      });

      const campoHora = document.getElementById('hora');
      const campoHoraAtual = document.getElementById('hora_atual');

      if (campoHora) campoHora.value = horaLegivel;
      if (campoHoraAtual) campoHoraAtual.value = horaFormatada;
    }

    // Executa ao carregar a página e atualiza a hora a cada segundo
    window.onload = () => {
      obterLocalizacao();
      atualizarHora();
      setInterval(atualizarHora, 1000);
    };
  </script>


  <!-- Scripts -->
  <script src="../../../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../../../assets/vendor/libs/popper/popper.js"></script>
  <script src="../../../assets/vendor/js/bootstrap.js"></script>
  <script src="../../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="../../../assets/vendor/js/menu.js"></script>
  <script src="../../../assets/js/main.js"></script>

</body>

</html>
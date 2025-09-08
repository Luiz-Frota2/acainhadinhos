<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// ✅ Recupera o identificador vindo da URL
$idSelecionado = $_GET['id'] ?? '';

// ✅ Verifica se a pessoa está logada
if (
  !isset($_SESSION['usuario_logado']) ||
  !isset($_SESSION['empresa_id']) ||
  !isset($_SESSION['tipo_empresa']) ||
  !isset($_SESSION['usuario_id']) ||
  !isset($_SESSION['nivel']) // Verifica se o nível está na sessão
) {
  header("Location: ../index.php?id=$idSelecionado");
  exit;
}

// ✅ Conexão com o banco de dados
require '../../assets/php/conexao.php';

$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id = $_SESSION['usuario_id'];
$tipoUsuarioSessao = $_SESSION['nivel']; // "Admin" ou "Comum"

try {
  // Verifica se é um usuário de contas_acesso (Admin) ou funcionarios_acesso
  if ($tipoUsuarioSessao === 'Admin') {
    // Buscar na tabela de contas_acesso
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
  } else {
    // Buscar na tabela de funcionarios_acesso
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM funcionarios_acesso WHERE id = :id");
  }

  $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
  $stmt->execute();
  $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($usuario) {
    $nomeUsuario = $usuario['usuario'];
    $tipoUsuario = ucfirst($usuario['nivel']);
  } else {
    echo "<script>alert('Usuário não encontrado.'); window.location.href = './index.php?id=$idSelecionado';</script>";
    exit;
  }
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar nome e tipo do usuário: " . addslashes($e->getMessage()) . "'); history.back();</script>";
  exit;
}

// ✅ Valida o tipo de empresa e o acesso permitido
if (str_starts_with($idSelecionado, 'principal_')) {
  // Para principal, verifica se é admin ou se pertence à mesma empresa
  if ($_SESSION['tipo_empresa'] !== 'principal' && 
      !($tipoUsuarioSessao === 'Admin' && $_SESSION['empresa_id'] === 'principal_1')) {
    echo "<script>
            alert('Acesso negado!');
            window.location.href = '../index.php?id=$idSelecionado';
        </script>";
    exit;
  }
  $id = 1;
} elseif (str_starts_with($idSelecionado, 'unidade_')) {
  $idUnidade = str_replace('unidade_', '', $idSelecionado);
  
  // Verifica se o usuário pertence à mesma unidade ou é admin da principal_1
  $acessoPermitido = ($_SESSION['empresa_id'] === $idSelecionado) || 
                    ($tipoUsuarioSessao === 'Admin' && $_SESSION['empresa_id'] === 'principal_1');
  
  if (!$acessoPermitido) {
    echo "<script>
            alert('Acesso negado!');
            window.location.href = '../index.php?id=$idSelecionado';
        </script>";
    exit;
  }
  $id = $idUnidade;
} else {
  echo "<script>
        alert('Empresa não identificada!');
        window.location.href = '../index.php?id=$idSelecionado';
    </script>";
  exit;
}

// ✅ Buscar imagem da empresa para usar como favicon
$iconeEmpresa = '../assets/img/favicon/favicon.ico'; // Ícone padrão

try {
  $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1");
  $stmt->bindParam(':id_selecionado', $idSelecionado);
  $stmt->execute();
  $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($empresa && !empty($empresa['imagem'])) {
    $iconeEmpresa = $empresa['imagem'];
  }
} catch (PDOException $e) {
  error_log("Erro ao carregar ícone da empresa: " . $e->getMessage());
  // Não mostra erro para o usuário para não quebrar a página
}

$dataAtual = date('Y-m-d');
$horaAtual = date('H:i:s');
$horaAgoraTs = strtotime($horaAtual);

// Traduz dia da semana
$diaSemanaIng = strtolower(date('l'));
$mapaDias = [
  'sunday' => 'domingo',
  'monday' => 'segunda',
  'tuesday' => 'terca',
  'wednesday' => 'quarta',
  'thursday' => 'quinta',
  'friday' => 'sexta',
  'saturday' => 'sabado'
];
$diaTraduzido = $mapaDias[$diaSemanaIng];

// PRINCIPAL CORREÇÃO: Obter o CPF do funcionário da sessão ou de outro lugar
// Você precisa definir $cpf antes de usá-lo na consulta
// Exemplo 1: Se o CPF está na sessão:
$cpf = $_SESSION['cpf'] ?? null;

// Exemplo 2: Se você precisa buscar o CPF do usuário logado:
if (!isset($cpf)) {
  try {
    if ($tipoUsuarioSessao === 'Admin') {
      $stmt = $pdo->prepare("SELECT cpf FROM contas_acesso WHERE id = :id");
    } else {
      $stmt = $pdo->prepare("SELECT cpf FROM funcionarios_acesso WHERE id = :id");
    }
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $cpf = $result['cpf'] ?? null;
  } catch (PDOException $e) {
    echo "<script>alert('Erro ao buscar CPF do usuário: " . addslashes($e->getMessage()) . "'); history.back();</script>";
    exit;
  }
}

if (empty($cpf)) {
  echo "<script>alert('CPF do funcionário não encontrado.'); history.back();</script>";
  exit;
}

// Busca o funcionário na tabela `funcionarios`
$stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE cpf = :cpf LIMIT 1");
$stmt->execute([':cpf' => $cpf]);
$func = $stmt->fetch(PDO::FETCH_ASSOC);

// Inicializa a variável para exibir o formulário
$exibirFormulario = false;
$registroPontoHoje = null; // Inicia a variável para evitar erros de undefined

// Verifica se o funcionário foi encontrado
if ($func) {
  $nomeFuncionario = $func['nome'];
  $horaEntradaRef = $func['entrada'];
  $horaSaidaIntervaloRef = $func['saida_intervalo'];
  $horaRetornoIntervaloRef = $func['retorno_intervalo'];
  $horaSaidaFinalRef = $func['saida_final'];

  // Calcula a tolerância de 10 minutos para entrada e retorno de intervalo
  $horaEntradaToleran = date('H:i:s', strtotime($horaEntradaRef . ' +10 minutes'));
  $horaRetornoToleran = date('H:i:s', strtotime($horaRetornoIntervaloRef . ' +10 minutes'));

  // Busca os registros de ponto do funcionário para o dia atual na tabela `pontos`
  try {
    $stmt = $pdo->prepare("SELECT * FROM pontos WHERE cpf = :cpf AND data = :data LIMIT 1");
    $stmt->execute([':cpf' => $cpf, ':data' => $dataAtual]);
    $registroPontoHoje = $stmt->fetch(PDO::FETCH_ASSOC);
  } catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar registros de ponto: " . addslashes($e->getMessage()) . "');</script>";
    exit;
  }

  // Exibe o formulário apenas se o funcionário for encontrado
  $exibirFormulario = true;

  // Mensagem para exibir o horário de entrada com tolerância
  $mensagemFuncionario = "O horário de entrada com tolerância é de 10 minutos";
} else {
  echo "<script>alert('Funcionário não encontrado.'); history.back();</script>";
  exit;
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

  <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
  <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="../../assets/css/demo.css" />
  <link rel="stylesheet" href="../../assets/css/button-responsive.css" />
  <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="../../assets/vendor/css/pages/page-auth.css" />

  <script src="../../assets/vendor/js/helpers.js"></script>
  <script src="../../assets/js/config.js"></script>
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
                  <label class="form-label">Entrada</label>
                  <input type="text" class="form-control" readonly value="<?= htmlspecialchars($horaEntradaRef) ?>">
                </div>

                <?php if (!empty($horaSaidaIntervaloRef) && !empty($horaRetornoIntervaloRef)): ?>
                  <div class="mb-3">
                    <label class="form-label">Saída para Intervalo</label>
                    <input type="text" class="form-control" readonly
                      value="<?= htmlspecialchars($horaSaidaIntervaloRef) ?>">
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Retorno do Intervalo</label>
                    <input type="text" class="form-control" readonly
                      value="<?= htmlspecialchars($horaRetornoIntervaloRef) ?>">
                  </div>
                <?php else: ?>
                  <input type="hidden" name="saida_intervalo" value="">
                  <input type="hidden" name="retorno_intervalo" value="">
                <?php endif; ?>

                <div class="mb-4">
                  <label class="form-label">Saída Final</label>
                  <input type="text" class="form-control" readonly value="<?= htmlspecialchars($horaSaidaFinalRef) ?>">
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

                <div id="mensagemSucesso" class="alert alert-success text-center mt-3" style="display:none;">
                  Imagem processada com sucesso!
                </div>

                <!-- Botões de Registro -->
                <div id="sumir" class="container mb-4" style="display: none;">
                  <div class="row justify-content-center g-2">
                    <!-- Botão de Entrada -->
                    <div class="col-12 col-md-auto text-center">
                      <button type="submit" name="acao" value="entrada" class="btn btn-primary w-100">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Registrar Entrada
                      </button>
                    </div>

                    <!-- Botão de Saída para Intervalo -->
                    <div class="col-12 col-md-auto text-center">
                      <button type="submit" name="acao" value="saida_intervalo" class="btn btn-warning w-100">
                        <i class="bi bi-arrow-right-square me-1"></i> Saída Intervalo
                      </button>
                    </div>

                    <!-- Botão de Retorno do Intervalo -->
                    <div class="col-12 col-md-auto text-center">
                      <button type="submit" name="acao" value="retorno_intervalo" class="btn btn-primary w-100">
                        <i class="bi bi-arrow-left-square me-1"></i> Retorno Intervalo
                      </button>
                    </div>

                    <!-- Botão de Saída Final -->
                    <div class="col-12 col-md-auto text-center">
                      <button type="submit" name="acao" value="saida_final" class="btn btn-warning w-100"
                        id="btnRegistrarSaida">
                        <i class="bi bi-box-arrow-left me-1"></i> Registrar Saída
                      </button>
                    </div>

                  </div>
                </div>


              </form>

              <div id="butao" style="display:block;" class="text-center mb-4">
                <button id="abrirCameraBtn" type="button" class="btn btn-primary w-100">📷 Tirar Foto</button>
              </div>

              <div class="text-center mb-3 alert alert-primary">
                <?= htmlspecialchars($mensagemFuncionario) ?>
              </div>
            <?php endif; ?>

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
  <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../../assets/vendor/libs/popper/popper.js"></script>
  <script src="../../assets/vendor/js/bootstrap.js"></script>
  <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="../../assets/vendor/js/menu.js"></script>
  <script src="../../assets/js/main.js"></script>

</body>

</html>
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

require '../../assets/php/conexao.php';
date_default_timezone_set('America/Manaus');

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

// ✅ Buscar imagem da empresa para usar como favicon
$iconeEmpresa = '../../assets/img/favicon/favicon.ico'; // Ícone padrão

try {
  $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1");
  $stmt->bindParam(':id_selecionado', $idSelecionado);
  $stmt->execute();
  $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($empresa && !empty($empresa['imagem'])) {
    $iconeEmpresa = $empresa['imagem'];
  }
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar ícone da empresa: " . addslashes($e->getMessage()) . "');</script>";
}

$usuario_id = $_SESSION['usuario_id'];
$tipoUsuarioSessao = $_SESSION['nivel'];
$cpf = '';
$nomeFuncionario = 'Desconhecido';
$tipoUsuario = 'Comum';

try {
  $stmt = $pdo->prepare(
    $tipoUsuarioSessao === 'Admin'
    ? "SELECT usuario, nivel, cpf FROM contas_acesso WHERE id = :id"
    : "SELECT usuario, nivel, cpf FROM funcionarios_acesso WHERE id = :id"
  );
  $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
  $stmt->execute();
  $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($usuario) {
    $nomeFuncionario = $usuario['usuario'];
    $tipoUsuario = ucfirst($usuario['nivel']);
    $cpf = $usuario['cpf'];
  } else {
    echo "<script>alert('Usuário não encontrado.'); window.location.href = './index.php?id=$idSelecionado';</script>";
    exit;
  }
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar usuário: " . $e->getMessage() . "');</script>";
  exit;
}

$dataAtual = date('Y-m-d');
$horaAtual = date('H:i:s');
$horaAgoraTimestamp = strtotime($horaAtual);

$diaAtualSemana = strtolower(date('l'));
$diasSemana = [
  'sunday' => 'domingo',
  'monday' => 'segunda',
  'tuesday' => 'terca',
  'wednesday' => 'quarta',
  'thursday' => 'quinta',
  'friday' => 'sexta',
  'saturday' => 'sabado'
];
$diaTraduzido = $diasSemana[$diaAtualSemana];

$exibirFormulario = false;
$mensagemTurno = '';
$horaEntradaReferencial = '';
$horaSaidaReferencial = '';
$horaEntradaTolerancia = '';
$registroPonto = null;

$stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE cpf = :cpf LIMIT 1");
$stmt->execute([':cpf' => $cpf]);
$func = $stmt->fetch(PDO::FETCH_ASSOC);

if ($func) {
  $nomeFuncionario = $func['nome'];

  $diaInicio = strtolower($func['dia_inicio']);
  $diaTermino = strtolower($func['dia_termino']);
  $diasSemanaNumerico = [
    'domingo' => 0,
    'segunda' => 1,
    'terca' => 2,
    'quarta' => 3,
    'quinta' => 4,
    'sexta' => 5,
    'sabado' => 6
  ];

  $numeroDiaAtual = $diasSemanaNumerico[$diaTraduzido];
  $numeroDiaInicio = $diasSemanaNumerico[$diaInicio];
  $numeroDiaTermino = $diasSemanaNumerico[$diaTermino];

  if (
    ($numeroDiaInicio <= $numeroDiaTermino && $numeroDiaAtual >= $numeroDiaInicio && $numeroDiaAtual <= $numeroDiaTermino) ||
    ($numeroDiaInicio > $numeroDiaTermino && ($numeroDiaAtual >= $numeroDiaInicio || $numeroDiaAtual <= $numeroDiaTermino))
  ) {
    $manhaEntrada = $func['hora_entrada_primeiro_turno'];
    $manhaSaida = $func['hora_saida_primeiro_turno'];
    $tardeEntrada = $func['hora_entrada_segundo_turno'];
    $tardeSaida = $func['hora_saida_segundo_turno'];

    $manhaInicio = $manhaEntrada ? strtotime($manhaEntrada) : null;
    $manhaFim = $manhaSaida ? strtotime($manhaSaida) : null;
    $tardeInicio = $tardeEntrada ? strtotime($tardeEntrada) : null;
    $tardeFim = $tardeSaida ? strtotime($tardeSaida) : null;

    if ($manhaInicio && $horaAgoraTimestamp >= $manhaInicio && $horaAgoraTimestamp <= $manhaFim) {
      $horaEntradaReferencial = $manhaEntrada;
      $horaSaidaReferencial = $manhaSaida;
      $exibirFormulario = true;
    } elseif ($tardeInicio && $horaAgoraTimestamp >= $tardeInicio && $horaAgoraTimestamp <= $tardeFim) {
      $horaEntradaReferencial = $tardeEntrada;
      $horaSaidaReferencial = $tardeSaida;
      $exibirFormulario = true;
    }

    if ($exibirFormulario && $horaEntradaReferencial) {
      $horaEntradaTolerancia = date('H:i:s', strtotime('+20 minutes', strtotime($horaEntradaReferencial)));
    }

    // Buscar registro de ponto do dia
    $stmt = $pdo->prepare("SELECT id, entrada, saida, status, horas_pendentes FROM registros_ponto WHERE cpf = :cpf AND data = :data LIMIT 1");
    $stmt->execute([':cpf' => $cpf, ':data' => $dataAtual]);
    $registroPonto = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($registroPonto) {
      if ($registroPonto['saida'] === NULL) {
        $mensagemTurno = "<div class='alert alert-warning text-center'>
                          Saída ainda não registrada.<br> 
                          Se você optar por continuar trabalhando além do horário previsto do turno, o tempo adicional será registrado como <strong>hora extra</strong>.
                          </div>";

      } else {
        $mensagemTurno = "<div class='alert alert-success text-center'>Ponto fechado para o dia de hoje.</div>";
        $exibirFormulario = false; // Fecha formulário se saída já registrada
      }
    } else {
      $mensagemTurno = "<div class='alert alert-warning text-center'>Nenhum ponto registrado hoje. Você pode registrar agora.</div>";
    }

  } else {
    $mensagemTurno = "<div class='alert alert-warning text-center'>Hoje não é dia de trabalho para este funcionário.</div>";
  }
} else {
  $mensagemTurno = "<div class='alert alert-danger text-center'>Funcionário não encontrado com este CPF.</div>";
}

echo "<script>console.log('Exibir Formulário: " . ($exibirFormulario ? 'Sim' : 'Não') . "');</script>";
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

            <!-- Exibição do Formulário de Registro de Ponto -->
            <?php if ($exibirFormulario): ?>
              <form id="formRegistroPonto" action="../php/sistemaPonto/registrarPonto.php" method="POST" class="mb-3">
                <input type="hidden" name="id_selecionado" value="<?= htmlspecialchars($idSelecionado) ?>">
                <input type="hidden" name="cpf" value="<?= htmlspecialchars($cpf) ?>">
                <input type="hidden" name="data" value="<?= htmlspecialchars($dataAtual) ?>">
                <input type="hidden" id="hora_atual" name="hora_atual" value="<?= htmlspecialchars($horaAtual) ?>">

                <div class="mb-3">
                  <label class="form-label">Funcionário</label>
                  <input type="text" class="form-control" readonly value="<?= htmlspecialchars($nomeFuncionario) ?>" />
                </div>

                <div class="mb-3">
                  <label class="form-label">Hora Atual</label>
                  <input type="text" id="hora" class="form-control" readonly
                    value="<?= htmlspecialchars($horaAtual) ?>" />
                </div>

                <div class="mb-3">
                  <label class="form-label">Hora de Entrada</label>
                  <input type="text" class="form-control" readonly
                    value="<?= htmlspecialchars($horaEntradaReferencial) ?>" />
                </div>

                <div class="mb-3">
                  <label class="form-label">Entrada com Tolerância</label>
                  <input type="text" class="form-control" readonly
                    value="<?= htmlspecialchars($horaEntradaTolerancia) ?>" />
                </div>

                <div class="mb-3">
                  <label class="form-label">Hora de Saída</label>
                  <input type="text" id="hora_saida" class="form-control" readonly
                    value="<?= htmlspecialchars($horaSaidaReferencial) ?>" />
                </div>

                <div class="mb-3 text-center">
                  <button type="submit" name="acao" value="entrada" class="btn btn-primary">Registrar Entrada</button>

                  <?php if ($registroPonto && $registroPonto['saida'] === NULL): ?>
                    <button type="submit" name="acao" value="saida" class="btn btn-warning" id="btnRegistrarSaida">Registrar
                      Saída</button>
                  <?php else: ?>
                    <button type="submit" class="btn btn-warning" id="btnRegistrarSaida" disabled>Registrar Saída</button>
                  <?php endif; ?>
                </div>
              </form>
            <?php endif; ?>

            <?= $mensagemTurno ?>

            <div class="text-center">
              <a href="./pontoRegistrado.php?id=<?= htmlspecialchars($idSelecionado) ?>"
                class="d-flex align-items-center justify-content-center">
                <i class="bx bx-chevron-left scaleX-n1-rtl bx-sm"></i>
                Voltar
              </a>
            </div>
          </div>
          <!-- Fim do Card -->
        </div>
      </div>
    </div>

    <!-- Scripts -->
    <script src="../../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../../assets/vendor/js/menu.js"></script>
    <script src="../../../assets/js/main.js"></script>

</body>

</html>
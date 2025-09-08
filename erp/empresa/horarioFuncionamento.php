<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// ✅ Recupera o identificador vindo da URL
$idSelecionado = $_GET['id'] ?? '';

if (!$idSelecionado) {
  header("Location: .././login.php");
  exit;
}

// ✅ Verifica se a pessoa está logada
if (
  !isset($_SESSION['usuario_logado']) ||
  !isset($_SESSION['empresa_id']) ||
  !isset($_SESSION['tipo_empresa']) ||
  !isset($_SESSION['usuario_id'])
) {
  header("Location: .././login.php?id=" . urlencode($idSelecionado));
  exit;
}

// ✅ Conexão com o banco de dados
require '../../assets/php/conexao.php';

// ✅ Buscar nome e tipo do usuário logado
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id = $_SESSION['usuario_id'];

try {
  $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
  $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
  $stmt->execute();
  $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($usuario) {
    $nomeUsuario = $usuario['usuario'];
    $tipoUsuario = ucfirst($usuario['nivel']);
  } else {
    echo "<script>alert('Usuário não encontrado.'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
    exit;
  }
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar usuário: " . $e->getMessage() . "'); history.back();</script>";
  exit;
}

// ✅ Valida o tipo de empresa e o acesso permitido
$acessoPermitido = false;
$idEmpresaSession = $_SESSION['empresa_id'];
$tipoSession = $_SESSION['tipo_empresa'];

if (str_starts_with($idSelecionado, 'principal_')) {
  $acessoPermitido = ($tipoSession === 'principal' && $idEmpresaSession === 'principal_1');
} elseif (str_starts_with($idSelecionado, 'filial_')) {
  $acessoPermitido = ($tipoSession === 'filial' && $idEmpresaSession === $idSelecionado);
} elseif (str_starts_with($idSelecionado, 'unidade_')) {
  $acessoPermitido = ($tipoSession === 'unidade' && $idEmpresaSession === $idSelecionado);
} elseif (str_starts_with($idSelecionado, 'franquia_')) {
  $acessoPermitido = ($tipoSession === 'franquia' && $idEmpresaSession === $idSelecionado);
}

if (!$acessoPermitido) {
  echo "<script>
          alert('Acesso negado!');
          window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';
        </script>";
  exit;
}

// ✅ Buscar logo da empresa
try {
  $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1");
  $stmt->bindParam(':id_selecionado', $idSelecionado, PDO::PARAM_STR);
  $stmt->execute();
  $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);

  $logoEmpresa = !empty($empresaSobre['imagem'])
    ? "../../assets/img/empresa/" . $empresaSobre['imagem']
    : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
  $logoEmpresa = "../../assets/img/favicon/logo.png"; // fallback
}

// ✅ Buscar horários de funcionamento com base no ID da empresa
$query = $pdo->prepare("SELECT * FROM horarios_funcionamento WHERE empresa_id = :empresa_id");
$query->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR); // Usa o idSelecionado diretamente
$query->execute();
$horarios = $query->fetchAll(PDO::FETCH_ASSOC);

$dias_da_semana = [
    "segunda" => "Segunda-feira",
    "terca" => "Terça-feira",
    "quarta" => "Quarta-feira",
    "quinta" => "Quinta-feira",
    "sexta" => "Sexta-feira",
    "sabado" => "Sábado",
    "domingo" => "Domingo"
];

?>

<!DOCTYPE html>
<html
    lang="en"
    class="light-style layout-menu-fixed"
    dir="ltr"
    data-theme="theme-default"
    data-assets-path="../assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>ERP - Empresa</title>

    <meta name="description" content="" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($logoEmpresa) ?>" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
        rel="stylesheet" />

    <!-- Icons. Uncomment required icon fonts -->
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

    <!-- Core CSS -->
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />

    <!-- Page CSS -->

    <!-- Helpers -->
    <script src="../../assets/vendor/js/helpers.js"></script>

    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="../../assets/js/config.js"></script>
</head>

<body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">

            <!-- Menu -->
            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="app-brand-link">

                        <span class="app-brand-text demo menu-text fw-bolder ms-2">Açaínhadinhos</span>
                    </a>

                    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
                        <i class="bx bx-chevron-left bx-sm align-middle"></i>
                    </a>
                </div>

                <div class="menu-inner-shadow"></div>

                <ul class="menu-inner py-1">
                    <!-- Dashboard -->
                    <li class="menu-item">
                        <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Dashboard</div>
                        </a>
                    </li>

                    <!-- Informações da empresa -->
                    <li class="menu-header small text-uppercase">
                        <span class="menu-header-text">Empresa</span>
                    </li>

                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-building"></i>
                            <div data-i18n="Adicionar">Empresa</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./sobreEmpresa.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="empresa">Sobre</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./enderecoEmpresa.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="empresa">Endereço</div>
                                </a>
                            </li>
                            <li class="menu-item active">
                                <a href="./horarioFuncionamento.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="empresa">Horario</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./formaPagamento.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="empresa">Formas de Pagamento</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!--END DELIVERY-->

                    <!-- Misc -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
                    <li class="menu-item">
                        <a href="../rh/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-group"></i>
                            <div data-i18n="Authentications">RH</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../financas/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-dollar"></i>
                            <div data-i18n="Authentications">Finanças</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../pdv/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-desktop"></i>
                            <div data-i18n="Authentications">PDV</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../delivery/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-cart"></i>
                            <div data-i18n="Authentications">Delivery</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-box"></i>
                            <div data-i18n="Authentications">Estoque</div>
                        </a>
                    </li>
                    <?php
                    $tipoLogado = $_SESSION['tipo_empresa'] ?? '';
                    $idLogado = $_SESSION['empresa_id'] ?? '';

                    // Se for matriz (principal), mostrar links para filial, franquia e unidade
                    if ($tipoLogado === 'principal') {
                    ?>
                        <li class="menu-item">
                            <a href="../filial/index.php?id=principal_1" class="menu-link">
                                <i class="menu-icon tf-icons bx bx-building"></i>
                                <div data-i18n="Authentications">Filial</div>
                            </a>
                        </li>
                        <li class="menu-item">
                            <a href="../franquia/index.php?id=principal_1" class="menu-link">
                                <i class="menu-icon tf-icons bx bx-store"></i>
                                <div data-i18n="Authentications">Franquias</div>
                            </a>
                        </li>
                    <?php
                    } elseif (in_array($tipoLogado, ['filial', 'franquia', 'unidade'])) {
                        // Se for filial, franquia ou unidade, mostra link para matriz
                    ?>
                        <li class="menu-item">
                            <a href="./matriz/index.php?id=<?= urlencode($idLogado) ?>" class="menu-link">
                                <i class="menu-icon tf-icons bx bx-cog"></i>
                                <div data-i18n="Authentications">Matriz</div>
                            </a>
                        </li>
                    <?php
                    }
                    ?>
                    <li class="menu-item">
                        <a href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-group"></i>
                            <div data-i18n="Authentications">Usuários </div>
                        </a>
                    </li>
                    <li class="menu-item mb-5">
                        <a href="https://wa.me/92991515710" target="_blank" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-support"></i>
                            <div data-i18n="Basic">Suporte</div>
                        </a>
                    </li>
                    <!--/MISC-->
                </ul>
            </aside>
            <!-- / Menu -->

            <!-- Layout container -->
            <div class="layout-page">
                <!-- Navbar -->

                <nav
                    class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
                    id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                            <i class="bx bx-menu bx-sm"></i>
                        </a>
                    </div>

                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <!-- Search -->

                        <!-- /Search -->

                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <!-- Place this tag where you want the button to render. -->
                            <!-- User -->
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);"
                                    data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <img src="<?= htmlspecialchars($logoEmpresa) ?>" alt
                                            class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="<?= htmlspecialchars($logoEmpresa) ?>" alt
                                                            class="w-px-40 h-auto rounded-circle" />
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <span class="fw-semibold d-block"><?= htmlspecialchars($nomeUsuario); ?></span>
                                                    <small class="text-muted"><?= htmlspecialchars($tipoUsuario); ?></small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                    <li>
                                        <div class="dropdown-divider"></div>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <i class="bx bx-user me-2"></i>
                                            <span class="align-middle">Minha Conta</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <i class="bx bx-cog me-2"></i>
                                            <span class="align-middle">Configurações</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <span class="d-flex align-items-center align-middle">
                                                <i class="flex-shrink-0 bx bx-credit-card me-2"></i>
                                                <span class="flex-grow-1 align-middle">Billing</span>
                                                <span
                                                    class="flex-shrink-0 badge badge-center rounded-pill bg-danger w-px-20 h-px-20">4</span>
                                            </span>
                                        </a>
                                    </li>
                                    <li>
                                        <div class="dropdown-divider"></div>
                                    </li>
                                    <li>
                                        <a class="dropdown-item"
                                            href="../logout.php?id=<?= urlencode($idSelecionado); ?>">
                                            <i class="bx bx-power-off me-2"></i>
                                            <span class="align-middle">Sair</span>
                                        </a>
                                    </li>
                                </ul>
                            </li>
                            <!--/ User -->
                        </ul>

                    </div>
                </nav>
                <!-- / Navbar -->

                <!-- container-xxl -->
                <div class="container-xxl flex-grow-1 container-p-y">

                    <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a href="#">Empresa</a>/</span>Horário de Funcionamento</h4>
                    <h5 class="fw-bold mt-3 mb-3 custor-font"><span class="text-muted fw-light">Configure o horário de funcionamento da empresa</span></h5>

                    <!-- Formulário -->
                    <form method="POST" action="../../assets/php/empresa/adicionarHorarioFuncionamento.php" id="horarioForm">
                        <!-- Envia o idSelecionado como campo oculto -->
                        <input type="hidden" name="idSelecionado" value="<?php echo htmlspecialchars($idSelecionado); ?>">

                        <div id="formulariosContainer">
                            <?php foreach ($horarios as $horario) { ?>
                                <div class="card mt-3 horario-salvo" data-id="<?php echo $horario['id']; ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h5 class="card-title">Horário Salvo</h5>
                                            <button type="button" class="btn btn-danger btn-sm remove-db-form" data-bs-toggle="modal" data-bs-target="#modalExcluir_<?php echo $horario['id']; ?>">
                                                <i class="bx bx-trash"></i>
                                            </button>
                                        </div>

                                        <input type="hidden" name="id[]" value="<?php echo $horario['id']; ?>">

                                        <div class="row g-3">
                                            <div class="col-12 col-md-2">
                                                <label class="form-label">De</label>
                                                <select class="form-control" name="dias_de[]">
                                                    <option value="">Selecione um dia</option>
                                                    <?php foreach ($dias_da_semana as $valor => $nome) { ?>
                                                        <option value="<?php echo $valor; ?>" <?php echo ($horario['dia_de'] == $valor) ? 'selected' : ''; ?>>
                                                            <?php echo $nome; ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                            <div class="col-12 col-md-2">
                                                <label class="form-label">Até</label>
                                                <select class="form-control" name="dias_ate[]">
                                                    <option value="">Selecione um dia</option>
                                                    <?php foreach ($dias_da_semana as $valor => $nome) { ?>
                                                        <option value="<?php echo $valor; ?>" <?php echo ($horario['dia_ate'] == $valor) ? 'selected' : ''; ?>>
                                                            <?php echo $nome; ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                            <div class="col-12 col-md-2">
                                                <label class="form-label">Das</label>
                                                <input type="time" class="form-control" name="horario_primeira_hora[]" value="<?php echo $horario['primeira_hora']; ?>">
                                            </div>
                                            <div class="col-12 col-md-2">
                                                <label class="form-label">Até as</label>
                                                <input type="time" class="form-control" name="horario_termino_primeiro_turno[]" value="<?php echo $horario['termino_primeiro_turno']; ?>">
                                            </div>
                                            <div class="col-12 col-md-2">
                                                <label class="form-label">E das</label>
                                                <input type="time" class="form-control" name="horario_comeco_segundo_turno[]" value="<?php echo $horario['comeco_segundo_turno']; ?>">
                                            </div>
                                            <div class="col-12 col-md-2">
                                                <label class="form-label">Até as</label>
                                                <input type="time" class="form-control" name="horario_termino_segundo_turno[]" value="<?php echo $horario['termino_segundo_turno']; ?>">
                                            </div>

                                            <!-- Modal de Exclusão -->
                                            <div class="modal fade" id="modalExcluir_<?php echo $horario['id']; ?>" tabindex="-1" aria-labelledby="modalExcluirLabel" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="modalExcluirLabel">Excluir Horário</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Tem certeza de que deseja excluir este horário?</p>
                                                            <!-- Adicionando o idSelecionado na URL -->
                                                            <a href="../../assets/php/delivery/excluirHorarioFuncionamento.php?id=<?php echo $horario['id']; ?>&idSelecionado=<?php echo htmlspecialchars($idSelecionado); ?>" class="btn btn-danger">Sim, excluir</a>
                                                            <button type="button" class="btn btn-secondary mx-2" data-bs-dismiss="modal">Cancelar</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- /Modal -->

                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>

                        <!-- Botão de salvar sempre visível -->
                        <div id="salvarBtn" class="col-md-12 mt-4 text-center" style="display: <?php echo count($horarios) > 0 || isset($_POST['dias_de']) ? 'block' : 'none'; ?>">
                            <button type="submit" class="btn btn-primary col-md-12">
                                <i class="bx bx-check"></i> Salvar Horário
                            </button>
                        </div>

                    </form>
                    <!-- /Formulário -->

                    <!-- Botão para adicionar novo horário -->
                    <div id="addHorario" class="mt-4 add-category justify-content-center d-flex text-center align-items-center">
                        <i class="tf-icons bx bx-plus me-2"></i>
                        <span>Adicionar novo horário</span>
                    </div>

                    <script>
                        document.getElementById('addHorario').addEventListener('click', function() {
                            let container = document.getElementById('formulariosContainer');
                            let salvarBtn = document.getElementById('salvarBtn');

                            // Adiciona novo formulário de horário
                            let formHtml = `
                                <div class="card mt-3 novo-horario">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h5 class="card-title">Novo Horário</h5>
                                            <button type="button" class="btn btn-danger btn-sm remove-form">
                                                <i class="bx bx-trash"></i>
                                            </button>
                                        </div>

                                        <div class="row g-3">
                                            <div class="col-12 col-md-2">
                                                <label class="form-label">De</label>
                                                <select class="form-control" name="dias_de[]">
                                                    <option value="">Selecione um dia</option>
                                                    <option value="segunda">Segunda-feira</option>
                                                    <option value="terca">Terça-feira</option>
                                                    <option value="quarta">Quarta-feira</option>
                                                    <option value="quinta">Quinta-feira</option>
                                                    <option value="sexta">Sexta-feira</option>
                                                    <option value="sabado">Sábado</option>
                                                    <option value="domingo">Domingo</option>
                                                </select>
                                            </div>
                                            <div class="col-12 col-md-2">
                                                <label class="form-label">Até</label>
                                                <select class="form-control" name="dias_ate[]">
                                                    <option value="">Selecione um dia</option>
                                                    <option value="segunda">Segunda-feira</option>
                                                    <option value="terca">Terça-feira</option>
                                                    <option value="quarta">Quarta-feira</option>
                                                    <option value="quinta">Quinta-feira</option>
                                                    <option value="sexta">Sexta-feira</option>
                                                    <option value="sabado">Sábado</option>
                                                    <option value="domingo">Domingo</option>
                                                </select>
                                            </div>
                                            <div class="col-12 col-md-2">
                                                <label class="form-label">Das</label>
                                                <input type="time" class="form-control" name="horario_primeira_hora[]">
                                            </div>
                                            <div class="col-12 col-md-2">
                                                <label class="form-label">Até as</label>
                                                <input type="time" class="form-control" name="horario_termino_primeiro_turno[]">
                                            </div>
                                            <div class="col-12 col-md-2">
                                                <label class="form-label">E das</label>
                                                <input type="time" class="form-control" name="horario_comeco_segundo_turno[]">
                                            </div>
                                            <div class="col-12 col-md-2">
                                                <label class="form-label">Até as</label>
                                                <input type="time" class="form-control" name="horario_termino_segundo_turno[]">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;

                            container.insertAdjacentHTML('beforeend', formHtml);

                            // Mostra o botão de salvar se houver um horário novo
                            salvarBtn.style.display = 'block';
                        });

                        // Remover formulário de horário
                        document.addEventListener('click', function(event) {
                            if (event.target.closest('.remove-form')) {
                                event.target.closest('.novo-horario').remove();

                                // Se não houver mais horários, esconder o botão de salvar
                                if (document.querySelectorAll('.novo-horario').length === 0) {
                                    document.getElementById('salvarBtn').style.display = 'none';
                                }
                            }
                        });
                    </script>

                </div>
                <!-- /container-xxl -->

            </div>
        </div>
    </div>

    <!-- Core JS -->
    <!-- build:js assets/vendor/js/core.js -->
    <script src="../../assets/js/delivery/taxaEntrega.js"></script>
    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>

    <script src="../../assets/vendor/js/menu.js"></script>
    <!-- endbuild -->

    <!-- Vendors JS -->
    <script src="../../assets/vendor/libs/apex-charts/apexcharts.js"></script>

    <!-- Main JS -->
    <script src="../../assets/js/main.js"></script>

    <!-- Page JS -->
    <script src="../../assets/js/dashboards-analytics.js"></script>

    <!-- Place this tag in your head or just before your close body tag. -->
    <script async defer src="https://buttons.github.io/buttons.js"></script>
</body>

</html>
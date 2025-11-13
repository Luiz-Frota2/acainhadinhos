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


// ✅ Se chegou até aqui, o acesso está liberado

// ✅ Buscar nome e nível do usuário logado
$nomeUsuario = 'Usuário';
$nivelUsuario = 'Comum'; // Valor padrão
$usuario_id = $_SESSION['usuario_id'];

try {
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $nomeUsuario = $usuario['usuario'];
        $nivelUsuario = $usuario['nivel'];
    }
} catch (PDOException $e) {
    $nomeUsuario = 'Erro ao carregar nome';
    $nivelUsuario = 'Erro ao carregar nível';
}

// ✅ Buscar imagem da tabela sobre_empresa com base no idSelecionado
try {
    $sql = "SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_selecionado', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);

    $logoEmpresa = !empty($empresaSobre['imagem'])
        ? "../../assets/img/empresa/" . $empresaSobre['imagem']
        : "../../assets/img/favicon/logo.png"; // fallback padrão
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png"; // fallback em caso de erro
}

// ✅ Consultar dados de 'configuracoes_retirada' e 'entregas' com base no idSelecionado
$retirada = 0;
$tempoMin = 0;
$tempoMax = 0;
$entrega = 0;
$tempoMinEntrega = 0;
$tempoMaxEntrega = 0;
$idEntrega = 0;

try {
    // Consulta para 'configuracoes_retirada'
    $sqlConfig = "SELECT * FROM configuracoes_retirada WHERE id_empresa = :id_empresa LIMIT 1";
    $stmtConfig = $pdo->prepare($sqlConfig);
    $stmtConfig->bindParam(':id_empresa', $idSelecionado, PDO::PARAM_STR); // Usando idSelecionado
    $stmtConfig->execute();
    $resultConfig = $stmtConfig->fetch(PDO::FETCH_ASSOC);

    if ($resultConfig) {
        $retirada = $resultConfig['retirada'];
        $tempoMin = $resultConfig['tempo_min'];
        $tempoMax = $resultConfig['tempo_max'];
    }

    // Consulta para 'entregas'
    $sqlEntrega = "SELECT * FROM entregas WHERE id_empresa = :id_empresa LIMIT 1";
    $stmtEntrega = $pdo->prepare($sqlEntrega);
    $stmtEntrega->bindParam(':id_empresa', $idSelecionado, PDO::PARAM_STR);
    $stmtEntrega->execute();
    $resultEntrega = $stmtEntrega->fetch(PDO::FETCH_ASSOC);

    if ($resultEntrega) {
        $idEntrega = $resultEntrega['id_entrega'];
        $entrega = $resultEntrega['entrega'];
        $tempoMinEntrega = $resultEntrega['tempo_min'];
        $tempoMaxEntrega = $resultEntrega['tempo_max'];
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar as configurações: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
    data-assets-path="../assets/" data-template="vertical-menu-template-free">

<head>

    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>ERP - Delivery</title>

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

        <!-- Layout container -->
        <div class="layout-container">

            <!-- Menu -->
            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">

                <div class="app-brand demo">
                    <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="app-brand-link">
                        <span class="app-brand-text demo menu-text fw-bolder ms-2"
                            style=" text-transform: capitalize;">Açaínhadinhos</span>

                    </a>

                    <a href="javascript:void(0);"
                        class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
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

                    <!--DELIVERY-->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Delivery</span></li>
                    <li class="menu-item">
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons tf-icons bx bx-food-menu"></i>
                            <div data-i18n="Authentications">Cardápio</div>
                        </a>
                        <ul class="menu-sub">

                            <li class="menu-item ">
                                <a href="./produtoAdicionados.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div data-i18n="Basic">Produtos Adicionados</div>
                                </a>
                            </li>

                        </ul>

                    </li>
                    <li class="menu-item active open">

                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons tf-icons bx bx-cog"></i>
                            <div data-i18n="Authentications">Configuração</div>
                        </a>

                        <ul class="menu-sub">
                            <li class="menu-item active">
                                <a href="./deliveryRetirada.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Delivery e Retirada</div>
                                </a>
                            </li>
                        </ul>

                    </li>

                    <li class="menu-item">

                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons tf-icons bx bx-trending-up"></i>
                            <div data-i18n="Authentications">Relatorios</div>
                        </a>

                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./listarPedidos.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Lista de Pedidos</div>
                                </a>
                            </li>
                        </ul>

                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./maisVendidos.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Mais vendidos</div>
                                </a>
                            </li>
                        </ul>

                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./relatorioVendas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Vendas</div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <!--END DELIVERY-->


                    <!-- Misc -->
                    <li class="menu-header small text-uppercase">
                        <span class="menu-header-text">Diversos</span>
                    </li>

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
                        <a href="../empresa/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div data-i18n="Authentications">Empresa</div>
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
                            <a href="../matriz/index.php?id=<?= urlencode($idLogado) ?>" class="menu-link">
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
                    <li class="menu-item">
                        <a href="https://wa.me/92991515710" target="_blank" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-support"></i>
                            <div data-i18n="Basic">Suporte</div>
                        </a>
                    </li>
                    <!--/MISC-->

            </aside>
            <!-- / Menu -->

            <!-- Layout page -->
            <div class="layout-page">

                <!-- Navbar -->
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
                    id="layout-navbar">

                    <!-- layout menu -->
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                            <i class="bx bx-menu bx-sm"></i>
                        </a>
                    </div>
                    <!-- /layout menu -->

                    <!-- navbar nav right -->
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
                                                    <span class="fw-semibold d-block"><?php echo $nomeUsuario; ?></span>
                                                    <small class="text-muted"><?php echo $nivelUsuario; ?></small>
                                                </div>
                                            </div>
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
                    <!-- /navbar nav right -->

                </nav>
                <!-- / Navbar -->

                <!-- container xxl -->
                <div class="container-xxl flex-grow-1 container-p-y">

                    <h4 class="fw-bold  mb-0"><span class="text-muted fw-light"><a
                                href="./deliveryRetirada.php?id=<?= urlencode($idSelecionado); ?>">Configuração</a>/</span>Delivery
                        e Retirada</h4>

                    <h5 class="fw-bold mt-3 mb-3 custor-font"><span class="text-muted fw-light">Selecione as opções de
                            taxas de entrega</span></h5>

                    <div class="row">

                        <div class="col-12">

                            <!-- Formulário para Retirada -->
                            <form action="../../assets/php/delivery/adicionarRetirada.php" method="POST"
                                id="retiradaForm">

                                <!-- Campo oculto para enviar o idSelecionado -->
                                <input type="hidden" name="id_selecionado"
                                    value="<?php echo htmlspecialchars($idSelecionado); ?>">

                                <!-- Card Retirada -->
                                <div class="card mb-4">
                                    <!-- card body -->
                                    <div class="card-body">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-12 col-md-12 text-md-start">
                                                <div class="col-12 col-md-12 check-card">
                                                    <div class="d-flex align-items-center">
                                                        <div>
                                                            <strong>Retirada</strong>
                                                        </div>

                                                        <div class="d-flex align-items-center ms-auto">
                                                            <strong for="toggleRetirada" id="labelToggle" class="me-2">
                                                                <?php echo $retirada ? 'Ligado' : 'Desligado'; ?>
                                                            </strong>

                                                            <div class="form-check form-switch mt-2">
                                                                <input class="form-check-input check w-px-50 h-px-20"
                                                                    type="checkbox" id="toggleRetirada" name="retirada"
                                                                    value="1" <?php echo $retirada ? 'checked' : ''; ?>
                                                                    onchange="submitForm()">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Condicionalmente exibe este bloco se 'retirada' for 1 -->
                                            <div
                                                class="col-md-12 mt-3 mt-md-0 d-flex flex-wrap inputemp gap-2 <?php echo $retirada == 1 ? '' : 'hidden'; ?>">

                                                <div class="flex-fill">
                                                    <label class="form-label">Tempo mínimo retirada (min)</label>
                                                    <input type="number" class="form-control" name="tempo_min"
                                                        value="<?php echo htmlspecialchars($tempoMin); ?>" required>
                                                </div>

                                                <div class="flex-fill">
                                                    <label class="form-label">Tempo máximo retirada (min)</label>
                                                    <input type="number" class="form-control" name="tempo_max"
                                                        value="<?php echo htmlspecialchars($tempoMax); ?>" required>
                                                </div>

                                                <div class="text-center btn-container ms-3 mt-1 text-md-end">
                                                    <button type="submit" class="mt-4 btn btn-primary"><i
                                                            class="bx bx-check"></i> Salvar</button>
                                                </div>

                                            </div>

                                        </div>

                                    </div>
                                    <!-- /card body -->

                                </div>
                                <!-- /Card Retirada -->

                            </form>
                            <!-- /Formulário para Retirada -->

                            <script>
                                // Função para submeter o formulário automaticamente ao clicar no checkbox
                                function submitForm() {
                                    document.getElementById('retiradaForm').submit();
                                }
                            </script>


                            <!-- Formulário para Entrega -->
                            <form action="../../assets/php/delivery/adicionarEntrega.php" method="POST"
                                id="entregaForm">
                                <input type="hidden" name="id_selecionado"
                                    value="<?php echo htmlspecialchars($idSelecionado); ?>">

                                <div class="card mb-4">
                                    <div class="card-body">

                                        <!-- Cabeçalho com toggle -->
                                        <div class="col-12 col-md-12 text-md-start">
                                            <div class="d-flex align-items-center check-card mb-4">
                                                <div><strong>Entrega</strong></div>

                                                <div class="d-flex align-items-center ms-auto">
                                                    <strong id="labelToggleEntrega" class="me-2">
                                                        <?php echo $entrega ? 'Ligado' : 'Desligado'; ?>
                                                    </strong>

                                                    <div class="form-check form-switch mt-2">
                                                        <input type="hidden" name="entrega" value="0">
                                                        <input class="form-check-input check w-px-50 h-px-20"
                                                            type="checkbox" id="toggleEntrega" name="entrega" value="1"
                                                            <?php echo $entrega ? 'checked' : ''; ?>
                                                            onchange="handleToggleEntrega();">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Campos de tempo -->
                                        <div
                                            class="mt-3 mb-4 inputemp d-flex flex-wrap gap-2 <?php echo $entrega ? '' : 'hidden'; ?>">
                                            <div class="flex-fill">
                                                <label class="form-label">Tempo mínimo entrega (min)</label>
                                                <input type="number" class="form-control" name="tempo_min"
                                                    value="<?php echo $tempoMinEntrega; ?>" required>
                                            </div>

                                            <div class="flex-fill">
                                                <label class="form-label">Tempo máximo entrega (min)</label>
                                                <input type="number" class="form-control" name="tempo_max"
                                                    value="<?php echo $tempoMaxEntrega; ?>" required>
                                            </div>

                                            <div class="text-center btn-container ms-3 mt-1 text-md-end">
                                                <button type="submit" class="mt-4 btn btn-primary">
                                                    <i class="bx bx-check"></i> Salvar
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Link para taxa -->
                                        <div class="col-12 mt-4 taxa <?php echo $entrega ? '' : 'hidden'; ?>">
                                            <a
                                                href="./taxaEntrega.php?id=<?php echo $idSelecionado; ?>&idEntrega=<?php echo $idEntrega; ?>">
                                                <div class="add-item mb-2">
                                                    <i class="bx bx-cog"></i> Configurações da taxa de entrega
                                                </div>
                                            </a>

                                        </div>
                                    </div>
                                </div>
                            </form>

                            <script>
                                function handleToggleEntrega() {
                                    var entregaCheckbox = document.getElementById('toggleEntrega');
                                    var form = document.getElementById('entregaForm');

                                    if (entregaCheckbox.checked) {
                                        var retiradaInput = document.querySelector('input[name="retirada"]');
                                        if (retiradaInput) retiradaInput.value = 0;
                                    }

                                    form.submit();
                                }
                            </script>

                        </div>

                    </div>

                </div>
                <!-- container xxl -->

            </div>
            <!-- Layout page -->

        </div>
        <!-- /Layout container -->

    </div>
    <!-- /Layout wrapper -->

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
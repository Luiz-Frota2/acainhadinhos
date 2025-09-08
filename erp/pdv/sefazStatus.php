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

/* =================== Helpers =================== */
function onlyDigits(string $s): string
{
    return preg_replace('/\D+/', '', $s);
}
function pad6(string $n): string
{
    $n = onlyDigits($n);
    return str_pad($n === '' ? '0' : $n, 6, '0', STR_PAD_LEFT);
}
function absPath(string $path): string
{
    if (preg_match('~^([A-Za-z]:\\\\|/)~', $path)) return $path; // já absoluto
    $full = rtrim(__DIR__, "/\\") . DIRECTORY_SEPARATOR . ltrim($path, "/\\");
    return realpath($full) ?: $full;
}
function acharPfxMaisRecente(string $pasta): ?string
{
    $dir = absPath($pasta);
    if (!is_dir($dir)) return null;
    $files = array_merge(glob($dir . '/*.pfx') ?: [], glob($dir . '/*.PFX') ?: []);
    if (empty($files)) return null;
    usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
    return $files[0];
}

/* =================== 1) Carrega CONFIG do arquivo =================== */
/* Vamos buscar em ../../frentedeloja/caixa/config.php (prioridade) */
$cfgArq = [
    'tpAmb' => null,
    'cnpj' => null,
    'razao_social' => null,
    'CSC' => null,
    'CSCid' => null,
    'pfx_path' => null,
    'pfx_password' => null,
];
$cfgFilePath = absPath('./config.php');
if (is_file($cfgFilePath)) {
    // inclui “silenciosamente”: pode declarar constantes (EMIT_*, TP_AMB, CSC, ID_TOKEN, PFX_PATH etc.)
    @include $cfgFilePath;

    // tenta capturar pelos nomes usuais de constantes do seu emissor
    $cfgArq['tpAmb']        = defined('TP_AMB')       ? (int)TP_AMB : null;
    $cfgArq['cnpj']         = defined('EMIT_CNPJ')    ? EMIT_CNPJ   : null;
    $cfgArq['razao_social'] = defined('EMIT_XNOME')   ? EMIT_XNOME  : null;
    $cfgArq['CSC']          = defined('CSC')          ? CSC         : null;
    $cfgArq['CSCid']        = defined('ID_TOKEN')     ? ID_TOKEN    : null;

    // certificado pode vir como caminho completo (PFX_PATH) ou só nome
    if (defined('PFX_PATH') && PFX_PATH) {
        $cfgArq['pfx_path'] = PFX_PATH;
    }
    $cfgArq['pfx_password'] = defined('PFX_PASSWORD') ? PFX_PASSWORD : null;
}

/* =================== 2) Carrega CONFIG do banco (fallback/complemento) =================== */
try {
    $stmt = $pdo->prepare("SELECT * FROM integracao_nfce WHERE empresa_id = :empresa_id LIMIT 1");
    $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $cfgDb = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $cfgDb = [];
}

/* =================== 3) Une dados (arquivo tem prioridade) =================== */
$tpAmb   = $cfgArq['tpAmb']        ?? (isset($cfgDb['ambiente']) ? (int)$cfgDb['ambiente'] : null);
$cnpj    = $cfgArq['cnpj']         ?? ($cfgDb['cnpj'] ?? null);
$razao   = $cfgArq['razao_social'] ?? ($cfgDb['razao_social'] ?? null);
$csc     = $cfgArq['CSC']          ?? ($cfgDb['csc'] ?? null);
$idToken = $cfgArq['CSCid']        ?? ($cfgDb['id_token'] ?? null);

// pasta padrão do certificado (NOVO diretório)
$pastaCert = '../../assets/img/certificado/';

// origem preferida do certificado: arquivo config → DB (nome) → mais recente na pasta
$pfxPath   = $cfgArq['pfx_path'];
$pfxPass   = $cfgArq['pfx_password'] ?? ($cfgDb['senha_certificado'] ?? null);

if (!$pfxPath || !is_file(absPath($pfxPath))) {
    // se banco tem nome do arquivo, monta caminho na pasta nova
    if (!empty($cfgDb['certificado_digital'])) {
        $possivel = absPath($pastaCert . '/' . basename($cfgDb['certificado_digital']));
        if (is_file($possivel)) $pfxPath = $possivel;
    }
}
if (!$pfxPath || !is_file(absPath($pfxPath))) {
    // procura o .pfx mais recente na pasta
    $maisNovo = acharPfxMaisRecente($pastaCert);
    if ($maisNovo) $pfxPath = $maisNovo;
}

/* Normalizações para exibir bonitinhas */
$cnpjExibe    = $cnpj ? onlyDigits($cnpj) : '';
$idTokenExibe = $idToken !== null ? str_pad(onlyDigits((string)$idToken), 6, '0', STR_PAD_LEFT) : '--';

/* =================== Monta status para os cards =================== */
$configNFCe = [
    'cnpj'         => $cnpjExibe ?: '--',
    'razao_social' => $razao     ?: '--',
    'ambiente'     => $tpAmb     ?? null,
];

if ($tpAmb === 1) {
    $ambienteStatus = 'Produção';
    $ambienteClass  = 'bg-label-primary';
} elseif ($tpAmb === 2) {
    $ambienteStatus = 'Homologação';
    $ambienteClass  = 'bg-label-info';
} else {
    $ambienteStatus = 'Não configurado';
    $ambienteClass  = 'bg-label-secondary';
}

/* Certificado */
$certificadoStatus = 'Não configurado';
$certificadoClass  = 'bg-label-warning';

if ($pfxPath) {
    $pfxAbs = absPath($pfxPath);
    if (is_file($pfxAbs)) {
        // tenta abrir PKCS#12 p/ validar senha e existência
        if ($pfxPass !== null && $pfxPass !== '') {
            $bin = @file_get_contents($pfxAbs);
            $ok  = false;
            if ($bin !== false) {
                $ok = @openssl_pkcs12_read($bin, $dummy, (string)$pfxPass);
            }
            if ($ok) {
                $certificadoStatus = 'Válido';
                $certificadoClass  = 'bg-label-success';
            } else {
                $certificadoStatus = 'Arquivo encontrado (senha inválida?)';
                $certificadoClass  = 'bg-label-danger';
            }
        } else {
            $certificadoStatus = 'Arquivo encontrado (sem senha)';
            $certificadoClass  = 'bg-label-warning';
        }
    } else {
        $certificadoStatus = 'Arquivo não encontrado';
        $certificadoClass  = 'bg-label-danger';
    }
} else {
    $certificadoStatus = 'Não informado';
    $certificadoClass  = 'bg-label-secondary';
}

/* =================== HTML dos cards =================== */
?>

<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
    data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>ERP - PDV</title>

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

                    <!-- DASHBOARD -->
                    <li class="menu-item">
                        <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Dashboard</div>
                        </a>
                    </li>

                    <!-- SEÇÃO ADMINISTRATIVO -->
                    <li class="menu-header small text-uppercase">
                        <span class="menu-header-text">PDV</span>
                    </li>

                    <!-- SUBMENU: SEFAZ -->
                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-file"></i>
                            <div data-i18n="Authentications">SEFAZ</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./adicionarNFCe.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">NFC-e</div>
                                </a>
                            </li>
                            <li class="menu-item active">
                                <a href="./sefazStatus.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Status</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./sefazConsulta.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Consulta</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- SUBMENU: CAIXA -->
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-user"></i>
                            <div data-i18n="Authentications">Caixas</div>
                        </a>
                        <ul class="menu-sub">
                            <!-- Caixa Aberto: Visualização de caixas abertos -->
                            <li class="menu-item">
                                <a href="./caixasAberto.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Caixas Aberto</div>
                                </a>
                            </li>
                            <!-- Caixa Fechado: Histórico ou controle de caixas encerrados -->
                            <li class="menu-item">
                                <a href="./caixasFechado.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Caixas Fechado</div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <!-- ESTOQUE COM SUBMENU -->
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-box"></i>
                            <div data-i18n="Basic">Estoque</div>
                        </a>
                        <ul class="menu-sub">
                            <!-- Produtos Adicionados: Cadastro ou listagem de produtos adicionados -->
                            <li class="menu-item ">
                                <a href="./produtosAdicionados.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div data-i18n="Basic">Produtos Adicionados</div>
                                </a>
                            </li>
                            <!-- Estoque Baixo -->
                            <li class="menu-item">
                                <a href="./estoqueBaixo.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Estoque Baixo</div>
                                </a>
                            </li>
                            <!-- Estoque Alto -->
                            <li class="menu-item">
                                <a href="./estoqueAlto.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Estoque Alto</div>
                                </a>
                            </li>
                        </ul>
                    </li>


                    <!-- SUBMENU: RELATÓRIOS -->
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-file"></i>
                            <div data-i18n="Authentications">Relatórios</div>
                        </a>
                        <ul class="menu-sub">
                            <!-- Relatório Operacional: Desempenho de operações -->
                            <li class="menu-item">
                                <a href="./relatorioOperacional.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div data-i18n="Basic">Operacional</div>
                                </a>
                            </li>
                            <!-- Relatório de Vendas: Estatísticas e resumo de vendas -->
                            <li class="menu-item">
                                <a href="./relatorioVendas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Vendas</div>
                                </a>
                            </li>
                        </ul>

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
                        <a href="../delivery/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-cart"></i>
                            <div data-i18n="Authentications">Delivery</div>

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
                </ul>
            </aside>
            <!-- / Menu -->

            <!-- Layout container -->
            <div class="layout-page">
                <!-- Navbar -->

                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
                    id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                            <i class="bx bx-menu bx-sm"></i>
                        </a>
                    </div>

                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <!-- Search -->
                        <div class="navbar-nav align-items-center">
                            <div class="nav-item d-flex align-items-center">
                                <i class="bx bx-search fs-4 lh-0"></i>
                                <input type="text" class="form-control border-0 shadow-none" placeholder="Search..."
                                    aria-label="Search..." />
                            </div>
                        </div>
                        <!-- /Search -->

                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <!-- User -->
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="avatar avatar-online">
                                        <img src="<?= htmlspecialchars($logoEmpresa, ENT_QUOTES) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownUser">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="<?= htmlspecialchars($logoEmpresa, ENT_QUOTES) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" />
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <span class="fw-semibold d-block"><?= htmlspecialchars($nomeUsuario, ENT_QUOTES); ?></span>
                                                    <small class="text-muted"><?= htmlspecialchars($tipoUsuario, ENT_QUOTES); ?></small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                    <li>
                                        <div class="dropdown-divider"></div>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="./contaUsuario.php?id=<?= urlencode($idSelecionado); ?>">
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
                                        <div class="dropdown-divider"></div>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="../logout.php?id=<?= urlencode($idSelecionado); ?>">
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


                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold py-3 mb-4">
                        <span class="fw-light" style="color: #696cff !important;">
                            <a href="#">PDV</a>
                        </span>/Status Integração
                    </h4>

                    <div class="row">
                        <!-- Card de Status da Integração -->
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-header d-flex align-items-center justify-content-between">
                                    <h5 class="card-title m-0 me-2">Status NFC-e</h5>
                                    <span class="badge <?php echo ($tpAmb && $cnpjExibe && $razao && $pfxPath && $csc && $idToken) ? 'bg-label-primary' : 'bg-label-danger'; ?>">
                                        <?php echo ($tpAmb && $cnpjExibe && $razao && $pfxPath && $csc && $idToken) ? 'Configurado' : 'Não Configurado'; ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled mb-0">
                                        <li class="mb-3">
                                            <span class="fw-medium me-2">Certificado Digital:</span>
                                            <span class="badge <?php echo $certificadoClass; ?>"><?php echo $certificadoStatus; ?></span>
                                            <?php if (!empty($pfxPath)): ?>
                                                <div style="font-size:12px;color:#666;word-break:break-all;margin-top:4px">
                                                    Caminho: <?php echo htmlspecialchars(absPath($pfxPath)); ?>
                                                </div>
                                            <?php endif; ?>
                                        </li>
                                        <li class="mb-3">
                                            <span class="fw-medium me-2">Ambiente:</span>
                                            <span class="badge <?php echo $ambienteClass; ?>"><?php echo $ambienteStatus; ?></span>
                                        </li>
                                        <li class="mb-3">
                                            <span class="fw-medium me-2">CNPJ:</span>
                                            <span><?php echo $configNFCe['cnpj']; ?></span>
                                        </li>
                                        <li class="mb-3">
                                            <span class="fw-medium me-2">Razão Social:</span>
                                            <span><?php echo htmlspecialchars($configNFCe['razao_social']); ?></span>
                                        </li>
                                        <li class="mb-3">
                                            <span class="fw-medium me-2">CSC / ID Token:</span>
                                            <span><?php echo ($csc ? '***' : '--'); ?> / <?php echo htmlspecialchars($idTokenExibe); ?></span>
                                        </li>
                                        <li class="mb-3">
                                            <span class="fw-medium me-2">Fonte:</span>
                                            <span class="badge bg-label-secondary">
                                                <?php
                                                $fonte = is_file($cfgFilePath) ? 'Arquivo de Configuração' : 'Banco de Dados';
                                                echo $fonte;
                                                ?>
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                                <div class="card-footer">
                                    <a href="adicionarNFCe.php?id=<?php echo urlencode($idSelecionado); ?>" class="btn btn-primary btn-sm">
                                        <?php echo ($tpAmb && $cnpjExibe && $razao && $csc && $idToken) ? 'Editar Configuração' : 'Configurar NFC-e'; ?>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Card de Últimas Notas -->
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="card-title m-0 me-2">Últimas NFC-e</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($tpAmb && $cnpjExibe && $razao && $pfxPath && $csc && $idToken): ?>
                                        <div class="alert alert-info">Integração configurada com sucesso!</div>
                                        <!-- TODO: listar últimas NFC-e emitidas -->
                                    <?php else: ?>
                                        <div class="alert alert-warning">
                                            A integração com NFC-e não está totalmente configurada. Clique em “Configurar NFC-e”.
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer">
                                    <a href="visualizarNFCe.php?id=<?php echo urlencode($idSelecionado); ?>" class="btn btn-outline-primary btn-sm">
                                        Visualizar NFC-e
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Card de Ajuda -->
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="card-title m-0 me-2">Ajuda</h5>
                                </div>
                                <div class="card-body">
                                    <p>Para configurar a NFC-e, você precisará:</p>
                                    <ol>
                                        <li>Certificado digital A1 válido (.pfx) em <code>../../assets/img/certificado/</code></li>
                                        <li>Dados cadastrais da empresa (CNPJ, Razão Social, IE, endereço, município IBGE)</li>
                                        <li>CSC e ID Token (SEFAZ) — o ID Token deve ter 6 dígitos</li>
                                    </ol>
                                    <?php if (!is_file($cfgFilePath)): ?>
                                        <div class="alert alert-secondary" style="font-size:13px">
                                            Dica: crie/ajuste <code><?php echo htmlspecialchars($cfgFilePath); ?></code> para centralizar a configuração.
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer">
                                    <a href="#" class="btn btn-outline-secondary btn-sm">Documentação</a>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
            <!-- Content wrapper -->
        </div>
        <!-- / Layout page -->

    </div>

    <!-- Overlay -->

    </div>
    <!-- / Layout wrapper -->

    <!-- Core JS -->
    <!-- build:js assets/vendor/js/core.js -->
    <script src="../../js/saudacao.js"></script>
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
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
$usuario_id = (int)$_SESSION['usuario_id'];

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
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        return 0 === strncmp($haystack, $needle, strlen($needle));
    }
}
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
    @include $cfgFilePath; // pode definir: TP_AMB, EMIT_CNPJ, EMIT_XNOME, CSC, ID_TOKEN, PFX_PATH, PFX_PASSWORD
    $cfgArq['tpAmb']        = defined('TP_AMB')       ? (int)TP_AMB : null;
    $cfgArq['cnpj']         = defined('EMIT_CNPJ')    ? EMIT_CNPJ   : null;
    $cfgArq['razao_social'] = defined('EMIT_XNOME')   ? EMIT_XNOME  : null;
    $cfgArq['CSC']          = defined('CSC')          ? CSC         : null;
    $cfgArq['CSCid']        = defined('ID_TOKEN')     ? ID_TOKEN    : null;
    if (defined('PFX_PATH') && PFX_PATH) $cfgArq['pfx_path'] = PFX_PATH;
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

// pasta padrão do certificado
$pastaCert = '../../assets/img/certificado/';

// origem preferida do certificado: arquivo config → DB (nome) → mais recente na pasta
$pfxPath   = $cfgArq['pfx_path'];
$pfxPass   = $cfgArq['pfx_password'] ?? ($cfgDb['senha_certificado'] ?? null);

if (!$pfxPath || !is_file(absPath($pfxPath))) {
    if (!empty($cfgDb['certificado_digital'])) {
        $possivel = absPath($pastaCert . '/' . basename($cfgDb['certificado_digital']));
        if (is_file($possivel)) $pfxPath = $possivel;
    }
}
if (!$pfxPath || !is_file(absPath($pfxPath))) {
    $maisNovo = acharPfxMaisRecente($pastaCert);
    if ($maisNovo) $pfxPath = $maisNovo;
}

/* Normalizações para exibir bonitinhas */
$cnpjExibe    = $cnpj ? onlyDigits($cnpj) : '--';
$idTokenExibe = $idToken !== null ? pad6((string)$idToken) : '--';

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
        if ($pfxPass !== null && $pfxPass !== '') {
            $bin = @file_get_contents($pfxAbs);
            $ok  = false;
            if ($bin !== false) $ok = @openssl_pkcs12_read($bin, $dummy, (string)$pfxPass);
            if ($ok) {
                $certificadoStatus = 'Válido';
                $certificadoClass = 'bg-label-success';
            } else {
                $certificadoStatus = 'Arquivo encontrado (senha inválida?)';
                $certificadoClass = 'bg-label-danger';
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
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
    data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>ERP - PDV | Documentação NFC-e</title>
    <meta name="description" content="" />

    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($logoEmpresa) ?>" />

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />

    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />

    <style>
        pre.code {
            background: #0f172a;
            color: #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            overflow: auto;
            font-size: 12px
        }

        .toc a {
            text-decoration: none
        }

        .toc .list-group-item {
            padding: .5rem .75rem
        }
    </style>

    <script src="../../assets/vendor/js/helpers.js"></script>
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
                        <span class="app-brand-text demo menu-text fw-bolder ms-2" style="text-transform:capitalize;">Açaínhadinhos</span>
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
                            <div>Dashboard</div>
                        </a>
                    </li>

                    <!-- PDV -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">PDV</span></li>

                    <!-- SEFAZ -->
                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-file"></i>
                            <div>SEFAZ</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item"><a href="./adicionarNFCe.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>NFC-e</div>
                                </a>
                            </li>
                            <li class="menu-item"><a href="./sefazStatus.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Status</div>
                                </a>
                            </li>
                            <li class="menu-item active"><a href="./documentacao.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Documentação</div>
                                </a>
                            </li>
                            <li class="menu-item"><a href="./sefazConsulta.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Consulta</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- Caixas -->
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-user"></i>
                            <div>Caixas</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item"><a href="./caixasAberto.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Caixas Aberto</div>
                                </a>
                            </li>
                            <li class="menu-item"><a href="./caixasFechado.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Caixas Fechado</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- Estoque -->
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-box"></i>
                            <div>Estoque</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item"><a href="./produtosAdicionados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Produtos Adicionados</div>
                                </a>
                            </li>
                            <li class="menu-item"><a href="./estoqueBaixo.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Estoque Baixo</div>
                                </a>
                            </li>
                            <li class="menu-item"><a href="./estoqueAlto.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Estoque Alto</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- Relatórios -->
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-file"></i>
                            <div>Relatórios</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item"><a href="./relatorioOperacional.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Operacional</div>
                                </a></li>
                            <li class="menu-item"><a href="./relatorioVendas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Vendas</div>
                                </a></li>
                        </ul>
                    </li>

                    <!-- Diversos -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
                    <li class="menu-item"><a href="../rh/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-group"></i>
                            <div>RH</div>
                        </a>
                    </li>
                    <li class="menu-item"><a href="../financas/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-dollar"></i>
                            <div>Finanças</div>
                        </a>
                    </li>
                    <li class="menu-item"><a href="../empresa/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div>Empresa</div>
                        </a>
                    </li>
                    <li class="menu-item"><a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-box"></i>
                            <div>Estoque</div>
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
                    <li class="menu-item"><a href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-group"></i>
                            <div>Usuários</div>
                        </a>
                    </li>
                    <li class="menu-item"><a href="https://wa.me/92991515710" target="_blank" class="menu-link"><i class="menu-icon tf-icons bx bx-support"></i>
                            <div>Suporte</div>
                        </a>
                    </li>
                </ul>
            </aside>
            <!-- / Menu -->

            <!-- Layout container -->
            <div class="layout-page">
                <!-- Navbar -->
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)"><i class="bx bx-menu bx-sm"></i></a>
                    </div>

                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <!-- Search -->
                        <div class="navbar-nav align-items-center">
                            <div class="nav-item d-flex align-items-center">
                                <i class="bx bx-search fs-4 lh-0"></i>
                                <input type="text" class="form-control border-0 shadow-none" placeholder="Search..." aria-label="Search..." />
                            </div>
                        </div>
                        <!-- /Search -->

                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <!-- User -->
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online"><img src="<?= htmlspecialchars($logoEmpresa, ENT_QUOTES) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" /></div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online"><img src="<?= htmlspecialchars($logoEmpresa, ENT_QUOTES) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" /></div>
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
                                    <li><a class="dropdown-item" href="./contaUsuario.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-user me-2"></i><span class="align-middle">Minha Conta</span></a></li>
                                    <li><a class="dropdown-item" href="#"><i class="bx bx-cog me-2"></i><span class="align-middle">Configurações</span></a></li>
                                    <li>
                                        <div class="dropdown-divider"></div>
                                    </li>
                                    <li><a class="dropdown-item" href="../logout.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-power-off me-2"></i><span class="align-middle">Sair</span></a></li>
                                </ul>
                            </li>
                            <!--/ User -->
                        </ul>
                    </div>
                </nav>
                <!-- / Navbar -->

                <!-- Content -->
                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold py-3 mb-4">
                        <span class="fw-light" style="color:#696cff!important;"><a href="#">PDV</a></span> / Documentação NFC-e
                    </h4>

                    <div class="row mt-1">
                        <!-- TOC -->
                        <div class="col-lg-3">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="card-title m-0">Sumário</h5>
                                </div>
                                <div class="card-body toc">
                                    <div class="list-group">
                                        <a href="#sec-visao" class="list-group-item list-group-item-action">1. Visão Geral</a>
                                        <a href="#sec-arquivos" class="list-group-item list-group-item-action">2. Arquivos e Pastas</a>
                                        <a href="#sec-config" class="list-group-item list-group-item-action">3. Configuração</a>
                                        <a href="#sec-visualizacao" class="list-group-item list-group-item-action">4. Visualização do DANFE</a>
                                        <a href="#sec-botao" class="list-group-item list-group-item-action">5. Botões e Rotas</a>
                                        <a href="#sec-erros" class="list-group-item list-group-item-action">6. Erros Comuns</a>
                                        <a href="#sec-checklist" class="list-group-item list-group-item-action">7. Checklist Rápido</a>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <span class="badge <?= ($tpAmb && $cnpjExibe && $razao && $pfxPath && $csc && $idToken) ? 'bg-label-primary' : 'bg-label-danger'; ?>">
                                        <?= ($tpAmb && $cnpjExibe && $razao && $pfxPath && $csc && $idToken) ? 'Configuração OK' : 'Configuração Incompleta' ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- CONTENT -->
                        <div class="col-lg-9">
                            <!-- 1) Visão Geral -->
                            <div id="sec-visao" class="card mb-4">
                                <div class="card-header d-flex align-items-center justify-content-between">
                                    <h5 class="card-title m-0">1) Visão Geral</h5>
                                    <span class="badge <?= $ambienteClass ?>"><?= $ambienteStatus ?></span>
                                </div>
                                <div class="card-body">
                                    <p>O módulo de NFC-e permite emitir e visualizar o DANFE da NFC-e. Para funcionar, você precisa informar:</p>
                                    <ul>
                                        <li>Ambiente: <b>1</b> (Produção) ou <b>2</b> (Homologação)</li>
                                        <li><b>CNPJ</b> e <b>Razão Social</b> da empresa</li>
                                        <li><b>Certificado A1 (.pfx)</b> e <b>senha</b></li>
                                        <li><b>CSC</b> (código de segurança) e <b>ID Token</b> (6 dígitos)</li>
                                    </ul>
                                    <p>Você pode informar isso pelo arquivo <code>config.php</code> (prioritário) ou pela tabela <code>integracao_nfce</code> no banco.</p>
                                </div>
                            </div>

                            <!-- 2) Arquivos e Pastas -->
                            <div id="sec-arquivos" class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title m-0">2) Arquivos e Pastas</h5>
                                </div>
                                <div class="card-body">
                                    <ul>
                                        <li><code>frentedeloja/caixa/config.php</code> — configurações da NFC-e (constantes).</li>
                                        <li><code>frentedeloja/caixa/danfe_nfce.php</code> — exibe o DANFE baseado em XML.</li>
                                        <li><code>frentedeloja/caixa/visualizarNFCe.php</code> — <i>preview</i> do modelo (sem XML).</li>
                                        <li><code>../../nfce/</code> — pasta onde ficam os XMLs autorizados (<code>procNFCe_&lt;chave&gt;.xml</code>).</li>
                                        <li><code>../../assets/img/certificado/</code> — pasta padrão para o arquivo <code>.pfx</code>.</li>
                                    </ul>
                                    <p>Permissões recomendadas (Linux):</p>
                                    <pre class="code"><code># Pasta de XML
                                        chmod 755 ../../nfce
                                        # Pasta de certificado
                                        chmod 750 ../../assets/img/certificado
                                        # Dono do arquivo do certificado (ex.: www-data)
                                        chown www-data:www-data ../../assets/img/certificado/seu_certificado.pfx
                                        </code>
                                    </pre>
                                </div>
                            </div>

                            <!-- 3) Configuração -->
                            <div id="sec-config" class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title m-0">3) Configuração</h5>
                                </div>
                                <div class="card-body">
                                    <p><b>Opção A — via arquivo</b> (<code>frentedeloja/caixa/config.php</code>):</p>
                                    <pre class="code"><code>&lt;?php
                                        /* Ambiente: 1=Produção, 2=Homologação */
                                        define('TP_AMB', 2);

                                        /* Empresa */
                                        define('EMIT_CNPJ', '12345678000195');
                                        define('EMIT_XNOME', 'MINHA EMPRESA LTDA');

                                        /* CSC e Token (ID_TOKEN com 6 dígitos) */
                                        define('CSC', 'SEU_CSC_AQUI');
                                        define('ID_TOKEN', '000001');

                                        /* Certificado A1 (.pfx) */
                                        define('PFX_PATH', __DIR__ . '/../../assets/img/certificado/seu_certificado.pfx');
                                        define('PFX_PASSWORD', 'sua_senha_forte');</code>
                                    </pre>

                                    <p class="mt-3"><b>Opção B — via banco</b> (tabela <code>integracao_nfce</code>): campos típicos:</p>
                                    <ul>
                                        <li><code>empresa_id</code>, <code>ambiente</code>, <code>cnpj</code>, <code>razao_social</code></li>
                                        <li><code>csc</code>, <code>id_token</code>, <code>certificado_digital</code> (nome do arquivo)</li>
                                        <li><code>senha_certificado</code></li>
                                    </ul>

                                    <div class="alert alert-secondary mt-3" role="alert">
                                        <i class="fa-solid fa-circle-info me-1"></i>
                                        Se existir <b>config.php</b>, ele tem prioridade sobre o banco.
                                    </div>

                                    <div class="mt-3">
                                        <span class="fw-medium me-2">Status atual:</span>
                                        <span class="badge <?= $ambienteClass ?>"><?= $ambienteStatus ?></span>
                                        &nbsp;•&nbsp; CNPJ: <b><?= htmlspecialchars($cnpjExibe) ?></b>
                                        &nbsp;•&nbsp; Certificado: <span class="badge <?= $certificadoClass ?>"><?= htmlspecialchars($certificadoStatus) ?></span>
                                        <?php if (!empty($pfxPath)): ?>
                                            <div class="text-muted" style="font-size:12px;word-break:break-all">
                                                PFX: <?= htmlspecialchars(absPath($pfxPath)) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- 4) Visualização do DANFE -->
                            <div id="sec-visualizacao" class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title m-0">4) Visualização do DANFE</h5>
                                </div>
                                <div class="card-body">
                                    <p>Para visualizar <b>um XML autorizado</b> como DANFE (HTML):</p>
                                    <pre class="code"><code>danfe_nfce.php?id=&lt;empresa_id&gt;&amp;venda_id=123&amp;chave=44DIGITOS
                                        # ou, se tiver o arquivo exato:
                                        danfe_nfce.php?id=&lt;empresa_id&gt;&amp;arq=procNFCe_44DIGITOS.xml
                                        </code></pre>
                                    <p>O script procura o XML em <code>../../nfce/</code> (prioritário) e fallbacks.</p>
                                    <p>Para só ver o <b>modelo</b> (sem XML):</p>
                                    <pre class="code"><code>visualizarNFCe.php?id=<?= htmlspecialchars($idSelecionado) ?>
                                        # parâmetros opcionais: fantasia, razao, cnpj, serie, nnf, valor, tpag, qrcode
                                        </code>
                                    </pre>
                                </div>
                            </div>

                            <!-- 5) Botões e Rotas -->
                            <div id="sec-botao" class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title m-0">5) Botões e Rotas</h5>
                                </div>
                                <div class="card-body">
                                    <p>Exemplos de botões:</p>
                                    <pre class="code"><code>
                                    &lt;a class="btn btn-outline-primary btn-sm"
                                        href="visualizarNFCe.php?id=&lt;?= urlencode($idSelecionado) ?&gt;" target="_blank"&gt;
                                        Visualizar NFC-e (Modelo)
                                    &lt;/a&gt;

                                    &lt;a class="btn btn-primary btn-sm"
                                        href="danfe_nfce.php?id=&lt;?= urlencode($idSelecionado) ?&gt;&amp;venda_id=&lt;?= 123 ?&gt;&amp;chave=&lt;?= urlencode('44...') ?&gt;"
                                        target="_blank"&gt;
                                        Ver DANFE (XML)
                                    &lt;/a&gt;</code>
                                    </pre>
                                    <p>Nos relatórios/listagens, monte a URL conforme a venda e a chave da NFC-e.</p>
                                </div>
                            </div>

                            <!-- 6) Erros Comuns -->
                            <div id="sec-erros" class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title m-0">6) Erros Comuns &amp; Soluções</h5>
                                </div>
                                <div class="card-body">
                                    <ul class="mb-0">
                                        <li><b>“Arquivo da NFC-e não encontrado em ../../nfce”</b>
                                            <br>— Verifique se o XML <code>procNFCe_44DIGITOS.xml</code> está realmente em <code>../../nfce/</code> e permissões da pasta.
                                        </li>
                                        <li class="mt-2"><b>“Arquivo encontrado (senha inválida?)”</b> no certificado
                                            <br>— Confirme a senha do <code>.pfx</code> e tente abrir localmente; se mudou, atualize-a no sistema.
                                        </li>
                                        <li class="mt-2"><b>Extensões PHP ausentes</b>
                                            <br>— Verifique <code>openssl</code>, <code>curl</code>, <code>dom</code>, <code>mbstring</code> habilitadas no PHP.
                                        </li>
                                        <li class="mt-2"><b>Ambiente incorreto</b>
                                            <br>— Em homologação (2), algumas consultas/QR usam endpoints diferentes dos de produção (1).
                                        </li>
                                        <li class="mt-2"><b>Permissões</b>
                                            <br>— O usuário do servidor web precisa ler as pastas <code>../../nfce</code> e <code>../../assets/img/certificado</code>.
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <!-- 7) Checklist -->
                            <div id="sec-checklist" class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title m-0">7) Checklist Rápido</h5>
                                </div>
                                <div class="card-body">
                                    <ol class="mb-2">
                                        <li>Definir ambiente: <b>TP_AMB</b> = 1 ou 2</li>
                                        <li>Informar <b>CNPJ</b> e <b>Razão Social</b></li>
                                        <li>Enviar <b>certificado A1 (.pfx)</b> para <code>../../assets/img/certificado/</code></li>
                                        <li>Configurar <b>CSC</b> e <b>ID Token (6 dígitos)</b></li>
                                        <li>Garantir a pasta <code>../../nfce/</code> para os XMLs</li>
                                        <li>Testar visualização: <code>visualizarNFCe.php</code></li>
                                        <li>Visualizar XML real: <code>danfe_nfce.php?id=...&amp;venda_id=...&amp;chave=...</code></li>
                                    </ol>
                                    <a class="btn btn-outline-secondary btn-sm" href="visualizarNFCe.php?id=<?= urlencode($idSelecionado) ?>" target="_blank">
                                        <i class="fa-solid fa-eye me-1"></i> Abrir Modelo agora
                                    </a>
                                </div>
                            </div>
                        </div><!-- /col-lg-9 -->
                    </div><!-- /row documentação -->
                </div>
                <!-- /Content -->
            </div>
            <!-- /Layout page -->
        </div>
        <!-- /Layout container -->
    </div>

    <!-- Core JS -->
    <script src="../../js/saudacao.js"></script>
    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../assets/vendor/js/menu.js"></script>
    <script src="../../assets/vendor/libs/apex-charts/apexcharts.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/dashboards-analytics.js"></script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>
</body>

</html>
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
$usuario_id  = $_SESSION['usuario_id'];

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
$acessoPermitido   = false;
$idEmpresaSession  = $_SESSION['empresa_id'];
$tipoSession       = $_SESSION['tipo_empresa'];

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
    echo "<script>alert('Acesso negado!'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
    exit;
}

// ✅ Logo da empresa (fallback)
try {
    $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1");
    $stmt->bindParam(':id', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $sobre = $stmt->fetch(PDO::FETCH_ASSOC);
    $logoEmpresa = !empty($sobre['imagem']) ? "../../assets/img/empresa/" . $sobre['imagem'] : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png";
}
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>ERP — Financeiro</title>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($logoEmpresa) ?>" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <script src="../../assets/vendor/js/helpers.js"></script>
    <script src="../../assets/js/config.js"></script>
    <style>
        .table thead th {
            white-space: nowrap;
        }

        .toolbar {
            gap: .5rem;
        }

        .toolbar .form-select,
        .toolbar .form-control {
            max-width: 220px;
        }

        .kpi-card .kpi-label {
            font-size: .875rem;
            color: #667085;
        }

        .kpi-card .kpi-value {
            font-size: 1.4rem;
            font-weight: 700;
        }

        .kpi-card .kpi-sub {
            font-size: .825rem;
            color: #818181;
        }

        .progress-skinny {
            height: 8px;
        }

        .badge-soft {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }
    </style>
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">

            <!-- ====== ASIDE ====== -->
            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="app-brand-link">
                        <span class="app-brand-text demo menu-text fw-bolder ms-2" style="text-transform: capitalize;">Açaínhadinhos</span>
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

                    <!-- Administração Filiais -->
                    <li class="menu-header small text-uppercase">
                        <span class="menu-header-text">Administração Filiais</span>
                    </li>

                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-building"></i>
                            <div>Filiais</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./filialAdicionada.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Adicionadas</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="menu-item ">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div>B2B - Matriz</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item"><a href="./contasFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Pagamentos Solic.</div>
                                </a></li>
                            <li class="menu-item"><a href="./produtosSolicitados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Produtos Solicitados</div>
                                </a></li>
                            <li class="menu-item"><a href="./produtosEnviados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Produtos Enviados</div>
                                </a></li>
                            <li class="menu-item"><a href="./transferenciasPendentes.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Transf. Pendentes</div>
                                </a></li>
                            <li class="menu-item"><a href="./historicoTransferencias.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Histórico Transf.</div>
                                </a></li>
                            <li class="menu-item"><a href="./estoqueMatriz.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Estoque Matriz</div>
                                </a></li>
                            <li class="menu-item"><a href="./relatoriosB2B.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Relatórios B2B</div>
                                </a></li>
                        </ul>
                    </li>

                    <!-- Relatórios -->
                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-bar-chart-alt-2"></i>
                            <div data-i18n="Relatorios">Relatórios</div>
                        </a>
                        <ul class="menu-sub active">
                            <li class="menu-item">
                                <a href="./VendasFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Vendas">Vendas por Filial</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./MaisVendidos.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="MaisVendidos">Mais Vendidos</div>
                                </a>
                            </li>
                            <li class="menu-item active">
                                <a href="./financeiroFilial.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Pedidos">Financeiro</div>
                                </a>
                            </li>

                        </ul>
                    </li>

                    <!-- Diversos -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
                    <li class="menu-item"><a href="../rh/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-group"></i>
                            <div>RH</div>
                        </a></li>
                    <li class="menu-item"><a href="../financas/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-dollar"></i>
                            <div>Finanças</div>
                        </a></li>
                    <li class="menu-item"><a href="../pdv/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-desktop"></i>
                            <div>PDV</div>
                        </a></li>
                    <li class="menu-item"><a href="../empresa/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div>Empresa</div>
                        </a></li>
                    <li class="menu-item"><a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-box"></i>
                            <div>Estoque</div>
                        </a></li>
                    <li class="menu-item"><a href="../filial/index.php?id=principal_1" class="menu-link"><i class="menu-icon tf-icons bx bx-building"></i>
                            <div>Filial</div>
                        </a></li>
                    <li class="menu-item"><a href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-group"></i>
                            <div>Usuários</div>
                        </a></li>
                    <li class="menu-item"><a href="https://wa.me/92991515710" target="_blank" class="menu-link"><i class="menu-icon tf-icons bx bx-support"></i>
                            <div>Suporte</div>
                        </a></li>
                </ul>
            </aside>
            <!-- ====== /ASIDE ====== -->

            <!-- Layout container -->
            <div class="layout-page">
                <!-- Navbar -->
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                            <i class="bx bx-menu bx-sm"></i>
                        </a>
                    </div>

                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <div class="navbar-nav align-items-center">
                            <div class="nav-item d-flex align-items-center">
                                <i class="bx bx-search fs-4 lh-0"></i>
                                <input type="text" class="form-control border-0 shadow-none" placeholder="Search..." aria-label="Search..." />
                            </div>
                        </div>

                        <ul class="navbar-nav flex-row align-items-center ms-auto">
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
                                    <li><a class="dropdown-item" href="./contaUsuario.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-user me-2"></i><span class="align-middle">Minha Conta</span></a></li>
                                    <li><a class="dropdown-item" href="#"><i class="bx bx-cog me-2"></i><span class="align-middle">Configurações</span></a></li>
                                    <li>
                                        <div class="dropdown-divider"></div>
                                    </li>
                                    <li><a class="dropdown-item" href="../logout.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-power-off me-2"></i><span class="align-middle">Sair</span></a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </nav>
                <!-- /Navbar -->

                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold mb-0">
                        <span class="text-muted fw-light"><a href="#">Relatórios</a> / </span>
                        Financeiro
                    </h4>
                    <h5 class="fw-bold mt-3 mb-3 custor-font">
                        <span class="text-muted fw-light">Recebíveis, fluxo de caixa e status por Filial — Mês Atual</span>
                    </h5>

                   <?php
// -----------------------------
// Captura e normaliza filtros
// -----------------------------
$idSelecionado = $_GET['id'] ?? '';
// garantir que id exista (você já fazia isso em outro trecho)
$de_raw   = $_GET['de']   ?? '';
$ate_raw  = $_GET['ate']  ?? '';
$status_raw = $_GET['status'] ?? ''; // pode estar vazio -> todos
$filial_raw  = $_GET['filial']  ?? ''; // nome da filial vindo do select

// helper para h() se não existir
if (!function_exists('h')) {
    function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

// Normalizar datas: se vazio, definir início do mês até hoje (com horário)
// Você pode alterar esse comportamento se preferir sem valores padrão.
try {
    $tz = new DateTimeZone('America/Sao_Paulo');
} catch (Exception $e) {
    $tz = null;
}

if (empty($de_raw)) {
    // primeiro dia do mês atual
    $de = (new DateTime('first day of this month', $tz))->format('Y-m-d');
} else {
    $de = $de_raw;
}

if (empty($ate_raw)) {
    // hoje
    $ate = (new DateTime('now', $tz))->format('Y-m-d');
} else {
    $ate = $ate_raw;
}

// criar variantes com hora para BETWEEN (inicio 00:00:00, fim 23:59:59)
$de_datetime = $de . ' 00:00:00';
$ate_datetime = $ate . ' 23:59:59';

// Normalizar status (string em minúsculas em DB)
$status = '';
if (!empty($status_raw) && strtolower($status_raw) !== 'status: todos' && $status_raw !== '') {
    // aceitar valores que user possa enviar (Aprovado / Pendente / Reprovado)
    $map = [
        'aprovado' => 'aprovado',
        'pago' => 'aprovado',
        'pendente' => 'pendente',
        'reprovado' => 'reprovado'
    ];
    $key = strtolower(trim($status_raw));
    $status = $map[$key] ?? '';
}

// Normalizar filial: filial_raw é nome exibido (ex: "Filial Centro")
// Se for vazio ou "Todas as Filiais" consideramos sem filtro
$filial = '';
if (!empty($filial_raw) && strtolower($filial_raw) !== 'todas as filiais') {
    $filial = trim($filial_raw);
}

// -----------------------------
// Buscar lista de filiais Ativas (para popular select)
// -----------------------------
$filiaisOptions = [];
try {
    $sqlFiliais = "SELECT id, nome FROM unidades WHERE tipo = 'Filial' AND status = 'Ativa' ORDER BY nome";
    $stmtF = $pdo->prepare($sqlFiliais);
    $stmtF->execute();
    $filiaisOptions = $stmtF->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // não interrompe — apenas lista vazia
    $filiaisOptions = [];
}

// Função auxiliar para montar cláusula de filtro por filial
// Observação: em cada query fizemos JOIN com unidades usando REPLACE(...,'unidade_','')
// Logo, filtrar por u.nome = :filial já é suficiente para limitar aos registros daquela filial.
// Para maior compatibilidade, mantemos somente esse filtro (que cobre ambos os formatos).
function addFilialClause(&$sqlWhereParts, &$params, $filial) {
    if (!empty($filial)) {
        $sqlWhereParts[] = "u.nome = :filialName";
        $params[':filialName'] = $filial;
    }
}

// -----------------------------
// HTML DO FILTRO (form)
// -----------------------------
?>
<!-- ============================= -->
<!-- Filtros                       -->
<!-- ============================= -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form class="w-100" method="get">
            <input type="hidden" name="id" value="<?= h($idSelecionado) ?>">

            <div class="row g-2 align-items-end">

                <!-- De -->
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label mb-1">De</label>
                    <input type="date" class="form-control form-control-sm" name="de" value="<?= h($de) ?>">
                </div>

                <!-- Até -->
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label mb-1">Até</label>
                    <input type="date" class="form-control form-control-sm" name="ate" value="<?= h($ate) ?>">
                </div>

                <!-- Status (aplica apenas a cards e recebíveis por status) -->
                <div class="col-12 col-sm-6 col-lg-3">
                    <label for="status" class="form-label mb-1">Status</label>
                    <select id="status" class="form-select form-select-sm" name="status">
                        <option value="">Status: Todos</option>
                        <option value="aprovado" <?= ($status === 'aprovado') ? 'selected' : '' ?>>Aprovado</option>
                        <option value="pendente" <?= ($status === 'pendente') ? 'selected' : '' ?>>Pendente</option>
                        <option value="reprovado" <?= ($status === 'reprovado') ? 'selected' : '' ?>>Reprovado</option>
                    </select>
                </div>

                <!-- Filial (populado dinamicamente) -->
                <div class="col-12 col-sm-6 col-lg-3">
                    <label for="filial" class="form-label mb-1">Filial</label>
                    <select id="filial" class="form-select form-select-sm" name="filial">
                        <option value="">Todas as Filiais</option>
                        <?php foreach ($filiaisOptions as $f): ?>
                            <option value="<?= h($f['nome']) ?>" <?= ($filial === $f['nome']) ? 'selected' : '' ?>>
                                <?= h($f['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Ações principais -->
                <div class="col-12 col-sm-6 col-lg-3 mr-3">
                    <div class="btn-toolbar" role="toolbar" aria-label="Exportar e imprimir">
                        <div class="btn-group btn-group-sm me-2" role="group" aria-label="Exportar">
                            <button type="button" class="btn btn-outline-dark dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bx bx-download me-1"></i>
                                <span class="align-middle">Exportar</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><button class="dropdown-item" type="button"><i class="bx bx-file me-2"></i> XLSX</button></li>
                                <li><button class="dropdown-item" type="button"><i class="bx bx-data me-2"></i> CSV</button></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><button class="dropdown-item" type="button"><i class="bx bx-table me-2"></i> PDF (tabela)</button></li>
                            </ul>
                        </div>

                        <div class="btn-group btn-group-sm me-2" role="group">
                            <button class="btn btn-outline-secondary" type="submit">
                                <i class="bx bx-filter-alt me-1"></i> Aplicar
                            </button>
                            <a class="btn btn-outline-dark" href="?id=<?= urlencode($idSelecionado) ?>" title="Limpar filtros">
                                <i class="bx bx-x me-1"></i> Limpar filtros
                            </a>
                        </div>

                        <div class="btn-group btn-group-sm" role="group" aria-label="Imprimir">
                            <button class="btn btn-outline-dark" type="button" onclick="window.print()" data-bs-toggle="tooltip" data-bs-title="Imprimir página">
                                <i class="bx bx-printer me-1"></i>
                                <span class="align-middle">Imprimir</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
// -----------------------------
// Aplicar filtros nas queries
// -----------------------------
// Observação: fazemos WHERE 1=1 e vamos acumulando cláusulas e parâmetros.
// Para as queries que fazem JOIN com unidades, usamos o mesmo alias 'u'.
//
// 1) CARDS (somas por status)  --> usa solicitacoes_pagamento (created_at)
//    OBS: status filter se aplica aqui.

$whereParts = ["1=1"];
$params = [];

// Filtrar por data (created_at)
if (!empty($de) && !empty($ate)) {
    $whereParts[] = "sp.created_at BETWEEN :de_created AND :ate_created";
    $params[':de_created'] = $de_datetime;
    $params[':ate_created'] = $ate_datetime;
}

// Filial (filtrar por nome via u.nome)
if (!empty($filial)) {
    $whereParts[] = "u.nome = :filialName";
    $params[':filialName'] = $filial;
}

// Status (aplica nos cards e no recebíveis por status) - se informado
if (!empty($status)) {
    $whereParts[] = "sp.status = :statusFilter";
    $params[':statusFilter'] = $status;
}

// Montar SQL dos cards (agrupamento por status)
try {
    $sql = "
        SELECT 
            sp.status,
            SUM(sp.valor) AS total
        FROM solicitacoes_pagamento sp
        INNER JOIN unidades u 
            ON u.id = REPLACE(sp.id_solicitante, 'unidade_', '')
        WHERE " . implode(" AND ", $whereParts) . "
        GROUP BY sp.status
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $cardData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo '<p>Erro ao carregar dados dos cards: ' . h($e->getMessage()) . '</p>';
    $cardData = [];
}

// Preencher cards
$cards = [
    "aprovado"  => 0,
    "pendente"  => 0,
    "reprovado" => 0
];
foreach ($cardData as $row) {
    $cards[strtolower($row['status'])] = (float)$row['total'];
}
$totalGeralCards = $cards['aprovado'] + $cards['pendente'] + $cards['reprovado'];
function p($valor, $total) { if ($total <= 0) return 0; return ($valor / $total) * 100; }

// -----------------------------
// RECEBÍVEIS POR STATUS (usa solicitacoes_pagamento)
// Aplica mesmo whereParts, porém se status foi informado já está considerado.
// -----------------------------
try {
    $sql = "
        SELECT 
            sp.status,
            COUNT(*) AS quantidade,
            SUM(sp.valor) AS total_valor
        FROM solicitacoes_pagamento sp
        INNER JOIN unidades u 
            ON u.id = REPLACE(sp.id_solicitante, 'unidade_', '')
        WHERE " . implode(" AND ", $whereParts) . "
        GROUP BY sp.status
        ORDER BY sp.status
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $statusData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<p>Erro ao carregar status: " . h($e->getMessage()) . "</p>";
    $statusData = [];
}

// converter para estrutura completa
$dados = [
    "aprovado"  => ["quantidade" => 0, "valor" => 0],
    "pendente"  => ["quantidade" => 0, "valor" => 0],
    "reprovado" => ["quantidade" => 0, "valor" => 0],
];
foreach ($statusData as $row) {
    $s = strtolower($row['status']);
    $dados[$s]['quantidade'] = (int)$row['quantidade'];
    $dados[$s]['valor'] = (float)$row['total_valor'];
}
$totalGeral = $dados['aprovado']['valor'] + $dados['pendente']['valor'] + $dados['reprovado']['valor'];

// -----------------------------
// FLUXO DE CAIXA (aberturas) - filtrar por fechamento_datetime
// -----------------------------
$wherePartsFluxo = ["1=1"];
$paramsFluxo = [];

// Data para aberturas -> fechamento_datetime
if (!empty($de) && !empty($ate)) {
    $wherePartsFluxo[] = "a.fechamento_datetime BETWEEN :de_fechamento AND :ate_fechamento";
    $paramsFluxo[':de_fechamento'] = $de_datetime;
    $paramsFluxo[':ate_fechamento'] = $ate_datetime;
}

// Filial: join e filtro por nome (se informado)
if (!empty($filial)) {
    $wherePartsFluxo[] = "u.nome = :filialNameFluxo";
    $paramsFluxo[':filialNameFluxo'] = $filial;
}

try {
    $sql = "
        SELECT
            a.responsavel,
            a.valor_total,
            a.valor_sangrias,
            a.valor_liquido,
            a.quantidade_vendas,
            u.nome AS nome_filial
        FROM aberturas a
        INNER JOIN unidades u
            ON u.id = REPLACE(a.empresa_id, 'unidade_', '')
        WHERE " . implode(" AND ", $wherePartsFluxo) . "
        AND a.status = 'fechado'
        AND u.tipo = 'Filial'
        AND u.status = 'Ativa'
        ORDER BY a.fechamento_datetime DESC
    ";
    $stmt = $pdo->prepare($sql);
    // merge paramsFluxo with mandatory no additional params
    $stmt->execute($paramsFluxo);
    $fluxo = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<p>Erro ao carregar fluxo de caixa: " . h($e->getMessage()) . "</p>";
    $fluxo = [];
}

// Totais do fluxo
$totalEntradas = 0; $totalSaidas = 0; $totalSaldo = 0; $totalVendas = 0;
foreach ($fluxo as $f) {
    $totalEntradas += $f['valor_total'];
    $totalSaidas += $f['valor_sangrias'];
    $totalSaldo += $f['valor_liquido'];
    $totalVendas += $f['quantidade_vendas'];
}

// -----------------------------
// CONTAS FUTURAS (contas.statuss = 'futura') - filtrar por datatransacao
// -----------------------------
$wherePartsContasF = ["1=1"];
$paramsContasF = [];
// data
if (!empty($de) && !empty($ate)) {
    $wherePartsContasF[] = "c.datatransacao BETWEEN :de_transacao AND :ate_transacao";
    $paramsContasF[':de_transacao'] = $de;
    $paramsContasF[':ate_transacao'] = $ate;
}
// filial
if (!empty($filial)) {
    $wherePartsContasF[] = "u.nome = :filialNameContas";
    $paramsContasF[':filialNameContas'] = $filial;
}
try {
    $sql = "
        SELECT 
            c.id,
            c.descricao,
            c.valorpago,
            c.datatransacao,
            c.responsavel,
            c.statuss,
            u.nome AS nome_filial
        FROM contas c
        INNER JOIN unidades u
            ON u.id = REPLACE(c.id_selecionado, 'unidade_', '')
        WHERE " . implode(" AND ", $wherePartsContasF) . "
            AND c.statuss = 'futura'
            AND u.tipo = 'Filial'
            AND u.status = 'Ativa'
        ORDER BY c.datatransacao ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($paramsContasF);
    $contasFuturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<p>Erro ao carregar contas futuras: " . h($e->getMessage()) . "</p>";
    $contasFuturas = [];
}

// -----------------------------
// CONTAS PAGAS (contas.statuss = 'pago') - filtrar por datatransacao
// -----------------------------
$wherePartsContasP = ["1=1"];
$paramsContasP = [];
if (!empty($de) && !empty($ate)) {
    $wherePartsContasP[] = "c.datatransacao BETWEEN :de_transacao_p AND :ate_transacao_p";
    $paramsContasP[':de_transacao_p'] = $de;
    $paramsContasP[':ate_transacao_p'] = $ate;
}
if (!empty($filial)) {
    $wherePartsContasP[] = "u.nome = :filialNameContasP";
    $paramsContasP[':filialNameContasP'] = $filial;
}
try {
    $sql = "
        SELECT 
            c.id,
            c.descricao,
            c.valorpago,
            c.datatransacao,
            c.responsavel,
            c.statuss,
            u.nome AS nome_filial
        FROM contas c
        INNER JOIN unidades u
            ON u.id = REPLACE(c.id_selecionado, 'unidade_', '')
        WHERE " . implode(" AND ", $wherePartsContasP) . "
            AND c.statuss = 'pago'
            AND u.tipo = 'Filial'
            AND u.status = 'Ativa'
        ORDER BY c.datatransacao DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($paramsContasP);
    $contasPagas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<p>Erro ao carregar contas pagas: " . h($e->getMessage()) . "</p>";
    $contasPagas = [];
}

// -----------------------------
// PAGAMENTOS APROVADOS (solicitacoes_pagamento status = 'aprovado')
// Aplica filtro por created_at e filial (via join) — mantém sua lógica
// -----------------------------
$wherePartsPag = ["1=1"];
$paramsPag = [];
if (!empty($de) && !empty($ate)) {
    $wherePartsPag[] = "sp.created_at BETWEEN :de_created_pag AND :ate_created_pag";
    $paramsPag[':de_created_pag'] = $de_datetime;
    $paramsPag[':ate_created_pag'] = $ate_datetime;
}
if (!empty($filial)) {
    $wherePartsPag[] = "u.nome = :filialNamePag";
    $paramsPag[':filialNamePag'] = $filial;
}
try {
    $sql = "
        SELECT 
            sp.ID,
            sp.valor,
            sp.descricao,
            sp.vencimento,
            sp.created_at,
            sp.comprovante_url,
            u.nome AS nome_filial
        FROM solicitacoes_pagamento sp
        INNER JOIN unidades u 
            ON u.id = REPLACE(sp.id_solicitante, 'unidade_', '')
        WHERE " . implode(" AND ", $wherePartsPag) . "
            AND sp.status = 'aprovado'
            AND u.tipo = 'Filial'
            AND u.status = 'Ativa'
        ORDER BY sp.created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($paramsPag);
    $pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<p>Erro ao carregar pagamentos: " . h($e->getMessage()) . "</p>";
    $pagamentos = [];
}

// calcular totalGeral para pagamentos
$totalGeral = 0;
foreach ($pagamentos as $pg) { $totalGeral += (float)$pg['valor']; }

?>
<!-- (a partir daqui seu HTML continua exatamente como antes, usando as variáveis preenchidas acima) -->

         <?php
// ---------------------------------------------------------
// CÁLCULO PARA OS CARDS DO RESUMO FINANCEIRO
// ---------------------------------------------------------

try {
    $sql = "
        SELECT 
            sp.status,
            SUM(sp.valor) AS total
        FROM solicitacoes_pagamento sp
        INNER JOIN unidades u 
            ON u.id = REPLACE(sp.id_solicitante, 'unidade_', '')
        WHERE 
            u.tipo = 'Filial'
            AND u.status = 'Ativa'
        GROUP BY sp.status
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $cardData = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo '<p>Erro ao carregar dados dos cards: ' . $e->getMessage() . '</p>';
    $cardData = [];
}

// Estrutura para armazenar
$cards = [
    "aprovado"  => 0,
    "pendente"  => 0,
    "reprovado" => 0
];

// Preencher
foreach ($cardData as $row) {
    $cards[strtolower($row["status"])] = (float)$row["total"];
}

// Total geral
$totalGeralCards = $cards["aprovado"] + $cards["pendente"] + $cards["reprovado"];

// Função porcentagem
function p($valor, $total) {
    if ($total <= 0) return 0;
    return ($valor / $total) * 100;
}
?>

<!-- ============================= -->
<!-- KPIs principais -->
<!-- ============================= -->
<div class="row">
    
    <!-- FATURAMENTO TOTAL (TODOS OS STATUS) -->
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card kpi-card">
            <div class="card-body">
                <div class="kpi-label">Faturamento (por Período)</div>
                <div class="kpi-value">
                    R$ <?= number_format($totalGeralCards, 2, ',', '.') ?>
                </div>
                <div class="kpi-sub">Pedidos fechados</div>
            </div>
        </div>
    </div>

    <!-- APROVADO -->
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card kpi-card">
            <div class="card-body">
                <div class="kpi-label">Recebido (Aprovado)</div>
                <div class="kpi-value">
                    R$ <?= number_format($cards["aprovado"], 2, ',', '.') ?>
                </div>
                <div class="kpi-sub">
                    <?= number_format(p($cards["aprovado"], $totalGeralCards), 1, ',', '.') ?>% do total
                </div>
            </div>
        </div>
    </div>

    <!-- PENDENTE -->
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card kpi-card">
            <div class="card-body">
                <div class="kpi-label">Em Aberto (Pendente)</div>
                <div class="kpi-value">
                    R$ <?= number_format($cards["pendente"], 2, ',', '.') ?>
                </div>
                <div class="kpi-sub">
                    <?= number_format(p($cards["pendente"], $totalGeralCards), 1, ',', '.') ?>% do total
                </div>
            </div>
        </div>
    </div>

    <!-- REPROVADO -->
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card kpi-card">
            <div class="card-body">
                <div class="kpi-label">Reprovados</div>
                <div class="kpi-value">
                    R$ <?= number_format($cards["reprovado"], 2, ',', '.') ?>
                </div>
                <div class="kpi-sub">
                    <?= number_format(p($cards["reprovado"], $totalGeralCards), 1, ',', '.') ?>% do total
                </div>
            </div>
        </div>
    </div>

</div>

           

<!-- ============================= -->
<!-- Recebíveis por Status -->
<!-- ============================= -->
<div class="card mb-3">
    <h5 class="card-header">Recebíveis por Status</h5>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Status</th>
                    <th class="text-end">Quantidade</th>
                    <th class="text-end">Valor (R$)</th>
                    <th style="min-width:180px;">% do Total</th>
                </tr>
            </thead>

            <tbody>

                <!-- Status: Aprovado -->
                <tr>
                    <td><span class="badge badge-soft bg-success text-white">Pago</span></td>
                    <td class="text-end"><?= $dados['aprovado']['quantidade'] ?></td>
                    <td class="text-end">
                        R$ <?= number_format($dados['aprovado']['valor'], 2, ',', '.') ?>
                    </td>

                    <?php $p_aprov = percent($dados['aprovado']['valor'], $totalGeral); ?>

                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="flex-grow-1">
                                <div class="progress progress-skinny">
                                    <div class="progress-bar bg-success" 
                                         style="width: <?= $p_aprov ?>%;">
                                    </div>
                                </div>
                            </div>
                            <div style="width:58px;" class="text-end">
                                <?= number_format($p_aprov, 1, ',', '.') ?>%
                            </div>
                        </div>
                    </td>
                </tr>

                <!-- Status: Pendente -->
                <tr>
                    <td><span class="badge badge-soft bg-warning text-dark">Em Aberto</span></td>

                    <td class="text-end"><?= $dados['pendente']['quantidade'] ?></td>

                    <td class="text-end">
                        R$ <?= number_format($dados['pendente']['valor'], 2, ',', '.') ?>
                    </td>

                    <?php $p_pend = percent($dados['pendente']['valor'], $totalGeral); ?>

                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="flex-grow-1">
                                <div class="progress progress-skinny">
                                    <div class="progress-bar bg-warning"
                                         style="width: <?= $p_pend ?>%;">
                                    </div>
                                </div>
                            </div>
                            <div style="width:58px;" class="text-end">
                                <?= number_format($p_pend, 1, ',', '.') ?>%
                            </div>
                        </div>
                    </td>
                </tr>

                <!-- Status: Reprovado -->
                <tr>
                    <td><span class="badge badge-soft bg-danger text-white">Reprovado</span></td>

                    <td class="text-end"><?= $dados['reprovado']['quantidade'] ?></td>

                    <td class="text-end">
                        R$ <?= number_format($dados['reprovado']['valor'], 2, ',', '.') ?>
                    </td>

                    <?php $p_rep = percent($dados['reprovado']['valor'], $totalGeral); ?>

                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="flex-grow-1">
                                <div class="progress progress-skinny">
                                    <div class="progress-bar bg-danger"
                                         style="width: <?= $p_rep ?>%;">
                                    </div>
                                </div>
                            </div>
                            <div style="width:58px;" class="text-end">
                                <?= number_format($p_rep, 1, ',', '.') ?>%
                            </div>
                        </div>
                    </td>
                </tr>

            </tbody>

            <tfoot>
                <tr>
                    <th>Total</th>
                    <th class="text-end">
                        <?= 
                            $dados['aprovado']['quantidade'] + 
                            $dados['pendente']['quantidade'] + 
                            $dados['reprovado']['quantidade']
                        ?>
                    </th>

                    <th class="text-end">
                        R$ <?= number_format($totalGeral, 2, ',', '.') ?>
                    </th>
                    <th></th>
                </tr>
            </tfoot>

        </table>
    </div>
</div>

<!-- ============================= -->
<!-- Fluxo de Caixa (Resumo) -->
<!-- ============================= -->
<div class="card mb-3">
    <h5 class="card-header">Fluxo de Caixa — Resumo do Período</h5>

    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Responsavel</th>
                    <th class="text-end">Entradas (R$)</th>
                    <th class="text-end">Saídas (R$)</th>
                    <th class="text-end">Saldo (R$)</th>
                    <th class="text-end">Quantidade de Vnd</th>
                </tr>
            </thead>

            <tbody>
                <?php if (empty($fluxo)) : ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted">
                            Nenhum caixa fechado de filial ativa encontrado.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($fluxo as $f): ?>

                        <?php
                            $totalEntradas += $f['valor_total'];
                            $totalSaidas   += $f['valor_sangrias'];
                            $totalSaldo    += $f['valor_liquido'];
                            $totalVendas   += $f['quantidade_vendas'];
                        ?>

                        <tr>
                            <td><?= htmlspecialchars($f['responsavel']) ?></td>

                            <td class="text-end">
                                R$ <?= number_format($f['valor_total'], 2, ',', '.') ?>
                            </td>

                            <td class="text-end">
                                R$ <?= number_format($f['valor_sangrias'], 2, ',', '.') ?>
                            </td>

                            <td class="text-end">
                                R$ <?= number_format($f['valor_liquido'], 2, ',', '.') ?>
                            </td>

                            <td class="text-end"><?= $f['quantidade_vendas'] ?></td>
                        </tr>

                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>

            <tfoot>
                <tr>
                    <th>Total</th>

                    <th class="text-end">
                        R$ <?= number_format($totalEntradas, 2, ',', '.') ?>
                    </th>

                    <th class="text-end">
                        R$ <?= number_format($totalSaidas, 2, ',', '.') ?>
                    </th>

                    <th class="text-end">
                        R$ <?= number_format($totalSaldo, 2, ',', '.') ?>
                    </th>

                    <th class="text-end"><?= $totalVendas ?></th>
                </tr>
            </tfoot>

        </table>
    </div>
</div>


           
<!-- ============================= -->
<!-- Contas a Pagar (Futuras) -->
<!-- ============================= -->
<div class="card mb-3">
    <h5 class="card-header">Contas a pagar (Futura)</h5>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Filial</th>
                    <th>Descrição</th>
                    <th>Data Transação</th>
                    <th class="text-end">Valor (R$)</th>
                    <th>responsavel</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>
                <?php if (empty($contasFuturas)) : ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">
                            Nenhuma conta futura de filial ativa encontrada.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($contasFuturas as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['id']) ?></td>

                            <td><?= htmlspecialchars($c['nome_filial']) ?></td>

                            <td><?= htmlspecialchars($c['descricao']) ?></td>

                            <td><?= date('d/m/Y', strtotime($c['datatransacao'])) ?></td>

                            <td class="text-end">
                                R$ <?= number_format($c['valorpago'], 2, ',', '.') ?>
                            </td>

                            <td><?= htmlspecialchars($c['responsavel']) ?></td>

                            <td>
                                <span class="badge bg-warning text-dark">Futura</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>

        </table>
    </div>
</div>

               
<!-- ============================= -->
<!-- Contas a Pagar / Contas Pagas -->
<!-- ============================= -->
<div class="card mb-3">
    <h5 class="card-header">Contas Pagas</h5>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Filial</th>
                    <th>Descrição</th>
                    <th>Data Transação</th>
                    <th class="text-end">Valor (R$)</th>
                    <th>Responsável</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>
                <?php if (empty($contasPagas)) : ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">
                            Nenhuma conta paga por filial ativa encontrada.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($contasPagas as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['id']) ?></td>

                            <td><?= htmlspecialchars($c['nome_filial']) ?></td>

                            <td><?= htmlspecialchars($c['descricao']) ?></td>

                            <td><?= date('d/m/Y', strtotime($c['datatransacao'])) ?></td>

                            <td class="text-end">
                                R$ <?= number_format($c['valorpago'], 2, ',', '.') ?>
                            </td>

                            <td><?= htmlspecialchars($c['responsavel']) ?></td>

                            <td>
                                <span class="badge bg-success">Pago</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>

        </table>
    </div>
</div>


                    <!-- ============================= -->
                  
<!-- Pagamentos por Filial -->
<div class="card mb-3">
    <h5 class="card-header">Pagamentos por Filial — Resumo</h5>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Filial</th>
                    <th class="text-end">Valor</th>
                    <th class="text-end">Data de Emissão</th>
                    <th class="text-end">Vencimento</th>
                    <th class="text-end">Comprovante</th>
                    <th class="text-end">Descrição</th>
                </tr>
            </thead>

            <tbody>
                <?php if (empty($pagamentos)) : ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">
                            Nenhum pagamento aprovado de filial ativa encontrado.
                        </td>
                    </tr>
                <?php else: ?>

                    <?php foreach ($pagamentos as $pg): ?>
                        <?php $totalGeral += $pg['valor']; ?>

                        <tr>
                            <td><strong><?= htmlspecialchars($pg['nome_filial']) ?></strong></td>

                            <td class="text-end">
                                R$ <?= number_format($pg['valor'], 2, ',', '.') ?>
                            </td>

                            <td class="text-end">
                                <?= date('d/m/Y H:i', strtotime($pg['created_at'])) ?>
                            </td>

                            <td class="text-end">
                                <?= date('d/m/Y', strtotime($pg['vencimento'])) ?>
                            </td>

                            <td class="text-end">
                                <?php if (!empty($pg['comprovante_url'])): ?>
                                    <a href="/assets/php/matriz/<?= $pg['comprovante_url'] ?>" target="_blank">
                                        Abrir
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Sem arquivo</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-end">
                                <?= htmlspecialchars($pg['descricao']) ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>
            </tbody>

            <tfoot>
                <tr>
                    <th>Total</th>
                    <th class="text-end">
                        R$ <?= number_format($totalGeral, 2, ',', '.') ?>
                    </th>
                </tr>
            </tfoot>

        </table>
    </div>
</div>


                </div><!-- /container -->
            </div><!-- /Layout page -->
        </div><!-- /Layout container -->
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // tooltips bootstrap
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

            // spinner no Aplicar
            const form = document.querySelector('form[method="get"]');
            const btnAplicar = document.getElementById('btnAplicar');
            if (form && btnAplicar) {
                form.addEventListener('submit', function() {
                    btnAplicar.disabled = true;
                    btnAplicar.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processando...';
                });
            }
        });
    </script>
    <!-- Core JS -->
    <script src="../../js/saudacao.js"></script>
    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../assets/vendor/js/menu.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>
</body>

</html>
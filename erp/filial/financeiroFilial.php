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
// ================================================================
// SISTEMA FINANCEIRO — SOMENTE FILIAL ATIVA (refatorado)
// ================================================================

// Escape
if (!function_exists('h')) {
    function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

// percent
if (!function_exists('percentVal')) {
    function percentVal($valor, $total) {
        if ($total <= 0) return 0;
        return ($valor / $total) * 100;
    }
}

// -----------------------------
// CONFIG
// -----------------------------
$perPage = 6; // itens por página (fixado conforme solicitado)

// -----------------------------
// ID
// -----------------------------
$idSelecionado = $_GET['id'] ?? ($idSelecionado ?? '');
if (!$idSelecionado) {
    echo '<div class="alert alert-danger">ID inválido</div>';
    return;
}

// -----------------------------
// FILTROS (mudança: se todos vazios => NÃO aplica filtro nenhum)
// -----------------------------
$de_raw     = isset($_GET['de']) ? trim($_GET['de']) : '';
$ate_raw    = isset($_GET['ate']) ? trim($_GET['ate']) : '';
$status_raw = isset($_GET['status']) ? trim($_GET['status']) : '';
$filial_raw = isset($_GET['filial']) ? trim($_GET['filial']) : '';

try { $tz = new DateTimeZone('America/Sao_Paulo'); } catch (Exception $e) { $tz = null; }

// IMPORTANT: by default do NOT apply date filters if user didn't provide them
$applyDateFilter = ($de_raw !== '' || $ate_raw !== '');

// prepare date boundaries only if provided
$de = $ate = null;
$de_datetime = $ate_datetime = null;
if ($de_raw !== '') {
    $de = $de_raw;
    $de_datetime = $de . ' 00:00:00';
}
if ($ate_raw !== '') {
    $ate = $ate_raw;
    $ate_datetime = $ate . ' 23:59:59';
}

// normalize status (allow only known values)
$status = '';
if ($status_raw !== '') {
    $s = strtolower(trim($status_raw));
    if (in_array($s, ['aprovado','pendente','reprovado'])) $status = $s;
}

// filial string
$filial = trim((string)$filial_raw);

// -----------------------------
// FILIAIS ATIVAS (select options)
// -----------------------------
$filiaisOptions = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, nome, empresa_id 
        FROM unidades 
        WHERE tipo = 'Filial' 
          AND status = 'Ativa'
        ORDER BY nome
    ");
    $stmt->execute();
    $filiaisOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $filiaisOptions = [];
}

// JOIN template: replace FIELD_ID at runtime with correct column
$JOIN_FILIAL = "
    JOIN unidades u 
      ON u.id = REPLACE(FIELD_ID, 'unidade_', '') 
     AND u.tipo = 'Filial'
     AND u.status = 'Ativa'
";

// -----------------------------
// UTIL: build WHERE and params based on filters (date optional)
// -----------------------------
function buildWhereAndParams(array $baseWhere, array $baseParams, $applyDateFilter, $de_datetime, $ate_datetime, $filial, $status, $dateColumn = 'created_at') {
    $where = $baseWhere;
    $params = $baseParams;

    if ($applyDateFilter) {
        // if only one boundary provided, still use appropriate condition
        if (!empty($de_datetime) && !empty($ate_datetime)) {
            $where[] = " {$dateColumn} BETWEEN :d1 AND :d2 ";
            $params[':d1'] = $de_datetime;
            $params[':d2'] = $ate_datetime;
        } elseif (!empty($de_datetime)) {
            $where[] = " {$dateColumn} >= :d1 ";
            $params[':d1'] = $de_datetime;
        } elseif (!empty($ate_datetime)) {
            $where[] = " {$dateColumn} <= :d2 ";
            $params[':d2'] = $ate_datetime;
        }
    }

    if ($filial !== '') {
        $where[] = " u.nome = :filialFiltro ";
        $params[':filialFiltro'] = $filial;
    }

    if ($status !== '') {
        $where[] = " sp.status = :statusFiltro ";
        $params[':statusFiltro'] = $status;
    }

    return [$where, $params];
}

// ================================================================
// 1) CARDS — solicitacoes_pagamento (mantém leitura total se sem filtro)
// ================================================================
$cards = ['aprovado'=>0.0,'pendente'=>0.0,'reprovado'=>0.0];

try {
    $where = ["1=1"];
    $params = [];

    // build dynamic where for cards based on if user applied anything
    list($where, $params) = buildWhereAndParams($where, $params, $applyDateFilter, $de_datetime, $ate_datetime, $filial, $status, 'sp.created_at');

    $sql = "
        SELECT sp.status, SUM(sp.valor) AS total
        FROM solicitacoes_pagamento sp
        ".str_replace("FIELD_ID","sp.id_solicitante",$JOIN_FILIAL)."
        WHERE ".implode(" AND ",$where)."
        GROUP BY sp.status
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($res as $r){
        $k = strtolower($r['status']);
        if(isset($cards[$k])) $cards[$k] = (float)$r['total'];
    }

} catch (PDOException $e){
    // opcional: log($e->getMessage());
}

$totalGeralCards = $cards['aprovado'] + $cards['pendente'] + $cards['reprovado'];

// ================================================================
// 2) RECEBÍVEIS POR STATUS (sem paginação — fixo: 3 linhas)
// ================================================================
$dados = [
    'aprovado' => ['quantidade'=>0, 'valor'=>0.0],
    'pendente' => ['quantidade'=>0, 'valor'=>0.0],
    'reprovado'=> ['quantidade'=>0, 'valor'=>0.0]
];

try {
    $where = ["1=1"];
    $params = [];

    list($where, $params) = buildWhereAndParams($where, $params, $applyDateFilter, $de_datetime, $ate_datetime, $filial, $status, 'sp.created_at');

    $sql = "
        SELECT sp.status, COUNT(*) AS qtd, SUM(sp.valor) AS soma
        FROM solicitacoes_pagamento sp
        ".str_replace("FIELD_ID","sp.id_solicitante",$JOIN_FILIAL)."
        WHERE ".implode(" AND ",$where)."
        GROUP BY sp.status
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($res as $r){
        $k = strtolower($r['status']);
        if (isset($dados[$k])){
            $dados[$k]['quantidade'] = (int)$r['qtd'];
            $dados[$k]['valor'] = (float)$r['soma'];
        }
    }

} catch (PDOException $e) { }

$totalGeralRecebiveis = $dados['aprovado']['valor'] + $dados['pendente']['valor'] + $dados['reprovado']['valor'];

// ================================================================
// 3) FLUXO DE CAIXA (aberturas) — COM PAGINAÇÃO
// ================================================================
$fluxo = [];
$totalEntradas = $totalSaidas = $totalSaldo = 0;
$totalVendas = 0;

try {
    $where = ["1=1"];
    $params = [];

    // date column used: a.fechamento_datetime
    if ($applyDateFilter) {
        // reuse buildWhereAndParams but with different date column and no status param
        list($where, $params) = buildWhereAndParams($where, $params, $applyDateFilter, $de_datetime, $ate_datetime, $filial, '', 'a.fechamento_datetime');
    } else {
        // only filial filter applies if set
        if ($filial !== '') {
            $where[] = " u.nome = :filialFiltro ";
            $params[':filialFiltro'] = $filial;
        }
    }

    // always only closed
    $where[] = " a.status = 'fechado' ";

    // COUNT total
    $countSql = "
        SELECT COUNT(*) as cnt
        FROM aberturas a
        ".str_replace("FIELD_ID","a.empresa_id",$JOIN_FILIAL)."
        WHERE ".implode(" AND ", $where)."
    ";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalRowsFluxo = (int)$stmt->fetchColumn();

    // pagination variables
    $page_fluxo = max(1, (int)($_GET['page_fluxo'] ?? 1));
    $offset_fluxo = ($page_fluxo - 1) * $perPage;

    $sql = "
        SELECT a.responsavel, a.valor_total, a.valor_sangrias, a.valor_liquido,
               a.quantidade_vendas, u.nome AS nome_filial, a.fechamento_datetime
        FROM aberturas a
        ".str_replace("FIELD_ID","a.empresa_id",$JOIN_FILIAL)."
        WHERE ".implode(" AND ", $where)."
        ORDER BY a.fechamento_datetime DESC
        LIMIT :lim OFFSET :off
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':lim', (int)$perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off', (int)$offset_fluxo, PDO::PARAM_INT);
    $stmt->execute();
    $fluxo = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($fluxo as $f){
        $totalEntradas += (float)$f['valor_total'];
        $totalSaidas   += (float)$f['valor_sangrias'];
        $totalSaldo    += (float)$f['valor_liquido'];
        $totalVendas   += (int)$f['quantidade_vendas'];
    }

} catch(PDOException $e){
    $fluxo = [];
    $totalRowsFluxo = 0;
}

// ================================================================
// 4) CONTAS FUTURAS — COM PAGINAÇÃO
// ================================================================
$contasFuturas = [];
$totalRowsContasFuturas = 0;

try {
    $where = ["1=1"];
    $params = [];

    // date column: c.datatransacao (note: earlier used dates without times)
    if ($applyDateFilter) {
        // when using datatransacao, strip times
        if (!empty($de)) {
            $where[] = " c.datatransacao >= :d1date ";
            $params[':d1date'] = $de;
        }
        if (!empty($ate)) {
            $where[] = " c.datatransacao <= :d2date ";
            $params[':d2date'] = $ate;
        }
    }

    if ($filial !== '') {
        $where[] = " u.nome = :filialFiltro ";
        $params[':filialFiltro'] = $filial;
    }

    $where[] = " c.statuss = 'futura' ";

    // count
    $countSql = "
        SELECT COUNT(*) as cnt
        FROM contas c
        ".str_replace("FIELD_ID","c.id_selecionado",$JOIN_FILIAL)."
        WHERE ".implode(" AND ", $where)."
    ";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalRowsContasFuturas = (int)$stmt->fetchColumn();

    // pagination
    $page_contas_futuras = max(1, (int)($_GET['page_contas_futuras'] ?? 1));
    $offset_contas_futuras = ($page_contas_futuras - 1) * $perPage;

    $sql = "
        SELECT c.*, u.nome AS nome_filial
        FROM contas c
        ".str_replace("FIELD_ID","c.id_selecionado",$JOIN_FILIAL)."
        WHERE ".implode(" AND ", $where)."
        ORDER BY c.datatransacao ASC
        LIMIT :lim OFFSET :off
    ";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':lim', (int)$perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off', (int)$offset_contas_futuras, PDO::PARAM_INT);
    $stmt->execute();
    $contasFuturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e){
    $contasFuturas = [];
    $totalRowsContasFuturas = 0;
}

// ================================================================
// 5) CONTAS PAGAS — COM PAGINAÇÃO
// ================================================================
$contasPagas = [];
$totalRowsContasPagas = 0;

try {
    $where = ["1=1"];
    $params = [];

    if ($applyDateFilter) {
        if (!empty($de)) {
            $where[] = " c.datatransacao >= :d1date ";
            $params[':d1date'] = $de;
        }
        if (!empty($ate)) {
            $where[] = " c.datatransacao <= :d2date ";
            $params[':d2date'] = $ate;
        }
    }

    if ($filial !== '') {
        $where[] = " u.nome = :filialFiltro ";
        $params[':filialFiltro'] = $filial;
    }

    $where[] = " c.statuss = 'pago' ";

    // count
    $countSql = "
        SELECT COUNT(*) as cnt
        FROM contas c
        ".str_replace("FIELD_ID","c.id_selecionado",$JOIN_FILIAL)."
        WHERE ".implode(" AND ", $where)."
    ";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalRowsContasPagas = (int)$stmt->fetchColumn();

    // pagination
    $page_contas_pagas = max(1, (int)($_GET['page_contas_pagas'] ?? 1));
    $offset_contas_pagas = ($page_contas_pagas - 1) * $perPage;

    $sql = "
        SELECT c.*, u.nome AS nome_filial
        FROM contas c
        ".str_replace("FIELD_ID","c.id_selecionado",$JOIN_FILIAL)."
        WHERE ".implode(" AND ", $where)."
        ORDER BY c.datatransacao DESC
        LIMIT :lim OFFSET :off
    ";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':lim', (int)$perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off', (int)$offset_contas_pagas, PDO::PARAM_INT);
    $stmt->execute();
    $contasPagas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e){
    $contasPagas = [];
    $totalRowsContasPagas = 0;
}

// ================================================================
// 6) PAGAMENTOS APROVADOS — COM PAGINAÇÃO
// ================================================================
$pagamentos = [];
$totalGeralPagamentos = 0;
$totalRowsPagamentos = 0;

try {
    $where = ["1=1"];
    $params = [];

    list($where, $params) = buildWhereAndParams($where, $params, $applyDateFilter, $de_datetime, $ate_datetime, $filial, $status, 'sp.created_at');

    $where[] = " sp.status = 'aprovado' ";

    // count
    $countSql = "
        SELECT COUNT(*) as cnt
        FROM solicitacoes_pagamento sp
        ".str_replace("FIELD_ID","sp.id_solicitante",$JOIN_FILIAL)."
        WHERE ".implode(" AND ", $where)."
    ";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalRowsPagamentos = (int)$stmt->fetchColumn();

    // pagination
    $page_pagamentos = max(1, (int)($_GET['page_pagamentos'] ?? 1));
    $offset_pagamentos = ($page_pagamentos - 1) * $perPage;

    $sql = "
        SELECT sp.*, u.nome AS nome_filial
        FROM solicitacoes_pagamento sp
        ".str_replace("FIELD_ID","sp.id_solicitante",$JOIN_FILIAL)."
        WHERE ".implode(" AND ", $where)."
        ORDER BY sp.created_at DESC
        LIMIT :lim OFFSET :off
    ";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':lim', (int)$perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off', (int)$offset_pagamentos, PDO::PARAM_INT);
    $stmt->execute();
    $pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pagamentos as $p){
        $totalGeralPagamentos += (float)$p['valor'];
    }

} catch(PDOException $e){
    $pagamentos = [];
    $totalRowsPagamentos = 0;
}

// ================================================================
// FUNÇÃO AUX: render pager links
// ================================================================
function renderPager($baseQueryParams, $currentPage, $totalRows, $perPage, $pageParamName) {
    $totalPages = (int)ceil($totalRows / $perPage);
    if ($totalPages <= 1) return '';

    $out = '<nav aria-label="Page navigation" class="mt-2"><ul class="pagination pagination-sm mb-0">';

    // previous
    $prev = max(1, $currentPage - 1);
    $qsPrev = http_build_query(array_merge($baseQueryParams, [$pageParamName => $prev]));
    $out .= '<li class="page-item '.($currentPage==1?'disabled':'').'"><a class="page-link" href="?'.$qsPrev.'">&laquo;</a></li>';

    // window pages (simple)
    $start = max(1, $currentPage - 3);
    $end = min($totalPages, $currentPage + 3);
    for ($p = $start; $p <= $end; $p++) {
        $qs = http_build_query(array_merge($baseQueryParams, [$pageParamName => $p]));
        $out .= '<li class="page-item '.($p==$currentPage?'active':'').'"><a class="page-link" href="?'.$qs.'">'. $p .'</a></li>';
    }

    // next
    $next = min($totalPages, $currentPage + 1);
    $qsNext = http_build_query(array_merge($baseQueryParams, [$pageParamName => $next]));
    $out .= '<li class="page-item '.($currentPage==$totalPages?'disabled':'').'"><a class="page-link" href="?'.$qsNext.'">&raquo;</a></li>';

    $out .= '</ul></nav>';
    return $out;
}

// Build base query params to preserve filters across pagination links
$baseQueryParams = [];
$baseQueryParams['id'] = $idSelecionado;
if ($de_raw !== '') $baseQueryParams['de'] = $de_raw;
if ($ate_raw !== '') $baseQueryParams['ate'] = $ate_raw;
if ($status_raw !== '') $baseQueryParams['status'] = $status_raw;
if ($filial_raw !== '') $baseQueryParams['filial'] = $filial_raw;

// ensure other page params preserved? we'll let pager generate individually

// --------------------------
// HTML: filtros reorganizados
// --------------------------
?>
<!-- ============================= -->
<!-- Filtros (De / Até, Status, Filial) -->
<!-- ============================= -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form class="w-100" method="get">
            <input type="hidden" name="id" value="<?= h($idSelecionado) ?>">

            <div class="row g-2 align-items-end">

                <!-- De -->
                <div class="col-6 col-md-2">
                    <label class="form-label mb-1">De</label>
                    <input type="date" class="form-control form-control-sm" name="de" value="<?= h($de_raw) ?>">
                </div>

                <!-- Até -->
                <div class="col-6 col-md-2">
                    <label class="form-label mb-1">Até</label>
                    <input type="date" class="form-control form-control-sm" name="ate" value="<?= h($ate_raw) ?>">
                </div>

                <!-- Status -->
                <div class="col-6 col-md-2">
                    <label for="status" class="form-label mb-1">Status</label>
                    <select id="status" class="form-select form-select-sm" name="status">
                        <option value="">Status: Todos</option>
                        <option value="aprovado" <?= ($status === 'aprovado') ? 'selected' : '' ?>>Aprovado</option>
                        <option value="pendente" <?= ($status === 'pendente') ? 'selected' : '' ?>>Pendente</option>
                        <option value="reprovado" <?= ($status === 'reprovado') ? 'selected' : '' ?>>Reprovado</option>
                    </select>
                </div>

                <!-- Filial -->
                <div class="col-6 col-md-3">
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

                <!-- Ações (reorganizado) -->
                <div class="col-12 col-md-4">
                    <button class="btn btn-sm btn-primary" type="submit" title="Aplicar filtros">
                        <i class="bx bx-filter-alt me-1"></i> Aplicar
                    </button>

                    <a class="btn btn-sm btn-outline-secondary" href="?id=<?= urlencode($idSelecionado) ?>" title="Limpar filtros">
                        <i class="bx bx-x me-1"></i> Limpar
                    </a>

                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-dark dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bx bx-download me-1"></i> Exportar
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><button class="dropdown-item" type="button"><i class="bx bx-file me-2"></i> XLSX</button></li>
                            <li><button class="dropdown-item" type="button"><i class="bx bx-data me-2"></i> CSV</button></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><button class="dropdown-item" type="button"><i class="bx bx-table me-2"></i> PDF (tabela)</button></li>
                        </ul>
                    </div>

                    <button class="btn btn-sm btn-outline-dark" type="button" onclick="window.print()" title="Imprimir">
                        <i class="bx bx-printer"></i>
                    </button>
                </div>

            </div>
        </form>
    </div>
</div>

<!-- ============================= -->
<!-- KPIs principais -->
<!-- ============================= -->
<div class="row">
    <!-- FATURAMENTO TOTAL -->
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card kpi-card">
            <div class="card-body">
                <div class="kpi-label">Faturamento (por Período)</div>
                <div class="kpi-value">R$ <?= number_format($totalGeralCards ?? 0, 2, ',', '.') ?></div>
                <div class="kpi-sub">Pedidos fechados</div>
            </div>
        </div>
    </div>

    <!-- APROVADO -->
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card kpi-card">
            <div class="card-body">
                <div class="kpi-label">Recebido (Aprovado)</div>
                <div class="kpi-value">R$ <?= number_format($cards['aprovado'] ?? 0, 2, ',', '.') ?></div>
                <div class="kpi-sub"><?= number_format(percentVal($cards['aprovado'] ?? 0, $totalGeralCards ?? 0),1,',','.') ?>% do total</div>
            </div>
        </div>
    </div>

    <!-- PENDENTE -->
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card kpi-card">
            <div class="card-body">
                <div class="kpi-label">Em Aberto (Pendente)</div>
                <div class="kpi-value">R$ <?= number_format($cards['pendente'] ?? 0, 2, ',', '.') ?></div>
                <div class="kpi-sub"><?= number_format(percentVal($cards['pendente'] ?? 0, $totalGeralCards ?? 0),1,',','.') ?>% do total</div>
            </div>
        </div>
    </div>

    <!-- REPROVADO -->
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card kpi-card">
            <div class="card-body">
                <div class="kpi-label">Reprovados</div>
                <div class="kpi-value">R$ <?= number_format($cards['reprovado'] ?? 0, 2, ',', '.') ?></div>
                <div class="kpi-sub"><?= number_format(percentVal($cards['reprovado'] ?? 0, $totalGeralCards ?? 0),1,',','.') ?>% do total</div>
            </div>
        </div>
    </div>
</div>

<!-- ============================= -->
<!-- Recebíveis por Status (sem paginação) -->
<!-- ============================= -->
<div class="card mb-3">
    <h5 class="card-header">Recebíveis por Status</h5>
    <div class="table-responsive">
        <table class="table table-hover align-middle text-nowrap">
            <thead>
                <tr>
                    <th>Status</th>
                    <th class="text-end">Quantidade</th>
                    <th class="text-end">Valor (R$)</th>
                    <th style="min-width:180px;">% do Total</th>
                </tr>
            </thead>
            <tbody>
                <?php
                    foreach (['aprovado','pendente','reprovado'] as $st) {
                        $q = $dados[$st]['quantidade'] ?? 0;
                        $v = $dados[$st]['valor'] ?? 0;
                        $p = ($totalGeralRecebiveis>0) ? ($v / $totalGeralRecebiveis) * 100 : 0;
                        $label = $st === 'aprovado' ? 'Pago' : ($st === 'pendente' ? 'Em Aberto' : 'Reprovado');
                        $badgeClass = $st === 'aprovado' ? 'bg-success text-white' : ($st === 'pendente' ? 'bg-warning text-dark' : 'bg-danger text-white');
                ?>
                <tr>
                    <td><span class="badge badge-soft <?= $badgeClass ?>"><?= $label ?></span></td>
                    <td class="text-end"><?= $q ?></td>
                    <td class="text-end">R$ <?= number_format($v,2,',','.') ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="flex-grow-1">
                                <div class="progress progress-skinny">
                                    <div class="progress-bar" style="width: <?= $p ?>%;" aria-valuenow="<?= $p ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                            <div style="width:58px;" class="text-end"><?= number_format($p,1,',','.') ?>%</div>
                        </div>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
            <tfoot>
                <tr>
                    <th>Total</th>
                    <th class="text-end"><?= ($dados['aprovado']['quantidade'] ?? 0) + ($dados['pendente']['quantidade'] ?? 0) + ($dados['reprovado']['quantidade'] ?? 0) ?></th>
                    <th class="text-end">R$ <?= number_format($totalGeralRecebiveis ?? 0,2,',','.') ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- ============================= -->
<!-- Fluxo de Caixa (Resumo) + pager -->
<!-- ============================= -->
<div class="card mb-3">
    <h5 class="card-header">Fluxo de Caixa — Resumo do Período</h5>
    <div class="table-responsive">
        <table class="table table-striped table-hover text-nowrap">
            <thead>
                <tr>
                    <th>Responsável</th>
                    <th class="text-end">Entradas (R$)</th>
                    <th class="text-end">Saídas (R$)</th>
                    <th class="text-end">Saldo (R$)</th>
                    <th class="text-end">Quantidade de Vnd</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($fluxo)) : ?>
                    <tr><td colspan="5" class="text-center text-muted">Nenhum caixa fechado de filial ativa encontrado.</td></tr>
                <?php else: ?>
                    <?php foreach ($fluxo as $f): ?>
                        <tr>
                            <td><?= h($f['responsavel']) ?> <small class="text-muted">/ <?= h($f['nome_filial']) ?></small></td>
                            <td class="text-end">R$ <?= number_format($f['valor_total'],2,',','.') ?></td>
                            <td class="text-end">R$ <?= number_format($f['valor_sangrias'],2,',','.') ?></td>
                            <td class="text-end">R$ <?= number_format($f['valor_liquido'],2,',','.') ?></td>
                            <td class="text-end"><?= (int)$f['quantidade_vendas'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th>Total</th>
                    <th class="text-end">R$ <?= number_format($totalEntradas,2,',','.') ?></th>
                    <th class="text-end">R$ <?= number_format($totalSaidas,2,',','.') ?></th>
                    <th class="text-end">R$ <?= number_format($totalSaldo,2,',','.') ?></th>
                    <th class="text-end"><?= $totalVendas ?></th>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="card-body pt-2">
        <?= renderPager($baseQueryParams, $page_fluxo ?? 1, $totalRowsFluxo ?? 0, $perPage, 'page_fluxo') ?>
    </div>
</div>

<!-- ============================= -->
<!-- Contas a Pagar (Futura) + pager -->
<!-- ============================= -->
<div class="card mb-3">
    <h5 class="card-header">Contas a pagar (Futura)</h5>
    <div class="table-responsive">
        <table class="table table-hover align-middle text-nowrap">
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
                <?php if (empty($contasFuturas)) : ?>
                    <tr><td colspan="7" class="text-center text-muted">Nenhuma conta futura de filial ativa encontrada.</td></tr>
                <?php else: ?>
                    <?php foreach ($contasFuturas as $c): ?>
                        <tr>
                            <td><?= h($c['id']) ?></td>
                            <td><?= h($c['nome_filial']) ?></td>
                            <td><?= h($c['descricao']) ?></td>
                            <td><?= date('d/m/Y', strtotime($c['datatransacao'])) ?></td>
                            <td class="text-end">R$ <?= number_format($c['valorpago'],2,',','.') ?></td>
                            <td><?= h($c['responsavel']) ?></td>
                            <td><span class="badge bg-warning text-dark">Futura</span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card-body pt-2">
        <?= renderPager($baseQueryParams, $page_contas_futuras ?? 1, $totalRowsContasFuturas ?? 0, $perPage, 'page_contas_futuras') ?>
    </div>
</div>

<!-- ============================= -->
<!-- Contas Pagas + pager -->
<!-- ============================= -->
<div class="card mb-3">
    <h5 class="card-header">Contas Pagas</h5>
    <div class="table-responsive">
        <table class="table table-hover align-middle text-nowrap">
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
                    <tr><td colspan="7" class="text-center text-muted">Nenhuma conta paga por filial ativa encontrada.</td></tr>
                <?php else: ?>
                    <?php foreach ($contasPagas as $c): ?>
                        <tr>
                            <td><?= h($c['id']) ?></td>
                            <td><?= h($c['nome_filial']) ?></td>
                            <td><?= h($c['descricao']) ?></td>
                            <td><?= date('d/m/Y', strtotime($c['datatransacao'])) ?></td>
                            <td class="text-end">R$ <?= number_format($c['valorpago'],2,',','.') ?></td>
                            <td><?= h($c['responsavel']) ?></td>
                            <td><span class="badge bg-success">Pago</span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card-body pt-2">
        <?= renderPager($baseQueryParams, $page_contas_pagas ?? 1, $totalRowsContasPagas ?? 0, $perPage, 'page_contas_pagas') ?>
    </div>
</div>

<!-- ============================= -->
<!-- Pagamentos por Filial — Aprovados + pager -->
<!-- ============================= -->
<div class="card mb-3">
    <h5 class="card-header">Pagamentos por Filial — Resumo</h5>
    <div class="table-responsive">
        <table class="table table-hover align-middle text-nowrap">
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
                    <tr><td colspan="6" class="text-center text-muted">Nenhum pagamento aprovado de filial ativa encontrado.</td></tr>
                <?php else: ?>
                    <?php foreach ($pagamentos as $pg): ?>
                        <tr>
                            <td><strong><?= h($pg['nome_filial']) ?></strong></td>
                            <td class="text-end">R$ <?= number_format($pg['valor'],2,',','.') ?></td>
                            <td class="text-end"><?= date('d/m/Y H:i', strtotime($pg['created_at'])) ?></td>
                            <td class="text-end"><?= date('d/m/Y', strtotime($pg['vencimento'])) ?></td>
                            <td class="text-end">
                                <?php if (!empty($pg['comprovante_url'])): ?>
                                    <a href="/assets/php/matriz/<?= h($pg['comprovante_url']) ?>" target="_blank">Abrir</a>
                                <?php else: ?>
                                    <span class="text-muted">Sem arquivo</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= h($pg['descricao']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th>Total</th>
                    <th class="text-end">R$ <?= number_format($totalGeralPagamentos ?? 0,2,',','.') ?></th>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="card-body pt-2">
        <?= renderPager($baseQueryParams, $page_pagamentos ?? 1, $totalRowsPagamentos ?? 0, $perPage, 'page_pagamentos') ?>
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
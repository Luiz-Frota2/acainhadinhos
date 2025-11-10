<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// Validar e obter 'id' (vindo da URL) – obrigatório
$idSelecionado = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_STRING) ?? '';
if (!$idSelecionado) {
    header("Location: .././login.php");
    exit;
}

// Verifica se a pessoa está logada e possui sessão necessária
if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id'])
) {
    header("Location: .././login.php?id=" . urlencode($idSelecionado));
    exit;
}

// Conexão com o banco de dados (garanta que este arquivo exista e retorne $pdo)
require '../../assets/php/conexao.php';

// Buscar nome e tipo do usuário logado
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id  = (int)($_SESSION['usuario_id'] ?? 0);

try {
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $usuario_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($usuario) {
        $nomeUsuario = $usuario['usuario'];
        $tipoUsuario = ucfirst($usuario['nivel']);
    } else {
        echo "<script>alert('Usuário não encontrado.'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar usuário: " . addslashes($e->getMessage()) . "'); history.back();</script>";
    exit;
}

// Valida o tipo de empresa e o acesso permitido
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

// Logo da empresa (fallback)
try {
    $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1");
    $stmt->execute([':id' => $idSelecionado]);
    $sobre = $stmt->fetch(PDO::FETCH_ASSOC);
    $logoEmpresa = !empty($sobre['imagem']) ? "../../assets/img/empresa/" . $sobre['imagem'] : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png";
}

/* ==========================================================
   Filtros (período + filial)
   ---------------------------------------------------------- */
// Recebe filtro de período via GET (ou mantém defaults)
$inicioFiltro = filter_input(INPUT_GET, 'inicio', FILTER_SANITIZE_STRING) ?? '';
$fimFiltro    = filter_input(INPUT_GET, 'fim', FILTER_SANITIZE_STRING) ?? '';
$filialSelecionada = filter_input(INPUT_GET, 'filial', FILTER_SANITIZE_STRING);
$periodo = filter_input(INPUT_GET, 'periodo', FILTER_SANITIZE_STRING) ?? 'month_current';

$hoje = new DateTime('today');
switch ($periodo) {
    case 'last30':
        $inicioPeriodDefault = (clone $hoje)->modify('-29 days')->setTime(0, 0, 0);
        $fimPeriodDefault    = (clone $hoje)->setTime(23, 59, 59);
        $tituloPeriodo = 'Últimos 30 dias';
        break;
    case 'last90':
        $inicioPeriodDefault = (clone $hoje)->modify('-89 days')->setTime(0, 0, 0);
        $fimPeriodDefault    = (clone $hoje)->setTime(23, 59, 59);
        $tituloPeriodo = 'Últimos 90 dias';
        break;
    case 'year':
        $inicioPeriodDefault = (new DateTime('first day of january ' . $hoje->format('Y')))->setTime(0, 0, 0);
        $fimPeriodDefault    = (clone $hoje)->setTime(23, 59, 59);
        $tituloPeriodo = 'Este ano';
        break;
    case 'month_current':
    default:
        $inicioPeriodDefault = (new DateTime('first day of this month'))->setTime(0, 0, 0);
        $fimPeriodDefault    = (new DateTime('last day of this month'))->setTime(23, 59, 59);
        $tituloPeriodo = 'Mês Atual';
        break;
}

// Se o usuário não passou datas via GET, usamos os defaults do período
if (empty($inicioFiltro)) {
    $inicioFiltro = $inicioPeriodDefault->format('Y-m-d');
}
if (empty($fimFiltro)) {
    $fimFiltro = $fimPeriodDefault->format('Y-m-d');
}

// Converter para datetimes completos para as consultas (formato MySQL)
try {
    $inicioDatetime = (new DateTime($inicioFiltro))->setTime(0,0,0)->format('Y-m-d H:i:s');
    $fimDatetime    = (new DateTime($fimFiltro))->setTime(23,59,59)->format('Y-m-d H:i:s');
} catch (Exception $e) {
    // Em caso de formato inválido, usar defaults
    $inicioDatetime = $inicioPeriodDefault->format('Y-m-d H:i:s');
    $fimDatetime = $fimPeriodDefault->format('Y-m-d H:i:s');
}

/* ==========================================================
   Carregar lista de filiais ativas (para select)
   ---------------------------------------------------------- */
try {
    $listaFiliais = $pdo->prepare("SELECT id, nome FROM unidades WHERE tipo = 'Filial' AND status = 'Ativa' ORDER BY nome");
    $listaFiliais->execute();
    $listaFiliais = $listaFiliais->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $listaFiliais = [];
}

/* ==========================================================
   Montar WHERE dinâmico (filial + período)
   - usamos placeholders e bind params para segurança
   ---------------------------------------------------------- */
$whereParts = [];
$params = [
    ':inicio' => $inicioDatetime,
    ':fim'    => $fimDatetime,
];

// período (aplica sempre)
$whereParts[] = "v.data_venda BETWEEN :inicio AND :fim";

// filial
// Se o usuário informou uma filial específica (value será o id numeric)
if ($filialSelecionada !== null && $filialSelecionada !== '') {
    // garantir que é inteiro (evita injeção)
    $filialId = (int)$filialSelecionada;
    $whereParts[] = "v.empresa_id LIKE :filial";
    $params[':filial'] = "%_" . $filialId;
} else {
    // quando não há filial selecionada, restringir aos IDs das filiais ativas obtidas
    if (!empty($listaFiliais)) {
        $orParts = [];
        foreach ($listaFiliais as $f) {
            $key = ':fil_' . intval($f['id']);
            $orParts[] = "v.empresa_id LIKE $key";
            $params[$key] = "%_" . intval($f['id']);
        }
        // juntar os ORs em um único grupo
        $whereParts[] = "(" . implode(" OR ", $orParts) . ")";
    } else {
        // Se não houver filiais ativas, garantir que a consulta não retorne vendas
        $whereParts[] = "1 = 0";
    }
}

$whereSQL = implode(" AND ", $whereParts);

/* ==========================================================
   Funções utilitárias
   ---------------------------------------------------------- */
function moeda($v)
{
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}
function inteiro($v)
{
    return number_format((int)$v, 0, ',', '.');
}

/* ==========================================================
   KPIs (Faturamento, Pedidos, Itens, Ticket Médio)
   ---------------------------------------------------------- */
try {
    $sqlKPI = "
        SELECT 
            COUNT(DISTINCT v.id) AS pedidos,
            COALESCE(SUM(v.valor_total),0) AS faturamento
        FROM vendas v
        WHERE $whereSQL
    ";
    $stm = $pdo->prepare($sqlKPI);
    $stm->execute($params);
    $kp = $stm->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $kp = ['pedidos' => 0, 'faturamento' => 0.0];
}

$pedidosTotal = (int)($kp['pedidos'] ?? 0);
$faturTotal = (float)($kp['faturamento'] ?? 0.0);

// Total de itens vendidos
try {
    $sqlItens = "
        SELECT COALESCE(SUM(iv.quantidade),0) AS total_itens
        FROM itens_venda iv
        INNER JOIN vendas v ON v.id = iv.venda_id
        WHERE $whereSQL
    ";
    $stm = $pdo->prepare($sqlItens);
    $stm->execute($params);
    $it = $stm->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $it = ['total_itens' => 0];
}

$itensTotal = (int)($it['total_itens'] ?? 0);

// Ticket médio (protegido contra divisão por zero)
$ticketMedio = ($pedidosTotal > 0) ? ($faturTotal / $pedidosTotal) : 0.0;

/* ==========================================================
   Resumo por Filial (calcula por cada filial aplicando período)
   ---------------------------------------------------------- */
$resumoFiliais = [];
try {
    foreach ($listaFiliais as $f) {
        $idFilial = intval($f['id']);

        // Se filtrando por uma filial e não é esta, pular
        if (!empty($filialSelecionada) && (string)$idFilial !== (string)$filialSelecionada) {
            continue;
        }

        // montar where específico: período + filial específica
        $partsLocal = [];
        $paramsLocal = [
            ':inicio' => $inicioDatetime,
            ':fim'    => $fimDatetime,
            ':fil_local' => "%_" . $idFilial
        ];

        $partsLocal[] = "v.data_venda BETWEEN :inicio AND :fim";
        $partsLocal[] = "v.empresa_id LIKE :fil_local";
        $whereLocal = implode(" AND ", $partsLocal);

        // Pedidos + faturamento por filial
        $sqlF = "
            SELECT 
                COUNT(DISTINCT v.id) AS pedidos,
                COALESCE(SUM(v.valor_total),0) AS faturamento
            FROM vendas v
            WHERE $whereLocal
        ";
        $stm = $pdo->prepare($sqlF);
        $stm->execute($paramsLocal);
        $r = $stm->fetch(PDO::FETCH_ASSOC);

        $ped = (int)($r['pedidos'] ?? 0);
        $fat = (float)($r['faturamento'] ?? 0.0);

        // Itens por filial
        $sqlItensFilial = "
            SELECT COALESCE(SUM(iv.quantidade),0) AS total_itens
            FROM itens_venda iv
            INNER JOIN vendas v ON v.id = iv.venda_id
            WHERE v.empresa_id LIKE :fil_local AND v.data_venda BETWEEN :inicio AND :fim
        ";
        $stm = $pdo->prepare($sqlItensFilial);
        $stm->execute($paramsLocal);
        $rowItens = $stm->fetch(PDO::FETCH_ASSOC);
        $totalItens = (int)($rowItens['total_itens'] ?? 0);

        $ticket = ($ped > 0) ? ($fat / $ped) : 0.0;

        $resumoFiliais[] = [
            "nome" => $f['nome'],
            "pedidos" => $ped,
            "itens" => $totalItens,
            "faturamento" => $fat,
            "ticket_medio" => $ticket
        ];
    }

    // calcular percentuais com base no faturamento total do conjunto
    $totalFat = array_sum(array_column($resumoFiliais, 'faturamento'));
    foreach ($resumoFiliais as &$linha) {
        $linha['percentual'] = ($totalFat > 0) ? ($linha['faturamento'] / $totalFat) * 100 : 0;
    }
    unset($linha);
} catch (PDOException $e) {
    $resumoFiliais = [];
}

/* ==========================================================
   Top produtos (LIMIT 5) filtrados pelo mesmo where (período + filial(s))
   ---------------------------------------------------------- */
try {
    $sqlTop = "
        SELECT 
            iv.produto_id AS sku,
            iv.produto_nome AS nome,
            COALESCE(SUM(iv.quantidade),0) AS total_quantidade,
            COUNT(DISTINCT iv.venda_id) AS total_pedidos
        FROM itens_venda iv
        INNER JOIN vendas v ON v.id = iv.venda_id
        WHERE $whereSQL
        GROUP BY iv.produto_id, iv.produto_nome
        ORDER BY total_quantidade DESC
        LIMIT 5
    ";
    $stm = $pdo->prepare($sqlTop);
    $stm->execute($params);
    $topProdutos = $stm->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $topProdutos = [];
}

/* ==========================================================
   Base para evitar divisão por zero (usado em percentuais visuais)
   ---------------------------------------------------------- */
$baseFaturamento = max(0.01, $faturTotal); // evita divisão por zero no cálculo de percentuais
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>ERP — Vendas por Filiais</title>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($logoEmpresa, ENT_QUOTES) ?>" />
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
        .table thead th { white-space: nowrap; }
        .toolbar { gap: .5rem; }
        .toolbar .form-select, .toolbar .form-control { max-width: 220px; }
        .kpi-card .kpi-label { font-size: .875rem; color: #667085; }
        .kpi-card .kpi-value { font-size: 1.4rem; font-weight: 700; }
        .kpi-card .kpi-sub { font-size: .825rem; color: #818181; }
        .progress-skinny { height: 8px; }
    </style>
</head>
<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- ASIDE -->
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
                    <li class="menu-item">
                        <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Dashboard</div>
                        </a>
                    </li>
                    <!-- restante do menu omitido por brevidade - mantenha o seu original -->
                </ul>
            </aside>
            <!-- /ASIDE -->

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
                                    <li><div class="dropdown-divider"></div></li>
                                    <li><a class="dropdown-item" href="./contaUsuario.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-user me-2"></i><span class="align-middle">Minha Conta</span></a></li>
                                    <li><a class="dropdown-item" href="#"><i class="bx bx-cog me-2"></i><span class="align-middle">Configurações</span></a></li>
                                    <li><div class="dropdown-divider"></div></li>
                                    <li><a class="dropdown-item" href="../logout.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-power-off me-2"></i><span class="align-middle">Sair</span></a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </nav>
                <!-- /Navbar -->

                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold mb-0">
                        <span class="text-muted fw-light"><a href="#">Filiais</a>/</span>
                        Vendas por Filiais
                    </h4>
                    <h5 class="fw-bold mt-3 mb-3 custor-font">
                        <span class="text-muted fw-light">Indicadores e comparativos por unidade franqueada — <?= htmlspecialchars($tituloPeriodo) ?></span>
                    </h5>

                    <!-- Filtros -->
                    <div class="card mb-3">
                        <div class="card-body d-flex flex-wrap toolbar">
                            <form class="d-flex flex-wrap w-100 gap-2" method="get">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($idSelecionado) ?>">

                                <div class="col-12 col-md-2">
                                    <label class="form-label">de</label>
                                    <input type="date" name="inicio" value="<?= htmlspecialchars(substr($inicioFiltro,0,10)) ?>" class="form-control form-control-sm">
                                </div>

                                <div class="col-12 col-md-2">
                                    <label class="form-label">até</label>
                                    <input type="date" name="fim" value="<?= htmlspecialchars(substr($fimFiltro,0,10)) ?>" class="form-control form-control-sm">
                                </div>

                                <select class="form-select me-2" name="filial">
                                    <option value="">Todas as Filiais</option>
                                    <?php foreach ($listaFiliais as $f): ?>
                                        <option value="<?= $f['id'] ?>" <?= ($filialSelecionada == $f['id'] ? 'selected' : '') ?>>
                                            <?= htmlspecialchars($f['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <button class="btn btn-outline-secondary me-2" type="submit">
                                    <i class="bx bx-filter-alt me-1"></i> Aplicar
                                </button>

                                <div class="ms-auto d-flex gap-2">
                                    <button class="btn btn-outline-dark" type="button" onclick="window.print()"><i class="bx bx-printer me-1"></i> Imprimir</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- KPIs -->
                    <div class="row">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card kpi-card">
                                <div class="card-body">
                                    <div class="kpi-label">Faturamento</div>
                                    <div class="kpi-value"><?= moeda($faturTotal) ?></div>
                                    <div class="kpi-sub"><?= htmlspecialchars($tituloPeriodo) ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card kpi-card">
                                <div class="card-body">
                                    <div class="kpi-label">Pedidos</div>
                                    <div class="kpi-value"><?= inteiro($pedidosTotal) ?></div>
                                    <div class="kpi-sub">Pedidos fechados</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card kpi-card">
                                <div class="card-body">
                                    <div class="kpi-label">Itens Vendidos</div>
                                    <div class="kpi-value"><?= inteiro($itensTotal) ?></div>
                                    <div class="kpi-sub">Qtde total</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card kpi-card">
                                <div class="card-body">
                                    <div class="kpi-label">Ticket Médio</div>
                                    <div class="kpi-value"><?= moeda($ticketMedio) ?></div>
                                    <div class="kpi-sub">Faturamento / Pedidos</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Resumo por Filial -->
                    <div class="card mb-3">
                        <h5 class="card-header">Resumo por Filial</h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Filial</th>
                                        <th class="text-end">Pedidos</th>
                                        <th class="text-end">Itens</th>
                                        <th class="text-end">Faturamento (R$)</th>
                                        <th class="text-end">Ticket Médio</th>
                                        <th style="min-width:180px;">% do Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resumoFiliais as $f): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($f["nome"]) ?></strong></td>
                                        <td class="text-end"><?= intval($f["pedidos"]) ?></td>
                                        <td class="text-end"><?= intval($f["itens"]) ?></td>
                                        <td class="text-end">R$ <?= number_format($f["faturamento"], 2, ',', '.') ?></td>
                                        <td class="text-end">R$ <?= number_format($f["ticket_medio"], 2, ',', '.') ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="flex-grow-1">
                                                    <div class="progress" style="height:8px;">
                                                        <div class="progress-bar"
                                                             role="progressbar"
                                                             style="width: <?= number_format($f["percentual"], 2) ?>%;"></div>
                                                    </div>
                                                </div>
                                                <div style="width:58px;" class="text-end">
                                                    <?= number_format($f["percentual"], 1, ',', '.') ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>

                                <tfoot>
                                    <tr>
                                        <th>Total</th>
                                        <th class="text-end"><?= array_sum(array_column($resumoFiliais, 'pedidos')) ?></th>
                                        <th class="text-end"><?= array_sum(array_column($resumoFiliais, 'itens')) ?></th>
                                        <th class="text-end">
                                            R$ <?= number_format(array_sum(array_column($resumoFiliais, 'faturamento')), 2, ',', '.') ?>
                                        </th>
                                        <th></th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Top Produtos -->
                    <div class="card mb-3">
                        <h5 class="card-header">Top Produtos no Período</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>SKU</th>
                                        <th>Produto</th>
                                        <th class="text-end">Quantidade</th>
                                        <th class="text-end">Pedidos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topProdutos as $p): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($p['sku']) ?></td>
                                        <td><?= htmlspecialchars($p['nome']) ?></td>
                                        <td class="text-end"><?= number_format($p['total_quantidade'], 0, ',', '.') ?></td>
                                        <td class="text-end"><?= intval($p['total_pedidos']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($topProdutos)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">Nenhum produto encontrado para o período selecionado.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div><!-- /container -->
            </div><!-- /Layout page -->
        </div><!-- /Layout container -->
    </div>

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

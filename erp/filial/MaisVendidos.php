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

/* ==========================================================
   🔎 Filtros — período + franquia (opcional)
   ---------------------------------------------------------- */
$periodo = $_GET['periodo'] ?? 'month_current';
$franquiaFiltro = isset($_GET['franquia_id']) && $_GET['franquia_id'] !== '' ? (int)$_GET['franquia_id'] : null;

$hoje = new DateTime('today');
switch ($periodo) {
    case 'last30':
        $inicio = (clone $hoje)->modify('-29 days')->setTime(0, 0, 0);
        $fim    = (clone $hoje)->setTime(23, 59, 59);
        $tituloPeriodo = 'Últimos 30 dias';
        break;
    case 'last90':
        $inicio = (clone $hoje)->modify('-89 days')->setTime(0, 0, 0);
        $fim    = (clone $hoje)->setTime(23, 59, 59);
        $tituloPeriodo = 'Últimos 90 dias';
        break;
    case 'year':
        $inicio = (new DateTime('first day of january ' . $hoje->format('Y')))->setTime(0, 0, 0);
        $fim    = (clone $hoje)->setTime(23, 59, 59);
        $tituloPeriodo = 'Este ano';
        break;
    case 'month_current':
    default:
        $inicio = (new DateTime('first day of this month'))->setTime(0, 0, 0);
        $fim    = (new DateTime('last day of this month'))->setTime(23, 59, 59);
        $tituloPeriodo = 'Mês Atual';
        break;
}

/* ==========================================================
   📋 Carrega franquias para o select
   ---------------------------------------------------------- */
try {
    $franquias = [];
    $sqlFranq = "SELECT id, nome FROM unidades WHERE tipo = 'Franquia' ORDER BY nome";
    $frq = $pdo->query($sqlFranq);
    $franquias = $frq->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $franquias = [];
}

/* ==========================================================
   🧮 Consultas de "Mais Vendidos"
   Tabelas assumidas:
   - vendas_peca (id, unidade_id, data_venda, status)
   - venda_itens_peca (id, venda_id, sku, nome_produto, quantidade, preco_unitario)
   - unidades (id, nome, tipo)
   ---------------------------------------------------------- */
$whereBase = " WHERE v.data_venda BETWEEN :inicio AND :fim
               AND v.status IN ('concluida','finalizada','paga') ";
$paramsBase = [
    ':inicio' => $inicio->format('Y-m-d H:i:s'),
    ':fim'    => $fim->format('Y-m-d H:i:s'),
];

$extraFranq = '';
if ($franquiaFiltro) {
    $extraFranq = " AND v.unidade_id = :franquia_id ";
    $paramsBase[':franquia_id'] = $franquiaFiltro;
}

// KPIs gerais
try {
    $sqlKpi = "
    SELECT 
      COUNT(DISTINCT v.id) AS pedidos,
      COALESCE(SUM(vi.quantidade),0) AS itens,
      COALESCE(SUM(vi.quantidade*vi.preco_unitario),0) AS faturamento
    FROM vendas_peca v
    JOIN venda_itens_peca vi ON vi.venda_id = v.id
    $whereBase $extraFranq
  ";
    $st = $pdo->prepare($sqlKpi);
    $st->execute($paramsBase);
    $kpis = $st->fetch(PDO::FETCH_ASSOC) ?: ['pedidos' => 0, 'itens' => 0, 'faturamento' => 0.0];
} catch (PDOException $e) {
    $kpis = ['pedidos' => 0, 'itens' => 0, 'faturamento' => 0.0];
}

// Top 20 (geral ou por franquia se filtrado)
try {
    $sqlTopGeral = "
    SELECT 
      vi.sku,
      vi.nome_produto,
      SUM(vi.quantidade) AS qtd_total,
      COUNT(DISTINCT v.id) AS pedidos,
      SUM(vi.quantidade*vi.preco_unitario) AS faturamento
    FROM vendas_peca v
    JOIN venda_itens_peca vi ON vi.venda_id = v.id
    $whereBase $extraFranq
    GROUP BY vi.sku, vi.nome_produto
    ORDER BY qtd_total DESC, faturamento DESC
    LIMIT 20
  ";
    $st = $pdo->prepare($sqlTopGeral);
    $st->execute($paramsBase);
    $topGeral = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $topGeral = [];
}

// Ranking por franquia (top 10 por franquia)
try {
    $sqlRankFranq = "
    SELECT 
      u.id AS franquia_id,
      u.nome AS franquia,
      vi.sku,
      vi.nome_produto,
      SUM(vi.quantidade) AS qtd_total,
      COUNT(DISTINCT v.id) AS pedidos
    FROM vendas_peca v
    JOIN unidades u          ON u.id = v.unidade_id AND u.tipo='Franquia'
    JOIN venda_itens_peca vi ON vi.venda_id = v.id
    $whereBase
    GROUP BY u.id,u.nome,vi.sku,vi.nome_produto
    HAVING SUM(vi.quantidade) > 0
    ORDER BY u.nome ASC, qtd_total DESC
  ";
    $st = $pdo->prepare($sqlRankFranq);
    $st->execute([
        ':inicio' => $paramsBase[':inicio'],
        ':fim'    => $paramsBase[':fim'],
    ]);
    $rankFranqRaw = $st->fetchAll(PDO::FETCH_ASSOC);

    // Limita 10 por franquia (lado PHP)
    $rankFranq = [];
    foreach ($rankFranqRaw as $r) {
        $fid = (int)$r['franquia_id'];
        if (!isset($rankFranq[$fid])) $rankFranq[$fid] = [];
        if (count($rankFranq[$fid]) < 10) $rankFranq[$fid][] = $r;
    }
} catch (PDOException $e) {
    $rankFranq = [];
}

// Helpers
function moeda($v)
{
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}
function inteiro($v)
{
    return number_format((int)$v, 0, ',', '.');
}

// Descobre “campeão” do período
$topSku = '-';
$topNome = '-';
$topQtd = 0;
if (!empty($topGeral)) {
    $topSku  = $topGeral[0]['sku'] ?? '-';
    $topNome = $topGeral[0]['nome_produto'] ?? '-';
    $topQtd  = (int)($topGeral[0]['qtd_total'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>ERP — Mais Vendidos</title>
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
    </style>
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">

            <!-- ====== ASIDE (mesmo padrão do seu menu) ====== -->
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

                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div>B2B - Matriz</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item"><a class="menu-link" href="./contasFiliais.php?id=<?= urlencode($idSelecionado); ?>">
                                    <div>Pagamentos Solic.</div>
                                </a></li>
                            <li class="menu-item"><a class="menu-link" href="./produtosSolicitados.php?id=<?= urlencode($idSelecionado); ?>">
                                    <div>Produtos Solicitados</div>
                                </a></li>
                            <li class="menu-item"><a class="menu-link" href="./produtosEnviados.php?id=<?= urlencode($idSelecionado); ?>">
                                    <div>Produtos Enviados</div>
                                </a></li>
                            <li class="menu-item"><a class="menu-link" href="./transferenciasPendentes.php?id=<?= urlencode($idSelecionado); ?>">
                                    <div>Transf. Pendentes</div>
                                </a></li>
                            <li class="menu-item"><a class="menu-link" href="./historicoTransferencias.php?id=<?= urlencode($idSelecionado); ?>">
                                    <div>Histórico Transf.</div>
                                </a></li>
                            <li class="menu-item"><a class="menu-link" href="./estoqueMatriz.php?id=<?= urlencode($idSelecionado); ?>">
                                    <div>Estoque Matriz</div>
                                </a></li>
                            <li class="menu-item"><a class="menu-link" href="./relatoriosB2B.php?id=<?= urlencode($idSelecionado); ?>">
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
                            <li class="menu-item active">
                                <a href="./MaisVendidos.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="MaisVendidos">Mais Vendidos</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./financeiroFilial.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Pedidos">Financeiro</div>
                                </a>
                            </li>

                        </ul>
                    </li>

                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
                    <li class="menu-item"><a class="menu-link" href="../rh/index.php?id=<?= urlencode($idSelecionado); ?>"><i class="menu-icon tf-icons bx bx-group"></i>
                            <div>RH</div>
                        </a></li>
                    <li class="menu-item"><a class="menu-link" href="../financas/index.php?id=<?= urlencode($idSelecionado); ?>"><i class="menu-icon tf-icons bx bx-dollar"></i>
                            <div>Finanças</div>
                        </a></li>
                    <li class="menu-item"><a class="menu-link" href="../pdv/index.php?id=<?= urlencode($idSelecionado); ?>"><i class="menu-icon tf-icons bx bx-desktop"></i>
                            <div>PDV</div>
                        </a></li>
                    <li class="menu-item"><a class="menu-link" href="../empresa/index.php?id=<?= urlencode($idSelecionado); ?>"><i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div>Empresa</div>
                        </a></li>
                    <li class="menu-item"><a class="menu-link" href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>"><i class="menu-icon tf-icons bx bx-box"></i>
                            <div>Estoque</div>
                        </a></li>
                    <li class="menu-item"><a class="menu-link" href="../filial/index.php?id=principal_1"><i class="menu-icon tf-icons bx bx-building"></i>
                            <div>Franquia</div>
                        </a></li>
                    <li class="menu-item"><a class="menu-link" href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>"><i class="menu-icon tf-icons bx bx-group"></i>
                            <div>Usuários</div>
                        </a></li>
                    <li class="menu-item"><a class="menu-link" target="_blank" href="https://wa.me/92991515710"><i class="menu-icon tf-icons bx bx-support"></i>
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
                        Mais Vendidos
                    </h4>
                    <h5 class="fw-bold mt-3 mb-3 custor-font">
                        <span class="text-muted fw-light">Produtos campeões — <?= htmlspecialchars($tituloPeriodo) ?><?= $franquiaFiltro ? ' · Franquia selecionada' : '' ?></span>
                    </h5>
                 <?php
// ---------------------------------------------
// CAPTURA DE FILTROS
// ---------------------------------------------
$inicioFiltro = $_GET['inicio'] ?? '';
$fimFiltro = $_GET['fim'] ?? '';
$filialSelecionada = $_GET['filial'] ?? '';
$idSelecionado = $_GET['id'] ?? '';

if (!$idSelecionado) {
    header("Location: ../login.php");
    exit;
}

// Se datas não enviadas → últimos 30 dias
if (empty($inicioFiltro) || empty($fimFiltro)) {
    $inicioFiltro = date('Y-m-d', strtotime('-30 days'));
    $fimFiltro = date('Y-m-d');
}

// ---------------------------------------------
// WHERE PADRÃO
// ---------------------------------------------
$where = "
    v.data_venda BETWEEN :inicio AND :fim
    AND u.tipo = 'Filial'
    AND u.status = 'Ativa'
";

$params = [
    ':inicio' => $inicioFiltro . ' 00:00:00',
    ':fim' => $fimFiltro . ' 23:59:59',
];

// Se selecionar filial → filtrar
if (!empty($filialSelecionada)) {
    $where .= " AND u.id = :filial";
    $params[':filial'] = $filialSelecionada;
}

// ---------------------------------------------
// LISTA DE FILIAIS ATIVAS
// ---------------------------------------------
$stmt = $pdo->prepare("
    SELECT id, nome 
    FROM unidades 
    WHERE tipo = 'Filial' AND status = 'Ativa'
");
$stmt->execute();
$listaFiliais = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------------------------------------------

// KPIs
// ---------------------------------------------
$kpis = [
    'itens' => 0,
    'pedidos' => 0,
    'faturamento' => 0,
    'produto_nome' => '',
    'produto_sku' => '',
    'produto_qtd' => 0
];

// ---------------------------------------------
// ITENS VENDIDOS
// ---------------------------------------------
$stmt = $pdo->prepare("
    SELECT SUM(iv.quantidade)
    FROM itens_venda iv
    INNER JOIN vendas v ON v.id = iv.venda_id
    INNER JOIN unidades u ON v.empresa_id = CONCAT('unidade_', u.id)
    WHERE $where
");
$stmt->execute($params);
$kpis['itens'] = $stmt->fetchColumn() ?: 0;

// ---------------------------------------------
// PEDIDOS
// ---------------------------------------------
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT v.id)
    FROM vendas v
    INNER JOIN unidades u ON v.empresa_id = CONCAT('unidade_', u.id)
    WHERE $where
");
$stmt->execute($params);
$kpis['pedidos'] = $stmt->fetchColumn() ?: 0;

// ---------------------------------------------
// FATURAMENTO
// ---------------------------------------------
$stmt = $pdo->prepare("
    SELECT SUM(v.valor_total)
    FROM vendas v
    INNER JOIN unidades u ON v.empresa_id = CONCAT('unidade_', u.id)
    WHERE $where
");
$stmt->execute($params);
$kpis['faturamento'] = $stmt->fetchColumn() ?: 0;

// ---------------------------------------------
// PRODUTO MAIS VENDIDO
// ---------------------------------------------
$stmt = $pdo->prepare("
    SELECT 
        e.nome_produto AS nome,
        e.codigo_produto AS sku,
        SUM(iv.quantidade) AS qtd
    FROM itens_venda iv
    INNER JOIN vendas v ON v.id = iv.venda_id
    INNER JOIN unidades u ON v.empresa_id = CONCAT('unidade_', u.id)
    INNER JOIN estoque e ON e.id = iv.produto_id
    WHERE $where
    GROUP BY e.id
    ORDER BY qtd DESC
    LIMIT 1
");
$stmt->execute($params);
$pmv = $stmt->fetch(PDO::FETCH_ASSOC);

if ($pmv) {
    $kpis['produto_nome'] = $pmv['nome'];
    $kpis['produto_sku'] = $pmv['sku'];
    $kpis['produto_qtd'] = $pmv['qtd'];
}

// ---------------------------------------------
// TOP 20 PRODUTOS
// ---------------------------------------------
$stmt = $pdo->prepare("
    SELECT 
        e.codigo_produto AS sku,
        e.nome_produto AS nome,
        SUM(iv.quantidade) AS total_quantidade,
        COUNT(DISTINCT v.id) AS total_pedidos,
        SUM(iv.quantidade * iv.preco_unitario) AS faturamento
    FROM itens_venda iv
    INNER JOIN vendas v ON v.id = iv.venda_id
    INNER JOIN unidades u ON v.empresa_id = CONCAT('unidade_', u.id)
    INNER JOIN estoque e ON e.id = iv.produto_id
    WHERE $where
    GROUP BY e.id
    ORDER BY total_quantidade DESC
    LIMIT 20
");
$stmt->execute($params);
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------------------------------------------
// RANKING POR FILIAL – TOP 10
// ---------------------------------------------
$stmt = $pdo->prepare("
    SELECT *
    FROM (
        SELECT 
            u.nome AS filial,
            e.codigo_produto AS sku,
            e.nome_produto AS produto,
            SUM(iv.quantidade) AS total_quantidade,
            COUNT(DISTINCT v.id) AS total_pedidos,
            ROW_NUMBER() OVER (
                PARTITION BY u.id
                ORDER BY SUM(iv.quantidade) DESC
            ) AS posicao
        FROM itens_venda iv
        INNER JOIN vendas v ON v.id = iv.venda_id
        INNER JOIN unidades u ON v.empresa_id = CONCAT('unidade_', u.id)
        INNER JOIN estoque e ON e.id = iv.produto_id
        WHERE $where
        GROUP BY u.id, u.nome, e.id
    ) AS t
    WHERE posicao <= 10
    ORDER BY filial, posicao
");
$stmt->execute($params);
$ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- ✅ MANTÉM SUA PÁGINA NORMAL -->
<!-- Seus filtros e tabelas ficam visíveis normalmente -->
<div class="card mb-3">
    <div class="card-body">
        <form class="d-flex flex-wrap w-100 gap-4 align-items-end" method="get">
            <input type="hidden" name="id" value="<?= htmlspecialchars($idSelecionado) ?>">

            <div class="col-12 col-md-2">
                <label class="form-label">De</label>
                <input type="date" name="inicio" value="<?= htmlspecialchars($inicioFiltro) ?>" class="form-control form-control-sm">
            </div>

            <div class="col-12 col-md-2">
                <label class="form-label">Até</label>
                <input type="date" name="fim" value="<?= htmlspecialchars($fimFiltro) ?>" class="form-control form-control-sm">
            </div>

            <div class="col-12 col-md-4">
                <label>Filial</label>
                <select class="form-select form-select-sm" name="filial">
                    <option value="">Todas as Filiais</option>
                    <?php foreach ($listaFiliais as $f): ?>
                        <option value="<?= $f['id'] ?>" <?= ($filialSelecionada == $f['id'] ? 'selected' : '') ?>>
                            <?= htmlspecialchars($f['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-primary" type="submit">
                    <i class="bx bx-filter-alt me-1"></i> Aplicar
                </button>
                <a href="?id=<?= urlencode($idSelecionado) ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bx bx-eraser me-1"></i> Limpar Filtro
                </a>
                <!-- 🔹 BOTÃO DE IMPRESSÃO -->
                <button class="btn btn-sm btn-outline-secondary" type="button" onclick="openPrintReport()">
                    <i class="bx bx-printer me-1"></i> Imprimir
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 🔹 CONTEÚDO NORMAL DA PÁGINA -->
<div id="tabelas-relatorio">
    <!-- Tudo o que o usuário vê normalmente -->
    <?php if ($nenhumResultado): ?>
        <div class="alert alert-warning text-center w-100">
            Nenhuma informação encontrada para o filtro aplicado.
        </div>
    <?php else: ?>
        <!-- Exemplo de tabela -->
        <div class="card mb-3">
            <h5 class="card-header">Top 20 Produtos (Geral)</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>SKU</th>
                            <th>Produto</th>
                            <th class="text-end">Qtd.</th>
                            <th class="text-end">Pedidos</th>
                            <th class="text-end">Faturamento (R$)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($produtos)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">Nenhum produto encontrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($produtos as $index => $p): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($p['sku']) ?></td>
                                    <td><?= htmlspecialchars($p['nome']) ?></td>
                                    <td class="text-end"><?= number_format($p['total_quantidade'], 0, ',', '.') ?></td>
                                    <td class="text-end"><?= number_format($p['total_pedidos'], 0, ',', '.') ?></td>
                                    <td class="text-end">R$ <?= number_format($p['faturamento'], 2, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ============================
     SCRIPT DE IMPRESSÃO
     ============================ -->
<script>
function openPrintReport() {
    try {
        // Captura o conteúdo que será impresso (somente a área das tabelas e KPIs)
        var reportHtml = document.getElementById('tabelas-relatorio').innerHTML;

        // Abre nova aba
        var win = window.open('', '_blank');
        if (!win) {
            alert('Bloqueador de pop-ups impediu a abertura da janela. Permita pop-ups e tente novamente.');
            return;
        }

        // CSS de impressão
        var style = `
            <style>
                @page { size: A4; margin: 18mm; }
                body { font-family: 'Public Sans', Arial, sans-serif; color: #111827; font-size: 12px; -webkit-print-color-adjust: exact; }
                table { width:100%; border-collapse:collapse; }
                th, td { padding:8px 10px; border:1px solid #e5e7eb; }
                thead th { background:#f3f4f6; }
                tr { page-break-inside: avoid; }
                h5.card-header { margin: 0; padding: 10px 0; font-size: 15px; }
            </style>
        `;

        // HTML final que vai pra nova aba
        var finalHtml = `
            <!doctype html>
            <html>
            <head>
                <meta charset="utf-8" />
                <title>Relatório — Vendas por Filial</title>
                <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
                ${style}
            </head>
            <body>
                ${reportHtml}
                <script>
                    window.focus();
                    setTimeout(function(){
                        window.print();
                    }, 300);
                    window.onafterprint = function() {
                        window.location.href = "MaisVendidos.php?id=principal_1";
                    };
                <\/script>
            </body>
            </html>
        `;

        // Escreve o conteúdo na nova janela
        win.document.open();
        win.document.write(finalHtml);
        win.document.close();
    } catch (err) {
        console.error(err);
        alert('Erro ao gerar o relatório para impressão: ' + err.message);
    }
}
</script>

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
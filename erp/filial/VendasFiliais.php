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
   🔎 Filtros (período + franquia) — server-side
   - periodo: month_current (default), last30, last90, year
   - franquia_id: opcional
   ---------------------------------------------------------- */
$periodo = $_GET['periodo'] ?? 'month_current';
$franquiaId = isset($_GET['franquia_id']) && $_GET['franquia_id'] !== '' ? (int)$_GET['franquia_id'] : null;

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
   📋 Carregar lista de franquias (select)
   (Ajuste os campos se sua tabela for diferente)
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
   📊 Consultas de Vendas (por período + franquia opcional)
   Tabelas assumidas:
   - vendas_peca (id, unidade_id, data_venda, status)
   - venda_itens_peca (id, venda_id, quantidade, preco_unitario)
   - unidades (id, nome, tipo='Franquia')
   Ajuste nomes/colunas conforme seu banco.
   ---------------------------------------------------------- */

// Filtros base
$where = " WHERE v.data_venda BETWEEN :inicio AND :fim
           AND v.status IN ('concluida','finalizada','paga') ";
$params = [
    ':inicio' => $inicio->format('Y-m-d H:i:s'),
    ':fim'    => $fim->format('Y-m-d H:i:s'),
];

if ($franquiaId) {
    $where .= " AND v.unidade_id = :franquia_id ";
    $params[':franquia_id'] = $franquiaId;
}

// Totais gerais do período
try {
    $sqlTotal = "
    SELECT 
      COUNT(DISTINCT v.id) AS pedidos,
      COALESCE(SUM(vi.quantidade),0) AS itens,
      COALESCE(SUM(vi.quantidade * vi.preco_unitario),0) AS faturamento
    FROM vendas_peca v
    JOIN venda_itens_peca vi ON vi.venda_id = v.id
    $where
  ";
    $stmt = $pdo->prepare($sqlTotal);
    $stmt->execute($params);
    $totais = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['pedidos' => 0, 'itens' => 0, 'faturamento' => 0.0];
} catch (PDOException $e) {
    $totais = ['pedidos' => 0, 'itens' => 0, 'faturamento' => 0.0];
}

// Por franquia (tabela principal)
try {
    $sqlPorFranq = "
    SELECT 
      u.id AS franquia_id,
      u.nome AS franquia,
      COUNT(DISTINCT v.id) AS pedidos,
      COALESCE(SUM(vi.quantidade),0) AS itens,
      COALESCE(SUM(vi.quantidade * vi.preco_unitario),0) AS faturamento
    FROM vendas_peca v
    JOIN unidades u          ON u.id = v.unidade_id AND u.tipo = 'Franquia'
    JOIN venda_itens_peca vi ON vi.venda_id = v.id
    $where
    GROUP BY u.id, u.nome
    ORDER BY faturamento DESC, pedidos DESC
  ";
    $stmt = $pdo->prepare($sqlPorFranq);
    $stmt->execute($params);
    $linhasFranq = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $linhasFranq = [];
}

// Produtos mais vendidos (TOP 10 do período — opcionalmente por franquia)
try {
    $sqlTopProd = "
    SELECT 
      vi.sku AS sku, 
      vi.nome_produto AS produto,
      SUM(vi.quantidade) AS qtd,
      COUNT(DISTINCT v.id) AS pedidos
    FROM vendas_peca v
    JOIN venda_itens_peca vi ON vi.venda_id = v.id
    $where
    GROUP BY vi.sku, vi.nome_produto
    ORDER BY qtd DESC
    LIMIT 10
  ";
    $stmt = $pdo->prepare($sqlTopProd);
    $stmt->execute($params);
    $topProdutos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $topProdutos = [];
}

// Helpers de formatação
function moeda($v)
{
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}
function inteiro($v)
{
    return number_format((int)$v, 0, ',', '.');
}

$pedidosTotal = (int)($totais['pedidos'] ?? 0);
$itensTotal   = (int)($totais['itens'] ?? 0);
$faturTotal   = (float)($totais['faturamento'] ?? 0.0);
$ticketMedio  = $pedidosTotal > 0 ? ($faturTotal / $pedidosTotal) : 0.0;

// Para % do total por linha
$baseFaturamento = max(0.01, $faturTotal); // evita divisão por zero
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>ERP — Vendas por Filiais</title>
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
    </style>
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">

            <!-- ====== ASIDE (igual ao seu padrão) ====== -->
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
                            <div data-i18n="Adicionar">Filiais</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./filialAdicionada.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Filiais">Adicionadas</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="menu-item ">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div data-i18n="B2B">B2B - Matriz</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./contasFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Pagamentos Solic.</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./produtosSolicitados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Produtos Solicitados</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./produtosEnviados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Produtos Enviados</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./transferenciasPendentes.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Transf. Pendentes</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./historicoTransferencias.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Histórico Transf.</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./estoqueMatriz.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Estoque Matriz</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./relatoriosB2B.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Relatórios B2B</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- Relatórios -->
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-bar-chart-alt-2"></i>
                            <div data-i18n="Relatorios">Relatórios</div>
                        </a>
                        <ul class="menu-sub">
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
                            <li class="menu-item">
                                <a href="./financeiroFillial.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Pedidos">Financeiro</div>
                                </a>
                            </li>

                        </ul>
                    </li>

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
                    <li class="menu-item">
                        <a href="../filial/index.php?id=principal_1" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-building"></i>
                            <div data-i18n="Authentications">Filial</div>
                        </a>
                    </li>
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
<?php
// -------------------------------
// CAPTURAR FILTROS
// -------------------------------
$inicioFiltro = isset($_GET['inicio']) ? $_GET['inicio'] : '';
$fimFiltro    = isset($_GET['fim']) ? $_GET['fim'] : '';
$filialSelecionada = isset($_GET['filial']) ? $_GET['filial'] : '';


// -------------------------------
// LISTAR FILIAIS ATIVAS
// -------------------------------
$listaFiliais = $pdo->query("
    SELECT id, nome 
    FROM unidades 
    WHERE tipo = 'Filial' AND status = 'Ativa'
")->fetchAll(PDO::FETCH_ASSOC);


// -------------------------------
// MONTAR WHERE DINÂMICO
// -------------------------------
$where = [];
$params = [];

// 1. FILTRO DE FILIAL
if ($filialSelecionada != '') {
    // Apenas 1 filial
    $where[] = "v.empresa_id LIKE :filial";
    $params[':filial'] = "%_" . $filialSelecionada;
} else {
    // Todas as filiais ativas
    $partes = [];
    foreach ($listaFiliais as $f) {
        $id = intval($f['id']);
        $partes[] = "v.empresa_id LIKE '%_$id'";
    }
    $where[] = "(" . implode(" OR ", $partes) . ")";
}

// 2. FILTRO DE DATA INÍCIO
if (!empty($inicioFiltro)) {
    $where[] = "DATE(v.data_venda) >= :inicio";
    $params[':inicio'] = $inicioFiltro;
}

// 3. FILTRO DE DATA FIM
if (!empty($fimFiltro)) {
    $where[] = "DATE(v.data_venda) <= :fim";
    $params[':fim'] = $fimFiltro;
}

$whereSQL = implode(" AND ", $where);


// -------------------------------
// KPIs (Faturamento, Pedidos, Itens, Ticket Médio)
// -------------------------------
$sqlKPI = "
    SELECT 
        COUNT(v.id) AS pedidos,
        SUM(v.valor_total) AS faturamento
    FROM vendas v
    WHERE $whereSQL
";
$stm = $pdo->prepare($sqlKPI);
$stm->execute($params);
$kp = $stm->fetch(PDO::FETCH_ASSOC);

$pedidosTotal = intval($kp['pedidos']);
$faturTotal = floatval($kp['faturamento']);


// === Total de itens vendidos
$sqlItens = "
    SELECT SUM(iv.quantidade) AS total_itens
    FROM itens_venda iv
    INNER JOIN vendas v ON v.id = iv.venda_id
    WHERE $whereSQL
";
$stm = $pdo->prepare($sqlItens);
$stm->execute($params);
$it = $stm->fetch(PDO::FETCH_ASSOC);

$itensTotal = intval($it['total_itens']);


// === Ticket médio
$ticketMedio = ($pedidosTotal > 0) ? ($faturTotal / $pedidosTotal) : 0;


// -------------------------------------------
// RESUMO POR FILIAL (com filtro aplicado)
// -------------------------------------------
$resumoFiliais = [];

foreach ($listaFiliais as $f) {

    // Se está filtrando por 1 filial → só calcula ela
    if ($filialSelecionada != '' && $filialSelecionada != $f['id']) {
        continue;
    }

    $idFilial = intval($f['id']);
    $nomeFilial = $f['nome'];

    // Filtro específico da filial
    $whereFilial = $whereSQL; // herda datas
    if ($filialSelecionada == '') {
        // Todas → substituir o OR gigante por filtro único por filial
        $whereFilial = str_replace($partes, "v.empresa_id LIKE '%_$idFilial'", $whereFilial);
    }

    // Pedidos + Faturamento
    $sqlF = "
        SELECT 
            COUNT(v.id) AS pedidos,
            SUM(v.valor_total) AS faturamento
        FROM vendas v
        WHERE " . ( $filialSelecionada == '' ? "v.empresa_id LIKE '%_$idFilial' AND " : "" ) . "
              (" . $whereSQL . ")
    ";

    $stm = $pdo->prepare($sqlF);
    $stm->execute($params);
    $r = $stm->fetch(PDO::FETCH_ASSOC);

    $ped = intval($r['pedidos']);
    $fat = floatval($r['faturamento']);

    // Itens
    $sqlItensFilial = "
        SELECT SUM(iv.quantidade) AS total_itens
        FROM itens_venda iv
        INNER JOIN vendas v ON v.id = iv.venda_id
        WHERE v.empresa_id LIKE '%_$idFilial' 
        AND $whereSQL
    ";
    $stm = $pdo->prepare($sqlItensFilial);
    $stm->execute($params);
    $rowItens = $stm->fetch(PDO::FETCH_ASSOC);

    $totalItens = intval($rowItens['total_itens']);

    // Ticket
    $ticket = ($ped > 0) ? $fat / $ped : 0;

    $resumoFiliais[] = [
        "nome" => $nomeFilial,
        "pedidos" => $ped,
        "itens" => $totalItens,
        "faturamento" => $fat,
        "ticket_medio" => $ticket
    ];
}


// Percentual total
$totalFat = array_sum(array_column($resumoFiliais, 'faturamento'));
foreach ($resumoFiliais as &$linha) {
    $linha['percentual'] = ($totalFat > 0) ? ($linha['faturamento'] / $totalFat) * 100 : 0;
}


// -------------------------------------------
// TOP 5 PRODUTOS (dinâmico com filtro)
// -------------------------------------------
$sqlTop = "
    SELECT 
        iv.produto_id AS sku,
        iv.produto_nome AS nome,
        SUM(iv.quantidade) AS total_quantidade,
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

?>

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

            <div class="col-12 col-md-2">
                <label class="form-label">de</label>
                <input type="date" name="inicio" value="<?= htmlspecialchars($inicioFiltro) ?>" class="form-control form-control-sm">
            </div>

            <div class="col-12 col-md-2">
                <label class="form-label">até</label>
                <input type="date" name="fim" value="<?= htmlspecialchars($fimFiltro) ?>" class="form-control form-control-sm">
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

<?php

// Buscar filiais ativas
$sqlFiliais = "SELECT id FROM unidades WHERE tipo = 'Filial' AND status = 'Ativa'";
$filiais = $pdo->query($sqlFiliais)->fetchAll(PDO::FETCH_COLUMN);

$faturTotal = 0;
$pedidosTotal = 0;
$itensTotal = 0;

// Monta filtros empresa_id LIKE '%_ID'
$condicoes = [];
foreach ($filiais as $id) {
    $condicoes[] = "v.empresa_id LIKE '%_" . intval($id) . "'";
}
$filtroFiliais = implode(" OR ", $condicoes);


// === TOTAL DE FATURAMENTO E PEDIDOS ===
$sqlFatur = "
    SELECT 
        COUNT(v.id) AS pedidos,
        SUM(v.valor_total) AS faturamento
    FROM vendas v
    WHERE $filtroFiliais
";

$stm = $pdo->query($sqlFatur);
$res = $stm->fetch(PDO::FETCH_ASSOC);

$pedidosTotal = intval($res['pedidos']);
$faturTotal = floatval($res['faturamento']);


// === TOTAL DE ITENS ===
$sqlItens = "
    SELECT SUM(iv.quantidade) AS total_itens
    FROM itens_venda iv
    INNER JOIN vendas v ON v.id = iv.venda_id
    WHERE $filtroFiliais
";

$stm = $pdo->query($sqlItens);
$resItens = $stm->fetch(PDO::FETCH_ASSOC);

$itensTotal = intval($resItens['total_itens']);


// === TICKET MÉDIO ===
$ticketMedio = ($pedidosTotal > 0)
    ? ($faturTotal / $pedidosTotal)
    : 0;

?>

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
                    <td><strong><?= $f["nome"] ?></strong></td>
                    <td class="text-end"><?= $f["pedidos"] ?></td>
                    <td class="text-end"><?= $f["itens"] ?></td>
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
                    <td class="text-end"><?= $p['total_pedidos'] ?></td>
                </tr>
                <?php endforeach; ?>
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
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
$usuario_id  = (int)$_SESSION['usuario_id'];

try {
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $nomeUsuario = $usuario['usuario'] ?? 'Usuário';
        $tipoUsuario = ucfirst((string)($usuario['nivel'] ?? 'Comum'));
    } else {
        echo "<script>alert('Usuário não encontrado.'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar usuário: " . htmlspecialchars($e->getMessage()) . "'); history.back();</script>";
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

    $logoEmpresa = (!empty($empresaSobre) && !empty($empresaSobre['imagem']))
        ? "../../assets/img/empresa/" . $empresaSobre['imagem']
        : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png"; // fallback
}

/* ==========================================================
   FILTROS DE PDV (período, caixa, forma, status NFC-e)
   ========================================================== */

function brToIsoDate($d)
{
    // aceita "YYYY-mm-dd" direto; se vier "dd/mm/YYYY", converte
    if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $d)) return $d;
    if (preg_match('~^(\d{2})/(\d{2})/(\d{4})$~', $d, $m)) {
        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }
    return null;
}

$periodo   = $_GET['periodo'] ?? 'hoje'; // hoje|ontem|ult7|mes|mes_anterior|custom
$dataIni   = $_GET['data_ini'] ?? '';
$dataFim   = $_GET['data_fim'] ?? '';
$caixaId   = isset($_GET['caixa_id']) && $_GET['caixa_id'] !== '' ? (int)$_GET['caixa_id'] : null;
$formaPag  = $_GET['forma_pagamento'] ?? '';
$statusNf  = $_GET['status_nfce'] ?? '';

$now = new DateTime('now');
$ini = new DateTime('today');
$ini->setTime(0, 0, 0);
$fim = new DateTime('today');
$fim->setTime(23, 59, 59);

switch ($periodo) {
    case 'ontem':
        $ini = (new DateTime('yesterday'))->setTime(0, 0, 0);
        $fim = (new DateTime('yesterday'))->setTime(23, 59, 59);
        break;
    case 'ult7':
        $ini = (new DateTime('today'))->modify('-6 days')->setTime(0, 0, 0);
        $fim = (new DateTime('today'))->setTime(23, 59, 59);
        break;
    case 'mes':
        $ini = (new DateTime('first day of this month'))->setTime(0, 0, 0);
        $fim = (new DateTime('last day of this month'))->setTime(23, 59, 59);
        break;
    case 'mes_anterior':
        $ini = (new DateTime('first day of last month'))->setTime(0, 0, 0);
        $fim = (new DateTime('last day of last month'))->setTime(23, 59, 59);
        break;
    case 'custom':
        $isoIni = brToIsoDate($dataIni);
        $isoFim = brToIsoDate($dataFim);
        if ($isoIni && $isoFim) {
            $ini = new DateTime($isoIni . ' 00:00:00');
            $fim = new DateTime($isoFim . ' 23:59:59');
        }
        break;
    case 'hoje':
    default:
        // já setado
        break;
}

// — Lista de caixas recentes (últimos 60 dias) para o filtro
$listaCaixas = [];
try {
    $st = $pdo->prepare("
    SELECT id, numero_caixa, responsavel, abertura_datetime, status
      FROM aberturas
     WHERE empresa_id = :empresa_id
       AND abertura_datetime >= DATE_SUB(NOW(), INTERVAL 60 DAY)
  ORDER BY abertura_datetime DESC
  ");
    $st->execute([':empresa_id' => $idSelecionado]);
    $listaCaixas = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}

/* ==========================================================
   MÉTRICAS PDV (usando os FILTROS)
   ========================================================== */

$caixaAtual = null; // opcionalmente mostramos info do caixa aberto mais recente
try {
    $st = $pdo->prepare("
    SELECT id, responsavel, numero_caixa, valor_abertura, valor_total, valor_sangrias, valor_suprimentos, valor_liquido,
           abertura_datetime, fechamento_datetime, quantidade_vendas, status, cpf_responsavel
      FROM aberturas
     WHERE empresa_id = :empresa_id
       AND status = 'aberto'
  ORDER BY abertura_datetime DESC
     LIMIT 1
  ");
    $st->execute([':empresa_id' => $idSelecionado]);
    $caixaAtual = $st->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}

$vendasQtd        = 0;
$vendasValor      = 0.00;
$vendasTroco      = 0.00;
$ticketMedio      = 0.00;
$pagamentoSeries  = []; // forma_pagamento => total
$vendasPorHora    = array_fill(0, 24, 0);
$topProdutos      = [];
$nfceStatusCont   = [];
$ultimasVendas    = [];

function bindPeriodo(&$params, DateTime $ini, DateTime $fim)
{
    $params[':ini'] = $ini->format('Y-m-d H:i:s');
    $params[':fim'] = $fim->format('Y-m-d H:i:s');
}

function mountWhere(string $empresaId, ?int $caixaId, string $forma, string $status, array &$params): string
{
    $where = " WHERE empresa_id = :empresa_id AND data_venda BETWEEN :ini AND :fim ";
    $params[':empresa_id'] = $empresaId;
    if (!empty($forma)) {
        $where .= " AND forma_pagamento = :forma_pagamento ";
        $params[':forma_pagamento'] = $forma;
    }
    if (!empty($status)) {
        $where .= " AND status_nfce = :status_nfce ";
        $params[':status_nfce'] = $status;
    }
    if (!empty($caixaId)) {
        $where .= " AND id_caixa = :id_caixa ";
        $params[':id_caixa'] = $caixaId;
    }
    return $where;
}

try {
    // 1) KPIs gerais do período
    $params = [];
    bindPeriodo($params, $ini, $fim);
    $whereV = mountWhere($idSelecionado, $caixaId, $formaPag, $statusNf, $params);

    $sql = "SELECT COUNT(*) AS qtd,
                 COALESCE(SUM(valor_total),0) AS soma_total,
                 COALESCE(SUM(troco),0) AS soma_troco
            FROM vendas
           $whereV";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    $vendasQtd   = (int)($r['qtd'] ?? 0);
    $vendasValor = (float)($r['soma_total'] ?? 0.0);
    $vendasTroco = (float)($r['soma_troco'] ?? 0.0);
    $ticketMedio = $vendasQtd > 0 ? ($vendasValor / $vendasQtd) : 0.0;

    // 2) Formas de pagamento (pizza)
    $params = [];
    bindPeriodo($params, $ini, $fim);
    $whereV = mountWhere($idSelecionado, $caixaId, $formaPag, $statusNf, $params);
    $sql = "SELECT forma_pagamento, COALESCE(SUM(valor_total),0) AS tot
            FROM vendas
           $whereV
        GROUP BY forma_pagamento";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $fp = $row['forma_pagamento'] ?: 'Outros';
        $pagamentoSeries[$fp] = (float)$row['tot'];
    }

    // 3) Vendas por hora
    $params = [];
    bindPeriodo($params, $ini, $fim);
    $whereV = mountWhere($idSelecionado, $caixaId, $formaPag, $statusNf, $params);
    $sql = "SELECT HOUR(data_venda) AS h, COUNT(*) AS qtd
            FROM vendas
           $whereV
        GROUP BY HOUR(data_venda)";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $h = (int)$row['h'];
        if ($h >= 0 && $h <= 23) $vendasPorHora[$h] = (int)$row['qtd'];
    }

    // 4) Top produtos por quantidade no período
    $params = [];
    bindPeriodo($params, $ini, $fim);
    // aplica também filtros de forma/status/caixa via tabela vendas
    $whereBase = " WHERE v.empresa_id = :empresa_id AND v.data_venda BETWEEN :ini AND :fim ";
    if (!empty($formaPag)) {
        $whereBase .= " AND v.forma_pagamento = :forma_pagamento ";
        $params[':forma_pagamento'] = $formaPag;
    }
    if (!empty($statusNf)) {
        $whereBase .= " AND v.status_nfce = :status_nfce ";
        $params[':status_nfce'] = $statusNf;
    }
    if (!empty($caixaId)) {
        $whereBase .= " AND v.id_caixa = :id_caixa ";
        $params[':id_caixa'] = $caixaId;
    }
    $params[':empresa_id'] = $idSelecionado;

    $sql = "SELECT iv.produto_nome,
                 SUM(iv.quantidade) AS qtd,
                 SUM(iv.quantidade * iv.preco_unitario) AS valor
            FROM itens_venda iv
            JOIN vendas v ON v.id = iv.venda_id
           $whereBase
        GROUP BY iv.produto_nome
        ORDER BY qtd DESC
           LIMIT 5";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $topProdutos = $st->fetchAll(PDO::FETCH_ASSOC);

    // 5) NFC-e por status no período
    $params = [];
    bindPeriodo($params, $ini, $fim);
    $whereV = mountWhere($idSelecionado, $caixaId, $formaPag, $statusNf, $params);
    $sql = "SELECT COALESCE(status_nfce,'sem_status') AS st, COUNT(*) AS qtd
            FROM vendas
           $whereV
        GROUP BY COALESCE(status_nfce,'sem_status')";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $nfceStatusCont[$row['st']] = (int)$row['qtd'];
    }

    // 6) Últimas vendas do período (5)
    $params = [];
    bindPeriodo($params, $ini, $fim);
    $whereV = mountWhere($idSelecionado, $caixaId, $formaPag, $statusNf, $params);
    $sql = "SELECT id, responsavel, forma_pagamento, valor_total, data_venda
            FROM vendas
           $whereV
        ORDER BY data_venda DESC
           LIMIT 5";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $ultimasVendas = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // mantém valores padrão
}

// ==== Dados para gráficos/labels
$labelsHoras = [];
for ($h = 0; $h < 24; $h++) {
    $labelsHoras[] = sprintf('%02d:00', $h);
}

$pagtoLabels = array_keys($pagamentoSeries);
$pagtoValues = array_values($pagamentoSeries);

$nfceLabels = array_keys($nfceStatusCont);
$nfceValues = array_values($nfceStatusCont);

$topProdLabels = [];
$topProdQtd    = [];
foreach ($topProdutos as $p) {
    $topProdLabels[] = $p['produto_nome'];
    $topProdQtd[]    = (int)$p['qtd'];
}

// Formatações úteis
function moneyBr($v)
{
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}
$periodoLabel = [
    'hoje' => 'Hoje',
    'ontem' => 'Ontem',
    'ult7' => 'Últimos 7 dias',
    'mes' => 'Mês atual',
    'mes_anterior' => 'Mês anterior',
    'custom' => 'Personalizado'
][$periodo] ?? 'Hoje';

$iniTxt = $ini->format('d/m/Y');
$fimTxt = $fim->format('d/m/Y');

?>

<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
    data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>ERP - Filial</title>

    <meta name="description" content="" />

    <!-- Favicon da empresa carregado dinamicamente -->
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
</style>

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

                    <!-- Administração de Filiais -->
                    <li class="menu-header small text-uppercase">
                        <span class="menu-header-text">Administração Filiais</span>
                    </li>

                    <!-- Adicionar Filial -->
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

                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div data-i18n="B2B">B2B - Matriz</div>
                        </a>
                        <ul class="menu-sub active">
                            <!-- Contas das Filiais -->
                            <li class="menu-item">
                                <a href="./contasFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Pagamentos Solic.</div>
                                </a>
                            </li>

                            <!-- Produtos solicitados pelas filiais -->
                            <li class="menu-item">
                                <a href="./produtosSolicitados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Produtos Solicitados</div>
                                </a>
                            </li>

                            <!-- Produtos enviados pela matriz -->
                            <li class="menu-item">
                                <a href="./produtosEnviados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Produtos Enviados</div>
                                </a>
                            </li>

                            <!-- Transferências em andamento -->
                            <li class="menu-item">
                                <a href="./transferenciasPendentes.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Transf. Pendentes</div>
                                </a>
                            </li>

                            <!-- Histórico de transferências -->
                            <li class="menu-item">
                                <a href="./historicoTransferencias.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Histórico Transf.</div>
                                </a>
                            </li>

                            <!-- Gestão de Estoque Central -->
                            <li class="menu-item">
                                <a href="./estoqueMatriz.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Estoque Matriz</div>
                                </a>
                            </li>

                            <!-- Relatórios e indicadores B2B -->
                            <li class="menu-item active">
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
                                <a href="./MaisVendidosFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="MaisVendidos">Mais Vendidos</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./vendasPeriodoFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Pedidos">Vendas por Período</div>
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
                        <a href="../franquia/index.php?id=principal_1" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-store"></i>
                            <div data-i18n="Authentications">Franquias</div>
                        </a>
                    </li>
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
                        <div class="navbar-nav align-items-center">
                            <div class="nav-item d-flex align-items-center">
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

                <!-- Content -->
                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold mb-0">
                        <span class="text-muted fw-light"><a href="#">Filiais</a>/</span>
                        Relatórios B2B
                    </h4>
                    <h5 class="fw-bold mt-3 mb-3 custor-font">
                        <span class="text-muted fw-light">Indicadores e resumos do canal B2B</span>
                    </h5>



                    <div class="card mb-3">
    <form method="get" class="card-body row g-3 align-items-end" autocomplete="off">
        <input type="hidden" name="id" >

        <div class="col-12 col-md-3">
           <select name="" id="" placeholder="filial"> <option value="filial do norte"></option></select>
        </div>

        <div class="col-12 col-md-2">
            <label class="form-label">de</label>
            <input type="date" name="codigo"  class="form-control form-control-sm">
        </div>

        <div class="col-12 col-md-2">
            <label class="form-label">até</label>
            <input type="date" name="categoria"  class="form-control form-control-sm" >
        </div>

        <div class="col-12 col-md-3 d-flex gap-2">
            <button class="btn btn-sm btn-primary"><i class="bx bx-filter-alt me-1"></i> Filtrar</button>
            <a  class="btn btn-sm btn-outline-secondary"><i class="bx bx-eraser me-1"></i> Limpar</a>
             
        </div>
    </form>
</div>


                  <?php
// ==========================================
// 1. FUNÇÃO QUE CALCULA OS DADOS DO PERÍODO
// ==========================================
function calcularPeriodo(PDO $pdo, $inicio, $fim)
{
    // ---- A) Busca IDS das FILIAIS ----
    $filiais = $pdo->query("SELECT id FROM unidades WHERE tipo = 'Filial'")
                   ->fetchAll(PDO::FETCH_COLUMN);

    if (!$filiais) {
        return [
            "pedidos" => 0,
            "itens" => 0,
            "faturamento" => 0,
            "ticket" => 0
        ];
    }

    // ex: unidade_3, unidade_7, unidade_9
    $filialKeys = array_map(fn($id) => "unidade_" . $id, $filiais);
    $inFiliais  = implode(",", array_fill(0, count($filialKeys), "?"));

    // ---- B) Pedidos B2B somente de FILIAIS ----
    $sqlPedidos = $pdo->prepare("
        SELECT id 
        FROM solicitacoes_b2b
        WHERE id_solicitante IN ($inFiliais)
        AND created_at BETWEEN ? AND ?
    ");
    $sqlPedidos->execute([...$filialKeys, $inicio, $fim]);

    $idsPedidos = $sqlPedidos->fetchAll(PDO::FETCH_COLUMN);
    $totalPedidos = count($idsPedidos);

    if ($totalPedidos == 0) {
        return [
            "pedidos" => 0,
            "itens" => 0,
            "faturamento" => 0,
            "ticket" => 0
        ];
    }

    // ---- C) Itens + Faturamento ----
    $inPedidos = implode(",", array_fill(0, count($idsPedidos), "?"));

    $sqlItens = $pdo->prepare("
        SELECT 
            SUM(quantidade) AS totalItens,
            SUM(subtotal) AS totalFaturamento
        FROM solicitacoes_b2b_itens
        WHERE solicitacao_id IN ($inPedidos)
    ");
    $sqlItens->execute($idsPedidos);
    $dados = $sqlItens->fetch(PDO::FETCH_ASSOC);

    $totalItens = $dados["totalItens"] ?? 0;
    $totalFaturamento = $dados["totalFaturamento"] ?? 0;

    // ---- D) Ticket Média ----
    $ticket = $totalPedidos > 0 ? ($totalFaturamento / $totalPedidos) : 0;

    return [
        "pedidos" => (int)$totalPedidos,
        "itens" => (int)$totalItens,
        "faturamento" => (float)$totalFaturamento,
        "ticket" => (float)$ticket,
    ];
}



// ==========================================
// 2. FUNÇÃO PARA CALCULAR VARIAÇÃO (%)
// ==========================================
function variacao($atual, $anterior)
{
    if ($anterior <= 0) return 0;
    return (($atual - $anterior) / $anterior) * 100;
}



// ==========================================
// 3. CALCULA O PERÍODO ATUAL E ANTERIOR
// ==========================================

// Você pode substituir por datas dinâmicas depois
$inicioAtual = "2025-11-01";
$fimAtual    = "2025-11-30";

$inicioAnterior = "2025-10-01";
$fimAnterior    = "2025-10-30";

$atual    = calcularPeriodo($pdo, $inicioAtual, $fimAtual);
$anterior = calcularPeriodo($pdo, $inicioAnterior, $fimAnterior);


// Prepara estrutura para tabela
$resumo = [
    "pedidos" => [
        "valor" => $atual["pedidos"],
        "var"   => variacao($atual["pedidos"], $anterior["pedidos"])
    ],
    "itens" => [
        "valor" => $atual["itens"],
        "var"   => variacao($atual["itens"], $anterior["itens"])
    ],
    "faturamento" => [
        "valor" => $atual["faturamento"],
        "var"   => variacao($atual["faturamento"], $anterior["faturamento"])
    ],
    "ticket" => [
        "valor" => $atual["ticket"],
        "var"   => variacao($atual["ticket"], $anterior["ticket"])
    ]
];
?>

<!-- ============================================== -->
<!-- 4. TABELA HTML — AGORA TOTALMENTE DINÂMICA -->
<!-- ============================================== -->

<div class="card mb-3">
    <h5 class="card-header">Resumo do Período</h5>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
            <tr>
                <th>Métrica</th>
                <th>Valor</th>
                <th>Variação</th>
                <th>Obs.</th>
            </tr>
            </thead>
            <tbody>

            <!-- Pedidos -->
            <tr>
                <td>Pedidos B2B</td>
                <td><?= $resumo["pedidos"]["valor"] ?></td>
                <td>
                    <span class="<?= $resumo["pedidos"]["var"] >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= number_format($resumo["pedidos"]["var"], 1, ',', '.') ?>%
                    </span>
                </td>
                <td>Somente solicitações feitas por filiais</td>
            </tr>

            <!-- Itens -->
            <tr>
                <td>Itens Solicitados</td>
                <td><?= $resumo["itens"]["valor"] ?></td>
                <td>
                    <span class="<?= $resumo["itens"]["var"] >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= number_format($resumo["itens"]["var"], 1, ',', '.') ?>%
                    </span>
                </td>
                <td>Total somado dos itens solicitados</td>
            </tr>

            <!-- Faturamento -->
            <tr>
                <td>Faturamento Estimado</td>
                <td>R$ <?= number_format($resumo["faturamento"]["valor"], 2, ',', '.') ?></td>
                <td>
                    <span class="<?= $resumo["faturamento"]["var"] >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= number_format($resumo["faturamento"]["var"], 1, ',', '.') ?>%
                    </span>
                </td>
                <td>Subtotal total</td>
            </tr>

            <!-- Ticket Médio -->
            <tr>
                <td>Ticket Médio</td>
                <td>R$ <?= number_format($resumo["ticket"]["valor"], 2, ',', '.') ?></td>
                <td>
                    <span class="<?= $resumo["ticket"]["var"] >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= number_format($resumo["ticket"]["var"], 1, ',', '.') ?>%
                    </span>
                </td>
                <td>Faturamento / número de pedidos</td>
            </tr>

            </tbody>
        </table>
    </div>
</div>
<?php
// =========================================================
// 1. BUSCAR TODAS AS FILIAIS
// =========================================================
$sqlFiliais = $pdo->query("
    SELECT id, nome
    FROM unidades
    WHERE tipo = 'Filial'
");
$filiais = $sqlFiliais->fetchAll(PDO::FETCH_ASSOC);

// =========================================================
// 2. CALCULAR VENDAS POR FILIAL (USANDO 'unidade_{id}')
// =========================================================

$listaFiliais = [];
$totalFaturamentoGeral = 0;

// Paginação
$itensPorPagina = 5;
$paginaAtual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;

foreach ($filiais as $f) {

    // Monta a chave que existe em vendas.empresa_id e solicitacoes_b2b.id_solicitante
    $empresaKey = "unidade_" . $f["id"]; // EX: 'unidade_3'
    $nomeFilial = $f["nome"];

    // Buscar total de pedidos (número de vendas da filial)
    $sqlV = $pdo->prepare("
        SELECT 
            COUNT(*) AS pedidos,
            SUM(valor_total) AS total_faturamento
        FROM vendas
        WHERE empresa_id = ?
        AND data_venda BETWEEN ? AND ?
    ");
    $sqlV->execute([$empresaKey, $inicioAtual, $fimAtual]);
    $dados = $sqlV->fetch(PDO::FETCH_ASSOC);

    $pedidos = (int)($dados["pedidos"] ?? 0);
    $faturamento = (float)($dados["total_faturamento"] ?? 0.0);

    // Buscar total de itens vendidos (somando itens_venda via join com vendas)
    $sqlItens = $pdo->prepare("
        SELECT SUM(iv.quantidade) AS total_itens
        FROM itens_venda iv
        INNER JOIN vendas v ON v.id = iv.venda_id
        WHERE v.empresa_id = ?
        AND v.data_venda BETWEEN ? AND ?
    ");
    $sqlItens->execute([$empresaKey, $inicioAtual, $fimAtual]);
    $dadosItens = $sqlItens->fetch(PDO::FETCH_ASSOC);

    $itens = (int)($dadosItens["total_itens"] ?? 0);

    // Ticket médio
    $ticket = ($pedidos > 0) ? ($faturamento / $pedidos) : 0;

    // Acumular faturamento geral
    $totalFaturamentoGeral += $faturamento;

    // Armazenar dados da filial
    $listaFiliais[] = [
        "nome" => $nomeFilial,
        "pedidos" => $pedidos,
        "itens" => $itens,
        "faturamento" => $faturamento,
        "ticket" => $ticket
    ];
}

// =========================================================
// 3. CALCULAR % DO TOTAL PARA CADA FILIAL
// =========================================================
foreach ($listaFiliais as $i => $f) {
    $perc = ($totalFaturamentoGeral > 0)
        ? ($f["faturamento"] / $totalFaturamentoGeral) * 100
        : 0;

    $listaFiliais[$i]["perc"] = $perc;
}

// =========================================================
// 4. PAGINAÇÃO - CORTAR ARRAY EM PEDAÇOS
// =========================================================
$totalRegistros = count($listaFiliais);
$totalPaginas = max(1, ceil($totalRegistros / $itensPorPagina));

$offset = ($paginaAtual - 1) * $itensPorPagina;
$listaPaginada = array_slice($listaFiliais, $offset, $itensPorPagina);
?>



<!-- ========================================================= -->
<!-- TABELA: VENDAS / PEDIDOS POR FILIAL                      -->
<!-- ========================================================= -->

<div class="card mb-3">
    <h5 class="card-header">Vendas / Pedidos por Filial</h5>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
            <tr>
                <th>Filial</th>
                <th>Pedidos</th>
                <th>Itens</th>
                <th>Faturamento (R$)</th>
                <th>Ticket Médio (R$)</th>
                <th>% do Total</th>
            </tr>
            </thead>
            <tbody>

            <?php foreach ($listaPaginada as $f): ?>

                <tr>
                    <td><strong><?= htmlspecialchars($f["nome"]) ?></strong></td>

                    <td><?= $f["pedidos"] ?></td>

                    <td><?= $f["itens"] ?></td>

                    <td>R$ <?= number_format($f["faturamento"], 2, ',', '.') ?></td>

                    <td>R$ <?= number_format($f["ticket"], 2, ',', '.') ?></td>

                    <td><?= number_format($f["perc"], 1, ',', '.') ?>%</td>
                </tr>
            <?php endforeach; ?>

            </tbody>
        </table>
        <!-- ========================================= -->
<!-- PAGINAÇÃO                                 -->
<!-- ========================================= -->
<!-- ========================================= -->
<!-- PAGINAÇÃO MINIMALISTA                     -->
<!-- ========================================= -->

<div class="d-flex justify-content-center mt-2">
    <nav>
        <ul class="pagination pagination-sm m-0">

            <!-- Botão Anterior -->
            <li class="page-item <?= ($paginaAtual <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="?pagina=<?= $paginaAtual - 1 ?>" tabindex="-1">Anterior</a>
            </li>

            <!-- Página atual (não clicável) -->
            <li class="page-item active">
                <span class="page-link"><?= $paginaAtual ?></span>
            </li>

            <!-- Botão Próxima -->
            <li class="page-item <?= ($paginaAtual >= $totalPaginas) ? 'disabled' : '' ?>">
                <a class="page-link" href="?pagina=<?= $paginaAtual + 1 ?>">Próximo</a>
            </li>

        </ul>
    </nav>
</div>



    </div>
</div>
<?php
// ==================================================================
// 1. BUSCAR IDS DE TODAS AS FILIAIS
// ==================================================================
$sqlFiliais = $pdo->query("
    SELECT id 
    FROM unidades 
    WHERE tipo = 'Filial'
");
$filiais = $sqlFiliais->fetchAll(PDO::FETCH_COLUMN);

if (!empty($filiais)) {

    // Converte para "unidade_X"
    $filialKeys = array_map(fn($id) => "unidade_" . $id, $filiais);
    $inFiliais = implode(",", array_fill(0, count($filialKeys), "?"));

    // ==================================================================
    // 2. PEGAR TODAS AS SOLICITAÇÕES B2B REALIZADAS POR FILIAIS
    // ==================================================================
    $sqlSolic = $pdo->prepare("
        SELECT id
        FROM solicitacoes_b2b
        WHERE id_solicitante IN ($inFiliais)
        AND created_at BETWEEN ? AND ?
    ");

    $sqlSolic->execute([...$filialKeys, $inicioAtual, $fimAtual]);
    $solicitacoesIds = $sqlSolic->fetchAll(PDO::FETCH_COLUMN);

    $produtosLista = [];

    if (!empty($solicitacoesIds)) {

        // ==================================================================
        // 3. PEGAR PRODUTOS MAIS SOLICITADOS (AGRUPADOS)
        // ==================================================================
        $inSolic = implode(",", array_fill(0, count($solicitacoesIds), "?"));

     $sqlItens = $pdo->prepare("
    SELECT 
        codigo_produto,
        nome_produto,
        SUM(quantidade) AS total_quantidade,
        COUNT(DISTINCT solicitacao_id) AS total_pedidos
    FROM solicitacoes_b2b_itens
    WHERE solicitacao_id IN ($inSolic)
    GROUP BY codigo_produto, nome_produto
    ORDER BY total_quantidade DESC
    LIMIT 5
");

        $sqlItens->execute($solicitacoesIds);
        $produtosLista = $sqlItens->fetchAll(PDO::FETCH_ASSOC);

        // ==================================================================
        // 4. CALCULAR PARTICIPAÇÃO
        // ==================================================================
        $totalGeral = array_sum(array_column($produtosLista, "total_quantidade"));

        foreach ($produtosLista as $i => $prod) {
            $perc = $totalGeral > 0 
                ? ($prod["total_quantidade"] / $totalGeral) * 100 
                : 0;

            $produtosLista[$i]["perc"] = $perc;
        }
    }
}
?>


<!-- ================================================================== -->
<!-- TABELA: PRODUTOS MAIS SOLICITADOS (FILIAIS) -->
<!-- ================================================================== -->

<div class="card mb-3">
    <h5 class="card-header">Produtos Mais Solicitados</h5>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
            <tr>
                <th>SKU</th>
                <th>Produto</th>
                <th>Quantidade</th>
                <th>Pedidos</th>
                <th>Participação</th>
            </tr>
            </thead>
            <tbody>

            <?php if (!empty($produtosLista)): ?>
                <?php foreach ($produtosLista as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p["codigo_produto"]) ?></td>
                        <td><?= htmlspecialchars($p["nome_produto"]) ?></td>

                        <td><?= (int)$p["total_quantidade"] ?></td>

                        <td><?= (int)$p["total_pedidos"] ?></td>

                        <td><?= number_format($p["perc"], 1, ',', '.') ?>%</td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="text-center">Nenhum produto solicitado por filiais no período.</td>
                </tr>
            <?php endif; ?>

            </tbody>
        </table>
    </div>
</div>

                   
                 <?php
// ========================================================================
// 1. BUSCAR IDS DAS FILIAIS
// ========================================================================
$sqlFiliais = $pdo->query("
    SELECT id 
    FROM unidades
    WHERE tipo = 'Filial'
");
$filiais = $sqlFiliais->fetchAll(PDO::FETCH_COLUMN);

$pagSolicitadosQtd = 0;
$pagSolicitadosValor = 0;

$remessasEnviadas = 0;
$remessasConcluidas = 0;

if (!empty($filiais)) {

    // Converte ids para formato unidade_X
    $filialKeys = array_map(fn($id) => "unidade_" . $id, $filiais);
    $inFiliais = implode(",", array_fill(0, count($filialKeys), "?"));

    // ========================================================================
    // 2. PEGAR PAGAMENTOS SOLICITADOS (status pendente)
    // ========================================================================
    $sqlPendentes = $pdo->prepare("
        SELECT COUNT(*) AS qtd, SUM(valor) AS total
        FROM solicitacoes_pagamento
        WHERE id_solicitante IN ($inFiliais)
        AND status = 'pendente'
        AND created_at BETWEEN ? AND ?
    ");
    $sqlPendentes->execute([...$filialKeys, $inicioAtual, $fimAtual]);
    $pend = $sqlPendentes->fetch(PDO::FETCH_ASSOC);

    $pagSolicitadosQtd = (int)$pend["qtd"];
    $pagSolicitadosValor = (float)($pend["total"] ?? 0);


    // ========================================================================
    // 3. REMESSAS ENVIADAS (status aprovado)
    // ========================================================================
    $sqlAprovado = $pdo->prepare("
        SELECT COUNT(*) 
        FROM solicitacoes_pagamento
        WHERE id_solicitante IN ($inFiliais)
        AND status = 'aprovado'
        AND created_at BETWEEN ? AND ?
    ");
    $sqlAprovado->execute([...$filialKeys, $inicioAtual, $fimAtual]);
    $remessasEnviadas = (int)$sqlAprovado->fetchColumn();


    // ========================================================================
    // 4. REMESSAS CONCLUÍDAS (status reprovado)
    // ========================================================================
    $sqlReprovado = $pdo->prepare("
        SELECT COUNT(*) 
        FROM solicitacoes_pagamento
        WHERE id_solicitante IN ($inFiliais)
        AND status = 'reprovado'
        AND created_at BETWEEN ? AND ?
    ");
    $sqlReprovado->execute([...$filialKeys, $inicioAtual, $fimAtual]);
    $remessasConcluidas = (int)$sqlReprovado->fetchColumn();
}
?>


<!-- ======================================================================== -->
<!-- TABELA: PAGAMENTOS X ENTREGAS (RESUMO)                                   -->
<!-- ======================================================================== -->

<?php
// ========================================================================
// 1. BUSCAR IDS DAS FILIAIS
// ========================================================================
$sqlFiliais = $pdo->query("SELECT id FROM unidades WHERE tipo = 'Filial'");
$filiais = $sqlFiliais->fetchAll(PDO::FETCH_COLUMN);

$pendQtd = $pendValor = 0;
$aprovQtd = $aprovValor = 0;
$reprovQtd = $reprovValor = 0;

if (!empty($filiais)) {

    $filialKeys = array_map(fn($id) => "unidade_" . $id, $filiais);
    $inFiliais = implode(",", array_fill(0, count($filialKeys), "?"));

    // ========================================================================
    // 2. PAGAMENTOS SOLICITADOS (pendente)
    // ========================================================================
    $sqlPend = $pdo->prepare("
        SELECT COUNT(*) AS qtd, SUM(valor) AS total
        FROM solicitacoes_pagamento
        WHERE id_solicitante IN ($inFiliais)
        AND status = 'pendente'
        AND created_at BETWEEN ? AND ?
    ");
    $sqlPend->execute([...$filialKeys, $inicioAtual, $fimAtual]);
    $r = $sqlPend->fetch(PDO::FETCH_ASSOC);

    $pendQtd = (int)$r["qtd"];
    $pendValor = (float)($r["total"] ?? 0);

    // ========================================================================
    // 3. PAGAMENTOS APROVADOS (aprovado)
    // ========================================================================
    $sqlAprov = $pdo->prepare("
        SELECT COUNT(*) AS qtd, SUM(valor) AS total
        FROM solicitacoes_pagamento
        WHERE id_solicitante IN ($inFiliais)
        AND status = 'aprovado'
        AND created_at BETWEEN ? AND ?
    ");
    $sqlAprov->execute([...$filialKeys, $inicioAtual, $fimAtual]);
    $r = $sqlAprov->fetch(PDO::FETCH_ASSOC);

    $aprovQtd = (int)$r["qtd"];
    $aprovValor = (float)($r["total"] ?? 0);

    // ========================================================================
    // 4. PAGAMENTOS REPROVADOS (reprovado)
    // ========================================================================
    $sqlReprov = $pdo->prepare("
        SELECT COUNT(*) AS qtd, SUM(valor) AS total
        FROM solicitacoes_pagamento
        WHERE id_solicitante IN ($inFiliais)
        AND status = 'reprovado'
        AND created_at BETWEEN ? AND ?
    ");
    $sqlReprov->execute([...$filialKeys, $inicioAtual, $fimAtual]);
    $r = $sqlReprov->fetch(PDO::FETCH_ASSOC);

    $reprovQtd = (int)$r["qtd"];
    $reprovValor = (float)($r["total"] ?? 0);
}
?>


<div class="card mb-3">
    <h5 class="card-header">Pagamentos x Entregas (Resumo)</h5>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Métrica</th>
                    <th>Quantidade</th>
                    <th>Valor (R$)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <!-- Solicitados -->
                <tr>
                    <td>Pagamentos Solicitados</td>
                    <td><?= $pendQtd ?></td>
                    <td>R$ <?= number_format($pendValor, 2, ',', '.') ?></td>
                    <td>Pendente</td>
                </tr>

                <!-- Aprovados -->
                <tr>
                    <td>Remessa Concluida</td>
                    <td><?= $aprovQtd ?></td>
                    <td>R$ <?= number_format($aprovValor, 2, ',', '.') ?></td>
                    <td>Aprovado</td>
                </tr>

                <!-- Reprovados -->
                <tr>
                    <td>Remessa Reprovada</td>
                    <td><?= $reprovQtd ?></td>
                    <td>R$ <?= number_format($reprovValor, 2, ',', '.') ?></td>
                    <td>Reprovado</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>


                </div>
                <!-- / Content -->

                <!-- Footer -->
                <footer class="content-footer footer bg-footer-theme text-center">
                    <div class="container-xxl d-flex  py-2 flex-md-row flex-column justify-content-center">
                        <div class="mb-2 mb-md-0">
                            &copy;
                            <script>
                                document.write(new Date().getFullYear());
                            </script>
                            , <strong>Açaínhadinhos</strong>. Todos os direitos reservados.
                            Desenvolvido por <strong>CodeGeek</strong>.
                        </div>
                    </div>
                </footer>

                <!-- / Footer -->

                <div class="content-backdrop fade"></div>
            </div>
            <!-- Content wrapper -->
        </div>
        <!-- / Layout page -->

    </div>

    <!-- Overlay -->
    <div class="layout-overlay layout-menu-toggle"></div>
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
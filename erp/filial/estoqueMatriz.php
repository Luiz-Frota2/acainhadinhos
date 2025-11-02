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
                            <li class="menu-item active">
                                <a href="./estoqueMatriz.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Estoque Matriz</div>
                                </a>
                            </li>

                            <!-- Relatórios e indicadores B2B -->
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

                <!-- / Navbar -->

                <!-- Content -->
                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold mb-0">
                        <span class="text-muted fw-light"><a href="#">Filial</a>/</span>
                        Estoque Matriz
                    </h4>
                    <h5 class="fw-bold mt-3 mb-3 custor-font">
                        <span class="text-muted fw-light">Visão geral do estoque central</span>
                    </h5>
                    <?php
// ✅ Buscar dados reais do estoque da empresa atual
try {

    // 1) Quantidade de códigos de produtos ativos
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total_produtos
        FROM estoque
        WHERE empresa_id = :empresa
    ");
    $stmt->execute([':empresa' => $idSelecionado]);
    $card1 = (int)$stmt->fetchColumn();

    // 2) Soma da quantidade disponível
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(quantidade_produto), 0) AS total_quantidade
        FROM estoque
        WHERE empresa_id = :empresa
    ");
    $stmt->execute([':empresa' => $idSelecionado]);
    $card2 = (int)$stmt->fetchColumn();

    // 3 e 4) Seu banco ainda não possui colunas de reservado e transferência
    // então deixo 0 até criarmos essas funções
    $card3 = 0; // Reservado
    $card4 = 0; // Em transferência

} catch (PDOException $e) {
    $card1 = $card2 = $card3 = $card4 = 0;
}
?>


                  <!-- Cards resumo -->
<div class="row g-3 mb-3">
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="mb-1 text-muted">Código Produto ativos</p>
                        <h4 class="mb-0"><?= number_format($card1, 0, ',', '.') ?></h4>
                    </div>
                    <i class="bx bx-box fs-2 text-primary"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="mb-1 text-muted">Qtde disponível</p>
                        <h4 class="mb-0"><?= number_format($card2, 0, ',', '.') ?></h4>
                    </div>
                    <i class="bx bx-package fs-2 text-success"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="mb-1 text-muted">Reservado</p>
                        <h4 class="mb-0"><?= number_format($card3, 0, ',', '.') ?></h4>
                    </div>
                    <i class="bx bx-bookmark-alt fs-2 text-warning"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="mb-1 text-muted">Em transferência</p>
                        <h4 class="mb-0"><?= number_format($card4, 0, ',', '.') ?></h4>
                    </div>
                    <i class="bx bx-transfer fs-2 text-info"></i>
                </div>
            </div>
        </div>
    </div>
</div>


                    <!-- Ações rápidas -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row g-2">
                                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                                    <button class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#modalTransferir">
                                        <i class="bx bx-right-arrow me-2"></i> Transferir p/ Filial
                                    </button>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4 col-lg-4">
                                    <button class="btn btn-outline-secondary w-100" data-bs-toggle="modal" data-bs-target="#modalHistorico">
                                        <i class="bx bx-time-five me-2"></i> Histórico de movimentações
                                    </button>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                                    <button class="btn btn-outline-dark w-100">
                                        <i class="bx bx-download me-2"></i> Exportar CSV
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- Tabela principal -->
                    <div class="card">
                        <h5 class="card-header">Estoque — Itens da Matriz</h5>
                        <div class="table-responsive text-nowrap">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Codigo Produto</th>
                                        <th>Produto</th>
                                        <th>Categoria</th>
                                        <th>Unidade</th>
                                        <th>Min</th>
                                        <th>Disp.</th>
                                        <th>Reserv.</th>
                                        <th>Transf.</th>
                                        <th>Status</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="table-border-bottom-0">
                                    <!-- Linha 1 -->
                                    <?php
// ✅ Usa a mesma conexão já carregada acima
// require '../../assets/php/conexao.php'; // JÁ ESTÁ EXECUTADO NO TOPO DO SEU ARQUIVO

try {
    // ✅ Buscar produtos apenas da empresa atual
    $stmt = $pdo->prepare("
        SELECT 
            id,
            empresa_id,
            codigo_produto,
            nome_produto,
            categoria_produto,
            unidade,
            quantidade_produto,
            reservado
        FROM estoque
        WHERE empresa_id = :empresa
        ORDER BY nome_produto ASC
    ");
    $stmt->bindParam(':empresa', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $produtosEstoque = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "<tr><td colspan='10'>Erro ao carregar estoque: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
    $produtosEstoque = [];
}

// ✅ Função de cálculo do status baseado no MIN (10%)
function calcularStatusEstoque($quantidade, $min)
{
    if ($quantidade < $min) {
        return ['Baixo', 'danger'];
    } elseif ($quantidade >= $min && $quantidade <= ($min * 2)) {
        return ['Estável', 'success'];
    } else {
        return ['Alto', 'primary'];
    }
}
?>

<tbody class="table-border-bottom-0">

<?php foreach ($produtosEstoque as $p): ?>

    <?php
        // ✅ Valor MIN = 10% da quantidade
        $min = max(1, $p['quantidade_produto'] * 0.10);

        // ✅ Calcular status
        list($statusTexto, $statusCor) = calcularStatusEstoque($p['quantidade_produto'], $min);
    ?>

    <tr>
        <td><strong><?= htmlspecialchars($p['codigo_produto']) ?></strong></td>
        <td><?= htmlspecialchars($p['nome_produto']) ?></td>
        <td><?= htmlspecialchars($p['categoria_produto']) ?></td>
        <td><?= htmlspecialchars($p['unidade']) ?></td>

        <!-- ✅ MIN calculado -->
        <td><?= number_format($min, 0, ',', '.') ?></td>

        <!-- ✅ DISPONÍVEL -->
        <td><?= number_format($p['quantidade_produto'], 0, ',', '.') ?></td>

        <!-- ✅ Seu banco não possui estas colunas, então deixei 0 -->
        <td><?= htmlspecialchars($p['reservado']) ?></td> <!-- Reservado -->
        <td>0</td> <!-- Transferido -->

        <!-- ✅ Status automático -->
        <td><span class="badge bg-label-<?= $statusCor ?>"><?= $statusTexto ?></span></td>

        <td class="text-end">
            <div class="btn-group">
                <button class="btn btn-sm btn-outline-secondary"
                        data-bs-toggle="modal"
                        data-bs-target="#modalProduto"
                        data-sku="<?= htmlspecialchars($p['codigo_produto']) ?>">
                    Detalhes
                </button>

                <button class="btn btn-sm btn-outline-primary"
                        data-bs-toggle="modal"
                        data-bs-target="#modalTransferir"
                        data-sku="<?= htmlspecialchars($p['codigo_produto']) ?>">
                    Transf.
                </button>
            </div>
        </td>
    </tr>

<?php endforeach; ?>

</tbody>

                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- ===== Modais ===== -->

                <!-- Modal: Detalhes do Produto -->
                <div class="modal fade" id="modalProduto" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Detalhes do Produto</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-3 mb-2">
                                    <div class="col-md-4">
                                        <p class="mb-1"><strong>Codigo Produto:</strong> <span id="det-sku">—</span></p>
                                    </div>
                                    <div class="col-md-8">
                                        <p class="mb-1"><strong>Produto:</strong> <span id="det-nome">—</span></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p class="mb-1"><strong>Categoria:</strong> <span id="det-categoria">—</span></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p class="mb-1"><strong>Unidade:</strong> <span id="det-validade">—</span></p>
                                    </div>
                                    <div class="col-md-3">
                                        <p class="mb-1"><strong>Mínimo:</strong> <span id="det-min">—</span></p>
                                    </div>
                                    <div class="col-md-3">
                                        <p class="mb-1"><strong>Disponível:</strong> <span id="det-disp">—</span></p>
                                    </div>
                                    <div class="col-md-3">
                                        <p class="mb-1"><strong>Reservado:</strong> <span id="det-res">—</span></p>
                                    </div>
                                    <div class="col-md-3">
                                        <p class="mb-1"><strong>Em transf.:</strong> <span id="det-transf">—</span></p>
                                    </div>
                                    
                                </div>
                                <div class="alert alert-info mb-0">
                                    <i class="bx bx-info-circle me-1"></i> Dica: clique em <strong>Mov.</strong> para entrada/saída/ajuste ou em <strong>Transf.</strong> para enviar às Filiais.
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal: Movimentar Estoque -->
                <div class="modal fade" id="modalMovimentar" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Movimentar Estoque</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                            </div>
                            <div class="modal-body">
                                <form>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label">Codigo Produto</label>
                                            <input type="text" class="form-control" placeholder="Ex.: ACA-500" value="">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Tipo de movimentação</label>
                                            <div class="d-flex gap-3 flex-wrap">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="tipo_mov" id="movEntrada" value="entrada" checked>
                                                    <label class="form-check-label" for="movEntrada">Entrada</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="tipo_mov" id="movSaida" value="saida">
                                                    <label class="form-check-label" for="movSaida">Saída</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="tipo_mov" id="movAjuste" value="ajuste">
                                                    <label class="form-check-label" for="movAjuste">Ajuste</label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Quantidade</label>
                                            <input type="number" class="form-control" min="1" placeholder="0">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Documento (NF/OS)</label>
                                            <input type="text" class="form-control" placeholder="Opcional">
                                        </div>

                                       
                                        <div class="col-12">
                                            <label class="form-label">Motivo</label>
                                            <select class="form-select">
                                                <option>Reposição de fornecedor</option>
                                                <option>Devolução</option>
                                                <option>Perda/avaria</option>
                                                <option>Inventário</option>
                                                <option>Outros</option>
                                            </select>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label">Observações</label>
                                            <textarea class="form-control" rows="3" placeholder="Detalhe a movimentação..."></textarea>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button class="btn btn-primary">Salvar movimentação</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal: Transferir p/ Filial -->
                <div class="modal fade" id="modalTransferir" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Transferir para Filial</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                            </div>
                            <div class="modal-body">
                                <form>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label">Codigo Produto</label>
                                            <input type="text" class="form-control" placeholder="Ex.: ACA-500" value="">
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label">Filial</label>
                                            <select class="form-select">
                                                <option>Filial Centro</option>
                                                <option>Filial Norte</option>
                                                <option>Filial Sul</option>
                                                <option>Filial Leste</option>
                                            </select>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Quantidade</label>
                                            <input type="number" class="form-control" min="1" placeholder="0">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Prioridade</label>
                                            <select class="form-select">
                                                <option>Baixa</option>
                                                <option>Média</option>
                                                <option>Alta</option>
                                            </select>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label">Observações</label>
                                            <textarea class="form-control" rows="3" placeholder="Instruções de envio, embalagem, etc."></textarea>
                                        </div>
                                    </div>
                                </form>
                                <div class="alert alert-warning mt-3 mb-0">
                                    <i class="bx bx-error-circle me-1"></i> A transferência reserva a quantidade informada até o envio.
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button class="btn btn-primary">Gerar transferência</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal: Histórico de Movimentações -->
                <div class="modal fade" id="modalHistorico" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Histórico de Movimentações</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                            </div>
                            <div class="modal-body">
                                <div class="table-responsive text-nowrap">
                                    <table class="table table-striped align-middle">
                                        <thead>
                                            <tr>
                                                <th>Data/Hora</th>
                                                <th>Codigo Produto</th>
                                                <th>Produto</th>
                                                <th>Tipo</th>
                                                <th>Qtd</th>
                                                <th>Doc</th>
                                                <th>Motivo</th>
                                                <th>Usuário</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>26/09/2025 10:15</td>
                                                <td>ACA-500</td>
                                                <td>Polpa Açaí 500g</td>
                                                <td><span class="badge bg-label-success">Entrada</span></td>
                                                <td>+300</td>
                                                <td>L2309-01</td>
                                                <td>NF 21544</td>
                                                <td>Reposição de fornecedor</td>
                                                <td>maria.silva</td>
                                            </tr>
                                            <tr>
                                                <td>25/09/2025 17:02</td>
                                                <td>ACA-1KG</td>
                                                <td>Polpa Açaí 1kg</td>
                                                <td><span class="badge bg-label-info">Ajuste</span></td>
                                                <td>+20</td>
                                                <td>L2309-05</td>
                                                <td>—</td>
                                                <td>Inventário</td>
                                                <td>joao.souza</td>
                                            </tr>
                                            <tr>
                                                <td>24/09/2025 09:40</td>
                                                <td>GRAN-200</td>
                                                <td>Granola 200g</td>
                                                <td><span class="badge bg-label-danger">Saída</span></td>
                                                <td>-60</td>
                                                <td>L2407-03</td>
                                                <td>OS 7711</td>
                                                <td>Perda/avaria</td>
                                                <td>ana.pereira</td>
                                            </tr>
                                            <tr>
                                                <td>23/09/2025 15:18</td>
                                                <td>COPO-300</td>
                                                <td>Copo 300ml</td>
                                                <td><span class="badge bg-label-primary">Transferência</span></td>
                                                <td>-500</td>
                                                <td>L2401-12</td>
                                                <td>TR-1026</td>
                                                <td>Envio p/ Filial Sul</td>
                                                <td>rodrigo.alves</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <small class="text-muted">* Valores positivos aumentam o saldo, negativos reduzem.</small>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                            </div>
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
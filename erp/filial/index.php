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
          <li class="menu-item active">
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

          <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-briefcase"></i>
              <div data-i18n="B2B">B2B - Matriz</div>
            </a>
            <ul class="menu-sub">
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
                <a href="./vendasFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
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

          <div class="row">

            <div class="col-lg-8 mb-4 order-0">
              <div class="card">
                <div class="d-flex align-items-end row">
                  <div class="col-sm-7">
                    <div class="card-body">
                      <h5 class="card-title text-primary saudacao" data-setor="Filial"></h5>
                      <p class="mb-4">Suas configurações das Filiais foram atualizadas em seu perfil. Continue
                        explorando e ajustando-as conforme suas preferências.</p>

                    </div>
                  </div>
                  <div class="col-sm-5 text-center text-sm-left">
                    <div class="card-body pb-0 px-0 px-md-4">
                      <img src="../../assets/img/illustrations/man-with-laptop-light.png" height="155"
                        alt="View Badge User" data-app-dark-img="illustrations/man-with-laptop-dark.png"
                        data-app-light-img="illustrations/man-with-laptop-light.png" />
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- KPIs PERÍODO -->
            <div class="col-lg-4 col-md-4 order-1">
              <div class="row">
                <div class="col-lg-6 col-md-12 col-6 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <div class="card-title d-flex align-items-start justify-content-between">
                        <span class="avatar-initial rounded bg-label-info p-2"><i class="bx bx-receipt"></i></span>
                      </div>
                      <span class="fw-semibold d-block mb-1">Vendas (período)</span>
                      <h3 class="card-title mb-1"><?= (int)$vendasQtd ?></h3>
                      <small class="text-muted">Ticket médio: <strong><?= moneyBr($ticketMedio) ?></strong></small>
                    </div>
                  </div>
                </div>
                <div class="col-lg-6 col-md-12 col-6 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <div class="card-title d-flex align-items-start justify-content-between">
                        <span class="avatar-initial rounded bg-label-primary p-2"><i class="bx bx-money"></i></span>
                      </div>
                      <span class="fw-semibold d-block mb-1">Total (período)</span>
                      <h3 class="card-title mb-1"><?= moneyBr($vendasValor) ?></h3>
                      <small class="text-muted">Troco: <strong><?= moneyBr($vendasTroco) ?></strong></small>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <!-- /KPIs -->
          </div>

          <!-- Filtros -->
          <form class="card mb-4" method="get">
            <div class="card-body row g-3 align-items-end">
              <input type="hidden" name="id" value="<?= htmlspecialchars($idSelecionado) ?>">
              <div class="col-md-3">
                <label class="form-label">Período</label>
                <select name="periodo" id="periodo" class="form-select">
                  <option value="hoje" <?= $periodo === 'hoje' ? 'selected' : ''; ?>>Hoje</option>
                  <option value="ontem" <?= $periodo === 'ontem' ? 'selected' : ''; ?>>Ontem</option>
                  <option value="ult7" <?= $periodo === 'ult7' ? 'selected' : ''; ?>>Últimos 7 dias</option>
                  <option value="mes" <?= $periodo === 'mes' ? 'selected' : ''; ?>>Mês atual</option>
                  <option value="mes_anterior" <?= $periodo === 'mes_anterior' ? 'selected' : ''; ?>>Mês anterior</option>
                  <option value="custom" <?= $periodo === 'custom' ? 'selected' : ''; ?>>Personalizado</option>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label">Data inicial</label>
                <input type="date" name="data_ini" id="data_ini" class="form-control"
                  value="<?= htmlspecialchars($ini->format('Y-m-d')) ?>" <?= $periodo !== 'custom' ? 'disabled' : ''; ?>>
              </div>
              <div class="col-md-2">
                <label class="form-label">Data final</label>
                <input type="date" name="data_fim" id="data_fim" class="form-control"
                  value="<?= htmlspecialchars($fim->format('Y-m-d')) ?>" <?= $periodo !== 'custom' ? 'disabled' : ''; ?>>
              </div>
              <div class="col-md-2">
                <label class="form-label">Caixa</label>
                <select name="caixa_id" class="form-select">
                  <option value="">Todos</option>
                  <?php foreach ($listaCaixas as $cx): ?>
                    <option value="<?= (int)$cx['id'] ?>" <?= $caixaId === (int)$cx['id'] ? 'selected' : ''; ?>>
                      #<?= (int)$cx['numero_caixa'] ?> — <?= htmlspecialchars($cx['responsavel']) ?> (<?= date('d/m H:i', strtotime($cx['abertura_datetime'])) ?>) <?= $cx['status'] === 'aberto' ? '[aberto]' : ''; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-1">
                <label class="form-label">Forma</label>
                <input type="text" name="forma_pagamento" class="form-control" placeholder="ex.: PIX"
                  value="<?= htmlspecialchars($formaPag) ?>">
              </div>
              <div class="col-md-2">
                <label class="form-label">Status NFC-e</label>
                <input type="text" name="status_nfce" class="form-control" placeholder="ex.: autorizada"
                  value="<?= htmlspecialchars($statusNf) ?>">
              </div>
              <div class="col-md-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Aplicar filtros</button>
                <a class="btn btn-outline-secondary" href="?id=<?= urlencode($idSelecionado) ?>">Limpar</a>
              </div>
            </div>
          </form>

          <div class="row">
            <!-- Caixa atual -->
            <div class="col-md-6 col-lg-4 order-0 mb-4">
              <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between pb-0">
                  <div class="card-title mb-0">
                    <h5 class="m-0 me-2">Caixa Atual</h5>
                    <?php if ($caixaAtual): ?>
                      <small class="text-muted">#<?= (int)$caixaAtual['numero_caixa'] ?> — <?= htmlspecialchars($caixaAtual['responsavel']) ?></small>
                    <?php else: ?>
                      <small class="text-muted">Sem caixa aberto</small>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="card-body">
                  <?php if ($caixaAtual): ?>
                    <ul class="list-unstyled mb-0">
                      <li class="d-flex justify-content-between mb-2">
                        <span>Abertura</span><strong><?= moneyBr($caixaAtual['valor_abertura']) ?></strong>
                      </li>
                      <li class="d-flex justify-content-between mb-2">
                        <span>Suprimentos</span><strong class="text-success"><?= moneyBr($caixaAtual['valor_suprimentos']) ?></strong>
                      </li>
                      <li class="d-flex justify-content-between mb-2">
                        <span>Sangrias</span><strong class="text-danger"><?= moneyBr($caixaAtual['valor_sangrias']) ?></strong>
                      </li>
                      <li class="d-flex justify-content-between mb-2">
                        <span>Qtd Vendas</span><strong><?= (int)$caixaAtual['quantidade_vendas'] ?></strong>
                      </li>
                      <hr>
                      <li class="d-flex justify-content-between">
                        <span>Saldo (valor_liquido)</span>
                        <strong><?= moneyBr($caixaAtual['valor_liquido']) ?></strong>
                      </li>
                    </ul>
                  <?php else: ?>
                    <div class="text-center text-muted py-3">Abra um caixa para começar.</div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Formas pagamento (pizza) -->
            <div class="col-md-6 col-lg-4 order-1 mb-4">
              <div class="card h-100">
                <div class="card-header">
                  <h5 class="m-0">Formas de Pagamento (Período)</h5>
                </div>
                <div class="card-body">
                  <div id="pagamentoPie"></div>
                  <?php if (empty($pagtoLabels)): ?>
                    <div class="text-center text-muted mt-2">Sem vendas no período</div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Vendas por hora (barras) -->
            <div class="col-md-12 col-lg-4 order-2 mb-4">
              <div class="card h-100">
                <div class="card-header">
                  <h5 class="m-0">Vendas por Hora (Período)</h5>
                </div>
                <div class="card-body">
                  <div id="vendasHoraChart"></div>
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <!-- Top produtos (período) -->
            <div class="col-md-6 col-lg-6 order-0 mb-4">
              <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                  <h5 class="m-0">Top Produtos (Período)</h5>
                </div>
                <div class="card-body">
                  <div id="topProdutosChart"></div>
                  <?php if (empty($topProdLabels)): ?>
                    <div class="text-center text-muted mt-2">Sem itens vendidos no período</div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- NFCE status -->
            <div class="col-md-6 col-lg-6 order-1 mb-4">
              <div class="card h-100">
                <div class="card-header">
                  <h5 class="m-0">NFC-e por Status (Período)</h5>
                </div>
                <div class="card-body">
                  <div id="nfceStatusChart"></div>
                  <?php if (empty($nfceLabels)): ?>
                    <div class="text-center text-muted mt-2">Sem cupons no período</div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <!-- Últimas vendas -->
            <div class="col-md-12 col-lg-12 order-2 mb-4">
              <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                  <h5 class="m-0 me-2">Últimas Vendas (Período)</h5>
                </div>
                <div class="card-body">
                  <ul class="p-0 m-0">
                    <?php foreach ($ultimasVendas as $v): ?>
                      <li class="d-flex mb-3 pb-2 border-bottom">
                        <div class="avatar flex-shrink-0 me-3">
                          <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-shopping-bag"></i></span>
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                          <div class="me-2">
                            <h6 class="mb-0"><?= moneyBr($v['valor_total']) ?></h6>
                            <small class="text-muted d-block mb-1">
                              <?= htmlspecialchars($v['forma_pagamento'] ?: '—') ?> •
                              <?= date('d/m/Y H:i', strtotime($v['data_venda'])) ?> •
                              Resp.: <?= htmlspecialchars($v['responsavel']) ?>
                            </small>
                          </div>
                          <div class="user-progress">
                            <small class="text-muted">#<?= (int)$v['id'] ?></small>
                          </div>
                        </div>
                      </li>
                    <?php endforeach; ?>
                    <?php if (empty($ultimasVendas)): ?>
                      <li class="d-flex">
                        <div class="w-100 text-center text-muted py-2">
                          Sem vendas no período selecionado
                        </div>
                      </li>
                    <?php endif; ?>
                  </ul>
                </div>
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

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Habilitar/Desabilitar datas quando período = custom
      const periodoSel = document.getElementById('periodo');
      const di = document.getElementById('data_ini');
      const df = document.getElementById('data_fim');

      function toggleDates() {
        const isCustom = periodoSel.value === 'custom';
        di.disabled = !isCustom;
        df.disabled = !isCustom;
      }
      if (periodoSel) {
        periodoSel.addEventListener('change', toggleDates);
        toggleDates();
      }

      if (typeof ApexCharts === 'undefined') {
        console.error('ApexCharts não carregado');
        return;
      }

      // Fallback de cores (caso o tema não exponha window.config.colors)
      const themeColors = (window.config && window.config.colors) ? window.config.colors : {
        primary: '#3b82f6',
        success: '#22c55e',
        warning: '#f59e0b',
        info: '#06b6d4',
        danger: '#ef4444'
      };

      // Pagamento (pizza)
      const pagamentoPieEl = document.getElementById('pagamentoPie');
      if (pagamentoPieEl && <?= json_encode(!empty($pagtoLabels)) ?>) {
        new ApexCharts(pagamentoPieEl, {
          chart: {
            type: 'donut',
            height: 280
          },
          labels: <?= json_encode($pagtoLabels, JSON_UNESCAPED_UNICODE) ?>,
          series: <?= json_encode(array_map('floatval', $pagtoValues)) ?>,
          colors: [themeColors.primary, themeColors.success, themeColors.warning, themeColors.info, '#8892b0', '#8b5cf6'],
          legend: {
            position: 'bottom'
          },
          dataLabels: {
            enabled: true
          }
        }).render();
      }

      // Vendas por hora (barras)
      const vendasHoraEl = document.getElementById('vendasHoraChart');
      if (vendasHoraEl) {
        new ApexCharts(vendasHoraEl, {
          chart: {
            type: 'bar',
            height: 300,
            toolbar: {
              show: false
            }
          },
          series: [{
            name: 'Cupons',
            data: <?= json_encode(array_map('intval', $vendasPorHora)) ?>
          }],
          colors: [themeColors.primary],
          plotOptions: {
            bar: {
              borderRadius: 6,
              columnWidth: '45%'
            }
          },
          dataLabels: {
            enabled: false
          },
          xaxis: {
            categories: <?= json_encode($labelsHoras) ?>
          },
          yaxis: {
            title: {
              text: 'Qtd'
            },
            min: 0,
            forceNiceScale: true
          },
          tooltip: {
            y: {
              formatter: val => `${val} vendas`
            }
          }
        }).render();
      }

      // Top produtos (barras horizontais)
      const topProdutosEl = document.getElementById('topProdutosChart');
      if (topProdutosEl && <?= json_encode(!empty($topProdLabels)) ?>) {
        new ApexCharts(topProdutosEl, {
          chart: {
            type: 'bar',
            height: 300,
            toolbar: {
              show: false
            }
          },
          series: [{
            name: 'Qtd',
            data: <?= json_encode(array_map('intval', $topProdQtd)) ?>
          }],
          colors: [themeColors.success],
          plotOptions: {
            bar: {
              horizontal: true,
              borderRadius: 6,
              barHeight: '60%'
            }
          },
          dataLabels: {
            enabled: true
          },
          xaxis: {
            categories: <?= json_encode($topProdLabels, JSON_UNESCAPED_UNICODE) ?>
          },
          tooltip: {
            y: {
              formatter: val => `${val} un.`
            }
          }
        }).render();
      }

      // NFCE status (pizza)
      const nfceStatusEl = document.getElementById('nfceStatusChart');
      if (nfceStatusEl && <?= json_encode(!empty($nfceLabels)) ?>) {
        new ApexCharts(nfceStatusEl, {
          chart: {
            type: 'donut',
            height: 280
          },
          labels: <?= json_encode($nfceLabels, JSON_UNESCAPED_UNICODE) ?>,
          series: <?= json_encode(array_map('intval', $nfceValues)) ?>,
          colors: [themeColors.info, themeColors.success, themeColors.warning, themeColors.danger, '#64748b'],
          legend: {
            position: 'bottom'
          },
          dataLabels: {
            enabled: true
          }
        }).render();
      }
    });
  </script>

  <!-- Main JS -->
  <script src="../../assets/js/main.js"></script>

  <!-- Page JS -->
  <script src="../../assets/js/dashboards-analytics.js"></script>

  <!-- Place this tag in your head or just before your close body tag. -->
  <script async defer src="https://buttons.github.io/buttons.js"></script>
</body>

</html>
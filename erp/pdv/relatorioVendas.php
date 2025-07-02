<?php
session_start();
require_once '../../assets/php/conexao.php';

// ✅ Recupera o identificador vindo da URL
$idSelecionado = $_GET['id'] ?? '';

// ✅ Verifica se a pessoa está logada
if (
  !isset($_SESSION['usuario_logado']) ||
  !isset($_SESSION['empresa_id']) ||
  !isset($_SESSION['tipo_empresa']) ||
  !isset($_SESSION['usuario_id'])
) {
  header("Location: .././login.php?id=$idSelecionado");
  exit;
}

// ✅ Valida o tipo de empresa e o acesso permitido
if (str_starts_with($idSelecionado, 'principal_')) {
  if ($_SESSION['tipo_empresa'] !== 'principal' || $_SESSION['empresa_id'] != 1) {
    echo "<script>
              alert('Acesso negado!');
              window.location.href = '.././login.php?id=$idSelecionado';
          </script>";
    exit;
  }
  $id = 1;
  $empresa_id = 'principal_1';
} elseif (str_starts_with($idSelecionado, 'filial_')) {
  $idFilial = (int) str_replace('filial_', '', $idSelecionado);
  if ($_SESSION['tipo_empresa'] !== 'filial' || $_SESSION['empresa_id'] != $idFilial) {
    echo "<script>
              alert('Acesso negado!');
              window.location.href = '.././login.php?id=$idSelecionado';
          </script>";
    exit;
  }
  $id = $idFilial;
  $empresa_id = 'filial_' . $idFilial;
} else {
  echo "<script>
          alert('Empresa não identificada!');
          window.location.href = '.././login.php?id=$idSelecionado';
      </script>";
  exit;
}

// ✅ Buscar imagem da tabela sobre_empresa
try {
  $sql = "SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1";
  $stmt = $pdo->prepare($sql);
  $stmt->bindParam(':id_selecionado', $idSelecionado, PDO::PARAM_STR);
  $stmt->execute();
  $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);

  $logoEmpresa = !empty($empresaSobre['imagem'])
    ? "../../assets/img/empresa/" . $empresaSobre['imagem']
    : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
  $logoEmpresa = "../../assets/img/favicon/logo.png";
}

// ✅ Buscar nome e nível do usuário logado
$nomeUsuario = 'Usuário';
$nivelUsuario = 'Comum';
$usuario_id = $_SESSION['usuario_id'];

try {
  $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
  $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
  $stmt->execute();
  $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($usuario) {
    $nomeUsuario = $usuario['usuario'];
    $nivelUsuario = $usuario['nivel'];
  }
} catch (PDOException $e) {
  $nomeUsuario = 'Erro ao carregar nome';
  $nivelUsuario = 'Erro ao carregar nível';
}

// ✅ Processar filtro de período
$filtro_periodo = $_GET['filtro_periodo'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';

// Definir condições de data com base no filtro
$condicao_data = "";
$params = [':empresa_id' => $empresa_id];

switch ($filtro_periodo) {
  case 'dia':
    $condicao_data = " AND DATE(data_venda) = CURDATE()";
    break;
  case 'semana':
    $condicao_data = " AND YEARWEEK(data_venda, 1) = YEARWEEK(CURDATE(), 1)";
    break;
  case 'mes':
    $condicao_data = " AND YEAR(data_venda) = YEAR(CURDATE()) AND MONTH(data_venda) = MONTH(CURDATE())";
    break;
  case 'ano':
    $condicao_data = " AND YEAR(data_venda) = YEAR(CURDATE())";
    break;
  case 'personalizar':
    if (!empty($data_inicio) && !empty($data_fim)) {
      $condicao_data = " AND DATE(data_venda) BETWEEN :data_inicio AND :data_fim";
      $params[':data_inicio'] = $data_inicio;
      $params[':data_fim'] = $data_fim;
    }
    break;
  default:
    // Sem filtro - últimos 30 dias
    $condicao_data = " AND data_venda >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    break;
}

// ✅ Calcular totais de vendas
try {
  // Total de vendas
  $sql_total = "SELECT COUNT(*) as total_vendas, SUM(total) as valor_total 
                FROM venda_rapida 
                WHERE empresa_id = :empresa_id $condicao_data";
  $stmt_total = $pdo->prepare($sql_total);
  foreach ($params as $key => &$val) {
    $stmt_total->bindParam($key, $val);
  }
  $stmt_total->execute();
  $totais = $stmt_total->fetch(PDO::FETCH_ASSOC);

  $total_vendas = $totais['total_vendas'] ?? 0;
  $valor_total = $totais['valor_total'] ?? 0;
  $media_venda = ($total_vendas > 0) ? $valor_total / $total_vendas : 0;
} catch (PDOException $e) {
  $total_vendas = 0;
  $valor_total = 0;
  $media_venda = 0;
}

// ✅ Buscar vendas recentes
$vendas_recentes = [];
try {
  $sql_vendas = "SELECT vr.*, a.responsavel as nome_responsavel 
                 FROM venda_rapida vr
                 LEFT JOIN aberturas a ON vr.id_caixa = a.id
                 WHERE vr.empresa_id = :empresa_id $condicao_data
                 ORDER BY vr.data_venda DESC 
                 LIMIT 5";
  $stmt_vendas = $pdo->prepare($sql_vendas);
  foreach ($params as $key => &$val) {
    $stmt_vendas->bindParam($key, $val);
  }
  $stmt_vendas->execute();
  $vendas_recentes = $stmt_vendas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $vendas_recentes = [];
}

// Função para formatar valores monetários
function formatarMoeda($valor)
{
  return 'R$ ' . number_format($valor, 2, ',', '.');
}

// Determinar se deve mostrar a modal automaticamente
$mostrar_modal = ($filtro_periodo === 'personalizar' && (empty($data_inicio) || empty($data_fim)));

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

          <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
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
            <span class="menu-header-text">Administrativo</span>
          </li>

          <!-- SUBMENU: SEFAZ -->
          <li class="menu-item">
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
              <li class="menu-item">
                <a href="./sefazSAT.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">SAT</div>
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
              <li class="menu-item">
                <a href="./produtosAdicionados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
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
          <li class="menu-item active open">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-file"></i>
              <div data-i18n="Authentications">Relatórios</div>
            </a>
            <ul class="menu-sub">
              <!-- Relatório Operacional: Desempenho de operações -->
              <li class="menu-item">
                <a href="./relatorioOperacional.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Operacional</div>
                </a>
              </li>
              <!-- Relatório de Vendas: Estatísticas e resumo de vendas -->
              <li class="menu-item active">
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
            <a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-box"></i>
              <div data-i18n="Authentications">Estoque</div>
            </a>
          </li>
          <li class="menu-item">
            <a href="../clientes/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-user"></i>
              <div data-i18n="Authentications">Clientes</div>
            </a>
          </li>
          <?php
          $isFilial = str_starts_with($idSelecionado, 'filial_');
          $link = $isFilial
            ? '../matriz/index.php?id=' . urlencode($idSelecionado)
            : '../filial/index.php?id=principal_1';
          $titulo = $isFilial ? 'Matriz' : 'Filial';
          ?>

          <li class="menu-item">
            <a href="<?= $link ?>" class="menu-link">
              <i class="menu-icon tf-icons bx bx-cog"></i>
              <div data-i18n="Authentications"><?= $titulo ?></div>
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
                <i class="bx bx-search fs-4 lh-0"></i>
                <input type="text" class="form-control border-0 shadow-none" id="searchInput" placeholder="Search..."
                  aria-label="Search..." />
              </div>
            </div>
            <!-- /Search -->

            <ul class="navbar-nav flex-row align-items-center ms-auto">
              <!-- User -->
              <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                  <div class="avatar avatar-online">
                    <img src="<?= htmlspecialchars($logoEmpresa) ?>" alt class="w-px-40 h-auto rounded-circle" />
                  </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li>
                    <a class="dropdown-item" href="#">
                      <div class="d-flex">
                        <div class="flex-shrink-0 me-3">
                          <div class="avatar avatar-online">
                            <img src="<?= htmlspecialchars($logoEmpresa) ?>" alt
                              class="w-px-40 h-auto rounded-circle" />
                          </div>
                        </div>
                        <div class="flex-grow-1">
                          <span class="fw-semibold d-block"><?php echo $nomeUsuario; ?></span>
                          <small class="text-muted"><?php echo $nivelUsuario; ?></small>
                        </div>
                      </div>
                    </a>
                  </li>
                  <li>
                    <div class="dropdown-divider"></div>
                  </li>
                  <li>
                    <a class="dropdown-item" href="#">
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
                    <a class="dropdown-item" href="#">
                      <span class="d-flex align-items-center align-middle">
                        <i class="flex-shrink-0 bx bx-credit-card me-2"></i>
                        <span class="flex-grow-1 align-middle">Billing</span>
                        <span class="flex-shrink-0 badge badge-center rounded-pill bg-danger w-px-20 h-px-20">4</span>
                      </span>
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
            <div class="col-12 mb-4">
              <h4 class="fw-bold py-3 mb-0">Relatório de Vendas</h4>
              <p class="mb-4">Visualize e analise os dados de vendas da sua empresa.</p>

              <div class="row">
                <!-- Resumo de Vendas -->
                <div class="col-12">
                  <div class="card mb-4">
                    <div class="card-body">
                      <div class="row align-items-center mb-5">
                        <div class="col-md-8 col-6 mb-2 mb-md-0">
                          <h5 class="card-title mb-0">Resumo de Vendas</h5>
                        </div>
                        <div class="col-md-4 col-6 text-md-end">
                          <!-- Filtro por período -->
                          <form method="get" action="" id="form-filtro-periodo" class="d-inline-block w-100 w-md-auto">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($idSelecionado) ?>">
                            <div class="input-group">
                              <select class="form-select" id="filtro_periodo_resumo" name="filtro_periodo">
                                <option value="">Selecione</option>
                                <option value="dia" <?= ($filtro_periodo === 'dia') ? 'selected' : '' ?>>Dia</option>
                                <option value="semana" <?= ($filtro_periodo === 'semana') ? 'selected' : '' ?>>Semana
                                </option>
                                <option value="mes" <?= ($filtro_periodo === 'mes') ? 'selected' : '' ?>>Mês</option>
                                <option value="ano" <?= ($filtro_periodo === 'ano') ? 'selected' : '' ?>>Ano</option>
                                <option value="personalizar" <?= ($filtro_periodo === 'personalizar') ? 'selected' : '' ?>>
                                  Personalizar</option>
                              </select>
                            </div>
                          </form>
                        </div>
                      </div>

                      <!-- Modal Personalizar -->
                      <div class="modal fade" id="modalPersonalizar" tabindex="-1"
                        aria-labelledby="modalPersonalizarLabel" aria-hidden="true">
                        <div class="modal-dialog">
                          <form method="get" action="" class="modal-content" id="form-personalizar">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($idSelecionado) ?>">
                            <input type="hidden" name="filtro_periodo" value="personalizar">
                            <div class="modal-header">
                              <h5 class="modal-title" id="modalPersonalizarLabel">Personalizar Período</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Fechar"></button>
                            </div>
                            <div class="modal-body">
                              <div class="mb-3">
                                <label for="data_inicio" class="form-label">Data Início</label>
                                <input type="date" class="form-control" id="data_inicio" name="data_inicio"
                                  value="<?= htmlspecialchars($data_inicio) ?>" required>
                              </div>
                              <div class="mb-3">
                                <label for="data_fim" class="form-label">Data Fim</label>
                                <input type="date" class="form-control" id="data_fim" name="data_fim"
                                  value="<?= htmlspecialchars($data_fim) ?>" required>
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="submit" class="btn btn-primary">Filtrar</button>
                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            </div>
                          </form>
                        </div>
                      </div>

                      <div class="row text-center">
                        <div class="col-md-4 mb-3 mb-md-0">
                          <div class="border rounded py-3 bg-light d-flex flex-column align-items-center">
                            <div class="avatar flex-shrink-0 mb-2">
                              <img src="../../assets/img/icons/unicons/chart-success.png" alt="Total Vendas"
                                class="rounded">
                            </div>
                            <div class="fs-6 text-muted">Total de Vendas</div>
                            <div class="fs-4 fw-bold text-success"><?= formatarMoeda($valor_total) ?></div>
                          </div>
                        </div>
                        <div class="col-md-4 mb-3 mb-md-0">
                          <div class="border rounded py-3 bg-light d-flex flex-column align-items-center">
                            <div class="avatar flex-shrink-0 mb-2">
                              <img src="../../assets/img/icons/unicons/wallet.png" alt="Transações" class="rounded">
                            </div>
                            <div class="fs-6 text-muted">Transações</div>
                            <div class="fs-4 fw-bold"><?= $total_vendas ?></div>
                          </div>
                        </div>
                        <div class="col-md-4">
                          <div class="border rounded py-3 bg-light d-flex flex-column align-items-center">
                            <div class="avatar flex-shrink-0 mb-2">
                              <img src="../../assets/img/icons/unicons/chart.png" alt="Média por Venda" class="rounded">
                            </div>
                            <div class="fs-6 text-muted">Média por Venda</div>
                            <div class="fs-4 fw-bold text-primary"><?= formatarMoeda($media_venda) ?></div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Vendas Recentes -->
                <div class="col-12">
                  <div class="card">
                    <div class="card-body">
                      <div class="row align-items-center mb-4">
                        <div class="col-md-6">
                          <h5 class="card-title mb-0">Vendas Recentes</h5>
                        </div>
                      </div>

                      <div class="table-responsive text-nowrap">
                        <table class="table table-hover align-middle mb-0" id="tabelaVendas">
                          <thead class="table-light">
                            <tr>
                              <th>Data - Hora</th>
                              <th>Responsável</th>
                              <th>Valor Total</th>
                              <th>Forma de Pagamento</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php if (empty($vendas_recentes)): ?>
                              <tr>
                                <td colspan="4" class="text-center">Nenhuma venda encontrada</td>
                              </tr>
                            <?php else: ?>
                              <?php foreach ($vendas_recentes as $venda): ?>
                                <tr>
                                  <td><?= date('d/m/Y - H:i', strtotime($venda['data_venda'])) ?></td>
                                  <td><?= htmlspecialchars($venda['responsavel']) ?></td>
                                  <td><?= formatarMoeda($venda['total']) ?></td>
                                  <td><?= htmlspecialchars($venda['forma_pagamento']) ?></td>
                                </tr>
                              <?php endforeach; ?>
                            <?php endif; ?>
                          </tbody>
                        </table>
                      </div>

                      <!-- Paginação -->
                      <div class="d-flex justify-content-start align-items-center gap-2 m-3">
                        <nav aria-label="Page navigation">
                          <ul class="pagination justify-content-end mb-0" id="paginationContainer">
                            <li class="page-item disabled" id="prevPage">
                              <a class="page-link" href="#" tabindex="-1" aria-disabled="true"
                                style="padding: 0.3rem 0.6rem; font-size: 0.875rem;">Anterior</a>
                            </li>
                            <!-- Botões de página serão inseridos aqui pelo JavaScript -->
                            <li class="page-item" id="nextPage">
                              <a class="page-link" href="#"
                                style="padding: 0.3rem 0.6rem; font-size: 0.875rem;">Próximo</a>
                            </li>
                          </ul>
                        </nav>
                      </div>
                      <!-- /Paginação -->

                    </div>
                  </div>
                </div>
              </div>

              <script>
                // Substitua todo o script por:
                document.addEventListener('DOMContentLoaded', function () {
                  // Filtro de período (mantido igual)
                  var modalPersonalizar = new bootstrap.Modal(document.getElementById('modalPersonalizar'));
                  <?php if ($mostrar_modal): ?>
                    modalPersonalizar.show();
                  <?php endif; ?>

                  document.getElementById('filtro_periodo_resumo').addEventListener('change', function (e) {
                    if (this.value === 'personalizar') {
                      modalPersonalizar.show();
                      this.value = '';
                    } else if (this.value !== '') {
                      document.getElementById('form-filtro-periodo').submit();
                    }
                  });

                  document.getElementById('form-personalizar').addEventListener('submit', function (e) {
                    var dataInicio = document.getElementById('data_inicio').value;
                    var dataFim = document.getElementById('data_fim').value;

                    if (!dataInicio || !dataFim) {
                      e.preventDefault();
                      alert('Por favor, preencha ambas as datas.');
                      return false;
                    }

                    modalPersonalizar.hide();
                    return true;
                  });

                  const searchInput = document.getElementById('searchInput');
                  const linhas = Array.from(document.querySelectorAll('#tabelaVendas tbody tr'));
                  const rowsPerPage = 10;
                  let currentPage = 1;

                  function getFilteredRows() {
                    const filtro = searchInput.value.toLowerCase();
                    return linhas.filter(linha => {
                      const cells = Array.from(linha.querySelectorAll('td'));
                      return cells.some(cell => cell.textContent.toLowerCase().includes(filtro));
                    });
                  }

                  function renderTable() {
                    const linhasFiltradas = getFilteredRows();
                    const totalRows = linhasFiltradas.length;
                    const totalPages = Math.max(1, Math.ceil(totalRows / rowsPerPage));

                    if (currentPage > totalPages) {
                      currentPage = totalPages;
                    }

                    const inicio = (currentPage - 1) * rowsPerPage;
                    const fim = Math.min(inicio + rowsPerPage, totalRows);

                    linhas.forEach(linha => linha.style.display = 'none');
                    linhasFiltradas.slice(inicio, fim).forEach(linha => linha.style.display = '');

                    renderPaginationButtons(totalPages);
                  }

                  function renderPaginationButtons(totalPages) {
                    const container = document.getElementById('paginationContainer');
                    const prevPageBtn = document.getElementById('prevPage');
                    const nextPageBtn = document.getElementById('nextPage');

                    // Remove botões anteriores
                    const existingButtons = container.querySelectorAll('.page-number');
                    existingButtons.forEach(btn => btn.remove());

                    // Atualiza estado dos botões
                    prevPageBtn.classList.toggle('disabled', currentPage === 1);
                    nextPageBtn.classList.toggle('disabled', currentPage >= totalPages);

                    prevPageBtn.onclick = (e) => {
                      e.preventDefault();
                      if (currentPage > 1) {
                        currentPage--;
                        renderTable();
                      }
                    };

                    nextPageBtn.onclick = (e) => {
                      e.preventDefault();
                      if (currentPage < totalPages) {
                        currentPage++;
                        renderTable();
                      }
                    };

                    const maxVisibleButtons = 5;
                    let startPage = Math.max(1, currentPage - Math.floor(maxVisibleButtons / 2));
                    let endPage = Math.min(totalPages, startPage + maxVisibleButtons - 1);

                    if (endPage - startPage + 1 < maxVisibleButtons) {
                      startPage = Math.max(1, endPage - maxVisibleButtons + 1);
                    }

                    if (startPage > 1) {
                      const firstPageBtn = document.createElement('li');
                      firstPageBtn.className = 'page-item page-number';
                      firstPageBtn.innerHTML = `<a class="page-link" href="#" style="padding: 0.3rem 0.6rem; font-size: 0.875rem;">1</a>`;
                      firstPageBtn.onclick = (e) => {
                        e.preventDefault();
                        currentPage = 1;
                        renderTable();
                      };
                      container.insertBefore(firstPageBtn, nextPageBtn);

                      if (startPage > 2) {
                        const ellipsis = document.createElement('li');
                        ellipsis.className = 'page-item disabled';
                        ellipsis.innerHTML = `<span class="page-link">...</span>`;
                        container.insertBefore(ellipsis, nextPageBtn);
                      }
                    }

                    for (let i = startPage; i <= endPage; i++) {
                      const pageBtn = document.createElement('li');
                      pageBtn.className = `page-item page-number ${i === currentPage ? 'active' : ''}`;
                      pageBtn.innerHTML = `<a class="page-link" href="#" style="padding: 0.3rem 0.6rem; font-size: 0.875rem;">${i}</a>`;
                      pageBtn.onclick = (e) => {
                        e.preventDefault();
                        currentPage = i;
                        renderTable();
                      };
                      container.insertBefore(pageBtn, nextPageBtn);
                    }

                    if (endPage < totalPages) {
                      if (endPage < totalPages - 1) {
                        const ellipsis = document.createElement('li');
                        ellipsis.className = 'page-item disabled';
                        ellipsis.innerHTML = `<span class="page-link">...</span>`;
                        container.insertBefore(ellipsis, nextPageBtn);
                      }

                      const lastPageBtn = document.createElement('li');
                      lastPageBtn.className = 'page-item page-number';
                      lastPageBtn.innerHTML = `<a class="page-link" href="#" style="padding: 0.3rem 0.6rem; font-size: 0.875rem;">${totalPages}</a>`;
                      lastPageBtn.onclick = (e) => {
                        e.preventDefault();
                        currentPage = totalPages;
                        renderTable();
                      };
                      container.insertBefore(lastPageBtn, nextPageBtn);
                    }
                  }

                  // Inicializa
                  renderTable();

                  // Filtro por texto
                  if (searchInput) {
                    searchInput.addEventListener('input', () => {
                      currentPage = 1;
                      renderTable();
                    });
                  }
                });
              </script>

            </div>
          </div>
        </div>
        <!-- / Content -->

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
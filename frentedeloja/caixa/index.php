<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// ✅ Recupera o identificador vindo da URL
$idSelecionado = $_GET['id'] ?? '';

// ✅ Verifica se a pessoa está logada
if (
  !isset($_SESSION['usuario_logado']) ||
  !isset($_SESSION['empresa_id']) ||
  !isset($_SESSION['tipo_empresa']) ||
  !isset($_SESSION['usuario_id']) // Verifica se o ID do usuário está na sessão
) {
  header("Location: ../index.php?id=$idSelecionado");
  exit;
}

// ✅ Conexão com o banco de dados
require '../../assets/php/conexao.php';

$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id = $_SESSION['usuario_id'];
$tipoUsuarioSessao = $_SESSION['nivel']; // "Admin" ou "Funcionario"

try {
  if ($tipoUsuarioSessao === 'Admin') {
    // Buscar na tabela de Admins
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
  } else {
    // Buscar na tabela de Funcionários
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM funcionarios_acesso WHERE id = :id");
  }

  $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
  $stmt->execute();
  $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($usuario) {
    $nomeUsuario = $usuario['usuario'];
    $tipoUsuario = ucfirst($usuario['nivel']);
  } else {
    echo "<script>alert('Usuário não encontrado.'); window.location.href = './index.php?id=$idSelecionado';</script>";
    exit;
  }
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar nome e tipo do usuário: " . $e->getMessage() . "'); history.back();</script>";
  exit;
}

// ✅ Valida o tipo de empresa e o acesso permitido
if (str_starts_with($idSelecionado, 'principal_')) {
  if ($_SESSION['tipo_empresa'] !== 'principal' || $_SESSION['empresa_id'] != 1) {
    echo "<script>
            alert('Acesso negado!');
            window.location.href = '../index.php?id=$idSelecionado';
        </script>";
    exit;
  }
  $id = 1;
} elseif (str_starts_with($idSelecionado, 'filial_')) {
  $idFilial = (int) str_replace('filial_', '', $idSelecionado);
  if ($_SESSION['tipo_empresa'] !== 'filial' || $_SESSION['empresa_id'] != $idFilial) {
    echo "<script>
            alert('Acesso negado!');
            window.location.href = '../index.php?id=$idSelecionado';
        </script>";
    exit;
  }
  $id = $idFilial;
} else {
  echo "<script>
        alert('Empresa não identificada!');
        window.location.href = '../index.php?id=$idSelecionado';
    </script>";
  exit;
}

// ✅ Buscar imagem da empresa para usar como favicon
$iconeEmpresa = '../../assets/img/favicon/favicon.ico'; // Ícone padrão

try {
  $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1");
  $stmt->bindParam(':id_selecionado', $idSelecionado);
  $stmt->execute();
  $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($empresa && !empty($empresa['imagem'])) {
    $iconeEmpresa = $empresa['imagem'];
  }
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar ícone da empresa: " . addslashes($e->getMessage()) . "');</script>";
}

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

  <!-- Favicon da empresa carregado dinamicamente -->
  <link rel="icon" type="image/x-icon" href="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>" />

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
          <!-- Dashboard -->
          <li class="menu-item active">
            <a href="index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
              <i class="menu-icon tf-icons bx bx-home-circle"></i>
              <div data-i18n="Analytics">Dashboard</div>
            </a>
          </li>

          <!-- CAIXA -->
          <li class="menu-header small text-uppercase"><span class="menu-header-text">Frente de Caixa</span></li>

          <!-- Operações de Caixa -->
          <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-barcode-reader"></i>
              <div data-i18n="Caixa">Operações de Caixa</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item">
                <a href="./abrirCaixa.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Abrir Caixa</div>
                </a>
              </li>
              <li class="menu-item">
                <a href="./fecharCaixa.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Fechar Caixa</div>
                </a>
              </li>
              <li class="menu-item">
                <a href="./sangria.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Sangria</div>
                </a>
              </li>
              <li class="menu-item">
                <a href="./suprimento.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Suprimento</div>
                </a>
              </li>
            </ul>
          </li>

          <!-- Vendas -->
          <li class="menu-item">
            <a href="./vendaRapida.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
              <i class="menu-icon tf-icons bx bx-cart-alt"></i>
              <div data-i18n="Vendas">Venda Rápida</div>
            </a>
          </li>

          <!-- Cancelamento / Ajustes -->
          <li class="menu-item">
            <a href="./cancelarVenda.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
              <i class="menu-icon tf-icons bx bx-x-circle"></i>
              <div data-i18n="Cancelamento">Cancelar Venda</div>
            </a>
          </li>
          <!-- Relatórios -->
          <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-bar-chart-alt"></i>
              <div data-i18n="Relatórios">Relatórios</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item">
                <a href="./relatorioVendas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Resumo de Vendas</div>
                </a>
              </li>

            </ul>
          </li>
          <!-- END CAIXA -->

          </li>
          <!-- Misc -->
          <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
          <li class="menu-item">
            <a href="../sistemadeponto/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-group"></i>
              <div data-i18n="Authentications">SIstema de Ponto</div>
            </a>
          </li>
          <li class="menu-item">
            <a href="../Delivery/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
              <i class="menu-icon tf-icons bx bx-cart"></i>
              <div data-i18n="Basic">Delivery</div>
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
              </div>
            </div>
            <!-- /Search -->

            <ul class="navbar-nav flex-row align-items-center ms-auto">
              <!-- Place this tag where you want the button to render. -->
              <!-- User -->
              <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                  <div class="avatar avatar-online">
                    <img src="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>" alt
                      class="w-px-40 h-auto rounded-circle" />
                  </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li>
                    <a class="dropdown-item" href="#">
                      <div class="d-flex">
                        <div class="flex-shrink-0 me-3">
                          <div class="avatar avatar-online">
                            <img src="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>" alt
                              class="w-px-40 h-auto rounded-circle" />
                          </div>
                        </div>
                        <div class="flex-grow-1">
                          <span class="fw-semibold d-block"><?= htmlspecialchars($nomeUsuario); ?></span>
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
                      <span class="align-middle">Minha conta</span>
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
            <!-- Painel Principal -->
            <div class="col-lg-8 mb-4 order-0">
              <div class="card">
                <div class="d-flex align-items-end row">
                  <div class="col-sm-7">
                    <div class="card-body">
                      <h5 class="card-title text-primary saudacao" data-setor="Caixa"></h5>
                      <p class="mb-4">Veja as configurações do Caixa que foram atualizadas em seu perfil. Continue
                        explorando e ajustando-as conforme suas preferências.</p>
                    </div>
                  </div>
                  <div class="col-sm-5 text-center text-sm-left">
                    <div class="card-body pb-0 px-0 px-md-4">
                      <img src="../../assets/img/illustrations/man-with-laptop-light.png" height="140"
                        alt="Painel Açaínhadinhos" data-app-dark-img="illustrations/man-with-laptop-dark.png"
                        data-app-light-img="illustrations/man-with-laptop-light.png" />
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Métricas Rápidas -->
            <div class="col-lg-4 col-md-4 order-1">
              <div class="row">
                <div class="col-lg-6 col-md-12 col-6 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <div class="card-title d-flex align-items-start justify-content-between">
                        <div class="avatar flex-shrink-0">
                          <span class="avatar-initial rounded bg-label-primary">
                            <i class="bx bx-store"></i>
                          </span>
                        </div>
                      </div>
                      <span class="fw-semibold d-block mb-1">Vendas Físicas</span>
                      <h3 class="card-title mb-2">R$ 1.245</h3>
                      <small class="text-success fw-semibold"><i class="bx bx-up-arrow-alt"></i> +18% ontem</small>
                    </div>
                  </div>
                </div>
                <div class="col-lg-6 col-md-12 col-6 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <div class="card-title d-flex align-items-start justify-content-between">
                        <div class="avatar flex-shrink-0">
                          <span class="avatar-initial rounded bg-label-success">
                            <i class="bx bx-cart"></i>
                          </span>
                        </div>
                      </div>
                      <span class="fw-semibold d-block mb-1">Vendas Delivery</span>
                      <h3 class="card-title mb-2">R$ 845</h3>
                      <small class="text-success fw-semibold"><i class="bx bx-up-arrow-alt"></i> +25.6%</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Gráfico de Vendas por Canal - Versão Responsiva -->
            <div class="col-12 col-lg-8 order-2 order-md-2 mb-4">
              <div class="card h-100">
                <div class="d-flex flex-column flex-md-row h-100">

                  <!-- Parte principal do gráfico -->
                  <div class="col-12 col-md-8 d-flex flex-column order-2 order-md-1">
                    <h5 class="card-header m-0 me-2 pb-3">Vendas por Canal</h5>
                    <div id="salesChannelChart" class="px-2 flex-grow-1" style="min-height: 150px;"></div>
                  </div>

                  <!-- Painel lateral (com borda adaptável) -->
                  <div
                    class="col-12 col-md-4 d-flex flex-column order-1 order-md-2 border-bottom border-md-bottom-0 border-md-start">
                    <div class="card-body">
                      <div class="text-center">
                        <div class="dropdown">
                          <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button"
                            id="salesReportId" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            Hoje
                          </button>
                          <div class="dropdown-menu dropdown-menu-end" aria-labelledby="salesReportId">
                            <a class="dropdown-item" href="javascript:void(0);">Ontem</a>
                            <a class="dropdown-item" href="javascript:void(0);">Esta Semana</a>
                            <a class="dropdown-item" href="javascript:void(0);">Este Mês</a>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div id="channelTrendChart" class="flex-grow-1 px-2" style="min-height: 150px;"></div>

                    <div class="text-center fw-semibold pt-3 mb-2">Conversão Delivery: 65%</div>

                    <div class="d-flex px-xxl-4 px-lg-2 p-4 gap-xxl-3 gap-lg-1 gap-3 justify-content-between flex-wrap">
                      <div class="d-flex mb-2 mb-md-0">
                        <div class="me-2">
                          <span class="badge bg-label-primary p-2"><i class="bx bx-store text-primary"></i></span>
                        </div>
                        <div class="d-flex flex-column">
                          <small>Loja Física</small>
                          <h6 class="mb-0">R$ 1.245</h6>
                        </div>
                      </div>
                      <div class="d-flex">
                        <div class="me-2">
                          <span class="badge bg-label-info p-2"><i class="bx bx-cart text-info"></i></span>
                        </div>
                        <div class="d-flex flex-column">
                          <small>Delivery</small>
                          <h6 class="mb-0">R$ 845</h6>
                        </div>
                      </div>
                    </div>
                  </div>

                </div>
              </div>
            </div>

            <!-- Status de Pedidos e Métricas Secundárias -->
            <div class="col-12 col-md-4 col-lg-4 order-3 order-md-3">
              <div class="row h-100">
                <!-- Status de Pedidos Delivery -->
                <div class="col-lg-6 col-md-12 col-6 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <div class="card-title d-flex align-items-start justify-content-between">
                        <div class="avatar flex-shrink-0">
                          <span class="avatar-initial rounded bg-label-info">
                            <i class="bx bx-user"></i>
                          </span>
                        </div>
                      </div>
                      <span class="fw-semibold d-block mb-1">Clientes Hoje</span>
                      <h3 class="card-title mb-2">42</h3>
                      <small class="text-success fw-semibold"><i class="bx bx-up-arrow-alt"></i> +12%</small>
                    </div>
                  </div>
                </div>
                <div class="col-lg-6 col-md-12 col-6 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <div class="card-title d-flex align-items-start justify-content-between">
                        <div class="avatar flex-shrink-0">
                          <span class="avatar-initial rounded bg-label-danger">
                            <i class="bx bx-time"></i>
                          </span>
                        </div>
                      </div>
                      <span class="fw-semibold d-block mb-1">Tempo Médio Delivery</span>
                      <h3 class="card-title mb-2">35 min</h3>
                      <small class="text-danger fw-semibold"><i class="bx bx-down-arrow-alt"></i> -5.2%</small>
                    </div>
                  </div>
                </div>
                <div class="col-12 mb-4">
                  <div class="card h-100">
                    <div class="card-body">
                      <h5 class="card-title mb-3">Status de Pedidos</h5>
                      <div class="d-flex align-items-center mb-3">
                        <div class="avatar flex-shrink-0 me-3">
                          <span class="avatar-initial rounded bg-label-primary">
                            <i class="bx bx-package"></i>
                          </span>
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                          <div class="me-2">
                            <h6 class="mb-0">Em Preparo</h6>
                            <small class="text-muted">5 pedidos</small>
                          </div>
                          <div class="badge bg-label-primary rounded-pill">Priorizar</div>
                        </div>
                      </div>
                      <div class="d-flex align-items-center mb-3">
                        <div class="avatar flex-shrink-0 me-3">
                          <span class="avatar-initial rounded bg-label-warning">
                            <i class="bx bx-time"></i>
                          </span>
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                          <div class="me-2">
                            <h6 class="mb-0">Aguardando Retirada</h6>
                            <small class="text-muted">3 pedidos</small>
                          </div>
                          <div class="badge bg-label-warning rounded-pill">Avisar</div>
                        </div>
                      </div>
                      <div class="d-flex align-items-center">
                        <div class="avatar flex-shrink-0 me-3">
                          <span class="avatar-initial rounded bg-label-success">
                            <i class="bx bx-cart"></i>
                          </span>
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                          <div class="me-2">
                            <h6 class="mb-0">Em Rota</h6>
                            <small class="text-muted">7 pedidos</small>
                          </div>
                          <div class="badge bg-label-success rounded-pill">Monitorar</div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <!-- Top Combinações -->
            <div class="col-md-6 col-lg-4 col-xl-4 order-2 order-md-1 mb-4">
              <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between pb-0">
                  <div class="card-title mb-0">
                    <h5 class="m-0 me-2">Combinações Populares</h5>
                    <small class="text-muted">Hoje</small>
                  </div>
                  <div class="dropdown">
                    <button class="btn p-0" type="button" id="topCombinations" data-bs-toggle="dropdown"
                      aria-haspopup="true" aria-expanded="false">
                      <i class="bx bx-dots-vertical-rounded"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="topCombinations">
                      <a class="dropdown-item" href="javascript:void(0);">Esta Semana</a>
                      <a class="dropdown-item" href="javascript:void(0);">Este Mês</a>
                    </div>
                  </div>
                </div>
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex flex-column align-items-center gap-1">
                      <h2 class="mb-2">5</h2>
                      <span>Combinações</span>
                    </div>
                    <div id="topCombinationsChart" style="width: 100px; height: 100px;"></div>
                  </div>
                  <ul class="p-0 m-0">
                    <li class="d-flex mb-4 pb-1">
                      <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-primary">
                          <i class="bx bx-bowl-hot"></i>
                        </span>
                      </div>
                      <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                        <div class="me-2">
                          <h6 class="mb-0">Açaí Tradicional + Granola + Banana</h6>
                          <small class="text-muted">28 pedidos</small>
                        </div>
                        <div class="user-progress">
                          <small class="fw-semibold">R$ 29,90</small>
                        </div>
                      </div>
                    </li>
                    <li class="d-flex mb-4 pb-1">
                      <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-success">
                          <i class="bx bx-cookie"></i>
                        </span>
                      </div>
                      <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                        <div class="me-2">
                          <h6 class="mb-0">Açaí com Nutella + Morango</h6>
                          <small class="text-muted">22 pedidos</small>
                        </div>
                        <div class="user-progress">
                          <small class="fw-semibold">R$ 34,90</small>
                        </div>
                      </div>
                    </li>
                    <li class="d-flex mb-4 pb-1">
                      <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-info">
                          <i class="bx bx-pear"></i>
                        </span>
                      </div>
                      <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                        <div class="me-2">
                          <h6 class="mb-0">Açaí com Leite Ninho + Paçoca</h6>
                          <small class="text-muted">18 pedidos</small>
                        </div>
                        <div class="user-progress">
                          <small class="fw-semibold">R$ 32,90</small>
                        </div>
                      </div>
                    </li>
                    <li class="d-flex">
                      <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-secondary">
                          <i class="bx bx-candles"></i>
                        </span>
                      </div>
                      <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                        <div class="me-2">
                          <h6 class="mb-0">Açaí Fitness + Whey + Aveia</h6>
                          <small class="text-muted">15 pedidos</small>
                        </div>
                        <div class="user-progress">
                          <small class="fw-semibold">R$ 39,90</small>
                        </div>
                      </div>
                    </li>
                  </ul>
                </div>
              </div>
            </div>

            <!-- Métodos de Pagamento por Canal -->
            <div class="col-md-6 col-lg-4 order-1 order-md-2 mb-4">
              <div class="card h-100">
                <div class="card-header">
                  <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                    <li class="nav-item" role="presentation">
                      <button class="nav-link active" id="pills-home-tab" data-bs-toggle="pill"
                        data-bs-target="#pills-home" type="button" role="tab" aria-controls="pills-home"
                        aria-selected="true">
                        <i class="bx bx-store"></i> Loja
                      </button>
                    </li>
                    <li class="nav-item" role="presentation">
                      <button class="nav-link" id="pills-profile-tab" data-bs-toggle="pill"
                        data-bs-target="#pills-profile" type="button" role="tab" aria-controls="pills-profile"
                        aria-selected="false">
                        <i class="bx bx-cart"></i> Delivery
                      </button>
                    </li>
                  </ul>
                </div>
                <div class="card-body">
                  <div class="tab-content" id="pills-tabContent">
                    <div class="tab-pane fade show active" id="pills-home" role="tabpanel"
                      aria-labelledby="pills-home-tab">
                      <div class="row">
                        <div class="col-6 mb-4">
                          <div class="d-flex flex-column">
                            <div class="avatar flex-shrink-0 mb-3">
                              <span class="avatar-initial rounded bg-label-primary">
                                <i class="bx bx-credit-card"></i>
                              </span>
                            </div>
                            <span class="d-block mb-1">Cartão Crédito</span>
                            <h4 class="mb-1">R$ 745</h4>
                            <small class="text-success fw-semibold">
                              <i class="bx bx-up-arrow-alt"></i> 62.3%
                            </small>
                          </div>
                        </div>
                        <div class="col-6 mb-4">
                          <div class="d-flex flex-column">
                            <div class="avatar flex-shrink-0 mb-3">
                              <span class="avatar-initial rounded bg-label-success">
                                <i class="bx bx-money"></i>
                              </span>
                            </div>
                            <span class="d-block mb-1">Dinheiro</span>
                            <h4 class="mb-1">R$ 398</h4>
                            <small class="text-danger fw-semibold">
                              <i class="bx bx-down-arrow-alt"></i> 12.4%
                            </small>
                          </div>
                        </div>
                        <div class="col-6 mb-4">
                          <div class="d-flex flex-column">
                            <div class="avatar flex-shrink-0 mb-3">
                              <span class="avatar-initial rounded bg-label-info">
                                <i class="bx bx-transfer"></i>
                              </span>
                            </div>
                            <span class="d-block mb-1">PIX</span>
                            <h4 class="mb-1">R$ 425</h4>
                            <small class="text-success fw-semibold">
                              <i class="bx bx-up-arrow-alt"></i> 42.9%
                            </small>
                          </div>
                        </div>
                        <div class="col-6 mb-4">
                          <div class="d-flex flex-column">
                            <div class="avatar flex-shrink-0 mb-3">
                              <span class="avatar-initial rounded bg-label-warning">
                                <i class="bx bx-credit-card-front"></i>
                              </span>
                            </div>
                            <span class="d-block mb-1">Cartão Débito</span>
                            <h4 class="mb-1">R$ 284</h4>
                            <small class="text-success fw-semibold">
                              <i class="bx bx-up-arrow-alt"></i> 8.1%
                            </small>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="tab-pane fade" id="pills-profile" role="tabpanel" aria-labelledby="pills-profile-tab">
                      <div class="row">
                        <div class="col-6 mb-4">
                          <div class="d-flex flex-column">
                            <div class="avatar flex-shrink-0 mb-3">
                              <span class="avatar-initial rounded bg-label-primary">
                                <i class="bx bx-credit-card"></i>
                              </span>
                            </div>
                            <span class="d-block mb-1">Cartão Crédito</span>
                            <h4 class="mb-1">R$ 520</h4>
                            <small class="text-success fw-semibold">
                              <i class="bx bx-up-arrow-alt"></i> 72.3%
                            </small>
                          </div>
                        </div>
                        <div class="col-6 mb-4">
                          <div class="d-flex flex-column">
                            <div class="avatar flex-shrink-0 mb-3">
                              <span class="avatar-initial rounded bg-label-info">
                                <i class="bx bx-transfer"></i>
                              </span>
                            </div>
                            <span class="d-block mb-1">PIX</span>
                            <h4 class="mb-1">R$ 625</h4>
                            <small class="text-success fw-semibold">
                              <i class="bx bx-up-arrow-alt"></i> 62.9%
                            </small>
                          </div>
                        </div>
                        <div class="col-6 mb-4">
                          <div class="d-flex flex-column">
                            <div class="avatar flex-shrink-0 mb-3">
                              <span class="avatar-initial rounded bg-label-warning">
                                <i class="bx bx-credit-card-front"></i>
                              </span>
                            </div>
                            <span class="d-block mb-1">Cartão Débito</span>
                            <h4 class="mb-1">R$ 184</h4>
                            <small class="text-success fw-semibold">
                              <i class="bx bx-up-arrow-alt"></i> 18.1%
                            </small>
                          </div>
                        </div>
                        <div class="col-6 mb-4">
                          <div class="d-flex flex-column">
                            <div class="avatar flex-shrink-0 mb-3">
                              <span class="avatar-initial rounded bg-label-danger">
                                <i class="bx bx-money"></i>
                              </span>
                            </div>
                            <span class="d-block mb-1">Dinheiro</span>
                            <h4 class="mb-1">R$ 120</h4>
                            <small class="text-danger fw-semibold">
                              <i class="bx bx-down-arrow-alt"></i> 22.4%
                            </small>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Pedidos Recentes -->
            <div class="col-md-6 col-lg-4 order-3 order-md-3 mb-4">
              <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                  <h5 class="card-title m-0 me-2">Pedidos Recentes</h5>
                  <div class="dropdown">
                    <button class="btn p-0" type="button" id="recentOrders" data-bs-toggle="dropdown"
                      aria-haspopup="true" aria-expanded="false">
                      <i class="bx bx-dots-vertical-rounded"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="recentOrders">
                      <a class="dropdown-item" href="javascript:void(0);">Últimas 2 horas</a>
                      <a class="dropdown-item" href="javascript:void(0);">Hoje</a>
                      <a class="dropdown-item" href="javascript:void(0);">Ontem</a>
                    </div>
                  </div>
                </div>
                <div class="card-body">
                  <ul class="p-0 m-0">
                    <!-- Item de Pedido -->
                    <li class="d-flex mb-4 pb-1">
                      <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-primary">
                          <i class="bx bx-store"></i>
                        </span>
                      </div>
                      <div class="d-flex w-100 align-items-center justify-content-between gap-2 flex-wrap">
                        <div class="me-2" style="flex: 1; min-width: 0;">
                          <h6 class="mb-0 text-truncate">#458 - Presencial</h6>
                          <small class="text-muted text-truncate d-block" style="max-width: 200px;">Açaí Tradicional +
                            Granola</small>
                          <small class="text-muted d-block">10:25 AM</small>
                        </div>
                        <div class="text-end">
                          <h6 class="mb-0">R$ 24,90</h6>
                          <span class="badge bg-label-success">Pago</span>
                        </div>
                      </div>
                    </li>

                    <li class="d-flex mb-4 pb-1">
                      <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-info">
                          <i class="bx bx-cart"></i>
                        </span>
                      </div>
                      <div class="d-flex w-100 align-items-center justify-content-between gap-2 flex-wrap">
                        <div class="me-2" style="flex: 1; min-width: 0;">
                          <h6 class="mb-0 text-truncate">#459 - Delivery</h6>
                          <small class="text-muted text-truncate d-block" style="max-width: 200px;">2 Açaí Médio +
                            Nutella</small>
                          <small class="text-muted d-block">10:42 AM</small>
                        </div>
                        <div class="text-end">
                          <h6 class="mb-0">R$ 59,80</h6>
                          <span class="badge bg-label-warning">Preparo</span>
                        </div>
                      </div>
                    </li>

                    <li class="d-flex mb-4 pb-1">
                      <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-primary">
                          <i class="bx bx-store"></i>
                        </span>
                      </div>
                      <div class="d-flex w-100 align-items-center justify-content-between gap-2 flex-wrap">
                        <div class="me-2" style="flex: 1; min-width: 0;">
                          <h6 class="mb-0 text-truncate">#460 - Presencial</h6>
                          <small class="text-muted text-truncate d-block" style="max-width: 200px;">Açaí Fitness +
                            Whey</small>
                          <small class="text-muted d-block">11:05 AM</small>
                        </div>
                        <div class="text-end">
                          <h6 class="mb-0">R$ 39,90</h6>
                          <span class="badge bg-label-success">Pago</span>
                        </div>
                      </div>
                    </li>

                    <li class="d-flex mb-4 pb-1">
                      <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-info">
                          <i class="bx bx-cart"></i>
                        </span>
                      </div>
                      <div class="d-flex w-100 align-items-center justify-content-between gap-2 flex-wrap">
                        <div class="me-2" style="flex: 1; min-width: 0;">
                          <h6 class="mb-0 text-truncate">#461 - Delivery</h6>
                          <small class="text-muted text-truncate d-block" style="max-width: 200px;">Açaí Familiar + 6
                            acompanhamentos</small>
                          <small class="text-muted d-block">11:23 AM</small>
                        </div>
                        <div class="text-end">
                          <h6 class="mb-0">R$ 69,90</h6>
                          <span class="badge bg-label-primary">Rota</span>
                        </div>
                      </div>
                    </li>

                    <li class="d-flex">
                      <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-primary">
                          <i class="bx bx-store"></i>
                        </span>
                      </div>
                      <div class="d-flex w-100 align-items-center justify-content-between gap-2 flex-wrap">
                        <div class="me-2" style="flex: 1; min-width: 0;">
                          <h6 class="mb-0 text-truncate">#462 - Presencial</h6>
                          <small class="text-muted text-truncate d-block" style="max-width: 200px;">Açaí Pequeno + Leite
                            Condensado</small>
                          <small class="text-muted d-block">11:45 AM</small>
                        </div>
                        <div class="text-end">
                          <h6 class="mb-0">R$ 19,90</h6>
                          <span class="badge bg-label-success">Pago</span>
                        </div>
                      </div>
                    </li>
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
  <script src="../../js/graficoDashboard.js"></script>

  <!-- Main JS -->
  <script src="../../assets/js/main.js"></script>

  <!-- Page JS -->
  <script src="../../assets/js/dashboards-analytics.js"></script>

  <!-- Place this tag in your head or just before your close body tag. -->
  <script async defer src="https://buttons.github.io/buttons.js"></script>
</body>

</html>
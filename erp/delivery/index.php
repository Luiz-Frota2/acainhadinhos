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
  !isset($_SESSION['usuario_id']) // adiciona verificação do id do usuário
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
} else {
  echo "<script>
          alert('Empresa não identificada!');
          window.location.href = '.././login.php?id=$idSelecionado';
      </script>";
  exit;
}

// ✅ Buscar imagem da tabela sobre_empresa com base no idSelecionado
try {
  $sql = "SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1";
  $stmt = $pdo->prepare($sql);
  $stmt->bindParam(':id_selecionado', $idSelecionado, PDO::PARAM_STR);
  $stmt->execute();
  $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);

  $logoEmpresa = !empty($empresaSobre['imagem'])
    ? "../../assets/img/empresa/" . $empresaSobre['imagem']
    : "../../assets/img/favicon/logo.png"; // fallback padrão
} catch (PDOException $e) {
  $logoEmpresa = "../../assets/img/favicon/logo.png"; // fallback em caso de erro
}

// ✅ Se chegou até aqui, o acesso está liberado

// ✅ Buscar nome e nível do usuário logado
$nomeUsuario = 'Usuário';
$nivelUsuario = 'Comum'; // Valor padrão
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

?>

<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
  data-assets-path="../assets/">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>ERP - Delivery</title>

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
          <!-- Dashboard -->
          <li class="menu-item active">
            <a href="index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
              <i class="menu-icon tf-icons bx bx-home-circle"></i>
              <div data-i18n="Analytics">Dashboard</div>
            </a>
          </li>

          <!--DELIVERY-->
          <li class="menu-header small text-uppercase"><span class="menu-header-text">Delivery</span></li>
          <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons tf-icons bx bx-food-menu"></i>
              <div data-i18n="Authentications">Cardápio</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item">
                <a href="./produtoAdicionados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Produtos Adicionados</div>
                </a>
              </li>
            </ul>
          </li>
          <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons tf-icons bx bx-cog"></i>
              <div data-i18n="Authentications">Configuração</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item">
                <a href="./deliveryRetirada.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Delivery e Retirada</div>
                </a>
              </li>
            </ul>
            <ul class="menu-sub">
              <li class="menu-item">
                <a href="./formaPagamento.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Formas de Pagamentos </div>
                </a>
              </li>
            </ul>
          </li>
          <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons tf-icons bx  bx-building"></i>
              <div data-i18n="Authentications">Empresa</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item">
                <a href="./sobreEmpresa.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Sobre</div>
                </a>
              </li>
            </ul>
            <ul class="menu-sub">
              <li class="menu-item">
                <a href="./enderecoEmpresa.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Endereço</div>
                </a>
              </li>
            </ul>
            <ul class="menu-sub">
              <li class="menu-item">
                <a href="./horarioFuncionamento.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Horário</div>
                </a>
              </li>
            </ul>
          </li>
          <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons tf-icons bx bx-trending-up"></i>
              <div data-i18n="Authentications">Relatorios</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item">
                <a href="./listarPedidos.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Lista de Pedidos</div>
                </a>
              </li>
            </ul>
            <ul class="menu-sub">
              <li class="menu-item">
                <a href="./maisVendidos.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Mais vendidos</div>
                </a>
              </li>
            </ul>
            <ul class="menu-sub">
              <li class="menu-item">
                <a href="./relatoriClientes.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Clientes</div>
                </a>
              </li>
            </ul>
            <ul class="menu-sub">
              <li class="menu-item">
                <a href="./relatorioVendas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Vendas</div>
                </a>
              </li>
            </ul>
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
                <input type="text" class="form-control border-0 shadow-none" placeholder="Search..."
                  aria-label="Search..." />
              </div>
            </div>
            <!-- /Search -->

            <ul class="navbar-nav flex-row align-items-center ms-auto">
              <!-- Place this tag where you want the button to render. -->
              <!-- User -->
              <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                  <div class="avatar avatar-online">
                    <img src="../../assets/img/avatars/1.png" alt class="w-px-40 h-auto rounded-circle" />
                  </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li>
                    <a class="dropdown-item" href="#">
                      <div class="d-flex">
                        <div class="flex-shrink-0 me-3">
                          <div class="avatar avatar-online">
                            <img src="../../assets/img/avatars/1.png" alt class="w-px-40 h-auto rounded-circle" />
                          </div>
                        </div>
                        <div class="flex-grow-1">
                          <!-- Exibindo o nome e nível do usuário -->
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
            <div class="col-lg-8 mb-4 order-0">
              <div class="card">
                <div class="d-flex align-items-end row">
                  <div class="col-sm-7">
                    <div class="card-body">
                      <h5 class="card-title text-primary saudacao" data-setor="Delivery"></h5>
                      <p class="mb-4">Suas configurações do delivery foram atualizadas em seu perfil. Continue
                        explorando e ajustando-as conforme suas preferências.</p>

                    </div>
                  </div>
                  <div class="col-sm-5 text-center text-sm-left">
                    <div class="card-body pb-0 px-0 px-md-4">
                      <img src="../../assets/img/illustrations/man-with-laptop-light.png" height="140"
                        alt="View Badge User" data-app-dark-img="illustrations/man-with-laptop-dark.png"
                        data-app-light-img="illustrations/man-with-laptop-light.png" />
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-4 col-md-4 order-1">
              <div class="row">
                <div class="col-lg-6 col-md-12 col-6 mb-4">
                  <div class="card">
                    <div class="card-body">
                      <div class="card-title d-flex align-items-start justify-content-between">
                        <div class="avatar flex-shrink-0">
                          <img src="../../assets/img/icons/unicons/chart-success.png" alt="gráfico de sucesso"
                            class="rounded" />
                        </div>
                        <div class="dropdown">
                          <button class="btn p-0" type="button" id="cardOpt3" data-bs-toggle="dropdown"
                            aria-haspopup="true" aria-expanded="false">
                            <i class="bx bx-dots-vertical-rounded"></i>
                          </button>
                          <div class="dropdown-menu dropdown-menu-end" aria-labelledby="cardOpt3">
                            <a class="dropdown-item" href="javascript:void(0);">Ver Mais</a>
                            <a class="dropdown-item" href="javascript:void(0);">Excluir</a>
                          </div>
                        </div>
                      </div>
                      <span class="fw-semibold d-block mb-1">Lucro</span>
                      <h3 class="card-title mb-2">$12.628</h3>
                      <small class="text-success fw-semibold"><i class="bx bx-up-arrow-alt"></i> +72,80%</small>
                    </div>
                  </div>
                </div>
                <div class="col-lg-6 col-md-12 col-6 mb-4">
                  <div class="card">
                    <div class="card-body">
                      <div class="card-title d-flex align-items-start justify-content-between">
                        <div class="avatar flex-shrink-0">
                          <img src="../../assets/img/icons/unicons/wallet-info.png" alt="Cartão de Crédito"
                            class="rounded" />
                        </div>
                        <div class="dropdown">
                          <button class="btn p-0" type="button" id="cardOpt6" data-bs-toggle="dropdown"
                            aria-haspopup="true" aria-expanded="false">
                            <i class="bx bx-dots-vertical-rounded"></i>
                          </button>
                          <div class="dropdown-menu dropdown-menu-end" aria-labelledby="cardOpt6">
                            <a class="dropdown-item" href="javascript:void(0);">Ver Mais</a>
                            <a class="dropdown-item" href="javascript:void(0);">Excluir</a>
                          </div>
                        </div>
                      </div>
                      <span>Vendas</span>
                      <h3 class="card-title text-nowrap mb-1">$4.679</h3>
                      <small class="text-success fw-semibold"><i class="bx bx-up-arrow-alt"></i> +28,42%</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Total Revenue -->
            <div class="col-12 col-lg-8 order-2 order-md-3 order-lg-2 mb-4">
              <div class="card">
                <div class="row row-bordered g-0">
                  <div class="col-md-8">
                    <h5 class="card-header m-0 me-2 pb-3">Receita Total</h5>
                    <div id="totalRevenueChart" class="px-2"></div>
                  </div>
                  <div class="col-md-4">
                    <div class="card-body">
                      <div class="text-center">
                        <div class="dropdown">
                          <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button"
                            id="growthReportId" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            2025
                          </button>
                          <div class="dropdown-menu dropdown-menu-end" aria-labelledby="growthReportId">
                            <a class="dropdown-item" href="javascript:void(0);">2024</a>
                            <a class="dropdown-item" href="javascript:void(0);">2023</a>
                            <a class="dropdown-item" href="javascript:void(0);">2022</a>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div id="growthChart"></div>
                    <div class="text-center fw-semibold pt-3 mb-2">62% de Crescimento da Empresa</div>

                    <div class="d-flex px-xxl-4 px-lg-2 p-4 gap-xxl-3 gap-lg-1 gap-3 justify-content-between">
                      <div class="d-flex">
                        <div class="me-2">
                          <span class="badge bg-label-primary p-2"><i class="bx bx-dollar text-primary"></i></span>
                        </div>
                        <div class="d-flex flex-column">
                          <small>2022</small>
                          <h6 class="mb-0">$32.5k</h6>
                        </div>
                      </div>
                      <div class="d-flex">
                        <div class="me-2">
                          <span class="badge bg-label-info p-2"><i class="bx bx-wallet text-info"></i></span>
                        </div>
                        <div class="d-flex flex-column">
                          <small>2021</small>
                          <h6 class="mb-0">$41.2k</h6>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!--/ Total Revenue -->
            <div class="col-12 col-md-8 col-lg-4 order-3 order-md-2">
              <div class="row">
                <div class="col-6 mb-4">
                  <div class="card">
                    <div class="card-body">
                      <div class="card-title d-flex align-items-start justify-content-between">
                        <div class="avatar flex-shrink-0">
                          <img src="../../assets/img/icons/unicons/paypal.png" alt="Cartão de Crédito"
                            class="rounded" />
                        </div>
                        <div class="dropdown">
                          <button class="btn p-0" type="button" id="cardOpt4" data-bs-toggle="dropdown"
                            aria-haspopup="true" aria-expanded="false">
                            <i class="bx bx-dots-vertical-rounded"></i>
                          </button>
                          <div class="dropdown-menu dropdown-menu-end" aria-labelledby="cardOpt4">
                            <a class="dropdown-item" href="javascript:void(0);">Ver Mais</a>
                            <a class="dropdown-item" href="javascript:void(0);">Excluir</a>
                          </div>
                        </div>
                      </div>
                      <span class="d-block mb-1">Pagamentos</span>
                      <h3 class="card-title text-nowrap mb-2">$2.456</h3>
                      <small class="text-danger fw-semibold"><i class="bx bx-down-arrow-alt"></i> -14.82%</small>
                    </div>
                  </div>
                </div>
                <div class="col-6 mb-4">
                  <div class="card">
                    <div class="card-body">
                      <div class="card-title d-flex align-items-start justify-content-between">
                        <div class="avatar flex-shrink-0">
                          <img src="../../assets/img/icons/unicons/cc-primary.png" alt="Cartão de Crédito"
                            class="rounded" />
                        </div>
                        <div class="dropdown">
                          <button class="btn p-0" type="button" id="cardOpt1" data-bs-toggle="dropdown"
                            aria-haspopup="true" aria-expanded="false">
                            <i class="bx bx-dots-vertical-rounded"></i>
                          </button>
                          <div class="dropdown-menu" aria-labelledby="cardOpt1">
                            <a class="dropdown-item" href="javascript:void(0);">Ver Mais</a>
                            <a class="dropdown-item" href="javascript:void(0);">Excluir</a>
                          </div>
                        </div>
                      </div>
                      <span class="fw-semibold d-block mb-1">Transações</span>
                      <h3 class="card-title mb-2">$14.857</h3>
                      <small class="text-success fw-semibold"><i class="bx bx-up-arrow-alt"></i> +28.14%</small>
                    </div>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col-12 mb-4">
                  <div class="card">
                    <div class="card-body">
                      <div class="d-flex justify-content-between flex-sm-row flex-column gap-3">
                        <div class="d-flex flex-sm-column flex-row align-items-start justify-content-between">
                          <div class="card-title">
                            <h5 class="text-nowrap mb-2">Relatório de Perfil</h5>
                            <span class="badge bg-label-warning rounded-pill">Ano 2021</span>
                          </div>
                          <div class="mt-sm-auto">
                            <small class="text-success text-nowrap fw-semibold"><i class="bx bx-chevron-up"></i>
                              68.2%</small>
                            <h3 class="mb-0">$84.686k</h3>
                          </div>
                        </div>
                        <div id="profileReportChart"></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="row">
            <!-- Order Statistics -->
            <div class="col-md-6 col-lg-4 col-xl-4 order-0 mb-4">
              <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between pb-0">
                  <div class="card-title mb-0">
                    <h5 class="m-0 me-2">Estatísticas de Pedidos</h5>
                    <small class="text-muted">42.82k Vendas Totais</small>
                  </div>
                  <div class="dropdown">
                    <button class="btn p-0" type="button" id="orederStatistics" data-bs-toggle="dropdown"
                      aria-haspopup="true" aria-expanded="false">
                      <i class="bx bx-dots-vertical-rounded"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="orederStatistics">
                      <a class="dropdown-item" href="javascript:void(0);">Selecionar Tudo</a>
                      <a class="dropdown-item" href="javascript:void(0);">Atualizar</a>
                      <a class="dropdown-item" href="javascript:void(0);">Compartilhar</a>
                    </div>
                  </div>
                </div>
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex flex-column align-items-center gap-1">
                      <h2 class="mb-2">8.258</h2>
                      <span>Pedidos Totais</span>
                    </div>
                    <div id="orderStatisticsChart"></div>
                  </div>
                  <ul class="p-0 m-0">
                    <li class="d-flex mb-4 pb-1">
                      <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-primary">
                          <i class="bx bx-mobile-alt"></i>
                        </span>
                      </div>
                      <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                        <div class="me-2">
                          <h6 class="mb-0">Eletrônicos</h6>
                          <small class="text-muted">Celular, Fones de Ouvido, TV</small>
                        </div>
                        <div class="user-progress">
                          <small class="fw-semibold">82.5k</small>
                        </div>
                      </div>
                    </li>
                    <li class="d-flex mb-4 pb-1">
                      <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-success">
                          <i class="bx bx-closet"></i>
                        </span>
                      </div>
                      <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                        <div class="me-2">
                          <h6 class="mb-0">Moda</h6>
                          <small class="text-muted">Camiseta, Calça Jeans, Sapatos</small>
                        </div>
                        <div class="user-progress">
                          <small class="fw-semibold">23.8k</small>
                        </div>
                      </div>
                    </li>
                    <li class="d-flex mb-4 pb-1">
                      <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-info">
                          <i class="bx bx-home-alt"></i>
                        </span>
                      </div>
                      <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                        <div class="me-2">
                          <h6 class="mb-0">Decoração</h6>
                          <small class="text-muted">Arte, Jantar</small>
                        </div>
                        <div class="user-progress">
                          <small class="fw-semibold">849k</small>
                        </div>
                      </div>
                    </li>
                    <li class="d-flex">
                      <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-secondary">
                          <i class="bx bx-football"></i>
                        </span>
                      </div>
                      <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                        <div class="me-2">
                          <h6 class="mb-0">Esportes</h6>
                          <small class="text-muted">Futebol, Kit de Críquete</small>
                        </div>
                        <div class="user-progress">
                          <small class="fw-semibold">99</small>
                        </div>
                      </div>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
            <!--/ Order Statistics -->

            <!-- Expense Overview -->
            <div class="col-md-6 col-lg-4 order-1 mb-4">
              <div class="card h-100">
                <div class="card-header">
                  <ul class="nav nav-pills" role="tablist">
                    <li class="nav-item">
                      <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab"
                        data-bs-target="#navs-tabs-line-card-income" aria-controls="navs-tabs-line-card-income"
                        aria-selected="true">
                        Receita
                      </button>
                    </li>
                    <li class="nav-item">
                      <button type="button" class="nav-link" role="tab">Despesas</button>
                    </li>
                    <li class="nav-item">
                      <button type="button" class="nav-link" role="tab">Lucro</button>
                    </li>
                  </ul>
                </div>
                <div class="card-body px-0">
                  <div class="tab-content p-0">
                    <div class="tab-pane fade show active" id="navs-tabs-line-card-income" role="tabpanel">
                      <div class="d-flex p-4 pt-3">
                        <div class="avatar flex-shrink-0 me-3">
                          <img src="../../assets/img/icons/unicons/wallet.png" alt="Usuário" />
                        </div>
                        <div>
                          <small class="text-muted d-block">Saldo Total</small>
                          <div class="d-flex align-items-center">
                            <h6 class="mb-0 me-1">$459.10</h6>
                            <small class="text-success fw-semibold">
                              <i class="bx bx-chevron-up"></i>
                              42,9%
                            </small>
                          </div>
                        </div>
                      </div>
                      <div id="incomeChart"></div>
                      <div class="d-flex justify-content-center pt-4 gap-2">
                        <div class="flex-shrink-0">
                          <div id="expensesOfWeek"></div>
                        </div>
                        <div>
                          <p class="mb-n1 mt-1">Despesas desta semana</p>
                          <small class="text-muted">$39 a menos que na semana passada</small>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <!--/ Expense Overview -->

            <!-- Transactions -->
            <div class="col-md-6 col-lg-4 order-2 mb-4">
              <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                  <h5 class="card-title m-0 me-2">Transações</h5>
                  <div class="dropdown">
                    <button class="btn p-0" type="button" id="transactionID" data-bs-toggle="dropdown"
                      aria-haspopup="true" aria-expanded="false">
                      <i class="bx bx-dots-vertical-rounded"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="transactionID">
                      <a class="dropdown-item" href="javascript:void(0);">Últimos 28 dias</a>
                      <a class="dropdown-item" href="javascript:void(0);">Último mês</a>
                      <a class="dropdown-item" href="javascript:void(0);">Último ano</a>
                    </div>
                  </div>
                </div>
                <div class="card-body">
                  <ul class="p-0 m-0">
                    <li class="d-flex mb-4 pb-1">
                      <div class="avatar flex-shrink-0 me-3">
                        <img src="../../assets/img/icons/unicons/paypal.png" alt="Usuário" class="rounded" />
                      </div>
                      <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                        <div class="me-2">
                          <small class="text-muted d-block mb-1">Paypal</small>
                          <h6 class="mb-0">Enviar dinheiro</h6>
                        </div>
                        <div class="user-progress d-flex align-items-center gap-1">
                          <h6 class="mb-0">+82.6</h6>
                          <span class="text-muted">USD</span>
                        </div>
                      </div>
                    </li>
                    <li class="d-flex mb-4 pb-1">
                      <div class="avatar flex-shrink-0 me-3">
                        <img src="../../assets/img/icons/unicons/wallet.png" alt="Usuário" class="rounded" />
                      </div>
                      <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                        <div class="me-2">
                          <small class="text-muted d-block mb-1">Carteira</small>
                          <h6 class="mb-0">Mac'D</h6>
                        </div>
                        <div class="user-progress d-flex align-items-center gap-1">
                          <h6 class="mb-0">+270.69</h6>
                          <span class="text-muted">USD</span>
                        </div>
                      </div>
                    </li>
                    <li class="d-flex mb-4 pb-1">
                      <div class="avatar flex-shrink-0 me-3">
                        <img src="../../assets/img/icons/unicons/chart.png" alt="Usuário" class="rounded" />
                      </div>
                      <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                        <div class="me-2">
                          <small class="text-muted d-block mb-1">Transferência</small>
                          <h6 class="mb-0">Reembolso</h6>
                        </div>
                        <div class="user-progress d-flex align-items-center gap-1">
                          <h6 class="mb-0">+637.91</h6>
                          <span class="text-muted">USD</span>
                        </div>
                      </div>
                    </li>
                    <li class="d-flex mb-4 pb-1">
                      <div class="avatar flex-shrink-0 me-3">
                        <img src="../../assets/img/icons/unicons/cc-success.png" alt="Usuário" class="rounded" />
                      </div>
                      <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                        <div class="me-2">
                          <small class="text-muted d-block mb-1">Cartão de Crédito</small>
                          <h6 class="mb-0">Pedido de comida</h6>
                        </div>
                        <div class="user-progress d-flex align-items-center gap-1">
                          <h6 class="mb-0">-838.71</h6>
                          <span class="text-muted">USD</span>
                        </div>
                      </div>
                    </li>
                    <li class="d-flex mb-4 pb-1">
                      <div class="avatar flex-shrink-0 me-3">
                        <img src="../../assets/img/icons/unicons/wallet.png" alt="Usuário" class="rounded" />
                      </div>
                      <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                        <div class="me-2">
                          <small class="text-muted d-block mb-1">Carteira</small>
                          <h6 class="mb-0">Starbucks</h6>
                        </div>
                        <div class="user-progress d-flex align-items-center gap-1">
                          <h6 class="mb-0">+203.33</h6>
                          <span class="text-muted">USD</span>
                        </div>
                      </div>
                    </li>
                    <li class="d-flex">
                      <div class="avatar flex-shrink-0 me-3">
                        <img src="../../assets/img/icons/unicons/cc-warning.png" alt="Usuário" class="rounded" />
                      </div>
                      <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                        <div class="me-2">
                          <small class="text-muted d-block mb-1">Mastercard</small>
                          <h6 class="mb-0">Pedido de comida</h6>
                        </div>
                        <div class="user-progress d-flex align-items-center gap-1">
                          <h6 class="mb-0">-92.45</h6>
                          <span class="text-muted">USD</span>
                        </div>
                      </div>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
            <!--/ Transactions -->
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
              Desenvolvido por <strong>Lucas Correa</strong>.
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
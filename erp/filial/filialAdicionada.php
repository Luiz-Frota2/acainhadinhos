<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
  data-assets-path="../assets/">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>ERP - Filial</title>

  <meta name="description" content="" />

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />

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
          <a href="./dashboard.html" class="app-brand-link">

            <span class="app-brand-text demo menu-text fw-bolder ms-2">Açaídinhos</span>
          </a>

          <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
            <i class="bx bx-chevron-left bx-sm align-middle"></i>
          </a>
        </div>

        <div class="menu-inner-shadow"></div>

        <ul class="menu-inner py-1">
          <!-- Dashboard -->
          <li class="menu-item">
            <a href="index.php" class="menu-link">
              <i class="menu-icon tf-icons bx bx-home-circle"></i>
              <div data-i18n="Analytics">Dashboard</div>
            </a>
          </li>

          <!-- Administração de Filiais -->
          <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Administração Filiais</span>
          </li>

          <!-- Adicionar Filial -->
          <li class="menu-item active open">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-building"></i>
              <div data-i18n="Adicionar">Filiais</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item active">
                <a href="./filialAdicionada.php" class="menu-link">
                  <div data-i18n="Filiais">Adicionadas</div>
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
                <a href="./VendasFiliais.php" class="menu-link">
                  <div data-i18n="Vendas">Vendas por Filial</div>
                </a>
              </li>
              <li class="menu-item">
                <a href="./MaisVendidos.php" class="menu-link">
                  <div data-i18n="MaisVendidos">Mais Vendidos</div>
                </a>
              </li>
              <li class="menu-item">
                <a href="./vendasPeriodo.php" class="menu-link">
                  <div data-i18n="Pedidos">Vendas por Período</div>
                </a>
              </li>

              <li class="menu-item">
                <a href="./FinanceiroFilial.php" class="menu-link">
                  <div data-i18n="Financeiro">Financeiro</div>
                </a>
              </li>
            </ul>
          </li>

          <!--END DELIVERY-->

          <!-- Misc -->
          <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
          <li class="menu-item">
            <a href="../rh/index.php" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-group"></i>
              <div data-i18n="Authentications">RH</div>
            </a>
          </li>
          <li class="menu-item">
            <a href="../financas/index.php" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-dollar"></i>
              <div data-i18n="Authentications">Finanças</div>
            </a>
          </li>
          <li class="menu-item">
            <a href="./pdv/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-desktop"></i>
              <div data-i18n="Authentications">PDV</div>
            </a>
          </li>
          <li class="menu-item">
            <a href="../delivery/index.php" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-cart"></i>
              <div data-i18n="Authentications">Delivery</div>
            </a>
          </li>
          <li class="menu-item">
            <a href="../estoque/index.php" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-box"></i>
              <div data-i18n="Authentications">Estoque</div>
            </a>
          </li>
          <li class="menu-item">
            <a href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-group"></i>
              <div data-i18n="Authentications">Usuários </div>
            </a>
          </li>
          <li class="menu-item">
            <a href="../clientes/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-group"></i>
              <div data-i18n="Authentications">usuarios </div>
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
                          <span class="fw-semibold d-block">John Doe</span>
                          <small class="text-muted">Admin</small>
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
                      <span class="align-middle">My Profile</span>
                    </a>
                  </li>
                  <li>
                    <a class="dropdown-item" href="#">
                      <i class="bx bx-cog me-2"></i>
                      <span class="align-middle">Settings</span>
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
                    <a class="dropdown-item" href="index.php">
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


        <!-- / Nav bar -->

        <div class="container-xxl flex-grow-1 container-p-y">
          <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a
                href="./filialAdicionada.php">Filial</a>/</span>Filiais Adicionadas</h4>
          <h5 class="fw-bold mt-3 mb-3 custor-font"><span class="text-muted fw-light">Visualize e gerencie as filiais da
              empresa</span></h5>

          <!-- Tabela de Contas da Empresa -->
          <div class="card">

            <h5 class="card-header">Lista de Filiais</h5>
            <div class="table-responsive text-nowrap">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>Nome da Filial</th>
                    <th>CNPJ</th>
                    <th>Telefone</th>
                    <th>Email</th>
                    <th>Responsável</th>
                    <th>Ações</th>
                  </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                  <?php
                  require '../../assets/php/conexao.php';

                  try {
                    $stmt = $pdo->query("SELECT * FROM filiais ORDER BY nome");
                    while ($filial = $stmt->fetch(PDO::FETCH_ASSOC)) {
                  ?>
                      <tr>
                        <td><strong><?= htmlspecialchars($filial['nome']) ?></strong></td>
                        <td><?= htmlspecialchars($filial['cnpj']) ?></td>
                        <td><?= htmlspecialchars($filial['telefone']) ?></td>
                        <td><?= htmlspecialchars($filial['email']) ?></td>
                        <td><?= htmlspecialchars($filial['responsavel']) ?></td>
                        <td>

                          <!-- Botão para visualizar Filial -->
                          <a href="#" data-bs-toggle="modal"
                            data-bs-target="#visualizarFilialModal_<?php echo $filial['id_filial']; ?>"
                            data-id="<?php echo $filial['id_filial']; ?>" class="text-secondary">
                            <i class="tf-icons bx bx-show"></i>
                          </a>

                          <span class="mx-2">|</span>

                          <!-- Editar -->
                          <button class="btn btn-link text-primary p-0" title="Editar"
                            onclick="window.location.href='editarFilial.php?id=<?= $filial['id_filial'] ?>';">
                            <i class="tf-icons bx bx-edit"></i>
                          </button>

                          <span class="mx-2">|</span>

                          <!-- Botão para Excluir Filial -->
                          <a href="#" data-bs-toggle="modal"
                            data-bs-target="#filialModal_<?php echo $filial['id_filial']; ?>"
                            class="delete-filial text-danger" data-id="<?php echo $filial['id_filial']; ?>">
                            <i class="tf-icons bx bx-trash" id="deleteIcon"></i>
                          </a>

                        </td>

                        <!-- Modal Visualizar Filial -->
                        <div class="modal fade" id="visualizarFilialModal_<?= $filial['id_filial'] ?>" tabindex="-1"
                          aria-labelledby="visualizarFilialModalLabel_<?= $filial['id_filial'] ?>" aria-hidden="true">
                          <div class="modal-dialog">
                            <div class="modal-content">

                              <div class="modal-header">
                                <h5 class="modal-title" id="visualizarFilialModalLabel_<?= $filial['id_filial'] ?>">Dados da
                                  Filial</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                  aria-label="Fechar"></button>
                              </div>

                              <div class="modal-body">
                                <p><strong>Nome:</strong> <?= htmlspecialchars($filial['nome']) ?></p>
                                <p><strong>CNPJ:</strong> <?= htmlspecialchars($filial['cnpj']) ?></p>
                                <p><strong>Telefone:</strong> <?= htmlspecialchars($filial['telefone']) ?></p>
                                <p><strong>Email:</strong> <?= htmlspecialchars($filial['email']) ?></p>
                                <p><strong>Responsável:</strong> <?= htmlspecialchars($filial['responsavel']) ?></p>
                                <p><strong>Endereço:</strong> <?= htmlspecialchars($filial['endereco']) ?></p>
                                <p><strong>Data de Abertura:</strong>
                                  <?= date('d/m/Y', strtotime($filial['data_abertura'])) ?></p>
                                <p><strong>Status:</strong> <?= htmlspecialchars($filial['status']) ?></p>
                              </div>

                              <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                              </div>

                            </div>
                          </div>
                        </div>
                        <!-- /Modal Visualizar Filial -->

                        <!-- Modal de Exclusão de Filial -->
                        <div class="modal fade" id="filialModal_<?php echo $filial['id_filial']; ?>" tabindex="-1"
                          aria-labelledby="filialModalLabel" aria-hidden="true">
                          <div class="modal-dialog">
                            <div class="modal-content">

                              <div class="modal-header">
                                <h5 class="modal-title" id="filialModalLabel">Excluir Filial</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                  aria-label="Fechar"></button>
                              </div>

                              <div class="modal-body">
                                <p>Tem certeza de que deseja excluir a filial
                                  "<?php echo htmlspecialchars($filial['nome']); ?>"?</p>
                                <a href="../../assets/php/filial/excluirFilial.php?id=<?php echo $filial['id_filial']; ?>"
                                  class="btn btn-danger">
                                  Sim, excluir
                                </a>
                                <button type="button" class="btn btn-secondary mx-2"
                                  data-bs-dismiss="modal">Cancelar</button>
                              </div>

                            </div>
                          </div>
                        </div>
                        <!-- /Modal de Exclusão de Filial -->

                      </tr>
                  <?php
                    }
                  } catch (PDOException $e) {
                    echo "<tr><td colspan='6'>Erro ao buscar filiais: " . $e->getMessage() . "</td></tr>";
                  }
                  ?>
                </tbody>
              </table>
            </div>

          </div>

          <div id="" class="mt-3 add-category justify-content-center d-flex text-center align-items-center"
            onclick="window.location.href='adicionarFilial.php';" style="cursor: pointer;">
            <i class="tf-icons bx bx-plus me-2"></i>
            <span>Adicionar nova Filial</span>
          </div>
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
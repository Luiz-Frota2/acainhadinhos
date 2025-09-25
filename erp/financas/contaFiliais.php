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
<html
  lang="pt-br"
  class="light-style layout-menu-fixed"
  dir="ltr"
  data-theme="theme-default"
  data-assets-path="../assets/">

<head>
  <meta charset="utf-8" />
  <meta
    name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>ERP - Finanças</title>

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

            <span class="app-brand-text demo menu-text fw-bolder ms-2" style=" text-transform: capitalize;">Açaínhadinhos</span>
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

          <!-- Finanças -->
          <li class="menu-header small text-uppercase"><span class="menu-header-text">Finanças</span></li>
          <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-list-check"></i>
              <div data-i18n="Authentications">Contas</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item"><a href="./contasAdicionadas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Adicionadas</div>
                </a></li>
              <li class="menu-item"><a href="./contasFuturos.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Futuras</div>
                </a></li>
              <li class="menu-item"><a href="./contasPagas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Pagas</div>
                </a></li>
              <li class="menu-item"><a href="./contasPendentes.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Pendentes</div>
                </a></li>
            </ul>
          </li>

          <li class="menu-item">
            <a href="./notaFiscal.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
              <i class="menu-icon tf-icons bx bx-envelope"></i>
              <div data-i18n="Basic">Nota Fiscal Online</div>
            </a>
          </li>

          <li class="menu-item active open">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-briefcase"></i>
              <div data-i18n="Authentications">B2B</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item active"><a href="./contaFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Contas Filiais</div>
                </a></li>
            </ul>
          </li>

          <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-cart"></i>
              <div data-i18n="Authentications">Compras</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item"><a href="./controleFornecedores.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Fornecedores</div>
                </a></li>
              <li class="menu-item"><a href="./gestaoPedidos.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Pedidos</div>
                </a></li>
            </ul>
          </li>
          <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-dollar"></i>
              <div data-i18n="Authentications">Relatórios</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item"><a href="./relatorioDiario.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Diário</div>
                </a></li>
              <li class="menu-item"><a href="./relatorioMensal.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Mensal</div>
                </a></li>
              <li class="menu-item"><a href="./relatorioAnual.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Anual</div>
                </a></li>
              <li class="menu-item"><a href="./fluxoCaixa.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Fluxo de Caixa</div>
                </a></li>
              <li class="menu-item"><a href="./projecoesFinaceira.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Projeções Financeiras</div>
                </a></li>
            </ul>
          </li>

          <!-- Diversos -->
          <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>

          <li class="menu-item">
            <a href="../rh/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
              <i class="menu-icon tf-icons bx bx-group"></i>
              <div data-i18n="Authentications">RH</div>
            </a>
          </li>
          <li class="menu-item">
            <a href="../pdv/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-desktop"></i>
              <div data-i18n="Authentications">PDV</div>
            </a>
          </li>
          <li class="menu-item">
            <a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
              <i class="menu-icon tf-icons bx bx-box"></i>
              <div data-i18n="Authentications">Estoque</div>
            </a>
          </li>

          <li class="menu-item">
            <a href="../clientes/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
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
                <input
                  type="text"
                  class="form-control border-0 shadow-none"
                  placeholder="Search..."
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

        <div class="container-xxl flex-grow-1 container-p-y">
          <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a href="#">B2B</a>/</span> Contas Filiais</h4>
          <h5 class=" mt-3 mb-3 custor-font"><span class="text-muted fw-light">Visualize e registre os pagamentos de contas de filiais</span></h5>

          <!-- Tabela de Pagamentos -->
          <div class="card">
            <h5 class="card-header">Lista de Pagamentos de Filiais</h5>
            <div class="table-responsive text-nowrap">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>Descrição</th>
                    <th>Valor Pago</th>
                    <th>Data da Transação</th>
                    <th>Responsável</th>
                    <th>Status</th>
                    <th>Ações</th>
                  </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                  <tr>
                    <td><strong>Pagamento de Fornecedor - Filial A</strong></td>
                    <td>R$ 3.000,00</td>
                    <td>10/04/2025</td>
                    <td>João Silva</td>
                    <td><span class="badge bg-success">Pago</span></td>
                    <td>
                      <button class="btn btn-link text-primary p-0" title="Editar" onclick="openEditModal();">
                        <i class="tf-icons bx bx-edit"></i>
                      </button>
                      <span class="mx-2">|</span>
                      <button class="btn btn-link text-danger p-0" title="Excluir" onclick="openDeleteModal();">
                        <i class="tf-icons bx bx-trash"></i>
                      </button>
                    </td>
                  </tr>
                  <tr>
                    <td><strong>Pagamento de Impostos - Filial B</strong></td>
                    <td>R$ 1.500,00</td>
                    <td>08/04/2025</td>
                    <td>Maria Souza</td>
                    <td><span class="badge bg-warning">Pendente</span></td>
                    <td>
                      <button class="btn btn-link text-primary p-0" title="Editar" onclick="openEditModal();">
                        <i class="tf-icons bx bx-edit"></i>
                      </button>
                      <span class="mx-2">|</span>
                      <button class="btn btn-link text-danger p-0" title="Excluir" onclick="openDeleteModal();">
                        <i class="tf-icons bx bx-trash"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Modal de Edição de Pagamento -->
          <div class="modal fade" id="editPagamentoModal" tabindex="-1" aria-labelledby="editPagamentoModalLabel" aria-hidden="true">
            <div class="modal-dialog">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title" id="editPagamentoModalLabel">Editar Pagamento de Filial</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <form id="editPagamentoForm">
                    <div class="mb-3">
                      <label for="editDescricao" class="form-label">Descrição</label>
                      <input type="text" class="form-control" id="editDescricao" value="Pagamento de Fornecedor - Filial A" disabled />
                    </div>
                    <div class="mb-3">
                      <label for="editValor" class="form-label">Valor Pago</label>
                      <input type="number" class="form-control" id="editValor" value="3000" />
                    </div>
                    <div class="mb-3">
                      <label for="editData" class="form-label">Data da Transação</label>
                      <input type="date" class="form-control" id="editData" value="2025-04-10" />
                    </div>
                    <div class="mb-3">
                      <label for="editResponsavel" class="form-label">Responsável</label>
                      <input type="text" class="form-control" id="editResponsavel" value="João Silva" />
                    </div>
                    <div class="mb-3">
                      <label for="editStatus" class="form-label">Status</label>
                      <select class="form-select" id="editStatus">
                        <option value="pago" selected>Pago</option>
                        <option value="pendente">Pendente</option>
                        <option value="cancelado">Cancelado</option>
                      </select>
                    </div>
                    <div class="d-flex justify-content-between">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                      <button type="submit" class="btn btn-primary">Salvar</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>

          <!-- Modal de Exclusão de Pagamento -->
          <div class="modal fade" id="deletePagamentoModal" tabindex="-1" aria-labelledby="deletePagamentoModalLabel" aria-hidden="true">
            <div class="modal-dialog">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title" id="deletePagamentoModalLabel">Excluir Pagamento</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <p>Tem certeza de que deseja excluir este pagamento de filial?</p>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                  <button type="button" class="btn btn-danger">Excluir</button>
                </div>
              </div>
            </div>
          </div>
          <div id="" class="mt-3 add-category justify-content-center d-flex text-center align-items-center" onclick="window.location.href='adicionarContaFilial.php';" style="cursor: pointer;">
            <i class="tf-icons bx bx-plus me-2"></i>
            <span>Adicionar nova Conta</span>
          </div>
        </div>
      </div>
    </div>
  </div>

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

  <!-- Modal & Form Handling -->
  <script>
    function openEditModal() {
      new bootstrap.Modal(document.getElementById('editPagamentoModal')).show();
    }

    function openDeleteModal() {
      new bootstrap.Modal(document.getElementById('deletePagamentoModal')).show();
    }
  </script>
</body>

</html>
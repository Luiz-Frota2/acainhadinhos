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
          <li class="menu-item ">
            <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
              <i class="menu-icon tf-icons bx bx-home-circle"></i>
              <div data-i18n="Analytics">Dashboard</div>
            </a>
          </li>

          <!-- Finanças -->
          <li class="menu-header small text-uppercase"><span class="menu-header-text">Finanças</span></li>
          <li class="menu-item ">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-list-check"></i>
              <div data-i18n="Authentications">Contas</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item "><a href="./contasAdicionadas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Adicionadas</div>
                </a></li>
              <li class="menu-item "><a href="./contasFuturos.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
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

          <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-briefcase"></i>
              <div data-i18n="Authentications">B2B</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item"><a href="./contaFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Contas Filiais</div>
                </a></li>
            </ul>
          </li>

          <li class="menu-item active open">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-cart"></i>
              <div data-i18n="Authentications">Compras</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item "><a href="./controleFornecedores.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Fornecedores</div>
                </a></li>
              <li class="menu-item active"><a href="./editarFornecedores.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Editar Fornecedor</div>
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
            <a href="../delivery/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
              <i class="menu-icon tf-icons bx bx-cart"></i>
              <div data-i18n="Authentications">Delivery</div>
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
          <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a href="#">Compras</a>/</span>Editar fornecedor</h4>
          <h5 class=" mt-3 mb-3 custor-font"><span class="text-muted fw-light">Preencha os detalhes do novo fornecedor</span></h5>

          <!-- Formulário de Adicionar Conta -->
          <div class="card">
            <h5 class="card-header">Cadastrar novo Fornecedor</h5>
            <div class="card-body">
              <form id="addFornecedorForm" action="../../assets/php/fornecedores/adicionarFornecedor.php" method="POST">

                <div class="mb-3">
                  <label for="nome_fornecedor" class="form-label">Nome do Fornecedor</label>
                  <input type="text" class="form-control" id="nome_fornecedor" name="nome_fornecedor" placeholder="Ex: Distribuidora XPTO" required />
                </div>

                <div class="mb-3">
                  <label for="cnpj_fornecedor" class="form-label">CNPJ</label>
                  <input type="text" class="form-control" id="cnpj_fornecedor" name="cnpj_fornecedor" placeholder="00.000.000/0001-00" required />
                </div>

                <div class="mb-3">
                  <label for="email_fornecedor" class="form-label">E-mail</label>
                  <input type="email" class="form-control" id="email_fornecedor" name="email_fornecedor" placeholder="contato@fornecedor.com" required />
                </div>

                <div class="mb-3">
                  <label for="telefone_fornecedor" class="form-label">Telefone</label>
                  <input type="text" class="form-control" id="telefone_fornecedor" name="telefone_fornecedor" placeholder="(00) 0000-0000" required />
                </div>

                <div class="mb-3">
                  <label for="endereco_fornecedor" class="form-label">Endereço</label>
                  <input type="text" class="form-control" id="endereco_fornecedor" name="endereco_fornecedor" placeholder="Rua Exemplo, 123 - Bairro" required />
                </div>

                <div class="d-flex custom-button">
                  <button type="submit" class="btn btn-primary col-12 w-100 col-md-auto">Salvar Fornecedor</button>
                </div>

              </form>
            </div>
          </div>

        </div>
        <!-- / Content -->



        <!-- build:js assets/vendor/js/core.js -->

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

        <script>
          function openEditModal() {
            new bootstrap.Modal(document.getElementById('editContaModal')).show();
          }

          function openDeleteModal() {
            new bootstrap.Modal(document.getElementById('deleteContaModal')).show();
          }
        </script>
</body>

</html>
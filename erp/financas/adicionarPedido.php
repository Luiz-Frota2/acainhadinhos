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

// ✅ Buscar fornecedores da empresa
$fornecedores = [];
try {
  $stmt = $pdo->prepare("SELECT * FROM fornecedores WHERE empresa_id = :empresa_id ORDER BY nome_fornecedor");
  $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
  $stmt->execute();
  $fornecedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  // Pode adicionar tratamento de erro se necessário
}

?>

<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
  data-assets-path="../assets/">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>ERP - Finanças</title>

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
              <li class="menu-item "><a href="./contasAdicionadas.php?id=<?= urlencode($idSelecionado); ?>"
                  class="menu-link">
                  <div>Adicionadas</div>
                </a></li>
              <li class="menu-item "><a href="./contasFuturos.php?id=<?= urlencode($idSelecionado); ?>"
                  class="menu-link">
                  <div>Futuras</div>
                </a></li>
              <li class="menu-item"><a href="./contasPagas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Pagas</div>
                </a></li>
              <li class="menu-item"><a href="./contasPendentes.php?id=<?= urlencode($idSelecionado); ?>"
                  class="menu-link">
                  <div>Pendentes</div>
                </a></li>
            </ul>
          </li>


          <li class="menu-item active open">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-cart"></i>
              <div data-i18n="Authentications">Compras</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item"><a href="./controleFornecedores.php?id=<?= urlencode($idSelecionado); ?>"
                  class="menu-link">
                  <div>Fornecedores</div>
                </a></li>
              <li class="menu-item active"><a href="./gestaoPedidos.php?id=<?= urlencode($idSelecionado); ?>"
                  class="menu-link">
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
              <li class="menu-item"><a href="./relatorioDiario.php?id=<?= urlencode($idSelecionado); ?>"
                  class="menu-link">
                  <div>Diário</div>
                </a></li>
              <li class="menu-item"><a href="./relatorioMensal.php?id=<?= urlencode($idSelecionado); ?>"
                  class="menu-link">
                  <div>Mensal</div>
                </a></li>
              <li class="menu-item"><a href="./relatorioAnual.php?id=<?= urlencode($idSelecionado); ?>"
                  class="menu-link">
                  <div>Anual</div>
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
            <a href="../empresa/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-briefcase"></i>
              <div data-i18n="Authentications">Empresa</div>
            </a>
          </li>

          <li class="menu-item">
            <a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
              <i class="menu-icon tf-icons bx bx-box"></i>
              <div data-i18n="Authentications">Estoque</div>
            </a>
          </li>
          <?php
          $tipoLogado = $_SESSION['tipo_empresa'] ?? '';
          $idLogado = $_SESSION['empresa_id'] ?? '';

          // Se for matriz (principal), mostrar links para filial, franquia e unidade
          if ($tipoLogado === 'principal') {
          ?>
            <li class="menu-item">
              <a href="../filial/index.php?id=principal_1" class="menu-link">
                <i class="menu-icon tf-icons bx bx-building"></i>
                <div data-i18n="Authentications">Filial</div>
              </a>
            </li>
            <li class="menu-item">
              <a href="../franquia/index.php?id=principal_1" class="menu-link">
                <i class="menu-icon tf-icons bx bx-store"></i>
                <div data-i18n="Authentications">Franquias</div>
              </a>
            </li>
          <?php
          } elseif (in_array($tipoLogado, ['filial', 'franquia', 'unidade'])) {
            // Se for filial, franquia ou unidade, mostra link para matriz
          ?>
            <li class="menu-item">
              <a href="../matriz/index.php?id=<?= urlencode($idLogado) ?>" class="menu-link">
                <i class="menu-icon tf-icons bx bx-cog"></i>
                <div data-i18n="Authentications">Matriz</div>
              </a>
            </li>
          <?php
          }
          ?>
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
          <h4 class="fw-bold mb-0">
            <span class="text-muted fw-light"><a href="#">Compras</a> /</span>Adicionar Pedido
          </h4>
          <h5 class="fw-bold mt-3 mb-3 custor-font">
            <span class="text-muted fw-light">Adicione os pedidos de insumos e embalagens para empresa</span>
          </h5>

          <div class="card mt-4">
            <h5 class="card-header">Adicionar Pedido</h5>
            <div class="card-body">
              <form action="../../assets/php/financas/adicionarPedido.php" method="POST" id="formAdicionarPedido">

                <!-- Campo oculto para ID da empresa -->
                <input type="hidden" name="empresa_id" value="<?= htmlspecialchars($idSelecionado) ?>" />

                <div class="row">

                  <div class="mb-3 col-12 col-md-6">
                    <label for="fornecedor" class="form-label">Fornecedor</label>
                    <select class="form-select" id="fornecedor" name="fornecedor" required>
                      <option value="">Selecione o fornecedor</option>
                      <?php foreach ($fornecedores as $fornecedor): ?>
                        <option value="<?= htmlspecialchars($fornecedor['nome_fornecedor']) ?>">
                          <?= htmlspecialchars($fornecedor['nome_fornecedor']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="mb-3 col-12 col-md-6">
                    <label for="produto" class="form-label">Produto</label>
                    <input type="text" class="form-control" id="produto" name="produto"
                      placeholder="Ex: Polpa de Açaí 10kg" required>
                  </div>

                  <div class="mb-3 col-12 col-md-6">
                    <label for="quantidade" class="form-label">Quantidade</label>
                    <input type="number" class="form-control" id="quantidade" name="quantidade" placeholder="Ex: 5"
                      min="1" required>
                  </div>

                  <div class="mb-3 col-12 col-md-6">
                    <label for="valor" class="form-label">Valor Total (R$)</label>
                    <input type="text" class="form-control" id="valor" name="valor" placeholder="Ex: 750.00" required>
                  </div>

                  <div class="mb-3 col-12 col-md-6">
                    <label for="dataPedido" class="form-label">Data do Pedido</label>
                    <input type="date" class="form-control" id="dataPedido" name="dataPedido" required>
                  </div>

                  <div class="mb-3 col-12 col-md-6">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status" required>
                      <option value="">Selecione o status</option>
                      <option value="Entregue">Entregue</option>
                      <option value="Aguardando entrega">Aguardando entrega</option>
                      <option value="Cancelado">Cancelado</option>
                    </select>
                  </div>
                </div>

                <div class="d-flex custom-button">
                  <button type="submit" class="btn btn-primary w-100">Salvar Pedido</button>
                </div>
              </form>
            </div>
          </div>
        </div>
        <!-- / Layout wrapper -->


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
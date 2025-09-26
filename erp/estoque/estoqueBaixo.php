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
$usuario_id = $_SESSION['usuario_id'];

try {
  $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
  $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
  $stmt->execute();
  $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($usuario) {
    $nomeUsuario = $usuario['usuario'];
    $tipoUsuario = ucfirst($usuario['nivel']);
  } else {
    echo "<script>alert('Usuário não encontrado.'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
    exit;
  }
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar usuário: " . $e->getMessage() . "'); history.back();</script>";
  exit;
}

// ✅ Valida o tipo de empresa e o acesso permitido
$acessoPermitido = false;
$idEmpresaSession = $_SESSION['empresa_id'];
$tipoSession = $_SESSION['tipo_empresa'];

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

// ✅ Consultar os produtos com o status "estoque_alto"
try {
  $sql = "SELECT * FROM estoque WHERE empresa_id = :empresa_id AND quantidade_produto < 50";
  $stmt = $pdo->prepare($sql);
  $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR); // Usa o idSelecionado
  $stmt->execute();
  $estoque = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  echo "Erro ao buscar produtos: " . $e->getMessage();
  exit;
}

?>

<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
  data-assets-path="../assets/">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>ERP - Estoque</title>

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

          <!-- Administração de Filiais -->
          <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Estoque</span>
          </li>

          <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-cart"></i>
              <div data-i18n="Authentications">Fornecedores</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item"><a href="./fornecedoresAdicionados.php?id=<?= urlencode($idSelecionado); ?>"
                  class="menu-link">
                  <div>Adicionados</div>
                </a>
              </li>
            </ul>
          </li>

          <!-- Estoque -->
          <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-package"></i>
              <div data-i18n="Estoque">Produtos</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item">
                <a href="./produtosAdicionados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Produtos">Adicionados</div>
                </a>
              </li>
            </ul>
          </li>

          <!-- Relatórios -->
          <li class="menu-item open active">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-bar-chart-alt-2"></i>
              <div data-i18n="Relatorios">Relatórios</div>
            </a>
            <ul class="menu-sub active">
              <li class="menu-item">
                <a href="./estoqueAlto.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="BaixoEstoque">Estoque Alto</div>
                </a>
              </li>
              <li class="menu-item active">
                <a href="./estoqueBaixo.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="BaixoEstoque">Estoque Baixo</div>
                </a>
              </li>
            </ul>
          </li>
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
          <h4 class="fw-bold py-3 mb-4"><span class="fw-light" style="color: #696cff !important;">Estoque</span>/Produtos
            com Estoque Baixo</h4>
          <div class="card">
            <h5 class="card-header">Lista de Produtos com Estoque Baixo</h5>
            <div class="table-responsive text-nowrap">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>Produto</th>
                    <th>Categoria</th>
                    <th>Quantidade</th>
                    <th>Preço Unitário</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <?php foreach ($estoque as $estoques): ?>
                  <tbody class="table-border-bottom-0">
                    <tr>
                      <input type="hidden" value="<?= htmlspecialchars($estoques['id']) ?>">
                      <td><?= htmlspecialchars($estoques['nome_produto']) ?></td>
                      <td><?= htmlspecialchars($estoques['categoria_produto']) ?></td>
                      <td><?= htmlspecialchars($estoques['quantidade_produto']) ?></td>
                      <td><?= htmlspecialchars($estoques['preco_produto']) ?></td>
                      <td>
                        <?php if ($estoques['quantidade_produto'] > 50): ?>
                          <span class="badge bg-success me-1">Estoque Alto</span>
                        <?php elseif ($estoques['quantidade_produto'] < 50): ?>
                          <span class="badge bg-danger">Estoque Baixo</span>
                        <?php else: ?>
                          <span class="badge bg-warning">Não definido</span>
                        <?php endif; ?>
                      </td>
                      <td>

                        <a href="#" data-bs-toggle="modal" data-bs-target="#editProdutoModal_<?= $estoques['id'] ?>"
                          data-empresa-id="<?= $idSelecionado ?>" data-produto-id="<?= $estoques['id'] ?>">
                          <i class="tf-icons bx bx-edit"></i>
                        </a>

                        <span class="mx-2">|</span>

                        <button class="btn btn-link text-danger p-0" title="Excluir" data-bs-toggle="modal"
                          data-bs-target="#deleteEstoqueModal_<?php echo $estoques['id']; ?>">
                          <i class="tf-icons bx bx-trash"></i>
                        </button>

                        <!-- Modal de Excluir Estoque -->
                        <div class="modal fade" id="deleteEstoqueModal_<?php echo $estoques['id']; ?>" tabindex="-1"
                          aria-labelledby="deleteEstoqueModalLabel_<?php echo $estoques['id']; ?>" aria-hidden="true">
                          <div class="modal-dialog">
                            <div class="modal-content">
                              <div class="modal-header">
                                <h5 class="modal-title" id="deleteEstoqueModalLabel_<?php echo $estoques['id']; ?>">
                                  Excluir Produto</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                  aria-label="Fechar"></button>
                              </div>
                              <div class="modal-body">
                                <p>Tem certeza de que deseja excluir o produto
                                  "<?php echo htmlspecialchars($estoques['nome_produto']); ?>"?</p>
                                <a href="../../assets/php/estoque/excluirEstoqueBaixo.php?id=<?php echo $estoques['id']; ?>&empresa_id=<?php echo urlencode($idSelecionado); ?>"
                                  class="btn btn-danger">Sim, excluir</a>
                                <button type="button" class="btn btn-secondary mx-2"
                                  data-bs-dismiss="modal">Cancelar</button>
                              </div>
                            </div>
                          </div>
                        </div>

                        <!-- Modal de Editar Produto -->
                        <div class="modal fade" id="editProdutoModal_<?= $estoques['id'] ?>" tabindex="-1"
                          aria-labelledby="editProdutoModalLabel_<?= $estoques['id'] ?>" aria-hidden="true">
                          <div class="modal-dialog">
                            <div class="modal-content">

                              <div class="modal-header">
                                <h5 class="modal-title" id="editProdutoModalLabel_<?= $estoques['id'] ?>">Editar Produto
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                  aria-label="Fechar"></button>
                              </div>

                              <div class="modal-body">
                                <form action="../../assets/php/estoque/editarEstoqueBaixo.php" method="POST">
                                  <!-- ID do produto -->
                                  <input type="hidden" name="id" value="<?= htmlspecialchars($estoques['id']) ?>">

                                  <!-- ID da empresa (idSelecionado) -->
                                  <input type="hidden" name="empresa_id" value="<?= $idSelecionado ?>">

                                  <div class="mb-3">
                                    <label for="nome_produto" class="form-label">Nome Produto</label>
                                    <input type="text" class="form-control" id="nome_produto" name="nome_produto"
                                      value="<?= htmlspecialchars($estoques['nome_produto']) ?>" required>
                                  </div>

                                  <div class="mb-3">
                                    <label for="codigo_produto" class="form-label">Código Produto</label>
                                    <input type="text" class="form-control" id="codigo_produto" name="codigo_produto"
                                      value="<?= htmlspecialchars($estoques['codigo_produto']) ?>" required>
                                  </div>

                                  <div class="mb-3">
                                    <label for="categoria_produto" class="form-label">Categoria</label>
                                    <input type="text" class="form-control" id="categoria_produto"
                                      name="categoria_produto"
                                      value="<?= htmlspecialchars($estoques['categoria_produto']) ?>" required>
                                  </div>

                                  <div class="mb-3">
                                    <label for="quantidade_produto" class="form-label">Quantidade</label>
                                    <input type="text" class="form-control" id="quantidade_produto"
                                      name="quantidade_produto"
                                      value="<?= htmlspecialchars($estoques['quantidade_produto']) ?>" required>
                                  </div>

                                  <div class="mb-3">
                                    <label for="preco_produto" class="form-label">Preço Unitário</label>
                                    <input type="text" class="form-control" id="preco_produto" name="preco_produto"
                                      value="<?= htmlspecialchars($estoques['preco_produto']) ?>" required>
                                  </div>

                                  <div class="mb-3">
                                    <label for="status_produto" class="form-label">Status Produto</label>
                                    <select class="form-select" id="status_produto" name="status_produto" required>
                                      <option value="estoque_alto" <?= $estoques['status_produto'] === 'estoque_alto' ? 'selected' : '' ?>>Estoque Alto</option>
                                      <option value="estoque_baixo" <?= $estoques['status_produto'] === 'estoque_baixo' ? 'selected' : '' ?>>Estoque Baixo</option>
                                    </select>
                                  </div>

                                  <div class="d-flex justify-content-between">
                                    <button type="button" class="btn btn-secondary"
                                      data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-primary">Salvar</button>
                                  </div>
                                </form>
                              </div>

                            </div>
                          </div>
                        </div>
                  </tbody>
                <?php endforeach; ?>
              </table>
            </div>
          </div>

          <div id="" class="mt-3 add-category justify-content-center d-flex text-center align-items-center"
            onclick="window.location.href='adicionarEstoque.php?id=<?= urlencode($idSelecionado); ?>';"
            style="cursor: pointer;">
            <i class="tf-icons bx bx-plus me-2"></i>
            <span>Adicionar novo Produto</span>
          </div>
        </div>

        <!-- / Content -->


      </div>
      <!-- Content wrapper -->
    </div>
    <!-- / Layout page -->

  </div>

  <!-- Overlay -->

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
  <script>
    function openEditModal() {
      new bootstrap.Modal(document.getElementById('editContaModal')).show();
    }

    function openDeleteModal() {
      new bootstrap.Modal(document.getElementById('deleteContaModal')).show();
    }
  </script>

  <!-- Place this tag in your head or just before your close body tag. -->
  <script async defer src="https://buttons.github.io/buttons.js"></script>
</body>

</html>
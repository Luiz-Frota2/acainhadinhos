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

// ✅ Buscar logo da empresa
try {
  $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1");
  $stmt->bindParam(':id_selecionado', $idSelecionado, PDO::PARAM_STR);
  $stmt->execute();
  $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);

  $logoEmpresa = !empty($empresaSobre['imagem'])
    ? "../../assets/img/empresa/" . $empresaSobre['imagem']
    : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
  $logoEmpresa = "../../assets/img/favicon/logo.png"; // fallback
}

try {
  // Consulta para filtrar os produtos pela empresa selecionada
  $sql = "SELECT * FROM estoque WHERE empresa_id = :empresa_id";
  $stmt = $pdo->prepare($sql);
  $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
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
            <span class="menu-header-text">PDV</span>
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
                <a href="./sefazStatus.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Status</div>
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
          <li class="menu-item active open">

            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-box"></i>
              <div data-i18n="Basic">Estoque</div>
            </a>

            <ul class="menu-sub">
              <!-- Produtos Adicionados: Cadastro ou listagem de produtos adicionados -->
              <li class="menu-item active">
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
          <li class="menu-item">
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
              <li class="menu-item">
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


        <div class="container-xxl flex-grow-1 container-p-y">
          <h4 class="fw-bold py-3 mb-4"><span class="fw-light" style="color: #696cff !important;">PDV</span>/Produtos Adicionados</h4>

          <div class="card">
            <h5 class="card-header">Lista de Produtos Adicionados</h5>
            <div class="table-responsive text-nowrap">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>Código</th>
                    <th>Produto</th>
                    <th>Categoria</th>
                    <th>Quantidade</th>
                    <th>Preço Unitário</th>
                    <th>Status</th>
                    <th>Ações</th>
                  </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                  <?php foreach ($estoque as $estoques): ?>
                    <tr>
                      <td><?= htmlspecialchars($estoques['codigo_produto']) ?></td>
                      <td><?= htmlspecialchars($estoques['nome_produto']) ?></td>
                      <td><?= htmlspecialchars($estoques['categoria_produto']) ?></td>
                      <td><?= htmlspecialchars($estoques['quantidade_produto']) ?></td>
                      <td>R$ <?= number_format($estoques['preco_produto'], 2, ',', '.') ?></td>
                      <td>
                        <?php if ($estoques['status_produto'] == 'estoque_alto'): ?>
                          <span class="badge bg-success me-1">Estoque Alto</span>
                        <?php elseif ($estoques['status_produto'] == 'estoque_baixo'): ?>
                          <span class="badge bg-danger">Estoque Baixo</span>
                        <?php elseif ($estoques['status_produto'] == 'ativo'): ?>
                          <span class="badge bg-primary">Ativo</span>
                        <?php elseif ($estoques['status_produto'] == 'inativo'): ?>
                          <span class="badge bg-secondary">Inativo</span>
                        <?php else: ?>
                          <span class="badge bg-warning">Não definido</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div class="d-flex">
                          <!-- Botão Editar -->
                          <a href="#" class="text-primary me-2" data-bs-toggle="modal"
                            data-bs-target="#editProdutoModal_<?= $estoques['id'] ?>"
                            title="Editar">
                            <i class="tf-icons bx bx-edit"></i>
                          </a>

                          <!-- Botão Excluir -->
                          <a href="#" class="text-danger" data-bs-toggle="modal"
                            data-bs-target="#deleteEstoqueModal_<?= $estoques['id'] ?>"
                            title="Excluir">
                            <i class="tf-icons bx bx-trash"></i>
                          </a>
                        </div>

                        <!-- Modal de Excluir Estoque -->
                        <div class="modal fade" id="deleteEstoqueModal_<?= $estoques['id'] ?>" tabindex="-1"
                          aria-hidden="true">
                          <div class="modal-dialog">
                            <div class="modal-content">
                              <div class="modal-header">
                                <h5 class="modal-title">Excluir Produto</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                              </div>
                              <div class="modal-body">
                                <p>Tem certeza de que deseja excluir o produto "<?= htmlspecialchars($estoques['nome_produto']) ?>"?</p>
                              </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <a href="../../assets/php/pdv/excluirEstoque.php?id=<?= $estoques['id'] ?>&empresa_id=<?= urlencode($idSelecionado) ?>"
                                  class="btn btn-danger">Excluir</a>
                              </div>
                            </div>
                          </div>
                        </div>

                        <!-- Modal de Editar Produto -->
                        <div class="modal fade" id="editProdutoModal_<?= $estoques['id'] ?>" tabindex="-1"
                          aria-hidden="true">
                          <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                              <div class="modal-header">
                                <h5 class="modal-title">Editar Produto</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                              </div>
                              <div class="modal-body">
                                <form action="../../assets/php/pdv/editarEstoque.php" method="POST">
                                  <input type="hidden" name="id" value="<?= htmlspecialchars($estoques['id']) ?>">
                                  <input type="text" name="empresa_id" value="<?= $idSelecionado ?>">

                                  <div class="row">
                                    <div class="col-md-6 mb-3">
                                      <label class="form-label">Código do Produto (GTIN/EAN)</label>
                                      <input type="text" class="form-control" name="codigo_produto"
                                        value="<?= htmlspecialchars($estoques['codigo_produto']) ?>" required>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                      <label class="form-label">Nome do Produto*</label>
                                      <input type="text" class="form-control" name="nome_produto"
                                        value="<?= htmlspecialchars($estoques['nome_produto']) ?>" required>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                      <label class="form-label">NCM*</label>
                                      <input type="text" class="form-control" name="ncm"
                                        value="<?= htmlspecialchars($estoques['ncm']) ?>" required>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                      <label class="form-label">CFOP*</label>
                                      <input type="text" class="form-control" name="cfop"
                                        value="<?= htmlspecialchars($estoques['cfop']) ?>" required>
                                    </div>

                                    <div class="col-md-4 mb-3">
                                      <label class="form-label">Categoria*</label>
                                      <input type="text" class="form-control" name="categoria_produto"
                                        value="<?= htmlspecialchars($estoques['categoria_produto']) ?>" required>
                                    </div>

                                    <div class="col-md-4 mb-3">
                                      <label class="form-label">Quantidade*</label>
                                      <input type="number" step="0.01" class="form-control" name="quantidade_produto"
                                        value="<?= htmlspecialchars($estoques['quantidade_produto']) ?>" required>
                                    </div>

                                    <div class="col-md-4 mb-3">
                                      <label class="form-label">Unidade*</label>
                                      <select class="form-select" name="unidade" required>
                                        <option value="UN" <?= $estoques['unidade'] === 'UN' ? 'selected' : '' ?>>UN - Unidade</option>
                                        <option value="KG" <?= $estoques['unidade'] === 'KG' ? 'selected' : '' ?>>KG - Quilograma</option>
                                        <option value="LT" <?= $estoques['unidade'] === 'LT' ? 'selected' : '' ?>>LT - Litro</option>
                                        <option value="CX" <?= $estoques['unidade'] === 'CX' ? 'selected' : '' ?>>CX - Caixa</option>
                                      </select>
                                    </div>

                                    <div class="col-md-4 mb-3">
                                      <label class="form-label">Preço Unitário (R$)*</label>
                                      <input type="text" class="form-control money" name="preco_produto"
                                        value="<?= number_format($estoques['preco_produto'], 2, ',', '.') ?>" required>
                                    </div>

                                    <div class="col-md-4 mb-3">
                                      <label class="form-label">Preço de Custo (R$)</label>
                                      <input type="text" class="form-control money" name="preco_custo"
                                        value="<?= isset($estoques['preco_custo']) ? number_format($estoques['preco_custo'], 2, ',', '.') : '' ?>">
                                    </div>

                                    <div class="col-md-4 mb-3">
                                      <label class="form-label">Status*</label>
                                      <select class="form-select" name="status_produto" required>
                                        <option value="ativo" <?= $estoques['status_produto'] === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                                        <option value="inativo" <?= $estoques['status_produto'] === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                                        <option value="estoque_alto" <?= $estoques['status_produto'] === 'estoque_alto' ? 'selected' : '' ?>>Estoque Alto</option>
                                        <option value="estoque_baixo" <?= $estoques['status_produto'] === 'estoque_baixo' ? 'selected' : '' ?>>Estoque Baixo</option>
                                      </select>
                                    </div>

                                    <div class="col-12 mb-3">
                                      <label class="form-label">Informações Adicionais (NFC-e)</label>
                                      <textarea class="form-control" name="informacoes_adicionais" rows="2"><?=
                                                                                                            htmlspecialchars($estoques['informacoes_adicionais'] ?? '') ?></textarea>
                                    </div>
                                  </div>

                                  <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                                  </div>
                                </form>
                              </div>
                            </div>
                          </div>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="mt-3 add-category justify-content-center d-flex text-center align-items-center"
            onclick="window.location.href='adicionarEstoque.php?id=<?= urlencode($idSelecionado) ?>';"
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


  <!-- Place this tag in your head or just before your close body tag. -->
  <script async defer src="https://buttons.github.io/buttons.js"></script>
</body>

</html>
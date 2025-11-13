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

?>

<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
  data-assets-path="../assets/">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>ERP - Franquias</title>

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
          <li class="menu-item">
            <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
              <i class="menu-icon tf-icons bx bx-home-circle"></i>
              <div data-i18n="Analytics">Dashboard</div>
            </a>
          </li>

          <!-- Administração de Filiais -->
          <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Administração Franquias</span>
          </li>

          <!-- Adicionar Filial -->
          <li class="menu-item active open">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-building"></i>
              <div data-i18n="Adicionar">Franquias</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item active">
                <a href="./franquiaAdicionada.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
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
                <a href="./contasFranquia.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
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
                <a href="./VendasFranquias.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Vendas">Vendas por Franquias</div>
                </a>
              </li>

              <li class="menu-item">
                <a href="./MaisVendidos.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="MaisVendidos">Mais Vendidos</div>
                </a>
              </li>

              <li class="menu-item">
                <a href="./FinanceiroFranquia.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Financeiro">Financeiro</div>
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
            <a href="../filial/index.php?id=principal_1" class="menu-link">
              <i class="menu-icon tf-icons bx bx-building"></i>
              <div data-i18n="Authentications">Filial</div>
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
                <input type="text" id="searchInput" class="form-control border-0 shadow-none" placeholder="Pesquisar..."
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
        <!-- / Nav bar -->

        <div class="container-xxl flex-grow-1 container-p-y">
          <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a
                href="#">Franquias</a>/</span>Franquias Adicionadas</h4>
          <h5 class="fw-bold mt-3 mb-3 custor-font"><span class="text-muted fw-light">Visualize e gerencie as filiais da
              empresa</span></h5>

          <!-- Tabela de Contas da Empresa -->
          <div class="card">

            <h5 class="card-header">Lista de Franquias</h5>
            <div class="table-responsive text-nowrap">
              <table class="table table-hover" id="tabelaFranquiaAdicionada">
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
                    $stmt = $pdo->prepare("SELECT * FROM unidades WHERE tipo = 'Franquia' ORDER BY nome");
                    $stmt->execute();
                    while ($franquia = $stmt->fetch(PDO::FETCH_ASSOC)) {
                  ?>
                      <tr>
                        <td><strong><?= htmlspecialchars($franquia['nome']) ?></strong></td>
                        <td><?= htmlspecialchars($franquia['cnpj']) ?></td>
                        <td><?= htmlspecialchars($franquia['telefone']) ?></td>
                        <td><?= htmlspecialchars($franquia['email']) ?></td>
                        <td><?= htmlspecialchars($franquia['responsavel']) ?></td>
                        <td>
                          <!-- Botão para visualizar -->
                          <a href="#" data-bs-toggle="modal"
                            data-bs-target="#visualizarFranquiaModal_<?= $franquia['id'] ?>"
                            class="text-secondary">
                            <i class="tf-icons bx bx-show"></i>
                          </a>

                          <span class="mx-2">|</span>
                          <!-- Editar (abre modal) -->
                          <button class="btn btn-link text-primary p-0" title="Editar"
                            data-bs-toggle="modal"
                            data-bs-target="#editarFilialModal_<?php echo $franquia['id']; ?>">
                            <i class="tf-icons bx bx-edit"></i>
                          </button>


                          <span class="mx-2">|</span>

                          <!-- Botão para excluir -->
                          <a href="#" data-bs-toggle="modal"
                            data-bs-target="#excluirFranquiaModal_<?= $franquia['id'] ?>"
                            class="text-danger">
                            <i class="tf-icons bx bx-trash"></i>
                          </a>
                        </td>
                      </tr>

                      <!-- Modal Visualizar Franquia -->
                      <div class="modal fade" id="visualizarFranquiaModal_<?= $franquia['id'] ?>" tabindex="-1"
                        aria-labelledby="visualizarFranquiaModalLabel_<?= $franquia['id'] ?>" aria-hidden="true">
                        <div class="modal-dialog">
                          <div class="modal-content">

                            <div class="modal-header">
                              <h5 class="modal-title" id="visualizarFranquiaModalLabel_<?= $franquia['id'] ?>">Detalhes da Franquia</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                            </div>

                            <div class="modal-body">
                              <p><strong>Nome:</strong> <?= htmlspecialchars($franquia['nome']) ?></p>
                              <p><strong>CNPJ:</strong> <?= htmlspecialchars($franquia['cnpj']) ?></p>
                              <p><strong>Telefone:</strong> <?= htmlspecialchars($franquia['telefone']) ?></p>
                              <p><strong>Email:</strong> <?= htmlspecialchars($franquia['email']) ?></p>
                              <p><strong>Responsável:</strong> <?= htmlspecialchars($franquia['responsavel']) ?></p>
                              <p><strong>Endereço:</strong> <?= htmlspecialchars($franquia['endereco']) ?></p>
                              <p><strong>Data de Abertura:</strong> <?= date('d/m/Y', strtotime($franquia['data_abertura'])) ?></p>
                              <p><strong>Status:</strong> <?= htmlspecialchars($franquia['status']) ?></p>
                            </div>

                            <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            </div>

                          </div>
                        </div>
                      </div>
                      <!-- /Modal Visualizar Franquia -->
                      <!-- Modal Editar Filial -->
                      <div class="modal fade" id="editarFilialModal_<?php echo $franquia['id']; ?>" tabindex="-1"
                        aria-labelledby="editarFilialLabel_<?php echo $franquia['id']; ?>" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                          <div class="modal-content">
                            <form action="../../assets/php/franquia/editarFranquia.php" method="POST" autocomplete="off">
                              <div class="modal-header">
                                <h5 class="modal-title" id="editarFilialLabel_<?php echo $franquia['id']; ?>">
                                  Editar Franquia — <?php echo htmlspecialchars($franquia['nome']); ?>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                              </div>
                              <div class="modal-body">
                                <input type="hidden" name="id" value="<?php echo (int)$franquia['id']; ?>">
                                <input type="hidden" name="empresa_id" value="<?= htmlspecialchars($idSelecionado); ?>">
                                <input type="hidden" name="return_url" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                                <div class="row g-3">
                                  <div class="col-md-6">
                                    <label class="form-label">Nome</label>
                                    <input type="text" name="nome" class="form-control"
                                      value="<?= htmlspecialchars($franquia['nome']) ?>" required>
                                  </div>
                                  <div class="col-md-3">
                                    <label class="form-label">Tipo</label>
                                    <select name="tipo" class="form-select" disabled>
                                      <option value="Franquia" <?= $franquia['tipo'] === 'Franquia' ? 'selected' : ''; ?>>Franquia</option>
                                      <option value="Filial" <?= $franquia['tipo'] === 'Filial'  ? 'selected' : ''; ?>>Filial</option>
                                    </select>
                                  </div>
                                  <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select" required>
                                      <option value="Ativa" <?= $franquia['status'] === 'Ativa'  ? 'selected' : ''; ?>>Ativa</option>
                                      <option value="Inativa" <?= $franquia['status'] === 'Inativa' ? 'selected' : ''; ?>>Inativa</option>
                                    </select>
                                  </div>
                                  <div class="col-md-4">
                                    <label class="form-label">CNPJ</label>
                                    <input type="text" name="cnpj" class="form-control"
                                      value="<?= htmlspecialchars($franquia['cnpj']) ?>" required>
                                  </div>
                                  <div class="col-md-4">
                                    <label class="form-label">Telefone</label>
                                    <input type="text" name="telefone" class="form-control"
                                      value="<?= htmlspecialchars($franquia['telefone']) ?>" required>
                                  </div>
                                  <div class="col-md-4">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control"
                                      value="<?= htmlspecialchars($franquia['email']) ?>" required>
                                  </div>
                                  <div class="col-md-6">
                                    <label class="form-label">Responsável</label>
                                    <input type="text" name="responsavel" class="form-control"
                                      value="<?= htmlspecialchars($franquia['responsavel']) ?>" required>
                                  </div>
                                  <div class="col-md-6">
                                    <label class="form-label">Data de Abertura</label>
                                    <input type="date" name="data_abertura" class="form-control"
                                      value="<?= htmlspecialchars($franquia['data_abertura']) ?>" required>
                                  </div>
                                  <div class="col-12">
                                    <label class="form-label">Endereço</label>
                                    <textarea name="endereco" class="form-control" rows="2" required><?= htmlspecialchars($franquia['endereco']) ?></textarea>
                                  </div>
                                </div>
                              </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Salvar</button>
                              </div>
                            </form>
                          </div>
                        </div>
                      </div>
                      <!-- /Modal Editar Filial -->

                      <!-- Modal Excluir Franquia -->
                      <div class="modal fade" id="excluirFranquiaModal_<?= $franquia['id'] ?>" tabindex="-1"
                        aria-labelledby="excluirFranquiaModalLabel_<?= $franquia['id'] ?>" aria-hidden="true">
                        <div class="modal-dialog">
                          <div class="modal-content">

                            <div class="modal-header">
                              <h5 class="modal-title">Excluir Franquia</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                            </div>

                            <div class="modal-body">
                              <p>Tem certeza que deseja excluir a franquia <strong><?= htmlspecialchars($franquia['nome']) ?></strong>?</p>
                              <a href="../../assets/php/franquia/excluirFranquia.php?id=<?= $franquia['id'] ?>&idSelecionado=<?= urlencode($idSelecionado) ?>" class="btn btn-danger">
                                Sim, excluir
                              </a>
                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            </div>

                          </div>
                        </div>
                      </div>
                      <!-- /Modal Excluir Franquia -->
                  <?php
                    }
                  } catch (PDOException $e) {
                    echo "<tr><td colspan='6'>Erro ao buscar franquias: " . $e->getMessage() . "</td></tr>";
                  }
                  ?>
                </tbody>

              </table>
            </div>

            <div class="d-flex gap-2 m-3">
              <button id="prevPageHoras" class="btn btn-outline-primary btn-sm">&laquo; Anterior</button>
              <div id="paginacaoHoras" class="d-flex gap-1"></div>
              <button id="nextPageHoras" class="btn btn-outline-primary btn-sm">Próxima &raquo;</button>
            </div>

          </div>

          <div id="" class="mt-3 add-category justify-content-center d-flex text-center align-items-center"
            onclick="window.location.href='adicionarFranquia.php?id=<?= urlencode($idSelecionado); ?>';" style="cursor: pointer;">
            <i class="tf-icons bx bx-plus me-2"></i>
            <span>Adicionar nova Franquia</span>
          </div>

          <script>
            const searchInput = document.getElementById('searchInput');
            const allRows = Array.from(document.querySelectorAll('#tabelaFranquiaAdicionada tbody tr'));
            const prevBtn = document.getElementById('prevPageHoras');
            const nextBtn = document.getElementById('nextPageHoras');
            const pageContainer = document.getElementById('paginacaoHoras');
            const perPage = 10;
            let currentPage = 1;

            function renderTable() {
              const filter = searchInput.value.trim().toLowerCase();
              const filteredRows = allRows.filter(row => {
                if (!filter) return true;
                return Array.from(row.cells).some(td =>
                  td.textContent.toLowerCase().includes(filter)
                );
              });

              const totalPages = Math.ceil(filteredRows.length / perPage) || 1;
              currentPage = Math.min(Math.max(1, currentPage), totalPages);

              // Hide all, then show slice
              allRows.forEach(r => r.style.display = 'none');
              filteredRows.slice((currentPage - 1) * perPage, currentPage * perPage)
                .forEach(r => r.style.display = '');

              // Render page buttons
              pageContainer.innerHTML = '';
              for (let i = 1; i <= totalPages; i++) {
                const btn = document.createElement('button');
                btn.textContent = i;
                btn.className = 'btn btn-sm ' + (i === currentPage ? 'btn-primary' : 'btn-outline-primary');
                btn.style.marginRight = '4px';
                btn.onclick = () => {
                  currentPage = i;
                  renderTable();
                };
                pageContainer.appendChild(btn);
              }

              prevBtn.disabled = currentPage === 1;
              nextBtn.disabled = currentPage === totalPages;
            }

            prevBtn.addEventListener('click', () => {
              if (currentPage > 1) {
                currentPage--;
                renderTable();
              }
            });
            nextBtn.addEventListener('click', () => {
              currentPage++;
              renderTable();
            });
            searchInput.addEventListener('input', () => {
              currentPage = 1;
              renderTable();
            });

            document.addEventListener('DOMContentLoaded', renderTable);
          </script>

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
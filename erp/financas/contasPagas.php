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

?>

<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>ERP - Finanças</title>

  <meta name="description" content="" />

  <!-- Favicon da empresa carregado dinamicamente -->
 <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($logoEmpresa) ?>" />

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />

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
          <a href="./index.php./index.php?id=<?= urlencode($idSelecionado); ?>" class="app-brand-link">

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
          <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Finanças</span>
          </li>

          <li class="menu-item active open">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-list-check"></i>
              <div data-i18n="Authentications">Contas</div>
            </a>

            <ul class="menu-sub">

              <li class="menu-item">
                <a href="./contasAdicionadas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Adicionadas</div>
                </a>
              </li>

              <li class="menu-item">
                <a href="./contasFuturos.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Futuras</div>
                </a>
              </li>

              <li class="menu-item active">
                <a href="./contasPagas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Pagas</div>
                </a>
              </li>
              <li class="menu-item">
                <a href="./contasPendentes.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Pendentes</div>
                </a>
              </li>

            </ul>
          </li>

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
            </ul>

          </li>

          <!-- Diversos -->
          <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Diversos</span>
          </li>

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
        <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">

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
                <input type="text" class="form-control border-0 shadow-none" placeholder="Search..." aria-label="Search..." />
              </div>
            </div>
            <!-- /Search -->

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

          <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a href="#">Contas da Empresa</a>/</span>Visualizar Contas</h4>
          <h5 class="fw-bold mt-3 mb-3 custor-font"><span class="text-muted fw-light">Visualize e gerencie as contas da empresa</span></h5>

          <!-- Tabela de Contas da Empresa -->
          <div class="card">

            <h5 class="card-header">Lista de Contas da Empresa</h5>

            <!-- table-responsive -->
            <div class="table-responsive text-nowrap">
              <?php

              require '../../assets/php/conexao.php';

              try {

                $sql = "SELECT * FROM contas WHERE statuss = 'pago'";
                $stmt = $pdo->query($sql);
                $contas = $stmt->fetchAll(PDO::FETCH_ASSOC);
              } catch (PDOException $e) {
                echo "Erro ao buscar contas: " . $e->getMessage();
                exit;
              }
              ?>


              <!-- table -->
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
                  <?php foreach ($contas as $conta): ?>
                    <tr>
                      <input type="hidden" value="<?= htmlspecialchars($conta['id']) ?>">
                      <td><?= htmlspecialchars($conta['descricao']) ?></td>
                      <td><?= htmlspecialchars($conta['valorpago']) ?></td>
                      <td><?= htmlspecialchars($conta['datatransacao']) ?></td>
                      <td><?= htmlspecialchars($conta['responsavel']) ?></td>
                      <td>
                        <?php if ($conta['statuss'] == 'pago'): ?>
                          <span class="badge bg-success ">Pago</span>
                        <?php elseif ($conta['statuss'] == 'pendente'): ?>
                          <span class="badge bg-danger">Pendente</span>
                        <?php else: ?>
                          <span class="badge bg-warning">Futura</span>
                        <?php endif; ?>
                      </td>

                      <td>

                        <!-- Botão para abrir o modal -->
                        <a href="#" data-bs-toggle="modal" data-bs-target="#editContaModal_<?= $conta['id'] ?>">
                          <i class="tf-icons bx bx-edit"></i>
                        </a>

                        <!-- Espaço entre os ícones -->
                        <span class="mx-2">|</span>

                        <button class="btn btn-link text-danger p-0" title="Excluir" data-bs-toggle="modal" data-bs-target="#modalExcluir_<?= $conta['id'] ?>">
                          <i class="tf-icons bx bx-trash"></i>
                        </button>

                        <!-- Modal de Exclusão de Transação -->
                        <div class="modal fade" id="modalExcluir_<?= $conta['id'] ?>" tabindex="-1" aria-labelledby="modalExcluirLabel_<?= $conta['id'] ?>" aria-hidden="true">
                          <div class="modal-dialog">
                            <div class="modal-content">
                              <div class="modal-header">
                                <h5 class="modal-title" id="modalExcluirLabel_<?= $conta['id'] ?>">Excluir Transação</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                              </div>
                              <div class="modal-body">
                                <p>Tem certeza de que deseja excluir esta transação?</p>
                                <a href="../../assets/php/financas/excluirContasPaga.php?id=<?= $conta['id'] ?>&&empresa_id=<?= htmlspecialchars($idSelecionado) ?>" class="btn btn-danger">Sim, excluir</a>
                                <button type="button" class="btn btn-secondary mx-2" data-bs-dismiss="modal">Cancelar</button>
                              </div>
                            </div>
                          </div>
                        </div>
                        <!-- /Modal de Exclusão de Transação -->

                        <!-- Modal de Editar Conta -->
                        <div class="modal fade" id="editContaModal_<?= $conta['id'] ?>" tabindex="-1" aria-labelledby="editContaModalLabel_<?= $conta['id'] ?>" aria-hidden="true">
                          <div class="modal-dialog">
                            <div class="modal-content">

                              <div class="modal-header">
                                <h5 class="modal-title" id="editContaModalLabel_<?= $conta['id'] ?>">Editar Transação</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                              </div>

                              <div class="modal-body">
                                <form action="../../assets/php/financas/editarContasPaga.php?empresa_id=<?= htmlspecialchars($idSelecionado) ?>" method="POST">
                                  <input type="hidden" name="empresa_id" value="<?= htmlspecialchars($idSelecionado) ?>" />
                                  <!-- ID da conta -->

                                  <input type="hidden" name="id" value="<?= htmlspecialchars($conta['id']) ?>">

                                  <div class="mb-3">
                                    <label for="descricao" class="form-label">Descrição</label>
                                    <input type="text" class="form-control" id="descricao" name="descricao" value="<?= htmlspecialchars($conta['descricao']) ?>" required>
                                  </div>

                                  <div class="mb-3">
                                    <label for="valorpago" class="form-label">Valor Pago</label>
                                    <input type="text" class="form-control" id="valorpago" name="valorpago" value="<?= number_format($conta['valorpago'], 2, ',', '.') ?>" required>
                                  </div>

                                  <div class="mb-3">
                                    <label for="datatransacao" class="form-label">Data da Transação</label>
                                    <input type="date" class="form-control" id="datatransacao" name="datatransacao" value="<?= htmlspecialchars($conta['datatransacao']) ?>" required>
                                  </div>

                                  <div class="mb-3">
                                    <label for="responsavel" class="form-label">Responsável</label>
                                    <input type="text" class="form-control" id="responsavel" name="responsavel" value="<?= htmlspecialchars($conta['responsavel']) ?>" required>
                                  </div>

                                  <div class="mb-3">
                                    <label for="statuss" class="form-label">Status</label>
                                    <select class="form-select" id="statuss" name="statuss" value="<?= htmlspecialchars($conta['statuss']) ?>" required>
                                      <option value="pago" <?= $conta['statuss'] === 'pago' ? 'selected' : '' ?>>Pago</option>
                                      <option value="pendente" <?= $conta['statuss'] === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                                      <option value="futura" <?= $conta['statuss'] === 'futura' ? 'selected' : '' ?>>Futura</option>
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
                      </td>

                    </tr>
                  <?php endforeach; ?>
                  <!-- Mais transações podem ser adicionadas aqui -->

                </tbody>

              </table>
              <!-- /table -->

            </div>
            <!-- table-responsive -->

          </div>

          <div id="" class="mt-3 add-category justify-content-center d-flex text-center align-items-center" onclick="window.location.href='adicionarConta.php';" style="cursor: pointer;">
            <i class="tf-icons bx bx-plus me-2"></i>
            <span>Adicionar nova Conta</span>
          </div>

        </div>

      </div>
      <!-- / Layout container -->

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

</body>

</html>
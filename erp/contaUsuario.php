<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

$idSelecionado = $_GET['id'] ?? '';
if (!$idSelecionado) {
  header("Location: ./login.php");
  exit;
}

if (
  !isset($_SESSION['usuario_logado']) ||
  !isset($_SESSION['empresa_id']) ||
  !isset($_SESSION['tipo_empresa']) ||
  !isset($_SESSION['usuario_id'])
) {
  header("Location: ./login.php?id=" . urlencode($idSelecionado));
  exit;
}

require '../assets/php/conexao.php';

$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id  = (int)$_SESSION['usuario_id'];

try {
  $stmt = $pdo->prepare("SELECT id, usuario, cpf, email, empresa_id FROM contas_acesso WHERE id = :id LIMIT 1");
  $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
  $stmt->execute();
  $conta = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($conta) {
    $nomeUsuario = $conta['usuario'];
  } else {
    echo "<script>alert('Usuário não encontrado.'); window.location.href = './login.php?id=" . urlencode($idSelecionado) . "';</script>";
    exit;
  }
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar usuário: " . $e->getMessage() . "'); history.back();</script>";
  exit;
}

// ACL (igual ao seu)
$acessoPermitido   = false;
$idEmpresaSession  = (string)$_SESSION['empresa_id'];
$tipoSession       = (string)$_SESSION['tipo_empresa'];
if (str_starts_with($idSelecionado, 'principal_')) {
  $acessoPermitido = ($tipoSession === 'principal' && $idEmpresaSession === $idSelecionado);
} elseif (str_starts_with($idSelecionado, 'filial_')) {
  $acessoPermitido = ($tipoSession === 'filial' && $idEmpresaSession === $idSelecionado);
} elseif (str_starts_with($idSelecionado, 'unidade_')) {
  $acessoPermitido = ($tipoSession === 'unidade' && $idEmpresaSession === $idSelecionado);
} elseif (str_starts_with($idSelecionado, 'franquia_')) {
  $acessoPermitido = ($tipoSession === 'franquia' && $idEmpresaSession === $idSelecionado);
}
if (!$acessoPermitido) {
  echo "<script>alert('Acesso negado!'); window.location.href = './login.php?id=" . urlencode($idSelecionado) . "';</script>";
  exit;
}

// Logo
try {
  $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1");
  $stmt->bindParam(':id', $idSelecionado, PDO::PARAM_STR);
  $stmt->execute();
  $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);
  $logoEmpresa = (!empty($empresaSobre['imagem'])) ? "../assets/img/empresa/" . $empresaSobre['imagem'] : "../assets/img/favicon/logo.png";
} catch (PDOException $e) {
  $logoEmpresa = "../assets/img/favicon/logo.png";
}

// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="pt-BR" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/" data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>ERP - Minha Conta</title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($logoEmpresa) ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
  <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="../assets/css/demo.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>
  <style>
    /* Polimento visual */
    .color-primary {
      color: #696cff !important
    }

    .card {
      border-radius: 16px;
    }

    .card-header {
      padding-bottom: 8px;
      font-weight: 600;
    }

    .form-label {
      font-weight: 600;
      color: #465;
    }

    .input-group>.btn {
      border-top-left-radius: 0;
      border-bottom-left-radius: 0
    }

    .muted {
      color: #6c757d
    }

    .small-hint {
      font-size: .85rem
    }
  </style>
</head>

<body>
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">

      <!-- Sidebar resumido (igual ao seu) -->

      <!-- Menu -->
      <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
        <div class="app-brand demo">
          <a href="./dashboard.php?id=<?= urlencode($idSelecionado); ?>" class="app-brand-link">
            <span class="app-brand-text demo menu-text fw-bolder ms-2">Açainhadinhos</span>
          </a>
          <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
            <i class="bx bx-chevron-left bx-sm align-middle"></i>
          </a>
        </div>

        <div class="menu-inner-shadow"></div>

        <ul class="menu-inner py-1">
          <li class="menu-item">
            <a href="dashboard.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
              <i class="menu-icon tf-icons bx bx-home-circle"></i>
              <div data-i18n="Analytics">Dashboard</div>
            </a>
          </li>

          <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
          <li class="menu-item">
            <a href="./rh/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-group"></i>
              <div>RH</div>
            </a>
            <a href="./financas/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-dollar"></i>
              <div>Finanças</div>
            </a>
            <a href="./pdv/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-desktop"></i>
              <div>PDV</div>
            </a>
            <a href="./estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-box"></i>
              <div>Estoque</div>
            </a>
            <a href="./empresa/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-briefcase"></i>
              <div>Empresa</div>
            </a>

            <?php
            $tipoLogado = $_SESSION['tipo_empresa'] ?? '';
            $idLogado   = $_SESSION['empresa_id'] ?? '';

            if ($tipoLogado === 'principal') {
            ?>
          <li class="menu-item">
            <a href="./filial/index.php?id=<?= urlencode($idSelecionado) ?>" class="menu-link">
              <i class="menu-icon tf-icons bx bx-building"></i>
              <div>Filial</div>
            </a>
          </li>
          <li class="menu-item">
            <a href="./franquia/index.php?id=<?= urlencode($idSelecionado) ?>" class="menu-link">
              <i class="menu-icon tf-icons bx bx-store"></i>
              <div>Franquias</div>
            </a>
          </li>
        <?php } elseif (in_array($tipoLogado, ['filial', 'franquia', 'unidade'])) { ?>
          <li class="menu-item">
            <a href="./matriz/index.php?id=<?= urlencode($idLogado) ?>" class="menu-link">
              <i class="menu-icon tf-icons bx bx-cog"></i>
              <div>Matriz</div>
            </a>
          </li>
        <?php } ?>
        </li>

        <li class="menu-item">
          <a href="./usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
            <i class="menu-icon tf-icons bx bx-user"></i>
            <div>Usuários</div>
          </a>
        </li>

        <li class="menu-header small text-uppercase"><span class="menu-header-text">Usuário</span></li>
        <li class="menu-item">
          <a href="https://wa.me/92991515710" target="_blank" class="menu-link">
            <i class="menu-icon tf-icons bx bx-support"></i>
            <div>Suporte</div>
          </a>
        </li>
        <li class="menu-item active">
          <a href="./contaUsuario.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
            <i class="menu-icon tf-icons bx bx-id-card"></i>
            <div>Minha Conta</div>
          </a>
        </li>
        </ul>
      </aside>
      <!-- / Menu -->

      <div class="layout-page">
        <!-- Navbar simples -->
        <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
          <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
            <ul class="navbar-nav flex-row align-items-center ms-auto">
              <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow" href="#" data-bs-toggle="dropdown">
                  <div class="avatar avatar-online">
                    <img src="<?= htmlspecialchars($logoEmpresa, ENT_QUOTES) ?>" class="w-px-40 h-auto rounded-circle" />
                  </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li><a class="dropdown-item" href="../logout.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-power-off me-2"></i>Sair</a></li>
                </ul>
              </li>
            </ul>
          </div>
        </nav>
        <!-- /Navbar -->

        <div class="content-wrapper">
          <div class="container-xxl flex-grow-1 container-p-y">
            <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light color-primary">Configurações da Conta /</span> Conta</h4>

            <div class="row">
              <div class="col-12">
                <div class="card mb-4">
                  <h5 class="card-header">Detalhes do perfil</h5>
                  <hr class="my-0" />
                  <div class="card-body">
                    <form id="formAccountSettings" method="POST" action="../assets/php/auth/atualizarConta.php" autocomplete="off">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                      <input type="hidden" name="id" value="<?= (int)$conta['id'] ?>">
                      <input type="hidden" name="redirect_id" value="<?= htmlspecialchars($idSelecionado, ENT_QUOTES) ?>">

                      <div class="row g-4">
                        <!-- Coluna esquerda -->
                        <div class="col-md-6">
                          <div class="mb-3">
                            <label for="usuario" class="form-label">Nome de Usuário</label>
                            <input class="form-control" type="text" id="usuario" name="usuario" required value="<?= htmlspecialchars($conta['usuario']) ?>">
                          </div>

                          <div class="mb-3">
                            <label for="email" class="form-label">E-mail (novo)</label>
                            <div class="input-group">
                              <input class="form-control" type="email" id="email" name="email" value="<?= htmlspecialchars($conta['email']) ?>">
                            </div>
                          </div>

                          <div class="mb-3">
                            <label for="empresa_id" class="form-label">Empresa ID</label>
                            <input class="form-control" type="text" id="empresa_id" value="<?= htmlspecialchars($conta['empresa_id']) ?>" disabled>
                            <input type="hidden" name="empresa_id_bypass" value="<?= htmlspecialchars($conta['empresa_id']) ?>">
                            <small class="small-hint muted">Este campo é bloqueado.</small>
                          </div>
                        </div>

                        <!-- Coluna direita -->
                        <div class="col-md-6">
                          <div class="mb-3">
                            <label for="cpf" class="form-label">CPF</label>
                            <input class="form-control" type="text" id="cpf" name="cpf" maxlength="14" required placeholder="000.000.000-00" value="<?= htmlspecialchars($conta['cpf']) ?>">
                          </div>

                          <div class="mb-3 d-flex align-items-center justify-content-between">
                            <label class="form-label m-0">Nova senha (opcional)</label>
                          </div>
                          <input class="form-control mb-2" type="password" id="senha" name="senha" minlength="6" placeholder="••••••••">
                          <input class="form-control mb-2" type="password" id="senha_confirm" name="senha_confirm" minlength="6" placeholder="Confirmar nova senha">

                        </div>
                      </div>

                      <div class="mt-4">
                        <button type="submit" class="btn btn-primary me-2">Salvar Alterações</button>
                        <a href="./contaUsuario.php?id=<?= urlencode($idSelecionado); ?>" class="btn btn-outline-secondary">Cancelar</a>
                      </div>
                    </form>
                  </div>
                </div>

                <!-- Excluir conta -->
                <div class="card">
                  <h5 class="card-header">Excluir Conta</h5>
                  <div class="card-body">
                    <div class="alert alert-warning">
                      <h6 class="alert-heading fw-bold mb-1">Tem certeza de que deseja excluir sua conta?</h6>
                      <p class="mb-0">Depois de excluir sua conta, não há como voltar atrás.</p>
                    </div>
                    <form method="POST" action="../assets/php/auth/excluirConta.php" onsubmit="return confirm('Confirmar exclusão da conta?');">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                      <input type="hidden" name="id" value="<?= (int)$conta['id'] ?>">
                      <input type="hidden" name="redirect_id" value="<?= htmlspecialchars($idSelecionado, ENT_QUOTES) ?>">
                      <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="confirm" id="confirmDel" required>
                        <label class="form-check-label" for="confirmDel">Confirmo a exclusão da minha conta</label>
                      </div>
                      <button type="submit" class="btn btn-danger">Desativar Conta</button>
                    </form>
                  </div>
                </div>

              </div>
            </div>
          </div>

          <footer class="content-footer footer bg-footer-theme text-center">
            <div class="container-xxl d-flex py-2 flex-md-row flex-column justify-content-center">
              <div class="mb-2 mb-md-0">&copy; <script>
                  document.write(new Date().getFullYear());
                </script>, <strong>Açainhadinhos</strong>. Todos os direitos reservados.</div>
            </div>
          </footer>
          <div class="content-backdrop fade"></div>
        </div>
      </div>
    </div>

    <div class="layout-overlay layout-menu-toggle"></div>
  </div>

  <!-- Core JS -->
  <script src="../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../assets/vendor/libs/popper/popper.js"></script>
  <script src="../assets/vendor/js/bootstrap.js"></script>
  <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="../assets/vendor/js/menu.js"></script>
  <script src="../assets/js/main.js"></script>

</body>

</html>
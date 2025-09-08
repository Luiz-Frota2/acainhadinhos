<?php
$idSelecionado = $_GET['id'] ?? '';

if (str_starts_with($idSelecionado, 'principal_')) {
  $id = 1;
  $tipoEmpresa = 'principal';
  // lógica para empresa principal
} elseif (str_starts_with($idSelecionado, 'unidade_')) {
  $id = (int) str_replace('unidade_', '', $idSelecionado);
  $tipoEmpresa = 'unidade';
  // lógica para franquia ou filial
} else {
  echo "<script>alert('Empresa não identificada!'); history.back();</script>";
  exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br" class="light-style customizer-hide" dir="ltr" data-theme="theme-default"
  data-assets-path="../assets/" data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>ERP - LOGIN</title>

  <meta name="description" content="" />

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
    rel="stylesheet" />

  <!-- Icons -->
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />

  <!-- Core CSS -->
  <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="../assets/css/demo.css" />

  <!-- Vendors CSS -->
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

  <!-- Page CSS -->
  <link rel="stylesheet" href="../assets/vendor/css/pages/page-auth.css" />

  <!-- Helpers -->
  <script src="../assets/vendor/js/helpers.js"></script>

  <!-- Template config -->
  <script src="../assets/js/config.js"></script>
</head>

<body>
  <!-- Content -->

  <div class="container-xxl">
    <div class="authentication-wrapper authentication-basic container-p-y">
      <div class="authentication-inner">
        <!-- Login Card -->
        <div class="card">
          <div class="card-body">
            <!-- Logo -->
            <div class="app-brand justify-content-center mb-4">
              <a href="index.html" class="app-brand-link gap-2">
                <span class="app-brand-text demo text-body fw-bolder">Login</span>
              </a>
            </div>
            <!-- /Logo -->

            <form id="formAuthentication" class="mb-3" action="../frentedeloja/login/processarLogin.php" method="POST">
              <input type="hidden" name="empresa_identificador" value="<?= htmlspecialchars($idSelecionado) ?>">

              <div class="mb-3">
                <label for="email" class="form-label">CPF ou Nome de Usuário</label>
                <input type="text" class="form-control" id="email" name="usuario_cpf"
                  placeholder="Digite seu CPF ou nome de usuário" required autofocus />
              </div>

              <div class="mb-3 form-password-toggle">
                <div class="d-flex justify-content-between">
                  <label class="form-label" for="password">Senha</label>
                  <a href="redefinirSenha.php?id=<?= htmlspecialchars($idSelecionado) ?>">
                    <small>Esqueceu a senha?</small>
                  </a>
                </div>
                <div class="input-group input-group-merge">
                  <input type="password" id="password" class="form-control" name="senha"
                    placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" required />
                  <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
                </div>
              </div>

              <div class="d-flex justify-content-between mb-3">
                <a href="criarConta.php?id=<?= htmlspecialchars($idSelecionado) ?>">
                  <small>Criar conta</small>
                </a>
              </div>

              <div class="mb-3">
                <button class="btn btn-primary d-grid w-100" type="submit">Entrar</button>
              </div>
            </form>

            <div class="text-center">
              <a href="../erp/painelAcesso.php?id=<?= htmlspecialchars($idSelecionado) ?>"
                class="d-flex align-items-center justify-content-center">
                <i class="bx bx-chevron-left scaleX-n1-rtl bx-sm"></i>
                Voltar para o painel de Acesso
              </a>
            </div>
          </div>
        </div>
        <!-- /Login Card -->
      </div>
    </div>
  </div>

  <!-- / Content -->

  <!-- Core JS -->
  <script src="../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../assets/vendor/libs/popper/popper.js"></script>
  <script src="../assets/vendor/js/bootstrap.js"></script>
  <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>

  <script src="../assets/vendor/js/menu.js"></script>

  <!-- Main JS -->
  <script src="../assets/js/main.js"></script>

  <!-- Optional: GitHub buttons -->
  <script async defer src="https://buttons.github.io/buttons.js"></script>

</body>

</html>

<?php

$idSelecionado = $_GET['id'] ?? '';

if (str_starts_with($idSelecionado, 'principal_')) {
  $id = 1;
  // lógica para a empresa principal
} elseif (str_starts_with($idSelecionado, 'filial_')) {
  $id = (int) str_replace('filial_', '', $idSelecionado);
  // lógica para a filial
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

  <title>ERP | Selecione o Módulo</title>

  <meta name="description" content="" />

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="../assets/img/favicon/site.png"/>

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
  <script src="../assets/js/config.js"></script>
</head>

<body>
  <!-- Content -->
  <div class="container-xxl">
    <div class="authentication-wrapper authentication-basic container-p-y">
      <div class="authentication-inner">
        <!-- Card -->
        <div class="card">
          <div class="card-body">
            <!-- Título -->

            <div class="app-brand justify-content-center mb-4">
              <a href="#" class="app-brand-link gap-2">
                <span class="app-brand-text demo text-body fw-bolder">Escolha o módulo</span>
              </a>
            </div>


            <!-- Botões -->
            <div class="d-grid gap-3 text-center mb-3">
              <a href="../../frentedeloja/index.php?id=<?= htmlspecialchars($idSelecionado) ?>"
                class="btn btn-outline-primary btn-lg">
                <i class="bx bx-store me-2"></i> Frente de Loja
              </a>
              <a href="./login.php?id=<?= htmlspecialchars($idSelecionado) ?>" class="btn btn-outline-primary btn-lg">
                <i class="bx bx-slider me-2"></i> Retaguarda
              </a>
            </div>

            <div class="text-center">
              <a href="index.php" class="d-flex align-items-center justify-content-center">
                <i class="bx bx-chevron-left scaleX-n1-rtl bx-sm"></i>
                Voltar para a página inicial
              </a>
            </div>

            <!-- Rodapé -->
            <div class="footer mt-4" style="font-size: 12px; color: #888; text-align: center;">
              &copy; Açainhadinhos. Todos os direitos reservados.
            </div>
          </div>
        </div>
        <!-- /Card -->
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
</body>

</html>
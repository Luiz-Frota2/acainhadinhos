<!DOCTYPE html>
<html lang="pt-br" class="light-style customizer-hide" dir="ltr" data-theme="theme-default"
  data-assets-path="../assets/" data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>ERP | Selecione a Empresa</title>

  <meta name="description" content="" />

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="../assets/img/favicon/site.png" />

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
    rel="stylesheet" />

  <!-- Icons. Uncomment required icon fonts -->
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />

  <!-- Core CSS -->
  <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="../assets/css/demo.css" />

  <!-- Vendors CSS -->
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

  <!-- Page CSS -->
  <!-- Page -->
  <link rel="stylesheet" href="../assets/vendor/css/pages/page-auth.css" />
  <!-- Helpers -->
  <script src="../assets/vendor/js/helpers.js"></script>

  <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
  <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
  <script src="../assets/js/config.js"></script>
</head>

<body>
  <!-- Content -->

  <div class="container-xxl">
    <div class="authentication-wrapper authentication-basic container-p-y">
      <div class="authentication-inner">
        <!-- Register -->
        <div class="card">
          <div class="card-body">
            <!-- Logo -->
            <div class="app-brand justify-content-center">
              <a href="index.php" class="app-brand-link gap-2">

                <span class="app-brand-text demo text-body fw-bolder">Selecione a Unidade</span>
              </a>
            </div>
            <!-- /Logo -->

            <?php
            require '../assets/php/conexao.php';

            $empresas = [];

            try {
              // Verifica se existe 'principal_1' no banco
              $stmt = $pdo->prepare("SELECT nome_empresa FROM sobre_empresa WHERE id_selecionado = 'principal_1' LIMIT 1");
              $stmt->execute();

              if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $empresas[] = [
                  'id' => 'principal_1',
                  'nome' => $row['nome_empresa'] . " - (PRINCIPAL)"
                ];
              } else {
                // Cria um registro fictício para 'principal_1'
                $pdo->prepare("INSERT INTO sobre_empresa (nome_empresa, sobre_empresa, imagem, id_selecionado)
                       VALUES ('EMPRESA PRINCIPAL', 'Informações da empresa principal ainda não cadastradas.', '', 'principal_1')")
                  ->execute();

                $empresas[] = [
                  'id' => 'principal_1',
                  'nome' => 'EMPRESA PRINCIPAL - (PRINCIPAL)'
                ];
              }
            } catch (PDOException $e) {
              echo "<script>history.back()</script>";
            }

            try {
              $stmtFiliais = $pdo->query("SELECT id_filial, nome FROM filiais ORDER BY nome");
              while ($filial = $stmtFiliais->fetch(PDO::FETCH_ASSOC)) {
                $empresas[] = [
                  'id' => 'filial_' . $filial['id_filial'],
                  'nome' => $filial['nome']
                ];
              }
            } catch (PDOException $e) {
              echo "<script>history.back()</script>";
            }
            ?>


            <form class="mb-3" action="painelAcesso.php" method="GET">
              <div class="mb-3">
                <select name="id" class="form-select mb-3" required>
                  <option value="">Escolha uma empresa</option>
                  <?php foreach ($empresas as $empresa): ?>
                    <option value="<?= $empresa['id'] ?>"><?= $empresa['nome'] ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-3">
                <button class="btn btn-primary d-grid w-100" type="submit">Entrar</button>
              </div>

              <div class="footer" style="margin-top: 20px; font-size: 12px; color: #888; text-align: center;">
                &copy; <?= date("Y") ?> Açainhadinhos. Todos os direitos reservados.
              </div>
            </form>

          </div>
        </div>
        <!-- /Register -->
      </div>
    </div>

  </div>

  <!-- / Content -->
  <!-- Core JS -->
  <!-- build:js assets/vendor/js/core.js -->
  <script src="../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../assets/vendor/libs/popper/popper.js"></script>
  <script src="../assets/vendor/js/bootstrap.js"></script>
  <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>

  <script src="../assets/vendor/js/menu.js"></script>
  <!-- endbuild -->

  <!-- Vendors JS -->

  <!-- Main JS -->
  <script src="../assets/js/main.js"></script>

  <!-- Page JS -->

  <!-- Place this tag in your head or just before your close body tag. -->
  <script async defer src="https://buttons.github.io/buttons.js"></script>

</body>

</html>
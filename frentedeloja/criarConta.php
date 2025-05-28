<!DOCTYPE html>
<html lang="pt-br" class="light-style customizer-hide" dir="ltr" data-theme="theme-default"
  data-assets-path="../assets/" data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>ERP - CRIAR CONTA</title>

  <meta name="description" content="" />

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />

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
        <!-- Register Card -->
        <div class="card">
          <div class="card-body">
            <!-- Logo -->
            <div class="app-brand justify-content-center">
              <a href="index.html" class="app-brand-link gap-2">
                <span class="app-brand-text demo text-body fw-bolder">Criar Conta</span>
              </a>
            </div>

            <?php
              require '../assets/php/conexao.php';

              // Captura o ID da URL
              $idSelecionado = $_GET['id'] ?? '';

              if (!str_starts_with($idSelecionado, 'principal_') && !str_starts_with($idSelecionado, 'filial_')) {
                echo "<script>alert('Empresa não identificada!'); history.back();</script>";
                exit;
              }

              // Carregar empresas
              $empresas = [];

              try {
                // Empresa principal
                $stmtPrincipal = $pdo->query("SELECT nome_empresa FROM sobre_empresa LIMIT 1");
                if ($stmtPrincipal->rowCount() > 0) {
                  $row = $stmtPrincipal->fetch(PDO::FETCH_ASSOC);
                  $empresas[] = [
                    'id' => 'principal_1',
                    'nome' => $row['nome_empresa'] . ' - (PRINCIPAL)'
                  ];
                }

                // Filiais
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

            <!-- /Logo -->
            <form id="formAuthentication" class="mb-3" action="../frentedeloja/login/processarCadastro.php" method="POST">

              <div class="mb-3">
                <label for="usuario" class="form-label">Nome de Usuário</label>
                <input type="text" class="form-control" id="usuario" name="usuario"
                  placeholder="Digite seu nome de usuário" autofocus />
              </div>

                <div class="mb-3">
                <label for="cpf" class="form-label">CPF</label>
                <input type="text" class="form-control" id="cpf" name="cpf" placeholder="Digite seu CPF" required maxlength="14" />
                </div>
                <script>
                document.addEventListener('DOMContentLoaded', function () {
                  const cpfInput = document.getElementById('cpf');
                  cpfInput.addEventListener('input', function (e) {
                  let value = cpfInput.value.replace(/\D/g, '');
                  if (value.length > 11) value = value.slice(0, 11);
                  value = value.replace(/(\d{3})(\d)/, '$1.$2');
                  value = value.replace(/(\d{3})(\d)/, '$1.$2');
                  value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                  cpfInput.value = value;
                  });
                });
                </script>

              <div class="mb-3">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" class="form-control" id="email" name="email" placeholder="Digite seu e-mail"
                  required />
              </div>

              <div class="mb-3 form-password-toggle">
                <label class="form-label" for="senha">Senha</label>
                <div class="input-group input-group-merge">
                  <input type="password" id="senha" class="form-control" name="senha"
                    placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                    aria-describedby="password" />
                  <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
                </div>
              </div>

              <div class="mb-3">
                <label for="empresa" class="form-label">Empresa</label>
                <select name="empresa_identificador" id="empresa" class="form-select" required>
                  <option value="">Selecione a empresa</option>
                  <?php foreach ($empresas as $empresa): ?>
                    <option value="<?= $empresa['id'] ?>" <?= $empresa['id'] == $idSelecionado ? 'selected' : '' ?>>
                      <?= $empresa['nome'] ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <!-- Campo hidden para garantir envio do ID também -->
              <input type="hidden" name="id" value="<?= htmlspecialchars($idSelecionado) ?>">

              <button class="btn btn-primary d-grid w-100">Cadastrar</button>
            </form>

            <p class="text-center">
              <span>Já tem uma conta?</span>
              <a href="login.php?id=<?= htmlspecialchars($idSelecionado) ?>">
                <span>Faça login em vez disso</span>
              </a>
            </p>

          </div>
        </div>
        <!-- Register Card -->
      </div>
    </div>
  </div>

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
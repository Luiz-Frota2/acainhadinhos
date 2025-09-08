<!DOCTYPE html>
<html lang="pt-br" class="light-style customizer-hide" dir="ltr" data-theme="theme-default"
  data-assets-path="../assets/" data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
  <title>ERP | Selecione a Empresa</title>

  <meta name="description" content="" />
  <link rel="icon" type="image/x-icon" href="../assets/img/favicon/site.png" />

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />

  <!-- Icons -->
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
  <!-- Core CSS -->
  <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="../assets/css/demo.css" />
  <!-- Vendors CSS -->
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="../assets/vendor/css/pages/page-auth.css" />

  <!-- Helpers -->
  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>
</head>

<body>
  <div class="container-xxl">
    <div class="authentication-wrapper authentication-basic container-p-y">
      <div class="authentication-inner">
        <div class="card">
          <div class="card-body">
            <!-- Logo -->
            <div class="app-brand justify-content-center">
              <a href="index.php" class="app-brand-link gap-2">
                <span class="app-brand-text demo text-body fw-bolder">Selecione a Unidade</span>
              </a>
            </div>

            <?php
            require '../assets/php/conexao.php';
            $empresas = [];

            try {
              // Verifica se a empresa principal já está cadastrada
              $stmt = $pdo->prepare("SELECT nome_empresa FROM sobre_empresa WHERE id_selecionado = 'principal_1' LIMIT 1");
              $stmt->execute();

              if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $empresas[] = [
                  'id' => 'principal_1',
                  'nome' => $row['nome_empresa'] . " - (PRINCIPAL)"
                ];
              } else {
                // Cadastra a empresa principal, se não existir
                $pdo->prepare("INSERT INTO sobre_empresa (nome_empresa, sobre_empresa, imagem, id_selecionado)
                               VALUES ('EMPRESA PRINCIPAL', 'Informações da empresa principal ainda não cadastradas.', '', 'principal_1')")->execute();
                $empresas[] = [
                  'id' => 'principal_1',
                  'nome' => 'EMPRESA PRINCIPAL - (PRINCIPAL)'
                ];
              }
            } catch (PDOException $e) {
              echo "<script>console.error('Erro ao buscar/cadastrar empresa principal'); history.back();</script>";
            }

            try {
              // Lista franquias e filiais
              $stmtUnidades = $pdo->query("SELECT id, nome, tipo FROM unidades WHERE status = 'Ativa' ORDER BY nome");
              while ($unidade = $stmtUnidades->fetch(PDO::FETCH_ASSOC)) {
                $empresas[] = [
                  'id' => 'unidade_' . $unidade['id'],
                  'nome' => $unidade['nome'] . ' - (' . strtoupper($unidade['tipo']) . ')'
                ];
              }
            } catch (PDOException $e) {
              echo "<script>console.error('Erro ao buscar unidades'); history.back();</script>";
            }
            ?>

            <form class="mb-3" action="painelAcesso.php" method="GET">
              <div class="mb-3">
                <select name="id" class="form-select mb-3" required>
                  <option value="">Escolha uma empresa</option>
                  <?php foreach ($empresas as $empresa): ?>
                    <option value="<?= htmlspecialchars($empresa['id']) ?>"><?= htmlspecialchars($empresa['nome']) ?></option>
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
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../assets/vendor/libs/popper/popper.js"></script>
  <script src="../assets/vendor/js/bootstrap.js"></script>
  <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="../assets/vendor/js/menu.js"></script>
  <script src="../assets/js/main.js"></script>
  <script async defer src="https://buttons.github.io/buttons.js"></script>
</body>

</html>

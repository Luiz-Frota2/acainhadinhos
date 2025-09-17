<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

/* ========= Parâmetros da URL ========= */
$idSelecionado = $_GET['id'] ?? '';

/* ========= Autenticação básica ========= */
if (
  !isset($_SESSION['usuario_logado']) ||
  !isset($_SESSION['empresa_id']) ||
  !isset($_SESSION['tipo_empresa']) ||
  !isset($_SESSION['usuario_id']) ||
  !isset($_SESSION['nivel'])
) {
  header("Location: ../index.php?id=" . urlencode($idSelecionado));
  exit;
}

require '../../assets/php/conexao.php'; // expõe $pdo

$usuario_id        = (int)$_SESSION['usuario_id'];
$tipoUsuarioSessao = $_SESSION['nivel']; // "Admin" ou "Comum"
$empresaSessao     = $_SESSION['empresa_id']; // ex: principal_1 ou unidade_5

/* ========= Regras de acesso por empresa ========= */
if (str_starts_with($idSelecionado, 'principal_')) {
  // só a principal_1 Admin ou quem é da própria principal
  if ($empresaSessao !== $idSelecionado && !($tipoUsuarioSessao === 'Admin' && $empresaSessao === 'principal_1')) {
    echo "<script>alert('Acesso negado!'); window.location.href='../index.php?id=" . htmlspecialchars($idSelecionado) . "';</script>";
    exit;
  }
} elseif (str_starts_with($idSelecionado, 'unidade_')) {
  // deve ser da mesma unidade ou Admin da principal_1
  if ($empresaSessao !== $idSelecionado && !($tipoUsuarioSessao === 'Admin' && $empresaSessao === 'principal_1')) {
    echo "<script>alert('Acesso negado!'); window.location.href='../index.php?id=" . htmlspecialchars($idSelecionado) . "';</script>";
    exit;
  }
} else {
  echo "<script>alert('Empresa não identificada!'); window.location.href='../index.php?id=" . htmlspecialchars($idSelecionado) . "';</script>";
  exit;
}

/* ========= Dados do usuário (nome, nível, CPF) ========= */
$nomeUsuario = 'Usuário';
$nivelUsuario = 'Comum';
$cpfUsuario = null;
$idFuncionario = $usuario_id;

try {
  if ($tipoUsuarioSessao === 'Admin') {
    // Admin na contas_acesso
    $stmt = $pdo->prepare("SELECT id, usuario, nivel, cpf FROM contas_acesso WHERE id = :id LIMIT 1");
  } else {
    // Demais usuários na funcionarios_acesso
    $stmt = $pdo->prepare("SELECT id, usuario, nivel, cpf FROM funcionarios_acesso WHERE id = :id LIMIT 1");
  }
  $stmt->bindValue(':id', $usuario_id, PDO::PARAM_INT);
  $stmt->execute();
  $u = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$u) {
    echo "<script>alert('Usuário não encontrado.'); window.location.href = '../index.php?id=" . htmlspecialchars($idSelecionado) . "';</script>";
    exit;
  }

  $nomeUsuario   = $u['usuario'] ?? 'Usuário';
  $nivelUsuario  = ucfirst($u['nivel'] ?? 'Comum');
  $cpfUsuario    = preg_replace('/\D+/', '', (string)($u['cpf'] ?? ''));
  $idFuncionario = (int)($u['id'] ?? $usuario_id);
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar dados do usuário: " . addslashes($e->getMessage()) . "'); history.back();</script>";
  exit;
}

/* ========= Favicon da empresa ========= */
$iconeEmpresa = '../../assets/img/favicon/favicon.ico';
try {
  $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1");
  $stmt->bindValue(':id', $idSelecionado);
  $stmt->execute();
  $emp = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($emp && !empty($emp['imagem'])) {
    // se já vier caminho pronto, só usa
    $iconeEmpresa = $emp['imagem'];
  }
} catch (PDOException $e) {
  error_log("Erro ao carregar ícone: " . $e->getMessage());
}

/* ========= Saldo do caixa aberto (por CPF + empresa) ========= */
$saldoFinalRaw = null;
$saldoFormatado = "Sem Saldo";

try {
  $stmtSaldo = $pdo->prepare("
    SELECT valor_liquido
    FROM aberturas
    WHERE cpf_responsavel = :cpf
      AND empresa_id = :empresa
      AND status = 'aberto'
    ORDER BY id DESC
    LIMIT 1
  ");
  $stmtSaldo->execute([
    ':cpf'     => $cpfUsuario,
    ':empresa' => $idSelecionado
  ]);
  $row = $stmtSaldo->fetch(PDO::FETCH_ASSOC);

  if ($row && isset($row['valor_liquido'])) {
    $v = (float)$row['valor_liquido'];
    $saldoFinalRaw  = number_format($v, 2, '.', '');   // para POST
    $saldoFormatado = number_format($v, 2, ',', '.');  // para exibição
  }
} catch (PDOException $e) {
  echo "<script>alert('Erro ao buscar saldo: " . addslashes($e->getMessage()) . "'); history.back();</script>";
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
  <link rel="icon" type="image/x-icon" href="<?=
                                              htmlspecialchars(
                                                // se o valor no banco já for relativo ao site, mantenha; senão, prefixe diretórios conforme seu projeto
                                                (str_starts_with($iconeEmpresa, 'http') || str_starts_with($iconeEmpresa, '/'))
                                                  ? $iconeEmpresa
                                                  : ('../../assets/img/empresa/' . ltrim($iconeEmpresa, '/'))
                                              );
                                              ?>" />
  <title>ERP - PDV | Fechamento de Caixa</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../../assets/vendor/fonts/boxicons.css" />
  <link rel="stylesheet" href="../../../assets/vendor/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="../../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="../../../assets/css/demo.css" />
  <link rel="stylesheet" href="../../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="../../../assets/vendor/css/pages/page-auth.css" />
  <script src="../../../assets/vendor/js/helpers.js"></script>
  <script src="../../../assets/js/config.js"></script>
</head>

<body>
  <div class="container-xxl">
    <div class="authentication-wrapper authentication-basic container-p-y">
      <div class="authentication-inner">
        <div class="card">
          <div class="card-body">
            <div class="app-brand justify-content-center mb-3">
              <span class="app-brand-text demo text-body fw-bolder">Fechamento de Caixa</span>
            </div>

            <!-- Form -->
            <form action="../../assets/php/frentedeloja/fecharCaixaSubmit.php?id=<?= urlencode($idSelecionado) ?>" method="POST">
              <input type="hidden" name="empresa_identificador" value="<?= htmlspecialchars($idSelecionado) ?>">
              <input type="hidden" name="funcionario_id" value="<?= htmlspecialchars((string)$idFuncionario) ?>">
              <input type="hidden" name="responsavel" value="<?= htmlspecialchars($nomeUsuario) ?>">
              <input type="hidden" name="cpf_funcionario" value="<?= htmlspecialchars($cpfUsuario) ?>">
              <input type="hidden" name="data_registro" id="data_registro">
              <input type="hidden" name="saldo_final" value="<?= htmlspecialchars((string)$saldoFinalRaw) ?>">

              <div class="mb-3">
                <label class="form-label">Responsável</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($nomeUsuario) ?>" readonly>
              </div>

              <div class="mb-3">
                <label class="form-label">Empresa</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($idSelecionado) ?>" readonly>
              </div>

              <div class="mb-3">
                <label for="saldo_final_display" class="form-label">Saldo Final</label>
                <input type="text" id="saldo_final_display" class="form-control"
                  value="<?= htmlspecialchars($saldoFormatado) ?>" readonly>
              </div>

              <?php if ($saldoFinalRaw === null || $cpfUsuario === null || $cpfUsuario === ''): ?>
                <div class="alert alert-warning">
                  <?php if (!$cpfUsuario): ?>
                    CPF do responsável não encontrado. Contate o administrador.
                  <?php else: ?>
                    Não há caixa aberto para esse responsável.
                  <?php endif; ?>
                </div>
              <?php else: ?>
                <div class="mb-3">
                  <button class="btn btn-primary d-grid w-100" type="submit">Fechar Caixa</button>
                </div>
              <?php endif; ?>
            </form>

            <script>
              document.addEventListener('DOMContentLoaded', function() {
                function pad(n) {
                  return String(n).padStart(2, '0');
                }

                function agora() {
                  const d = new Date();
                  return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) +
                    ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
                }
                const input = document.getElementById('data_registro');
                const form = document.querySelector('form');
                if (input) input.value = agora();
                if (form && input) {
                  form.addEventListener('submit', () => input.value = agora());
                }
              });
            </script>

            <div class="text-center">
              <a href="index.php?id=<?= htmlspecialchars($idSelecionado) ?>" class="d-flex align-items-center justify-content-center">
                <i class="bx bx-chevron-left bx-sm"></i> Voltar
              </a>
            </div>
          </div>
        </div>
        <!-- /card -->
      </div>
    </div>
  </div>

  <script src="../../../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../../../assets/vendor/libs/popper/popper.js"></script>
  <script src="../../../assets/vendor/js/bootstrap.js"></script>
  <script src="../../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="../../../assets/vendor/js/menu.js"></script>
  <script src="../../../assets/js/main.js"></script>
</body>

</html>
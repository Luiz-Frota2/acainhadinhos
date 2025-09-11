<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// ✅ Recupera o identificador vindo da URL
$idSelecionado = $_GET['id'] ?? '';

// ✅ Verifica se a pessoa está logada
if (
  !isset($_SESSION['usuario_logado']) ||
  !isset($_SESSION['empresa_id']) ||
  !isset($_SESSION['tipo_empresa']) ||
  !isset($_SESSION['usuario_id']) ||
  !isset($_SESSION['nivel']) || // Verifica se o nível está na sessão
  !isset($_SESSION['usuario_cpf']) // Verifica se o CPF está na sessão
) {
  header("Location: ../index.php?id=$idSelecionado");
  exit;
}

// ✅ Conexão com o banco de dados
require '../../assets/php/conexao.php';

$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id = $_SESSION['usuario_id'];
$usuario_cpf = $_SESSION['usuario_cpf']; // Recupera o CPF da sessão
$tipoUsuarioSessao = $_SESSION['nivel']; // "Admin" ou "Comum"

try {
  // Verifica se é um usuário de contas_acesso (Admin) ou funcionarios_acesso
  if ($tipoUsuarioSessao === 'Admin') {
    // Buscar na tabela de contas_acesso
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
  } else {
    // Buscar na tabela de funcionarios_acesso
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM funcionarios_acesso WHERE id = :id");
  }

  $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
  $stmt->execute();
  $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($usuario) {
    $nomeUsuario = $usuario['usuario'];
    $tipoUsuario = ucfirst($usuario['nivel']);
  } else {
    echo "<script>alert('Usuário não encontrado.'); window.location.href = './index.php?id=$idSelecionado';</script>";
    exit;
  }
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar nome e tipo do usuário: " . addslashes($e->getMessage()) . "'); history.back();</script>";
  exit;
}

// ✅ Valida o tipo de empresa e o acesso permitido
if (str_starts_with($idSelecionado, 'principal_')) {
  // Para principal, verifica se é admin ou se pertence à mesma empresa
  if (
    $_SESSION['tipo_empresa'] !== 'principal' &&
    !($tipoUsuarioSessao === 'Admin' && $_SESSION['empresa_id'] === 'principal_1')
  ) {
    echo "<script>
            alert('Acesso negado!');
            window.location.href = '../index.php?id=$idSelecionado';
        </script>";
    exit;
  }
  $id = 1;
} elseif (str_starts_with($idSelecionado, 'unidade_')) {
  $idUnidade = str_replace('unidade_', '', $idSelecionado);

  // Verifica se o usuário pertence à mesma unidade ou é admin da principal_1
  $acessoPermitido = ($_SESSION['empresa_id'] === $idSelecionado) ||
    ($tipoUsuarioSessao === 'Admin' && $_SESSION['empresa_id'] === 'principal_1');

  if (!$acessoPermitido) {
    echo "<script>
            alert('Acesso negado!');
            window.location.href = '../index.php?id=$idSelecionado';
        </script>";
    exit;
  }
  $id = $idUnidade;
} else {
  echo "<script>
        alert('Empresa não identificada!');
        window.location.href = '../index.php?id=$idSelecionado';
    </script>";
  exit;
}

// ✅ Buscar imagem da empresa para usar como favicon
$iconeEmpresa = '../../assets/img/favicon/favicon.ico'; // Ícone padrão

try {
  $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1");
  $stmt->bindParam(':id_selecionado', $idSelecionado);
  $stmt->execute();
  $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($empresa && !empty($empresa['imagem'])) {
    $iconeEmpresa = $empresa['imagem'];
  }
} catch (PDOException $e) {
  error_log("Erro ao carregar ícone da empresa: " . $e->getMessage());
  // Não mostra erro para o usuário para não quebrar a página
}

// ✅ Função para buscar o nome do funcionário pelo CPF
function obterNomeFuncionario($pdo, $cpf)
{
  try {
    $stmt = $pdo->prepare("SELECT nome, cpf FROM funcionarios_acesso WHERE cpf = :cpf");
    $stmt->bindParam(':cpf', $cpf, PDO::PARAM_STR);
    $stmt->execute();
    $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($funcionario && (!empty($funcionario['nome']) || !empty($funcionario['cpf']))) {
      return $funcionario['nome'];
    } else {
      return 'Funcionário não identificado';
    }
  } catch (PDOException $e) {
    return 'Erro ao buscar nome';
  }
}

// ✅ Aplica a função se for funcionário
if (!empty($usuario_cpf)) {
  $nomeFuncionario = obterNomeFuncionario($pdo, $usuario_cpf);
}

?>

<!DOCTYPE html>
<html lang="pt-br" class="light-style customizer-hide" dir="ltr" data-theme="theme-default"
  data-assets-path="../assets/" data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>" />

  <title>ERP - PDV</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
  <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="../../assets/css/demo.css" />
  <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="../../assets/vendor/css/pages/page-auth.css" />

  <script src="../../../assets/vendor/js/helpers.js"></script>
  <script src="../../../assets/js/config.js"></script>
</head>

<body>
  <div class="container-xxl">
    <div class="authentication-wrapper authentication-basic container-p-y">
      <div class="authentication-inner">
        <!-- Abertura de Caixa -->
        <div class="card">
          <div class="card-body">
            <!-- Logo -->
            <div class="app-brand justify-content-center">
              <a href="index.php" class="app-brand-link gap-2">
                <span class="app-brand-text demo text-body fw-bolder">Abertura de Caixa</span>
              </a>
            </div>
            <!-- /Logo -->

            <form action="../../assets/php/frentedeloja/abrirCaixaSubmit.php?id=<?= urlencode($idSelecionado); ?>"
              method="POST">

              <!-- Valor de Abertura -->
              <div class="mb-3">
                <input type="hidden" name="idSelecionado" value="<?php echo htmlspecialchars($idSelecionado); ?>" />
                <label for="valor_abertura" class="form-label">Valor de Abertura</label>
                <input type="hidden" id="status_abertura" name="status_abertura" value="aberto">
                <input type="hidden" id="cpf" name="cpf" value="<?php echo isset($usuario_cpf) ? htmlspecialchars($usuario_cpf) : ''; ?>">
                <input type="hidden" name="data_registro" id="data_registro">
                <input type="hidden" id="responsavel" name="responsavel" value="<?= ucwords($nomeUsuario); ?>">
                <input type="text" class="form-control" id="valor_abertura" name="valor_abertura"
                  placeholder="Digite o valor de abertura" required autofocus />
              </div>


              <!-- Botão para submeter -->
              <div class="mb-3">
                <button class="btn btn-primary d-grid w-100" type="submit">Abrir Caixa</button>
              </div>

            </form>

            <script>
              document.addEventListener('DOMContentLoaded', function() {
                // Função para formatar data/hora local como "YYYY-MM-DD HH:mm:ss"
                function formatarDataLocal(date) {
                  const pad = num => String(num).padStart(2, '0');
                  const ano = date.getFullYear();
                  const mes = pad(date.getMonth() + 1);
                  const dia = pad(date.getDate());
                  const horas = pad(date.getHours());
                  const minutos = pad(date.getMinutes());
                  const segundos = pad(date.getSeconds());
                  return `${ano}-${mes}-${dia} ${horas}:${minutos}:${segundos}`;
                }

                const inputDataRegistro = document.getElementById('data_registro');
                const form = document.querySelector('form');

                if (inputDataRegistro) {
                  // Define data atual assim que o DOM carregar
                  inputDataRegistro.value = formatarDataLocal(new Date());
                }

                if (form && inputDataRegistro) {
                  form.addEventListener('submit', function() {
                    inputDataRegistro.value = formatarDataLocal(new Date());
                  });
                }
              });
            </script>

            <div class="text-center">
              <a href="index.php?id=<?= htmlspecialchars($idSelecionado) ?>"
                class="d-flex align-items-center justify-content-center">
                <i class="bx bx-chevron-left scaleX-n1-rtl bx-sm"></i>
                Voltar
              </a>
            </div>
          </div>
        </div>
        <!-- /Abertura de Caixa -->
      </div>
    </div>
  </div>

  <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../../assets/vendor/libs/popper/popper.js"></script>
  <script src="../../assets/vendor/js/bootstrap.js"></script>
  <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="../../assets/vendor/js/menu.js"></script>
  <script src="../../assets/js/main.js"></script>
</body>

</html>
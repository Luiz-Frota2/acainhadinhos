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
  !isset($_SESSION['usuario_id'])
) {
  header("Location: ../index.php?id=$idSelecionado");
  exit;
}

// ✅ Conexão com o banco de dados
require '../../assets/php/conexao.php';

$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id = $_SESSION['usuario_id'];
$tipoUsuarioSessao = $_SESSION['nivel']; // "Admin" ou "Funcionario"
$cpfUsuario = '';
$nomeFuncionario = '';

try {
  if ($tipoUsuarioSessao === 'Admin') {
    // Buscar na tabela de Admins
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
  } else {
    // Buscar na tabela de Funcionários
    $stmt = $pdo->prepare("SELECT usuario, nivel, cpf FROM funcionarios_acesso WHERE id = :id");
  }

  $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
  $stmt->execute();
  $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($usuario) {
    $nomeUsuario = $usuario['usuario'];
    $tipoUsuario = ucfirst($usuario['nivel']);
    if (isset($usuario['cpf'])) {
      $cpfUsuario = $usuario['cpf'];
    }
  } else {
    echo "<script>alert('Usuário não encontrado.'); window.location.href = './index.php?id=$idSelecionado';</script>";
    exit;
  }
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar nome e tipo do usuário: " . $e->getMessage() . "'); history.back();</script>";
  exit;
}

// ✅ Função para buscar o nome do funcionário pelo CPF
function obterNomeFuncionario($pdo, $cpf)
{
  try {
    $stmt = $pdo->prepare("SELECT nome FROM funcionarios WHERE cpf = :cpf");
    $stmt->bindParam(':cpf', $cpf, PDO::PARAM_STR);
    $stmt->execute();
    $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($funcionario && !empty($funcionario['nome'])) {
      return $funcionario['nome'];
    } else {
      return 'Funcionário não identificado';
    }
  } catch (PDOException $e) {
    return 'Erro ao buscar nome';
  }
}

// ✅ Aplica a função se for funcionário
if (!empty($cpfUsuario)) {
  $nomeFuncionario = obterNomeFuncionario($pdo, $cpfUsuario);
}

// ✅ Valida o tipo de empresa e o acesso permitido
if (str_starts_with($idSelecionado, 'principal_')) {
  if ($_SESSION['tipo_empresa'] !== 'principal' || $_SESSION['empresa_id'] != 1) {
    echo "<script>
            alert('Acesso negado!');
            window.location.href = '../index.php?id=$idSelecionado';
        </script>";
    exit;
  }
  $id = 1;
} elseif (str_starts_with($idSelecionado, 'filial_')) {
  $idFilial = (int) str_replace('filial_', '', $idSelecionado);
  if ($_SESSION['tipo_empresa'] !== 'filial' || $_SESSION['empresa_id'] != $idFilial) {
    echo "<script>
            alert('Acesso negado!');
            window.location.href = '../index.php?id=$idSelecionado';
        </script>";
    exit;
  }
  $id = $idFilial;
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
  echo "<script>alert('Erro ao carregar ícone da empresa: " . addslashes($e->getMessage()) . "');</script>";
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
        <!-- Fechamento de Caixa -->
        <div class="card">
          <div class="card-body">
            <!-- Logo -->
            <div class="app-brand justify-content-center">
              <a href="index.php" class="app-brand-link gap-2">
                <span class="app-brand-text demo text-body fw-bolder">Fechamento de Caixa</span>
              </a>
            </div>
            <!-- /Logo -->

            <?php
            // Supondo que $idSelecionado e $nomeUsuario já estejam definidos anteriormente no script
            // Defina $idFuncionario conforme sua lógica (exemplo: da sessão)
            // $idFuncionario = $_SESSION['idFuncionario'] ?? 0;
            
            if (str_starts_with($idSelecionado, 'principal_')) {
              $id = 1;
            } elseif (str_starts_with($idSelecionado, 'filial_')) {
              $id = (int) str_replace('filial_', '', $idSelecionado);
            } else {
              echo "<script>alert('Empresa não identificada!'); history.back();</script>";
              exit;
            }

            $saldoFinal = 0;
            $responsavel = ucwords($nomeUsuario);

            try {
              // Busca valor_liquido do caixa aberto mais recente do responsável na empresa
              $stmtSaldo = $pdo->prepare("
                                                  SELECT valor_liquido
                                                  FROM aberturas 
                                                  WHERE responsavel = :responsavel 
                                                    AND status = 'aberto'
                                                    AND empresa_id = :empresa_id
                                                  ORDER BY id DESC 
                                                  LIMIT 1
                                              ");
              $stmtSaldo->execute([
                'responsavel' => $responsavel,
                'empresa_id' => $idSelecionado // ou use $id se quiser enviar o id numérico
              ]);
              $saldo = $stmtSaldo->fetch(PDO::FETCH_ASSOC);

              if ($saldo && isset($saldo['valor_liquido'])) {
                // Formata para exibir com vírgula e ponto para usuário
                $valorTotal = (float) $saldo['valor_liquido'];
                $saldoFormatado = number_format($valorTotal, 2, ',', '.');
                // Valor limpo para enviar no input hidden (usar ponto decimal)
                $saldoFinal = number_format($valorTotal, 2, '.', '');
              } else {
                $saldoFormatado = "Sem Saldo";
                $saldoFinal = null;
              }
            } catch (PDOException $e) {
              echo "Erro ao buscar saldo: " . $e->getMessage();
              exit;
            }
            ?>

            <!-- Formulário de Fechamento -->
            <form action="../../assets/php/frentedeloja/fecharCaixaSubmit.php?id=<?= urlencode($idSelecionado); ?>"
              method="POST">
              <input type="hidden" name="empresa_identificador" value="<?= htmlspecialchars($idSelecionado) ?>">
              <input type="hidden" name="funcionario_id" value="<?= htmlspecialchars($idFuncionario ?? '') ?>">
              <input type="hidden" name="responsavel" value="<?= htmlspecialchars($responsavel) ?>">
              <input type="hidden" name="cpf_funcionario" value="<?= htmlspecialchars($cpfUsuario ?? '') ?>">
              <input type="hidden" name="data_registro" id="data_registro">
              <input type="hidden" name="saldo_final" value="<?= htmlspecialchars($saldoFinal) ?>">

              <div class="mb-3">
                <label for="saldo_final_display" class="form-label">Saldo Final</label>
                <input type="text" class="form-control" id="saldo_final_display"
                  value="<?= htmlspecialchars($saldoFormatado) ?>" readonly>
              </div>

              <?php if ($saldoFinal === null): ?>
                <div class="alert alert-warning">Não há caixa aberto para esse responsável.</div>
              <?php else: ?>
                <div class="mb-3">
                  <button class="btn btn-primary d-grid w-100" type="submit">Fechar Caixa</button>
                </div>
              <?php endif; ?>
            </form>

            <script>
              document.addEventListener('DOMContentLoaded', function () {
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
                  form.addEventListener('submit', function () {
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
        <!-- /Fechamento de Caixa -->
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
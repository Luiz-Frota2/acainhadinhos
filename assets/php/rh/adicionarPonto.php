<?php

require_once '../conexao.php';

// Verifica se a requisição é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<script>
                    alert('Requisição inválida!');
                    history.back();
                </script>";
    exit;
}

// Valida os dados recebidos
$requiredFields = ['cpf', 'data', 'entrada', 'saida_intervalo', 'retorno_intervalo', 'saida_final', 'id_selecionado'];
foreach ($requiredFields as $field) {
  if (empty($_POST[$field])) {
    echo "<script>
            alert('Todos os campos são obrigatórios!');
            history.back();
        </script>";
    exit;
  }
}

$cpf = $_POST['cpf'];
$data = $_POST['data'];
$entrada = $_POST['entrada'];
$saida_intervalo = $_POST['saida_intervalo'];
$retorno_intervalo = $_POST['retorno_intervalo'];
$saida_final = $_POST['saida_final'];
$idSelecionado = $_POST['id_selecionado'];

try {
  // Verificar se já existe registro para esse CPF na data especificada
  $stmt = $pdo->prepare("SELECT id FROM pontos WHERE cpf = :cpf AND data = :data AND empresa_id = :empresa_id");
  $stmt->bindParam(':cpf', $cpf, PDO::PARAM_STR);
  $stmt->bindParam(':data', $data, PDO::PARAM_STR);
  $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
  $stmt->execute();
  
  if ($stmt->rowCount() > 0) {
    echo "<script>
            alert('Já existe um registro de ponto para este funcionário na data selecionada!');
            history.back();
        </script>";
    exit;
  }
  
  // Buscar nome do funcionário
  $stmt = $pdo->prepare("SELECT nome FROM funcionarios WHERE cpf = :cpf AND empresa_id = :empresa_id");
  $stmt->bindParam(':cpf', $cpf, PDO::PARAM_STR);
  $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
  $stmt->execute();
  $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);
  
  if (!$funcionario) {
    echo "<script>
            alert('Funcionário não encontrado!');
            history.back();
        </script>";
    exit;
  }
  
  // Inserir o novo registro de ponto
  $stmt = $pdo->prepare("INSERT INTO pontos 
                        (cpf, nome, data, entrada, saida_intervalo, retorno_intervalo, saida_final, empresa_id)
                        VALUES (:cpf, :nome, :data, :entrada, :saida_intervalo, :retorno_intervalo, :saida_final, :empresa_id)");
  
  $stmt->bindParam(':cpf', $cpf, PDO::PARAM_STR);
  $stmt->bindParam(':nome', $funcionario['nome'], PDO::PARAM_STR);
  $stmt->bindParam(':data', $data, PDO::PARAM_STR);
  $stmt->bindParam(':entrada', $entrada, PDO::PARAM_STR);
  $stmt->bindParam(':saida_intervalo', $saida_intervalo, PDO::PARAM_STR);
  $stmt->bindParam(':retorno_intervalo', $retorno_intervalo, PDO::PARAM_STR);
  $stmt->bindParam(':saida_final', $saida_final, PDO::PARAM_STR);
  $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
  
  if ($stmt->execute()) {
    echo "<script>
            alert('Ponto registrado com sucesso!');
            window.location.href = '../../../erp/rh/adicionarPonto.php?id=" . urlencode($idSelecionado) . "';
        </script>";
  } else {
    echo "<script>
            alert('Erro ao registrar ponto!');
            history.back();
        </script>";
  }
} catch (PDOException $e) {
  echo "<script>
          alert('Erro no banco de dados: " . addslashes($e->getMessage()) . "');
          history.back();
      </script>";
}
?>
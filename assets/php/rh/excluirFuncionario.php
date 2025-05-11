<?php

require '../conexao.php';

// ✅ Recupera o id do funcionário e o id da empresa/filial
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$idSelecionado = filter_input(INPUT_GET, 'idSelecionado', FILTER_SANITIZE_STRING);  // Para garantir que o idSelecionado seja seguro

if (!$id || !$idSelecionado) {
    echo "<script>
            alert('ID ou ID da empresa inválidos.');
            history.back();
          </script>";
    exit;
}

try {
    // Deletar o funcionário
    $sql = "DELETE FROM funcionarios WHERE id = :id AND empresa_id = :empresa_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->bindParam(":empresa_id", $idSelecionado, PDO::PARAM_STR);  // Passa o idSelecionado como filtro para garantir que exclua da empresa correta

    if ($stmt->execute()) {
        echo "<script>
                alert('Funcionário excluído com sucesso!');
                window.location.href = '../../../erp/rh/funcionarioAdicionados.php?id=$idSelecionado'; // Redireciona para a página com o idSelecionado
              </script>";
        exit;
    } else {
        echo "<script>
                alert('Erro ao excluir funcionário.');
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

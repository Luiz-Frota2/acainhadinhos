<?php

require '../conexao.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    echo "<script>
            alert('ID inválido.');
            history.back();
          </script>";
    exit;
}

try {
    // Deletar o setor
    $sql = "DELETE FROM produtos_estoque WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        echo "<script>
                window.location.href = '../../../erp/estoque/produtosAdicionados.php';
              </script>";
        exit;
    } else {
        echo "<script>
                alert('Erro ao excluir produtos.');
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

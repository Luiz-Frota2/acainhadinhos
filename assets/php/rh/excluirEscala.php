<?php

require '../conexao.php';

// ✅ Recupera o identificador da escala a ser excluída e o idSelecionado da URL
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$idSelecionado = filter_input(INPUT_GET, 'idSelecionado', FILTER_SANITIZE_STRING);

if (!$id) {
    echo "<script>
            alert('ID inválido.');
            history.back();
          </script>";
    exit;
}

try {
    // Deletar a escala
    $sql = "DELETE FROM escalas WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        // Redireciona para a página com a mensagem de sucesso e passando o idSelecionado
        echo "<script>
                alert('Escala excluída com sucesso!');
                window.location.href = '../../../erp/rh/escalaAdicionadas.php?id=$idSelecionado';
              </script>";
        exit;
    } else {
        echo "<script>
                alert('Erro ao excluir escala.');
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

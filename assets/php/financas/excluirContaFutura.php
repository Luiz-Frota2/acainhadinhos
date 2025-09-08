<?php

require '../conexao.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$empresa_id = filter_input(INPUT_GET, 'empresa_id', FILTER_SANITIZE_STRING);

if (!$id) {
    echo "<script>
            alert('ID inválido.');
            history.back();
          </script>";
    exit;
}

try {
    // Deletar o conta
    $sql = "DELETE FROM contas WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        echo '<script>
                window.location.href = "../../../erp/financas/contasFuturos.php?id=' . urlencode($empresa_id) . '";
              </script>';
        exit;
    } else {
        echo "<script>
                alert('Erro ao excluir contas.');
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

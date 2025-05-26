<?php

require '../conexao.php';

// ✅ Recupera o ID do setor e o idSelecionado da URL
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$idSelecionado = $_GET['idSelecionado'] ?? '';

if (!$id || empty($idSelecionado)) {
    echo "<script>
            alert('Dados inválidos.');
            history.back();
          </script>";
    exit;
}

try {
    // Deletar o setor
    $sql = "DELETE FROM setores WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        echo "<script>
                alert('Setor excluído com sucesso!');
                window.location.href = '../../../erp/rh/setoresAdicionados.php?id=$idSelecionado';
              </script>";
        exit;
    } else {
        echo "<script>
                alert('Erro ao excluir setor.');
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

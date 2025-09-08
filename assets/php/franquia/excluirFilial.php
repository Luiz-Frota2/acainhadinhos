<?php
require '../conexao.php'; // Ajuste o caminho se necessário

$idSelecionado = isset($_GET['idSelecionado']) ? $_GET['idSelecionado'] : '';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_filial = $_GET['id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM filiais WHERE id_filial = :id");
        $stmt->bindParam(':id', $id_filial, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            echo "<script>
                alert('Filial excluída com sucesso!');
                window.location.href='../../../erp/filial/filialAdicionada.php?idSelecionado=" . urlencode($idSelecionado) . "';
            </script>";
        } else {
            echo "<script>
                alert('Nenhuma filial foi excluída. Verifique o ID.');
                history.back();
            </script>";
        }

    } catch (PDOException $e) {
        echo "<script>
            alert('Erro ao excluir filial: " . $e->getMessage() . "');
            history.back();
        </script>";
    }

} else {
    echo "<script>
        alert('ID inválido para exclusão.');
        history.back();
    </script>";
}
?>
<?php
// Incluindo o arquivo de conexão PDO
include '../conexao.php';

// Verifique se os parâmetros 'id' e 'idSelecionado' foram passados pela URL
if (isset($_GET['id']) && isset($_GET['idSelecionado'])) {
    // Obtenha os parâmetros da URL
    $id = intval($_GET['id']); // Usa 'intval' para garantir que o valor é um inteiro
    $idSelecionado = $_GET['idSelecionado']; // Obtém o idSelecionado

    // Verifique se o ID é válido
    if ($id > 0) {
        try {
            // Preparando a consulta para excluir o opcional com base no id e idSelecionado
            $sql = "DELETE FROM opcionais WHERE id = :id AND id_selecionado = :idSelecionado";

            // Preparando a declaração
            $stmt = $pdo->prepare($sql);

            // Vinculando os parâmetros :id e :idSelecionado
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':idSelecionado', $idSelecionado, PDO::PARAM_STR); // Supondo que idSelecionado seja string (ex: "principal_1")

            // Executando a consulta
            if ($stmt->execute()) {
                // Sucesso: Redireciona com uma mensagem de sucesso, incluindo o idSelecionado
                echo "<script>alert('Opcional excluído com sucesso!'); window.location.href = '../../../erp/delivery/produtoAdicionados.php?id=" . urlencode($idSelecionado) . "';</script>";
                exit();
            } else {
                // Caso ocorra um erro ao executar a consulta
                echo "<script>alert('Erro ao excluir o opcional.'); history.back();</script>";
            }
        } catch (PDOException $e) {
            // Caso ocorra uma exceção, exibe a mensagem de erro em um alert
            echo "<script>alert('Erro: " . $e->getMessage() . "'); history.back();</script>";
        }
    } else {
        // Caso o ID não seja válido
        echo "<script>alert('ID inválido.'); history.back();</script>";
    }
} else {
    // Caso os parâmetros 'id' ou 'idSelecionado' não tenham sido passados pela URL
    echo "<script>alert('ID ou ID Selecionado não fornecido.'); history.back();</script>";
}
?>

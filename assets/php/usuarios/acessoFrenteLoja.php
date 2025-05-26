<?php
require '../conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);

    try {
        // Primeiro, pega o status atual
        $stmt = $pdo->prepare("SELECT autorizado FROM funcionarios_acesso WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($resultado) {
            // Inverte o valor
            $novoStatus = ($resultado['autorizado'] === 'sim') ? 'nao' : 'sim';

            // Atualiza no banco
            $stmtUpdate = $pdo->prepare("UPDATE funcionarios_acesso SET autorizado = :novoStatus WHERE id = :id");
            $stmtUpdate->bindParam(':novoStatus', $novoStatus, PDO::PARAM_STR);
            $stmtUpdate->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtUpdate->execute();
        }

        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();

    } catch (PDOException $e) {
        echo "Erro ao atualizar status: " . htmlspecialchars($e->getMessage());
    }
}
?>


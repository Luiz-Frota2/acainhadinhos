<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../conexao.php'; // ajuste o caminho se necessário

// Valida o ID recebido via GET
$id = $_GET['id'] ?? null;
$idSelecionado = $_GET['idSelecionado'] ?? '';

if (!$id || !is_numeric($id)) {
    echo "<script>alert('ID inválido.'); history.back();</script>";
    exit;
}

try {
    // Verifica se a franquia existe
    $stmt = $pdo->prepare("SELECT * FROM unidades WHERE id = :id AND tipo = 'Franquia'");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $franquia = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$franquia) {
        echo "<script>alert('Franquia não encontrada.'); history.back();</script>";
        exit;
    }

    // Realiza a exclusão
    $deleteStmt = $pdo->prepare("DELETE FROM unidades WHERE id = :id AND tipo = 'Franquia'");
    $deleteStmt->bindParam(':id', $id, PDO::PARAM_INT);
    $deleteStmt->execute();

    echo "<script>alert('Franquia excluída com sucesso.'); window.location.href='../../../erp/franquia/franquiaAdicionada.php?id=" . urlencode($idSelecionado) . "';</script>";
    exit;
} catch (PDOException $e) {
    echo "<script>alert('Erro ao excluir: " . $e->getMessage() . "'); history.back();</script>";
    exit;
}
?>

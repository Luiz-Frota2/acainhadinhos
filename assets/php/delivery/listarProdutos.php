<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../conexaoLocal.php';// Ajuste o caminho conforme necessário

try {
    $sql = "SELECT * FROM adicionarProdutos";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$produtos) {
        echo json_encode(["error" => "Nenhum produto encontrado."]);
        exit;
    }

    echo json_encode($produtos);
} catch (PDOException $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>

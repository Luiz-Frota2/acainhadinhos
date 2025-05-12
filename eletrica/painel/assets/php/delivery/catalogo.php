<?php
// Conectar ao banco de dados
require_once 'conexao.php';

// Definindo um tipo de resposta JSON
header('Content-Type: application/json');

// Definir o que será retornado: categorias e produtos
$response = [];

try {
    // Buscar categorias
    $stmt = $pdo->query("SELECT * FROM adicionarcategoria");
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar produtos relacionados às categorias
    $stmtProdutos = $pdo->query("SELECT * FROM produtos");
    $produtos = $stmtProdutos->fetchAll(PDO::FETCH_ASSOC);

    // Preparando os dados para retornar
    $response['categorias'] = $categorias;
    $response['produtos'] = $produtos;

    // Retornando dados como JSON
    echo json_encode($response);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Erro ao acessar o banco de dados: ' . $e->getMessage()]);
}
?>

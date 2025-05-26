<?php

require_once '../conexao.php';

if (isset($_GET['id'])) {
    $id_produto = intval($_GET['id']); // Garante que o ID seja um número inteiro
    $idSelecionado = $_GET['empresa_id'] ?? ''; // Recupera o idSelecionado que foi passado via URL

    try {
        $pdo->beginTransaction();

        // 1️⃣ Buscar a imagem do produto antes de excluir
        $sql = "SELECT imagem_produto FROM adicionarProdutos WHERE id_produto = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_produto]);
        $produto = $stmt->fetch(PDO::FETCH_ASSOC);

        // 2️⃣ Se houver uma imagem, remover do diretório
        if ($produto && !empty($produto['imagem_produto'])) {
            $caminhoImagem = "../../img/uploads/" . $produto['imagem_produto'];
            if (file_exists($caminhoImagem)) {
                unlink($caminhoImagem); // Remove a imagem
            }
        }

        // 3️⃣ Excluir o produto do banco de dados
        $sql = "DELETE FROM adicionarProdutos WHERE id_produto = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_produto]);

        $pdo->commit();
        echo "<script>alert('Produto excluído com sucesso!'); window.location.href='../../../erp/delivery/produtoAdicionados.php?id=$idSelecionado';</script>";
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('Erro ao excluir produto: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
        exit();
    }

} else {
    echo "<script>alert('ID do produto não fornecido!'); window.history.back();</script>";
    exit();
}

?>

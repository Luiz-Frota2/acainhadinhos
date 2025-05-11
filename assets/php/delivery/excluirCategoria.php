<?php

require_once '../conexao.php';

if (isset($_GET['id']) && isset($_GET['idSelecionado'])) {
    $id_categoria = intval($_GET['id']); // Garante que seja um número inteiro
    $idSelecionado = $_GET['idSelecionado']; // Pode ser 'principal_1' ou 'filial_2'

    try {
        $pdo->beginTransaction(); // Inicia a transação

        // 1️⃣ BUSCAR IMAGENS DOS PRODUTOS DA CATEGORIA
        $sql = "SELECT imagem_produto FROM adicionarProdutos WHERE id_categoria = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_categoria]);
        $imagens = $stmt->fetchAll(PDO::FETCH_COLUMN); // Pega todas as imagens

        $diretorioImagens = '../../img/uploads/'; // Diretório das imagens

        // Excluir as imagens do diretório
        foreach ($imagens as $imagem) {
            $caminhoImagem = $diretorioImagens . $imagem;
            if (!empty($imagem) && file_exists($caminhoImagem)) {
                unlink($caminhoImagem); // Remove o arquivo da pasta
            }
        }

        // 2️⃣ EXCLUIR OS PRODUTOS DESSA CATEGORIA
        $sql = "DELETE FROM adicionarProdutos WHERE id_categoria = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_categoria]);

        // 3️⃣ EXCLUIR A CATEGORIA
        $sql = "DELETE FROM adicionarCategoria WHERE id_categoria = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_categoria]);

        $pdo->commit(); // Confirma a exclusão

        // Exibir mensagem e redirecionar com o idSelecionado na URL
        echo "<script>
                alert('Categoria e produtos excluídos com sucesso!');
                window.location.href='../../../erp/delivery/produtoAdicionados.php?id=" . urlencode($idSelecionado) . "';
              </script>";
        exit();
    } catch (Exception $e) {
        $pdo->rollBack(); // Desfaz a transação se algo der errado
        echo "<script>alert('Erro ao excluir categoria: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
        exit();
    }
} else {
    echo "<script>alert('ID da categoria ou da empresa não fornecido!'); window.history.back();</script>";
    exit();
}

?>

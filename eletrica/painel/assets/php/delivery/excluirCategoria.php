<?php

require_once '../conexao.php';

if (isset($_GET['id'])) {
    $id_categoria = intval($_GET['id']); // Garante que seja um número inteiro

    try {
        $pdo->beginTransaction(); // Inicia a transação

        // 1️⃣ BUSCAR IMAGENS DOS PRODUTOS DA CATEGORIA
        $sql = "SELECT imagem_produto FROM adicionarprodutos WHERE id_categoria = ?";
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
        $sql = "DELETE FROM adicionarprodutos WHERE id_categoria = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_categoria]);

        // 3️⃣ EXCLUIR A CATEGORIA
        $sql = "DELETE FROM adicionarcategoria WHERE id_categoria = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_categoria]);

        $pdo->commit(); // Confirma a exclusão

        // Exibir mensagem e redirecionar
        echo "<script>
                alert('Categoria e produtos excluídos com sucesso!');
                window.location.href='../../../produtoAdicionados.php';
              </script>";
        exit();
    } catch (Exception $e) {
        $pdo->rollBack(); // Desfaz a transação se algo der errado
        echo "<script>alert('Erro ao excluir categoria: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
        exit();
    }
} else {
    echo "<script>alert('ID da categoria não fornecido!'); window.history.back();</script>";
    exit();
}

?>


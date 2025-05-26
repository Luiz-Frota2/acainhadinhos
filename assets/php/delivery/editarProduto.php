<?php

require_once '../conexao.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_produto = intval($_POST['id_produto']);
    $idSelecionado = $_POST['idSelecionado'] ?? ''; // Recupera o idSelecionado
    $nome_produto = trim($_POST['nomeProduto']);
    $quantidade_produto = intval($_POST['quantidadeProduto']);
    $preco_produto = floatval(str_replace(',', '.', $_POST['precoProduto']));
    $descricao_produto = trim($_POST['descricaoProduto']);

    try {
        $pdo->beginTransaction();

        // Recupera a empresa ou filial do produto atual
        $sqlCheck = "SELECT nome_produto, id_empresa FROM adicionarProdutos WHERE id_produto = ?";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute([$id_produto]);
        $produtoAtual = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        // Se o nome do produto foi alterado, verifica se já existe um produto com o novo nome
        if ($nome_produto != $produtoAtual['nome_produto']) {
            // Verifica se o nome já existe, mas apenas dentro da mesma empresa ou filial
            $sqlCheckDuplicate = "SELECT COUNT(*) FROM adicionarProdutos WHERE nome_produto = ? AND id_produto != ? AND id_empresa = ?";
            $stmtCheckDuplicate = $pdo->prepare($sqlCheckDuplicate);
            $stmtCheckDuplicate->execute([$nome_produto, $id_produto, $produtoAtual['id_empresa']]);
            $existe = $stmtCheckDuplicate->fetchColumn();

            if ($existe > 0) {
                echo "<script>alert('Erro: Já existe um produto com esse nome nesta empresa ou filial!'); window.history.back();</script>";
                exit();
            }
        }

        // Busca a imagem atual para possível exclusão
        $sqlImagem = "SELECT imagem_produto FROM adicionarProdutos WHERE id_produto = ?";
        $stmtImagem = $pdo->prepare($sqlImagem);
        $stmtImagem->execute([$id_produto]);
        $produtoAtual = $stmtImagem->fetch(PDO::FETCH_ASSOC);
        $imagemAntiga = $produtoAtual['imagem_produto'];

        // Gerenciar imagem
        if (!empty($_FILES['imagemProduto']['name'])) {
            $imagemNome = uniqid() . '-' . $_FILES['imagemProduto']['name'];
            $caminhoImagem = "../../img/uploads/" . $imagemNome;

            // Move a nova imagem
            if (move_uploaded_file($_FILES['imagemProduto']['tmp_name'], $caminhoImagem)) {
                // Se houver imagem antiga, remove
                if (!empty($imagemAntiga) && file_exists("../../img/uploads/" . $imagemAntiga)) {
                    unlink("../../img/uploads/" . $imagemAntiga);
                }
            } else {
                echo "<script>alert('Erro ao fazer upload da imagem!'); window.history.back();</script>";
                exit();
            }
        } else {
            $imagemNome = $imagemAntiga; // Mantém a imagem antiga
        }

        // Atualiza os dados do produto
        $sqlUpdate = "UPDATE adicionarProdutos 
                      SET nome_produto = ?, quantidade_produto = ?, preco_produto = ?, imagem_produto = ?, descricao_produto = ?
                      WHERE id_produto = ?";
        $stmtUpdate = $pdo->prepare($sqlUpdate);
        $stmtUpdate->execute([$nome_produto, $quantidade_produto, $preco_produto, $imagemNome, $descricao_produto, $id_produto]);

        $pdo->commit();
        echo "<script>alert('Produto atualizado com sucesso!'); window.location.href='../../../erp/delivery/produtoAdicionados.php?id={$idSelecionado}';</script>";
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('Erro ao atualizar produto: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
        exit();
    }
} else {
    echo "<script>alert('Requisição inválida!'); window.history.back();</script>";
    exit();
}

?>

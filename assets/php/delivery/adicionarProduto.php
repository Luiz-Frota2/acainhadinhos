<?php
// Inclui o arquivo de conexão com o banco de dados
require_once '../conexao.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Receber dados do formulário
    $nomeProduto = $_POST['nomeProduto'];
    $quantidadeProduto = $_POST['quantidadeProduto'];
    $precoProduto = $_POST['precoProduto'];
    $descricaoProduto = $_POST['descricaoProduto'];
    $idCategoria = $_POST['id_categoria'];
    $idSelecionado = $_POST['id_empresa'] ?? '';

    // Interpretar o ID da empresa
    if (str_starts_with($idSelecionado, 'principal_')) {
        $idEmpresa = 1;
    } elseif (str_starts_with($idSelecionado, 'filial_')) {
        $idEmpresa = (int) str_replace('filial_', '', $idSelecionado);
    } else {
        echo "<script>alert('Empresa não identificada!'); history.back();</script>";
        exit;
    }

    try {
        // Verificar se o produto já existe pelo nome
        $sqlVerificarProduto = "SELECT * FROM adicionarProdutos WHERE nome_produto = :nome_produto AND id_empresa = :id_empresa";
        $stmtVerificar = $pdo->prepare($sqlVerificarProduto);
        $stmtVerificar->bindParam(':nome_produto', $nomeProduto, PDO::PARAM_STR);
        $stmtVerificar->bindParam(':id_empresa', $idEmpresa, PDO::PARAM_INT);
        $stmtVerificar->execute();

        if ($stmtVerificar->rowCount() > 0) {
            echo "<script>alert('Já existe um produto cadastrado com esse nome.'); history.back();</script>";
        } else {
            // Lógica para o upload da imagem
            $imagemProduto = '';
            if (isset($_FILES['imagemProduto']) && $_FILES['imagemProduto']['error'] === UPLOAD_ERR_OK) {
                $diretorioDestino = '../../img/uploads/';
                $nomeImagem = basename($_FILES['imagemProduto']['name']);
                $caminhoImagem = $diretorioDestino . $nomeImagem;
                $extensaoImagem = strtolower(pathinfo($caminhoImagem, PATHINFO_EXTENSION));
                $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'gif'];

                if (in_array($extensaoImagem, $extensoesPermitidas)) {
                    if (move_uploaded_file($_FILES['imagemProduto']['tmp_name'], $caminhoImagem)) {
                        $imagemProduto = $nomeImagem;
                    } else {
                        echo "<script>alert('Erro ao fazer o upload da imagem.'); history.back();</script>";
                        exit;
                    }
                } else {
                    echo "<script>alert('Somente imagens JPG, JPEG, PNG ou GIF são permitidas.'); history.back();</script>";
                    exit;
                }
            }

            // Inserir o produto
            $sqlInserirProduto = "INSERT INTO adicionarProdutos (
                nome_produto, quantidade_produto, preco_produto, imagem_produto, descricao_produto, id_categoria, id_empresa
            ) VALUES (
                :nome_produto, :quantidade_produto, :preco_produto, :imagem_produto, :descricao_produto, :id_categoria, :id_empresa
            )";

            $stmtInserir = $pdo->prepare($sqlInserirProduto);
            $stmtInserir->bindParam(':nome_produto', $nomeProduto, PDO::PARAM_STR);
            $stmtInserir->bindParam(':quantidade_produto', $quantidadeProduto, PDO::PARAM_INT);
            $stmtInserir->bindParam(':preco_produto', $precoProduto, PDO::PARAM_STR);
            $stmtInserir->bindParam(':imagem_produto', $imagemProduto, PDO::PARAM_STR);
            $stmtInserir->bindParam(':descricao_produto', $descricaoProduto, PDO::PARAM_STR);
            $stmtInserir->bindParam(':id_categoria', $idCategoria, PDO::PARAM_INT);
            $stmtInserir->bindParam(':id_empresa', $idEmpresa, PDO::PARAM_INT);

            if ($stmtInserir->execute()) {
                echo '<script>alert("Produto adicionado com sucesso!"); window.location.href = "../../../erp/delivery/produtoAdicionados.php?id=' . $idSelecionado . '";</script>';
            } else {
                echo "<script>alert('Erro ao adicionar produto.'); history.back();</script>";
            }
        }

    } catch (PDOException $e) {
        echo "<script>alert('Erro: " . $e->getMessage() . "'); history.back();</script>";
    }
}
?>

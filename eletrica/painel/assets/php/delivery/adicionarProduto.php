<?php
// Inclui o arquivo de conexão com o banco de dados
require_once '../conexao.php';  // Ajuste o caminho conforme necessário

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Receber dados do formulário
    $nomeProduto = $_POST['nomeProduto'];
    $quantidadeProduto = $_POST['quantidadeProduto'];
    $precoProduto = $_POST['precoProduto'];
    $descricaoProduto = $_POST['descricaoProduto'];
    $idCategoria = $_POST['id_categoria'];

    // Verificar se o produto já existe pelo nome
    try {
        $sqlVerificarProduto = "SELECT * FROM adicionarprodutos WHERE nome_produto = :nome_produto";
        $stmtVerificar = $pdo->prepare($sqlVerificarProduto);
        $stmtVerificar->bindParam(':nome_produto', $nomeProduto, PDO::PARAM_STR);
        $stmtVerificar->execute();

        if ($stmtVerificar->rowCount() > 0) {
            // Retorna uma mensagem de erro em script
            echo "<script>alert('Já existe um produto cadastrado com esse nome.'); history.back();</script>";
        } else {
            // Lógica para o upload da imagem
            $imagemProduto = '';
            if (isset($_FILES['imagemProduto']) && $_FILES['imagemProduto']['error'] === UPLOAD_ERR_OK) {
                $diretorioDestino = '../../img/uploads/';
                $nomeImagem = basename($_FILES['imagemProduto']['name']);
                $caminhoImagem = $diretorioDestino . $nomeImagem;
                $extensaoImagem = strtolower(pathinfo($caminhoImagem, PATHINFO_EXTENSION));
                $extensoesPermitidas = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'jfif');

                // Verificar se a extensão da imagem é permitida
                if (in_array($extensaoImagem, $extensoesPermitidas)) {
                    // Mover a imagem para o diretório de uploads
                    if (move_uploaded_file($_FILES['imagemProduto']['tmp_name'], $caminhoImagem)) {
                        $imagemProduto = $nomeImagem; // Salvar o nome da imagem no banco
                    } else {
                        echo "<script>alert('Erro ao fazer o upload da imagem.'); history.back();</script>";
                        exit;
                    }
                } else {
                    echo "<script>alert('Somente imagens com extensões .jpg, .jpeg, .png ou .gif são permitidas.'); history.back();</script>";
                    exit;
                }
            }

            // Preparar a query de inserção do produto
            $sqlInserirProduto = "INSERT INTO adicionarprodutos (nome_produto, quantidade_produto, preco_produto, imagem_produto, descricao_produto, id_categoria) 
                                  VALUES (:nome_produto, :quantidade_produto, :preco_produto, :imagem_produto, :descricao_produto, :id_categoria)";

            // Preparar a query com PDO
            $stmtInserir = $pdo->prepare($sqlInserirProduto);
            $stmtInserir->bindParam(':nome_produto', $nomeProduto, PDO::PARAM_STR);
            $stmtInserir->bindParam(':quantidade_produto', $quantidadeProduto, PDO::PARAM_INT);
            $stmtInserir->bindParam(':preco_produto', $precoProduto, PDO::PARAM_STR); // Usando PARAM_STR por conta do DECIMAL
            $stmtInserir->bindParam(':imagem_produto', $imagemProduto, PDO::PARAM_STR);
            $stmtInserir->bindParam(':descricao_produto', $descricaoProduto, PDO::PARAM_STR);
            $stmtInserir->bindParam(':id_categoria', $idCategoria, PDO::PARAM_INT);

            // Executar a query
            if ($stmtInserir->execute()) {
                // Retorna uma mensagem de sucesso em script
                echo '<script>alert("Produto adicionado com sucesso!"); window.location.href = "../../../produtoAdicionados.php"</script>';
            } else {
                echo "<script>alert('Erro ao adicionar produto.'); history.back();</script>";
            }
        }
    } catch (PDOException $e) {
        // Captura erro de PDO e exibe mensagem
        echo "<script>alert('Erro ao verificar ou inserir o produto: " . $e->getMessage() . "');</script>";
    }
}
?>

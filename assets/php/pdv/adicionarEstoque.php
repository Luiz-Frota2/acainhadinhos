<?php
// Inclui o arquivo de conexão
require '../conexao.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Pega o ID da empresa enviado pelo formulário
    $empresa_id = $_POST['idSelecionado'] ?? '';

    // Recebe os demais dados do formulário
    $codigo = trim($_POST["codigo_produto"]);
    $nome = trim($_POST["nome_produto"]);
    $categoria = trim($_POST["categoria_produto"]);
    $quantidade = trim($_POST["quantidade_produto"]);
    $preco = trim($_POST["preco_produto"]);
    $statuss = trim($_POST["status_produto"]);

    try {
        // Atualiza a query para incluir empresa_id
        $sql = "INSERT INTO estoque (
                    empresa_id, codigo_produto, nome_produto, categoria_produto,
                    quantidade_produto, preco_produto, status_produto
                ) VALUES (
                    :empresa_id, :codigo_produto, :nome_produto, :categoria_produto,
                    :quantidade_produto, :preco_produto, :status_produto
                )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":empresa_id", $empresa_id);
        $stmt->bindParam(":codigo_produto", $codigo);
        $stmt->bindParam(":nome_produto", $nome);
        $stmt->bindParam(":categoria_produto", $categoria);
        $stmt->bindParam(":quantidade_produto", $quantidade);
        $stmt->bindParam(":preco_produto", $preco);
        $stmt->bindParam(":status_produto", $statuss);

        // Executar e exibir mensagem de sucesso
        if ($stmt->execute()) {
            echo "<script>
                    alert('Produto adicionado com sucesso');
                    window.location.href = '../../../erp/pdv/produtosAdicionados.php?id=" . urlencode($empresa_id) . "';
                  </script>";
            exit();
        } else {
            echo "<script>
                    alert('Erro ao cadastrar produto.');
                    history.back();
                  </script>";
        }
    } catch (PDOException $e) {
        echo "<script>
                alert('Erro no banco de dados: " . addslashes($e->getMessage()) . "');
                history.back();
              </script>";
    }
}
?>

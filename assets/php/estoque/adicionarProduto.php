<?php
// Inclui o arquivo de conexão
require '../conexao.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome_produto = trim($_POST["nome_produto"]);
    $fornecedor_produto = trim($_POST["fornecedor_produto"]);
    $quantidade_produto = trim($_POST["quantidade_produto"]);
    $status_produto = trim($_POST["status_produto"]);

    try {
        // Preparar a query SQL
        $sql = "INSERT INTO produtos (nome_produto, fornecedor_produto, quantidade_produto, status_produto) VALUES (:nome_produto, :fornecedor_produto, :quantidade_produto, :status_produto)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":nome_produto", $nome_produto, PDO::PARAM_STR);
        $stmt->bindParam(":fornecedor_produto", $fornecedor_produto, PDO::PARAM_STR);
        $stmt->bindParam(":quantidade_produto", $quantidade_produto, PDO::PARAM_STR);
        $stmt->bindParam(":status_produto", $status_produto, PDO::PARAM_STR);

        // Executar e exibir mensagem de sucesso
        if ($stmt->execute()) {
            echo "<script>alert('Dados adicionados com sucesso!');
                    window.location.href = '../../../erp/estoque/produtoAdicionados.php';
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


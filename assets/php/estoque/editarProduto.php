<?php
// Inclui o arquivo de conexão
require '../conexao.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST["id"] ?? null;
    $nome_produto = trim($_POST["nome_produto"] ?? '');
    $fornecedor_produto = trim($_POST["fornecedor_produto"] ?? '');
    $quantidade_produto = trim($_POST["quantidade_produto"] ?? '');
    $status_produto = trim($_POST["status_produto"] ?? '');
    

    // Verifica se os campos foram preenchidos
    if (!$id || empty($nome_produto) || empty($fornecedor_produto) || empty($quantidade_produto) || empty($status_produto)) {
        echo "<script>
                alert('Preencha todos os campos corretamente.');
                history.back();
              </script>";
        exit;
    }

    try {
        // Query de atualização
        $sql = "UPDATE produtos SET nome_produto = :nome_produto, fornecedor_produto = :fornecedor_produto, quantidade_produto = :quantidade_produto, status_produto = :status_produto WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":nome_produto", $nome_produto, PDO::PARAM_STR);
        $stmt->bindParam(":fornecedor_produto", $fornecedor_produto, PDO::PARAM_STR);
        $stmt->bindParam(":quantidade_produto", $quantidade_produto, PDO::PARAM_STR);
        $stmt->bindParam(":status_produto", $status_produto, PDO::PARAM_STR);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            echo "<script>
                    window.location.href = '../../../erp/estoque/produtosAdicionados.php';
                  </script>";
            exit;
        } else {
            echo "<script>
                    alert('Erro ao atualizar produtoss');
                    history.back();
                  </script>";
            exit;
        }
    } catch (PDOException $e) {
        echo "<script>
                alert('Erro no banco de dados: " . addslashes($e->getMessage()) . "');
                history.back();
              </script>";
        exit;
    }
} else {
    echo "<script>
            alert('Requisição inválida.');
            history.back();
          </script>";
    exit;
}
?>

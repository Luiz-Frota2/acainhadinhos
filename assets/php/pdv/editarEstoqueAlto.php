<?php
// Inclui o arquivo de conexão
require '../conexao.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST["id"] ?? null;
    $empresa_id = $_POST["empresa_id"] ?? null; // Recupere o ID da empresa
    $codigo_produto = trim($_POST["codigo_produto"] ?? '');
    $nome_produto = trim($_POST["nome_produto"] ?? '');
    $categoria_produto = trim($_POST["categoria_produto"] ?? '');
    $quantidade_produto = trim($_POST["quantidade_produto"] ?? '');
    $preco_produto = trim($_POST["preco_produto"] ?? '');
    $status_produto = trim($_POST["status_produto"] ?? '');


    // Verifica se os campos foram preenchidos
    if (!$id || !$empresa_id || empty($codigo_produto) || empty($nome_produto) || empty($categoria_produto) || empty($quantidade_produto) || empty($preco_produto) || empty($status_produto)) {
        echo "<script>
                alert('Preencha todos os campos corretamente.');
                history.back();
              </script>";
        exit;
    }

    try {
        // Query de atualização
        $sql = "UPDATE estoque SET codigo_produto = :codigo_produto, nome_produto = :nome_produto, categoria_produto = :categoria_produto, quantidade_produto = :quantidade_produto, preco_produto = :preco_produto, status_produto = :status_produto, empresa_id = :empresa_id WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":codigo_produto", $codigo_produto, PDO::PARAM_STR);
        $stmt->bindParam(":nome_produto", $nome_produto, PDO::PARAM_STR);
        $stmt->bindParam(":categoria_produto", $categoria_produto, PDO::PARAM_STR);
        $stmt->bindParam(":quantidade_produto", $quantidade_produto, PDO::PARAM_STR);
        $stmt->bindParam(":preco_produto", $preco_produto, PDO::PARAM_STR);
        $stmt->bindParam(":status_produto", $status_produto, PDO::PARAM_STR);
        $stmt->bindParam(":empresa_id", $empresa_id, PDO::PARAM_STR); // Adiciona o empresa_id no SQL
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            // Redireciona com o idSelecionado na URL
            echo "<script>
                    alert('Produto editado com sucesso!');
                    window.location.href = '../../../erp/pdv/estoqueAlto.php?id=" . urlencode($empresa_id) . "';
                  </script>";
            exit;
        } else {
            echo "<script>
                    alert('Erro ao atualizar produto');
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
<?php
// Inclui o arquivo de conexão
require '../conexao.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $descricao = trim($_POST["descricao"]);
    $valorpago = trim($_POST["valorpago"]);
    $datatransacao = trim($_POST["datatransacao"]);
    $responsavel = trim($_POST["responsavel"]);
    $statuss = trim($_POST["statuss"]);
    $empresa_id = trim($_POST['empresa_id']);
    try {
        // Preparar a query SQL
        $sql = "INSERT INTO contas (descricao, valorpago, datatransacao, responsavel, statuss) VALUES (:descricao, :valorpago, :datatransacao, :responsavel, :statuss)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":descricao", $descricao, PDO::PARAM_STR);
        $stmt->bindParam(":valorpago", $valorpago, PDO::PARAM_STR);
        $stmt->bindParam(":datatransacao", $datatransacao, PDO::PARAM_STR);
        $stmt->bindParam(":responsavel", $responsavel, PDO::PARAM_STR);
        $stmt->bindParam(":statuss", $statuss, PDO::PARAM_STR);

        // Executar e exibir mensagem de sucesso
        if ($stmt->execute()) {
            echo '<script>
                alert("Conta adicionado com sucesso!");
                window.location.href = "../../../erp/financas/contasAdicionadas.php?id=' . urlencode($empresa_id) . '";
            </script>';
                  ;
            exit();
        } else {
            echo "<script>
                    alert('Erro ao cadastrar conta.');
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


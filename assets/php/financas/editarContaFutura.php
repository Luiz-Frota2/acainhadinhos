<?php
// Inclui o arquivo de conexão
require '../conexao.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST["id"] ?? null;
    $descricao = trim($_POST["descricao"] ?? '');
    $valorpago = trim($_POST["valorpago"] ?? '');
    $datatransacao = trim($_POST["datatransacao"] ?? '');
    $responsavel = trim($_POST["responsavel"] ?? '');
    $statuss = trim($_POST["statuss"] ?? '');
    $empresa_id = trim($_GET['id']);

    // Verifica se os campos foram preenchidos
    if (!$id || empty($descricao) || empty($valorpago) || empty($datatransacao) || empty($responsavel) || empty($statuss)) {
        echo "<script>
                alert('Preencha todos os campos corretamente.');
                history.back();
              </script>";
        exit;
    }

    try {
        // Query de atualização
        $sql = "UPDATE contas SET descricao = :descricao, valorpago = :valorpago, datatransacao = :datatransacao, responsavel = :responsavel, statuss = :statuss WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":descricao", $descricao, PDO::PARAM_STR);
        $stmt->bindParam(":valorpago", $valorpago, PDO::PARAM_STR);
        $stmt->bindParam(":datatransacao", $datatransacao, PDO::PARAM_STR);
        $stmt->bindParam(":responsavel", $responsavel, PDO::PARAM_STR);
        $stmt->bindParam(":statuss", $statuss, PDO::PARAM_STR);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            echo '<script>
                     alert("Conta Atualizada com sucesso!");
                    window.location.href = "../../../erp/financas/contasFuturos.php?id=' . urlencode($empresa_id) . '";
                  </script>';
            exit;
        } else {
            echo "<script>
                    alert('Erro ao atualizar conta');
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

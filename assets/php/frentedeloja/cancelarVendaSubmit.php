<?php

require_once '../conexao.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}
$empresa_id = $_POST['idSelecionado'] ?? '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id || !$empresa_id) {
    echo "<script>
            alert('ID inválido.');
            history.back();
          </script>";
    exit;
}

try {
    // Deletar o conta
    $sql = "DELETE FROM vendarapida WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        echo "<script>
                 window.location.href = '../../../../frentedeloja/caixa/cancelarVenda.php?id=" . urlencode($empresa_id) . "';
              </script>";
        exit;
    } else {
        echo "<script>
                alert('Erro ao excluir contas.');
                history.back();
              </script>";
    }
} catch (PDOException $e) {
    echo "<script>
            alert('Erro no banco de dados: " . addslashes($e->getMessage()) . "');
            history.back();
          </script>";
}
?>

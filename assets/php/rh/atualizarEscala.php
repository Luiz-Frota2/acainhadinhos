<?php
// Inclui o arquivo de conexão
require '../conexao.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST["id"] ?? null;
    $nome_escala = trim($_POST["nome_escala"] ?? '');
    $data_escala = trim($_POST["data_escala"] ?? '');

    // Verifica se os campos foram preenchidos
    if (!$id || empty($nome_escala) || empty($data_escala)) {
        echo "<script>
                alert('Preencha todos os campos corretamente.');
                history.back();
              </script>";
        exit;
    }

    try {
        // Query de atualização
        $sql = "UPDATE escalas SET nome_escala = :nome_escala, data_escala = :data_escala WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":nome_escala", $nome_escala, PDO::PARAM_STR);
        $stmt->bindParam(":data_escala", $data_escala, PDO::PARAM_STR);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            echo "<script>
                    window.location.href = '../../../erp/rh/escalaAdicionadas.php';
                  </script>";
            exit;
        } else {
            echo "<script>
                    alert('Erro ao atualizar escala');
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

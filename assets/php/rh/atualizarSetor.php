<?php
// Inclui o arquivo de conexão
require '../conexao.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST["id"] ?? null;
    $nomeSetor = trim($_POST["nome_setor"] ?? '');
    $gerenteSetor = trim($_POST["gerente_setor"] ?? '');
    $idSelecionado = $_POST["id_selecionado"] ?? ''; // ✅ Captura o id_selecionado

    // Verifica se os campos foram preenchidos
    if (!$id || empty($nomeSetor) || empty($gerenteSetor) || empty($idSelecionado)) {
        echo "<script>
                alert('Preencha todos os campos corretamente.');
                history.back();
              </script>";
        exit;
    }

    try {
        // Query de atualização
        $sql = "UPDATE setores SET nome = :nome, gerente = :gerente WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":nome", $nomeSetor, PDO::PARAM_STR);
        $stmt->bindParam(":gerente", $gerenteSetor, PDO::PARAM_STR);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            echo "<script>
                    alert('Setor atualizado com sucesso!');
                    window.location.href = '../../../erp/rh/setoresAdicionados.php?id=$idSelecionado';
                  </script>";
            exit;
        } else {
            echo "<script>
                    alert('Erro ao atualizar setor.');
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

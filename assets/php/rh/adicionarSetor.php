<?php
// Inclui o arquivo de conexão
require '../conexao.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nomeSetor = trim($_POST["nome"]);
    $gerenteSetor = trim($_POST["gerente"]);
    $idSelecionado = trim($_POST["id_selecionado"]); // ✅ captura o id_selecionado

    try {
        // Preparar a query SQL com o campo id_selecionado
        $sql = "INSERT INTO setores (nome, gerente, id_selecionado) VALUES (:nome, :gerente, :id_selecionado)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":nome", $nomeSetor, PDO::PARAM_STR);
        $stmt->bindParam(":gerente", $gerenteSetor, PDO::PARAM_STR);
        $stmt->bindParam(":id_selecionado", $idSelecionado, PDO::PARAM_STR);

        // Executar e exibir mensagem de sucesso
        if ($stmt->execute()) {
            echo "<script>
                    alert('Setor cadastrado com sucesso!');
                    window.location.href = '../../../erp/rh/setoresAdicionados.php?id=$idSelecionado';
                  </script>";
            exit();
        } else {
            echo "<script>
                    alert('Erro ao cadastrar setor.');
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

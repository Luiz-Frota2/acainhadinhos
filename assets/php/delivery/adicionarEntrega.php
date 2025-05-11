<?php
require_once '../conexao.php';

$entrega = 0;
$tempo_min = 0;
$tempo_max = 0;
$idEntrega = 0;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // 👇 Mantenha como string (ex: 'filial_3')
    $idSelecionado = isset($_POST['id_selecionado']) ? $_POST['id_selecionado'] : '';
    $entrega = isset($_POST['entrega']) ? intval($_POST['entrega']) : 0;
    $tempo_min = isset($_POST['tempo_min']) ? intval($_POST['tempo_min']) : 0;
    $tempo_max = isset($_POST['tempo_max']) ? intval($_POST['tempo_max']) : 0;

    try {
        // Verifica se já existe uma entrega cadastrada para essa empresa
        $sqlCheck = "SELECT id_entrega FROM entregas WHERE id_empresa = :id_empresa LIMIT 1";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->bindParam(':id_empresa', $idSelecionado, PDO::PARAM_STR);
        $stmtCheck->execute();
        $result = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $idEntrega = $result['id_entrega'];

            $sqlUpdateEntrega = "UPDATE entregas 
                                 SET entrega = :entrega, tempo_min = :tempo_min, tempo_max = :tempo_max 
                                 WHERE id_entrega = :id_entrega";
            $stmtUpdate = $pdo->prepare($sqlUpdateEntrega);
            $stmtUpdate->bindParam(':entrega', $entrega, PDO::PARAM_INT);
            $stmtUpdate->bindParam(':tempo_min', $tempo_min, PDO::PARAM_INT);
            $stmtUpdate->bindParam(':tempo_max', $tempo_max, PDO::PARAM_INT);
            $stmtUpdate->bindParam(':id_entrega', $idEntrega, PDO::PARAM_INT);
            $stmtUpdate->execute();

            echo "<script>
                    alert('Configuração de entrega atualizada com sucesso!');
                    window.location.href='../../../erp/delivery/deliveryRetirada.php?id={$idSelecionado}';
                  </script>";
        } else {
            $sqlInsertEntrega = "INSERT INTO entregas (entrega, tempo_min, tempo_max, id_empresa) 
                                 VALUES (:entrega, :tempo_min, :tempo_max, :id_empresa)";
            $stmtInsert = $pdo->prepare($sqlInsertEntrega);
            $stmtInsert->bindParam(':entrega', $entrega, PDO::PARAM_INT);
            $stmtInsert->bindParam(':tempo_min', $tempo_min, PDO::PARAM_INT);
            $stmtInsert->bindParam(':tempo_max', $tempo_max, PDO::PARAM_INT);
            $stmtInsert->bindParam(':id_empresa', $idSelecionado, PDO::PARAM_STR);
            $stmtInsert->execute();

            echo "<script>
                    alert('Configuração de entrega salva com sucesso!');
                    window.location.href='../../../erp/delivery/deliveryRetirada.php?id={$idSelecionado}';
                  </script>";
        }

    } catch (PDOException $e) {
        echo "<script>alert('Erro ao processar: " . addslashes($e->getMessage()) . "');</script>";
    }
}
?>

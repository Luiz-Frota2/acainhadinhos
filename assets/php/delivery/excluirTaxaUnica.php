<?php
require_once '../conexao.php';

// Verifica se o método da requisição é GET e se o id_taxa está presente na URL
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET['id_taxa'])) {
    // Captura os valores da URL
    $id_taxa = isset($_GET['id_taxa']) ? intval($_GET['id_taxa']) : 0;
    $id_entrega = isset($_GET['id_entrega']) ? intval($_GET['id_entrega']) : 0;
    $idSelecionado = isset($_GET['idSelecionado']) ? $_GET['idSelecionado'] : ''; // ✅ Novo campo capturado

    // Verifica se o id_taxa é válido
    if ($id_taxa > 0) {
        try {
            // Inicia uma transação para garantir a integridade dos dados
            $pdo->beginTransaction();

            // Query para excluir a taxa pelo id_taxa
            $sql = "DELETE FROM entrega_taxas_unica WHERE id = :id_taxa";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id_taxa', $id_taxa, PDO::PARAM_INT);
            $stmt->execute();

            // Confirma a transação
            $pdo->commit();

            // Verifica o valor de taxa_unica após a exclusão
            $sqlTaxaUnica = "SELECT taxa_unica FROM entrega_taxas WHERE id_entrega = :id_entrega";
            $stmtTaxaUnica = $pdo->prepare($sqlTaxaUnica);
            $stmtTaxaUnica->bindParam(':id_entrega', $id_entrega, PDO::PARAM_INT);
            $stmtTaxaUnica->execute();
            $taxaUnicaData = $stmtTaxaUnica->fetch(PDO::FETCH_ASSOC);

            if (isset($taxaUnicaData['taxa_unica']) && $taxaUnicaData['taxa_unica'] == 1) {
                // Se taxa_unica for 1
                echo "<script>
                        alert('Taxa excluída com sucesso!');
                        window.location.href = '../../../erp/delivery/taxaEntrega.php?id=" . $idSelecionado . "&idEntrega=" . $id_entrega . "';
                      </script>";
            } else {
                // Caso contrário
                echo "<script>
                        alert('Taxa excluída, mas a taxa única não está configurada.');
                        window.location.href = '../../../erp/delivery/taxaEntrega.php?id=" . $idSelecionado . "&idEntrega=" . $id_entrega . "';
                      </script>";
            }

            exit();
        } catch (PDOException $e) {
            // Desfaz a transação em caso de erro
            $pdo->rollBack();

            echo "<script>
                    alert('Erro ao excluir a taxa: " . addslashes($e->getMessage()) . "');
                    history.back();
                  </script>";
            exit();
        }
    }
}
?>

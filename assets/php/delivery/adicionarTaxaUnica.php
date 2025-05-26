<?php
require_once '../conexao.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['id_entrega']) && isset($_POST['precoTaxaUnica'])) {
    // Coleta os dados do formulário
    $id_entrega = isset($_POST['id_entrega']) ? intval($_POST['id_entrega']) : 0;
    $idSelecionado = isset($_POST['idSelecionado']) ? $_POST['idSelecionado'] : ''; // ✅ Coleta o idSelecionado
    $taxa_unica = 1; // Já vem como '1' do formulário
    $precoTaxaUnica = isset($_POST['precoTaxaUnica']) ? floatval(str_replace(',', '.', $_POST['precoTaxaUnica'])) : 0.00;

    try {
        // Verifica se já existe um registro para a entrega no banco de dados
        $sqlCheck = "SELECT COUNT(*) FROM entrega_taxas_unica WHERE id_entrega = :id_entrega AND id_selecionado = :idSelecionado";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->bindParam(':id_entrega', $id_entrega, PDO::PARAM_INT);
        $stmtCheck->bindParam(':idSelecionado', $idSelecionado, PDO::PARAM_STR); // ✅ Usa idSelecionado
        $stmtCheck->execute();
        $count = $stmtCheck->fetchColumn();

        if ($count == 0) {
            // Se não existir, insere um novo registro
            $sql = "INSERT INTO entrega_taxas_unica (id_entrega, taxa_unica, valor_taxa, id_selecionado) 
                    VALUES (:id_entrega, :taxa_unica, :precoTaxaUnica, :idSelecionado)";
        } else {
            // Se já existir, faz o UPDATE
            $sql = "UPDATE entrega_taxas_unica 
                    SET taxa_unica = :taxa_unica, valor_taxa = :precoTaxaUnica 
                    WHERE id_entrega = :id_entrega AND id_selecionado = :idSelecionado";
        }

        // Prepara e executa a consulta
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_entrega', $id_entrega, PDO::PARAM_INT);
        $stmt->bindParam(':taxa_unica', $taxa_unica, PDO::PARAM_INT);
        $stmt->bindParam(':precoTaxaUnica', $precoTaxaUnica, PDO::PARAM_STR);
        $stmt->bindParam(':idSelecionado', $idSelecionado, PDO::PARAM_STR); // ✅ Passa aqui também
        $stmt->execute();

        // Redireciona com sucesso após a inserção/atualização
        echo "<script>
                alert('Configuração da taxa salva com sucesso!');
                window.location.href='../../../erp/delivery/taxaEntrega.php?id=" . $idSelecionado . "&idEntrega=" . $id_entrega . "';
              </script>";
        exit();

    } catch (PDOException $e) {
        // Exibe a mensagem de erro
        echo "<script>
                alert('Erro ao processar: " . addslashes($e->getMessage()) . "');
                history.back();
              </script>";
        exit();
    }
}
?>

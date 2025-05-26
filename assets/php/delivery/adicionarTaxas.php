<?php
require_once '../conexao.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Garantir que as variáveis sejam tratadas
    $id_entrega = isset($_POST['id_entrega']) ? intval($_POST['id_entrega']) : 0;
    $idSelecionado = isset($_POST['idSelecionado']) ? $_POST['idSelecionado'] : '';
    $sem_taxa = isset($_POST['sem_taxa']) ? intval($_POST['sem_taxa']) : 0;
    $taxa_unica = isset($_POST['taxa_unica']) ? intval($_POST['taxa_unica']) : 0;

    // Regras: Se "Sem Taxa" for ativado, "Taxa Única" será desativada e vice-versa
    if ($sem_taxa == 1) {
        $taxa_unica = 0; // Desativa "Taxa Única"
    } elseif ($taxa_unica == 1) {
        $sem_taxa = 0; // Desativa "Sem Taxa"
    }

    try {
        // Verifica se já existe um registro para a entrega
        $sqlCheck = "SELECT COUNT(*) FROM entrega_taxas WHERE id_entrega = :id_entrega AND idSelecionado = :idSelecionado";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->bindParam(':id_entrega', $id_entrega, PDO::PARAM_INT);
        $stmtCheck->bindParam(':idSelecionado', $idSelecionado, PDO::PARAM_STR);
        $stmtCheck->execute();
        $count = $stmtCheck->fetchColumn();

        if ($count == 0) {
            // Se não existir, faz INSERT
            $sql = "INSERT INTO entrega_taxas (id_entrega, sem_taxa, taxa_unica, idSelecionado) VALUES (:id_entrega, :sem_taxa, :taxa_unica, :idSelecionado)";
        } else {
            // Se já existir, faz UPDATE
            $sql = "UPDATE entrega_taxas SET sem_taxa = :sem_taxa, taxa_unica = :taxa_unica WHERE id_entrega = :id_entrega AND idSelecionado = :idSelecionado";
        }

        // Prepara e executa a consulta
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_entrega', $id_entrega, PDO::PARAM_INT);
        $stmt->bindParam(':sem_taxa', $sem_taxa, PDO::PARAM_INT);
        $stmt->bindParam(':taxa_unica', $taxa_unica, PDO::PARAM_INT);
        $stmt->bindParam(':idSelecionado', $idSelecionado, PDO::PARAM_STR);
        $stmt->execute();

        // Redireciona o usuário com sucesso para a página de configuração de entrega com o id_entrega e idSelecionado
        echo "<script>
                alert('Configuração de entrega salva com sucesso!');
                window.location.href='../../../erp/delivery/taxaEntrega.php?id=" . $idSelecionado . "&idEntrega=" . $id_entrega . "';
              </script>";

    } catch (PDOException $e) {
        // Exibe a mensagem de erro de forma mais amigável
        echo "<script>alert('Erro ao processar: " . addslashes($e->getMessage()) . "');</script>";
    }
}
?>

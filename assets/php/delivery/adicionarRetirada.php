<?php
require_once '../conexao.php'; // Inclui a conexão com o banco de dados

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $retirada = isset($_POST['retirada']) ? 1 : 0;
    $tempoMin = isset($_POST['tempo_min']) ? intval($_POST['tempo_min']) : 0;
    $tempoMax = isset($_POST['tempo_max']) ? intval($_POST['tempo_max']) : 0;
    $idSelecionado = $_POST['id_selecionado'] ?? ''; // 🔥 Captura o ID

    if (empty($idSelecionado)) {
        echo "<script>alert('ID Selecionado não foi fornecido.'); window.history.back();</script>";
        exit;
    }

    try {
        // Verifica se já existe um registro para esse id_empresa
        $sqlCheck = "SELECT COUNT(*) FROM configuracoes_retirada WHERE id_empresa = :id_empresa";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->bindParam(':id_empresa', $idSelecionado, PDO::PARAM_STR);
        $stmtCheck->execute();
        $count = $stmtCheck->fetchColumn();

        if ($count == 0) {
            // Se não houver registro, faz INSERT
            $sql = "INSERT INTO configuracoes_retirada (id_empresa, retirada, tempo_min, tempo_max) 
                    VALUES (:id_empresa, :retirada, :tempo_min, :tempo_max)";
        } else {
            // Se já houver registro, faz UPDATE
            $sql = "UPDATE configuracoes_retirada 
                    SET retirada = :retirada, tempo_min = :tempo_min, tempo_max = :tempo_max 
                    WHERE id_empresa = :id_empresa";
        }

        // Prepara e executa a query
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_empresa', $idSelecionado, PDO::PARAM_STR);
        $stmt->bindParam(':retirada', $retirada, PDO::PARAM_INT);
        $stmt->bindParam(':tempo_min', $tempoMin, PDO::PARAM_INT);
        $stmt->bindParam(':tempo_max', $tempoMax, PDO::PARAM_INT);
        $stmt->execute();

        echo "<script>alert('Configuração de retirada salva com sucesso!'); window.location.href='../../../erp/delivery/deliveryRetirada.php?id=$idSelecionado';</script>";

    } catch (PDOException $e) {
        echo "<script>alert('Erro ao salvar: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }
} else {
    echo "<script>alert('Método inválido!'); window.history.back();</script>";
}
?>

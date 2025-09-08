<?php
require_once '../conexao.php';

try {
    // 1. Validação do ID da empresa
    $idSelecionado = trim($_POST['idSelecionado'] ?? '');
    
    if (empty($idSelecionado)) {
        throw new Exception("Nenhum ID de empresa foi selecionado.");
    }

    // 2. Validação das formas de pagamento
    $camposValidos = [
        'dinheiro' => 'Dinheiro',
        'pix' => 'Pix',
        'cartaoDebito' => 'Cartão de Débito',
        'cartaoCredito' => 'Cartão de Crédito'
    ];

    // Identifica qual forma de pagamento foi enviada
    $campo = null;
    $valor = null;
    $nomeCampo = null;

    foreach ($camposValidos as $key => $nome) {
        if (isset($_POST[$key])) {
            $campo = $key;
            $valor = ($_POST[$key] == '1') ? 1 : 0;
            $nomeCampo = $nome;
            break;
        }
    }

    if ($campo === null) {
        throw new Exception("Nenhuma forma de pagamento válida foi enviada.");
    }

    // 3. Verifica/Insere registro da empresa
    $pdo->beginTransaction();

    // Verifica se existe registro para essa empresa
    $sqlCheck = "SELECT COUNT(*) FROM formas_pagamento WHERE empresa_id = :empresa_id";
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute([':empresa_id' => $idSelecionado]);
    
    if ($stmtCheck->fetchColumn() == 0) {
        // Insere novo registro com todos os valores como 0
        $sqlInsert = "INSERT INTO formas_pagamento 
                      (empresa_id, dinheiro, pix, cartaoDebito, cartaoCredito)
                      VALUES (:empresa_id, 0, 0, 0, 0)";
        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->execute([':empresa_id' => $idSelecionado]);
    }

    // 4. Atualiza a forma de pagamento específica
    $sqlUpdate = "UPDATE formas_pagamento 
                 SET $campo = :valor 
                 WHERE empresa_id = :empresa_id";
    
    $stmtUpdate = $pdo->prepare($sqlUpdate);
    $stmtUpdate->execute([
        ':valor' => $valor,
        ':empresa_id' => $idSelecionado
    ]);

    $pdo->commit();

    // 5. Retorna com mensagem de sucesso
    echo "<script>
        alert('Forma de pagamento \"$nomeCampo\" atualizada com sucesso!');
        window.location.href = '../../../erp/empresa/formaPagamento.php?id=" . htmlspecialchars($idSelecionado, ENT_QUOTES) . "';
    </script>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<script>
        alert('Erro: " . addslashes($e->getMessage()) . "');
        history.back();
    </script>";
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<script>
        alert('Erro no banco de dados ao atualizar formas de pagamento.');
        history.back();
    </script>";
}

?>
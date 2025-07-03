<?php
require_once '../conexao.php'; // caminho ajustável

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitização dos dados
    $idSelecionado = isset($_POST['id_selecionado']) ? trim($_POST['id_selecionado']) : null; // agora é string: ex. "principal_1"
    $idFilial = isset($_POST['id_filial']) ? intval($_POST['id_filial']) : null;
    $descricao = isset($_POST['descricao']) ? trim($_POST['descricao']) : '';
    $valor = isset($_POST['valor']) ? floatval($_POST['valor']) : 0;
    $dataVencimento = isset($_POST['data_vencimento']) ? $_POST['data_vencimento'] : '';

    // Validação
    if (!$idSelecionado || !$idFilial || !$descricao || !$valor || !$dataVencimento) {
        echo "<script>alert('Dados inválidos');history.back();</script>";
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO pagamentos_filial 
            (id_selecionado, id_filial, descricao, valor, data_vencimento, status_pagamento, criado_em) 
            VALUES 
            (:idSelecionado, :idFilial, :descricao, :valor, :dataVencimento, 'pendente', NOW())
        ");
        $stmt->execute([
            ':idSelecionado'   => $idSelecionado,
            ':idFilial'        => $idFilial,
            ':descricao'       => $descricao,
            ':valor'           => $valor,
            ':dataVencimento'  => $dataVencimento
        ]);

        echo "<script>alert('Pagamento registrado com sucesso');window.location.href='../../../erp/filial/contasFiliais.php?id=" . urlencode($idSelecionado) . "';</script>";
        exit;

    } catch (PDOException $e) {
        // Você pode logar o erro em arquivo aqui
        echo "<script>alert('Erro ao registrar pagamento');history.back();</script>";
        exit;
    }
} else {
    echo "<script>alert('Requisição inválida');history.back();</script>";
    exit;
}
?>

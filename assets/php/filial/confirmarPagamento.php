<?php

require '../conexao.php';

// Recebe os dados do POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $idSelecionado = isset($_POST['id_selecionado']) ? $_POST['id_selecionado'] : '';

    if ($id > 0 && $idSelecionado !== '') {
        // Atualiza o status para 'pago'
        $stmt = $pdo->prepare("UPDATE pagamentos_filial SET status_pagamento = 'pago', atualizado_em = NOW() WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            // Sucesso: alerta e redireciona
            echo "<script>alert('Pagamento confirmado com sucesso!');window.location.href='../../../erp/filial/contasFiliais.php?id=" . urlencode($idSelecionado) . "';</script>";
            exit;
        } else {
            // Erro: alerta e volta
            echo "<script>alert('Erro ao confirmar pagamento.');history.back();</script>";
            exit;
        }
    } else {
        echo "<script>alert('Dados inválidos.');history.back();</script>";
        exit;
    }
} else {
    echo "<script>alert('Requisição inválida.');history.back();</script>";
    exit;
}

?>
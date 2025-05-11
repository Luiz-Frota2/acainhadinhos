<?php

require '../conexao.php';

if (isset($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['idSelecionado'])) {
    $id = $_GET['id'];
    $idSelecionado = $_GET['idSelecionado']; // Recupera o idSelecionado da URL
    
    $stmt = $pdo->prepare("DELETE FROM horarios_funcionamento WHERE id = ?");
    if ($stmt->execute([$id])) {
        // Após a exclusão, redireciona para a página de horário de funcionamento, passando o idSelecionado
        echo "<script>alert('Horário excluído com sucesso!'); window.location.href='../../../erp/delivery/horarioFuncionamento.php?id=$idSelecionado';</script>";
    } else {
        echo "<script>alert('Erro ao excluir o horário!'); history.back();</script>";
    }
} else {
    echo "<script>alert('ID inválido!'); history.back();</script>";
}

?>

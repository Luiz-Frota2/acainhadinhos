<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../conexao.php'; // Ajuste o caminho conforme sua estrutura

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_logado']) || !isset($_SESSION['empresa_id'])) {
    die("Acesso não autorizado");
}

// Verifica se os dados necessários foram enviados
if (!isset($_POST['id'], $_POST['data_folga'], $_POST['cpf'], $_POST['empresa_id'])) {
    die("Dados incompletos para atualização");
}

$id = intval($_POST['id']);
$novaData = $_POST['data_folga'];
$cpf = $_POST['cpf'];
$idSelecionado = $_POST['empresa_id'];

// Validação simples da data
if (!DateTime::createFromFormat('Y-m-d', $novaData)) {
    die("Data inválida");
}

try {
    // Atualiza a data da folga no banco
    $stmt = $pdo->prepare("UPDATE folgas SET data_folga = :data_folga WHERE id = :id AND cpf = :cpf");
    $stmt->bindParam(':data_folga', $novaData);
    $stmt->bindParam(':id', $id);
    $stmt->bindParam(':cpf', $cpf);

    if ($stmt->execute()) {
        // Redireciona de volta para a página de ajuste com sucesso
        header("Location: ../../../erp/rh/folgasIndividuaisDias.php?id=" . urlencode($idSelecionado) . "&cpf=" . urlencode($cpf));
        exit;
    } else {
        echo "Erro ao atualizar a folga.";
    }
} catch (PDOException $e) {
    echo "Erro ao atualizar: " . $e->getMessage();
}

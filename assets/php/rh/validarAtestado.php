<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require '../../../assets/php/conexao.php';

$atestadoId = $_POST['atestado_id'] ?? '';
$cpf = $_POST['cpf'] ?? '';
$idSelecionado = $_POST['empresa_id'] ?? '';

if (empty($atestadoId) || empty($cpf) || empty($idSelecionado)) {
    echo "<script>alert('Dados incompletos para validação do atestado.'); history.back();</script>";
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE atestados SET status_atestado = 'válido' WHERE id = :id AND cpf_usuario = :cpf");
    $stmt->bindParam(':id', $atestadoId, PDO::PARAM_INT);
    $stmt->bindParam(':cpf', $cpf);
    $stmt->execute();

    echo "<script>
        alert('Atestado validado com sucesso!');
        window.location.href = '../../../erp/rh/atestadosFuncionarios.php?id=" . urlencode($idSelecionado) . "';
    </script>";
} catch (PDOException $e) {
    echo "<script>alert('Erro ao validar atestado: " . addslashes($e->getMessage()) . "'); history.back();</script>";
    exit;
}
?>

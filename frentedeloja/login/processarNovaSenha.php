<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../assets/php/conexao.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $idSelecionado = $_POST['id'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $confirmarSenha = $_POST['confirmar_senha'] ?? '';

    if (!$email || !$idSelecionado || !$senha || !$confirmarSenha) {
        echo "<script>alert('Todos os campos são obrigatórios.'); history.back();</script>";
        exit;
    }

    if ($senha !== $confirmarSenha) {
        echo "<script>alert('As senhas não coincidem.'); history.back();</script>";
        exit;
    }

    // Identifica o tipo de empresa e define o ID real
    if (str_starts_with($idSelecionado, 'principal_')) {
        $empresa_id = 1;
        $tipo = 'principal';
    } elseif (str_starts_with($idSelecionado, 'filial_')) {
        $empresa_id = (int) str_replace('filial_', '', $idSelecionado);
        $tipo = 'filial';
    } else {
        echo "<script>alert('Empresa não identificada!'); history.back();</script>";
        exit;
    }

    // Gera salt e hash da senha
    $salt = bin2hex(random_bytes(16));
    $senhaHash = hash('sha256', $salt . $senha);

    try {
        $sql = "UPDATE funcionarios_acesso 
                SET senha = :senha, salt = :salt, 
                    codigo_verificacao = NULL, 
                    codigo_verificacao_expires_at = 0 
                WHERE email = :email AND empresa_id = :empresa_id AND tipo = :tipo";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':senha' => $senhaHash,
            ':salt' => $salt,
            ':email' => $email,
            ':empresa_id' => $empresa_id,
            ':tipo' => $tipo
        ]);

        if ($stmt->rowCount() > 0) {
            echo "<script>alert('Senha redefinida com sucesso!'); window.location.href='../../../../frentedeloja/index.php?id=" . urlencode($idSelecionado) . "';</script>";
        } else {
            echo "<script>alert('Nenhum registro encontrado para atualizar. Verifique os dados.'); history.back();</script>";
        }
    } catch (PDOException $e) {
        echo "<script>alert('Erro ao atualizar senha: " . $e->getMessage() . "'); history.back();</script>";
    }
} else {
    echo "<script>alert('Requisição inválida.'); history.back();</script>";
}
?>

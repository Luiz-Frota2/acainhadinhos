<?php
require '../../assets/php/conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    $cpf = preg_replace('/[^0-9]/', '', $cpf); // Limpa o CPF
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $empresa_identificador = $_POST['empresa_identificador'] ?? '';

    // Verificação de campos obrigatórios
    if (empty($usuario) || empty($cpf) || empty($email) || empty($senha) || empty($empresa_identificador)) {
        echo "<script>alert('Preencha todos os campos!'); history.back();</script>";
        exit;
    }

    // Validação de e-mail
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('Formato de e-mail inválido.'); history.back();</script>";
        exit;
    }

    // Validação de senha
    if (strlen($senha) < 8) {
        echo "<script>alert('A senha deve ter no mínimo 8 caracteres.'); history.back();</script>";
        exit;
    }

    // Identifica empresa e tipo
    if (str_starts_with($empresa_identificador, 'principal_')) {
        $empresa_id = $empresa_identificador; // ✅ Mantém "principal_1"
        $tipo = 'principal';
    } elseif (str_starts_with($empresa_identificador, 'unidade_')) {
        $empresa_id = str_replace('unidade_', '', $empresa_identificador); // unidade_5 -> "5"
        $tipo = 'unidade';
    } else {
        echo "<script>alert('Identificador da empresa inválido.'); history.back();</script>";
        exit;
    }

    try {
        // Verifica duplicidade
        $verificaConta = $pdo->prepare("
            SELECT id FROM contas_acesso 
            WHERE (usuario = ? OR cpf = ? OR email = ?) 
              AND empresa_id = ? AND tipo = ?
        ");
        $verificaConta->execute([$usuario, $cpf, $email, $empresa_id, $tipo]);

        if ($verificaConta->rowCount() > 0) {
            echo "<script>alert('Já existe uma conta com este usuário, CPF ou e-mail nesta empresa.'); history.back();</script>";
            exit;
        }

        // Gera hash e salt
        $salt = bin2hex(random_bytes(16));
        $senhaHash = hash('sha256', $salt . $senha);

        $nivel = 'Comum';
        $autorizado = 'nao';

        // Inserção
        $stmt = $pdo->prepare("
            INSERT INTO contas_acesso 
            (usuario, cpf, email, senha, salt, empresa_id, tipo, nivel, autorizado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $usuario, $cpf, $email, $senhaHash, $salt,
            $empresa_id, $tipo, $nivel, $autorizado
        ]);

        echo "<script>
            alert('Conta cadastrada com sucesso! Aguarde a autorização do administrador.');
            window.location.href = '../login.php?id=" . htmlspecialchars($empresa_identificador) . "';
        </script>";
        exit;

    } catch (PDOException $e) {
        echo "<script>alert('Erro ao cadastrar: " . addslashes($e->getMessage()) . "'); history.back();</script>";
        exit;
    }
}
?>

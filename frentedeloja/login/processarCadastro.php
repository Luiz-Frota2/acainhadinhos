<?php
require '../../assets/php/conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize input data
    $usuario = trim($_POST['usuario'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    // Remove all non-digit characters from CPF
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $empresa_identificador = $_POST['empresa_identificador'] ?? '';

    // Validate required fields
    if (empty($usuario) || empty($cpf) || empty($email) || empty($senha) || empty($empresa_identificador)) {
        echo "<script>alert('Preencha todos os campos obrigatórios!'); history.back();</script>";
        exit;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('Formato de e-mail inválido.'); history.back();</script>";
        exit;
    }

    // Validate password strength (minimum 8 characters)
    if (strlen($senha) < 8) {
        echo "<script>alert('A senha deve ter no mínimo 8 caracteres.'); history.back();</script>";
        exit;
    }

    // Determine company type and ID
    if (str_starts_with($empresa_identificador, 'principal_')) {
        $empresa_id = 1;
        $tipo = 'principal';
    } elseif (str_starts_with($empresa_identificador, 'filial_')) {
        $empresa_id = (int) str_replace('filial_', '', $empresa_identificador);
        $tipo = 'filial';
    } else {
        echo "<script>alert('Identificador da empresa inválido.'); history.back();</script>";
        exit;
    }

    try {
        // Check for duplicate in contas_acesso table
        $verificaConta = $pdo->prepare("SELECT id FROM contas_acesso WHERE (usuario = ? OR cpf = ? OR email = ?) AND empresa_id = ? AND tipo = ?");
        $verificaConta->execute([$usuario, $cpf, $email, $empresa_id, $tipo]);

        if ($verificaConta->rowCount() > 0) {
            echo "<script>alert('Já existe uma conta com este usuário, CPF ou e-mail nesta empresa.'); history.back();</script>";
            exit;
        }

        // Check for duplicate in funcionarios_acesso table
        $verificaFuncionario = $pdo->prepare("SELECT id FROM funcionarios_acesso WHERE (usuario = ? OR cpf = ? OR email = ?) AND empresa_id = ? AND tipo = ?");
        $verificaFuncionario->execute([$usuario, $cpf, $email, $empresa_id, $tipo]);

        if ($verificaFuncionario->rowCount() > 0) {
            echo "<script>alert('Já existe um funcionário com este usuário, CPF ou e-mail nesta empresa.'); history.back();</script>";
            exit;
        }

        // Generate secure password hash
        $salt = bin2hex(random_bytes(16));
        $senhaHash = hash('sha256', $salt . $senha);

        // Set default values
        $nivel = 'Comum';
        $autorizado = 'nao';

        // Insert into funcionarios_acesso table
        $inserirFuncionario = $pdo->prepare("INSERT INTO funcionarios_acesso 
            (usuario, cpf, email, senha, salt, empresa_id, tipo, nivel, autorizado, criado_em) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        
        $inserirFuncionario->execute([
            $usuario, 
            $cpf, 
            $email, 
            $senhaHash, 
            $salt, 
            $empresa_id, 
            $tipo, 
            $nivel, 
            $autorizado
        ]);

        // Success message with redirect
        echo "<script>
            alert('Conta de funcionário cadastrada com sucesso! Aguarde autorização do administrador.');
            window.location.href = '../../../../frentedeloja/index.php?id={$empresa_identificador}';
        </script>";
        exit;

    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        echo "<script>alert('Erro ao cadastrar: Ocorreu um problema no sistema. Por favor, tente novamente.'); history.back();</script>";
        exit;
    }
} else {
    // Invalid request method
    echo "<script>alert('Método de requisição inválido.'); history.back();</script>";
    exit;
}

?>
<?php
require '../../assets/php/conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $empresa_identificador = $_POST['empresa_identificador'] ?? '';

    if (empty($usuario) || empty($cpf) || empty($email) || empty($senha) || empty($empresa_identificador)) {
        echo "<script>alert('Preencha todos os campos!'); history.back();</script>";
        exit;
    }

    // Determina tipo e ID da empresa
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
        // Verifica duplicidade na tabela contas_acesso
        $verificaConta = $pdo->prepare("SELECT id FROM funcionarios_acesso WHERE (usuario = ? OR cpf = ? OR email = ?) AND empresa_id = ? AND tipo = ?");
        $verificaConta->execute([$usuario, $cpf, $email, $empresa_id, $tipo]);

        if ($verificaConta->rowCount() > 0) {
            echo "<script>alert('Já existe uma conta em contas_acesso com este usuário, CPF ou e-mail nesta empresa.'); history.back();</script>";
            exit;
        }

        // Verifica duplicidade na tabela funcionarios_acesso
        $verificaFuncionario = $pdo->prepare("SELECT id FROM funcionarios_acesso WHERE (usuario = ? OR cpf = ? OR email = ?) AND empresa_id = ? AND tipo = ?");
        $verificaFuncionario->execute([$usuario, $cpf, $email, $empresa_id, $tipo]);

        if ($verificaFuncionario->rowCount() > 0) {
            echo "<script>alert('Já existe uma conta em funcionarios_acesso com este usuário, CPF ou e-mail nesta empresa.'); history.back();</script>";
            exit;
        }

        // Gera salt e hash da senha
        $salt = bin2hex(random_bytes(16));
        $senhaHash = hash('sha256', $salt . $senha);

        // Define os padrões
        $nivel = 'Comum';
        $autorizado = 'nao';

        // Insere na tabela correta: funcionarios_acesso
        $inserirFuncionario = $pdo->prepare("INSERT INTO funcionarios_acesso (usuario, cpf, email, senha, salt, empresa_id, tipo, nivel, autorizado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $inserirFuncionario->execute([$usuario, $cpf, $email, $senhaHash, $salt, $empresa_id, $tipo, $nivel, $autorizado]);

        echo "<script>
            alert('Conta cadastrada com sucesso! Aguarde a autorização do administrador.');
            window.location.href = '../../../../frentedeloja/index.php?id={$empresa_identificador}';
        </script>";
        exit;

    } catch (PDOException $e) {
        echo "<script>alert('Erro ao cadastrar: " . $e->getMessage() . "'); history.back();</script>";
        exit;
    }
}
?>

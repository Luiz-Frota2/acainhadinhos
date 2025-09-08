<?php
require '../../assets/php/conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Coleta e sanitiza os dados
    $usuario = trim($_POST['usuario'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $empresa_identificador = trim($_POST['empresa_identificador'] ?? '');
    $idSelecionado = trim($_POST['id'] ?? '');

    // Valida campos obrigatórios
    if (empty($usuario) || empty($cpf) || empty($email) || empty($senha) || empty($empresa_identificador) || empty($idSelecionado)) {
        echo "<script>alert('Preencha todos os campos obrigatórios!'); history.back();</script>";
        exit;
    }

    // Verifica consistência entre os dois campos de empresa
    if ($empresa_identificador !== $idSelecionado) {
        echo "<script>alert('Inconsistência na identificação da empresa.'); history.back();</script>";
        exit;
    }

    // Valida e-mail
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('Formato de e-mail inválido.'); history.back();</script>";
        exit;
    }

    // Valida força da senha
    if (strlen($senha) < 8) {
        echo "<script>alert('A senha deve ter no mínimo 8 caracteres.'); history.back();</script>";
        exit;
    }

    // Determina tipo e ID da empresa
    if (str_starts_with($empresa_identificador, 'principal_')) {
        $tipo = 'principal';
        $empresa_id = $empresa_identificador; // Armazena o identificador completo
    } elseif (str_starts_with($empresa_identificador, 'unidade_')) {
        $unidade_id = str_replace('unidade_', '', $empresa_identificador);
        
        // Busca o tipo real da unidade no banco de dados
        try {
            $stmt = $pdo->prepare("SELECT tipo FROM unidades WHERE id = ?");
            $stmt->execute([$unidade_id]);
            $tipo_unidade = $stmt->fetchColumn();

            if (!$tipo_unidade) {
                echo "<script>alert('Unidade não encontrada.'); history.back();</script>";
                exit;
            }

            $tipo = $tipo_unidade; // 'filial' ou 'franquia'
            $empresa_id = $empresa_identificador; // Armazena o identificador completo
        } catch (PDOException $e) {
            error_log("Erro ao buscar tipo da unidade: " . $e->getMessage());
            echo "<script>alert('Erro ao verificar unidade.'); history.back();</script>";
            exit;
        }
    } else {
        echo "<script>alert('Identificador de empresa inválido.'); history.back();</script>";
        exit;
    }

    try {
        // Verifica duplicados
        $verificaFuncionario = $pdo->prepare("SELECT id FROM funcionarios_acesso 
            WHERE (usuario = ? OR cpf = ? OR email = ?) AND empresa_id = ? AND tipo = ?");
        $verificaFuncionario->execute([$usuario, $cpf, $email, $empresa_id, $tipo]);

        if ($verificaFuncionario->rowCount() > 0) {
            echo "<script>alert('Já existe um funcionário com este usuário, CPF ou e-mail nesta empresa.'); history.back();</script>";
            exit;
        }

        // Cria hash de senha
        $salt = bin2hex(random_bytes(16));
        $senhaHash = hash('sha256', $salt . $senha);

        $nivel = 'Comum';
        $autorizado = 'nao';

        // Insere
        $inserirFuncionario = $pdo->prepare("INSERT INTO funcionarios_acesso 
            (usuario, cpf, email, senha, salt, empresa_id, tipo, nivel, autorizado, criado_em) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

        $inserirFuncionario->execute([
            $usuario,
            $cpf,
            $email,
            $senhaHash,
            $salt,
            $empresa_id, // Agora armazena o identificador completo (principal_1, unidade_1, etc)
            $tipo,       // Armazena o tipo (principal, filial, franquia)
            $nivel,
            $autorizado
        ]);

        echo "<script>
            alert('Conta de funcionário cadastrada com sucesso! Aguarde autorização do administrador.');
            window.location.href = '../index.php?id={$empresa_identificador}';
        </script>";
        exit;

    } catch (PDOException $e) {
        error_log("Erro no banco: " . $e->getMessage());
        echo "<script>alert('Erro ao cadastrar. Tente novamente.'); history.back();</script>";
        exit;
    }
} else {
    echo "<script>alert('Método inválido.'); history.back();</script>";
    exit;
}
?>
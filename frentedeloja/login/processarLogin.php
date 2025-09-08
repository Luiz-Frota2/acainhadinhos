<?php
require '../../assets/php/conexao.php';
session_start();

function normaliza_cpf($cpf) {
    return preg_replace('/[^0-9]/', '', $cpf);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_cpf = trim($_POST['usuario_cpf'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $empresa_identificador = trim($_POST['empresa_identificador'] ?? '');

    if (empty($usuario_cpf) || empty($senha) || empty($empresa_identificador)) {
        echo "<script>alert('Preencha todos os campos.'); history.back();</script>";
        exit;
    }

    $cpf_normalizado = normaliza_cpf($usuario_cpf);

    // Determina tipo e empresa_id a partir do identificador
    if (str_starts_with($empresa_identificador, 'principal_')) {
        $tipo = 'principal';
        $empresa_id = $empresa_identificador; // Usa o identificador completo
    } elseif (str_starts_with($empresa_identificador, 'unidade_')) {
        $empresa_id = $empresa_identificador; // Usa o identificador completo
        
        // Busca o tipo real da unidade no banco (filial ou franquia)
        $unidade_id = str_replace('unidade_', '', $empresa_identificador);
        try {
            $stmt = $pdo->prepare("SELECT tipo FROM unidades WHERE id = ?");
            $stmt->execute([$unidade_id]);
            $tipo = $stmt->fetchColumn();
            
            if (!$tipo) {
                echo "<script>alert('Unidade não encontrada.'); history.back();</script>";
                exit;
            }
        } catch (PDOException $e) {
            error_log("Erro ao buscar tipo da unidade: " . $e->getMessage());
            echo "<script>alert('Erro ao verificar unidade.'); history.back();</script>";
            exit;
        }
    } else {
        echo "<script>alert('Identificador da empresa inválido.'); history.back();</script>";
        exit;
    }

    try {
        // Primeiro verifica na tabela funcionarios_acesso
        $sql = "
            SELECT * FROM funcionarios_acesso
            WHERE (usuario = :usuario OR cpf = :cpf_normalizado)
              AND empresa_id = :empresa_id
              AND tipo = :tipo
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':usuario' => $usuario_cpf,
            ':cpf_normalizado' => $cpf_normalizado,
            ':empresa_id' => $empresa_id,
            ':tipo' => $tipo
        ]);

        $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($funcionario) {
            // Verifica a senha para funcionário
            $hashInformado = hash('sha256', $funcionario['salt'] . $senha);

            if ($hashInformado !== $funcionario['senha']) {
                echo "<script>alert('Senha incorreta.'); history.back();</script>";
                exit;
            }

            if ($funcionario['autorizado'] !== 'sim') {
                echo "<script>alert('Seu acesso ainda não foi autorizado.'); history.back();</script>";
                exit;
            }

            // Seta a sessão do funcionário
            $_SESSION['usuario_logado'] = true;
            $_SESSION['usuario_id'] = $funcionario['id'];
            $_SESSION['usuario_nome'] = $funcionario['usuario'];
            $_SESSION['usuario_cpf'] = $funcionario['cpf'];
            $_SESSION['empresa_id'] = $funcionario['empresa_id'];
            $_SESSION['tipo_empresa'] = $funcionario['tipo'];
            $_SESSION['nivel'] = $funcionario['nivel'];
            $_SESSION['empresa_identificador'] = $empresa_identificador;

            echo "<script>window.location.href = '../dashboard.php?id={$empresa_identificador}';</script>";
            exit;
        }

        // Se não encontrou no funcionarios_acesso, verifica contas_acesso
        $sql = "
            SELECT * FROM contas_acesso
            WHERE (usuario = :usuario OR cpf = :cpf_normalizado)
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':usuario' => $usuario_cpf,
            ':cpf_normalizado' => $cpf_normalizado
        ]);

        $conta = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($conta) {
            // Verifica a senha para conta de acesso
            $hashInformado = hash('sha256', $conta['salt'] . $senha);

            if ($hashInformado !== $conta['senha']) {
                echo "<script>alert('Senha incorreta.'); history.back();</script>";
                exit;
            }

            // Verifica se é admin da principal_1 ou está autorizado
            $isAdminPrincipal1 = ($conta['empresa_id'] === 'principal_1' && $conta['nivel'] === 'Admin');
            $isAutorizado = ($conta['autorizado'] === 'sim');

            if (!$isAdminPrincipal1 && !$isAutorizado) {
                echo "<script>alert('Seu acesso ainda não foi autorizado.'); history.back();</script>";
                exit;
            }

            // Seta a sessão da conta de acesso
            $_SESSION['usuario_logado'] = true;
            $_SESSION['usuario_id'] = $conta['id'];
            $_SESSION['usuario_nome'] = $conta['usuario'];
            $_SESSION['usuario_cpf'] = $conta['cpf'];
            $_SESSION['empresa_id'] = $conta['empresa_id'];
            $_SESSION['tipo_empresa'] = $conta['tipo'];
            $_SESSION['nivel'] = $conta['nivel'];
            
            // Mantém o empresa_identificador original da requisição para o redirecionamento
            $_SESSION['empresa_identificador'] = $empresa_identificador;

            // Usa o $empresa_identificador original no redirecionamento
            echo "<script>window.location.href = '../dashboard.php?id={$empresa_identificador}';</script>";
            exit;
        }

        // Se não encontrou em nenhuma tabela
        echo "<script>alert('Usuário não encontrado.'); history.back();</script>";
        exit;

    } catch (PDOException $e) {
        error_log("Erro no login: " . $e->getMessage());
        echo "<script>alert('Erro ao verificar login. Tente novamente mais tarde.'); history.back();</script>";
        exit;
    }
} else {
    echo "<script>alert('Método de requisição inválido.'); history.back();</script>";
    exit;
}
?>
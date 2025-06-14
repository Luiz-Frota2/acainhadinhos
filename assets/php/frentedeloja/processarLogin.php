<?php
require '../../assets/php/conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_cpf = $_POST['usuario_cpf'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $empresa_identificador = $_POST['empresa_identificador'] ?? '';

    if (empty($usuario_cpf) || empty($senha) || empty($empresa_identificador)) {
        echo "<script>alert('Preencha todos os campos.'); history.back();</script>";
        exit;
    }

    // Determinar tipo e empresa_id
    if (str_starts_with($empresa_identificador, 'principal_')) {
        $empresa_id = 1;
        $tipo = 'principal';
    } elseif (str_starts_with($empresa_identificador, 'filial_')) {
        $empresa_id = (int) str_replace('filial_', '', $empresa_identificador);
        $tipo = 'filial';
    } else {
        echo "<script>alert('Empresa inválida.'); history.back();</script>";
        exit;
    }

    try {
        // Tenta encontrar na tabela de contas_acesso (Admin)
        $stmtAdmin = $pdo->prepare("SELECT * FROM contas_acesso WHERE (usuario = ? OR cpf = ?) AND empresa_id = ? AND tipo = ?");
        $stmtAdmin->execute([$usuario_cpf, $usuario_cpf, $empresa_id, $tipo]);
        $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

        if ($admin) {
            $hashInformado = hash('sha256', $admin['salt'] . $senha);

            if ($hashInformado === $admin['senha']) {
                if ($admin['autorizado'] !== 'sim') {
                    echo "<script>alert('Seu acesso ainda não foi autorizado.'); history.back();</script>";
                    exit;
                }

                // Verifica se é Admin
                if ($admin['nivel'] === 'Admin') {
                    session_start();
                    $_SESSION['usuario_logado'] = true;
                    $_SESSION['usuario_id'] = $admin['id'];
                    $_SESSION['usuario_nome'] = $admin['usuario'];
                    $_SESSION['empresa_id'] = $admin['empresa_id'];
                    $_SESSION['tipo_empresa'] = $admin['tipo'];
                    $_SESSION['nivel'] = $admin['nivel'];

                    echo "<script>window.location.href = '../../../../frentedeloja/dashboard.php?id={$empresa_identificador}';</script>";
                    exit;
                }
            }
        }

        // Se não for Admin ou Admin inválido, tenta em funcionarios_acesso
        $stmtFunc = $pdo->prepare("SELECT * FROM funcionarios_acesso WHERE (usuario = ? OR cpf = ?) AND empresa_id = ? AND tipo = ?");
        $stmtFunc->execute([$usuario_cpf, $usuario_cpf, $empresa_id, $tipo]);
        $funcionario = $stmtFunc->fetch(PDO::FETCH_ASSOC);

        if ($funcionario) {
            $hashInformado = hash('sha256', $funcionario['salt'] . $senha);

            if ($hashInformado === $funcionario['senha']) {
                if ($funcionario['autorizado'] !== 'sim') {
                    echo "<script>alert('Seu acesso ainda não foi autorizado.'); history.back();</script>";
                    exit;
                }

                session_start();
                $_SESSION['usuario_logado'] = true;
                $_SESSION['usuario_id'] = $funcionario['id'];
                $_SESSION['usuario_nome'] = $funcionario['usuario'];
                $_SESSION['empresa_id'] = $funcionario['empresa_id'];
                $_SESSION['tipo_empresa'] = $funcionario['tipo'];
                $_SESSION['nivel'] = $funcionario['nivel'];

                echo "<script>window.location.href = '../../../../frentedeloja/dashboard.php?id={$empresa_identificador}';</script>";
                exit;
            } else {
                echo "<script>alert('Senha incorreta.'); history.back();</script>";
                exit;
            }
        } else {
            echo "<script>alert('Usuário não encontrado.'); history.back();</script>";
            exit;
        }

    } catch (PDOException $e) {
        echo "<script>alert('Erro ao verificar login.'); history.back();</script>";
    }
}
?>

<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../../assets/php/conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_cpf = $_POST['usuario_cpf'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $empresa_identificador = $_POST['empresa_identificador'] ?? '';

    if (empty($usuario_cpf) || empty($senha) || empty($empresa_identificador)) {
        echo "<script>alert('Preencha todos os campos.'); history.back();</script>";
        exit;
    }

    // Normaliza o CPF removendo caracteres não numéricos
    $cpf_normalizado = preg_replace('/\D/', '', $usuario_cpf);

    // Determinar tipo e ID com base no prefixo do identificador
    if (str_starts_with($empresa_identificador, 'principal_')) {
        $empresa_alvo_id = 'principal_1';
        $tipo_alvo = 'principal';
    } elseif (str_starts_with($empresa_identificador, 'unidade_')) {
        $empresa_alvo_id = $empresa_identificador;
        $tipo_alvo = 'unidade';
    } elseif (str_starts_with($empresa_identificador, 'franquia_')) {
        $empresa_alvo_id = $empresa_identificador;
        $tipo_alvo = 'franquia';
    } else {
        echo "<script>alert('Identificador de empresa inválido.'); history.back();</script>";
        exit;
    }

    try {
        // Buscar usuário por nome ou CPF (sem filtrar por empresa ainda)
        $stmt = $pdo->prepare("
            SELECT * FROM contas_acesso 
            WHERE (usuario = ? OR REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = ?)
        ");
        $stmt->execute([$usuario_cpf, $cpf_normalizado]);
        $conta = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($conta) {
            // Verifica senha
            $saltArmazenado = $conta['salt'];
            $hashArmazenado = $conta['senha'];
            $hashInformado = hash('sha256', $saltArmazenado . $senha);

            if ($hashInformado !== $hashArmazenado) {
                echo "<script>alert('Senha incorreta.'); history.back();</script>";
                exit;
            }

            // Verifica se está autorizado
            if ($conta['autorizado'] !== 'sim') {
                echo "<script>alert('Seu acesso ainda não foi autorizado.'); history.back();</script>";
                exit;
            }

            // Verifica se é Admin da empresa principal
            $is_admin_matriz = (
                $conta['empresa_id'] === 'principal_1' &&
                $conta['tipo'] === 'principal' &&
                $conta['nivel'] === 'Admin'
            );

            // Valida se tem permissão para acessar essa empresa
            if (!$is_admin_matriz) {
                if (
                    $conta['empresa_id'] !== $empresa_alvo_id ||
                    $conta['tipo'] !== $tipo_alvo
                ) {
                    echo "<script>alert('Você não tem permissão para acessar esta empresa.'); history.back();</script>";
                    exit;
                }
            }

            // ✅ Login bem-sucedido
            session_start();
            $_SESSION['usuario_logado'] = true;
            $_SESSION['usuario_id'] = $conta['id'];
            $_SESSION['usuario_nome'] = $conta['usuario'];
            $_SESSION['empresa_id'] = $empresa_alvo_id; // ex: unidade_2
            $_SESSION['tipo_empresa'] = $tipo_alvo;
            $_SESSION['nivel'] = $conta['nivel'];
            $_SESSION['empresa_original_id'] = $conta['empresa_id'];
            $_SESSION['tipo_empresa_original'] = $conta['tipo'];
            $_SESSION['is_admin_matriz'] = $is_admin_matriz;

            echo "<script>window.location.href = '../dashboard.php?id=" . htmlspecialchars($empresa_identificador) . "';</script>";
            exit;
        } else {
            echo "<script>alert('Usuário não encontrado.'); history.back();</script>";
            exit;
        }

    } catch (PDOException $e) {
        echo "<script>alert('Erro ao verificar login: " . $e->getMessage() . "'); history.back();</script>";
        exit;
    }
}
?>

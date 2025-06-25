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

    // Normaliza o CPF removendo tudo que não for número
    $cpf_normalizado = preg_replace('/\D/', '', $usuario_cpf);

    // Determinar o tipo e empresa_id da empresa que está tentando acessar
    if (str_starts_with($empresa_identificador, 'principal_')) {
        $empresa_alvo_id = 1;
        $tipo_alvo = 'principal';
    } elseif (str_starts_with($empresa_identificador, 'filial_')) {
        $empresa_alvo_id = (int) str_replace('filial_', '', $empresa_identificador);
        $tipo_alvo = 'filial';
    } else {
        echo "<script>alert('Empresa inválida.'); history.back();</script>";
        exit;
    }

    try {
        // Buscar conta pelo usuário/cpf (sem filtrar por empresa ainda)
        $stmt = $pdo->prepare("SELECT * FROM contas_acesso WHERE (usuario = ? OR REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = ?)");
        $stmt->execute([$usuario_cpf, $cpf_normalizado]);
        $conta = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($conta) {
            $saltArmazenado = $conta['salt'];
            $hashArmazenado = $conta['senha'];
            $hashInformado = hash('sha256', $saltArmazenado . $senha);

            if ($hashInformado === $hashArmazenado) {
                // Verifica autorização
                if ($conta['autorizado'] !== 'sim') {
                    echo "<script>alert('Seu acesso ainda não foi autorizado.'); history.back();</script>";
                    exit;
                }

                // Verifica se é admin da matriz
                $is_admin_matriz = ($conta['empresa_id'] == 1 && $conta['tipo'] == 'principal' && $conta['nivel'] == 'Admin');

                // Se NÃO for admin da matriz, verifica se pertence à mesma empresa
                if (!$is_admin_matriz) {
                    if ($conta['empresa_id'] != $empresa_alvo_id || $conta['tipo'] != $tipo_alvo) {
                        echo "<script>alert('Você não tem permissão para acessar esta empresa.'); history.back();</script>";
                        exit;
                    }
                }

                // ✅ Login bem-sucedido
                session_start();
                $_SESSION['usuario_logado'] = true;
                $_SESSION['usuario_id'] = $conta['id'];
                $_SESSION['usuario_nome'] = $conta['usuario'];
                $_SESSION['empresa_id'] = $empresa_alvo_id; // Usa a empresa acessada
                $_SESSION['tipo_empresa'] = $tipo_alvo;    // Usa o tipo da empresa acessada
                $_SESSION['nivel'] = $conta['nivel'];
                $_SESSION['empresa_original_id'] = $conta['empresa_id']; // Guarda a empresa original
                $_SESSION['tipo_empresa_original'] = $conta['tipo'];    // Guarda o tipo original
                $_SESSION['is_admin_matriz'] = $is_admin_matriz;         // Flag para admin da matriz

                echo "<script>
                    window.location.href = '../dashboard.php?id=$empresa_identificador';
                </script>";
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
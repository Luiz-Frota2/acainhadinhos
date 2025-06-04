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

    // Remove pontos, traços, espaços – normaliza o CPF informado
    $cpf_normalizado = preg_replace('/\D/', '', $usuario_cpf);

    // Determina o tipo da empresa e ID
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
        // Compara o campo CPF do banco removendo pontuação e espaços
        $stmt = $pdo->prepare("
            SELECT * FROM contas_acesso 
            WHERE (usuario = :usuario OR 
                   REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = :cpf)
              AND empresa_id = :empresa_id 
              AND tipo = :tipo
        ");
        $stmt->execute([
            ':usuario' => $usuario_cpf,
            ':cpf' => $cpf_normalizado,
            ':empresa_id' => $empresa_id,
            ':tipo' => $tipo
        ]);

        $conta = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($conta) {
            $saltArmazenado = $conta['salt'];
            $hashArmazenado = $conta['senha'];
            $hashInformado = hash('sha256', $saltArmazenado . $senha);

            if ($hashInformado === $hashArmazenado) {
                // Verifica se autorizado
                if ($conta['autorizado'] !== 'sim') {
                    echo "<script>alert('Seu acesso ainda não foi autorizado.'); history.back();</script>";
                    exit;
                }

                // Login OK
                session_start();
                $_SESSION['usuario_logado'] = true;
                $_SESSION['usuario_id'] = $conta['id'];
                $_SESSION['usuario_nome'] = $conta['usuario'];
                $_SESSION['empresa_id'] = $conta['empresa_id'];
                $_SESSION['tipo_empresa'] = $conta['tipo'];
                $_SESSION['nivel'] = $conta['nivel'];

                echo "<script>window.location.href = '../dashboard.php?id=$empresa_identificador';</script>";
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
        exit;
    }
}
?>

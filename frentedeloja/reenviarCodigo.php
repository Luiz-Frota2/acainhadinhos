<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../assets/php/conexao.php';

$email = $_POST['email'] ?? '';
$idSelecionado = $_POST['id'] ?? '';

if (!$email || !$idSelecionado) {
    echo "<script>alert('Dados incompletos.'); history.back();</script>";
    exit;
}

// Detectar o tipo de empresa
if (str_starts_with($idSelecionado, 'principal_')) {
    $empresa_id = 1;
} elseif (str_starts_with($idSelecionado, 'filial_')) {
    $empresa_id = (int) str_replace('filial_', '', $idSelecionado);
} else {
    echo "<script>alert('Formato de empresa inválido.'); history.back();</script>";
    exit;
}

try {
    // Buscar o usuário pelo email e empresa_id
    $stmt = $pdo->prepare("SELECT id, usuario FROM funcionarios_acesso WHERE email = :email AND empresa_id = :empresa_id");
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $codigo = rand(100000, 999999);
        $expiracaoTimestamp = time() + 180; // 3 minutos

        $_SESSION['codigo_verificacao'] = $codigo;
        $_SESSION['usuario_para_reset'] = $usuario['id'];

        // Atualizar código no banco
        $update = $pdo->prepare("UPDATE funcionarios_acesso SET codigo_verificacao = :codigo, codigo_verificacao_expires_at = :expira WHERE id = :id");
        $update->bindParam(':codigo', $codigo);
        $update->bindParam(':expira', $expiracaoTimestamp, PDO::PARAM_INT);
        $update->bindParam(':id', $usuario['id'], PDO::PARAM_INT);
        $update->execute();

        // Enviar e-mail
        $assunto = "Código de Verificação - Redefinição de Senha";
        $mensagem = "
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    color: #333;
                }
                .container {
                    max-width: 100%;
                    margin: auto;
                    padding: 20px;
                    border: 1px solid #ddd;
                    border-radius: 8px;
                    background-color: #f9f9f9;
                }
                .logo {
                    text-align: center;
                    margin-bottom: 20px;
                }
                p {
                    font-size: 15px;
                }
                .codigo {
                    font-size: 26px;
                    font-weight: bold;
                    color: green;
                    background-color: #eafbe7;
                    padding: 15px 30px;
                    border-radius: 8px;
                    display: inline-block;
                    width: 100%;
                    max-width: 700px;
                    box-sizing: border-box;
                    text-align: center;
                    letter-spacing: 2px;
                    box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
                }
                .footer {
                    font-size: 13px;
                    color: #999;
                    text-align: center;
                    margin-top: 30px;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='logo'>
                    <div class='logo'>
                        <img src='https://codegeek.dev.br/assets/img/favicon/codegeek.jpeg' alt='CodeGeek' style='width: 120px; height: auto; border-radius: 8px;'>
                    </div>
                </div>
                <p>Olá, <strong>" . htmlspecialchars($usuario['usuario']) . "</strong>!</p>
                <p>Seu novo código de verificação é:</p>
                <div class='codigo'>{$codigo}</div>
                <p style='margin-top: 20px;'>Este código é válido por <strong>3 minutos</strong>.</p>
                <div class='footer'>
                    Caso não tenha solicitado este código, ignore este e-mail.
                </div>
            </div>
        </body>
        </html>";

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        $headers .= "From: suportecodegeek@gmail.com\r\n";

        if (mail($email, $assunto, $mensagem, $headers)) {
            echo "<script>
                alert('Novo código enviado com sucesso.');
                window.location.href = './verificarCodigo.php?id=" . urlencode($idSelecionado) . "&email=" . urlencode($email) . "';
            </script>";
        } else {
            echo "<script>alert('Erro ao enviar o e-mail.'); history.back();</script>";
        }
    } else {
        echo "<script>alert('Conta não encontrada para este e-mail e empresa.'); history.back();</script>";
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro: " . addslashes($e->getMessage()) . "'); history.back();</script>";
}
?>
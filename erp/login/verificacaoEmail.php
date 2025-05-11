<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../assets/php/conexao.php';

$email = $_POST['email'] ?? '';
$empresa_id = $_POST['empresa_id'] ?? '';
$tipo = $_POST['tipo'] ?? '';
$idSelecionado = $_POST['idSelecionado'] ?? '';

if (!$email || !$empresa_id || !$tipo) {
    echo "<script>alert('Dados incompletos.'); history.back();</script>";
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, usuario, codigo_verificacao, codigo_verificacao_expires_at FROM contas_acesso WHERE email = :email AND empresa_id = :empresa_id AND tipo = :tipo LIMIT 1");
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt->bindParam(':tipo', $tipo);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        // Verificar se o código de verificação ainda não expirou
        $codigoExpiracao = $usuario['codigo_verificacao_expires_at'];
        $codigoAtual = $usuario['codigo_verificacao'];
        
        if (empty($codigoAtual) || time() > $codigoExpiracao) {
            // Gerar código de verificação
            $codigo = rand(100000, 999999);

            // Definir a expiração para 3 minutos a partir de agora
            $expiracao = time() + 180; // agora + 3 minutos

            $_SESSION['codigo_verificacao'] = $codigo;
            $_SESSION['usuario_para_reset'] = $usuario['id'];

            // Atualizar código e expiração no banco
            $update = $pdo->prepare("UPDATE contas_acesso SET codigo_verificacao = :codigo, codigo_verificacao_expires_at = :expira WHERE id = :id");
            $update->bindParam(':codigo', $codigo);
            $update->bindParam(':expira', $expiracao);
            $update->bindParam(':id', $usuario['id'], PDO::PARAM_INT);
            $update->execute();

            // Enviar o e-mail
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
                        <img src='https://upload.wikimedia.org/wikipedia/commons/2/2f/Google_2015_logo.svg' width='150' alt='Logo Google'>
                    </div>
                    <p>Olá, <strong>" . htmlspecialchars($usuario['usuario']) . "</strong>!</p>
                    <p>Recebemos uma solicitação para redefinir sua senha no sistema <strong>Açainhadinhos</strong>.</p>
                    <p>Utilize o código abaixo para prosseguir com a redefinição. Ele será válido por <strong>3 minutos</strong>:</p>
                    <div class='codigo'>{$codigo}</div>
                    <p>Se você não solicitou isso, pode ignorar esta mensagem.</p>
                    <div class='footer'>
                        Este é um e-mail automático. Não responda diretamente a esta mensagem.<br>
                        Suporte: suportecodegeek@gmail.com
                    </div>
                </div>
            </body>
            </html>
            ";

            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
            $headers .= "From: suportecodegeek@gmail.com\r\n";
            $headers .= "Reply-To: suportecodegeek@gmail.com\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();

            if (mail($email, $assunto, $mensagem, $headers)) {
                $emailUrl = urlencode($email);
                $idUrl = urlencode($idSelecionado);
                echo "<script>
                    alert('Código de verificação enviado. Você tem 3 minutos para usá-lo.');
                    window.location.href = '../verificarCodigo.php?id={$idUrl}&email={$emailUrl}';
                </script>";
            } else {
                echo "<script>alert('Erro ao enviar o e-mail.'); history.back();</script>";
            }
        } else {
            // Código já existe e ainda não expirou
            echo "<script>alert('O código ainda não expirou. Tente novamente em breve.'); history.back();</script>";
        }
    } else {
        echo "<script>alert('E-mail não encontrado para essa empresa.'); history.back();</script>";
    }

} catch (PDOException $e) {
    echo "<script>alert('Erro: " . addslashes($e->getMessage()) . "'); history.back();</script>";
}
?>

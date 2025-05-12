<?php
session_start();
require '../conexao.php'; // Importa a conexão PDO

// Recebe os dados do formulário
$data = json_decode(file_get_contents("php://input"), true);
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

try {
    // Busca usuário pelo CPF ou nome de usuário
    $sql = "SELECT username, password FROM usuario WHERE username = :username OR cpf = :username";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verifica a senha
        if (password_verify($password, $usuario["password"])) {
            $_SESSION["user"] = $usuario["username"];
            echo json_encode(["success" => true, "message" => "Login bem-sucedido!"]);
        } else {
            echo json_encode(["success" => false, "message" => "Usuário ou senha incorretos."]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Usuário ou senha incorretos."]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Erro no login: " . $e->getMessage()]);
}
?>

<?php
require_once '../conexao.php'; // Importa a conexão PDO

// Recebe os dados do formulário
$username = $_POST['username'];
$cpf = $_POST['cpf'];
$password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash da senha

// Insere no banco
try {
    $sql = "INSERT INTO usuario (username, cpf, password) VALUES (:username, :cpf, :password)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':cpf', $cpf);
    $stmt->bindParam(':password', $password);
    
    if ($stmt->execute()) {
        echo "Cadastro realizado com sucesso!";
    } else {
        echo "Erro ao cadastrar.";
    }
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
?>

<?php
//POR FAVOR USAR ESSE ESQUEMA DE CONEXAO 

$host = 'localhost'; // ou o IP do servidor de banco de dados
$dbname = 'u922223647_erp'; // Nome do banco de dados
$username = 'u922223647_erp'; // Seu nome de usuário do banco de dados
$password = '*V5z7GqLfa~E'; // Sua senha do banco de dados

try {
    // Cria uma instância PDO para conexão com o banco de dados
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    
    // Configura o PDO para lançar exceções em caso de erro
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
 //   echo "Conexão bem-sucedida!";
} catch (PDOException $e) {
    // Captura o erro e exibe a mensagem
    echo "Erro na conexão: " . $e->getMessage();
}
?>

<?php
// REMOVA QUALQUER ESPAÇO ANTES DESTA LINHA!
header('Content-Type: application/json');

// Configurações do banco de dados - ATUALIZE COM SEUS DADOS
$host = 'localhost'; // ou o IP do servidor de banco de dados
$dbname = 'u920914488_ERP'; // Nome do banco de dados
$username = 'u920914488_ERP'; // Seu nome de usuário do banco de dados
$password = 'N8r=$&Wrs$'; // Sua senha do banco de dados

// Função para erros em JSON
function enviarErro($mensagem) {
    die(json_encode(['success' => false, 'error' => $mensagem]));
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Validar CPF
    $cpf = $_GET['cpf'] ?? enviarErro('CPF não fornecido');
    if (!preg_match('/^\d{11}$/', $cpf)) {
        enviarErro('Formato de CPF inválido');
    }
    
    // Buscar funcionário
    $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE cpf = ?");
    $stmt->execute([$cpf]);
    $funcionario = $stmt->fetch(PDO::FETCH_ASSOC) ?: enviarErro('Funcionário não encontrado');
    
    // Buscar setor
    $stmt = $pdo->prepare("SELECT * FROM setores WHERE nome = ?");
    $stmt->execute([$funcionario['setor']]);
    $setor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Buscar pontos
    $stmt = $pdo->prepare("SELECT * FROM pontos WHERE cpf = ? ORDER BY data DESC");
    $stmt->execute([$cpf]);
    $pontos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Saída JSON
    die(json_encode([
        'success' => true,
        'funcionario' => $funcionario,
        'setor' => $setor,
        'pontos' => $pontos
    ]));

} catch (PDOException $e) {
    enviarErro('Erro no banco de dados: ' . $e->getMessage());
}
?>
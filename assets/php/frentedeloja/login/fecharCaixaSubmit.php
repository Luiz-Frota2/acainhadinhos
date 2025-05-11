<?php
$host = 'localhost'; // ou o IP do servidor de banco de dados
$dbname = 'u920914488_ERP'; // Nome do banco de dados
$username = 'u920914488_ERP'; // Seu nome de usuário do banco de dados
$password = 'K5yJv;lVIKc>'; // Sua senha do banco de dados

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}
// Coleta os dados do POST
$empresa_id = $_POST['idSelecionado'] ?? '';
$responsavel = $_POST['responsavel'] ?? '';
if (!$responsavel || !$empresa_id) {
    die("Erro: Dados obrigatórios ausentes.");
}

try {
    // Atualiza o status, a data e a hora do fechamento
    $stmt = $pdo->prepare("
        UPDATE aberturas
        SET 
            status_abertura = 'fechado',
            data_fechamento = CURDATE(),
            hora_fechamento = CURTIME()
        WHERE responsavel = :responsavel AND status_abertura = 'aberto'
        ORDER BY id DESC LIMIT 1
    ");

    $stmt->execute([
        'responsavel' => $responsavel
    ]);

    echo "<script>alert('Caixa fechado com sucesso.'); window.location.href='../../../../frentedeloja/caixa/index.php?id=" . urlencode($empresa_id) . "';</script>";
    exit;

} catch (PDOException $e) {
    echo "Erro ao fechar o caixa: " . $e->getMessage();
    exit;
}

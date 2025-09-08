<?php
// Ativa exibição de erros para ajudar no debug (remova em produção)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../conexao.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $empresa_id = trim($_POST['idSelecionado'] ?? '');
    $valorAbertura = floatval($_POST['valor_abertura'] ?? 0);
    $responsavel = trim($_POST['responsavel'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    $status = 'aberto';
    $dataRegistro  = trim($_POST['data_registro'] ?? '');

    // Verifica se já existe um caixa aberto para o mesmo CPF e empresa
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM aberturas 
        WHERE cpf_responsavel = :cpf 
          AND status = 'aberto'
          AND empresa_id = :empresa_id
    ");
    $stmt->execute([
        'cpf' => $cpf,
        'empresa_id' => $empresa_id
    ]);

    if ($stmt->fetchColumn() > 0) {
        echo "<script>
            alert('Você já possui um caixa aberto nesta empresa. Para abrir um novo, é necessário fechar o caixa atual primeiro.');
            history.back();
        </script>";
        exit;
    }

    // Verifica se esse CPF já teve um número de caixa nesta empresa
    $stmt = $pdo->prepare("
        SELECT numero_caixa FROM aberturas 
        WHERE cpf_responsavel = :cpf 
          AND empresa_id = :empresa_id
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([
        'cpf' => $cpf,
        'empresa_id' => $empresa_id
    ]);
    $numeroCaixaResponsavel = $stmt->fetchColumn();

    if ($numeroCaixaResponsavel) {
        $numero_caixa = $numeroCaixaResponsavel;
    } else {
        // Se não teve, atribui novo número de caixa com base no maior da empresa
        $stmt = $pdo->prepare("
            SELECT MAX(numero_caixa) FROM aberturas 
            WHERE empresa_id = :empresa_id
        ");
        $stmt->execute(['empresa_id' => $empresa_id]);
        $ultimoNumero = $stmt->fetchColumn();
        $numero_caixa = $ultimoNumero ? $ultimoNumero + 1 : 1;
    }

    // Inserção incluindo abertura_datetime
    $stmt = $pdo->prepare("
        INSERT INTO aberturas (
            valor_abertura,
            responsavel,
            status,
            numero_caixa,
            empresa_id,
            cpf_responsavel,
            abertura_datetime
        ) VALUES (
            :valor_abertura,
            :responsavel,
            :status,
            :numero_caixa,
            :empresa_id,
            :cpf,
            :abertura_datetime
        )
    ");

    $stmt->bindParam(':valor_abertura', $valorAbertura);
    $stmt->bindParam(':responsavel', $responsavel);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':numero_caixa', $numero_caixa);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->bindParam(':cpf', $cpf);
    $stmt->bindParam(':abertura_datetime', $dataRegistro);

    if ($stmt->execute()) {
        echo "<script>
            alert('Caixa aberto com sucesso!');
            window.location.href = '../../../frentedeloja/caixa/index.php?id=" . urlencode($empresa_id) . "';
        </script>";
        exit;
    } else {
        echo "<script>
            alert('Erro ao abrir o caixa.');
            history.back();
        </script>";
    }
}

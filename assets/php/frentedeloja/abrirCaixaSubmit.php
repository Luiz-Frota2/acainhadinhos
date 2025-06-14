<?php

require_once '../conexao.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $empresa_id = $_POST['idSelecionado'] ?? '';
    $valorAbertura = $_POST['valor_abertura'] ?? '0.00';
    $responsavel = $_POST['responsavel'] ?? '';
    $cpf =  $_POST['cpf'] ?? '';
    $status_abertura = $_POST['status_abertura'] ?? '';
    $numeroCaixa = $_POST['numeroCaixa'] ?? '';
    $liquido =  $_POST['valor_abertura'] ?? '';
    

    // Verifica se já existe um caixa aberto para o mesmo responsável E empresa
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM aberturas 
        WHERE responsavel = :responsavel 
          AND status_abertura = 'aberto'
          AND empresa_id = :empresa_id
    ");
    $stmt->execute([
        'responsavel' => $responsavel,
        'empresa_id' => $empresa_id
    ]);
    $caixaAberto = $stmt->fetchColumn();

    if ($caixaAberto > 0) {
        echo "<script>alert('Você já possui um caixa aberto para esta empresa. Feche o caixa atual antes de abrir outro.'); history.back();</script>";
        exit;
    }

    // Verifica se já existe um caixa aberto com o mesmo número, responsável e empresa
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM aberturas 
        WHERE numeroCaixa = :numeroCaixa 
          AND responsavel = :responsavel 
          AND status_abertura = 'aberto'
          AND empresa_id = :empresa_id
    ");
    $stmt->execute([
        'numeroCaixa' => $numeroCaixa,
        'responsavel' => $responsavel,
        'empresa_id' => $empresa_id
    ]);
    $caixaAberto = $stmt->fetchColumn();

    if ($caixaAberto > 0) {
        echo "<script>alert('Este caixa já está aberto para o responsável selecionado nesta empresa.'); history.back();</script>";
        exit;
    }

// Verifica o último número de caixa usado globalmente (por qualquer responsável)
$stmt = $pdo->prepare("
    SELECT MAX(numeroCaixa) FROM aberturas
    WHERE empresa_id = :empresa_id
");
$stmt->execute(['empresa_id' => $empresa_id]);
$ultimoNumeroGlobal = $stmt->fetchColumn();

// Verifica se o responsável já teve número de caixa nesta empresa
$stmt = $pdo->prepare("
    SELECT numeroCaixa FROM aberturas 
    WHERE responsavel = :responsavel 
      AND empresa_id = :empresa_id
    ORDER BY id DESC LIMIT 1
");
$stmt->execute([
    'responsavel' => $responsavel,
    'empresa_id' => $empresa_id
]);
$numeroCaixaResponsavel = $stmt->fetchColumn();

if ($numeroCaixaResponsavel) {
    // Responsável já teve um número de caixa → mantém o mesmo
    $numeroCaixa = $numeroCaixaResponsavel;
} else {
    // Novo responsável na empresa → pega o maior número geral da empresa e soma 1
    $stmt = $pdo->prepare("
        SELECT MAX(numeroCaixa) FROM aberturas 
        WHERE empresa_id = :empresa_id
    ");
    $stmt->execute(['empresa_id' => $empresa_id]);
    $ultimoNumeroEmpresa = $stmt->fetchColumn();

    $numeroCaixa = $ultimoNumeroEmpresa ? $ultimoNumeroEmpresa + 1 : 1;
}


    // Insere os dados
    $stmt = $pdo->prepare("
        INSERT INTO aberturas (valor_abertura, valor_liquido, responsavel, status_abertura, numeroCaixa, empresa_id, cpf)
        VALUES (:valor_abertura, :valor_liquido, :responsavel, :status_abertura, :numeroCaixa, :empresa_id, :cpf)
    ");
    $stmt->bindParam(':valor_abertura', $valorAbertura);
    $stmt->bindParam(':valor_liquido', $liquido);
    $stmt->bindParam(':responsavel', $responsavel);
    $stmt->bindParam(':status_abertura', $status_abertura);
    $stmt->bindParam(':numeroCaixa', $numeroCaixa);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->bindParam(':cpf', $cpf);

    if ($stmt->execute()) {
        echo "<script>
            alert('Dados adicionados com sucesso!');
            window.location.href = '../../../../frentedeloja/caixa/index.php?id=" . urlencode($empresa_id) . "';
        </script>";
        exit();
    } else {
        echo "<script>
            alert('Erro ao cadastrar conta.');
            history.back();
        </script>";
    }
}
?>

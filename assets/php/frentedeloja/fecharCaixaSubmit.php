<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../conexao.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

/* ======= Coleta POST ======= */
$empresa_id      = $_POST['empresa_identificador'] ?? '';
$cpf_funcionario = preg_replace('/\D+/', '', (string)($_POST['cpf_funcionario'] ?? ''));
$saldo_final     = $_POST['saldo_final'] ?? null; // opcional
$dataFechamento  = $_POST['data_registro'] ?? '';

/* ======= Validação ======= */
if ($empresa_id === '' || $cpf_funcionario === '') {
    echo "<script>alert('Erro: dados obrigatórios não foram enviados.'); history.back();</script>";
    exit;
}

/* ======= Fecha o caixa aberto mais recente do CPF nessa empresa ======= */
try {
    // acha o último caixa aberto do CPF nessa empresa
    $stmt = $pdo->prepare("
    SELECT id
    FROM aberturas
    WHERE cpf_responsavel = :cpf
      AND empresa_id = :empresa
      AND status = 'aberto'
    ORDER BY id DESC
    LIMIT 1
  ");
    $stmt->execute([
        ':cpf'     => $cpf_funcionario,
        ':empresa' => $empresa_id
    ]);
    $abertura = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$abertura) {
        echo "<script>
      alert('Nenhum caixa aberto encontrado para esse responsável.');
      window.location.href='../../../frentedeloja/caixa/index.php?id=" . urlencode($empresa_id) . "';
    </script>";
        exit;
    }

    // fecha com a data informada
    $stmtUpdate = $pdo->prepare("
    UPDATE aberturas
    SET status = 'fechado',
        fechamento_datetime = :fechamento
    WHERE id = :id
  ");
    $stmtUpdate->execute([
        ':fechamento' => $dataFechamento !== '' ? $dataFechamento : date('Y-m-d H:i:s'),
        ':id'         => $abertura['id']
    ]);

    echo "<script>
    alert('Caixa fechado com sucesso.');
    window.location.href='../../../frentedeloja/caixa/index.php?id=" . urlencode($empresa_id) . "';
  </script>";
    exit;
} catch (PDOException $e) {
    echo "<script>
    alert('Erro ao fechar o caixa: " . addslashes($e->getMessage()) . "');
    history.back();
  </script>";
    exit;
}
?>
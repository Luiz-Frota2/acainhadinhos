<?php
require_once '../conexao.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

// Coleta os dados do POST
$empresa_id = $_POST['empresa_identificador'] ?? '';
$cpf_funcionario = $_POST['cpf_funcionario'] ?? '';
$saldo_final = $_POST['saldo_final'] ?? null; // Se quiser usar esse valor depois
$dataFechamento  = $_POST['data_registro'] ?? ''; // <-- pegando valor do input

// Validação básica
if (empty($cpf_funcionario) || empty($empresa_id)) {
    echo "<script>
            alert('Erro: dados obrigatórios não foram enviados.');
            history.back();
          </script>";
    exit;
}

try {
    // Busca o caixa aberto mais recente
    $stmt = $pdo->prepare("
        SELECT id FROM aberturas 
        WHERE cpf_responsavel = :cpf_responsavel
          AND empresa_id = :empresa_id
          AND status = 'aberto' 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([
        'cpf_responsavel' => $cpf_funcionario,
        'empresa_id' => $empresa_id
    ]);
    $abertura = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$abertura) {
        echo "<script>
                alert('Nenhum caixa aberto encontrado para esse responsável.');
                window.location.href='../../../frentedeloja/caixa/index.php?id=" . urlencode($empresa_id) . "';
              </script>";
        exit;
    }

    // Atualiza o fechamento com a data enviada
    $stmtUpdate = $pdo->prepare("
        UPDATE aberturas
        SET 
            status = 'fechado',
            fechamento_datetime = :data_fechamento
        WHERE id = :id
    ");
    $stmtUpdate->execute([
        'data_fechamento' => $dataFechamento,
        'id' => $abertura['id']
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
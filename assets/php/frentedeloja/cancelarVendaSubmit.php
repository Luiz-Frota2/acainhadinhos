<?php

require_once '../conexao.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

$idVenda = isset($_GET['id']) ? intval($_GET['id']) : 0;
$empresa_id = $_POST['idSelecionado'] ?? '';

if (!$idVenda || !$empresa_id) {
    echo "<script>
            alert('ID inválido.');
            history.back();
          </script>";
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Buscar os itens da venda para repor estoque
    $stmtItens = $pdo->prepare("SELECT * FROM itens_venda WHERE venda_id = :venda_id");
    $stmtItens->execute([':venda_id' => $idVenda]);
    $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

    if (!$itens) {
        throw new Exception("Nenhum item encontrado para esta venda.");
    }

    // 2. Buscar dados da venda rápida (valor total, cpf_responsavel, id_caixa) para atualizar abertura
    $stmtVenda = $pdo->prepare("SELECT total, cpf_responsavel, id_caixa FROM vendas WHERE id = :id");
    $stmtVenda->execute([':id' => $idVenda]);
    $venda = $stmtVenda->fetch(PDO::FETCH_ASSOC);

    if (!$venda) {
        throw new Exception("Venda não encontrada.");
    }

    $valorTotalVenda = $venda['total'];
    $cpfResponsavel = $venda['cpf_responsavel'];
    $idCaixa = $venda['id_caixa'];

    // 3. Repor os produtos no estoque
    $stmtUpdateEstoque = $pdo->prepare("
        UPDATE estoque 
        SET quantidade_produto = quantidade_produto + :quantidade 
        WHERE empresa_id = :empresa_id AND codigo_produto = :codigo_produto
    ");

    foreach ($itens as $item) {
        $stmtUpdateEstoque->execute([
            ':quantidade' => $item['quantidade'],
            ':empresa_id' => $empresa_id,
            ':codigo_produto' => $item['id_produto']
        ]);
    }

    // 4. Atualizar a tabela aberturas - diminuir valor_total e quantidade_vendas
    // Buscar abertura aberta para o cpf, empresa e caixa
    $stmtAbertura = $pdo->prepare("
        SELECT id, valor_total, quantidade_vendas 
        FROM aberturas 
        WHERE cpf_responsavel = :cpf 
          AND empresa_id = :empresa_id 
          AND numero_caixa = :num_caixa
          AND status = 'aberto'
        ORDER BY id DESC LIMIT 1
    ");
    $stmtAbertura->execute([
        ':cpf' => $cpfResponsavel,
        ':empresa_id' => $empresa_id,
        ':num_caixa' => $idCaixa
    ]);
    $abertura = $stmtAbertura->fetch(PDO::FETCH_ASSOC);

    if ($abertura) {
        $novoValorTotal = $abertura['valor_total'] - $valorTotalVenda;
        if ($novoValorTotal < 0) $novoValorTotal = 0;

        $novaQtdVendas = $abertura['quantidade_vendas'] - 1;
        if ($novaQtdVendas < 0) $novaQtdVendas = 0;

        $stmtUpdateAbertura = $pdo->prepare("
            UPDATE aberturas 
            SET valor_total = :valor_total, quantidade_vendas = :quantidade_vendas
            WHERE id = :id
        ");
        $stmtUpdateAbertura->execute([
            ':valor_total' => $novoValorTotal,
            ':quantidade_vendas' => $novaQtdVendas,
            ':id' => $abertura['id']
        ]);
    }

    // 5. Excluir itens da venda
    $stmtDeleteItens = $pdo->prepare("DELETE FROM itens_venda WHERE venda_id = :venda_id");
    $stmtDeleteItens->execute([':venda_id' => $idVenda]);

    // 6. Excluir venda rápida
    $stmtDeleteVenda = $pdo->prepare("DELETE FROM vendas WHERE id = :id");
    $stmtDeleteVenda->execute([':id' => $idVenda]);

    $pdo->commit();

    // Redirecionar de volta com sucesso
    echo "<script>
             alert('Venda cancelada com sucesso.');
             window.location.href = '../../../frentedeloja/caixa/cancelarVenda.php?id=" . urlencode($empresa_id) . "';
          </script>";
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    echo "<script>
            alert('Erro ao cancelar venda: " . addslashes($e->getMessage()) . "');
            history.back();
          </script>";
}

?>
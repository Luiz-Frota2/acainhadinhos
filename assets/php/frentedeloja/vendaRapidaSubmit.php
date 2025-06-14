<?php 

require_once '../conexao.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $produtos = $_POST['produtos'] ?? [];
    $total = $_POST['totalTotal'] ?? '0.00';
    $empresa_id = $_POST['idSelecionado'] ?? '';
    $id_caixa = $_POST['id_caixa'] ?? '';
    $responsavel = $_POST['responsavel'] ?? ''; // Usuário logado
    $quantidade = $_POST['quantidade'] ?? [];
    $precos = $_POST['precos'] ?? [];
    $pagamento = $_POST['forma_pagamento'] ?? '';

$itensFormatados = [];
for ($i = 0; $i < count($produtos); $i++) {
    $nome = $produtos[$i];
    $qtd = $quantidade[$i];
    $preco = $precos[$i];
    $itensFormatados[] = "$nome (x$qtd) - R$" . number_format($preco, 2, ',', '.');
}

$nomesProdutos = implode(', ', $itensFormatados);

    // Insere no banco de vendas
    $stmt = $pdo->prepare("INSERT INTO vendarapida (produtos, total, empresa_id, id_caixa, forma_pagamento) VALUES (:produtos, :total, :empresa_id, :id_caixa, :forma_pagamento)");
    $stmt->bindParam(':produtos', $nomesProdutos);
    $stmt->bindParam(':total', $total);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->bindParam(':id_caixa', $id_caixa);
    $stmt->bindParam(':forma_pagamento', $pagamento);
    $stmt->execute();

$vendaId = $pdo->lastInsertId(); // ID da venda principal

// 2. Inserir itens da venda
// 2. Inserir itens da venda
for ($i = 0; $i < count($produtos); $i++) {
    $nome = $produtos[$i];
    $qtd = $quantidade[$i];
    $preco = $precos[$i];
    $precoTotal = $qtd * $preco;

    $stmtItem = $pdo->prepare("INSERT INTO itens_venda 
        (venda_id, id_caixa, empresa_id, nome_produto, quantidade, preco_unitario, preco_total) 
        VALUES (:venda_id, :id_caixa, :empresa_id, :nome_produto, :quantidade, :preco_unitario, :preco_total)");

    $stmtItem->execute([
        'venda_id' => $vendaId,
        'id_caixa' => $id_caixa,
        'empresa_id' => $empresa_id,
        'nome_produto' => $nome,
        'quantidade' => $qtd,
        'preco_unitario' => $preco,
        'preco_total' => $precoTotal
    ]);
}


    if ($stmt) {

        // Atualiza valor_total e quantidade_venda
        $stmtUpdate = $pdo->prepare("
            UPDATE aberturas 
            SET 
                quantidade_venda = quantidade_venda + 1,
                valor_total = valor_total + :total
            WHERE responsavel = :responsavel 
              AND empresa_id = :empresa_id 
              AND status_abertura = 'aberto'
        ");
        $stmtUpdate->execute([
            'total' => $total,
            'responsavel' => $responsavel,
            'empresa_id' => $empresa_id
        ]);

     
        $stmtUpdateLiquido = $pdo->prepare("
            UPDATE aberturas 
            SET valor_liquido = valor_abertura + valor_total
            WHERE responsavel = :responsavel 
              AND empresa_id = :empresa_id 
              AND status_abertura = 'aberto'
        ");
        $stmtUpdateLiquido->execute([
            'responsavel' => $responsavel,
            'empresa_id' => $empresa_id
        ]);

        echo "<script>alert('Dados adicionados com sucesso!');
                window.location.href = '../../../../frentedeloja/caixa/vendaRapida.php?id=" . urlencode($empresa_id) . "';</script>";
        exit();
    } else {
        echo "<script>
                alert('Erro ao cadastrar venda.');
                history.back();
              </script>";
    }
}
?>

<?php
session_start();

header('Content-Type: application/json');

// Aceita apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status'   => 'erro',
        'mensagem' => 'Método não permitido.'
    ]);
    exit;
}

try {
    // sua conexão, que você já usa nos outros arquivos
    require './assets/php/conexao.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'   => 'erro',
        'mensagem' => 'Falha na conexão com o banco de dados.'
    ]);
    exit;
}

// Dados recebidos do carrinho.js / carrinho.php
$nome              = trim($_POST['nome'] ?? '');
$telefone          = trim($_POST['telefone'] ?? '');
$endereco          = trim($_POST['endereco'] ?? '');
$forma_pagamento   = trim($_POST['forma_pagamento'] ?? '');
$detalhe_pagamento = trim($_POST['detalhe_pagamento'] ?? '');
$total             = isset($_POST['total']) ? floatval($_POST['total']) : 0;

// Itens (vem como JSON da sessão do carrinho)
$itens_json = $_POST['itens_json'] ?? '[]';
$itens      = json_decode($itens_json, true);
if (!is_array($itens)) {
    $itens = [];
}

// Validação simples
if ($nome === '' || $telefone === '' || $endereco === '' || $forma_pagamento === '') {
    http_response_code(400);
    echo json_encode([
        'status'   => 'erro',
        'mensagem' => 'Dados obrigatórios não informados.'
    ]);
    exit;
}

try {
    $pdo->beginTransaction();

    // ========== 1) INSERE NA TABELA RASCUNHO ==========
    $sqlRascunho = "INSERT INTO rascunho
        (nome_cliente, telefone_cliente, endereco, forma_pagamento, detalhe_pagamento, total)
        VALUES (:nome, :telefone, :endereco, :forma_pagamento, :detalhe_pagamento, :total)";
    $stmt = $pdo->prepare($sqlRascunho);
    $stmt->execute([
        ':nome'              => $nome,
        ':telefone'          => $telefone,
        ':endereco'          => $endereco,
        ':forma_pagamento'   => $forma_pagamento,
        ':detalhe_pagamento' => $detalhe_pagamento,
        ':total'             => $total
    ]);

    // ID do rascunho criado
    $pedidoId = $pdo->lastInsertId();

    // ========== 2) INSERE ITENS NA TABELA RASCUNHO_ITENS ==========
    if (!empty($itens)) {
        $sqlItem = "INSERT INTO rascunho_itens
            (pedido_id, nome_item, quantidade, preco_unitario, observacao, opcionais_json)
            VALUES (:pedido_id, :nome_item, :quantidade, :preco_unitario, :observacao, :opcionais_json)";
        $stmtItem = $pdo->prepare($sqlItem);

        foreach ($itens as $item) {
            if (!is_array($item)) continue;

            $nomeItem   = $item['nome'] ?? 'Item';
            $quantidade = isset($item['quant']) ? (int)$item['quant'] : 1;
            $precoTotal = isset($item['preco']) ? (float)$item['preco'] : 0.0;
            $observacao = $item['observacao'] ?? '';

            // Preço unitário (total do item dividido pela quantidade)
            $precoUnitario = $quantidade > 0 ? ($precoTotal / $quantidade) : $precoTotal;

            // Junta opcionais simples + seleção em um JSON
            $opcSimples = $item['opc_simples'] ?? [];
            $opcSelecao = $item['opc_selecao'] ?? [];
            $opcionais  = [
                'simples' => is_array($opcSimples) ? $opcSimples : [],
                'selecao' => is_array($opcSelecao) ? $opcSelecao : []
            ];
            $opcionaisJson = json_encode($opcionais, JSON_UNESCAPED_UNICODE);

            $stmtItem->execute([
                ':pedido_id'      => $pedidoId,
                ':nome_item'      => $nomeItem,
                ':quantidade'     => $quantidade,
                ':preco_unitario' => $precoUnitario,
                ':observacao'     => $observacao,
                ':opcionais_json' => $opcionaisJson
            ]);
        }
    }

    $pdo->commit();

    echo json_encode([
        'status'    => 'ok',
        'mensagem'  => 'Rascunho salvo com sucesso.',
        'pedido_id' => $pedidoId
    ]);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'status'   => 'erro',
        'mensagem' => 'Erro ao salvar rascunho.',
        'detalhe'  => $e->getMessage()
    ]);
    exit;
}

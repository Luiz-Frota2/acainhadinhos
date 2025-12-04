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

// Empresa (vem via GET na URL salvar_rascunho.php?empresa=...)
$empresaID = $_GET['empresa'] ?? null;
if (!$empresaID) {
    http_response_code(400);
    echo json_encode([
        'status'   => 'erro',
        'mensagem' => 'Empresa não informada.'
    ]);
    exit;
}

// Conexão com o banco
try {
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

// NOVO: identificador do cliente (por sessão/aparelho)
$clienteSessao     = trim($_POST['cliente_sessao'] ?? '');
if (strlen($clienteSessao) > 64) {
    $clienteSessao = substr($clienteSessao, 0, 64);
}

// NOVO: taxa de entrega (se não enviar nada, vira 0)
$taxaEntrega       = isset($_POST['taxa_entrega']) ? floatval($_POST['taxa_entrega']) : 0;

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
    // Incluindo empresa_id, cliente_sessao e taxa_entrega
    $sqlRascunho = "INSERT INTO rascunho
        (empresa_id, cliente_sessao, nome_cliente, telefone_cliente, endereco, forma_pagamento, detalhe_pagamento, total, taxa_entrega)
        VALUES (:empresa_id, :cliente_sessao, :nome, :telefone, :endereco, :forma_pagamento, :detalhe_pagamento, :total, :taxa_entrega)";
    $stmt = $pdo->prepare($sqlRascunho);
    $stmt->execute([
        ':empresa_id'        => $empresaID,
        ':cliente_sessao'    => $clienteSessao,
        ':nome'              => $nome,
        ':telefone'          => $telefone,
        ':endereco'          => $endereco,
        ':forma_pagamento'   => $forma_pagamento,
        ':detalhe_pagamento' => $detalhe_pagamento,
        ':total'             => $total,
        ':taxa_entrega'      => $taxaEntrega
    ]);

    // ID do rascunho criado
    $pedidoId = $pdo->lastInsertId();

    // ========== 2) INSERE ITENS NA TABELA RASCUNHO_ITENS ==========
    if (!empty($itens)) {
        $sqlItem = "INSERT INTO rascunho_itens
            (empresa_id, pedido_id, nome_item, quantidade, preco_unitario, observacao, opcionais_json)
            VALUES (:empresa_id, :pedido_id, :nome_item, :quantidade, :preco_unitario, :observacao, :opcionais_json)";
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
                ':empresa_id'     => $empresaID,
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

    // LIMPAR CARRINHO DA SESSÃO DEPOIS DE FINALIZAR (apenas em sucesso)
    if (isset($_SESSION['carrinho'])) {
        unset($_SESSION['carrinho']);
    }

    echo json_encode([
        'status'        => 'ok',
        'mensagem'      => 'Rascunho salvo com sucesso.',
        'pedido_id'     => $pedidoId,
        'taxa_entrega'  => $taxaEntrega
    ]);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // NÃO limpa o carrinho em caso de erro:
    http_response_code(500);
    echo json_encode([
        'status'   => 'erro',
        'mensagem' => 'Erro ao salvar rascunho. Tente novamente.',
        'detalhe'  => $e->getMessage()
    ]);
    exit;
}

?>
<?php
session_start();
require './assets/php/conexao.php'; // AJUSTE SE NECESSÁRIO

header("Content-Type: application/json");

// PROTEÇÃO
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["erro" => "Método inválido"]);
    exit;
}

// Dados recebidos do carrinho.js
$nome      = $_POST['nome'] ?? '';
$telefone  = $_POST['telefone'] ?? '';
$endereco  = $_POST['endereco'] ?? '';
$pagamento = $_POST['pagamento'] ?? '';
$detalhe   = $_POST['detalhe_pagamento'] ?? '';
$total     = $_POST['total'] ?? 0;
$itens     = json_decode($_POST['itens_json'] ?? '[]', true);

// Validação básica
if ($nome == "" || $telefone == "" || $endereco == "" || empty($itens)) {
    echo json_encode(["erro" => "Dados incompletos"]);
    exit;
}

// =============== 1) SALVAR PEDIDO EM "RASCUNHO" ===============
$sql = "INSERT INTO rascunho
        (nome_cliente, telefone_cliente, endereco, forma_pagamento, detalhe_pagamento, total, data_pedido)
        VALUES (?, ?, ?, ?, ?, ?, NOW())";
$stmt = $pdo->prepare($sql);
$stmt->execute([$nome, $telefone, $endereco, $pagamento, $detalhe, $total]);

$pedido_id = $pdo->lastInsertId();


// =============== 2) SALVAR ITENS EM "RASCUNHO_ITENS" ===============
$sql_item = "INSERT INTO rascunho_itens
             (pedido_id, nome_item, quantidade, preco_unitario, observacao, opcionais_json)
             VALUES (?, ?, ?, ?, ?, ?)";
$stmt_item = $pdo->prepare($sql_item);

foreach ($itens as $it) {
    $stmt_item->execute([
        $pedido_id,
        $it['nome'],
        $it['quant'],
        $it['preco_unit'],
        $it['observacao'],
        json_encode($it['opcionais'], JSON_UNESCAPED_UNICODE)
    ]);
}


// =============== 3) MONTAR MENSAGEM PARA ENVIAR AO CLIENTE ===============
$mensagem = "";
$mensagem .= "NOVO PEDIDO - AÇAIDINHOS%0A%0A";
$mensagem .= "Olá " . urlencode($nome) . ", seu pedido foi registrado.%0A%0A";

$mensagem .= "ITENS DO PEDIDO:%0A";

foreach ($itens as $item) {
    $mensagem .= "- " . $item['quant'] . "x " . urlencode($item['nome']) . "%0A";

    if (!empty($item['opcionais'])) {
        $mensagem .= "  Adicionais:%0A";
        foreach ($item['opcionais'] as $opc) {
            $preco = number_format($opc['preco'], 2, ',', '.');
            $mensagem .= "   - " . urlencode($opc['nome']) . " (+R$ {$preco})%0A";
        }
    }

    if (!empty($item['observacao'])) {
        $mensagem .= "  Observação: " . urlencode($item['observacao']) . "%0A";
    }

    $mensagem .= "%0A";
}

$mensagem .= "TOTAL:%0A";
$mensagem .= "R$ " . number_format($total, 2, ',', '.') . "%0A%0A";

$mensagem .= "ENDEREÇO DE ENTREGA:%0A";
$mensagem .= urlencode($endereco) . "%0A%0A";

$mensagem .= "FORMA DE PAGAMENTO:%0A";
$mensagem .= urlencode($pagamento) . "%0A%0A";

$mensagem .= "Agradecemos seu pedido!";


// =============== 4) NÚMEROS ===============
$numero_empresa  = "5597981434585"; // Empresa envia
$numero_cliente  = "55" . preg_replace('/[^0-9]/', '', $telefone); // Cliente recebe

// Link WhatsApp da empresa -> cliente
$link_whats = "https://wa.me/" . $numero_cliente . "?text=" . $mensagem;


// =============== 5) RETORNAR PARA O JAVASCRIPT ===============
echo json_encode([
    "status" => "ok",
    "redirect" => $link_whats,
    "pedido_id" => $pedido_id
]);

exit;
?>

<?php
session_start();
require './assets/php/conexao.php';
header('Content-Type: application/json');

// ===========================
// CONFIGURAÇÕES WHATSAPP META
// ===========================
$WHATSAPP_TOKEN = "COLOQUE_AQUI_SEU_TOKEN_PERMANENTE";
$PHONE_NUMBER_ID = "COLOQUE_AQUI_SEU_PHONE_NUMBER_ID";

// ===========================
// VALIDAR REQUISIÇÃO
// ===========================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["erro" => "Método inválido"]);
    exit;
}

$nome      = $_POST['nome'] ?? '';
$telefone  = $_POST['telefone'] ?? '';
$endereco  = $_POST['endereco'] ?? '';
$pagamento = $_POST['pagamento'] ?? '';
$total     = $_POST['total'] ?? 0;
$itens     = json_decode($_POST['itens_json'] ?? '[]', true);

if ($nome == "" || $telefone == "" || $endereco == "" || empty($itens)) {
    echo json_encode(["erro" => "Dados incompletos"]);
    exit;
}

// ===========================
// SALVAR PEDIDO EM RASCUNHO
// ===========================
$sql = "INSERT INTO rascunho (nome_cliente, telefone_cliente, endereco, forma_pagamento, total, data_pedido)
        VALUES (?, ?, ?, ?, ?, NOW())";
$stmt = $pdo->prepare($sql);
$stmt->execute([$nome, $telefone, $endereco, $pagamento, $total]);

$pedido_id = $pdo->lastInsertId();

// ===========================
// SALVAR ITENS
// ===========================
$sqlItem = "INSERT INTO rascunho_itens (pedido_id, nome_item, quantidade, preco_unitario)
            VALUES (?, ?, ?, ?)";
$stmtItem = $pdo->prepare($sqlItem);

foreach ($itens as $item) {
    $stmtItem->execute([
        $pedido_id,
        $item['nome'],
        $item['quant'],
    ]);
}

// ===========================
// MONTAR MENSAGEM
// ===========================
$mensagem = "Olá *$nome*!\nSeu pedido foi registrado.\n\n";
$mensagem .= "📦 *Itens:*\n";

foreach ($itens as $it) {
    $mensagem .= "- {$it['quant']}x {$it['nome']}\n";
}

$mensagem .= "\n💰 *Total:* R$ " . number_format($total, 2, ',', '.') . "\n";
$mensagem .= "🏠 *Endereço:* $endereco\n";
$mensagem .= "💳 *Pagamento:* $pagamento\n\n";
$mensagem .= "Obrigado por pedir conosco!";

// ===========================
// ENVIO AUTOMÁTICO VIA META
// ===========================
$numero_cliente = "55" . preg_replace('/\D/', '', $telefone);

$url = "https://graph.facebook.com/v20.0/$PHONE_NUMBER_ID/messages";

$data = [
    "messaging_product" => "whatsapp",
    "to" => $numero_cliente,
    "type" => "text",
    "text" => ["body" => $mensagem]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $WHATSAPP_TOKEN",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$res = curl_exec($ch);
curl_close($ch);

// Retorno para o JS
echo json_encode([
    "status" => "ok",
    "pedido_id" => $pedido_id,
    "api_response" => $res
]);
exit;
?>

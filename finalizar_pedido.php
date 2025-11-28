<?php
session_start();
require './assets/php/conexao.php'; // AJUSTAR CAMINHO SE NECESSÁRIO

header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["erro" => "Método inválido"]);
    exit;
}

// Dados do cliente
$nome      = $_POST['nome'];
$telefone  = $_POST['telefone'];
$endereco  = $_POST['endereco'];
$pagamento = $_POST['pagamento'];
$detalhe   = $_POST['detalhe_pagamento'] ?? '';
$total     = $_POST['total'];

// Itens (vem do JS)
$itens = json_decode($_POST['itens_json'], true);

// Salvar pedido
$sql = "INSERT INTO pedidos (nome_cliente, telefone_cliente, endereco, forma_pagamento, detalhe_pagamento, total)
        VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $pdo->prepare($sql);
$stmt->execute([$nome, $telefone, $endereco, $pagamento, $detalhe, $total]);

$pedido_id = $pdo->lastInsertId();

// Salvar itens
$sql_item = "INSERT INTO pedidos_itens (pedido_id, nome_item, quantidade, preco_unitario, observacao, opcionais_json)
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

// Montar mensagem
$mensagem = "";
$mensagem .= "NOVO PEDIDO - AÇAIDINHOS%0A%0A";
$mensagem .= "Olá " . urlencode($nome) . ", seu pedido foi registrado com sucesso.%0A%0A";

$mensagem .= "ITENS DO PEDIDO:%0A";
foreach ($itens as $item) {
    $mensagem .= "- " . $item['quant'] . "x " . urlencode($item['nome']) . "%0A";

    if (!empty($item['opcionais'])) {
        $mensagem .= "  Adicionais:%0A";
        foreach ($item['opcionais'] as $opc) {
            $mensagem .= "   - " . urlencode($opc['nome']) . " (+R$ " . number_format($opc['preco'],2,',','.') . ")%0A";
        }
    }

    if (!empty($item['observacao'])) {
        $mensagem .= "  Observação: " . urlencode($item['observacao']) . "%0A";
    }

    $mensagem .= "%0A";
}

$mensagem .= "TOTAL:%0A";
$mensagem .= "R$ " . number_format($total, 2, ',', '.') . "%0A%0A";

$mensagem .= "ENDEREÇO:%0A" . urlencode($endereco) . "%0A%0A";

$mensagem .= "FORMA DE PAGAMENTO:%0A" . urlencode($pagamento) . "%0A%0A";

$mensagem .= "Obrigado por escolher a Açaidinhos!";

// Número da empresa
$numero_empresa = "5597981434585"; 

// Numero do cliente
$numero_cliente = "55" . preg_replace('/[^0-9]/', '', $telefone);

// Link para enviar mensagem da empresa para o cliente
$link_whatsapp = "https://wa.me/" . $numero_cliente . "?text=" . $mensagem;

// Retorna para o JS o link que deve ser aberto
echo json_encode([
    "status" => "ok",
    "redirect" => $link_whatsapp,
    "pedido_id" => $pedido_id
]);
exit;
?>

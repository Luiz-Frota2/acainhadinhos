<?php
session_start();
require './assets/php/conexao.php'; // AJUSTE SE NECESSÁRIO

header("Content-Type: application/json");

// SEMPRE CAPTURAR ERROS
try {

    // PROTEÇÃO
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(["erro" => "Método inválido"]);
        exit;
    }

    // Dados recebidos
    $nome      = $_POST['nome'] ?? '';
    $telefone  = $_POST['telefone'] ?? '';
    $endereco  = $_POST['endereco'] ?? '';
    $pagamento = $_POST['pagamento'] ?? '';
    $detalhe   = $_POST['detalhe_pagamento'] ?? '';
    $total     = floatval($_POST['total'] ?? 0);
    $itensRaw  = $_POST['itens_json'] ?? '[]';

    // Decodificar itens
    $itens = json_decode($itensRaw, true);

    if (!is_array($itens)) {
        echo json_encode(["erro" => "Itens inválidos. JSON corrompido."]);
        exit;
    }

    // Validação
    if ($nome == "" || $telefone == "" || $endereco == "" || empty($itens)) {
        echo json_encode(["erro" => "Dados incompletos"]);
        exit;
    }


    // ===========================================
    // 1) SALVAR NO BANCO EM RASCUNHO
    // ===========================================

    $sql = "INSERT INTO rascunho
            (nome_cliente, telefone_cliente, endereco, forma_pagamento, detalhe_pagamento, total, data_pedido)
            VALUES (?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nome, $telefone, $endereco, $pagamento, $detalhe, $total]);

    $pedido_id = $pdo->lastInsertId();


    // ===========================================
    // 2) SALVAR ITENS
    // ===========================================

    $sqlItem = "INSERT INTO rascunho_itens
                (pedido_id, nome_item, quantidade, preco_unitario, observacao, opcionais_json)
                VALUES (?, ?, ?, ?, ?, ?)";

    $stmtItem = $pdo->prepare($sqlItem);

    foreach ($itens as $it) {

        $opc = json_encode($it['opcionais'] ?? [], JSON_UNESCAPED_UNICODE);

        $stmtItem->execute([
            $pedido_id,
            $it['nome'],
            $it['quant'],
            $it['preco_unit'],
            $it['observacao'] ?? "",
            $opc
        ]);
    }


    // ===========================================
    // 3) MONTAR MENSAGEM PARA CLIENTE
    // ===========================================

    $mensagem  = "NOVO PEDIDO - AÇAIDINHOS%0A%0A";
    $mensagem .= "Olá " . urlencode($nome) . ", seu pedido foi registrado.%0A%0A";

    $mensagem .= "ITENS DO PEDIDO:%0A";

    foreach ($itens as $item) {

        $mensagem .= "- {$item['quant']}x " . urlencode($item['nome']) . "%0A";

        if (!empty($item['opcionais'])) {
            $mensagem .= "  Adicionais:%0A";
            foreach ($item['opcionais'] as $opc) {
                $p = number_format($opc['preco'], 2, ',', '.');
                $mensagem .= "   - " . urlencode($opc['nome']) . " (+R$ {$p})%0A";
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


    // ===========================================
    // 4) MONTA LINK DO WHATSAPP
    // ===========================================

    $numero_cliente  = "55" . preg_replace('/\D/', '', $telefone);
    $link_whats = "https://wa.me/{$numero_cliente}?text={$mensagem}";


    // ===========================================
    // 5) RETORNO JSON
    // ===========================================

    echo json_encode([
        "status" => "ok",
        "redirect" => $link_whats,
        "pedido_id" => $pedido_id
    ]);

    exit;


} catch (Exception $e) {

    // CAPTURAR O ERRO REAL
    echo json_encode([
        "erro" => "Erro interno no servidor",
        "detalhes" => $e->getMessage()
    ]);
    exit;
}
?>

<?php
session_start();

// CAPTURA CAMPOS PRINCIPAIS
$nome = $_POST['nome'] ?? '';
$observacao = $_POST['observacao'] ?? '';
$quantidade = intval($_POST['quantidade_itens'] ?? 1);
$total = floatval($_POST['total_itens'] ?? 0);

// ===== CAPTURAR OPCIONAIS SIMPLES =====
$opcionaisSimples = [];
if (!empty($_POST['opcionais_simples'])) {
    foreach ($_POST['opcionais_simples'] as $opc) {
        $opcionaisSimples[] = [
            "nome" => $opc["nome"],
            "preco" => $opc["preco"]
        ];
    }
}

// ===== CAPTURAR OPCIONAIS DAS SELEÇÕES =====
$opcionaisSelecionados = [];
if (!empty($_POST['opcao_selecao'])) {
    foreach ($_POST['opcao_selecao'] as $opc) {
        $opcionaisSelecionados[] = [
            "nome" => $opc["nome"],
            "preco" => $opc["preco"]
        ];
    }
}

// MONTA ITEM FINAL
$item = [
    "nome" => $nome,
    "quant" => $quantidade,
    "preco" => $total,
    "observacao" => $observacao,
    "opc_simples" => $opcionaisSimples,
    "opc_selecao" => $opcionaisSelecionados
];

// ADICIONA AO CARRINHO
$_SESSION['carrinho'][] = $item;

// VOLTA PARA O CARRINHO
header("Location: carrinho.php");
exit;

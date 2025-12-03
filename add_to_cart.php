<?php
session_start();

require './assets/php/conexao.php';
/* ===========================================
   1. PEGAR EMPRESA E PRODUTO DA URL
   =========================================== */
$empresaID   = $_GET['empresa'] ?? null;

if (!$empresaID) {
    die('Empresa não informada.');
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Trata campos básicos
    $nome   = $_POST['nome']           ?? '';
    $preco  = isset($_POST['total_itens']) ? floatval($_POST['total_itens']) : 0;
    $quant  = isset($_POST['quantidade_itens']) ? intval($_POST['quantidade_itens']) : 1;
    $obs    = $_POST['observacao']     ?? '';

    // Trata opcionais (JSON vindo do item.js)
    $opc_simples_json = $_POST['opc_simples'] ?? '[]';
    $opc_selecao_json = $_POST['opc_selecao'] ?? '[]';

    $opc_simples = json_decode($opc_simples_json, true);
    $opc_selecao = json_decode($opc_selecao_json, true);

    if (!is_array($opc_simples)) $opc_simples = [];
    if (!is_array($opc_selecao)) $opc_selecao = [];

    $item = [
        'nome'         => $nome,
        'preco'        => $preco,
        'quant'        => $quant,
        'observacao'   => $obs,
        'opc_simples'  => $opc_simples,
        'opc_selecao'  => $opc_selecao
    ];

    if (!isset($_SESSION['carrinho']) || !is_array($_SESSION['carrinho'])) {
        $_SESSION['carrinho'] = [];
    }

    $_SESSION['carrinho'][] = $item;
}

header('Location: carrinho.php?empresa="urlencode($empresaID)"');
exit;
?>

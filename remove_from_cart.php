<?php
session_start();
require './assets/php/conexao.php';

/* ===========================================
   1. PEGAR EMPRESA DA URL
   =========================================== */
$empresaID = $_GET['empresa'] ?? null;

if (!$empresaID) {
    die('Empresa não informada.');
}

if (isset($_POST['index'])) {
    $index = (int)$_POST['index'];

    if (isset($_SESSION['carrinho'][$index])) {
        unset($_SESSION['carrinho'][$index]);
        $_SESSION['carrinho'] = array_values($_SESSION['carrinho']); // reorganiza os índices

        $_SESSION['flash_msg']  = 'Item removido do carrinho.';
        $_SESSION['flash_tipo'] = 'error';
    } else {
        $_SESSION['flash_msg']  = 'Não foi possível remover o item.';
        $_SESSION['flash_tipo'] = 'error';
    }
} else {
    $_SESSION['flash_msg']  = 'Nenhum item selecionado para remoção.';
    $_SESSION['flash_tipo'] = 'error';
}

header("Location: carrinho.php?empresa={$empresaID}");
exit;

?>
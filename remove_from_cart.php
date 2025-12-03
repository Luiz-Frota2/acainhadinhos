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

if (isset($_POST['index'])) {
    $index = $_POST['index'];

    if (isset($_SESSION['carrinho'][$index])) {
        unset($_SESSION['carrinho'][$index]);
        $_SESSION['carrinho'] = array_values($_SESSION['carrinho']); // reorganiza os índices
    }
}

header("Location: carrinho.php?empresa='urlencode($empresaID)'");
exit;
?>

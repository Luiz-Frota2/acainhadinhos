<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $produto = [
        'nome' => $_POST['nome'],
        'preco' => $_POST['total_itens'],
        'quant' => $_POST['quantidade_itens'],
        'observacao' => $_POST['observacao']
    ];

    if (!isset($_SESSION['carrinho'])) {
        $_SESSION['carrinho'] = [];
    }

    $_SESSION['carrinho'][] = $produto;
}

header('Location: carrinho.php');
exit;
?>

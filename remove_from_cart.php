<?php
session_start();

if (isset($_POST['index'])) {
    $index = $_POST['index'];

    if (isset($_SESSION['carrinho'][$index])) {
        unset($_SESSION['carrinho'][$index]);
        $_SESSION['carrinho'] = array_values($_SESSION['carrinho']); // reorganiza os índices
    }
}

header('Location: carrinho.php');
exit;
?>

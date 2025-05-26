<?php
session_start();

// Captura o ID selecionado da URL
$idSelecionado = $_GET['id'] ?? '';

// Destroi todos os dados da sessão
session_unset();
session_destroy();

// Redireciona para o login com o ID selecionado (se existir)
if (!empty($idSelecionado)) {
    header("Location: ./index.php?id=$idSelecionado");
} else {
    header("Location: ./index.php?id=$idSelecionado");
}
exit;
?>

<?php
session_start();

// Recebe os parâmetros da URL
$idSelecionado = $_GET['id'] ?? '';
$email = $_GET['email'] ?? '';

// Valida os parâmetros
if (!$idSelecionado || !$email) {
    echo "<script>alert('Parâmetros ausentes.'); history.back();</script>";
    exit;
}

if (str_starts_with($idSelecionado, 'principal_')) {
    $empresa_id = 1;
} elseif (str_starts_with($idSelecionado, 'filial_')) {
    $empresa_id = (int) str_replace('filial_', '', $idSelecionado);
} else {
    echo "<script>alert('Empresa não identificada!'); history.back();</script>";
    exit;
}

// Verifica se o código de verificação está presente na sessão
$codigoVerificacao = $_SESSION['codigo_verificacao'] ?? null;
$usuarioIdParaReset = $_SESSION['usuario_para_reset'] ?? null;

// Caso o código não tenha sido gerado ou a sessão tenha expirado
if (!$codigoVerificacao || !$usuarioIdParaReset) {
    echo "<script>alert('Sessão expirada ou código inválido.'); window.location.href = 'reenviarCodigo.php?id={$idSelecionado}&email={$email}';</script>";
    exit;
}

// Verifica se o código enviado no formulário corresponde ao código gerado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigoInserido = $_POST['codigo'] ?? '';

    if ($codigoInserido == $codigoVerificacao) {
        // O código está correto, redireciona para a página de redefinição de senha
        echo "<script>alert('Código válido. Redefina sua senha.'); window.location.href = 'senhaNova.php?id={$idSelecionado}&email={$email}';</script>";
    } else {
        // O código está incorreto
        echo "<script>alert('Código inválido. Tente novamente.'); window.location.href = 'verificarCodigo.php?id={$idSelecionado}&email={$email}&erro=1';</script>";
    }
} else {
    // Caso não seja um POST (por exemplo, se o usuário apenas acessou a página)
    echo "<script>alert('Método de requisição inválido.'); history.back();</script>";
}
?>

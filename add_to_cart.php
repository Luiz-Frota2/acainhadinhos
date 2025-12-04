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

$mensagem  = '';
$sucesso   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Trata campos básicos
    $nome   = $_POST['nome']              ?? '';
    $preco  = isset($_POST['total_itens'])      ? floatval($_POST['total_itens'])     : 0;
    $quant  = isset($_POST['quantidade_itens']) ? intval($_POST['quantidade_itens'])  : 1;
    $obs    = $_POST['observacao']        ?? '';

    // Trata opcionais (JSON vindo do item.php)
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

    $mensagem = 'Produto adicionado ao carrinho com sucesso!';
    $sucesso  = true;
} else {
    $mensagem = 'Requisição inválida.';
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Açaidinhos - Carrinho</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f5f5;
        }

        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            max-width: 360px;
            background: #28a745;
            color: #fff;
            padding: 16px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.18);
            display: flex;
            align-items: flex-start;
            gap: 12px;
            z-index: 9999;
            animation: fadeIn 0.3s ease-out;
        }

        .toast.toast-error {
            background: #dc3545;
        }

        .toast-icon {
            font-size: 20px;
            margin-top: 2px;
        }

        .toast-content strong {
            display: block;
            margin-bottom: 4px;
            font-size: 15px;
        }

        .toast-content span {
            font-size: 14px;
            opacity: 0.95;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* MOBILE: centraliza a mensagem */
        @media (max-width: 768px) {
            .toast {
                top: 50%;
                right: auto;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 80%;
            }
        }
    </style>
</head>
<body>

<div id="toast" class="toast <?php if (!$sucesso) echo 'toast-error'; ?>">
    <div class="toast-icon">
        <?php if ($sucesso): ?>
            ✓
        <?php else: ?>
            !
        <?php endif; ?>
    </div>
    <div class="toast-content">
        <strong><?php echo $sucesso ? 'Tudo certo!' : 'Ops...'; ?></strong>
        <span><?php echo htmlspecialchars($mensagem); ?></span>
    </div>
</div>

<script>
    // URL do carrinho (ajuste o nome do arquivo se o seu carrinho tiver outro nome)
    const urlCarrinho = "carrinho.php?empresa=<?= urlencode($empresaID) ?>";

    // Tempo para redirecionar (ms)
    const tempoRedirecionar = <?= $sucesso ? 1800 : 2000 ?>;

    setTimeout(() => {
        window.location.href = urlCarrinho;
    }, tempoRedirecionar);
</script>

</body>
</html>

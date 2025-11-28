<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Açaidinhos</title>

    <link rel="stylesheet" href="./assets/css/cardapio/animate.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/cardapio/bootstrap.min.css" />
    <link rel="stylesheet" href="./assets/css/cardapio/main.css" />
    <link rel="stylesheet" href="./assets/vendor/fonts/boxicons.css" />
</head>
<body>

<div class="bg-top pedido"></div>

<header class="width-fix mt-3">
    <div class="card">
        <div class="d-flex">
            <a href="./index.php" class="container-voltar">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="infos text-center">
                <h1 class="mb-0"><b>Seu carrinho</b></h1>
            </div>
        </div>
    </div>
</header>

<section class="carrinho width-fix mt-4">
    <div class="card card-address">
        <div class="img-icon-details">
            <i class="fas fa-cart-plus"></i>
        </div>
        <div class="infos">
            <?php if (!empty($_SESSION['carrinho'])): ?>
                <p class='name mb-0'><b>Itens no seu carrinho</b></p>
                <span class='text mb-0'>Finalize sua compra</span>
            <?php else: ?>
                <p class='name mb-0'><b>Seu carrinho está vazio</b></p>
                <span class='text mb-0'>Adicione itens ao seu carrinho</span>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="carrinho width-fix mt-4">

<?php
$totalProdutos = 0;

if (!empty($_SESSION['carrinho'])) {

    foreach ($_SESSION['carrinho'] as $index => $item) {

        $totalProdutos += floatval($item['preco']);

        echo "
        <div class='card mb-2 pr-0'>
            <div class='container-detalhes'>
                <div class='detalhes-produto'>

                    <div class='infos-produto'>
                        <p class='name'><b>{$item['quant']}x {$item['nome']}</b></p>
                        <p class='price'><b>R$ " . number_format($item['preco'],2,',','.') . "</b></p>
                    </div>
        ";

        // ============================
        // ✔ OPCIONAIS SIMPLES
        // ============================
        if (!empty($item['opc_simples'])) {
            foreach ($item['opc_simples'] as $opc) {
                echo "
                <div class='infos-produto'>
                    <p class='name-opcional mb-0'>+ {$opc['nome']}</p>
                    <p class='price-opcional mb-0'>+ R$ " . number_format($opc['preco'],2,',','.') . "</p>
                </div>
                ";
            }
        }

        // ============================
        // ✔ OPCIONAIS DAS SELEÇÕES
        // ============================
        if (!empty($item['opc_selecao'])) {
            foreach ($item['opc_selecao'] as $opc) {
                echo "
                <div class='infos-produto'>
                    <p class='name-opcional mb-0'>+ {$opc['nome']}</p>
                    <p class='price-opcional mb-0'>+ R$ " . number_format($opc['preco'],2,',','.') . "</p>
                </div>
                ";
            }
        }

        // ============================
        // ✔ OBSERVAÇÕES
        // ============================
        if (!empty($item['observacao'])) {
            echo "
            <div class='infos-produto'>
                <p class='obs-opcional mb-0'>- {$item['observacao']}</p>
            </div>
            ";
        }

        // ============================
        // ✔ BOTÃO REMOVER
        // ============================
        echo "
                </div>

                <form action='remove_from_cart.php' method='post' style='margin-top:5px;'>
                    <input type='hidden' name='index' value='{$index}'>
                    <div class='detalhes-produto-edit'>
                        <button type='submit' class='btn btn-link text-danger p-0' title='Excluir'>
                            <i class='tf-icons bx bx-trash'></i>
                        </button>
                    </div>
                </form>

            </div>
        </div>
        ";
    }
} else {
    echo "<p>O carrinho está vazio.</p>";
}
?>

<?php
$taxaEntrega = 15.00;
$totalFinal = $totalProdutos + $taxaEntrega;
?>

<!-- TAXA DE ENTREGA -->
<div class="card mb-2">
    <div class="detalhes-produto">
        <div class="infos-produto">
            <p class="name mb-0"><i class="fas fa-motorcycle"></i>&nbsp; <b>Taxa de entrega</b></p>
            <p class="price mb-0"><b>+ R$ <?= number_format($taxaEntrega,2,',','.') ?></b></p>
        </div>
    </div>
</div>

<!-- TOTAL FINAL -->
<div class="card mb-2">
    <div class="detalhes-produto">
        <div class="infos-produto">
            <p class="name-total mb-0"><b>Total</b></p>
            <p class="price-total mb-0"><b>R$ <?= number_format($totalFinal,2,',','.') ?></b></p>
        </div>
    </div>
</div>

</section>

<section class="opcionais width-fix mt-5 pb-5">

    <!-- ENTREGA OU RETIRADA -->
    <div class="container-group mb-5">
        <span class="badge">Obrigatório</span>
        <p class="title-categoria mb-0"><b>Escolha uma opção</b></p>
        <span class="sub-title-categoria">Como quer receber o pedido?</span>

        <div class="card card-opcionais mt-2">
            <div class="infos-produto-opcional">
                <p class="name mb-0"><b>Entrega (60-90min)</b></p>
            </div>
            <div class="checks">
                <label class="container-check">
                    <input type="checkbox" />
                    <span class="checkmark"></span>
                </label>
            </div>
        </div>

        <div class="card card-opcionais mt-2">
            <div class="infos-produto-opcional">
                <p class="name mb-0"><b>Retirar no estabelecimento</b></p>
            </div>
            <div class="checks">
                <label class="container-check">
                    <input type="checkbox" />
                    <span class="checkmark"></span>
                </label>
            </div>
        </div>
    </div>

    <!-- ENDEREÇO -->
    <div class="container-group mb-5">
        <span class="badge">Obrigatório</span>
        <p class="title-categoria mb-0"><b>Qual o seu endereço?</b></p>
        <span class="sub-title-categoria">Informe o endereço da entrega</span>

        <div class="card card-select mt-2">
            <div class="infos-produto-opcional">
                <p class="mb-0 color-primary">
                    <i class="fas fa-plus-circle"></i>&nbsp; Nenhum endereço selecionado
                </p>
            </div>
        </div>

        <div class="card card-address mt-2">
            <div class="img-icon-details">
                <i class="fas fa-map-marked-alt"></i>
            </div>
            <div class="infos">
                <p class="name mb-0"><b>Rua Olá Mundo, 123, Meu Bairro</b></p>
                <span class="text mb-0">Cidade-SP / 12345-678</span>
            </div>
            <div class="icon-edit">
                <i class="fas fa-pencil-alt"></i>
            </div>
        </div>
    </div>

    <!-- NOME -->
    <div class="container-group mb-5">
        <span class="badge">Obrigatório</span>
        <p class="title-categoria mb-0"><b>Nome e Sobrenome</b></p>
        <span class="sub-title-categoria">Como vamos te chamar?</span>

        <input type="text" id="nomeCliente" class="form-control mt-2" placeholder="* Informe o nome e sobrenome" />
    </div>

    <!-- TELEFONE -->
    <div class="container-group mb-5">
        <span class="badge">Obrigatório</span>
        <p class="title-categoria mb-0"><b>Número do seu celular</b></p>
        <span class="sub-title-categoria">Para mais informações do pedido</span>

        <input type="text" id="telefoneCliente" class="form-control mt-2" placeholder="(00) 0000-0000" />
    </div>

    <!-- PAGAMENTO -->
    <div class="container-group mb-5">
        <span class="badge">Obrigatório</span>
        <p class="title-categoria mb-0"><b>Como você prefere pagar?</b></p>
        <span class="sub-title-categoria">* Pagamento na entrega</span>

        <div class="card card-select mt-2">
            <div class="infos-produto-opcional">
                <p class="mb-0 color-primary">
                    <i class="fas fa-plus-circle"></i>&nbsp; Nenhuma forma selecionada
                </p>
            </div>
        </div>

        <div class="card card-address mt-2">
            <div class="img-icon-details">
                <i class="fas fa-credit-card"></i>
            </div>
            <div class="infos">
                <p class="name mb-0"><b>Cartão de Crédito</b></p>
                <span class="text mb-0">Levar maquininha</span>
            </div>
            <div class="icon-edit">
                <i class="fas fa-pencil-alt"></i>
            </div>
        </div>
    </div>

</section>

<!-- BOTÃO FAZER PEDIDO -->
<a id="btnFinalizar" class="btn btn-yellow btn-full">
    Fazer pedido <span>R$ <?= number_format($totalFinal,2,',','.') ?></span>
</a>

<section class="menu-bottom disabled hidden" id="menu-bottom-closed">
    <p class="mb-0"><b>Loja fechada no momento.</b></p>
</section>

<script>
// ENVIO PARA WHATSAPP
document.getElementById("btnFinalizar").addEventListener("click", function() {

    let nome = document.getElementById("nomeCliente").value;
    let telefone = document.getElementById("telefoneCliente").value;

    if (!nome || !telefone) {
        alert("Preencha nome e telefone!");
        return;
    }

    let texto = "*NOVO PEDIDO — AÇAIDINHOS*\n\n";
    texto += "*Cliente:* " + nome + "\n";
    texto += "*Telefone:* " + telefone + "\n\n";
    texto += "*Itens do pedido:*\n\n";
</script>

<?php if (!empty($_SESSION['carrinho'])): ?>
<script>
    texto += "<?php foreach ($_SESSION['carrinho'] as $p): ?>";
    texto += "<?= $p['quant'] ?>x <?= addslashes($p['nome']) ?> - R$ <?= number_format($p['preco'],2,',','.') ?>\n";

    <?php if (!empty($p['opc_simples'])): ?>
        texto += "  *Opcionais:*\n";
        <?php foreach ($p['opc_simples'] as $opc): ?>
            texto += "   + <?= addslashes($opc['nome']) ?> (R$ <?= number_format($opc['preco'],2,',','.') ?>)\n";
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($p['opc_selecao'])): ?>
        texto += "  *Seleções:*\n";
        <?php foreach ($p['opc_selecao'] as $opc): ?>
            texto += "   + <?= addslashes($opc['nome']) ?> (R$ <?= number_format($opc['preco'],2,',','.') ?>)\n";
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($p['observacao'])): ?>
        texto += "  Obs: <?= addslashes($p['observacao']) ?>\n";
    <?php endif; ?>

    texto += "\n";
    "<?php endforeach; ?>";

    texto += "\n*Total:* R$ <?= number_format($totalFinal,2,',','.') ?>";
    let url = "https://api.whatsapp.com/send?phone=5597981434585&text=" + encodeURIComponent(texto);
    window.open(url, "_blank");
</script>
<?php endif; ?>

<script src="./js/bootstrap.bundle.min.js"></script>
<script src="./js/item.js"></script>

</body>
</html>

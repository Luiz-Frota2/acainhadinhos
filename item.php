<?php
session_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Açaidinhos</title>

    <link rel="stylesheet" href="./assets/css/cardapio/animate.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/cardapio/bootstrap.min.css" />
    <link rel="stylesheet" href="./assets/css/cardapio/main.css" />
</head>
<body>

<div class="bg-top details"></div>

<header class="width-fix mt-5">
    <div class="card">
        <div class="d-flex align-items-center">
            <a href="./index.php" class="container-voltar">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="infos text-center">
                <h1 class="mb-0"><b>Detalhes do item</b></h1>
            </div>
        </div>
    </div>
</header>

<section class="item width-fix mt-4">
<?php
if (isset($_GET['nome'])) {

    $nome       = $_GET['nome'];
    $descricao  = $_GET['descricao'] ?? '';
    $preco      = isset($_GET['preco']) ? floatval($_GET['preco']) : 0;
    $imagem     = $_GET['imagem'] ?? '';

    $selecoes          = isset($_GET['selecoes']) ? json_decode($_GET['selecoes'], true) : [];
    $opcionais_simples = isset($_GET['opcionais']) ? json_decode($_GET['opcionais'], true) : [];
?>
    <form action="add_to_cart.php" method="POST" id="form-add-cart">

        <div class="card mb-3">
            <div class="container-imagem-produto" style="background-image: url('<?= htmlspecialchars($imagem) ?>'); background-size: cover;"></div>
            <div class="infos-produto">
                <p class="name mb-1"><b><?= htmlspecialchars($nome) ?></b></p>
                <p class="description mb-1"><?= htmlspecialchars($descricao) ?></p>
                <p class="price mb-0"><b>R$ <?= number_format($preco, 2, ',', '.') ?></b></p>
            </div>
        </div>

        <!-- GRUPOS DE SELEÇÃO (se existirem) -->
        <?php if (!empty($selecoes) && is_array($selecoes)): ?>
            <?php foreach ($selecoes as $idxSelecao => $selecao): ?>
                <div class="container-group mb-4 opcionais">
                    <span class="badge">Obrigatório</span>

                    <p class="title-categoria mb-0">
                        <b><?= htmlspecialchars($selecao['titulo'] ?? '') ?></b>
                    </p>
                    <span class="sub-title-categoria">
                        <?= htmlspecialchars($selecao['descricao'] ?? '') ?>
                    </span>

                    <?php if (!empty($selecao['opcoes']) && is_array($selecao['opcoes'])): ?>
                        <?php foreach ($selecao['opcoes'] as $op): ?>
                            <?php
                            $opNome  = $op['nome']  ?? '';
                            $opPreco = isset($op['preco']) ? floatval($op['preco']) : 0;
                            ?>
                            <div class="card card-opcionais mt-2">
                                <div class="infos-produto-opcional">
                                    <p class="name mb-0"><b><?= htmlspecialchars($opNome) ?></b></p>
                                    <p class="price-opcional mb-0">
                                        <b>+ R$ <?= number_format($opPreco, 2, ',', '.') ?></b>
                                    </p>
                                </div>
                                <div class="checks">
                                    <label class="container-check">
                                        <input type="checkbox"
                                               class="opcao-selecao"
                                               data-selecao="<?= $idxSelecao ?>"
                                               data-nome="<?= htmlspecialchars($opNome) ?>"
                                               data-preco="<?= $opPreco ?>">
                                        <span class="checkmark"></span>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- OPCIONAIS SIMPLES -->
        <?php if (!empty($opcionais_simples) && is_array($opcionais_simples)): ?>
            <div class="container-group mb-4 opcionais">
                <p class="title-categoria mb-0"><b>Opcionais</b></p>
                <span class="sub-title-categoria">Escolha adicionais se quiser</span>

                <?php foreach ($opcionais_simples as $op): ?>
                    <?php
                    $opNome  = $op['nome']  ?? '';
                    $opPreco = isset($op['preco']) ? floatval($op['preco']) : 0;
                    ?>
                    <div class="card card-opcionais mt-2">
                        <div class="infos-produto-opcional">
                            <p class="name mb-0"><b><?= htmlspecialchars($opNome) ?></b></p>
                            <p class="price-opcional mb-0">
                                <b>+ R$ <?= number_format($opPreco, 2, ',', '.') ?></b>
                            </p>
                        </div>
                        <div class="checks">
                            <label class="container-check">
                                <input type="checkbox"
                                       class="opcao-simples"
                                       data-nome="<?= htmlspecialchars($opNome) ?>"
                                       data-preco="<?= $opPreco ?>">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- OBSERVAÇÃO -->
        <div class="container-group mb-5">
            <p class="title-categoria mb-0"><b>Observações</b></p>
            <span class="sub-title-categoria">Algum detalhe importante?</span>
            <textarea name="observacao" class="form-control mt-2" rows="4"
                      placeholder="Ex: pouco gelo, sem leite condensado, etc."></textarea>
        </div>

        <!-- HIDDENs para o carrinho -->
        <input type="hidden" name="nome" value="<?= htmlspecialchars($nome) ?>">
        <input type="hidden" id="total_itens" name="total_itens" value="<?= $preco ?>">
        <input type="hidden" id="quantidade_itens" name="quantidade_itens" value="1">
        <input type="hidden" id="opc_simples" name="opc_simples">
        <input type="hidden" id="opc_selecao" name="opc_selecao">

        <section class="menu-bottom details" id="menu-bottom">
            <div class="add-carrinho">
                <span class="btn-menos">
                    <i class="fas fa-minus"></i>
                </span>
                <span class="add-numero-itens">
                    1
                </span>
                <span class="btn-mais">
                    <i class="fas fa-plus"></i>
                </span>
            </div>

            <button type="submit" id="btn-adicionar" name="btn-adicionar"
                    class="btn btn-yellow btn-sm">
                Adicionar <span id="preco"></span>
            </button>
        </section>

    </form>

<?php
} else {
    echo "<p>Produto não encontrado.</p>";
}
?>

</section>

<section class="menu-bottom disabled hidden" id="menu-bottom-closed">
    <p class="mb-0"><b>Loja fechada no momento.</b></p>
</section>

<script type="text/javascript" src="./js/bootstrap.bundle.min.js"></script>
<script type="text/javascript" src="./js/item.js"></script>

</body>
</html>

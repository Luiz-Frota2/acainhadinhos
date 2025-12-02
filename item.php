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

    <div class="container-mensagens" id="container-mensagens-erro"></div>
    <div class="container-mensagens-success" id="container-mensagens-success"></div>

    <div class="bg-top details"></div>

    <header class="width-fix mt-3">
        <div class="card">
            <div class="d-flex">
                <a href="./cardapio.php" class="container-voltar">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div class="infos text-center">
                    <h1 class="mb-0"><b>Detalhes do produto</b></h1>
                </div>
            </div>
        </div>
    </header>

    <?php
    require './assets/php/conexao.php';

    $id_produto = isset($_GET['id']) ? intval($_GET['id']) : 0;

    // PRODUTO
    $stmt_produto = $pdo->prepare("SELECT * FROM adicionarProdutos WHERE id_produto = ?");
    $stmt_produto->execute([$id_produto]);
    $produto = $stmt_produto->fetch(PDO::FETCH_ASSOC);

    if (!$produto) {
        echo "Produto não encontrado.";
        exit;
    }

    // OPCIONAIS SIMPLES
    $stmt_opcionais = $pdo->prepare("SELECT * FROM opcionais WHERE id_produto = ?");
    $stmt_opcionais->execute([$id_produto]);
    $opcionais = $stmt_opcionais->fetchAll(PDO::FETCH_ASSOC);

    // SELEÇÕES
    $stmt_selecoes = $pdo->prepare("SELECT * FROM opcionais_selecoes WHERE id_produto = ?");
    $stmt_selecoes->execute([$id_produto]);
    $selecoes = $stmt_selecoes->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <form action="add_to_cart.php" method="POST" id="form-item">

        <section class="imagem width-fix mt-4">
            <div class="container-imagem-produto"
                style="background-image: url('./assets/img/uploads/<?= htmlspecialchars($produto['imagem_produto']) ?>'); background-size: cover;">
            </div>

            <div class="card mb-2">
                <div class="d-flex">
                    <div class="infos-produto">
                        <input readonly name="nome" class="name mb-2"
                            style="border: 0px solid; outline: none;"
                            value="<?= htmlspecialchars($produto['nome_produto']) ?>">

                        <p class="description mb-3">
                            <?= htmlspecialchars($produto['descricao_produto'] ?: 'Sem descrição.') ?>
                        </p>

                        <input readonly class="price"
                            style="border: 0px solid; outline: none;"
                            value="R$ <?= number_format($produto['preco_produto'], 2, ',', '.') ?>">
                    </div>
                </div>
            </div>
        </section>

        <section class="opcionais width-fix mt-4 pb-5">

            <!-- OPCIONAIS SIMPLES -->
            <?php if (count($opcionais) > 0): ?>
                <p class="title-categoria mb-0"><b>Opcionais</b></p>

                <?php foreach ($opcionais as $op): ?>
                    <div class="card card-opcionais mt-2">
                        <div class="infos-produto-opcional">
                            <p class="name mb-0"><b><?= htmlspecialchars($op['nome']) ?></b></p>
                            <p class="price mb-0"><b>+ R$ <?= number_format($op['preco'], 2, ',', '.') ?></b></p>
                        </div>

                        <div class="checks">
                            <label class="container-check">
                                <input type="checkbox"
                                       class="opcional-checkbox"
                                       data-nome="<?= htmlspecialchars($op['nome']) ?>"
                                       data-preco="<?= $op['preco'] ?>">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>


            <!-- SELEÇÕES -->
            <?php if (count($selecoes) > 0): ?>
                <?php foreach ($selecoes as $index => $selecao): ?>
                    <div class="container-group mb-4 mt-4" id="selecao-<?= $index ?>">

                        <p class="title-categoria mb-0">
                            <b><?= htmlspecialchars($selecao['titulo']) ?></b>
                        </p>

                        <span class="sub-title-categoria">
                            Escolha de <?= $selecao['minimo'] ?> até <?= $selecao['maximo'] ?> opção(ões)
                        </span>

                        <?php
                        $stmt_opcoes = $pdo->prepare("SELECT * FROM opcionais_opcoes WHERE id_selecao = ?");
                        $stmt_opcoes->execute([$selecao['id']]);
                        $opcoes = $stmt_opcoes->fetchAll(PDO::FETCH_ASSOC);
                        ?>

                        <div class="row mt-0">
                            <?php foreach ($opcoes as $opcao): ?>
                                <div class="col-12">
                                    <div class="card card-opcionais mt-2">
                                        <div class="infos-produto-opcional">
                                            <p class="name mb-0"><b><?= htmlspecialchars($opcao['nome']) ?></b></p>
                                            <p class="price mb-0"><b>+ R$ <?= number_format($opcao['preco'], 2, ',', '.') ?></b></p>
                                        </div>
                                        <div class="checks">
                                            <label class="container-check">
                                                <input type="checkbox"
                                                       class="opcao-checkbox"
                                                       data-nome="<?= htmlspecialchars($opcao['nome']) ?>"
                                                       data-preco="<?= $opcao['preco'] ?>"
                                                       data-selecao="<?= $index ?>">
                                                <span class="checkmark"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- OBSERVAÇÃO -->
            <div class="container-group mb-5">
                <p class="title-categoria mb-0"><b>Observações</b></p>
                <span class="sub-title-categoria">Informe alguma observação abaixo</span>

                <textarea name="observacao" class="form-control mt-2" rows="4"
                    placeholder="Digite suas observações aqui..."></textarea>
            </div>
        </section>


        <section class="menu-bottom details" id="menu-bottom">
            <div class="add-carrinho">
                <span class="btn-menos"><i class="fas fa-minus"></i></span>
                <span class="add-numero-itens">1</span>
                <span class="btn-mais"><i class="fas fa-plus"></i></span>
            </div>

            <input type="hidden" name="quantidade_itens" id="quantidade_itens" value="1">
            <input type="hidden" name="total_itens" id="total_itens" value="0">

            <!-- inputs PARA OPCIONAIS JSON -->
            <input type="hidden" name="opc_simples" id="opc_simples">
            <input type="hidden" name="opc_selecao" id="opc_selecao">

            <button type="submit" id="btn-adicionar" class="btn btn-yellow btn-sm">
                Adicionar <span id="preco">R$ 0,00</span>
            </button>
        </section>

    </form>


    <!-- ========= SCRIPT DE CORREÇÃO ========= -->
    <script>
        const precoBase = parseFloat("<?= $produto['preco_produto'] ?>");
        const spanPreco = document.getElementById("preco");
        const quantEl = document.querySelector(".add-numero-itens");
        const inputQuant = document.getElementById("quantidade_itens");
        const inputTotal = document.getElementById("total_itens");

        const inputOpcSimples = document.getElementById("opc_simples");
        const inputOpcSelecao = document.getElementById("opc_selecao");

        function calcular() {
            let total = precoBase;
            let quantidade = parseInt(quantEl.textContent);

            let listaSimples = [];
            let listaSelecoes = [];

            document.querySelectorAll(".opcional-checkbox:checked").forEach(ch => {
                total += parseFloat(ch.dataset.preco);
                listaSimples.push({
                    nome: ch.dataset.nome,
                    preco: parseFloat(ch.dataset.preco)
                });
            });

            document.querySelectorAll(".opcao-checkbox:checked").forEach(ch => {
                total += parseFloat(ch.dataset.preco);
                listaSelecoes.push({
                    nome: ch.dataset.nome,
                    preco: parseFloat(ch.dataset.preco)
                });
            });

            total *= quantidade;

            spanPreco.textContent = "R$ " + total.toFixed(2).replace(".", ",");
            inputTotal.value = total.toFixed(2);
            inputQuant.value = quantidade;

            inputOpcSimples.value = JSON.stringify(listaSimples);
            inputOpcSelecao.value = JSON.stringify(listaSelecoes);
        }

        document.querySelector(".btn-mais").onclick = () => {
            quantEl.textContent = parseInt(quantEl.textContent) + 1;
            calcular();
        };

        document.querySelector(".btn-menos").onclick = () => {
            let q = parseInt(quantEl.textContent);
            if (q > 1) quantEl.textContent = q - 1;
            calcular();
        };

        document.querySelectorAll(".opcional-checkbox").forEach(ch => {
            ch.onchange = calcular;
        });

        document.querySelectorAll(".opcao-checkbox").forEach(ch => {
            ch.onchange = calcular;
        });

        calcular();
    </script>

    <script src="./js/bootstrap.bundle.min.js"></script>

</body>
</html>

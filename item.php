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

</head>

<body>

    <div class="container-mensagens" id="container-mensagens-erro"></div>

    <div class="container-mensagens-success" id="container-mensagens-success"></div>


    <div class="bg-top details"></div>

    <header class="width-fix mt-3">
        <div class="card">

            <div class="d-flex">

                <a href="./index.php" class="container-voltar">
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

    // Obtém o id_produto da URL
    $id_produto = isset($_GET['id']) ? intval($_GET['id']) : 0;

    // Busca os dados do produto
    $sql_produto = "SELECT * FROM adicionarProdutos WHERE id_produto = ?";
    $stmt_produto = $pdo->prepare($sql_produto);
    $stmt_produto->execute([$id_produto]);
    $produto = $stmt_produto->fetch(PDO::FETCH_ASSOC);

    // Verifica se o produto existe
    if (!$produto) {
        echo "Produto não encontrado.";
        exit;
    }

    // Busca os opcionais simples para o produto
    $sql_opcionais = "SELECT * FROM opcionais WHERE id_produto = ?";
    $stmt_opcionais = $pdo->prepare($sql_opcionais);
    $stmt_opcionais->execute([$id_produto]);
    $opcionais = $stmt_opcionais->fetchAll(PDO::FETCH_ASSOC);

    // Busca as seleções de opcionais para o produto
    $sql_selecoes = "SELECT * FROM opcionais_selecoes WHERE id_produto = ?";
    $stmt_selecoes = $pdo->prepare($sql_selecoes);
    $stmt_selecoes->execute([$id_produto]);
    $selecoes = $stmt_selecoes->fetchAll(PDO::FETCH_ASSOC);

    ?>
    <form action="add_to_cart.php" method="POST">
        <section class="imagem width-fix mt-4">
            <div class="container-imagem-produto"
                style="background-image: url('./assets/img/uploads/<?= htmlspecialchars($produto['imagem_produto'] ?: './img/default.jpg') ?>'); background-size: cover;">
            </div>
            <div class="card mb-2">
                <div class="d-flex">
                    <div class="infos-produto">
                        <input readonly style="border: 0px solid; outline: none;" name="nome" class="name mb-2"
                            value="<?= htmlspecialchars($produto['nome_produto']) ?>">
                        <p class="description mb-3">
                            <?= htmlspecialchars($produto['descricao_produto'] ?: 'Sem descrição.') ?>
                        </p>
                        <!-- Exibe o preço base sem formatação -->
                        <input readonly style="border: 0px solid; outline: none;" class="price"
                            value="R$ <?= number_format($produto['preco_produto'], 2, ',', '.') ?>">
                    </div>
                </div>
            </div>
        </section>

        <section class="opcionais width-fix mt-4 pb-5">
            <!-- Exibe os opcionais -->
            <?php if (count($opcionais) > 0): ?>
                <?php foreach ($opcionais as $opcional): ?>
                    <p class="title-categoria mb-0"><b>Opcionais</b></p>
                    <div class="card card-opcionais mt-2">
                        <div class="infos-produto-opcional">
                            <p class="name mb-0"><b><?= htmlspecialchars($opcional['nome']) ?></b></p>
                            <!-- Exibe o preço do opcional sem formatação -->
                            <p class="price mb-0"><b>+ R$ <?= number_format($opcional['preco'], 2, ',', '.') ?></b></p>
                        </div>
                        <div class="checks">
                            <label class="container-check">
                                <input type="checkbox" class="opcional-checkbox" data-preco="<?= $opcional['preco'] ?>" />
                                <span class="checkmark"></span>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p></p>
            <?php endif; ?>

            <!-- Exibe as seleções de opcionais -->
            <?php if (count($selecoes) > 0): ?>
                <?php foreach ($selecoes as $index => $selecao): ?>
                    <div class="container-group mb-4 mt-4" id="selecao-<?= $index ?>">
                        <p class="title-categoria mb-0"><b><?= htmlspecialchars($selecao['titulo']) ?></b></p>
                        <span class="sub-title-categoria">Escolha <span>de <?= $selecao['minimo'] ?> até
                                <?= $selecao['maximo'] ?> opção(ões)</span>

                            <?php
                            // Buscar opções dessa seleção
                            $sql_opcoes = "SELECT * FROM opcionais_opcoes WHERE id_selecao = ?";
                            $stmt_opcoes = $pdo->prepare($sql_opcoes);
                            $stmt_opcoes->execute([$selecao['id']]);
                            $opcoes = $stmt_opcoes->fetchAll(PDO::FETCH_ASSOC);
                            ?>

                            <?php if (count($opcoes) > 0): ?>
                                <div class="row mt-0">
                                    <?php foreach ($opcoes as $opcao): ?>
                                        <div class="col-12 col-md-12">
                                            <div class="card card-opcionais mt-2">
                                                <div class="infos-produto-opcional">
                                                    <p class="name mb-0"><b><?= htmlspecialchars($opcao['nome']) ?></b></p>
                                                    <!-- Exibe o preço da opção sem formatação -->
                                                    <p class="price mb-0"><b>+ R$ <?= number_format($opcao['preco'], 2, ',', '.') ?></b>
                                                    </p>
                                                </div>
                                                <div class="checks">
                                                    <label class="container-check">
                                                        <input type="checkbox" class="opcao-checkbox"
                                                            data-preco="<?= $opcao['preco'] ?>" data-selecao-index="<?= $index ?>" />
                                                        <span class="checkmark"></span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p></p>
                            <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p></p>
            <?php endif; ?>

            <!-- Seção de Observações -->
            <div class="container-group mb-5">
                <p class="title-categoria mb-0"><b>Observações</b></p>
                <span class="sub-title-categoria">Informe alguma observação abaixo</span>
                <textarea name="observacao" class="form-control mt-2" rows="4"
                    placeholder="Digite suas observações aqui..."></textarea>
            </div>


        </section>

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

            <!-- Campos ocultos hidden -->
            <input type="hidden" name="quantidade_itens" id="quantidade_itens" value="1 x">
            <input type="hidden" name="total_itens" id="total_itens" value="R$ 0.00">
            <input type="hidden" name="opcionais_simples[]" id="opcionais_simples_hidden">
<input type="hidden" name="opcao_selecao[]" id="opcao_selecao_hidden">


            <!-- Botão "Adicionar" -->
            <button type="submit" id="btn-adicionar" name="btn-adicionar" value="btn-adicionar"
                class="btn btn-yellow btn-sm">
                Adicionar <span id="preco" name="preco">R$ 0,00</span>
            </button>
        </section>
        </>
        <script>
            function atualizarValorTotal() {
                var precoBase = parseFloat("<?= $produto['preco_produto'] ?>");
                var quantidade = parseInt(document.querySelector('.add-numero-itens').textContent);
                var valorTotal = precoBase;

                // Soma os opcionais gerais
                document.querySelectorAll('.opcional-checkbox:checked').forEach(function (checkbox) {
                    var precoOpcional = parseFloat(checkbox.getAttribute('data-preco'));
                    if (!isNaN(precoOpcional)) {
                        valorTotal += precoOpcional;
                    }
                });

                // Soma as seleções específicas
                document.querySelectorAll('.opcao-checkbox:checked').forEach(function (checkbox) {
                    var precoOpcional = parseFloat(checkbox.getAttribute('data-preco'));
                    if (!isNaN(precoOpcional)) {
                        valorTotal += precoOpcional;
                    }
                });

                valorTotal *= quantidade;

                // Atualiza o texto do botão
                var btnAdicionar = document.querySelector('#btn-adicionar span');
                if (btnAdicionar) {
                    btnAdicionar.textContent = 'R$ ' + valorTotal.toFixed(2).replace('.', ',');
                }

                // Atualiza inputs hidden
                document.getElementById('quantidade_itens').value = quantidade;
                document.getElementById('total_itens').value = valorTotal.toFixed(2);
            }
            function mostrarMensagemErro(mensagem) {
                var containerErro = document.getElementById('container-mensagens-erro');
                if (containerErro) {
                    containerErro.innerHTML = `<p class="erro-texto">${mensagem}</p>`;
                    containerErro.style.display = 'block';

                    // Remove a mensagem após 3 segundos
                    setTimeout(function () {
                        containerErro.innerHTML = '';
                        containerErro.style.display = 'none';
                    }, 3000);
                }
            }

            function verificarLimiteCheckbox(event) {
                var checkbox = event.target;
                var selecaoIndex = checkbox.getAttribute('data-selecao-index');
                var selecaoContainer = document.getElementById('selecao-' + selecaoIndex);
                var maximo = parseInt(selecaoContainer.querySelector('.sub-title-categoria').textContent.match(/\d+/g)[1]);

                var checkboxesSelecionados = selecaoContainer.querySelectorAll('.opcao-checkbox:checked');

                if (checkboxesSelecionados.length > maximo) {
                    checkbox.checked = false; // Impede que o usuário selecione mais do que o limite permitido
                    mostrarMensagemErro(`Você só pode selecionar até ${maximo} opção(ões)`);
                }

                atualizarValorTotal();
            }

            // Eventos para checkboxes de opcionais gerais
            document.querySelectorAll('.opcional-checkbox').forEach(function (checkbox) {
                checkbox.addEventListener('change', atualizarValorTotal);
            });

            // Eventos para checkboxes de seleções específicas
            document.querySelectorAll('.opcao-checkbox').forEach(function (checkbox) {
                checkbox.addEventListener('change', verificarLimiteCheckbox);
            });

            document.querySelector('.btn-mais').addEventListener('click', function () {
                var quantidadeEl = document.querySelector('.add-numero-itens');
                quantidadeEl.textContent = parseInt(quantidadeEl.textContent) + 1;
                atualizarValorTotal();
            });

            document.querySelector('.btn-menos').addEventListener('click', function () {
                var quantidadeEl = document.querySelector('.add-numero-itens');
                var quantidade = parseInt(quantidadeEl.textContent);
                if (quantidade > 1) {
                    quantidadeEl.textContent = quantidade - 1;
                    atualizarValorTotal();
                }
            });

            document.addEventListener('DOMContentLoaded', atualizarValorTotal);

        </script>

        <section class="menu-bottom disabled hidden" id="menu-bottom-closed">
            <p class="mb-0"><b>Loja fechada no momento.</b></p>
        </section>


        <script type="text/javascript" src="./js/bootstrap.bundle.min.js"></script>
        <script type="text/javascript" src="./js/item.js"></script>

</body>

</html>
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
          <!-- Icons. Uncomment required icon fonts -->
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
                    
                    <?php if (!empty($_SESSION['carrinho'])){
                        echo "<p class='name mb-0'><b>itens no seu carrinho</b></p>";
                        echo "<span class='text mb-0'>Finalize sua compra</span>";
                    } else {
                        echo "<p class='name mb-0'><b>Seu carrinho está vazio</b></p>";
                        echo "<span class='text mb-0'>Adicione itens ao seu carrinho</span>";

                    }
                     ?>
                </div>
            </div>
        </section>
        
        <section class="carrinho width-fix mt-4">
      




<?php
if (!empty($_SESSION['carrinho'])) {
    foreach ($_SESSION['carrinho'] as $index => $item) {

        echo "<div class='card mb-2 pr-0'>
                <div class='container-detalhes'>
                    <div class='detalhes-produto'>

                        <div class='infos-produto'>
                            <p class='name'><b>". $item['quant'] ."x ". $item['nome'] ."</b></p>
                            <p class='price'><b>R$ ". number_format($item['preco'], 2, ',', '.') ."</b></p>
                        </div>";

        // =====================================
        // ➤ OPCIONAIS SIMPLES
        // =====================================
        if (!empty($item['opc_simples'])) {
            foreach ($item['opc_simples'] as $opc) {

                echo "<div class='infos-produto'>
                        <p class='name-opcional mb-0'>+ ". htmlspecialchars($opc['nome']) ."</p>
                        <p class='price-opcional mb-0'>+ R$ ". number_format($opc['preco'], 2, ',', '.') ."</p>
                      </div>";
            }
        }

        // =====================================
        // ➤ OPCIONAIS DAS SELEÇÕES
        // =====================================
        if (!empty($item['opc_selecao'])) {
            foreach ($item['opc_selecao'] as $opc) {

                echo "<div class='infos-produto'>
                        <p class='name-opcional mb-0'>+ ". htmlspecialchars($opc['nome']) ."</p>
                        <p class='price-opcional mb-0'>+ R$ ". number_format($opc['preco'], 2, ',', '.') ."</p>
                      </div>";
            }
        }

        // =====================================
        // ➤ OBSERVAÇÃO
        // =====================================
        if (!empty($item['observacao'])) {
            echo "<div class='infos-produto'>
                    <p class='obs-opcional mb-0'>- ". htmlspecialchars($item['observacao']) ."</p>
                  </div>";
        }

        // FIM DA LISTA
        echo "      </div>";

        // =====================================
        // ➤ BOTÃO REMOVER ITEM
        // =====================================
        echo "<form action='remove_from_cart.php' method='post' style='margin-top:5px;'>
                <input type='hidden' name='index' value='{$index}'>
                <div class='detalhes-produto-edit'>
                    <button type='submit' class='btn btn-link text-danger p-0' title='Excluir'>
                        <i class='tf-icons bx bx-trash'></i>
                    </button>
                </div>
              </form>";

        echo "  </div>
              </div>";
    }
} else {
    echo "<p>O carrinho está vazio.</p>";
}
?>

            

            <div class="card mb-2">
                <div class="detalhes-produto">
                    <div class="infos-produto">
                        <p class="name mb-0"><i class="fas fa-motorcycle"></i>&nbsp; <b>Taxa de entrega</b></p>
                        <p class="price mb-0"><b>+ R$ 15,00</b></p>
                    </div>
                </div>
            </div>

            <div class="card mb-2">
                <div class="detalhes-produto">
                    <div class="infos-produto">
                        <p class="name-total mb-0"><b>Total</b></p>
                        <p class="price-total mb-0"><b>R$ 105,50</b></p>
                    </div>
                </div>
            </div>

        </section>


        <section class="opcionais width-fix mt-5 pb-5">

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
                        <p class="name mb-0"><b>Rua Olá Mundo, 123, Meu Mairro</b></p>
                        <span class="text mb-0">Cidade-SP / 12345-678</span>
                    </div>
                    <div class="icon-edit">
                        <i class="fas fa-pencil-alt"></i>
                    </div>
                </div>
    
            </div>

            <div class="container-group mb-5">
                <span class="badge">Obrigatório</span>
    
                <p class="title-categoria mb-0"><b>Nome e Sobrenome</b></p>
                <span class="sub-title-categoria">Como vamos te chamar?</span>
    
                <input type="text" class="form-control mt-2" placeholder="* Informe o nome e sobrenome" />
    
            </div>

            <div class="container-group mb-5">
                <span class="badge">Obrigatório</span>
    
                <p class="title-categoria mb-0"><b>Número do seu celular</b></p>
                <span class="sub-title-categoria">Para mais informações do pedido</span>
    
                <input type="text" class="form-control mt-2" placeholder="(00) 0000-0000" />
    
            </div>

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
                        <!-- <i class="fas fa-coins"></i> -->
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


        <a href="./index.php" class="btn btn-yellow btn-full">
          Fazer pedido <span>R$ 105,50</span>  
        </a>


        <section class="menu-bottom disabled hidden" id="menu-bottom-closed">
            <p class="mb-0"><b>Loja fechada no momento.</b></p>
        </section>

        <script type="text/javascript" src="./js/bootstrap.bundle.min.js"></script>
        <script type="text/javascript" src="./js/item.js"></script>

    </body>
</html>
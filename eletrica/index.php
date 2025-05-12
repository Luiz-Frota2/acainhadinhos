<!DOCTYPE html>
<html>

    <head>
        <meta charset="UTF-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Eletrica - Seja bem-vindo!</title>

        <link rel="stylesheet" href="./css/bootstrap.min.css" />
        <link rel="stylesheet" href="./css/fontawesome.css" />
        <link rel="stylesheet" href="./css/animate.css" />
        <link rel="stylesheet" href="./css/main.css" />
        <link rel="stylesheet" href="./css/responsivo.css" />

    </head>

    <body>

        <div class="container-mensagens" id="container-mensagens">

        </div>

        <a class="botao-carrinho animated bounceIn hidden" onclick="cardapio.metodos.abrirCarrinho(true)">
            <div class="badge-total-carrinho">0</div>
            <i class="fa fa-shopping-bag"></i>
        </a>

        <section class="header position-fixed w-100"  style="top: 0; z-index: 1030; background-color: var(--color-background);">

            <div class="container">
                <nav class="navbar navbar-expand-lg pl-0 pr-0 col-one">
                    <a class="wow fadeIn" href="index.php">
                        <img src="./img/logo.png" width="120" class="img-logo" />
                    </a>
                    <?php
                            require_once './painel/assets/php/conexao.php';

                            session_start();

                            // Verificar se já existe um ID para o carrinho no cookie
                            if (!isset($_COOKIE['id_carrinho'])) {
                                // Gerar um ID único para o carrinho, usando uniqid() e uma chave aleatória
                                $idCarrinho = uniqid('carrinho_', true);

                                // Armazenar o ID do carrinho em um cookie por 30 dias
                                setcookie('id_carrinho', $idCarrinho, time() + (30 * 24 * 60 * 60), "/"); // 30 dias de validade
                            } else {
                                // Caso o cookie já exista, utilizamos o ID do carrinho armazenado
                                $idCarrinho = $_COOKIE['id_carrinho'];
                            }

                            // Depuração: Verificando se o ID do carrinho está correto
                            // echo "ID do Carrinho: " . $idCarrinho . "<br>";  

                            // Query para obter a quantidade total de itens no carrinho
                            $queryTotalItens = "SELECT SUM(quantidade) AS total FROM carrinhotemporario WHERE id_carrinho = :idCarrinho";
                            $stmtTotal = $pdo->prepare($queryTotalItens);
                            $stmtTotal->bindParam(':idCarrinho', $idCarrinho, PDO::PARAM_STR);
                            $stmtTotal->execute();
                            $totalItens = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

                            // Depuração: Verificando o total de itens
                            // echo "Total de Itens no Carrinho: " . $totalItens . "<br>";
                        ?>
                    <span class="icon iconHidden"   onclick="window.location.href='carrinho.php'">
                        <div class="container-total-carrinho badge-total-carrinho <?= ($totalItens > 0) ? '' : 'hidden' ?>" 
                            style="color: #000; background-color: #ffda6f;" 
                          >
                            <?= $totalItens ?>
                        </div>
                        <i class="fa fa-shopping-bag"></i>
                    </span>
                    <div class="collapse navbar-collapse" id="navbarNavDropdown">
                        <ul class="navbar-nav ml-auto mr-auto wow fadeIn">
                            <li class="nav-item">
                                <a href="#reservas" class="nav-link"><b>Orçamentos</b></a>
                            </li>
                            <li class="nav-item">
                                <a href="#servicos" class="nav-link"><b>Serviços</b></a>
                            </li>
                            <li class="nav-item">
                                <a href="#cardapio" class="nav-link"><b>Catálogo</b></a>
                            </li>
                        </ul>
                        <?php
                            require_once './painel/assets/php/conexao.php';

                            session_start();

                            // Verificar se já existe um ID para o carrinho no cookie
                            if (!isset($_COOKIE['id_carrinho'])) {
                                // Gerar um ID único para o carrinho, usando uniqid() e uma chave aleatória
                                $idCarrinho = uniqid('carrinho_', true);

                                // Armazenar o ID do carrinho em um cookie por 30 dias
                                setcookie('id_carrinho', $idCarrinho, time() + (30 * 24 * 60 * 60), "/"); // 30 dias de validade
                            } else {
                                // Caso o cookie já exista, utilizamos o ID do carrinho armazenado
                                $idCarrinho = $_COOKIE['id_carrinho'];
                            }

                            // Depuração: Verificando se o ID do carrinho está correto
                            // echo "ID do Carrinho: " . $idCarrinho . "<br>";  

                            // Query para obter a quantidade total de itens no carrinho
                            $queryTotalItens = "SELECT SUM(quantidade) AS total FROM carrinhotemporario WHERE id_carrinho = :idCarrinho";
                            $stmtTotal = $pdo->prepare($queryTotalItens);
                            $stmtTotal->bindParam(':idCarrinho', $idCarrinho, PDO::PARAM_STR);
                            $stmtTotal->execute();
                            $totalItens = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

                            // Depuração: Verificando o total de itens
                            // echo "Total de Itens no Carrinho: " . $totalItens . "<br>";
                        ?>

                        <a class="btn btn-white btn-icon wow fadeIn" href="carrinho.php">
                            Meu carrinho 
                            <span class="icon">
                                <div class="container-total-carrinho badge-total-carrinho <?= ($totalItens > 0) ? '' : 'hidden' ?>" style="color: #000 !important; background-color: #ffda6f;">
                                    <?= $totalItens ?>
                                </div>
                                <i class="fa fa-shopping-bag"></i>
                            </span>
                        </a>
                    </div>
                </nav>
            </div>
        </section>


        <section class="banner" id="banner">
            <div class="container">
                <div class="row">
                    <div class="col-12 col-lg-6 col-md-6 col-sm-12 col-one">
                        <div class="d-flex text-banner">
                            <p class="wow fadeInLeft text-title" id="text-title" ><b>Encontre o material <b class="color-primary">elétrico ideal.</b></b></p>
                            <p class="wow fadeInLeft text-simple delay-02s">
                                Tudo o que você precisa para sua obra ou projeto elétrico com qualidade e segurança. Faça seu pedido de forma rápida e fácil!
                            </p>
                            <div class="wow fadeIn delay-05s">
                                <a class="btn btn-yellow mt-4 mr-3" href="#cardapio">
                                    Ver catálogo
                                </a>
                                <a href="#" class="btn btn-white btn-icon-left mt-4" id="btnLigar">
                                    <span class="icon-left">
                                        <i class="fa fa-phone"></i>
                                    </span>
                                    (92) 99151-5710
                                </a>
                            </div>
                        </div>
                        <a href="https://www.instagram.com/limafront/" target="_blank" class="btn btn-sm btn-white btn-social  mr-3 wow fadeIn delay-05s">
                            <i class="fab fa-instagram"></i>
                        </a>
                      
                        <a class="btn btn-sm btn-white btn-social wow fadeIn delay-05s">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                    </div>

                    <div class="col-6 no-mobile">
                        <div class="card-banner wow fadeIn delay-02s"></div>
                        <div class="d-flex img-banner wow fadeIn delay-05s">
                            <img src="./img/produto/foto-flex-4.png" />
                        </div>
                        <div class="card card-case wow fadeInRight delay-07s">
                            "Ótimos preços e produtos de qualidade.
                            <br>Entrega rápida e atendimento <br>
                            excelente!"
                            <span class="card-case-name">
                                <b>Thiago Lopes</b>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="servicos" id="servicos">

            <div class="background-servicos"></div>

            <div class="container">
                <div class="row">

                    <div class="col-12 col-one text-center mb-5 wow fadeIn">
                        <span class="hint-title"><b>Serviços</b></span>
                        <h1 class="title">
                            <b>Como são nossos serviços?</b>
                        </h1>
                    </div>

                    <div class="col-12 col-lg-4 col-md-4 col-sm-12 col-one mb-5 wow fadeInUp">
                        <div class="card-icon text-center">
                            <img src="./img/icone-pedido.svg" width="150" />
                        </div>
                        <div class="card-text text-center mt-3">
                            <p><b>Fácil de pedir</b></p>
                            <span>
                                Basta seguir alguns passos simples para solicitar o seu material.
                            </span>
                        </div>
                    </div>

                    <div class="col-12 col-lg-4 col-md-4 col-sm-12 col-one mb-5 wow fadeInUp">
                        <div class="card-icon text-center">
                            <img src="./img/icone-delivery.svg" width="250" />
                        </div>
                        <div class="card-text text-center mt-3">
                            <p><b>Entrega rápida</b></p>
                            <span>
                                Nossa entrega é sempre pontual, rápida e segura.
                            </span>
                        </div>
                    </div>

                    <div class="col-12 col-lg-4 col-md-4 col-sm-12 col-one mb-5 wow fadeInUp">
                        <div class="card-icon text-center">
                            <img src="./img/icone-qualidade.svg" width="250" />
                        </div>
                        <div class="card-text text-center mt-3">
                            <p><b>Melhor qualidade</b></p>
                            <span>
                                A qualidade do nosso produto também é o nosso forte.
                            </span>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <section class="cardapio" id="cardapio">

            <?php
                // Conectar ao banco de dados
                require_once './painel/assets/php/conexao.php';

                // Buscar categorias
                $queryCategorias = "SELECT * FROM adicionarcategoria ORDER BY id_categoria ASC"; // Ordenar categorias pelo id
                $stmtCategorias = $pdo->query($queryCategorias);
                $categorias = $stmtCategorias->fetchAll(PDO::FETCH_ASSOC);

                // Verificar se o 'categoria_id' foi passado na URL
                $categoriaSelecionada = isset($_GET['categoria_id']) ? $_GET['categoria_id'] : $categorias[0]['id_categoria']; // Pega a categoria da URL ou a primeira categoria

                // Buscar produtos pela categoria selecionada
                $queryProdutos = "SELECT * FROM adicionarprodutos WHERE id_categoria = :categoriaId ORDER BY nome_produto ASC";
                $stmtProdutos = $pdo->prepare($queryProdutos);
                $stmtProdutos->bindParam(':categoriaId', $categoriaSelecionada, PDO::PARAM_INT);
                $stmtProdutos->execute();
                $produtos = $stmtProdutos->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <div class="container">
                <div class="row">
                    <div class="col-12 col-one text-center mb-5 wow fadeIn">
                        <span class="hint-title"><b>Material Elétrico</b></span>
                        <h1 class="title">
                            <b>Conheça nossos produtos</b>
                        </h1>
                    </div>

                    <div class="col-12 col-one container-menu wow fadeInUp d-flex justify-content-center align-items-center">
                        <!-- Menu de Itens -->
                        <div id="menu-links" class="d-flex justify-content-center align-items-center">
                            <?php 
                                foreach ($categorias as $categoria): 
                                $activeClass = ($categoria['id_categoria'] == $categoriaSelecionada) ? 'active' : ''; // Adicionar a classe active
                            ?>
                                <a href="?categoria_id=<?php echo $categoria['id_categoria']; ?>#cardapio" class="btn btn-white btn-sm menu-item mr-2 <?php echo $activeClass; ?>">
                                    <i class="<?php echo htmlspecialchars($categoria['icone_categoria']); ?>"></i>&nbsp; <?php echo $categoria['nome_categoria']; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-one">
                    <div class="container-group mb-5">
                        <div class="row row-custom d-flex justify-content-center align-items-center" id="produtos-container">
                            <?php foreach ($produtos as $produto): ?>
                                <div class="card col-lg-5 col-md-5 col-12 item-cardapio produto" data-categoria-id="<?php echo $produto['id_categoria']; ?>" onclick="redirecionarParaProduto(<?php echo $produto['id_produto']; ?>)">
                                    <!-- Removendo o <a> e tornando a div clicável -->
                                    <div class="d-flex">
                                        <div class="container-img-produto" style="background-image: url('./painel/assets/img/uploads/<?php echo $produto['imagem_produto']; ?>'); background-size: cover;"></div>
                                        <div class="infos-produto abrir">
                                            <p class="name"><b><?php echo $produto['nome_produto']; ?></b></p>
                                            <p class="description">
                                                <?php 
                                                    $descricao = explode(' ', $produto['descricao_produto']);
                                                    echo implode(' ', array_slice($descricao, 0, 6)) . '...'; 
                                                ?>
                                            </p>

                                            <p class="price"><b>R$ <?php echo number_format($produto['preco_produto'], 2, ',', '.'); ?></b></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

        </section>


        <section class="reserva" id="reservas">
            <div class="container">
                <div class="row">
                    <div class="col-12 col-one">

                        <div class="card-secondary wow fadeInUp">

                            <div class="row">
                                <div class="col-12 col-lg-7 col-md-7 col-sm-12">
                                    <span class="hint-title"><b>Orçamento</b></span>
                                    <h1 class="title orca">
                                        <b>Quer fazer um orçamento?</b>
                                    </h1>
                                    <p class="text-justify">
                                        Entre em contato clicando no botão abaixo.
                                        Solicite um orçamento de forma simples e rápida.
                                    </p>
                                    <a class="btn btn-yellow mt-4 wow fadeIn delay-05s" href="#" id="btnOrcamento" target="_blank">
                                        Solicitar Orçamento
                                    </a>
                                </div>
                                
                                <div class="col-5 no-mobile">
                                    <div class="card-reserva"></div>
                                    <div class="d-flex img-banner">
                                        <img src="./img/icone-orçamento.svg" />
                                    </div>
                                </div>

                            </div>

                        </div>

                    </div>
                </div>
            </div>
        </section>

        <footer>
            <div class="container">
                <div class="row">

                <div class="col-12 col-lg-6 col-md-6 col-sm-12 col-one container-texto-footer wow fadeIn" style="font-size: 12px;">
                    <p class="mb-0">
                        <b>Catálogo Eletrico</b> &copy; Todos os direitos reservados. Desenvolvido por 
                        <a href="https://wa.me/5592991515710" style="text-decoration: none; color: inherit;"><b>Lucas Correa</b></a>
                    </p>
                </div>


                    <div class="col-12 col-lg-6 col-md-6 col-sm-12 container-redes-footer wow fadeIn">
                        <a class="btn btn-sm btn-white btn-social mr-3">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a class="btn btn-sm btn-white btn-social">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                    </div>
                    

                </div>
            </div>
        </footer>
        <script type="text/javascript" src="./js/jquery-1.12.4.min.js"></script>
        <script type="text/javascript" src="./js/modernizr-3.5.0.min.js"></script>
        <script type="text/javascript" src="./js/bootstrap.min.js"></script>
        <script type="text/javascript" src="./js/popper.min.js"></script>
        <script type="text/javascript" src="./js/wow.min.js"></script>
        <script type="text/javascript" src="./js/app.js"></script>

    </body>

</html>
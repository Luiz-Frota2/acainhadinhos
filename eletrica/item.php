<?php
// Conectar ao banco de dados
require_once './painel/assets/php/conexao.php';

// Iniciar a sessão (caso necessário para outros dados)
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

// Recuperar o ID do produto da URL
$idProduto = isset($_GET['id_produto']) ? $_GET['id_produto'] : 0;

// Verificar se o ID do produto é válido
if ($idProduto > 0) {
    // Buscar detalhes do produto no banco de dados
    $queryProduto = "SELECT * FROM adicionarprodutos WHERE id_produto = :idProduto";
    $stmtProduto = $pdo->prepare($queryProduto);
    $stmtProduto->bindParam(':idProduto', $idProduto, PDO::PARAM_INT);
    $stmtProduto->execute();
    $produto = $stmtProduto->fetch(PDO::FETCH_ASSOC);

    // Verificar se o produto foi encontrado
    if ($produto) {
        $nomeProduto = $produto['nome_produto'];
        $descricaoProduto = $produto['descricao_produto'];
        $precoProduto = $produto['preco_produto'];
        $precoFormatado = number_format($precoProduto, 2, ',', '.'); // Formatação para exibição
        $imagemProduto = $produto['imagem_produto'];
    } else {
        echo "Produto não encontrado.";
        exit;
    }
} else {
    echo "ID de produto inválido.";
    exit;
}

// Variável para controlar a exibição da mensagem de sucesso
$mensagemSucesso = "";

// Processar a adição ao carrinho
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar'])) {
    // Recuperar os dados do formulário
    $quantidade = isset($_POST['quantidade']) ? $_POST['quantidade'] : 1;

    // Verificar se o item já existe no carrinho (com base no ID do carrinho)
    $queryCheckCarrinho = "SELECT id FROM carrinhotemporario WHERE id_produto = :idProduto AND id_carrinho = :idCarrinho";
    $stmtCheckCarrinho = $pdo->prepare($queryCheckCarrinho);
    $stmtCheckCarrinho->bindParam(':idProduto', $idProduto, PDO::PARAM_INT);
    $stmtCheckCarrinho->bindParam(':idCarrinho', $idCarrinho, PDO::PARAM_STR);
    $stmtCheckCarrinho->execute();

    if ($stmtCheckCarrinho->rowCount() > 0) {
        // Se o produto já está no carrinho, atualiza a quantidade
        $queryUpdateCarrinho = "UPDATE carrinhotemporario SET quantidade = quantidade + :quantidade WHERE id_produto = :idProduto AND id_carrinho = :idCarrinho";
        $stmtUpdateCarrinho = $pdo->prepare($queryUpdateCarrinho);
        $stmtUpdateCarrinho->bindParam(':quantidade', $quantidade, PDO::PARAM_INT);
        $stmtUpdateCarrinho->bindParam(':idProduto', $idProduto, PDO::PARAM_INT);
        $stmtUpdateCarrinho->bindParam(':idCarrinho', $idCarrinho, PDO::PARAM_STR);
        $stmtUpdateCarrinho->execute();
    } else {
        // Caso contrário, insere um novo item no carrinho
        $precoTotal = $quantidade * $precoProduto; // Calcular o preço total
        $queryAddCarrinho = "INSERT INTO carrinhotemporario (id_produto, quantidade, preco, id_carrinho) VALUES (:idProduto, :quantidade, :precoTotal, :idCarrinho)";
        $stmtAddCarrinho = $pdo->prepare($queryAddCarrinho);
        $stmtAddCarrinho->bindParam(':idProduto', $idProduto, PDO::PARAM_INT);
        $stmtAddCarrinho->bindParam(':quantidade', $quantidade, PDO::PARAM_INT);
        $stmtAddCarrinho->bindParam(':precoTotal', $precoTotal, PDO::PARAM_STR);
        $stmtAddCarrinho->bindParam(':idCarrinho', $idCarrinho, PDO::PARAM_STR);
        $stmtAddCarrinho->execute();
    }

    // Definir a mensagem de sucesso
    $mensagemSucesso ="Produto adicionado ao carrinho!";
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eletrica - Item</title>

    <link rel="stylesheet" href="./css/cardapio/animate.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="./css/cardapio/bootstrap.min.css" />
    <link rel="stylesheet" href="./css/cardapio/main.css" />

    <style>
       /* CSS para garantir que a mensagem de sucesso fique na frente */
        .alert {
            position: fixed;
            top: 20px;
            left: 50%;
            background-color: #2ecc71;
            transform: translateX(-50%);
            color: #fff;
            z-index: 1050;
            width: calc(100% - 20px);
            max-width: 800px;
            border-radius: 5px;
            opacity: 0;
            visibility: hidden;
            text-align: center;
            transition: opacity 0.5s ease-in-out, visibility 0.5s ease-in-out;
        }

        /* Quando a mensagem está visível */
        .alert.show {
            opacity: 1;
            visibility: visible;
        }
    </style>
</head>
<body>

    <div class="bg-top details">
    </div>

    <header class="width-fix mt-3">
        <div class="card">
            <div class="d-flex">
                <a href="./index.php#cardapio" class="container-voltar">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div class="infos text-center">
                    <h1 class="mb-0"><b>Detalhes do produto</b></h1>
                </div>
            </div>
        </div>
    </header>

    <section class="imagem width-fix mt-4">
        <div class="container-imagem-produto">
            <!-- Exibir a imagem do produto dinamicamente -->
            <img src="./painel/assets/img/uploads/<?php echo $imagemProduto; ?>" alt="Imagem do produto">
        </div>        
        <div class="card mb-2">
            <div class="d-flex">
                <div class="infos-produto">
                    <p class="name mb-2"><b><?php echo $nomeProduto; ?></b></p>
                    <p class="description mb-4"><?php echo $descricaoProduto; ?></p>
                    <p class="price"><b>R$ <?php echo $precoFormatado; ?></b></p>
                </div>
            </div>
        </div>
  
    </section>

    <?php if ($mensagemSucesso): ?>
    <div class="alert alert-success alert-dismissible fade show" style="font-size: 12px;" role="alert">
        <?php echo $mensagemSucesso; ?>
    </div>
    <!-- Exibir mensagem de sucesso, se houver -->

        <script>
            // Exibir e ocultar a mensagem com transição suave + redirecionamento
            setTimeout(function() {
                let alert = document.querySelector('.alert');
                if (alert) {
                    alert.classList.add('fade'); // Adiciona efeito de transição

                    setTimeout(() => {
                        alert.classList.remove('show'); // Remove a visibilidade após a transição

                        // Redireciona para index.php após desaparecer
                        window.location.href = "index.php#cardapio";
                    }, 500); // Aguarda a transição antes do redirecionamento
                }
            }, 1000); // 2500ms = 2.5 segundos
        </script>
    <?php endif; ?>

    <section class="lista width-fix mt-5 pb-5">
        <div class="container-group mb-5">
            <p class="title-categoria mb-0">
                <i class="fas fa-coins"></i>&nbsp; <b>Formas de pagamento</b>
            </p>
            <div class="card mt-2" style=" box-shadow: none !important;">
                <p class="normal-text mb-0"><b>Pix</b></p>
            </div>
            <div class="card mt-2" style=" box-shadow: none !important;">
                <p class="normal-text mb-0"><b>Dinheiro</b></p>
            </div>
            <div class="card mt-2" style=" box-shadow: none !important;">
                <p class="normal-text mb-0"><b>Cartão de débito</b></p>
            </div>
            <div class="card mt-2" style=" box-shadow: none !important;">
                <p class="normal-text mb-0"><b>Cartão de Crétido</b></p>
            </div>

        </div>
    </section>

    <section class="menu-bottom details" id="menu-bottom">
        <div class="add-carrinho">
            <span class="btn-menos" onclick="atualizarQuantidade(-1)">
                <i class="fas fa-minus"></i>
            </span>
            <span class="add-numero-itens" id="quantidade">1</span>
            <span class="btn-mais" onclick="atualizarQuantidade(1)">
                <i class="fas fa-plus"></i>
            </span>
        </div>
        <form method="POST">
            <input type="hidden" name="quantidade" id="quantidadeInput" value="1">
            <button type="submit" name="adicionar" class="btn btn-yellow btn-sm">
                Adicionar <span id="preco">R$ <?php echo $precoFormatado; ?></span>
            </button>
        </form>
    </section>

    <script>
        let quantidade = 1;
        let precoUnitario = parseFloat(<?php echo $precoProduto; ?>);

        function atualizarQuantidade(valor) {
            quantidade += valor;
            if (quantidade < 1) {
                quantidade = 1;
            }
            document.getElementById("quantidade").textContent = quantidade;
            document.getElementById("preco").textContent = `R$ ${(precoUnitario * quantidade).toFixed(2).replace(".", ",")}`;
            document.getElementById("quantidadeInput").value = quantidade; // Atualiza o valor oculto do formulário
        }
    </script>

    <script type="text/javascript" src="./js/bootstrap.bundle.min.js"></script>
    <script type="text/javascript" src="./js/item.js"></script>

</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Açaidinhos</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/cardapio/animate.css" />
    <link rel="stylesheet" href="./assets/css/cardapio/bootstrap.min.css" />
    <link rel="stylesheet" href="./assets/css/cardapio/main.css" />

</head>
<body>

    <div class="bg-top"></div>

    <header class="width-fix mt-5">

        <div class="card">

            <div class="d-flex">

                <div class="container-img"></div>

                <div class="infos">
                    <h1><b>Açaidinhos</b></h1>
                    <div class="infos-sub">
                        <p class="status-open">
                            <i class="fas fa-clock"></i> Aberta
                        </p>
                        <a href="./sobre.html" class="link">
                            ver mais
                        </a>
                    </div>

                </div>

            </div>


        </div>

    </header>

    <?php
        
        require './assets/php/conexao.php';

        try {
            // Busca todas as categorias ordenadas pelo menor ID
            $sql = "SELECT id_categoria, nome_categoria FROM adicionarCategoria ORDER BY id_categoria ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Erro ao buscar categorias: " . $e->getMessage());
        }

        // Captura a categoria selecionada na URL OU seleciona a primeira categoria da lista
        $id_categoria_selecionada = isset($_GET['categoria']) ? (int)$_GET['categoria'] : ($categorias[0]['id_categoria'] ?? null);

        // Se houver uma categoria selecionada, busca os produtos dela
        $produtos = [];
        if ($id_categoria_selecionada) {
            try {
                $sql = "SELECT * FROM adicionarProdutos WHERE id_categoria = :id_categoria";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id_categoria', $id_categoria_selecionada, PDO::PARAM_INT);
                $stmt->execute();
                $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                die("Erro ao buscar produtos: " . $e->getMessage());
            }
        }
    ?>

    <!-- LISTA DE CATEGORIAS -->
    <section class="categoria width-fix mt-4">
        <div class="container-menu" id="listaCategorias">
            <?php if (!empty($categorias)): ?>
                <?php foreach ($categorias as $categoria): ?>
                    <a href="?categoria=<?= $categoria['id_categoria'] ?>" 
                    class="item-categoria btn btn-white btn-sm mb-3 me-3 
                    <?= ($id_categoria_selecionada === $categoria['id_categoria']) ? 'active' : '' ?>">
                        <i class="fa-solid fa-tag"></i>&nbsp; <?= htmlspecialchars($categoria['nome_categoria']) ?>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Nenhuma categoria encontrada.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- LISTA DE PRODUTOS POR CATEGORIA SELECIONADA -->
    <section class="lista width-fix mt-0 pb-5" id="listaItensCardapio">
        <?php if ($id_categoria_selecionada && !empty($produtos)): ?>
            <div class="container-group mb-5">
                <p class="title-categoria"><b>
                    <?= htmlspecialchars($categorias[array_search($id_categoria_selecionada, array_column($categorias, 'id_categoria'))]['nome_categoria']) ?>
                </b></p>
                <?php foreach ($produtos as $produto): ?>
                    <div class="card mb-2 item-cardapio abrir" 
                        onclick="window.location.href='item.php?id=<?= htmlspecialchars($produto['id_produto']) ?>'">
                        <div class="d-flex">
                            <div class="container-img-produto" 
                                style="background-image: url('./assets/img/uploads/<?= htmlspecialchars($produto['imagem_produto'] ?: './img/default.jpg') ?>'); background-size: cover;">
                            </div>
                            <div class="infos-produto">
                                <p class="name"><b><?= htmlspecialchars($produto['nome_produto']) ?></b></p>
                                <p class="description"><?= htmlspecialchars($produto['descricao_produto'] ?: 'Sem descrição.') ?></p>
                                <p class="price"><b>R$ <?= number_format($produto['preco_produto'], 2, ',', '.') ?></b></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
            </div>
        <?php elseif ($id_categoria_selecionada): ?>
            <p class="text-center"></p>
        <?php endif; ?>
    </section>


    <section class="menu-bottom" id="menu-bottom" >
        <a class="menu-bottom-item active">
            <i class="fas fa-book-open"></i>&nbsp; Cardápio
        </a>
        <a href="./pedido.php" class="menu-bottom-item">
            <i class="fas fa-utensils"></i>&nbsp; Pedido
        </a>
        <a href="./carrinho.php" class="menu-bottom-item">
            <span class="badge-total-carrinho">2</span>
            <!-- <i class="fas fa-shopping-cart"></i> -->
            Carrinho
        </a>
    </section>

    <section class="menu-bottom disabled hidden" id="menu-bottom-closed">
        <p class="mb-0"><b>Loja fechada no momento.</b></p>
    </section>


    <script type="text/javascript" src="./js/bootstrap.bundle.min.js"></script>
    <script type="text/javascript" src="./js/cardapio.js"></script>
    
</body>
</html>
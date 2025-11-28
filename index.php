<?php
require './assets/php/conexao.php';

/* ===========================================
   1. PEGAR EMPRESA DA URL (?empresa=...)
   =========================================== */
$empresaID = $_GET['empresa'] ?? null;

if (!$empresaID) {
    die('Empresa não informada.');
}

/* ===========================================
   2. STATUS ABERTO / FECHADO (tabela entregas)
   =========================================== */
$lojaAberta = false;

try {
    $sql = "SELECT entrega FROM entregas WHERE id_empresa = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $empresaID);
    $stmt->execute();
    $entrega = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($entrega && (int)$entrega['entrega'] === 1) {
        $lojaAberta = true;
    }
} catch (PDOException $e) {
    // Se der erro, mantém como fechada (false) para não quebrar a página
    $lojaAberta = false;
}

/* ===========================================
   3. BUSCAR NOME E IMAGEM DA EMPRESA (opcional)
   =========================================== */
$nomeEmpresa = 'Açainhadinhos';
$imagemEmpresa = './assets/img/default.jpg';

try {
    $sql = "SELECT nome_empresa, imagem FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $empresaID);
    $stmt->execute();
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($empresa) {
        if (!empty($empresa['nome_empresa'])) {
            $nomeEmpresa = $empresa['nome_empresa'];
        }
        if (!empty($empresa['imagem'])) {
            $imagemEmpresa = './assets/img/uploads/' . $empresa['imagem'];
        }
    }
} catch (PDOException $e) {
    // Se der erro, segue com nome/imagem padrão
}

/* ===========================================
   4. BUSCAR CATEGORIAS DA EMPRESA
   =========================================== */
try {
    $sql = "SELECT id_categoria, nome_categoria 
            FROM adicionarCategoria 
            WHERE empresa_id = :empresa
            ORDER BY id_categoria ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':empresa', $empresaID);
    $stmt->execute();
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao buscar categorias: " . $e->getMessage());
}

/* Categoria selecionada (ou primeira da empresa) */
$id_categoria_selecionada = isset($_GET['categoria'])
    ? (int)$_GET['categoria']
    : ($categorias[0]['id_categoria'] ?? null);

/* ===========================================
   5. BUSCAR PRODUTOS DA CATEGORIA E EMPRESA
   =========================================== */
$produtos = [];

if ($id_categoria_selecionada) {
    try {
        $sql = "SELECT * 
                FROM adicionarProdutos 
                WHERE id_categoria = :id_categoria
                  AND id_empresa   = :empresa";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id_categoria', $id_categoria_selecionada, PDO::PARAM_INT);
        $stmt->bindValue(':empresa', $empresaID);
        $stmt->execute();
        $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Erro ao buscar produtos: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($nomeEmpresa) ?></title>

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

                <div class="container-img"
                     style="background-image:url('<?= htmlspecialchars($imagemEmpresa) ?>'); background-size:cover;">
                </div>

                <div class="infos">
                    <h1><b><?= htmlspecialchars($nomeEmpresa) ?></b></h1>
                    <div class="infos-sub">
                        <?php if ($lojaAberta): ?>
                            <p class="status-open">
                                <i class="fas fa-clock"></i> Aberta
                            </p>
                        <?php else: ?>
                            <p class="status-open closed">
                                <i class="fas fa-clock"></i> Fechado
                            </p>
                        <?php endif; ?>
                        <a href="./sobre.html" class="link">
                            ver mais
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </header>

    <!-- LISTA DE CATEGORIAS -->
    <section class="categoria width-fix mt-4">
        <div class="container-menu" id="listaCategorias">
            <?php if (!empty($categorias)): ?>
                <?php foreach ($categororias as $categoria): ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- (CORRIGIDO: saída das categorias – versão certa abaixo) -->
    <section class="categoria width-fix mt-4">
        <div class="container-menu" id="listaCategorias">
            <?php if (!empty($categorias)): ?>
                <?php foreach ($categorias as $categoria): ?>
                    <a href="?empresa=<?= urlencode($empresaID) ?>&categoria=<?= $categoria['id_categoria'] ?>" 
                       class="item-categoria btn btn-white btn-sm mb-3 me-3 
                       <?= ($id_categoria_selecionada === (int)$categoria['id_categoria']) ? 'active' : '' ?>">
                        <i class="fa-solid fa-tag"></i>&nbsp;
                        <?= htmlspecialchars($categoria['nome_categoria']) ?>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <p></p>
            <?php endif; ?>
        </div>
    </section>

    <!-- LISTA DE PRODUTOS POR CATEGORIA SELECIONADA -->
    <section class="lista width-fix mt-0 pb-5" id="listaItensCardapio">
        <?php if ($id_categoria_selecionada && !empty($produtos)): ?>
            <div class="container-group mb-5">
                <p class="title-categoria"><b>
                    <?php
                    // pegar o nome da categoria selecionada
                    $nomeCat = '';
                    if (!empty($categorias)) {
                        foreach ($categorias as $c) {
                            if ((int)$c['id_categoria'] === (int)$id_categoria_selecionada) {
                                $nomeCat = $c['nome_categoria'];
                                break;
                            }
                        }
                    }
                    echo htmlspecialchars($nomeCat);
                    ?>
                </b></p>

                <?php foreach ($produtos as $produto): ?>
                    <?php
                        $imgProd = !empty($produto['imagem_produto'])
                            ? './assets/img/uploads/' . $produto['imagem_produto']
                            : './assets/img/favicon/logo.png';
                    ?>
                    <div class="card mb-2 item-cardapio abrir" 
                         onclick="window.location.href='item.php?id=<?= htmlspecialchars($produto['id_produto']) ?>&empresa=<?= urlencode($empresaID) ?>'">
                        <div class="d-flex">
                            <div class="container-img-produto" 
                                 style="background-image: url('<?= htmlspecialchars($imgProd) ?>'); background-size: cover;">
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

    <!-- MENU ABERTO / FECHADO -->
    <section class="menu-bottom <?= $lojaAberta ? '' : 'disabled hidden' ?>" id="menu-bottom">
        <a class="menu-bottom-item active">
            <i class="fas fa-book-open"></i>&nbsp; Cardápio
        </a>
        <a href="./pedido.php?empresa=<?= urlencode($empresaID) ?>" class="menu-bottom-item">
            <i class="fas fa-utensils"></i>&nbsp; Pedido
        </a>
        <a href="./carrinho.php?empresa=<?= urlencode($empresaID) ?>" class="menu-bottom-item">
            <span class="badge-total-carrinho">2</span>
            Carrinho
        </a>
    </section>

    <section class="menu-bottom disabled <?= $lojaAberta ? 'hidden' : '' ?>" id="menu-bottom-closed">
        <p class="mb-0"><b>Loja fechada no momento.</b></p>
    </section>

    <script type="text/javascript" src="./js/bootstrap.bundle.min.js"></script>
    <script type="text/javascript" src="./js/cardapio.js"></script>
    
</body>
</html>

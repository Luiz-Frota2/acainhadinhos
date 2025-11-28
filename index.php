<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);


require './assets/php/conexao.php';

/* =========================================================
    1. PEGAR ID DA EMPRESA
   ========================================================= */
$empresaID = $_GET['empresa'] ?? null;

if (!$empresaID) {
    die("<h2>Erro: Nenhuma empresa informada.</h2>");
}

/* =========================================================
    2. BUSCAR DADOS DA EMPRESA (sobre_empresa)
   ========================================================= */
$stmt = $pdo->prepare("
    SELECT nome_empresa, imagem 
    FROM sobre_empresa 
    WHERE id_selecionado = :id LIMIT 1
");
$stmt->bindValue(":id", $empresaID);
$stmt->execute();
$empresa = $stmt->fetch(PDO::FETCH_ASSOC);

$nomeEmpresa = $empresa["nome_empresa"] ?? "Empresa sem nome cadastrado";
$fotoEmpresa = (!empty($empresa["imagem"]))
    ? "./assets/img/uploads/" . $empresa["imagem"]
    : "./assets/img/default.jpg";

/* =========================================================
    3. ENDEREÇO DA EMPRESA
   ========================================================= */
$stmt = $pdo->prepare("
    SELECT cidade, uf 
    FROM endereco_empresa 
    WHERE empresa_id = :id LIMIT 1
");
$stmt->bindValue(":id", $empresaID);
$stmt->execute();
$endereco = $stmt->fetch(PDO::FETCH_ASSOC);

$cidadeUF = $endereco ? ($endereco["cidade"] . " - " . $endereco["uf"]) : "Localização não informada";

/* =========================================================
    4. HORÁRIO DE FUNCIONAMENTO
   ========================================================= */
$horaAtual = date("H:i");

$aberta = false; // padrão

$stmt = $pdo->prepare("
    SELECT tempo_min, tempo_max 
    FROM entregas 
    WHERE id_empresa = :id LIMIT 1
");
$stmt->bindValue(":id", $empresaID);
$stmt->execute();
$func = $stmt->fetch(PDO::FETCH_ASSOC);

if ($func) {
    $abertura = "10:00";
    $fechamento = "23:00";
    $aberta = ($horaAtual >= $abertura && $horaAtual <= $fechamento);
}

/* =========================================================
    5. BUSCAR CATEGORIAS DA EMPRESA
   ========================================================= */
$stmt = $pdo->prepare("
    SELECT id_categoria, nome_categoria
    FROM adicionarCategoria
    WHERE empresa_id = :id
    ORDER BY id_categoria ASC
");
$stmt->bindValue(":id", $empresaID);
$stmt->execute();
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

$id_categoria_selecionada = $_GET["categoria"] ?? ($categorias[0]["id_categoria"] ?? null);

/* =========================================================
    6. BUSCAR PRODUTOS
   ========================================================= */
$produtos = [];
if ($id_categoria_selecionada) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM adicionarProdutos
        WHERE id_categoria = :cat
        AND id_empresa = :id
    ");
    $stmt->bindValue(":cat", $id_categoria_selecionada);
    $stmt->bindValue(":id", $empresaID);
    $stmt->execute();
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($nomeEmpresa) ?></title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/cardapio/animate.css">
    <link rel="stylesheet" href="./assets/css/cardapio/bootstrap.min.css">
    <link rel="stylesheet" href="./assets/css/cardapio/main.css">
</head>

<body>

<div class="bg-top"></div>

<header class="width-fix mt-5">
    <div class="card">
        <div class="d-flex">

            <div class="container-img"
                 style="background-image: url('<?= $fotoEmpresa ?>'); background-size: cover;"></div>

            <div class="infos">
                <h1><b><?= htmlspecialchars($nomeEmpresa) ?></b></h1>

                <div class="infos-sub">
                    <?php if ($aberta): ?>
                        <p class="status-open"><i class="fas fa-clock"></i> Aberta</p>
                    <?php else: ?>
                        <p class="status-close"><i class="fas fa-clock"></i> Fechada</p>
                    <?php endif; ?>

                    <a href="./sobre.php?empresa=<?= $empresaID ?>" class="link">ver mais</a>
                </div>
            </div>

        </div>
    </div>
</header>

<!-- CATEGORIAS -->
<section class="categoria width-fix mt-4">
    <div class="container-menu">

        <?php if ($categorias): ?>
            <?php foreach ($categorias as $c): ?>
                <a href="?empresa=<?= $empresaID ?>&categoria=<?= $c['id_categoria'] ?>"
                   class="item-categoria btn btn-white btn-sm mb-2 me-2 
                   <?= ($id_categoria_selecionada == $c['id_categoria']) ? 'active' : '' ?>">
                    <i class="fa-solid fa-tag"></i> <?= htmlspecialchars($c['nome_categoria']) ?>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted">Nenhuma categoria cadastrada.</p>
        <?php endif; ?>

    </div>
</section>

<!-- PRODUTOS -->
<section class="lista width-fix mt-2 pb-5">

<?php if ($aberta): ?>

    <?php if ($produtos): ?>
        <div class="container-group mb-5">
            <?php
            $catName = "";
            foreach ($categorias as $c) {
                if ($c["id_categoria"] == $id_categoria_selecionada) {
                    $catName = $c["nome_categoria"];
                }
            }
            ?>
            <p class="title-categoria"><b><?= htmlspecialchars($catName) ?></b></p>

            <?php foreach ($produtos as $p): ?>
            <div class="card mb-2 item-cardapio abrir"
                 onclick="window.location.href='item.php?id=<?= $p['id_produto'] ?>&empresa=<?= $empresaID ?>'">

                <div class="d-flex">
                    <div class="container-img-produto"
                         style="background-image: url('./assets/img/uploads/<?= htmlspecialchars($p['imagem_produto']) ?>');">
                    </div>

                    <div class="infos-produto">
                        <p class="name"><b><?= htmlspecialchars($p['nome_produto']) ?></b></p>
                        <p class="description"><?= htmlspecialchars($p['descricao_produto'] ?: 'Sem descrição.') ?></p>
                        <p class="price"><b>R$ <?= number_format($p['preco_produto'], 2, ',', '.') ?></b></p>
                    </div>
                </div>

            </div>
            <?php endforeach; ?>

        </div>

    <?php else: ?>
        <p class="text-center mt-4">Nenhum produto nesta categoria.</p>
    <?php endif; ?>

<?php else: ?>

    <div class="text-center mt-4">
        <p class="text-danger"><b>Loja fechada no momento.</b></p>
    </div>

<?php endif; ?>

</section>

<!-- MENU INFERIOR -->
<section class="menu-bottom <?= $aberta ? '' : 'disabled hidden' ?>">
    <a class="menu-bottom-item active">
        <i class="fas fa-book-open"></i>&nbsp; Cardápio
    </a>

    <a href="./pedido.php?empresa=<?= $empresaID ?>" class="menu-bottom-item">
        <i class="fas fa-utensils"></i>&nbsp; Pedido
    </a>

    <a href="./carrinho.php?empresa=<?= $empresaID ?>" class="menu-bottom-item">
        Carrinho
    </a>
</section>

<script src="./js/bootstrap.bundle.min.js"></script>
<script src="./js/cardapio.js"></script>

</body>
</html>

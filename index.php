<?php
require './assets/php/conexao.php';

/* ==========================================================
   1. PEGAR A EMPRESA SELECIONADA
   ========================================================== */
$empresaID = $_GET['empresa'] ?? null;

if (!$empresaID) {
    die("<h2>Erro: nenhuma empresa informada.</h2>");
}

/* ==========================================================
   2. BUSCAR INFORMAÇÕES DA EMPRESA
   ========================================================== */
$stmt = $pdo->prepare("SELECT * FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1");
$stmt->bindValue(":id", $empresaID);
$stmt->execute();
$empresa = $stmt->fetch(PDO::FETCH_ASSOC);

$nomeEmpresa = $empresa["nome_empresa"] ?? "Açainhadinhos";
$imgEmpresa = !empty($empresa["imagem"]) ?
    "./assets/img/uploads/" . $empresa["imagem"] :
    "./assets/img/default.jpg";

/* ==========================================================
   3. ENDEREÇO DA EMPRESA
   ========================================================== */
$stmt = $pdo->prepare("SELECT * FROM endereco_empresa WHERE empresa_id = :id LIMIT 1");
$stmt->bindValue(":id", $empresaID);
$stmt->execute();
$end = $stmt->fetch(PDO::FETCH_ASSOC);

$cidadeUF = $end ? ($end["cidade"] . " - " . $end["uf"]) : "Cidade não informada";

/* ==========================================================
   4. HORÁRIO / STATUS (ABERTA ou FECHADA)
   ========================================================== */
$stmt = $pdo->prepare("SELECT * FROM entregas WHERE id_empresa = :id LIMIT 1");
$stmt->bindValue(":id", $empresaID);
$stmt->execute();
$horario = $stmt->fetch(PDO::FETCH_ASSOC);

$abertura = $horario["abertura"] ?? null;
$fechamento = $horario["fechamento"] ?? null;

$horaAtual = date("H:i");

$abertaAgora = false;

if ($abertura && $fechamento) {
    if ($horaAtual >= $abertura && $horaAtual <= $fechamento) {
        $abertaAgora = true;
    }
}

/* ==========================================================
   5. BUSCAR CATEGORIAS SOMENTE DA EMPRESA
   ========================================================== */
$stmt = $pdo->prepare("
    SELECT id_categoria, nome_categoria
    FROM adicionarCategoria
    WHERE empresa_id = :id
    ORDER BY id_categoria ASC
");
$stmt->bindValue(":id", $empresaID);
$stmt->execute();
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Categoria padrão */
$id_categoria_selecionada = $_GET['categoria'] ?? ($categorias[0]['id_categoria'] ?? null);

/* ==========================================================
   6. BUSCAR PRODUTOS DA CATEGORIA E EMPRESA
   ========================================================== */
$produtos = [];

if ($id_categoria_selecionada) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM adicionarProdutos
        WHERE id_categoria = :cat
        AND id_empresa = :empresa
        ORDER BY id_produto DESC
    ");
    $stmt->bindValue(":cat", $id_categoria_selecionada);
    $stmt->bindValue(":empresa", $empresaID);
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
    <link rel="stylesheet" href="./assets/css/cardapio/animate.css" />
    <link rel="stylesheet" href="./assets/css/cardapio/bootstrap.min.css" />
    <link rel="stylesheet" href="./assets/css/cardapio/main.css" />
</head>
<body>

    <div class="bg-top"></div>

    <!-- CABEÇALHO -->
    <header class="width-fix mt-5">
        <div class="card">
            <div class="d-flex">

                <div class="container-img"
                     style="background-image:url('<?= $imgEmpresa ?>'); background-size:cover;">
                </div>

                <div class="infos">
                    <h1><b><?= htmlspecialchars($nomeEmpresa) ?></b></h1>

                    <div class="infos-sub">
                        <?php if ($abertaAgora): ?>
                            <p class="status-open"><i class="fas fa-clock"></i> Aberta agora</p>
                        <?php else: ?>
                            <p class="status-close"><i class="fas fa-clock"></i> Fechada</p>
                        <?php endif; ?>

                        <a href="./sobre.php?empresa=<?= $empresaID ?>" class="link">ver mais</a>
                    </div>

                </div>
            </div>
        </div>
    </header>

    <!-- LISTA DE CATEGORIAS -->
    <section class="categoria width-fix mt-4">
        <div class="container-menu">

            <?php if ($categorias): ?>
                <?php foreach ($categorias as $c): ?>
                    <a href="?empresa=<?= $empresaID ?>&categoria=<?= $c['id_categoria'] ?>"
                       class="item-categoria btn btn-white btn-sm mb-3 me-3
                       <?= ($id_categoria_selecionada == $c['id_categoria']) ? 'active' : '' ?>">
                        <i class="fa-solid fa-tag"></i>
                        <?= htmlspecialchars($c['nome_categoria']) ?>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted">Nenhuma categoria cadastrada.</p>
            <?php endif; ?>

        </div>
    </section>

    <!-- LISTA DE PRODUTOS -->
    <section class="lista width-fix mt-0 pb-5">

        <?php if (!$abertaAgora): ?>
            <div class="text-center mt-5">
                <h4><b>Loja fechada no momento</b></h4>
                <p>Horário de funcionamento: <?= $abertura ?> às <?= $fechamento ?></p>
            </div>
        <?php else: ?>

            <?php if ($produtos): ?>
                <div class="container-group mb-5">

                    <p class="title-categoria"><b>
                        <?php
                        $catNome = "";
                        foreach ($categorias as $c) {
                            if ($c['id_categoria'] == $id_categoria_selecionada) {
                                $catNome = $c['nome_categoria'];
                            }
                        }
                        echo htmlspecialchars($catNome);
                        ?>
                    </b></p>

                    <?php foreach ($produtos as $p): ?>
                        <div class="card mb-2 item-cardapio abrir"
                             onclick="window.location.href='item.php?id=<?= $p['id_produto'] ?>&empresa=<?= $empresaID ?>'">

                            <div class="d-flex">

                                <?php
                                $img = (!empty($p["imagem_produto"]))
                                    ? "./assets/img/uploads/" . $p["imagem_produto"]
                                    : "./assets/img/default.jpg";
                                ?>

                                <div class="container-img-produto"
                                     style="background-image:url('<?= $img ?>'); background-size:cover;">
                                </div>

                                <div class="infos-produto">
                                    <p class="name"><b><?= htmlspecialchars($p['nome_produto']) ?></b></p>
                                    <p class="description"><?= htmlspecialchars($p['descricao_produto'] ?: "Sem descrição.") ?></p>
                                    <p class="price"><b>R$
                                        <?= number_format($p['preco_produto'], 2, ',', '.') ?>
                                    </b></p>
                                </div>

                            </div>

                        </div>
                    <?php endforeach; ?>

                </div>

            <?php else: ?>
                <p class="text-center mt-4">Nenhum produto nesta categoria.</p>
            <?php endif; ?>

        <?php endif; ?>

    </section>

    <!-- MENU INFERIOR -->
    <section class="menu-bottom <?= $abertaAgora ? '' : 'disabled hidden' ?>">
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

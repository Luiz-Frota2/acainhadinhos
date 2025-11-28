<?php
require './assets/php/conexao.php';

/* ===========================================
   PEGAR EMPRESA DA URL
   =========================================== */
$empresaID = $_GET['empresa'] ?? null;

if (!$empresaID) {
    die('Empresa não informada.');
}

/* ===========================================
   STATUS ABERTA / FECHADA
   =========================================== */
$lojaAberta = false;

try {
    $sql = "SELECT entrega FROM entregas WHERE id_empresa = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $empresaID);
    $stmt->execute();
    $entregaInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($entregaInfo && (int)$entregaInfo['entrega'] === 1) {
        $lojaAberta = true;
    }
} catch (PDOException $e) {
    $lojaAberta = false;
}

/* ===========================================
   BUSCAR DADOS DA EMPRESA
   =========================================== */
$nomeEmpresa = "Açainhadinhos";
$imagemEmpresa = "./assets/img/favicon/logo.png";
$sobreTexto = "Nenhuma informação disponível.";

try {
    $sql = "SELECT nome_empresa, sobre_empresa, imagem 
            FROM sobre_empresa 
            WHERE id_selecionado = :id LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $empresaID);
    $stmt->execute();
    $info = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($info) {
        if (!empty($info['nome_empresa']))  $nomeEmpresa = $info['nome_empresa'];
        if (!empty($info['sobre_empresa'])) $sobreTexto = $info['sobre_empresa'];
        if (!empty($info['imagem']))        $imagemEmpresa = "./assets/img/uploads/" . $info['imagem'];
    }
} catch (PDOException $e) {
}

/* ===========================================
   ENDEREÇO
   =========================================== */
$enderecoCompleto = "Endereço não informado";

try {
    $sql = "SELECT endereco, numero, bairro, cidade, uf 
            FROM endereco_empresa 
            WHERE empresa_id = :id LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $empresaID);
    $stmt->execute();
    $end = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($end) {
        $enderecoCompleto =
            $end['endereco'] . ", " . $end['numero'] .
            " - " . $end['bairro'] . ", " .
            $end['cidade'] . " - " . $end['uf'];
    }
} catch (PDOException $e) {
}

/* ===========================================
   FORMAS DE PAGAMENTO
   =========================================== */
$pagamento = [
    "dinheiro"      => false,
    "pix"           => false,
    "cartaoDebito"  => false,
    "cartaoCredito" => false
];

try {
    $sql = "SELECT * FROM formas_pagamento WHERE empresa_id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $empresaID);
    $stmt->execute();
    $pg = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pg) {
        foreach ($pagamento as $k => $v) {
            $pagamento[$k] = $pg[$k] == 1;
        }
    }
} catch (PDOException $e) {
}

/* ===========================================
   HORÁRIOS DE FUNCIONAMENTO
   =========================================== */
$horarios = [];

try {
    $sql = "SELECT * FROM horarios_funcionamento 
            WHERE empresa_id = :id ORDER BY id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $empresaID);
    $stmt->execute();
    $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sobre - <?= htmlspecialchars($nomeEmpresa) ?></title>

    <link rel="shortcut icon" href="<?= htmlspecialchars($imagemEmpresa) ?>" type="image/x-icon">

    <link rel="stylesheet" href="./assets/css/cardapio/animate.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/cardapio/bootstrap.min.css" />
    <link rel="stylesheet" href="./assets/css/cardapio/main.css" />
</head>

<body>

    <div class="bg-top sobre"></div>

    <header class="width-fix mt-3">
        <div class="card">
            <div class="d-flex">

                <a href="./index.php?empresa=<?= urlencode($empresaID) ?>" class="container-voltar">
                    <i class="fas fa-arrow-left"></i>
                </a>

                <div class="infos text-center">
                    <h1 class="mb-0"><b>Sobre a loja</b></h1>
                </div>

            </div>
        </div>
    </header>

    <section class="width-fix mt-5 mb-4">
        <div class="card">
            <div class="d-flex">

                <div class="container-img-sobre"
                    style="background-image:url('<?= htmlspecialchars($imagemEmpresa) ?>');">
                </div>

                <div class="infos">
                    <h1 class="title-sobre"><b><?= htmlspecialchars($nomeEmpresa) ?></b></h1>
                    <div class="infos-sub">
                        <p class="sobre mb-2"><?= nl2br(htmlspecialchars($sobreTexto)) ?></p>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <section class="lista width-fix mt-5 pb-5">

        <!-- ENDEREÇO -->
        <div class="container-group mb-5">
            <p class="title-categoria mb-0">
                <i class="fas fa-map-marker-alt"></i>&nbsp; <b>Endereço</b>
            </p>
            <div class="card mt-2">
                <p class="normal-text mb-0"><?= htmlspecialchars($enderecoCompleto) ?></p>
            </div>
        </div>

        <!-- HORÁRIOS -->
        <div class="container-group mb-5">
            <p class="title-categoria mb-0">
                <i class="fas fa-clock"></i>&nbsp; <b>Horário de funcionamento</b>
            </p>

            <?php if ($horarios): ?>
                <?php foreach ($horarios as $h): ?>
                    <div class="card mt-2">
                        <p class="normal-text mb-0">
                            <b><?= htmlspecialchars($h['dia_de']) ?>
                                <?php if ($h['dia_ate']): ?>
                                    à <?= htmlspecialchars($h['dia_ate']) ?>
                                <?php endif; ?>
                            </b>
                        </p>

                        <p class="normal-text mb-0">
                            <?= substr($h['primeira_hora'], 0, 5) ?>
                            às
                            <?= substr($h['termino_primeiro_turno'], 0, 5) ?>
                        </p>

                        <?php if ($h['comeco_segundo_turno'] && $h['termino_segundo_turno']): ?>
                            <p class="normal-text mb-0">
                                e
                                <?= substr($h['comeco_segundo_turno'], 0, 5) ?>
                                às
                                <?= substr($h['termino_segundo_turno'], 0, 5) ?>
                            </p>
                        <?php endif; ?>

                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="card mt-2">
                    <p class="normal-text mb-0">Horário não informado</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- FORMAS DE PAGAMENTO -->
        <div class="container-group mb-5">
            <p class="title-categoria mb-0">
                <i class="fas fa-coins"></i>&nbsp; <b>Formas de pagamento</b>
            </p>

            <?php if (array_filter($pagamento)): ?>
                <?php if ($pagamento["dinheiro"]): ?>
                    <div class="card mt-2">
                        <p class="normal-text mb-0"><b>Dinheiro</b></p>
                    </div>
                <?php endif; ?>

                <?php if ($pagamento["pix"]): ?>
                    <div class="card mt-2">
                        <p class="normal-text mb-0"><b>Pix</b></p>
                    </div>
                <?php endif; ?>

                <?php if ($pagamento["cartaoDebito"]): ?>
                    <div class="card mt-2">
                        <p class="normal-text mb-0"><b>Cartão Débito</b></p>
                    </div>
                <?php endif; ?>

                <?php if ($pagamento["cartaoCredito"]): ?>
                    <div class="card mt-2">
                        <p class="normal-text mb-0"><b>Cartão Crédito</b></p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="card mt-2">
                    <p class="normal-text mb-0">Nenhuma forma de pagamento cadastrada</p>
                </div>
            <?php endif; ?>
        </div>

    </section>

    <!-- BOTÃO VOLTAR -->
    <a href="./index.php?empresa=<?= urlencode($empresaID) ?>"
        class="btn btn-yellow btn-full voltar">
        Voltar para o cardápio
    </a>

    <!-- LOJA FECHADA / ABERTA -->
    <section
        class="menu-bottom disabled <?= $lojaAberta ? 'hidden' : '' ?>"
        id="menu-bottom-closed">
        <p class="mb-0"><b>Loja fechada no momento.</b></p>
    </section>

    <script src="./js/bootstrap.bundle.min.js"></script>
    <script src="./js/item.js"></script>

</body>

</html>
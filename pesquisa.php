<?php
// ===========================
// CONFIGURAÇÕES INICIAIS
// ===========================
require "./assets/php/conexao.php"; // SEU ARQUIVO DE CONEXÃO

// ============= MATRIZ =============
$matriz_id = "principal_1";

/* ---------------------------------------------------
   BUSCA NOME DA MATRIZ (sobre_empresa)
---------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT nome_empresa 
    FROM sobre_empresa 
    WHERE id_selecionado = ?
    LIMIT 1
");
$stmt->execute([$matriz_id]);
$dados_matriz_nome = $stmt->fetch(PDO::FETCH_ASSOC);

$nome_matriz = $dados_matriz_nome["nome_empresa"] ?? "Matriz Central";

/* ---------------------------------------------------
   BUSCA ENDEREÇO DA MATRIZ
---------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT * FROM endereco_empresa
    WHERE empresa_id = ?
    LIMIT 1
");
$stmt->execute([$matriz_id]);
$end_matriz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$end_matriz) {
    $end_matriz = [
        "cidade" => "Coari",
        "uf" => "AM",
        "endereco" => "Centro",
        "bairro" => "Centro",
        "numero" => "S/N"
    ];
}

/* ---------------------------------------------------
   ENTREGA MATRIZ
---------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT * FROM entregas
    WHERE id_empresa = ?
    LIMIT 1
");
$stmt->execute([$matriz_id]);
$entrega_matriz = $stmt->fetch(PDO::FETCH_ASSOC);

$tempo_entrega_matriz = $entrega_matriz ? "{$entrega_matriz['tempo_min']}–{$entrega_matriz['tempo_max']} min" : "30–40 min";

/* ---------------------------------------------------
   TAXA MATRIZ
---------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT valor_taxa 
    FROM entrega_taxas_unica eu
    JOIN entregas e ON eu.id_entrega = e.id_entrega
    WHERE e.id_empresa = ?
    LIMIT 1
");
$stmt->execute([$matriz_id]);
$taxa_matriz = $stmt->fetchColumn();
$taxa_matriz = $taxa_matriz ? "A partir de R$ " . number_format($taxa_matriz, 2, ',', '.') : "Consultar";

/* ---------------------------------------------------
   SITUAÇÃO MATRIZ
---------------------------------------------------- */
$situacao_matriz = "Aceitando pedidos agora";


// =====================================================
// =========== BUSCA TODAS AS FILIAIS / FRANQUIAS ===========
// =====================================================

$stmt = $pdo->query("
    SELECT *
    FROM unidades
    ORDER BY id ASC
");

$unidades = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Função p/ buscar endereço de cada unidade
function getEndereco($pdo, $empresa_id) {
    $sql = "SELECT * FROM endereco_empresa WHERE empresa_id = ? LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([$empresa_id]);
    return $st->fetch(PDO::FETCH_ASSOC);
}

// Função p/ buscar entrega/taxa da unidade
function getEntrega($pdo, $empresa_id) {
    $sql = "SELECT * FROM entregas WHERE id_empresa = ? LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([$empresa_id]);
    return $st->fetch(PDO::FETCH_ASSOC);
}

function getTaxa($pdo, $entrega_id) {
    if (!$entrega_id) return null;
    $sql = "SELECT valor_taxa FROM entrega_taxas_unica WHERE id_entrega = ? LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([$entrega_id]);
    return $st->fetchColumn();
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8" />
<title>Escolha a Unidade - Delivery</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />

<style>
/* === TODO SEU CSS ORIGINAL AQUI === */
<?php include "style_seletor.css"; ?>
</style>

</head>
<body>

<div class="page">
<div class="app-shell">

<!-- =============== CABEÇALHO =============== -->
<header class="top-bar">
    <div class="brand">
        <div class="logo-circle">DL</div>
        <div class="brand-text">
            <div class="brand-name"><?= htmlspecialchars($nome_matriz) ?></div>
            <div class="brand-sub">Escolha a unidade</div>
        </div>
    </div>

    <div class="location">
        <div class="location-dot"></div>
        Entregando em <span><?= $end_matriz["cidade"] ?> - <?= $end_matriz["uf"] ?></span>
    </div>
</header>


<!-- ==========================================================
   HERO – (mesmo do seu layout, mantido)
========================================================== -->
<!-- (mantém todo o HTML do HERO aqui, igual ao seu modelo) -->



<!-- ==========================================================
   LOJA PRINCIPAL (MATRIZ)
========================================================== -->

<section class="main-store">
<header class="main-header">
    <div class="main-titles">
        <div class="main-label">Loja principal da rede</div>
        <div class="main-name"><?= $nome_matriz ?> • Matriz</div>
    </div>
    <div class="badge-matriz">Matriz</div>
</header>

<div class="main-body">

    <div class="info-list">
        <div>
            <div class="info-label">Cidade / Endereço</div>
            <div class="info-value">
                <?= $end_matriz["cidade"] . " - " . $end_matriz["uf"] ?> • 
                <?= $end_matriz["endereco"] ?> - <?= $end_matriz["bairro"] ?>, nº <?= $end_matriz["numero"] ?>
            </div>
        </div>

        <div>
            <div class="info-label">Tempo médio de entrega</div>
            <div class="info-value"><?= $tempo_entrega_matriz ?></div>
        </div>

        <div>
            <div class="info-label">Taxa de entrega</div>
            <div class="info-value"><?= $taxa_matriz ?></div>
        </div>

        <div class="info-tags">
            <span class="info-tag">Atende toda a cidade</span>
            <span class="info-tag">Maior cardápio da rede</span>
        </div>
    </div>

    <div class="info-list">
        <div>
            <div class="info-label">Horário de hoje</div>
            <div class="info-value">10:00 às 23:00</div>
        </div>

        <div>
            <div class="info-label">Avaliação média</div>
            <div class="info-value">4,8 ★ (1.240 avaliações)</div>
        </div>

        <div>
            <div class="info-label">Situação</div>
            <div class="info-value"><?= $situacao_matriz ?></div>
        </div>
    </div>
</div>

<footer class="main-footer">
    <a href="index.php?id=principal_1" class="btn-primary">
        <span class="btn-icon">🛒</span>
        Fazer pedido na Matriz
    </a>
    <p class="hint">Página vinculada à unidade principal.</p>
</footer>

</section>




<!-- ==========================================================
   OUTRAS UNIDADES (Filiais + Franquias)
========================================================== -->

<section class="other-stores">
<header class="other-header">
    <div class="other-title">
        Outras unidades
        <span>Filiais e Franquias disponíveis</span>
    </div>
</header>

<div class="other-grid">

<?php foreach ($unidades as $u): 

    $empresa_id = $u["empresa_id"];

    // Nome fictício se não existir
    $nome_unidade = $u["nome"] ?: "Unidade " . strtoupper($empresa_id);

    // Endereço da unidade
    $end = getEndereco($pdo, $empresa_id);
    $cidade = $end["cidade"] ?? "Cidade não informada";
    $uf = $end["uf"] ?? "AM";

    // Entrega
    $ent = getEntrega($pdo, $empresa_id);
    $tempo = $ent ? "{$ent['tempo_min']}–{$ent['tempo_max']} min" : "—";
    $taxa = getTaxa($pdo, $ent["id_entrega"] ?? null);
    $taxa = $taxa ? "R$ " . number_format($taxa, 2, ',', '.') : "Consultar";

    // Situação
    $status = $u["status"] === "Ativa" ? "Online" : "Fechada no momento";
    $offline = ($status !== "Online") ? "offline" : "";
?>

    <article class="store-card">
        <div class="store-header">
            <div class="store-name"><?= htmlspecialchars($nome_unidade) ?></div>

            <?php if ($u["tipo"] == "Filial"): ?>
            <span class="badge-tipo badge-filial">Filial</span>
            <?php else: ?>
            <span class="badge-tipo badge-franquia">Franquia</span>
            <?php endif; ?>
        </div>

        <div class="store-body">
            <div class="store-row">
                <span>Cidade: <strong><?= $cidade ?> - <?= $uf ?></strong></span>

                <?php if ($cidade !== $end_matriz["cidade"]): ?>
                    <span class="badge-remote">Outra cidade</span>
                <?php endif; ?>
            </div>

            <div class="store-row">
                <span>Taxa: <?= $taxa ?></span>
                <span>Tempo: <?= $tempo ?></span>
            </div>
        </div>

        <div class="store-foot">
            <span class="status-pill <?= $offline ?>"><?= $status ?></span>

            <?php if ($status === "Online"): ?>
                <a href="index.php?id=<?= $empresa_id ?>" class="store-cta">
                    Fazer pedido <span>⟶</span>
                </a>
            <?php else: ?>
                <div class="store-cta" style="color:#9ca3af;">
                    Indisponível <span>⟶</span>
                </div>
            <?php endif; ?>
        </div>
    </article>

<?php endforeach; ?>

</div>
</section>

</div>
</div>

</body>
</html>

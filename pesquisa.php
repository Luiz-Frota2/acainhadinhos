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
function getEndereco($pdo, $empresa_id)
{
  $sql = "SELECT * FROM endereco_empresa WHERE empresa_id = ? LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute([$empresa_id]);
  return $st->fetch(PDO::FETCH_ASSOC);
}

// Função p/ buscar entrega/taxa da unidade
function getEntrega($pdo, $empresa_id)
{
  $sql = "SELECT * FROM entregas WHERE id_empresa = ? LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute([$empresa_id]);
  return $st->fetch(PDO::FETCH_ASSOC);
}

function getTaxa($pdo, $entrega_id)
{
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
    :root {
      --bg: #f3f4f6;
      --card-bg: #ffffff;
      --card-soft: #f9fafb;
      --accent: #6366f1;
      --accent-2: #8b5cf6;
      --text: #111827;
      --muted: #6b7280;
      --border: #e5e7eb;
      --badge-matriz: #22c55e;
      --badge-filial: #3b82f6;
      --badge-franquia: #f97316;
      --shadow-soft: 0 10px 30px rgba(15, 23, 42, 0.08);
      --radius-lg: 18px;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      padding: 16px;
    }

    .page {
      width: 100%;
      max-width: 1240px;
      margin: auto;
    }

    .app-shell {
      width: 100%;
      background: var(--card-soft);
      border-radius: 24px;
      border: 1px solid var(--border);
      box-shadow: var(--shadow-soft);
      padding: 22px 28px 26px;
    }

    /* CABEÇALHO */
    .top-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 22px;
      flex-wrap: wrap;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .logo-circle {
      width: 40px;
      height: 40px;
      border-radius: 999px;
      background: linear-gradient(135deg, var(--accent), var(--accent-2));
      display: flex;
      align-items: center;
      justify-content: center;
      color: #ffffff;
      font-weight: 800;
      font-size: 1rem;
      box-shadow: 0 0 0 2px rgba(129, 140, 248, 0.45);
    }

    .brand-text {
      display: flex;
      flex-direction: column;
      gap: 3px;
    }

    .brand-name {
      font-size: 1.05rem;
      font-weight: 600;
      letter-spacing: 0.04em;
    }

    .brand-sub {
      font-size: 0.74rem;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.16em;
    }

    .location {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 7px 12px;
      border-radius: 999px;
      background: #ffffff;
      border: 1px solid var(--border);
      font-size: 0.8rem;
      color: var(--muted);
      white-space: nowrap;
    }

    .location-dot {
      width: 10px;
      height: 10px;
      border-radius: 999px;
      background: #22c55e;
    }

    .location span {
      font-weight: 500;
      color: var(--text);
    }

    /* HERO */
    .hero {
      display: grid;
      grid-template-columns: minmax(0, 1.7fr) minmax(0, 1.3fr);
      gap: 24px;
      margin-bottom: 26px;
      align-items: start;
    }

    @media (max-width: 900px) {
      .hero {
        grid-template-columns: 1fr;
      }
    }

    .hero-main {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .hero-kicker {
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 0.2em;
      color: var(--muted);
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .hero-kicker-dot {
      width: 6px;
      height: 6px;
      border-radius: 999px;
      background: var(--accent);
    }

    .hero-title {
      font-size: clamp(1.5rem, 2.2vw + 1rem, 2.1rem);
      font-weight: 650;
      line-height: 1.18;
    }

    .hero-title span {
      color: var(--accent);
    }

    .hero-subtitle {
      font-size: 0.9rem;
      color: var(--muted);
      max-width: 40rem;
      line-height: 1.5;
    }

    .hero-tags {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }

    .hero-tag {
      font-size: 0.75rem;
      padding: 5px 9px;
      border-radius: 999px;
      border: 1px solid var(--border);
      color: var(--muted);
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #ffffff;
    }

    .hero-tag-dot {
      width: 6px;
      height: 6px;
      border-radius: 999px;
      background: #22c55e;
    }

    .hero-filters {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 4px;
    }

    .filter-chip {
      padding: 6px 10px;
      border-radius: 999px;
      border: 1px solid var(--border);
      background: #ffffff;
      font-size: 0.8rem;
      color: var(--muted);
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .filter-chip span {
      font-weight: 500;
      color: var(--text);
    }

    /* COMO FUNCIONA */
    .hero-steps {
      border-radius: 18px;
      border: 1px solid var(--border);
      background: #ffffff;
      padding: 14px 16px 12px;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .steps-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }

    .steps-title {
      font-size: 0.88rem;
      font-weight: 550;
    }

    .steps-badge {
      font-size: 0.72rem;
      padding: 3px 8px;
      border-radius: 999px;
      background: #eef2ff;
      color: var(--accent);
      border: 1px solid rgba(129, 140, 248, 0.4);
      text-transform: uppercase;
      letter-spacing: 0.14em;
    }

    .steps-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 8px;
    }

    @media (max-width: 600px) {
      .steps-grid {
        grid-template-columns: 1fr;
      }
    }

    .step-card {
      padding: 8px 9px;
      border-radius: 14px;
      background: #f9fafb;
      border: 1px solid #e5e7eb;
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .step-label {
      font-size: 0.7rem;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.14em;
    }

    .step-title {
      font-size: 0.86rem;
      font-weight: 600;
    }

    .step-desc {
      font-size: 0.74rem;
      color: var(--muted);
    }

    .steps-footnote {
      font-size: 0.7rem;
      color: var(--muted);
    }

    /* LOJA PRINCIPAL (FULL WIDTH) */
    .main-store {
      border-radius: var(--radius-lg);
      border: 1px solid var(--border);
      background: #ffffff;
      padding: 16px 18px 14px;
      display: flex;
      flex-direction: column;
      gap: 12px;
      box-shadow: var(--shadow-soft);
      margin-bottom: 18px;
    }

    .main-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 12px;
    }

    .main-titles {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .main-label {
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 0.16em;
      color: var(--muted);
    }

    .main-name {
      font-size: 1.02rem;
      font-weight: 650;
    }

    .badge-matriz {
      align-self: flex-start;
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 0.16em;
      padding: 4px 10px;
      border-radius: 999px;
      background: rgba(34, 197, 94, 0.08);
      border: 1px solid rgba(34, 197, 94, 0.7);
      color: #166534;
    }

    .main-body {
      display: grid;
      grid-template-columns: minmax(0, 1.3fr) minmax(0, 1.3fr);
      gap: 10px;
    }

    @media (max-width: 780px) {
      .main-body {
        grid-template-columns: 1fr;
      }
    }

    .info-list {
      display: flex;
      flex-direction: column;
      gap: 6px;
      font-size: 0.8rem;
    }

    .info-label {
      color: var(--muted);
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.14em;
    }

    .info-value {
      font-size: 0.86rem;
    }

    .info-tags {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-top: 6px;
    }

    .info-tag {
      font-size: 0.7rem;
      padding: 4px 8px;
      border-radius: 999px;
      background: #f9fafb;
      border: 1px solid #e5e7eb;
      color: var(--muted);
    }

    .main-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 4px;
      gap: 10px;
      flex-wrap: wrap;
    }

    .btn-primary {
      padding: 9px 16px;
      border-radius: 999px;
      border: none;
      outline: none;
      cursor: pointer;
      font-size: 0.82rem;
      font-weight: 550;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      background: linear-gradient(135deg, var(--accent), var(--accent-2));
      color: #ffffff;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 10px 20px rgba(129, 140, 248, 0.4);
      transition: transform 0.12s ease-out, box-shadow 0.12s ease-out;
      white-space: nowrap;
    }

    .btn-primary:hover {
      transform: translateY(-1px);
      box-shadow: 0 14px 26px rgba(129, 140, 248, 0.45);
    }

    .btn-icon {
      font-size: 1rem;
    }

    .hint {
      font-size: 0.75rem;
      color: var(--muted);
    }

    /* OUTRAS UNIDADES (FULL WIDTH GRID) */
    .other-stores {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .other-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }

    .other-title {
      font-size: 0.9rem;
      font-weight: 550;
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .other-title span {
      font-size: 0.75rem;
      text-transform: uppercase;
      color: var(--muted);
      letter-spacing: 0.16em;
    }

    .other-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
    }

    @media (max-width: 1100px) {
      .other-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    @media (max-width: 700px) {
      .other-grid {
        grid-template-columns: 1fr;
      }
    }

    .store-card {
      border-radius: var(--radius-lg);
      padding: 9px 10px;
      background: #ffffff;
      border: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      gap: 6px;
      cursor: pointer;
      transition: transform 0.12s ease-out, box-shadow 0.12s ease-out, border-color 0.12s ease-out, background 0.12s ease-out;
    }

    .store-card:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-soft);
      border-color: rgba(129, 140, 248, 0.6);
      background: #fdfdff;
    }

    .store-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 6px;
    }

    .store-name {
      font-size: 0.9rem;
      font-weight: 550;
    }

    .badge-tipo {
      font-size: 0.7rem;
      padding: 3px 7px;
      border-radius: 999px;
      text-transform: uppercase;
      letter-spacing: 0.14em;
      border: 1px solid transparent;
      white-space: nowrap;
    }

    .badge-filial {
      background: rgba(59, 130, 246, 0.08);
      border-color: rgba(59, 130, 246, 0.5);
      color: #1d4ed8;
    }

    .badge-franquia {
      background: rgba(249, 115, 22, 0.08);
      border-color: rgba(249, 115, 22, 0.6);
      color: #c2410c;
    }

    .badge-remote {
      font-size: 0.68rem;
      padding: 3px 7px;
      border-radius: 999px;
      background: #fef3c7;
      border: 1px solid #facc15;
      color: #92400e;
      text-transform: uppercase;
      letter-spacing: 0.12em;
    }

    .store-body {
      display: flex;
      flex-direction: column;
      gap: 4px;
      font-size: 0.78rem;
      color: var(--muted);
    }

    .store-row {
      display: flex;
      justify-content: space-between;
      gap: 8px;
      flex-wrap: wrap;
    }

    .store-foot {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 4px;
      font-size: 0.72rem;
      color: var(--muted);
    }

    .status-pill {
      padding: 3px 7px;
      border-radius: 999px;
      border: 1px solid rgba(34, 197, 94, 0.4);
      background: rgba(34, 197, 94, 0.1);
      color: #166534;
      text-transform: uppercase;
      letter-spacing: 0.12em;
      font-size: 0.68rem;
    }

    .status-pill.offline {
      border-color: rgba(239, 68, 68, 0.6);
      background: rgba(239, 68, 68, 0.06);
      color: #b91c1c;
    }

    .store-cta {
      font-size: 0.78rem;
      font-weight: 500;
      color: var(--accent);
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    .store-cta span {
      font-size: 0.9rem;
    }

    /* MOBILE AJUSTES */
    @media (max-width: 640px) {
      body {
        padding: 10px;
      }

      .app-shell {
        padding: 14px 14px 16px;
        border-radius: 18px;
      }

      .top-bar {
        align-items: flex-start;
      }

      .location {
        width: 100%;
        justify-content: flex-start;
      }

      .hero-subtitle {
        font-size: 0.86rem;
      }

      .hint {
        flex-basis: 100%;
      }
    }
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
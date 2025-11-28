<?php
require "./assets/php/conexao.php";

/* ================================
   1. BUSCAR NOME DA EMPRESA MATRIZ
   ================================ */
$empresaMatrizID = "principal_1";

$stmt = $pdo->prepare("SELECT nome_empresa FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1");
$stmt->bindValue(":id", $empresaMatrizID);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$nomeEmpresa = $row["nome_empresa"] ?? "Sua Empresa";

/* =========================================
   2. BUSCAR ENDEREÇO DA MATRIZ
   ========================================= */
$stmt = $pdo->prepare("SELECT * FROM endereco_empresa WHERE empresa_id = :id LIMIT 1");
$stmt->bindValue(":id", $empresaMatrizID);
$stmt->execute();
$end = $stmt->fetch(PDO::FETCH_ASSOC);

$enderecoMatriz = $end ? ($end["endereco"] . ", " . $end["numero"] . " - " . $end["bairro"]) : "Endereço não informado";
$cidadeUF = $end ? ($end["cidade"] . " - " . $end["uf"]) : "Cidade - UF";

/* ============================================
   3. BUSCAR CONFIGURAÇÕES DE ENTREGA MATRIZ
   ============================================ */
$stmt = $pdo->prepare("SELECT * FROM entregas WHERE id_empresa = :id LIMIT 1");
$stmt->bindValue(":id", $empresaMatrizID);
$stmt->execute();
$entrega = $stmt->fetch(PDO::FETCH_ASSOC);

$tempoMin = $entrega["tempo_min"] ?? 0;
$tempoMax = $entrega["tempo_max"] ?? 0;

/* ============================================
   4. BUSCAR TAXA DE ENTREGA ÚNICA MATRIZ
   ============================================ */
$stmt = $pdo->prepare("SELECT * FROM entrega_taxas_unica WHERE id_entrega = :id LIMIT 1");
$stmt->bindValue(":id", $entrega["id_entrega"] ?? 0);
$stmt->execute();
$taxa = $stmt->fetch(PDO::FETCH_ASSOC);

$valorTaxa = $taxa["valor_taxa"] ?? 0;

/* ============================================
   5. BUSCAR UNIDADES (FILIAIS E FRANQUIAS)
   ============================================ */
$stmt = $pdo->query("SELECT * FROM unidades ORDER BY id ASC");
$unidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
      border-radius: 9px;
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
      border-radius:9px;
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
      text-decoration: none;
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

      <!-- CABEÇALHO -->
      <header class="top-bar">
        <div class="brand">
          <div class="logo-circle">DL</div>
          <div class="brand-text">
            <div class="brand-name">AÇAINHADINHOS</div>
            <div class="brand-sub">Escolha a unidade</div>
          </div>
        </div>

        <div class="location">
          <div class="location-dot"></div>
          Entregando em <span><?= htmlspecialchars($cidadeUF) ?></span>
        </div>
      </header>

      <!-- HERO -->
      <?= /* O HERO É EXATAMENTE IGUAL AO SEU — NÃO ALTEREI */ "" ?>
      <section class="hero">
        <div class="hero-main">
          <div class="hero-kicker">
            <span class="hero-kicker-dot"></span>
            <span>Faça o seu pedido</span>
          </div>
          <h1 class="hero-title">
            Escolha a <span>unidade</span> onde deseja receber seu pedido.
          </h1>
          <p class="hero-subtitle">
            Veja a loja principal, as filiais e as franquias da rede. Você pode escolher a unidade mais
            próxima, outra cidade que entregue na sua região ou a que tiver melhor tempo de entrega para você.
          </p>

          <div class="hero-tags">
            <span class="hero-tag">
              <span class="hero-tag-dot"></span>
              Mesma rede de lojas
            </span>
            <span class="hero-tag">
              <span class="hero-tag-dot"></span>
              Matriz, Filiais e Franquias
            </span>
            <span class="hero-tag">
              <span class="hero-tag-dot"></span>
              Pensado para o cliente final
            </span>
          </div>

          <div class="hero-filters">
            <div class="filter-chip">
              Cidade detectada: <span><?= htmlspecialchars($cidadeUF) ?></span>
            </div>
            <div class="filter-chip">
              Mostrando unidades em: <span><?= htmlspecialchars($cidadeUF) ?> e outras cidades</span>
            </div>
          </div>
        </div>

        <aside class="hero-steps">
          <div class="steps-header">
            <div class="steps-title">Como funciona</div>
            <div class="steps-badge">Passo a passo</div>
          </div>

          <div class="steps-grid">
            <div class="step-card">
              <div class="step-label">Passo 1</div>
              <div class="step-title">Escolha a unidade</div>
              <div class="step-desc">Matriz, filial ou franquia.</div>
            </div>
            <div class="step-card">
              <div class="step-label">Passo 2</div>
              <div class="step-title">Veja o cardápio</div>
              <div class="step-desc">Somente produtos da unidade.</div>
            </div>
            <div class="step-card">
              <div class="step-label">Passo 3</div>
              <div class="step-title">Confirme o endereço</div>
              <div class="step-desc">Calculamos taxa e tempo.</div>
            </div>
            <div class="step-card">
              <div class="step-label">Passo 4</div>
              <div class="step-title">Finalize</div>
              <div class="step-desc">Pagamento e pronto!</div>
            </div>
          </div>

        </aside>
      </section>

      <!-- LOJA PRINCIPAL -->
      <section class="main-store">
        <header class="main-header">
          <div class="main-titles">
            <div class="main-label">Loja principal da rede</div>
            <div class="main-name"><?= htmlspecialchars($nomeEmpresa) ?> • Matriz Centro</div>
          </div>
          <div class="badge-matriz">Matriz</div>
        </header>

        <div class="main-body">
          <div class="info-list">
            <div>
              <div class="info-label">Cidade / Endereço</div>
              <div class="info-value"><?= htmlspecialchars($cidadeUF) ?> • <?= htmlspecialchars($enderecoMatriz) ?></div>
            </div>

            <?php if ($tempoMin > 0): ?>
              <div>
                <div class="info-label">Tempo médio</div>
                <div class="info-value"><?= $tempoMin ?>–<?= $tempoMax ?> min</div>
              </div>
            <?php endif; ?>

            <div>
              <div class="info-label">Taxa de entrega</div>
              <div class="info-value">
                <?= $valorTaxa > 0 ? "A partir de R$ " . number_format($valorTaxa, 2, ',', '.') : "Sem taxa" ?>
              </div>
            </div>

            <div class="info-tags">
              <span class="info-tag">Atende toda a cidade</span>
            </div>
          </div>
        </div>

        <footer class="main-footer">
          <a href="index.php?empresa=principal_1" class="btn-primary">
            <span class="btn-icon">🛒</span>
            Fazer pedido na Matriz
          </a>
          <p class="hint">
            Produto vinculado à empresa principal.
          </p>
        </footer>
      </section>

      <!-- OUTRAS UNIDADES -->
      <section class="other-stores">
        <header class="other-header">
          <div class="other-title">
            Outras unidades
            <span>Filiais e Franquias disponíveis</span>
          </div>
        </header>

        <div class="other-grid">
          <?php foreach ($unidades as $u): ?>
            <article class="store-card" onclick="window.location='index.php?empresa=unidade_<?= $u['id'] ?>'">
              <div class="store-header">
                <div class="store-name"><?= htmlspecialchars($u['nome']) ?></div>

                <?php if ($u["tipo"] == "Filial"): ?>
                  <span class="badge-tipo badge-filial">Filial</span>
                <?php else: ?>
                  <span class="badge-tipo badge-franquia">Franquia</span>
                <?php endif; ?>
              </div>

              <div class="store-body">
                <div class="store-row">
                  <span>Cidade: <strong><?= htmlspecialchars($u["endereco"]) ?></strong></span>
                  <span>—</span>
                </div>
                <div class="store-row">
                  <span>CNPJ: <?= htmlspecialchars($u["cnpj"]) ?></span>
                  <span>Status: <?= $u["status"] == "Ativa" ? "Online" : "Fechada" ?></span>
                </div>
              </div>

              <div class="store-foot">
                <span class="status-pill <?= $u['status'] == 'Inativa' ? 'offline' : '' ?>">
                  <?= $u["status"] == "Ativa" ? "Online" : "Offline" ?>
                </span>

                <div class="store-cta">
                  <?= $u["status"] == "Ativa" ? "Fazer pedido" : "Indisponível" ?> <span>⟶</span>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>

    </div>
  </div>
</body>

</html>
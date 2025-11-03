<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
date_default_timezone_set('America/Manaus');

require_once '../conexao.php';
require_once '../lib/auth_guard.php';

$idSelecionado = $_GET['id'] ?? '';
$q = $_GET['q'] ?? '';
$status = $_GET['status'] ?? '';
$dtIni = $_GET['venc_ini'] ?? '';
$dtFim = $_GET['venc_fim'] ?? '';

if (!$idSelecionado) {
  die('ID da empresa não informado.');
}

/* ==================== Consulta ==================== */
$sql = "
  SELECT 
    sp.*, 
    f.nome AS fornecedor_nome,
    f.cnpj AS fornecedor_cnpj,
    u.nome AS unidade_nome
  FROM solicitacoes_pagamento sp
  LEFT JOIN fornecedores f ON f.id = sp.fornecedor_id
  LEFT JOIN unidades u ON u.id = sp.unidade_id
  WHERE sp.empresa_id = :empresa_id
    AND u.tipo = 'Franquia'
";

$params = ['empresa_id' => $idSelecionado];

if (!empty($q)) {
  $sql .= " AND (
    f.nome LIKE :q OR
    f.cnpj LIKE :q OR
    sp.nome_produto LIKE :q OR
    sp.codigo_produto LIKE :q OR
    sp.codigo_pedido LIKE :q OR
    u.nome LIKE :q
  )";
  $params['q'] = "%{$q}%";
}

if (!empty($status)) {
  $sql .= " AND sp.status_pagamento = :status";
  $params['status'] = $status;
}

if (!empty($dtIni)) {
  $sql .= " AND DATE(sp.data_solicitacao) >= :dtIni";
  $params['dtIni'] = $dtIni;
}

if (!empty($dtFim)) {
  $sql .= " AND DATE(sp.data_solicitacao) <= :dtFim";
  $params['dtFim'] = $dtFim;
}

$sql .= " ORDER BY sp.id DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Solicitações de Pagamento - Franquias</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />

  <style>
    /* ===== Layout geral ===== */
    body {
      background-color: #f9fafb;
      font-family: "Inter", sans-serif;
    }

    .card {
      border-radius: 12px;
    }

    /* ===== Filtros ===== */
    .toolbar {
      margin-bottom: 0 !important;
    }

    .toolbar .form-label {
      font-size: 0.8rem;
    }

    .toolbar .form-control,
    .toolbar .form-select {
      border-radius: 8px;
      border: 1px solid #d1d5db;
    }

    .toolbar .btn {
      height: 36px;
      font-size: 0.85rem;
      font-weight: 500;
      border-radius: 8px;
    }

    .toolbar .btn i {
      font-size: 1rem;
      vertical-align: middle;
      margin-right: 3px;
    }

    .search-wrap {
      position: relative;
    }

    .suggestions {
      position: absolute;
      background: #fff;
      border: 1px solid #e6ebf3;
      max-height: 240px;
      overflow: auto;
      z-index: 2000;
      width: 100%;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
      border-radius: 6px;
    }

    .suggestions .item {
      padding: 8px 10px;
      cursor: pointer;
      border-bottom: 1px solid #f1f4f7;
      font-size: 14px;
    }

    .suggestions .item:hover {
      background: #f6fbff;
    }

    @media (max-width: 768px) {

      .toolbar .col-12,
      .toolbar .col-6 {
        flex: 0 0 100%;
        max-width: 100%;
      }

      .toolbar .btn {
        width: 100%;
      }
    }

    /* ===== Tabela ===== */
    table thead {
      background: #f1f5f9;
    }

    table th {
      font-size: 0.85rem;
      font-weight: 600;
      color: #374151;
    }

    table td {
      font-size: 0.85rem;
      vertical-align: middle;
    }

    .badge {
      font-size: 0.75rem;
    }

    .status-pendente {
      background: #fff3cd;
      color: #856404;
    }

    .status-aprovado {
      background: #d1e7dd;
      color: #0f5132;
    }

    .status-reprovado {
      background: #f8d7da;
      color: #842029;
    }
  </style>
</head>

<body>
  <div class="container py-4">

    <h5 class="mb-3 fw-semibold text-secondary">
      <i class="bx bx-credit-card me-1"></i> Solicitações de Pagamento - Franquias
    </h5>

    <!-- ==================== FILTROS ==================== -->
    <div class="card mb-3 shadow-sm border-0">
      <div class="card-body">
        <form class="toolbar row g-3 align-items-end" method="get" id="formFiltro">
          <input type="hidden" name="id" value="<?= htmlspecialchars($idSelecionado, ENT_QUOTES) ?>">

          <div class="col-12 col-md-2">
            <label class="form-label mb-1 fw-semibold text-secondary">Status</label>
            <select name="status" class="form-select form-select-sm">
              <option value="">Todos</option>
              <option value="pendente" <?= $status === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
              <option value="aprovado" <?= $status === 'aprovado' ? 'selected' : ''; ?>>Aprovado</option>
              <option value="reprovado" <?= $status === 'reprovado' ? 'selected' : ''; ?>>Reprovado</option>
            </select>
          </div>

          <div class="col-6 col-md-2">
            <label class="form-label mb-1 fw-semibold text-secondary">De</label>
            <input type="date" name="venc_ini" value="<?= htmlspecialchars($dtIni, ENT_QUOTES) ?>" class="form-control form-control-sm">
          </div>

          <div class="col-6 col-md-2">
            <label class="form-label mb-1 fw-semibold text-secondary">Até</label>
            <input type="date" name="venc_fim" value="<?= htmlspecialchars($dtFim, ENT_QUOTES) ?>" class="form-control form-control-sm">
          </div>

          <div class="col-12 col-md-4 search-wrap">
            <label class="form-label mb-1 fw-semibold text-secondary">Buscar</label>
            <input type="text" id="q" name="q" autocomplete="off" value="<?= htmlspecialchars($q, ENT_QUOTES) ?>"
              class="form-control form-control-sm"
              placeholder="Solicitante, fornecedor, doc..." />
            <div id="suggestions" class="suggestions d-none" aria-hidden="true"></div>
          </div>

          <div class="col-12 col-md-2 d-flex gap-2">
            <button class="btn btn-sm btn-primary flex-fill"><i class="bx bx-filter-alt"></i> Filtrar</button>
            <a class="btn btn-sm btn-outline-secondary flex-fill" href="?id=<?= urlencode($idSelecionado) ?>">
              <i class="bx bx-reset"></i> Limpar
            </a>
          </div>
        </form>

        <div class="mt-3 text-secondary small">
          Encontradas <strong><?= count($rows) ?></strong> solicitações (somente <strong>Franquias</strong>)
        </div>
      </div>
    </div>

    <!-- ==================== LISTAGEM ==================== -->
    <div class="card shadow-sm border-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>#</th>
              <th>Fornecedor</th>
              <th>Produto</th>
              <th>Unidade</th>
              <th>Data</th>
              <th>Valor (R$)</th>
              <th>Status</th>
              <th>Comprovante</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)) : ?>
              <tr>
                <td colspan="8" class="text-center text-muted py-3">Nenhuma solicitação encontrada.</td>
              </tr>
            <?php else : ?>
              <?php foreach ($rows as $r) : ?>
                <tr>
                  <td><?= $r['id'] ?></td>
                  <td><?= htmlspecialchars($r['fornecedor_nome']) ?></td>
                  <td><?= htmlspecialchars($r['nome_produto']) ?></td>
                  <td><?= htmlspecialchars($r['unidade_nome']) ?></td>
                  <td><?= date('d/m/Y', strtotime($r['data_solicitacao'])) ?></td>
                  <td><?= number_format($r['valor_total'], 2, ',', '.') ?></td>
                  <td>
                    <?php
                    $statusClass = 'status-' . strtolower($r['status_pagamento']);
                    ?>
                    <span class="badge <?= $statusClass ?> text-uppercase px-2 py-1">
                      <?= ucfirst($r['status_pagamento']) ?>
                    </span>
                  </td>
                  <td>
                    <?php if (!empty($r['comprovante_url'])): ?>
                      <a href="https://srv1885-files.hstgr.io/e9aded9b7b308c83/files/public_html/public/pagamentos/<?= urlencode($r['comprovante_url']) ?>"
                        target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="bx bx-download"></i> Baixar
                      </a>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
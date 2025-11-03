<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

/* ==================== Sessão & parâmetros ==================== */
$idSelecionado = $_GET['id'] ?? '';
if (!$idSelecionado) {
  header("Location: .././login.php");
  exit;
}

/* ==================== Login obrigatório ==================== */
if (
  !isset($_SESSION['usuario_logado']) ||
  !isset($_SESSION['empresa_id']) ||
  !isset($_SESSION['tipo_empresa']) ||
  !isset($_SESSION['usuario_id'])
) {
  header("Location: .././login.php?id=" . urlencode($idSelecionado));
  exit;
}

/* ==================== Conexão ==================== */
require '../../assets/php/conexao.php';

/* ==================== Usuário logado ==================== */
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id  = (int)$_SESSION['usuario_id'];

try {
  $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
  $stmt->execute([':id' => $usuario_id]);
  if ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $nomeUsuario = $u['usuario'];
    $tipoUsuario = ucfirst($u['nivel']);
  } else {
    echo "<script>alert('Usuário não encontrado.'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
    exit;
  }
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar usuário: " . $e->getMessage() . "'); history.back();</script>";
  exit;
}

/* ==================== Permissão ==================== */
$acessoPermitido   = false;
$idEmpresaSession  = $_SESSION['empresa_id'];
$tipoSession       = $_SESSION['tipo_empresa'];

if (str_starts_with($idSelecionado, 'principal_')) {
  $acessoPermitido = ($tipoSession === 'principal' && $idEmpresaSession === $idSelecionado);
} elseif (str_starts_with($idSelecionado, 'filial_')) {
  $acessoPermitido = ($tipoSession === 'filial' && $idEmpresaSession === $idSelecionado);
} elseif (str_starts_with($idSelecionado, 'unidade_')) {
  $acessoPermitido = ($tipoSession === 'unidade' && $idEmpresaSession === $idSelecionado);
} elseif (str_starts_with($idSelecionado, 'franquia_')) {
  $acessoPermitido = ($tipoSession === 'franquia' && $idEmpresaSession === $idSelecionado);
}

if (!$acessoPermitido) {
  echo "<script>alert('Acesso negado!'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
  exit;
}

/* ==================== Logo ==================== */
try {
  $s = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :i LIMIT 1");
  $s->execute([':i' => $idSelecionado]);
  $sobre = $s->fetch(PDO::FETCH_ASSOC);
  $logoEmpresa = !empty($sobre['imagem']) ? "../../assets/img/empresa/" . $sobre['imagem'] : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
  $logoEmpresa = "../../assets/img/favicon/logo.png";
}

/* ==================== CSRF (mudar status) ==================== */
if (empty($_SESSION['csrf_pagto_status'])) {
  $_SESSION['csrf_pagto_status'] = bin2hex(random_bytes(32));
}
$csrfStatus = $_SESSION['csrf_pagto_status'];

/* -----------------------
   Autocomplete AJAX handler
   ----------------------- */
if (isset($_GET['ajax_search']) && $_GET['ajax_search'] == '1') {
  $term = trim((string)($_GET['term'] ?? ''));
  $out = [];
  if ($term !== '') {
    $sqlA = "
      SELECT
        sp.ID as id,
        sp.fornecedor as fornecedor,
        sp.documento as documento,
        COALESCE(u.nome, '') as unidade_nome,
        sp.valor as valor,
        sp.id_solicitante as id_solicitante
      FROM solicitacoes_pagamento sp
      LEFT JOIN unidades u ON u.id = CAST(SUBSTRING_INDEX(sp.id_solicitante, '_', -1) AS UNSIGNED)
      WHERE sp.id_matriz = :id_matriz
        AND u.tipo = :tipo
        AND (
          sp.fornecedor LIKE :t OR
          sp.documento LIKE :t OR
          sp.descricao LIKE :t OR
          u.nome LIKE :t OR
          sp.id_solicitante LIKE :t
        )
      ORDER BY sp.created_at DESC
      LIMIT 15
    ";
    try {
      $stm = $pdo->prepare($sqlA);
      $like = "%{$term}%";
      $stm->execute([':id_matriz' => $idSelecionado, ':tipo' => 'Franquia', ':t' => $like]);
      $res = $stm->fetchAll(PDO::FETCH_ASSOC);
      foreach ($res as $r) {
        $label = trim(sprintf("%s · %s · %s · %s", $r['id_solicitante'], $r['unidade_nome'] ?: '—', $r['fornecedor'] ?: '—', $r['documento'] ?: '—'));
        $out[] = [
          'id' => (int)$r['id'],
          'label' => $label,
          'fornecedor' => $r['fornecedor'],
          'documento' => $r['documento'],
          'unidade' => $r['unidade_nome']
        ];
      }
    } catch (PDOException $e) {
      $out = [];
    }
  }
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($out);
  exit;
}

/* ==================== Filtros (apenas os necessários) ==================== */
$status = $_GET['status']   ?? '';              // pendente/aprovado/reprovado
$dtIni  = $_GET['venc_ini'] ?? '';             // YYYY-MM-DD
$dtFim  = $_GET['venc_fim'] ?? '';             // YYYY-MM-DD
$q      = trim($_GET['q']   ?? '');            // texto livre

$params = [':id_matriz' => $idSelecionado, ':tipo' => 'Franquia'];
$where  = ["sp.id_matriz = :id_matriz", "u.tipo = :tipo"]; // <-- SOMENTE FRANQUIA

if ($status !== '' && in_array($status, ['pendente', 'aprovado', 'reprovado'], true)) {
  $where[] = "sp.status = :status";
  $params[':status'] = $status;
}
if ($dtIni !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dtIni)) {
  $where[] = "sp.vencimento >= :vini";
  $params[':vini'] = $dtIni;
}
if ($dtFim !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dtFim)) {
  $where[] = "sp.vencimento <= :vfim";
  $params[':vfim'] = $dtFim;
}
if ($q !== '') {
  $where[] = "(sp.fornecedor LIKE :q OR sp.documento LIKE :q OR sp.descricao LIKE :q OR sp.id_solicitante LIKE :q OR u.nome LIKE :q)";
  $params[':q'] = "%$q%";
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

/* ==================== Consulta principal ==================== */
$sql = "
  SELECT
    sp.ID as id_solicitacao,
    sp.id_solicitante as id_solicitante,
    sp.status as status,
    sp.fornecedor as fornecedor,
    sp.documento as documento,
    sp.descricao as descricao,
    sp.vencimento as vencimento,
    sp.valor as valor,
    sp.comprovante_url as comprovante_url,
    sp.created_at as criado_em,
    u.id         AS unidade_id,
    u.nome       AS unidade_nome,
    u.tipo       AS unidade_tipo
  FROM solicitacoes_pagamento sp
  LEFT JOIN unidades u
    ON u.id = CAST(SUBSTRING_INDEX(sp.id_solicitante, '_', -1) AS UNSIGNED)
  $whereSql
  ORDER BY sp.created_at DESC, sp.ID DESC
";
$rows = [];
try {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar solicitações: " . htmlspecialchars($e->getMessage(), ENT_QUOTES) . "'); history.back();</script>";
  exit;
}

function badgeStatus(string $s): string
{
  $s = strtolower($s);
  if ($s === 'aprovado')  return '<span class="badge bg-label-success status-badge">APROVADO</span>';
  if ($s === 'reprovado') return '<span class="badge bg-label-danger status-badge">REPROVADO</span>';
  return '<span class="badge bg-label-warning status-badge">PENDENTE</span>';
}
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
  <title>ERP — Pagamentos Solicitados</title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($logoEmpresa, ENT_QUOTES) ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
  <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="../../assets/css/demo.css" />
  <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <script src="../../assets/vendor/js/helpers.js"></script>
  <script src="../../assets/js/config.js"></script>

  <style>
    /* ======= Your requested base styles (integrated) ======= */
    .table thead th {
      white-space: nowrap
    }

    .status-badge {
      font-size: .78rem
    }

    .pagination .page-link {
      min-width: 38px;
      text-align: center
    }

    .small-muted {
      font-size: .8rem;
      color: #8b98a8
    }

    .autocomplete {
      position: relative
    }

    .autocomplete-list {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      max-height: 260px;
      overflow: auto;
      background: #fff;
      border: 1px solid #e6e9ef;
      border-radius: .5rem;
      box-shadow: 0 10px 24px rgba(24, 28, 50, .12);
      z-index: 2060
    }

    .autocomplete-item {
      padding: .5rem .75rem;
      cursor: pointer;
      display: flex;
      justify-content: space-between;
      gap: .75rem
    }

    .autocomplete-item:hover,
    .autocomplete-item.active {
      background: #f5f7fb
    }

    .autocomplete-tag {
      font-size: .75rem;
      color: #6b7280
    }

    @media (max-width: 991.98px) {
      .filter-col {
        width: 100%
      }
    }

    .sticky-actions {
      white-space: nowrap
    }

    /* ===== Additional styles to match the rest of the page (kept minimal) ===== */
    .toolbar {
      display: flex;
      gap: .75rem;
      flex-wrap: wrap;
      align-items: end;
    }

    .toolbar .form-select,
    .toolbar .form-control {
      min-width: 180px;
      border-radius: 8px;
    }

    .toolbar .btn {
      height: 38px;
      border-radius: 8px;
    }

    .muted {
      color: #6b7280;
      font-size: 0.95rem;
    }

    .card-header {
      font-weight: 600;
      background: transparent;
      border-bottom: 1px solid #eef2f6;
    }

    .small-muted {
      font-size: 12px;
      color: #9aa6b2;
    }

    .search-wrap {
      position: relative;
    }

    /* small refinements for the suggestions items to include an extra tag on right */
    .autocomplete-item .left {
      flex: 1;
      overflow: hidden;
      white-space: nowrap;
      text-overflow: ellipsis;
    }

    .autocomplete-item .right {
      flex: 0 0 auto;
      text-align: right;
    }
  </style>
</head>

<body>
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
      <!-- ASIDE (mantido igual) -->
      <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
        <div class="app-brand demo">
          <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="app-brand-link">
            <span class="app-brand-text demo menu-text fw-bolder ms-2" style="text-transform: capitalize;">Açaínhadinhos</span>
          </a>
          <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
            <i class="bx bx-chevron-left bx-sm align-middle"></i>
          </a>
        </div>
        <div class="menu-inner-shadow"></div>
        <ul class="menu-inner py-1">
          <li class="menu-item"><a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-home-circle"></i>
              <div>Dashboard</div>
            </a></li>
          <!-- resto do menu omitido para brevidade -->
        </ul>
      </aside>

      <div class="layout-page">
        <!-- Navbar -->
        <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
          <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
            <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)"><i class="bx bx-menu bx-sm"></i></a>
          </div>
          <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
            <div class="navbar-nav align-items-center">
              <div class="nav-item d-flex align-items-center">
                <i class="bx bx-file fs-4 lh-0"></i>
                <span class="ms-2">Pagamentos Solicitados</span>
              </div>
            </div>
            <ul class="navbar-nav flex-row align-items-center ms-auto">
              <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false">
                  <div class="avatar avatar-online"><img src="<?= htmlspecialchars($logoEmpresa, ENT_QUOTES) ?>" class="w-px-40 h-auto rounded-circle" alt="Avatar" /></div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li>
                    <a class="dropdown-item" href="#">
                      <div class="d-flex">
                        <div class="flex-shrink-0 me-3">
                          <div class="avatar avatar-online"><img src="<?= htmlspecialchars($logoEmpresa, ENT_QUOTES) ?>" class="w-px-40 h-auto rounded-circle" alt="Avatar" /></div>
                        </div>
                        <div class="flex-grow-1">
                          <span class="fw-semibold d-block"><?= htmlspecialchars($nomeUsuario, ENT_QUOTES); ?></span>
                          <small class="text-muted"><?= htmlspecialchars($tipoUsuario, ENT_QUOTES); ?></small>
                        </div>
                      </div>
                    </a>
                  </li>
                  <li>
                    <div class="dropdown-divider"></div>
                  </li>
                  <li><a class="dropdown-item" href="../logout.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-power-off me-2"></i><span class="align-middle">Sair</span></a></li>
                </ul>
              </li>
            </ul>
          </div>
        </nav>
        <!-- /Navbar -->

        <div class="container-xxl flex-grow-1 container-p-y">
          <h4 class="fw-bold mb-0">
            <span class="text-muted fw-light"><a href="#">Franquias</a>/</span>
            Pagamentos Solicitados
          </h4>
          <p class="small-muted mb-3">Pedidos de pagamento enviados por <strong>Franquias</strong></p>

          <!-- Filtros (igual layout Produtos Solicitados, usando o CSS que você pediu) -->
          <div class="card mb-3">
            <div class="card-body">
              <form class="toolbar row gx-3 gy-2 align-items-end" method="get" id="formFiltro">
                <input type="hidden" name="id" value="<?= htmlspecialchars($idSelecionado, ENT_QUOTES) ?>">

                <div class="filter-col col-12 col-lg-2">
                  <label class="form-label mb-1">STATUS</label>
                  <select name="status" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="pendente" <?= $status === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                    <option value="aprovado" <?= $status === 'aprovado' ? 'selected' : ''; ?>>Aprovado</option>
                    <option value="reprovado" <?= $status === 'reprovado' ? 'selected' : ''; ?>>Reprovado</option>
                  </select>
                </div>

                <div class="filter-col col-6 col-lg-2">
                  <label class="form-label mb-1">DE</label>
                  <input type="date" name="venc_ini" value="<?= htmlspecialchars($dtIni, ENT_QUOTES) ?>" class="form-control form-control-sm">
                </div>

                <div class="filter-col col-6 col-lg-2">
                  <label class="form-label mb-1">ATÉ</label>
                  <input type="date" name="venc_fim" value="<?= htmlspecialchars($dtFim, ENT_QUOTES) ?>" class="form-control form-control-sm">
                </div>

                <div class="filter-col col-12 col-lg-5 autocomplete">
                  <label class="form-label mb-1">BUSCAR</label>
                  <input type="text" id="q" name="q" autocomplete="off" value="<?= htmlspecialchars($q, ENT_QUOTES) ?>" class="form-control form-control-sm" placeholder="Solicitante (ex.: unidade_1), fornecedor, doc..." />
                  <div id="autocomplete-list" class="autocomplete-list d-none" role="listbox" aria-label="Sugestões"></div>
                </div>

                <div class="filter-col col-12 col-lg-3 d-flex gap-2 form-control-sm">
                  <button class="btn btn-primary btn-sm w-100"><i class="bx bx-filter-alt"></i> Filtrar</button>
                  <a class="btn btn-outline-secondary btn-sm w-100" href="?id=<?= urlencode($idSelecionado) ?>"><i class="bx bx-reset"></i> Limpar</a>
                </div>
              </form>

              <div class="mt-2 muted">
                Encontradas <strong><?= count($rows) ?></strong> solicitações (somente <strong>Franquias</strong>) · Página 1 de 1
              </div>
            </div>
          </div>

          <!-- Tabela (estilo igual Produtos Solicitados) -->
          <div class="card">
            <h5 class="card-header">Lista de Pagamentos Solicitados (Somente Franquias)</h5>
            <div class="table-responsive text-nowrap">
              <table class="table table-hover align-middle">
                <thead>
                  <tr>
                    <th class="col-num">#</th>
                    <th class="col-unidade">NOME DA UNIDADE</th>
                    <th class="col-fornecedor">FORNECEDOR</th>
                    <th class="col-documento">DOCUMENTO</th>
                    <th class="col-total">VALOR</th>
                    <th class="col-venc">VENCIMENTO</th>
                    <th class="col-anexo">ANEXO</th>
                    <th class="col-status">STATUS</th>
                    <th class="col-acoes">AÇÕES</th>
                  </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                  <?php if (!$rows): ?>
                    <tr>
                      <td colspan="10" class="text-center text-muted py-4">Nenhuma solicitação encontrada.</td>
                    </tr>
                  <?php else: ?>

                    <?php foreach ($rows as $r): ?>
                      <?php
                      $dataCriado = $r['criado_em'] ? date('d/m/Y', strtotime($r['criado_em'])) : '—';
                      $venc = $r['vencimento'] ? date('d/m/Y', strtotime($r['vencimento'])) : '—';
                      $valorNum = (float)str_replace([',', 'R$', ' '], ['', '.', ''], $r['valor']);
                      $valorFmt = 'R$ ' . number_format($valorNum, 2, ',', '.');

                      $solicitante_raw = htmlspecialchars($r['id_solicitante'] ?: '—', ENT_QUOTES);
                      $unit_name_attr = htmlspecialchars($r['unidade_nome'] ?: '—', ENT_QUOTES);
                      $fornecedor_attr = htmlspecialchars($r['fornecedor'] ?: '—', ENT_QUOTES);
                      $documento_attr = htmlspecialchars($r['documento'] ?: '—', ENT_QUOTES);
                      ?>
                      <tr>
                        <td class="text-nowrap"><?= (int)$r['id_solicitacao'] ?></td>
                        <td><strong><?= $unit_name_attr ?></strong></td>
                        <td class="truncate" title="<?= $fornecedor_attr ?>"><?= $fornecedor_attr ?></td>
                        <td class="truncate" title="<?= $documento_attr ?>"><?= $documento_attr ?></td>
                        <td class="text-end"><?= $valorFmt ?></td>
                        <td><?= $venc ?></td>
                        <td class="text-center">
                          <?php if (!empty($r['comprovante_url'])): ?>
                            <a href="<?= htmlspecialchars($r['comprovante_url'], ENT_QUOTES) ?>" target="_blank" class="text-primary">baixar</a>
                          <?php else: ?>
                            <span class="text-muted">—</span>
                          <?php endif; ?>
                        </td>
                        <td class=""><?= badgeStatus($r['status']) ?></td>
                        <td class="sticky-actions">
                          <button
                            class="btn btn-sm btn-outline-secondary btn-detalhes"
                            data-bs-toggle="modal" data-bs-target="#modalDetalhes"
                            data-id="<?= (int)$r['id_solicitacao'] ?>"
                            data-unidade="<?= $unit_name_attr ?>"
                            data-unidadeid="<?= $solicitante_raw ?>"
                            data-fornecedor="<?= $fornecedor_attr ?>"
                            data-documento="<?= $documento_attr ?>"
                            data-descricao="<?= htmlspecialchars($r['descricao'] ?: '—', ENT_QUOTES) ?>"
                            data-valor="<?= htmlspecialchars($valorFmt, ENT_QUOTES) ?>"
                            data-venc="<?= $venc ?>"
                            data-anexo="<?= htmlspecialchars($r['comprovante_url'] ?: '—', ENT_QUOTES) ?>"
                            data-status="<?= htmlspecialchars(strtoupper($r['status']), ENT_QUOTES) ?>"
                            data-criado="<?= $dataCriado ?>">
                            <i class="bx bx-detail"></i> Detalhes
                          </button>

                          <button
                            class="btn btn-sm btn-outline-primary btn-status"
                            data-bs-toggle="modal" data-bs-target="#modalStatus"
                            data-id="<?= (int)$r['id_solicitacao'] ?>"
                            data-status="<?= htmlspecialchars($r['status'], ENT_QUOTES) ?>"
                            data-fornecedor="<?= $fornecedor_attr ?>"
                            data-documento="<?= $documento_attr ?>">
                            Mudar Status
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

        </div><!-- /container -->
      </div><!-- /layout-page -->
    </div><!-- /layout-container -->
  </div>

  <!-- Modais (mesmos que antes) -->
  <div class="modal fade" id="modalDetalhes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Detalhes da Solicitação</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <p><strong>ID:</strong> <span id="det-id">—</span></p>
              <p><strong>Unidade:</strong> <span id="det-unidade">—</span> (<span id="det-unidadeid">—</span>)</p>
              <p><strong>Status:</strong> <span id="det-status">—</span></p>
            </div>
            <div class="col-md-6">
              <p><strong>Fornecedor:</strong> <span id="det-fornecedor">—</span></p>
              <p><strong>Documento:</strong> <span id="det-documento">—</span></p>
              <p><strong>Valor:</strong> <span id="det-valor">—</span></p>
              <p><strong>Vencimento:</strong> <span id="det-venc">—</span></p>
            </div>
            <div class="col-12">
              <p><strong>Descrição:</strong></p>
              <div id="det-descricao" class="border rounded p-2" style="min-height:60px; white-space:pre-wrap;"></div>
            </div>
            <div class="col-12">
              <p><strong>Anexo:</strong> <span id="det-anexo">—</span></p>
            </div>
            <div class="col-12">
              <p class="text-muted">Criado em: <span id="det-criado">—</span></p>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modalStatus" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content" method="post" action="../../assets/php/matriz/solicitacaoPagamentoStatus.php">
        <div class="modal-header">
          <h5 class="modal-title">Mudar status da solicitação</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfStatus, ENT_QUOTES) ?>">
          <input type="hidden" name="id" id="st-id">
          <input type="hidden" name="id_matriz" value="<?= htmlspecialchars($idSelecionado, ENT_QUOTES) ?>">

          <div class="mb-2 text-muted small">
            <span id="st-info">—</span>
          </div>

          <div class="mb-3">
            <label class="form-label">Ação</label>
            <select name="acao" id="st-acao" class="form-select" required>
              <option value="">Selecionar...</option>
              <option value="aprovado">Aprovar</option>
              <option value="reprovado">Reprovar</option>
            </select>
            <div class="form-text">Ao reprovar, informe o comentário.</div>
          </div>

          <div class="mb-3 d-none" id="st-obs-wrap">
            <label class="form-label">Comentário (obrigatório ao reprovar)</label>
            <textarea name="obs" id="st-obs" rows="3" class="form-control" placeholder="Explique o motivo da reprovação..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancelar</button>
          <button class="btn btn-primary" type="submit">Confirmar</button>
        </div>
      </form>
    </div>
  </div>

  <!-- JS -->
  <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../../assets/vendor/libs/popper/popper.js"></script>
  <script src="../../assets/vendor/js/bootstrap.js"></script>
  <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="../../assets/vendor/js/menu.js"></script>
  <script src="../../assets/js/main.js"></script>

  <script>
    (function() {
      // Detalhes
      document.querySelectorAll('.btn-detalhes').forEach(btn => {
        btn.addEventListener('click', () => {
          const g = (k) => btn.getAttribute('data-' + k) || '—';
          document.getElementById('det-id').textContent = g('id');
          document.getElementById('det-unidade').textContent = g('unidade');
          document.getElementById('det-unidadeid').textContent = g('unidadeid');
          document.getElementById('det-status').textContent = g('status');
          document.getElementById('det-fornecedor').textContent = g('fornecedor');
          document.getElementById('det-documento').textContent = g('documento');
          document.getElementById('det-valor').textContent = g('valor');
          document.getElementById('det-venc').textContent = g('venc');
          document.getElementById('det-descricao').textContent = g('descricao');
          const anexo = g('anexo');
          document.getElementById('det-anexo').innerHTML = (anexo && anexo !== '—') ?
            `<a href="${anexo}" target="_blank">abrir</a>` : '—';
          document.getElementById('det-criado').textContent = g('criado');
        });
      });

      // Status modal logic
      const wrapObs = document.getElementById('st-obs-wrap');
      const selAcao = document.getElementById('st-acao');
      const txtObs = document.getElementById('st-obs');

      const toggleObs = () => {
        if (selAcao.value === 'reprovado') {
          wrapObs.classList.remove('d-none');
          txtObs.setAttribute('required', 'required');
        } else {
          wrapObs.classList.add('d-none');
          txtObs.removeAttribute('required');
          txtObs.value = '';
        }
      };
      selAcao.addEventListener('change', toggleObs);

      document.querySelectorAll('.btn-status').forEach(btn => {
        btn.addEventListener('click', () => {
          document.getElementById('st-id').value = btn.getAttribute('data-id');
          const info = `#${btn.getAttribute('data-id')} · ${btn.getAttribute('data-fornecedor')} · Doc.: ${btn.getAttribute('data-documento')} · Status atual: ${btn.getAttribute('data-status')}`;
          document.getElementById('st-info').textContent = info;

          selAcao.value = '';
          toggleObs();
        });
      });

      /* ---------------------------
         Autocomplete (partial search)
         --------------------------- */
      const inputQ = document.getElementById('q');
      const listBox = document.getElementById('autocomplete-list');

      let debounceTimer = null;
      inputQ.addEventListener('input', function() {
        const v = this.value.trim();
        if (debounceTimer) clearTimeout(debounceTimer);
        if (v.length === 0) {
          listBox.classList.add('d-none');
          listBox.innerHTML = '';
          return;
        }
        debounceTimer = setTimeout(() => fetchSuggestions(v), 250);
      });

      function fetchSuggestions(term) {
        const url = new URL(window.location.href);
        url.searchParams.set('ajax_search', '1');
        url.searchParams.set('term', term);
        // keep id param already in URL
        fetch(url.toString(), {
            credentials: 'same-origin'
          })
          .then(r => r.json())
          .then(data => {
            renderSuggestions(data);
          })
          .catch(e => {
            listBox.classList.add('d-none');
            listBox.innerHTML = '';
            console.error(e);
          });
      }

      function renderSuggestions(list) {
        listBox.innerHTML = '';
        if (!list || !list.length) {
          listBox.classList.add('d-none');
          return;
        }
        list.forEach(it => {
          const row = document.createElement('div');
          row.className = 'autocomplete-item';
          row.tabIndex = 0;

          const left = document.createElement('div');
          left.className = 'left';
          left.textContent = it.label;

          const right = document.createElement('div');
          right.className = 'right autocomplete-tag';
          // show unidade as tag if available
          right.textContent = it.unidade ? it.unidade : '';

          row.appendChild(left);
          row.appendChild(right);

          row.addEventListener('click', () => {
            // Behavior: fill search input with solicitante (first token before '·')
            const val = it.label.split('·')[0].trim();
            inputQ.value = val;
            listBox.classList.add('d-none');
            // auto-submit form to filter results immediately
            document.getElementById('formFiltro').submit();
          });

          row.addEventListener('keydown', (ev) => {
            if (ev.key === 'Enter') row.click();
          });

          listBox.appendChild(row);
        });
        listBox.classList.remove('d-none');
      }

      // close suggestions when clicking outside
      document.addEventListener('click', (e) => {
        if (!e.target.closest('.autocomplete')) {
          listBox.classList.add('d-none');
        }
      });

      // Allow keyboard navigation inside autocomplete
      inputQ.addEventListener('keydown', function(e) {
        const items = Array.from(listBox.querySelectorAll('.autocomplete-item'));
        if (!items.length || listBox.classList.contains('d-none')) return;
        const active = listBox.querySelector('.autocomplete-item.active');
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          if (!active) {
            items[0].classList.add('active');
            items[0].focus();
          } else {
            const idx = items.indexOf(active);
            active.classList.remove('active');
            const next = items[idx + 1] || items[0];
            next.classList.add('active');
            next.focus();
          }
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          if (!active) {
            items[items.length - 1].classList.add('active');
            items[items.length - 1].focus();
          } else {
            const idx = items.indexOf(active);
            active.classList.remove('active');
            const prev = items[idx - 1] || items[items.length - 1];
            prev.classList.add('active');
            prev.focus();
          }
        } else if (e.key === 'Escape') {
          listBox.classList.add('d-none');
        }
      });

    })();
  </script>
</body>

</html>
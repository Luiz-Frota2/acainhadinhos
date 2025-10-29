<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
date_default_timezone_set('America/Sao_Paulo');

/* ================== AUTENTICAÇÃO / SESSÃO ================== */
$idSelecionado = $_GET['id'] ?? '';
if (!$idSelecionado) {
  header("Location: .././login.php");
  exit;
}
if (
  !isset($_SESSION['usuario_logado']) ||
  !isset($_SESSION['empresa_id']) ||
  !isset($_SESSION['tipo_empresa']) ||
  !isset($_SESSION['usuario_id'])
) {
  header("Location: .././login.php?id=" . urlencode($idSelecionado));
  exit;
}

require '../../assets/php/conexao.php';

/* ================== USUÁRIO ================== */
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id  = (int)$_SESSION['usuario_id'];

try {
  $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
  $stmt->execute([':id' => $usuario_id]);
  if ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $nomeUsuario = $u['usuario'] ?? 'Usuário';
    $tipoUsuario = ucfirst($u['nivel'] ?? 'Comum');
  } else {
    echo "<script>alert('Usuário não encontrado.'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
    exit;
  }
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar usuário: " . htmlspecialchars($e->getMessage(), ENT_QUOTES) . "'); history.back();</script>";
  exit;
}

/* ================== AUTORIZAÇÃO ================== */
$acessoPermitido   = false;
$idEmpresaSession  = $_SESSION['empresa_id'];
$tipoSession       = $_SESSION['tipo_empresa'];

if (str_starts_with($idSelecionado, 'principal_')) {
  $acessoPermitido = ($tipoSession === 'principal' && $idEmpresaSession === 'principal_1');
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

/* ================== LOGO ================== */
try {
  $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1");
  $stmt->execute([':id' => $idSelecionado]);
  $sobre = $stmt->fetch(PDO::FETCH_ASSOC);
  $logoEmpresa = !empty($sobre['imagem']) ? "../../assets/img/empresa/" . $sobre['imagem'] : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
  $logoEmpresa = "../../assets/img/favicon/logo.png";
}

/* ================== CSRF ================== */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf_token'];

/* ================== AJAX: Itens ================== */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'itens') {
  header('Content-Type: text/html; charset=UTF-8');
  $sid = (int)($_GET['sid'] ?? 0);
  if ($sid <= 0) {
    http_response_code(400);
    echo '<div class="text-danger p-2">Solicitação inválida.</div>';
    exit;
  }

  // Valida acesso: pertence à matriz atual E é de FRANQUIA
  $ok = $pdo->prepare("
    SELECT COUNT(*)
    FROM solicitacoes_b2b s
    JOIN unidades u
      ON u.id = CAST(SUBSTRING_INDEX(s.id_solicitante, '_', -1) AS UNSIGNED)
    WHERE s.id = :id
      AND s.id_matriz = :matriz
      AND u.tipo = 'Franquia'
  ");
  $ok->execute([':id' => $sid, ':matriz' => $idSelecionado]);
  if (!$ok->fetchColumn()) {
    http_response_code(403);
    echo '<div class="text-danger p-2">Acesso negado.</div>';
    exit;
  }

  $q = $pdo->prepare("
    SELECT id, produto_id, codigo_produto, nome_produto, unidade, preco_unitario, quantidade, subtotal
    FROM solicitacoes_b2b_itens
    WHERE solicitacao_id = :sid
    ORDER BY id ASC
  ");
  $q->execute([':sid' => $sid]);
  $itens = $q->fetchAll(PDO::FETCH_ASSOC);

  if (!$itens) {
    echo '<div class="text-muted p-2">Nenhum item nesta solicitação.</div>';
    exit;
  } ?>
  <div class="table-responsive">
    <table class="table table-sm table-hover">
      <thead>
        <tr>
          <th>#</th>
          <th>SKU</th>
          <th>Produto</th>
          <th class="text-end">Qtde</th>
          <th>Unid.</th>
          <th class="text-end">Preço</th>
          <th class="text-end">Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($itens as $it): ?>
          <tr>
            <td><?= (int)$it['id'] ?></td>
            <td><?= htmlspecialchars($it['codigo_produto'], ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($it['nome_produto'], ENT_QUOTES) ?></td>
            <td class="text-end"><?= (int)$it['quantidade'] ?></td>
            <td><?= htmlspecialchars($it['unidade'], ENT_QUOTES) ?></td>
            <td class="text-end">R$ <?= number_format((float)$it['preco_unitario'], 2, ',', '.') ?></td>
            <td class="text-end">R$ <?= number_format((float)$it['subtotal'], 2, ',', '.') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php
  exit;
}

/* ================== AJAX: Autocomplete (apenas Franquias) ================== */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'autocomplete') {
  header('Content-Type: application/json; charset=UTF-8');
  $term = trim($_GET['q'] ?? '');
  $out  = [];
  if ($term !== '' && mb_strlen($term) >= 2) {
    $s1 = $pdo->prepare("
      SELECT DISTINCT s.id_solicitante AS val, 'Solicitante' AS tipo
      FROM solicitacoes_b2b s
      JOIN unidades u
        ON u.id = CAST(SUBSTRING_INDEX(s.id_solicitante, '_', -1) AS UNSIGNED)
      WHERE s.id_matriz = :matriz
        AND u.tipo = 'Franquia'
        AND s.id_solicitante LIKE :q
      ORDER BY s.id_solicitante
      LIMIT 10
    ");
    $s1->execute([':matriz' => $idSelecionado, ':q' => "%$term%"]);
    foreach ($s1 as $r) $out[] = ['label' => $r['val'], 'value' => $r['val'], 'tipo' => $r['tipo']];

    $s2 = $pdo->prepare("
      SELECT DISTINCT it.codigo_produto AS val, 'SKU' AS tipo
      FROM solicitacoes_b2b s
      JOIN solicitacoes_b2b_itens it ON it.solicitacao_id = s.id
      JOIN unidades u
        ON u.id = CAST(SUBSTRING_INDEX(s.id_solicitante, '_', -1) AS UNSIGNED)
      WHERE s.id_matriz = :matriz
        AND u.tipo = 'Franquia'
        AND it.codigo_produto LIKE :q
      ORDER BY it.codigo_produto
      LIMIT 10
    ");
    $s2->execute([':matriz' => $idSelecionado, ':q' => "%$term%"]);
    foreach ($s2 as $r) $out[] = ['label' => $r['val'], 'value' => $r['val'], 'tipo' => $r['tipo']];

    $s3 = $pdo->prepare("
      SELECT DISTINCT it.nome_produto AS val, 'Produto' AS tipo
      FROM solicitacoes_b2b s
      JOIN solicitacoes_b2b_itens it ON it.solicitacao_id = s.id
      JOIN unidades u
        ON u.id = CAST(SUBSTRING_INDEX(s.id_solicitante, '_', -1) AS UNSIGNED)
      WHERE s.id_matriz = :matriz
        AND u.tipo = 'Franquia'
        AND it.nome_produto LIKE :q
      ORDER BY it.nome_produto
      LIMIT 10
    ");
    $s3->execute([':matriz' => $idSelecionado, ':q' => "%$term%"]);
    foreach ($s3 as $r) $out[] = ['label' => $r['val'], 'value' => $r['val'], 'tipo' => $r['tipo']];
  }
  echo json_encode($out, JSON_UNESCAPED_UNICODE);
  exit;
}

/* ================== POST: mudar status ================== */
$flashMsg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'], $_POST['sid'], $_POST['csrf'])) {
  if (!hash_equals($CSRF, $_POST['csrf'])) {
    $flashMsg = ['type' => 'danger', 'text' => 'Falha de segurança (CSRF). Recarregue a página.'];
  } else {
    $sid  = (int)$_POST['sid'];
    $acao = $_POST['acao'];
    $id_matriz_post      = $_POST['id_matriz']      ?? $idSelecionado;
    $id_solicitante_post = $_POST['id_solicitante'] ?? '';

    try {
      // Confirma que pertence à matriz e é franquia
      $st = $pdo->prepare("
        SELECT s.id, s.status, s.id_matriz, s.id_solicitante
        FROM solicitacoes_b2b s
        JOIN unidades u
          ON u.id = CAST(SUBSTRING_INDEX(s.id_solicitante, '_', -1) AS UNSIGNED)
        WHERE s.id = :id
          AND s.id_matriz = :matriz
          AND u.tipo = 'Franquia'
      ");
      $st->execute([':id' => $sid, ':matriz' => $idSelecionado]);
      $row = $st->fetch(PDO::FETCH_ASSOC);
      if (!$row) {
        $flashMsg = ['type' => 'danger', 'text' => 'Solicitação não encontrada (ou não é de franquia).'];
      } else {
        if ($id_matriz_post !== $row['id_matriz'] || ($id_solicitante_post && $id_solicitante_post !== $row['id_solicitante'])) {
          $flashMsg = ['type' => 'danger', 'text' => 'Dados divergentes da solicitação.'];
        } else {
          $statusAtual = $row['status'];
          $novoStatus  = null;
          $setTime     = [];
          switch ($acao) {
            case 'aprovar':
              if ($statusAtual === 'pendente') {
                $novoStatus = 'aprovada';
                $setTime['aprovada_em'] = date('Y-m-d H:i:s');
              }
              break;
            case 'reprovar':
              if ($statusAtual === 'pendente') $novoStatus = 'reprovada';
              break;
            case 'cancelar':
              if (in_array($statusAtual, ['pendente', 'aprovada'])) $novoStatus = 'cancelada';
              break;
            case 'enviar':
              if ($statusAtual === 'aprovada') {
                $novoStatus = 'em_transito';
                $setTime['enviada_em'] = date('Y-m-d H:i:s');
              }
              break;
            case 'entregar':
              if ($statusAtual === 'em_transito') {
                $novoStatus = 'entregue';
                $setTime['entregue_em'] = date('Y-m-d H:i:s');
              }
              break;
          }
          if (!$novoStatus) {
            $flashMsg = ['type' => 'warning', 'text' => 'Transição de status não permitida.'];
          } else {
            $sql = "UPDATE solicitacoes_b2b SET status=:status, updated_at=NOW()";
            $params = [':status' => $novoStatus, ':id' => $sid, ':matriz' => $idSelecionado];
            foreach ($setTime as $col => $val) {
              $sql .= ", {$col}=:{$col}";
              $params[":{$col}"] = $val;
            }
            $sql .= " WHERE id=:id AND id_matriz=:matriz";
            $pdo->prepare($sql)->execute($params);
            $flashMsg = ['type' => 'success', 'text' => 'Status atualizado para "' . $novoStatus . '".'];
          }
        }
      }
    } catch (PDOException $e) {
      $flashMsg = ['type' => 'danger', 'text' => 'Erro ao atualizar status: ' . $e->getMessage()];
    }
  }
}

/* ================== FILTROS + PAGINAÇÃO ================== */
/* Esta página está fixa para mostrar APENAS status "aprovada" */
$perPage = 20;
$page    = max(1, (int)($_GET['p'] ?? 1));
$offset  = ($page - 1) * $perPage;

$status = 'aprovada'; // fixa "aprovada"
$q      = trim($_GET['q'] ?? '');
$de     = trim($_GET['de'] ?? '');
$ate    = trim($_GET['ate'] ?? '');

/* ===== WHERE base: por matriz + Apenas FRANQUIAS + status aprovado ===== */
$where  = ["s.id_matriz = :matriz", "u.tipo = 'Franquia'", "s.status = :status"];
$params = [':matriz' => $idSelecionado, ':status' => $status];

if ($de !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $de)) {
  $where[] = "DATE(s.created_at) >= :de";
  $params[':de']  = $de;
}
if ($ate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) {
  $where[] = "DATE(s.created_at) <= :ate";
  $params[':ate'] = $ate;
}
if ($q !== '') {
  $where[] = "(
    s.id_solicitante LIKE :q
    OR EXISTS(
      SELECT 1 FROM solicitacoes_b2b_itens it
      WHERE it.solicitacao_id = s.id
        AND (it.codigo_produto LIKE :q OR it.nome_produto LIKE :q)
    )
  )";
  $params[':q'] = "%$q%";
}
$whereSql = implode(' AND ', $where);

/* ===== COUNT (apenas Franquias) ===== */
$stCount = $pdo->prepare("
  SELECT COUNT(*)
  FROM solicitacoes_b2b s
  JOIN unidades u
    ON u.id = CAST(SUBSTRING_INDEX(s.id_solicitante, '_', -1) AS UNSIGNED)
  WHERE $whereSql
");
$stCount->execute($params);
$totalRows  = (int)$stCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

/* ===== SELECT (apenas Franquias) ===== */
$sql = "
SELECT
  s.id, s.id_solicitante, s.status, s.total_estimado,
  s.created_at, s.aprovada_em, s.enviada_em, s.entregue_em,
  COALESCE(COUNT(it.id),0) AS itens_count,
  COALESCE(SUM(it.quantidade),0) AS qtd_total,
  COALESCE(SUM(it.subtotal),0.00) AS subtotal_calc
  -- , MAX(u.nome) AS nome_unidade -- (se quiser exibir o nome)
FROM solicitacoes_b2b s
JOIN unidades u
  ON u.id = CAST(SUBSTRING_INDEX(s.id_solicitante, '_', -1) AS UNSIGNED)
LEFT JOIN solicitacoes_b2b_itens it ON it.solicitacao_id = s.id
WHERE $whereSql
GROUP BY s.id
ORDER BY s.created_at DESC
LIMIT :lim OFFSET :off
";
$st = $pdo->prepare($sql);
foreach ($params as $k => $v) $st->bindValue($k, $v);
$st->bindValue(':lim', (int)$perPage, PDO::PARAM_INT);
$st->bindValue(':off', (int)$offset, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

/* ================== MAPA STATUS ================== */
$statusMap = [
  'pendente'     => ['cls' => 'bg-label-warning', 'txt' => 'PENDENTE'],
  'aprovada'     => ['cls' => 'bg-label-info',    'txt' => 'APROVADA'],
  'reprovada'    => ['cls' => 'bg-label-dark',    'txt' => 'REPROVADA'],
  'em_transito'  => ['cls' => 'bg-label-primary', 'txt' => 'EM TRÂNSITO'],
  'entregue'     => ['cls' => 'bg-label-success', 'txt' => 'ENTREGUE'],
  'cancelada'    => ['cls' => 'bg-label-secondary', 'txt' => 'CANCELADA'],
];
?>

<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
  <title>ERP — Produtos Enviados (Franquias)</title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($logoEmpresa) ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
  <link rel="stylesheet" href="../../assets/vendor/css/core.css" />
  <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" />
  <link rel="stylesheet" href="../../assets/css/demo.css" />
  <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <script src="../../assets/vendor/js/helpers.js"></script>
  <script src="../../assets/js/config.js"></script>
  <style>
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
  </style>
</head>

<body>
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">

      <!-- ===== ASIDE ===== -->
      <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
        <div class="app-brand demo">
          <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="app-brand-link">
            <span class="app-brand-text demo menu-text fw-bolder ms-2" style="text-transform:capitalize;">Açaínhadinhos</span>
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
          <li class="menu-header small text-uppercase"><span class="menu-header-text">Administração Franquias</span></li>
          <li class="menu-item ">
            <a href="javascript:void(0);" class="menu-link menu-toggle"><i class="menu-icon tf-icons bx bx-building"></i>
              <div>Franquias</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item"><a href="./franquiaAdicionada.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Adicionadas</div>
                </a></li>
            </ul>
          </li>
          <li class="menu-item active open">
            <a href="javascript:void(0);" class="menu-link menu-toggle"><i class="menu-icon tf-icons bx bx-briefcase"></i>
              <div>B2B - Matriz</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item"><a href="./contasFranquia.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Pagamentos Solic.</div>
                </a></li>
              <li class="menu-item"><a href="./produtosSolicitados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Produtos Solicitados</div>
                </a></li>
              <li class="menu-item active"><a href="#" class="menu-link">
                  <div>Produtos Enviados</div>
                </a></li>
              <li class="menu-item"><a href="./transferenciasPendentes.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Transf. Pendentes</div>
                </a></li>
              <li class="menu-item"><a href="./historicoTransferencias.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Histórico Transf.</div>
                </a></li>
              <li class="menu-item"><a href="./estoqueMatriz.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Estoque Matriz</div>
                </a></li>
              <li class="menu-item"><a href="./relatoriosB2B.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Relatórios B2B</div>
                </a></li>
            </ul>
          </li>
          <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle"><i class="menu-icon tf-icons bx bx-bar-chart-alt-2"></i>
              <div>Relatórios</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item"><a href="./VendasFranquias.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Vendas por Franquias</div>
                </a></li>
              <li class="menu-item"><a href="./MaisVendidos.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Mais Vendidos</div>
                </a></li>
              <li class="menu-item"><a href="./FinanceiroFranquia.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Financeiro</div>
                </a></li>
            </ul>
          </li>
          <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
          <li class="menu-item"><a href="../rh/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-group"></i>
              <div>RH</div>
            </a></li>
          <li class="menu-item"><a href="../financas/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-dollar"></i>
              <div>Finanças</div>
            </a></li>
          <li class="menu-item"><a href="../pdv/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-desktop"></i>
              <div>PDV</div>
            </a></li>
          <li class="menu-item"><a href="../empresa/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-briefcase"></i>
              <div>Empresa</div>
            </a></li>
          <li class="menu-item"><a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-box"></i>
              <div>Estoque</div>
            </a></li>
          <li class="menu-item"><a href="../filial/index.php?id=principal_1" class="menu-link"><i class="menu-icon tf-icons bx bx-building"></i>
              <div>Filial</div>
            </a></li>
          <li class="menu-item"><a href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-group"></i>
              <div>Usuários</div>
            </a></li>
          <li class="menu-item"><a href="https://wa.me/92991515710" target="_blank" class="menu-link"><i class="menu-icon tf-icons bx bx-support"></i>
              <div>Suporte</div>
            </a></li>
        </ul>
      </aside>
      <!-- ===== /ASIDE ===== -->

      <!-- ===== Layout page ===== -->
      <div class="layout-page">
        <!-- Navbar -->
        <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
          <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
            <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)"><i class="bx bx-menu bx-sm"></i></a>
          </div>
          <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
            <div class="navbar-nav align-items-center">
              <div class="nav-item d-flex align-items-center">
              </div>
            </div>
            <ul class="navbar-nav flex-row align-items-center ms-auto">
              <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false">
                  <div class="avatar avatar-online"><img src="<?= htmlspecialchars($logoEmpresa, ENT_QUOTES) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" /></div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li><a class="dropdown-item" href="#">
                      <div class="d-flex">
                        <div class="flex-shrink-0 me-3">
                          <div class="avatar avatar-online"><img src="<?= htmlspecialchars($logoEmpresa, ENT_QUOTES) ?>" class="w-px-40 h-auto rounded-circle" /></div>
                        </div>
                        <div class="flex-grow-1"><span class="fw-semibold d-block"><?= htmlspecialchars($nomeUsuario, ENT_QUOTES); ?></span><small class="text-muted"><?= htmlspecialchars($tipoUsuario, ENT_QUOTES); ?></small></div>
                      </div>
                    </a></li>
                  <li>
                    <div class="dropdown-divider"></div>
                  </li>
                  <li><a class="dropdown-item" href="./contaUsuario.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-user me-2"></i><span class="align-middle">Minha Conta</span></a></li>
                  <li><a class="dropdown-item" href="#"><i class="bx bx-cog me-2"></i><span class="align-middle">Configurações</span></a></li>
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
          <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a href="#">Franquias</a>/</span> Produtos Enviados</h4>
          <h5 class="fw-bold mt-3 mb-3 custor-font"><span class="text-muted fw-light">Somente solicitações de <strong>Franquias</strong> aprovadas</span></h5>

          <?php if ($flashMsg): ?>
            <div class="alert alert-<?= htmlspecialchars($flashMsg['type']) ?> alert-dismissible" role="alert">
              <?= htmlspecialchars($flashMsg['text']) ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endif; ?>

          <!-- ===== Filtros ===== -->
          <form class="card mb-3" method="get" id="filtroForm" autocomplete="off">
            <input type="hidden" name="id" value="<?= htmlspecialchars($idSelecionado, ENT_QUOTES) ?>">
            <div class="card-body">
              <div class="row g-3 align-items-end">
                <!-- Status fixo (aprovada) - deixei o select, mas desabilitado/sem bind -->
                <div class="col-12 col-md-auto filter-col">
                  <label class="form-label mb-1">Status</label>
                  <select class="form-select form-select-sm" disabled>
                    <option selected>aprovada</option>
                  </select>
                </div>
                <div class="col-12 col-md-auto filter-col">
                  <label class="form-label mb-1">De</label>
                  <input type="date" class="form-control form-control-sm" name="de" value="<?= htmlspecialchars($de, ENT_QUOTES) ?>">
                </div>
                <div class="col-12 col-md-auto filter-col">
                  <label class="form-label mb-1">Até</label>
                  <input type="date" class="form-control form-control-sm" name="ate" value="<?= htmlspecialchars($ate, ENT_QUOTES) ?>">
                </div>
                <div class="col-12 col-md flex-grow-1 filter-col">
                  <label class="form-label mb-1">Buscar</label>
                  <div class="autocomplete">
                    <input type="text" class="form-control form-control-sm" id="qInput" name="q" placeholder="Solicitante (ex.: franquia_1), SKU ou Produto…" value="<?= htmlspecialchars($q, ENT_QUOTES) ?>" autocomplete="off">
                    <div class="autocomplete-list d-none" id="qList"></div>
                  </div>
                </div>
                <div class="col-12 col-md-auto d-flex gap-2 filter-col">
                  <button class="btn btn-sm btn-primary" type="submit"><i class="bx bx-filter-alt me-1"></i> Filtrar</button>
                  <a class="btn btn-sm btn-outline-secondary" href="?id=<?= urlencode($idSelecionado) ?>"><i class="bx bx-eraser me-1"></i> Limpar</a>
                </div>
              </div>
              <div class="small-muted mt-2">
                Encontradas <strong><?= (int)$totalRows ?></strong> solicitações (somente <strong>Franquias</strong>) · Página <strong><?= (int)$page ?></strong> de <strong><?= (int)$totalPages ?></strong>
              </div>
            </div>
          </form>

          <?php
          $qs = $_GET;
          $qs['id'] = $idSelecionado;
          $makeUrl = function ($p) use ($qs) {
            $qs['p'] = $p;
            return '?' . http_build_query($qs);
          };
          $range = 2;
          ?>
          <?php if ($totalPages > 1): ?>
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
              <div>
                <a class="btn btn-sm btn-outline-primary <?= ($page <= 1 ? 'disabled' : '') ?>" href="<?= $makeUrl(max(1, $page - 1)) ?>">Voltar</a>
                <a class="btn btn-sm btn-outline-primary <?= ($page >= $totalPages ? 'disabled' : '') ?>" href="<?= $makeUrl(min($totalPages, $page + 1)) ?>">Próximo</a>
              </div>
              <ul class="pagination mb-0">
                <li class="page-item <?= ($page <= 1 ? 'disabled' : '') ?>"><a class="page-link" href="<?= $makeUrl(1) ?>"><i class="bx bx-chevrons-left"></i></a></li>
                <li class="page-item <?= ($page <= 1 ? 'disabled' : '') ?>"><a class="page-link" href="<?= $makeUrl(max(1, $page - 1)) ?>"><i class="bx bx-chevron-left"></i></a></li>
                <?php
                $start = max(1, $page - $range);
                $end   = min($totalPages, $page + $range);
                if ($start > 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                for ($i = $start; $i <= $end; $i++) {
                  $active = ($i == $page) ? 'active' : '';
                  echo '<li class="page-item ' . $active . '"><a class="page-link" href="' . $makeUrl($i) . '">' . $i . '</a></li>';
                }
                if ($end < $totalPages) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                ?>
                <li class="page-item <?= ($page >= $totalPages ? 'disabled' : '') ?>"><a class="page-link" href="<?= $makeUrl(min($totalPages, $page + 1)) ?>"><i class="bx bx-chevron-right"></i></a></li>
                <li class="page-item <?= ($page >= $totalPages ? 'disabled' : '') ?>"><a class="page-link" href="<?= $makeUrl($totalPages) ?>"><i class="bx bx-chevrons-right"></i></a></li>
              </ul>
              <span class="small text-muted">Mostrando <?= (int)min($totalRows, $offset + 1) ?>–<?= (int)min($totalRows, $offset + $perPage) ?> de <?= (int)$totalRows ?></span>
            </div>
          <?php endif; ?>

          <!-- ===== Tabela ===== -->
          <div class="card table-zone">
            <h5 class="card-header">Lista de Produtos Enviados (Franquias aprovadas)</h5>
            <div class="overflow-x">
              <div class="table-responsive text-nowrap">
                <table class="table table-hover align-middle">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Solicitante</th>
                      <th class="text-end">Itens</th>
                      <th class="text-end">Qtd Total</th>
                      <th class="text-end">Total (Calc.)</th>
                      <th class="text-end">Total (Registro)</th>
                      <th>Criado em</th>
                      <th>Status</th>
                      <th class="sticky-actions">Ações</th>
                    </tr>
                  </thead>
                  <tbody class="table-border-bottom-0">
                    <?php if (!$rows): ?>
                      <tr>
                        <td colspan="9" class="text-center text-muted py-4">Nenhuma solicitação encontrada.</td>
                      </tr>
                      <?php else: foreach ($rows as $r):
                        $sm = $statusMap[$r['status']] ?? ['cls' => 'bg-label-secondary', 'txt' => $r['status']];
                      ?>
                        <tr>
                          <td><?= (int)$r['id'] ?></td>
                          <td><strong><?= htmlspecialchars($r['id_solicitante'], ENT_QUOTES) ?></strong> <span class="badge bg-label-success ms-1">Franquia</span></td>
                          <td class="text-end"><?= (int)$r['itens_count'] ?></td>
                          <td class="text-end"><?= (int)$r['qtd_total'] ?></td>
                          <td class="text-end">R$ <?= number_format((float)$r['subtotal_calc'], 2, ',', '.') ?></td>
                          <td class="text-end">R$ <?= number_format((float)$r['total_estimado'], 2, ',', '.') ?></td>
                          <td><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
                          <td><span class="badge status-badge <?= $sm['cls'] ?>"><?= htmlspecialchars($sm['txt']) ?></span></td>
                          <td class="sticky-actions">
                            <div class="btn-group btn-group-sm">
                              <button class="btn btn-outline-secondary"
                                data-bs-toggle="modal" data-bs-target="#modalDetalhes"
                                data-sid="<?= (int)$r['id'] ?>">
                                <i class="bx bx-detail me-1"></i> Detalhes
                              </button>
                              <!-- Abre a modal de status -->
                              <button class="btn btn-outline-primary"
                                data-bs-toggle="modal"
                                data-bs-target="#modalStatus"
                                data-sid="<?= (int)$r['id'] ?>"
                                data-status="<?= htmlspecialchars($r['status'], ENT_QUOTES) ?>"
                                data-solicitante="<?= htmlspecialchars($r['id_solicitante'], ENT_QUOTES) ?>">
                                Mudar Status
                              </button>
                            </div>
                            <?php if (!empty($r['aprovada_em']) || !empty($r['enviada_em']) || !empty($r['entregue_em'])): ?>
                              <div class="small-muted mt-1">
                                <?php if (!empty($r['aprovada_em'])): ?>Aprov.: <?= date('d/m H:i', strtotime($r['aprovada_em'])) ?> · <?php endif; ?>
                              <?php if (!empty($r['enviada_em'])): ?>Env.: <?= date('d/m H:i', strtotime($r['enviada_em'])) ?> · <?php endif; ?>
                            <?php if (!empty($r['entregue_em'])): ?>Entreg.: <?= date('d/m H:i', strtotime($r['entregue_em'])) ?><?php endif; ?>
                              </div>
                            <?php endif; ?>
                          </td>
                        </tr>
                    <?php endforeach;
                    endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Paginação (rodapé) -->
            <?php if ($totalPages > 1): ?>
              <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                  <a class="btn btn-sm btn-outline-primary <?= ($page <= 1 ? 'disabled' : '') ?>" href="<?= $makeUrl(max(1, $page - 1)) ?>">Voltar</a>
                  <a class="btn btn-sm btn-outline-primary <?= ($page >= $totalPages ? 'disabled' : '') ?>" href="<?= $makeUrl(min($totalPages, $page + 1)) ?>">Próximo</a>
                </div>
                <ul class="pagination mb-0">
                  <li class="page-item <?= ($page <= 1 ? 'disabled' : '') ?>"><a class="page-link" href="<?= $makeUrl(1) ?>"><i class="bx bx-chevrons-left"></i></a></li>
                  <li class="page-item <?= ($page <= 1 ? 'disabled' : '') ?>"><a class="page-link" href="<?= $makeUrl(max(1, $page - 1)) ?>"><i class="bx bx-chevron-left"></i></a></li>
                  <?php
                  $start = max(1, $page - 2);
                  $end   = min($totalPages, $page + 2);
                  if ($start > 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                  for ($i = $start; $i <= $end; $i++) {
                    $active = ($i == $page) ? 'active' : '';
                    echo '<li class="page-item ' . $active . '"><a class="page-link" href="' . $makeUrl($i) . '">' . $i . '</a></li>';
                  }
                  if ($end < $totalPages) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                  ?>
                  <li class="page-item <?= ($page >= $totalPages ? 'disabled' : '') ?>"><a class="page-link" href="<?= $makeUrl(min($totalPages, $page + 1)) ?>"><i class="bx bx-chevron-right"></i></a></li>
                  <li class="page-item <?= ($page >= $totalPages ? 'disabled' : '') ?>"><a class="page-link" href="<?= $makeUrl($totalPages) ?>"><i class="bx bx-chevrons-right"></i></a></li>
                </ul>
                <span class="small text-muted">Mostrando <?= (int)min($totalRows, $offset + 1) ?>–<?= (int)min($totalRows, $offset + $perPage) ?> de <?= (int)$totalRows ?></span>
              </div>
            <?php endif; ?>
          </div>

          <!-- Modal Detalhes -->
          <div class="modal fade" id="modalDetalhes" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">Itens da Solicitação <span id="md-title-id" class="text-muted"></span></h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                  <div id="modal-detalhes-body" class="py-2 text-center text-muted">Carregando…</div>
                </div>
                <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button></div>
              </div>
            </div>
          </div>

          <!-- Modal Mudar Status -->
          <div class="modal fade" id="modalStatus" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">Mudar status da solicitação <span id="ms-title-id" class="text-muted"></span></h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <!-- Mantido seu destino de submit -->
                <form method="post" id="formStatus" class="m-0" action="../../assets/php/franquia/produtosSolicitadosSubmit.php">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF, ENT_QUOTES) ?>">
                  <input type="hidden" name="id" value="<?= htmlspecialchars($idSelecionado, ENT_QUOTES) ?>">
                  <input type="hidden" name="sid" id="ms-sid" value="">
                  <input type="hidden" name="acao" id="ms-acao" value="">
                  <input type="hidden" name="id_matriz" id="ms-id-matriz" value="<?= htmlspecialchars($idSelecionado, ENT_QUOTES) ?>">
                  <input type="hidden" name="id_solicitante" id="ms-id-solicitante" value="">
                  <div class="modal-body">
                    <div class="mb-3">
                      <label class="form-label">Ação</label>
                      <select class="form-select" id="ms-select"></select>
                    </div>
                    <div class="mb-3 d-none" id="ms-motivo-wrap">
                      <label class="form-label">Motivo (opcional)</label>
                      <textarea class="form-control" rows="3" name="motivo" placeholder="Descreva o motivo..."></textarea>
                    </div>
                    <div class="small text-muted">As opções exibidas dependem do status atual da solicitação.</div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Confirmar</button>
                  </div>
                </form>
              </div>
            </div>
          </div>

        </div><!-- /container -->
      </div><!-- /layout-page -->
    </div><!-- /layout-container -->
  </div>

  <!-- Core JS -->
  <script src="../../js/saudacao.js"></script>
  <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../../assets/vendor/libs/popper/popper.js"></script>
  <script src="../../assets/vendor/js/bootstrap.js"></script>
  <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="../../assets/vendor/js/menu.js"></script>
  <script src="../../assets/js/main.js"></script>
  <script async defer src="https://buttons.github.io/buttons.js"></script>

  <script>
    /* ===== Modal Detalhes (AJAX) ===== */
    const modalDetalhes = document.getElementById('modalDetalhes');
    modalDetalhes.addEventListener('show.bs.modal', function(event) {
      const button = event.relatedTarget;
      const sid = button?.getAttribute('data-sid');
      const body = document.getElementById('modal-detalhes-body');
      const titleId = document.getElementById('md-title-id');
      body.innerHTML = 'Carregando…';
      titleId.textContent = sid ? ('#' + sid) : '';
      if (!sid) {
        body.innerHTML = '<div class="text-danger">Solicitação inválida.</div>';
        return;
      }
      const url = new URL(window.location.href);
      url.searchParams.set('ajax', 'itens');
      url.searchParams.set('sid', sid);
      fetch(url.toString(), {
          credentials: 'same-origin'
        })
        .then(r => r.text()).then(html => body.innerHTML = html)
        .catch(() => body.innerHTML = '<div class="text-danger">Falha ao carregar itens.</div>');
    });

    /* ===== Modal Status (dinâmica) ===== */
    const modalStatus = document.getElementById('modalStatus');
    const msSid = document.getElementById('ms-sid');
    const msAcao = document.getElementById('ms-acao');
    const msSelect = document.getElementById('ms-select');
    const msTitulo = document.getElementById('ms-title-id');
    const msMotivoWrap = document.getElementById('ms-motivo-wrap');
    const msIdMatriz = document.getElementById('ms-id-matriz');
    const msIdSolicitante = document.getElementById('ms-id-solicitante');

    function optionsForStatus(st) {
      if (st === 'pendente') return [{
        v: 'aprovar',
        t: 'Aprovar'
      }, {
        v: 'reprovar',
        t: 'Reprovar'
      }, {
        v: 'cancelar',
        t: 'Cancelar'
      }];
      if (st === 'aprovada') return [{
        v: 'enviar',
        t: 'Marcar Em Trânsito'
      }, {
        v: 'cancelar',
        t: 'Cancelar'
      }];
      if (st === 'em_transito') return [{
        v: 'entregar',
        t: 'Marcar Entregue'
      }];
      return [];
    }

    modalStatus.addEventListener('show.bs.modal', function(event) {
      const btn = event.relatedTarget;
      const sid = btn?.getAttribute('data-sid');
      const statusAtual = btn?.getAttribute('data-status') || '';
      const solicitante = btn?.getAttribute('data-solicitante') || '';

      msSid.value = sid || '';
      msTitulo.textContent = sid ? ('#' + sid) : '';
      msIdMatriz.value = '<?= htmlspecialchars($idSelecionado, ENT_QUOTES) ?>';
      msIdSolicitante.value = solicitante;

      const ops = optionsForStatus(statusAtual);
      if (!ops.length) {
        msSelect.innerHTML = '<option value="">Sem ações disponíveis</option>';
        msSelect.disabled = true;
      } else {
        msSelect.disabled = false;
        msSelect.innerHTML = ops.map(o => `<option value="${o.v}">${o.t}</option>`).join('');
      }

      const toggleMotivo = () => {
        const v = msSelect.value;
        if (v === 'reprovar' || v === 'cancelar') msMotivoWrap.classList.remove('d-none');
        else msMotivoWrap.classList.add('d-none');
      };
      toggleMotivo();
      msSelect.onchange = toggleMotivo;
    });

    document.getElementById('formStatus').addEventListener('submit', function(e) {
      if (!msSelect.value) {
        e.preventDefault();
        return;
      }
      msAcao.value = msSelect.value;
    });

    /* ===== Autocomplete ===== */
    (function() {
      const qInput = document.getElementById('qInput');
      const list = document.getElementById('qList');
      const form = document.getElementById('filtroForm');
      let items = [],
        activeIndex = -1,
        aborter = null;

      function closeList() {
        list.classList.add('d-none');
        list.innerHTML = '';
        activeIndex = -1;
        items = [];
      }

      function openList() {
        list.classList.remove('d-none');
      }

      function escapeHtml(s) {
        return (s || '').toString().replace(/[&<>"']/g, m => ({
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#039;'
        } [m]));
      }

      function render(data) {
        if (!data || !data.length) {
          closeList();
          return;
        }
        items = data.slice(0, 15);
        list.innerHTML = items.map((it, i) => `
          <div class="autocomplete-item" data-i="${i}">
            <span>${escapeHtml(it.label)}</span><span class="autocomplete-tag">${escapeHtml(it.tipo)}</span>
          </div>`).join('');
        openList();
      }

      function pick(i) {
        if (i < 0 || i >= items.length) return;
        qInput.value = items[i].value;
        closeList();
        form.submit();
      }
      qInput.addEventListener('input', function() {
        const v = qInput.value.trim();
        if (v.length < 2) {
          closeList();
          return;
        }
        if (aborter) aborter.abort();
        aborter = new AbortController();
        const url = new URL(window.location.href);
        url.searchParams.set('ajax', 'autocomplete');
        url.searchParams.set('q', v);
        fetch(url.toString(), {
            signal: aborter.signal
          })
          .then(r => r.json()).then(render).catch(() => {});
      });
      qInput.addEventListener('keydown', function(e) {
        if (list.classList.contains('d-none')) return;
        if (e.key === 'ArrowDown') {
          activeIndex = Math.min(activeIndex + 1, items.length - 1);
          highlight();
          e.preventDefault();
        } else if (e.key === 'ArrowUp') {
          activeIndex = Math.max(activeIndex - 1, 0);
          highlight();
          e.preventDefault();
        } else if (e.key === 'Enter') {
          if (activeIndex >= 0) {
            pick(activeIndex);
            e.preventDefault();
          }
        } else if (e.key === 'Escape') {
          closeList();
        }
      });
      list.addEventListener('mousedown', function(e) {
        const el = e.target.closest('.autocomplete-item');
        if (!el) return;
        pick(parseInt(el.dataset.i, 10));
      });
      document.addEventListener('click', function(e) {
        if (!list.contains(e.target) && e.target !== qInput) closeList();
      });

      function highlight() {
        [...list.querySelectorAll('.autocomplete-item')].forEach((el, idx) => el.classList.toggle('active', idx === activeIndex));
      }
    })();
  </script>
</body>

</html>
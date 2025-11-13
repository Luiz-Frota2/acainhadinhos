<?php
// transferenciasPendentes.php — SOMENTE status = 'pendente'
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
date_default_timezone_set('America/Sao_Paulo');

// ==== Polyfill p/ PHP < 8 (str_starts_with) ====
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        return $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

// ================== AUTENTICAÇÃO / SESSÃO ==================
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

// ================== CONEXÃO ==================
require_once '../../assets/php/conexao.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo "Erro: conexão indisponível.";
    exit;
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// helpers mínimos antes de qualquer saída
function e(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
function dateBr(?string $dt): string
{
    return $dt ? date('d/m/Y H:i', strtotime($dt)) : '-';
}

// ================== USUÁRIO ==================
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
    echo "<script>alert('Erro ao carregar usuário: " . e($e->getMessage()) . "'); history.back();</script>";
    exit;
}

// ================== AUTORIZAÇÃO ==================
$acessoPermitido   = false;
$idEmpresaSession  = $_SESSION['empresa_id'];
$tipoSession       = $_SESSION['tipo_empresa']; // 'principal' | 'filial' | 'unidade' | 'franquia'

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

// ================== LOGO ==================
try {
    $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1");
    $stmt->execute([':id' => $idSelecionado]);
    $sobre = $stmt->fetch(PDO::FETCH_ASSOC);
    $logoEmpresa = !empty($sobre['imagem']) ? "../../assets/img/empresa/" . $sobre['imagem'] : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png";
}

// ================== CSRF ==================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf_token'];

// ================== MAPA STATUS (só exibimos pendente aqui) ==================
$statusMap = [
    'pendente'     => ['cls' => 'bg-label-warning',  'txt' => 'PENDENTE'],
    'aprovada'     => ['cls' => 'bg-label-info',     'txt' => 'APROVADA'],
    'reprovada'    => ['cls' => 'bg-label-dark',     'txt' => 'REPROVADA'],
    'em_transito'  => ['cls' => 'bg-label-primary',  'txt' => 'EM TRÂNSITO'],
    'entregue'     => ['cls' => 'bg-label-success',  'txt' => 'ENTREGUE'],
    'cancelada'    => ['cls' => 'bg-label-secondary', 'txt' => 'CANCELADA'],
];

// ================== MINI ENDPOINT AJAX (ANTES DO HTML!) ==================
if (($_GET['ajax'] ?? '') === 'itens') {
    header('Content-Type: text/html; charset=UTF-8');

    $sid = (int)($_GET['sid'] ?? 0);
    if ($sid <= 0) {
        echo '<div class="text-danger p-2">Solicitação inválida.</div>';
        exit;
    }

    // valida escopo
    if ($tipoSession === 'franquia') {
        $ok = $pdo->prepare("
      SELECT COUNT(*)
      FROM solicitacoes_b2b s
      JOIN unidades u ON u.id = CAST(SUBSTRING_INDEX(s.id_solicitante, '_', -1) AS UNSIGNED)
      WHERE s.id = :id
        AND u.tipo = 'Franquia'
        AND CAST(SUBSTRING_INDEX(s.id_solicitante, '_', -1) AS UNSIGNED) = CAST(SUBSTRING_INDEX(:sol, '_', -1) AS UNSIGNED)
    ");
        $ok->execute([':id' => $sid, ':sol' => $idSelecionado]);
    } else {
        $ok = $pdo->prepare("
      SELECT COUNT(*)
      FROM solicitacoes_b2b s
      JOIN unidades u ON u.id = CAST(SUBSTRING_INDEX(s.id_solicitante, '_', -1) AS UNSIGNED)
      WHERE s.id = :id
        AND s.id_matriz = :mat
        AND u.tipo = 'Franquia'
    ");
        $ok->execute([':id' => $sid, ':mat' => $idSelecionado]);
    }
    if (!$ok->fetchColumn()) {
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
    }
?>
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
                        <td><?= e((string)$it['codigo_produto']) ?></td>
                        <td><?= e((string)$it['nome_produto']) ?></td>
                        <td class="text-end"><?= (int)$it['quantidade'] ?></td>
                        <td><?= e((string)$it['unidade']) ?></td>
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

// ============== POST: transições de status (perfil principal) ==============
$flashMsg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'], $_POST['sid'], $_POST['csrf'])) {
    if ($tipoSession !== 'principal') {
        $flashMsg = ['type' => 'danger', 'text' => 'Ação não permitida para seu perfil.'];
    } elseif (!hash_equals($CSRF, (string)$_POST['csrf'])) {
        $flashMsg = ['type' => 'danger', 'text' => 'Falha de segurança (CSRF). Recarregue a página.'];
    } else {
        $sid  = (int)$_POST['sid'];
        $acao = (string)$_POST['acao'];

        try {
            $st = $pdo->prepare("
        SELECT s.id, s.status
        FROM solicitacoes_b2b s
        JOIN unidades u ON u.id = CAST(SUBSTRING_INDEX(s.id_solicitante, '_', -1) AS UNSIGNED)
        WHERE s.id = :id
          AND s.id_matriz = :mat
          AND u.tipo = 'Franquia'
      ");
            $st->execute([':id' => $sid, ':mat' => $idSelecionado]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $flashMsg = ['type' => 'danger', 'text' => 'Solicitação não encontrada para esta matriz (ou não é de franquia).'];
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
                        if (in_array($statusAtual, ['pendente', 'aprovada'], true)) $novoStatus = 'cancelada';
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
                    $flashMsg = ['type' => 'warning', 'text' => 'Transição de status não permitida a partir de "' . $statusAtual . '".'];
                } else {
                    $sql = "UPDATE solicitacoes_b2b SET status=:status, updated_at=NOW()";
                    $params = [':status' => $novoStatus, ':id' => $sid, ':mat' => $idSelecionado];
                    foreach ($setTime as $col => $val) {
                        $sql .= ", {$col}=:{$col}";
                        $params[":{$col}"] = $val;
                    }
                    $sql .= " WHERE id=:id AND id_matriz=:mat";
                    $pdo->prepare($sql)->execute($params);
                    $flashMsg = ['type' => 'success', 'text' => 'Status atualizado para "' . $novoStatus . '".'];
                }
            }
        } catch (PDOException $e) {
            $flashMsg = ['type' => 'danger', 'text' => 'Erro ao atualizar status: ' . $e->getMessage()];
        }
    }
}

// ================== FILTROS + ESCOPOS ==================
$perPage = 20;
$page    = max(1, (int)($_GET['p'] ?? 1));
$offset  = ($page - 1) * $perPage;

$q      = trim($_GET['q'] ?? '');
$de     = trim($_GET['de'] ?? '');
$ate    = trim($_GET['ate'] ?? '');

// WHERE base: somente FRANQUIAS + escopo
$where  = ["u.tipo = 'Franquia'"];
$params = [];

if ($tipoSession === 'franquia') {
    $where[] = "CAST(SUBSTRING_INDEX(s.id_solicitante, '_', -1) AS UNSIGNED) = CAST(SUBSTRING_INDEX(:sol, '_', -1) AS UNSIGNED)";
    $params[':sol'] = $idSelecionado;
} else {
    $where[] = "s.id_matriz = :matriz";
    $params[':matriz'] = $idSelecionado;
}

// **TRAVADO EM PENDENTE**
$where[] = "s.status = 'pendente'";

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

// COUNT
$stCount = $pdo->prepare("
  SELECT COUNT(*)
  FROM solicitacoes_b2b s
  JOIN unidades u ON u.id = CAST(SUBSTRING_INDEX(s.id_solicitante, '_', -1) AS UNSIGNED)
  WHERE $whereSql
");
$stCount->execute($params);
$totalRows  = (int)$stCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

// SELECT
$sql = "
SELECT
  s.id, s.id_solicitante, s.status, s.total_estimado,
  s.created_at, s.aprovada_em, s.enviada_em,
  COALESCE(COUNT(it.id),0) AS itens_count,
  COALESCE(SUM(it.quantidade),0) AS qtd_total,
  COALESCE(SUM(it.subtotal),0.00) AS subtotal_calc,
  MAX(u.nome) AS nome_unidade
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

// ===== view helpers =====
function actionsFor(string $status): array
{
    // aqui só vai cair 'pendente', mas deixo por segurança
    if ($status === 'pendente')   return [['v' => 'aprovar', 't' => 'Aprovar'], ['v' => 'reprovar', 't' => 'Reprovar'], ['v' => 'cancelar', 't' => 'Cancelar']];
    if ($status === 'aprovada')   return [['v' => 'enviar', 't' => 'Marcar Em Trânsito'], ['v' => 'cancelar', 't' => 'Cancelar']];
    if ($status === 'em_transito') return [['v' => 'entregar', 't' => 'Marcar Entregue']];
    return [];
}
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>ERP — Transferências Pendentes (Franquias)</title>
    <link rel="icon" type="image/x-icon" href="<?= e($logoEmpresa) ?>" />
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

        .sticky-actions {
            white-space: nowrap
        }

        .small-muted {
            font-size: .8rem;
            color: #8b98a8
        }
    </style>
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">

            <!-- ===== ASIDE (mantém seu layout) ===== -->
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
                    <li class="menu-item">
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
                            <li class="menu-item"><a href="./produtosEnviados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Produtos Enviados</div>
                                </a></li>
                            <li class="menu-item active"><a href="./transferenciasPendentes.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
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
                       <!-- Relatórios -->
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-bar-chart-alt-2"></i>
                            <div data-i18n="Relatorios">Relatórios</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./VendasFranquias.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Vendas">Vendas por Franquias</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./MaisVendidos.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="MaisVendidos">Mais Vendidos</div>
                                </a>
                            </li>
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
                                    <div class="avatar avatar-online"><img src="<?= e($logoEmpresa) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" /></div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online"><img src="<?= e($logoEmpresa) ?>" class="w-px-40 h-auto rounded-circle" /></div>
                                                </div>
                                                <div class="flex-grow-1"><span class="fw-semibold d-block"><?= e($nomeUsuario); ?></span><small class="text-muted"><?= e($tipoUsuario); ?></small></div>
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
                    <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a href="#">Franquias</a>/</span> Transferências Pendentes</h4>
                    <h5 class="fw-bold mt-3 mb-3 custor-font"><span class="text-muted fw-light">Listando somente solicitações com status <strong>PENDENTE</strong>.</span></h5>

                    <?php if ($flashMsg): ?>
                        <div class="alert alert-<?= e($flashMsg['type']) ?> alert-dismissible" role="alert">
                            <?= e($flashMsg['text']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Filtros (sem select de status) -->
                    <form class="card mb-3" method="get" id="filtroForm">
                        <input type="hidden" name="id" value="<?= e($idSelecionado) ?>">
                        <div class="card-body">
                            <div class="row g-3 align-items-end">
                                <div class="col-12 col-md-auto">
                                    <label class="form-label mb-1">De</label>
                                    <input type="date" class="form-control form-control-sm" name="de" value="<?= e($de) ?>">
                                </div>
                                <div class="col-12 col-md-auto">
                                    <label class="form-label mb-1">Até</label>
                                    <input type="date" class="form-control form-control-sm" name="ate" value="<?= e($ate) ?>">
                                </div>
                                <div class="col-12 col-md">
                                    <label class="form-label mb-1">Buscar</label>
                                    <input type="text" class="form-control form-control-sm" name="q" placeholder="Solicitante (ex.: franquia_3), SKU ou Produto…" value="<?= e($q) ?>">
                                </div>
                                <div class="col-12 col-md-auto d-flex gap-2">
                                    <button class="btn btn-sm btn-primary" type="submit"><i class="bx bx-filter-alt me-1"></i> Filtrar</button>
                                    <a class="btn btn-sm btn-outline-secondary" href="?id=<?= urlencode($idSelecionado) ?>"><i class="bx bx-eraser me-1"></i> Limpar</a>
                                </div>
                            </div>
                            <div class="small-muted mt-2">
                                Encontradas <strong><?= (int)$totalRows ?></strong> pendentes · Página <strong><?= (int)$page ?></strong> de <strong><?= (int)$totalPages ?></strong>
                            </div>
                        </div>
                    </form>

                    <!-- Tabela -->
                    <div class="card">
                        <h5 class="card-header">Lista de Transferências Pendentes (Franquias)</h5>
                        <div class="table-responsive text-nowrap">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Solicitante</th>
                                        <th>Unidade</th>
                                        <th class="text-end">Itens</th>
                                        <th class="text-end">Qtd Total</th>
                                        <th class="text-end">Total (Calc.)</th>
                                        <th>Criado</th>
                                        <th>Status</th>
                                        <th class="sticky-actions">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="table-border-bottom-0">
                                    <?php if (!$rows): ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-4">Nenhuma solicitação pendente no critério informado.</td>
                                        </tr>
                                        <?php else: foreach ($rows as $r):
                                            $sm = $statusMap[$r['status']] ?? ['cls' => 'bg-label-secondary', 'txt' => strtoupper($r['status'])];
                                            $ops = actionsFor($r['status']);
                                        ?>
                                            <tr>
                                                <td><?= (int)$r['id'] ?></td>
                                                <td><strong><?= e($r['id_solicitante']) ?></strong> <span class="badge bg-label-success ms-1">Franquia</span></td>
                                                <td><?= e($r['nome_unidade'] ?: '-') ?></td>
                                                <td class="text-end"><?= (int)$r['itens_count'] ?></td>
                                                <td class="text-end"><?= (int)$r['qtd_total'] ?></td>
                                                <td class="text-end">R$ <?= number_format((float)$r['subtotal_calc'], 2, ',', '.') ?></td>
                                                <td><?= e(dateBr($r['created_at'])) ?></td>
                                                <td><span class="badge status-badge <?= e($sm['cls']) ?>"><?= e($sm['txt']) ?></span></td>
                                                <td class="sticky-actions">
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-secondary"
                                                            data-bs-toggle="modal" data-bs-target="#modalDetalhes"
                                                            data-sid="<?= (int)$r['id'] ?>">
                                                            Detalhes
                                                        </button>
                                                        <?php if ($tipoSession === 'principal' && $ops): ?>
                                                            <button class="btn btn-outline-primary"
                                                                data-bs-toggle="modal" data-bs-target="#modalStatus"
                                                                data-sid="<?= (int)$r['id'] ?>"
                                                                data-status="<?= e($r['status']) ?>">
                                                                Mudar Status
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if (!empty($r['aprovada_em']) || !empty($r['enviada_em'])): ?>
                                                        <div class="small-muted mt-1">
                                                            <?php if (!empty($r['aprovada_em'])): ?>Aprov.: <?= e(date('d/m H:i', strtotime($r['aprovada_em']))) ?><?php endif; ?>
                                                            <?php if (!empty($r['enviada_em'])): ?><?= !empty($r['aprovada_em']) ? ' · ' : '' ?>Env.: <?= e(date('d/m H:i', strtotime($r['enviada_em']))) ?><?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                    <?php endforeach;
                                    endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginação -->
                        <?php
                        $qs = $_GET;
                        $qs['id'] = $idSelecionado;
                        $range = 2;
                        $makeUrl = function ($p) use ($qs) {
                            $qs['p'] = $p;
                            return '?' . http_build_query($qs);
                        };
                        if ($totalPages > 1): ?>
                            <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <a class="btn btn-sm btn-outline-primary <?= ($page <= 1 ? 'disabled' : '') ?>" href="<?= e($makeUrl(max(1, $page - 1))) ?>">Voltar</a>
                                    <a class="btn btn-sm btn-outline-primary <?= ($page >= $totalPages ? 'disabled' : '') ?>" href="<?= e($makeUrl(min($totalPages, $page + 1))) ?>">Próximo</a>
                                </div>
                                <ul class="pagination mb-0">
                                    <li class="page-item <?= ($page <= 1 ? 'disabled' : '') ?>"><a class="page-link" href="<?= e($makeUrl(1)) ?>"><i class="bx bx-chevrons-left"></i></a></li>
                                    <li class="page-item <?= ($page <= 1 ? 'disabled' : '') ?>"><a class="page-link" href="<?= e($makeUrl(max(1, $page - 1))) ?>"><i class="bx bx-chevron-left"></i></a></li>
                                    <?php
                                    $start = max(1, $page - $range);
                                    $end = min($totalPages, $page + $range);
                                    if ($start > 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                    for ($i = $start; $i <= $end; $i++) {
                                        $active = ($i == $page) ? 'active' : '';
                                        echo '<li class="page-item ' . $active . '"><a class="page-link" href="' . e($makeUrl($i)) . '">' . $i . '</a></li>';
                                    }
                                    if ($end < $totalPages) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                    ?>
                                    <li class="page-item <?= ($page >= $totalPages ? 'disabled' : '') ?>"><a class="page-link" href="<?= e($makeUrl(min($totalPages, $page + 1))) ?>"><i class="bx bx-chevron-right"></i></a></li>
                                    <li class="page-item <?= ($page >= $totalPages ? 'disabled' : '') ?>"><a class="page-link" href="<?= e($makeUrl($totalPages)) ?>"><i class="bx bx-chevrons-right"></i></a></li>
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
                                <form method="post" id="formStatus" class="m-0" action="">
                                    <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
                                    <input type="hidden" name="id" value="<?= e($idSelecionado) ?>">
                                    <input type="hidden" name="sid" id="ms-sid" value="">
                                    <input type="hidden" name="acao" id="ms-acao" value="">
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Ação</label>
                                            <select class="form-select" id="ms-select"></select>
                                        </div>
                                        <div class="small text-muted">As opções dependem do status atual (aqui: PENDENTE).</div>
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
        // Modal Detalhes (carrega SOMENTE a tabela HTML do endpoint)
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
                    credentials: 'same-origin',
                    cache: 'no-store'
                })
                .then(r => r.text()).then(html => body.innerHTML = html)
                .catch(() => body.innerHTML = '<div class="text-danger">Falha ao carregar itens.</div>');
        });

        // Modal Status (somente pendente terá ações aqui)
        const modalStatus = document.getElementById('modalStatus');
        const msSid = document.getElementById('ms-sid');
        const msAcao = document.getElementById('ms-acao');
        const msSelect = document.getElementById('ms-select');
        const msTitulo = document.getElementById('ms-title-id');

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
            msSid.value = sid || '';
            msTitulo.textContent = sid ? ('#' + sid) : '';
            const ops = optionsForStatus(statusAtual);
            if (!ops.length) {
                msSelect.innerHTML = '<option value="">Sem ações disponíveis</option>';
                msSelect.disabled = true;
            } else {
                msSelect.disabled = false;
                msSelect.innerHTML = ops.map(o => `<option value="${o.v}">${o.t}</option>`).join('');
            }
        });

        document.getElementById('formStatus').addEventListener('submit', function(e) {
            if (!msSelect.value) {
                e.preventDefault();
                return;
            }
            msAcao.value = msSelect.value;
        });
    </script>
</body>

</html>
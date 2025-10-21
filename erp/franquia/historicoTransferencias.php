<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

/* ================== ENTRADA / SESSÃO ================== */
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

/* ================== CONEXÃO ================== */
require '../../assets/php/conexao.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ================== USUÁRIO ================== */
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id  = (int)$_SESSION['usuario_id'];

try {
    $st = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    $st->execute([':id' => $usuario_id]);
    if ($u = $st->fetch(PDO::FETCH_ASSOC)) {
        $nomeUsuario = $u['usuario'] ?? 'Usuário';
        $tipoUsuario = ucfirst($u['nivel'] ?? 'Comum');
    } else {
        echo "<script>alert('Usuário não encontrado.'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
        exit;
    }
} catch (Throwable $e) {
    echo "<script>alert('Erro ao carregar usuário: " . htmlspecialchars($e->getMessage(), ENT_QUOTES) . "'); history.back();</script>";
    exit;
}

/* ================== AUTORIZAÇÃO ================== */
$acessoPermitido   = false;
$idEmpresaSession  = $_SESSION['empresa_id'];
$tipoSession       = $_SESSION['tipo_empresa']; // principal | filial | unidade | franquia

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
} catch (Throwable $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png";
}

/* ================== TOKENS / HELPERS ================== */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function dbr(?string $dt)
{
    return $dt ? date('d/m/Y H:i', strtotime($dt)) : '—';
}

$mapStatus = [
    'aguardando'  => ['label' => 'Aguardando',   'class' => 'bg-label-secondary'],
    'enviado'     => ['label' => 'Enviado',      'class' => 'bg-label-warning'],
    'em_transito' => ['label' => 'Em trânsito',  'class' => 'bg-label-info'],
    'recebido'    => ['label' => 'Recebido',     'class' => 'bg-label-success'],
    'cancelado'   => ['label' => 'Cancelado',    'class' => 'bg-label-danger'],
];

/* =======================================================
   AJAX: Detalhes de itens (?ajax=itens&t=ID)
   ======================================================= */
if (($_GET['ajax'] ?? '') === 'itens') {
    header('Content-Type: application/json; charset=utf-8');
    $tId = (int)($_GET['t'] ?? 0);
    $out = ['ok' => false, 'itens' => [], 'obs' => '', 'erro' => null];

    try {
        if ($tId <= 0) throw new Exception('ID inválido.');

        // Checa se a transferência existe e é de franquia
        $st = $pdo->prepare("
      SELECT t.id, t.obs
        FROM transferencias_b2b t
        JOIN franquias f ON f.id = t.filial_id
       WHERE t.id = :id
       LIMIT 1
    ");
        $st->execute([':id' => $tId]);
        $head = $st->fetch(PDO::FETCH_ASSOC);
        if (!$head) throw new Exception('Transferência não encontrada (ou não é de franquia).');

        $si = $pdo->prepare("
      SELECT i.id, i.sku, i.nome, i.qtd
        FROM transferencias_itens i
       WHERE i.transferencia_id = :tid
       ORDER BY i.nome ASC
    ");
        $si->execute([':tid' => $tId]);
        $out['itens'] = $si->fetchAll(PDO::FETCH_ASSOC);
        $out['obs']   = (string)($head['obs'] ?? '');
        $out['ok']    = true;
    } catch (Throwable $e) {
        $out['erro'] = $e->getMessage();
    }

    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

/* =======================================================
   CONSULTA: TODAS AS TRANSFERÊNCIAS DE FRANQUIAS
   =======================================================

   - Se sessão = franquia → filtra por essa franquia
   - Caso contrário → traz TODAS as transferências de franquias (todas as situações)
*/
$params = [];
$where  = [];

if ($tipoSession === 'franquia') {
    // precisamos mapear o id interno da franquia para comparar com t.filial_id (inteiro)
    // assumindo que você guarda o código "franquia_X" no campo 'empresa_id' da tabela franquias
    $qIdFr = $pdo->prepare("SELECT id FROM franquias WHERE empresa_id = :eid LIMIT 1");
    $qIdFr->execute([':eid' => $idSelecionado]);
    $idFranquia = (int)($qIdFr->fetchColumn() ?: 0);
    // se não achar, ainda assim evita vazar dados:
    $where[] = "t.filial_id = :fid";
    $params[':fid'] = $idFranquia ?: -1;
}

// Filtros opcionais (se quiser usar via GET sem atrapalhar o “todas”)
$fStatus  = $_GET['status'] ?? '';   // aguardando | enviado | em_transito | recebido | cancelado
$fBusca   = trim($_GET['q'] ?? '');  // busca por código / franquia / obs
$fIni     = trim($_GET['de'] ?? '');
$fFim     = trim($_GET['ate'] ?? '');

if ($fStatus !== '' && in_array($fStatus, ['aguardando', 'enviado', 'em_transito', 'recebido', 'cancelado'], true)) {
    $where[] = "t.status = :st";
    $params[':st'] = $fStatus;
}
if ($fIni !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fIni)) {
    $where[] = "DATE(t.criado_em) >= :di";
    $params[':di'] = $fIni;
}
if ($fFim !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fFim)) {
    $where[] = "DATE(t.criado_em) <= :df";
    $params[':df'] = $fFim;
}
if ($fBusca !== '') {
    $where[] = "(t.codigo LIKE :q OR t.obs LIKE :q OR f.nome LIKE :q)";
    $params[':q'] = "%{$fBusca}%";
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// paginação
$perPage = 20;
$page    = max(1, (int)($_GET['p'] ?? 1));
$offset  = ($page - 1) * $perPage;

// count
$sqlCount = "
  SELECT COUNT(*)
    FROM transferencias_b2b t
    JOIN franquias f ON f.id = t.filial_id
  $whereSql
";
$stCount = $pdo->prepare($sqlCount);
foreach ($params as $k => $v) $stCount->bindValue($k, $v);
$stCount->execute();
$totalRows  = (int)$stCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

// listagem
$sql = "
  SELECT
    t.id,
    t.codigo,
    t.filial_id,
    f.nome AS franquia_nome,
    t.status,
    t.criado_em,
    t.enviado_em,
    t.recebido_em,   -- se sua tabela usa cancelado_em, inclua abaixo
    t.cancelado_em,
    t.obs,
    COUNT(i.id)              AS itens_total,
    COALESCE(SUM(i.qtd),0)   AS qtd_total
  FROM transferencias_b2b t
  JOIN franquias f ON f.id = t.filial_id
  LEFT JOIN transferencias_itens i ON i.transferencia_id = t.id
  $whereSql
  GROUP BY t.id, t.codigo, t.filial_id, f.nome, t.status, t.criado_em, t.enviado_em, t.recebido_em, t.cancelado_em, t.obs
  ORDER BY t.criado_em DESC
  LIMIT :lim OFFSET :off
";
$st = $pdo->prepare($sql);
foreach ($params as $k => $v) $st->bindValue($k, $v);
$st->bindValue(':lim', (int)$perPage, PDO::PARAM_INT);
$st->bindValue(':off', (int)$offset, PDO::PARAM_INT);
$st->execute();
$linhas = $st->fetchAll(PDO::FETCH_ASSOC);

/* ================== URL helper paginação ================== */
$qs = $_GET;
$qs['id'] = $idSelecionado;
$makeUrl = function ($p) use ($qs) {
    $qs['p'] = $p;
    return '?' . http_build_query($qs);
};
$range = 2;
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>ERP — Histórico de Transferências</title>
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

        .toolbar {
            gap: .5rem;
            flex-wrap: wrap
        }

        .toolbar .form-select,
        .toolbar .form-control {
            max-width: 220px
        }

        .badge-dot {
            display: inline-flex;
            align-items: center;
            gap: .4rem
        }

        .badge-dot::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            display: inline-block
        }
    </style>
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">

            <!-- ====== ASIDE (como o seu) ====== -->
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
                            <li class="menu-item"><a href="./transferenciasPendentes.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Transf. Pendentes</div>
                                </a></li>
                            <li class="menu-item active"><a href="./historicoTransferencias.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
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
            <!-- ====== /ASIDE ====== -->

            <div class="layout-page">
                <!-- Navbar -->
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)"><i class="bx bx-menu bx-sm"></i></a>
                    </div>
                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <div class="navbar-nav align-items-center">
                            <div class="nav-item d-flex align-items-center">
                                <i class="bx bx-search fs-4 lh-0"></i>
                                <input type="text" class="form-control border-0 shadow-none" placeholder="Search..." aria-label="Search..." />
                            </div>
                        </div>
                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="avatar avatar-online">
                                        <img src="<?= e($logoEmpresa) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online"><img src="<?= e($logoEmpresa) ?>" class="w-px-40 h-auto rounded-circle" /></div>
                                                </div>
                                                <div class="flex-grow-1"><span class="fw-semibold d-block"><?= e($nomeUsuario) ?></span><small class="text-muted"><?= e($tipoUsuario) ?></small></div>
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
                    <h4 class="fw-bold mb-0">
                        <span class="text-muted fw-light"><a href="#">Franquias</a>/</span>
                        Histórico de Transferências
                    </h4>
                    <h5 class="fw-bold mt-3 mb-3 custor-font">
                        <span class="text-muted fw-light">Produtos enviados para as Franquias — todas as situações</span>
                    </h5>

                    <!-- (Opcional) Barra de filtros simples -->
                    <form class="card mb-3" method="get" autocomplete="off">
                        <input type="hidden" name="id" value="<?= e($idSelecionado) ?>">
                        <div class="card-body toolbar d-flex">
                            <select class="form-select form-select-sm" name="status">
                                <option value="">Todos os status</option>
                                <?php foreach (['aguardando', 'enviado', 'em_transito', 'recebido', 'cancelado'] as $opt): ?>
                                    <option value="<?= $opt ?>" <?= ($opt === ($_GET['status'] ?? '')) ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $opt)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="date" name="de" class="form-control form-control-sm" value="<?= e($_GET['de'] ?? '') ?>" placeholder="De">
                            <input type="date" name="ate" class="form-control form-control-sm" value="<?= e($_GET['ate'] ?? '') ?>" placeholder="Até">
                            <input type="text" name="q" class="form-control form-control-sm" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Código, franquia ou obs…">
                            <button class="btn btn-sm btn-primary" type="submit"><i class="bx bx-filter-alt me-1"></i> Filtrar</button>
                            <a class="btn btn-sm btn-outline-secondary" href="?id=<?= urlencode($idSelecionado) ?>"><i class="bx bx-eraser me-1"></i> Limpar</a>
                            <div class="ms-auto small text-muted">
                                Registros: <strong><?= (int)$totalRows ?></strong> · Página <strong><?= (int)$page ?></strong> de <strong><?= (int)$totalPages ?></strong>
                            </div>
                        </div>
                    </form>

                    <div class="card">
                        <h5 class="card-header">Histórico de Transferências</h5>
                        <div class="table-responsive text-nowrap">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Franquia</th>
                                        <th>Itens</th>
                                        <th>Qtd</th>
                                        <th>Criado</th>
                                        <th>Envio</th>
                                        <th>Recebido/Cancelado</th>
                                        <th>Status Final</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="table-border-bottom-0">
                                    <?php if (!$linhas): ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-4">Nenhuma transferência encontrada.</td>
                                        </tr>
                                        <?php else:
                                        foreach ($linhas as $r):
                                            $badge = $mapStatus[$r['status']] ?? ['label' => $r['status'], 'class' => 'bg-label-secondary'];
                                            $finalDate = ($r['status'] === 'recebido') ? $r['recebido_em'] : (($r['status'] === 'cancelado') ? ($r['cancelado_em'] ?? null) : null);
                                        ?>
                                            <tr>
                                                <td><strong><?= e($r['codigo']) ?></strong></td>
                                                <td><?= e($r['franquia_nome']) ?></td>
                                                <td><?= (int)$r['itens_total'] ?></td>
                                                <td><?= (int)$r['qtd_total'] ?></td>
                                                <td><?= dbr($r['criado_em']) ?></td>
                                                <td><?= dbr($r['enviado_em']) ?></td>
                                                <td><?= dbr($finalDate) ?></td>
                                                <td><span class="badge <?= e($badge['class']) ?> status-badge"><?= e($badge['label']) ?></span></td>
                                                <td class="text-end">
                                                    <button
                                                        class="btn btn-sm btn-outline-secondary"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#modalHistDetalhes"
                                                        data-id="<?= (int)$r['id'] ?>"
                                                        data-codigo="<?= e($r['codigo']) ?>"
                                                        data-filial="<?= e($r['franquia_nome']) ?>"
                                                        data-status="<?= e($badge['label']) ?>"
                                                        data-criado="<?= dbr($r['criado_em']) ?>"
                                                        data-enviado="<?= dbr($r['enviado_em']) ?>"
                                                        data-final="<?= dbr($finalDate) ?>"
                                                        data-itens="<?= (int)$r['itens_total'] ?>">Detalhes</button>
                                                </td>
                                            </tr>
                                    <?php endforeach;
                                    endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginação -->
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
                    </div>

                    <!-- Modal Detalhes (Histórico) -->
                    <div class="modal fade" id="modalHistDetalhes" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Detalhes da Transferência</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row g-3 mb-2">
                                        <div class="col-md-3">
                                            <p><strong>Código:</strong> <span id="hist-codigo">-</span></p>
                                        </div>
                                        <div class="col-md-3">
                                            <p><strong>Franquia:</strong> <span id="hist-filial">-</span></p>
                                        </div>
                                        <div class="col-md-3">
                                            <p><strong>Status:</strong> <span id="hist-status">-</span></p>
                                        </div>
                                        <div class="col-md-3">
                                            <p><strong>Total Itens:</strong> <span id="hist-itens">-</span></p>
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>SKU</th>
                                                    <th>Produto</th>
                                                    <th>Qtd</th>
                                                </tr>
                                            </thead>
                                            <tbody id="hist-itens-body">
                                                <tr>
                                                    <td colspan="3" class="text-muted">Carregando...</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="mt-2">
                                        <strong>Observações:</strong>
                                        <div id="hist-obs" class="text-muted">—</div>
                                    </div>

                                    <div class="mt-3">
                                        <strong>Linha do tempo:</strong>
                                        <ul class="mb-0">
                                            <li><span class="text-muted">Criado:</span> <span id="hist-criado">—</span></li>
                                            <li><span class="text-muted">Enviado:</span> <span id="hist-enviado">—</span></li>
                                            <li><span class="text-muted">Recebido/Cancelado:</span> <span id="hist-final">—</span></li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                                </div>
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
        // Abre modal e carrega itens
        const modal = document.getElementById('modalHistDetalhes');
        modal?.addEventListener('show.bs.modal', function(e) {
            const btn = e.relatedTarget;
            if (!btn) return;

            const id = btn.getAttribute('data-id');
            const codigo = btn.getAttribute('data-codigo');
            const filial = btn.getAttribute('data-filial');
            const status = btn.getAttribute('data-status');
            const criado = btn.getAttribute('data-criado');
            const enviado = btn.getAttribute('data-enviado');
            const finalDt = btn.getAttribute('data-final');
            const itens = btn.getAttribute('data-itens');

            document.getElementById('hist-codigo').textContent = codigo || '-';
            document.getElementById('hist-filial').textContent = filial || '-';
            document.getElementById('hist-status').textContent = status || '-';
            document.getElementById('hist-itens').textContent = itens || '0';
            document.getElementById('hist-criado').textContent = criado || '—';
            document.getElementById('hist-enviado').textContent = enviado || '—';
            document.getElementById('hist-final').textContent = finalDt || '—';

            const tbody = document.getElementById('hist-itens-body');
            tbody.innerHTML = '<tr><td colspan="3" class="text-muted">Carregando...</td></tr>';
            document.getElementById('hist-obs').textContent = '—';

            const url = new URL(window.location.href);
            url.searchParams.set('ajax', 'itens');
            url.searchParams.set('t', id);

            fetch(url.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(r => r.ok ? r.json() : Promise.reject())
                .then(data => {
                    if (!data || !Array.isArray(data.itens) || !data.ok) {
                        tbody.innerHTML = '<tr><td colspan="3" class="text-danger">Falha ao carregar itens.</td></tr>';
                        return;
                    }
                    if (!data.itens.length) {
                        tbody.innerHTML = '<tr><td colspan="3" class="text-muted">Sem itens.</td></tr>';
                    } else {
                        tbody.innerHTML = data.itens.map(i => `
              <tr>
                <td>${(i.sku ?? '').toString().replaceAll('<','&lt;')}</td>
                <td>${(i.nome ?? '').toString().replaceAll('<','&lt;')}</td>
                <td>${parseInt(i.qtd ?? 0)}</td>
              </tr>
            `).join('');
                    }
                    document.getElementById('hist-obs').textContent = (data.obs ?? '—') || '—';
                })
                .catch(() => {
                    tbody.innerHTML = '<tr><td colspan="3" class="text-danger">Erro ao carregar itens.</td></tr>';
                });
        });
    </script>
</body>

</html>
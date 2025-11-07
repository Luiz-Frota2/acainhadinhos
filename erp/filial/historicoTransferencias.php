<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
date_default_timezone_set('America/Manaus');

/* ==========================================================
   Helpers
   ========================================================== */
function json_out(array $payload, int $statusCode = 200)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
function dtBr(?string $dt)
{
    if (!$dt) return '—';
    $t = strtotime($dt);
    if (!$t) return '—';
    return date('d/m/Y H:i', $t);
}
function e(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

/* ==========================================================
   Flags AJAX
   ========================================================== */
$IS_AJAX_DET = (
    (isset($_GET['ajax'])  && $_GET['ajax']  === 'detalhes') ||
    (isset($_POST['ajax']) && $_POST['ajax'] === 'detalhes')
);

/* ==========================================================
   Sessão / parâmetros
   ========================================================== */
$idSelecionado = $_GET['id'] ?? '';
if (!$idSelecionado) {
    if ($IS_AJAX_DET) json_out(['ok' => false, 'erro' => 'Identificador ausente (id).'], 400);
    header("Location: .././login.php");
    exit;
}

if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id'])
) {
    if ($IS_AJAX_DET) json_out(['ok' => false, 'erro' => 'Sessão expirada. Faça login novamente.'], 401);
    header("Location: .././login.php?id=" . urlencode($idSelecionado));
    exit;
}

/* ==========================================================
   Conexão
   ========================================================== */
require '../../assets/php/conexao.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if ($IS_AJAX_DET) json_out(['ok' => false, 'erro' => 'Conexão indisponível.'], 500);
    http_response_code(500);
    echo "Erro: conexão indisponível.";
    exit;
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ==========================================================
   Usuário logado
   ========================================================== */
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id  = (int)$_SESSION['usuario_id'];
try {
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($u) {
        $nomeUsuario = $u['usuario'] ?? 'Usuário';
        $tipoUsuario = ucfirst((string)($u['nivel'] ?? 'Comum'));
    } else {
        if ($IS_AJAX_DET) json_out(['ok' => false, 'erro' => 'Usuário não encontrado.'], 403);
        echo "<script>alert('Usuário não encontrado.'); window.location.href = '.././login.php?id=" . e($idSelecionado) . "';</script>";
        exit;
    }
} catch (PDOException $e) {
    if ($IS_AJAX_DET) json_out(['ok' => false, 'erro' => 'Erro ao carregar usuário: ' . $e->getMessage()], 500);
    echo "<script>alert('Erro ao carregar usuário.'); history.back();</script>";
    exit;
}

/* ==========================================================
   Validação de acesso
   ========================================================== */
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
    if ($IS_AJAX_DET) json_out(['ok' => false, 'erro' => 'Acesso negado.'], 403);
    echo "<script>alert('Acesso negado!'); window.location.href = '.././login.php?id=" . e($idSelecionado) . "';</script>";
    exit;
}

/* ==========================================================
   Logo da empresa
   ========================================================== */
try {
    $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1");
    $stmt->execute([':id' => $idSelecionado]);
    $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);
    $logoEmpresa = (!empty($empresaSobre) && !empty($empresaSobre['imagem']))
        ? "../../assets/img/empresa/" . $empresaSobre['imagem']
        : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png";
}

/* ==========================================================
   ENDPOINT AJAX — Autocomplete
   aceita: ?ajax=autocomplete&q=...
   ========================================================== */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'autocomplete') {
    header('Content-Type: application/json; charset=UTF-8');
    $term = trim($_GET['q'] ?? '');
    $out  = [];

    if (mb_strlen($term) >= 2) {
        // id_solicitante
        $s1 = $pdo->prepare("
            SELECT DISTINCT s.id_solicitante AS val, 'Solicitante' AS tipo
            FROM solicitacoes_b2b s
            JOIN unidades u 
              ON u.id = CAST(REPLACE(s.id_solicitante,'unidade_','') AS UNSIGNED)
             AND u.tipo = 'Filial'
             AND u.empresa_id = :matriz
            WHERE s.id_matriz = :matriz
              AND s.id_solicitante LIKE :q
            ORDER BY s.id_solicitante
            LIMIT 10
        ");
        $s1->execute([':matriz' => $idSelecionado, ':q' => "%$term%"]);
        foreach ($s1 as $r) $out[] = ['label' => $r['val'], 'value' => $r['val'], 'tipo' => $r['tipo']];

        // SKU
        $s2 = $pdo->prepare("
            SELECT DISTINCT it.codigo_produto AS val, 'SKU' AS tipo
            FROM solicitacoes_b2b s
            JOIN solicitacoes_b2b_itens it ON it.solicitacao_id = s.id
            JOIN unidades u 
              ON u.id = CAST(REPLACE(s.id_solicitante,'unidade_','') AS UNSIGNED)
             AND u.tipo = 'Filial'
             AND u.empresa_id = :matriz
            WHERE s.id_matriz = :matriz
              AND it.codigo_produto LIKE :q
            ORDER BY it.codigo_produto
            LIMIT 10
        ");
        $s2->execute([':matriz' => $idSelecionado, ':q' => "%$term%"]);
        foreach ($s2 as $r) $out[] = ['label' => $r['val'], 'value' => $r['val'], 'tipo' => $r['tipo']];

        // Nome do produto
        $s3 = $pdo->prepare("
            SELECT DISTINCT it.nome_produto AS val, 'Produto' AS tipo
            FROM solicitacoes_b2b s
            JOIN solicitacoes_b2b_itens it ON it.solicitacao_id = s.id
            JOIN unidades u 
              ON u.id = CAST(REPLACE(s.id_solicitante,'unidade_','') AS UNSIGNED)
             AND u.tipo = 'Filial'
             AND u.empresa_id = :matriz
            WHERE s.id_matriz = :matriz
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

/* ==========================================================
   ENDPOINT AJAX — Detalhes (sempre JSON)
   Aceita: ?ajax=detalhes&solicitacao_id=ID
   ========================================================== */
if ($IS_AJAX_DET) {
    try {
        $sid = (int)($_GET['solicitacao_id'] ?? $_POST['solicitacao_id'] ?? 0);
        if ($sid <= 0) json_out(['ok' => false, 'erro' => 'solicitacao_id inválido.'], 400);

        $cab = $pdo->prepare("
            SELECT 
                s.id, s.id_matriz, s.id_solicitante, s.status, s.observacao,
                s.created_at, s.aprovada_em, s.enviada_em, s.entregue_em,
                u.nome AS filial_nome
            FROM solicitacoes_b2b s
            JOIN unidades u
              ON u.id = CAST(REPLACE(s.id_solicitante,'unidade_','') AS UNSIGNED)
             AND u.tipo = 'Filial'
             AND u.empresa_id = :matriz
            WHERE s.id = :sid
              AND s.id_matriz = :matriz
            LIMIT 1
        ");
        $cab->execute([':sid' => $sid, ':matriz' => $idSelecionado]);
        $cabecalho = $cab->fetch(PDO::FETCH_ASSOC);
        if (!$cabecalho) json_out(['ok' => false, 'erro' => 'Registro não encontrado.'], 404);

        $it = $pdo->prepare("
            SELECT 
                COALESCE(i.codigo_produto,'') AS codigo_produto,
                COALESCE(i.nome_produto,'')   AS nome_produto,
                COALESCE(i.quantidade,0)      AS quantidade,
                COALESCE(i.unidade,'UN')      AS unidade
            FROM solicitacoes_b2b_itens i
            WHERE i.solicitacao_id = :sid
            ORDER BY i.id ASC
        ");
        $it->execute([':sid' => $sid]);
        $itens = $it->fetchAll(PDO::FETCH_ASSOC);

        json_out(['ok' => true, 'cabecalho' => $cabecalho, 'itens' => $itens]);
    } catch (Throwable $e) {
        json_out(['ok' => false, 'erro' => $e->getMessage()], 500);
    }
}

/* ==========================================================
   FILTROS
   - status (agora só 'entregue' ou 'cancelada')
   - de/ate (data criada)
   - q (id_solicitante, SKU, nome_produto)
   ========================================================== */
$status = $_GET['status'] ?? '';
$de     = trim($_GET['de'] ?? '');
$ate    = trim($_GET['ate'] ?? '');
$q      = trim($_GET['q'] ?? '');

/* WHERE base: somente FILIAIS desta matriz */
$where  = [];
$params = [':empresa_id' => $idSelecionado];

$where[] = "u.tipo = 'Filial'";
$where[] = "u.empresa_id = :empresa_id";
$where[] = "s.id_matriz = :empresa_id";

/* Status: restringido a 'entregue' e 'cancelada' */
$validStatus = ['entregue', 'cancelada'];
if ($status !== '' && in_array($status, $validStatus, true)) {
    $where[] = "s.status = :status";
    $params[':status'] = $status;
} else {
    // padrão: mostrar os dois
    $where[] = "s.status IN ('entregue','cancelada')";
}

/* Datas */
if ($de !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $de)) {
    $where[] = "DATE(s.created_at) >= :de";
    $params[':de'] = $de;
}
if ($ate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) {
    $where[] = "DATE(s.created_at) <= :ate";
    $params[':ate'] = $ate;
}

/* Busca (id_solicitante ou itens) */
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

/* ==========================================================
   LISTAGEM (aplicando filtros)
   ========================================================== */
$historico = [];
try {
    $sql = "
        SELECT
            s.id,
            s.id_solicitante,
            u.nome AS filial_nome,
            s.created_at,
            s.enviada_em,
            s.entregue_em,
            s.status,
            COUNT(i.id)                   AS itens,
            COALESCE(SUM(i.quantidade),0) AS qtd_total
        FROM solicitacoes_b2b s
        JOIN unidades u
          ON u.id = CAST(REPLACE(s.id_solicitante,'unidade_','') AS UNSIGNED)
        LEFT JOIN solicitacoes_b2b_itens i
          ON i.solicitacao_id = s.id
        WHERE {$whereSql}
        GROUP BY s.id, s.id_solicitante, u.nome, s.created_at, s.enviada_em, s.entregue_em, s.status
        ORDER BY s.entregue_em DESC, s.enviada_em DESC, s.created_at DESC, s.id DESC
    ";
    $st = $pdo->prepare($sql);
    foreach ($params as $k => $v) $st->bindValue($k, $v);
    $st->execute();
    $historico = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $historico = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>ERP - Filial</title>
    <meta name="description" content="" />
    <link rel="icon" type="image/x-icon" href="<?= e($logoEmpresa) ?>" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />
    <script src="../../assets/vendor/js/helpers.js"></script>
    <script src="../../assets/js/config.js"></script>
    <style>
        .table thead th {
            white-space: nowrap;
        }

        .status-badge {
            font-size: .78rem;
        }

        .table-responsive {
            overflow: auto;
        }

        .filter-col {
            min-width: 150px;
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
    </style>
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- Menu -->
            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="app-brand-link">
                        <span class="app-brand-text demo menu-text fw-bolder ms-2">Açaínhadinhos</span>
                    </a>
                    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
                        <i class="bx bx-chevron-left bx-sm align-middle"></i>
                    </a>
                </div>
                <div class="menu-inner-shadow"></div>
                <ul class="menu-inner py-1">
                    <li class="menu-item">
                        <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Dashboard</div>
                        </a>
                    </li>

                    <li class="menu-header small text-uppercase">
                        <span class="menu-header-text">Administração Filiais</span>
                    </li>

                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-building"></i>
                            <div data-i18n="Adicionar">Filiais</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./filialAdicionada.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Filiais">Adicionadas</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div data-i18n="B2B">B2B - Matriz</div>
                        </a>
                        <ul class="menu-sub active">
                            <li class="menu-item"><a href="./contasFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
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

                    <!-- Relatórios -->
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-bar-chart-alt-2"></i>
                            <div data-i18n="Relatorios">Relatórios</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./VendasFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Vendas">Vendas por Filial</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./MaisVendidos.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="MaisVendidos">Mais Vendidos</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./vendasFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Pedidos">Vendas por Período</div>
                                </a>
                            </li>

                        </ul>
                    </li>

                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
                    <li class="menu-item"><a href="../rh/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-group"></i>
                            <div>RH</div>
                        </a></li>
                    <li class="menu-item"><a href="../financas/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-dollar"></i>
                            <div>Finanças</div>
                        </a></li>
                    <li class="menu-item"><a href="../pdv/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-desktop"></i>
                            <div>PDV</div>
                        </a></li>
                    <li class="menu-item"><a href="../empresa/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div>Empresa</div>
                        </a></li>
                    <li class="menu-item"><a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-box"></i>
                            <div>Estoque</div>
                        </a></li>
                    <li class="menu-item"><a href="../franquia/index.php?id=principal_1" class="menu-link"><i class="menu-icon tf-icons bx bx-store"></i>
                            <div>Franquias</div>
                        </a></li>
                    <li class="menu-item"><a href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-group"></i>
                            <div>Usuários</div>
                        </a></li>
                    <li class="menu-item mb-5"><a href="https://wa.me/92991515710" target="_blank" class="menu-link"><i class="menu-icon tf-icons bx bx-support"></i>
                            <div>Suporte</div>
                        </a></li>
                </ul>
            </aside>
            <!-- / Menu -->

            <div class="layout-page">
                <!-- Navbar -->
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)"><i class="bx bx-menu bx-sm"></i></a>
                    </div>
                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <div class="navbar-nav align-items-center">
                            <div class="nav-item d-flex align-items-center"></div>
                        </div>
                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="avatar avatar-online"><img src="<?= e($logoEmpresa) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" /></div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownUser">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online"><img src="<?= e($logoEmpresa) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" /></div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <span class="fw-semibold d-block"><?= e($nomeUsuario) ?></span>
                                                    <small class="text-muted"><?= e($tipoUsuario) ?></small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
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
                <!-- / Navbar -->

                <!-- Content -->
                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold mb-0">
                        <span class="text-muted fw-light"><a href="#">Filial</a>/</span>
                        Histórico de Transferências
                    </h4>
                    <h5 class="fw-bold mt-3 mb-3 custor-font">
                        <span class="text-muted fw-light">Produtos enviados para as Filiais — com filtros (Status, Período e Busca)</span>
                    </h5>

                    <!-- ===== Filtros ===== -->
                    <form class="card mb-3" method="get" id="filtroForm" autocomplete="off">
                        <input type="hidden" name="id" value="<?= e($idSelecionado) ?>">
                        <div class="card-body">
                            <div class="row g-3 align-items-end">
                                <div class="col-12 col-md-auto filter-col">
                                    <label class="form-label mb-1">Status</label>
                                    <select class="form-select form-select-sm" name="status">
                                        <option value="">Entregue + Cancelada (padrão)</option>
                                        <option value="entregue" <?= $status === 'entregue'  ? 'selected' : '' ?>>Entregue</option>
                                        <option value="cancelada" <?= $status === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                                    </select>
                                </div>

                                <div class="col-12 col-md-auto filter-col">
                                    <label class="form-label mb-1">De</label>
                                    <input type="date" class="form-control form-control-sm" name="de" value="<?= e($de) ?>">
                                </div>

                                <div class="col-12 col-md-auto filter-col">
                                    <label class="form-label mb-1">Até</label>
                                    <input type="date" class="form-control form-control-sm" name="ate" value="<?= e($ate) ?>">
                                </div>

                                <div class="col-12 col-md flex-grow-1 filter-col">
                                    <label class="form-label mb-1">Buscar</label>
                                    <div class="autocomplete">
                                        <input type="text" class="form-control form-control-sm" id="qInput" name="q" placeholder="Solicitante (ex.: unidade_3), SKU ou Produto…" value="<?= e($q) ?>" autocomplete="off">
                                        <div class="autocomplete-list d-none" id="qList"></div>
                                    </div>
                                </div>

                                <div class="col-12 col-md-auto d-flex gap-2 filter-col">
                                    <button class="btn btn-sm btn-primary" type="submit"><i class="bx bx-filter-alt me-1"></i> Filtrar</button>
                                    <a class="btn btn-sm btn-outline-secondary" href="?id=<?= urlencode($idSelecionado) ?>"><i class="bx bx-eraser me-1"></i> Limpar</a>
                                </div>
                            </div>
                            <div class="small text-muted mt-2">
                                Resultados: <strong><?= count($historico) ?></strong> registros
                            </div>
                        </div>
                    </form>

                    <!-- Histórico -->
                    <div class="card">
                        <h5 class="card-header">Histórico de Transferências</h5>
                        <div class="table-responsive text-nowrap">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Filial</th>
                                        <th>Itens</th>
                                        <th>Qtd</th>
                                        <th>Criado</th>
                                        <th>Envio</th>
                                        <th>Entregue/Cancelado</th>
                                        <th>Status Final</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="table-border-bottom-0">
                                    <?php if (empty($historico)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-4">Nenhum registro encontrado.</td>
                                        </tr>
                                        <?php else: foreach ($historico as $row):
                                            $statusRow = $row['status'];
                                            $badge  = ($statusRow === 'entregue') ? 'bg-label-success'
                                                : (($statusRow === 'cancelada') ? 'bg-label-danger' : 'bg-label-secondary');
                                        ?>
                                            <tr>
                                                <td><strong><?= (int)$row['id'] ?></strong></td>
                                                <td><?= e($row['filial_nome'] ?? '-') ?></td>
                                                <td><?= (int)($row['itens'] ?? 0) ?></td>
                                                <td><?= (int)($row['qtd_total'] ?? 0) ?></td>
                                                <td><?= dtBr($row['created_at']) ?></td>
                                                <td><?= dtBr($row['enviada_em']) ?></td>
                                                <td><?= dtBr($row['entregue_em']) ?></td>
                                                <td><span class="badge <?= $badge ?> status-badge"><?= e(ucfirst($statusRow)) ?></span></td>
                                                <td class="text-end">
                                                    <button
                                                        class="btn btn-sm btn-outline-secondary"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#modalHistDetalhes"
                                                        data-id="<?= (int)$row['id'] ?>"
                                                        data-codigo="TR-<?= (int)$row['id'] ?>"
                                                        data-filial="<?= e($row['filial_nome'] ?? '-') ?>"
                                                        data-status="<?= e(ucfirst($statusRow)) ?>">
                                                        Detalhes
                                                    </button>
                                                </td>
                                            </tr>
                                    <?php endforeach;
                                    endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Modal Detalhes -->
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
                                            <p><strong>Código:</strong> <span id="hist-codigo">—</span></p>
                                        </div>
                                        <div class="col-md-3">
                                            <p><strong>Filial:</strong> <span id="hist-filial">—</span></p>
                                        </div>
                                        <div class="col-md-3">
                                            <p><strong>Status:</strong> <span id="hist-status">—</span></p>
                                        </div>
                                        <div class="col-md-3">
                                            <p><strong>Total Itens:</strong> <span id="hist-itens">—</span></p>
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Código</th>
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
                                            <li><span class="text-muted">Entregue/Cancelado:</span> <span id="hist-final">—</span></li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- / Content -->

                <footer class="content-footer footer bg-footer-theme text-center">
                    <div class="container-xxl d-flex py-2 flex-md-row flex-column justify-content-center">
                        <div class="mb-2 mb-md-0">
                            &copy; <script>
                                document.write(new Date().getFullYear());
                            </script>, <strong>Açaínhadinhos</strong>. Todos os direitos reservados.
                            Desenvolvido por <strong>CodeGeek</strong>.
                        </div>
                    </div>
                </footer>

                <div class="content-backdrop fade"></div>
            </div>
        </div>
    </div>

    <!-- Core JS -->
    <script src="../../js/saudacao.js"></script>
    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../assets/vendor/js/menu.js"></script>
    <script src="../../assets/vendor/libs/apex-charts/apexcharts.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/dashboards-analytics.js"></script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>

    <!-- JS Modal: fetch seguro (mantém ?id=empresa e usa solicitacao_id) -->
    <script>
        (function() {
            const modalEl = document.getElementById('modalHistDetalhes');
            if (!modalEl) return;

            modalEl.addEventListener('show.bs.modal', function(event) {
                const btn = event.relatedTarget;
                if (!btn) return;

                const idSol = btn.getAttribute('data-id');
                const cod = btn.getAttribute('data-codigo') || '—';
                const fil = btn.getAttribute('data-filial') || '—';
                const sts = btn.getAttribute('data-status') || '—';

                document.getElementById('hist-codigo').textContent = cod;
                document.getElementById('hist-filial').textContent = fil;
                document.getElementById('hist-status').textContent = sts;

                const tbody = document.getElementById('hist-itens-body');
                tbody.innerHTML = '<tr><td colspan="3" class="text-muted">Carregando...</td></tr>';

                const url = new URL(window.location.href);
                url.searchParams.set('ajax', 'detalhes');
                url.searchParams.set('solicitacao_id', idSol);

                fetch(url.toString(), {
                        credentials: 'same-origin'
                    })
                    .then(async (r) => {
                        const ct = r.headers.get('content-type') || '';
                        const text = await r.text();
                        if (!ct.includes('application/json')) {
                            throw new Error((text || '').trim().slice(0, 500) || 'Resposta não-JSON recebida.');
                        }
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            throw new Error('JSON inválido: ' + (text.trim().slice(0, 300)));
                        }
                    })
                    .then(data => {
                        if (!data.ok) throw new Error(data.erro || 'Falha ao carregar itens');

                        const cab = data.cabecalho || {};
                        const itens = data.itens || [];

                        document.getElementById('hist-obs').textContent = cab.observacao || '—';
                        document.getElementById('hist-criado').textContent = fmtBr(cab.created_at);
                        document.getElementById('hist-enviado').textContent = fmtBr(cab.enviada_em);
                        document.getElementById('hist-final').textContent = fmtBr(cab.entregue_em);
                        document.getElementById('hist-itens').textContent = String(itens.length || 0);

                        if (!itens.length) {
                            tbody.innerHTML = '<tr><td colspan="3" class="text-muted">Sem itens.</td></tr>';
                        } else {
                            tbody.innerHTML = itens.map(it => {
                                const cod = it.codigo_produto || '—';
                                const nm = it.nome_produto || '—';
                                const qt = it.quantidade ?? 0;
                                return `<tr><td>${escapeHtml(cod)}</td><td>${escapeHtml(nm)}</td><td>${qt}</td></tr>`;
                            }).join('');
                        }
                    })
                    .catch(err => {
                        tbody.innerHTML = `<tr><td colspan="3" class="text-danger">Erro: ${escapeHtml(err.message)}</td></tr>`;
                    });
            });

            function fmtBr(iso) {
                if (!iso) return '—';
                const d = new Date(String(iso).replace(' ', 'T'));
                if (isNaN(d)) return iso;
                const dd = String(d.getDate()).padStart(2, '0');
                const mm = String(d.getMonth() + 1).padStart(2, '0');
                const yyyy = d.getFullYear();
                const hh = String(d.getHours()).padStart(2, '0');
                const ii = String(d.getMinutes()).padStart(2, '0');
                return `${dd}/${mm}/${yyyy} ${hh}:${ii}`;
            }

            function escapeHtml(s) {
                return String(s).replace(/[&<>"']/g, m => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                } [m]));
            }
        })();
    </script>

    <!-- Autocomplete -->
    <script>
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

            function render(data) {
                if (!data || !data.length) {
                    closeList();
                    return;
                }
                items = data.slice(0, 15);
                list.innerHTML = items.map((it, i) => `
      <div class="autocomplete-item" data-i="${i}">
        <span>${escapeHtml(it.label)}</span>
        <span class="autocomplete-tag">${escapeHtml(it.tipo)}</span>
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
                    .then(r => r.json())
                    .then(render)
                    .catch(() => {});
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

            function escapeHtml(s) {
                return String(s || '').replace(/[&<>"']/g, m => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                } [m]));
            }
        })();
    </script>
</body>

</html>
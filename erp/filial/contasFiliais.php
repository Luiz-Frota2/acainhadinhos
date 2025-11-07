<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
date_default_timezone_set('America/Manaus');

/* ================= Helpers ================= */
function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
function moneyBr($v)
{
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}
function json_out(array $payload, int $code = 200)
{
    while (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ================= Sessão / acesso ================= */
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

/* ================= Conexão ================= */
require '../../assets/php/conexao.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ================= Usuário ================= */
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id  = (int)$_SESSION['usuario_id'];

try {
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    if ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $nomeUsuario = $u['usuario'] ?? 'Usuário';
        $tipoUsuario = ucfirst((string)($u['nivel'] ?? 'Comum'));
    } else {
        echo "<script>alert('Usuário não encontrado.'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar usuário: " . e($e->getMessage()) . "'); history.back();</script>";
    exit;
}

/* ================= Acesso por tipo de empresa ================= */
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
    echo "<script>
      alert('Acesso negado!');
      window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';
    </script>";
    exit;
}

/* ================= Logo empresa ================= */
try {
    $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1");
    $stmt->bindParam(':id_selecionado', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);
    $logoEmpresa = (!empty($empresaSobre) && !empty($empresaSobre['imagem']))
        ? "../../assets/img/empresa/" . $empresaSobre['imagem']
        : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png";
}

/* ==========================================================
   DOWNLOAD do comprovante (stream local) ?id=...&download=ID
   ========================================================== */
if (isset($_GET['download'])) {
    $idPay = (int)$_GET['download'];

    try {
        $q = $pdo->prepare("SELECT comprovante_url FROM solicitacoes_pagamento WHERE id = :id LIMIT 1");
        $q->execute([':id' => $idPay]);
        $row = $q->fetch(PDO::FETCH_ASSOC);

        if (!$row || empty($row['comprovante_url'])) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=UTF-8');
            echo "Comprovante não encontrado para esta solicitação.";
            exit;
        }

        $raw = trim((string)$row['comprovante_url']);
        $raw = str_replace("\\", "/", $raw);
        $raw = ltrim($raw, "./");

        if (strpos($raw, 'public/pagamentos/') === 0) {
            $raw = substr($raw, strlen('public/pagamentos/'));
        } elseif (strpos($raw, 'pagamentos/') === 0) {
            $raw = substr($raw, strlen('pagamentos/'));
        }

        $possiveisBases = [
            '/home/u922223647/domains/acainhadinhos.com.br/files/public_html/public/pagamentos',
            '/home/u922223647/domains/acainhadinhos.com.br/public_html/public/pagamentos',
            realpath(__DIR__ . '/../../pagamentos'),
            realpath(__DIR__ . '/../../../public/pagamentos')
        ];

        $baseLocal = null;
        foreach ($possiveisBases as $b) {
            if ($b && is_dir($b)) {
                $baseLocal = $b;
                break;
            }
        }
        if (!$baseLocal) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8');
            echo "Pasta local de comprovantes não encontrada.";
            exit;
        }

        $segments = array_filter(explode('/', $raw), 'strlen');
        foreach ($segments as &$seg) {
            $seg = str_replace(['..', "\0"], '', $seg);
        }
        unset($seg);

        $nomeArquivo  = end($segments) ?: 'comprovante.pdf';
        $caminhoLocal = $baseLocal . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);

        if (!is_file($caminhoLocal) || !is_readable($caminhoLocal)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=UTF-8');
            echo "Arquivo não encontrado no servidor: " . e($caminhoLocal);
            exit;
        }

        if (ob_get_level()) @ob_end_clean();
        $filesize = filesize($caminhoLocal);

        header('Content-Type: application/pdf');
        if ($filesize !== false) header('Content-Length: ' . $filesize);
        header('Content-Disposition: attachment; filename="' . basename($nomeArquivo) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-transform, no-store, must-revalidate, max-age=0');
        header('Pragma: public');

        $fp = fopen($caminhoLocal, 'rb');
        if ($fp) {
            while (!feof($fp)) {
                echo fread($fp, 8192);
                flush();
            }
            fclose($fp);
        } else {
            readfile($caminhoLocal);
        }
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Erro ao processar o download do comprovante.";
        exit;
    }
}

/* ==========================================================
   ROTAS AJAX (aprovar/recusar e detalhes)
   ========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=UTF-8');

    $action = $_POST['action'];

    if ($action === 'update_status') {
        $idPay     = (int)($_POST['id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';
        if (!in_array($newStatus, ['aprovado', 'reprovado'], true)) {
            echo json_encode(['ok' => false, 'msg' => 'Status inválido']);
            exit;
        }
        try {
            $up = $pdo->prepare("
                UPDATE solicitacoes_pagamento
                   SET status = :st
                 WHERE id = :id
                   AND status = 'pendente'
                 LIMIT 1
            ");
            $up->execute([':st' => $newStatus, ':id' => $idPay]);
            echo json_encode(['ok' => $up->rowCount() > 0]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'msg' => 'Erro DB: ' . $e->getMessage()]);
            exit;
        }
    }

    if ($action === 'get_details') {
        $idPay = (int)($_POST['id'] ?? 0);
        try {
            $sql = "
                SELECT 
                    sp.*,
                    COALESCE(sp.comprovante_url, '') AS documento,
                    u.id   AS unidade_id,
                    u.nome AS unidade_nome,
                    u.tipo AS unidade_tipo
                FROM solicitacoes_pagamento sp
                JOIN unidades u 
                  ON u.id = CAST(SUBSTRING_INDEX(sp.id_solicitante, '_', -1) AS UNSIGNED)
                WHERE sp.id = :id
                LIMIT 1
            ";
            $st = $pdo->prepare($sql);
            $st->execute([':id' => $idPay]);
            if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                echo json_encode(['ok' => true, 'data' => $row]);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Solicitação não encontrada.']);
            }
            exit;
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'msg' => 'Erro DB: ' . $e->getMessage()]);
            exit;
        }
    }

    echo json_encode(['ok' => false, 'msg' => 'Ação inválida']);
    exit;
}

/* ==========================================================
   AUTOCOMPLETE (id_solicitante / descricao)
   ========================================================== */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'autocomplete') {
    $term = trim($_GET['q'] ?? '');
    $out  = [];
    if (mb_strlen($term) >= 2) {
        // id_solicitante
        $s1 = $pdo->prepare("
            SELECT DISTINCT sp.id_solicitante AS val, 'Solicitante' AS tipo
            FROM solicitacoes_pagamento sp
            JOIN unidades u
              ON u.id = CAST(SUBSTRING_INDEX(sp.id_solicitante,'_',-1) AS UNSIGNED)
             AND u.tipo = 'Filial'
             AND u.empresa_id = :matriz
            WHERE sp.id_matriz = :matriz
              AND sp.id_solicitante LIKE :q
            ORDER BY sp.id_solicitante
            LIMIT 10
        ");
        $s1->execute([':matriz' => $idSelecionado, ':q' => "%{$term}%"]);
        foreach ($s1 as $r) $out[] = ['label' => $r['val'], 'value' => $r['val'], 'tipo' => $r['tipo']];

        // descricao
        $s2 = $pdo->prepare("
            SELECT DISTINCT sp.descricao AS val, 'Descrição' AS tipo
            FROM solicitacoes_pagamento sp
            JOIN unidades u
              ON u.id = CAST(SUBSTRING_INDEX(sp.id_solicitante,'_',-1) AS UNSIGNED)
             AND u.tipo = 'Filial'
             AND u.empresa_id = :matriz
            WHERE sp.id_matriz = :matriz
              AND sp.descricao LIKE :q
            ORDER BY sp.descricao
            LIMIT 10
        ");
        $s2->execute([':matriz' => $idSelecionado, ':q' => "%{$term}%"]);
        foreach ($s2 as $r) {
            if (!empty($r['val'])) $out[] = ['label' => $r['val'], 'value' => $r['val'], 'tipo' => $r['tipo']];
        }
    }
    json_out($out);
}

/* ==========================================================
   >>>>>>>  FILTROS (Status pendente/aprovado/reprovado)  <<<<<<<
   - status: 'pendente' | 'aprovado' | 'reprovado'
   - de/ate: por VENCIMENTO (YYYY-MM-DD)
   - q: id_solicitante OU descricao
   ========================================================== */
$status = $_GET['status'] ?? '';
$de     = trim($_GET['de'] ?? '');
$ate    = trim($_GET['ate'] ?? '');
$q      = trim($_GET['q'] ?? '');

$where  = [];
$params = [':matriz' => $idSelecionado];

$where[] = "u.tipo = 'Filial'";
$where[] = "sp.id_matriz = :matriz";

/* status: pendente/aprovado/reprovado */
$validStatus = ['pendente', 'aprovado', 'reprovado'];
if ($status !== '' && in_array($status, $validStatus, true)) {
    $where[] = "sp.status = :status";
    $params[':status'] = $status;
}

/* período: por vencimento (YYYY-MM-DD) */
if ($de !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $de)) {
    $where[] = "DATE(sp.vencimento) >= :de";
    $params[':de'] = $de;
}
if ($ate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) {
    $where[] = "DATE(sp.vencimento) <= :ate";
    $params[':ate'] = $ate;
}

/* busca: id_solicitante OU descricao */
if ($q !== '') {
    $where[] = "(sp.id_solicitante LIKE :q OR sp.descricao LIKE :q)";
    $params[':q'] = "%{$q}%";
}

$whereSql = implode(' AND ', $where);

/* ==========================================================
   LISTAGEM (aplicando filtros)
   ========================================================== */
$pagamentos = [];
try {
    $sql = "
        SELECT
            sp.id,
            sp.id_matriz,
            sp.id_solicitante,
            COALESCE(sp.descricao, '')        AS descricao,
            COALESCE(sp.valor, 0.00)          AS valor,
            sp.vencimento,
            COALESCE(sp.comprovante_url, '')  AS documento,
            sp.status,
            u.id        AS unidade_id,
            u.nome      AS unidade_nome,
            u.tipo      AS unidade_tipo
        FROM solicitacoes_pagamento sp
        JOIN unidades u
          ON u.id = CAST(SUBSTRING_INDEX(sp.id_solicitante,'_',-1) AS UNSIGNED)
        WHERE {$whereSql}
        ORDER BY sp.vencimento DESC, sp.id DESC
    ";
    $st = $pdo->prepare($sql);
    foreach ($params as $k => $v) $st->bindValue($k, $v);
    $st->execute();
    $pagamentos = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pagamentos = [];
}

?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>ERP - Filial</title>
    <link rel="icon" type="image/x-icon" href="<?= e($logoEmpresa) ?>" />

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />

    <script src="../../assets/vendor/js/helpers.js"></script>
    <script src="../../assets/js/config.js"></script>
    <style>
        .status-badge {
            text-transform: capitalize;
        }

        .table td,
        .table th {
            vertical-align: middle;
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
                            <div>Dashboard</div>
                        </a>
                    </li>

                    <li class="menu-header small text-uppercase">
                        <span class="menu-header-text">Administração Filiais</span>
                    </li>

                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div>B2B - Matriz</div>
                        </a>
                        <ul class="menu-sub active">
                            <li class="menu-item active">
                                <a href="./contasFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Pagamentos Solic.</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./produtosSolicitados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Produtos Solicitados</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./produtosEnviados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Produtos Enviados</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./transferenciasPendentes.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Transf. Pendentes</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./historicoTransferencias.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Histórico Transf.</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./estoqueMatriz.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Estoque Matriz</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./relatoriosB2B.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Relatórios B2B</div>
                                </a>
                            </li>
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
                    <li class="menu-item"><a href="../franquia/index.php?id=principal_1" class="menu-link"><i class="menu-icon tf-icons bx bx-store"></i>
                            <div>Franquias</div>
                        </a></li>
                    <li class="menu-item"><a href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-group"></i>
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
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                            <i class="bx bx-menu bx-sm"></i>
                        </a>
                    </div>
                </nav>
                <!-- / Navbar -->

                <!-- Content -->
                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold mb-0">
                        <span class="text-muted fw-light"><a href="#">Filiais</a> /</span> Pagamentos Solicitados
                    </h4>
                    <h5 class="fw-bold mt-3 mb-3">
                        <span class="text-muted fw-light">Visualize e gerencie as solicitações de pagamento das filiais</span>
                    </h5>

                    <!-- ===== Filtros ===== -->
                    <form class="card mb-3" method="get" id="filtroForm" autocomplete="off">
                        <input type="hidden" name="id" value="<?= e($idSelecionado) ?>">
                        <div class="card-body">
                            <div class="row g-3 align-items-end">
                                <div class="col-12 col-md-auto filter-col">
                                    <label class="form-label mb-1">Status</label>
                                    <select class="form-select form-select-sm" name="status">
                                        <option value="">Todos (pendente, aprovado, reprovado)</option>
                                        <option value="pendente" <?= $status === 'pendente'  ? 'selected' : '' ?>>Pendente</option>
                                        <option value="aprovado" <?= $status === 'aprovado'  ? 'selected' : '' ?>>Aprovado</option>
                                        <option value="reprovado" <?= $status === 'reprovado' ? 'selected' : '' ?>>Reprovado</option>
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
                                        <input type="text" class="form-control form-control-sm" id="qInput" name="q"
                                            placeholder="Solicitante (ex.: unidade_3) ou descrição…" value="<?= e($q) ?>" autocomplete="off">
                                        <div class="autocomplete-list d-none" id="qList"></div>
                                    </div>
                                </div>

                                <div class="col-12 col-md-auto d-flex gap-2 filter-col">
                                    <button class="btn btn-sm btn-primary" type="submit">
                                        <i class="bx bx-filter-alt me-1"></i> Filtrar
                                    </button>
                                    <a class="btn btn-sm btn-outline-secondary" href="?id=<?= urlencode($idSelecionado) ?>">
                                        <i class="bx bx-eraser me-1"></i> Limpar
                                    </a>
                                </div>
                            </div>
                            <div class="small text-muted mt-2">
                                Resultados: <strong><?= count($pagamentos) ?></strong> registros
                            </div>
                        </div>
                    </form>

                    <!-- Tabela -->
                    <div class="card">
                        <h5 class="card-header">Lista de Pagamentos (Filiais)</h5>
                        <div class="table-responsive text-nowrap">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Filial</th>
                                        <th>Solicitante (id)</th>
                                        <th>Descrição</th>
                                        <th>Valor</th>
                                        <th>Vencimento</th>
                                        <th>Documento</th>
                                        <th>Status</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="table-border-bottom-0" id="tbody-pagamentos">
                                    <?php if (!$pagamentos): ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-4">Nenhum registro encontrado.</td>
                                        </tr>
                                        <?php else: foreach ($pagamentos as $p): ?>
                                            <?php
                                            $id = (int)$p['id'];
                                            $isPendente = ($p['status'] === 'pendente');
                                            $badge = 'bg-label-secondary';
                                            if ($p['status'] === 'pendente')  $badge = 'bg-label-warning';
                                            if ($p['status'] === 'aprovado')  $badge = 'bg-label-success';
                                            if ($p['status'] === 'reprovado') $badge = 'bg-label-danger';
                                            ?>
                                            <tr id="row-<?= $id ?>">
                                                <td><?= $id ?></td>
                                                <td><strong><?= e($p['unidade_nome'] ?? ('Unidade #' . ($p['unidade_id'] ?? ''))) ?></strong></td>
                                                <td><?= e($p['id_solicitante']) ?></td>
                                                <td><?= e($p['descricao']) ?></td>
                                                <td><?= moneyBr($p['valor']) ?></td>
                                                <td><?= $p['vencimento'] ? date('d/m/Y', strtotime($p['vencimento'])) : '—' ?></td>
                                                <td>
                                                    <?php if (!empty($p['documento'])): ?>
                                                        <a href="?id=<?= urlencode($idSelecionado) ?>&download=<?= $id ?>" class="text-primary">Baixar</a>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge <?= $badge ?> status-badge" id="status-<?= $id ?>">
                                                        <?= e($p['status']) ?>
                                                    </span>
                                                </td>
                                                <td class="text-end" id="acoes-<?= $id ?>">
                                                    <?php if ($isPendente): ?>
                                                        <button class="btn btn-sm btn-success me-1 btn-aprovar" data-id="<?= $id ?>">
                                                            <i class="bx"></i> Aprovar
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger me-1 btn-reprovar" data-id="<?= $id ?>">
                                                            <i class="bx"></i> Reprovar
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-outline-secondary btn-detalhes" data-id="<?= $id ?>" data-bs-toggle="modal" data-bs-target="#modalDetalhes">Detalhes</button>
                                                </td>
                                            </tr>
                                    <?php endforeach;
                                    endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- /Tabela -->

                    <!-- Modal Detalhes -->
                    <div class="modal fade" id="modalDetalhes" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Detalhes da Solicitação</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                </div>
                                <div class="modal-body">
                                    <div id="detalhes-conteudo">
                                        <div class="text-center text-muted py-3">Carregando…</div>
                                    </div>
                                </div>
                                <div class="modal-footer" id="detalhes-footer">
                                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                                    <!-- botão Baixar é injetado dinamicamente -->
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- / Content -->

                <footer class="content-footer footer bg-footer-theme text-center">
                    <div class="container-xxl d-flex py-2 flex-md-row flex-column justify-content-center">
                        <div class="mb-2 mb-md-0">
                            &copy;<script>
                                document.write(new Date().getFullYear());
                            </script>,
                            <strong>Açaínhadinhos</strong>. Todos os direitos reservados. Desenvolvido por <strong>CodeGeek</strong>.
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

    <script>
        (function() {
            function post(action, payload) {
                const data = new URLSearchParams();
                data.append('action', action);
                Object.keys(payload || {}).forEach(k => data.append(k, payload[k]));
                return fetch(location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: data.toString()
                }).then(r => r.json());
            }

            function formatMoney(v) {
                if (v == null) return 'R$ 0,00';
                const n = Number(v);
                return n.toLocaleString('pt-BR', {
                    style: 'currency',
                    currency: 'BRL'
                });
            }

            function formatDate(iso) {
                if (!iso) return '—';
                const d = new Date(iso);
                if (isNaN(d.getTime())) return '—';
                const dd = String(d.getDate()).padStart(2, '0');
                const mm = String(d.getMonth() + 1).padStart(2, '0');
                const yyyy = d.getFullYear();
                return `${dd}/${mm}/${yyyy}`;
            }

            function escapeHtml(s) {
                return String(s ?? '').replace(/[&<>"']/g, m => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                } [m]));
            }

            // Clique Aprovar / Reprovar
            document.getElementById('tbody-pagamentos')?.addEventListener('click', function(e) {
                const btnAprovar = e.target.closest('.btn-aprovar');
                const btnReprovar = e.target.closest('.btn-reprovar');
                const btnDetalhes = e.target.closest('.btn-detalhes');

                // Detalhes
                if (btnDetalhes) {
                    const id = btnDetalhes.getAttribute('data-id');
                    const box = document.getElementById('detalhes-conteudo');
                    const footer = document.getElementById('detalhes-footer');
                    if (box) box.innerHTML = '<div class="text-center text-muted py-3">Carregando…</div>';
                    if (footer) {
                        const oldBtn = footer.querySelector('.btn-baixar-modal');
                        if (oldBtn) oldBtn.remove();
                    }
                    post('get_details', {
                            id
                        })
                        .then(json => {
                            if (json && json.ok && json.data) {
                                const d = json.data;
                                const html = `
                        <div class="row g-3">
                          <div class="col-md-6">
                            <p><strong>Filial:</strong> ${escapeHtml(d.unidade_nome ?? '')} (ID: ${escapeHtml(d.unidade_id ?? '')})</p>
                            <p><strong>Solicitante:</strong> ${escapeHtml(d.id_solicitante ?? '')}</p>
                            <p><strong>Status:</strong> ${escapeHtml(d.status ?? '')}</p>
                          </div>
                          <div class="col-md-6">
                            <p><strong>Descrição:</strong> ${escapeHtml(d.descricao ?? '')}</p>
                            <p><strong>Valor:</strong> ${formatMoney(d.valor)}</p>
                            <p><strong>Vencimento:</strong> ${formatDate(d.vencimento)}</p>
                          </div>
                          <div class="col-12">
                            <p><strong>Documento:</strong> ${d.documento ? 'Disponível' : '<span class="text-muted">—</span>'}</p>
                          </div>
                        </div>`;
                                if (box) box.innerHTML = html;

                                if (footer && d.documento) {
                                    const a = document.createElement('a');
                                    a.className = 'btn btn-primary btn-baixar-modal';
                                    a.textContent = 'Baixar comprovante';
                                    a.href = `?id=<?= urlencode($idSelecionado) ?>&download=${encodeURIComponent(String(id))}`;
                                    footer.appendChild(a);
                                }
                            } else {
                                if (box) box.innerHTML = `<div class="text-danger">Não foi possível carregar os detalhes.</div>`;
                            }
                        })
                        .catch(() => {
                            if (box) box.innerHTML = `<div class="text-danger">Falha de rede ao buscar detalhes.</div>`;
                        });
                    return;
                }

                // Aprovar / Reprovar
                const btn = btnAprovar || btnReprovar;
                if (!btn) return;

                const id = btn.getAttribute('data-id');
                const novoStatus = btnAprovar ? 'aprovado' : 'reprovado';
                const msgConf = btnAprovar ? 'Confirmar APROVAÇÃO desta solicitação?' : 'Confirmar REPROVAÇÃO desta solicitação?';

                if (!confirm(msgConf)) return;

                post('update_status', {
                        id,
                        status: novoStatus
                    })
                    .then(json => {
                        if (!json || !json.ok) {
                            alert(json?.msg || 'Não foi possível atualizar o status.');
                            return;
                        }
                        // Atualiza badge
                        const badge = document.getElementById('status-' + id);
                        if (badge) {
                            badge.textContent = novoStatus;
                            badge.classList.remove('bg-label-warning', 'bg-label-success', 'bg-label-danger', 'bg-label-secondary');
                            if (novoStatus === 'aprovado') badge.classList.add('bg-label-success');
                            if (novoStatus === 'reprovado') badge.classList.add('bg-label-danger');
                        }
                        // Remove botões Aprovar/Reprovar da linha
                        const acoes = document.getElementById('acoes-' + id);
                        if (acoes) {
                            acoes.querySelectorAll('.btn-aprovar, .btn-reprovar').forEach(el => el.remove());
                        }
                    })
                    .catch(() => alert('Falha de rede ao atualizar o status.'));
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
                qInput?.addEventListener('input', function() {
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
                qInput?.addEventListener('keydown', function(e) {
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
                list?.addEventListener('mousedown', function(e) {
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
        })();
    </script>
</body>

</html>
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// ✅ Recupera o identificador vindo da URL
$idSelecionado = $_GET['id'] ?? '';
if (!$idSelecionado) {
    header("Location: .././login.php");
    exit;
}

// ✅ Verifica se a pessoa está logada
if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id'])
) {
    header("Location: .././login.php?id=" . urlencode($idSelecionado));
    exit;
}

// ✅ Conexão com o banco de dados
require '../../assets/php/conexao.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ✅ Buscar nome e tipo do usuário logado
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id  = (int)$_SESSION['usuario_id'];

try {
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $nomeUsuario = $usuario['usuario'] ?? 'Usuário';
        $tipoUsuario = ucfirst((string)($usuario['nivel'] ?? 'Comum'));
    } else {
        echo "<script>alert('Usuário não encontrado.'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar usuário: " . htmlspecialchars($e->getMessage()) . "'); history.back();</script>";
    exit;
}

// ✅ Valida o tipo de empresa e o acesso permitido
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

// ✅ Buscar logo da empresa
try {
    $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1");
    $stmt->bindParam(':id_selecionado', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);

    $logoEmpresa = (!empty($empresaSobre) && !empty($empresaSobre['imagem']))
        ? "../../assets/img/empresa/" . $empresaSobre['imagem']
        : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png"; // fallback
}

/* ============================================
   🔸 MODO AJAX (DETALHES)
   ============================================ */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'detalhes') {
    header('Content-Type: application/json; charset=utf-8');

    $sid = (int)($_GET['solicitacao_id'] ?? 0);
    if ($sid <= 0) {
        echo json_encode(['ok' => false, 'erro' => 'ID inválido']);
        exit;
    }

    try {
        $sqlItens = "
            SELECT 
                COALESCE(i.codigo_produto,'') AS codigo_produto,
                COALESCE(i.nome_produto,'')   AS nome_produto,
                COALESCE(i.quantidade,0)      AS quantidade
            FROM solicitacoes_b2b_itens i
            WHERE i.solicitacao_id = :sid
            ORDER BY i.id ASC
        ";
        $st = $pdo->prepare($sqlItens);
        $st->execute([':sid' => $sid]);
        $itens = $st->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['ok' => true, 'itens' => $itens]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

/* ==========================================================
   🟠 FILTROS — Status (Enviado/Entregue/Todos), Período e Busca
   ========================================================== */
$statusFiltro = strtolower(trim($_GET['status'] ?? '')); // '', 'enviado', 'entregue'
$de           = trim($_GET['de'] ?? '');
$ate          = trim($_GET['ate'] ?? '');
$q            = trim($_GET['q'] ?? '');

$where  = [];
$params = [':empresa_id' => $idSelecionado];

$where[] = "s.id_matriz = :empresa_id";

/* 🔹 Status:
   - '' (Todos) => IN ('em_transito','entregue')
   - 'enviado'  => = 'em_transito'
   - 'entregue' => = 'entregue'
*/
if ($statusFiltro === 'enviado') {
    $where[] = "s.status = 'em_transito'";
} elseif ($statusFiltro === 'entregue') {
    $where[] = "s.status = 'entregue'";
} else {
    $where[] = "s.status IN ('em_transito','entregue')";
}

/* 🔹 Período pelo primeiro marco disponível (entregue/enviada/updated/aprovada/created) */
$dateExpr = "DATE(COALESCE(s.entregue_em, s.enviada_em, s.updated_at, s.aprovada_em, s.created_at))";
if ($de !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $de)) {
    $where[] = "$dateExpr >= :de";
    $params[':de'] = $de;
}
if ($ate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) {
    $where[] = "$dateExpr <= :ate";
    $params[':ate'] = $ate;
}

/* 🔹 Busca livre */
if ($q !== '') {
    $where[] = "(u.nome LIKE :q OR s.id_solicitante LIKE :q OR s.observacao LIKE :q)";
    $params[':q'] = "%{$q}%";
}

/* ==========================================================
   🟢 LISTAGEM
   ========================================================== */
$envios = [];
try {
    $whereSql = implode(' AND ', $where);
    $sql = "
    SELECT
        s.id,
        s.id_solicitante,
        u.nome AS filial_nome,
        s.status,
        COALESCE(s.entregue_em, s.enviada_em, s.updated_at, s.aprovada_em, s.created_at) AS mov_em,
        COUNT(i.id)                               AS itens,
        COALESCE(SUM(i.quantidade),0)             AS qtd_total,
        fi.codigo_produto AS primeiro_sku,
        fi.nome_produto   AS primeiro_nome
    FROM solicitacoes_b2b s
    JOIN unidades u
      ON u.id = CAST(REPLACE(s.id_solicitante, 'unidade_', '') AS UNSIGNED)
     AND u.tipo IN ('Filial','filial')
     AND u.empresa_id = s.id_matriz
    LEFT JOIN solicitacoes_b2b_itens i
      ON i.solicitacao_id = s.id
    LEFT JOIN (
        SELECT ii.solicitacao_id,
               SUBSTRING_INDEX(GROUP_CONCAT(ii.codigo_produto ORDER BY ii.id ASC SEPARATOR '||'), '||', 1) AS codigo_produto,
               SUBSTRING_INDEX(GROUP_CONCAT(ii.nome_produto   ORDER BY ii.id ASC SEPARATOR '||'), '||', 1) AS nome_produto
        FROM solicitacoes_b2b_itens ii
        GROUP BY ii.solicitacao_id
    ) fi ON fi.solicitacao_id = s.id
    WHERE {$whereSql}
    GROUP BY s.id, s.id_solicitante, u.nome, s.status, mov_em, fi.codigo_produto, fi.nome_produto
    ORDER BY mov_em DESC, s.id DESC
    LIMIT 300
    ";
    $st = $pdo->prepare($sql);
    foreach ($params as $k => $v) $st->bindValue($k, $v);
    $st->execute();
    $envios = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $envios = [];
}

// Helpers
function dtBr(?string $dt) {
    if (!$dt) return '-';
    $t = strtotime($dt); if (!$t) return '-';
    return date('d/m/Y H:i', $t);
}
function h(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
    data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>ERP - Filial</title>

    <meta name="description" content="" />

    <!-- Favicon da empresa carregado dinamicamente -->
    <link rel="icon" type="image/x-icon" href="<?= h($logoEmpresa) ?>" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,600;1,700&display=swap"
        rel="stylesheet" />

    <!-- Icons -->
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />

    <!-- Helpers -->
    <script src="../../assets/vendor/js/helpers.js"></script>

    <!-- Config -->
    <script src="../../assets/js/config.js"></script>

    <style>.status-badge{text-transform:capitalize}</style>
</head>

<body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- Menu (mantido) -->
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
                    <!-- Dashboard -->
                    <li class="menu-item">
                        <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Dashboard</div>
                        </a>
                    </li>

                    <!-- Administração de Filiais -->
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

                    <li class="menu-item open active">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div data-i18n="B2B">B2B - Matriz</div>
                        </a>
                        <ul class="menu-sub active">
                            <li class="menu-item">
                                <a href="./contasFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Pagamentos Solic.</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./produtosSolicitados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Produtos Solicitados</div>
                                </a>
                            </li>
                            <li class="menu-item active">
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

                    <!-- Diversos -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
                    <li class="menu-item">
                        <a href="../rh/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-group"></i>
                            <div data-i18n="Authentications">RH</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../financas/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-dollar"></i>
                            <div data-i18n="Authentications">Finanças</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../pdv/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-desktop"></i>
                            <div data-i18n="Authentications">PDV</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../empresa/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div data-i18n="Authentications">Empresa</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-box"></i>
                            <div data-i18n="Authentications">Estoque</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../franquia/index.php?id=principal_1" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-store"></i>
                            <div data-i18n="Authentications">Franquias</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-group"></i>
                            <div data-i18n="Authentications">Usuários </div>
                        </a>
                    </li>
                    <li class="menu-item mb-5">
                        <a href="https://wa.me/92991515710" target="_blank" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-support"></i>
                            <div data-i18n="Basic">Suporte</div>
                        </a>
                    </li>
                </ul>
            </aside>
            <!-- / Menu -->

            <!-- Layout container -->
            <div class="layout-page">
                <!-- Navbar (mantida) -->
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
                    id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                            <i class="bx bx-menu bx-sm"></i>
                        </a>
                    </div>

                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <div class="navbar-nav align-items-center">
                            <div class="nav-item d-flex align-items-center"></div>
                        </div>
                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="avatar avatar-online">
                                        <img src="<?= h($logoEmpresa) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownUser">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="<?= h($logoEmpresa) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" />
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <span class="fw-semibold d-block"><?= h($nomeUsuario); ?></span>
                                                    <small class="text-muted"><?= h($tipoUsuario); ?></small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                    <li><div class="dropdown-divider"></div></li>
                                    <li><a class="dropdown-item" href="./contaUsuario.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-user me-2"></i><span class="align-middle">Minha Conta</span></a></li>
                                    <li><a class="dropdown-item" href="#"><i class="bx bx-cog me-2)"></i><span class="align-middle">Configurações</span></a></li>
                                    <li><div class="dropdown-divider"></div></li>
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
                        <span class="text-muted fw-light"><a href="#">Filiais</a>/</span>
                        Produtos Enviados
                    </h4>
                    <h5 class="fw-bold mt-3 mb-3">
                        <span class="text-muted fw-light">Filtre por status: Enviado (em trânsito) ou Entregue</span>
                    </h5>

                    <!-- ===== Filtros ===== -->
                    <form class="card mb-3" method="get" id="filtroForm">
                      <input type="hidden" name="id" value="<?= h($idSelecionado) ?>">
                      <div class="card-body">
                        <div class="row g-3 align-items-end">
                          <div class="col-12 col-md-auto">
                            <label class="form-label mb-1">Status</label>
                            <select class="form-select form-select-sm" name="status">
                              <option value=""           <?= $statusFiltro===''          ? 'selected' : '' ?>>Todos (Enviado + Entregue)</option>
                              <option value="enviado"   <?= $statusFiltro==='enviado'   ? 'selected' : '' ?>>Enviado</option>
                              <option value="entregue"  <?= $statusFiltro==='entregue'  ? 'selected' : '' ?>>Entregue</option>
                            </select>
                          </div>

                          <div class="col-12 col-md-auto">
                            <label class="form-label mb-1">De</label>
                            <input type="date" class="form-control form-control-sm" name="de" value="<?= h($de) ?>">
                          </div>

                          <div class="col-12 col-md-auto">
                            <label class="form-label mb-1">Até</label>
                            <input type="date" class="form-control form-control-sm" name="ate" value="<?= h($ate) ?>">
                          </div>

                          <div class="col-12 col-md">
                            <label class="form-label mb-1">Buscar</label>
                            <input type="text" class="form-control form-control-sm" name="q" value="<?= h($q) ?>"
                                   placeholder="Filial, id_solicitante (ex.: unidade_3) ou observação…">
                          </div>

                          <div class="col-12 col-md-auto d-flex gap-2">
                            <button class="btn btn-sm btn-primary" type="submit"><i class="bx bx-filter-alt me-1"></i> Filtrar</button>
                            <a class="btn btn-sm btn-outline-secondary" href="?id=<?= urlencode($idSelecionado) ?>"><i class="bx bx-eraser me-1"></i> Limpar</a>
                          </div>
                        </div>
                      </div>
                    </form>

                    <!-- Tabela -->
                    <div class="card">
                        <h5 class="card-header">Lista de Produtos Enviados</h5>
                        <div class="table-responsive text-nowrap">
                            <table class="table table-hover" id="tabela-enviados">
                                <thead>
                                    <tr>
                                        <th># Pedido</th>
                                        <th>Filial</th>
                                        <th>SKU</th>
                                        <th>Produto</th>
                                        <th>Qtd</th>
                                        <th>Movimentado em</th>
                                        <th>Status</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="table-border-bottom-0">
                                <?php if (empty($envios)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">Nenhum registro encontrado.</td>
                                    </tr>
                                <?php else: foreach ($envios as $row): ?>
                                    <?php
                                        $sku   = $row['primeiro_sku']  ?: '—';
                                        $nome  = $row['primeiro_nome'] ?: '—';
                                        $itens = (int)$row['itens'];
                                        if ($itens > 1 && $nome !== '—') {
                                            $nome .= " <span class='text-muted'>(+ " . ($itens-1) . ")</span>";
                                        }

                                        // mapeia visual: em_transito => Enviado
                                        $statusDb = strtolower((string)$row['status']);
                                        if ($statusDb === 'entregue') {
                                            $badgeHtml = '<span class="badge bg-label-success status-badge">Entregue</span>';
                                            $statusTexto = 'Entregue';
                                        } elseif ($statusDb === 'em_transito') {
                                            $badgeHtml = '<span class="badge bg-label-warning status-badge">Enviado</span>';
                                            $statusTexto = 'Enviado';
                                        } else {
                                            $badgeHtml = '<span class="badge bg-label-secondary status-badge">'.h($row['status']).'</span>';
                                            $statusTexto = ucfirst(str_replace('_',' ', (string)$row['status']));
                                        }
                                    ?>
                                    <tr data-row-id="<?= (int)$row['id'] ?>">
                                        <td>PS-<?= (int)$row['id'] ?></td>
                                        <td><strong><?= h($row['filial_nome'] ?? '-') ?></strong></td>
                                        <td><?= h($sku) ?></td>
                                        <td><?= $nome ?></td>
                                        <td><?= (int)$row['qtd_total'] ?></td>
                                        <td><?= dtBr($row['mov_em']) ?></td>
                                        <td><?= $badgeHtml ?></td>
                                        <td class="text-end">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-secondary btn-detalhes"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalDetalhes"
                                                data-id="<?= (int)$row['id'] ?>"
                                                data-codigo="PS-<?= (int)$row['id'] ?>"
                                                data-filial="<?= h($row['filial_nome'] ?? '-') ?>"
                                                data-status="<?= h($statusTexto) ?>">
                                                Detalhes
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Modal Detalhes -->
                    <div class="modal fade" id="modalDetalhes" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Detalhes do Pedido</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row g-3 mb-2">
                                        <div class="col-md-4">
                                            <p><strong>Código:</strong> <span id="det-codigo">-</span></p>
                                        </div>
                                        <div class="col-md-4">
                                            <p><strong>Filial:</strong> <span id="det-filial">-</span></p>
                                        </div>
                                        <div class="col-md-4">
                                            <p><strong>Status:</strong> <span id="det-status">-</span></p>
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
                                            <tbody id="det-itens">
                                                <tr>
                                                    <td colspan="3" class="text-muted">Carregando...</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="mt-2">
                                        <strong>Observações:</strong>
                                        <div id="det-obs" class="text-muted">—</div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- JS da LISTAGEM: carrega detalhes na modal -->
                    <script>
                        (function(){
                            const modalEl = document.getElementById('modalDetalhes');
                            if (modalEl) {
                                modalEl.addEventListener('show.bs.modal', function (event) {
                                    const btn = event.relatedTarget;
                                    if (!btn) return;

                                    const id  = btn.getAttribute('data-id');
                                    const cod = btn.getAttribute('data-codigo') || '-';
                                    const fil = btn.getAttribute('data-filial') || '-';
                                    const sts = btn.getAttribute('data-status') || '-';

                                    document.getElementById('det-codigo').textContent = cod;
                                    document.getElementById('det-filial').textContent = fil;
                                    document.getElementById('det-status').textContent = sts;

                                    const tbody = document.getElementById('det-itens');
                                    tbody.innerHTML = '<tr><td colspan="3" class="text-muted">Carregando...</td></tr>';

                                    const url = new URL(window.location.href);
                                    url.searchParams.set('ajax', 'detalhes');
                                    url.searchParams.set('solicitacao_id', id);

                                    fetch(url.toString(), { credentials: 'same-origin' })
                                        .then(r => r.json())
                                        .then(data => {
                                            if (!data.ok) throw new Error(data.erro || 'Falha ao carregar itens');
                                            const itens = data.itens || [];
                                            if (!itens.length) {
                                                tbody.innerHTML = '<tr><td colspan="3" class="text-muted">Sem itens para esta solicitação.</td></tr>';
                                                return;
                                            }
                                            tbody.innerHTML = itens.map(it => {
                                                const cod = (it.codigo_produto || '—');
                                                const nome = (it.nome_produto || '—');
                                                const qtd  = (it.quantidade || 0);
                                                return `<tr>
                                                    <td>${cod}</td>
                                                    <td>${nome}</td>
                                                    <td>${qtd}</td>
                                                </tr>`;
                                            }).join('');
                                        })
                                        .catch(err => {
                                            tbody.innerHTML = `<tr><td colspan="3" class="text-danger">Erro: ${err.message}</td></tr>`;
                                        });
                                });
                            }
                        })();
                    </script>

                </div>
                <!-- / Content -->

                <!-- Footer -->
                <footer class="content-footer footer bg-footer-theme text-center">
                    <div class="container-xxl d-flex  py-2 flex-md-row flex-column justify-content-center">
                        <div class="mb-2 mb-md-0">
                            &copy;
                            <script>document.write(new Date().getFullYear());</script>
                            , <strong>Açaínhadinhos</strong>. Todos os direitos reservados.
                            Desenvolvido por <strong>CodeGeek</strong>.
                        </div>
                    </div>
                </footer>
                <!-- / Footer -->

                <div class="content-backdrop fade"></div>
            </div>
            <!-- Content wrapper -->
        </div>
        <!-- / Layout page -->
    </div>

    <!-- Overlay -->
    <div class="layout-overlay layout-menu-toggle"></div>

    <!-- Core JS -->
    <script src="../../js/saudacao.js"></script>
    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../assets/vendor/js/menu.js"></script>

    <!-- Vendors JS -->
    <script src="../../assets/vendor/libs/apex-charts/apexcharts.js"></script>

    <!-- Main JS -->
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/dashboards-analytics.js"></script>
</body>
</html>

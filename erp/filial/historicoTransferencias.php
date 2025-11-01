<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
date_default_timezone_set('America/Manaus');

/* =========================
   Sessão / Parâmetros
   ========================= */
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

/* =========================
   Conexão
   ========================= */
require '../../assets/php/conexao.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo "Erro: conexão indisponível.";
    exit;
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* =========================
   Usuário logado (nome/tipo)
   ========================= */
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
    echo "<script>alert('Erro ao carregar usuário: " . htmlspecialchars($e->getMessage()) . "'); history.back();</script>";
    exit;
}

/* =========================
   Validação de acesso
   ========================= */
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

/* =========================
   Logo da empresa
   ========================= */
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
   AJAX de Detalhes (mesmo arquivo)
   GET ?ajax=detalhes&id=123  → JSON com cabeçalho + itens
   ========================================================== */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'detalhes') {
    header('Content-Type: application/json; charset=utf-8');
    $sid = (int)($_GET['id'] ?? 0);
    if ($sid <= 0) {
        echo json_encode(['ok' => false, 'erro' => 'ID inválido']);
        exit;
    }
    try {
        // Cabeçalho (apenas entregues ou canceladas, e origem precisa ser Filial)
        $cab = $pdo->prepare("
            SELECT
                s.id,
                s.id_matriz,
                s.id_solicitante,
                s.status,
                s.observacao,
                s.created_at,
                s.enviada_em,
                s.entregue_em,
                u.nome AS filial_nome
            FROM solicitacoes_b2b s
            JOIN unidades u
              ON u.id = CAST(REPLACE(s.id_solicitante,'unidade_','') AS UNSIGNED)
             AND u.empresa_id = s.id_matriz
            WHERE s.id = :sid
              AND s.id_matriz = :empresa
              AND LOWER(u.tipo) = 'filial'
              AND s.status IN ('entregue','cancelada')
            LIMIT 1
        ");
        $cab->execute([':sid' => $sid, ':empresa' => $idSelecionado]);
        $cabecalho = $cab->fetch(PDO::FETCH_ASSOC);
        if (!$cabecalho) {
            echo json_encode(['ok' => false, 'erro' => 'Registro não encontrado para esta matriz/filial.']);
            exit;
        }

        // Itens
        $it = $pdo->prepare("
            SELECT
                COALESCE(i.codigo_produto,'') AS codigo_produto,
                COALESCE(i.nome_produto,'')   AS nome_produto,
                COALESCE(i.quantidade,0)      AS quantidade
            FROM solicitacoes_b2b_itens i
            WHERE i.solicitacao_id = :sid
            ORDER BY i.id ASC
        ");
        $it->execute([':sid' => $sid]);
        $itens = $it->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['ok' => true, 'cabecalho' => $cabecalho, 'itens' => $itens], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

/* ==========================================================
   LISTAGEM — SOMENTE Filial + (entregue | cancelada)
   ========================================================== */
$historico = [];
try {
    $sql = "
        SELECT
            s.id,
            s.id_solicitante,
            u.nome                         AS filial_nome,
            s.created_at,
            s.enviada_em,
            s.entregue_em,
            s.status,
            COUNT(i.id)                    AS itens,
            COALESCE(SUM(i.quantidade),0)  AS qtd_total
        FROM solicitacoes_b2b s
        JOIN unidades u
          ON u.id = CAST(REPLACE(s.id_solicitante,'unidade_','') AS UNSIGNED)
         AND u.empresa_id = s.id_matriz
        LEFT JOIN solicitacoes_b2b_itens i
          ON i.solicitacao_id = s.id
        WHERE s.id_matriz = :empresa_id
          AND LOWER(u.tipo) = 'filial'
          AND s.status IN ('entregue','cancelada')
        GROUP BY
            s.id, s.id_solicitante, u.nome, s.created_at, s.enviada_em, s.entregue_em, s.status
        ORDER BY
            -- entregues mais recentes primeiro, depois canceladas
            FIELD(s.status,'entregue','cancelada'),
            COALESCE(s.entregue_em, s.created_at) DESC,
            s.id DESC
        LIMIT 500
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':empresa_id' => $idSelecionado]);
    $historico = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $historico = [];
}

/* =========================
   Helpers
   ========================= */
function dtBr(?string $dt): string {
    if (!$dt) return '—';
    $t = strtotime($dt);
    return $t ? date('d/m/Y H:i', $t) : '—';
}
function e(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
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
    <link rel="icon" type="image/x-icon" href="<?= e($logoEmpresa) ?>" />
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css"/>
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css"/>
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css"/>
    <link rel="stylesheet" href="../../assets/css/demo.css"/>
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css"/>
    <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css"/>
    <script src="../../assets/vendor/js/helpers.js"></script>
    <script src="../../assets/js/config.js"></script>
    <style>
        .status-badge { font-size: .78rem; }
        .table thead th { white-space: nowrap; }
        .text-end { text-align: end; }
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
                        <li class="menu-item active">
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
                            <a href="./MaisVendidosFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                <div data-i18n="MaisVendidos">Mais Vendidos</div>
                            </a>
                        </li>
                        <li class="menu-item">
                            <a href="./vendasPeriodoFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                <div data-i18n="Pedidos">Vendas por Período</div>
                            </a>
                        </li>
                    </ul>
                </li>

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

        <!-- Layout page -->
        <div class="layout-page">
            <!-- Navbar -->
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
                                    <img src="<?= e($logoEmpresa) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle"/>
                                </div>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownUser">
                                <li>
                                    <a class="dropdown-item" href="#">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0 me-3">
                                                <div class="avatar avatar-online">
                                                    <img src="<?= e($logoEmpresa) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle"/>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <span class="fw-semibold d-block"><?= e($nomeUsuario) ?></span>
                                                <small class="text-muted"><?= e($tipoUsuario) ?></small>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                                <li><div class="dropdown-divider"></div></li>
                                <li><a class="dropdown-item" href="./contaUsuario.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-user me-2"></i><span class="align-middle">Minha Conta</span></a></li>
                                <li><a class="dropdown-item" href="#"><i class="bx bx-cog me-2"></i><span class="align-middle">Configurações</span></a></li>
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
                    <span class="text-muted fw-light"><a href="#">Filial</a>/</span>
                    Histórico de Transferências
                </h4>
                <h5 class="fw-bold mt-3 mb-3">
                    <span class="text-muted fw-light">Somente Filiais — Status: Entregue ou Cancelada</span>
                </h5>

                <div class="card">
                    <h5 class="card-header">Histórico de Transferências</h5>
                    <div class="table-responsive text-nowrap">
                        <table class="table table-hover" id="tabela-historico">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Filial</th>
                                <th>Itens</th>
                                <th>Qtd</th>
                                <th>Criado</th>
                                <th>Enviado</th>
                                <th>Entregue/Cancelado</th>
                                <th>Status Final</th>
                                <th class="text-end">Ações</th>
                            </tr>
                            </thead>
                            <tbody class="table-border-bottom-0">
                            <?php if (empty($historico)): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        Nenhum registro encontrado (Filial + Entregue/Cancelada).
                                    </td>
                                </tr>
                            <?php else: foreach ($historico as $row):
                                $id    = (int)$row['id'];
                                $fil   = $row['filial_nome'] ?? '—';
                                $itens = (int)$row['itens'];
                                $qtd   = (int)$row['qtd_total'];
                                $cri   = dtBr($row['created_at'] ?? null);
                                $env   = dtBr($row['enviada_em'] ?? null);
                                $fin   = dtBr($row['entregue_em'] ?? null); // para cancelada, ficará '—'
                                $st    = strtolower((string)($row['status'] ?? ''));
                                $badge = ($st === 'entregue')
                                    ? '<span class="badge bg-label-success status-badge">Entregue</span>'
                                    : '<span class="badge bg-label-danger status-badge">Cancelada</span>';
                                ?>
                                <tr>
                                    <td><strong><?= 'TR-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT) ?></strong></td>
                                    <td><?= e($fil) ?></td>
                                    <td><?= $itens ?></td>
                                    <td><?= $qtd ?></td>
                                    <td><?= e($cri) ?></td>
                                    <td><?= e($env) ?></td>
                                    <td><?= e($fin) ?></td>
                                    <td><?= $badge ?></td>
                                    <td class="text-end">
                                        <button
                                          class="btn btn-sm btn-outline-secondary btn-detalhes"
                                          data-bs-toggle="modal"
                                          data-bs-target="#modalHistDetalhes"
                                          data-id="<?= $id ?>"
                                          data-codigo="<?= 'TR-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT) ?>"
                                          data-filial="<?= e($fil) ?>"
                                          data-status="<?= ($st === 'entregue' ? 'Entregue' : 'Cancelada') ?>"
                                        >
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
                <div class="modal fade" id="modalHistDetalhes" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Detalhes da Transferência</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-3 mb-2">
                                    <div class="col-md-3"><p><strong>Código:</strong> <span id="hist-codigo">—</span></p></div>
                                    <div class="col-md-3"><p><strong>Filial:</strong> <span id="hist-filial">—</span></p></div>
                                    <div class="col-md-3"><p><strong>Status:</strong> <span id="hist-status">—</span></p></div>
                                    <div class="col-md-3"><p><strong>Total Itens:</strong> <span id="hist-itens">—</span></p></div>
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
                                        <tr><td colspan="3" class="text-muted">Carregando...</td></tr>
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

            <!-- Footer -->
            <footer class="content-footer footer bg-footer-theme text-center">
                <div class="container-xxl d-flex py-2 flex-md-row flex-column justify-content-center">
                    <div class="mb-2 mb-md-0">
                        &copy; <script>document.write(new Date().getFullYear());</script>,
                        <strong>Açaínhadinhos</strong>. Todos os direitos reservados.
                        Desenvolvido por <strong>CodeGeek</strong>.
                    </div>
                </div>
            </footer>
            <div class="content-backdrop fade"></div>
        </div>
        <!-- / Layout page -->
    </div>

    <!-- Overlay -->
    <div class="layout-overlay layout-menu-toggle"></div>
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

<script>
(function(){
  const modal = document.getElementById('modalHistDetalhes');
  if (!modal) return;

  modal.addEventListener('show.bs.modal', function (ev) {
    const btn = ev.relatedTarget;
    if (!btn) return;

    const id     = btn.getAttribute('data-id');
    const codigo = btn.getAttribute('data-codigo') || '—';
    const filial = btn.getAttribute('data-filial') || '—';
    const status = btn.getAttribute('data-status') || '—';

    document.getElementById('hist-codigo').textContent  = codigo;
    document.getElementById('hist-filial').textContent  = filial;
    document.getElementById('hist-status').textContent  = status;
    document.getElementById('hist-itens').textContent   = '—';
    document.getElementById('hist-obs').textContent     = '—';
    document.getElementById('hist-criado').textContent  = '—';
    document.getElementById('hist-enviado').textContent = '—';
    document.getElementById('hist-final').textContent   = '—';

    const tbody = document.getElementById('hist-itens-body');
    tbody.innerHTML = '<tr><td colspan="3" class="text-muted">Carregando...</td></tr>';

    const url = new URL(window.location.href);
    url.searchParams.set('ajax','detalhes');
    url.searchParams.set('id', id);

    fetch(url.toString(), { credentials: 'same-origin' })
      .then(r => r.json())
      .then(data => {
        if (!data.ok) throw new Error(data.erro || 'Falha ao buscar detalhes');

        const cab = data.cabecalho || {};
        const itens = data.itens || [];

        document.getElementById('hist-obs').textContent     = cab.observacao || '—';
        document.getElementById('hist-criado').textContent  = cab.created_at   ? fmtBr(cab.created_at)   : '—';
        document.getElementById('hist-enviado').textContent = cab.enviada_em   ? fmtBr(cab.enviada_em)   : '—';
        document.getElementById('hist-final').textContent   = cab.entregue_em  ? fmtBr(cab.entregue_em)  : '—';

        document.getElementById('hist-itens').textContent = String(itens.length || 0);

        if (!itens.length) {
          tbody.innerHTML = '<tr><td colspan="3" class="text-muted">Sem itens.</td></tr>';
        } else {
          tbody.innerHTML = itens.map(it => {
            const cod = it.codigo_produto || '—';
            const nm  = it.nome_produto || '—';
            const qt  = it.quantidade ?? 0;
            return `<tr><td>${escapeHtml(cod)}</td><td>${escapeHtml(nm)}</td><td>${qt}</td></tr>`;
          }).join('');
        }
      })
      .catch(err => {
        tbody.innerHTML = `<tr><td colspan="3" class="text-danger">Erro: ${escapeHtml(err.message)}</td></tr>`;
      });
  });

  function fmtBr(iso) {
    // Formata 'YYYY-mm-dd HH:ii:ss' em 'dd/mm/YYYY HH:ii'
    const d = new Date(iso.replace(' ', 'T'));
    if (isNaN(d.getTime())) return iso;
    const dd = String(d.getDate()).padStart(2,'0');
    const mm = String(d.getMonth()+1).padStart(2,'0');
    const yyyy = String(d.getFullYear());
    const hh = String(d.getHours()).padStart(2,'0');
    const ii = String(d.getMinutes()).padStart(2,'0');
    return `${dd}/${mm}/${yyyy} ${hh}:${ii}`;
  }
  function escapeHtml(s){
    return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  }
})();
</script>
</body>
</html>

<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

/* ================== PARÂMETROS / SESSÃO ================== */
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
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo "Erro: conexão indisponível.";
    exit;
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ================== USUÁRIO ================== */
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
        echo "<script>alert('Usuário não encontrado.'); window.location.href='.././login.php?id=" . htmlspecialchars(urlencode($idSelecionado)) . "';</script>";
        exit;
    }
} catch (Throwable $e) {
    echo "<script>alert('Erro ao carregar usuário: " . htmlspecialchars($e->getMessage()) . "'); history.back();</script>";
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
    echo "<script>alert('Acesso negado!'); window.location.href='.././login.php?id=" . htmlspecialchars(urlencode($idSelecionado)) . "';</script>";
    exit;
}

/* ================== LOGO / FAVICON ================== */
$favicon = '';
try {
    $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1");
    $stmt->execute([':id' => $idSelecionado]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['imagem'])) {
        $favicon = $row['imagem'];
    }
} catch (Throwable $e) { /* silencioso */ }

/* ================== HELPERS ================== */
function h(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function dtbr(?string $iso): string {
    if (!$iso) return '-';
    $ts = strtotime($iso);
    if (!$ts) return h($iso);
    return date('d/m/Y H:i', $ts);
}
function brl(float $v): string {
    return 'R$ ' . number_format($v, 2, ',', '.');
}

/* ================== BUSCA: SOMENTE APROVADAS ==================
   Agora somando diretamente nas colunas da tabela `solicitacoes_b2b_itens`:
   - quantidade  -> SUM(i.quantidade)  AS total_qtd
   - subtotal    -> SUM(i.subtotal)    AS total_valor
*/
$sql = "
    SELECT
        s.id,
        s.id_matriz,
        s.id_solicitante,
        s.status,
        s.created_at,
        u.nome AS nome_solicitante,
        COUNT(i.id)                         AS total_itens,
        COALESCE(SUM(i.quantidade), 0)      AS total_qtd,
        COALESCE(SUM(i.subtotal), 0.00)     AS total_valor
    FROM solicitacoes_b2b s
    LEFT JOIN solicitacoes_b2b_itens i
        ON i.solicitacao_id = s.id
    LEFT JOIN usuarios_peca u
        ON u.empresa_cnpj = s.id_solicitante OR u.id = s.id_solicitante
    WHERE s.id_matriz = :empresa
      AND s.status = 'aprovado'
    GROUP BY s.id, s.id_matriz, s.id_solicitante, s.status, s.created_at, u.nome
    ORDER BY s.created_at DESC, s.id DESC
    LIMIT 300
";

$linhas = [];
try {
    $st = $pdo->prepare($sql);
    $st->execute([':empresa' => $idSelecionado]);
    $linhas = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $linhas = [];
}

/* ================== HTML ================== */
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Transferências — Aguardando envio</title>
    <?php if ($favicon): ?>
        <link rel="icon" type="image/png" href="<?= h($favicon) ?>">
    <?php endif; ?>

    <!-- CSS/Libs (ajuste p/ seu bundle) -->
    <link rel="stylesheet" href="../dashboard/assets/vendor/css/core.css">
    <link rel="stylesheet" href="../dashboard/assets/vendor/css/theme-default.css">
    <link rel="stylesheet" href="../dashboard/assets/vendor/libs/boxicons/css/boxicons.min.css">
    <link rel="stylesheet" href="../dashboard/assets/css/demo.css">
    <script src="../dashboard/assets/vendor/js/helpers.js"></script>

    <style>
        thead th { white-space: nowrap; }
        .status-badge { font-size: .78rem; }
        .actions .btn { margin-right: .25rem; }
        .table-responsive { overflow: auto; }
    </style>
</head>
<body>
<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
        <!-- Sidebar -->
        <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
            <div class="app-brand demo">
                <a href="./dashboard.php?id=<?= urlencode($idSelecionado) ?>" class="app-brand-link">
                    <span class="app-brand-logo demo"><i class="bx bxs-package"></i></span>
                    <span class="app-brand-text demo menu-text fw-bolder ms-2">Matriz & Filiais</span>
                </a>
            </div>

            <ul class="menu-inner py-1">
                <li class="menu-item">
                    <a href="./produtosSolicitados.php?id=<?= urlencode($idSelecionado) ?>" class="menu-link"><div>Produtos Solicitados</div></a>
                </li>
                <li class="menu-item">
                    <a href="./produtosEnviados.php?id=<?= urlencode($idSelecionado) ?>" class="menu-link"><div>Produtos Enviados</div></a>
                </li>
                <li class="menu-item active">
                    <a href="./transferenciasPendentes.php?id=<?= urlencode($idSelecionado) ?>" class="menu-link"><div>Transferências (Aguardando)</div></a>
                </li>
            </ul>
        </aside>

        <!-- Conteúdo -->
        <div class="layout-page">
            <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached bg-navbar-theme">
                <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                    <span class="fw-semibold">Olá, <?= h($nomeUsuario) ?> (<?= h($tipoUsuario) ?>)</span>
                </div>
            </nav>

            <div class="content-wrapper">
                <div class="container-xxl flex-grow-1 container-p-y">
                    <h5 class="pb-1 mb-4">
                        <i class="bx bx-transfer-alt"></i>
                        Transferências &nbsp;<span class="text-muted">— Solicitadas e aprovadas (aguardando envio)</span>
                    </h5>

                    <!-- Tabela -->
                    <div class="card">
                        <h5 class="card-header">Lista de Transferências</h5>
                        <div class="table-responsive text-nowrap">
                            <table class="table table-hover">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Filial</th>
                                    <th>Itens</th>
                                    <th>Qtd</th>
                                    <th>Total (R$)</th>
                                    <th>Criado</th>
                                    <!-- REMOVIDO: <th>Envio</th> -->
                                    <th>Status</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (!$linhas): ?>
                                    <tr><td colspan="8" class="text-center text-muted">Nenhuma transferência aprovada encontrada.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($linhas as $r): ?>
                                        <tr>
                                            <td><strong>TR-<?= h($r['id']) ?></strong></td>
                                            <td><?= h($r['nome_solicitante'] ?: $r['id_solicitante']) ?></td>
                                            <td><?= (int)($r['total_itens'] ?? 0) ?></td>
                                            <td><?= (int)($r['total_qtd'] ?? 0) ?></td>
                                            <td><?= brl((float)($r['total_valor'] ?? 0)) ?></td>
                                            <td><?= dtbr($r['created_at']) ?></td>
                                            <!-- REMOVIDO: coluna Envio -->
                                            <td>
                                                <!-- Força visual: “Aguardando” para tudo que vier como aprovado -->
                                                <span class="badge bg-label-secondary status-badge">Aguardando</span>
                                            </td>
                                            <td class="text-end actions">
                                                <a class="btn btn-sm btn-outline-secondary"
                                                   href="./transferenciaDetalhe.php?id=<?= urlencode($idSelecionado) ?>&tr=<?= urlencode($r['id']) ?>">
                                                    Detalhes
                                                </a>

                                                <!-- REMOVIDO: botão Marcar recebido -->

                                                <!-- Opcional: Cancelar enquanto aguarda -->
                                                <form class="d-inline" method="post" action="./transferenciaAcao.php?id=<?= urlencode($idSelecionado) ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? 'TOKEN_AQUI') ?>">
                                                    <input type="hidden" name="transferencia_id" value="<?= (int)$r['id'] ?>">
                                                    <input type="hidden" name="acao" value="cancelar">
                                                    <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Cancelar esta transferência?');">Cancelar</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div><!-- /container -->
            </div><!-- /content-wrapper -->
        </div><!-- /layout-page -->
    </div><!-- /layout-container -->
</div><!-- /layout-wrapper -->

<script src="../dashboard/assets/vendor/libs/jquery/jquery.js"></script>
<script src="../dashboard/assets/vendor/js/bootstrap.js"></script>
<script src="../dashboard/assets/vendor/js/menu.js"></script>
<script src="../dashboard/assets/js/main.js"></script>
</body>
</html>

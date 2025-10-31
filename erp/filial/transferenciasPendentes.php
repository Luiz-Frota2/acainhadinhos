<?php
/********************************************************************************************
 * transferenciasPendentes.php
 * 
 * Objetivo:
 *   - Listar SOMENTE as solicitações APROVADAS (status = 'aprovado') do módulo B2B,
 *     apresentando resumo por solicitação (itens, soma de quantidade e soma de total).
 *   - Remover a coluna "Envio" do grid.
 *   - Remover o botão "Marcar recebido" nas ações.
 *   - No grid, exibir a palavra "Aguardando" na coluna Status, independentemente do valor bruto
 *     (mas no SQL filtramos apenas 'aprovado').
 * 
 * Origem dos dados:
 *   - solicitacoes_b2b (cabeçalho)
 *   - solicitacoes_b2b_itens (itens; usamos as colunas `quantidade` e `subtotal`)
 *   - usuarios_peca (nome da filial/unidade)
 * 
 * Segurança:
 *   - Checagem de sessão + escopo por empresa (idSelecionado).
 *   - Prepared statements (PDO).
 * 
 * Observações:
 *   - O layout segue seu padrão (assets do dashboard). Ajuste caminhos se necessário.
 *   - Mantive "Cancelar" como ação OPCIONAL (pode remover a qualquer momento).
 * 
 ********************************************************************************************/

declare(strict_types=1);

/* ==========================================================================================
 * 1) AMBIENTE / DEBUG
 * ========================================================================================== */
ini_set('display_errors', '1');
error_reporting(E_ALL);

/* ==========================================================================================
 * 2) SESSÃO
 * ========================================================================================== */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* ==========================================================================================
 * 3) PARÂMETROS BÁSICOS
 * ========================================================================================== */
$idSelecionado = $_GET['id'] ?? '';
if (!$idSelecionado) {
    // Sem id de escopo, redireciona para login.
    header("Location: .././login.php");
    exit;
}

/* ==========================================================================================
 * 4) VERIFICAÇÃO DE SESSÃO BÁSICA
 * ========================================================================================== */
$usuarioAutenticado = isset($_SESSION['usuario_logado'], $_SESSION['empresa_id'], $_SESSION['tipo_empresa'], $_SESSION['usuario_id']);
if (!$usuarioAutenticado) {
    header("Location: .././login.php?id=" . urlencode($idSelecionado));
    exit;
}

/* ==========================================================================================
 * 5) CONEXÃO COM O BANCO
 * ========================================================================================== */
require '../../assets/php/conexao.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Erro: conexão indisponível.";
    exit;
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ==========================================================================================
 * 6) CARREGA USUÁRIO LOGADO (NOME / PERFIL)
 * ========================================================================================== */
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuarioId   = (int)($_SESSION['usuario_id'] ?? 0);

try {
    $sqlUsuario = "SELECT usuario, nivel FROM contas_acesso WHERE id = :id";
    $stmtUsuario = $pdo->prepare($sqlUsuario);
    $stmtUsuario->bindValue(':id', $usuarioId, PDO::PARAM_INT);
    $stmtUsuario->execute();
    $rowUsuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

    if ($rowUsuario) {
        $nomeUsuario = $rowUsuario['usuario'] ?? 'Usuário';
        $tipoUsuario = ucfirst((string)($rowUsuario['nivel'] ?? 'Comum'));
    } else {
        echo "<script>alert('Usuário não encontrado.'); window.location.href='.././login.php?id=" . htmlspecialchars(urlencode($idSelecionado)) . "';</script>";
        exit;
    }
} catch (Throwable $e) {
    echo "<script>alert('Erro ao carregar usuário: " . htmlspecialchars($e->getMessage()) . "'); history.back();</script>";
    exit;
}

/* ==========================================================================================
 * 7) AUTORIZAÇÃO POR ESCOPO (Matriz / Filial / Unidade / Franquia)
 * ========================================================================================== */
$empresaSessao = (string)($_SESSION['empresa_id'] ?? '');
$tipoSessao    = (string)($_SESSION['tipo_empresa'] ?? '');
$acessoPermitido = false;

/**
 * Regras (ajuste se precisar):
 * - principal_* → exige tipo_empresa principal e empresa_id = 'principal_1'
 * - filial_*, unidade_*, franquia_* → exige tipo correspondente e empresa_id igual ao idSelecionado
 */
if (str_starts_with($idSelecionado, 'principal_')) {
    $acessoPermitido = ($tipoSessao === 'principal' && $empresaSessao === 'principal_1');
} elseif (str_starts_with($idSelecionado, 'filial_')) {
    $acessoPermitido = ($tipoSessao === 'filial' && $empresaSessao === $idSelecionado);
} elseif (str_starts_with($idSelecionado, 'unidade_')) {
    $acessoPermitido = ($tipoSessao === 'unidade' && $empresaSessao === $idSelecionado);
} elseif (str_starts_with($idSelecionado, 'franquia_')) {
    $acessoPermitido = ($tipoSessao === 'franquia' && $empresaSessao === $idSelecionado);
}

if (!$acessoPermitido) {
    echo "<script>alert('Acesso negado para este escopo de empresa.'); window.location.href='.././login.php?id=" . htmlspecialchars(urlencode($idSelecionado)) . "';</script>";
    exit;
}

/* ==========================================================================================
 * 8) FAVICON / LOGO
 * ========================================================================================== */
$favicon = '';
try {
    $stmtFav = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1");
    $stmtFav->execute([':id' => $idSelecionado]);
    $rowFav = $stmtFav->fetch(PDO::FETCH_ASSOC);
    if ($rowFav && !empty($rowFav['imagem'])) {
        $favicon = (string)$rowFav['imagem'];
    }
} catch (Throwable $e) {
    // silencioso
}

/* ==========================================================================================
 * 9) HELPERS
 * ========================================================================================== */

/**
 * Escapa HTML com segurança.
 */
function h(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/**
 * Formata data/hora ISO para padrão brasileiro.
 */
function dtbr(?string $iso): string
{
    if (!$iso) return '-';
    $ts = strtotime($iso);
    if (!$ts) return h($iso);
    return date('d/m/Y H:i', $ts);
}

/**
 * Formata número float (BRL).
 */
function brl($v): string
{
    $n = (float)$v;
    return 'R$ ' . number_format($n, 2, ',', '.');
}

/* ==========================================================================================
 * 10) SQL PRINCIPAL — SOMENTE APROVADAS
 *      - SOMA quantidade e subtotal diretamente de solicitacoes_b2b_itens
 *      - Remove a coluna Envio (nem buscamos)
 *      - Status no front: sempre "Aguardando"
 * ========================================================================================== */

$paramEmpresa = $idSelecionado;  // escopo atual

$sqlLista = <<<SQL
SELECT
    s.id                                           AS solicitacao_id,
    s.id_matriz                                    AS id_matriz,
    s.id_solicitante                               AS id_solicitante,
    s.status                                       AS status_bruto,          -- será "aprovado" pelo filtro
    s.created_at                                   AS criado_em,
    u.nome                                         AS nome_solicitante,

    COUNT(i.id)                                    AS total_itens,           -- Qtde de linhas na itens
    COALESCE(SUM(i.quantidade), 0)                 AS total_qtd,             -- Soma das quantidades
    COALESCE(SUM(i.subtotal),  0.00)               AS total_valor            -- Soma dos subtotais (R$)

FROM solicitacoes_b2b s
LEFT JOIN solicitacoes_b2b_itens i
       ON i.solicitacao_id = s.id
LEFT JOIN usuarios_peca u
       ON (u.empresa_cnpj = s.id_solicitante OR u.id = s.id_solicitante)

WHERE s.id_matriz = :empresa
  AND s.status    = 'aprovado'                     -- Somente aprovadas!

GROUP BY
    s.id,
    s.id_matriz,
    s.id_solicitante,
    s.status,
    s.created_at,
    u.nome

ORDER BY s.created_at DESC, s.id DESC
LIMIT 300
SQL;

$linhas = [];

try {
    $stmtLista = $pdo->prepare($sqlLista);
    $stmtLista->bindValue(':empresa', $paramEmpresa, PDO::PARAM_STR);
    $stmtLista->execute();
    $linhas = $stmtLista->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // Em caso de erro, mantemos a tabela vazia e podemos exibir uma mensagem amigável no HTML
    $linhas = [];
}

/* ==========================================================================================
 * 11) (OPCIONAL) CSRF
 *      - Apenas gerando um token para usar em ações POST (se mantiver "Cancelar")
 * ========================================================================================== */
if (empty($_SESSION['csrf_token'])) {
    // token simples; troque por um gerador mais robusto, se preferir
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = (string)($_SESSION['csrf_token'] ?? '');

/* ==========================================================================================
 * 12) FUNÇÕES DE RENDERIZAÇÃO
 * ========================================================================================== */

/**
 * Renderiza a linha da tabela com as colunas definidas na especificação.
 * - Remove a coluna "Envio".
 * - Remove o botão "Marcar recebido".
 * - Mostra "Aguardando" no Status.
 */
function renderLinhaTabela(array $r, string $idSelecionado, string $csrfToken): string
{
    // Extrai e formata os valores
    $idSolicitacao  = (int)($r['solicitacao_id'] ?? 0);
    $nomeSolic      = trim((string)($r['nome_solicitante'] ?? ''));
    $idSolic        = (string)($r['id_solicitante'] ?? '');
    $labelSolic     = $nomeSolic !== '' ? $nomeSolic : $idSolic;

    $totalItens     = (int)($r['total_itens'] ?? 0);
    $totalQtd       = (int)($r['total_qtd']   ?? 0);
    $totalValor     = (float)($r['total_valor'] ?? 0.00);
    $criadoEm       = dtbr($r['criado_em'] ?? null);

    // Status “fixo” no front
    $statusBadge    = '<span class="badge bg-label-secondary status-badge">Aguardando</span>';

    // Botões de ação:
    // - Detalhes
    // - (Opcional) Cancelar — mantém para eventuais fluxos (remova se não quiser essa ação)
    $urlDetalhes = "./transferenciaDetalhe.php?id=" . urlencode($idSelecionado) . "&tr=" . urlencode((string)$idSolicitacao);

    $btnDetalhes = '<a class="btn btn-sm btn-outline-secondary" href="' . h($urlDetalhes) . '">Detalhes</a>';

    // Removido: botão "Marcar recebido"
    // Mantido: "Cancelar" (opcional). Comente este bloco se quiser remover do front.
    $btnCancelar = '
        <form class="d-inline" method="post" action="./transferenciaAcao.php?id=' . h(urlencode($idSelecionado)) . '">
            <input type="hidden" name="csrf_token" value="' . h($csrfToken) . '">
            <input type="hidden" name="transferencia_id" value="' . h((string)$idSolicitacao) . '">
            <input type="hidden" name="acao" value="cancelar">
            <button class="btn btn-sm btn-outline-danger" onclick="return confirm(\'Cancelar esta transferência?\');">Cancelar</button>
        </form>
    ';

    // Monta a TR
    $html = '';
    $html .= "<tr>\n";
    $html .= '  <td><strong>TR-' . h((string)$idSolicitacao) . "</strong></td>\n";
    $html .= '  <td>' . h($labelSolic) . "</td>\n";
    $html .= '  <td>' . h((string)$totalItens) . "</td>\n";
    $html .= '  <td>' . h((string)$totalQtd) . "</td>\n";
    $html .= '  <td>' . h(brl($totalValor)) . "</td>\n";
    $html .= '  <td>' . h($criadoEm) . "</td>\n";
    // REMOVIDO: coluna "Envio"
    $html .= '  <td>' . $statusBadge . "</td>\n";
    $html .= '  <td class="text-end actions">' . $btnDetalhes . "\n" . $btnCancelar . "</td>\n";
    $html .= "</tr>\n";

    return $html;
}

/* ==========================================================================================
 * 13) HTML
 * ========================================================================================== */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Transferências — Aguardando envio</title>

    <?php if ($favicon): ?>
        <link rel="icon" type="image/png" href="<?= h($favicon) ?>">
    <?php endif; ?>

    <!-- CSS / LIBS (ajuste conforme seu bundle local) -->
    <link rel="stylesheet" href="../dashboard/assets/vendor/css/core.css">
    <link rel="stylesheet" href="../dashboard/assets/vendor/css/theme-default.css">
    <link rel="stylesheet" href="../dashboard/assets/vendor/libs/boxicons/css/boxicons.min.css">
    <link rel="stylesheet" href="../dashboard/assets/css/demo.css">

    <script src="../dashboard/assets/vendor/js/helpers.js"></script>

    <style>
        /* Ajustes visuais do grid */
        thead th                { white-space: nowrap; }
        .status-badge           { font-size: .78rem; }
        .actions .btn           { margin-right: .25rem; }
        .table-responsive       { overflow: auto; }
        .card-header            { display: flex; align-items: center; justify-content: space-between; }
        .muted                  { color: #6c757d; }
    </style>
</head>
<body>
<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">

        <!-- SIDEBAR -->
        <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
            <div class="app-brand demo">
                <a href="./dashboard.php?id=<?= urlencode($idSelecionado) ?>" class="app-brand-link">
                    <span class="app-brand-logo demo"><i class="bx bxs-package"></i></span>
                    <span class="app-brand-text demo menu-text fw-bolder ms-2">Matriz & Filiais</span>
                </a>
            </div>

            <ul class="menu-inner py-1">
                <li class="menu-item">
                    <a href="./produtosSolicitados.php?id=<?= urlencode($idSelecionado) ?>" class="menu-link">
                        <div>Produtos Solicitados</div>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="./produtosEnviados.php?id=<?= urlencode($idSelecionado) ?>" class="menu-link">
                        <div>Produtos Enviados</div>
                    </a>
                </li>
                <li class="menu-item active">
                    <a href="./transferenciasPendentes.php?id=<?= urlencode($idSelecionado) ?>" class="menu-link">
                        <div>Transferências (Aguardando)</div>
                    </a>
                </li>
            </ul>
        </aside>
        <!-- /SIDEBAR -->

        <!-- CONTEÚDO -->
        <div class="layout-page">
            <!-- NAVBAR -->
            <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached bg-navbar-theme">
                <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                    <span class="fw-semibold">
                        Olá, <?= h($nomeUsuario) ?> (<?= h($tipoUsuario) ?>)
                    </span>
                </div>
            </nav>
            <!-- /NAVBAR -->

            <!-- WRAPPER -->
            <div class="content-wrapper">
                <div class="container-xxl flex-grow-1 container-p-y">

                    <!-- TÍTULO / DESCRIÇÃO -->
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="mb-0">
                            <i class="bx bx-transfer-alt"></i>
                            Transferências
                            <span class="muted">— Solicitações aprovadas aguardando envio</span>
                        </h5>
                    </div>

                    <!-- CARD DA TABELA -->
                    <div class="card">
                        <div class="card-header">
                            <span class="fw-semibold">Lista de Transferências</span>
                            <small class="muted">Somente status aprovado (mostrado como “Aguardando”)</small>
                        </div>

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
                                    <?php if (empty($linhas)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">
                                                Nenhuma transferência aprovada encontrada.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($linhas as $r): ?>
                                            <?= renderLinhaTabela($r, $idSelecionado, $csrfToken) ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- /CARD -->

                </div> <!-- /container -->
            </div>
            <!-- /WRAPPER -->
        </div>
        <!-- /CONTEÚDO -->

    </div><!-- /layout-container -->
</div><!-- /layout-wrapper -->

<!-- SCRIPTS (ajuste os paths conforme seu projeto) -->
<script src="../dashboard/assets/vendor/libs/jquery/jquery.js"></script>
<script src="../dashboard/assets/vendor/js/bootstrap.js"></script>
<script src="../dashboard/assets/vendor/js/menu.js"></script>
<script src="../dashboard/assets/js/main.js"></script>

</body>
</html>

<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
date_default_timezone_set('America/Manaus');

/* ================= Helpers ================= */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function json_out(array $payload, int $code=200) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
function dtbr(?string $iso) {
    if (!$iso) return '—';
    $t = strtotime($iso);
    return $t ? date('d/m/Y', $t) : '—';
}
function money_br($v) { return 'R$ ' . number_format((float)$v, 2, ',', '.'); }

/* ================= Sessão & Acesso ================= */
$idSelecionado = $_GET['id'] ?? '';
if (!$idSelecionado) { header("Location: .././login.php"); exit; }

if (
  !isset($_SESSION['usuario_logado']) ||
  !isset($_SESSION['empresa_id']) ||
  !isset($_SESSION['tipo_empresa']) ||
  !isset($_SESSION['usuario_id'])
) {
  header("Location: .././login.php?id=".urlencode($idSelecionado));
  exit;
}

/* ================= Conexão ================= */
require '../../assets/php/conexao.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo "Erro: conexão indisponível.";
    exit;
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ================= Usuário ================= */
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id  = (int)$_SESSION['usuario_id'];
try {
    $st = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id=:id");
    $st->execute([':id'=>$usuario_id]);
    if ($u = $st->fetch(PDO::FETCH_ASSOC)) {
        $nomeUsuario = $u['usuario'] ?? 'Usuário';
        $tipoUsuario = ucfirst((string)($u['nivel'] ?? 'Comum'));
    }
} catch (Throwable $e) { /* segue com defaults */ }

/* ================= ACL do idSelecionado ================= */
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
    echo "<script>alert('Acesso negado!'); location.href='.././login.php?id=".e($idSelecionado)."';</script>";
    exit;
}

/* ================= Logo ================= */
try {
    $st = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1");
    $st->execute([':id'=>$idSelecionado]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    $logoEmpresa = (!empty($row['imagem'])) ? "../../assets/img/empresa/".$row['imagem'] : "../../assets/img/favicon/logo.png";
} catch (Throwable $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png";
}

/* =======================================================
   ENDPOINTS JSON (no mesmo arquivo)
   - Detalhes:   GET  ?ajax=detalhes&id=ID
   - Aprovar:    POST ?ajax=status&id=ID&acao=aprovar
   - Reprovar:   POST ?ajax=status&id=ID&acao=reprovar&motivo=...
   Obs: Sempre garantimos que o solicitante seja Filial.
   ======================================================= */

$isAjax = isset($_GET['ajax']) || isset($_POST['ajax']);
if ($isAjax) {
    $mode = $_GET['ajax'] ?? $_POST['ajax'];

    /* ---------- Detalhes ---------- */
    if ($mode === 'detalhes') {
        try {
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) json_out(['ok'=>false,'erro'=>'ID inválido.'], 400);

            // Busca cabeçalho garantindo: id_matriz = :matriz e UNIDADE de tipo Filial
            $cab = $pdo->prepare("
                SELECT 
                    s.id, s.id_matriz, s.id_solicitante, s.solicitante_nome, s.descricao,
                    s.valor, s.vencimento, s.documento, s.status, s.observacao,
                    s.created_at, s.updated_at,
                    u.nome AS filial_nome, u.tipo AS filial_tipo
                FROM solicitacoes_pagamento s
                JOIN unidades u
                  ON u.id = CAST(REPLACE(TRIM(s.id_solicitante),'unidade_','') AS UNSIGNED)
                 AND LOWER(u.tipo) = 'filial'
                 AND u.empresa_id = :matriz
                WHERE s.id = :id
                  AND s.id_matriz = :matriz
                LIMIT 1
            ");
            $cab->execute([':id'=>$id, ':matriz'=>$idSelecionado]);
            $cabecalho = $cab->fetch(PDO::FETCH_ASSOC);
            if (!$cabecalho) json_out(['ok'=>false,'erro'=>'Registro não encontrado para esta matriz/filial.'], 404);

            // Se tiver itens/arquivos associados em outra tabela, você pode buscar aqui.
            // Exemplo fictício:
            // $it = $pdo->prepare("SELECT ... FROM solicitacoes_pagamento_itens WHERE solicitacao_id=:id");
            // $it->execute([':id'=>$id]);
            // $itens = $it->fetchAll(PDO::FETCH_ASSOC);

            json_out(['ok'=>true, 'cabecalho'=>$cabecalho /*,'itens'=>$itens*/ ]);
        } catch (Throwable $e) {
            json_out(['ok'=>false,'erro'=>$e->getMessage()], 500);
        }
    }

    /* ---------- Aprovar/Recusar ---------- */
    if ($mode === 'status') {
        try {
            $id    = (int)($_POST['id'] ?? 0);
            $acao  = strtolower(trim((string)($_POST['acao'] ?? '')));
            $motivo= trim((string)($_POST['motivo'] ?? ''));

            if ($id <= 0) json_out(['ok'=>false,'erro'=>'ID inválido.'], 400);
            if (!in_array($acao, ['aprovar','reprovar'], true)) json_out(['ok'=>false,'erro'=>'Ação inválida.'], 400);

            // Garante que o registro existe, pertence à mesma matriz e é de Filial
            $chk = $pdo->prepare("
                SELECT s.id, s.status
                FROM solicitacoes_pagamento s
                JOIN unidades u
                  ON u.id = CAST(REPLACE(TRIM(s.id_solicitante),'unidade_','') AS UNSIGNED)
                 AND LOWER(u.tipo) = 'filial'
                 AND u.empresa_id = :matriz
                WHERE s.id = :id
                  AND s.id_matriz = :matriz
                LIMIT 1
            ");
            $chk->execute([':id'=>$id, ':matriz'=>$idSelecionado]);
            $r = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$r) json_out(['ok'=>false,'erro'=>'Registro não encontrado ou fora do escopo.'], 404);
            if (($r['status'] ?? '') !== 'pendente') {
                json_out(['ok'=>false,'erro'=>'Somente solicitações pendentes podem ser alteradas.'], 409);
            }

            $novo = ($acao==='aprovar') ? 'aprovado' : 'reprovado';

            // Atualiza status (ajuste colunas se houver campos como aprovado_em, motivo_recusa etc.)
            if ($acao === 'aprovar') {
                $up = $pdo->prepare("UPDATE solicitacoes_pagamento SET status='aprovado', updated_at=NOW() WHERE id=:id LIMIT 1");
                $up->execute([':id'=>$id]);
            } else {
                // se existir coluna 'motivo_recusa', descomente:
                // $up = $pdo->prepare("UPDATE solicitacoes_pagamento SET status='reprovado', motivo_recusa=:m, updated_at=NOW() WHERE id=:id LIMIT 1");
                // $up->execute([':m'=>$motivo, ':id'=>$id]);
                $up = $pdo->prepare("UPDATE solicitacoes_pagamento SET status='reprovado', updated_at=NOW() WHERE id=:id LIMIT 1");
                $up->execute([':id'=>$id]);
            }

            json_out(['ok'=>true,'novo_status'=>$novo]);
        } catch (Throwable $e) {
            json_out(['ok'=>false,'erro'=>$e->getMessage()], 500);
        }
    }

    // Se não caiu em nenhum endpoint conhecido:
    json_out(['ok'=>false,'erro'=>'Endpoint inválido.'], 400);
}

/* ================== LISTAGEM (HTML) ==================
   Filtra SOMENTE: unidades.tipo = 'Filial' (case-insensitive) e
   unidades.empresa_id = :idSelecionado (matriz atual).
   Mostra todos os status, mas os botões Aprovar/Recusar só
   aparecem quando status = 'pendente'.
======================================================= */

$lista = [];
try {
    $sql = "
        SELECT
            s.id,
            s.id_solicitante,
            s.solicitante_nome,
            s.descricao,
            s.valor,
            s.vencimento,
            s.documento,
            s.status,
            u.nome AS filial_nome
        FROM solicitacoes_pagamento s
        JOIN unidades u
          ON u.id = CAST(REPLACE(TRIM(s.id_solicitante),'unidade_','') AS UNSIGNED)
         AND LOWER(u.tipo) = 'filial'
         AND u.empresa_id = :matriz
        WHERE s.id_matriz = :matriz
        ORDER BY s.id DESC
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':matriz'=>$idSelecionado]);
    $lista = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $lista = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>ERP - Pagamentos (Filiais)</title>
<link rel="icon" type="image/x-icon" href="<?= e($logoEmpresa) ?>" />
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
<link rel="stylesheet" href="../../assets/vendor/css/core.css" />
<link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" />
<link rel="stylesheet" href="../../assets/css/demo.css" />
<link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
<link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />
<script src="../../assets/vendor/js/helpers.js"></script>
<script src="../../assets/js/config.js"></script>
<style>
.status-badge{font-size:.78rem}
.table thead th{white-space:nowrap}
</style>
</head>
<body>
<div class="layout-wrapper layout-content-navbar">
<div class="layout-container">
    <!-- Menu lateral (mesmo padrão do seu template) -->
    <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
        <div class="app-brand demo">
            <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="app-brand-link">
                <span class="app-brand-text demo menu-text fw-bolder ms-2">Açaínhadinhos</span>
            </a>
            <a href="#" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
                <i class="bx bx-chevron-left bx-sm align-middle"></i>
            </a>
        </div>
        <div class="menu-inner-shadow"></div>
        <ul class="menu-inner py-1">
            <li class="menu-item"><a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-home-circle"></i><div>Dashboard</div></a></li>
            <li class="menu-header small text-uppercase"><span class="menu-header-text">Administração Filiais</span></li>
            <li class="menu-item active open">
                <a href="javascript:void(0);" class="menu-link menu-toggle">
                    <i class="menu-icon tf-icons bx bx-briefcase"></i><div>B2B - Matriz</div>
                </a>
                <ul class="menu-sub active">
                    <li class="menu-item active"><a href="./contasFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><div>Pagamentos Solic.</div></a></li>
                    <li class="menu-item"><a href="./produtosSolicitados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><div>Produtos Solicitados</div></a></li>
                    <li class="menu-item"><a href="./produtosEnviados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><div>Produtos Enviados</div></a></li>
                    <li class="menu-item"><a href="./transferenciasPendentes.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><div>Transf. Pendentes</div></a></li>
                    <li class="menu-item"><a href="./historicoTransferencias.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><div>Histórico Transf.</div></a></li>
                    <li class="menu-item"><a href="./estoqueMatriz.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><div>Estoque Matriz</div></a></li>
                    <li class="menu-item"><a href="./relatoriosB2B.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><div>Relatórios B2B</div></a></li>
                </ul>
            </li>
            <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
            <li class="menu-item"><a href="../rh/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-group"></i><div>RH</div></a></li>
            <li class="menu-item"><a href="../financas/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-dollar"></i><div>Finanças</div></a></li>
            <li class="menu-item"><a href="../pdv/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-desktop"></i><div>PDV</div></a></li>
            <li class="menu-item"><a href="../empresa/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-briefcase"></i><div>Empresa</div></a></li>
            <li class="menu-item"><a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-box"></i><div>Estoque</div></a></li>
            <li class="menu-item"><a href="../franquia/index.php?id=principal_1" class="menu-link"><i class="menu-icon tf-icons bx bx-store"></i><div>Franquias</div></a></li>
            <li class="menu-item"><a href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-group"></i><div>Usuários</div></a></li>
            <li class="menu-item mb-5"><a href="https://wa.me/92991515710" target="_blank" class="menu-link"><i class="menu-icon tf-icons bx bx-support"></i><div>Suporte</div></a></li>
        </ul>
    </aside>

    <div class="layout-page">
        <!-- Navbar -->
        <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
            <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none"><a class="nav-item nav-link px-0 me-xl-4" href="#"><i class="bx bx-menu bx-sm"></i></a></div>
            <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                <div class="navbar-nav align-items-center"><div class="nav-item d-flex align-items-center"></div></div>
                <ul class="navbar-nav flex-row align-items-center ms-auto">
                    <li class="nav-item navbar-dropdown dropdown-user dropdown">
                        <a class="nav-link dropdown-toggle hide-arrow" href="#" data-bs-toggle="dropdown"><div class="avatar avatar-online"><img src="<?= e($logoEmpresa) ?>" class="w-px-40 h-auto rounded-circle" /></div></a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#"><div class="d-flex"><div class="flex-shrink-0 me-3"><div class="avatar avatar-online"><img src="<?= e($logoEmpresa) ?>" class="w-px-40 h-auto rounded-circle" /></div></div><div class="flex-grow-1"><span class="fw-semibold d-block"><?= e($nomeUsuario) ?></span><small class="text-muted"><?= e($tipoUsuario) ?></small></div></div></a></li>
                            <li><div class="dropdown-divider"></div></li>
                            <li><a class="dropdown-item" href="./contaUsuario.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-user me-2"></i><span>Minha Conta</span></a></li>
                            <li><a class="dropdown-item" href="#"><i class="bx bx-cog me-2"></i><span>Configurações</span></a></li>
                            <li><div class="dropdown-divider"></div></li>
                            <li><a class="dropdown-item" href="../logout.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-power-off me-2"></i><span>Sair</span></a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>
        <!-- /Navbar -->

        <!-- Content -->
        <div class="container-xxl flex-grow-1 container-p-y">
            <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a href="#">Filiais</a> / </span>Pagamentos Solicitados</h4>
            <h5 class="fw-bold mt-3 mb-3"><span class="text-muted fw-light">Somente solicitações originadas por Filiais</span></h5>

            <div class="card">
                <h5 class="card-header">Lista de Pagamentos (Filiais)</h5>
                <div class="table-responsive text-nowrap">
                    <table class="table table-hover align-middle">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Filial</th>
                            <th>Solicitante</th>
                            <th>Descrição</th>
                            <th>Valor</th>
                            <th>Vencimento</th>
                            <th>Documento</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($lista)): ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">Nenhuma solicitação de Filial encontrada.</td></tr>
                        <?php else: foreach ($lista as $r):
                            $isPendente = ($r['status'] === 'pendente');
                            $badge = match ($r['status']) {
                                'aprovado'  => 'bg-label-success',
                                'reprovado' => 'bg-label-danger',
                                'pendente'  => 'bg-label-warning',
                                default     => 'bg-label-secondary'
                            };
                        ?>
                            <tr id="row-<?= (int)$r['id'] ?>">
                                <td><strong><?= (int)$r['id'] ?></strong></td>
                                <td><?= e($r['filial_nome'] ?? '—') ?></td>
                                <td><?= e($r['solicitante_nome'] ?? '—') ?></td>
                                <td><?= e($r['descricao'] ?? '—') ?></td>
                                <td><?= money_br($r['valor'] ?? 0) ?></td>
                                <td><?= dtbr($r['vencimento'] ?? null) ?></td>
                                <td>
                                    <?php if (!empty($r['documento'])): ?>
                                        <a href="<?= e($r['documento']) ?>" target="_blank">Abrir</a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= $badge ?> status-badge" id="st-<?= (int)$r['id'] ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                                <td class="text-end" id="act-<?= (int)$r['id'] ?>">
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalDetalhes" data-id="<?= (int)$r['id'] ?>">Detalhes</button>
                                    <?php if ($isPendente): ?>
                                        <button class="btn btn-sm btn-outline-success" onclick="alterarStatus(<?= (int)$r['id'] ?>,'aprovar')">Aprovar</button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="abrirRecusa(<?= (int)$r['id'] ?>)">Recusar</button>
                                    <?php endif; ?>
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
                            <h5 class="modal-title">Detalhes da Solicitação</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                        </div>
                        <div class="modal-body">
                            <div id="det-erro" class="text-danger mb-2" style="display:none"></div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <p><strong>Filial:</strong> <span id="det-filial">—</span></p>
                                    <p><strong>Solicitante:</strong> <span id="det-solic">—</span></p>
                                    <p><strong>Descrição:</strong> <span id="det-desc">—</span></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Valor:</strong> <span id="det-valor">—</span></p>
                                    <p><strong>Vencimento:</strong> <span id="det-venc">—</span></p>
                                    <p><strong>Status:</strong> <span id="det-status">—</span></p>
                                </div>
                                <div class="col-12">
                                    <p><strong>Documento:</strong> <span id="det-doc">—</span></p>
                                    <p><strong>Observações:</strong> <span id="det-obs">—</span></p>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Recusar -->
            <div class="modal fade" id="modalRecusar" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title">Motivo da Recusa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
                            <input type="hidden" id="rec-id" />
                            <label class="form-label">Motivo (opcional)</label>
                            <textarea id="rec-motivo" class="form-control" rows="3" placeholder="Descreva o motivo da recusa..."></textarea>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-danger" onclick="confirmarRecusa()">Confirmar Recusa</button>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /container -->
        <footer class="content-footer footer bg-footer-theme text-center">
            <div class="container-xxl d-flex py-2 flex-md-row flex-column justify-content-center">
                <div class="mb-2 mb-md-0">
                    &copy; <script>document.write(new Date().getFullYear());</script>, <strong>Açaínhadinhos</strong>. Todos os direitos reservados. Desenvolvido por <strong>CodeGeek</strong>.
                </div>
            </div>
        </footer>
    </div><!-- /layout-page -->
</div><!-- /layout-container -->
</div><!-- /layout-wrapper -->

<!-- Core JS -->
<script src="../../assets/vendor/libs/jquery/jquery.js"></script>
<script src="../../assets/vendor/libs/popper/popper.js"></script>
<script src="../../assets/vendor/js/bootstrap.js"></script>
<script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
<script src="../../assets/vendor/js/menu.js"></script>
<script src="../../assets/vendor/libs/apex-charts/apexcharts.js"></script>
<script src="../../assets/js/main.js"></script>
<script>
(function(){
  // Modal de detalhes: busca JSON no MESMO arquivo
  const det = document.getElementById('modalDetalhes');
  det.addEventListener('show.bs.modal', function (ev) {
    const btn = ev.relatedTarget;
    if (!btn) return;
    const id = btn.getAttribute('data-id');
    const url = new URL(window.location.href);
    url.searchParams.set('ajax','detalhes');
    url.searchParams.set('id', id);

    // limpa
    setText('det-erro','',true);
    setText('det-filial','—'); setText('det-solic','—'); setText('det-desc','—');
    setText('det-valor','—'); setText('det-venc','—'); setText('det-status','—'); setText('det-doc','—'); setText('det-obs','—');

    fetch(url.toString(), {credentials:'same-origin'})
      .then(r=>r.text())
      .then(txt=>{
        let data;
        try {
          data = JSON.parse(txt);
        } catch (e) {
          throw new Error('Resposta inválida do servidor.');
        }
        if (!data.ok) throw new Error(data.erro || 'Falha ao carregar.');
        const c = data.cabecalho || {};
        setText('det-filial', c.filial_nome || '—');
        setText('det-solic',  c.solicitante_nome || '—');
        setText('det-desc',   c.descricao || '—');
        setText('det-valor',  formatMoney(c.valor));
        setText('det-venc',   fmtDate(c.vencimento));
        setText('det-status', (c.status||'—').toString().charAt(0).toUpperCase() + (c.status||'—').toString().slice(1));
        setText('det-obs',    c.observacao || '—');
        if (c.documento) {
          document.getElementById('det-doc').innerHTML = '<a href="'+escapeHtml(c.documento)+'" target="_blank">Abrir</a>';
        }
      })
      .catch(err=>{
        setText('det-erro', err.message, false);
        document.getElementById('det-erro').style.display = 'block';
      });
  });

  window.abrirRecusa = function(id){
    document.getElementById('rec-id').value = String(id);
    document.getElementById('rec-motivo').value = '';
    const m = new bootstrap.Modal(document.getElementById('modalRecusar'));
    m.show();
  };

  window.confirmarRecusa = function(){
    const id = document.getElementById('rec-id').value;
    const motivo = document.getElementById('rec-motivo').value;
    alterarStatus(id,'reprovar',motivo);
    const m = bootstrap.Modal.getInstance(document.getElementById('modalRecusar'));
    if (m) m.hide();
  };

  window.alterarStatus = function(id, acao, motivo=''){
    const form = new FormData();
    form.set('ajax','status');
    form.set('id', id);
    form.set('acao', acao);
    if (motivo) form.set('motivo', motivo);

    fetch(window.location.href, {method:'POST', body: form, credentials:'same-origin'})
      .then(r=>r.text())
      .then(txt=>{
        let data;
        try { data = JSON.parse(txt); } catch(e){ throw new Error('Resposta inválida do servidor.'); }
        if (!data.ok) throw new Error(data.erro || 'Falha ao alterar status.');

        // Atualiza a linha na tabela
        const novo = data.novo_status || '';
        const stEl = document.getElementById('st-'+id);
        const actEl = document.getElementById('act-'+id);
        if (stEl) {
          stEl.textContent = novo.charAt(0).toUpperCase()+novo.slice(1);
          stEl.classList.remove('bg-label-warning','bg-label-success','bg-label-danger','bg-label-secondary');
          stEl.classList.add(novo==='aprovado'?'bg-label-success':(novo==='reprovado'?'bg-label-danger':'bg-label-secondary'));
        }
        if (actEl) {
          // Mantém apenas botão Detalhes
          const btnDetalhes = actEl.querySelector('[data-bs-target="#modalDetalhes"]');
          actEl.innerHTML = '';
          if (btnDetalhes) actEl.appendChild(btnDetalhes);
        }
      })
      .catch(err=>{
        alert(err.message);
      });
  };

  function setText(id, txt, isErr=false){
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = String(txt);
    if (isErr) el.style.display = txt ? 'block' : 'none';
  }
  function fmtDate(iso){
    if (!iso) return '—';
    const d = new Date(String(iso).replace(' ','T'));
    if (isNaN(d)) return '—';
    const dd = String(d.getDate()).padStart(2,'0');
    const mm = String(d.getMonth()+1).padStart(2,'0');
    const yyyy = d.getFullYear();
    return `${dd}/${mm}/${yyyy}`;
  }
  function formatMoney(v){
    const n = Number(v || 0);
    return n.toLocaleString('pt-BR', {style:'currency', currency:'BRL'});
  }
  function escapeHtml(s){return String(s).replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
})();
</script>
</body>
</html>

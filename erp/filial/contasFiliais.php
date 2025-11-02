<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

/* ========================= Helpers ========================= */
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function moneyBr($v){ return 'R$ ' . number_format((float)$v, 2, ',', '.'); }
function isRemote($path){ return (bool)preg_match('~^https?://~i', $path); }
function cleanToken($s){ return preg_replace('~[^A-Za-z0-9_\-\.]~', '', (string)$s); }
function servePdf($fullPath, $dispName, $asAttachment = false){
    if (!is_file($fullPath) || !is_readable($fullPath)) {
        http_response_code(404); echo "Arquivo não encontrado."; exit;
    }
    $size = @filesize($fullPath);
    header('Content-Type: application/pdf');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=60');
    header('Accept-Ranges: none');
    header('Content-Disposition: ' . ($asAttachment ? 'attachment' : 'inline') . '; filename="' . $dispName . '"');
    if ($size !== false) header('Content-Length: ' . $size);
    readfile($fullPath); exit;
}

/* ====== BASE PÚBLICA HOSTINGER (para quando vier só o nome) ====== */
const HOSTINGER_BASE = 'https://srv1885-files.hstgr.io/e9aded9b7b308c83/files/public_html/public/pagamentos/';

/* ========================= Parâmetros ======================= */
$idSelecionado = $_GET['id'] ?? '';
if (!$idSelecionado) { header("Location: .././login.php"); exit; }

/* ===================== Verificação login ==================== */
if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id'])
) { header("Location: .././login.php?id=" . urlencode($idSelecionado)); exit; }

/* ========================= Conexão DB ======================= */
require '../../assets/php/conexao.php';

/* ===================== Checagem de escopo =================== */
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
if (!$acessoPermitido) { http_response_code(403); echo "Acesso negado."; exit; }

/* ============= Função utilitária para baixar o comprovante ============= */
function baixarComprovanteParaTmp(PDO $pdo, string $idSelecionado, int $pid): array {
    // Pasta tmp
    $baseTmpDir = realpath(__DIR__ . '/../../assets/tmp');
    if ($baseTmpDir === false) {
        $tryDir = __DIR__ . '/../../assets/tmp';
        if (!is_dir($tryDir)) @mkdir($tryDir, 0775, true);
        $baseTmpDir = realpath($tryDir);
    }
    if ($baseTmpDir === false) {
        throw new RuntimeException("Não foi possível preparar o diretório temporário.");
    }
    $comprovantesDir = $baseTmpDir . '/comprovantes';
    if (!is_dir($comprovantesDir)) { @mkdir($comprovantesDir, 0775, true); }

    // Busca origem (apenas Filial)
    $sql = "
        SELECT sp.id, sp.id_matriz, sp.id_solicitante, sp.comprovante_url,
               u.id AS unidade_id, u.tipo AS unidade_tipo
        FROM solicitacoes_pagamento sp
        JOIN unidades u
          ON u.id = CAST(SUBSTRING_INDEX(sp.id_solicitante, '_', -1) AS UNSIGNED)
        WHERE sp.id = :id
          AND sp.id_matriz = :matriz
          AND (u.tipo = 'Filial' OR u.tipo = 'filial')
        LIMIT 1
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':id' => $pid, ':matriz' => $idSelecionado]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || empty($row['comprovante_url'])) {
        throw new RuntimeException("Comprovante não encontrado.");
    }
    $src = trim($row['comprovante_url']);

    // Normaliza URL (se veio só o nome)
    if (!isRemote($src)) {
        $filename = basename($src);
        $filename_enc = str_replace(' ', '%20', $filename);
        $src = HOSTINGER_BASE . $filename_enc;
    }
    if (strpos($src, ' ') !== false) $src = str_replace(' ', '%20', $src);

    // Baixa via cURL
    $token   = 'pdf_' . $pid . '_' . bin2hex(random_bytes(6)) . '.pdf';
    $tmpPath = $comprovantesDir . '/' . $token;

    $ch = curl_init($src);
    $fp = fopen($tmpPath, 'wb');
    if ($fp === false) throw new RuntimeException("Falha ao criar arquivo temporário.");
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_USERAGENT      => 'Mozilla/5.0',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $ok   = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if ($ok === false || $code >= 400 || @filesize($tmpPath) === 0) {
        @unlink($tmpPath);
        throw new RuntimeException("Falha ao baixar do Hostinger.");
    }

    // Limpa temporários antigos (>1 dia)
    foreach (glob($comprovantesDir . '/pdf_*.pdf') as $f) {
        if (@filemtime($f) !== false && (time() - filemtime($f)) > 86400) { @unlink($f); }
    }

    return [$tmpPath, $token];
}

/* =================== ROTEADOR PDF (MESMO ARQ.) ===============
   Download direto (sem abrir):
   ?op=pdf&mode=download&pid=123&id=principal_1
=============================================================== */
if (($_GET['op'] ?? '') === 'pdf') {
    $mode = $_GET['mode'] ?? '';
    $pid  = isset($_GET['pid']) ? (int)$_GET['pid'] : 0;

    // Download direto SEM token 'f'
    if ($mode === 'download' && empty($_GET['f'])) {
        if (!$pid) { http_response_code(400); echo "Parâmetro 'pid' ausente."; exit; }
        try {
            [$fullPath, $token] = baixarComprovanteParaTmp($pdo, $idSelecionado, $pid);
        } catch (Throwable $e) {
            http_response_code(502); echo e($e->getMessage()); exit;
        }
        $filename = 'comprovante_' . $pid . '.pdf';
        servePdf($fullPath, $filename, true); // attachment (só baixar)
    }

    // Entrega por token (compatibilidade)
    $baseTmpDir = realpath(__DIR__ . '/../../assets/tmp');
    if ($baseTmpDir === false) {
        $tryDir = __DIR__ . '/../../assets/tmp';
        if (!is_dir($tryDir)) @mkdir($tryDir, 0775, true);
        $baseTmpDir = realpath($tryDir);
    }
    if ($baseTmpDir === false) { http_response_code(500); echo "Não foi possível preparar o diretório temporário."; exit; }
    $comprovantesDir = $baseTmpDir . '/comprovantes';
    if (!is_dir($comprovantesDir)) { @mkdir($comprovantesDir, 0775, true); }

    if ($mode === 'inline' || $mode === 'download') {
        $fileToken = cleanToken($_GET['f'] ?? '');
        if (!$fileToken) { http_response_code(400); echo "Token inválido."; exit; }
        $fullPath = realpath($comprovantesDir . '/' . $fileToken);
        if ($fullPath === false || strpos($fullPath, $comprovantesDir) !== 0) { http_response_code(400); echo "Caminho inválido."; exit; }
        $filename = 'comprovante_' . ($pid ?: 'sol') . '.pdf';
        servePdf($fullPath, $filename, $mode === 'download');
    }

    http_response_code(400); echo "Requisição inválida."; exit;
}

/* =================== Rotas AJAX (POST) no mesmo arquivo =============== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $action = $_POST['action'];

    if ($action === 'update_status') {
        $idPay     = (int)($_POST['id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';
        if (!in_array($newStatus, ['aprovado','reprovado'], true)) { echo json_encode(['ok'=>false,'msg'=>'Status inválido']); exit; }
        try {
            $up = $pdo->prepare("UPDATE solicitacoes_pagamento SET status=:st, updated_at=NOW() WHERE id=:id AND status='pendente' LIMIT 1");
            $up->execute([':st'=>$newStatus, ':id'=>$idPay]);
            echo json_encode(['ok' => $up->rowCount() > 0, 'msg' => $up->rowCount() ? '' : 'Nada atualizado.']); exit;
        } catch (PDOException $e) { echo json_encode(['ok'=>false,'msg'=>'Erro DB: '.$e->getMessage()]); exit; }
    }

    if ($action === 'get_details') {
        $idPay = (int)($_POST['id'] ?? 0);
        try {
            $sql = "
                SELECT sp.id, sp.id_matriz, sp.id_solicitante, sp.status, sp.fornecedor, sp.documento,
                       sp.descricao, sp.vencimento, sp.valor, sp.comprovante_url,
                       u.id AS unidade_id, u.nome AS unidade_nome, u.tipo AS unidade_tipo
                FROM solicitacoes_pagamento sp
                JOIN unidades u
                  ON u.id = CAST(SUBSTRING_INDEX(sp.id_solicitante, '_', -1) AS UNSIGNED)
                WHERE sp.id=:id AND sp.id_matriz=:matriz AND (u.tipo='Filial' OR u.tipo='filial')
                LIMIT 1
            ";
            $st = $pdo->prepare($sql);
            $st->execute([':id'=>$idPay, ':matriz'=>$idSelecionado]);
            if ($row = $st->fetch(PDO::FETCH_ASSOC)) { echo json_encode(['ok'=>true,'data'=>$row]); }
            else { echo json_encode(['ok'=>false,'msg'=>'Solicitação não encontrada.']); }
            exit;
        } catch (PDOException $e) { echo json_encode(['ok'=>false,'msg'=>'Erro DB: '.$e->getMessage()]); exit; }
    }

    echo json_encode(['ok'=>false,'msg'=>'Ação inválida']); exit;
}

/* ====================== UI: usuário/logo ====================== */
$nomeUsuario = 'Usuário'; $tipoUsuario = 'Comum';
$usuario_id  = (int)$_SESSION['usuario_id'];
try {
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id=:id");
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    if ($u = $stmt->fetch(PDO::FETCH_ASSOC)) { $nomeUsuario = $u['usuario'] ?? 'Usuário'; $tipoUsuario = ucfirst((string)($u['nivel'] ?? 'Comum')); }
    else { echo "<script>alert('Usuário não encontrado.'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>"; exit; }
} catch (PDOException $e) { echo "<script>alert('Erro ao carregar usuário: " . e($e->getMessage()) . "'); history.back();</script>"; exit; }

try {
    $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado=:id LIMIT 1");
    $stmt->bindParam(':id', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);
    $logoEmpresa = (!empty($empresaSobre) && !empty($empresaSobre['imagem'])) ? "../../assets/img/empresa/" . $empresaSobre['imagem'] : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) { $logoEmpresa = "../../assets/img/favicon/logo.png"; }

/* ====================== LISTAGEM (Filiais) ==================== */
$pagamentos = [];
try {
    $sql = "
        SELECT sp.id, sp.id_matriz, sp.id_solicitante, sp.status, sp.fornecedor, sp.documento,
               sp.descricao, sp.vencimento, sp.valor, COALESCE(sp.comprovante_url,'') AS comprovante_url,
               u.id AS unidade_id, u.nome AS unidade_nome, u.tipo AS unidade_tipo
        FROM solicitacoes_pagamento sp
        JOIN unidades u
          ON u.id = CAST(SUBSTRING_INDEX(sp.id_solicitante, '_', -1) AS UNSIGNED)
        WHERE sp.id_matriz = :id_matriz
          AND (u.tipo='Filial' OR u.tipo='filial')
        ORDER BY sp.id DESC
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':id_matriz' => $idSelecionado]);
    $pagamentos = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $pagamentos = []; }

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
        .status-badge { text-transform: capitalize; }
        .table td, .table th { vertical-align: middle; }
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
                <li class="menu-item"><a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                    <i class="menu-icon tf-icons bx bx-home-circle"></i><div>Dashboard</div></a>
                </li>

                <li class="menu-header small text-uppercase"><span class="menu-header-text">Administração Filiais</span></li>

                <li class="menu-item">
                    <a href="javascript:void(0);" class="menu-link menu-toggle">
                        <i class="menu-icon tf-icons bx bx-building"></i><div>Filiais</div>
                    </a>
                    <ul class="menu-sub">
                        <li class="menu-item"><a href="./filialAdicionada.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><div>Adicionadas</div></a></li>
                    </ul>
                </li>

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
                <li class="menu-item"><a href="../rh/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-group"></i><div>RH</div></a></li>
                <li class="menu-item"><a href="../financas/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-dollar"></i><div>Finanças</div></a></li>
                <li class="menu-item"><a href="../pdv/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-desktop"></i><div>PDV</div></a></li>
                <li class="menu-item"><a href="../empresa/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-briefcase"></i><div>Empresa</div></a></li>
                <li class="menu-item"><a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-box"></i><div>Estoque</div></a></li>
                <li class="menu-item"><a href="../franquia/index.php?id=principal_1" class="menu-link"><i class="menu-icon tf-icons bx bx-store"></i><div>Franquias</div></a></li>
                <li class="menu-item"><a href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-group"></i><div>Usuários</div></a></li>
                <li class="menu-item mb-5"><a href="https://wa.me/92991515710" target="_blank" class="menu-link"><i class="menu-icon tf-icons bx bx-support"></i><div>Suporte</div></a></li>
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
                    <div class="navbar-nav align-items-center"><div class="nav-item d-flex align-items-center"></div></div>
                    <ul class="navbar-nav flex-row align-items-center ms-auto">
                        <li class="nav-item navbar-dropdown dropdown-user dropdown">
                            <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false">
                                <div class="avatar avatar-online"><img src="<?= e($logoEmpresa) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" /></div>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownUser">
                                <li>
                                    <a class="dropdown-item" href="#">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0 me-3"><div class="avatar avatar-online"><img src="<?= e($logoEmpresa) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" /></div></div>
                                            <div class="flex-grow-1"><span class="fw-semibold d-block"><?= e($nomeUsuario) ?></span><small class="text-muted"><?= e($tipoUsuario) ?></small></div>
                                        </div>
                                    </a>
                                </li>
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
            <!-- / Navbar -->

            <!-- Content -->
            <div class="container-xxl flex-grow-1 container-p-y">
                <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a href="#">Filiais</a> /</span> Pagamentos Solicitados</h4>
                <h5 class="fw-bold mt-3 mb-3"><span class="text-muted fw-light">Visualize e gerencie as solicitações de pagamento das filiais</span></h5>

                <div class="card">
                    <h5 class="card-header">Lista de Pagamentos (apenas Filiais)</h5>
                    <div class="table-responsive text-nowrap">
                        <table class="table table-hover">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Filial</th>
                                <th>Solicitante</th>
                                <th>Comprovante</th> <!-- ÚNICA COLUNA DE ARQUIVO -->
                                <th>Status</th>
                                <th class="text-end">Ações</th>
                            </tr>
                            </thead>
                            <tbody class="table-border-bottom-0" id="tbody-pagamentos">
                            <?php if (!$pagamentos): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma solicitação encontrada.</td></tr>
                            <?php else: foreach ($pagamentos as $p): $id = (int)$p['id']; $isPendente = ($p['status'] === 'pendente'); ?>
                                <tr id="row-<?= $id ?>">
                                    <td><?= $id ?></td>
                                    <td><strong><?= e($p['unidade_nome'] ?? ('Unidade #'.($p['unidade_id'] ?? ''))) ?></strong></td>
                                    <td><?= e($p['id_solicitante']) ?></td>

                                    <!-- ===== ÚNICA COLUNA DE ARQUIVO: COMPROVANTE (BAIXA DIRETO) ===== -->
                                    <td>
                                        <?php if (!empty($p['comprovante_url'])): ?>
                                            <a href="./contasFiliais.php?op=pdf&mode=download&pid=<?= $id ?>&id=<?= urlencode($idSelecionado) ?>" class="text-primary">Baixar PDF</a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php $badge='bg-label-secondary';
                                        if ($p['status']==='pendente') $badge='bg-label-warning';
                                        if ($p['status']==='aprovado') $badge='bg-label-success';
                                        if ($p['status']==='reprovado') $badge='bg-label-danger'; ?>
                                        <span class="badge <?= $badge ?> status-badge" id="status-<?= $id ?>"><?= e($p['status']) ?></span>
                                    </td>
                                    <td class="text-end" id="acoes-<?= $id ?>">
                                        <?php if ($isPendente): ?>
                                            <button class="btn btn-sm btn-outline-success me-1 btn-aprovar" data-id="<?= $id ?>">Aprovar</button>
                                            <button class="btn btn-sm btn-outline-danger me-1 btn-recusar" data-id="<?= $id ?>">Recusar</button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-outline-secondary btn-detalhes" data-id="<?= $id ?>" data-bs-toggle="modal" data-bs-target="#modalDetalhes">Detalhes</button>
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
                                <div id="detalhes-conteudo"><div class="text-center text-muted py-3">Carregando…</div></div>
                            </div>
                            <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button></div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- / Content -->

            <footer class="content-footer footer bg-footer-theme text-center">
                <div class="container-xxl d-flex py-2 flex-md-row flex-column justify-content-center">
                    <div class="mb-2 mb-md-0">
                        &copy; <script>document.write(new Date().getFullYear());</script>, <strong>Açaínhadinhos</strong>. Todos os direitos reservados. Desenvolvido por <strong>CodeGeek</strong>.
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
(function(){
    function post(action, payload){
        const data = new URLSearchParams();
        data.append('action', action);
        Object.keys(payload||{}).forEach(k => data.append(k, payload[k]));
        return fetch(location.href, {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
            body: data.toString()
        }).then(r => r.json());
    }

    function setStatusRow(id, newStatus){
        const $status = document.getElementById('status-' + id);
        const $acoes  = document.getElementById('acoes-' + id);
        if ($status){
            $status.textContent = newStatus;
            $status.classList.remove('bg-label-warning','bg-label-success','bg-label-danger','bg-label-secondary');
            if (newStatus === 'pendente') $status.classList.add('bg-label-warning');
            else if (newStatus === 'aprovado') $status.classList.add('bg-label-success');
            else if (newStatus === 'reprovado') $status.classList.add('bg-label-danger');
            else $status.classList.add('bg-label-secondary');
        }
        if ($acoes && newStatus !== 'pendente'){
            $acoes.querySelectorAll('.btn-aprovar, .btn-recusar').forEach(btn => btn.remove());
        }
    }

    document.getElementById('tbody-pagamentos')?.addEventListener('click', function(e){
        const t = e.target;

        if (t.classList.contains('btn-aprovar')){
            const id = t.getAttribute('data-id');
            if (!id) return;
            if (!confirm('Confirmar aprovação deste pagamento?')) return;
            post('update_status', {id, status:'aprovado'})
              .then(json => { if (json && json.ok) setStatusRow(id, 'aprovado'); else alert(json?.msg || 'Erro ao aprovar.'); })
              .catch(() => alert('Falha de rede ao aprovar.'));
        }

        if (t.classList.contains('btn-recusar')){
            const id = t.getAttribute('data-id');
            if (!id) return;
            const _motivo = prompt('Motivo da recusa (opcional):', '');
            if (!confirm('Confirmar recusa deste pagamento?')) return;
            post('update_status', {id, status:'reprovado'})
              .then(json => { if (json && json.ok) setStatusRow(id, 'reprovado'); else alert(json?.msg || 'Erro ao recusar.'); })
              .catch(() => alert('Falha de rede ao recusar.'));
        }

        if (t.classList.contains('btn-detalhes')){
            const id = t.getAttribute('data-id');
            const box = document.getElementById('detalhes-conteudo');
            if (box){ box.innerHTML = '<div class="text-center text-muted py-3">Carregando…</div>'; }
            post('get_details', {id})
              .then(json => {
                  if (json && json.ok && json.data){
                      const d = json.data;
                      const html = `
                        <div class="row g-3">
                          <div class="col-md-6">
                            <p><strong>Filial:</strong> ${escapeHtml(d.unidade_nome ?? '')} (ID: ${escapeHtml(d.unidade_id ?? '')})</p>
                            <p><strong>Solicitante:</strong> ${escapeHtml(d.id_solicitante ?? '')}</p>
                            <p><strong>Status:</strong> ${escapeHtml(d.status ?? '')}</p>
                            <p><strong>Fornecedor:</strong> ${escapeHtml(d.fornecedor ?? '')}</p>
                          </div>
                          <div class="col-md-6">
                            <p><strong>Comprovante:</strong> ${
                              d.comprovante_url
                                ? `<a href="./contasFiliais.php?op=pdf&mode=download&pid=${Number(d.id)}&id=<?= e($idSelecionado) ?>" target="_blank">Baixar PDF</a>`
                                : '<span class="text-muted">—</span>'
                            }</p>
                            <p><strong>Descrição:</strong> ${escapeHtml(d.descricao ?? '')}</p>
                            <p><strong>Valor:</strong> ${formatMoney(d.valor)}</p>
                            <p><strong>Vencimento:</strong> ${formatDate(d.vencimento)}</p>
                          </div>
                        </div>`;
                      if (box){ box.innerHTML = html; }
                  } else {
                      if (box){ box.innerHTML = `<div class="text-danger">Não foi possível carregar os detalhes.</div>`; }
                  }
              })
              .catch(() => { if (box){ box.innerHTML = `<div class="text-danger">Falha de rede ao buscar detalhes.</div>`; }});
        }
    });

    function formatDate(iso){
        if (!iso) return '—';
        const d = new Date(iso);
        if (isNaN(d.getTime())) {
            const parts = String(iso).split(' ')[0]?.split('-');
            if (parts && parts.length === 3) return `${parts[2].padStart(2,'0')}/${parts[1].padStart(2,'0')}/${parts[0]}`;
            return '—';
        }
        const dd = String(d.getDate()).padStart(2,'0');
        const mm = String(d.getMonth()+1).padStart(2,'0');
        const yyyy = d.getFullYear();
        return `${dd}/${mm}/${yyyy}`;
    }
    function formatMoney(v){ if (v == null) return 'R$ 0,00'; return Number(v).toLocaleString('pt-BR', {style:'currency', currency:'BRL'}); }
    function escapeHtml(s){ return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }
})();
</script>
</body>
</html>

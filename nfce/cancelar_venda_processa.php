<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
header('Content-Type: text/html; charset=utf-8');

// ==== Helpers ================================================================
function getBodyJson(): array {
    $ct  = $_SERVER['CONTENT_TYPE'] ?? '';
    $raw = file_get_contents('php://input');
    if ($raw && stripos($ct, 'application/json') !== false) {
        try {
            $j = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($j)) return $j;
        } catch (Throwable $e) { /* ignora */ }
    }
    return [];
}
function val($arr, $key, $default=''){ return isset($arr[$key]) ? trim((string)$arr[$key]) : $default; }
function onlyDigits($s){ return preg_replace('/\D+/', '', (string)$s); }
function redirVendaRapida($empresaId, $ok, $modelo, $msg=''){
    $empresaId = urlencode((string)$empresaId);
    $modelo    = urlencode((string)$modelo);
    $status    = $ok ? 'ok' : 'erro';
    $msg       = $msg ? ('&msg='.urlencode($msg)) : '';
    header("Location: ../frentedeloja/caixa/vendaRapida.php?id={$empresaId}&cancel={$status}&modelo={$modelo}{$msg}");
    exit;
}

// ==== Normaliza entrada (POST + JSON) =======================================
$in = array_merge($_POST, getBodyJson());

$modelo           = val($in, 'modelo');            // 'por_chave' | 'por_motivo' | 'por_substituicao'
$empresa_id       = val($in, 'empresa_id', $_GET['id'] ?? $_GET['empresa_id'] ?? '');
$chave            = onlyDigits(val($in, 'chave'));
$last4            = onlyDigits(val($in, 'last4'));
$motivo           = val($in, 'motivo');
$chaveSubstituta  = onlyDigits(val($in, 'chave_substituta'));

// ==== Conexão PDO (procura em caminhos comuns do seu projeto) ===============
$pdo = null;
$candidates = [
    __DIR__ . '/assets/conexao.php',
    __DIR__ . '/../assets/conexao.php',
    __DIR__ . '/../assets/php/conexao.php',
    __DIR__ . '/../../assets/conexao.php',
    __DIR__ . '/../../assets/php/conexao.php',
    $_SERVER['DOCUMENT_ROOT'] . '/assets/conexao.php',
    $_SERVER['DOCUMENT_ROOT'] . '/assets/php/conexao.php',
    __DIR__ . '/../conexao/conexao.php',
    __DIR__ . '/../../conexao/conexao.php',
];
foreach ($candidates as $p) {
    if (is_file($p)) {
        require_once $p;
        if (isset($pdo) && $pdo instanceof PDO) break;
    }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $host = getenv('DB_HOST'); $db = getenv('DB_NAME'); $user = getenv('DB_USER'); $pass = getenv('DB_PASS');
    if ($host && $db && $user !== false) {
        $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";
        try { $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); } catch (Throwable $e) { /* passa */ }
    }
}

// ==== Se empresa_id faltou, tenta descobrir via chave_nfce ===================
if (!$empresa_id && $pdo && $chave) {
    try {
        $st = $pdo->prepare("SELECT empresa_id FROM vendas WHERE chave_nfce = :ch LIMIT 1");
        $st->execute([':ch' => $chave]);
        $empresa_id = (string)($st->fetchColumn() ?: '');
    } catch (Throwable $e) { /* ignora */ }
}

// ==== Fallback: deduz modelo se não informado ================================
if (!$modelo) {
    if ($chave && $last4 && !$chaveSubstituta) $modelo = 'por_chave';
    elseif ($chave && $chaveSubstituta)        $modelo = 'por_substituicao';
    elseif ($motivo)                            $modelo = 'por_motivo';
}

// ==== Validações por modelo ==================================================
if ($modelo === 'por_chave') {
    if (strlen($chave) !== 44) {
        redirVendaRapida($empresa_id, false, $modelo, 'Chave inválida (44 dígitos).');
    }
    if (strlen($last4) !== 4 || substr($chave, -4) !== $last4) {
        redirVendaRapida($empresa_id, false, $modelo, 'Confirmação (últimos 4) não confere.');
    }
} elseif ($modelo === 'por_motivo') {
    if ($motivo === '') {
        redirVendaRapida($empresa_id, false, $modelo, 'Motivo não informado.');
    }
} elseif ($modelo === 'por_substituicao') {
    if (strlen($chave) !== 44) {
        redirVendaRapida($empresa_id, false, $modelo, 'Chave (original) inválida (44 dígitos).');
    }
    if (strlen($chaveSubstituta) !== 44) {
        redirVendaRapida($empresa_id, false, $modelo, 'Chave substituta inválida (44 dígitos).');
    }
    if ($chaveSubstituta === $chave) {
        redirVendaRapida($empresa_id, false, $modelo, 'A chave substituta deve ser diferente da chave a cancelar.');
    }
} else {
    redirVendaRapida($empresa_id, false, $modelo, 'Modelo de cancelamento inválido.');
}

// Se ainda não temos empresa_id, aborta com erro explícito
if (!$empresa_id) {
    redirVendaRapida($empresa_id, false, $modelo, 'empresa_id ausente');
}

// ==== Ponto de integração: chame sua rotina SEFAZ ============================
function runCancel($modelo, $empresa_id, $chave, $motivo, $chaveSubstituta){
    // Integre aqui:
    // if ($modelo === 'por_chave')        { /* evento 110111 */ }
    // if ($modelo === 'por_substituicao') { /* evento 110112 (usa $chaveSubstituta) */ }
    // if ($modelo === 'por_motivo')       { /* cancelamento interno */ }
    return true;
}

$ok = runCancel($modelo, $empresa_id, $chave, $motivo, $chaveSubstituta);

// ==== Exclusão da venda (apenas se $ok === true) =============================
if ($ok && $pdo) {
    try {
        $pdo->beginTransaction();

        $vendaId = null;
        if ($chave) {
            $st = $pdo->prepare("SELECT id FROM vendas WHERE empresa_id = :emp AND chave_nfce = :ch ORDER BY id DESC LIMIT 1");
            $st->execute([':emp'=>$empresa_id, ':ch'=>$chave]);
            $vendaId = $st->fetchColumn();
        }
        if (!$vendaId) {
            // 1) Buscar itens da venda e repor estoque
$selItens = $pdo->prepare("
    SELECT produto_id, quantidade
      FROM itens_venda
     WHERE venda_id = :id
");
$selItens->execute([':id' => $vendaId]);
$itens = $selItens->fetchAll(PDO::FETCH_ASSOC);

// Trava e repõe por item
$lockEst = $pdo->prepare("
    SELECT id, empresa_id
      FROM estoque
     WHERE id = :id
     FOR UPDATE
");
$updEst = $pdo->prepare("
    UPDATE estoque
       SET quantidade_produto = quantidade_produto + :qtd
     WHERE id = :id AND empresa_id = :emp
");

foreach ($itens as $it) {
    $pid = (int)$it['produto_id'];
    $qtd = (float)$it['quantidade'];

    // trava a linha do estoque
    $lockEst->execute([':id' => $pid]);
    $row = $lockEst->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        redirVendaRapida($empresa_id, false, $modelo, "Produto $pid não encontrado no estoque para devolução.");
    }
    if ((string)$row['empresa_id'] !== (string)$empresa_id) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        redirVendaRapida($empresa_id, false, $modelo, "Produto $pid pertence a outra empresa.");
    }

    // repõe a quantidade
    $updEst->execute([':qtd' => $qtd, ':id' => $pid, ':emp' => $empresa_id]);
}

// 2) Agora sim, apague os itens e a venda
$di = $pdo->prepare("DELETE FROM itens_venda WHERE venda_id = :id");
$di->execute([':id' => $vendaId]);

$dv = $pdo->prepare("DELETE FROM vendas WHERE id = :id");
$dv->execute([':id' => $vendaId]);

            $st = $pdo->prepare("SELECT id FROM vendas WHERE empresa_id = :emp ORDER BY id DESC LIMIT 1");
            $st->execute([':emp'=>$empresa_id]);
            $vendaId = $st->fetchColumn();
        }

        if ($vendaId) {
            $di = $pdo->prepare("DELETE FROM itens_venda WHERE venda_id = :id");
            $di->execute([':id'=>$vendaId]);

            $dv = $pdo->prepare("DELETE FROM vendas WHERE id = :id");
            $dv->execute([':id'=>$vendaId]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        redirVendaRapida($empresa_id, false, $modelo, 'Erro ao remover venda: '.$e->getMessage());
    }
}

// ==== Redireciona para a Venda Rápida =======================================
if ($ok) {
    redirVendaRapida($empresa_id, true, $modelo, '');
} else {
    redirVendaRapida($empresa_id, false, $modelo, 'Falha ao cancelar.');
}

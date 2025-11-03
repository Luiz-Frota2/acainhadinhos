<?php

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);
session_start();

/* ==================== Funções Helpers ==================== */
function brMoneyToFloat($v)
{
    $v = trim((string)$v);
    if ($v === '') return 0.0;
    $v = str_replace(['.', ','], ['', '.'], $v);
    return (float)$v;
}

function resolve_id_matriz(string $idSelecionado): string
{
    return 'principal_1';
}

/* ==================== Utilitários JS ==================== */
function js_alert_and_back(string $msg): void
{
    $safe = json_encode($msg, JSON_UNESCAPED_UNICODE);
    echo "<script>alert($safe); history.back();</script>";
    exit;
}

function js_alert_and_redirect(string $msg, string $url): void
{
    $safe = json_encode($msg, JSON_UNESCAPED_UNICODE);
    $safeUrl = json_encode($url, JSON_UNESCAPED_SLASHES);
    echo "<script>alert($safe); location.href=$safeUrl;</script>";
    exit;
}

/* ==================== Checagem da sessão ==================== */
$idSelecionado = $_POST['id'] ?? '';
if (!$idSelecionado) {
    js_alert_and_redirect('Sessão expirada. Faça login novamente.', '../../../public/login.php');
}

if (!isset($_SESSION['usuario_logado'], $_SESSION['empresa_id'], $_SESSION['tipo_empresa'], $_SESSION['usuario_id'])) {
    js_alert_and_redirect('Sessão inválida. Faça login novamente.', '../../../public/login.php?id=' . urlencode($idSelecionado));
}

/* ==================== Conexão ==================== */
require __DIR__ . '/../conexao.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    js_alert_and_back('Erro: conexão indisponível.');
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ==================== CSRF ==================== */
if (!isset($_POST['csrf'], $_SESSION['csrf_pagamento']) || $_POST['csrf'] !== $_SESSION['csrf_pagamento']) {
    unset($_SESSION['csrf_pagamento']);
    js_alert_and_redirect('Token de segurança inválido. Tente novamente.', '../../../public/b2b/solicitarPagamentoConta.php?id=' . urlencode($idSelecionado));
}
unset($_SESSION['csrf_pagamento']);

/* ==================== Coleta/validação dos campos ==================== */
try {
    $fornecedor = trim($_POST['fornecedor'] ?? '');
    $documento  = trim($_POST['documento'] ?? '');
    $descricao  = trim($_POST['descricao'] ?? '');
    $vencimento = trim($_POST['vencimento'] ?? '');
    $valorBR    = trim($_POST['valor'] ?? '');
    $valor      = brMoneyToFloat($valorBR);

    if ($fornecedor === '') throw new RuntimeException('Informe o fornecedor.');
    if ($valor <= 0) throw new RuntimeException('Informe um valor maior que zero.');
    if ($vencimento === '') throw new RuntimeException('Informe o vencimento.');

    $vencData = date_create_from_format('Y-m-d', $vencimento) ?: date_create_from_format('d/m/Y', $vencimento);
    if (!$vencData) throw new RuntimeException('Data de vencimento inválida.');
    $vencSql = $vencData->format('Y-m-d');

    /* ==================== Upload do arquivo ==================== */
    $comprovante_url = null;

    if (!empty($_FILES['arquivo']['name']) && is_uploaded_file($_FILES['arquivo']['tmp_name'])) {
        $f = $_FILES['arquivo'];

        if ($f['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Falha no upload do arquivo (código ' . $f['error'] . ').');
        }
        if ($f['size'] > 10 * 1024 * 1024) {
            throw new RuntimeException('Arquivo excede 10MB.');
        }

        $allowed = [
            'application/pdf',
            'image/png',
            'image/jpeg',
            'image/jpg',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel'
        ];

        $mime = mime_content_type($f['tmp_name']);
        if ($mime && !in_array($mime, $allowed, true)) {
            throw new RuntimeException('Tipo de arquivo não permitido.');
        }

        // Diretório relativo ./pagamentos/ na mesma pasta do script
        $uploadDir = __DIR__ . '/pagamentos'; // ajusta conforme sua estrutura
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            throw new RuntimeException('Não foi possível criar o diretório ./pagamentos/');
        }

        $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
        $safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($f['name'], PATHINFO_FILENAME));
        $newName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safeBase . ($ext ? '.' . $ext : '');
        $destPath = $uploadDir . '/' . $newName;

        if (!move_uploaded_file($f['tmp_name'], $destPath)) {
            throw new RuntimeException('Falha ao mover o arquivo enviado.');
        }

        // Caminho relativo para salvar no banco
        $comprovante_url = './pagamentos/' . $newName;
    }

    /* ==================== Inserção no banco ==================== */
    $id_matriz      = resolve_id_matriz($idSelecionado);
    $id_solicitante = $idSelecionado;

    $sql = "INSERT INTO solicitacoes_pagamento
            (id_matriz, id_solicitante, status, fornecedor, documento, descricao, vencimento, valor, comprovante_url, created_at)
            VALUES
            (:id_matriz, :id_solicitante, :status, :fornecedor, :documento, :descricao, :vencimento, :valor, :comprovante_url, NOW())";

    $st = $pdo->prepare($sql);
    $ok = $st->execute([
        ':id_matriz'       => $id_matriz,
        ':id_solicitante'  => $id_solicitante,
        ':status'          => 'pendente',
        ':fornecedor'      => $fornecedor,
        ':documento'       => $documento,
        ':descricao'       => $descricao,
        ':vencimento'      => $vencSql,
        ':valor'           => $valor,
        ':comprovante_url' => $comprovante_url
    ]);

    if (!$ok) throw new RuntimeException('Não foi possível salvar a solicitação.');

    js_alert_and_redirect('Solicitação registrada com sucesso!', '../../../erp/matriz/novaSolicitacaoPagamento.php?id=' . urlencode($id_solicitante));

} catch (Throwable $e) {
    js_alert_and_back('Erro: ' . $e->getMessage());
}

?>

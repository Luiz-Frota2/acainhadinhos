<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);
session_start();

/**
 * Conexão PDO — mesmo include usado nas suas páginas
 * Este arquivo deve definir $pdo (PDO). Se definir $conn (PDO), também suportamos.
 */
require_once __DIR__ . '/../../assets/php/conexao.php';

$pdo = $pdo ?? null;
if (!$pdo && isset($conn) && $conn instanceof PDO) {
  $pdo = $conn;
}
if (!$pdo || !($pdo instanceof PDO)) {
  resposta(false, 'Conexão indisponível.');
}

/** Requer empresa logada (mesmo padrão da sua listagem) */
if (!isset($_SESSION['empresa_id'])) {
  resposta(false, 'Empresa não logada.');
}

$empresaId = (string)$_SESSION['empresa_id'];

/** Detecta se é ajax (para decidir JSON vs redirect) */
$isAjax = (
  (isset($_GET['ajax']) && $_GET['ajax'] === '1') ||
  (isset($_POST['ajax']) && $_POST['ajax'] === '1') ||
  (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && stripos((string)$_SERVER['HTTP_X_REQUESTED_WITH'], 'xmlhttprequest') !== false)
);

/** Helper de resposta */
function resposta(bool $ok, string $msg, array $extra = []): never {
  global $isAjax;
  if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => $ok, 'message' => $msg] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
  } else {
    // fallback: redireciona de volta (ajuste o destino, se quiser)
    $dest = $_POST['_redirect'] ?? $_GET['_redirect'] ?? ($_SERVER['HTTP_REFERER'] ?? '../pages/ajusteFolga.php');
    $glue = (strpos($dest, '?') !== false) ? '&' : '?';
    header('Location: ' . $dest . $glue . 'ok=' . (int)$ok . '&msg=' . urlencode($msg));
    exit;
  }
}

/** Sanitização/validação básica */
$cpfRaw = trim((string)($_POST['cpf'] ?? ''));
$nome   = trim((string)($_POST['nome'] ?? ''));
$data   = trim((string)($_POST['data_folga'] ?? ''));

/** Normaliza CPF (remove pontuação) */
$cpf = preg_replace('/\D+/', '', $cpfRaw) ?? '';

if ($cpf === '' || strlen($cpf) !== 11) {
  resposta(false, 'Informe um CPF válido (11 dígitos).');
}
if ($nome === '') {
  resposta(false, 'Informe o nome.');
}
if ($data === '') {
  resposta(false, 'Informe a data da folga.');
}

/** Valida formato de data (YYYY-MM-DD) e se é uma data válida */
$valida = false;
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
  $partes = explode('-', $data);
  $valida = checkdate((int)$partes[1], (int)$partes[2], (int)$partes[0]);
}
if (!$valida) {
  resposta(false, 'Data inválida. Use o formato AAAA-MM-DD.');
}

try {
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Evita duplicidade (mesmo cpf + data)
  $sqlDup = "SELECT 1 FROM folgas WHERE cpf = :cpf AND data_folga = :data LIMIT 1";
  $stDup = $pdo->prepare($sqlDup);
  $stDup->execute([':cpf' => $cpf, ':data' => $data]);
  if ($stDup->fetchColumn()) {
    resposta(false, 'Já existe folga cadastrada para este CPF nesta data.');
  }

  // Insere — conforme estrutura da sua tabela (id AUTO_INCREMENT; cpf, nome, data_folga)
  $sqlIns = "INSERT INTO folgas (cpf, nome, data_folga) VALUES (:cpf, :nome, :data)";
  $stIns = $pdo->prepare($sqlIns);
  $stIns->execute([
    ':cpf'  => $cpf,
    ':nome' => $nome,
    ':data' => $data,
  ]);

  resposta(true, 'Folga cadastrada com sucesso.', ['id' => (int)$pdo->lastInsertId()]);
} catch (Throwable $e) {
  resposta(false, 'Erro ao salvar: ' . $e->getMessage());
}

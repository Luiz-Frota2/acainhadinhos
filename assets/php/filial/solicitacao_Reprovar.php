<?php
// actions/solicitacao_Reprovar.php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');

session_start();
require_once __DIR__ . '/../assets/php/conexao.php'; // ajuste se seu caminho for diferente

function jexit($ok, $msg = '', $extra = []) {
  echo json_encode(['ok' => $ok, 'msg' => $msg] + $extra);
  exit;
}

try {
  if (!isset($_SESSION['empresa_id'])) jexit(false, 'Sessão expirada (empresa).');
  $empresaId = $_SESSION['empresa_id'];

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') jexit(false, 'Método inválido.');
  $pedidoId = isset($_POST['pedido_id']) ? (int)$_POST['pedido_id'] : 0;
  if ($pedidoId <= 0) jexit(false, 'ID do pedido inválido.');

  $motivo = trim((string)($_POST['motivo'] ?? ''));
  if (mb_strlen($motivo) > 500) $motivo = mb_substr($motivo, 0, 500);

  if (!isset($pdo) || !($pdo instanceof PDO)) jexit(false, 'Conexão indisponível.');
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Verifica existência e propriedade (mesma matriz)
  $stmt = $pdo->prepare("
    SELECT s.id, s.status, s.observacao
    FROM solicitacoes_b2b s
    WHERE s.id = :id AND s.id_matriz = :empresa
    LIMIT 1
  ");
  $stmt->execute([':id' => $pedidoId, ':empresa' => $empresaId]);
  $sol = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$sol) jexit(false, 'Solicitação não encontrada para esta empresa.');

  // Evita reprocesso
  $statusAtual = strtolower((string)($sol['status'] ?? ''));
  if ($statusAtual === 'reprovada') jexit(true, 'Solicitação já está reprovada.');

  // Descobre colunas extras
  $cols = [];
  try {
    $colStmt = $pdo->query("SHOW COLUMNS FROM solicitacoes_b2b");
    while ($c = $colStmt->fetch(PDO::FETCH_ASSOC)) $cols[$c['Field']] = true;
  } catch (Throwable $e) {}

  // Monta SET dinâmico
  $set = "status = 'reprovada'";
  $params = [':id' => $pedidoId, ':empresa' => $empresaId];

  if (isset($cols['updated_at'])) $set .= ", updated_at = NOW()";

  // Se houver coluna 'observacao', acrescenta o motivo
  if ($motivo !== '' && isset($cols['observacao'])) {
    // concatena sem perder o que já existe
    $novaObs = trim((string)($sol['observacao'] ?? ''));
    if ($novaObs !== '') $novaObs .= " | ";
    $novaObs .= "Reprovado: " . $motivo;
    $set .= ", observacao = :obs";
    $params[':obs'] = $novaObs;
  }

  $upd = $pdo->prepare("UPDATE solicitacoes_b2b SET $set WHERE id = :id AND id_matriz = :empresa LIMIT 1");
  $upd->execute($params);

  jexit(true, 'Solicitação reprovada com sucesso.');
} catch (Throwable $e) {
  jexit(false, 'Erro ao reprovar: ' . $e->getMessage());
}

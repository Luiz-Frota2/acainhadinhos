<?php
// solicitacaoPagamentoStatus.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

/**
 *  Comportamento desejado:
 *  - sucesso: <script>alert(...); window.location.href = 'referer';</script>
 *  - erro:    <script>alert(...); history.back();</script>
 *
 * Observação: ajuste o require se o conexao.php não estiver em ../ (relativo a este arquivo).
 */

// caminho padrão caso não exista HTTP_REFERER
$defaultRedirect = '../../../erp/franquia/contasFranquia.php';

// helper JS output (alert + redirect/back)
function js_success_and_redirect(string $message, string $redirectUrl) {
  // json_encode para escapar corretamente a string no JS
  $m = json_encode($message, JSON_UNESCAPED_UNICODE);
  $r = json_encode($redirectUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  echo "<!doctype html><html><head><meta charset='utf-8'></head><body><script>";
  echo "alert($m); window.location.href = $r;";
  echo "</script></body></html>";
  exit;
}
function js_error_and_back(string $message) {
  $m = json_encode($message, JSON_UNESCAPED_UNICODE);
  echo "<!doctype html><html><head><meta charset='utf-8'></head><body><script>";
  echo "alert($m); history.back();";
  echo "</script></body></html>";
  exit;
}

// checagem básica de sessão/entrada
if (!isset($_SESSION['usuario_logado']) || !isset($_SESSION['empresa_id']) || !isset($_SESSION['tipo_empresa']) || !isset($_SESSION['usuario_id'])) {
  js_error_and_back('Sessão inválida. Faça login novamente.');
}

// pegar POST
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$id_matriz = trim($_POST['id_matriz'] ?? '');
$csrf = $_POST['csrf'] ?? '';
$acao = trim($_POST['acao'] ?? '');
$obs = trim($_POST['obs'] ?? '');

// referer para redirecionamento
$referer = $_SERVER['HTTP_REFERER'] ?? $defaultRedirect;

// validações
if (empty($csrf) || empty($_SESSION['csrf_pagto_status']) || !hash_equals($_SESSION['csrf_pagto_status'], $csrf)) {
  js_error_and_back('Token CSRF inválido.');
}

if ($id <= 0) {
  js_error_and_back('ID da solicitação inválido.');
}

if ($id_matriz === '') {
  js_error_and_back('ID da matriz não informado.');
}

if (!in_array($acao, ['aprovado', 'reprovado'], true)) {
  js_error_and_back('Ação inválida.');
}

if ($acao === 'reprovado' && $obs === '') {
  js_error_and_back('Comentário obrigatório ao reprovar.');
}

/* ==================== Permissão: checar se o usuário pode alterar esta matriz ==================== */
$idEmpresaSession = $_SESSION['empresa_id'];
$tipoSession = $_SESSION['tipo_empresa'];

function hasAccessToIdSelecionado(string $idSelecionado, string $tipoSession, string $idEmpresaSession): bool {
  if (str_starts_with($idSelecionado, 'principal_')) {
    return ($tipoSession === 'principal' && $idEmpresaSession === $idSelecionado);
  } elseif (str_starts_with($idSelecionado, 'filial_')) {
    return ($tipoSession === 'filial' && $idEmpresaSession === $idSelecionado);
  } elseif (str_starts_with($idSelecionado, 'unidade_')) {
    return ($tipoSession === 'unidade' && $idEmpresaSession === $idSelecionado);
  } elseif (str_starts_with($idSelecionado, 'franquia_')) {
    return ($tipoSession === 'franquia' && $idEmpresaSession === $idSelecionado);
  }
  return false;
}

if (!hasAccessToIdSelecionado($id_matriz, $tipoSession, $idEmpresaSession)) {
  js_error_and_back('Acesso negado para esta matriz/unidade.');
}

/* ==================== Conexão ==================== */
// Presume-se que este arquivo ficará em assets/php/matriz/ e conexao.php em assets/php/
// ajuste se necessário.
$possibleConPath = __DIR__ . '/../conexao.php';
if (!file_exists($possibleConPath)) {
  // tenta caminho alternativo histórico (caso você coloque em outro local)
  $possibleConPath = __DIR__ . '/../../assets/php/conexao.php';
}
if (!file_exists($possibleConPath)) {
  js_error_and_back('Arquivo de conexão não encontrado. Ajuste o require no arquivo solicitacaoPagamentoStatus.php.');
}
require $possibleConPath;

/* ==================== Atualização ==================== */
try {
  $pdo->beginTransaction();

  // Verifica existência e pertencimento
  $checkSql = "SELECT ID, status FROM solicitacoes_pagamento WHERE ID = :id AND id_matriz = :id_matriz LIMIT 1";
  $checkStmt = $pdo->prepare($checkSql);
  $checkStmt->execute([':id' => $id, ':id_matriz' => $id_matriz]);
  $row = $checkStmt->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    $pdo->rollBack();
    js_error_and_back('Solicitação não encontrada ou não pertence à matriz informada.');
  }

  $oldStatus = $row['status'];

  if ($oldStatus === $acao) {
    $pdo->commit();
    js_success_and_redirect("Solicitação #{$id} já estava como " . strtoupper($acao) . ".", $referer);
  }

  if ($acao === 'aprovado') {
    $sql = "UPDATE solicitacoes_pagamento
            SET status = :status, obs_reprovacao = NULL, updated_at = NOW()
            WHERE ID = :id AND id_matriz = :id_matriz";
    $params = [':status' => 'aprovado', ':id' => $id, ':id_matriz' => $id_matriz];
  } else { // reprovado
    $sql = "UPDATE solicitacoes_pagamento
            SET status = :status, obs_reprovacao = :obs, updated_at = NOW()
            WHERE ID = :id AND id_matriz = :id_matriz";
    // limitado a 2000 chars por segurança (ajuste se precisar)
    $obsToStore = mb_substr($obs, 0, 2000);
    $params = [':status' => 'reprovado', ':obs' => $obsToStore, ':id' => $id, ':id_matriz' => $id_matriz];
  }

  $upd = $pdo->prepare($sql);
  $ok = $upd->execute($params);

  if (!$ok) {
    $pdo->rollBack();
    js_error_and_back('Erro ao atualizar status (falha na execução).');
  }

  $pdo->commit();

  // sucesso -> mostrar alert com o id selecionado e redirecionar
  if ($acao === 'aprovado') {
    js_success_and_redirect("Solicitação #{$id} aprovada com sucesso.", $referer);
  } else {
    js_success_and_redirect("Solicitação #{$id} reprovada com sucesso.", $referer);
  }

} catch (PDOException $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  // detalhe do erro só no alert; se preferir, troque por mensagem genérica
  js_error_and_back('Erro de banco de dados: ' . $e->getMessage());
} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  js_error_and_back('Erro: ' . $e->getMessage());
}

?>
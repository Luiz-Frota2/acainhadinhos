<?php
// ./assets/php/filial/excluirFilial.php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_GET['id'])) {
  $ret = $_GET['return'] ?? '../../../filialAdicionada.php';
  header('Location: ' . $ret);
  exit;
}

require_once __DIR__ . '/../conexao.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
  echo "<script>alert('Erro de conexão com o banco.');history.back();</script>"; exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$empresaId = (string)($_GET['idSelecionado'] ?? $_GET['idEmpresa'] ?? $_GET['empresa_id'] ?? '');
$returnUrl = (string)($_GET['return'] ?? '');

if ($id <= 0) {
  $dest = $returnUrl !== '' ? $returnUrl : ('../../../filialAdicionada.php?id=' . urlencode($empresaId));
  header('Location: ' . $dest . '&del=0&msg=' . urlencode('ID inválido.')); 
  exit;
}

try {
  $stmt = $pdo->prepare("DELETE FROM unidades WHERE id = :id");
  $stmt->bindValue(':id', $id, PDO::PARAM_INT);
  $stmt->execute();

  $dest = $returnUrl !== '' ? $returnUrl : ('../../../filialAdicionada.php?id=' . urlencode($empresaId));
  header('Location: ' . $dest . '&del=1'); 
  exit;
} catch (PDOException $e) {
  $dest = $returnUrl !== '' ? $returnUrl : ('../../../filialAdicionada.php?id=' . urlencode($empresaId));
  header('Location: ' . $dest . '&del=0&msg=' . urlencode('Erro: '.$e->getMessage())); 
  exit;
}

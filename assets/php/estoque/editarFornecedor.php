<?php
// ./assets/php/filial/editarFilial_Processar.php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  // volta pra onde veio se possível
  $ret = $_SERVER['HTTP_REFERER'] ?? '../../../fornecedoresAdicionados.php';
  header('Location: ' . $ret);
  exit;
}

require_once __DIR__ . '/../conexao.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
  echo "<script>alert('Erro de conexão com o banco.');history.back();</script>"; exit;
}

// Coleta e saneamento
$id          = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$empresaId   = trim((string)($_POST['empresa_id'] ?? ''));
$returnUrl   = trim((string)($_POST['return_url'] ?? ''));
$nome        = trim((string)($_POST['nome_fornecedor'] ?? ''));
$cnpj        = trim((string)($_POST['cnpj_fornecedor'] ?? ''));
$telefone    = trim((string)($_POST['telefone_fornecedor'] ?? ''));
$email       = trim((string)($_POST['email_fornecedor'] ?? ''));
$endereco    = trim((string)($_POST['endereco_fornecedor'] ?? ''));

if ($id <= 0 || $nome === '' || $cnpj === '' || $telefone === '' || $email === ''  || $endereco === '') {
  $dest = $returnUrl !== '' ? $returnUrl : ('../../../fornecedoresAdicionados.php?id=' . urlencode($empresaId));
  header('Location: ' . $dest . '&edit=0&msg=' . urlencode('Dados inválidos.'));
  exit;
}

try {
  // Atualiza a filial
  $sql = "UPDATE fornecedores SET 
            nome_fornecedor = :nome_fornecedor,
            cnpj_fornecedor = :cnpj_fornecedor,
            telefone_fornecedor = :telefone_fornecedor,
            email_fornecedor = :email_fornecedor,
            endereco_fornecedor = :endereco_fornecedor,
          WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->bindValue(':nome_fornecedor', $nome);
  $stmt->bindValue(':cnpj_fornecedor', $cnpj);
  $stmt->bindValue(':telefone_fornecedor', $telefone);
  $stmt->bindValue(':email_fornecedor', $email);
  $stmt->bindValue(':endereco_fornecedor', $endereco);
  $stmt->bindValue(':id', $id, PDO::PARAM_INT);
  $stmt->execute();

  $dest = $returnUrl !== '' ? $returnUrl : ('../../../fornecedoresAdicionados.php?id=' . urlencode($empresaId));
  header('Location: ' . $dest . '&edit=1'); 
  exit;
} catch (PDOException $e) {
  $dest = $returnUrl !== '' ? $returnUrl : ('../../../fornecedoresAdicionados.php?id=' . urlencode($empresaId));
  header('Location: ' . $dest . '&edit=0&msg=' . urlencode('Erro: '.$e->getMessage())); 
  exit;
}

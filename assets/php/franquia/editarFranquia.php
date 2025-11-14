<?php
// ./assets/php/filial/editarFilial_Processar.php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  // volta pra onde veio se possível
  $ret = $_SERVER['HTTP_REFERER'] ?? '../../../franquiaAdicionada.php';
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
$nome        = trim((string)($_POST['nome'] ?? ''));
$tipo        = trim((string)($_POST['tipo'] ?? ''));
$cnpj        = trim((string)($_POST['cnpj'] ?? ''));
$telefone    = trim((string)($_POST['telefone'] ?? ''));
$email       = trim((string)($_POST['email'] ?? ''));
$responsavel = trim((string)($_POST['responsavel'] ?? ''));
$endereco    = trim((string)($_POST['endereco'] ?? ''));
$dataAbert   = trim((string)($_POST['data_abertura'] ?? ''));
$status      = trim((string)($_POST['status'] ?? ''));

if ($id <= 0 || $nome === '' || $tipo === '' || $status === '' || $cnpj === '' || $telefone === '' || $email === '' || $responsavel === '' || $endereco === '' || $dataAbert === '') {
  $dest = $returnUrl !== '' ? $returnUrl : ('../../../franquiaAdicionada.php?id=' . urlencode($empresaId));
  header('Location: ' . $dest . '&edit=0&msg=' . urlencode('Dados inválidos.'));
  exit;
}

try {
  // Atualiza a filial
  $sql = "UPDATE unidades SET 
            nome = :nome,
            tipo = :tipo,
            cnpj = :cnpj,
            telefone = :telefone,
            email = :email,
            responsavel = :responsavel,
            endereco = :endereco,
            data_abertura = :data_abertura,
            status = :status
          WHERE id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->bindValue(':nome', $nome);
  $stmt->bindValue(':tipo', $tipo);
  $stmt->bindValue(':cnpj', $cnpj);
  $stmt->bindValue(':telefone', $telefone);
  $stmt->bindValue(':email', $email);
  $stmt->bindValue(':responsavel', $responsavel);
  $stmt->bindValue(':endereco', $endereco);
  $stmt->bindValue(':data_abertura', $dataAbert);
  $stmt->bindValue(':status', $status);
  $stmt->bindValue(':id', $id, PDO::PARAM_INT);
  $stmt->execute();

  $dest = $returnUrl !== '' ? $returnUrl : ('../../../franquiaAdicionada.php?id=' . urlencode($empresaId));
  header('Location: ' . $dest . '&edit=1'); 
  exit;
} catch (PDOException $e) {
  $dest = $returnUrl !== '' ? $returnUrl : ('../../../franquiaAdicionada.php?id=' . urlencode($empresaId));
  header('Location: ' . $dest . '&edit=0&msg=' . urlencode('Erro: '.$e->getMessage())); 
  exit;
}

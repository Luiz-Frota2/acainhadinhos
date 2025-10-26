<?php
declare(strict_types=1);
ini_set('display_errors','1');
error_reporting(E_ALL);
session_start();

/**
 * Router unificado para cancelamentos.
 * Espera POST:
 *   - id        : empresa_id (ex.: principal_1, unidade_3, etc.)
 *   - venda_id  : ID da venda
 *   - acao      : 'interno' | '110111' | '110112'
 * 
 * Este arquivo pode fazer validações mínimas e então delegar para
 * cancelar_venda_processa.php (existente), mantendo compatibilidade.
 */

function response($ok, $msg, $http=200) {
  http_response_code($http);
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode(['ok'=>$ok, 'message'=>$msg], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  response(false, 'Método não permitido', 405);
}

$id        = $_POST['id']        ?? '';
$venda_id  = $_POST['venda_id']  ?? '';
$acao      = $_POST['acao']      ?? '';

$id        = is_string($id) ? trim($id) : '';
$venda_id  = (string)(is_scalar($venda_id) ? $venda_id : '');
$acao      = is_string($acao) ? strtolower(trim($acao)) : '';

if ($id === '' || $venda_id === '' || $acao === '') {
  response(false, 'Parâmetros obrigatórios ausentes: id, venda_id, acao', 400);
}

// Opcional: validação de acao
$valid = ['interno','110111','110112'];
if (!in_array($acao, $valid, true)) {
  response(false, 'Ação inválida. Use: interno, 110111 ou 110112', 400);
}

/**
 * Aqui você pode mapear a ação para chaves que seu cancelar_venda_processa.php
 * já entenda (se ele usa parâmetros diferentes). Exemplo:
 *   - 'interno'  => $_POST['tipo_cancelamento'] = 'interno'
 *   - '110111'   => $_POST['evento'] = '110111'
 *   - '110112'   => $_POST['evento'] = '110112'
 * 
 * Abaixo vamos adicionar compatibilidade mínima sem interferir em lógicas internas.
 */

if ($acao === 'interno') {
  $_POST['tipo_cancelamento'] = 'interno';
} elseif ($acao === '110111') {
  $_POST['evento'] = '110111';
} elseif ($acao === '110112') {
  $_POST['evento'] = '110112';
}

// Garante que os nomes base existam
$_POST['empresa_id'] = $_POST['empresa_id'] ?? $id;
$_POST['venda']      = $_POST['venda']      ?? $venda_id;

// Encaminha para o processador já existente
$alvos = [
  __DIR__ . '../../nfce/cancelar_venda_processa.php',
  __DIR__ . '/cancelar_venda_processa.php',
  __DIR__ . '/cancelar_venda_processa', // caso o ambiente trate sem .php
];

$incluiu = false;
foreach ($alvos as $arq) {
  if (is_file($arq)) {
    $incluiu = true;
    include $arq; // deve finalizar a resposta
    break;
  }
}

if (!$incluiu) {
  response(false, 'Arquivo cancelar_venda_processa.php não encontrado em ../nfce/', 500);
}

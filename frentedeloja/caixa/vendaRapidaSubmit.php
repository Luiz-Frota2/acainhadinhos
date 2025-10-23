<?php
// frentedeloja/caixa/vendaRapidaSubmit.php
// Cadastra venda + itens, baixa estoque e redireciona para emissão (sem emitir aqui).
declare(strict_types=1);
ini_set('display_errors','1');
error_reporting(E_ALL);
session_start();

/* =========================
   Redirect / JSON
   ========================= */
$redirect = !(
  (isset($_GET['redirect']) && $_GET['redirect'] === '0') ||
  (isset($_POST['redirect']) && $_POST['redirect'] === '0')
);
header('Content-Type: application/json; charset=utf-8');

/* =========================
   Conexão (caminhos)
   ========================= */
$__candidates = [
  __DIR__ . '/../../assets/php/conexao.php',
  __DIR__ . '/../../ERP/assets/php/conexao.php',
  __DIR__ . '/../dashboard/php/conexao.php',
  $_SERVER['DOCUMENT_ROOT'] . '/assets/php/conexao.php',
  $_SERVER['DOCUMENT_ROOT'] . '/ERP/assets/php/conexao.php',
];
$__found = false;
foreach ($__candidates as $__p) {
  if (is_file($__p)) { require_once $__p; $__found = true; break; }
}
if (!$__found || !isset($pdo) || !($pdo instanceof PDO)) {
  http_response_code(500);
  echo json_encode([
    'ok'=>false,
    'error_code'=>'CONEXAO_NAO_ENCONTRADA',
    'message'=>'Não foi possível localizar conexao.php ou $pdo.',
    'details'=>$__candidates
  ], JSON_UNESCAPED_UNICODE);
  exit;
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

/* =========================
   Helpers
   ========================= */
function soDig(string $s): string { return preg_replace('/\D+/', '', $s); }
function jexit(int $status, string $code, string $message, array $details=[]): void {
  http_response_code($status);
  echo json_encode(['ok'=>false,'error_code'=>$code,'message'=>$message,'details'=>$details], JSON_UNESCAPED_UNICODE);
  exit;
}
function jok(array $payload): void {
  http_response_code(200);
  echo json_encode(['ok'=>true]+$payload, JSON_UNESCAPED_UNICODE);
  exit;
}

/* Sanitizadores SEFAZ */
function saneEAN(?string $v): string {
  $v = trim((string)$v);
  $d = preg_replace('/\D+/','',$v);
  if ($d === '' || !in_array(strlen($d), [8,12,13,14], true)) return 'SEM GTIN';
  return $d;
}
function saneNCM(?string $v): string {
  $v = preg_replace('/\D+/','', (string)$v);
  if (in_array($v, ['', '0', '00', '00000000', '000', '000000'], true)) return '21069090'; // default seguro
  if (preg_match('/^([0-9]{2}|[0-9]{8})$/', $v)) return $v;
  return '21069090';
}
function saneCFOP(?string $v): string {
  $v = preg_replace('/\D+/','', (string)$v);
  if (in_array($v, ['', '0','00','000','0000'], true)) return '5102'; // venda dentro do estado
  if (preg_match('/^[123567][0-9]{3}$/', $v)) return $v;
  return '5102';
}
function saneUN(?string $v): string {
  $v = strtoupper(trim((string)$v));
  return ($v==='') ? 'UN' : $v;
}
function saneOrigem($v): string {
  $v = (string)$v;
  return preg_match('/^[0-8]$/',$v) ? $v : '0';
}
function saneCSOSN($v): string {
  $v = (string)$v;
  return preg_match('/^(101|102|103|300|400|500|900)$/',$v) ? $v : '102';
}

/* ====== Identificadores de produto ====== */
function extractProductIdentifiers(array $row): array {
  $lower = [];
  foreach ($row as $k=>$v) $lower[strtolower((string)$k)] = $v;
  $raw_id = (int)($lower['produto_id'] ?? $lower['id_produto'] ?? $lower['id'] ?? $lower['produtoid'] ?? 0);
  $ean    = trim((string)($lower['codigo_barras'] ?? $lower['codigobarras'] ?? $lower['ean'] ?? $lower['gtin'] ?? ''));
  $sku    = trim((string)($lower['sku'] ?? ''));
  $desc   = trim((string)($lower['desc'] ?? $lower['descricao'] ?? $lower['nome'] ?? $lower['produto'] ?? ''));
  return compact('raw_id','ean','sku','desc');
}
function resolveProductIdByAnyIdentifier(PDO $pdo, string $empresa_id, array $idn): int {
  if ($idn['raw_id'] > 0) {
    $st = $pdo->prepare("SELECT id FROM estoque WHERE id=:id AND empresa_id=:e AND status_produto='ativo' LIMIT 1");
    $st->execute([':id'=>$idn['raw_id'], ':e'=>$empresa_id]);
    if ($st->fetchColumn()) return $idn['raw_id'];
  }
  if ($idn['ean'] !== '') {
    $st = $pdo->prepare("SELECT id FROM estoque WHERE empresa_id=:e AND status_produto='ativo' AND (REPLACE(codigo_barras,' ','')=:ean) LIMIT 1");
    $st->execute([':e'=>$empresa_id, ':ean'=>preg_replace('/\D+/','',$idn['ean'])]);
    if ($pid = $st->fetchColumn()) return (int)$pid;
  }
  if ($idn['sku'] !== '') {
    $st = $pdo->prepare("SELECT id FROM estoque WHERE empresa_id=:e AND status_produto='ativo' AND sku=:sku LIMIT 1");
    $st->execute([':e'=>$empresa_id, ':sku'=>$idn['sku']]);
    if ($pid = $st->fetchColumn()) return (int)$pid;
  }
  if ($idn['desc'] !== '') {
    $st = $pdo->prepare("SELECT id FROM estoque WHERE empresa_id=:e AND status_produto='ativo' AND nome_produto=:n LIMIT 1");
    $st->execute([':e'=>$empresa_id, ':n'=>$idn['desc']]);
    if ($pid = $st->fetchColumn()) return (int)$pid;
    $st = $pdo->prepare("SELECT id FROM estoque WHERE empresa_id=:e AND status_produto='ativo' AND nome_produto LIKE :n LIMIT 1");
    $st->execute([':e'=>$empresa_id, ':n'=>$idn['desc'].'%']);
    if ($pid = $st->fetchColumn()) return (int)$pid;
  }
  return 0;
}
function getPostedItems(PDO $pdo, string $empresa_id): array {
  $items = [];
  $normalizeRow = function(array $row, int $idx) use ($pdo, $empresa_id): array {
    $qtd = $row['qtd'] ?? $row['quantidade'] ?? $row['qtdes'] ?? 0;
    $qtd = (float)str_replace(',','.', (string)$qtd);
    $vun = $row['vun'] ?? $row['preco_unitario'] ?? $row['preco'] ?? $row['valor'] ?? 0;
    $vun = (float)str_replace(',','.', (string)$vun);
    $idn = extractProductIdentifiers($row);
    $pid = resolveProductIdByAnyIdentifier($pdo, $empresa_id, $idn);
    if ($pid <= 0 || $qtd <= 0 || $vun < 0) {
      $keys = implode(',', array_keys($row));
      jexit(400,'BAD_ITEM_ROW',"Item inválido na posição {$idx}.",[
        'pid'=>$pid,'qtd'=>$qtd,'vun'=>$vun,'received_keys'=>$keys,'identifiers'=>$idn
      ]);
    }
    return ['produto_id'=>(int)$pid,'quantidade'=>$qtd,'preco_unitario'=>$vun];
  };

  if (!empty($_POST['itens'])) {
    $tmp = json_decode((string)$_POST['itens'], true);
    if (!is_array($tmp)) jexit(400,'BAD_ITEMS_JSON','O campo "itens" não é um JSON válido.');
    foreach ($tmp as $i=>$r) {
      if (!is_array($r)) jexit(400,'BAD_ITEM_ROW',"Formato de item inválido na posição {$i}.");
      $items[] = $normalizeRow($r, $i);
    }
  } else {
    $pids = $_POST['produto_id'] ?? $_POST['id_produto'] ?? $_POST['id'] ?? $_POST['produtos'] ?? [];
    $qtds = $_POST['qtd'] ?? $_POST['quantidade'] ?? $_POST['quantidades'] ?? [];
    $vuns = $_POST['vunit'] ?? $_POST['preco_unitario'] ?? $_POST['valores'] ?? $_POST['precos'] ?? [];
    $n = max(count((array)$pids), count((array)$qtds), count((array)$vuns));
    if ($n === 0) jexit(400,'NO_ITEMS','Nenhum item foi enviado.');
    for ($i=0; $i<$n; $i++) {
      $row = [
        'produto_id' => is_array($pids) && array_key_exists($i,$pids) ? $pids[$i] : ($pids ?? null),
        'qtd'        => is_array($qtds) && array_key_exists($i,$qtds) ? $qtds[$i] : ($qtds ?? null),
        'vun'        => is_array($vuns) && array_key_exists($i,$vuns) ? $vuns[$i] : ($vuns ?? null),
      ];
      $items[] = $normalizeRow($row, $i);
    }
  }
  return $items;
}

/* =========================
   ENTRADA & PROCESSO
   ========================= */
try {
  // Empresa
  $empresa_raw = $_POST['empresa_id'] ?? ($_GET['id'] ?? '');
  if (trim($empresa_raw) === '') jexit(400,'NO_EMPRESA','empresa_id ausente.');
  $empresa_id = trim($empresa_raw);

  // Responsável
  $responsavel = $_POST['responsavel'] ?? ($_SESSION['nome'] ?? $_SESSION['usuario'] ?? 'Responsável');
  $cpf_resp    = soDig((string)($_POST['cpf_responsavel'] ?? ($_SESSION['usuario_cpf'] ?? '')));
  if ($cpf_resp === '') jexit(400,'NO_CPF_RESP','CPF do responsável ausente.');

  // Cliente (opcional)
  $cpf_cliente = soDig((string)($_POST['cpf_cliente'] ?? ''));

  // Pagamento
  $forma_pg = $_POST['forma_pagamento'] ?? $_POST['formaPagamento'] ?? $_POST['forma'] ?? $_POST['fpag'] ?? $_POST['tPag'] ?? $_POST['meio_pagamento'] ?? '01';
  $forma_pg = trim((string)$forma_pg);

  $valor_recebido = isset($_POST['valor_recebido']) ? (float)str_replace(',','.', (string)$_POST['valor_recebido']) : 0.00;
  $troco          = isset($_POST['troco']) ? (float)str_replace(',','.', (string)$_POST['troco']) : 0.00;
  $data_venda     = date('Y-m-d H:i:s');

  // Itens
  $itens = getPostedItems($pdo, $empresa_id);
  if (count($itens) === 0) jexit(400,'NO_ITEMS','Nenhum item foi enviado.');

  // 1) Caixa
  $st = $pdo->prepare("
    SELECT id FROM aberturas
     WHERE empresa_id=:emp AND cpf_responsavel=:cpf AND status='aberto'
     ORDER BY abertura_datetime DESC LIMIT 1
  ");
  $st->execute([':emp'=>$empresa_id, ':cpf'=>$cpf_resp]);
  $caixa = $st->fetch();
  if (!$caixa) jexit(409,'CAIXA_NAO_ENCONTRADO','Nenhum caixa ABERTO encontrado para este responsável nesta empresa.', [
    'empresa_id'=>$empresa_id,'cpf_responsavel'=>$cpf_resp
  ]);
  $id_caixa = (int)$caixa['id'];

  // 2) Estoque
  $pdo->beginTransaction();
  $valor_total = 0.00;

  $selEst = $pdo->prepare("
    SELECT id, empresa_id, nome_produto, quantidade_produto, preco_produto,
           ncm, cest, cfop, origem, tributacao, unidade, informacoes_adicionais, codigo_barras
      FROM estoque
     WHERE id=:id AND status_produto='ativo'
     FOR UPDATE
  ");

  foreach ($itens as $k=>$it) {
    $selEst->execute([':id'=>$it['produto_id']]);
    $p = $selEst->fetch();
    if (!$p) { $pdo->rollBack(); jexit(404,'PRODUTO_NAO_ENCONTRADO',"Produto id={$it['produto_id']} não encontrado ou inativo."); }
    if ((string)$p['empresa_id'] !== (string)$empresa_id) {
      $pdo->rollBack();
      jexit(409,'EMPRESA_DIVERGENTE',"Produto id={$it['produto_id']} pertence a outra empresa.",[
        'produto_empresa'=>$p['empresa_id'], 'empresa_pedido'=>$empresa_id
      ]);
    }
    $qtdEst = (float)$p['quantidade_produto'];
    if ($qtdEst < (float)$it['quantidade']) {
      $pdo->rollBack();
      jexit(409,'ESTOQUE_INSUFICIENTE',"Estoque insuficiente para o produto id={$it['produto_id']}.",[
        'em_estoque'=>$qtdEst,'necessario'=>(float)$it['quantidade']
      ]);
    }

    // Preenche defaults e SANEIA para NFC-e
    if ($it['preco_unitario'] <= 0) $itens[$k]['preco_unitario'] = (float)$p['preco_produto'];

    $ncm  = saneNCM($p['ncm'] ?? '');
    $cfop = saneCFOP($p['cfop'] ?? '');
    $cest = preg_replace('/\D+/','', (string)($p['cest'] ?? ''));
    if ($cest === '' || $cest === '0000000') $cest = null; // opcional
    $ean  = saneEAN($p['codigo_barras'] ?? '');
    $orig = saneOrigem($p['origem'] ?? '0');
    $csosn= saneCSOSN($p['tributacao'] ?? '102');
    $un   = saneUN($p['unidade'] ?? 'UN');

    $itens[$k]['produto_nome']            = (string)$p['nome_produto'];
    $itens[$k]['ncm']                     = $ncm;
    $itens[$k]['cest']                    = $cest;
    $itens[$k]['cfop']                    = $cfop;
    $itens[$k]['origem']                  = $orig;
    $itens[$k]['tributacao']              = $csosn;
    $itens[$k]['unidade']                 = $un;
    $itens[$k]['informacoes_adicionais']  = $p['informacoes_adicionais'];
    $itens[$k]['codigo_barras']           = $ean;

    $valor_total += ((float)$itens[$k]['preco_unitario'] * (float)$itens[$k]['quantidade']);
  }

  // 3) Vendas
  $insVenda = $pdo->prepare("
    INSERT INTO vendas
      (responsavel, cpf_responsavel, cpf_cliente, forma_pagamento,
       valor_total, valor_recebido, troco, empresa_id, id_caixa, data_venda,
       chave_nfce, protocolo_nfce, status_nfce, motivo_nfce)
    VALUES
      (:resp,:cpf_resp,:cpf_cli,:forma,:vtotal,:vrec,:troco,:emp,:idc,:data,
       NULL,NULL,'pendente',NULL)
  ");
  $insVenda->execute([
    ':resp'=>$responsavel, ':cpf_resp'=>$cpf_resp, ':cpf_cli'=>($cpf_cliente ?: null),
    ':forma'=>$forma_pg, ':vtotal'=>$valor_total, ':vrec'=>$valor_recebido, ':troco'=>$troco,
    ':emp'=>$empresa_id, ':idc'=>$id_caixa, ':data'=>$data_venda
  ]);
  $venda_id = (int)$pdo->lastInsertId();

  // 4) Itens
  $insItem = $pdo->prepare("
    INSERT INTO itens_venda
      (venda_id, produto_id, produto_nome, quantidade, preco_unitario,
       ncm, cest, cfop, origem, tributacao, unidade, informacoes_adicionais, codigo_barras)
    VALUES
      (:venda_id,:produto_id,:produto_nome,:quantidade,:preco_unitario,
       :ncm,:cest,:cfop,:origem,:tributacao,:unidade,:info,:codigo_barras)
  ");
  foreach ($itens as $it) {
    $insItem->execute([
      ':venda_id'=>$venda_id,
      ':produto_id'=>$it['produto_id'],
      ':produto_nome'=>$it['produto_nome'],
      ':quantidade'=>$it['quantidade'],
      ':preco_unitario'=>$it['preco_unitario'],
      ':ncm'=>$it['ncm'],
      ':cest'=>$it['cest'],
      ':cfop'=>$it['cfop'],
      ':origem'=>$it['origem'],
      ':tributacao'=>$it['tributacao'],
      ':unidade'=>$it['unidade'],
      ':info'=>$it['informacoes_adicionais'],
      ':codigo_barras'=>$it['codigo_barras']
    ]);
  }

  // 5) Baixar estoque
  $updEst = $pdo->prepare("UPDATE estoque SET quantidade_produto = quantidade_produto - :qtd WHERE id=:id");
  foreach ($itens as $it) $updEst->execute([':qtd'=>$it['quantidade'], ':id'=>$it['produto_id']]);

  // 6) Atualizar caixa
  $updCx = $pdo->prepare("
    UPDATE aberturas
       SET valor_total = COALESCE(valor_total,0)+:vtotal,
           quantidade_vendas = COALESCE(quantidade_vendas,0)+1
     WHERE id=:id AND status='aberto' AND empresa_id=:emp AND cpf_responsavel=:cpf
  ");
  $updCx->execute([':vtotal'=>$valor_total, ':id'=>$id_caixa, ':emp'=>$empresa_id, ':cpf'=>$cpf_resp]);
  if ($updCx->rowCount() === 0) { $pdo->rollBack(); jexit(409,'CAIXA_INVALIDADO','O caixa deixou de estar aberto durante a operação.'); }

  $pdo->commit();

  // ===== Redireciona para emitir NFC-e =====
  if ($redirect) {
    header_remove('Content-Type');
    $qs = http_build_query(['venda_id'=>$venda_id,'id'=>$empresa_id]);
    $url = '../../../nfce/emitir.php?'.$qs;
    header("Location: $url", true, 302);
    exit;
  } else {
    jok([
      'message'=>'Venda cadastrada (pendente de emissão NFC-e).',
      'venda_id'=>$venda_id,
      'id_caixa'=>$id_caixa,
      'empresa_id'=>$empresa_id,
      'valor_total'=>$valor_total
    ]);
  }

} catch (PDOException $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  jexit(500,'DB_ERROR','Erro de banco ao processar venda.',['sqlstate'=>$e->getCode(),'error'=>$e->getMessage()]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  jexit(500,'UNEXPECTED','Erro inesperado.',['error'=>$e->getMessage()]);
}

?>
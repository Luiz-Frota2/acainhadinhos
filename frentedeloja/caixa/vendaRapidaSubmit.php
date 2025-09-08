<?php
// frentedeloja/caixa/vendaRapidaSubmit.php (patched + redirect to emitir.php)
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// Decide response mode early (allow forcing JSON: redirect=0)
$redirect = true;
if ((isset($_GET['redirect']) && $_GET['redirect'] == '0') || (isset($_POST['redirect']) && $_POST['redirect'] == '0')) {
  $redirect = false;
}

header('Content-Type: application/json; charset=utf-8');

/* =========================
   Carrega conexão (caminho robusto)
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
if (!$__found) {
  http_response_code(500);
  echo json_encode([
    'ok'=>false,
    'error_code'=>'CONEXAO_NAO_ENCONTRADA',
    'message'=>'Não foi possível localizar conexao.php. Ajuste o caminho no topo do arquivo.',
    'details'=>$__candidates
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ===== Helpers ===== */
function soDig(string $s) : string { return preg_replace('/\D+/', '', $s); }
function jexit(int $status, string $code, string $message, array $details = []) : void {
  http_response_code($status);
  echo json_encode(['ok'=>false,'error_code'=>$code,'message'=>$message,'details'=>$details], JSON_UNESCAPED_UNICODE);
  exit;
}
function jok(array $payload) : void {
  http_response_code(200);
  echo json_encode(['ok'=>true] + $payload, JSON_UNESCAPED_UNICODE);
  exit;
}

/* ====== Itens: extração e resolução de produto ====== */
function extractProductIdentifiers(array $row): array {
  $lower = [];
  foreach ($row as $k=>$v) { $lower[strtolower($k)] = $v; }
  $raw_id = (int)($lower['produto_id'] ?? $lower['id_produto'] ?? $lower['id'] ?? $lower['produtoid'] ?? 0);
  $ean    = trim((string)($lower['codigo_barras'] ?? $lower['codigobarras'] ?? $lower['ean'] ?? $lower['gtin'] ?? ''));
  $sku    = trim((string)($lower['sku'] ?? ''));
  $desc   = trim((string)($lower['desc'] ?? $lower['descricao'] ?? $lower['nome'] ?? $lower['produto'] ?? ''));
  return compact('raw_id','ean','sku','desc');
}

function resolveProductIdByAnyIdentifier(PDO $pdo, string $empresa_id, array $idn): int {
  if ($idn['raw_id'] > 0) {
    $st = $pdo->prepare("SELECT id FROM estoque WHERE id = :id AND empresa_id = :e AND status_produto='ativo' LIMIT 1");
    $st->execute([':id'=>$idn['raw_id'], ':e'=>$empresa_id]);
    if ($st->fetchColumn()) return $idn['raw_id'];
  }
  if ($idn['ean'] !== '') {
    $st = $pdo->prepare("SELECT id FROM estoque WHERE empresa_id = :e AND status_produto='ativo' AND (codigo_barras = :ean OR REPLACE(codigo_barras,' ','')=:ean) LIMIT 1");
    $st->execute([':e'=>$empresa_id, ':ean'=>$idn['ean']]);
    if ($pid = $st->fetchColumn()) return (int)$pid;
  }
  if ($idn['sku'] !== '') {
    $st = $pdo->prepare("SELECT id FROM estoque WHERE empresa_id = :e AND status_produto='ativo' AND sku = :sku LIMIT 1");
    $st->execute([':e'=>$empresa_id, ':sku'=>$idn['sku']]);
    if ($pid = $st->fetchColumn()) return (int)$pid;
  }
  if ($idn['desc'] !== '') {
    $st = $pdo->prepare("SELECT id FROM estoque WHERE empresa_id = :e AND status_produto='ativo' AND nome_produto = :n LIMIT 1");
    $st->execute([':e'=>$empresa_id, ':n'=>$idn['desc']]);
    if ($pid = $st->fetchColumn()) return (int)$pid;

    $st = $pdo->prepare("SELECT id FROM estoque WHERE empresa_id = :e AND status_produto='ativo' AND nome_produto LIKE :n LIMIT 1");
    $st->execute([':e'=>$empresa_id, ':n'=>$idn['desc'].'%']);
    if ($pid = $st->fetchColumn()) return (int)$pid;
  }
  return 0;
}

function getPostedItems(PDO $pdo, string $empresa_id): array {
  $items = [];

  $normalizeRow = function(array $row, int $idx) use ($pdo, $empresa_id): array {
    $qtd = isset($row['qtd']) ? $row['qtd']
         : (isset($row['quantidade']) ? $row['quantidade']
         : (isset($row['qtdes']) ? $row['qtdes'] : 0));
    $qtd = (float)str_replace(',','.', (string)$qtd);

    $vun = isset($row['vun']) ? $row['vun']
         : (isset($row['preco_unitario']) ? $row['preco_unitario']
         : (isset($row['preco']) ? $row['preco']
         : (isset($row['valor']) ? $row['valor'] : 0)));
    $vun = (float)str_replace(',','.', (string)$vun);

    $idn = extractProductIdentifiers($row);
    $pid = resolveProductIdByAnyIdentifier($pdo, $empresa_id, $idn);

    if ($pid <= 0 || $qtd <= 0 || $vun < 0) {
      $keys = implode(',', array_keys($row));
      jexit(400,'BAD_ITEM_ROW',"Item inválido na posição {$idx}.",
        ['pid'=>$pid,'qtd'=>$qtd,'vun'=>$vun,'received_keys'=>$keys,'identifiers'=>$idn]);
    }

    return [
      'produto_id'     => (int)$pid,
      'quantidade'     => $qtd,
      'preco_unitario' => $vun
    ];
  };

  if (!empty($_POST['itens'])) {
    $tmp = json_decode($_POST['itens'], true);
    if (!is_array($tmp)) jexit(400,'BAD_ITEMS_JSON','O campo "itens" não é um JSON válido.');
    foreach ($tmp as $i => $row) {
      if (!is_array($row)) jexit(400,'BAD_ITEM_ROW',"Formato de item inválido na posição {$i}.");
      $items[] = $normalizeRow($row, $i);
    }
  } else {
    $pids = $_POST['produto_id'] ?? $_POST['id_produto'] ?? $_POST['id'] ?? $_POST['produtos'] ?? [];
    $qtds = $_POST['qtd'] ?? $_POST['quantidade'] ?? $_POST['quantidades'] ?? [];
    $vuns = $_POST['vunit'] ?? $_POST['preco_unitario'] ?? $_POST['valores'] ?? $_POST['precos'] ?? [];
    $n = max(count((array)$pids), count((array)$qtds), count((array)$vuns));
    if ($n === 0) jexit(400,'NO_ITEMS','Nenhum item foi enviado.');
    for ($i=0; $i<$n; $i++) {
      $row = [
        'produto_id' => is_array($pids) && isset($pids[$i]) ? $pids[$i] : ($pids ?? null),
        'qtd'        => is_array($qtds) && isset($qtds[$i]) ? $qtds[$i] : ($qtds ?? null),
        'vun'        => is_array($vuns) && isset($vuns[$i]) ? $vuns[$i] : ($vuns ?? null),
      ];
      $items[] = $normalizeRow($row, $i);
    }
  }
  return $items;
}

/* =========================
   Entrada
   ========================= */
try {
  if (!isset($pdo) || !$pdo instanceof PDO) {
    jexit(500, 'SEM_PDO', 'Variável $pdo não encontrada após require da conexão.');
  }
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

  // empresa_id como STRING, exatamente como está nas tabelas (ex.: "principal_1")
  $empresa_raw = $_POST['empresa_id'] ?? ($_GET['id'] ?? '');
  if (trim($empresa_raw) === '') jexit(400,'NO_EMPRESA','empresa_id ausente.');
  $empresa_id = trim($empresa_raw);

  $responsavel = $_POST['responsavel'] ?? ($_SESSION['nome'] ?? $_SESSION['usuario'] ?? 'Responsável');
  $cpf_resp    = soDig($_POST['cpf_responsavel'] ?? ($_SESSION['usuario_cpf'] ?? ''));
  if ($cpf_resp === '') jexit(400,'NO_CPF_RESP','CPF do responsável ausente.');

  $cpf_cliente = soDig($_POST['cpf_cliente'] ?? '');
  $forma_pg = $_POST['forma_pagamento']
         ?? $_POST['formaPagamento']
         ?? $_POST['forma']
         ?? $_POST['fpag']
         ?? $_POST['tPag']
         ?? $_POST['meio_pagamento']
         ?? '01';
$forma_pg = trim((string)$forma_pg);
  $valor_recebido = isset($_POST['valor_recebido']) ? (float)str_replace(',','.',$_POST['valor_recebido']) : 0.00;
  $troco          = isset($_POST['troco']) ? (float)str_replace(',','.',$_POST['troco']) : 0.00;
  $data_venda     = date('Y-m-d H:i:s');

  $itens = getPostedItems($pdo, $empresa_id);
  if (count($itens) === 0) jexit(400,'NO_ITEMS','Nenhum item foi enviado.');

  /* =========================
     1) CAIXA: aberto, mesmo responsável e mesma empresa
     ========================= */
  $st = $pdo->prepare("
    SELECT id, status, empresa_id, cpf_responsavel
      FROM aberturas
     WHERE empresa_id = :emp
       AND cpf_responsavel = :cpf
       AND status = 'aberto'
     ORDER BY abertura_datetime DESC
     LIMIT 1
  ");
  $st->execute([':emp'=>$empresa_id, ':cpf'=>$cpf_resp]);
  $caixa = $st->fetch();
  if (!$caixa) {
    jexit(409,'CAIXA_NAO_ENCONTRADO','Nenhum caixa ABERTO encontrado para este responsável nesta empresa.',
      ['empresa_id'=>$empresa_id,'cpf_responsavel'=>$cpf_resp]);
  }
  $id_caixa = (int)$caixa['id'];

  /* =========================
     2) Estoque: travar linhas e validar empresa/quantidade
     ========================= */
  $pdo->beginTransaction();

  $valor_total = 0.00;
  $selEst = $pdo->prepare("
    SELECT id, empresa_id, nome_produto, quantidade_produto, preco_produto,
           ncm, cest, cfop, origem, tributacao, unidade, informacoes_adicionais
      FROM estoque
     WHERE id = :id
       AND status_produto = 'ativo'
     FOR UPDATE
  ");

  foreach ($itens as $k => $it) {
    $selEst->execute([':id'=>$it['produto_id']]);
    $p = $selEst->fetch();

    if (!$p) {
      $pdo->rollBack();
      jexit(404,'PRODUTO_NAO_ENCONTRADO', "Produto id={$it['produto_id']} não encontrado ou inativo.");
    }
    if ((string)$p['empresa_id'] !== (string)$empresa_id) {
      $pdo->rollBack();
      jexit(409,'EMPRESA_DIVERGENTE', "Produto id={$it['produto_id']} pertence a outra empresa.",
        ['produto_empresa'=>$p['empresa_id'], 'empresa_pedido'=>$empresa_id]);
    }

    $qtdEst = (float)$p['quantidade_produto'];
    if ($qtdEst < (float)$it['quantidade']) {
      $pdo->rollBack();
      jexit(409,'ESTOQUE_INSUFICIENTE',
        "Estoque insuficiente para o produto id={$it['produto_id']}.",
        ['em_estoque'=>$qtdEst, 'necessario'=>(float)$it['quantidade']]
      );
    }

    // Preenche campos faltantes a partir do cadastro
    if ($it['preco_unitario'] <= 0) $itens[$k]['preco_unitario'] = (float)$p['preco_produto'];

    $itens[$k]['produto_nome'] = $p['nome_produto'];
    $itens[$k]['ncm']          = $p['ncm'];
    $itens[$k]['cest']         = $p['cest'];
    $itens[$k]['cfop']         = $p['cfop'];
    $itens[$k]['origem']       = $p['origem'];
    $itens[$k]['tributacao']   = $p['tributacao'];
    $itens[$k]['unidade']      = $p['unidade'];
    $itens[$k]['informacoes_adicionais'] = $p['informacoes_adicionais'];

    $valor_total += ((float)$itens[$k]['preco_unitario'] * (float)$itens[$k]['quantidade']);
  }

  /* =========================
     3) Insert VENDAS
     ========================= */
  $insVenda = $pdo->prepare("
    INSERT INTO vendas
      (responsavel, cpf_responsavel, cpf_cliente, forma_pagamento,
       valor_total, valor_recebido, troco, empresa_id, id_caixa, data_venda, chave_nfce, status_nfce)
    VALUES
      (:resp, :cpf_resp, :cpf_cli, :forma, :vtotal, :vrec, :troco, :emp, :idc, :data, NULL, 'pendente')
  ");
  $insVenda->execute([
    ':resp'     => $responsavel,
    ':cpf_resp' => $cpf_resp,
    ':cpf_cli'  => ($cpf_cliente ?: null),
    ':forma'    => $forma_pg,
    ':vtotal'   => $valor_total,
    ':vrec'     => $valor_recebido,
    ':troco'    => $troco,
    ':emp'      => $empresa_id,
    ':idc'      => $id_caixa,
    ':data'     => $data_venda
  ]);
  $venda_id = (int)$pdo->lastInsertId();

  /* =========================
     4) Insert ITENS_VENDA
     ========================= */
  $insItem = $pdo->prepare("
    INSERT INTO itens_venda
      (venda_id, produto_id, produto_nome, quantidade, preco_unitario,
       ncm, cest, cfop, origem, tributacao, unidade, informacoes_adicionais)
    VALUES
      (:venda_id, :produto_id, :produto_nome, :quantidade, :preco_unitario,
       :ncm, :cest, :cfop, :origem, :tributacao, :unidade, :info)
  ");

  foreach ($itens as $it) {
    $insItem->execute([
      ':venda_id'        => $venda_id,
      ':produto_id'      => $it['produto_id'],
      ':produto_nome'    => $it['produto_nome'],
      ':quantidade'      => $it['quantidade'],
      ':preco_unitario'  => $it['preco_unitario'],
      ':ncm'             => $it['ncm'],
      ':cest'            => $it['cest'],
      ':cfop'            => $it['cfop'],
      ':origem'          => $it['origem'],
      ':tributacao'      => $it['tributacao'],
      ':unidade'         => $it['unidade'],
      ':info'            => $it['informacoes_adicionais']
    ]);
  }

  /* =========================
     5) Baixar ESTOQUE
     ========================= */
  $updEst = $pdo->prepare("UPDATE estoque SET quantidade_produto = quantidade_produto - :qtd WHERE id = :id");
  foreach ($itens as $it) {
    $updEst->execute([':qtd'=>$it['quantidade'], ':id'=>$it['produto_id']]);
  }

  /* =========================
     6) Atualizar CAIXA (totais)
     ========================= */
  $updCx = $pdo->prepare("
    UPDATE aberturas
       SET valor_total = COALESCE(valor_total,0) + :vtotal,
           quantidade_vendas = COALESCE(quantidade_vendas,0) + 1
     WHERE id = :id
       AND status = 'aberto'
       AND empresa_id = :emp
       AND cpf_responsavel = :cpf
  ");
  $updCx->execute([
    ':vtotal' => $valor_total,
    ':id'     => $id_caixa,
    ':emp'    => $empresa_id,
    ':cpf'    => $cpf_resp
  ]);
  if ($updCx->rowCount() === 0) {
    $pdo->rollBack();
    jexit(409,'CAIXA_INVALIDADO','O caixa deixou de estar aberto durante a operação. Tente novamente.');
  }

  $pdo->commit();

  // ====== Sucesso: redirecionar para emitir.php ou retornar JSON ======
  if ($redirect) {
    // Saia do modo JSON e redirecione
    header_remove('Content-Type'); // remove JSON header
    $qs = http_build_query(['venda_id' => $venda_id, 'id' => $empresa_id]);
    $url = '../../../nfce/emitir.php?' . $qs; // emitir.php no mesmo diretório
    if (!headers_sent()) {
      header("Location: $url", true, 302);
      exit;
    } else {
      // Fallback: inclui emitir.php no mesmo processo
      $_GET['venda_id'] = $venda_id;
      $_GET['id'] = $empresa_id;
      require __DIR__ . '/../../../nfce/emitir.php';
      exit;
    }
  } else {
    jok([
      'message'     => 'Venda cadastrada com sucesso.',
      'venda_id'    => $venda_id,
      'id_caixa'    => $id_caixa,
      'empresa_id'  => $empresa_id,
      'valor_total' => $valor_total
    ]);
  }

} catch (PDOException $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  jexit(500,'DB_ERROR','Erro de banco de dados ao processar a venda.', [
    'sqlstate'=>$e->getCode(),
    'error'=>$e->getMessage()
  ]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  jexit(500,'UNEXPECTED','Erro inesperado.', ['error'=>$e->getMessage()]);
}

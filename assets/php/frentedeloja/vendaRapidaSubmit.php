<?php
declare(strict_types=1);
ini_set('display_errors','1');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
date_default_timezone_set('America/Manaus');
session_start();

/* ===== Conexão DIRETA ===== */
$host     = 'localhost';
$dbname   = 'u920914488_ERP';
$username = 'u920914488_ERP';
$password = 'N8r=$&Wrs$';

try {
  $pdo = new PDO(
    "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
    $username,
    $password,
    [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ]
  );
} catch (PDOException $e) {
  http_response_code(500);
  exit('Erro na conexão: '.$e->getMessage());
}

/* ===== Helpers ===== */
function soDig($s){ return preg_replace('/\D+/', '', (string)$s); }
function norm($s){ return strtolower(trim(preg_replace('/\s+/', '', (string)$s))); }
function asFloat($v){ return (float)str_replace(',', '.', (string)$v); }

/** Retorna um id de caixa válido pra FK: 1) aberto por empresa/cpf; 2) último da empresa; 3) último geral */
function pickValidCaixa(PDO $pdo, string $empresaId, ?string $cpfResp=null): int {
  if ($cpfResp) {
    $q=$pdo->prepare("SELECT id FROM aberturas WHERE status='aberto' AND empresa_id=:e AND cpf_responsavel=:c ORDER BY abertura_datetime DESC LIMIT 1");
    $q->execute([':e'=>$empresaId, ':c'=>$cpfResp]);
    if ($id=$q->fetchColumn()) return (int)$id;
  }
  $q=$pdo->prepare("SELECT id FROM aberturas WHERE empresa_id=:e ORDER BY abertura_datetime DESC LIMIT 1");
  $q->execute([':e'=>$empresaId]);
  if ($id=$q->fetchColumn()) return (int)$id;

  $q=$pdo->query("SELECT id FROM aberturas ORDER BY abertura_datetime DESC LIMIT 1");
  if ($id=$q->fetchColumn()) return (int)$id;

  // último fallback para satisfazer FK se existir algum registro
  $q=$pdo->query("SELECT MIN(id) FROM aberturas");
  $id=$q->fetchColumn();
  if ($id) return (int)$id;

  // se não houver nenhum caixa mesmo, é melhor abortar e orientar abrir um caixa
  throw new RuntimeException('Nenhum caixa encontrado. Abra um caixa antes de vender.');
}

/** Resolve produto_id: usa id; senão, tenta por nome; senão, NULL (recomenda-se FK aceitar NULL) */
function resolverProdutoId(PDO $pdo, $produtoId, string $nome, ?string $empresaId=null): ?int {
  $pid = (int)($produtoId ?? 0);
  if ($pid>0) {
    $q=$pdo->prepare("SELECT id FROM estoque WHERE id=:id LIMIT 1");
    $q->execute([':id'=>$pid]);
    if ($q->fetchColumn()) return $pid;
  }
  if ($nome!=='') {
    if ($empresaId) {
      $q=$pdo->prepare("SELECT id FROM estoque WHERE nome_produto=:n AND empresa_id=:e LIMIT 1");
      $q->execute([':n'=>$nome, ':e'=>$empresaId]);
    } else {
      $q=$pdo->prepare("SELECT id FROM estoque WHERE nome_produto=:n LIMIT 1");
      $q->execute([':n'=>$nome]);
    }
    if ($id=$q->fetchColumn()) return (int)$id;
  }
  return null;
}

/* ===== Entrada ===== */
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); exit('Método inválido'); }

$empresa_id   = $_POST['empresa_id'] ?? ($_GET['id'] ?? ($_SESSION['empresa_id'] ?? 'principal_1'));
$responsavel  = $_POST['responsavel'] ?? ($_SESSION['usuario'] ?? $_SESSION['nome'] ?? 'Usuário');
$cpfResp      = soDig($_POST['cpf_responsavel'] ?? ($_POST['cpf'] ?? ($_SESSION['usuario_cpf'] ?? '')));
$cpfCliente   = soDig($_POST['cpf_cliente'] ?? '');

/* Pagamento */
$formaPagamentoStr = $_POST['forma_pagamento'] ?? ($_POST['forma_pagto'] ?? 'Dinheiro');
$mapFP = ['dinheiro'=>'01','pix'=>'17','pixqr'=>'17','pixcopiaecola'=>'17','credito'=>'03','cartaocredito'=>'03','cartãocredito'=>'03','debito'=>'04','cartaodebito'=>'04','cartãodebito'=>'04','boleto'=>'15','vale'=>'99','outros'=>'99'];
$tPagCodigo  = $mapFP[norm($formaPagamentoStr)] ?? (preg_match('/^\d{2}$/',$formaPagamentoStr)?$formaPagamentoStr:'01');
$formaPagDescricao = $_POST['forma_pagamento'] ?? 'Dinheiro';

$valorRecebido = isset($_POST['valor_recebido']) ? asFloat($_POST['valor_recebido']) : 0.00;
$troco         = isset($_POST['troco']) ? asFloat($_POST['troco']) : 0.00;

/* Itens (JSON ou arrays) */
$itensReq=[];
if (!empty($_POST['itens'])) {
  $tmp=json_decode((string)$_POST['itens'],true);
  if (is_array($tmp)) $itensReq=$tmp;
} else {
  $produtos=(array)($_POST['produtos']??[]);
  $quantid =(array)($_POST['quantidades']??($_POST['qtd']??[]));
  $valores =(array)($_POST['valores']??($_POST['precos']??$_POST['vunit']??[]));
  $ncm     =(array)($_POST['ncm']??[]);
  $cest    =(array)($_POST['cest']??[]);
  $cfop    =(array)($_POST['cfop']??[]);
  $origem  =(array)($_POST['origem']??[]);
  $trib    =(array)($_POST['tributacao']??($_POST['cst']??[]));
  $unid    =(array)($_POST['unidade']??[]);
  $info    =(array)($_POST['informacoes_adicionais']??($_POST['info']??[]));
  $n = max(count($produtos),count($quantid),count($valores));
  for($i=0;$i<$n;$i++){
    $desc = isset($produtos[$i])?trim((string)$produtos[$i]):'';
    $qtd  = isset($quantid[$i])?(float)$quantid[$i]:0.0;
    $vun  = isset($valores[$i])?asFloat($valores[$i]):0.0;
    if($desc!=='' && $qtd>0 && $vun>=0){
      $itensReq[]=[
        'produto_id'=>0,
        'desc'=>$desc,'qtd'=>$qtd,'vun'=>$vun,
        'ncm'=>$ncm[$i]??null,'cest'=>$cest[$i]??null,'cfop'=>$cfop[$i]??null,
        'origem'=>$origem[$i]??null,'tributacao'=>$trib[$i]??null,'unidade'=>$unid[$i]??null,'info'=>$info[$i]??null
      ];
    }
  }
}
if (count($itensReq)===0){ http_response_code(400); exit('Nenhum item para venda.'); }

/* Totais */
$valor_total=0.0; foreach($itensReq as $it){ $valor_total += ((float)$it['qtd'])*((float)$it['vun']); } $valor_total=round($valor_total,2);

/* Gravação */
try{
  $pdo->beginTransaction();

  $id_caixa = pickValidCaixa($pdo,(string)$empresa_id,$cpfResp); // garante FK de vendas->aberturas

  $st=$pdo->prepare("INSERT INTO vendas
    (responsavel, cpf_responsavel, cpf_cliente, forma_pagamento, valor_total, valor_recebido, troco, empresa_id, id_caixa, data_venda, chave_nfce, status_nfce)
    VALUES
    (:responsavel,:cpf_responsavel,:cpf_cliente,:forma_pagamento,:valor_total,:valor_recebido,:troco,:empresa_id,:id_caixa,NOW(),NULL,'pendente')");
  $st->execute([
    ':responsavel'=>$responsavel,
    ':cpf_responsavel'=>$cpfResp?:null,
    ':cpf_cliente'=>$cpfCliente?:null,
    ':forma_pagamento'=>$formaPagDescricao, // legível
    ':valor_total'=>$valor_total,
    ':valor_recebido'=>$valorRecebido,
    ':troco'=>$troco,
    ':empresa_id'=>$empresa_id,
    ':id_caixa'=>$id_caixa,
  ]);
  $venda_id=(int)$pdo->lastInsertId();
  if($venda_id<=0) throw new RuntimeException('ID da venda não retornado.');

  $sti=$pdo->prepare("INSERT INTO itens_venda
    (venda_id, produto_id, produto_nome, quantidade, preco_unitario, ncm, cest, cfop, origem, tributacao, unidade, informacoes_adicionais)
    VALUES
    (:venda_id,:produto_id,:produto_nome,:quantidade,:preco_unitario,:ncm,:cest,:cfop,:origem,:tributacao,:unidade,:info)");

  foreach($itensReq as $it){
    $produtoId = resolverProdutoId($pdo, $it['produto_id']??null, (string)$it['desc'], (string)$empresa_id);
    $sti->execute([
      ':venda_id'=>$venda_id,
      ':produto_id'=>$produtoId, // pode ser NULL (recomenda-se FK aceitar NULL)
      ':produto_nome'=>(string)$it['desc'],
      ':quantidade'=>(int)round((float)$it['qtd']), // se precisar frações, troque para DECIMAL na tabela
      ':preco_unitario'=>(float)$it['vun'],
      ':ncm'=>!empty($it['ncm'])?(string)$it['ncm']:null,
      ':cest'=>!empty($it['cest'])?(string)$it['cest']:null,
      ':cfop'=>!empty($it['cfop'])?(string)$it['cfop']:null,
      ':origem'=>!empty($it['origem'])?(string)$it['origem']:null,
      ':tributacao'=>!empty($it['tributacao'])?(string)$it['tributacao']:null,
      ':unidade'=>!empty($it['unidade'])?(string)$it['unidade']:null,
      ':info'=>!empty($it['info'])?(string)$it['info']:null,
    ]);
  }

  $pdo->commit();

} catch(Throwable $e){
  if($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  exit('Erro ao gravar venda: '.$e->getMessage());
}

/* Emissão imediata (comprovante/nota) */
$_POST = []; $_GET = [];
$_POST['itens'] = json_encode(array_map(function($it){
  return [
    'desc'=>(string)$it['desc'],
    'qtd'=>(float)$it['qtd'],
    'vun'=>(float)$it['vun'],
    'ncm'=>$it['ncm']??null,'cfop'=>$it['cfop']??null,'unid'=>$it['unidade']??null,
    'info'=>$it['info']??null,'cest'=>$it['cest']??null,'origem'=>$it['origem']??null,'trib'=>$it['tributacao']??null,
  ];
}, $itensReq), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$_POST['cpf'] = $cpfCliente;
$_POST['venda_id'] = (string)$venda_id; // útil para o emissor atualizar a tabela vendas

require __DIR__.'/../nfce/emitir.php';
exit;

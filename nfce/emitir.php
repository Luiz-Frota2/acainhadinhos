<?php
// === Wrapper de emissão para layout Venda Rápida ===
ob_start(); // captura qualquer HTML que a lógica atual imprimir
// Normaliza entrada: body JSON e/ou itens_json do form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $ct  = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false && $raw) {
        $_POST['_json_payload'] = $raw;
    }
    if (!empty($_POST['itens_json']) && empty($_POST['_json_payload'])) {
        $_POST['_json_payload'] = $_POST['itens_json'];
    }
}
// A lógica original virá abaixo (mantida)
?><?php
// emitir.php — NFC-e (mod 65) com NFePHP: CHAVE correta, assina <infNFe>, envia ARRAY, e exibe link do DANFE (sem auto-print)

// Autoload + config se existirem
if (file_exists(__DIR__ . '/vendor/autoload.php')) require __DIR__ . '/vendor/autoload.php';
if (file_exists(__DIR__ . '/config.php'))          require __DIR__ . '/config.php';


use NFePHP\Common\Certificate;
use NFePHP\NFe\Tools;
use NFePHP\NFe\Common\Standardize;

header('Content-Type: text/html; charset=utf-8');

/* ===== Helpers (guards) ===== */
if (!function_exists('soDig')) { function soDig($s){ return preg_replace('/\D+/', '', (string)$s); } }
if (!function_exists('pad'))   { function pad($n,$t){ return str_pad((string)$n,(int)$t,'0',STR_PAD_LEFT); } }
if (!function_exists('e'))     { function e($s){ return htmlspecialchars((string)$s, ENT_XML1|ENT_COMPAT, 'UTF-8'); } }
if (!function_exists('mod11')){
  function mod11(string $num): int { $f=[2,3,4,5,6,7,8,9]; $s=0;$k=0; for($i=strlen($num)-1;$i>=0;$i--){ $s+=(int)$num[$i]*$f[$k++%count($f)]; }
    $r=$s%11; return ($r==0||$r==1)?0:(11-$r); }
}
if (!function_exists('nfeproc')) {
  function nfeproc($nfe,$prot){
    $nfe = preg_replace('/<\?xml.*?\?>/','', $nfe);
    return '<?xml version="1.0" encoding="UTF-8"?>'
         . '<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">'
         . $nfe.$prot.'</nfeProc>';
  }
}

if (!function_exists('mapFormaToTPag')) {
  /**
   * Converte a forma (texto ou número) para o código tPag da NFC-e (2 dígitos).
   * Aceita valores como: "01", "1", "pix", "débito", "credito", etc.
   */
  function mapFormaToTPag($forma): string {
    $f = (string)$forma;
    $fTrim = trim(mb_strtolower($f, 'UTF-8'));

    // Já é numérico? Normaliza para 2 dígitos (ex.: "1" -> "01")
    if (preg_match('/^\d+$/', $fTrim)) {
      return str_pad(substr($fTrim, -2), 2, '0', STR_PAD_LEFT);
    }

    // Normaliza pontuação/acentos básicos
    $norm = iconv('UTF-8', 'ASCII//TRANSLIT', $fTrim);
    $norm = preg_replace('/[^a-z0-9]+/',' ', $norm);
    $norm = trim($norm);

    // Mapa de textos → códigos
    $map = [
      'dinheiro'                 => '01', 'cash' => '01',
      'cheque'                   => '02',
      'cartao de credito'        => '03', 'credito' => '03', 'cartao credito' => '03', 'credito loja' => '05',
      'cartao de debito'         => '04', 'debito' => '04', 'cartao debito' => '04',
      'vale alimentacao'         => '10', 'va' => '10',
      'vale refeicao'            => '11', 'vr' => '11',
      'vale presente'            => '12',
      'vale combustivel'         => '13',
      'boleto'                   => '15',
      'deposito'                 => '16',
      'pix'                      => '17', 'qr' => '17', 'qr code' => '17',
      'transferencia'            => '18', 'carteira' => '18', 'carteira digital' => '18',
      'programa de fidelidade'   => '19',
      'sem pagamento'            => '90',
      // dentro do array $map de mapFormaToTPag()
'pix estatico' => '20', 'pix estático' => '20', 'qrcode estatico' => '20', 'qr code estatico' => '20',

      'outros'                   => '99',
    ];

    if (isset($map[$norm])) {
      return $map[$norm];
    }

    // Heurísticas simples
    if (strpos($norm, 'pix') !== false) return '17';
    if (strpos($norm, 'debito') !== false) return '04';
    if (strpos($norm, 'credito') !== false) return '03';
    if (strpos($norm, 'boleto') !== false) return '15';
    if (strpos($norm, 'deposit') !== false) return '16';
    if (strpos($norm, 'transfer') !== false) return '18';

    // Desconhecido → dinheiro
    return '01';
  }
}


/* ===== Config NFePHP ===== */
$nfceConfig = file_exists(__DIR__.'/nfce_config.php')
  ? require __DIR__.'/nfce_config.php'
  : [
      'atualizacao' => date('Y-m-d H:i:s'),
      'tpAmb'       => (int)TP_AMB,
      'razaosocial' => EMIT_XNOME,
      'siglaUF'     => EMIT_UF,
      'cnpj'        => EMIT_CNPJ,
      'schemes'     => 'PL_009_V4',
      'versao'      => '4.00',
      'urlChave'    => (defined('URL_CHAVE') ? constant('URL_CHAVE') : ''),
      'urlQRCode'   => (defined('URL_QR') ? constant('URL_QR') : ''),
      'CSC'         => CSC,
      'CSCid'       => ID_TOKEN,
      'proxyConf'   => ['proxyIp'=>'','proxyPort'=>'','proxyUser'=>'','proxyPass'=>''],
    ];

$configJson = json_encode($nfceConfig, JSON_UNESCAPED_UNICODE);

/* ===== Entrada do PDV ===== */
/* Aceita:
   1) $_POST['itens'] como JSON string
   2) Corpo JSON (Content-Type: application/json) => {"itens":[...], "cpf": "..."}
   3) Carrega a partir do banco usando ?venda_id=123 (tabelas vendas/itens_venda)
*/
$payload = [];
$ct = $_SERVER['CONTENT_TYPE'] ?? '';
$raw = file_get_contents('php://input');
if ($raw && stripos($ct, 'application/json') !== false) {
  $payload = json_decode($raw, true) ?: [];
}
if (!$payload && isset($_POST['payload'])) {
  $payload = json_decode((string)$_POST['payload'], true) ?: [];
}

$itens = json_decode($_POST['itens'] ?? '[]', true) ?: ($payload['itens'] ?? []);

/* Se ainda não houver itens, tenta buscar pelo venda_id no banco */
if (!$itens && isset($_GET['venda_id'])) {
  $venda_id = (int)$_GET['venda_id'];

  /* Carrega conexão (mesmos candidatos do vendaRapidaSubmit.php) */
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
if ($__found && isset($pdo) && $pdo instanceof PDO) {
  try {
    // Busca itens diretamente
    $st = $pdo->prepare("SELECT produto_id, produto_nome, quantidade, preco_unitario, unidade, ncm, cfop
                           FROM itens_venda
                          WHERE venda_id = :v");
    $st->execute([':v'=>$venda_id]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
      $itens[] = [
        'id'   => (int)$r['produto_id'],
        'desc' => (string)$r['produto_nome'],
        'qtd'  => (float)$r['quantidade'],
        'vun'  => (float)$r['preco_unitario'],
        'unid' => $r['unidade'] !== null && $r['unidade'] !== '' ? (string)$r['unidade'] : 'UN',
        'ncm'  => (string)($r['ncm'] ?? ''),
        'cfop' => (string)($r['cfop'] ?? '')
      ];
    }
  
    // Carrega dados agregados da venda (cpf_cliente, forma_pagamento, valores)
    $vendaRow = null;
    try {
      $stV = $pdo->prepare("SELECT cpf_cliente, forma_pagamento, valor_total, valor_recebido, troco
                              FROM vendas
                             WHERE id = :v
                             LIMIT 1");
      $stV->execute([':v' => $venda_id]);
      $vendaRow = $stV->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $eV) {
      // silencioso
    }
  } catch (Throwable $e) {
    // Se der erro de banco, ignora e segue (cairá no "Sem itens")
  }
} else {
  // Não foi possível carregar a conexão PDO; não buscar itens do banco
}
}

/* Normaliza itens vindos por JSON em formato alternativo */
if ($itens) {
  $_norm = [];
  foreach ($itens as $it) {
    $desc = $it['desc'] ?? $it['produto_nome'] ?? $it['nome'] ?? ($it['desc'] ?? '');
    $qtd  = $it['qtd']  ?? $it['quantidade'] ?? $it['qCom'] ?? 1;
    $vun  = $it['vun']  ?? $it['preco_unitario'] ?? $it['vUn'] ?? $it['vUnCom'] ?? 0;
    $un   = $it['unid'] ?? $it['un'] ?? $it['uCom'] ?? 'UN';
    $ncm  = $it['ncm']  ?? '';
    $cfop = $it['cfop'] ?? '';
    if ($desc !== '' && (float)$qtd > 0) {
      $_norm[] = [
        'desc' => (string)$desc,
        'qtd'  => (float)$qtd,
        'vun'  => (float)$vun,
        'unid' => (string)$un,
        'ncm'  => (string)$ncm,
        'cfop' => (string)$cfop
      ];
    }
  }
  $itens = $_norm;
}

if (!$itens) die('Sem itens.');

/* Documento do destinatário (opcional) */
$docInput = soDig($_POST['cpf'] ?? $payload['cpf'] ?? $payload['cpf_cliente'] ?? '');
$cpf  = strlen($docInput) === 11 ? $docInput : '';
$cnpj = strlen($docInput) === 14 ? $docInput : '';
// Fallback: se não veio CPF/CNPJ da requisição e temos venda do BD
if (empty($cpf) && !empty($vendaRow['cpf_cliente'])) {
  $docVenda = soDig($vendaRow['cpf_cliente']);
  if (strlen($docVenda) === 11) { $cpf = $docVenda; }
  elseif (strlen($docVenda) === 14) { $cnpj = $docVenda; }
}


/* ===== Certificado A1 ===== */
$pfx = @file_get_contents(PFX_PATH);
if ($pfx === false) die('Não encontrei o PFX em: '.e(PFX_PATH));
try { $cert = Certificate::readPfx($pfx, PFX_PASSWORD); }
catch (Throwable $e) { die('<pre>Falha ao abrir certificado: '.$e->getMessage().'</pre>'); }

/* ===== Tools ===== */
$tools = new Tools($configJson, $cert);
$tools->model('65'); // NFC-e

/* ===== CHAVE + Id ===== */
$cUF    = pad(COD_UF,2);
$AAMM   = date('ym');
$CNPJ   = pad(soDig(EMIT_CNPJ),14);
$mod    = '65';
$serie  = pad((string)NFC_SERIE,3);

// nNF controlado no BD (integracao_nfce.ultimo_numero_nfce) — transacional
$nNF = isset($_POST['nnf']) ? (int)$_POST['nnf'] : null;
if ($nNF === null) {
  // Carregar PDO se ainda não disponível
  if (!isset($pdo) || !($pdo instanceof PDO)) {
    $__found = false;
    foreach ([__DIR__ . '/../conexao/conexao.php', __DIR__ . '/../../conexao/conexao.php', __DIR__ . '/../../../conexao/conexao.php'] as $__p) {
      if (is_file($__p)) { require_once $__p; $__found = true; break; }
    }
    if (!isset($pdo) || !($pdo instanceof PDO)) {
      die('Não consegui carregar a conexão PDO para gerar o número da NFC-e.');
    }
  }
  try {
    $pdo->beginTransaction();
    // trava linha da empresa
    $st = $pdo->prepare("SELECT ultimo_numero_nfce FROM integracao_nfce WHERE empresa_id = :e FOR UPDATE");
    $st->execute([':e' => NFCE_EMPRESA_ID]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) { throw new RuntimeException('integracao_nfce não encontrado para empresa: '.NFCE_EMPRESA_ID); }
    $proximo = (int)$row['ultimo_numero_nfce'] + 1;
    $up = $pdo->prepare("UPDATE integracao_nfce SET ultimo_numero_nfce = :n WHERE empresa_id = :e");
    $up->execute([':n'=>$proximo, ':e'=>NFCE_EMPRESA_ID]);
    $pdo->commit();
    $nNF = (int)$proximo;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die('<pre>Falha ao gerar nNF do BD: '.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8').'</pre>');
  }
}
$nNF = (int)substr(str_pad((string)$nNF,9,'0',STR_PAD_LEFT), -9);
$tpEmis = '1';
$cNF    = pad((string)mt_rand(1,99999999),8);

$base44 = $cUF.$AAMM.$CNPJ.$mod.$serie.pad($nNF,9).$tpEmis.$cNF;
$cDV    = (string)mod11($base44);
$chave  = $base44.$cDV;
$IdNFe  = 'NFe'.$chave;

/* ===== ide / emit / dest ===== */
$ide = [
  'cUF'=>$cUF,'cNF'=>$cNF,'natOp'=>'VENDA','mod'=>65,'serie'=>(int)$serie,'nNF'=>$nNF,
  'dhEmi'=>date('c'),'tpNF'=>1,'idDest'=>1,'cMunFG'=>COD_MUN,'tpImp'=>4,'tpEmis'=>1,'cDV'=>(int)$cDV,
  'tpAmb'=>(int)TP_AMB,'finNFe'=>1,'indFinal'=>1,'indPres'=>1,'procEmi'=>0,'verProc'=>'PDV-ACAI-1.0'
];
$enderEmit = [
  'xLgr'=>EMIT_XLGR,'nro'=>EMIT_NRO,'xBairro'=>EMIT_XBAIRRO,'cMun'=>COD_MUN,'xMun'=>EMIT_XMUN,
  'UF'=>EMIT_UF,'CEP'=>EMIT_CEP,'cPais'=>1058,'xPais'=>'Brasil'
];
if (defined('EMIT_FONE') && preg_match('/^\d{6,14}$/', (string)EMIT_FONE)) {
  $enderEmit['fone'] = EMIT_FONE;
}

$emit = [
  'CNPJ'=>EMIT_CNPJ,'xNome'=>EMIT_XNOME,'xFant'=>EMIT_XFANT,'IE'=>EMIT_IE,'CRT'=>EMIT_CRT,
  'enderEmit'=>$enderEmit
];
$dest = [];
if ($cpf)  $dest = ['CPF'=>$cpf,'indIEDest'=>9];
if ($cnpj) $dest = ['CNPJ'=>$cnpj,'indIEDest'=>9];

/* ===== det dos itens ===== */
$i=1; $vProd=0.00; $detXML='';
foreach ($itens as $it) {
  $xProd=e($it['desc']); $qCom=number_format((float)$it['qtd'],3,'.',''); $vUn=number_format((float)$it['vun'],2,'.','');
  $vTot=number_format((float)$it['qtd']*(float)$it['vun'],2,'.','');
  $ncm=!empty($it['ncm'])?e($it['ncm']):'21069090'; $cfop=!empty($it['cfop'])?e($it['cfop']):'5102';
  $un=!empty($it['unid'])?e($it['unid']):'UN';
  $detXML.='
  <det nItem="'.$i.'">
    <prod>
      <cProd>'.$i.'</cProd><cEAN>SEM GTIN</cEAN><xProd>'.$xProd.'</xProd>
      <NCM>'.$ncm.'</NCM><CFOP>'.$cfop.'</CFOP>
      <uCom>'.$un.'</uCom><qCom>'.$qCom.'</qCom><vUnCom>'.$vUn.'</vUnCom><vProd>'.$vTot.'</vProd>
      <cEANTrib>SEM GTIN</cEANTrib><uTrib>'.$un.'</uTrib><qTrib>'.$qCom.'</qTrib><vUnTrib>'.$vUn.'</vUnTrib>
      <indTot>1</indTot>
    </prod>
    <imposto>
      <ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS>
      <PIS><PISNT><CST>07</CST></PISNT></PIS>
      <COFINS><COFINSNT><CST>07</CST></COFINSNT></COFINS>
    </imposto>
  </det>';
  $i++; $vProd += (float)$it['qtd']*(float)$it['vun'];
}

/* ===== Totais / transp / pag / infAd ===== */
$vProdFmt = number_format($vProd,2,'.','');
$totXML   = '<total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>'.$vProdFmt.'</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>'.$vProdFmt.'</vNF></ICMSTot></total>';
$transpXML= '<transp><modFrete>9</modFrete></transp>';

// Pagamento dinâmico + troco (quando dinheiro)
// ===== NOVO BLOCO =====
$tPagCode   = '01';
$vNFnum     = round((float)$vProd, 2); // Use o mesmo total já calculado para a NFC-e
$valorRec   = 0.00;
$trocoDb    = 0.00;

// 1) Descobrir forma de pagamento e valores vindos da venda/payload
if (!empty($vendaRow)) {
  $tPagCode = mapFormaToTPag($vendaRow['forma_pagamento'] ?? '01');
  $valorRec = isset($vendaRow['valor_recebido']) ? (float)$vendaRow['valor_recebido'] : 0.00;
  $trocoDb  = isset($vendaRow['troco'])          ? (float)$vendaRow['troco']          : 0.00;
} elseif (!empty($payload['pagamento'])) {
  $tPagCode = mapFormaToTPag($payload['pagamento']['tPag'] ?? '01');
  $valorRec = isset($payload['pagamento']['vPag'])   ? (float)$payload['pagamento']['vPag']   : 0.00;
  $trocoDb  = isset($payload['pagamento']['vTroco']) ? (float)$payload['pagamento']['vTroco'] : 0.00;
}

// 2) PIX não integrado? Preferir tPag=20 (PIX estático)
if ($tPagCode === '17') {
  // se você não tiver cAut (endToEndId) ou integração TEF, trate como PIX estático
  $temAut = !empty($payload['pagamento']['cAut']) || !empty($_POST['cAut']);
  if (!$temAut) {
    $tPagCode = '20'; // PIX – Estático (IT 2024.002)
  }
}

// 3) Calcular vPag e vTroco conforme a regra da Sefaz
$vPagVal   = 0.00;
$vTrocoVal = 0.00;

if ($tPagCode === '01') { // Dinheiro
  // Se não veio valor recebido, reconstrói a partir do troco do banco
  if ($valorRec <= 0 && $trocoDb > 0) {
    $valorRec = $vNFnum + $trocoDb;
  }
  // vPag = quanto o cliente entregou; vTroco = vPag - vNF (se houver)
  $vPagVal   = max($vNFnum, round($valorRec, 2));
  $vTrocoVal = max(0.00, round($vPagVal - $vNFnum, 2));
} else {
  // Cartão/PIX/… normalmente não têm troco
  $vPagVal   = ($valorRec > 0 ? round($valorRec, 2) : $vNFnum);
  $vTrocoVal = 0.00;
}

// 4) Montar XML <pag> com <card> quando necessário
$pagXML  = '<pag><detPag>';
$pagXML .=   '<indPag>0</indPag>'; // à vista
$pagXML .=   '<tPag>'.$tPagCode.'</tPag>';
$pagXML .=   '<vPag>'.number_format($vPagVal, 2, '.', '').'</vPag>';

// Se for Cartão de Crédito (03), Débito (04) ou PIX dinâmico (17), informar <card>
if (in_array($tPagCode, ['03','04','17'], true)) {
  // Por padrão considere NÃO integrado (tpIntegra=2) para não exigir CNPJ/cAut
  $tpIntegra = 2;
  $cAut = null;

  // Se você capturar o NSU/autorização do cartão ou o endToEndId do PIX dinâmico, use tpIntegra=1 e informe cAut
  if (!empty($payload['pagamento']['cAut']) || !empty($_POST['cAut'])) {
    $tpIntegra = 1;
    $cAut = htmlspecialchars((string)($_POST['cAut'] ?? $payload['pagamento']['cAut']), ENT_XML1|ENT_COMPAT, 'UTF-8');
  }

  $pagXML .= '<card><tpIntegra>'.$tpIntegra.'</tpIntegra>';
  if ($tpIntegra === 1 && $cAut) {
    $pagXML .= '<cAut>'.$cAut.'</cAut>';
    // Opcional: se você tiver o CNPJ da credenciadora (adquirente/subadquirente), informe aqui:
    // $pagXML .= '<CNPJ>XXXXXXXXXXXXXX</CNPJ>';
    // Opcional: se souber a bandeira, informe <tBand> (01=Visa, 02=Mastercard, 03=Amex, 04=Sorocred, 99=Outros...)
    // $pagXML .= '<tBand>99</tBand>';
  }
  $pagXML .= '</card>';
}

$pagXML .= '</detPag>';

// vTroco só quando for dinheiro e existir troco
if ($tPagCode === '01' && $vTrocoVal > 0) {
  $pagXML .= '<vTroco>'.number_format($vTrocoVal, 2, '.', '').'</vTroco>';
}

$pagXML .= '</pag>';
// ===== FIM DO NOVO BLOCO =====

$infAd   = '<infAdic><infCpl>PDV Açaiteria</infCpl></infAdic>';

/* ===== MONTA SÓ A NFe (para assinar) ===== */
$nfe = '<?xml version="1.0" encoding="UTF-8"?>'
     . '<NFe xmlns="http://www.portalfiscal.inf.br/nfe">'
     .   '<infNFe Id="'.$IdNFe.'" versao="4.00">'
     .     '<ide>'
     .       '<cUF>'.$ide['cUF'].'</cUF><cNF>'.$ide['cNF'].'</cNF><natOp>'.$ide['natOp'].'</natOp>'
     .       '<mod>'.$ide['mod'].'</mod><serie>'.$ide['serie'].'</serie><nNF>'.$ide['nNF'].'</nNF>'
     .       '<dhEmi>'.$ide['dhEmi'].'</dhEmi><tpNF>'.$ide['tpNF'].'</tpNF><idDest>'.$ide['idDest'].'</idDest>'
     .       '<cMunFG>'.$ide['cMunFG'].'</cMunFG><tpImp>'.$ide['tpImp'].'</tpImp><tpEmis>'.$ide['tpEmis'].'</tpEmis>'
     .       '<cDV>'.$ide['cDV'].'</cDV><tpAmb>'.$ide['tpAmb'].'</tpAmb><finNFe>'.$ide['finNFe'].'</finNFe>'
     .       '<indFinal>'.$ide['indFinal'].'</indFinal><indPres>'.$ide['indPres'].'</indPres>'
     .       '<procEmi>'.$ide['procEmi'].'</procEmi><verProc>'.$ide['verProc'].'</verProc>'
     .     '</ide>'
     .     '<emit>'
     .       '<CNPJ>'.$emit['CNPJ'].'</CNPJ><xNome>'.$emit['xNome'].'</xNome><xFant>'.$emit['xFant'].'</xFant>'
     .       '<enderEmit><xLgr>'.$emit['enderEmit']['xLgr'].'</xLgr><nro>'.$emit['enderEmit']['nro'].'</nro><xBairro>'.$emit['enderEmit']['xBairro'].'</xBairro><cMun>'.$emit['enderEmit']['cMun'].'</cMun><xMun>'.$emit['enderEmit']['xMun'].'</xMun><UF>'.$emit['enderEmit']['UF'].'</UF><CEP>'.$emit['enderEmit']['CEP'].'</CEP><cPais>'.$emit['enderEmit']['cPais'].'</cPais><xPais>'.$emit['enderEmit']['xPais'].'</xPais><fone>'.$emit['enderEmit']['fone'].'</fone></enderEmit>'
     .       '<IE>'.$emit['IE'].'</IE><CRT>'.$emit['CRT'].'</CRT>'
     .     '</emit>'
     .      (!empty($dest)
        ? ('<dest>'.(isset($dest['CPF'])?'<CPF>'.$dest['CPF'].'</CPF>':'<CNPJ>'.$dest['CNPJ'].'</CNPJ>').'<indIEDest>'.$dest['indIEDest'].'</indIEDest></dest>')
        : '')
     .     $detXML
     .     $totXML
     .     $transpXML
     .     $pagXML
     .     $infAd
     .   '</infNFe>'
     . '</NFe>';

/* ===== Assina SOMENTE a NFe ===== */
try { $nfeAss = $tools->signNFe($nfe); }
catch (Throwable $e) { die('<pre>Falha ao assinar: '.$e->getMessage().'</pre>'); }

/* ===== Envia — passa ARRAY de NFe assinadas ===== */
try {
  $respEnv = $tools->sefazEnviaLote([$nfeAss], '1', 1);
} catch (Throwable $e) {
  try { $respEnv = $tools->sefazEnviaLote([$nfeAss], '1', false, 1); }
  catch (Throwable $e2) { die('<pre>Falha na autorização: '.$e->getMessage()."\n\n".$e2->getMessage()."</pre>"); }
}

$stdEnv = (new Standardize)->toStd($respEnv);

/* ===== Trata retorno ===== */
if (!empty($stdEnv->cStat) && (int)$stdEnv->cStat === 104) {
  if (!preg_match('~(<protNFe[^>]*>.*?</protNFe>)~s', $respEnv, $mProt)) {
    die("Autorizado, mas não localizei protNFe no retorno.");
  }
  $proc = nfeproc($nfeAss, $mProt[1]);
  $xmlPath = __DIR__ . '/procNFCe_'.$chave.'.xml';
  file_put_contents($xmlPath, $proc);


  // ===== Persistir retorno SEFAZ 104 em nfce_emitidas (antes do redirect) =====
  try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
      $__found = false;
      foreach ([__DIR__ . '/../conexao/conexao.php', __DIR__ . '/../../conexao/conexao.php', __DIR__ . '/../../../conexao/conexao.php'] as $__p) {
        if (is_file($__p)) { require_once $__p; $__found = true; break; }
      }
    }
    if (isset($pdo) && ($pdo instanceof PDO)) {
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $cStat   = (string)($stdEnv->cStat ?? '');
      $xMotivo = (string)($stdEnv->xMotivo ?? '');
      $tpAmb   = (int)TP_AMB;
      $serieI  = (int)NFC_SERIE;
      $nnfI    = isset($nNF) ? (int)$nNF : 0;
      $vendaId = isset($venda_id) ? (string)$venda_id : (isset($_GET['venda_id']) ? (string)$_GET['venda_id'] : null);
      $chaveI  = isset($chave) ? (string)$chave : (isset($chaveI) ? (string)$chaveI : '');

      $xmlProcContent = isset($proc) ? $proc : (isset($xmlProcPath) && is_file($xmlProcPath) ? @file_get_contents($xmlProcPath) : null);
      $xmlEnvio       = isset($nfeAss) ? $nfeAss : (isset($nfe) ? $nfe : null);
      $xmlRetorno     = isset($respEnv) ? $respEnv : (isset($ret) ? $ret : null);

      // Extrai protocolo do protNFe capturado ($mProt)
      $nProt = null;
      if (isset($mProt[1]) && preg_match('~<nProt>([^<]+)</nProt>~', $mProt[1], $m2)) {
        $nProt = $m2[1];
      }

      // Monta JSON de pagamento se existir código de tPag
      $tpagJsonStr = isset($tPagCode) ? json_encode(['tPag' => $tPagCode], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null;

      // tenta identificar vNF
      $vNFnum = null;
      if (isset($proc) && preg_match('~<vNF>([0-9]+\.[0-9]{2})</vNF>~', $proc, $mV)) {
        $vNFnum = $mV[1];
      } elseif (isset($nfeAss) && preg_match('~<vNF>([0-9]+\.[0-9]{2})</vNF>~', $nfeAss, $mV)) {
        $vNFnum = $mV[1];
      }

      $vTrocoVal = isset($vTrocoVal) ? $vTrocoVal : (isset($stdPag->vTroco) ? $stdPag->vTroco : null);

      $st = $pdo->prepare("
        INSERT INTO nfce_emitidas
          (empresa_id, venda_id, ambiente, serie, numero, chave, protocolo, status_sefaz, mensagem,
           xml_nfeproc, xml_envio, xml_retorno, valor_total, valor_troco, tpag_json)
        VALUES
          (:empresa_id, :venda_id, :ambiente, :serie, :numero, :chave, :protocolo, :status_sefaz, :mensagem,
           :xml_nfeproc, :xml_envio, :xml_retorno, :valor_total, :valor_troco, :tpag_json)
        ON DUPLICATE KEY UPDATE
          protocolo    = VALUES(protocolo),
          status_sefaz = VALUES(status_sefaz),
          mensagem     = VALUES(mensagem),
          xml_nfeproc  = VALUES(xml_nfeproc),
          xml_envio    = VALUES(xml_envio),
          xml_retorno  = VALUES(xml_retorno),
          valor_total  = VALUES(valor_total),
          valor_troco  = VALUES(valor_troco),
          tpag_json    = VALUES(tpag_json),
          created_at   = NOW()
      ");

      $st->execute([
        ':empresa_id'   => defined('NFCE_EMPRESA_ID') ? constant('NFCE_EMPRESA_ID') : (defined('EMPRESA_ID') ? constant('EMPRESA_ID') : ''),
        ':venda_id'     => $vendaId,
        ':ambiente'     => $tpAmb,
        ':serie'        => $serieI,
        ':numero'       => $nnfI,
        ':chave'        => $chaveI,
        ':protocolo'    => $nProt,
        ':status_sefaz' => $cStat,
        ':mensagem'     => $xMotivo,
        ':xml_nfeproc'  => $xmlProcContent,
        ':xml_envio'    => $xmlEnvio,
        ':xml_retorno'  => $xmlRetorno,
        ':valor_total'  => isset($vNFnum) ? number_format((float)$vNFnum, 2, '.', '') : null,
        ':valor_troco'  => isset($vTrocoVal) ? number_format((float)$vTrocoVal, 2, '.', '') : null,
        ':tpag_json'    => $tpagJsonStr
      ]);
    }
  } catch (Throwable $e) {
    error_log('Falha ao gravar nfce_emitidas (104): ' . $e->getMessage());
  }
   $empresaId = $_POST['empresa_id'] ?? ($_GET['id'] ?? '');
  // ======= SEM AUTO-PRINT: só mostra link para abrir/imprimir =======
  // ======= AGORA: redireciona direto para o DANFE =======
  $danfeUrl = '../../../nfce/danfe_nfce.php?chave=' . urlencode($chave)
     . '&venda_id=' . urlencode((string)$vendaId)
     . '&id=' . urlencode($empresaId); // mesmo diretório
  // limpa qualquer saída anterior para poder enviar headers
  while (ob_get_level() > 0) { ob_end_clean(); }
  if (!headers_sent()) {
    header('Location: ' . $danfeUrl);
    exit;
  }
  // Fallback JS se headers já enviados
  echo '<!doctype html><meta charset="utf-8">';
  echo '<script>location.replace(' . json_encode($danfeUrl) . ');</script>';
  exit;
}

if (!empty($stdEnv->cStat) && (int)$stdEnv->cStat === 103 && !empty($stdEnv->infRec->nRec)) {
  $nRec = (string)$stdEnv->infRec->nRec;
  sleep(2);
  $ret = $tools->sefazConsultaRecibo($nRec);
  if (preg_match('~(<protNFe[^>]*>.*?</protNFe>)~s', $ret, $mProt)) {
    $proc = nfeproc($nfeAss, $mProt[1]);
    $xmlPath = __DIR__ . '/procNFCe_'.$chave.'.xml';
    file_put_contents($xmlPath, $proc);

    // ===== Persistir retorno SEFAZ no BD =====
    try {
      if (!isset($pdo) || !($pdo instanceof PDO)) {
        $__found = false;
        foreach ([ __DIR__ . '/../../assets/php/conexao.php',
  __DIR__ . '/../../ERP/assets/php/conexao.php',
  __DIR__ . '/../dashboard/php/conexao.php',
  $_SERVER['DOCUMENT_ROOT'] . '/assets/php/conexao.php',
  $_SERVER['DOCUMENT_ROOT'] . '/ERP/assets/php/conexao.php',] as $__p) {
          if (is_file($__p)) { require_once $__p; $__found = true; break; }
        }
      }
      if (isset($pdo) && ($pdo instanceof PDO)) {
        $cStat   = (string)($stdEnv->cStat ?? '');
        $xMotivo = (string)($stdEnv->xMotivo ?? '');
        $tpAmb   = (string)TP_AMB;
        $serieI  = (int)NFC_SERIE;
        $nnfI    = (int)$nNF;
        $vendaId = isset($venda_id) ? (string)$venda_id : (isset($_GET['venda_id']) ? (string)$_GET['venda_id'] : null);
        $protXML = '';
        $nProt   = null;
        $dhRecb  = null;
        $chaveI  = (string)$chave;
        if (preg_match('~<protNFe[^>]*>(.*?)</protNFe>~s', $respEnv, $m)) { $protXML = $m[0]; }
        if (preg_match('~<nProt>([^<]+)</nProt>~', $mProt[1], $m2)) { $nProt = $m2[1]; }
        if (preg_match('~<dhRecbto>([^<]+)</dhRecbto>~', $mProt[1], $m3)) { $dhRecb = $m3[1]; }
        $xmlProcPath = __DIR__ . '/procNFCe_' . $chaveI . '.xml';
        // === Persistir em nfce_emitidas ===
$xmlProcContent = isset($proc) ? $proc : ((isset($xmlProcPath) && is_file($xmlProcPath)) ? @file_get_contents($xmlProcPath) : null);
$xmlEnvio       = isset($nfeAss) ? $nfeAss : (isset($nfe) ? $nfe : null);
$xmlRetorno     = isset($ret) ? $ret : (isset($respEnv) ? $respEnv : null);
$tpagJsonStr    = isset($tPagCode) ? json_encode(['tPag' => $tPagCode], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null;

$st = $pdo->prepare("INSERT INTO nfce_emitidas
  (empresa_id, venda_id, ambiente, serie, numero, chave, protocolo, status_sefaz, mensagem, xml_nfeproc, xml_envio, xml_retorno, valor_total, valor_troco, tpag_json)
  VALUES (:empresa_id, :venda_id, :ambiente, :serie, :numero, :chave, :protocolo, :status_sefaz, :mensagem, :xml_nfeproc, :xml_envio, :xml_retorno, :valor_total, :valor_troco, :tpag_json)
  ON DUPLICATE KEY UPDATE
    protocolo=VALUES(protocolo),
    status_sefaz=VALUES(status_sefaz),
    mensagem=VALUES(mensagem),
    xml_nfeproc=VALUES(xml_nfeproc),
    xml_envio=VALUES(xml_envio),
    xml_retorno=VALUES(xml_retorno),
    valor_total=VALUES(valor_total),
    valor_troco=VALUES(valor_troco),
    tpag_json=VALUES(tpag_json),
    created_at=NOW()
");
$st->execute([
  ':empresa_id'   => NFCE_EMPRESA_ID,
  ':venda_id'     => $vendaId,
  ':ambiente'     => (int)$tpAmb,
  ':serie'        => $serieI,
  ':numero'       => $nnfI,
  ':chave'        => $chaveI,
  ':protocolo'    => isset($nProt) ? $nProt : null,
  ':status_sefaz' => (string)$cStat,
  ':mensagem'     => (string)$xMotivo,
  ':xml_nfeproc'  => $xmlProcContent,
  ':xml_envio'    => $xmlEnvio,
  ':xml_retorno'  => $xmlRetorno,
  ':valor_total'  => isset($vNFnum) ? number_format((float)$vNFnum, 2, '.', '') : null,
  ':valor_troco'  => isset($vTrocoVal) ? number_format((float)$vTrocoVal, 2, '.', '') : null,
  ':tpag_json'    => $tpagJsonStr
]);
}
    } catch (Throwable $e) {
      // Não bloquear o fluxo por erro de log
      // error_log('Falha ao persistir retorno SEFAZ: '.$e->getMessage());
    }
    
    // ======= AGORA: redireciona direto para o DANFE =======
    $danfeUrl = 'danfe_nfce.php?chave=' . urlencode($chave);
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!headers_sent()) {
      header('Location: ' . $danfeUrl);
      exit;
    }
    echo '<!doctype html><meta charset="utf-8">';
    echo '<script>location.replace(' . json_encode($danfeUrl) . ');</script>';
    exit;
}
// === LOGAR REJEIÇÕES TAMBÉM (sem protNFe) ===
try {
  if (!isset($pdo) || !($pdo instanceof PDO)) {
    foreach ([__DIR__ . '/../conexao/conexao.php', __DIR__ . '/../../conexao/conexao.php', __DIR__ . '/../../../conexao/conexao.php'] as $__p) {
      if (is_file($__p)) { require_once $__p; break; }
    }
  }
  if (isset($pdo) && ($pdo instanceof PDO)) {
    // garanta exceptions para capturar qualquer erro de SQL
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $cStat   = (string)($stdEnv->cStat ?? '');
    $xMotivo = (string)($stdEnv->xMotivo ?? '');
    $tpAmb   = (int)TP_AMB;
    $serieI  = (int)NFC_SERIE;
    $nnfI    = isset($nNF) ? (int)$nNF : 0;
    $vendaId = isset($venda_id) ? (string)$venda_id : (isset($_GET['venda_id']) ? (string)$_GET['venda_id'] : null);
    $chaveI  = isset($chave) ? (string)$chave : (isset($chaveI) ? (string)$chaveI : '');

    // === Persistir em nfce_emitidas ===
$xmlProcContent = isset($proc) ? $proc : ((isset($xmlProcPath) && is_file($xmlProcPath)) ? @file_get_contents($xmlProcPath) : null);
$xmlEnvio       = isset($nfeAss) ? $nfeAss : (isset($nfe) ? $nfe : null);
$xmlRetorno     = isset($ret) ? $ret : (isset($respEnv) ? $respEnv : null);
$tpagJsonStr    = isset($tPagCode) ? json_encode(['tPag' => $tPagCode], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null;

$st = $pdo->prepare("INSERT INTO nfce_emitidas
  (empresa_id, venda_id, ambiente, serie, numero, chave, protocolo, status_sefaz, mensagem, xml_nfeproc, xml_envio, xml_retorno, valor_total, valor_troco, tpag_json)
  VALUES (:empresa_id, :venda_id, :ambiente, :serie, :numero, :chave, :protocolo, :status_sefaz, :mensagem, :xml_nfeproc, :xml_envio, :xml_retorno, :valor_total, :valor_troco, :tpag_json)
  ON DUPLICATE KEY UPDATE
    protocolo=VALUES(protocolo),
    status_sefaz=VALUES(status_sefaz),
    mensagem=VALUES(mensagem),
    xml_nfeproc=VALUES(xml_nfeproc),
    xml_envio=VALUES(xml_envio),
    xml_retorno=VALUES(xml_retorno),
    valor_total=VALUES(valor_total),
    valor_troco=VALUES(valor_troco),
    tpag_json=VALUES(tpag_json),
    created_at=NOW()
");
$st->execute([
  ':empresa_id'   => NFCE_EMPRESA_ID,
  ':venda_id'     => $vendaId,
  ':ambiente'     => (int)$tpAmb,
  ':serie'        => $serieI,
  ':numero'       => $nnfI,
  ':chave'        => $chaveI,
  ':protocolo'    => isset($nProt) ? $nProt : null,
  ':status_sefaz' => (string)$cStat,
  ':mensagem'     => (string)$xMotivo,
  ':xml_nfeproc'  => $xmlProcContent,
  ':xml_envio'    => $xmlEnvio,
  ':xml_retorno'  => $xmlRetorno,
  ':valor_total'  => isset($vNFnum) ? number_format((float)$vNFnum, 2, '.', '') : null,
  ':valor_troco'  => isset($vTrocoVal) ? number_format((float)$vTrocoVal, 2, '.', '') : null,
  ':tpag_json'    => $tpagJsonStr
]);
}
} catch (Throwable $e) {
  // habilite durante testes, depois pode comentar
  error_log('Falha ao persistir retorno SEFAZ (rejeição): '.$e->getMessage());
}

  echo "<pre>Retorno não autorizado:\n".e($ret)."</pre>";
  exit;
}

/* Retorno inesperado */
echo "<pre>Retorno inesperado:\n".e($respEnv)."</pre>";?>
<?php
$__RETORNO_HTML__ = ob_get_clean();
?><!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>ERP - PDV</title>

    <meta name="description" content="" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon"
        href="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
        rel="stylesheet" />

    <!-- Icons. Uncomment required icon fonts -->
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />

    <!-- Page CSS -->

    <!-- Helpers -->
    <script src="../../assets/vendor/js/helpers.js"></script>

    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="../../assets/js/config.js"></script>
</head>
<body class="bg-gray-50 min-h-screen">
<nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
                    id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                            <i class="bx bx-menu bx-sm"></i>
                        </a>
                    </div>

                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <!-- Search -->
                        <div class="navbar-nav align-items-center">
                            <div class="nav-item d-flex align-items-center">
                                <i class="bx bx-search fs-4 lh-0"></i>
                                <input type="text" class="form-control border-0 shadow-none" placeholder="Search..."
                                    aria-label="Search..." />
                            </div>
                        </div>
                        <!-- /Search -->

                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <!-- Place this tag where you want the button to render. -->
                            <!-- User -->
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);"
                                    data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <img src="../../assets/img/avatars/1.png" alt
                                            class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="../../assets/img/avatars/1.png" alt
                                                            class="w-px-40 h-auto rounded-circle" />
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <!-- Exibindo o nome e nível do usuário -->
                                                    <span class="fw-semibold d-block"><?php echo $nomeUsuario; ?></span>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                    <li>
                                        <div class="dropdown-divider"></div>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <i class="bx bx-user me-2"></i>
                                            <span class="align-middle">Minha conta</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <i class="bx bx-cog me-2"></i>
                                            <span class="align-middle">Configurações</span>

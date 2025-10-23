<?php
// nfce/emitir.php — Emissão NFC-e (modelo 65) com saneamento NCM/CFOP/EAN
declare(strict_types=1);
ini_set('display_errors','1');
error_reporting(E_ALL);

ob_start();

if (file_exists(__DIR__ . '/vendor/autoload.php')) require __DIR__ . '/vendor/autoload.php';
if (file_exists(__DIR__ . '/config.php'))          require __DIR__ . '/config.php';

use NFePHP\Common\Certificate;
use NFePHP\NFe\Tools;
use NFePHP\NFe\Common\Standardize;

header('Content-Type: text/html; charset=utf-8');

/* ==== Helpers ==== */
function soDig($s){ return preg_replace('/\D+/', '', (string)$s); }
function pad($n,$t){ return str_pad((string)$n,(int)$t,'0',STR_PAD_LEFT); }
function e($s){ return htmlspecialchars((string)$s, ENT_XML1|ENT_COMPAT, 'UTF-8'); }
function mod11(string $num): int {
  $f=[2,3,4,5,6,7,8,9]; $s=0;$k=0;
  for($i=strlen($num)-1;$i>=0;$i--){ $s+=(int)$num[$i]*$f[$k++%count($f)]; }
  $r=$s%11; return ($r==0||$r==1)?0:(11-$r);
}
function nfeproc($nfe,$prot){
  $nfe = preg_replace('/<\?xml.*?\?>/','', $nfe);
  return '<?xml version="1.0" encoding="UTF-8"?>'
       . '<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">'
       . $nfe.$prot.'</nfeProc>';
}

/* Saneadores iguais ao submit */
function saneEAN(?string $v): string {
  $v = trim((string)$v);
  $d = preg_replace('/\D+/','',$v);
  if ($d === '' || !in_array(strlen($d), [8,12,13,14], true)) return 'SEM GTIN';
  return $d;
}
function saneNCM(?string $v): string {
  $v = preg_replace('/\D+/','', (string)$v);
  if (in_array($v, ['', '0', '00', '00000000', '000', '000000'], true)) return '21069090';
  if (preg_match('/^([0-9]{2}|[0-9]{8})$/', $v)) return $v;
  return '21069090';
}
function saneCFOP(?string $v): string {
  $v = preg_replace('/\D+/','', (string)$v);
  if (in_array($v, ['', '0','00','000','0000'], true)) return '5102';
  if (preg_match('/^[123567][0-9]{3}$/', $v)) return $v;
  return '5102';
}
function saneUN(?string $v): string {
  $v = strtoupper(trim((string)$v));
  return ($v==='') ? 'UN' : $v;
}

/* ===== Carrega itens da venda do BD ===== */
$itens = [];
$vendaRow = null;
$venda_id = isset($_GET['venda_id']) ? (int)$_GET['venda_id'] : 0;
$empresaId = $_POST['empresa_id'] ?? ($_GET['id'] ?? '');

$__candidates = [
  __DIR__ . '/../assets/php/conexao.php',
  __DIR__ . '/../ERP/assets/php/conexao.php',
  $_SERVER['DOCUMENT_ROOT'] . '/assets/php/conexao.php',
  $_SERVER['DOCUMENT_ROOT'] . '/ERP/assets/php/conexao.php',
];
$__found = false;
foreach ($__candidates as $__p) {
  if (is_file($__p)) { require_once $__p; $__found = true; break; }
}
if (!($__found && isset($pdo) && $pdo instanceof PDO)) {
  while (ob_get_level() > 0) ob_end_clean();
  die('Conexão PDO indisponível para emitir NFC-e.');
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Itens
$st = $pdo->prepare("SELECT produto_id, produto_nome, quantidade, preco_unitario, unidade, ncm, cfop, codigo_barras
                       FROM itens_venda WHERE venda_id=:v ORDER BY id ASC");
$st->execute([':v'=>$venda_id]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
  $itens[] = [
    'desc' => (string)$r['produto_nome'],
    'qtd'  => (float)$r['quantidade'],
    'vun'  => (float)$r['preco_unitario'],
    'unid' => saneUN($r['unidade'] ?? 'UN'),
    'ncm'  => saneNCM($r['ncm'] ?? ''),
    'cfop' => saneCFOP($r['cfop'] ?? ''),
    'ean'  => saneEAN($r['codigo_barras'] ?? '')
  ];
}
// Venda (pagamento)
$stV = $pdo->prepare("SELECT cpf_cliente, forma_pagamento, valor_total, valor_recebido, troco
                        FROM vendas WHERE id=:v LIMIT 1");
$stV->execute([':v'=>$venda_id]);
$vendaRow = $stV->fetch(PDO::FETCH_ASSOC) ?: null;

if (!$itens) {
  while (ob_get_level() > 0) ob_end_clean();
  die('Sem itens para emitir.');
}

/* ===== Certificado A1 ===== */
$pfx = @file_get_contents(PFX_PATH);
if ($pfx === false) { while (ob_get_level() > 0) ob_end_clean(); die('PFX não encontrado: '.e(PFX_PATH)); }
try { $cert = Certificate::readPfx($pfx, PFX_PASSWORD); }
catch (Throwable $e) { while (ob_get_level() > 0) ob_end_clean(); die('<pre>Falha ao abrir certificado: '.$e->getMessage().'</pre>'); }

/* ===== Tools ===== */
$configJson = json_encode([
  'atualizacao' => date('Y-m-d H:i:s'),
  'tpAmb'       => (int)TP_AMB,
  'razaosocial' => EMIT_XNOME,
  'siglaUF'     => EMIT_UF,
  'cnpj'        => soDig(EMIT_CNPJ),
  'schemes'     => 'PL_009_V4',
  'versao'      => '4.00',
  'CSC'         => CSC,
  'CSCid'       => ID_TOKEN
], JSON_UNESCAPED_UNICODE);
$tools = new Tools($configJson, $cert);
$tools->model('65');

/* ===== CHAVE ===== */
$cUF    = pad(COD_UF,2);
$AAMM   = date('ym');
$CNPJ   = pad(soDig(EMIT_CNPJ),14);
$mod    = '65';
$serie  = pad((string)NFC_SERIE,3);

// nNF sequencial em integracao_nfce
$nNF = null;
try {
  $pdo->beginTransaction();
  $q = $pdo->prepare("SELECT ultimo_numero_nfce FROM integracao_nfce WHERE empresa_id=:e FOR UPDATE");
  $q->execute([':e'=>$empresaId ?: (defined('NFCE_EMPRESA_ID') ? NFCE_EMPRESA_ID : 'principal_1')]);
  $row = $q->fetch(PDO::FETCH_ASSOC);
  if (!$row) throw new RuntimeException('integracao_nfce não configurado para a empresa.');
  $nNF = (int)$row['ultimo_numero_nfce'] + 1;
  $u = $pdo->prepare("UPDATE integracao_nfce SET ultimo_numero_nfce=:n WHERE empresa_id=:e");
  $u->execute([':n'=>$nNF, ':e'=>$empresaId ?: (defined('NFCE_EMPRESA_ID') ? NFCE_EMPRESA_ID : 'principal_1')]);
  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  while (ob_get_level() > 0) ob_end_clean();
  die('<pre>Falha ao gerar número NFC-e: '.$e->getMessage().'</pre>');
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
  'tpAmb'=>(int)TP_AMB,'finNFe'=>1,'indFinal'=>1,'indPres'=>1,'procEmi'=>0,'verProc'=>'autoERP-1.0'
];
$enderEmit = [
  'xLgr'=>EMIT_XLGR,'nro'=>EMIT_NRO,'xBairro'=>EMIT_XBAIRRO,'cMun'=>COD_MUN,'xMun'=>EMIT_XMUN,
  'UF'=>EMIT_UF,'CEP'=>EMIT_CEP,'cPais'=>1058,'xPais'=>'Brasil'
];
if (defined('EMIT_FONE') && preg_match('/^\d{6,14}$/', (string)EMIT_FONE)) $enderEmit['fone'] = EMIT_FONE;

$emit = [
  'CNPJ'=>soDig(EMIT_CNPJ),'xNome'=>EMIT_XNOME,'xFant'=>EMIT_XFANT,'IE'=>EMIT_IE,'CRT'=>EMIT_CRT,
  'enderEmit'=>$enderEmit
];

$docDest = soDig($vendaRow['cpf_cliente'] ?? '');
$dest = [];
if (strlen($docDest)===11) $dest = ['CPF'=>$docDest,'indIEDest'=>9];
elseif (strlen($docDest)===14) $dest = ['CNPJ'=>$docDest,'indIEDest'=>9];

/* ===== Itens (com saneamento) ===== */
$i=1; $vProd=0.00; $detXML='';
foreach ($itens as $it) {
  $xProd = e($it['desc']);
  $qCom  = number_format((float)$it['qtd'],3,'.','');
  $vUn   = number_format((float)$it['vun'],2,'.','');
  $vTot  = number_format(((float)$it['qtd']*(float)$it['vun']),2,'.','');
  $ncm   = saneNCM($it['ncm'] ?? '');
  $cfop  = saneCFOP($it['cfop'] ?? '');
  $un    = saneUN($it['unid'] ?? 'UN');
  $ean   = saneEAN($it['ean'] ?? '');

  $detXML .= '
  <det nItem="'.$i.'">
    <prod>
      <cProd>'.$i.'</cProd><cEAN>'.$ean.'</cEAN><xProd>'.$xProd.'</xProd>
      <NCM>'.$ncm.'</NCM><CFOP>'.$cfop.'</CFOP>
      <uCom>'.$un.'</uCom><qCom>'.$qCom.'</qCom><vUnCom>'.$vUn.'</vUnCom><vProd>'.$vTot.'</vProd>
      <cEANTrib>'.$ean.'</cEANTrib><uTrib>'.$un.'</uTrib><qTrib>'.$qCom.'</qTrib><vUnTrib>'.$vUn.'</vUnTrib>
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

/* ===== Totais/Transp/Pagamento/InfAdic ===== */
$vProdFmt = number_format($vProd,2,'.','');
$totXML   = '<total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>'.$vProdFmt.'</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>'.$vProdFmt.'</vNF></ICMSTot></total>';
$transpXML= '<transp><modFrete>9</modFrete></transp>';

/* Pagamento (dinheiro ou forma da venda) */
$tPag = '01'; // dinheiro
if (!empty($vendaRow['forma_pagamento'])) {
  // aceita "01" etc. Se vier palavra, deixe dinheiro como fallback
  $f = trim((string)$vendaRow['forma_pagamento']);
  if (preg_match('/^\d+$/',$f)) $tPag = str_pad(substr($f,-2),2,'0',STR_PAD_LEFT);
}
$vPag = number_format((float)($vendaRow['valor_total'] ?? $vProd), 2, '.', '');
$vTroco = number_format(max(0.00, (float)($vendaRow['troco'] ?? 0)), 2, '.', '');
$pagXML  = '<pag><detPag><indPag>0</indPag><tPag>'.$tPag.'</tPag><vPag>'.$vPag.'</vPag></detPag>';
if ($tPag==='01' && (float)$vTroco>0) $pagXML .= '<vTroco>'.$vTroco.'</vTroco>';
$pagXML .= '</pag>';

$infAd   = '<infAdic><infCpl>Emissão via autoERP</infCpl></infAd>';

/* ===== XML da NFe ===== */
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
     .       '<enderEmit><xLgr>'.$emit['enderEmit']['xLgr'].'</xLgr><nro>'.$emit['enderEmit']['nro'].'</nro><xBairro>'.$emit['enderEmit']['xBairro'].'</xBairro><cMun>'.$emit['enderEmit']['cMun'].'</cMun><xMun>'.$emit['enderEmit']['xMun'].'</xMun><UF>'.$emit['enderEmit']['UF'].'</UF><CEP>'.$emit['enderEmit']['CEP'].'</CEP><cPais>'.$emit['enderEmit']['cPais'].'</cPais><xPais>'.$emit['enderEmit']['xPais'].'</xPais>'.(isset($emit['enderEmit']['fone'])?'<fone>'.$emit['enderEmit']['fone'].'</fone>':'').'</enderEmit>'
     .       '<IE>'.$emit['IE'].'</IE><CRT>'.$emit['CRT'].'</CRT>'
     .     '</emit>'
     .     . (!empty($dest) ? ('<dest>'.(isset($dest['CPF'])?'<CPF>'.$dest['CPF'].'</CPF>':'<CNPJ>'.$dest['CNPJ'].'</CNPJ>').'<indIEDest>'.$dest['indIEDest'].'</indIEDest></dest>') : '')
     .     $detXML
     .     $totXML
     .     $transpXML
     .     $pagXML
     .     $infAd
     .   '</infNFe>'
     . '</NFe>';

/* ===== Assina ===== */
try { $nfeAss = $tools->signNFe($nfe); }
catch (Throwable $e) {
  while (ob_get_level() > 0) ob_end_clean();
  die('<pre>Falha ao assinar: '.$e->getMessage().'</pre>');
}

/* ===== Envia ===== */
try {
  $respEnv = $tools->sefazEnviaLote([$nfeAss], '1', 1);
} catch (Throwable $e) {
  try { $respEnv = $tools->sefazEnviaLote([$nfeAss], '1', false, 1); }
  catch (Throwable $e2) {
    while (ob_get_level() > 0) ob_end_clean();
    die('<pre>Falha na autorização: '.$e->getMessage()."\n\n".$e2->getMessage().'</pre>');
  }
}

$stdEnv = (new Standardize)->toStd($respEnv);

/* ===== Autorizado (cStat 104 com protNFe) ===== */
if (!empty($stdEnv->cStat) && (int)$stdEnv->cStat === 104) {
  if (!preg_match('~(<protNFe[^>]*>.*?</protNFe>)~s', $respEnv, $mProt)) {
    while (ob_get_level() > 0) ob_end_clean();
    die("Autorizado, mas sem protNFe no retorno.");
  }
  $proc = nfeproc($nfeAss, $mProt[1]);
  $xmlPath = __DIR__ . '/procNFCe_'.$chave.'.xml';
  file_put_contents($xmlPath, $proc);

  // Atualiza vendas / log
  try {
    $cStat   = (string)($stdEnv->cStat ?? '');
    $xMotivo = (string)($stdEnv->xMotivo ?? '');
    $nProt   = null;
    if (preg_match('~<nProt>([^<]+)</nProt>~', $mProt[1], $m2)) $nProt = $m2[1];

    $up = $pdo->prepare("UPDATE vendas SET chave_nfce=:ch, protocolo_nfce=:prot, status_nfce='autorizada', motivo_nfce=:m WHERE id=:v");
    $up->execute([':ch'=>$chave, ':prot'=>$nProt, ':m'=>$xMotivo, ':v'=>$venda_id]);

    // grava em nfce_emitidas
    $st = $pdo->prepare("INSERT INTO nfce_emitidas
      (empresa_id, venda_id, ambiente, serie, numero, chave, protocolo, status_sefaz, mensagem, xml_nfeproc, xml_envio, xml_retorno, valor_total)
      VALUES (:empresa_id,:venda_id,:amb,:serie,:numero,:chave,:protocolo,:status,:msg,:xmlp,:xmle,:xmlr,:vtotal)
      ON DUPLICATE KEY UPDATE protocolo=VALUES(protocolo), status_sefaz=VALUES(status_sefaz), mensagem=VALUES(mensagem),
      xml_nfeproc=VALUES(xml_nfeproc), xml_envio=VALUES(xml_envio), xml_retorno=VALUES(xml_retorno), valor_total=VALUES(valor_total)");
    $st->execute([
      ':empresa_id'=>$empresaId ?: (defined('NFCE_EMPRESA_ID') ? NFCE_EMPRESA_ID : 'principal_1'),
      ':venda_id'=>$venda_id,
      ':amb'=>(int)TP_AMB,
      ':serie'=>(int)NFC_SERIE,
      ':numero'=>$nNF,
      ':chave'=>$chave,
      ':protocolo'=>$nProt,
      ':status'=>$cStat,
      ':msg'=>$xMotivo,
      ':xmlp'=>$proc,
      ':xmle'=>$nfeAss,
      ':xmlr'=>$respEnv,
      ':vtotal'=>number_format((float)($vendaRow['valor_total'] ?? $vProd), 2, '.', '')
    ]);
  } catch (Throwable $e) { /* log se quiser */ }

  // Redirect para DANFE
  $danfeUrl = './danfe_nfce.php?chave='.urlencode($chave).'&venda_id='.urlencode((string)$venda_id).'&id='.urlencode((string)$empresaId);
  while (ob_get_level() > 0) ob_end_clean();
  if (!headers_sent()) { header('Location: '.$danfeUrl); exit; }
  echo '<script>location.replace('.json_encode($danfeUrl).');</script>';
  exit;
}

/* ===== Recibo (103 -> consulta) ===== */
if (!empty($stdEnv->cStat) && (int)$stdEnv->cStat === 103 && !empty($stdEnv->infRec->nRec)) {
  $nRec = (string)$stdEnv->infRec->nRec;
  sleep(2);
  $ret = $tools->sefazConsultaRecibo($nRec);
  if (preg_match('~(<protNFe[^>]*>.*?</protNFe>)~s', $ret, $mProt)) {
    $proc = nfeproc($nfeAss, $mProt[1]);
    $xmlPath = __DIR__ . '/procNFCe_'.$chave.'.xml';
    file_put_contents($xmlPath, $proc);

    // Redirect
    $danfeUrl = './danfe_nfce.php?chave='.urlencode($chave).'&venda_id='.urlencode((string)$venda_id).'&id='.urlencode((string)$empresaId);
    while (ob_get_level() > 0) ob_end_clean();
    if (!headers_sent()) { header('Location: '.$danfeUrl); exit; }
    echo '<script>location.replace('.json_encode($danfeUrl).');</script>';
    exit;
  }
}

/* ===== Rejeição / retorno inesperado ===== */
while (ob_get_level() > 0) ob_end_clean();
echo "<pre>Retorno SEFAZ:\n".htmlspecialchars($respEnv, ENT_QUOTES, 'UTF-8')."</pre>";
?>
<?php
// danfe_nfce.php — visualização/print do DANFE NFC-e (80mm)
// Uso: danfe_nfce.php?chave=NNNN... ou danfe_nfce.php?arq=procNFCe_...xml

header('Content-Type: text/html; charset=utf-8');


// === ATUALIZAÇÃO DA VENDA (chave/status) AO GERAR O DANFE ====================
try {
    // Ajuste o caminho da sua conexão (precisa expor $pdo = new PDO(...))
    require_once __DIR__ . '/../assets/php/conexao.php';

    // empresa_id é VARCHAR — não faça cast para int
    $empresaId = isset($_GET['id']) ? trim($_GET['id']) : (string)($_SESSION['empresa_id'] ?? '');
    $vendaId   = isset($_GET['venda_id']) ? (int)$_GET['venda_id'] : (int)($_SESSION['venda_id'] ?? 0);

    // Extrair a chave: aceita ?chave=44dígitos ou tenta ler do XML passado em ?arq=procNFCe_....xml
    $chave = null;
    if (!empty($_GET['chave'])) {
        $chave = preg_replace('/\D+/', '', $_GET['chave']);
    } elseif (!empty($_GET['arq'])) {
        $arq = $_GET['arq'];
        // evitar path traversal: apenas letras/números/ponto/traço/underline/slash
        if (preg_match('/^[\w.\-\/]+$/', $arq) && is_file($arq)) {
            $xml = @file_get_contents($arq);
            if ($xml) {
                if (preg_match('/Id="NFe(\d{44})"/', $xml, $m)) {
                    $chave = $m[1];
                } elseif (preg_match('/<chNFe>(\d{44})<\/chNFe>/', $xml, $m)) {
                    $chave = $m[1];
                }
            }
        }
    }

    // Se tiver tudo que precisamos, atualiza a venda
    if ($vendaId > 0 && $empresaId !== '' && $chave && strlen($chave) === 44) {
        $sql = "UPDATE vendas
                   SET chave_nfce = :ch, status_nfce = 'autorizada'
                 WHERE id = :id AND empresa_id = :emp";
        $st = $pdo->prepare($sql);
        $st->execute([
            ':ch'  => $chave,
            ':id'  => $vendaId,
            ':emp' => $empresaId,
        ]);
    }
} catch (Throwable $e) {
    error_log('DANFE:update venda falhou: ' . $e->getMessage());
}
// =============================================================================

function br($v){ return number_format((float)$v, 2, ',', '.'); }
function limpar($s){ return trim((string)$s); }
function fmtChave($ch){
  $ch = preg_replace('/\D+/', '', $ch);
  return trim(implode(' ', str_split($ch, 4)));
}
function mapTPag($t){
  $k = str_pad(preg_replace('/\D+/', '', (string)$t), 2, '0', STR_PAD_LEFT);
  $m = [
    '01'=>'Dinheiro','02'=>'Cheque','03'=>'Cartão de Crédito','04'=>'Cartão de Débito','05'=>'Crédito Loja',
    '10'=>'Vale Alimentação','11'=>'Vale Refeição','12'=>'Vale Presente','13'=>'Vale Combustível',
    '15'=>'Boleto','16'=>'Depósito','17'=>'PIX','18'=>'Transferência/Carteira','19'=>'Programa de Fidelidade',
    '90'=>'Sem Pagamento','99'=>'Outros'
  ];
  return $m[$k] ?? 'Outros';
}

// === carrega o XML procNFe ===
$base = __DIR__ . DIRECTORY_SEPARATOR;
if (!empty($_GET['arq'])) {
  $file = $base . basename($_GET['arq']);
} elseif (!empty($_GET['chave'])) {
  $file = $base . 'procNFCe_' . preg_replace('/\D+/', '', $_GET['chave']) . '.xml';
} else {
  die('Informe ?chave=... ou ?arq=procNFCe_....xml');
}
if (!is_file($file)) die('Arquivo não encontrado: '.htmlspecialchars($file));

$xml = file_get_contents($file);
$dom = new DOMDocument(); $dom->loadXML($xml);

// namespaces
$nfeNS = 'http://www.portalfiscal.inf.br/nfe';

// nós principais
$infNFe = $dom->getElementsByTagNameNS($nfeNS,'infNFe')->item(0);
$nfe    = $dom->getElementsByTagNameNS($nfeNS,'NFe')->item(0);
$supl   = $dom->getElementsByTagNameNS($nfeNS,'infNFeSupl')->item(0);
$prot   = $dom->getElementsByTagNameNS($nfeNS,'protNFe')->item(0);

// emitente
$emit = $dom->getElementsByTagNameNS($nfeNS,'emit')->item(0);
$enderEmit = $emit? $emit->getElementsByTagNameNS($nfeNS,'enderEmit')->item(0):null;
$emit_xNome = $emit? limpar($emit->getElementsByTagName('xNome')->item(0)->nodeValue):'';
$emit_xFant = $emit? limpar(($emit->getElementsByTagName('xFant')->item(0)->nodeValue ?? '')):'';
$emit_CNPJ  = $emit? limpar($emit->getElementsByTagName('CNPJ')->item(0)->nodeValue):'';
$emit_IE    = $emit? limpar($emit->getElementsByTagName('IE')->item(0)->nodeValue):'';
$end_txt = '';
if ($enderEmit){
  $end_txt = limpar($enderEmit->getElementsByTagName('xLgr')->item(0)->nodeValue).' '.
             limpar($enderEmit->getElementsByTagName('nro')->item(0)->nodeValue).', '.
             limpar($enderEmit->getElementsByTagName('xBairro')->item(0)->nodeValue).', '.
             limpar($enderEmit->getElementsByTagName('xMun')->item(0)->nodeValue).' - '.
             limpar($enderEmit->getElementsByTagName('UF')->item(0)->nodeValue);
}

// ide, chave, data
$ide    = $dom->getElementsByTagNameNS($nfeNS,'ide')->item(0);
$serie  = $ide? limpar($ide->getElementsByTagName('serie')->item(0)->nodeValue):'';
$nNF    = $ide? limpar($ide->getElementsByTagName('nNF')->item(0)->nodeValue):'';
$dhEmi  = $ide? limpar($ide->getElementsByTagName('dhEmi')->item(0)->nodeValue):'';
$idAttr = $infNFe? $infNFe->getAttribute('Id') : '';
$chave  = preg_replace('/^NFe/','',$idAttr);

// totais
$tot    = $dom->getElementsByTagNameNS($nfeNS,'ICMSTot')->item(0);
$vProd  = $tot? br($tot->getElementsByTagName('vProd')->item(0)->nodeValue):'0,00';
$vDesc  = $tot? br($tot->getElementsByTagName('vDesc')->item(0)->nodeValue):'0,00';
$vNF    = $tot? br($tot->getElementsByTagName('vNF')->item(0)->nodeValue):'0,00';
$vTrib  = $tot? br(($tot->getElementsByTagName('vTotTrib')->item(0)->nodeValue ?? 0)):'0,00';

// pagamento
$detPag = $dom->getElementsByTagNameNS($nfeNS,'detPag')->item(0);
$tPag   = $detPag? limpar($detPag->getElementsByTagName('tPag')->item(0)->nodeValue):'';
$vPag   = $detPag? br($detPag->getElementsByTagName('vPag')->item(0)->nodeValue):'0,00';
$vTroco = $dom->getElementsByTagNameNS($nfeNS,'vTroco')->item(0);
$vTroco = $vTroco? br($vTroco->nodeValue) : '0,00';

// destinatário (pode não existir)
$dest     = $dom->getElementsByTagNameNS($nfeNS,'dest')->item(0);
$dest_doc = '';
if ($dest){
  $dCNPJ = $dest->getElementsByTagName('CNPJ')->item(0);
  $dCPF  = $dest->getElementsByTagName('CPF')->item(0);
  $dest_doc = $dCNPJ ? 'CNPJ: '.limpar($dCNPJ->nodeValue) : ($dCPF ? 'CPF: '.limpar($dCPF->nodeValue) : '');
}

// protocolo/autorização (pode não existir em homologação com rejeição)
$protInfo = '';
if ($prot){
  $infProt = $prot->getElementsByTagName('infProt')->item(0);
  $cStat   = $infProt? limpar($infProt->getElementsByTagName('cStat')->item(0)->nodeValue):'';
  $xMotivo = $infProt? limpar($infProt->getElementsByTagName('xMotivo')->item(0)->nodeValue):'';
  $nProt   = $infProt? limpar(($infProt->getElementsByTagName('nProt')->item(0)->nodeValue ?? '')):'';
  $dhRec   = $infProt? limpar($infProt->getElementsByTagName('dhRecbto')->item(0)->nodeValue):'';
  if ($nProt) $protInfo = "Protocolo de Autorização: $nProt — $dhRec";
  else        $protInfo = "Status: $cStat — $xMotivo";
}

// QRCode
$qrTxt   = $supl? limpar($supl->getElementsByTagName('qrCode')->item(0)->nodeValue):'';
$urlChave= $supl? limpar($supl->getElementsByTagName('urlChave')->item(0)->nodeValue):'';

// itens
$itens = [];
foreach ($dom->getElementsByTagNameNS($nfeNS,'det') as $det){
  $prod = $det->getElementsByTagNameNS($nfeNS,'prod')->item(0);
  if (!$prod) continue;
  $itens[] = [
    'cProd' => limpar($prod->getElementsByTagName('cProd')->item(0)->nodeValue),
    'xProd' => limpar($prod->getElementsByTagName('xProd')->item(0)->nodeValue),
    'qCom'  => number_format((float)$prod->getElementsByTagName('qCom')->item(0)->nodeValue,3,',','.'),
    'uCom'  => limpar($prod->getElementsByTagName('uCom')->item(0)->nodeValue),
    'vUn'   => br($prod->getElementsByTagName('vUnCom')->item(0)->nodeValue),
    'vTot'  => br($prod->getElementsByTagName('vProd')->item(0)->nodeValue),
  ];
}
?>
<!doctype html>
<html>
<meta charset="utf-8">
<title>DANFE NFC-e</title>
<style>
/* Largura típica bobina 80mm */
@page { size: 80mm auto; margin: 3mm; }
*{ box-sizing: border-box; }
body{ font: 12px monospace; color:#000; margin:0; }
.wrapper{ width: 75mm; margin:0 auto; }
.center{ text-align:center; }
.hr{ border-top:1px dashed #000; margin:4px 0; }
h1{ font-size:13px; margin:6px 0; text-transform:uppercase; }
h2{ font-size:12px; margin:4px 0; }
.small{ font-size:10px; }
.tbl{ width:100%; border-collapse:collapse; }
.tbl th, .tbl td{ padding:2px 0; }
.tbl thead th{ border-bottom:1px dashed #000; font-weight:bold; }
.right{ text-align:right; }
.key{ letter-spacing:1px; word-spacing:4px; }
.logo{ max-height:28px; }
.qr{ display:block; margin:6px auto; width:210px; height:210px; }
@media print{
  .noprint{ display:none; }
}
</style>

<div class="wrapper">
  <div class="center">
    <!-- Se tiver logo.png na pasta, mostra -->
    <?php if (is_file(__DIR__.'/logo.png')): ?>
      <img class="logo" src="logo.png" alt="logo"><br>
    <?php endif; ?>
    <h2><?= htmlspecialchars($emit_xFant ?: $emit_xNome) ?></h2>
    <div class="small">
      CNPJ: <?= htmlspecialchars($emit_CNPJ) ?>
      &nbsp;&nbsp; IE: <?= htmlspecialchars($emit_IE) ?><br>
      <?= htmlspecialchars($end_txt) ?>
    </div>
    <div class="hr"></div>
    <h1>DANFE NFC-e Documento Auxiliar da Nota Fiscal de Consumidor Eletrônica</h1>
    <div class="small">NFC-e não permite aproveitamento de crédito de ICMS</div>
    <div class="hr"></div>
  </div>

  <table class="tbl small">
    <thead>
      <tr>
        <th class="left">Cód</th>
        <th class="left">Descrição</th>
        <th class="right">Qtde</th>
        <th class="right">Un</th>
        <th class="right">V.Unit</th>
        <th class="right">V.Total</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($itens as $it): ?>
      <tr>
        <td><?= htmlspecialchars($it['cProd']) ?></td>
        <td><?= htmlspecialchars($it['xProd']) ?></td>
        <td class="right"><?= htmlspecialchars($it['qCom']) ?></td>
        <td class="right"><?= htmlspecialchars($it['uCom']) ?></td>
        <td class="right"><?= htmlspecialchars($it['vUn']) ?></td>
        <td class="right"><?= htmlspecialchars($it['vTot']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="hr"></div>

  <table class="tbl small">
    <tr><td><b>QTDE TOTAL DE ITENS</b></td><td class="right"><?= count($itens) ?></td></tr>
    <tr><td><b>VALOR TOTAL R$</b></td><td class="right"><?= $vNF ?></td></tr>
    <?php if ($vDesc !== '0,00'): ?>
    <tr><td><b>DESCONTO</b></td><td class="right">- <?= $vDesc ?></td></tr>
    <?php endif; ?>
    <tr><td><b>FORMA DE PAGAMENTO</b></td><td class="right"><?= htmlspecialchars(mapTPag($tPag)) ?></td></tr>
    <tr><td><b>VALOR PAGO</b></td><td class="right"><?= $vPag ?></td></tr>
    <?php if ($vTroco !== '0,00'): ?>
    <tr><td><b>TROCO</b></td><td class="right"><?= $vTroco ?></td></tr>
    <?php endif; ?>
  </table>

  <?php if ($vTrib !== '0,00'): ?>
  <div class="small">Inf. dos Tributos Totais Incidentes (Lei Federal 12.741/2012): R$ <?= $vTrib ?></div>
  <?php endif; ?>

  <div class="hr"></div>

  <div class="small">
    Nº: <?= htmlspecialchars($nNF) ?> &nbsp;&nbsp; Série: <?= htmlspecialchars($serie) ?>
    &nbsp;&nbsp; Data de emissão: <?= htmlspecialchars($dhEmi) ?>
  </div>

  <div class="center">
    <h2>CHAVE DE ACESSO</h2>
    <div class="key small"><?= fmtChave($chave) ?></div>
  </div>

  <div class="hr"></div>

  <div class="small">CONSUMIDOR</div>
  <div class="small"><?= htmlspecialchars($dest_doc ?: '—') ?></div>

  <div class="hr"></div>

  <div class="center small">Consulta via leitor de QR Code</div>
  <!-- QR gerado no browser; fallback <img> externo se necessário -->
  <div id="qrcode" class="qr"></div>

  <div class="hr"></div>

  <?php if ($protInfo): ?>
  <div class="small"><?= htmlspecialchars($protInfo) ?></div>
  <?php endif; ?>

  <div class="center noprint" style="margin:8px 0">
    <button onclick="window.print()">Imprimir</button>
  </div>
  <button id="nfce-cancelar" class="center noprint" title="Cancelar NFC-e (110111/110112)">Cancelar NFC-e</button>
</div>

<!-- QRCode JS (CDN). Se preferir, baixe qrcode.min.js local e troque o src. -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" integrity="sha512-MnZ3x9f8Qe8S3lTneG3c4z5kXVxD3X8Q0m9L2v2N1K0wC3W6s2bP2eVd5f3v4mC1BXx3S7o1YVtYhGkSxP3GVA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
(function(){
  var qrTxt = <?= json_encode($qrTxt, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
  var el = document.getElementById('qrcode');

  function buildQR(){
    if (window.QRCode) {
      new QRCode(el, { text: qrTxt, width: 210, height: 210, correctLevel: QRCode.CorrectLevel.M });
    } else {
      // fallback via serviço externo (se estiver offline do CDN)
      var img = new Image();
      img.className = 'qr';
      img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=210x210&data='+encodeURIComponent(qrTxt);
      el.appendChild(img);
    }
  }
  buildQR();
})();
</script>
<?php include __DIR__ . '/cancelar_venda_ui.php'; ?>
</html>

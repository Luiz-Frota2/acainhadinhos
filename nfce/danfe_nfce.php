<?php
// danfe_nfce.php — visualização/print do DANFE NFC-e (80mm)
// Uso: danfe_nfce.php?chave=NNNN...&venda_id=123&id=principal_1  OU  danfe_nfce.php?arq=procNFCe_...xml

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
header('Content-Type: text/html; charset=utf-8');

/* ===================== Atualiza venda (chave/status) ===================== */
try {
  require_once __DIR__ . '/../assets/php/conexao.php';

  // Empresa/venda vindos da URL ou sessão
  $empresaId = isset($_GET['id']) ? trim((string)$_GET['id']) : (string)($_SESSION['empresa_id'] ?? '');
  $vendaId   = isset($_GET['venda_id']) ? (int)$_GET['venda_id'] : (int)($_SESSION['venda_id'] ?? 0);

  // Descobre a chave informada
  $chaveReq = null;
  if (!empty($_GET['chave'])) {
    $chaveReq = preg_replace('/\D+/', '', (string)$_GET['chave']);
  } elseif (!empty($_GET['arq'])) {
    $arq = (string)$_GET['arq'];
    if (preg_match('/^[\w.\-\/]+$/', $arq) && is_file($arq)) {
      $xmlTmp = @file_get_contents($arq);
      if ($xmlTmp) {
        if (preg_match('/Id="NFe(\d{44})"/', $xmlTmp, $m))             $chaveReq = $m[1];
        elseif (preg_match('/<chNFe>(\d{44})<\/chNFe>/', $xmlTmp, $m)) $chaveReq = $m[1];
      }
    }
  }

  // Atualiza a venda quando já sabemos tudo
  if ($vendaId > 0 && $empresaId !== '' && $chaveReq && strlen($chaveReq) === 44) {
    $sql = "UPDATE vendas
               SET chave_nfce = :ch, status_nfce = 'autorizada'
             WHERE id = :id AND empresa_id = :emp";
    $st = $pdo->prepare($sql);
    $st->execute([':ch' => $chaveReq, ':id' => $vendaId, ':emp' => $empresaId]);
  }
} catch (Throwable $e) {
  error_log('DANFE:update venda falhou: ' . $e->getMessage());
}
/* ======================================================================== */

function br($v)
{
  return number_format((float)$v, 2, ',', '.');
}
function limpar($s)
{
  return trim((string)$s);
}
function fmtChave($ch)
{
  $ch = preg_replace('/\D+/', '', $ch);
  return trim(implode(' ', str_split($ch, 4)));
}
function mapTPag($t)
{
  $k = str_pad(preg_replace('/\D+/', '', (string)$t), 2, '0', STR_PAD_LEFT);
  $m = ['01' => 'Dinheiro', '02' => 'Cheque', '03' => 'Cartão de Crédito', '04' => 'Cartão de Débito', '05' => 'Crédito Loja', '10' => 'Vale Alimentação', '11' => 'Vale Refeição', '12' => 'Vale Presente', '13' => 'Vale Combustível', '15' => 'Boleto', '16' => 'Depósito', '17' => 'PIX', '18' => 'Transferência/Carteira', '19' => 'Programa de Fidelidade', '90' => 'Sem Pagamento', '99' => 'Outros'];
  return $m[$k] ?? 'Outros';
}

/* =========================== Carrega XML procNFe ========================== */
$base = __DIR__ . DIRECTORY_SEPARATOR;
if (!empty($_GET['arq'])) {
  $file = $base . basename((string)$_GET['arq']);
} elseif (!empty($_GET['chave'])) {
  $file = $base . 'procNFCe_' . preg_replace('/\D+/', '', (string)$_GET['chave']) . '.xml';
} else {
  die('Informe ?chave=... ou ?arq=procNFCe_....xml');
}
if (!is_file($file)) die('Arquivo não encontrado: ' . htmlspecialchars($file));

$xml = file_get_contents($file);
$dom = new DOMDocument();
$dom->loadXML($xml);

$nfeNS  = 'http://www.portalfiscal.inf.br/nfe';
$infNFe = $dom->getElementsByTagNameNS($nfeNS, 'infNFe')->item(0);
$nfe    = $dom->getElementsByTagNameNS($nfeNS, 'NFe')->item(0);
$supl   = $dom->getElementsByTagNameNS($nfeNS, 'infNFeSupl')->item(0);
$prot   = $dom->getElementsByTagNameNS($nfeNS, 'protNFe')->item(0);

/* ============================== Emitente ================================ */
$emit = $dom->getElementsByTagNameNS($nfeNS, 'emit')->item(0);
$enderEmit = $emit ? $emit->getElementsByTagNameNS($nfeNS, 'enderEmit')->item(0) : null;
$emit_xNome = $emit ? limpar($emit->getElementsByTagName('xNome')->item(0)->nodeValue) : '';
$emit_xFant = $emit ? limpar(($emit->getElementsByTagName('xFant')->item(0)->nodeValue ?? '')) : '';
$emit_CNPJ  = $emit ? limpar($emit->getElementsByTagName('CNPJ')->item(0)->nodeValue) : '';
$emit_IE    = $emit ? limpar($emit->getElementsByTagName('IE')->item(0)->nodeValue) : '';
$end_txt = '';
if ($enderEmit) {
  $end_txt = limpar($enderEmit->getElementsByTagName('xLgr')->item(0)->nodeValue) . ' ' .
    limpar($enderEmit->getElementsByTagName('nro')->item(0)->nodeValue) . ', ' .
    limpar($enderEmit->getElementsByTagName('xBairro')->item(0)->nodeValue) . ', ' .
    limpar($enderEmit->getElementsByTagName('xMun')->item(0)->nodeValue) . ' - ' .
    limpar($enderEmit->getElementsByTagName('UF')->item(0)->nodeValue);
}

/* ================================ IDE ================================== */
$ide    = $dom->getElementsByTagNameNS($nfeNS, 'ide')->item(0);
$serie  = $ide ? limpar($ide->getElementsByTagName('serie')->item(0)->nodeValue) : '';
$nNF    = $ide ? limpar($ide->getElementsByTagName('nNF')->item(0)->nodeValue) : '';
$dhEmi  = $ide ? limpar($ide->getElementsByTagName('dhEmi')->item(0)->nodeValue) : '';
$idAttr = $infNFe ? $infNFe->getAttribute('Id') : '';
$chave  = preg_replace('/^NFe/', '', $idAttr);

/* =============================== Totais ================================ */
$tot    = $dom->getElementsByTagNameNS($nfeNS, 'ICMSTot')->item(0);
$vProd  = $tot ? br($tot->getElementsByTagName('vProd')->item(0)->nodeValue) : '0,00';
$vDesc  = $tot ? br($tot->getElementsByTagName('vDesc')->item(0)->nodeValue) : '0,00';
$vNF    = $tot ? br($tot->getElementsByTagName('vNF')->item(0)->nodeValue) : '0,00';
$vTrib  = $tot ? br(($tot->getElementsByTagName('vTotTrib')->item(0)->nodeValue ?? 0)) : '0,00';

/* ============================== Pagamento ============================== */
$detPag = $dom->getElementsByTagNameNS($nfeNS, 'detPag')->item(0);
$tPag   = $detPag ? limpar($detPag->getElementsByTagName('tPag')->item(0)->nodeValue) : '';
$vPag   = $detPag ? br($detPag->getElementsByTagName('vPag')->item(0)->nodeValue) : '0,00';
$vTroco = $dom->getElementsByTagNameNS($nfeNS, 'vTroco')->item(0);
$vTroco = $vTroco ? br($vTroco->nodeValue) : '0,00';

/* ============================== Destinatário =========================== */
$dest     = $dom->getElementsByTagNameNS($nfeNS, 'dest')->item(0);
$dest_doc = '';
if ($dest) {
  $dCNPJ = $dest->getElementsByTagName('CNPJ')->item(0);
  $dCPF  = $dest->getElementsByTagName('CPF')->item(0);
  $dest_doc = $dCNPJ ? 'CNPJ: ' . limpar($dCNPJ->nodeValue) : ($dCPF ? 'CPF: ' . limpar($dCPF->nodeValue) : '');
}

/* ============================ Protocolo ================================ */
$protInfo = '';
if ($prot) {
  $infProt = $prot->getElementsByTagName('infProt')->item(0);
  $cStat   = $infProt ? limpar($infProt->getElementsByTagName('cStat')->item(0)->nodeValue) : '';
  $xMotivo = $infProt ? limpar($infProt->getElementsByTagName('xMotivo')->item(0)->nodeValue) : '';
  $nProt   = $infProt ? limpar(($infProt->getElementsByTagName('nProt')->item(0)->nodeValue ?? '')) : '';
  $dhRec   = $infProt ? limpar($infProt->getElementsByTagName('dhRecbto')->item(0)->nodeValue) : '';
  $protInfo = $nProt ? "Protocolo de Autorização: $nProt — $dhRec" : "Status: $cStat — $xMotivo";
}

/* ============================== QR Code ================================ */
$qrTxt   = $supl ? limpar($supl->getElementsByTagName('qrCode')->item(0)->nodeValue) : '';
$urlChave = $supl ? limpar($supl->getElementsByTagName('urlChave')->item(0)->nodeValue) : '';

/* ================================ Itens ================================ */
$itens = [];
foreach ($dom->getElementsByTagNameNS($nfeNS, 'det') as $det) {
  $prod = $det->getElementsByTagNameNS($nfeNS, 'prod')->item(0);
  if (!$prod) continue;
  $itens[] = [
    'cProd' => limpar($prod->getElementsByTagName('cProd')->item(0)->nodeValue),
    'xProd' => limpar($prod->getElementsByTagName('xProd')->item(0)->nodeValue),
    'qCom'  => number_format((float)$prod->getElementsByTagName('qCom')->item(0)->nodeValue, 3, ',', '.'),
    'uCom'  => limpar($prod->getElementsByTagName('uCom')->item(0)->nodeValue),
    'vUn'   => br($prod->getElementsByTagName('vUnCom')->item(0)->nodeValue),
    'vTot'  => br($prod->getElementsByTagName('vProd')->item(0)->nodeValue),
  ];
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>DANFE NFC-e</title>
  <style>
    :root {
      --ticket-max: 384px;
      --pad: 12px;
      --qr: 210px;
      --accent: #1a73e8;
      --danger: #e11d48;
      --ink: #111;
      --paper: #fff;
      --bg: #f5f7fb
    }

    * {
      box-sizing: border-box;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale
    }

    html,
    body {
      margin: 0;
      padding: 0;
      background: var(--bg);
      color: var(--ink);
      -webkit-text-size-adjust: 100%
    }

    body {
      font: 13px/1.45 monospace
    }

    .wrapper {
      width: 100%;
      max-width: var(--ticket-max);
      margin: 10px auto 92px;
      background: var(--paper);
      border-radius: 12px;
      box-shadow: 0 10px 28px rgba(0, 0, 0, .08);
      padding: var(--pad)
    }

    header h2 {
      font-size: 14px;
      margin: 4px 0 2px;
      text-transform: uppercase
    }

    .small {
      font-size: 11px;
      color: #111
    }

    .hr {
      border-top: 1px dashed #000;
      margin: 8px 0
    }

    .tbl {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed
    }

    .tbl thead th {
      border-bottom: 1px dashed #000;
      font-weight: 700;
      padding: 4px 0
    }

    .tbl td {
      padding: 3px 0;
      vertical-align: top
    }

    .left {
      text-align: left
    }

    .right {
      text-align: right
    }

    .center {
      text-align: center
    }

    .key {
      letter-spacing: 1px;
      word-spacing: 4px
    }

    .logo {
      max-height: 28px
    }

    .qr {
      display: block;
      margin: 8px auto;
      width: min(var(--qr), calc(100% - 2*var(--pad)));
      height: auto;
      aspect-ratio: 1/1
    }

    .badge {
      display: inline-block;
      background: #eef2ff;
      color: #1f2937;
      padding: 3px 6px;
      border-radius: 6px;
      font-size: 10px
    }

    .actions {
      position: fixed;
      left: 0;
      right: 0;
      bottom: 0;
      z-index: 50;
      padding: 10px env(safe-area-inset-right) calc(10px + env(safe-area-inset-bottom)) env(safe-area-inset-left);
      background: #fff;
      border-top: 1px solid #e5e7eb;
      display: flex;
      gap: 10px;
      justify-content: center
    }

    .btn {
      appearance: none;
      border: 0;
      border-radius: 10px;
      padding: 11px 16px;
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      font-weight: 600;
      cursor: pointer;
      transition: .2s;
      white-space: nowrap
    }

    .btn:focus {
      outline: 3px solid rgba(26, 115, 232, .25);
      outline-offset: 2px
    }

    .btn-primary {
      background: var(--accent);
      color: #fff
    }

    .btn-primary:hover {
      filter: brightness(.95)
    }

    .btn-danger {
      background: var(--danger);
      color: #fff
    }

    .btn-danger:hover {
      filter: brightness(.95)
    }

    .btn-secondary {
      background: #e5e7eb;
      color: #111;
    }

    .btn-secondary:hover {
      filter: brightness(.97);
    }

    @media (max-width:420px) {
      body {
        font-size: 12px
      }

      .wrapper {
        margin: 6px auto 88px;
        border-radius: 10px
      }

      :root {
        --qr: 180px
      }

      .tbl thead th,
      .tbl td {
        padding: 2px 0
      }
    }

    @media (max-width:340px) {
      .wrapper {
        border-radius: 0;
        box-shadow: none;
        margin: 0 auto 88px
      }
    }

    @page {
      size: 80mm auto;
      margin: 3mm;
    }

    @media print {

      html,
      body {
        background: #fff
      }

      .wrapper {
        box-shadow: none;
        border-radius: 0;
        margin: 0;
        max-width: unset;
        width: 75mm;
        padding: 0
      }

      .actions {
        display: none
      }

      .qr {
        width: 210px;
        height: 210px
      }
    }

    /* Fallback modal (se a UI não existir) */
    #cv-overlay.fallback {
      position: fixed;
      inset: 0;
      display: none;
      place-items: center;
      background: rgba(0, 0, 0, .45);
      z-index: 60
    }

    #cv-overlay.fallback .cv-modal {
      display: none;
      background: #fff;
      border-radius: 12px;
      padding: 16px;
      width: min(520px, 92vw);
      box-shadow: 0 10px 30px rgba(0, 0, 0, .25);
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial
    }

    #cv-overlay.fallback .cv-modal h3 {
      margin: 0 0 8px;
      font-size: 18px
    }

    #cv-overlay.fallback .cv-modal p {
      margin: 0 0 12px;
      color: #374151;
      font-size: 14px
    }

    #cv-overlay.fallback .cv-actions {
      display: flex;
      gap: 8px;
      justify-content: flex-end;
      margin-top: 12px
    }

    #cv-overlay.fallback .btn {
      font-size: 14px;
      padding: 10px 14px
    }
  </style>
</head>

<body>

  <div class="wrapper" role="document" aria-label="DANFE NFC-e">
    <header class="center">
      <?php if (is_file(__DIR__ . '/logo.png')): ?>
        <img class="logo" src="logo.png" alt="Logo"><br>
      <?php endif; ?>
      <h2><?= htmlspecialchars($emit_xFant ?: $emit_xNome) ?></h2>
      <div class="small">
        CNPJ: <?= htmlspecialchars($emit_CNPJ) ?> &nbsp; IE: <?= htmlspecialchars($emit_IE) ?><br>
        <?= htmlspecialchars($end_txt) ?>
      </div>
      <div class="hr"></div>
      <div class="small badge">NFC-e não permite aproveitamento de crédito de ICMS</div>
      <div class="hr"></div>
    </header>

    <table class="tbl small" aria-label="Itens">
      <colgroup>
        <col style="width:16%">
        <col style="width:42%">
        <col style="width:10%">
        <col style="width:8%">
        <col style="width:12%">
        <col style="width:12%">
      </colgroup>
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
            <td class="left"><?= htmlspecialchars($it['cProd']) ?></td>
            <td class="left"><?= htmlspecialchars($it['xProd']) ?></td>
            <td class="right"><?= htmlspecialchars($it['qCom']) ?></td>
            <td class="right"><?= htmlspecialchars($it['uCom']) ?></td>
            <td class="right"><?= htmlspecialchars($it['vUn']) ?></td>
            <td class="right"><?= htmlspecialchars($it['vTot']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="hr"></div>

    <table class="tbl small" aria-label="Totais">
      <tbody>
        <tr>
          <td class="left"><b>QTDE TOTAL DE ITENS</b></td>
          <td class="right"><?= count($itens) ?></td>
        </tr>
        <tr>
          <td class="left"><b>VALOR TOTAL R$</b></td>
          <td class="right"><?= $vNF ?></td>
        </tr>
        <?php if ($vDesc !== '0,00'): ?><tr>
            <td class="left"><b>DESCONTO</b></td>
            <td class="right">- <?= $vDesc ?></td>
          </tr><?php endif; ?>
        <tr>
          <td class="left"><b>FORMA DE PAGAMENTO</b></td>
          <td class="right"><?= htmlspecialchars(mapTPag($tPag)) ?></td>
        </tr>
        <tr>
          <td class="left"><b>VALOR PAGO</b></td>
          <td class="right"><?= $vPag ?></td>
        </tr>
        <?php if ($vTroco !== '0,00'): ?><tr>
            <td class="left"><b>TROCO</b></td>
            <td class="right"><?= $vTroco ?></td>
          </tr><?php endif; ?>
      </tbody>
    </table>

    <?php if ($vTrib !== '0,00'): ?><div class="small">Inf. dos Tributos Totais Incidentes (Lei 12.741/2012): R$ <?= $vTrib ?></div><?php endif; ?>

    <div class="hr"></div>

    <div class="small">Nº: <?= htmlspecialchars($nNF) ?> &nbsp;&nbsp; Série: <?= htmlspecialchars($serie) ?> &nbsp;&nbsp; Emissão: <?= htmlspecialchars($dhEmi) ?></div>

    <div class="center" style="margin-top:6px">
      <div class="small"><b>CHAVE DE ACESSO</b></div>
      <div class="key small"><?= fmtChave($chave) ?></div>
    </div>

    <div class="hr"></div>

    <div class="small"><b>CONSUMIDOR</b></div>
    <div class="small"><?= htmlspecialchars($dest_doc ?: '—') ?></div>

    <div class="hr"></div>

    <div class="center small">Consulta via leitor de QR Code</div>
    <div id="qrcode" class="qr" role="img" aria-label="QR Code da NFC-e"></div>

    <div class="hr"></div>

    <?php if ($protInfo): ?><div class="small"><?= htmlspecialchars($protInfo) ?></div><?php endif; ?>
  </div>

  <!-- Barra fixa de ações -->
  <div class="actions" aria-label="Ações">
    <button id="btn-back" class="btn btn-secondary" type="button">Voltar</button>
    <button id="btn-print" class="btn btn-primary" type="button">Imprimir</button>
    <button id="nfce-cancelar" class="btn btn-danger" type="button" title="Cancelar NFC-e (110111/110112)">Cancelar NFC-e</button>
  </div>

  <!-- QRCode JS -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <script>
    (function() {
      var qrTxt = <?= json_encode($qrTxt, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
      var el = document.getElementById('qrcode');

      // id/empresa para o botão Voltar
      var empresaId = <?= json_encode($empresaId, JSON_UNESCAPED_UNICODE) ?> || '';

      function size() {
        var w = Math.min(210, Math.max(140, el.clientWidth || 180));
        return {
          w: w,
          h: w
        };
      }

      function buildQR() {
        if (!qrTxt) {
          el.innerHTML = '';
          return;
        }
        el.innerHTML = '';
        try {
          var s = size();
          if (window.QRCode) new QRCode(el, {
            text: qrTxt,
            width: s.w,
            height: s.h,
            correctLevel: QRCode.CorrectLevel.M
          });
          else {
            var img = new Image();
            img.className = 'qr';
            img.alt = 'QR Code';
            img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=' + s.w + 'x' + s.h + '&data=' + encodeURIComponent(qrTxt);
            el.appendChild(img);
          }
        } catch (e) {
          console.warn('QR falhou:', e);
        }
      }
      window.addEventListener('load', buildQR);
      window.addEventListener('resize', function() {
        clearTimeout(window.__qr);
        window.__qr = setTimeout(buildQR, 120);
      });

      document.getElementById('btn-print').addEventListener('click', function() {
        window.print();
      });

      // Botão VOLTAR -> ../frentedeloja/caixa/vendaRapida.php?id=<empresaId>
      document.getElementById('btn-back').addEventListener('click', function() {
        var url = '../frentedeloja/caixa/vendaRapida.php?id=' + encodeURIComponent(empresaId);
        window.location.href = url;
      });

      function openCancelUiFallback() {
        var overlay = document.getElementById('cv-overlay');
        var modal = document.querySelector('#cv-escolha, #cv-escolha-modal, .cv-modal');
        if (overlay) {
          overlay.style.display = 'grid';
          overlay.removeAttribute('aria-hidden');
        }
        if (modal) {
          modal.style.display = 'grid';
          modal.removeAttribute('aria-hidden');
        }
      }
      document.getElementById('nfce-cancelar').addEventListener('click', function() {
        if (typeof window.cvOpen === 'function') {
          try {
            window.cvOpen();
            return;
          } catch (_) {}
        }
        openCancelUiFallback();
      });
    })();
  </script>

  <?php
  // Garante que a UI receba empresa_id e venda_id mesmo se vieram de sessão
  if (!isset($_REQUEST['id']) && !empty($empresaId))   $_REQUEST['id'] = $_GET['id'] = $empresaId;
  if (!isset($_REQUEST['venda_id']) && !empty($vendaId)) $_REQUEST['venda_id'] = $_GET['venda_id'] = (string)$vendaId;

  /* ========= inclui a UI de cancelamento (vários caminhos) ========= */
  $__cv_paths = [
    __DIR__ . '/cancelar_venda_ui.php',
    __DIR__ . '/../nfce/cancelar_venda_ui.php',
    __DIR__ . '/../frentedeloja/caixa/cancelar_venda_ui.php',
    __DIR__ . '/../modals/cancelar_venda_ui.php',
    __DIR__ . '/modals/cancelar_venda_ui.php',
  ];
  $__cv_included = false;
  foreach ($__cv_paths as $__p) {
    if (is_file($__p)) {
      include $__p;
      $__cv_included = true;
      break;
    }
  }

  /* ========= fallback minimal se o arquivo não existir ========= */
  if (!$__cv_included): ?>
    <div id="cv-overlay" class="fallback" style="display:none" aria-hidden="true">
      <div class="cv-modal" role="dialog" aria-modal="true" style="display:none">
        <h3>Cancelar venda</h3>
        <p>Interface de cancelamento padrão não encontrada.</p>
        <div class="cv-actions">
          <button type="button" class="btn" onclick="cvClose()">Fechar</button>
        </div>
      </div>
    </div>
    <script>
      if (typeof window.cvOpen !== 'function') {
        window.cvOpen = function() {
          var ov = document.getElementById('cv-overlay');
          var md = ov ? ov.querySelector('.cv-modal') : null;
          if (ov) {
            ov.style.display = 'grid';
            ov.removeAttribute('aria-hidden');
          }
          if (md) {
            md.style.display = 'block';
            md.focus && md.focus();
          }
        };
      }
      if (typeof window.cvClose !== 'function') {
        window.cvClose = function() {
          var ov = document.getElementById('cv-overlay');
          var md = ov ? ov.querySelector('.cv-modal') : null;
          if (md) md.style.display = 'none';
          if (ov) {
            ov.style.display = 'none';
            ov.setAttribute('aria-hidden', 'true');
          }
        };
      }
    </script>
  <?php endif; ?>

</body>

</html>
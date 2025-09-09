<?php
// visualizarNFCe.php — MODELO (preview) do DANFE NFC-e em HTML (80mm)
// Não depende de XML, banco ou bibliotecas. Somente visual.
// Você pode passar alguns campos via GET para customizar rapidamente:
// ?fantasia=Minha%20Loja&razao=Minha%20Loja%20LTDA&cnpj=12345678000195&ie=123456&end=Rua%20X,%20100%20-%20Centro%20-%20SP
// &serie=1&nnf=123&valor=49.90&tpag=01&qrcode=https://exemplo

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, max-age=60');

function onlyDigits($v){ return preg_replace('/\D+/', '', (string)$v) ?? ''; }
function br($v){ return number_format((float)$v, 2, ',', '.'); }
function limpar($s){ return trim((string)$s); }
function fmtChave($ch){
    $ch = onlyDigits($ch);
    if (strlen($ch) < 44) $ch = str_pad($ch, 44, '9'); // completa pra 44 em modo demo
    if (strlen($ch) > 44) $ch = substr($ch, 0, 44);
    return trim(implode(' ', str_split($ch, 4)));
}
function mapTPag($t){
    $k = str_pad(onlyDigits($t), 2, '0', STR_PAD_LEFT);
    $m = [
        '01' => 'Dinheiro','02' => 'Cheque','03' => 'Cartão de Crédito','04' => 'Cartão de Débito',
        '05' => 'Crédito Loja','10' => 'Vale Alimentação','11' => 'Vale Refeição','12' => 'Vale Presente',
        '13' => 'Vale Combustível','15' => 'Boleto','16' => 'Depósito','17' => 'PIX','18' => 'Transferência/Carteira',
        '19' => 'Programa de Fidelidade','90' => 'Sem Pagamento','99' => 'Outros'
    ];
    return $m[$k] ?? 'Outros';
}

/* ---------------------------- Dados DEMO (sobrescrevíveis por GET) ---------------------------- */
$emit_xFant = $_GET['fantasia'] ?? 'EMPRESA MODELO';
$emit_xNome = $_GET['razao']    ?? 'EMPRESA MODELO LTDA';
$emit_CNPJ  = onlyDigits($_GET['cnpj'] ?? '12.345.678/0001-95');
$emit_IE    = $_GET['ie']       ?? '123456789';
$end_txt    = $_GET['end']      ?? 'Av. Exemplo, 100, Centro, São Paulo - SP';

$serie      = (string)($_GET['serie'] ?? '1');
$nNF        = (string)($_GET['nnf']   ?? '566');
$dhEmi      = date('Y-m-d\TH:i:sP'); // ISO
$valorTotal = (float)($_GET['valor'] ?? 41.00);
$vDesc      = 0.00;
$vNF        = $valorTotal;
$vTrib      = 0.00;
$tPag       = $_GET['tpag'] ?? '01'; // Dinheiro

// Chave de acesso DEMO (44 dígitos)
$chaveDemo  = onlyDigits($_GET['chave'] ?? '');
if (strlen($chaveDemo) !== 44) {
    // monta uma chave de aparência válida (não oficial)
    $uf   = '35'; $ano = date('y'); $mes = date('m');
    $cnpj = str_pad($emit_CNPJ, 14, '0', STR_PAD_LEFT);
    $mod  = '65';
    $ser  = str_pad($serie, 3, '0', STR_PAD_LEFT);
    $num  = str_pad($nNF, 9, '0', STR_PAD_LEFT);
    $tpEm = '1';
    $cNum = str_pad((string)mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
    $idUF = '1';
    $base = $uf.$ano.$mes.$cnpj.$mod.$ser.$num.$tpEm.$cNum.$idUF;
    // DV módulo 11
    $peso = 2; $soma = 0;
    for ($i = strlen($base) - 1; $i >= 0; $i--) {
        $soma += intval($base[$i]) * $peso;
        $peso = ($peso == 9) ? 2 : $peso + 1;
    }
    $mod = $soma % 11;
    $dv  = 11 - $mod;
    if ($dv >= 10) $dv = 0;
    $chaveDemo = $base . $dv;
}
$chave = $chaveDemo;

// QRCode demo (pode vir por GET)
$qrTxt = $_GET['qrcode'] ?? 'https://www.sefaz.sp.gov.br';
$protInfo = 'Protocolo de Autorização: 135230000000000 — ' . date('Y-m-d\TH:i:sP');

// Itens demo
$itens = [
    ['cProd'=>'001','xProd'=>'AÇAÍ 300ML COM GRANOLA','qCom'=>number_format(1, 3, ',', '.'),'uCom'=>'UN','vUn'=>br(12.00),'vTot'=>br(12.00)],
    ['cProd'=>'002','xProd'=>'CREME DE CUPUAÇU 300ML','qCom'=>number_format(1, 3, ',', '.'),'uCom'=>'UN','vUn'=>br(14.00),'vTot'=>br(14.00)],
    ['cProd'=>'003','xProd'=>'AGUA 500ML','qCom'=>number_format(1, 3, ',', '.'),'uCom'=>'UN','vUn'=>br(5.00),'vTot'=>br(5.00)],
];
/* ---------------------------------------------------------------------------------------------- */
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>MODELO — DANFE NFC-e</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <style>
        :root{--ticket-max:384px;--pad:12px;--qr:210px;--accent:#1a73e8;--danger:#e11d48;--ink:#111;--paper:#fff;--bg:#f5f7fb}
        *{box-sizing:border-box;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
        html,body{margin:0;padding:0;background:var(--bg);color:var(--ink);-webkit-text-size-adjust:100%}
        body{font:13px/1.45 monospace}
        .wrapper{width:100%;max-width:var(--ticket-max);margin:10px auto 92px;background:var(--paper);border-radius:12px;box-shadow:0 10px 28px rgba(0,0,0,.08);padding:var(--pad);position:relative}
        .ribbon{position:absolute;top:10px;right:-40px;transform:rotate(45deg);background:var(--danger);color:#fff;padding:4px 48px;font:bold 11px/1 monospace;letter-spacing:.5px}
        header h2{font-size:14px;margin:4px 0 2px;text-transform:uppercase}
        .small{font-size:11px;color:#111}
        .hr{border-top:1px dashed #000;margin:8px 0}
        .tbl{width:100%;border-collapse:collapse;table-layout:fixed}
        .tbl thead th{border-bottom:1px dashed #000;font-weight:700;padding:4px 0}
        .tbl td{padding:3px 0;vertical-align:top}
        .left{text-align:left}.right{text-align:right}.center{text-align:center}
        .key{letter-spacing:1px;word-spacing:4px}
        .logo{max-height:28px}
        .qr{display:block;margin:8px auto;width:min(var(--qr), calc(100% - 2*var(--pad)));height:auto;aspect-ratio:1/1}
        .badge{display:inline-block;background:#eef2ff;color:#1f2937;padding:3px 6px;border-radius:6px;font-size:10px}
        .actions{position:fixed;left:0;right:0;bottom:0;z-index:50;padding:10px env(safe-area-inset-right) calc(10px + env(safe-area-inset-bottom)) env(safe-area-inset-left);
                 background:#fff;border-top:1px solid #e5e7eb;display:flex;gap:10px;justify-content:center}
        .btn{appearance:none;border:0;border-radius:10px;padding:11px 16px;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;font-weight:600;cursor:pointer;transition:.2s;white-space:nowrap}
        .btn:focus{outline:3px solid rgba(26,115,232,.25);outline-offset:2px}
        .btn-primary{background:var(--accent);color:#fff}.btn-primary:hover{filter:brightness(.95)}
        .btn-secondary{background:#6b7280;color:#fff}.btn-secondary:hover{filter:brightness(.95)}
        @media (max-width:420px){body{font-size:12px}.wrapper{margin:6px auto 88px;border-radius:10px}:root{--qr:180px}.tbl thead th,.tbl td{padding:2px 0}}
        @media (max-width:340px){.wrapper{border-radius:0;box-shadow:none;margin:0 auto 88px}}
        @page{size:80mm auto;margin:3mm}
        @media print{html,body{background:#fff}.wrapper{box-shadow:none;border-radius:0;margin:0;max-width:unset;width:75mm;padding:0}
            .actions{display:none}.qr{width:210px;height:210px}}
    </style>
</head>
<body>

<div class="wrapper" role="document" aria-label="DANFE NFC-e (MODELO)">
    <div class="ribbon">MODELO / DEMONSTRATIVO</div>

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
            <col style="width:16%"><col style="width:42%"><col style="width:10%"><col style="width:8%"><col style="width:12%"><col style="width:12%">
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
        <tr><td class="left"><b>QTDE TOTAL DE ITENS</b></td><td class="right"><?= count($itens) ?></td></tr>
        <tr><td class="left"><b>VALOR TOTAL R$</b></td><td class="right"><?= br($vNF) ?></td></tr>
        <?php if ($vDesc > 0): ?>
            <tr><td class="left"><b>DESCONTO</b></td><td class="right">- <?= br($vDesc) ?></td></tr>
        <?php endif; ?>
        <tr><td class="left"><b>FORMA DE PAGAMENTO</b></td><td class="right"><?= htmlspecialchars(mapTPag($tPag)) ?></td></tr>
        <tr><td class="left"><b>VALOR PAGO</b></td><td class="right"><?= br($vNF) ?></td></tr>
        </tbody>
    </table>

    <div class="hr"></div>

    <div class="small">Nº: <?= htmlspecialchars($nNF) ?> &nbsp;&nbsp; Série: <?= htmlspecialchars($serie) ?> &nbsp;&nbsp; Emissão: <?= htmlspecialchars($dhEmi) ?></div>

    <div class="center" style="margin-top:6px">
        <div class="small"><b>CHAVE DE ACESSO</b></div>
        <div class="key small"><?= fmtChave($chave) ?></div>
    </div>

    <div class="hr"></div>

    <div class="small"><b>CONSUMIDOR</b></div>
    <div class="small">—</div>

    <div class="hr"></div>

    <div class="center small">Consulta via leitor de QR Code</div>
    <div id="qrcode" class="qr" role="img" aria-label="QR Code da NFC-e (DEMO)"></div>

    <div class="hr"></div>

    <div class="small"><?= htmlspecialchars($protInfo) ?></div>
</div>

<!-- Barra de ações -->
<div class="actions" aria-label="Ações">
    <a class="btn btn-secondary" href="javascript:history.back()">← Voltar</a>
    <button id="btn-print" class="btn btn-primary" type="button">Imprimir</button>
</div>

<!-- QRCode JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
(function(){
    var el = document.getElementById('qrcode');
    var txt = <?= json_encode($qrTxt, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    function size(){
        var w = Math.min(210, Math.max(140, el.clientWidth || 180));
        return {w:w,h:w};
    }
    function buildQR(){
        el.innerHTML = '';
        if(!txt) return;
        try{
            var s = size();
            if (window.QRCode) new QRCode(el, {text: txt, width: s.w, height: s.h, correctLevel: QRCode.CorrectLevel.M});
        }catch(e){}
    }
    window.addEventListener('load', buildQR);
    window.addEventListener('resize', function(){ clearTimeout(window.__qrR); window.__qrR = setTimeout(buildQR, 120); });
    document.getElementById('btn-print').addEventListener('click', function(){ window.print(); });
})();
</script>
</body>
</html>

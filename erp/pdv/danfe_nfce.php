<?php
// danfe_nfce.php — visualização/print do DANFE NFC-e (80mm)
// Uso: danfe_nfce.php?id=<empresa_id>&venda_id=123&chave=NNNN... (44)  OU  danfe_nfce.php?arq=procNFCe_....xml

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

header('Content-Type: text/html; charset=utf-8');
// ajuda no carregamento em revisitas durante a sessão
header('Cache-Control: private, max-age=60');

// Conexão (ajuste o caminho se necessário)
require_once __DIR__ . '/../../assets/php/conexao.php';

// -------------------- Parâmetros --------------------
$empresaId = isset($_GET['id']) ? trim((string)$_GET['id']) : (string)($_SESSION['empresa_id'] ?? '');
$vendaId   = isset($_GET['venda_id']) ? (int)$_GET['venda_id'] : (int)($_SESSION['venda_id'] ?? 0);

// Descobre a chave solicitada
$chaveReq = null;
if (!empty($_GET['chave'])) {
    $chaveReq = preg_replace('/\D+/', '', (string)$_GET['chave']);
}

// Se veio "arq", usamos somente o basename para evitar traversal
$arqReq = null;
if (!empty($_GET['arq'])) {
    $arqReq = basename((string)$_GET['arq']); // segurança
}

// -------------------- Localização do XML --------------------
// Monta nome-alvo
$xmlFileName = null;
if ($arqReq) {
    $xmlFileName = $arqReq;
} elseif ($chaveReq && strlen($chaveReq) === 44) {
    $xmlFileName = 'procNFCe_' . $chaveReq . '.xml';
}

// Diretórios candidatos (PRIORIDADE: ../../nfce/)
$candidateDirs = [
    __DIR__ . '/../../nfce/', // principal
    __DIR__ . '/../nfce/',
    __DIR__ . '/nfce/',
    __DIR__ . '/',            // fallback no mesmo diretório
];

// Tenta achar arquivo físico
$file = null;
if ($xmlFileName) {
    foreach ($candidateDirs as $dir) {
        $try = $dir . $xmlFileName;
        if (is_file($try)) {
            $file = $try;
            break;
        }
    }
}

// Fallback: tenta obter XML do banco (nfce_emitidas.xml_nfeproc)
$xmlRaw = null;
if (!$file) {
    try {
        if ($chaveReq || $vendaId > 0) {
            $sql = "SELECT xml_nfeproc
                      FROM nfce_emitidas
                     WHERE empresa_id = :emp
                       " . ($chaveReq ? "AND REPLACE(REPLACE(REPLACE(REPLACE(chave,'.',''),'-',''),' ','') ,'/', '') = :chave" : "AND venda_id = :venda") . "
                     ORDER BY id DESC
                     LIMIT 1";
            $st = $pdo->prepare($sql);
            $bind = [':emp' => $empresaId];
            if ($chaveReq) $bind[':chave'] = $chaveReq;
            else $bind[':venda'] = $vendaId;
            $st->execute($bind);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['xml_nfeproc'])) {
                $xmlRaw = (string)$row['xml_nfeproc'];
            }
        }
    } catch (Throwable $e) {
        // silencioso
    }
}

// Se até aqui não temos arquivo nem XML bruto, aborta com mensagem clara
if (!$file && !$xmlRaw) {
    $hint = $xmlFileName ? " (procurado como {$xmlFileName})" : "";
    echo '<!doctype html><meta charset="utf-8"><title>NFC-e não encontrada</title>';
    echo '<style>body{font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;padding:24px;}</style>';
    echo '<h2>Arquivo da NFC-e não encontrado</h2>';
    echo '<p>Não foi possível localizar o XML em <code>../../nfce/</code> nem carregar do banco.' . htmlspecialchars($hint) . '</p>';
    if ($empresaId) {
        echo '<p><a href="./sefazConsulta.php?id=' . htmlspecialchars(urlencode($empresaId)) . '">← Voltar para Consulta</a></p>';
    }
    exit;
}

// -------------------- Atualiza venda (opcional) --------------------
try {
    if ($vendaId > 0 && $empresaId !== '' && $chaveReq && strlen($chaveReq) === 44) {
        $sql = "UPDATE vendas
                   SET chave_nfce = :ch, status_nfce = COALESCE(status_nfce,'autorizada')
                 WHERE id = :id AND empresa_id = :emp";
        $st = $pdo->prepare($sql);
        $st->execute([':ch' => $chaveReq, ':id' => $vendaId, ':emp' => $empresaId]);
    }
} catch (Throwable $e) {
    // silencioso
}

// -------------------- Utilitários --------------------
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
    $m = [
        '01' => 'Dinheiro',
        '02' => 'Cheque',
        '03' => 'Cartão de Crédito',
        '04' => 'Cartão de Débito',
        '05' => 'Crédito Loja',
        '10' => 'Vale Alimentação',
        '11' => 'Vale Refeição',
        '12' => 'Vale Presente',
        '13' => 'Vale Combustível',
        '15' => 'Boleto',
        '16' => 'Depósito',
        '17' => 'PIX',
        '18' => 'Transferência/Carteira',
        '19' => 'Programa de Fidelidade',
        '90' => 'Sem Pagamento',
        '99' => 'Outros'
    ];
    return $m[$k] ?? 'Outros';
}

// -------------------- Carrega XML --------------------
$xml = $xmlRaw ?: file_get_contents($file);

$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadXML($xml, LIBXML_COMPACT | LIBXML_NOBLANKS | LIBXML_NONET);
libxml_clear_errors();

$nfeNS  = 'http://www.portalfiscal.inf.br/nfe';
$infNFe = $dom->getElementsByTagNameNS($nfeNS, 'infNFe')->item(0);
$nfe    = $dom->getElementsByTagNameNS($nfeNS, 'NFe')->item(0);
$supl   = $dom->getElementsByTagNameNS($nfeNS, 'infNFeSupl')->item(0);
$prot   = $dom->getElementsByTagNameNS($nfeNS, 'protNFe')->item(0);

/* Emitente */
$emit = $dom->getElementsByTagNameNS($nfeNS, 'emit')->item(0);
$enderEmit = $emit ? $emit->getElementsByTagNameNS($nfeNS, 'enderEmit')->item(0) : null;
$emit_xNome = $emit ? limpar($emit->getElementsByTagName('xNome')->item(0)->nodeValue) : '';
$emit_xFant = ($emit && $emit->getElementsByTagName('xFant')->item(0)) ? limpar($emit->getElementsByTagName('xFant')->item(0)->nodeValue) : '';
$emit_CNPJ  = $emit ? limpar($emit->getElementsByTagName('CNPJ')->item(0)->nodeValue) : '';
$emit_IE    = ($emit && $emit->getElementsByTagName('IE')->item(0)) ? limpar($emit->getElementsByTagName('IE')->item(0)->nodeValue) : '';
$end_txt = '';
if ($enderEmit) {
    $get = fn($t) => ($x = $enderEmit->getElementsByTagName($t)->item(0)) ? limpar($x->nodeValue) : '';
    $end_txt = $get('xLgr') . ' ' . $get('nro') . ', ' . $get('xBairro') . ', ' . $get('xMun') . ' - ' . $get('UF');
}

/* IDE */
$ide    = $dom->getElementsByTagNameNS($nfeNS, 'ide')->item(0);
$serie  = $ide ? limpar($ide->getElementsByTagName('serie')->item(0)->nodeValue) : '';
$nNF    = $ide ? limpar($ide->getElementsByTagName('nNF')->item(0)->nodeValue) : '';
$dhEmi  = $ide ? limpar($ide->getElementsByTagName('dhEmis')->item(0)->nodeValue) : '';
$idAttr = $infNFe ? $infNFe->getAttribute('Id') : '';
$chave  = preg_replace('/^NFe/', '', $idAttr);

/* Totais */
$tot    = $dom->getElementsByTagNameNS($nfeNS, 'ICMSTot')->item(0);
$vProd  = $tot ? br($tot->getElementsByTagName('vProd')->item(0)->nodeValue) : '0,00';
$vDesc  = ($tot && $tot->getElementsByTagName('vDesc')->item(0)) ? br($tot->getElementsByTagName('vDesc')->item(0)->nodeValue) : '0,00';
$vNF    = $tot ? br($tot->getElementsByTagName('vNF')->item(0)->nodeValue) : '0,00';
$vTrib  = ($tot && $tot->getElementsByTagName('vTotTrib')->item(0)) ? br($tot->getElementsByTagName('vTotTrib')->item(0)->nodeValue) : '0,00';

/* Pagamento (pega 1º detPag) */
$detPag = $dom->getElementsByTagNameNS($nfeNS, 'detPag')->item(0);
$tPag   = $detPag ? limpar($detPag->getElementsByTagName('tPag')->item(0)->nodeValue) : '';
$vPag   = $detPag ? br($detPag->getElementsByTagName('vPag')->item(0)->nodeValue) : '0,00';
$vTroco = $dom->getElementsByTagNameNS($nfeNS, 'vTroco')->item(0);
$vTroco = $vTroco ? br($vTroco->nodeValue) : '0,00';

/* Destinatário */
$dest     = $dom->getElementsByTagNameNS($nfeNS, 'dest')->item(0);
$dest_doc = '';
if ($dest) {
    $dCNPJ = $dest->getElementsByTagName('CNPJ')->item(0);
    $dCPF  = $dest->getElementsByTagName('CPF')->item(0);
    $dest_doc = $dCNPJ ? 'CNPJ: ' . limpar($dCNPJ->nodeValue) : ($dCPF ? 'CPF: ' . limpar($dCPF->nodeValue) : '');
}

/* Protocolo */
$protInfo = '';
if ($prot) {
    $infProt = $prot->getElementsByTagName('infProt')->item(0);
    $cStat   = $infProt ? limpar($infProt->getElementsByTagName('cStat')->item(0)->nodeValue) : '';
    $xMotivo = $infProt ? limpar($infProt->getElementsByTagName('xMotivo')->item(0)->nodeValue) : '';
    $nProt   = ($infProt && $infProt->getElementsByTagName('nProt')->item(0)) ? limpar($infProt->getElementsByTagName('nProt')->item(0)->nodeValue) : '';
    $dhRec   = ($infProt && $infProt->getElementsByTagName('dhRecbto')->item(0)) ? limpar($infProt->getElementsByTagName('dhRecbto')->item(0)->nodeValue) : '';
    $protInfo = $nProt ? "Protocolo de Autorização: $nProt — $dhRec" : "Status: $cStat — $xMotivo";
} else {
    $cStat = '';
    $xMotivo = '';
}

/* QR Code */
$qrTxt    = ($supl && $supl->getElementsByTagName('qrCode')->item(0)) ? limpar($supl->getElementsByTagName('qrCode')->item(0)->nodeValue) : '';
$urlChave = ($supl && $supl->getElementsByTagName('urlChave')->item(0)) ? limpar($supl->getElementsByTagName('urlChave')->item(0)->nodeValue) : '';

/* Itens */
$itens = [];
foreach ($dom->getElementsByTagNameNS($nfeNS, 'det') as $det) {
    $prod = $det->getElementsByTagNameNS($nfeNS, 'prod')->item(0);
    if (!$prod) continue;
    $get = fn($t) => limpar($prod->getElementsByTagName($t)->item(0)->nodeValue);
    $q   = number_format((float)$prod->getElementsByTagName('qCom')->item(0)->nodeValue, 3, ',', '.');
    $u   = $get('uCom');
    $vUn = br($prod->getElementsByTagName('vUnCom')->item(0)->nodeValue);
    $vTo = br($prod->getElementsByTagName('vProd')->item(0)->nodeValue);
    $itens[] = ['cProd' => $get('cProd'), 'xProd' => $get('xProd'), 'qCom' => $q, 'uCom' => $u, 'vUn' => $vUn, 'vTot' => $vTo];
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <title>DANFE NFC-e</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <style>
        :root {
            --ticket-max: 384px;
            --pad: 12px;
            --qr: 210px;
            --accent: #1a73e8;
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

        .btn-secondary {
            background: #6b7280;
            color: #fff
        }

        .btn-secondary:hover {
            filter: brightness(.95)
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
            margin: 3mm
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
                <?php if ($vDesc !== '0,00'): ?>
                    <tr>
                        <td class="left"><b>DESCONTO</b></td>
                        <td class="right">- <?= $vDesc ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td class="left"><b>FORMA DE PAGAMENTO</b></td>
                    <td class="right"><?= htmlspecialchars(mapTPag($tPag)) ?></td>
                </tr>
                <tr>
                    <td class="left"><b>VALOR PAGO</b></td>
                    <td class="right"><?= $vPag ?></td>
                </tr>
                <?php if ($vTroco !== '0,00'): ?>
                    <tr>
                        <td class="left"><b>TROCO</b></td>
                        <td class="right"><?= $vTroco ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($vTrib !== '0,00'): ?>
            <div class="small">Inf. dos Tributos Totais Incidentes (Lei 12.741/2012): R$ <?= $vTrib ?></div>
        <?php endif; ?>

        <div class="hr"></div>

        <div class="small">Nº: <?= htmlspecialchars($nNF) ?> &nbsp;&nbsp; Série: <?= htmlspecialchars($serie) ?> &nbsp;&nbsp; Emissão: <?= htmlspecialchars($dhEmi) ?></div>

        <div class="center" style="margin-top:6px">
            <div class="small"><b>CHAVE DE ACESSO</b></div>
            <div class="key small"><?= fmtChave($chave ?: ($chaveReq ?: '')) ?></div>
        </div>

        <div class="hr"></div>

        <div class="small"><b>CONSUMIDOR</b></div>
        <div class="small"><?= htmlspecialchars($dest_doc ?: '—') ?></div>

        <div class="hr"></div>

        <div class="center small">Consulta via leitor de QR Code</div>
        <div id="qrcode" class="qr" role="img" aria-label="QR Code da NFC-e"></div>

        <div class="hr"></div>

        <?php if (!empty($protInfo)): ?>
            <div class="small"><?= htmlspecialchars($protInfo) ?></div>
        <?php elseif (!empty($xMotivo)): ?>
            <div class="small">Status: <?= htmlspecialchars($xMotivo) ?></div>
        <?php endif; ?>
    </div>

    <!-- Barra de ações -->
    <div class="actions" aria-label="Ações">
        <?php if ($empresaId): ?>
            <a class="btn btn-secondary" href="./vendaRapida.php?id=<?= urlencode($empresaId) ?>">← Voltar para PDV</a>
        <?php endif; ?>
        <button id="btn-print" class="btn btn-primary" type="button">Imprimir</button>
    </div>

    <!-- QRCode JS (leve) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        (function() {
            var el = document.getElementById('qrcode');
            var txt = <?= json_encode($qrTxt, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

            function size() {
                var w = Math.min(210, Math.max(140, el.clientWidth || 180));
                return {
                    w: w,
                    h: w
                };
            }

            function buildQR() {
                el.innerHTML = '';
                if (!txt) return;
                try {
                    var s = size();
                    if (window.QRCode) new QRCode(el, {
                        text: txt,
                        width: s.w,
                        height: s.h,
                        correctLevel: QRCode.CorrectLevel.M
                    });
                } catch (e) {}
            }
            window.addEventListener('load', buildQR);
            window.addEventListener('resize', function() {
                clearTimeout(window.__qrR);
                window.__qrR = setTimeout(buildQR, 120);
            });
            document.getElementById('btn-print').addEventListener('click', function() {
                window.print();
            });
        })();
    </script>
</body>

</html>
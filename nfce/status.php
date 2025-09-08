<?php
require __DIR__ . '/vendor/autoload.php';

use NFePHP\Common\Certificate;
use NFePHP\NFe\Tools;
use NFePHP\NFe\Common\Standardize;

// === Certificado A1 (.pfx)
$pfxPath = __DIR__ . '/certificados/N R DOS SANTOS ACAINHADINHOS (2).pfx';
$pfxPass = '1234';
$pfx     = file_get_contents($pfxPath);
if ($pfx === false) {
    die('Não encontrei o PFX em: ' . htmlspecialchars($pfxPath));
}
$cert = Certificate::readPfx($pfx, $pfxPass);

// === Config
$config = require __DIR__ . '/nfce_config.php';
$configJson = json_encode($config, JSON_UNESCAPED_UNICODE);

// === Tools (modelo 65 = NFC-e)
$tools = new Tools($configJson, $cert);
$tools->model('65'); // importantíssimo

// Status (a UF vem da config: RN)
$xml = $tools->sefazStatus();

$std = new Standardize();
$std = $std->toStd($xml);

header('Content-Type: text/html; charset=utf-8');
echo "<h3>Status do Serviço NFC-e – SVRS (RN)</h3>";
echo "<p><b>cStat:</b> {$std->cStat}</p>";
echo "<p><b>xMotivo:</b> {$std->xMotivo}</p>";
if (!empty($std->dhRecbto)) echo "<p><b>dhRecbto:</b> {$std->dhRecbto}</p>";

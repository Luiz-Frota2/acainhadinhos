<?php
/**
 * nfce/nfce_config.php — retorna o array de configuração para a NFePHP
 * totalmente a partir dos dados do BANCO, via config.php.
 */

declare(strict_types=1);

// Carrega config.php (ele lê integracao_nfce e define as constantes)
$try = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
];
$ok = false;
foreach ($try as $p) {
    if (is_file($p)) { require_once $p; $ok = true; break; }
}
if (!$ok) {
    http_response_code(500);
    die('nfce_config.php: não encontrei config.php');
}

// Sanity check das constantes essenciais vindas do banco
$need = ['TP_AMB','EMIT_XNOME','EMIT_UF','EMIT_CNPJ','CSC','ID_TOKEN'];
foreach ($need as $c) {
    if (!defined($c) || constant($c) === '' || constant($c) === null) {
        http_response_code(500);
        die("nfce_config.php: constante ausente/invalidada: {$c}");
    }
}

// Monta e retorna a configuração que a NFePHP espera
return [
    'atualizacao' => date('Y-m-d H:i:s'),
    'tpAmb'       => (int)TP_AMB,             // 1=Produção, 2=Homologação (normalizado no config.php)
    'razaosocial' => EMIT_XNOME,
    'siglaUF'     => EMIT_UF,
    'cnpj'        => (string)EMIT_CNPJ,
    'schemes'     => 'PL_009_V4',
    'versao'      => '4.00',

    // URLs oficiais (por UF e ambiente), já resolvidas no config.php
    'urlChave'    => defined('URL_CHAVE') ? constant('URL_CHAVE') : '',
    'urlQRCode'   => defined('URL_QR')    ? constant('URL_QR')    : '',

    // NFC-e (QR-Code)
    'CSC'         => CSC,
    'CSCid'       => ID_TOKEN,

    // Proxy (se não usar, deixe vazio)
    'proxyConf'   => [
        'proxyIp'   => '',
        'proxyPort' => '',
        'proxyUser' => '',
        'proxyPass' => ''
    ],
];

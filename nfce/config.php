<?php
/**
 * nfce/config.php — carrega 100% do banco (integracao_nfce) pela empresa_id.
 * Sem dados estáticos. Certificado é resolvido localmente.
 */

if (defined('NFCE_CONFIG_LOADED')) { return; }
define('NFCE_CONFIG_LOADED', true);

date_default_timezone_set('America/Manaus');

// ========== Localiza conexão PDO ($pdo) ==========
$pdo = null;
$tryPaths = [
  __DIR__ . '/../conexao/conexao.php',
  __DIR__ . '/../../conexao/conexao.php',
  __DIR__ . '/../../../conexao/conexao.php',
  __DIR__ . '/../assets/conexao.php',
  __DIR__ . '/../../assets/conexao.php',
  $_SERVER['DOCUMENT_ROOT'] . '/assets/php/conexao.php',
];
foreach ($tryPaths as $p) {
  if (is_file($p)) {
    require_once $p; // deve popular $pdo
    if (isset($pdo) && $pdo instanceof PDO) { break; }
  }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
  http_response_code(500);
  die('Config NFC-e: conexão PDO não encontrada. Ajuste os caminhos em nfce/config.php.');
}

// ========== Helpers ==========
function so_digitos(?string $v): ?string {
  if ($v === null) return null;
  $v = preg_replace('/\D+/', '', $v);
  return $v !== '' ? $v : null;
}

/**
 * Resolve o caminho local do certificado .pfx/.p12 a partir do valor salvo no banco.
 * Aceita:
 *   - Nome simples: "meu.pfx" (preferido). Busca em /assets/img/certificado/ e outras pastas comuns.
 *   - Caminho relativo: "nfce/certificado.pfx" → procura a partir do DOCUMENT_ROOT.
 *   - URL (Hostinger): extrai o basename e busca localmente.
 *   - Caminho absoluto: usa diretamente se existir.
 */
function resolve_cert_path(?string $fileFromDb, array &$attempts = []): ?string {
  if (!$fileFromDb) return null;

  $original = $fileFromDb;

  // Se veio URL, extrai o nome do arquivo
  if (preg_match('~^https?://~i', $fileFromDb)) {
    $urlPath = parse_url($fileFromDb, PHP_URL_PATH);
    $fileFromDb = $urlPath ? basename($urlPath) : basename($fileFromDb);
  }

  $docroot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');

  // Se começou com '/', trate como caminho absoluto
  if (strlen($fileFromDb) > 0 && $fileFromDb[0] === '/') {
    $attempts[] = $fileFromDb;
    if (is_file($fileFromDb)) return $fileFromDb;
  } else {
    // Caminho relativo informado (ex.: "nfce/certificado.pfx") — tentar relativo ao DOCROOT
    if (strpos($fileFromDb, '/') !== false) {
      $rel = $docroot . '/' . ltrim($fileFromDb, '/');
      $attempts[] = $rel;
      if (is_file($rel)) return $rel;
      // Também tentar dentro de public_html (hosts comuns)
      $rel2 = $docroot . '/public_html/' . ltrim($fileFromDb, '/');
      $attempts[] = $rel2;
      if (is_file($rel2)) return $rel2;
    }

    // Nome simples — usar somente o basename
    $base = basename($fileFromDb);
    $candidates = [
      $docroot . '/assets/img/certificado/' . $base,
      $docroot . '/public_html/assets/img/certificado/' . $base,
      __DIR__   . '/../../assets/img/certificado/' . $base,
      __DIR__   . '/../certificados/' . $base,
      __DIR__   . '/' . $base,
    ];
    foreach ($candidates as $c) {
      $attempts[] = $c;
      if (is_file($c)) return $c;
    }
  }

  // Última chance: se o valor original parecer absoluto e existir
  if ($original !== $fileFromDb) {
    $attempts[] = $original;
    if (is_file($original)) return $original;
  }

  return null;
}

// ========== Qual empresa carregar? ==========
$empresaId = null;
if (isset($_GET['id']) && $_GET['id'] !== '') {
  $empresaId = (string)$_GET['id'];
} elseif (defined('EMPRESA_ID')) {
  $empresaId = (string)constant('EMPRESA_ID');
} elseif (isset($_SESSION['empresa_id'])) {
  $empresaId = (string)$_SESSION['empresa_id'];
}
if (!$empresaId) {
  http_response_code(400);
  die('Config NFC-e: empresa_id não informada. Passe ?id=EMPRESA_ID ao chamar emitir.php.');
}

// ========== Lê integracao_nfce ==========
$st = $pdo->prepare("
  SELECT empresa_id, cnpj, razao_social, nome_fantasia, inscricao_estadual, inscricao_municipal,
         cep, logradouro, numero_endereco, complemento, bairro, cidade, uf, codigo_uf, codigo_municipio,
         telefone, certificado_digital, senha_certificado, ambiente, regime_tributario, csc, csc_id,
         serie_nfce, tipo_emissao, finalidade, ind_pres, tipo_impressao
    FROM integracao_nfce
   WHERE empresa_id = :emp
   LIMIT 1
");
$st->execute([':emp' => $empresaId]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) {
  http_response_code(404);
  die('Config NFC-e: não há registro em integracao_nfce para empresa_id=' . $empresaId);
}
// ========== Normalização de ambiente (tpAmb) ==========
function map_tp_amb($v) {
  $v = trim((string)$v);
  $vl = mb_strtolower($v, 'UTF-8');
  if ($v === '1' || $vl === 'producao' || $vl === 'produção' || $vl === 'prod' || $vl === 'p') return '1';
  if ($v === '2' || $vl === 'homologacao' || $vl === 'homologação' || $vl === 'hom' || $vl === 'h') return '2';
  if ($v === '0') return '2'; // alguns bancos usam 0 para homologação
  return '';
}
$TP_AMB   = map_tp_amb($row['ambiente'] ?? '');
$ID_TOKEN = (string)($row['csc_id'] ?? '');
$CSC      = (string)($row['csc'] ?? '');
$NFC_SERIE= (string)($row['serie_nfce'] ?? '');

// ========== Validações críticas (sem defaults em produção) ==========

if ($ID_TOKEN === '' || $CSC === '') {
  http_response_code(422);
  die('Config NFC-e: CSC e/ou ID_TOKEN ausentes no banco.');
}
if ($NFC_SERIE === '') {
  http_response_code(422);
  die('Config NFC-e: Série da NFC-e ausente no banco.');
}
if ($TP_AMB === '') {
  http_response_code(422);
  die('Config NFC-e: Ambiente (1=produção, 2=homologação) ausente no banco.');
}


// ========== Valida obrigatórios do emissor ==========
$EMIT_CNPJ  = so_digitos($row['cnpj'] ?? null);
$EMIT_XNOME = trim((string)($row['razao_social'] ?? ''));
$EMIT_XFANT = trim((string)($row['nome_fantasia'] ?? ''));
$EMIT_IE    = so_digitos($row['inscricao_estadual'] ?? null);
$EMIT_CRT   = (string)($row['regime_tributario'] ?? '');

$EMIT_CEP   = so_digitos($row['cep'] ?? null);
$EMIT_XLGR  = trim((string)($row['logradouro'] ?? ''));
$EMIT_NRO   = trim((string)($row['numero_endereco'] ?? ''));
$EMIT_XBAIRRO = trim((string)($row['bairro'] ?? ''));
$EMIT_XMUN  = trim((string)($row['cidade'] ?? ''));
$EMIT_UF    = trim((string)($row['uf'] ?? ''));
$EMIT_CMUN  = (string)($row['codigo_municipio'] ?? '');

$EMIT_FONE  = so_digitos($row['telefone'] ?? null);
if ($EMIT_FONE !== null && (strlen($EMIT_FONE) < 6 || strlen($EMIT_FONE) > 14)) {
  // telefone inválido — NÃO define constante; manter XML válido (fone é opcional)
  $EMIT_FONE = null;
}

// Ambiente / CSC / série

// Código da UF
$COD_UF     = $row['codigo_uf'] ?? null;
if (!$COD_UF && $EMIT_CMUN) {
  $COD_UF = substr(preg_replace('/\D+/', '', (string)$EMIT_CMUN), 0, 2);
}
$COD_UF = (string)$COD_UF;

// Certificado
$PFX_PASSWORD = (string)($row['senha_certificado'] ?? '');
$PFX_FROM_DB  = $row['certificado_digital'] ?? null;
$attempts = [];
$PFX_PATH     = resolve_cert_path($PFX_FROM_DB, $attempts);

// ====== Checagens finais (sem fallback estático) ======
$missing = [];
foreach ([
  'CNPJ'=>$EMIT_CNPJ,'xNome'=>$EMIT_XNOME,'IE'=>$EMIT_IE,'CRT'=>$EMIT_CRT,
  'CEP'=>$EMIT_CEP,'xLgr'=>$EMIT_XLGR,'nro'=>$EMIT_NRO,'xBairro'=>$EMIT_XBAIRRO,
  'xMun'=>$EMIT_XMUN,'UF'=>$EMIT_UF,'cMun'=>$EMIT_CMUN,
  'tpAmb'=>$TP_AMB,'CSC'=>$CSC,'CSCid'=>$ID_TOKEN,'COD_UF'=>$COD_UF,
] as $k=>$v) { if ($v === null || $v === '') $missing[] = $k; }

if (!$PFX_PATH) {
  $missing[] = 'certificado (.pfx/.p12)';
  $msg = "Config NFC-e incompleta: faltando " . implode(', ', $missing) . ". ";
  $msg .= "Não encontrei o PFX. Caminhos testados:\n - " . implode("\n - ", $attempts) . "\n";
  $msg .= "Valor salvo no banco: " . ($PFX_FROM_DB ?? '(nulo)');
  http_response_code(422);
  die(nl2br(htmlentities($msg)));
}
if ($PFX_PASSWORD === '') {
  $missing[] = 'senha do certificado';
  http_response_code(422);
  die('Config NFC-e incompleta: faltando ' . implode(', ', $missing) . '. Verifique integracao_nfce desta empresa.');
}

// URL de consulta do QRCode (RN)



// ========== Define as constantes ==========
define('EMIT_CNPJ',  $EMIT_CNPJ);
define('EMIT_XNOME', $EMIT_XNOME);
define('EMIT_XFANT', $EMIT_XFANT);
define('EMIT_IE',    $EMIT_IE);
define('EMIT_CRT',   (string)$EMIT_CRT);

define('EMIT_XLGR',    $EMIT_XLGR);
define('EMIT_NRO',     $EMIT_NRO);
define('EMIT_XBAIRRO', $EMIT_XBAIRRO);
define('EMIT_XMUN',    $EMIT_XMUN);
define('EMIT_UF',      $EMIT_UF);
define('EMIT_CEP',     (string)$EMIT_CEP);
if ($EMIT_FONE !== null) define('EMIT_FONE', $EMIT_FONE);

define('EMIT_CMUN',  (string)$EMIT_CMUN);
if (!defined('COD_MUN')) { define('COD_MUN', (string)EMIT_CMUN); }
define('COD_UF',     (string)$COD_UF);
define('NFC_SERIE',  (string)$NFC_SERIE);

define('TP_AMB',     (string)$TP_AMB);
define('ID_TOKEN',   (string)$ID_TOKEN);
define('CSC',        (string)$CSC);

define('PFX_PATH',     (string)$PFX_PATH);
define('PFX_PASSWORD', (string)$PFX_PASSWORD);

define('URL_QR',     (string)$URL_QR);

if (!defined('NFCE_EMPRESA_ID')) {
  define('NFCE_EMPRESA_ID', $empresaId);
}


// ========== URLs por UF (QRCode e Chave) ==========
$urlsUF = [
  'RN' => [
    'prod' => ['qr' => 'https://nfce.set.rn.gov.br/portalDFE/NFCe/ConsultaNFCe.aspx', 'chave' => 'https://nfce.set.rn.gov.br/portalDFE/NFCe/ConsultaNFCe.aspx'],
    'hom'  => ['qr' => 'https://hom.nfce.set.rn.gov.br/portalDFE/NFCe/ConsultaNFCe.aspx', 'chave' => 'https://hom.nfce.set.rn.gov.br/portalDFE/NFCe/ConsultaNFCe.aspx'],
  ],
  'AM' => [
    'prod' => ['qr' => 'https://nfce.sefaz.am.gov.br/nfceweb/consultarNFCe.jsp', 'chave' => 'https://nfce.sefaz.am.gov.br/nfceweb/consultarNFCe.jsp'],
    'hom'  => ['qr' => 'https://homnfce.sefaz.am.gov.br/nfceweb/consultarNFCe.jsp', 'chave' => 'https://homnfce.sefaz.am.gov.br/nfceweb/consultarNFCe.jsp'],
  ],
  // Adicione outras UF aqui conforme necessário
];
$ufKey  = strtoupper((string)($row['uf'] ?? 'RN'));
$ambKey = ($TP_AMB === '1') ? 'prod' : 'hom';
$URL_QR     = $urlsUF[$ufKey][$ambKey]['qr']    ?? $urlsUF['RN'][$ambKey]['qr'];
$URL_CHAVE  = $urlsUF[$ufKey][$ambKey]['chave'] ?? $urlsUF['RN'][$ambKey]['chave'];


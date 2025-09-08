<?php
// Mostra erros (opcional em produção)
ini_set('display_errors', '1');
error_reporting(E_ALL);

/* ================== Sessão (sem aviso de já iniciada) ================== */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* ================== Parâmetros de navegação ================== */
$idSelecionado = $_GET['id'] ?? '';
if (!$idSelecionado) {
    header("Location: .././login.php");
    exit;
}

/* ================== Checagem de sessão ================== */
if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id'])
) {
    header("Location: .././login.php?id=" . rawurlencode($idSelecionado));
    exit;
}

/* ================== Conexão ================== */
require '../../assets/php/conexao.php';

/* ================== Usuário logado ================== */
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id  = (int)$_SESSION['usuario_id'];

try {
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    $stmt->bindValue(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $nomeUsuario = $usuario['usuario'];
        $tipoUsuario = ucfirst((string)$usuario['nivel']);
    } else {
        echo "<script>alert('Usuário não encontrado.'); window.location.href = '.././login.php?id=" . rawurlencode($idSelecionado) . "';</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar usuário: " . htmlspecialchars($e->getMessage()) . "'); history.back();</script>";
    exit;
}

/* ================== Validação de acesso por empresa ================== */
$tipoSession      = (string)$_SESSION['tipo_empresa'];         // principal | filial | unidade | franquia
$empresaIdSession = (string)$_SESSION['empresa_id'];           // pode ser numérico
// Normaliza um identificador do tipo "tipo_numero" (ex.: principal_1)
$empresaIdentSession = $_SESSION['empresa_identificador']
    ?? ($tipoSession . '_' . preg_replace('/\D+/', '', $empresaIdSession));

$prefixValido = (
    str_starts_with($idSelecionado, 'principal_') ||
    str_starts_with($idSelecionado, 'filial_')    ||
    str_starts_with($idSelecionado, 'unidade_')   ||
    str_starts_with($idSelecionado, 'franquia_')
);

$acessoPermitido = $prefixValido
    && str_starts_with($idSelecionado, $tipoSession . '_')
    && ($idSelecionado === $empresaIdentSession);

if (!$acessoPermitido) {
    echo "<script>
        alert('Acesso negado!');
        window.location.href = '.././login.php?id=" . rawurlencode($idSelecionado) . "';
    </script>";
    exit;
}

/* ================== Logo da empresa ================== */
try {
    $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1");
    $stmt->bindValue(':id', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);

    $logoEmpresa = (!empty($empresaSobre['imagem']))
        ? "../../assets/img/empresa/" . $empresaSobre['imagem']
        : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png"; // fallback
}

/* ===================================================================
   =============  BLOCO DE CONFIGURAÇÃO FISCAL (DINÂMICO)  ============
   =================================================================== */

/* ===== Helpers com prefixo (evita conflito) ===== */
if (!function_exists('nfce_digits')) {
    function nfce_digits(string $s): string { return preg_replace('/\D+/', '', $s); }
}
if (!function_exists('nfce_pad6')) {
    function nfce_pad6(string $n): string {
        $n = nfce_digits($n);
        return str_pad($n === '' ? '0' : $n, 6, '0', STR_PAD_LEFT);
    }
}
if (!function_exists('nfce_abspath')) {
    function nfce_abspath(string $path): string {
        // Se já parecer absoluto (Windows ou Unix), retorna
        if (preg_match('~^([A-Za-z]:\\\\|/)~', $path)) return $path;
        $base = rtrim(__DIR__, "/\\") . DIRECTORY_SEPARATOR . ltrim($path, "/\\");
        // realpath pode falhar se o arquivo não existir ainda; nesse caso retorna montagem
        $rp = realpath($base);
        return $rp !== false ? $rp : $base;
    }
}
if (!function_exists('nfce_is_basename')) {
    function nfce_is_basename(string $p): bool {
        return !str_contains($p, '/') && !str_contains($p, '\\');
    }
}
if (!function_exists('nfce_locate_pfx')) {
    /**
     * Localiza um .pfx a partir de:
     *  - caminho absoluto
     *  - pasta
     *  - apenas o nome do arquivo (ex.: cert_loja123.pfx)
     * Retorna caminho encontrado ou null. Preenche $checked com os caminhos testados.
     */
    function nfce_locate_pfx(string $hint, array &$checked = []): ?string {
        $hint = trim($hint);
        if ($hint === '') return null;

        // 1) Se for arquivo absoluto ou relativo e existir, retorna
        $p = nfce_abspath($hint);
        if (is_file($p) && preg_match('/\.pfx$/i', $p)) {
            $checked[] = $p . ' [ARQ]';
            return $p;
        }
        $checked[] = $p . ' [ARQ?]';

        // 2) Se for uma pasta, pega o .pfx mais recente
        if (is_dir($p)) {
            $dir = rtrim($p, "/\\") . DIRECTORY_SEPARATOR;
            $lst = array_merge(glob($dir.'*.pfx') ?: [], glob($dir.'*.PFX') ?: []);
            $checked[] = $dir . '*.pfx [DIR]';
            if (!empty($lst)) {
                usort($lst, fn($a,$b) => filemtime($b) <=> filemtime($a));
                return $lst[0];
            }
        }

        // 3) Se veio só o nome do arquivo, tenta em várias pastas padrão
        if (nfce_is_basename($hint)) {
            $name = $hint;
            if (!preg_match('/\.pfx$/i', $name)) $name .= '.pfx';

            $candidates = [
                __DIR__ . '/certificados/' . $name,
                __DIR__ . '/../certificados/' . $name,
                __DIR__ . '/../../assets/certificados/' . $name,
                __DIR__ . '/../../assets/img/certificado/' . $name,
                getcwd() . '/certificados/' . $name,
                __DIR__ . '/' . $name,
            ];
            foreach ($candidates as $cand) {
                $checked[] = $cand . ' [CAND]';
                if (is_file($cand)) return $cand;
            }
        }

        return null;
    }
}

/* ===== Carrega integracao_nfce da empresa (usa a chave que você já usa hoje) =====
   Observação: se sua tabela usar outro campo (ex.: empresa_identificador), ajuste a query conforme seu schema. */
try {
    $stmt = $pdo->prepare("SELECT * FROM integracao_nfce WHERE empresa_id = :eid LIMIT 1");
    $stmt->bindValue(':eid', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $cfg = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cfg) {
        // Se não encontrou, você pode tentar por identificador alternativo:
        // $stmt = $pdo->prepare("SELECT * FROM integracao_nfce WHERE empresa_identificador = :eid OR id_selecionado = :eid LIMIT 1");
        // $stmt->bindValue(':eid', $idSelecionado, PDO::PARAM_STR);
        // $stmt->execute();
        // $cfg = $stmt->fetch(PDO::FETCH_ASSOC);
        // if (!$cfg) { die(...); }

        die('Configuração NFC-e não encontrada para empresa_id=' . htmlspecialchars($idSelecionado) . '. Cadastre em integracao_nfce.');
    }
} catch (PDOException $e) {
    die('Erro ao carregar integracao_nfce: ' . htmlspecialchars($e->getMessage()));
}

/* ===== Normalizações e defaults ===== */
$cnpj    = nfce_digits($cfg['cnpj']               ?? '');
$ie      = nfce_digits($cfg['inscricao_estadual'] ?? '');
$cep     = nfce_digits($cfg['cep']                ?? '');
$cmc     = nfce_digits((string)($cfg['codigo_municipio'] ?? '')); // IBGE
$crtVal  = (int)($cfg['regime_tributario']        ?? 1);          // 1,2,3
$crt     = (string)($crtVal >= 1 && $crtVal <= 3 ? $crtVal : 1);
$amb     = (int)($cfg['ambiente']                 ?? 2);          // 1=Produção, 2=Homolog
$csc     = trim((string)($cfg['csc']              ?? ''));
$idtoken = nfce_pad6((string)($cfg['id_token']    ?? '1'));

$razao   = trim((string)($cfg['razao_social']  ?? ''));
$fant    = trim((string)($cfg['nome_fantasia'] ?? $razao));
$logradouro = trim((string)($cfg['logradouro'] ?? ''));
$nro        = trim((string)($cfg['numero_endereco'] ?? 'SN'));
$compl      = trim((string)($cfg['complemento'] ?? ''));
$bairro     = trim((string)($cfg['bairro'] ?? ''));
$cidade     = trim((string)($cfg['cidade'] ?? ''));
$uf         = strtoupper(trim((string)($cfg['uf'] ?? 'RN')));

/* ===== Certificado: aceita arquivo, pasta ou só o nome ===== */
$hintPfx = trim((string)($cfg['certificado_digital'] ?? ''));
if ($hintPfx === '') {
    // fallback: pasta padrão
    $hintPfx = '../../assets/img/certificado/';
}
$checados = [];
$pfxFile  = nfce_locate_pfx($hintPfx, $checados);
if (!$pfxFile) {
    // Mostra caminhos testados para facilitar debug
    die('Não encontrei nenhum .pfx com base em: ' . htmlspecialchars($hintPfx) .
        '. Verifique se o arquivo foi enviado e o caminho está correto. Testados: ' .
        htmlspecialchars(implode(' | ', $checados)));
}

$pfxPass = (string)($cfg['senha_certificado'] ?? '');
if ($pfxPass === '') {
    die('Senha do certificado vazia na tabela integracao_nfce.');
}

/* ===== Validação rápida de CSC ===== */
if ($csc === '') {
    die('CSC vazio na tabela integracao_nfce. Preencha o CSC e o ID Token corretos.');
}

/* ===== URL pública de consulta (QR) do RN, por ambiente) ===== */
$urlQR = ($amb === 1)
  ? 'https://nfce.set.rn.gov.br/portalDFE/NFCe/ConsultaNFCe.aspx'
  : 'https://hom.nfce.set.rn.gov.br/portalDFE/NFCe/ConsultaNFCe.aspx';

/* ===== Define CONSTANTES usadas pelo emissor ===== */
if (!defined('EMIT_CNPJ'))    define('EMIT_CNPJ',    $cnpj);
if (!defined('EMIT_IE'))      define('EMIT_IE',      $ie);
if (!defined('EMIT_XNOME'))   define('EMIT_XNOME',   $razao);
if (!defined('EMIT_XFANT'))   define('EMIT_XFANT',   $fant);
if (!defined('EMIT_CRT'))     define('EMIT_CRT',     $crt);
if (!defined('EMIT_CMUN'))    define('EMIT_CMUN',    $cmc);
if (!defined('EMIT_XMUN'))    define('EMIT_XMUN',    strtoupper($cidade));
if (!defined('EMIT_UF'))      define('EMIT_UF',      $uf);
if (!defined('EMIT_CEP'))     define('EMIT_CEP',     $cep);
if (!defined('EMIT_END'))     define('EMIT_END',     $logradouro . ($compl !== '' ? ' ' . $compl : '')); // logradouro + complemento
if (!defined('EMIT_NUM'))     define('EMIT_NUM',     $nro);
if (!defined('EMIT_BAIRRO'))  define('EMIT_BAIRRO',  strtoupper($bairro));

if (!defined('TP_AMB'))       define('TP_AMB',       (string)$amb);
if (!defined('CSC'))          define('CSC',          $csc);
if (!defined('ID_TOKEN'))     define('ID_TOKEN',     $idtoken);

if (!defined('PFX_PATH'))     define('PFX_PATH',     $pfxFile);
if (!defined('PFX_PASSWORD')) define('PFX_PASSWORD', $pfxPass);

if (!defined('URL_QR'))       define('URL_QR',       $urlQR);

/* ===== Fuso (RN está em -03) ===== */
date_default_timezone_set('America/Fortaleza');

/* ================== (Daqui pra baixo fica sua página/app) ================== */
/* As CONSTANTES já estão prontas para o emitir.php/status.php usarem sem conflito. */

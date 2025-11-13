<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

/* ====================== Sessão / Acesso ====================== */
$idSelecionado = $_GET['id'] ?? '';
if (!$idSelecionado) {
    header("Location: .././login.php");
    exit;
}

if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id'])
) {
    header("Location: .././login.php?id=" . urlencode($idSelecionado));
    exit;
}

require '../../assets/php/conexao.php';

/* ====================== Usuário ====================== */
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id = (int)$_SESSION['usuario_id'];

try {
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($usuario) {
        $nomeUsuario = $usuario['usuario'];
        $tipoUsuario = ucfirst($usuario['nivel']);
    } else {
        echo "<script>alert('Usuário não encontrado.'); window.location.href='.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar usuário: " . addslashes($e->getMessage()) . "'); history.back();</script>";
    exit;
}

/* ====================== Permissões ====================== */
if (!function_exists('str_starts_with')) {
    function str_starts_with($h, $n)
    {
        return 0 === strncmp($h, $n, strlen($n));
    }
}
$acessoPermitido = false;
$idEmpresaSession = $_SESSION['empresa_id'];
$tipoSession      = $_SESSION['tipo_empresa'];

if (str_starts_with($idSelecionado, 'principal_')) {
    $acessoPermitido = ($tipoSession === 'principal' && $idEmpresaSession === 'principal_1');
} elseif (str_starts_with($idSelecionado, 'filial_')) {
    $acessoPermitido = ($tipoSession === 'filial' && $idEmpresaSession === $idSelecionado);
} elseif (str_starts_with($idSelecionado, 'unidade_')) {
    $acessoPermitido = ($tipoSession === 'unidade' && $idEmpresaSession === $idSelecionado);
} elseif (str_starts_with($idSelecionado, 'franquia_')) {
    $acessoPermitido = ($tipoSession === 'franquia' && $idEmpresaSession === $idSelecionado);
}
if (!$acessoPermitido) {
    echo "<script>alert('Acesso negado!'); window.location.href='.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
    exit;
}

/* ====================== Logo ====================== */
try {
    $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1");
    $stmt->bindParam(':id', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);
    $logoEmpresa = !empty($empresaSobre['imagem'])
        ? "../../assets/img/empresa/" . $empresaSobre['imagem']
        : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png";
}

/* ====================== Helpers ====================== */
function onlyDigits(string $s): string
{
    return preg_replace('/\D+/', '', $s);
}
function moeda($v): string
{
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}
function fmtChave($ch): string
{
    $ch = onlyDigits((string)$ch);
    return trim(implode(' ', str_split($ch, 4)));
}
function mapTPag($t)
{
    $k = str_pad(onlyDigits((string)$t), 2, '0', STR_PAD_LEFT);
    $m = ['01' => 'Dinheiro', '02' => 'Cheque', '03' => 'Cartão de Crédito', '04' => 'Cartão de Débito', '05' => 'Crédito Loja', '10' => 'Vale Alimentação', '11' => 'Vale Refeição', '12' => 'Vale Presente', '13' => 'Vale Combustível', '15' => 'Boleto', '16' => 'Depósito', '17' => 'PIX', '18' => 'Transferência/Carteira', '19' => 'Fidelidade', '90' => 'Sem Pagamento', '99' => 'Outros'];
    return $m[$k] ?? 'Outros';
}

/* ====================== Ação: download XML ====================== */
if (isset($_GET['dl'])) {
    $dlId = (int)$_GET['dl'];
    if ($dlId > 0) {
        try {
            $st = $pdo->prepare("SELECT chave, xml_nfeproc FROM nfce_emitidas WHERE id = :id AND empresa_id = :emp LIMIT 1");
            $st->execute([':id' => $dlId, ':emp' => $idSelecionado]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['xml_nfeproc'])) {
                $ch = onlyDigits((string)$row['chave']);
                $nome = 'procNFCe_' . ($ch ?: 'semchave') . '.xml';
                $xml = (string)$row['xml_nfeproc'];
                while (ob_get_level()) ob_end_clean();
                header('Content-Type: application/xml; charset=UTF-8');
                header('Content-Disposition: attachment; filename="' . $nome . '"');
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                echo $xml;
                exit;
            }
        } catch (Throwable $e) {
        }
    }
    // fallback
    while (ob_get_level()) ob_end_clean();
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "XML não encontrado.";
    exit;
}

/* ====================== Filtros / Paginação ====================== */
$q         = trim((string)($_GET['q'] ?? ''));            // chave (parcial ou cheia)
$venda_id  = (int)($_GET['venda_id'] ?? 0);
$pp        = (int)($_GET['pp'] ?? 20);
$pp        = ($pp === 50 || $pp === 100) ? $pp : 20;
$p         = max(1, (int)($_GET['p'] ?? 1));
$offset    = ($p - 1) * $pp;

$where = ["empresa_id = :emp"];
$params = [':emp' => $idSelecionado];

if ($q !== '') {
    $qDig = onlyDigits($q);
    if ($qDig !== '') {
        $where[] = "REPLACE(REPLACE(REPLACE(REPLACE(chave,'.',''),'-',''),' ','') ,'/', '') LIKE :ch";
        $params[':ch'] = '%' . $qDig . '%';
    }
}
if ($venda_id > 0) {
    $where[] = "venda_id = :v";
    $params[':v'] = $venda_id;
}

$whereSql = implode(' AND ', $where);

$total = 0;
try {
    $st = $pdo->prepare("SELECT COUNT(*) AS c FROM nfce_emitidas WHERE $whereSql");
    $st->execute($params);
    $total = (int)($st->fetchColumn() ?: 0);
} catch (Throwable $e) {
    $total = 0;
}

$rows = [];
try {
    $sql = "SELECT id, venda_id, chave, xml_nfeproc
            FROM nfce_emitidas
           WHERE $whereSql
           ORDER BY id DESC
           LIMIT :lim OFFSET :off";
    $st = $pdo->prepare($sql);
    foreach ($params as $k => $v) $st->bindValue($k, $v);
    $st->bindValue(':lim', $pp, PDO::PARAM_INT);
    $st->bindValue(':off', $offset, PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $rows = [];
}

/* ====================== Parse XML helper ====================== */
function parseNFCeInfo(?string $xmlStr): array
{
    $ret = [
        'nNF' => '',
        'serie' => '',
        'dhEmis' => '',
        'vNF' => '',
        'tPag' => '',
        'cStat' => '',
        'xMotivo' => ''
    ];
    if (!$xmlStr) return $ret;
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    if (!$dom->loadXML($xmlStr, LIBXML_COMPACT | LIBXML_NOBLANKS | LIBXML_NONET)) return $ret;
    $ns = 'http://www.portalfiscal.inf.br/nfe';

    $ide = $dom->getElementsByTagNameNS($ns, 'ide')->item(0);
    if ($ide) {
        $ret['nNF']   = ($ide->getElementsByTagName('nNF')->item(0)->nodeValue ?? '') ?: '';
        $ret['serie'] = ($ide->getElementsByTagName('serie')->item(0)->nodeValue ?? '') ?: '';
        $ret['dhEmis'] = ($ide->getElementsByTagName('dhEmis')->item(0)->nodeValue ?? '') ?: '';
    }

    $tot = $dom->getElementsByTagNameNS($ns, 'ICMSTot')->item(0);
    if ($tot) {
        $ret['vNF'] = ($tot->getElementsByTagName('vNF')->item(0)->nodeValue ?? '') ?: '';
    }

    $detPag = $dom->getElementsByTagNameNS($ns, 'detPag')->item(0);
    if ($detPag) {
        $ret['tPag'] = ($detPag->getElementsByTagName('tPag')->item(0)->nodeValue ?? '') ?: '';
    }

    $prot = $dom->getElementsByTagNameNS($ns, 'protNFe')->item(0);
    if ($prot) {
        $infProt = $prot->getElementsByTagName('infProt')->item(0);
        if ($infProt) {
            $ret['cStat']   = ($infProt->getElementsByTagName('cStat')->item(0)->nodeValue ?? '') ?: '';
            $ret['xMotivo'] = ($infProt->getElementsByTagName('xMotivo')->item(0)->nodeValue ?? '') ?: '';
        }
    }
    libxml_clear_errors();
    return $ret;
}

function statusBadgeClass($cStat): string
{
    $c = (string)$cStat;
    if ($c === '100') return 'bg-label-success';   // Autorizado
    if (in_array($c, ['101', '102', '135'], true)) return 'bg-label-secondary'; // Cancel/deneg pref.
    if (in_array($c, ['110', '204', '539'], true)) return 'bg-label-warning';
    return 'bg-label-info';
}

/* ====================== HTML ====================== */
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>ERP - PDV | NFC-e Consulta</title>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($logoEmpresa) ?>" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />
    <script src="../../assets/vendor/js/helpers.js"></script>
    <script src="../../assets/js/config.js"></script>
    <style>
        .chave {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 12px
        }

        .table td {
            vertical-align: middle;
        }

        .nowrap {
            white-space: nowrap;
        }
    </style>
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- Menu -->
            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="app-brand-link">
                        <span class="app-brand-text demo menu-text fw-bolder ms-2" style="text-transform:capitalize;">Açaínhadinhos</span>
                    </a>
                    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
                        <i class="bx bx-chevron-left bx-sm align-middle"></i>
                    </a>
                </div>
                <div class="menu-inner-shadow"></div>
                <ul class="menu-inner py-1">
                    <li class="menu-item"><a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div>Dashboard</div>
                        </a>
                    </li>
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">PDV</span></li>
                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle"><i class="menu-icon tf-icons bx bx-file"></i>
                            <div>SEFAZ</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item"><a href="./adicionarNFCe.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>NFC-e</div>
                                </a>
                            </li>
                            <li class="menu-item"><a href="./sefazStatus.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Status</div>
                                </a>
                            </li>
                            <li class="menu-item active"><a href="./sefazConsulta.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Consulta</div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle"><i class="menu-icon tf-icons bx bx-user"></i>
                            <div>Caixas</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item"><a href="./caixasAberto.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Caixas Aberto</div>
                                </a>
                            </li>
                            <li class="menu-item"><a href="./caixasFechado.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Caixas Fechado</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="menu-item"><a href="javascript:void(0);" class="menu-link menu-toggle"><i class="menu-icon tf-icons bx bx-file"></i>
                            <div>Relatórios</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item"><a href="./relatorioOperacional.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Operacional</div>
                                </a>
                            </li>
                            <li class="menu-item"><a href="./relatorioVendas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Vendas</div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
                    <li class="menu-item"><a href="../rh/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-group"></i>
                            <div>RH</div>
                        </a>
                    </li>
                    <li class="menu-item"><a href="../financas/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-dollar"></i>
                            <div>Finanças</div>
                        </a>
                    </li>
                    <li class="menu-item"><a href="../empresa/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div>Empresa</div>
                        </a>
                    </li>
                    <li class="menu-item"><a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-box"></i>
                            <div>Estoque</div>
                        </a>
                    </li>
                    <?php
                    $tipoLogado = $_SESSION['tipo_empresa'] ?? '';
                    $idLogado = $_SESSION['empresa_id'] ?? '';

                    if ($tipoLogado === 'principal') {
                    ?>
                        <li class="menu-item">
                            <a href="../filial/index.php?id=principal_1" class="menu-link">
                                <i class="menu-icon tf-icons bx bx-building"></i>
                                <div data-i18n="Authentications">Filial</div>
                            </a>
                        </li>
                        <li class="menu-item">
                            <a href="../franquia/index.php?id=principal_1" class="menu-link">
                                <i class="menu-icon tf-icons bx bx-store"></i>
                                <div data-i18n="Authentications">Franquias</div>
                            </a>
                        </li>
                    <?php
                    } elseif (in_array($tipoLogado, ['filial', 'franquia', 'unidade'])) {
                    ?>
                        <li class="menu-item">
                            <a href="../matriz/index.php?id=<?= urlencode($idLogado) ?>" class="menu-link">
                                <i class="menu-icon tf-icons bx bx-cog"></i>
                                <div data-i18n="Authentications">Matriz</div>
                            </a>
                        </li>
                    <?php } ?>
                    <li class="menu-item"><a href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-group"></i>
                            <div>Usuários</div>
                        </a>
                    </li>
                    <li class="menu-item"><a href="https://wa.me/92991515710" target="_blank" class="menu-link"><i class="menu-icon tf-icons bx bx-support"></i>
                            <div>Suporte</div>
                        </a>
                    </li>
                </ul>
            </aside>
            <!-- /Menu -->

            <!-- Layout container -->
            <div class="layout-page">
                <!-- Navbar -->
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)"><i class="bx bx-menu bx-sm"></i></a>
                    </div>

                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <div class="navbar-nav align-items-center">
                            <div class="nav-item d-flex align-items-center">

                            </div>
                        </div>
                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="avatar avatar-online">
                                        <img src="<?= htmlspecialchars($logoEmpresa, ENT_QUOTES) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownUser">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online"><img src="<?= htmlspecialchars($logoEmpresa, ENT_QUOTES) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" /></div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <span class="fw-semibold d-block"><?= htmlspecialchars($nomeUsuario, ENT_QUOTES); ?></span>
                                                    <small class="text-muted"><?= htmlspecialchars($tipoUsuario, ENT_QUOTES); ?></small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                
                                    <li>
                                        <div class="dropdown-divider"></div>
                                    </li>
                                    <li><a class="dropdown-item" href="../logout.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-power-off me-2"></i><span class="align-middle">Sair</span></a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </nav>
                <!-- /Navbar -->

                <!-- Content -->
                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold py-3 mb-4">
                        <span class="fw-light" style="color:#696cff!important;"><a href="#">PDV</a></span> / NFC-e Consulta
                    </h4>

                    <!-- Filtros -->
                    <div class="card mb-4">
                        <form class="card-body" method="get" action="">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($idSelecionado) ?>">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-5">
                                    <label class="form-label">Chave da NFC-e (44 dígitos ou parcial)</label>
                                    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="Ex.: 3519... ou parte da chave">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">ID da Venda</label>
                                    <input type="number" name="venda_id" value="<?= $venda_id ?: '' ?>" class="form-control" min="1" placeholder="Ex.: 1234">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Por página</label>
                                    <select name="pp" class="form-select">
                                        <option value="20" <?= $pp === 20 ? 'selected' : ''; ?>>20</option>
                                        <option value="50" <?= $pp === 50 ? 'selected' : ''; ?>>50</option>
                                        <option value="100" <?= $pp === 100 ? 'selected' : ''; ?>>100</option>
                                    </select>
                                </div>
                                <div class="col-md-2 d-grid">
                                    <button class="btn btn-primary" type="submit"><i class="bx bx-search me-1"></i> Buscar</button>
                                </div>
                            </div>
                        </form>
                        <?php if ($q !== '' || $venda_id > 0): ?>
                            <div class="px-4 pb-3 text-muted"><small>Filtro ativo.</small></div>
                        <?php endif; ?>
                    </div>

                    <!-- Resultados -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title m-0">Resultados</h5>
                            <small class="text-muted">Mostrando <?= $total ? ($offset + 1) : 0 ?>–<?= min($offset + $pp, $total) ?> de <?= $total ?></small>
                        </div>
                        <div class="table-responsive text-nowrap">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th class="nowrap">Emissão</th>
                                        <th>Nº / Série</th>
                                        <th class="text-end">Valor</th>
                                        <th>Pagamento</th>
                                        <th>Chave</th>
                                        <th>Venda</th>
                                        <th>Situação</th>
                                        <th class="text-center">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($rows)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4">Nenhuma NFC-e encontrada.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($rows as $r):
                                            $info = parseNFCeInfo($r['xml_nfeproc'] ?? '');
                                            $emissao = $info['dhEmis'] ? date('d/m/Y H:i', strtotime($info['dhEmis'])) : '—';
                                            $numSerie = trim(($info['nNF'] ?: '—') . ' / ' . ($info['serie'] ?: '—'));
                                            $valor = $info['vNF'] !== '' ? moeda($info['vNF']) : '—';
                                            $pagto = $info['tPag'] !== '' ? mapTPag($info['tPag']) : '—';
                                            $chave = $r['chave'] ?: '';
                                            $cStat = $info['cStat'] ?: '';
                                            $xMot  = $info['xMotivo'] ?: '';
                                            $badge = statusBadgeClass($cStat);
                                        ?>
                                            <tr>
                                                <td class="nowrap"><?= htmlspecialchars($emissao) ?></td>
                                                <td><?= htmlspecialchars($numSerie) ?></td>
                                                <td class="text-end"><?= htmlspecialchars($valor) ?></td>
                                                <td><?= htmlspecialchars($pagto) ?></td>
                                                <td class="chave"><small><?= $chave ? fmtChave($chave) : '—' ?></small></td>
                                                <td>#<?= (int)$r['venda_id'] ?></td>
                                                <td>
                                                    <span class="badge <?= $badge ?>" title="<?= htmlspecialchars($xMot) ?>">
                                                        <?= $cStat === '100' ? 'Autorizada' : ($cStat ?: '—') ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($chave): ?>
                                                        <a class="btn btn-sm btn-primary me-1"
                                                            href="danfe_nfce.php?id=<?= urlencode($idSelecionado) ?>&venda_id=<?= (int)$r['venda_id'] ?>&chave=<?= urlencode($chave) ?>"
                                                            target="_blank" rel="noopener">Ver DANFE</a>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-secondary me-1" disabled>Sem DANFE</button>
                                                    <?php endif; ?>
                                                    <a class="btn btn-sm btn-outline-secondary me-1"
                                                        href="?id=<?= urlencode($idSelecionado) ?>&dl=<?= (int)$r['id'] ?>&p=<?= $p ?>&pp=<?= $pp ?>&q=<?= urlencode($q) ?>&venda_id=<?= (int)$venda_id ?>">
                                                        XML
                                                    </a>
                                                    <?php if ($chave): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-dark copy-chave" data-chave="<?= htmlspecialchars(onlyDigits($chave)) ?>">
                                                            Copiar chave
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php
                        // Paginação simples
                        $totalPag = $pp ? (int)ceil($total / $pp) : 1;
                        if ($totalPag < 1) $totalPag = 1;
                        ?>
                        <div class="card-footer d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted">Página <?= $p ?> de <?= $totalPag ?></small>
                            </div>
                            <nav>
                                <ul class="pagination pagination-sm m-0">
                                    <?php
                                    $qstr = '&id=' . urlencode($idSelecionado) . '&pp=' . $pp . '&q=' . urlencode($q) . '&venda_id=' . $venda_id;
                                    $prev = max(1, $p - 1);
                                    $next = min($totalPag, $p + 1);
                                    ?>
                                    <li class="page-item <?= $p <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?p=1<?= $qstr ?>">«</a>
                                    </li>
                                    <li class="page-item <?= $p <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?p=<?= $prev ?><?= $qstr ?>">‹</a>
                                    </li>
                                    <li class="page-item active"><span class="page-link"><?= $p ?></span></li>
                                    <li class="page-item <?= $p >= $totalPag ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?p=<?= $next ?><?= $qstr ?>">›</a>
                                    </li>
                                    <li class="page-item <?= $p >= $totalPag ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?p=<?= $totalPag ?><?= $qstr ?>">»</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div><!-- /card resultados -->
                </div>
                <!-- /Content -->
            </div>
            <!-- /Layout page -->
        </div>
    </div>

    <!-- Core JS -->
    <script src="../../js/saudacao.js"></script>
    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../assets/vendor/js/menu.js"></script>
    <script src="../../assets/vendor/libs/apex-charts/apexcharts.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/dashboards-analytics.js"></script>
    <script>
        // Copiar chave para área de transferência
        document.addEventListener('click', function(ev) {
            const btn = ev.target.closest('.copy-chave');
            if (!btn) return;
            const chave = btn.getAttribute('data-chave') || '';
            if (!chave) return;
            navigator.clipboard.writeText(chave).then(function() {
                btn.classList.remove('btn-outline-dark');
                btn.classList.add('btn-success');
                btn.textContent = 'Copiada!';
                setTimeout(function() {
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-outline-dark');
                    btn.textContent = 'Copiar chave';
                }, 1400);
            }).catch(function() {
                alert('Não foi possível copiar.');
            });
        }, false);
    </script>
</body>

</html>
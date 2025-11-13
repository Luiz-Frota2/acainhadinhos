<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

/* ============================================================================
   CONTEXTO E SEGURANÇA
   ============================================================================ */
$idSelecionado = $_GET['id'] ?? '';
if (!$idSelecionado) {
    header("Location: .././login.php");
    exit;
}

if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id'])     ||
    !isset($_SESSION['tipo_empresa'])   ||
    !isset($_SESSION['usuario_id'])
) {
    header("Location: .././login.php?id=" . urlencode($idSelecionado));
    exit;
}

/* ============================================================================
   CONEXÃO
   ============================================================================ */
require '../../assets/php/conexao.php';

/* ============================================================================
   USUÁRIO LOGADO
   ============================================================================ */
$nomeUsuario  = 'Usuário';
$tipoUsuario  = 'Comum';
$nivelUsuario = 'Comum';
$usuario_id   = $_SESSION['usuario_id'];

try {
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($usuario) {
        $nomeUsuario  = $usuario['usuario'];
        $tipoUsuario  = ucfirst($usuario['nivel']);
        $nivelUsuario = $usuario['nivel'];
    } else {
        echo "<script>alert('Usuário não encontrado.'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar usuário: " . $e->getMessage() . "'); history.back();</script>";
    exit;
}

/* ============================================================================
   REGRAS DE ACESSO
   ============================================================================ */
$acessoPermitido   = false;
$idEmpresaSession  = $_SESSION['empresa_id'];
$tipoSession       = $_SESSION['tipo_empresa'];

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
    echo "<script>alert('Acesso negado!'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
    exit;
}

/* ============================================================================
   LOGO DA EMPRESA
   ============================================================================ */
try {
    $sql  = "SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);

    $logoEmpresa = (!empty($empresaSobre['imagem']))
        ? "../../assets/img/empresa/" . $empresaSobre['imagem']
        : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png";
}

/* ============================================================================
   RELATÓRIO ANUAL (MANTENDO O MESMO ESTILO/UI)
   ============================================================================ */
date_default_timezone_set('America/Manaus');

$empresa_id = $idSelecionado;
$ano        = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');

/* --- Helpers --- */
function fmtBR($v)
{
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}
function nomeMes($m)
{
    static $nomes = [
        1 => 'Janeiro',
        'Fevereiro',
        'Março',
        'Abril',
        'Maio',
        'Junho',
        'Julho',
        'Agosto',
        'Setembro',
        'Outubro',
        'Novembro',
        'Dezembro'
    ];
    return $nomes[(int)$m] ?? (string)$m;
}

/* --- Descobrir coluna correta de valor em sangrias (valor -> valor_sangria -> valor_liquido) --- */
function tabelaTemColuna(PDO $pdo, string $tabela, string $coluna): bool
{
    $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c";
    $st  = $pdo->prepare($sql);
    $st->execute([':t' => $tabela, ':c' => $coluna]);
    return (int)$st->fetchColumn() > 0;
}
$sangriaValorCol = null;
if (tabelaTemColuna($pdo, 'sangrias', 'valor')) {
    $sangriaValorCol = 'valor';
} elseif (tabelaTemColuna($pdo, 'sangrias', 'valor_sangria')) {
    $sangriaValorCol = 'valor_sangria';
} elseif (tabelaTemColuna($pdo, 'sangrias', 'valor_liquido')) {
    // fallback (não é o ideal para “saídas” de sangria, mas garante compatibilidade)
    $sangriaValorCol = 'valor_liquido';
}

/* --- Coletas/Agregações --- */
$dadosAnuais = [];
$entradasAno = 0.0;
$saidasAno   = 0.0;
$saldoAno    = 0.0;

for ($m = 1; $m <= 12; $m++) {
    // Vendas (entradas)
    $st = $pdo->prepare("
        SELECT COALESCE(SUM(valor_total),0) AS total, COUNT(*) AS qtd
        FROM vendas
        WHERE empresa_id = :e AND YEAR(data_venda) = :a AND MONTH(data_venda) = :m
    ");
    $st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $m]);
    $v  = $st->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'qtd' => 0];
    $tv = (float)$v['total'];
    $qv = (int)$v['qtd'];

    // Suprimentos (entradas extras)
    $st = $pdo->prepare("
        SELECT COALESCE(SUM(valor_suprimento),0)
        FROM suprimentos
        WHERE empresa_id = :e AND YEAR(data_registro) = :a AND MONTH(data_registro) = :m
    ");
    $st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $m]);
    $ts = (float)($st->fetchColumn() ?: 0);

    // Sangrias (saídas) → usa a coluna detectada de valor de sangria (não o saldo!)
    $tg = 0.0;
    if ($sangriaValorCol) {
        $st = $pdo->prepare("
            SELECT COALESCE(SUM($sangriaValorCol),0)
            FROM sangrias
            WHERE empresa_id = :e AND YEAR(data_registro) = :a AND MONTH(data_registro) = :m
        ");
        $st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $m]);
        $tg = (float)($st->fetchColumn() ?: 0);
    }

    $entr = $tv + $ts;
    $said = $tg;
    $lucro  = $entr - $said;
    $ticket = $qv > 0 ? ($tv / $qv) : 0;

    $dadosAnuais[$m] = [
        'mes'      => $m,
        'entradas' => $entr,
        'saidas'   => $said,
        'lucro'    => $lucro,
        'vendas'   => $qv,
        'ticket'   => $ticket,
    ];

    $entradasAno += $entr;
    $saidasAno   += $said;
    $saldoAno    += $lucro;
}

$mediaMensal = $entradasAno / 12.0;

/* --- CSV --- */
if (isset($_GET['download']) && $_GET['download'] == '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio-anual.csv"');
    echo "\xEF\xBB\xBF"; // BOM UTF-8
    echo "Mês;Entradas;Saídas;Lucro;Vendas;Ticket Médio\n";
    for ($m = 1; $m <= 12; $m++) {
        $d = $dadosAnuais[$m];
        echo implode(";", [
            nomeMes($m),
            number_format((float)$d['entradas'], 2, ',', ''),
            number_format((float)$d['saidas'],   2, ',', ''),
            number_format((float)$d['lucro'],    2, ',', ''),
            (int)$d['vendas'],
            number_format((float)$d['ticket'],   2, ',', ''),
        ]) . "\n";
    }
    exit;
}

/* ============================================================================
   AGREGADOS, DESTAQUES E INDICADORES (mantendo o layout/labels)
   ============================================================================ */
$__meses             = $dadosAnuais;
$__total_vendas_ano  = array_sum(array_map(fn($d) => (int)($d['vendas'] ?? 0), $__meses));
$__media_entr_ano    = count($__meses) > 0 ? array_sum(array_map(fn($d) => (float)($d['entradas'] ?? 0), $__meses)) / 12 : 0.0;
$__media_said_ano    = count($__meses) > 0 ? array_sum(array_map(fn($d) => (float)($d['saidas'] ?? 0),   $__meses)) / 12 : 0.0;
$__media_lucro_ano   = count($__meses) > 0 ? array_sum(array_map(fn($d) => (float)($d['lucro'] ?? 0),    $__meses)) / 12 : 0.0;
$__ticket_medio_ano  = $__total_vendas_ano > 0
    ? (array_sum(array_map(fn($d) => (float)($d['entradas'] ?? 0), $__meses)) / $__total_vendas_ano)
    : 0.0;

$__total_lucro_ano = array_sum(array_map(fn($d) => (float)($d['lucro'] ?? 0), $__meses));

$__jan         = $dadosAnuais[1]['entradas']  ?? 0.0;
$__dez         = $dadosAnuais[12]['entradas'] ?? 0.0;
$__crescimento = ($__jan > 0) ? (($__dez - $__jan) / $__jan) * 100.0 : 0.0;

/* -- Destaques por máximo -- */
$__max_entr = 0.0;
$__max_entr_mes   = 1;
$__max_lucro = 0.0;
$__max_lucro_mes  = 1;
$__max_ticket = 0.0;
$__max_ticket_mes = 1;

for ($mm = 1; $mm <= 12; $mm++) {
    $d = $dadosAnuais[$mm];

    if (($d['entradas'] ?? 0) > $__max_entr) {
        $__max_entr     = (float)$d['entradas'];
        $__max_entr_mes = $mm;
    }
    if (($d['lucro'] ?? 0) > $__max_lucro) {
        $__max_lucro     = (float)$d['lucro'];
        $__max_lucro_mes = $mm;
    }
    if (($d['ticket'] ?? 0) > $__max_ticket) {
        $__max_ticket     = (float)$d['ticket'];
        $__max_ticket_mes = $mm;
    }
}

/* -- Mês com mais vendas + % acima da média de vendas (mantendo seu badge/estilo) -- */
$__max_vendas     = 0;
$__max_vendas_mes = 1;

for ($mm = 1; $mm <= 12; $mm++) {
    $qv = (int)($__meses[$mm]['vendas'] ?? 0);
    if ($qv > $__max_vendas) {
        $__max_vendas     = $qv;
        $__max_vendas_mes = $mm;
    }
}
$__media_vendas_mensal    = $__total_vendas_ano > 0 ? ($__total_vendas_ano / 12.0) : 0.0;
$__pct_acima_media_vendas = ($__media_vendas_mensal > 0)
    ? (($__max_vendas - $__media_vendas_mensal) / $__media_vendas_mensal) * 100.0
    : 0.0;

/* -- YoY do lucro (vs ano anterior), usando a MESMA coluna de sangrias detectada -- */
$__ano_anterior        = (int)$ano - 1;
$__lucro_ano_anterior  = 0.0;

for ($mm = 1; $mm <= 12; $mm++) {
    // vendas ano anterior
    $st = $pdo->prepare("
        SELECT COALESCE(SUM(valor_total),0)
        FROM vendas
        WHERE empresa_id=:e AND YEAR(data_venda)=:a AND MONTH(data_venda)=:m
    ");
    $st->execute([':e' => $empresa_id, ':a' => $__ano_anterior, ':m' => $mm]);
    $tv_prev = (float)($st->fetchColumn() ?: 0);

    // suprimentos ano anterior
    $st = $pdo->prepare("
        SELECT COALESCE(SUM(valor_suprimento),0)
        FROM suprimentos
        WHERE empresa_id=:e AND YEAR(data_registro)=:a AND MONTH(data_registro)=:m
    ");
    $st->execute([':e' => $empresa_id, ':a' => $__ano_anterior, ':m' => $mm]);
    $ts_prev = (float)($st->fetchColumn() ?: 0);

    // sangrias ANO ANTERIOR com a mesma coluna de valor
    $tg_prev = 0.0;
    if ($sangriaValorCol) {
        $st = $pdo->prepare("
            SELECT COALESCE(SUM($sangriaValorCol),0)
            FROM sangrias
            WHERE empresa_id=:e AND YEAR(data_registro)=:a AND MONTH(data_registro)=:m
        ");
        $st->execute([':e' => $empresa_id, ':a' => $__ano_anterior, ':m' => $mm]);
        $tg_prev = (float)($st->fetchColumn() ?: 0);
    }

    $__lucro_ano_anterior += ($tv_prev + $ts_prev) - $tg_prev;
}

$__crescimento_lucro_yoy_pct = ($__lucro_ano_anterior > 0)
    ? (($__total_lucro_ano - $__lucro_ano_anterior) / $__lucro_ano_anterior) * 100.0
    : 0.0;

/* -- Trimestres (mantendo blocos e rótulos) -- */
function somaTri($arr, $startMes, $endMes, $key)
{
    $s = 0.0;
    for ($i = $startMes; $i <= $endMes; $i++) {
        $s += (float)($arr[$i][$key] ?? 0);
    }
    return $s;
}
$__tri1_receita = somaTri($__meses, 1, 3, 'entradas');  // Jan-Mar
$__tri2_receita = somaTri($__meses, 4, 6, 'entradas');  // Abr-Jun

// Ano anterior (para % de crescimento dos trimestres) — compara ENTRADAS
$__tri1_prev = 0.0;  // Jan-Mar
$__tri2_prev = 0.0;  // Abr-Jun
for ($mm = 1; $mm <= 6; $mm++) {
    // entradas = vendas + suprimentos (sem sangrias)
    $st = $pdo->prepare("SELECT COALESCE(SUM(valor_total),0)
                         FROM vendas
                         WHERE empresa_id=:e AND YEAR(data_venda)=:a AND MONTH(data_venda)=:m");
    $st->execute([':e' => $empresa_id, ':a' => $__ano_anterior, ':m' => $mm]);
    $tvp = (float)($st->fetchColumn() ?: 0);

    $st = $pdo->prepare("SELECT COALESCE(SUM(valor_suprimento),0)
                         FROM suprimentos
                         WHERE empresa_id=:e AND YEAR(data_registro)=:a AND MONTH(data_registro)=:m");
    $st->execute([':e' => $empresa_id, ':a' => $__ano_anterior, ':m' => $mm]);
    $tsp = (float)($st->fetchColumn() ?: 0);

    $valEntradasPrev = $tvp + $tsp;
    if ($mm <= 3) $__tri1_prev += $valEntradasPrev;
    else          $__tri2_prev += $valEntradasPrev;
}

$__tri1_cres_pct = ($__tri1_prev > 0) ? (($__tri1_receita - $__tri1_prev) / $__tri1_prev) * 100.0 : 0.0;
$__tri2_cres_pct = ($__tri2_prev > 0) ? (($__tri2_receita - $__tri2_prev) / $__tri2_prev) * 100.0 : 0.0;

/* -- Projeção anual (mantendo seus rótulos) -- */
$__ultimo_mes_com_dados    = 0;
$__soma_receita_ate_agora  = 0.0;
for ($mm = 1; $mm <= 12; $mm++) {
    $val = (float)($__meses[$mm]['entradas'] ?? 0.0);
    if ($val > 0) $__ultimo_mes_com_dados = $mm;
    $__soma_receita_ate_agora += $val;
}
$__meses_decorridos         = max($__ultimo_mes_com_dados, (($ano == (int)date('Y')) ? (int)date('n') : 12));
$__media_ate_agora          = ($__meses_decorridos > 0) ? ($__soma_receita_ate_agora / $__meses_decorridos) : 0.0;
$__projecao_anual_receita   = $__media_ate_agora * 12.0;
$__projecao_crescimento_pct = ($__soma_receita_ate_agora > 0)
    ? (($__projecao_anual_receita - $__soma_receita_ate_agora) / $__soma_receita_ate_agora) * 100.0
    : 0.0;
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>ERP - Finanças</title>
    <meta name="description" content="" />
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($logoEmpresa ?? "../../assets/img/favicon/logo.png") ?>" />

    <!-- Fonts / Icons / CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />
    <script src="../../assets/vendor/js/helpers.js"></script>
    <script src="../../assets/js/config.js"></script>
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- MENU -->
            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="app-brand-link">
                        <span class="app-brand-text demo menu-text fw-bolder ms-2" style="text-transform: capitalize;">Açaínhadinhos</span>
                    </a>
                    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
                        <i class="bx bx-chevron-left bx-sm align-middle"></i>
                    </a>
                </div>

                <div class="menu-inner-shadow"></div>

                <ul class="menu-inner py-1">
                    <li class="menu-item">
                        <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Dashboard</div>
                        </a>
                    </li>

                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Finanças</span></li>
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-list-check"></i>
                            <div data-i18n="Authentications">Contas</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item"><a href="./contasAdicionadas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Adicionadas</div>
                                </a></li>
                            <li class="menu-item"><a href="./contasFuturos.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Futuras</div>
                                </a></li>
                            <li class="menu-item"><a href="./contasPagas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Pagas</div>
                                </a></li>
                            <li class="menu-item"><a href="./contasPendentes.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Pendentes</div>
                                </a></li>
                        </ul>
                    </li>

                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-dollar"></i>
                            <div data-i18n="Authentications">Relatórios</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item"><a href="./relatorioDiario.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Diário</div>
                                </a></li>
                            <li class="menu-item"><a href="./relatorioMensal.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Mensal</div>
                                </a></li>
                            <li class="menu-item active"><a href="./relatorioAnual.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Anual</div>
                                </a></li>
                        </ul>
                    </li>

                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
                    <li class="menu-item"><a href="../rh/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-group"></i>
                            <div data-i18n="Authentications">RH</div>
                        </a></li>
                    <li class="menu-item"><a href="../pdv/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-desktop"></i>
                            <div data-i18n="Authentications">PDV</div>
                        </a></li>
                    <li class="menu-item">
                        <a href="../empresa/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div data-i18n="Authentications">Empresa</div>
                        </a>
                    </li>
                    <li class="menu-item"><a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-box"></i>
                            <div data-i18n="Authentications">Estoque</div>
                        </a></li>

                    <?php
                    $tipoLogado = $_SESSION['tipo_empresa'] ?? '';
                    $idLogado = $_SESSION['empresa_id'] ?? '';

                    // Se for matriz (principal), mostrar links para filial, franquia e unidade
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
                        // Se for filial, franquia ou unidade, mostra link para matriz
                    ?>
                        <li class="menu-item">
                            <a href="../matriz/index.php?id=<?= urlencode($idLogado) ?>" class="menu-link">
                                <i class="menu-icon tf-icons bx bx-cog"></i>
                                <div data-i18n="Authentications">Matriz</div>
                            </a>
                        </li>
                    <?php
                    }
                    ?>

                    <li class="menu-item"><a href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-group"></i>
                            <div data-i18n="Authentications">Usuários</div>
                        </a></li>
                    <li class="menu-item"><a href="https://wa.me/92991515710" target="_blank" class="menu-link"><i class="menu-icon tf-icons bx bx-support"></i>
                            <div data-i18n="Basic">Suporte</div>
                        </a></li>
                </ul>
            </aside>
            <!-- /MENU -->

            <!-- CONTEÚDO -->
            <div class="layout-page">
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                            <i class="bx bx-menu bx-sm"></i>
                        </a>
                    </div>

                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <div class="navbar-nav align-items-center">
                            <div class="nav-item d-flex align-items-center">
                                <i class="bx bx-search fs-4 lh-0"></i>
                                <input type="text" class="form-control border-0 shadow-none" placeholder="Search..." aria-label="Search..." />
                            </div>
                        </div>
                    </div>
                </nav>

                <div class="container-xxl flex-grow-1 container-p-y">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="fw-bold mb-0"><span class="text-muted fw-light">Financeiro /</span> Relatório Anual</h4>
                        <div class="input-group input-group-sm w-auto">
                            <select class="form-select">
                                <?php for ($y = (int)date("Y"); $y >= date("Y") - 4; $y--): ?>
                                    <option value="<?= $y ?>" <?= $y == $ano ? "selected" : "" ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                            <button class="btn btn-outline-primary" type="button">
                                <i class="bx bx-filter"></i> Filtrar
                            </button>
                        </div>
                    </div>

                    <!-- CARDS RESUMO -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-2">
                            <div class="card card-slim h-100">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted d-block">Entradas Anuais</small>
                                            <h6 class="mb-0"><?= fmtBR($entradasAno) ?></h6>
                                        </div>
                                        <span class="badge bg-label-success">
                                            <?= number_format($__pct_acima_media_vendas, 1, ',', '.') ?>% acima da média
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mb-2">
                            <div class="card card-slim h-100">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted d-block">Saídas Anuais</small>
                                            <h6 class="mb-0"><?= fmtBR($saidasAno) ?></h6>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mb-2">
                            <div class="card card-slim h-100">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted d-block">Lucro Anual</small>
                                            <h6 class="mb-0"><?= fmtBR($__total_lucro_ano) ?></h6>
                                        </div>
                                        <span class="badge bg-label-success">
                                            <?= ($__crescimento_lucro_yoy_pct >= 0 ? '+' : '') . number_format($__crescimento_lucro_yoy_pct, 1, ',', '.') . '%' ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mb-2">
                            <div class="card card-slim h-100">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted d-block">Média Mensal</small>
                                            <h6 class="mb-0"><?= fmtBR($mediaMensal) ?></h6>
                                        </div>
                                        <span class="badge bg-label-info"><?= htmlspecialchars((string)$ano) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TABELA MENSAL -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center p-3">
                            <h5 class="mb-0">Desempenho Mensal</h5>
                            <div>
                                <button class="btn btn-sm btn-outline-secondary"><i class="bx bx-printer"></i></button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Mês</th>
                                        <th class="text-end">Entradas</th>
                                        <th class="text-end">Saídas</th>
                                        <th class="text-end">Lucro</th>
                                        <th class="text-end">Vendas</th>
                                        <th class="text-end">Ticket Médio</th>
                                        <th class="text-end">% Crescimento</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $temDados = array_sum(array_map(fn($d) => (int)($d['vendas'] ?? 0), $dadosAnuais)) > 0;
                                    if (!$temDados): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">Nenhuma venda encontrada</td>
                                        </tr>
                                    <?php endif; ?>

                                    <?php
                                    $prev = 0.0;
                                    for ($m = 1; $m <= 12; $m++):
                                        $d    = $dadosAnuais[$m];
                                        $cres = ($m == 1 || $prev <= 0) ? 0 : (($d['entradas'] - $prev) / $prev) * 100.0;
                                        $prev = $d['entradas'];
                                    ?>
                                        <tr>
                                            <td><strong><?= nomeMes($m) ?></strong></td>
                                            <td class="text-end"><?= fmtBR($d['entradas']) ?></td>
                                            <td class="text-end"><?= fmtBR($d['saidas']) ?></td>
                                            <td class="text-end <?= ($d['lucro'] >= 0 ? 'text-success' : 'text-danger') ?>"><?= fmtBR($d['lucro']) ?></td>
                                            <td class="text-end"><?= (int)$d['vendas'] ?></td>
                                            <td class="text-end"><?= fmtBR($d['ticket']) ?></td>
                                            <td class="text-end"><?= number_format($cres, 2, ',', '.') ?>%</td>
                                        </tr>
                                    <?php endfor; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th>Média/Total</th>
                                        <th class="text-end"><?= fmtBR($__media_entr_ano) ?></th>
                                        <th class="text-end"><?= fmtBR($__media_said_ano) ?></th>
                                        <th class="text-end <?= ($__media_lucro_ano >= 0 ? 'text-success' : 'text-danger') ?>"><?= fmtBR($__media_lucro_ano) ?></th>
                                        <th class="text-end"><?= (int)$__total_vendas_ano ?></th>
                                        <th class="text-end"><?= fmtBR($__ticket_medio_ano) ?></th>
                                        <th class="text-end <?= ($__crescimento >= 0 ? 'text-success' : 'text-danger') ?>"><?= number_format($__crescimento, 1, ",", "") ?>%</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- DESTAQUES -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-header p-3">
                                    <h5 class="mb-0">Melhores Meses</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="list-group list-group-flush">
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Maior Faturamento</h6>
                                                    <small class="text-muted"><?= nomeMes($__max_entr_mes) ?> - <?= fmtBR($__max_entr) ?></small>
                                                </div>
                                                <span class="badge bg-success">+25% crescimento</span>
                                            </div>
                                        </div>

                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Mês com Mais Vendas</h6>
                                                    <small class="text-muted"><?= nomeMes($__max_vendas_mes) ?> - <?= (int)$__max_vendas ?> vendas</small>
                                                </div>
                                                <span class="badge bg-primary"><?= number_format($__pct_acima_media_vendas, 1, ',', '.') ?>% acima da média</span>
                                            </div>
                                        </div>

                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Melhor Ticket Médio</h6>
                                                    <small class="text-muted"><?= nomeMes($__max_ticket_mes) ?> - <?= fmtBR($__max_ticket) ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-header p-3">
                                    <h5 class="mb-0">Resumo por Trimestre</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="list-group list-group-flush">
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">1° Trimestre</h6>
                                                    <small class="text-muted">Jan-Mar</small>
                                                </div>
                                                <div>
                                                    <span class="fw-semibold"><?= fmtBR($__tri1_receita) ?></span>
                                                    <small class="text-success ms-2">
                                                        <?= ($__tri1_cres_pct >= 0 ? '+' : '') . number_format($__tri1_cres_pct, 1, ',', '.') . '%' ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">2° Trimestre</h6>
                                                    <small class="text-muted">Abr-Jun</small>
                                                </div>
                                                <div>
                                                    <span class="fw-semibold"><?= fmtBR($__tri2_receita) ?></span>
                                                    <small class="text-success ms-2">
                                                        <?= ($__tri2_cres_pct >= 0 ? '+' : '') . number_format($__tri2_cres_pct, 1, ',', '.') . '%' ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Projeção Anual</h6>
                                                    <small class="text-muted">Baseado no 1° semestre</small>
                                                </div>
                                                <div>
                                                    <span class="fw-semibold"><?= fmtBR($__projecao_anual_receita) ?></span>
                                                    <small class="text-warning ms-2">
                                                        <?= ($__projecao_crescimento_pct >= 0 ? '+' : '') . number_format($__projecao_crescimento_pct, 1, ',', '.') . '%' ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div> <!-- card-body -->
                            </div>
                        </div>
                    </div> <!-- /row -->

                </div> <!-- /container -->
            </div> <!-- /layout-page -->
        </div> <!-- /layout-container -->

        <style>
            .card-slim {
                border-radius: .375rem;
                box-shadow: 0 1px 2px 0 rgba(0, 0, 0, .05);
            }

            .card-slim .card-body {
                padding: .75rem;
            }

            .table-sm th,
            .table-sm td {
                padding: .5rem .75rem;
            }
        </style>

        <!-- Core JS -->
        <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
        <script src="../../assets/vendor/libs/popper/popper.js"></script>
        <script src="../../assets/vendor/js/bootstrap.js"></script>
        <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
        <script src="../../assets/vendor/js/menu.js"></script>

        <!-- Vendors JS -->
        <script src="../../assets/vendor/libs/apex-charts/apexcharts.js"></script>

        <!-- Main JS -->
        <script src="../../assets/js/main.js"></script>
        <script src="../../assets/js/dashboards-analytics.js"></script>
        <script async defer src="https://buttons.github.io/buttons.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Localiza a área do "Desempenho Mensal"
    const area = Array.from(document.querySelectorAll('.card')).find(c => {
        const h = c.querySelector('.card-header h5, .card-header h4');
        return h && h.textContent.trim().toLowerCase() === 'desempenho mensal';
    });

    if (area) {
        const btnPrintIcon = area.querySelector('.bx-printer');
        if (btnPrintIcon) {
            const btnPrint = btnPrintIcon.closest('button, a');
            if (btnPrint) {
                btnPrint.addEventListener('click', function(e) {
                    e.preventDefault();

                    // Captura seções
                    const cardsResumo = document.querySelector('.row.mb-4');
                    const tabela = area.querySelector('table');
                    const melhoresMeses = document.querySelector('.col-md-6:nth-of-type(1) .list-group');
                    const resumoTrimestre = document.querySelector('.col-md-6:nth-of-type(2) .list-group');

                    // Verificação mínima
                    if (!cardsResumo && !tabela) {
                        alert('Nenhum dado disponível para impressão.');
                        return;
                    }

                    // Conteúdo principal do relatório
                    const reportHtml = `
                        <div style="text-align:center; margin-bottom:25px;">
                            <h2 style="margin:0;">Relatório Financeiro Anual</h2>
                            <p style="margin:5px 0; font-size:13px; color:#555;">
                                Indicadores e Desempenho • ${new Date().getFullYear()}
                            </p>
                        </div>

                        <div>
                            <h4 style="margin-bottom:10px;">Resumo Geral</h4>
                            ${cardsResumo ? cardsResumo.outerHTML : '<p style="color:#777;">Nenhum dado disponível</p>'}
                        </div>

                        <hr style="margin:25px 0; border:none; border-top:1px solid #ddd;">

                        <div>
                            <h4 style="margin-bottom:10px;">Desempenho Mensal</h4>
                            ${tabela ? tabela.outerHTML : '<p style="color:#777;">Nenhum dado mensal encontrado</p>'}
                        </div>

                        <hr style="margin:25px 0; border:none; border-top:1px solid #ddd;">

                        <div>
                            <h4 style="margin-bottom:10px;">Melhores Meses</h4>
                            ${melhoresMeses ? melhoresMeses.outerHTML : '<p style="color:#777;">Sem destaques disponíveis</p>'}
                        </div>

                        <hr style="margin:25px 0; border:none; border-top:1px solid #ddd;">

                        <div>
                            <h4 style="margin-bottom:10px;">Resumo por Trimestre</h4>
                            ${resumoTrimestre ? resumoTrimestre.outerHTML : '<p style="color:#777;">Sem resumo trimestral disponível</p>'}
                        </div>

                        <footer style="text-align:center; margin-top:40px; font-size:11px; color:#888;">
                            Relatório gerado automaticamente em ${new Date().toLocaleDateString('pt-BR')} às ${new Date().toLocaleTimeString('pt-BR')}
                        </footer>
                    `;

                    // Abre nova aba
                    const win = window.open('', '_blank');
                    if (!win) {
                        alert('Bloqueador de pop-ups impediu a abertura da janela. Permita pop-ups e tente novamente.');
                        return;
                    }

                    // Estilo moderno e limpo
                    const style = `
                        <style>
                            @page { size: A4; margin: 18mm; }
                            body {
                                font-family: 'Public Sans', Arial, sans-serif;
                                color: #111827;
                                font-size: 12px;
                                -webkit-print-color-adjust: exact;
                                background: white;
                            }
                            h2, h4 {
                                font-weight: 600;
                                color: #222;
                            }
                            table {
                                width: 100%;
                                border-collapse: collapse;
                                margin-top: 10px;
                            }
                            th, td {
                                padding: 8px 10px;
                                border: 1px solid #ddd;
                                text-align: left;
                                font-size: 12px;
                            }
                            thead th {
                                background: #f2f3f5;
                                font-weight: 600;
                                text-transform: uppercase;
                                font-size: 11px;
                            }
                            tbody tr:nth-child(even) { background: #fafafa; }
                            tbody tr:hover { background: #f0f4ff; }
                            .list-group {
                                border: 1px solid #ddd;
                                border-radius: 6px;
                                overflow: hidden;
                                margin-top: 10px;
                            }
                            .list-group-item {
                                display: flex;
                                justify-content: space-between;
                                padding: 8px 10px;
                                border-bottom: 1px solid #eee;
                                font-size: 12px;
                            }
                            .list-group-item:last-child {
                                border-bottom: none;
                            }
                            .fw-semibold { font-weight: 600; }
                            .row.mb-4 {
                                display: grid;
                                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                                gap: 12px;
                            }
                            .card {
                                border: 1px solid #ddd;
                                border-radius: 6px;
                                padding: 8px 10px;
                                text-align: center;
                                background: #fafafa;
                            }
                            .card small {
                                display: block;
                                color: #666;
                                font-size: 11px;
                            }
                            .card h6, .card h4 {
                                margin: 4px 0 0;
                                font-size: 14px;
                                font-weight: 600;
                            }
                            hr { border: none; border-top: 1px solid #ddd; margin: 20px 0; }
                        </style>
                    `;

                    // HTML final com script de impressão e retorno
                    const finalHtml = `
                        <!doctype html>
                        <html>
                        <head>
                            <meta charset="utf-8" />
                            <title>Relatório Financeiro Anual</title>
                            <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
                            ${style}
                        </head>
                        <body>
                            ${reportHtml}
                            <script>
                                window.focus();
                                setTimeout(() => window.print(), 300);
                                window.onafterprint = function() {
                                    try {
                                        if (window.opener && !window.opener.closed) {
                                            window.opener.focus();
                                        }
                                    } catch(e){}
                                    window.close();
                                    history.back();
                                };
                            <\/script>
                        </body>
                        </html>
                    `;

                    win.document.open();
                    win.document.write(finalHtml);
                    win.document.close();
                });
            }
        }
    }

    // Filtro de ano (mantém igual)
    const toolbar = document.querySelector('.input-group.input-group-sm.w-auto');
    if (toolbar) {
        const sel = toolbar.querySelector('select.form-select');
        const btn = toolbar.querySelector('button.btn');
        if (sel && btn) {
            btn.addEventListener('click', function() {
                const ano = sel.value || new Date().getFullYear();
                const q = new URLSearchParams(window.location.search);
                q.set('ano', ano);
                window.location.href = window.location.pathname + '?' + q.toString();
            });
        }
    }
});
</script>


    </div> <!-- /layout-wrapper -->
</body>

</html>
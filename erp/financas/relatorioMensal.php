<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

/* ============================================================================
 * INPUT & SESSÃO
 * ============================================================================ */
$idSelecionado = $_GET['id'] ?? '';
if (!$idSelecionado) {
    header("Location: .././login.php");
    exit;
}

// Verifica login
if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id'])
) {
    header("Location: .././login.php?id=" . urlencode($idSelecionado));
    exit;
}

/* ============================================================================
 * DB
 * ============================================================================ */
require '../../assets/php/conexao.php';

/* ============================================================================
 * USUÁRIO LOGADO
 * ============================================================================ */
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
        $tipoUsuario = ucfirst($usuario['nivel']);
    } else {
        echo "<script>alert('Usuário não encontrado.'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar usuário: " . $e->getMessage() . "'); history.back();</script>";
    exit;
}

/* ============================================================================
 * CONTROLE DE ACESSO (empresa/tipo)
 * ============================================================================ */
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
    echo "<script>
          alert('Acesso negado!');
          window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';
        </script>";
    exit;
}

/* ============================================================================
 * LOGO DA EMPRESA
 * ============================================================================ */
try {
    $sql  = "SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_selecionado', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);

    $logoEmpresa = !empty($empresaSobre['imagem'])
        ? "../../assets/img/empresa/" . $empresaSobre['imagem']
        : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png";
}

/* ============================================================================
 * RELATÓRIO MENSAL
 * ============================================================================ */
date_default_timezone_set('America/Manaus');
$empresa_id = $idSelecionado;

$ano = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('n');
if ($mes < 1 || $mes > 12) $mes = (int)date('n');

function fmtBR($v)
{
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}

/* ---------- Dias úteis (do mês) ---------- */
if (!function_exists('dias_uteis_mes')) {
    function dias_uteis_mes(int $ano, int $mes): int
    {
        $inicio = new DateTime(sprintf('%04d-%02d-01', $ano, $mes));
        $fim    = (clone $inicio)->modify('last day of this month');
        $total = 0;
        for ($d = clone $inicio; $d <= $fim; $d->modify('+1 day')) {
            $w = (int)$d->format('N'); // 1..7 (Seg..Dom)
            if ($w <= 5) $total++;
        }
        return $total;
    }
}
if (!function_exists('dias_uteis_decorridos_mes')) {
    function dias_uteis_decorridos_mes(int $ano, int $mes): int
    {
        $hoje   = new DateTime('today');
        $inicio = new DateTime(sprintf('%04d-%02d-01', $ano, $mes));
        $fim    = (clone $inicio)->modify('last day of this month');
        if ($hoje < $inicio) return 0;
        $limite = $hoje > $fim ? $fim : $hoje;
        $total = 0;
        for ($d = clone $inicio; $d <= $limite; $d->modify('+1 day')) {
            $w = (int)$d->format('N');
            if ($w <= 5) $total++;
        }
        return $total;
    }
}

$diasUteisMesTotal   = dias_uteis_mes($ano, $mes);
$diasUteisDecorridos = dias_uteis_decorridos_mes($ano, $mes);
$badgeDiasUteis      = 'Mês ' . str_pad((string)$mes, 2, '0', STR_PAD_LEFT) . '/' . $ano;

/* ---------- Helpers ---------- */
if (!function_exists('dia_semana_ptbr')) {
    function dia_semana_ptbr(?string $dateTime): string
    {
        if (!$dateTime) return '—';
        $w = (int)date('w', strtotime($dateTime)); // 0=Dom,6=Sáb
        $map = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
        return $map[$w] ?? '';
    }
}
if (!function_exists('safe_date_br')) {
    function safe_date_br(?string $dateTime): string
    {
        if (!$dateTime) return '—';
        $ts = strtotime($dateTime);
        return $ts ? date('d/m', $ts) : '—';
    }
}

/* ---------- Totais do mês (Vendas) ---------- */
$st = $pdo->prepare("SELECT COALESCE(SUM(valor_total),0) AS tv, COUNT(*) AS qv
                     FROM vendas WHERE empresa_id=:e AND YEAR(data_venda)=:a AND MONTH(data_venda)=:m");
$st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $mes]);
$r = $st->fetch(PDO::FETCH_ASSOC) ?: ['tv' => 0, 'qv' => 0];
$totalVendasMes = (float)$r['tv'];
$qtdVendasMes   = (int)$r['qv'];

/* ---------- Maior/Menor venda do mês ---------- */
$st = $pdo->prepare("SELECT id, data_venda, valor_total 
                     FROM vendas 
                     WHERE empresa_id=:e AND YEAR(data_venda)=:a AND MONTH(data_venda)=:m AND valor_total>0
                     ORDER BY valor_total DESC LIMIT 1");
$st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $mes]);
$mov_maior    = $st->fetch(PDO::FETCH_ASSOC) ?: null;
$maiorVenda_leg = $mov_maior ? ('#' . (string)$mov_maior['id'] . ' - ' . dia_semana_ptbr($mov_maior['data_venda']) . ' (' . safe_date_br($mov_maior['data_venda']) . ')') : '—';
$maiorVenda_val = $mov_maior ? (float)$mov_maior['valor_total'] : 0.0;

$st = $pdo->prepare("SELECT id, data_venda, valor_total 
                     FROM vendas 
                     WHERE empresa_id=:e AND YEAR(data_venda)=:a AND MONTH(data_venda)=:m AND valor_total>0
                     ORDER BY valor_total ASC LIMIT 1");
$st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $mes]);
$mov_menor       = $st->fetch(PDO::FETCH_ASSOC) ?: null;
$menorVenda_leg  = $mov_menor ? ('#' . (string)$mov_menor['id'] . ' - ' . dia_semana_ptbr($mov_menor['data_venda']) . ' (' . safe_date_br($mov_menor['data_venda']) . ')') : '—';
$menorVenda_val  = $mov_menor ? (float)$mov_menor['valor_total'] : 0.0;

/* ---------- Dia com mais vendas ---------- */
$st = $pdo->prepare("SELECT DATE(data_venda) AS d, COUNT(*) AS qtd, COALESCE(SUM(valor_total),0) AS total
                     FROM vendas 
                     WHERE empresa_id=:e AND YEAR(data_venda)=:a AND MONTH(data_venda)=:m
                     GROUP BY DATE(data_venda) 
                     ORDER BY qtd DESC, total DESC LIMIT 1");
$st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $mes]);
$diaTop     = $st->fetch(PDO::FETCH_ASSOC) ?: null;
$diaTop_leg = $diaTop ? (dia_semana_ptbr($diaTop['d']) . ' (' . safe_date_br($diaTop['d']) . ') - ' . (int)$diaTop['qtd'] . ' vendas') : '—';
$diaTop_val = $diaTop ? (float)$diaTop['total'] : 0.0;

/* ---------- Suprimentos / Sangrias ---------- */
/* Suprimentos: só conta registros com valor > 0 */
$st = $pdo->prepare("
    SELECT 
      SUM(CASE WHEN valor_suprimento > 0 THEN 1 ELSE 0 END) AS qtd, 
      COALESCE(SUM(valor_suprimento),0) AS total 
    FROM suprimentos 
    WHERE empresa_id=:e AND YEAR(data_registro)=:a AND MONTH(data_registro)=:m
");
$st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $mes]);
$supr             = $st->fetch(PDO::FETCH_ASSOC) ?: ['qtd' => 0, 'total' => 0];
$qtdSuprimentos   = (int)$supr['qtd'];
$totalSuprimentos = (float)$supr['total'];

/* Sangrias: usa APENAS o valor realmente retirado do caixa (coluna `valor`) */
$st = $pdo->prepare("
    SELECT 
      SUM(CASE WHEN valor > 0 THEN 1 ELSE 0 END) AS qtd,
      COALESCE(SUM(valor),0) AS total
    FROM sangrias 
    WHERE empresa_id=:e AND YEAR(data_registro)=:a AND MONTH(data_registro)=:m
");
$st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $mes]);
$sang          = $st->fetch(PDO::FETCH_ASSOC) ?: ['qtd' => 0, 'total' => 0];
$qtdSangrias   = (int)$sang['qtd'];
$totalSangrias = (float)$sang['total'];

/* ---------- Itens/Serviços ---------- */
$st = $pdo->prepare("
    SELECT 
      COALESCE(SUM(iv.quantidade),0) AS qtd_itens,
      COALESCE(SUM(iv.quantidade*iv.preco_unitario),0) AS total_itens
    FROM itens_venda iv
    JOIN vendas v ON v.id = iv.venda_id
    WHERE v.empresa_id=:e AND YEAR(v.data_venda)=:a AND MONTH(v.data_venda)=:m
");
$st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $mes]);
$iv            = $st->fetch(PDO::FETCH_ASSOC) ?: ['qtd_itens' => 0, 'total_itens' => 0];
$qtdItensMes   = (int)$iv['qtd_itens'];
$totalItensMes = (float)$iv['total_itens'];

/* ---------- Despesas Fixas (contas pagas) ---------- */
$st = $pdo->prepare("
    SELECT descricao, valorpago, datatransacao 
    FROM contas 
    WHERE id_selecionado=:e AND statuss='pago' 
      AND YEAR(datatransacao)=:a AND MONTH(datatransacao)=:m
    ORDER BY valorpago DESC LIMIT 1
");
$st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $mes]);
$contaMaior = $st->fetch(PDO::FETCH_ASSOC) ?: null;

$maiorDespesa_leg = $contaMaior
    ? (trim((string)$contaMaior['descricao']) . ' - ' . dia_semana_ptbr($contaMaior['datatransacao']) . ' (' . safe_date_br($contaMaior['datatransacao']) . ')')
    : '—';
$maiorDespesa_val = $contaMaior ? (float)$contaMaior['valorpago'] : 0.0;

/* Totais de contas pagas (qtd/valor) */
$st = $pdo->prepare("
    SELECT 
      COUNT(*) AS qtd, 
      COALESCE(SUM(valorpago),0) AS total
    FROM contas
    WHERE id_selecionado=:e AND statuss='pago' 
      AND YEAR(datatransacao)=:a AND MONTH(datatransacao)=:m
");
$st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $mes]);
$dp = $st->fetch(PDO::FETCH_ASSOC);
if (!$dp) {
    $dp = ['qtd' => 0, 'total' => 0];
}
$qtdDespesasPagas   = (int)$dp['qtd'];
$totalDespesasPagas = (float)$dp['total'];

/* ---------- Consolidação (entradas/saídas do caixa) ---------- */
$entradasMes    = $totalVendasMes + $totalSuprimentos;
$saidasMes      = $totalSangrias; // ⬅ Saídas do caixa = Sangrias (não soma contas fixas)
// Se quiser incluir despesas fixas no saldo, troque a linha acima por:
// $saidasMes   = $totalSangrias + $totalDespesasPagas;
$saldoMes       = $entradasMes - $saidasMes;
$ticketMedioMes = $qtdVendasMes > 0 ? $totalVendasMes / $qtdVendasMes : 0;

$mensal = [
    'total_vendas'   => $totalVendasMes,
    'qtd_vendas'     => $qtdVendasMes,
    'entradas'       => $entradasMes,
    'saidas'         => $saidasMes,
    'saldo'          => $saldoMes,
    'ticket_medio'   => $ticketMedioMes,
    'suprimentos'    => $totalSuprimentos,
    'sangrias'       => $totalSangrias,
];

/* ---------- Resumo por semana (no mês) ---------- */
$vendasSem = [];
$st = $pdo->prepare("
    SELECT YEARWEEK(data_venda,3) AS wkey, WEEK(data_venda,3) AS semana,
           MIN(DATE(data_venda)) AS inicio, MAX(DATE(data_venda)) AS fim,
           COUNT(*) AS qtd, COALESCE(SUM(valor_total),0) AS total
    FROM vendas
    WHERE empresa_id=:e AND YEAR(data_venda)=:a AND MONTH(data_venda)=:m
    GROUP BY wkey, semana 
    ORDER BY inicio
");
$st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $mes]);
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $vendasSem[$r['wkey']] = [
        'semana'       => (int)$r['semana'],
        'inicio'       => $r['inicio'],
        'fim'          => $r['fim'],
        'qtd_vendas'   => (int)$r['qtd'],
        'vendas'       => (float)$r['total'],
        'suprimentos'  => 0.0,
        'sangrias'     => 0.0,
    ];
}

/* agrega suprimentos por semana */
$st = $pdo->prepare("
    SELECT YEARWEEK(data_registro,3) AS wkey, COALESCE(SUM(valor_suprimento),0) AS total
    FROM suprimentos 
    WHERE empresa_id=:e AND YEAR(data_registro)=:a AND MONTH(data_registro)=:m
    GROUP BY wkey
");
$st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $mes]);
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $k = $r['wkey'];
    if (!isset($vendasSem[$k])) $vendasSem[$k] = ['semana' => null, 'inicio' => null, 'fim' => null, 'qtd_vendas' => 0, 'vendas' => 0.0, 'suprimentos' => 0.0, 'sangrias' => 0.0];
    $vendasSem[$k]['suprimentos'] = (float)$r['total'];
}

/* agrega sangrias por semana (apenas coluna `valor`) */
$st = $pdo->prepare("
    SELECT YEARWEEK(data_registro,3) AS wkey, COALESCE(SUM(valor),0) AS total
    FROM sangrias
    WHERE empresa_id=:e AND YEAR(data_registro)=:a AND MONTH(data_registro)=:m
    GROUP BY wkey
");
$st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $mes]);
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $k = $r['wkey'];
    if (!isset($vendasSem[$k])) $vendasSem[$k] = ['semana' => null, 'inicio' => null, 'fim' => null, 'qtd_vendas' => 0, 'vendas' => 0.0, 'suprimentos' => 0.0, 'sangrias' => 0.0];
    $vendasSem[$k]['sangrias'] = (float)$r['total'];
}

$resumoSemanal = [];
foreach ($vendasSem as $wk => $info) {
    $entr   = (float)$info['vendas'] + (float)$info['suprimentos'];
    $said   = (float)$info['sangrias']; // + (despesas por semana, se desejar)
    $saldo  = $entr - $said;
    $ticket = $info['qtd_vendas'] > 0 ? $info['vendas'] / $info['qtd_vendas'] : 0;
    if ($info['inicio'] && $info['fim']) {
        $d1 = date('d/m', strtotime($info['inicio']));
        $d2 = date('d/m', strtotime($info['fim']));
        $rotulo = ($info['semana'] ?: '') . " ($d1 - $d2)";
    } else {
        $rotulo = (string)($info['semana'] ?: '—');
    }
    $resumoSemanal[] = [
        'semana_rotulo' => $rotulo,
        'entradas'      => $entr,
        'saidas'        => $said,
        'saldo'         => $saldo,
        'qtd_vendas'    => (int)$info['qtd_vendas'],
        'ticket_medio'  => $ticket,
        'status'        => $saldo >= 0 ? 'Positivo' : 'Negativo',
    ];
}
usort($resumoSemanal, fn($a, $b) => strnatcmp($a['semana_rotulo'], $b['semana_rotulo']));

/* ---------- CSV (download) ---------- */
if (isset($_GET['download']) && $_GET['download'] == '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio-mensal.csv"');
    echo "\xEF\xBB\xBF"; // BOM
    echo "Semana;Entradas;Saídas;Saldo;Vendas;Ticket Médio;Status\n";
    foreach ($resumoSemanal as $s) {
        echo implode(";", [
            $s['semana_rotulo'],
            number_format((float)$s['entradas'], 2, ',', ''),
            number_format((float)$s['saidas'],   2, ',', ''),
            number_format((float)$s['saldo'],    2, ',', ''),
            (int)$s['qtd_vendas'],
            number_format((float)$s['ticket_medio'], 2, ',', ''),
            $s['status'],
        ]) . "\n";
    }
    exit;
}

/* ---------- Rodapé da tabela (médias) ---------- */
$__qtd_semanas = count($resumoSemanal);
$__sum_entr    = array_sum(array_map(fn($x) => (float)($x['entradas'] ?? 0), $resumoSemanal));
$__sum_said    = array_sum(array_map(fn($x) => (float)($x['saidas'] ?? 0), $resumoSemanal));
$__sum_saldo   = array_sum(array_map(fn($x) => (float)($x['saldo'] ?? 0), $resumoSemanal));
$__sum_vendas  = array_sum(array_map(fn($x) => (int)($x['qtd_vendas'] ?? 0), $resumoSemanal));

$__media_entr  = $__qtd_semanas > 0 ? $__sum_entr / $__qtd_semanas : 0.0;
$__media_said  = $__qtd_semanas > 0 ? $__sum_said / $__qtd_semanas : 0.0;
$__media_saldo = $__qtd_semanas > 0 ? $__sum_saldo / $__qtd_semanas : 0.0;
$__ticket_mes  = $mensal['ticket_medio'] ?? 0.0;
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0" />
    <title>ERP - Finanças</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars((string)$logoEmpresa) ?>" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Icons -->
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />

    <!-- Helpers & Config -->
    <script src="../../assets/vendor/js/helpers.js"></script>
    <script src="../../assets/js/config.js"></script>
</head>

<body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">

            <!-- SIDEBAR -->
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
                            <li class="menu-item active"><a href="./relatorioMensal.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Mensal</div>
                                </a></li>
                            <li class="menu-item"><a href="./relatorioAnual.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Anual</div>
                                </a></li>
                        </ul>
                    </li>

                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
                    <li class="menu-item"><a href="../rh/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-group"></i>
                            <div>RH</div>
                        </a></li>
                    <li class="menu-item"><a href="../pdv/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-desktop"></i>
                            <div>PDV</div>
                        </a></li>
                    <li class="menu-item">
                        <a href="../empresa/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div data-i18n="Authentications">Empresa</div>
                        </a>
                    </li>

                    <li class="menu-item"><a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-box"></i>
                            <div>Estoque</div>
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
                    <li class="menu-item"><a href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-group"></i>
                            <div>Usuários </div>
                        </a></li>
                    <li class="menu-item"><a href="https://wa.me/92991515710" target="_blank" class="menu-link"><i class="menu-icon tf-icons bx bx-support"></i>
                            <div>Suporte</div>
                        </a></li>
                </ul>
            </aside>
            <!-- /SIDEBAR -->

            <div class="layout-page">
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                            <i class="bx bx-menu bx-sm"></i>
                        </a>
                    </div>
                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <div class="navbar-nav align-items-center">
                            <div class="nav-item d-flex align-items-center"></div>
                        </div>
                    </div>
                </nav>

                <!-- CONTEÚDO -->
                <div class="container-xxl flex-grow-1 container-p-y">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="fw-bold mb-0"><span class="text-muted fw-light">Financeiro /</span> Relatório Mensal</h4>
                    </div>

                    <!-- RESUMO SLIM -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-2">
                            <div class="card card-slim text-center">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted d-block">Entradas</small>
                                            <h4 class="mb-0"><?= fmtBR($mensal['entradas']) ?></h4>
                                        </div>
                                    
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="card card-slim text-center">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted d-block">Saídas</small>
                                            <h4 class="mb-0"><?= fmtBR($mensal['saidas']) ?></h4>
                                        </div>
                                      
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="card card-slim text-center">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted d-block">Saldo</small>
                                            <h4 class="mb-0"><?= fmtBR($mensal['saldo']) ?></h4>
                                        </div>
                                    
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="card card-slim text-center">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted d-block">Dias Úteis</small>
                                            <h4 class="mb-0"><?= $diasUteisDecorridos ?>/<?= $diasUteisMesTotal ?> dias</h4>
                                        </div>
                                        <span class="badge bg-label-info"><?= htmlspecialchars((string)$badgeDiasUteis) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- DETALHES POR SEMANA -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center p-3">
                            <h5 class="mb-0">Detalhes por Semana</h5>
                            <div>
                                <button class="btn btn-sm btn-outline-secondary" id="btn-print"><i class="bx bx-printer"></i></button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0" id="tb-semanas">
                                <thead class="table-light">
                                    <tr>
                                        <th>Semana</th> 
                                        <th class="text-end">Entradas</th>
                                        <th class="text-end">Saídas</th>
                                        <th class="text-end">Saldo</th>
                                        <th class="text-end">Vendas</th>
                                        <th class="text-end">Ticket Médio</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($resumoSemanal)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">Nenhuma venda encontrada</td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php foreach ($resumoSemanal as $sem): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars((string)$sem['semana_rotulo']) ?></strong></td>
                                            <td class="text-end"><?= fmtBR($sem['entradas']) ?></td>
                                            <td class="text-end"><?= fmtBR($sem['saidas']) ?></td>
                                            <td class="text-end <?= ($sem['saldo'] >= 0 ? 'text-success' : 'text-danger') ?>"><?= fmtBR($sem['saldo']) ?></td>
                                            <td class="text-end"><?= (int)$sem['qtd_vendas'] ?></td>
                                            <td class="text-end"><?= fmtBR($sem['ticket_medio']) ?></td>
                                            <td><?= $sem['status'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th>Média Mensal</th>
                                        <th class="text-end"><?= fmtBR($__media_entr) ?></th>
                                        <th class="text-end"><?= fmtBR($__media_said) ?></th>
                                        <th class="text-end <?= ($__media_saldo >= 0 ? 'text-success' : 'text-danger') ?>"><?= fmtBR($__media_saldo) ?></th>
                                        <th class="text-end"><?= (int)$__sum_vendas ?></th>
                                        <th class="text-end"><?= fmtBR($__ticket_mes) ?></th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- RESUMOS -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 card-flow">
                                <div class="card-header p-3">
                                    <h5 class="mb-0">Movimentações</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="list-group list-group-flush">
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Maior Venda</h6>
                                                    <small class="text-muted"><?= htmlspecialchars((string)$maiorVenda_leg) ?></small>
                                                </div>
                                                <span class="text-success fw-semibold"><?= fmtBR($maiorVenda_val) ?></span>
                                            </div>
                                        </div>
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Menor Venda</h6>
                                                    <small class="text-muted"><?= htmlspecialchars((string)$menorVenda_leg) ?></small>
                                                </div>
                                                <span class="text-info fw-semibold"><?= fmtBR($menorVenda_val) ?></span>
                                            </div>
                                        </div>
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Maior Despesa</h6>
                                                    <small class="text-muted"><?= htmlspecialchars((string)$maiorDespesa_leg) ?></small>
                                                </div>
                                                <span class="text-danger fw-semibold"><?= fmtBR($maiorDespesa_val) ?></span>
                                            </div>
                                        </div>
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Dia com Mais Vendas</h6>
                                                    <small class="text-muted"><?= htmlspecialchars((string)$diaTop_leg) ?></small>
                                                </div>
                                                <span class="text-primary fw-semibold"><?= fmtBR($diaTop_val) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <div class="card h-100 card-flow">
                                <div class="card-header p-3">
                                    <h5 class="mb-0">Resumo por Categoria</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="list-group list-group-flush">
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Vendas</h6>
                                                    <small class="text-muted">Total de transações</small>
                                                </div>
                                                <div>
                                                    <span class="fw-semibold"><?= (int)$qtdVendasMes ?></span>
                                                    <small class="text-muted ms-2">(<?= fmtBR($totalVendasMes) ?>)</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Serviços</h6>
                                                    <small class="text-muted">Itens/serviços vendidos</small>
                                                </div>
                                                <div>
                                                    <span class="fw-semibold"><?= (int)$qtdItensMes ?></span>
                                                    <small class="text-muted ms-2">(<?= fmtBR($totalItensMes) ?>)</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Despesas Fixas</h6>
                                                    <small class="text-muted">Contas pagas no mês</small>
                                                </div>
                                                <div>
                                                    <span class="fw-semibold"><?= (int)$qtdDespesasPagas ?></span>
                                                    <small class="text-muted ms-2">(<?= fmtBR($totalDespesasPagas) ?>)</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Outras Receitas</h6>
                                                    <small class="text-muted">Suprimentos, etc.</small>
                                                </div>
                                                <div>
                                                    <span class="fw-semibold"><?= (int)$qtdSuprimentos ?></span>
                                                    <small class="text-muted ms-2">(<?= fmtBR($totalSuprimentos) ?>)</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div><!-- /row -->
                </div><!-- /container -->
            </div><!-- /layout-page -->

            <style>
                .card-slim {
                    border-radius: .375rem;
                    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, .05);
                    height: 90px !important;
                }

                .card-slim .card-body {
                    padding: .75rem;
                }

                .card-flow {
                    height: auto !important;
                }

                .card-flow .card-body {
                    padding: 0 !important;
                }

                .card-flow .list-group {
                    border: 0;
                    margin-bottom: 0;
                }

                .card-flow .list-group-item {
                    background: transparent;
                    border: 0;
                    padding: .75rem 1rem;
                }

                .card-flow .list-group-item+.list-group-item {
                    border-top: 1px solid rgba(0, 0, 0, .06);
                }

                .card-flow .d-flex>div:last-child {
                    text-align: right;
                }

                .card-flow .fw-semibold {
                    display: inline-block;
                    min-width: 60px;
                    text-align: right;
                }
            </style>

        </div><!-- /layout-container -->
    </div><!-- /layout-wrapper -->

    <!-- Core JS (ordem importa para o sidebar abrir/fechar) -->
    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../assets/vendor/js/menu.js"></script> <!-- ✅ necessário para o sidebar -->
    <!-- Vendors JS -->
    <script src="../../assets/vendor/libs/apex-charts/apexcharts.js"></script>
    <!-- Main JS -->
    <script src="../../assets/js/main.js"></script>

 <script>
document.addEventListener('DOMContentLoaded', function() {
    const btnPrint = document.getElementById('btn-print');
    const table = document.getElementById('tb-semanas');

    if (!btnPrint || !table) return; // segurança

    btnPrint.addEventListener('click', function(e) {
        e.preventDefault();

        // Seleciona corretamente os blocos de resumo
        const cards = document.querySelectorAll('.card-flow .list-group');
        const movs = cards[0] ? cards[0].outerHTML : '<p>Nenhum dado de movimentações</p>';
        const resumoCat = cards[1] ? cards[1].outerHTML : '<p>Nenhum resumo por categoria</p>';

        // Abre nova janela para impressão
        const w = window.open('', '_blank');
        w.document.write(`
            <html>
                <head>
                    <meta charset="utf-8" />
                    <title>Relatório Mensal - Financeiro</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            margin: 30px 50px;
                            color: #333;
                            background: #fff;
                        }
                        header {
                            text-align: center;
                            margin-bottom: 30px;
                        }
                        header h2 {
                            margin: 0;
                            font-size: 22px;
                            font-weight: 700;
                            color: #222;
                        }
                        header p {
                            margin: 0;
                            font-size: 14px;
                            color: #777;
                        }
                        section {
                            margin-bottom: 30px;
                        }
                        h3 {
                            border-bottom: 2px solid #ccc;
                            padding-bottom: 5px;
                            margin-bottom: 10px;
                            color: #444;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-top: 10px;
                            font-size: 14px;
                        }
                        th, td {
                            padding: 8px 10px;
                            border: 1px solid #ccc;
                        }
                        th {
                            background-color: #f9f9f9;
                            font-weight: 600;
                        }
                        td.text-end, th.text-end {
                            text-align: right;
                        }
                        .list-group {
                            border: 1px solid #ddd;
                            border-radius: 5px;
                            overflow: hidden;
                        }
                        .list-group-item {
                            padding: 10px 15px;
                            border-bottom: 1px solid #eee;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                        }
                        .list-group-item:last-child {
                            border-bottom: none;
                        }
                        .list-group-item small {
                            color: #777;
                            display: block;
                        }
                        .list-group-item h6 {
                            margin: 0;
                            font-size: 14px;
                            font-weight: 600;
                        }
                        .fw-semibold {
                            font-weight: bold;
                        }
                        @media print {
                            body {
                                margin: 10mm;
                            }
                            table {
                                page-break-inside: avoid;
                            }
                        }
                    </style>
                </head>
                <body>
                    <header>
                        <h2>Relatório Financeiro Mensal</h2>
                        <p>Detalhes e Resumos</p>
                    </header>

                    <section>
                        <h3>Detalhes por Semana</h3>
                        ${table.outerHTML}
                    </section>

                    <section>
                        <h3>Movimentações</h3>
                        ${movs}
                    </section>

                    <section>
                        <h3>Resumo por Categoria</h3>
                        ${resumoCat}
                    </section>

                    <script>
                        window.focus();
                        setTimeout(() => window.print(), 300);
                        window.onafterprint = function() {
                            window.close();
                            history.back();
                        };
                    <\/script>
                </body>
            </html>
        `);
        w.document.close();
    });
});
</script>


</body>

</html>
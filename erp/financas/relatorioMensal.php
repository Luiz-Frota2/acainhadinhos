<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// ✅ Recupera o identificador vindo da URL
$idSelecionado = $_GET['id'] ?? '';

if (!$idSelecionado) {
    header("Location: .././login.php");
    exit;
}

// ✅ Verifica se a pessoa está logada
if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id'])
) {
    header("Location: .././login.php?id=" . urlencode($idSelecionado));
    exit;
}

// ✅ Conexão com o banco de dados
require '../../assets/php/conexao.php';

// ✅ Buscar nome e tipo do usuário logado
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id = $_SESSION['usuario_id'];

try {
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
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

// ✅ Valida o tipo de empresa e o acesso permitido
$acessoPermitido = false;
$idEmpresaSession = $_SESSION['empresa_id'];
$tipoSession = $_SESSION['tipo_empresa'];

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


// ✅ Buscar imagem da tabela sobre_empresa com base no idSelecionado
try {
    $sql = "SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_selecionado', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);

    $logoEmpresa = !empty($empresaSobre['imagem'])
        ? "../../assets/img/empresa/" . $empresaSobre['imagem']
        : "../../assets/img/favicon/logo.png"; // fallback padrão
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png"; // fallback em caso de erro
}

// ✅ Se chegou até aqui, o acesso está liberado

// ✅ Buscar nome e nível do usuário logado
$nomeUsuario = 'Usuário';
$nivelUsuario = 'Comum'; // Valor padrão
$usuario_id = $_SESSION['usuario_id'];

try {
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $nomeUsuario = $usuario['usuario'];
        $nivelUsuario = $usuario['nivel'];
    }
} catch (PDOException $e) {
    $nomeUsuario = 'Erro ao carregar nome';
    $nivelUsuario = 'Erro ao carregar nível';
}


// ===== BLOCO DINÂMICO: RELATÓRIO MENSAL =====
date_default_timezone_set('America/Manaus');
$empresa_id = $idSelecionado;

$ano = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('n');
if ($mes < 1 || $mes > 12) $mes = (int)date('n');

function fmtBR($v)
{
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}


// ==== DIAS ÚTEIS (MENSAL) ====================================================
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

// Pré-cálculos para o card "Dias Úteis"
$diasUteisMesTotal    = dias_uteis_mes((int)$ano, (int)$mes);
$diasUteisDecorridos  = dias_uteis_decorridos_mes((int)$ano, (int)$mes);
$badgeDiasUteis       = 'Mês ' . str_pad((string)$mes, 2, '0', STR_PAD_LEFT) . '/' . $ano;
// Totais do mês
$st = $pdo->prepare("SELECT COALESCE(SUM(valor_total),0) AS tv, COUNT(*) AS qv
                     FROM vendas WHERE empresa_id=:e AND YEAR(data_venda)=:a AND MONTH(data_venda)=:m");
$st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $mes]);
$r = $st->fetch(PDO::FETCH_ASSOC) ?: ['tv' => 0, 'qv' => 0];
$totalVendasMes = (float)$r['tv'];
$qtdVendasMes   = (int)$r['qv'];

// ==== FUNÇÕES AUXILIARES DINÂMICAS (mensal) ==================================
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

// Maior venda do mês
$st = $pdo->prepare("SELECT id, data_venda, valor_total 
                     FROM vendas 
                     WHERE empresa_id=:e AND YEAR(data_venda)=:a AND MONTH(data_venda)=:m AND valor_total>0
                     ORDER BY valor_total DESC LIMIT 1");
$st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $mes]);
$mov_maior = $st->fetch(PDO::FETCH_ASSOC) ?: null;
$maiVenda_leg = $mov_maior ? ('#' . (string)$mov_maior['id'] . ' - ' . dia_semana_ptbr($mov_maior['data_venda']) . ' (' . safe_date_br($mov_maior['data_venda']) . ')') : '—';
$maiVenda_val = $mov_maior ? (float)$mov_maior['valor_total'] : 0.0;

// Menor venda do mês (desconsiderando zeradas)
$st = $pdo->prepare("SELECT id, data_venda, valor_total 
                     FROM vendas 
                     WHERE empresa_id=:e AND YEAR(data_venda)=:a AND MONTH(data_venda)=:m AND valor_total>0
                     ORDER BY valor_total ASC LIMIT 1");
$st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $mes]);
$mov_menor = $st->fetch(PDO::FETCH_ASSOC) ?: null;
$menorVenda_leg = $mov_menor ? ('#' . (string)$mov_menor['id'] . ' - ' . dia_semana_ptbr($mov_menor['data_venda']) . ' (' . safe_date_br($mov_menor['data_venda']) . ')') : '—';
$menorVenda_val = $mov_menor ? (float)$mov_menor['valor_total'] : 0.0;

// Dia com mais vendas
$st = $pdo->prepare("SELECT DATE(data_venda) AS d, COUNT(*) AS qtd, COALESCE(SUM(valor_total),0) AS total
                     FROM vendas 
                     WHERE empresa_id=:e AND YEAR(data_venda)=:a AND MONTH(data_venda)=:m
                     GROUP BY DATE(data_venda) 
                     ORDER BY qtd DESC, total DESC LIMIT 1");
$st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $mes]);
$diaTop = $st->fetch(PDO::FETCH_ASSOC) ?: null;
$diaTop_leg = $diaTop ? (dia_semana_ptbr($diaTop['d']) . ' (' . safe_date_br($diaTop['d']) . ') - ' . (int)$diaTop['qtd'] . ' vendas') : '—';
$diaTop_val = $diaTop ? (float)$diaTop['total'] : 0.0;

// Maior despesa (contas pagas no mês)
$st = $pdo->prepare("SELECT descricao, valorpago, datatransacao 
                     FROM contas 
                     WHERE id_selecionado=:e AND statuss='pago' AND YEAR(datatransacao)=:a AND MONTH(datatransacao)=:m
                     ORDER BY valorpago DESC LIMIT 1");
$st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $mes]);
$contaMaior = $st->fetch(PDO::FETCH_ASSOC) ?: null;
$maiorDespesa_leg = $contaMaior ? (trim((string)$contaMaior['descricao']) . ' - ' . dia_semana_ptbr($contaMaior['datatransacao']) . ' (' . safe_date_br($contaMaior['datatransacao']) . ')') : '—';
$maiorDespesa_val = $contaMaior ? (float)$contaMaior['valorpago'] : 0.0;

// Suprimentos (outras receitas)
$st = $pdo->prepare("SELECT COUNT(*) AS qtd, COALESCE(SUM(valor_suprimento),0) AS total 
                     FROM suprimentos WHERE empresa_id=:e AND YEAR(data_registro)=:a AND MONTH(data_registro)=:m");
$st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $mes]);
$supr = $st->fetch(PDO::FETCH_ASSOC) ?: ['qtd' => 0, 'total' => 0];
$qtdSuprimentos = (int)$supr['qtd'];
$totalSuprimentos = (float)$supr['total'];

// Sangrias (saídas de caixa)
$st = $pdo->prepare("SELECT COUNT(*) AS qtd, COALESCE(SUM(valor_liquido),0) AS total 
                     FROM sangrias 
                     WHERE empresa_id=:e AND YEAR(data_registro)=:a AND MONTH(data_registro)=:m");
$st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $mes]);
$sang = $st->fetch(PDO::FETCH_ASSOC) ?: ['qtd' => 0, 'total' => 0];
$qtdSangrias = (int)$sang['qtd'];
$totalSangrias = (float)$sang['total'];

// Itens/Serviços vendidos (dos itens_venda)
$st = $pdo->prepare("SELECT COALESCE(SUM(iv.quantidade),0) AS qtd_itens,
                            COALESCE(SUM(iv.quantidade*iv.preco_unitario),0) AS total_itens
                     FROM itens_venda iv
                     JOIN vendas v ON v.id = iv.venda_id
                     WHERE v.empresa_id=:e AND YEAR(v.data_venda)=:a AND MONTH(v.data_venda)=:m");
$st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $mes]);
$iv = $st->fetch(PDO::FETCH_ASSOC) ?: ['qtd_itens' => 0, 'total_itens' => 0];
$qtdItensMes = (int)$iv['qtd_itens'];
$totalItensMes = (float)$iv['total_itens'];

// Despesas pagas (usando 'Despesas Fixas' no layout)
$st = $pdo->prepare("SELECT COUNT(*) AS qtd, COALESCE(SUM(valorpago),0) AS total
                     FROM contas
                     WHERE id_selecionado=:e AND statuss='pago' AND YEAR(datatransacao)=:a AND MONTH(datatransacao)=:m");
$st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $mes]);
$dp = $st->fetch(PDO::FETCH_ASSOC) ?: ['qtd' => 0, 'total' => 0];
$qtdDespesasPagas = (int)$dp['qtd'];
$totalDespesasPagas = (float)$dp['total'];

// Ticket médio semanal (média ponderada == média mensal)
$ticketMedioSemanal = $qtdVendasMes > 0 ? ($totalVendasMes / $qtdVendasMes) : 0.0;
// Número de semanas com vendas no mês (para descrição)
$st = $pdo->prepare("SELECT COUNT(DISTINCT YEARWEEK(data_venda,3)) AS semanas
                     FROM vendas WHERE empresa_id=:e AND YEAR(data_venda)=:a AND MONTH(data_venda)=:m");
$st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $mes]);
$semanasComVendas = (int)($st->fetchColumn() ?: 0);


$st = $pdo->prepare("SELECT COALESCE(SUM(valor_suprimento),0) FROM suprimentos WHERE empresa_id=:e AND YEAR(data_registro)=:a AND MONTH(data_registro)=:m");
$st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $mes]);
$totalSuprMes = (float)($st->fetchColumn() ?: 0);

$st = $pdo->prepare("SELECT COALESCE(SUM(valor_liquido),0) FROM sangrias WHERE empresa_id=:e AND YEAR(data_registro)=:a AND MONTH(data_registro)=:m");
$st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $mes]);
$totalSangMes = (float)($st->fetchColumn() ?: 0);

$entradasMes = $totalVendasMes + $totalSuprMes;
$saidasMes   = $totalSangMes;
$saldoMes    = $entradasMes - $saidasMes;
$ticketMedioMes = $qtdVendasMes > 0 ? $totalVendasMes / $qtdVendasMes : 0;

$mensal = [
    'total_vendas'  => $totalVendasMes,
    'qtd_vendas'    => $qtdVendasMes,
    'entradas'      => $entradasMes,
    'saidas'        => $saidasMes,
    'saldo'         => $saldoMes,
    'ticket_medio'  => $ticketMedioMes,
    'suprimentos'   => $totalSuprMes,
    'sangrias'      => $totalSangMes,
];

// Resumo por semana (no mês)
$vendasSem = [];
$st = $pdo->prepare("SELECT YEARWEEK(data_venda,3) AS wkey, WEEK(data_venda,3) AS semana,
                            MIN(DATE(data_venda)) AS inicio, MAX(DATE(data_venda)) AS fim,
                            COUNT(*) AS qtd, COALESCE(SUM(valor_total),0) AS total
                     FROM vendas
                     WHERE empresa_id=:e AND YEAR(data_venda)=:a AND MONTH(data_venda)=:m
                     GROUP BY wkey, semana ORDER BY inicio");
$st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $mes]);
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $vendasSem[$r['wkey']] = [
        'semana' => (int)$r['semana'],
        'inicio' => $r['inicio'],
        'fim'    => $r['fim'],
        'qtd_vendas' => (int)$r['qtd'],
        'vendas'     => (float)$r['total'],
        'suprimentos' => 0.0,
        'sangrias'   => 0.0,
    ];
}

$st = $pdo->prepare("SELECT YEARWEEK(data_registro,3) AS wkey, COALESCE(SUM(valor_suprimento),0) AS total
                     FROM suprimentos WHERE empresa_id=:e AND YEAR(data_registro)=:a AND MONTH(data_registro)=:m
                     GROUP BY wkey");
$st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $mes]);
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $k = $r['wkey'];
    if (!isset($vendasSem[$k])) $vendasSem[$k] = ['semana' => null, 'inicio' => null, 'fim' => null, 'qtd_vendas' => 0, 'vendas' => 0.0, 'suprimentos' => 0.0, 'sangrias' => 0.0];
    $vendasSem[$k]['suprimentos'] = (float)$r['total'];
}

$st = $pdo->prepare("SELECT YEARWEEK(data_registro,3) AS wkey, COALESCE(SUM(valor_liquido),0) AS total
                     FROM sangrias
                     WHERE empresa_id=:e AND YEAR(data_registro)=:a AND MONTH(data_registro)=:m
                     GROUP BY wkey");
$st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $mes]);
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $k = $r['wkey'];
    if (!isset($vendasSem[$k])) $vendasSem[$k] = ['semana' => null, 'inicio' => null, 'fim' => null, 'qtd_vendas' => 0, 'vendas' => 0.0, 'suprimentos' => 0.0, 'sangrias' => 0.0];
    $vendasSem[$k]['sangrias'] = (float)$r['total'];
}

$resumoSemanal = [];
foreach ($vendasSem as $wk => $info) {
    $entr = (float)$info['vendas'] + (float)$info['suprimentos'];
    $said = (float)$info['sangrias'];
    $saldo = $entr - $said;
    $ticket = $info['qtd_vendas'] > 0 ? $info['vendas'] / $info['qtd_vendas'] : 0;
    $rotulo = '';
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
usort($resumoSemanal, function ($a, $b) {
    return strnatcmp($a['semana_rotulo'], $b['semana_rotulo']);
});

// Endpoint de download CSV (sem alterar layout)
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
?>
<?php
// === Rodapé dinâmico para tabela semanal ===
$__qtd_semanas = count($resumoSemanal);
$__sum_entr = array_sum(array_map(fn($x) => (float)($x['entradas'] ?? 0), $resumoSemanal));
$__sum_said = array_sum(array_map(fn($x) => (float)($x['saidas'] ?? 0), $resumoSemanal));
$__sum_saldo = array_sum(array_map(fn($x) => (float)($x['saldo'] ?? 0), $resumoSemanal));
$__sum_vendas = array_sum(array_map(fn($x) => (int)($x['qtd_vendas'] ?? 0), $resumoSemanal));

$__media_entr = $__qtd_semanas > 0 ? $__sum_entr / $__qtd_semanas : 0.0;
$__media_said = $__qtd_semanas > 0 ? $__sum_said / $__qtd_semanas : 0.0;
$__media_saldo = $__qtd_semanas > 0 ? $__sum_saldo / $__qtd_semanas : 0.0;
$__ticket_mes = $mensal['ticket_medio'] ?? 0.0;
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
    data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>ERP - Finanças</title>

    <meta name="description" content="" />

    <!-- Favicon da empresa carregado dinamicamente -->
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($logoEmpresa) ?>" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
        rel="stylesheet" />

    <!-- Icons. Uncomment required icon fonts -->
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />

    <!-- Page CSS -->

    <!-- Helpers -->
    <script src="../../assets/vendor/js/helpers.js"></script>

    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="../../assets/js/config.js"></script>
</head>

<body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- Menu -->

            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="app-brand-link">

                        <span class="app-brand-text demo menu-text fw-bolder ms-2"
                            style=" text-transform: capitalize;">Açaínhadinhos</span>
                    </a>

                    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
                        <i class="bx bx-chevron-left bx-sm align-middle"></i>
                    </a>
                </div>

                <div class="menu-inner-shadow"></div>

                <ul class="menu-inner py-1">
                    <!-- Dashboard -->
                    <li class="menu-item">
                        <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Dashboard</div>
                        </a>
                    </li>

                    <!-- Finanças -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Finanças</span></li>
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-list-check"></i>
                            <div data-i18n="Authentications">Contas</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item"><a href="./contasAdicionadas.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div>Adicionadas</div>
                                </a></li>
                            <li class="menu-item"><a href="./contasFuturos.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div>Futuras</div>
                                </a></li>
                            <li class="menu-item"><a href="./contasPagas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Pagas</div>
                                </a></li>
                            <li class="menu-item"><a href="./contasPendentes.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div>Pendentes</div>
                                </a></li>
                        </ul>
                    </li>


                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-cart"></i>
                            <div data-i18n="Authentications">Compras</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item"><a href="./controleFornecedores.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div>Fornecedores</div>
                                </a></li>
                            <li class="menu-item"><a href="./gestaoPedidos.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div>Pedidos</div>
                                </a></li>
                        </ul>
                    </li>

                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-dollar"></i>
                            <div data-i18n="Authentications">Relatórios</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item"><a href="./relatorioDiario.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div>Diário</div>
                                </a></li>
                            <li class="menu-item active"><a href="./relatorioMensal.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div>Mensal</div>
                                </a></li>
                            <li class="menu-item"><a href="./relatorioAnual.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div>Anual</div>
                                </a></li>
                        </ul>
                    </li>

                    <!-- Diversos -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>

                    <li class="menu-item">
                        <a href="../rh/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-group"></i>
                            <div data-i18n="Authentications">RH</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../pdv/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-desktop"></i>
                            <div data-i18n="Authentications">PDV</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../delivery/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-cart"></i>
                            <div data-i18n="Authentications">Delivery</div>
                        </a>
                    </li>

                    <li class="menu-item">
                        <a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-box"></i>
                            <div data-i18n="Authentications">Estoque</div>
                        </a>
                    </li>

                    <?php
                    $isFilial = str_starts_with($idSelecionado, 'filial_');
                    $link = $isFilial
                        ? '../matriz/index.php?id=' . urlencode($idSelecionado)
                        : '../filial/index.php?id=principal_1';
                    $titulo = $isFilial ? 'Matriz' : 'Filial';
                    ?>

                    <li class="menu-item">
                        <a href="<?= $link ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-cog"></i>
                            <div data-i18n="Authentications"><?= $titulo ?></div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-group"></i>
                            <div data-i18n="Authentications">Usuários </div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="https://wa.me/92991515710" target="_blank" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-support"></i>
                            <div data-i18n="Basic">Suporte</div>
                        </a>
                    </li>
                </ul>

            </aside>
            <!-- / Menu -->

            <div class="layout-page">
                <nav
                    class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
                    id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                            <i class="bx bx-menu bx-sm"></i>
                        </a>
                    </div>

                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <!-- Search -->
                        <div class="navbar-nav align-items-center">
                            <div class="nav-item d-flex align-items-center">

                            </div>
                        </div>
                    </div>
                </nav>
                <!-- /Search -->
                <div class="container-xxl flex-grow-1 container-p-y">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="fw-bold mb-0"><span class="text-muted fw-light">Financeiro /</span> Relatório Semanal</h4>

                    </div>

                    <!-- RESUMO SEMANAL SLIM -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-2">
                            <div class="card card-slim text-center">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted d-block">Entradas</small>
                                            <h4 class="mb-0"><?= fmtBR($mensal['entradas']) ?></h4>
                                        </div>
                                        <span class="badge bg-label-success">+8%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="card card-slim text-center ">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted d-block">Saídas</small>
                                            <h4 class="mb-0"><?= fmtBR($mensal['saidas']) ?></h4>
                                        </div>
                                        <span class="badge bg-label-danger">+3%</span>
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
                                        <span class="badge bg-label-success">+12%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="card card-slim ">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted d-block">Dias Úteis</small>
                                            <h4 class="mb-0"><?= $diasUteisDecorridos ?>/<?= $diasUteisMesTotal ?> dias</h4>
                                        </div>
                                        <span class="badge bg-label-info"><?= htmlspecialchars($badgeDiasUteis) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- DETALHES POR semana -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center p-3">
                            <h5 class="mb-0">Detalhes por Semana</h5>
                            <div>
                                <button class="btn btn-sm btn-outline-secondary me-2">
                                    <i class="bx bx-download"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary">
                                    <i class="bx bx-printer"></i>
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
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
                                            <td><strong><?= htmlspecialchars($sem['semana_rotulo']) ?></strong></td>
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

                    <!-- RESUMO SEMANAL -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-header p-3">
                                    <h5 class="mb-0">Movimentações</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="list-group list-group-flush">
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Maior Venda</h6>
                                                    <small class="text-muted"><?= htmlspecialchars($maiVenda_leg) ?></small>
                                                </div>
                                                <span class="text-success fw-semibold"><?= fmtBR($maiVenda_val) ?></span>
                                            </div>
                                        </div>
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">menor Venda</h6>
                                                    <small class="text-muted"><?= htmlspecialchars($menorVenda_leg) ?></small>
                                                </div>
                                                <span class="text-info fw-semibold"><?= fmtBR($menorVenda_val) ?></span>
                                            </div>
                                        </div>
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Maior Despesa</h6>
                                                    <small class="text-muted"><?= htmlspecialchars($maiorDespesa_leg) ?></small>
                                                </div>
                                                <span class="text-danger fw-semibold"><?= fmtBR($maiorDespesa_val) ?></span>
                                            </div>
                                        </div>
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Dia com Mais Vendas</h6>
                                                    <small class="text-muted"><?= htmlspecialchars($diaTop_leg) ?></small>
                                                </div>
                                                <span class="text-primary fw-semibold"><?= fmtBR($diaTop_val) ?></span>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-header p-3">
                                    <h5 class="mb-0">Resumo por Categoria</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="list-group list-group-flush">
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Vendas</h6>
                                                    <small class="text-muted"><?= "Total de transações" ?></small>
                                                </div>
                                                <div>
                                                    <span class="fw-semibold"><?= (int)$qtdVendasMes ?></span>
                                                    <small class="text-muted ms-2">(<small class="text-muted ms-2">(<?= fmtBR($totalVendasMes) ?>))</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Serviços</h6>
                                                    <small class="text-muted"><?= "Serviços prestados" ?></small>
                                                </div>
                                                <div>
                                                    <span class="fw-semibold"><?= (int)$qtdItensMes ?></span>
                                                    <small class="text-muted ms-2">(<small class="text-muted ms-2">(<?= fmtBR($totalItensMes) ?>))</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Despesas Fixas</h6>
                                                    <small class="text-muted"><?= "Contas pagas no mês" ?></small>
                                                </div>
                                                <div>
                                                    <span class="fw-semibold"><?= (int)$qtdDespesasPagas ?></span>
                                                    <small class="text-muted ms-2">(<small class="text-muted ms-2">(<?= fmtBR($totalDespesasPagas) ?>))</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Outras Receitas</h6>
                                                    <small class="text-muted"><?= "Suprimentos, etc" ?></small>
                                                </div>
                                                <div>
                                                    <span class="fw-semibold"><?= (int)$qtdSuprimentos ?></span>
                                                    <small class="text-muted ms-2">(<small class="text-muted ms-2">(<?= fmtBR($totalSuprimentos) ?>))</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <style>
                .card-slim {
                    border-radius: 0.375rem;
                    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
                    height: 90px !important;
                }

                .card-slim .card-body {
                    padding: 0.75rem;
                }


                /* === Ajustes pontuais para os cards "Movimentações" e "Resumo por Categoria" ===
                - Remove visual de "caixa dentro de caixa" nos itens (list-group)
                - Evita espaço vazio no final do card (altura se adapta ao conteúdo)
                - Mantém layout global intacto
                */
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
            <!-- build:js assets/vendor/js/core.js -->

            <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
            <script src="../../assets/vendor/libs/popper/popper.js"></script>
            <script src="../../assets/vendor/js/bootstrap.js"></script>
            <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>

            <script src="../../assets/vendor/js/menu.js"></script>
            <!-- endbuild -->

            <!-- Vendors JS -->
            <script src="../../assets/vendor/libs/apex-charts/apexcharts.js"></script>

            <!-- Main JS -->
            <script src="../../assets/js/main.js"></script>

            <!-- Page JS -->
            <script src="../../assets/js/dashboards-analytics.js"></script>

            <!-- Place this tag in your head or just before your close body tag. -->
            <script async defer src="https://buttons.github.io/buttons.js"></script>

            <script>
                function openEditModal() {
                    new bootstrap.Modal(document.getElementById('editContaModal')).show();
                }

                function openDeleteModal() {
                    new bootstrap.Modal(document.getElementById('deleteContaModal')).show();
                }
            </script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Botão de download CSV (primeiro card que tiver ícone bx-download dentro de "Detalhes por Semana")
                    var area = Array.from(document.querySelectorAll('.card')).find(c => {
                        var h = c.querySelector('.card-header h5, .card-header h4');
                        return h && h.textContent.trim().toLowerCase() === 'detalhes por semana';
                    });
                    if (area) {
                        var btnDown = area.querySelector('.bx-download');
                        if (btnDown) {
                            var btn = btnDown.closest('button, a');
                            if (btn) btn.addEventListener('click', function(e) {
                                e.preventDefault();
                                var q = new URLSearchParams(window.location.search);
                                q.set('download', '1');
                                window.location.href = window.location.pathname + '?' + q.toString();
                            });
                        }
                        var btnPrint = area.querySelector('.bx-printer');
                        if (btnPrint) {
                            var btn = btnPrint.closest('button, a');
                            if (btn) btn.addEventListener('click', function(e) {
                                e.preventDefault();
                                // Abre impressão somente da tabela
                                var table = area.querySelector('table');
                                if (!table) {
                                    window.print();
                                    return;
                                }
                                var w = window.open('', '_blank');
                                w.document.write('<html><head><title>Imprimir</title>');
                                w.document.write('<meta charset="utf-8" />');
                                w.document.write('</head><body>');
                                w.document.write('<h3>Detalhes por Semana</h3>');
                                w.document.write(table.outerHTML);
                                w.document.write('</body></html>');
                                w.document.close();
                                w.focus();
                                w.print();
                                w.close();
                            });
                        }
                    }
                });
            </script>

            <script>
                (function() {
                    function ensureHtml2Canvas() {
                        return new Promise(function(res, rej) {
                            if (window.html2canvas) return res(window.html2canvas);
                            var s = document.createElement('script');
                            s.src = "https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js";
                            s.onload = function() {
                                res(window.html2canvas);
                            };
                            s.onerror = function() {
                                rej(new Error('Falha ao carregar html2canvas'));
                            };
                            document.head.appendChild(s);
                        });
                    }
                    document.addEventListener('click', async function(e) {
                        const icon = e.target.closest('.bx-printer');
                        if (!icon) return;
                        const btn = icon.closest('button, a');
                        if (!btn) return;
                        const card = btn.closest('.card');
                        const table = card ? card.querySelector('table') : null;
                        if (!table) return;
                        e.preventDefault();
                        try {
                            await ensureHtml2Canvas();
                            const canvas = await html2canvas(table, {
                                scale: 2,
                                useCORS: true
                            });
                            const dataUrl = canvas.toDataURL('image/png');
                            const w = window.open('', '_blank');
                            w.document.write('<html><head><title>Impressão</title><meta charset="utf-8"></head><body style="margin:0">');
                            w.document.write('<img src="' + dataUrl + '" style="width:100%;display:block"/>');
                            w.document.write('</body></html>');
                            w.document.close();
                            w.focus();
                            w.print();
                        } catch (err) {
                            console.error(err);
                            window.print();
                        }
                    });
                })();
            </script>


            <script>
                (function() {
                    // Garanta que rode após o carregamento do DOM e também caso o JS esteja no rodapé
                    function applyCardFlow() {
                        var headers = document.querySelectorAll('h5.mb-0');
                        headers.forEach(function(h) {
                            var t = (h.textContent || '').trim();
                            if (t === 'Movimentações' || t === 'Resumo por Categoria') {
                                var card = h.closest('.card');
                                if (card && !card.classList.contains('card-flow')) {
                                    card.classList.add('card-flow');
                                }
                            }
                        });
                    }
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', applyCardFlow);
                    } else {
                        applyCardFlow();
                    }
                })();
            </script>

</body>

</html>
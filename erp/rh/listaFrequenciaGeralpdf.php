<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

require_once '../../assets/php/conexao.php';

$idSelecionado = $_GET['id'] ?? '';
if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id'])
) {
    header("Location: ../login.php?id=$idSelecionado");
    exit;
}

// Validação da empresa
if (str_starts_with($idSelecionado, 'principal_')) {
    if ($_SESSION['tipo_empresa'] !== 'principal' || $_SESSION['empresa_id'] != 1) {
        header("Location: ../login.php?id=$idSelecionado");
        exit;
    }
} elseif (str_starts_with($idSelecionado, 'filial_')) {
    $empresa_id = (int) str_replace('filial_', '', $idSelecionado);
    if ($_SESSION['tipo_empresa'] !== 'filial' || $_SESSION['empresa_id'] != $empresa_id) {
        header("Location: ../login.php?id=$idSelecionado");
        exit;
    }
} else {
    header("Location: ../login.php?id=$idSelecionado");
    exit;
}

// Buscar imagem da tabela sobre_empresa
try {
    $sql = "SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1";
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

// Buscar dados do usuário logado
$nomeUsuario = 'Usuário';
$nivelUsuario = 'Comum';
$usuario_id = $_SESSION['usuario_id'];

try {
    $stmt = $pdo->prepare("SELECT usuario, nivel, cpf FROM contas_acesso WHERE id = :id");
    $stmt->bindParam(':id', $usuario_id);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($usuario) {
        $nomeUsuario = $usuario['usuario'];
        $nivelUsuario = $usuario['nivel'];
        $cpfUsuario = $usuario['cpf'];
    }
} catch (PDOException $e) {
    $nomeUsuario = 'Erro ao carregar';
    $nivelUsuario = 'Erro';
}

// Helper functions
function timeToMinutes($time)
{
    if (!$time || $time === '00:00:00' || $time === '--:--') return 0;
    list($h, $m) = explode(':', $time);
    return $h * 60 + $m;
}

function minutesToHM($min)
{
    if ($min <= 0) return '00h 00m';
    $h = floor($min / 60);
    $m = $min % 60;
    return sprintf('%02dh %02dm', $h, $m);
}

function decimalToHM($decimal)
{
    $horas = floor($decimal);
    $minutos = round(($decimal - $horas) * 60);
    return sprintf('%02dh %02dm', $horas, $minutos);
}

function formatarData($dataString)
{
    if (!$dataString) return '--/--/----';
    return date('d/m/Y', strtotime($dataString));
}

function formatarCPF($cpf)
{
    if (empty($cpf)) return '';
    $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpfLimpo) === 11) {
        return substr($cpfLimpo, 0, 3) . '.' . substr($cpfLimpo, 3, 3) . '.' .
            substr($cpfLimpo, 6, 3) . '-' . substr($cpfLimpo, 9, 2);
    }
    return $cpf;
}

function converterHoraParaDecimal($horaString)
{
    if (!$horaString || $horaString === '--:--') return 0;
    list($hours, $minutes) = explode(':', $horaString);
    return $hours + $minutes / 60;
}

function calcularDiferencaMinutos($horaInicial, $horaFinal)
{
    if (!$horaInicial || !$horaFinal || $horaInicial === '--:--' || $horaFinal === '--:--') return 0;
    list($hi, $mi) = explode(':', $horaInicial);
    list($hf, $mf) = explode(':', $horaFinal);
    return ($hf * 60 + $mf) - ($hi * 60 + $mi);
}

function mesPortugues($mes)
{
    $meses = [
        1 => 'Janeiro',
        2 => 'Fevereiro',
        3 => 'Março',
        4 => 'Abril',
        5 => 'Maio',
        6 => 'Junho',
        7 => 'Julho',
        8 => 'Agosto',
        9 => 'Setembro',
        10 => 'Outubro',
        11 => 'Novembro',
        12 => 'Dezembro'
    ];
    return $meses[$mes] ?? '';
}

function calcularHorasTrabalhadas($entrada, $saida_intervalo, $retorno_intervalo, $saida_final)
{
    if (!$entrada || !$saida_final || $entrada === '--:--' || $saida_final === '--:--') return 0;

    $total = 0;

    // Calcula tempo antes do intervalo
    if ($saida_intervalo && $saida_intervalo !== '--:--') {
        $total += timeToMinutes($saida_intervalo) - timeToMinutes($entrada);
    }

    // Calcula tempo após o intervalo
    if ($retorno_intervalo && $retorno_intervalo !== '--:--' && $saida_final && $saida_final !== '--:--') {
        $total += timeToMinutes($saida_final) - timeToMinutes($retorno_intervalo);
    } elseif ($saida_final && $saida_final !== '--:--') {
        $total += timeToMinutes($saida_final) - timeToMinutes($entrada);
    }

    return $total;
}

function calcularAdicionalNoturno($entrada, $saida_intervalo, $retorno_intervalo, $saida_final)
{
    if (!$entrada || !$saida_final || $entrada === '--:--' || $saida_final === '--:--') return 0;

    $totalMinutosNoturnos = 0;
    $inicioNoturno = 22 * 60;    // 22:00 = 1320min
    $fimNoturno = 5 * 60;        // 05:00 = 300min

    $calcularNoturnoPeriodo = function ($inicio, $fim) use ($inicioNoturno, $fimNoturno) {
        $minutosNoturnos = 0;

        if ($fim < $inicio) {
            $fim += 1440;
        }

        // Período noturno (22h às 5h)
        if ($inicio >= $inicioNoturno || $fim <= $fimNoturno + 1440) {
            $inicioNoturnoPeriodo = max($inicio, $inicioNoturno);
            $fimNoturnoPeriodo = min($fim, $fimNoturno + 1440);

            if ($fimNoturnoPeriodo > $inicioNoturnoPeriodo) {
                $minutosNoturnos = $fimNoturnoPeriodo - $inicioNoturnoPeriodo;
            }
        }

        return $minutosNoturnos;
    };

    // Calcula para o período antes do intervalo
    if ($saida_intervalo && $saida_intervalo !== '--:--') {
        $entrada_min = timeToMinutes($entrada);
        $saida_intervalo_min = timeToMinutes($saida_intervalo);
        $totalMinutosNoturnos += $calcularNoturnoPeriodo($entrada_min, $saida_intervalo_min);
    }

    // Calcula para o período após o intervalo
    if ($retorno_intervalo && $retorno_intervalo !== '--:--') {
        $retorno_intervalo_min = timeToMinutes($retorno_intervalo);
        $saida_final_min = timeToMinutes($saida_final);
        $totalMinutosNoturnos += $calcularNoturnoPeriodo($retorno_intervalo_min, $saida_final_min);
    } else {
        $entrada_min = timeToMinutes($entrada);
        $saida_final_min = timeToMinutes($saida_final);
        $totalMinutosNoturnos += $calcularNoturnoPeriodo($entrada_min, $saida_final_min);
    }

    return $totalMinutosNoturnos;
}

function calcularHorasExtras($horasTrabalhadas, $cargaHorariaDiaria)
{
    $cargaHorariaMinutos = timeToMinutes($cargaHorariaDiaria);
    if ($cargaHorariaMinutos <= 0) return 0;

    $diferenca = $horasTrabalhadas - $cargaHorariaMinutos;
    return $diferenca > 0 ? $diferenca : 0;
}

function calcularCargaHorariaDia($funcionario, $ponto)
{
    if (!$ponto['entrada'] || !$ponto['saida_final'] || $ponto['entrada'] === '--:--' || $ponto['saida_final'] === '--:--') {
        return '--:--';
    }

    $minutos = timeToMinutes($ponto['saida_final']) - timeToMinutes($ponto['entrada']);

    if (
        $ponto['saida_intervalo'] && $ponto['retorno_intervalo'] &&
        $ponto['saida_intervalo'] !== '--:--' && $ponto['retorno_intervalo'] !== '--:--'
    ) {
        $minutos -= (timeToMinutes($ponto['retorno_intervalo']) - timeToMinutes($ponto['saida_intervalo']));
    }

    return minutesToHM($minutos);
}

function verificarFolga($cpf, $data, $pdo)
{
    $stmt = $pdo->prepare("SELECT * FROM folgas 
                          WHERE REPLACE(REPLACE(cpf, '.', ''), '-', '') = REPLACE(REPLACE(?, '.', ''), '-', '')
                          AND data_folga = ?");
    $stmt->execute([$cpf, $data]);
    return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
}

try {
    $mes = $_GET['mes'] ?? date('m');
    $ano = $_GET['ano'] ?? date('Y');

    if (!is_numeric($mes) || $mes < 1 || $mes > 12) {
        die('Mês inválido (deve ser entre 1 e 12)');
    }

    if (!is_numeric($ano) || strlen($ano) != 4) {
        die('Ano inválido');
    }

    $nomeMes = mesPortugues($mes);

    // Buscar todos os funcionários da empresa
    $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE empresa_id = ?");
    $stmt->execute([$idSelecionado]);
    $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($funcionarios)) {
        echo "<script>alert('Nenhum funcionário encontrado para esta empresa'); history.back();</script>";
        exit;
    }

    // Processar cada funcionário
    $relatoriosFuncionarios = [];
    foreach ($funcionarios as $funcionario) {
        // Buscar pontos do funcionário no mês/ano
        $stmt = $pdo->prepare("SELECT * FROM pontos 
                             WHERE REPLACE(REPLACE(cpf, '.', ''), '-', '') = REPLACE(REPLACE(?, '.', ''), '-', '')
                             AND MONTH(data) = ? 
                             AND YEAR(data) = ?
                             ORDER BY data");
        $stmt->execute([$funcionario['cpf'], $mes, $ano]);
        $pontos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Buscar todas as folgas do funcionário no mês
        $stmtFolgas = $pdo->prepare("SELECT data_folga FROM folgas 
                                    WHERE REPLACE(REPLACE(cpf, '.', ''), '-', '') = REPLACE(REPLACE(?, '.', ''), '-', '')
                                    AND MONTH(data_folga) = ? 
                                    AND YEAR(data_folga) = ?");
        $stmtFolgas->execute([$funcionario['cpf'], $mes, $ano]);
        $folgas = $stmtFolgas->fetchAll(PDO::FETCH_COLUMN);

        // Criar array com todos os dias do mês
        $diasNoMes = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
        $todosOsDias = [];
        for ($dia = 1; $dia <= $diasNoMes; $dia++) {
            $data = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
            $todosOsDias[$data] = null;
        }

        // Preencher com os pontos registrados
        foreach ($pontos as $ponto) {
            $todosOsDias[$ponto['data']] = $ponto;
        }

        // Preencher com as folgas
        foreach ($folgas as $folga) {
            if (array_key_exists($folga, $todosOsDias)) {
                $todosOsDias[$folga] = ['folga' => true, 'data' => $folga];
            }
        }

        $estatisticas = [
            'totalDias' => $diasNoMes,
            'diasTrabalhados' => 0,
            'horasTrabalhadas' => 0,
            'horasExtras' => 0,
            'horasPendentes' => 0,
            'atrasos' => 0,
            'saidasAntecipadas' => 0,
            'horasDevidas' => 0,
            'horasExcedentes' => 0,
            'adicionalNoturno' => 0,
            'mediaDiaria' => 0,
            'diasFolga' => count($folgas)
        ];

        $cargaHorariaDiaria = $funcionario['carga_horaria_diaria'] ?? '08:00'; // Padrão de 8 horas

        foreach ($todosOsDias as $data => $ponto) {
            if ($ponto === null) {
                continue; // Dia sem registro
            }

            if (isset($ponto['folga'])) {
                continue; // Pula dias de folga
            }

            if ($ponto['entrada'] && $ponto['saida_final'] && $ponto['entrada'] !== '--:--' && $ponto['saida_final'] !== '--:--') {
                $estatisticas['diasTrabalhados']++;

                $horasTrabalhadas = calcularHorasTrabalhadas(
                    $ponto['entrada'],
                    $ponto['saida_intervalo'],
                    $ponto['retorno_intervalo'],
                    $ponto['saida_final']
                );

                $estatisticas['horasTrabalhadas'] += $horasTrabalhadas / 60;

                // Calcula adicional noturno
                $minutosNoturnos = calcularAdicionalNoturno(
                    $ponto['entrada'],
                    $ponto['saida_intervalo'],
                    $ponto['retorno_intervalo'],
                    $ponto['saida_final']
                );
                $estatisticas['adicionalNoturno'] += $minutosNoturnos;

                // Calcula horas extras
                $horasExtras = calcularHorasExtras($horasTrabalhadas, $cargaHorariaDiaria);
                $estatisticas['horasExtras'] += $horasExtras / 60;
                $estatisticas['horasExcedentes'] += $horasExtras / 60;

                // Calcula horas devidas (quando trabalhou menos que a carga horária)
                if ($horasTrabalhadas < timeToMinutes($cargaHorariaDiaria)) {
                    $estatisticas['horasDevidas'] += (timeToMinutes($cargaHorariaDiaria) - $horasTrabalhadas) / 60;
                }

                // Verifica atraso apenas se não houver horas extras ou adicional noturno
                if ($horasExtras <= 0 && $minutosNoturnos <= 0) {
                    $entradaEsperada = $funcionario['entrada'];
                    $entradaRegistrada = $ponto['entrada'];

                    if ($entradaEsperada && $entradaRegistrada && $entradaEsperada !== '--:--' && $entradaRegistrada !== '--:--') {
                        $diffEntrada = calcularDiferencaMinutos($entradaEsperada, $entradaRegistrada);
                        if ($diffEntrada > 10) { // Mais de 10 minutos de atraso
                            $estatisticas['atrasos']++;
                        }
                    }
                }

                // Verifica saída antecipada apenas se não houver horas extras ou adicional noturno
                if ($horasExtras <= 0 && $minutosNoturnos <= 0) {
                    $saidaEsperada = $funcionario['saida_final'];
                    $saidaRegistrada = $ponto['saida_final'];

                    if ($saidaEsperada && $saidaRegistrada && $saidaEsperada !== '--:--' && $saidaRegistrada !== '--:--') {
                        $diffSaida = calcularDiferencaMinutos($saidaRegistrada, $saidaEsperada);
                        if ($diffSaida > 0) { // Saída antecipada
                            $estatisticas['saidasAntecipadas']++;
                        }
                    }
                }
            }

            if ($ponto['horas_pendentes'] && $ponto['horas_pendentes'] !== '--:--') {
                $estatisticas['horasPendentes'] += converterHoraParaDecimal($ponto['horas_pendentes']);
                $estatisticas['horasDevidas'] += converterHoraParaDecimal($ponto['horas_pendentes']);
            }
        }

        if ($estatisticas['diasTrabalhados'] > 0) {
            $estatisticas['mediaDiaria'] = $estatisticas['horasTrabalhadas'] / $estatisticas['diasTrabalhados'];
        }

        // Buscar CNPJ da empresa
        try {
            $sqlCnpj = "SELECT cnpj FROM endereco_empresa WHERE empresa_id = :id_selecionado LIMIT 1";
            $stmtCnpj = $pdo->prepare($sqlCnpj);
            $stmtCnpj->bindParam(':id_selecionado', $idSelecionado, PDO::PARAM_STR);
            $stmtCnpj->execute();
            $resultadoCnpj = $stmtCnpj->fetch(PDO::FETCH_ASSOC);

            $cnpjEmpresa = $resultadoCnpj['cnpj'] ?? 'CNPJ não cadastrado';
        } catch (PDOException $e) {
            $cnpjEmpresa = '';
        }

        $relatoriosFuncionarios[] = [
            'funcionario' => $funcionario,
            'pontos' => $todosOsDias,
            'estatisticas' => $estatisticas,
            'cnpjEmpresa' => $cnpjEmpresa,
            'cargaHorariaDiaria' => $cargaHorariaDiaria
        ];
    }
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
    data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>ERP - Recursos Humanos</title>
    <meta name="description" content="" />
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($logoEmpresa) ?>" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
        rel="stylesheet" />

    <!-- Icons -->
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />
    <link href="https://cdn.jsdelivr.net/npm/boxicons/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

    <!-- Helpers -->
    <script src="../../assets/vendor/js/helpers.js"></script>
    <script src="../../assets/js/config.js"></script>
</head>

<style>
    @page {
        size: A4;

        @bottom-center {
            content: counter(page);
            font-size: 10px;
        }
    }

    .card-body p {
        margin: -3px !important;
    }

    .report-body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        margin: 0;
        padding: 0;
        color: #333;
        font-size: 12px;
    }

    .report-container {
        padding: 20px;
    }

    .report-title {
        text-align: center;
        font-size: 16px;
        margin-bottom: 20px;
        color: #2c3e50;
        border-bottom: 2px solid #3498db;
        padding-bottom: 8px;
    }

    .header-info {
        margin-bottom: 25px;
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        border-left: 4px solid #3498db;
    }

    .header-info p {
        margin: 4px 0;
        display: flex;
    }

    .header-info strong {
        width: 130px;
        display: inline-block;
    }

    .report-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
        page-break-inside: avoid;
    }

    .report-table th,
    .report-table td {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: left;
        font-size: 11px;
    }

    .report-table th {
        background-color: #3498db;
        color: white;
        font-weight: 600;
    }

    .report-table tr:nth-child(even) {
        background-color: #f8f9fa;
    }

    .summary {
        display: flex;
        justify-content: space-between;
        margin-top: 30px;
        page-break-inside: avoid;
    }

    .summary-table {
        width: 48%;
    }

    .page-break {
        page-break-after: always;
    }

    .legend {
        margin-top: 20px;
        font-size: 11px;
        background-color: #f8f9fa;
        padding: 10px;
        border-radius: 5px;
    }

    .signatures {
        display: flex;
        justify-content: space-between;
        margin-top: 30px;
        page-break-inside: avoid;

    }

    .signature-box {
        width: 30%;
        border-top: 1px solid #000;
        text-align: center;
        padding-top: 5px;
        font-size: 11px;
    }

    .report-footer {
        text-align: center;
        margin-top: 10px;
        font-size: 10px;
        color: #7f8c8d;
    }

    sup {
        color: #e74c3c;
        font-weight: bold;
    }

    .no-data {
        color: #95a5a6;
        font-style: italic;
    }

    .print-button {
        margin-bottom: 20px;
        max-width: 200px !important;
    }

    .btn-group-responsive {
        display: flex;
        justify-content: space-between;
        gap: 0;
    }

    .btn-group-responsive .btn {
        width: 48%;
    }

    .card-header {
        background-color: #f0f0f0 !important;
        color: #000 !important;
        padding: 5px !important;
        font-weight: bold;
        border-bottom: 1px solid #ddd !important;
        box-shadow: none !important;
    }

    /* Responsividade para telas menores */
    @media (max-width: 992px) {
        .card {
            margin-bottom: 15px;
        }

        .report-table {
            font-size: 10px;
        }

        .report-table th,
        .report-table td {
            padding: 5px;
        }

        .signatures {
            margin-top: 20px;
        }
    }

    @media (max-width: 768px) {
        .report-title {
            font-size: 14px;
        }

        .card-body p {
            font-size: 12px;
        }

        .signature-box {
            width: 80%;
            margin-bottom: 20px;
        }

        .btn-group-responsive {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .btn-group-responsive .btn {
            width: 100%;
        }
    }

    @media (max-width: 576px) {
        .report-container {
            padding: 10px;
        }

        .report-table {
            display: block;
            overflow-x: auto;
            white-space: nowrap;
        }

        .card-header {
            font-size: 14px;
        }

        .card-body p {
            font-size: 11px;
        }

        .print-button {
            width: 100% !important;
        }
    }

    /* Estilos para impressão */
    @media print {

        /* Estilos gerais para impressão */
        body,
        .report-body {
            margin-top: -25px !important;
            padding: 0;
            font-size: 10px;
            color: #000;
            background: none;
        }

        /* Ocultar elementos não necessários */
        .layout-navbar,
        .layout-menu,
        .menu-inner,
        .app-brand,
        .navbar-nav-right,
        .print-button,
        .menu-header,
        .menu-item,
        .menu-link,
        .fw-bold {
            display: none !important;
        }

        /* Reset de espaçamentos */
        .layout-page,
        .container-xxl,
        .container-p-y,
        .card,
        .card-body {
            padding: 0 !important;
            margin: 0 !important;
            border: none !important;
            box-shadow: none !important;

        }

        /* Container principal */
        .report-container {
            padding: 0.5cm !important;
            margin: 0 !important;
            width: 100% !important;
        }

        /* Título do relatório */
        .report-title {
            font-size: 14px !important;
            margin-bottom: 10px !important;
            padding-bottom: 5px !important;
            text-align: center;
            border-bottom: 1px solid #000;
        }

        /* Estilos para os cards na impressão */
        .row {
            display: flex !important;
            flex-wrap: wrap !important;
            margin-bottom: 15px !important;
        }

        .col-md-4 {
            width: 33.33% !important;
            padding: 0 5px !important;
        }

        .card {
            page-break-inside: avoid;
            border: 1px solid #ddd !important;
            margin-bottom: 10px !important;
        }

        .card-header {
            background-color: #f0f0f0 !important;
            color: #000 !important;
            padding: 5px !important;
            font-weight: bold;
            border-bottom: 1px solid #ddd !important;
        }

        .card-body {
            padding: 5px !important;
        }

        .bdy {
            border: none !important;
        }

        .card-text {
            margin: 3px 0 !important;
            font-size: 9px !important;
        }

        .card-text strong {
            font-weight: bold;
        }

        .text-muted {
            color: #666 !important;
        }

        /* Tabela */
        .report-table {
            width: 100% !important;
            border-collapse: collapse;
            page-break-inside: auto;
            margin-top: 10px !important;
        }

        .report-table th,
        .report-table td {
            padding: 4px !important;
            font-size: 9px !important;
            line-height: 1.1;
            border: 1px solid #ddd !important;
        }

        .report-table th {
            background-color: #f0f0f0 !important;
            color: #000 !important;
            padding: 5px !important;
        }

        /* Elementos adicionais */
        .signatures {
            margin-top: 48px !important;
            font-size: 9px !important;
            gap: 20px !important;
            margin-bottom: 30px !important;
        }

        .signature-box {
            width: 100% !important;
            font-size: 9px !important;
        }

        .report-footer {
            font-size: 8px !important;
            margin-top: 10px !important;
        }

        /* Configuração da página */
        @page {
            size: A4;
            margin: 0cm 0cm 0cm 0cm;
        }
    }
</style>

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

                    <a href="javascript:void(0);"
                        class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
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

                    <!-- Recursos Humanos (RH) -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Recursos
                            Humanos</span></li>

                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-buildings"></i>
                            <div data-i18n="Authentications">Setores</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./setoresAdicionados.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div data-i18n="Basic">Adicionados</div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-user-plus"></i>
                            <div data-i18n="Authentications">Funcionários</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./funcionarioAdicionados.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div data-i18n="Basic">Adicionados </div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- Menu Sistema de Ponto -->
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-time"></i>
                            <div data-i18n="Sistema de Ponto">Sistema de Ponto</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./escalaAdicionadas.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div data-i18n="Escalas e Configuração"> Escalas Adicionadas</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./adicionarPonto.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Registro de Ponto Eletrônico">Adicionar Ponto</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./ajustePonto.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Registro de Ponto Eletrônico">Ajuste de Ponto</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./atestadosFuncionarios.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div data-i18n="Basic">Atestados</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- Menu Relatórios -->
                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-trending-up"></i>
                            <div data-i18n="Relatórios">Relatórios</div>
                        </a>

                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./relatorio.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Visualização Geral">Visualização Geral</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./bancoHoras.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Ajuste de Horários e Banco de Horas">Banco de Horas</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./frequencia.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Ajuste de Horários e Banco de Horas">Frequência</div>
                                </a>
                            </li>
                            <li class="menu-item active">
                                <a href="#" class="menu-link">
                                    <div data-i18n="Ajuste de Horários e Banco de Horas">Frequência Geral</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- Misc -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
                    <li class="menu-item">
                        <a href="../financas/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-dollar"></i>
                            <div data-i18n="Authentications">Finanças</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../pdv/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
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
                    <li class="menu-item">
                        <a href="../clientes/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-user"></i>
                            <div data-i18n="Authentications">Clientes</div>
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
                        <a href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
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

            <!-- Layout container -->
            <div class="layout-page">
                <!-- Navbar -->
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
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
                                <i class="bx bx-search fs-4 lh-0"></i>
                                <input type="text" id="searchInput" class="form-control border-0 shadow-none"
                                    placeholder="Pesquisar funcionário..." aria-label="Pesquisar..." />
                            </div>
                        </div>
                        <!-- /Search -->

                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <!-- User -->
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);"
                                    data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <img src="<?= htmlspecialchars($logoEmpresa) ?>" alt
                                            class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="<?= htmlspecialchars($logoEmpresa) ?>" alt
                                                            class="w-px-40 h-auto rounded-circle" />
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <span class="fw-semibold d-block"><?php echo $nomeUsuario; ?></span>
                                                    <small class="text-muted"><?php echo $nivelUsuario; ?></small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                    <li>
                                        <div class="dropdown-divider"></div>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <i class="bx bx-user me-2"></i>
                                            <span class="align-middle">Minha Conta</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <i class="bx bx-cog me-2"></i>
                                            <span class="align-middle">Configurações</span>
                                        </a>
                                    </li>
                                    <li>
                                        <div class="dropdown-divider"></div>
                                    </li>
                                    <li>
                                        <a class="dropdown-item"
                                            href="../logout.php?id=<?= urlencode($idSelecionado); ?>">
                                            <i class="bx bx-power-off me-2"></i>
                                            <span class="align-middle">Sair</span>
                                        </a>
                                    </li>
                                </ul>
                            </li>
                            <!--/ User -->
                        </ul>

                    </div>
                </nav>
                <!-- / Navbar -->



                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a href="#">Sistema de Ponto</a>/</span>Frequência Geral</h4>
                    <h5 class="fw-bold mt-3 mb-3 custor-font"><span class="text-muted fw-light">Visualize as Frequências dos Funcionários</span></h5>

                    <div class="card mt-3">
                        <div class="card-body bdy">
                            <div class="btn-group-responsive d-flex flex-wrap gap-2 mb-3">
                                <button onclick="window.print()" class="btn btn-primary print-button flex-grow-1">
                                    <i class="bx bx-printer me-1"></i> Imprimir Relatório
                                </button>
                                <button type="button" class="btn btn-primary color-blue print-button flex-grow-1" onclick="enviarPorEmail()">
                                    <i class="bx bx-mail-send me-1"></i> Enviar por E-mail
                                </button>
                            </div>

                            <div class="report-body">
                                <?php foreach ($relatoriosFuncionarios as $relatorio):
                                    $funcionario = $relatorio['funcionario'];
                                    $pontos = $relatorio['pontos'];
                                    $estatisticas = $relatorio['estatisticas'];
                                    $cnpjEmpresa = $relatorio['cnpjEmpresa'];
                                    $cargaHorariaDiaria = $relatorio['cargaHorariaDiaria'];
                                ?>
                                    <div class="report-container">
                                        <h1 class="report-title">RELATÓRIO ESPELHO PONTO - <?= strtoupper($nomeMes) ?>/<?= $ano ?></h1>

                                        <div class="row mb-4">
                                            <div class="col-md-4 mb-3">
                                                <div class="card h-100">
                                                    <div class="card-header"><i class="bx bx-user me-2"></i> Dados Pessoais</div>
                                                    <div class="card-body">
                                                        <p><strong>Nome:</strong> <?= htmlspecialchars($funcionario['nome']) ?></p>
                                                        <p><strong>CPF:</strong> <?= formatarCPF($funcionario['cpf']) ?></p>
                                                        <p><strong>Empresa:</strong> N R DOS SANTOS ACAINHA.</p>
                                                        <p><strong>Matricula:</strong> <?= htmlspecialchars($funcionario['matricula'] ?? 'Não informado') ?></p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-4 mb-3">
                                                <div class="card h-100">
                                                    <div class="card-header"><i class="bx bx-building me-2"></i> Dados Empresariais</div>
                                                    <div class="card-body">
                                                        <p><strong>PIS:</strong> <?= htmlspecialchars($funcionario['pis'] ?? 'Não informado') ?></p>
                                                        <p><strong>CNPJ:</strong> <?= htmlspecialchars($cnpjEmpresa) ?></p>
                                                        <p><strong>Departamento:</strong> <?= htmlspecialchars($funcionario['setor']) ?></p>
                                                        <p><strong>Cargo:</strong> <?= htmlspecialchars($funcionario['cargo'] ?? 'Não informado') ?></p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-4 mb-3">
                                                <div class="card h-100">
                                                    <div class="card-header"><i class="bx bx-time me-2"></i> Informações de Trabalho</div>
                                                    <div class="card-body">
                                                        <p><strong>Horário:</strong> DIARIA</p>
                                                        <p><strong>Carga Horária Diária:</strong> <?= $cargaHorariaDiaria ?></p>
                                                        <?php
                                                        $dataAdmissao = !empty($funcionario['data_admissao']) ? formatarData($funcionario['data_admissao']) : '--/--/----';
                                                        ?>
                                                        <p><strong>Data Admissão:</strong> <?= $dataAdmissao ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="table-responsive">
                                            <table class="report-table">
                                                <thead>
                                                    <tr>
                                                        <th>Data</th>
                                                        <th>Entrada</th>
                                                        <th>Saída Int.</th>
                                                        <th>Entrada Int.</th>
                                                        <th>Saída</th>
                                                        <th>Horas Trab.</th>
                                                        <th>Carga Horária</th>
                                                        <th>Horas Extras</th>
                                                        <th>Ad. Noturno</th>
                                                        <th>Ocorrências</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $primeiroDiaMes = date('Y-m-01', strtotime("$ano-$mes-01"));
                                                    $ultimoDiaMes = date('Y-m-t', strtotime("$ano-$mes-01"));

                                                    $dataAtual = $primeiroDiaMes;
                                                    while ($dataAtual <= $ultimoDiaMes):
                                                        $ponto = $pontos[$dataAtual] ?? null;
                                                        $folga = isset($ponto['folga']) ? $ponto['folga'] : verificarFolga($funcionario['cpf'], $dataAtual, $pdo);

                                                        if ($folga):
                                                    ?>
                                                            <tr>
                                                                <td><?= formatarData($dataAtual) ?></td>
                                                                <td>--:--</td>
                                                                <td>--:--</td>
                                                                <td>--:--</td>
                                                                <td>--:--</td>
                                                                <td>--:--</td>
                                                                <td>--:--</td>
                                                                <td>--:--</td>
                                                                <td>--:--</td>
                                                                <td>Folga</td>
                                                            </tr>
                                                        <?php
                                                            $dataAtual = date('Y-m-d', strtotime($dataAtual . ' +1 day'));
                                                            continue;
                                                        endif;

                                                        if ($ponto && !isset($ponto['folga'])):
                                                            $ocorrencias = [];
                                                            $normal = false;

                                                            // Verifica se o dia está completo
                                                            if (!$ponto['entrada'] || !$ponto['saida_final'] || $ponto['entrada'] === '--:--' || $ponto['saida_final'] === '--:--') {
                                                                $ocorrencias[] = 'Dia Incompleto';
                                                            } else {
                                                                // Calcula horas trabalhadas
                                                                $horasTrabalhadas = calcularHorasTrabalhadas(
                                                                    $ponto['entrada'],
                                                                    $ponto['saida_intervalo'],
                                                                    $ponto['retorno_intervalo'],
                                                                    $ponto['saida_final']
                                                                );

                                                                // Calcula adicional noturno
                                                                $minutosNoturnos = calcularAdicionalNoturno(
                                                                    $ponto['entrada'],
                                                                    $ponto['saida_intervalo'],
                                                                    $ponto['retorno_intervalo'],
                                                                    $ponto['saida_final']
                                                                );

                                                                // Calcula horas extras
                                                                $horasExtras = calcularHorasExtras($horasTrabalhadas, $cargaHorariaDiaria);

                                                                // Verifica se é um dia normal (horas trabalhadas = carga horária)
                                                                if ($horasTrabalhadas == timeToMinutes($cargaHorariaDiaria)) {
                                                                    $normal = true;
                                                                }

                                                                // Adiciona adicional noturno se houver, independente de ser dia normal
                                                                if ($minutosNoturnos > 0) {
                                                                    $ocorrencias[] = 'Ad. Noturno';
                                                                }

                                                                // Se não for dia normal, verifica outras ocorrências
                                                                if (!$normal) {
                                                                    // Adiciona horas extras se houver
                                                                    if ($horasExtras > 0) {
                                                                        $ocorrencias[] = 'Hora Extra';
                                                                    }

                                                                    // Verifica atraso apenas se não houver horas extras ou adicional noturno
                                                                    if ($horasExtras <= 0 && $minutosNoturnos <= 0) {
                                                                        if ($funcionario['entrada'] && $funcionario['entrada'] !== '--:--') {
                                                                            $diffEntrada = calcularDiferencaMinutos($funcionario['entrada'], $ponto['entrada']);
                                                                            if ($diffEntrada > 10) {
                                                                                $ocorrencias[] = 'Atraso';
                                                                            }
                                                                        }
                                                                    }

                                                                    // Verifica saída antecipada apenas se não houver horas extras ou adicional noturno
                                                                    if ($horasExtras <= 0 && $minutosNoturnos <= 0) {
                                                                        if ($funcionario['saida_final'] && $funcionario['saida_final'] !== '--:--') {
                                                                            $diffSaida = calcularDiferencaMinutos($ponto['saida_final'], $funcionario['saida_final']);
                                                                            if ($diffSaida > 0) {
                                                                                $ocorrencias[] = 'Saída Antecip.';
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                        ?>
                                                            <tr>
                                                                <td><?= formatarData($ponto['data']) ?></td>
                                                                <td><?= $ponto['entrada'] ? date('H:i', strtotime($ponto['entrada'])) : '--:--' ?></td>
                                                                <td><?= $ponto['saida_intervalo'] ? date('H:i', strtotime($ponto['saida_intervalo'])) : '--:--' ?></td>
                                                                <td><?= $ponto['retorno_intervalo'] ? date('H:i', strtotime($ponto['retorno_intervalo'])) : '--:--' ?></td>
                                                                <td><?= $ponto['saida_final'] ? date('H:i', strtotime($ponto['saida_final'])) : '--:--' ?></td>
                                                                <td><?= $horasTrabalhadas > 0 ? minutesToHM($horasTrabalhadas) : '--:--' ?></td>
                                                                <td><?= $cargaHorariaDiaria ?></td>
                                                                <td><?= $horasExtras > 0 ? minutesToHM($horasExtras) : '--:--' ?></td>
                                                                <td><?= $minutosNoturnos > 0 ? minutesToHM($minutosNoturnos) : '--:--' ?></td>
                                                                <td>
                                                                    <?php
                                                                    if ($normal && $minutosNoturnos > 0) {
                                                                        echo 'Normal, Ad. Noturno';
                                                                    } elseif ($normal) {
                                                                        echo 'Normal';
                                                                    } else {
                                                                        echo empty($ocorrencias) ? '--' : implode(', ', $ocorrencias);
                                                                    }
                                                                    ?>
                                                                </td>
                                                            </tr>
                                                        <?php else: ?>
                                                            <tr>
                                                                <td><?= formatarData($dataAtual) ?></td>
                                                                <td>--:--</td>
                                                                <td>--:--</td>
                                                                <td>--:--</td>
                                                                <td>--:--</td>
                                                                <td>--:--</td>
                                                                <td>--:--</td>
                                                                <td>--:--</td>
                                                                <td>--:--</td>
                                                                <td>Sem Registro</td>
                                                            </tr>
                                                        <?php endif; ?>
                                                    <?php
                                                        $dataAtual = date('Y-m-d', strtotime($dataAtual . ' +1 day'));
                                                    endwhile;
                                                    ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="row mb-4">
                                            <div class="col-md-4 mb-3">
                                                <div class="card h-100">
                                                    <div class="card-header"><i class="bx bx-time me-2"></i> Carga Horária</div>
                                                    <div class="card-body">
                                                        <p><strong>Total Dias:</strong> <?= $estatisticas['totalDias'] ?></p>
                                                        <p><strong>Dias Trabalhados:</strong> <?= $estatisticas['diasTrabalhados'] ?></p>
                                                        <p><strong>Dias de Folga:</strong> <?= $estatisticas['diasFolga'] ?></p>
                                                        <p><strong>Horas Trabalhadas:</strong> <?= decimalToHM($estatisticas['horasTrabalhadas']) ?></p>
                                                        <p><strong>Média Diária:</strong> <?= decimalToHM($estatisticas['mediaDiaria']) ?></p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-4 mb-3">
                                                <div class="card h-100">
                                                    <div class="card-header"><i class="bx bx-minus-circle me-2"></i> Débito</div>
                                                    <div class="card-body">
                                                        <p><strong>Horas Devidas:</strong> <?= decimalToHM($estatisticas['horasDevidas']) ?></p>
                                                        <p><strong>Atrasos:</strong> <?= $estatisticas['atrasos'] ?></p>
                                                        <p><strong>Saídas Antecip.:</strong> <?= $estatisticas['saidasAntecipadas'] ?></p>
                                                        <p><strong>Dias Incompletos:</strong> <?= $estatisticas['totalDias'] - $estatisticas['diasTrabalhados'] - $estatisticas['diasFolga'] ?></p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-4 mb-3">
                                                <div class="card h-100">
                                                    <div class="card-header"><i class="bx bx-plus-circle me-2"></i> Crédito</div>
                                                    <div class="card-body">
                                                        <p><strong>Horas Extras:</strong> <?= decimalToHM($estatisticas['horasExtras']) ?></p>
                                                        <p><strong>Horas Excedentes:</strong> <?= decimalToHM($estatisticas['horasExcedentes']) ?></p>
                                                        <p><strong>Adicional Noturno:</strong> <?= minutesToHM($estatisticas['adicionalNoturno']) ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="signatures">
                                            <div class="signature-box">Empregador</div>
                                            <div class="signature-box">Responsável</div>
                                            <div class="signature-box">Empregado(a)</div>
                                        </div>

                                        <div class="page-break"></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="../../js/saudacao.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>

    <script src="../../assets/vendor/js/menu.js"></script>
    <!-- endbuild -->

    <!-- Vendors JS -->
    <script src="../../assets/vendor/libs/apex-charts/apexcharts.js"></script>

    <!-- Main JS -->
    <script src="../../assets/js/main.js"></script>


    <!-- Place this tag in your head or just before your close body tag. -->
    <script async defer src="https://buttons.github.io/buttons.js"></script>

    <script>
        // Adiciona a data atual no rodapé
        document.getElementById('current-date').textContent = new Date().toLocaleDateString('pt-BR');

        // Configuração para impressão em PDF
        document.title = "Relatório Espelho Ponto - Naara Kaliane";

        function enviarPorEmail() {
            alert('Funcionalidade de envio por e-mail em desenvolvimento.');
            // Aqui você pode abrir um modal ou redirecionar para um endpoint PHP para envio real
        }
    </script>
</body>

</html>
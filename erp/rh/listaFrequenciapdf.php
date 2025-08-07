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

// Buscar imagem da empresa
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

// Buscar dados do usuário
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

// Helpers
function timeToMinutes($time)
{
    if (!$time || $time === '00:00:00')
        return 0;
    list($h, $m, $s) = explode(':', $time);
    return $h * 60 + $m + round($s / 60);
}

function minutesToHM($min)
{
    $h = floor($min / 60);
    $m = $min % 60;
    return sprintf('%dh %02dm', $h, $m);
}

function limparCPF($cpf)
{
    return preg_replace('/[^0-9]/', '', $cpf);
}

function formatarData($dataString)
{
    if (!$dataString)
        return '--/--/----';
    return date('d/m/Y', strtotime($dataString));
}

function formatarCPF($cpf)
{
    if (empty($cpf))
        return '';
    $cpfLimpo = limparCPF($cpf);
    if (strlen($cpfLimpo) === 11) {
        return substr($cpfLimpo, 0, 3) . '.' . substr($cpfLimpo, 3, 3) . '.' .
            substr($cpfLimpo, 6, 3) . '-' . substr($cpfLimpo, 9, 2);
    }
    return $cpf;
}

function formatarTelefone($telefone)
{
    if (!$telefone)
        return '';
    if (strlen($telefone) === 11) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7);
    }
    return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6);
}

function capitalize($str)
{
    if (!$str)
        return '';
    return ucfirst($str);
}

function converterHoraParaDecimal($horaString)
{
    if (empty($horaString)) {
        return 0;
    }
    $parts = explode(':', $horaString);
    $hours = (int)$parts[0];
    $minutes = isset($parts[1]) ? (int)$parts[1] : 0;
    return $hours + ($minutes / 60);
}

function formatarHoraDecimal($decimal)
{
    $horas = floor($decimal);
    $minutos = round(($decimal - $horas) * 60);
    return sprintf("%dh %02dm", $horas, $minutos);
}

function calcularDiferencaMinutos($horaInicial, $horaFinal)
{
    if (!$horaInicial || !$horaFinal)
        return 0;

    // Converter para timestamp
    $hi = strtotime($horaInicial);
    $hf = strtotime($horaFinal);

    // Se a saída for no dia seguinte (após meia-noite)
    if ($hf < $hi) {
        $hf += 86400; // Adiciona 24 horas em segundos
    }

    return ($hf - $hi) / 60;
}

function calcularHorasTrabalhadas($entrada, $saidaIntervalo, $retornoIntervalo, $saidaFinal)
{
    if (!$entrada || !$saidaFinal)
        return 0;

    $totalMinutos = 0;

    if ($saidaIntervalo) {
        $totalMinutos += calcularDiferencaMinutos($entrada, $saidaIntervalo);
    }

    if ($retornoIntervalo) {
        $totalMinutos += calcularDiferencaMinutos($retornoIntervalo, $saidaFinal);
    } else {
        $totalMinutos = calcularDiferencaMinutos($entrada, $saidaFinal);
    }

    return $totalMinutos / 60;
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

function calcularHorasExtras($entrada, $saida, $saidaIntervalo = null, $retornoIntervalo = null)
{
    if (!$entrada || !$saida) return 0;

    $totalMinutos = calcularDiferencaMinutos($entrada, $saida);

    if ($saidaIntervalo && $retornoIntervalo) {
        $totalMinutos -= calcularDiferencaMinutos($saidaIntervalo, $retornoIntervalo);
    }

    if ($totalMinutos <= 480) {
        return 0;
    }

    return ($totalMinutos - 480) / 60;
}

function calcularHorasNoturnas($entrada, $saida, $saidaIntervalo = null, $retornoIntervalo = null)
{
    if (!$entrada || !$saida) return 0;

    $entradaTs = strtotime($entrada);
    $saidaTs = strtotime($saida);

    if ($saidaTs < $entradaTs) {
        $saidaTs += 86400;
    }

    $inicioNoturno = strtotime('22:00:00');
    $fimNoturno = strtotime('05:00:00') + 86400;

    $inicioTrabalhado = max($entradaTs, $inicioNoturno);
    $fimTrabalhado = min($saidaTs, $fimNoturno);

    if ($inicioTrabalhado >= $fimTrabalhado) {
        return 0;
    }

    $segundosNoturnos = $fimTrabalhado - $inicioTrabalhado;
    $horasNoturnas = ($segundosNoturnos / 3600) * (60 / 60.0);

    return round($horasNoturnas, 2);
}

// Função corrigida para calcular a carga horária real
function calcularCargaHorariaDia($registro, $funcionario)
{
    if (!$registro['entrada'] || !$registro['saida_final']) {
        return 0;
    }

    $entrada = strtotime($registro['entrada']);
    $saida_final = strtotime($registro['saida_final']);

    if ($saida_final < $entrada) {
        $saida_final += 86400; // Saída no dia seguinte
    }

    $totalMinutos = ($saida_final - $entrada) / 60;

    if (!empty($registro['saida_intervalo']) && !empty($registro['retorno_intervalo'])) {
        $saida_intervalo = strtotime($registro['saida_intervalo']);
        $retorno_intervalo = strtotime($registro['retorno_intervalo']);

        if ($retorno_intervalo < $saida_intervalo) {
            $retorno_intervalo += 86400;
        }

        $intervaloMinutos = ($retorno_intervalo - $saida_intervalo) / 60;
        $totalMinutos -= $intervaloMinutos;
    }

    return round($totalMinutos);
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

// Buscar dados do relatório
try {
    $cpf = $_GET['cpf'] ?? die('CPF não fornecido');
    $cpfLimpo = limparCPF($cpf);

    if (strlen($cpfLimpo) != 11)
        die('CPF inválido');

    $mes = $_GET['mes'] ?? die('Mês não fornecido');
    $ano = $_GET['ano'] ?? date('Y');

    if (!is_numeric($mes) || $mes < 1 || $mes > 12)
        die('Mês inválido');
    if (!is_numeric($ano) || strlen($ano) != 4)
        die('Ano inválido');

    // Buscar funcionário
    $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE REPLACE(REPLACE(cpf, '.', ''), '-', '') = ?");
    $stmt->execute([$cpfLimpo]);
    $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$funcionario)
        die('Funcionário não encontrado');

    // Buscar setor
    $stmt = $pdo->prepare("SELECT * FROM setores WHERE nome = ?");
    $stmt->execute([$funcionario['setor']]);
    $setor = $stmt->fetch(PDO::FETCH_ASSOC);

    // Buscar pontos
    $stmt = $pdo->prepare("SELECT * FROM pontos 
                          WHERE REPLACE(REPLACE(cpf, '.', ''), '-', '') = ? 
                          AND MONTH(data) = ? 
                          AND YEAR(data) = ?
                          ORDER BY data ASC");
    $stmt->execute([$cpfLimpo, $mes, $ano]);
    $pontos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar folgas do funcionário no mês
    $stmt = $pdo->prepare("SELECT * FROM folgas 
                          WHERE REPLACE(REPLACE(cpf, '.', ''), '-', '') = ? 
                          AND MONTH(data_folga) = ? 
                          AND YEAR(data_folga) = ?
                          ORDER BY data_folga ASC");
    $stmt->execute([$cpfLimpo, $mes, $ano]);
    $folgas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Criar array com todos os dias do mês
    $numeroDias = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
    $registros = [];

    for ($dia = 1; $dia <= $numeroDias; $dia++) {
        $data = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
        $registros[$data] = [
            'tipo' => 'sem_registro',
            'data' => $data
        ];
    }

    // Adicionar pontos
    foreach ($pontos as $ponto) {
        $registros[$ponto['data']] = [
            'tipo' => 'ponto',
            'data' => $ponto['data'],
            'entrada' => $ponto['entrada'],
            'saida_intervalo' => $ponto['saida_intervalo'],
            'retorno_intervalo' => $ponto['retorno_intervalo'],
            'saida_final' => $ponto['saida_final'],
            'hora_extra' => $ponto['hora_extra'],
            'horas_pendentes' => $ponto['horas_pendentes']
        ];
    }

    // Adicionar folgas
    foreach ($folgas as $folga) {
        $registros[$folga['data_folga']] = [
            'tipo' => 'folga',
            'data' => $folga['data_folga']
        ];
    }

    // Ordenar por data
    ksort($registros);

    // Calcular estatísticas
    $estatisticas = [
        'totalDias' => count($registros),
        'diasTrabalhados' => 0,
        'diasFolga' => count($folgas),
        'horasTrabalhadas' => 0,
        'horasExtras' => 0,
        'horasPendentes' => 0,
        'atrasos' => 0,
        'saidasAntecipadas' => 0,
        'horasDevidas' => 0,
        'horasExcedentes' => 0,
        'horasNoturnas' => 0
    ];

    // Calcular horas noturnas e extras para cada dia
    foreach ($registros as &$registro) {
        if ($registro['tipo'] === 'ponto' && $registro['entrada'] && $registro['saida_final']) {
            $registro['horas_noturnas'] = calcularHorasNoturnas(
                $registro['entrada'],
                $registro['saida_final'],
                $registro['saida_intervalo'],
                $registro['retorno_intervalo']
            );

            $registro['hora_extra'] = formatarHoraDecimal(
                calcularHorasExtras(
                    $registro['entrada'],
                    $registro['saida_final'],
                    $registro['saida_intervalo'],
                    $registro['retorno_intervalo']
                )
            );

            $estatisticas['horasNoturnas'] += $registro['horas_noturnas'];
            $estatisticas['horasExtras'] += converterHoraParaDecimal($registro['hora_extra']);
        } else {
            $registro['horas_noturnas'] = 0;
        }
    }
    unset($registro);

    foreach ($registros as $registro) {
        if ($registro['tipo'] === 'ponto') {
            if ($registro['entrada'] && $registro['saida_final']) {
                $estatisticas['diasTrabalhados']++;

                $horasDia = calcularHorasTrabalhadas(
                    $registro['entrada'],
                    $registro['saida_intervalo'],
                    $registro['retorno_intervalo'],
                    $registro['saida_final']
                );
                $estatisticas['horasTrabalhadas'] += $horasDia;

                // Verificar tolerância de 10 minutos na entrada
                $temAtraso = false;
                if ($funcionario['entrada']) {
                    $entradaEsperada = strtotime($funcionario['entrada']);
                    $entradaRegistrada = strtotime($registro['entrada']);
                    $diferencaMinutos = ($entradaRegistrada - $entradaEsperada) / 60;

                    if ($diferencaMinutos > 10) {
                        $temAtraso = true;
                        $estatisticas['atrasos']++;
                        $estatisticas['horasDevidas'] += $diferencaMinutos / 60;
                    }
                }

                // Verificar saída antecipada
                $temSaidaAntecipada = false;
                if ($funcionario['saida_final']) {
                    $saidaEsperada = strtotime($funcionario['saida_final']);
                    $saidaRegistrada = strtotime($registro['saida_final']);
                    $diferencaMinutos = ($saidaEsperada - $saidaRegistrada) / 60;

                    if ($diferencaMinutos > 0) {
                        $temSaidaAntecipada = true;
                        $estatisticas['saidasAntecipadas']++;
                        $estatisticas['horasDevidas'] += $diferencaMinutos / 60;
                    }
                }
            }

            if (!empty($registro['hora_extra'])) {
                $valorExtra = converterHoraParaDecimal($registro['hora_extra']);
                $estatisticas['horasExtras'] += $valorExtra;
                $estatisticas['horasExcedentes'] += $valorExtra;
            }

            if (!empty($registro['horas_pendentes'])) {
                $valorPendente = converterHoraParaDecimal($registro['horas_pendentes']);
                $estatisticas['horasPendentes'] += $valorPendente;
                $estatisticas['horasDevidas'] += $valorPendente;
            }
        }
    }

    if ($estatisticas['diasTrabalhados'] > 0) {
        $estatisticas['mediaDiaria'] = $estatisticas['horasTrabalhadas'] / $estatisticas['diasTrabalhados'];
    } else {
        $estatisticas['mediaDiaria'] = 0;
    }

    $nomeMes = mesPortugues($mes);
} catch (PDOException $e) {
    die("Erro ao gerar relatório: " . $e->getMessage());
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

    <!-- Icons. Uncomment required icon fonts -->
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

    <!-- Page CSS -->
    <style>
        @page {
            size: A4;

            @bottom-center {
                content: counter(page);
                font-size: 10px;
            }
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
            margin: 2px 0;
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

        .card-body p {
            margin: -3px !important;

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
                margin-top: 46px !important;
                font-size: 9px !important;
                gap: 20px !important;
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
    <!-- /Page CSS -->

    <!-- Helpers -->
    <script src="../../assets/vendor/js/helpers.js"></script>

    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="../../assets/js/config.js"></script>

</head>

<body>

    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">

        <!-- layout-container -->
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
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Recursos Humanos</span>
                    </li>

                    <li class="menu-item  ">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-buildings"></i>
                            <div data-i18n="Authentications">Setores</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item ">
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
                            <li class="menu-item ">
                                <a href="./ajusteFolga.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Registro de Ponto Eletrônico">Ajuste de folga</div>
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
                            <li class="menu-item ">
                                <a href="./frequencia.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Ajuste de Horários e Banco de Horas">Frequência</div>
                                </a>
                            </li>
                            <li class="menu-item active ">
                                <a href="#"
                                    class="menu-link">
                                    <div data-i18n="Ajuste de Horários e Banco de Horas">Frequência Individual</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./frequenciaGeral.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Ajuste de Horários e Banco de Horas">Frequência Geral</div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <!-- Misc -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
                    <li class="menu-item">
                        <a href="../financas/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-dollar"></i>
                            <div data-i18n="Authentications">Finanças</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../pdv/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-desktop"></i>
                            <div data-i18n="Authentications">PDV</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../delivery/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-cart"></i>
                            <div data-i18n="Authentications">Delivery</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-box"></i>
                            <div data-i18n="Authentications">Estoque</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../clientes/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
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
                    <!--/MISC-->
                </ul>
            </aside>
            <!-- / Menu -->

            <!-- layout-page -->
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
                    <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a href="#">Sistema de Ponto</a>/</span>Frequência Individual</h4>
                    <h5 class="fw-bold mt-3 mb-3 custor-font"><span class="text-muted fw-light">Visualize a Frequência do Funcionário</span></h5>

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

                            <div id="tabela-frequencia" class="report-body">
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
                                                    <p><strong>Data Admissão:</strong>
                                                        <?php
                                                        $dataAdmissao = $funcionario['data_admissao'];
                                                        echo ($dataAdmissao && $dataAdmissao !== '0000-00-00') ? formatarData($dataAdmissao) : '';
                                                        ?>
                                                    </p>
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
                                                    <th>Trab</th>
                                                    <th>Carga Horária</th>
                                                    <th>Horas Noturnas</th>
                                                    <th>Horas Extras</th>
                                                    <th>Ocorrências</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($registros as $registro):
                                                    // Determinar ocorrências
                                                    $ocorrencias = [];
                                                    $horasExtrasDia = 0;
                                                    $horasNoturnasDia = 0;

                                                    if ($registro['tipo'] === 'folga') {
                                                        $ocorrencias[] = 'Folga';
                                                    } elseif ($registro['tipo'] === 'sem_registro') {
                                                        $ocorrencias[] = 'Sem Registro';
                                                    } elseif ($registro['entrada'] && $registro['saida_final']) {
                                                        // Calcular horas trabalhadas e carga horária
                                                        $horasTrabalhadas = calcularHorasTrabalhadas(
                                                            $registro['entrada'],
                                                            $registro['saida_intervalo'],
                                                            $registro['retorno_intervalo'],
                                                            $registro['saida_final']
                                                        );
                                                        $cargaHorariaMinutos = calcularCargaHorariaDia($registro, $funcionario);
                                                        $horasTrabalhadasMinutos = $horasTrabalhadas * 60;

                                                        // Verificar adicional noturno
                                                        $horasNoturnasDia = $registro['horas_noturnas'];
                                                        $temAdicionalNoturno = $horasNoturnasDia > 0;

                                                        // Verificar horas extras
                                                        $horasExtrasDia = converterHoraParaDecimal($registro['hora_extra']);
                                                        $temHorasExtras = $horasExtrasDia > 0;

                                                        // Verificar atraso (com tolerância de 10 minutos)
                                                        $temAtraso = false;
                                                        if ($funcionario['entrada']) {
                                                            $entradaEsperada = strtotime($funcionario['entrada']);
                                                            $entradaRegistrada = strtotime($registro['entrada']);
                                                            $diferencaMinutos = ($entradaRegistrada - $entradaEsperada) / 60;

                                                            if ($diferencaMinutos > 10) {
                                                                $temAtraso = true;
                                                            }
                                                        }

                                                        // Verificar saída antecipada
                                                        $temSaidaAntecipada = false;
                                                        if ($funcionario['saida_final']) {
                                                            $saidaEsperada = strtotime($funcionario['saida_final']);
                                                            $saidaRegistrada = strtotime($registro['saida_final']);
                                                            $diferencaMinutos = ($saidaEsperada - $saidaRegistrada) / 60;

                                                            if ($diferencaMinutos > 0) {
                                                                $temSaidaAntecipada = true;
                                                            }
                                                        }

                                                        // Se horas trabalhadas for igual ou maior que carga horária, considera "Normal"
                                                        if ($horasTrabalhadasMinutos >= $cargaHorariaMinutos) {
                                                            $ocorrencias[] = 'Normal';
                                                        } else {
                                                            // Verificar atraso
                                                            if ($temAtraso) {
                                                                $ocorrencias[] = 'Atraso';
                                                            }

                                                            // Verificar saída antecipada APENAS se as horas trabalhadas forem MENORES que a carga horária
                                                            if ($temSaidaAntecipada && $horasTrabalhadasMinutos < $cargaHorariaMinutos && !$temAdicionalNoturno && !$temHorasExtras) {
                                                                $ocorrencias[] = 'Saída Antecip.';
                                                            }
                                                        }

                                                        // Adicionar adicional noturno se houver
                                                        if ($temAdicionalNoturno) {
                                                            $ocorrencias[] = 'Adicional Noturno';
                                                        }

                                                        // Adicionar horas extras se houver
                                                        if ($temHorasExtras) {
                                                            $ocorrencias[] = 'Horas Extras';
                                                        }
                                                    } else {
                                                        $ocorrencias[] = 'Dia Incompleto';
                                                    }
                                                ?>
                                                    <tr>
                                                        <td><?= formatarData($registro['data']) ?></td>
                                                        <td><?= ($registro['tipo'] === 'folga' || $registro['tipo'] === 'sem_registro') ? '--:--' : ($registro['entrada'] ? date('H:i', strtotime($registro['entrada'])) : '--:--') ?></td>
                                                        <td><?= ($registro['tipo'] === 'folga' || $registro['tipo'] === 'sem_registro') ? '--:--' : ($registro['saida_intervalo'] ? date('H:i', strtotime($registro['saida_intervalo'])) : '--:--') ?></td>
                                                        <td><?= ($registro['tipo'] === 'folga' || $registro['tipo'] === 'sem_registro') ? '--:--' : ($registro['retorno_intervalo'] ? date('H:i', strtotime($registro['retorno_intervalo'])) : '--:--') ?></td>
                                                        <td><?= ($registro['tipo'] === 'folga' || $registro['tipo'] === 'sem_registro') ? '--:--' : ($registro['saida_final'] ? date('H:i', strtotime($registro['saida_final'])) : '--:--') ?></td>
                                                        <td>
                                                            <?php
                                                            if ($registro['tipo'] === 'folga' || $registro['tipo'] === 'sem_registro') {
                                                                echo '0h 00m';
                                                            } elseif ($registro['entrada'] && $registro['saida_final']) {
                                                                $horasDia = calcularHorasTrabalhadas(
                                                                    $registro['entrada'],
                                                                    $registro['saida_intervalo'],
                                                                    $registro['retorno_intervalo'],
                                                                    $registro['saida_final']
                                                                );
                                                                echo formatarHoraDecimal($horasDia);
                                                            } else {
                                                                echo '0h 00m';
                                                            }
                                                            ?>
                                                        </td>
                                                        <td><?= ($registro['tipo'] === 'folga' || $registro['tipo'] === 'sem_registro') ? '--:--' : minutesToHM(calcularCargaHorariaDia($registro, $funcionario)) ?></td>
                                                        <td><?= ($registro['tipo'] === 'folga' || $registro['tipo'] === 'sem_registro') ? '0h 00m' : ($registro['horas_noturnas'] > 0 ? formatarHoraDecimal($registro['horas_noturnas']) : '0h 00m') ?></td>
                                                        <td><?= ($registro['tipo'] === 'folga' || $registro['tipo'] === 'sem_registro') ? '0h 00m' : ($horasExtrasDia > 0 ? formatarHoraDecimal($horasExtrasDia) : '0h 00m') ?></td>
                                                        <td><?= implode(', ', $ocorrencias) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
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
                                                    <p><strong>Horas Trabalhadas:</strong> <?= formatarHoraDecimal($estatisticas['horasTrabalhadas']) ?></p>
                                                    <p><strong>Média Diária:</strong> <?= formatarHoraDecimal($estatisticas['mediaDiaria']) ?></p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <div class="card h-100">
                                                <div class="card-header"><i class="bx bx-minus-circle me-2"></i> Débito</div>
                                                <div class="card-body">
                                                    <p><strong>Horas Devidas:</strong> <?= formatarHoraDecimal($estatisticas['horasDevidas']) ?></p>
                                                    <p><strong>Atrasos:</strong> <?= $estatisticas['atrasos'] ?></p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <div class="card h-100">
                                                <div class="card-header"><i class="bx bx-plus-circle me-2"></i> Crédito</div>
                                                <div class="card-body">
                                                    <p><strong>Horas Extras:</strong> <?= formatarHoraDecimal($estatisticas['horasExtras']) ?></p>
                                                    <p><strong>Horas Excedentes:</strong> <?= formatarHoraDecimal($estatisticas['horasExcedentes']) ?></p>
                                                    <p><strong>Adicional Noturno:</strong> <?= formatarHoraDecimal($estatisticas['horasNoturnas']) ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="signatures">
                                        <div class="signature-box">Empregador</div>
                                        <div class="signature-box">Responsável</div>
                                        <div class="signature-box">Empregado(a)</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <!-- /layout-page -->

        </div>
        <!-- /layout-container -->

    </div>
    <!-- /Layout wrapper -->

    <script>
        function enviarPorEmail() {
            const {
                jsPDF
            } = window.jspdf;
            const doc = new jsPDF();

            // ✅ Altere este seletor para o ID ou classe da sua tabela!
            const tabela = document.querySelector("#tabela-frequencia");

            if (!tabela) {
                alert("Erro: Tabela não encontrada. Verifique o seletor no código.");
                return;
            }

            html2canvas(tabela).then((canvas) => {
                const imgData = canvas.toDataURL("image/png");
                const imgWidth = doc.internal.pageSize.getWidth() - 20;
                const imgHeight = (canvas.height * imgWidth) / canvas.width;

                doc.addImage(imgData, "PNG", 10, 10, imgWidth, imgHeight);
                const pdfBlob = doc.output("blob");
                const pdfUrl = URL.createObjectURL(pdfBlob);

                const link = document.createElement("a");
                link.href = pdfUrl;
                link.download = `<?= htmlspecialchars($funcionario['nome'] ?? 'relatorio') ?>_frequencia.pdf`;
                link.click();

                const destinatario = "<?= htmlspecialchars($funcionario['email'] ?? 'email@padrao.com') ?>";
                const assunto = "Relatório de Frequência";
                const corpo = "Segue em anexo o relatório de frequência em PDF.";

                window.open(
                    `https://mail.google.com/mail/?view=cm&to=${destinatario}&su=${encodeURIComponent(assunto)}&body=${encodeURIComponent(corpo)}`,
                    "_blank"
                );

                alert("PDF gerado! Verifique seu download e anexe-o ao e-mail.");
            });
        }
    </script>


    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- build:js assets/vendor/js/core.js -->
    <script src="../../js/saudacao.js"></script>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>

    <script>
        // Adiciona a data atual no rodapé
        document.getElementById('current-date').textContent = new Date().toLocaleDateString('pt-BR');

        // Configuração para impressão em PDF
        document.title = "Relatório Espelho Ponto - Naiara Kaliane";
    </script>

</body>

</html>
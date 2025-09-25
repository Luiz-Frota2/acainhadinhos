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

// ✅ Buscar logo da empresa
try {
    $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1");
    $stmt->bindParam(':id_selecionado', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);

    $logoEmpresa = !empty($empresaSobre['imagem'])
        ? "../../assets/img/empresa/" . $empresaSobre['imagem']
        : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png"; // fallback
}

/* ===== Helpers ===== */
function limparCPF($cpf)
{
    return preg_replace('/[^0-9]/', '', $cpf);
}

function timeToMinutes($time)
{
    if (!$time || $time === '00:00:00' || $time === '--:--') return 0;
    $parts = explode(':', $time);
    $h = (int)$parts[0];
    $m = (int)($parts[1] ?? 0);
    return $h * 60 + $m;
}
function minutesToHM($min)
{
    if ($min <= 0) return '00h 00m';
    $h = floor($min / 60);
    $m = $min % 60;
    return sprintf('%02dh %02dm', $h, $m);
}
function converterHoraParaDecimal($horaString)
{
    if (empty($horaString) || $horaString === '--:--') return 0;
    $parts = explode(':', $horaString);
    $hours = (int)$parts[0];
    $minutes = (int)($parts[1] ?? 0);
    return $hours + $minutes / 60;
}
function formatarHoraDecimal($decimal)
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
    $cpfLimpo = limparCPF($cpf);
    if (strlen($cpfLimpo) === 11) {
        return substr($cpfLimpo, 0, 3) . '.' . substr($cpfLimpo, 3, 3) . '.' . substr($cpfLimpo, 6, 3) . '-' . substr($cpfLimpo, 9, 2);
    }
    return $cpf;
}
function calcularDiferencaMinutos($horaInicial, $horaFinal)
{
    if (!$horaInicial || !$horaFinal || $horaInicial === '--:--' || $horaFinal === '--:--') return 0;
    $hiParts = explode(':', $horaInicial);
    $hfParts = explode(':', $horaFinal);
    $hi = (int)$hiParts[0] * 60 + (int)($hiParts[1] ?? 0);
    $hf = (int)$hfParts[0] * 60 + (int)($hfParts[1] ?? 0);
    // se cruzou meia-noite
    if ($hf < $hi) $hf += 24 * 60;
    return $hf - $hi;
}
function calcularHorasTrabalhadas($entrada, $saida_intervalo, $retorno_intervalo, $saida_final)
{
    if (!$entrada || !$saida_final || $entrada === '--:--' || $saida_final === '--:--') return 0;
    $total = 0;
    if ($saida_intervalo && $saida_intervalo !== '--:--') {
        $total += timeToMinutes($saida_intervalo) - timeToMinutes($entrada);
    }
    if ($retorno_intervalo && $retorno_intervalo !== '--:--') {
        $total += timeToMinutes($saida_final) - timeToMinutes($retorno_intervalo);
    } else {
        $total += timeToMinutes($saida_final) - timeToMinutes($entrada);
    }
    return $total / 60; // em horas
}
function mesPortugues($mes)
{
    $meses = [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'];
    return $meses[(int)$mes] ?? '';
}
function calcularCargaHorariaDia($funcionario)
{
    if (empty($funcionario['entrada']) || empty($funcionario['saida_final']) || $funcionario['entrada'] === '--:--' || $funcionario['saida_final'] === '--:--') return 0;
    $minutos = calcularDiferencaMinutos($funcionario['entrada'], $funcionario['saida_final']);
    if (
        !empty($funcionario['saida_intervalo']) && !empty($funcionario['retorno_intervalo'])
        && $funcionario['saida_intervalo'] !== '--:--' && $funcionario['retorno_intervalo'] !== '--:--'
    ) {
        $minutos -= calcularDiferencaMinutos($funcionario['saida_intervalo'], $funcionario['retorno_intervalo']);
    }
    return $minutos / 60; // horas
}
function calcularHorasNoturnas($entrada, $saida, $saidaIntervalo = null, $retornoIntervalo = null)
{
    if (!$entrada || !$saida || $entrada === '--:--' || $saida === '--:--') return 0;
    // Considera períodos antes e depois do intervalo se existir
    $periodos = [];
    if ($saidaIntervalo && $retornoIntervalo && $saidaIntervalo !== '--:--' && $retornoIntervalo !== '--:--') {
        $periodos[] = [$entrada, $saidaIntervalo];
        $periodos[] = [$retornoIntervalo, $saida];
    } else {
        $periodos[] = [$entrada, $saida];
    }

    $totalMin = 0;
    foreach ($periodos as [$ini, $fim]) {
        $iniMin = timeToMinutes($ini);
        $fimMin = timeToMinutes($fim);
        if ($fimMin < $iniMin) $fimMin += 24 * 60;

        // janela noturna 22:00 (1320) -> 05:00 (+24h = 1800)
        $iniNot = 22 * 60;
        $fimNot = 5 * 60 + 24 * 60;

        $iniClip = max($iniMin, $iniNot);
        $fimClip = min($fimMin, $fimNot);
        if ($fimClip > $iniClip) $totalMin += ($fimClip - $iniClip);
    }
    return $totalMin / 60; // horas
}

/* ===== CNPJ da empresa ===== */
try {
    $sqlCnpj = "SELECT cnpj FROM endereco_empresa WHERE empresa_id = :id_selecionado LIMIT 1";
    $stmtCnpj = $pdo->prepare($sqlCnpj);
    $stmtCnpj->bindParam(':id_selecionado', $idSelecionado, PDO::PARAM_STR);
    $stmtCnpj->execute();
    $resultadoCnpj = $stmtCnpj->fetch(PDO::FETCH_ASSOC);
    $cnpjEmpresa = $resultadoCnpj['cnpj'] ?? '';
} catch (PDOException $e) {
    $cnpjEmpresa = '';
}

/* ===== Dados do relatório ===== */
try {
    $cpf = $_GET['cpf'] ?? die('CPF não fornecido');
    $cpfLimpo = limparCPF($cpf);
    if (strlen($cpfLimpo) != 11) die('CPF inválido');

    $mes = $_GET['mes'] ?? die('Mês não fornecido');
    $ano = $_GET['ano'] ?? date('Y');
    if (!is_numeric($mes) || $mes < 1 || $mes > 12) die('Mês inválido');
    if (!is_numeric($ano) || strlen($ano) != 4) die('Ano inválido');

    // Funcionário
    $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE REPLACE(REPLACE(cpf,'.',''),'-','') = ?");
    $stmt->execute([$cpfLimpo]);
    $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$funcionario) die('Funcionário não encontrado');

    // Setor (se precisar)
    $stmt = $pdo->prepare("SELECT * FROM setores WHERE nome = ?");
    $stmt->execute([$funcionario['setor']]);
    $setor = $stmt->fetch(PDO::FETCH_ASSOC);

    // Pontos do mês
    $stmt = $pdo->prepare("SELECT * FROM pontos 
                          WHERE REPLACE(REPLACE(cpf,'.',''),'-','') = ?
                            AND MONTH(data) = ?
                            AND YEAR(data)  = ?
                          ORDER BY data ASC");
    $stmt->execute([$cpfLimpo, $mes, $ano]);
    $pontos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Folgas do mês
    $stmt = $pdo->prepare("SELECT data_folga FROM folgas 
                          WHERE REPLACE(REPLACE(cpf,'.',''),'-','') = ?
                            AND MONTH(data_folga) = ?
                            AND YEAR(data_folga)  = ?
                          ORDER BY data_folga ASC");
    $stmt->execute([$cpfLimpo, $mes, $ano]);
    $folgas = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Mapa de registros do mês
    $numeroDias = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
    $registros = [];
    for ($dia = 1; $dia <= $numeroDias; $dia++) {
        $data = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
        $registros[$data] = ['tipo' => 'sem_registro', 'data' => $data];
    }
    foreach ($pontos as $ponto) {
        $registros[$ponto['data']] = [
            'tipo'               => 'ponto',
            'data'               => $ponto['data'],
            'entrada'            => $ponto['entrada'],
            'saida_intervalo'    => $ponto['saida_intervalo'],
            'retorno_intervalo'  => $ponto['retorno_intervalo'],
            'saida_final'        => $ponto['saida_final'],
            'hora_extra'         => $ponto['hora_extra'],      // <- vem do banco (TIME)
            'horas_pendentes'    => $ponto['horas_pendentes']  // <- se houver
        ];
    }
    foreach ($folgas as $folga) {
        $registros[$folga] = ['tipo' => 'folga', 'data' => $folga];
    }
    ksort($registros);

    // Estatísticas
    $estatisticas = [
        'totalDias'         => count($registros),
        'diasTrabalhados'   => 0,
        'diasFolga'         => count($folgas),
        'horasTrabalhadas'  => 0,
        'horasExtras'       => 0,   // soma do campo hora_extra (TIME)
        'horasExcedentes'   => 0,   // = horasExtras
        'horasPendentes'    => 0,
        'horasDevidas'      => 0,
        'atrasos'           => 0,
        'horasNoturnas'     => 0,
        'cargaHorariaTotal' => 0,
        'mediaDiaria'       => 0
    ];

    // Carga horária diária esperada (tabela funcionarios)
    $cargaHorariaDia = calcularCargaHorariaDia($funcionario);
    $estatisticas['cargaHorariaTotal'] = $cargaHorariaDia * ($numeroDias - count($folgas));

    // Enriquecer registros + somar estatísticas (UMA passagem)
    foreach ($registros as &$registro) {
        if ($registro['tipo'] !== 'ponto') {
            // nada para somar em folga/sem_registro
            $registro['horas_trab_dec']  = 0;
            $registro['horas_noturnas']  = 0;
            $registro['horas_extras_dec'] = 0;
            continue;
        }

        // Horas trabalhadas (decimal)
        $horasTrab = calcularHorasTrabalhadas(
            $registro['entrada'] ?? null,
            $registro['saida_intervalo'] ?? null,
            $registro['retorno_intervalo'] ?? null,
            $registro['saida_final'] ?? null
        );
        $registro['horas_trab_dec'] = $horasTrab;

        // Se tem entrada+saida_final conta como trabalhado
        if (!empty($registro['entrada']) && !empty($registro['saida_final']) && $registro['entrada'] !== '--:--' && $registro['saida_final'] !== '--:--') {
            $estatisticas['diasTrabalhados']++;
        }

        // Horas noturnas (decimal)
        $registro['horas_noturnas'] = calcularHorasNoturnas(
            $registro['entrada'] ?? null,
            $registro['saida_final'] ?? null,
            $registro['saida_intervalo'] ?? null,
            $registro['retorno_intervalo'] ?? null
        );

        // Hora extra: DIRETO do banco (TIME -> decimal)
        $registro['horas_extras_dec'] = converterHoraParaDecimal($registro['hora_extra'] ?? '');

        // Acúmulos
        $estatisticas['horasTrabalhadas'] += $registro['horas_trab_dec'];
        $estatisticas['horasNoturnas']    += $registro['horas_noturnas'];
        $estatisticas['horasExtras']      += $registro['horas_extras_dec'];
        $estatisticas['horasExcedentes']  += $registro['horas_extras_dec'];

        // Horas devidas quando trabalhou menos que a carga
        $cargaMin = $cargaHorariaDia * 60;
        $trabMin  = (int)round($registro['horas_trab_dec'] * 60);
        if ($cargaHorariaDia > 0 && $trabMin < $cargaMin) {
            $estatisticas['horasDevidas'] += ($cargaMin - $trabMin) / 60;
        }

        // Horas pendentes (TIME -> decimal) afetam pendentes e devidas
        if (!empty($registro['horas_pendentes']) && $registro['horas_pendentes'] !== '--:--') {
            $pend = converterHoraParaDecimal($registro['horas_pendentes']);
            $estatisticas['horasPendentes'] += $pend;
            $estatisticas['horasDevidas']   += $pend;
        }

        // Atraso (>10 min) — mantido; sem "Saída Antecipada"
        if (
            !empty($funcionario['entrada']) && $funcionario['entrada'] !== '--:--'
            && !empty($registro['entrada']) && $registro['entrada'] !== '--:--'
        ) {
            // regra opcional: só marcar atraso se não houver HE nem Noturno
            if ($registro['horas_extras_dec'] <= 0 && $registro['horas_noturnas'] <= 0) {
                $diffEntradaMin = calcularDiferencaMinutos($funcionario['entrada'], $registro['entrada']);
                if ($diffEntradaMin > 10) {
                    $estatisticas['atrasos']++;
                    $estatisticas['horasDevidas'] += $diffEntradaMin / 60;
                }
            }
        }
    }
    unset($registro);

    if ($estatisticas['diasTrabalhados'] > 0) {
        $estatisticas['mediaDiaria'] = $estatisticas['horasTrabalhadas'] / $estatisticas['diasTrabalhados'];
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
                            <li class="menu-item">
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
                        <a href="../empresa/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div data-i18n="Authentications">Empresa</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-box"></i>
                            <div data-i18n="Authentications">Estoque</div>
                        </a>
                    </li>
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
                                                    <div class="avatar avatar-online">
                                                        <img src="<?= htmlspecialchars($logoEmpresa, ENT_QUOTES) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" />
                                                    </div>
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
                                    <li>
                                        <a class="dropdown-item" href="./contaUsuario.php?id=<?= urlencode($idSelecionado); ?>">
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
                                        <a class="dropdown-item" href="../logout.php?id=<?= urlencode($idSelecionado); ?>">
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
                                                        $dataAdmissao = $funcionario['data_admissao'] ?? '';
                                                        echo ($dataAdmissao && $dataAdmissao !== '0000-00-00') ? formatarData($dataAdmissao) : '';
                                                        ?>
                                                    </p>
                                                    <p><strong>Carga Horária Diária:</strong> <?= formatarHoraDecimal($cargaHorariaDia) ?></p>
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
                                                    $ocorrencias = [];
                                                    $horasExtrasDia    = ($registro['tipo'] === 'ponto') ? ($registro['horas_extras_dec'] ?? converterHoraParaDecimal($registro['hora_extra'] ?? '')) : 0;
                                                    $horasNoturnasDia  = ($registro['tipo'] === 'ponto') ? ($registro['horas_noturnas'] ?? 0) : 0;

                                                    if ($registro['tipo'] === 'folga') {
                                                        $ocorrencias[] = 'Folga';
                                                    } elseif ($registro['tipo'] === 'sem_registro') {
                                                        $ocorrencias[] = 'Sem Registro';
                                                    } elseif (!empty($registro['entrada']) && !empty($registro['saida_final']) && $registro['entrada'] !== '--:--' && $registro['saida_final'] !== '--:--') {
                                                        // Atraso (tolerância 10 min) — sem "Saída Antecipada" e sem "Dia Incompleto"
                                                        $temAtraso = false;
                                                        if (!empty($funcionario['entrada']) && $funcionario['entrada'] !== '--:--') {
                                                            // Regra: só marca atraso se não houver HE nem Noturno
                                                            if ($horasExtrasDia <= 0 && $horasNoturnasDia <= 0) {
                                                                $entradaEsperada   = $funcionario['entrada'];
                                                                $entradaRegistrada = $registro['entrada'];
                                                                $diferencaMinutos  = calcularDiferencaMinutos($entradaEsperada, $entradaRegistrada);
                                                                if ($diferencaMinutos > 10) $temAtraso = true;
                                                            }
                                                        }

                                                        if ($horasNoturnasDia > 0) $ocorrencias[] = 'Adicional Noturno';
                                                        if ($horasExtrasDia > 0)   $ocorrencias[] = 'Horas Extras';
                                                        if ($temAtraso)            $ocorrencias[] = 'Atraso';

                                                        // Se nada se aplica, marca Normal
                                                        if (empty($ocorrencias)) $ocorrencias[] = 'Normal';
                                                    } else {
                                                        // ponto com dados parciais -> não marcar "Dia Incompleto": deixa vazio/--
                                                        // $ocorrencias[] = '--';
                                                    }
                                                ?>
                                                    <tr>
                                                        <td><?= formatarData($registro['data']) ?></td>
                                                        <td><?= ($registro['tipo'] !== 'ponto') ? '--:--' : (!empty($registro['entrada']) ? date('H:i', strtotime($registro['entrada'])) : '--:--') ?></td>
                                                        <td><?= ($registro['tipo'] !== 'ponto') ? '--:--' : (!empty($registro['saida_intervalo']) ? date('H:i', strtotime($registro['saida_intervalo'])) : '--:--') ?></td>
                                                        <td><?= ($registro['tipo'] !== 'ponto') ? '--:--' : (!empty($registro['retorno_intervalo']) ? date('H:i', strtotime($registro['retorno_intervalo'])) : '--:--') ?></td>
                                                        <td><?= ($registro['tipo'] !== 'ponto') ? '--:--' : (!empty($registro['saida_final']) ? date('H:i', strtotime($registro['saida_final'])) : '--:--') ?></td>
                                                        <td>
                                                            <?php
                                                            if ($registro['tipo'] !== 'ponto') {
                                                                echo '00h 00m';
                                                            } elseif (!empty($registro['entrada']) && !empty($registro['saida_final']) && $registro['entrada'] !== '--:--' && $registro['saida_final'] !== '--:--') {
                                                                echo formatarHoraDecimal($registro['horas_trab_dec'] ?? calcularHorasTrabalhadas(
                                                                    $registro['entrada'],
                                                                    $registro['saida_intervalo'],
                                                                    $registro['retorno_intervalo'],
                                                                    $registro['saida_final']
                                                                ));
                                                            } else {
                                                                echo '00h 00m';
                                                            }
                                                            ?>
                                                        </td>
                                                        <td><?= ($registro['tipo'] === 'ponto') ? formatarHoraDecimal($cargaHorariaDia) : '--:--' ?></td>
                                                        <td><?= ($registro['tipo'] === 'ponto' && $horasNoturnasDia > 0) ? formatarHoraDecimal($horasNoturnasDia) : '00h 00m' ?></td>
                                                        <td><?= ($registro['tipo'] === 'ponto' && $horasExtrasDia > 0) ? formatarHoraDecimal($horasExtrasDia) : '00h 00m' ?></td>
                                                        <td><?= empty($ocorrencias) ? '--' : implode(', ', $ocorrencias) ?></td>
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
                                                    <!-- Removido: Saídas Antecipadas -->
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

    <!-- Place this tag in your head or just before your close body tag. -->
    <script async defer src="https://buttons.github.io/buttons.js"></script>

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

</body>

</html>
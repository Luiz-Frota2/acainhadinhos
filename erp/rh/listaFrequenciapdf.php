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
    return sprintf('%02dh %02dm', $h, $m);
}

// Fetch registros + escala
try {
    $sql = "
    SELECT 
      p.*,
      f.dia_inicio, f.dia_folga,
      f.entrada   AS f_entrada,
      f.saida_intervalo   AS f_saida_intervalo,
      f.retorno_intervalo AS f_retorno_intervalo,
      f.saida_final       AS f_saida_final
    FROM pontos p
    LEFT JOIN funcionarios f
      ON p.cpf = f.cpf AND p.empresa_id = f.empresa_id
    WHERE p.empresa_id = :empresa_id
    ORDER BY p.cpf, p.data
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':empresa_id', $idSelecionado);
    $stmt->execute();
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao buscar registros: " . $e->getMessage());
}

// Agrupa por CPF|mês|ano
$dadosAgrupados = [];
foreach ($registros as $r) {
    $cpf = $r['cpf'];
    $mes = date('m', strtotime($r['data']));
    $ano = date('Y', strtotime($r['data']));
    $key = "$cpf|$mes|$ano";
    if (!isset($dadosAgrupados[$key])) {
        // calcula dias úteis
        $sem = ['domingo', 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado'];
        $i0 = array_search($r['dia_inicio'], $sem);
        $i1 = array_search($r['dia_folga'], $sem);
        $perm = [];
        for ($i = $i0;; $i = ($i + 1) % 7) {
            $perm[] = $sem[$i];
            if ($i === $i1)
                break;
        }
        $dm = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
        $du = 0;
        for ($d = 1; $d <= $dm; $d++) {
            $dw = strtolower(date('l', strtotime("$ano-$mes-" . str_pad($d, 2, '0', STR_PAD_LEFT))));
            if (in_array($dw, $perm, true))
                $du++;
        }
        // referencia diária
        $refE = $r['f_entrada'] ?: $r['entrada'];
        $refSI = $r['f_saida_intervalo'] ?: $r['saida_intervalo'];
        $refR = $r['f_retorno_intervalo'] ?: $r['retorno_intervalo'];
        $refS = $r['f_saida_final'] ?: $r['saida_final'];
        $minTurno = (timeToMinutes($refS) - timeToMinutes($refE))
            - (timeToMinutes($refR) - timeToMinutes($refSI));
        $minDevidos = $minTurno * $du;
        $dadosAgrupados[$key] = [
            'nome' => $r['nome'],
            'mes_ano' => "$mes/$ano",
            'minTrabalhados' => 0,
            'minPendentes' => 0,
            'minExtras' => 0,
            'minDevidos' => $minDevidos,
            // escala
            'dia_inicio' => $r['dia_inicio'],
            'dia_folga' => $r['dia_folga'],
            'entrada' => $refE,
            'saida_intervalo' => $refSI,
            'retorno_intervalo' => $refR,
            'saida_final' => $refS,
        ];
    }
    // acumula minutos trabalhados com correção para intervalos NULL
    $m = 0;
    $entrada = $r['entrada'];
    $saida_intervalo = $r['saida_intervalo'];
    $retorno_intervalo = $r['retorno_intervalo'];
    $saida_final = $r['saida_final'];

    if ($entrada && $saida_final) {
        if ($saida_intervalo && $retorno_intervalo) {
            // calcula dois períodos (antes e depois do intervalo)
            $m += timeToMinutes($saida_intervalo) - timeToMinutes($entrada);
            $m += timeToMinutes($saida_final) - timeToMinutes($retorno_intervalo);
        } else {
            // intervalo não registrado: calcula direto entrada até saída final
            $m += timeToMinutes($saida_final) - timeToMinutes($entrada);
        }
    }

    $dadosAgrupados[$key]['minTrabalhados'] += $m;
    $dadosAgrupados[$key]['minPendentes'] += timeToMinutes($r['horas_pendentes']);
    $dadosAgrupados[$key]['minExtras'] += timeToMinutes($r['hora_extra']);
}

// Ajusta saldos e formata horas
foreach ($dadosAgrupados as &$d) {
    $p = $d['minPendentes'];
    $e = $d['minExtras'];
    if ($e > $p) {
        $d['minLiquidaExtra'] = $e - $p;
        $d['minLiquidaPend'] = 0;
    } else {
        $d['minLiquidaPend'] = $p - $e;
        $d['minLiquidaExtra'] = 0;
    }
    $d['horas_trabalhadas'] = minutesToHM($d['minTrabalhados']);
    $d['hora_extra_liquida'] = minutesToHM($d['minLiquidaExtra']);
    $d['horas_pendentes_liquida'] = minutesToHM($d['minLiquidaPend']);
}
unset($d);
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
                                <a href="./frequencia.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div data-i18n="Ajuste de Horários e Banco de Horas">Frequência</div>
                                </a>
                            </li>
                            <li class="menu-item active ">
                                <a href="./frequenciaIndividual.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div data-i18n="Ajuste de Horários e Banco de Horas">Frequência Individual</div>
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
                    <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a href="#">Sistema de
                                Ponto</a>/</span>Frequência Individual</h4>
                    <h5 class="fw-bold mt-3 mb-3 custor-font"><span class="text-muted fw-light">Visualize a Frequência
                            do Funcionário</span></h5>

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
                            <?php
                            $host = 'localhost';
                            $dbname = 'u920914488_ERP';
                            $username = 'u920914488_ERP';
                            $password = 'N8r=$&Wrs$';

                          
                            function limparCPF($cpf)
                            {
                                return preg_replace('/[^0-9]/', '', $cpf);
                            }

                            function formatarData($dataString)
                            {
                                if (!$dataString) return '--/--/----';
                                return date('d/m/Y', strtotime($dataString));
                            }

                            function formatarCPF($cpf)
                            {
                                if (empty($cpf)) {
                                    return '';
                                }
                             
                                $cpfLimpo = limparCPF($cpf);
                                if (strlen($cpfLimpo) === 11) {
                                    return substr($cpfLimpo, 0, 3) . '.' . substr($cpfLimpo, 3, 3) . '.' . substr($cpfLimpo, 6, 3) . '-' . substr($cpfLimpo, 9, 2);
                                }
                                return $cpf;
                            }

                            function formatarTelefone($telefone)
                            {
                                if (!$telefone) return '';
                                if (strlen($telefone) === 11) {
                                    return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7);
                                }
                                return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6);
                            }

                            function capitalize($str)
                            {
                                if (!$str) return '';
                                return ucfirst($str);
                            }

                            function converterHoraParaDecimal($horaString)
                            {
                                if (!$horaString) return 0;
                                list($hours, $minutes) = explode(':', $horaString);
                                return $hours + $minutes / 60;
                            }

                            function formatarHoraDecimal($decimal)
                            {
                                $horas = floor($decimal);
                                $minutos = round(($decimal - $horas) * 60);
                                return sprintf("%02d:%02d", $horas, $minutos);
                            }

                            function calcularDiferencaMinutos($horaInicial, $horaFinal)
                            {
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


                            try {
                                $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                                // Recebe e valida o CPF da URL (aceita formatado ou não)
                                $cpf = $_GET['cpf'] ?? die('CPF não fornecido');
                                $cpfLimpo = limparCPF($cpf);

                                if (strlen($cpfLimpo) != 11) {
                                    die('CPF inválido');
                                }

                                $mes = $_GET['mes'] ?? die('Mês não fornecido');
                                $ano = $_GET['ano'] ?? date('Y');

                                if (!is_numeric($mes) || $mes < 1 || $mes > 12) {
                                    die('Mês inválido (deve ser entre 1 e 12)');
                                }

                                if (!is_numeric($ano) || strlen($ano) != 4) {
                                    die('Ano inválido');
                                }

                                // Consulta funcionários comparando CPFs limpos
                                $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE REPLACE(REPLACE(cpf, '.', ''), '-', '') = ?");
                                $stmt->execute([$cpfLimpo]);
                                $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);

                                if (!$funcionario) {
                                    die('Funcionário não encontrado');
                                }

                                // Consulta setores (mantida original)
                                $stmt = $pdo->prepare("SELECT * FROM setores WHERE nome = ?");
                                $stmt->execute([$funcionario['setor']]);
                                $setor = $stmt->fetch(PDO::FETCH_ASSOC);

                                // Consulta pontos comparando CPFs limpos
                                $stmt = $pdo->prepare("SELECT * FROM pontos 
                          WHERE REPLACE(REPLACE(cpf, '.', ''), '-', '') = ? 
                          AND MONTH(data) = ? 
                          AND YEAR(data) = ?
                          ORDER BY data DESC");
                                $stmt->execute([$cpfLimpo, $mes, $ano]);
                                $pontos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                $estatisticas = [
                                    'totalDias' => count($pontos),
                                    'diasTrabalhados' => 0,
                                    'horasTrabalhadas' => 0,
                                    'horasExtras' => 0,
                                    'horasPendentes' => 0,
                                    'atrasos' => 0,
                                    'saidasAntecipadas' => 0,
                                    'horasDevidas' => 0,
                                    'horasExcedentes' => 0
                                ];

                                foreach ($pontos as $ponto) {
                                    if ($ponto['entrada'] && $ponto['saida_final']) {
                                        $estatisticas['diasTrabalhados']++;

                                        if ($ponto['total_horas']) {
                                            $estatisticas['horasTrabalhadas'] += converterHoraParaDecimal($ponto['total_horas']);
                                        }

                                        if ($funcionario['entrada'] && $ponto['entrada'] > $funcionario['entrada']) {
                                            $estatisticas['atrasos']++;
                                            $estatisticas['horasDevidas'] += calcularDiferencaMinutos($funcionario['entrada'], $ponto['entrada']) / 60;
                                        }

                                        if ($funcionario['saida_final'] && $ponto['saida_final'] < $funcionario['saida_final']) {
                                            $estatisticas['saidasAntecipadas']++;
                                            $estatisticas['horasDevidas'] += calcularDiferencaMinutos($ponto['saida_final'], $funcionario['saida_final']) / 60;
                                        }
                                    }

                                    if ($ponto['hora_extra']) {
                                        $estatisticas['horasExtras'] += converterHoraParaDecimal($ponto['hora_extra']);
                                        $estatisticas['horasExcedentes'] += converterHoraParaDecimal($ponto['hora_extra']);
                                    }

                                    if ($ponto['horas_pendentes']) {
                                        $estatisticas['horasPendentes'] += converterHoraParaDecimal($ponto['horas_pendentes']);
                                        $estatisticas['horasDevidas'] += converterHoraParaDecimal($ponto['horas_pendentes']);
                                    }
                                }

                                if ($estatisticas['diasTrabalhados'] > 0) {
                                    $estatisticas['mediaDiaria'] = $estatisticas['horasTrabalhadas'] / $estatisticas['diasTrabalhados'];
                                } else {
                                    $estatisticas['mediaDiaria'] = 0;
                                }

                                $nomeMes = mesPortugues($mes);
                            ?>
                                <div id="tabela-frequencia" class="report-body">
                                    <div class="report-container">
                                        <h1 class="report-title">RELATÓRIO ESPELHO PONTO - <?= strtoupper($nomeMes) ?>/<?= $ano ?></h1>
                                        </h1>

                                        <div class="row mb-4">
                                            <div class="col-md-4 mb-3">
                                                <div class="card h-100">
                                                    <div class="card-header"><i class="bx bx-user me-2"></i> Dados Pessoais</div>
                                                    <div class="card-body">
                                                        <p><strong>Nome:</strong> <?= htmlspecialchars($funcionario['nome']) ?></p>
                                                        <p><strong>CPF:</strong> <?= formatarCPF($funcionario['cpf']) ?></p>
                                                        <p><strong>RG:</strong> <?= htmlspecialchars($funcionario['rg']) ?></p>
                                                        <p><strong>Data Nasc.:</strong> <?= formatarData($funcionario['data_nascimento']) ?></p>
                                                        <p><strong>Endereço:</strong> <?= htmlspecialchars($funcionario['endereco']) ?>, <?= htmlspecialchars($funcionario['cidade']) ?></p>
                                                        <p><strong>Telefone:</strong> <?= formatarTelefone($funcionario['telefone']) ?></p>
                                                        <p><strong>E-mail:</strong> <?= htmlspecialchars($funcionario['email']) ?></p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-4 mb-3">
                                                <div class="card h-100">
                                                    <div class="card-header"><i class="bx bx-building me-2"></i> Dados Empresariais</div>
                                                    <div class="card-body">
                                                        <p><strong>Empresa:</strong> <?= htmlspecialchars($setor['id_selecionado'] ?? 'Não informado') ?></p>
                                                        <p><strong>Setor:</strong> <?= htmlspecialchars($funcionario['setor']) ?></p>
                                                        <p><strong>Gerente:</strong> <?= htmlspecialchars($setor['gerente'] ?? 'Não informado') ?></p>
                                                        <p><strong>Código:</strong> <?= htmlspecialchars($funcionario['empresa_id']) ?></p>
                                                        <p><strong>Data Admissão:</strong> <?= formatarData($funcionario['criado_em']) ?></p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-4 mb-3">
                                                <div class="card h-100">
                                                    <div class="card-header"><i class="bx bx-time me-2"></i> Informações de Trabalho</div>
                                                    <div class="card-body">
                                                        <p><strong>Cargo:</strong> <?= htmlspecialchars($funcionario['cargo']) ?></p>
                                                        <p><strong>Salário:</strong> R$ <?= number_format($funcionario['salario'], 2, ',', '.') ?></p>
                                                        <p><strong>Escala:</strong> <?= htmlspecialchars($funcionario['escala']) ?></p>
                                                        <p><strong>Dia Início:</strong> <?= capitalize(htmlspecialchars($funcionario['dia_inicio'])) ?></p>
                                                        <p><strong>Dia Folga:</strong> <?= capitalize(htmlspecialchars($funcionario['dia_folga'])) ?></p>
                                                        <p><strong>Horário:</strong> <?= htmlspecialchars($funcionario['entrada']) ?> às <?= htmlspecialchars($funcionario['saida_final']) ?></p>
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
                                                        <th>Ocorrências</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($pontos as $ponto):
                                                        $ocorrencias = [];
                                                        if ($funcionario['entrada'] && $ponto['entrada'] > $funcionario['entrada']) {
                                                            $ocorrencias[] = 'Atraso';
                                                        }
                                                        if ($funcionario['saida_final'] && $ponto['saida_final'] < $funcionario['saida_final']) {
                                                            $ocorrencias[] = 'Saída Antecip.';
                                                        }
                                                        if (!$ponto['entrada'] || !$ponto['saida_final']) {
                                                            $ocorrencias[] = 'Dia Incompleto';
                                                        }
                                                    ?>
                                                        <tr>
                                                            <td><?= formatarData($ponto['data']) ?></td>
                                                            <td><?= $ponto['entrada'] ? htmlspecialchars($ponto['entrada']) : '--:--' ?></td>
                                                            <td><?= $ponto['saida_intervalo'] ? htmlspecialchars($ponto['saida_intervalo']) : '--:--' ?></td>
                                                            <td><?= $ponto['retorno_intervalo'] ? htmlspecialchars($ponto['retorno_intervalo']) : '--:--' ?></td>
                                                            <td><?= $ponto['saida_final'] ? htmlspecialchars($ponto['saida_final']) : '--:--' ?></td>
                                                            <td><?= $ponto['total_horas'] ? htmlspecialchars($ponto['total_horas']) : '--:--' ?></td>
                                                            <td><?= calcularCargaHorariaDia($ponto, $funcionario) ?></td>
                                                            <td><?= $ocorrencias ? implode(', ', $ocorrencias) : 'Normal' ?></td>
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
                                                        <p><strong>Saídas Antecip.:</strong> <?= $estatisticas['saidasAntecipadas'] ?></p>
                                                        <p><strong>Dias Incompletos:</strong> <?= $estatisticas['totalDias'] - $estatisticas['diasTrabalhados'] ?></p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-4 mb-3">
                                                <div class="card h-100">
                                                    <div class="card-header"><i class="bx bx-plus-circle me-2"></i> Crédito</div>
                                                    <div class="card-body">
                                                        <p><strong>Horas Extras:</strong> <?= formatarHoraDecimal($estatisticas['horasExtras']) ?></p>
                                                        <p><strong>Horas Excedentes:</strong> <?= formatarHoraDecimal($estatisticas['horasExcedentes']) ?></p>
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
                                <script>
    function enviarPorEmail() {
        const { jsPDF } = window.jspdf;
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

<button type="button" class="btn btn-primary" onclick="enviarPorEmail()">
    <i class="bx bx-mail-send me-1"></i> Enviar por E-mail
</button>

<!-- Botão para acionar a função -->
<button type="button" class="btn btn-primary color-blue print-button flex-grow-1" onclick="enviarPorEmail()">
    <i class="bx bx-mail-send me-1"></i> Enviar por E-mail
</button>               <?php

                            } catch (PDOException $e) {
                                die('<div class="alert alert-danger">Erro no banco de dados: ' . htmlspecialchars($e->getMessage()) . '</div>');
                            }

                            function calcularCargaHorariaDia($ponto, $funcionario)
                            {
                                if (!$ponto['entrada'] || !$ponto['saida_final']) return '--:--';

                                if (!$ponto['saida_intervalo'] || !$ponto['retorno_intervalo']) {
                                    return htmlspecialchars($funcionario['saida_final']);
                                }

                                $entrada = strtotime($ponto['entrada']);
                                $saida = strtotime($ponto['saida_final']);
                                $intervaloInicio = strtotime($ponto['saida_intervalo']);
                                $intervaloFim = strtotime($ponto['retorno_intervalo']);

                                $manha = ($intervaloInicio - $entrada) / 3600;
                                $tarde = ($saida - $intervaloFim) / 3600;
                                $totalHoras = $manha + $tarde;

                                $horas = floor($totalHoras);
                                $minutos = round(($totalHoras - $horas) * 60);
                                return sprintf("%02d:%02d", $horas, $minutos);
                            }
                            ?>

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
                                document.title = "Relatório Espelho Ponto - Naara Kaliane";
                            </script>
</body>

</html>
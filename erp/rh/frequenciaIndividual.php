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
                            <!-- Place this tag where you want the button to render. -->
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
                                                    <!-- Exibindo o nome e nível do usuário -->
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

                    <?php

                    function conectarBanco()
                    {
                        $host = 'localhost';
                        $dbname = 'u920914488_ERP';
                        $username = 'u920914488_ERP';
                        $password = 'N8r=$&Wrs$';

                        try {
                            $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            return $pdo;
                        } catch (PDOException $e) {
                            die("Erro na conexão com o banco de dados: " . $e->getMessage());
                        }
                    }

                    function formatarHoras($segundos)
                    {
                        if ($segundos === null || $segundos == 0) {
                            return '00h 00m';
                        }

                        $horas = floor($segundos / 3600);
                        $minutos = floor(($segundos % 3600) / 60);

                        return sprintf("%02dh %02dm", $horas, $minutos);
                    }

                    function mesPortugues($mesNumero)
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

                        return $meses[$mesNumero] ?? '';
                    }

                    try {
                        // Validação e limpeza do CPF da URL
                        if (!isset($_GET['cpf']) || empty($_GET['cpf'])) {
                            throw new Exception("CPF não informado na URL");
                        }

                        // Remove qualquer formatação do CPF (pontos e traço)
                        $cpfBusca = preg_replace('/[^0-9]/', '', $_GET['cpf']);

                        if (strlen($cpfBusca) != 11) {
                            throw new Exception("CPF inválido");
                        }

                        $pdo = conectarBanco();

                        // Consulta que funciona com CPF formatado ou não no banco
                        $stmt = $pdo->prepare("SELECT nome FROM pontos WHERE REPLACE(REPLACE(cpf, '.', ''), '-', '') = :cpf LIMIT 1");
                        $stmt->bindParam(':cpf', $cpfBusca, PDO::PARAM_STR);
                        $stmt->execute();

                        $funcionario = $stmt->fetch();

                        if (!$funcionario) {
                            throw new Exception("Funcionário não encontrado");
                        }

                        $nomeFuncionario = $funcionario['nome'];

                        // Consulta principal também adaptada para comparar CPFs limpos
                        $sql = "SELECT 
                YEAR(data) as ano,
                MONTH(data) as mes_numero,
                SUM(TIME_TO_SEC(total_horas)) as total_segundos,
                SUM(TIME_TO_SEC(horas_pendentes)) as pendentes_segundos,
                SUM(TIME_TO_SEC(hora_extra)) as extras_segundos,
                cpf as cpf
            FROM pontos 
            WHERE REPLACE(REPLACE(cpf, '.', ''), '-', '') = :cpf
            GROUP BY YEAR(data), MONTH(data)
            ORDER BY ano DESC, mes_numero DESC";

                        $stmt = $pdo->prepare($sql);
                        $stmt->bindParam(':cpf', $cpfBusca, PDO::PARAM_STR);
                        $stmt->execute();

                        $registros = $stmt->fetchAll();
                    } catch (PDOException $e) {
                        die("Erro no banco de dados: " . $e->getMessage());
                    } catch (Exception $e) {
                        die("Erro: " . $e->getMessage());
                    }
                    ?>
                    <div class="container mt-4">
                        <div class="card mt-3">
                            <h5 class="card-header">Frequência do Funcionário: <?= htmlspecialchars($nomeFuncionario) ?></h5>
                            <div class="table-responsive text-nowarp">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Ano</th>
                                            <th>Mês</th>
                                            <th>Horas Trabalhadas</th>
                                            <th>Horas Pendentes</th>
                                            <th>Horas Extras</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($registros as $registro):
                                            $mesPortugues = mesPortugues($registro['mes_numero']);
                                        ?>
                                            <tr>
                                                <td><?= htmlspecialchars($registro['ano']) ?></td>
                                                <td><?= htmlspecialchars($mesPortugues) ?></td>
                                                <td><?= formatarHoras($registro['total_segundos']) ?></td>
                                                <td><?= formatarHoras($registro['pendentes_segundos']) ?></td>
                                                <td><?= formatarHoras($registro['extras_segundos']) ?></td>

                                                <td>
                                                    <a href="listaFrequenciapdf.php?id=<?= urlencode($idSelecionado); ?>&cpf=<?= htmlspecialchars($registro['cpf']) ?>&mes=<?= htmlspecialchars($registro['mes_numero']) ?>&ano=<?= htmlspecialchars($registro['ano']) ?>" title="Visualizar">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    &nbsp; | &nbsp;
                                                    <a href="#" data-bs-toggle="modal" data-bs-target="#emailModal<?= $registro['ano'] . $registro['mes_numero'] ?>" title="Enviar por e-mail">
                                                        <i class="fas fa-envelope"></i>
                                                    </a>


                                                    <div class="modal fade" id="emailModal<?= $registro['ano'] . $registro['mes_numero'] ?>" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <form action="enviar_email.php" method="POST">
                                                                    <input type="hidden" name="cpf" value="<?= $cpf ?>">
                                                                    <input type="hidden" name="ano" value="<?= $registro['ano'] ?>">
                                                                    <input type="hidden" name="mes" value="<?= $registro['mes_numero'] ?>">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title" id="enviarEmailModalLabel">Enviar Frequência por E-mail</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <div class="mb-3">
                                                                            <label for="emailDestino" class="form-label">E-mail do Destinatário</label>
                                                                            <input type="email" class="form-control" id="emailDestino" name="emailDestino" placeholder="exemplo@email.com" required>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label for="assunto" class="form-label">Assunto</label>
                                                                            <input type="text" class="form-control" id="assunto" name="assunto"
                                                                                value="Relatório de Frequência - <?= $mesPortugues ?>/<?= $registro['ano'] ?>">
                                                                            <div class="mb-3">
                                                                                <label for="mensagemEmail" class="form-label">Mensagem</label>
                                                                                <textarea class="form-control" id="mensagemEmail" name="mensagemEmail" rows="4" placeholder="Digite uma mensagem opcional..."></textarea>
                                                                            </div>
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                                            <button type="submit" class="btn btn-primary">Enviar</button>
                                                                        </div>
                                                                </form>
                                                            </div>
                                                        </div>

                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 m-3">
                        <button id="prevPageHoras" class="btn btn-outline-primary btn-sm">&laquo; Anterior</button>
                        <div id="paginacaoHoras" class="d-flex gap-1"></div>
                        <button id="nextPageHoras" class="btn btn-outline-primary btn-sm">Próxima &raquo;</button>
                    </div>
                </div>



                <script>
                    const searchInput = document.getElementById('searchInput');
                    const allRows = Array.from(document.querySelectorAll('#tabelaBancoHoras tbody tr'));
                    const prevBtn = document.getElementById('prevPageHoras');
                    const nextBtn = document.getElementById('nextPageHoras');
                    const pageContainer = document.getElementById('paginacaoHoras');
                    const perPage = 10;
                    let currentPage = 1;

                    function renderTable() {
                        const filter = searchInput.value.trim().toLowerCase();
                        const filteredRows = allRows.filter(row => {
                            if (!filter) return true;
                            return Array.from(row.cells).some(td =>
                                td.textContent.toLowerCase().includes(filter)
                            );
                        });

                        const totalPages = Math.ceil(filteredRows.length / perPage) || 1;
                        currentPage = Math.min(Math.max(1, currentPage), totalPages);

                        // Hide all, then show slice
                        allRows.forEach(r => r.style.display = 'none');
                        filteredRows.slice((currentPage - 1) * perPage, currentPage * perPage)
                            .forEach(r => r.style.display = '');

                        // Render page buttons
                        pageContainer.innerHTML = '';
                        for (let i = 1; i <= totalPages; i++) {
                            const btn = document.createElement('button');
                            btn.textContent = i;
                            btn.className = 'btn btn-sm ' + (i === currentPage ? 'btn-primary' : 'btn-outline-primary');
                            btn.style.marginRight = '4px';
                            btn.onclick = () => {
                                currentPage = i;
                                renderTable();
                            };
                            pageContainer.appendChild(btn);
                        }

                        prevBtn.disabled = currentPage === 1;
                        nextBtn.disabled = currentPage === totalPages;
                    }

                    prevBtn.addEventListener('click', () => {
                        if (currentPage > 1) {
                            currentPage--;
                            renderTable();
                        }
                    });
                    nextBtn.addEventListener('click', () => {
                        currentPage++;
                        renderTable();
                    });
                    searchInput.addEventListener('input', () => {
                        currentPage = 1;
                        renderTable();
                    });

                    document.addEventListener('DOMContentLoaded', renderTable);
                </script>

            </div>
        </div>
    </div>
    </div>
    </div>



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
</body>

</html>
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// ✅ Recupera o identificador vindo da URL
$idSelecionado = $_GET['id'] ?? '';

// ✅ Verifica se a pessoa está logada
if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id']) ||
    !isset($_SESSION['nivel'])
) {
    header("Location: ../index.php?id=$idSelecionado");
    exit;
}

require '../../assets/php/conexao.php';

$usuario_id = $_SESSION['usuario_id'];
$tipoUsuarioSessao = $_SESSION['nivel'];
$cpfUsuario = '';
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';

// ✅ Recuperar dados do usuário
try {
    if ($tipoUsuarioSessao === 'Admin') {
        $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    } else {
        $stmt = $pdo->prepare("SELECT usuario, nivel, cpf FROM funcionarios_acesso WHERE id = :id");
    }

    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $nomeUsuario = $usuario['usuario'];
        $tipoUsuario = ucfirst($usuario['nivel']);
        if (isset($usuario['cpf'])) {
            $cpfUsuario = $usuario['cpf'];
        }
    } else {
        echo "<script>alert('Usuário não encontrado.'); window.location.href = './index.php?id=$idSelecionado';</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar usuário: " . addslashes($e->getMessage()) . "'); history.back();</script>";
    exit;
}

// ✅ Função para buscar nome do funcionário por CPF
function obterNomeFuncionario($pdo, $cpf)
{
    try {
        $stmt = $pdo->prepare("SELECT nome FROM funcionarios_acesso WHERE cpf = :cpf LIMIT 1");
        $stmt->bindParam(':cpf', $cpf, PDO::PARAM_STR);
        $stmt->execute();
        $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);
        return $funcionario && !empty($funcionario['nome']) ? $funcionario['nome'] : 'Funcionário não identificado';
    } catch (PDOException $e) {
        return 'Erro ao buscar nome';
    }
}

// ✅ Valida empresa
if (str_starts_with($idSelecionado, 'principal_')) {
    if ($_SESSION['tipo_empresa'] !== 'principal' || $_SESSION['empresa_id'] != 1) {
        echo "<script>alert('Acesso negado!'); window.location.href = '../index.php?id=$idSelecionado';</script>";
        exit;
    }
    $id = 1;
} elseif (str_starts_with($idSelecionado, 'filial_')) {
    $idFilial = (int) str_replace('filial_', '', $idSelecionado);
    if ($_SESSION['tipo_empresa'] !== 'filial' || $_SESSION['empresa_id'] != $idFilial) {
        echo "<script>alert('Acesso negado!'); window.location.href = '../index.php?id=$idSelecionado';</script>";
        exit;
    }
    $id = $idFilial;
} else {
    echo "<script>alert('Empresa não identificada!'); window.location.href = '../index.php?id=$idSelecionado';</script>";
    exit;
}

// ✅ Favicon da empresa
$iconeEmpresa = '../../assets/img/favicon/favicon.ico';
try {
    $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1");
    $stmt->bindParam(':id', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($empresa && !empty($empresa['imagem'])) {
        $iconeEmpresa = $empresa['imagem'];
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar ícone: " . addslashes($e->getMessage()) . "');</script>";
}

// ✅ Processar filtros de data
$filtroData = $_GET['filtro'] ?? 'mes_atual';
$dataInicial = '';
$dataFinal = '';

switch ($filtroData) {
    case 'mes_atual':
        $dataInicial = date('Y-m-01');
        $dataFinal = date('Y-m-t');
        break;
    case '3_meses':
        $dataInicial = date('Y-m-01', strtotime('-2 months'));
        $dataFinal = date('Y-m-t');
        break;
    case 'personalizado':
        $dataInicial = $_GET['de'] ?? date('Y-m-01');
        $dataFinal = $_GET['ate'] ?? date('Y-m-t');
        break;
    default:
        $dataInicial = date('Y-m-01');
        $dataFinal = date('Y-m-t');
}

// ✅ Consulta para obter resumo de vendas
try {
    // Total de vendas
    $stmtVendas = $pdo->prepare("
        SELECT 
            COUNT(*) as quantidade_vendas,
            SUM(total) as valor_total
        FROM venda_rapida
        WHERE empresa_id = :empresa_id
        AND cpf_responsavel = :cpf_responsavel
        AND DATE(data_venda) BETWEEN :data_inicial AND :data_final
    ");
    $stmtVendas->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
    $stmtVendas->bindParam(':cpf_responsavel', $cpfUsuario, PDO::PARAM_STR);
    $stmtVendas->bindParam(':data_inicial', $dataInicial, PDO::PARAM_STR);
    $stmtVendas->bindParam(':data_final', $dataFinal, PDO::PARAM_STR);
    $stmtVendas->execute();
    $resumoVendas = $stmtVendas->fetch(PDO::FETCH_ASSOC);

    // Sangrias e Suprimentos
    $stmtMovimentos = $pdo->prepare("
        SELECT 
            SUM(valor_sangrias) as valor_sangrias,
            SUM(valor_suprimentos) as valor_suprimentos
        FROM aberturas
        WHERE empresa_id = :empresa_id
        AND cpf_responsavel = :cpf_responsavel
        AND DATE(abertura_datetime) BETWEEN :data_inicial AND :data_final
    ");
    $stmtMovimentos->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
    $stmtMovimentos->bindParam(':cpf_responsavel', $cpfUsuario, PDO::PARAM_STR);
    $stmtMovimentos->bindParam(':data_inicial', $dataInicial, PDO::PARAM_STR);
    $stmtMovimentos->bindParam(':data_final', $dataFinal, PDO::PARAM_STR);
    $stmtMovimentos->execute();
    $movimentos = $stmtMovimentos->fetch(PDO::FETCH_ASSOC);

    // Formas de pagamento
    $stmtPagamentos = $pdo->prepare("
        SELECT 
            forma_pagamento,
            COUNT(*) as quantidade,
            SUM(total) as valor_total
        FROM venda_rapida
        WHERE empresa_id = :empresa_id
        AND cpf_responsavel = :cpf_responsavel
        AND DATE(data_venda) BETWEEN :data_inicial AND :data_final
        GROUP BY forma_pagamento
    ");
    $stmtPagamentos->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
    $stmtPagamentos->bindParam(':cpf_responsavel', $cpfUsuario, PDO::PARAM_STR);
    $stmtPagamentos->bindParam(':data_inicial', $dataInicial, PDO::PARAM_STR);
    $stmtPagamentos->bindParam(':data_final', $dataFinal, PDO::PARAM_STR);
    $stmtPagamentos->execute();
    $formasPagamento = $stmtPagamentos->fetchAll(PDO::FETCH_ASSOC);

    // Vendas por dia
    $stmtVendasDia = $pdo->prepare("
        SELECT 
            DATE(data_venda) as data,
            COUNT(*) as quantidade,
            SUM(total) as valor_total,
            DAYNAME(data_venda) as dia_semana
        FROM venda_rapida
        WHERE empresa_id = :empresa_id
        AND cpf_responsavel = :cpf_responsavel
        AND DATE(data_venda) BETWEEN :data_inicial AND :data_final
        GROUP BY DATE(data_venda)
        ORDER BY DATE(data_venda)
    ");
    $stmtVendasDia->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
    $stmtVendasDia->bindParam(':cpf_responsavel', $cpfUsuario, PDO::PARAM_STR);
    $stmtVendasDia->bindParam(':data_inicial', $dataInicial, PDO::PARAM_STR);
    $stmtVendasDia->bindParam(':data_final', $dataFinal, PDO::PARAM_STR);
    $stmtVendasDia->execute();
    $vendasPorDia = $stmtVendasDia->fetchAll(PDO::FETCH_ASSOC);

    // Formas de pagamento por dia da semana
    $stmtPagamentosDiaSemana = $pdo->prepare("
        SELECT 
            DAYNAME(data_venda) as dia_semana,
            forma_pagamento,
            COUNT(*) as quantidade,
            SUM(total) as valor_total
        FROM venda_rapida
        WHERE empresa_id = :empresa_id
        AND cpf_responsavel = :cpf_responsavel
        AND DATE(data_venda) BETWEEN :data_inicial AND :data_final
        GROUP BY DAYNAME(data_venda), forma_pagamento
        ORDER BY FIELD(DAYNAME(data_venda), 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday')
    ");
    $stmtPagamentosDiaSemana->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
    $stmtPagamentosDiaSemana->bindParam(':cpf_responsavel', $cpfUsuario, PDO::PARAM_STR);
    $stmtPagamentosDiaSemana->bindParam(':data_inicial', $dataInicial, PDO::PARAM_STR);
    $stmtPagamentosDiaSemana->bindParam(':data_final', $dataFinal, PDO::PARAM_STR);
    $stmtPagamentosDiaSemana->execute();
    $pagamentosPorDiaSemana = $stmtPagamentosDiaSemana->fetchAll(PDO::FETCH_ASSOC);

    // Organizar dados por dia da semana
    $dadosPorDiaSemana = [];
    foreach ($pagamentosPorDiaSemana as $pagamento) {
        $diaSemana = strtolower($pagamento['dia_semana']);
        $dadosPorDiaSemana[$diaSemana][] = [
            'forma' => $pagamento['forma_pagamento'],
            'valor' => (float) $pagamento['valor_total']
        ];
    }

    // Aberturas de caixa
    $stmtAberturas = $pdo->prepare("
        SELECT 
            id,
            responsavel,
            numero_caixa,
            valor_abertura,
            valor_total,
            valor_sangrias,
            valor_suprimentos,
            valor_liquido,
            abertura_datetime,
            fechamento_datetime,
            quantidade_vendas,
            status
        FROM aberturas
        WHERE empresa_id = :empresa_id
        AND cpf_responsavel = :cpf_responsavel
        AND DATE(abertura_datetime) BETWEEN :data_inicial AND :data_final
        ORDER BY abertura_datetime DESC
    ");
    $stmtAberturas->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
    $stmtAberturas->bindParam(':cpf_responsavel', $cpfUsuario, PDO::PARAM_STR);
    $stmtAberturas->bindParam(':data_inicial', $dataInicial, PDO::PARAM_STR);
    $stmtAberturas->bindParam(':data_final', $dataFinal, PDO::PARAM_STR);
    $stmtAberturas->execute();
    $aberturas = $stmtAberturas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar dados: " . addslashes($e->getMessage()) . "');</script>";
    $resumoVendas = ['quantidade_vendas' => 0, 'valor_total' => 0];
    $movimentos = ['valor_sangrias' => 0, 'valor_suprimentos' => 0];
    $formasPagamento = [];
    $vendasPorDia = [];
    $aberturas = [];
    $dadosPorDiaSemana = [];
}

// Calcular valores para os cards
$totalVendas = $resumoVendas['valor_total'] ?? 0;
$valorLiquido = ($resumoVendas['valor_total'] ?? 0) + ($movimentos['valor_suprimentos'] ?? 0) - ($movimentos['valor_sangrias'] ?? 0);
$quantidadeVendas = $resumoVendas['quantidade_vendas'] ?? 0;
$ticketMedio = $quantidadeVendas > 0 ? $totalVendas / $quantidadeVendas : 0;

// Pré-processamento dos dados
foreach ($vendasPorDia as &$venda) {
    // Formatar a data no PHP antes de enviar para a view
    $venda['data_formatada'] = date('d/m', strtotime($venda['data']));

    // Garantir que os tipos numéricos estão corretos
    $venda['valor_total'] = (float) $venda['valor_total'];
    $venda['quantidade'] = (int) $venda['quantidade'];
}
unset($venda); // Quebra a referência

// Preparar dados para gráficos
$dadosGraficoLinha = [];
$dadosGraficoBarras = [];
$labelsDias = [];
$valoresDias = [];
$quantidadesDias = [];

foreach ($vendasPorDia as $venda) {
    $labelsDias[] = $venda['data_formatada'];
    $valoresDias[] = $venda['valor_total'];
    $quantidadesDias[] = $venda['quantidade'];

    $dadosGraficoLinha[] = [
        'data' => $venda['data'],
        'valor' => $venda['valor_total']
    ];

    $dadosGraficoBarras[] = [
        'data' => $venda['data'],
        'quantidade' => $venda['quantidade']
    ];
}

$dadosGraficoPizza = [];
foreach ($formasPagamento as $pagamento) {
    $dadosGraficoPizza[] = [
        'forma' => $pagamento['forma_pagamento'],
        'valor' => (float) $pagamento['valor_total']
    ];
}

// Mapear dias da semana em português
$diasSemana = [
    'sunday' => 'Domingo',
    'monday' => 'Segunda-feira',
    'tuesday' => 'Terça-feira',
    'wednesday' => 'Quarta-feira',
    'thursday' => 'Quinta-feira',
    'friday' => 'Sexta-feira',
    'saturday' => 'Sábado'
];

// Função para formatar moeda
function formatarMoeda($valor)
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}


?>

<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
    data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>ERP - PDV</title>

    <meta name="description" content="" />

    <!-- Favicon da empresa carregado dinamicamente -->
    <link rel="icon" type="image/x-icon"
        href="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>" />

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
                            style="text-transform: capitalize;">Açaínhadinhos</span>
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
                        <a href="index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Dashboard</div>
                        </a>
                    </li>
                    <!-- CAIXA -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Frente de Caixa</span>
                    </li>
                    <!-- Operações de Caixa -->
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-barcode-reader"></i>
                            <div data-i18n="Caixa">Operações de Caixa</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./abrirCaixa.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Abrir Caixa</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./fecharCaixa.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Fechar Caixa</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./sangria.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Sangria</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./suprimento.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Suprimento</div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <!-- Vendas -->
                    <li class="menu-item">
                        <a href="./vendaRapida.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-cart-alt"></i>
                            <div data-i18n="Vendas">Venda Rápida</div>
                        </a>
                    </li>
                    <!-- Cancelamento / Ajustes -->
                    <li class="menu-item">
                        <a href="./cancelarVenda.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-x-circle"></i>
                            <div data-i18n="Cancelamento">Cancelar Venda</div>
                        </a>
                    </li>
                    <!-- Relatórios -->
                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-bar-chart-alt"></i>
                            <div data-i18n="Relatórios">Relatórios</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item active">
                                <a href="./relatorioVendas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Resumo de Vendas</div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <!-- Misc -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
                    <li class="menu-item">
                        <a href="../sistemadeponto/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-group"></i>
                            <div data-i18n="Authentications">Sistema de Ponto</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../Delivery/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-cart"></i>
                            <div data-i18n="Basic">Delivery</div>
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
                                <input type="text" class="form-control border-0 shadow-none" placeholder="Search..."
                                    aria-label="Search..." />
                            </div>
                        </div>
                        <!-- /Search -->
                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <!-- User -->
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);"
                                    data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <img src="../../assets/img/avatars/1.png" alt
                                            class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="../../assets/img/avatars/1.png" alt
                                                            class="w-px-40 h-auto rounded-circle" />
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <span
                                                        class="fw-semibold d-block"><?= htmlspecialchars($nomeUsuario); ?></span>
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
                                            <span class="align-middle">Minha conta</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <i class="bx bx-cog me-2"></i>
                                            <span class="align-middle">Configurações</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <span class="d-flex align-items-center align-middle">
                                                <i class="flex-shrink-0 bx bx-credit-card me-2"></i>
                                                <span class="flex-grow-1 align-middle">Billing</span>
                                                <span
                                                    class="flex-shrink-0 badge badge-center rounded-pill bg-danger w-px-20 h-px-20">4</span>
                                            </span>
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

                <!-- Content wrapper -->
                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold mb-0">
                        <span class="text-muted fw-light">
                            <a href="#">Relatório</a> /
                        </span>
                        Resumo de Vendas
                    </h4>
                    <h5 class="fw-semibold mt-2 mb-4 text-muted">Visualize os Resumos de Vendas</h5>

                    <!-- Cards de Resumo -->
                    <div class="row mb-4">
                        <!-- Card 1 -->
                        <div class="col-md-4 mb-3">
                            <div class="card text-center h-100">
                                <div class="card-body">
                                    <img src="../../assets/img/icons/unicons/chart-success.png" alt="Total Vendas"
                                        class="mb-2" style="width:32px;">
                                    <div class="fw-semibold">Total de Vendas</div>
                                    <h4 class="mb-1"><?= formatarMoeda($totalVendas) ?></h4>
                                    <small class="text-muted"><?= $quantidadeVendas ?> vendas realizadas</small>
                                </div>
                            </div>
                        </div>

                        <!-- Card 2 -->
                        <div class="col-md-4 mb-3">
                            <div class="card text-center h-100">
                                <div class="card-body">
                                    <img src="../../assets/img/icons/unicons/wallet-info.png" alt="Valor Líquido"
                                        class="mb-2" style="width:32px;">
                                    <div class="fw-semibold">Valor Líquido</div>
                                    <h4 class="mb-1"><?= formatarMoeda($valorLiquido) ?></h4>
                                    <small class="text-muted">
                                        <?= formatarMoeda($movimentos['valor_suprimentos'] ?? 0) ?> suprimentos -
                                        <?= formatarMoeda($movimentos['valor_sangrias'] ?? 0) ?> sangrias
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Card 3 -->
                        <div class="col-md-4 mb-3">
                            <div class="card text-center h-100">
                                <div class="card-body">
                                    <img src="../../assets/img/icons/unicons/cc-primary.png" alt="Ticket Médio"
                                        class="mb-2" style="width:32px;">
                                    <div class="fw-semibold">Ticket Médio</div>
                                    <h4 class="mb-1"><?= formatarMoeda($ticketMedio) ?></h4>
                                    <small class="text-muted">Média por venda</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabela de Resumo de Vendas -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card shadow-sm">
                                <h5 class="card-header bg-primary text-white">Resumo de Vendas</h5>
                                <div class="card-body p-0">
                                    <div class="table-responsive text-nowrap">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Data</th>
                                                    <th>Quant. de Vendas</th>
                                                    <th>Valor Abertura</th>
                                                    <th>Valor Total</th>
                                                    <th>Sangria</th>
                                                    <th>Suprimento</th>
                                                    <th>Valor Líquido</th>
                                                    <th>Status</th>
                                                    <th>Detalhes</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($aberturas)): ?>
                                                    <tr>
                                                        <td colspan="9" class="text-center py-4">Nenhuma abertura de caixa
                                                            encontrada no período selecionado</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($aberturas as $abertura): ?>
                                                        <tr>
                                                            <td><?= date('d/m/Y', strtotime($abertura['abertura_datetime'])) ?>
                                                            </td>
                                                            <td><?= $abertura['quantidade_vendas'] ?></td>
                                                            <td><?= formatarMoeda($abertura['valor_abertura']) ?></td>
                                                            <td><?= formatarMoeda($abertura['valor_total']) ?></td>
                                                            <td><?= formatarMoeda($abertura['valor_sangrias']) ?></td>
                                                            <td><?= formatarMoeda($abertura['valor_suprimentos']) ?></td>
                                                            <td><?= formatarMoeda($abertura['valor_liquido']) ?></td>
                                                            <td>
                                                                <span
                                                                    class="badge <?= $abertura['status'] === 'aberto' ? 'bg-success' : 'bg-danger' ?>">
                                                                    <?= ucfirst($abertura['status']) ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <a href="./detalheVendas.php?id=<?= urlencode($idSelecionado); ?>&chave=<?= htmlspecialchars($abertura['id']) ?>"
                                                                    class="btn btn-sm btn-outline-primary" title="Ver detalhes">
                                                                    <i class="bx bx-show-alt"></i>
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Gráficos -->
                    <div class="container-fluid">
                        <div class="row mb-4">
                            <!-- Gráfico de Linha: Evolução das Vendas -->
                            <div class="col-12 col-md-8 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <h5 class="card-header">Evolução das Vendas
                                        (<?= date('d/m/Y', strtotime($dataInicial)) ?> a
                                        <?= date('d/m/Y', strtotime($dataFinal)) ?>)
                                    </h5>
                                    <div class="card-body">
                                        <div id="evolucaoDiariaChart" style="min-height: 350px;"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Gráfico de Pizza: Composição de Pagamento -->
                            <div class="col-12 col-md-4 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">Composição de Pagamento</h5>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                                type="button" id="relatorioPagamentosDropdown" data-bs-toggle="dropdown"
                                                aria-expanded="false">
                                                Filtro
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end"
                                                aria-labelledby="relatorioPagamentosDropdown">
                                                <li><a class="dropdown-item"
                                                        href="?id=<?= $idSelecionado ?>&filtro=mes_atual">Este mês</a>
                                                </li>
                                                <li><a class="dropdown-item"
                                                        href="?id=<?= $idSelecionado ?>&filtro=3_meses">Últimos 3
                                                        meses</a></li>
                                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal"
                                                        data-bs-target="#modalPersonalizar">Personalizar</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div id="graficoPizzaPagamento" style="min-height: 250px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal de Personalização de Período -->
                        <div class="modal fade" id="modalPersonalizar" tabindex="-1"
                            aria-labelledby="modalPersonalizarLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <form method="GET" class="modal-content">
                                    <input type="hidden" name="id" value="<?= $idSelecionado ?>">
                                    <input type="hidden" name="filtro" value="personalizado">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="modalPersonalizarLabel">Selecionar Período
                                            Personalizado</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                            aria-label="Fechar"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label for="dataInicialModal" class="form-label">Data Inicial</label>
                                            <input type="date" class="form-control" id="dataInicialModal" name="de"
                                                required value="<?= $dataInicial ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label for="dataFinalModal" class="form-label">Data Final</label>
                                            <input type="date" class="form-control" id="dataFinalModal" name="ate"
                                                required value="<?= $dataFinal ?>">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" class="btn btn-primary">Filtrar</button>
                                        <button type="button" class="btn btn-secondary"
                                            data-bs-dismiss="modal">Cancelar</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Gráfico de Barras e Relatório de Pagamentos -->
                        <div class="row mb-4">
                            <div class="col-12 col-md-8 mb-4">
                                <div class="card h-100">
                                    <div class="card-header d-flex align-items-center justify-content-between">
                                        <h5 class="card-title m-0">Volume de Vendas por Dia</h5>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                                type="button" id="dropdownDiasSemana" data-bs-toggle="dropdown"
                                                aria-expanded="false">
                                                <?php
                                                $diaSelecionado = $_GET['dia_semana'] ?? 'todos';
                                                $nomesDias = [
                                                    'todos' => 'Todos os Dias',
                                                    'sunday' => 'Domingo',
                                                    'monday' => 'Segunda-Feira',
                                                    'tuesday' => 'Terça-Feira',
                                                    'wednesday' => 'Quarta-Feira',
                                                    'thursday' => 'Quinta-Feira',
                                                    'friday' => 'Sexta-Feira',
                                                    'saturday' => 'Sábado'
                                                ];
                                                echo $nomesDias[$diaSelecionado] ?? 'Selecione o dia';
                                                ?>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end"
                                                aria-labelledby="dropdownDiasSemana">
                                                <li><a class="dropdown-item <?= ($diaSelecionado === 'todos') ? 'active' : '' ?>"
                                                        href="?id=<?= $idSelecionado ?>&dia_semana=todos&de=<?= $dataInicial ?>&ate=<?= $dataFinal ?>">Todos
                                                        os Dias</a>
                                                </li>
                                                <li><a class="dropdown-item <?= ($diaSelecionado === 'monday') ? 'active' : '' ?>"
                                                        href="?id=<?= $idSelecionado ?>&dia_semana=monday&de=<?= $dataInicial ?>&ate=<?= $dataFinal ?>">Segunda-Feira</a>
                                                </li>
                                                <li><a class="dropdown-item <?= ($diaSelecionado === 'tuesday') ? 'active' : '' ?>"
                                                        href="?id=<?= $idSelecionado ?>&dia_semana=tuesday&de=<?= $dataInicial ?>&ate=<?= $dataFinal ?>">Terça-Feira</a>
                                                </li>
                                                <li><a class="dropdown-item <?= ($diaSelecionado === 'wednesday') ? 'active' : '' ?>"
                                                        href="?id=<?= $idSelecionado ?>&dia_semana=wednesday&de=<?= $dataInicial ?>&ate=<?= $dataFinal ?>">Quarta-Feira</a>
                                                </li>
                                                <li><a class="dropdown-item <?= ($diaSelecionado === 'thursday') ? 'active' : '' ?>"
                                                        href="?id=<?= $idSelecionado ?>&dia_semana=thursday&de=<?= $dataInicial ?>&ate=<?= $dataFinal ?>">Quinta-Feira</a>
                                                </li>
                                                <li><a class="dropdown-item <?= ($diaSelecionado === 'friday') ? 'active' : '' ?>"
                                                        href="?id=<?= $idSelecionado ?>&dia_semana=friday&de=<?= $dataInicial ?>&ate=<?= $dataFinal ?>">Sexta-Feira</a>
                                                </li>
                                                <li><a class="dropdown-item <?= ($diaSelecionado === 'saturday') ? 'active' : '' ?>"
                                                        href="?id=<?= $idSelecionado ?>&dia_semana=saturday&de=<?= $dataInicial ?>&ate=<?= $dataFinal ?>">Sábado</a>
                                                </li>
                                                <li><a class="dropdown-item <?= ($diaSelecionado === 'sunday') ? 'active' : '' ?>"
                                                        href="?id=<?= $idSelecionado ?>&dia_semana=sunday&de=<?= $dataInicial ?>&ate=<?= $dataFinal ?>">Domingo</a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div id="graficoBarrasQuantidade" style="min-height: 300px;"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-4 mb-4">
                                <div class="card h-100 d-flex flex-column">
                                    <div class="card-header">
                                        <h5 class="card-title m-0">Formas de Pagamento por Dia</h5>
                                    </div>
                                    <div class="card-body flex-grow-1">
                                        <div id="graficoPizzaPagamentoNoCard" style="height: 300px; max-width: 100%;">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- /Gráficos -->

                </div>
                <!-- Fim do Content wrapper -->

                <!-- Scripts para os gráficos -->
                <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const dadosGraficoPizza = <?= json_encode($dadosGraficoPizza) ?>;
                        const dadosPorDiaSemana = <?= json_encode($dadosPorDiaSemana) ?>;
                        const diasSemana = <?= json_encode($diasSemana) ?>;
                        const diaSelecionado = '<?= $_GET['dia_semana'] ?? 'todos' ?>';

                        // Mapeamento de dias em inglês para português
                        const diasTraducao = {
                            'sunday': 'Domingo',
                            'monday': 'Segunda-feira',
                            'tuesday': 'Terça-feira',
                            'wednesday': 'Quarta-feira',
                            'thursday': 'Quinta-feira',
                            'friday': 'Sexta-feira',
                            'saturday': 'Sábado',
                            'todos': 'Todos os Dias'
                        };

                        // Função para filtrar vendas por dia da semana
                        function filtrarVendasPorDia(dia) {
                            if (dia === 'todos') {
                                // Consolida todas as formas de pagamento de todos os dias
                                const formasPagamento = {};
                                <?php foreach ($formasPagamento as $pagamento): ?>
                                    formasPagamento['<?= $pagamento['forma_pagamento'] ?>'] = <?= (float) $pagamento['valor_total'] ?>;
                                <?php endforeach; ?>

                                return {
                                    labels: <?= json_encode($labelsDias) ?>,
                                    valores: <?= json_encode($valoresDias) ?>,
                                    quantidades: <?= json_encode($quantidadesDias) ?>,
                                    formasPagamento: Object.entries(formasPagamento).map(([forma, valor]) => ({ forma, valor }))
                                };
                            }

                            // Filtra por dia específico
                            const vendasFiltradas = <?= json_encode($vendasPorDia) ?>.filter(venda => {
                                const diaVenda = venda.dia_semana.toLowerCase();
                                return diaVenda === dia;
                            });

                            // Retorna os dados filtrados
                            return {
                                labels: vendasFiltradas.map(venda => venda.data_formatada),
                                valores: vendasFiltradas.map(venda => parseFloat(venda.valor_total)),
                                quantidades: vendasFiltradas.map(venda => parseInt(venda.quantidade)),
                                formasPagamento: dadosPorDiaSemana[dia] || []
                            };
                        }

                        // Gráfico de Linha - Evolução Diária
                        const optionsLinha = {
                            series: [{
                                name: 'Valor de Vendas',
                                data: <?= json_encode($valoresDias) ?>
                            }],
                            chart: {
                                height: 350,
                                type: 'line',
                                zoom: {
                                    enabled: true
                                },
                                toolbar: {
                                    show: true
                                }
                            },
                            dataLabels: {
                                enabled: false
                            },
                            stroke: {
                                curve: 'smooth',
                                width: 3
                            },
                            colors: ['#7367F0'],
                            xaxis: {
                                categories: <?= json_encode($labelsDias) ?>
                            },
                            yaxis: {
                                labels: {
                                    formatter: val => 'R$ ' + val.toLocaleString('pt-BR', {
                                        minimumFractionDigits: 2
                                    })
                                }
                            },
                            tooltip: {
                                y: {
                                    formatter: val => 'R$ ' + val.toLocaleString('pt-BR', {
                                        minimumFractionDigits: 2
                                    })
                                }
                            },
                            responsive: [{
                                breakpoint: 768,
                                options: {
                                    chart: {
                                        height: 300
                                    },
                                }
                            }]
                        };
                        const chartLinha = new ApexCharts(document.querySelector("#evolucaoDiariaChart"), optionsLinha);
                        chartLinha.render();

                        // Gráfico de Pizza - Formas de Pagamento
                        const labelsPizza = dadosGraficoPizza.map(item => item.forma);
                        const valoresPizza = dadosGraficoPizza.map(item => item.valor);

                        const optionsPizza = {
                            series: valoresPizza,
                            chart: {
                                type: 'pie',
                                height: 350
                            },
                            labels: labelsPizza,
                            colors: ['#7367F0', '#28C76F', '#EA5455', '#FF9F43', '#00CFE8'],
                            tooltip: {
                                y: {
                                    formatter: val => 'R$ ' + val.toLocaleString('pt-BR', {
                                        minimumFractionDigits: 2
                                    })
                                }
                            },
                            responsive: [{
                                breakpoint: 768,
                                options: {
                                    chart: {
                                        height: 280
                                    },
                                    legend: {
                                        position: 'bottom'
                                    }
                                }
                            }, {
                                breakpoint: 480,
                                options: {
                                    chart: {
                                        height: 250
                                    },
                                    legend: {
                                        position: 'bottom'
                                    }
                                }
                            }]
                        };
                        const chartPizza = new ApexCharts(document.querySelector("#graficoPizzaPagamento"), optionsPizza);
                        chartPizza.render();

                        // Inicializar gráficos de barras e pizza por dia
                        let chartBarras = null;
                        let chartPizzaDia = null;

                        function atualizarGraficosPorDia(dia) {
                            const vendasFiltradas = filtrarVendasPorDia(dia);

                            // Atualiza gráfico de barras
                            if (chartBarras) {
                                chartBarras.updateOptions({
                                    xaxis: {
                                        categories: vendasFiltradas.labels
                                    },
                                    series: [{
                                        data: vendasFiltradas.quantidades
                                    }]
                                });
                            } else {
                                const optionsBarras = {
                                    series: [{
                                        name: 'Quantidade de Vendas',
                                        data: vendasFiltradas.quantidades
                                    }],
                                    chart: {
                                        type: 'bar',
                                        height: 350,
                                        toolbar: {
                                            show: true
                                        }
                                    },
                                    plotOptions: {
                                        bar: {
                                            borderRadius: 4,
                                            horizontal: false
                                        }
                                    },
                                    dataLabels: {
                                        enabled: false
                                    },
                                    colors: ['#28C76F'],
                                    xaxis: {
                                        categories: vendasFiltradas.labels
                                    },
                                    yaxis: {
                                        title: {
                                            text: 'Quantidade de Vendas'
                                        }
                                    },
                                    responsive: [{
                                        breakpoint: 768,
                                        options: {
                                            chart: {
                                                height: 300
                                            }
                                        }
                                    }]
                                };
                                chartBarras = new ApexCharts(document.querySelector("#graficoBarrasQuantidade"), optionsBarras);
                                chartBarras.render();
                            }

                            // Atualiza gráfico de pizza por dia
                            const containerPizza = document.querySelector("#graficoPizzaPagamentoNoCard");
                            containerPizza.innerHTML = '<div></div>'; // Limpa o container

                            const dadosPizza = dia === 'todos' ? vendasFiltradas.formasPagamento : dadosPorDiaSemana[dia] || [];

                            if (dadosPizza.length === 0) {
                                containerPizza.innerHTML = `<div class="text-center py-4 text-muted">Nenhum dado disponível para ${diasTraducao[dia] || 'o período selecionado'}</div>`;
                                if (chartPizzaDia) {
                                    chartPizzaDia.destroy();
                                    chartPizzaDia = null;
                                }
                                return;
                            }

                            const optionsPizzaDia = {
                                series: dadosPizza.map(item => item.valor),
                                chart: {
                                    type: 'pie',
                                    height: 300
                                },
                                labels: dadosPizza.map(item => item.forma),
                                colors: ['#7367F0', '#28C76F', '#EA5455', '#FF9F43', '#00CFE8'],
                                tooltip: {
                                    y: {
                                        formatter: val => 'R$ ' + val.toLocaleString('pt-BR', {
                                            minimumFractionDigits: 2
                                        })
                                    }
                                },
                                title: {
                                    text: dia === 'todos' ? 'Todas as Formas de Pagamento' : diasTraducao[dia],
                                    align: 'center',
                                    style: {
                                        fontSize: '16px',
                                        fontWeight: 'bold'
                                    }
                                }
                            };

                            if (chartPizzaDia) {
                                chartPizzaDia.updateOptions(optionsPizzaDia);
                            } else {
                                chartPizzaDia = new ApexCharts(containerPizza.firstChild, optionsPizzaDia);
                                chartPizzaDia.render();
                            }
                        }

                        // Inicializar com o dia selecionado ou 'todos'
                        atualizarGraficosPorDia(diaSelecionado);

                        // Event listeners para filtros de dia
                        document.querySelectorAll('[data-filtro-dia]').forEach(btn => {
                            btn.addEventListener('click', function () {
                                const dia = this.getAttribute('data-filtro-dia');
                                atualizarGraficosPorDia(dia);

                                // Atualizar classe ativa
                                document.querySelectorAll('[data-filtro-dia]').forEach(el => {
                                    el.classList.remove('active');
                                });
                                this.classList.add('active');
                            });
                        });

                        // Opcional: atualizar gráficos ao redimensionar a janela
                        window.addEventListener('resize', () => {
                            chartLinha && chartLinha.resize();
                            chartPizza && chartPizza.resize();
                            chartBarras && chartBarras.resize();
                            chartPizzaDia && chartPizzaDia.resize();
                        });
                    });
                </script>
                <!--- / Scripts para os gráficos -->

            </div>

        </div>
        <!-- / Content wrapper -->
    </div>
    <!-- / Layout page -->
    </div>
    <!-- / Layout container -->
    </div>
    <!-- / Layout wrapper -->

    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../assets/vendor/js/menu.js"></script>
    <script src="../../assets/js/main.js"></script>
</body>

</html>
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
    !isset($_SESSION['nivel']) || // Verifica se o nível está na sessão
    !isset($_SESSION['usuario_cpf']) // Verifica se o CPF está na sessão
) {
    header("Location: ../index.php?id=$idSelecionado");
    exit;
}

// ✅ Conexão com o banco de dados
require '../../assets/php/conexao.php';

/** Helpers **/
function soDigitos(string $v): string
{
    return preg_replace('/\D+/', '', $v) ?? '';
}
function formatarMoeda($valor)
{
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

$nomeUsuario        = 'Usuário';
$tipoUsuario        = 'Comum';
$usuario_id         = (int)$_SESSION['usuario_id'];
$usuario_cpf_masc   = (string)$_SESSION['usuario_cpf']; // Pode vir com pontuação
$cpfUsuario         = soDigitos($usuario_cpf_masc);     // Só dígitos para comparar
$tipoUsuarioSessao  = $_SESSION['nivel']; // "Admin" ou "Comum"

try {
    // Verifica se é um usuário de contas_acesso (Admin) ou funcionarios_acesso
    if ($tipoUsuarioSessao === 'Admin') {
        $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    } else {
        $stmt = $pdo->prepare("SELECT usuario, nivel FROM funcionarios_acesso WHERE id = :id");
    }

    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $nomeUsuario = $usuario['usuario'];
        $tipoUsuario = ucfirst($usuario['nivel']);
    } else {
        echo "<script>alert('Usuário não encontrado.'); window.location.href = './index.php?id=$idSelecionado';</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar nome e tipo do usuário: " . addslashes($e->getMessage()) . "'); history.back();</script>";
    exit;
}

// ✅ Valida o tipo de empresa e o acesso permitido
if (str_starts_with($idSelecionado, 'principal_')) {
    if (
        $_SESSION['tipo_empresa'] !== 'principal' &&
        !($tipoUsuarioSessao === 'Admin' && $_SESSION['empresa_id'] === 'principal_1')
    ) {
        echo "<script>
            alert('Acesso negado!');
            window.location.href = '../index.php?id=$idSelecionado';
        </script>";
        exit;
    }
    $id = 1;
} elseif (str_starts_with($idSelecionado, 'unidade_')) {
    $idUnidade = str_replace('unidade_', '', $idSelecionado);
    $acessoPermitido = ($_SESSION['empresa_id'] === $idSelecionado) ||
        ($tipoUsuarioSessao === 'Admin' && $_SESSION['empresa_id'] === 'principal_1');
    if (!$acessoPermitido) {
        echo "<script>
            alert('Acesso negado!');
            window.location.href = '../index.php?id=$idSelecionado';
        </script>";
        exit;
    }
    $id = $idUnidade;
} else {
    echo "<script>
        alert('Empresa não identificada!');
        window.location.href = '../index.php?id=$idSelecionado';
    </script>";
    exit;
}

// ✅ Buscar imagem da empresa para usar como favicon (opcional)
$iconeEmpresa = '../../assets/img/favicon/favicon.ico';
try {
    $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1");
    $stmt->bindParam(':id_selecionado', $idSelecionado);
    $stmt->execute();
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($empresa && !empty($empresa['imagem'])) {
        $iconeEmpresa = $empresa['imagem'];
    }
} catch (PDOException $e) {
    error_log("Erro ao carregar ícone da empresa: " . $e->getMessage());
}

// ✅ Função para buscar o nome do funcionário pelo CPF (opcional)
function obterNomeFuncionario($pdo, $cpf)
{
    try {
        $stmt = $pdo->prepare("SELECT usuario AS nome FROM funcionarios_acesso WHERE REPLACE(REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),'/',''),' ','') = :cpf LIMIT 1");
        $stmt->bindParam(':cpf', $cpf, PDO::PARAM_STR);
        $stmt->execute();
        $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);
        return $funcionario['nome'] ?? 'Funcionário não identificado';
    } catch (PDOException $e) {
        return 'Erro ao buscar nome';
    }
}

// ✅ Nome do funcionário (se desejar exibir)
$nomeFuncionario = !empty($cpfUsuario) ? obterNomeFuncionario($pdo, $cpfUsuario) : $nomeUsuario;

// ✅ Processar filtros de data
$filtroData = $_GET['filtro'] ?? 'mes_atual';
$dataInicial = '';
$dataFinal = '';

switch ($filtroData) {
    case 'mes_atual':
        $dataInicial = date('Y-m-01');
        $dataFinal   = date('Y-m-t');
        break;
    case '3_meses':
        $dataInicial = date('Y-m-01', strtotime('-2 months'));
        $dataFinal   = date('Y-m-t');
        break;
    case 'personalizado':
        $dataInicial = $_GET['de']  ?? date('Y-m-01');
        $dataFinal   = $_GET['ate'] ?? date('Y-m-t');
        break;
    default:
        $dataInicial = date('Y-m-01');
        $dataFinal   = date('Y-m-t');
}

// ========================== CONSULTAS PRINCIPAIS ==========================
// Observação: usamos REPLACE() para comparar CPF sem máscara no banco.
// Fallback: se não houver CPF, usamos o nome do responsável.
try {
    // Total de vendas + quantidade
    $stmtVendas = $pdo->prepare("
      SELECT 
          COUNT(*)                        AS quantidade_vendas,
          COALESCE(SUM(valor_total), 0)  AS valor_total
        FROM vendas
       WHERE empresa_id = :empresa_id
         AND (
              (:cpf <> '' AND REPLACE(REPLACE(REPLACE(REPLACE(cpf_responsavel, '.', ''), '-', ''), '/', ''), ' ', '') = :cpf)
           OR (:cpf = ''  AND responsavel = :responsavel)
         )
         AND DATE(data_venda) BETWEEN :data_inicial AND :data_final
  ");
    $stmtVendas->execute([
        ':empresa_id'   => $idSelecionado,
        ':cpf'          => $cpfUsuario,
        ':responsavel'  => $nomeUsuario,
        ':data_inicial' => $dataInicial,
        ':data_final'   => $dataFinal,
    ]);
    $resumoVendas = $stmtVendas->fetch(PDO::FETCH_ASSOC) ?: ['quantidade_vendas' => 0, 'valor_total' => 0];

    // Sangrias e Suprimentos (aberturas)
    $stmtMovimentos = $pdo->prepare("
      SELECT 
          COALESCE(SUM(valor_sangrias), 0)     AS valor_sangrias,
          COALESCE(SUM(valor_suprimentos), 0)  AS valor_suprimentos
        FROM aberturas
       WHERE empresa_id = :empresa_id
         AND (
              (:cpf <> '' AND REPLACE(REPLACE(REPLACE(REPLACE(cpf_responsavel, '.', ''), '-', ''), '/', ''), ' ', '') = :cpf)
           OR (:cpf = ''  AND responsavel = :responsavel)
         )
         AND DATE(abertura_datetime) BETWEEN :data_inicial AND :data_final
  ");
    $stmtMovimentos->execute([
        ':empresa_id'   => $idSelecionado,
        ':cpf'          => $cpfUsuario,
        ':responsavel'  => $nomeUsuario,
        ':data_inicial' => $dataInicial,
        ':data_final'   => $dataFinal,
    ]);
    $movimentos = $stmtMovimentos->fetch(PDO::FETCH_ASSOC) ?: ['valor_sangrias' => 0, 'valor_suprimentos' => 0];

    // Formas de pagamento
    $stmtPagamentos = $pdo->prepare("
      SELECT 
          forma_pagamento,
          COUNT(*)                       AS quantidade,
          COALESCE(SUM(valor_total), 0)  AS valor_total
        FROM vendas
       WHERE empresa_id = :empresa_id
         AND (
              (:cpf <> '' AND REPLACE(REPLACE(REPLACE(REPLACE(cpf_responsavel, '.', ''), '-', ''), '/', ''), ' ', '') = :cpf)
           OR (:cpf = ''  AND responsavel = :responsavel)
         )
         AND DATE(data_venda) BETWEEN :data_inicial AND :data_final
       GROUP BY forma_pagamento
       ORDER BY forma_pagamento
  ");
    $stmtPagamentos->execute([
        ':empresa_id'   => $idSelecionado,
        ':cpf'          => $cpfUsuario,
        ':responsavel'  => $nomeUsuario,
        ':data_inicial' => $dataInicial,
        ':data_final'   => $dataFinal,
    ]);
    $formasPagamento = $stmtPagamentos->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Vendas por dia (para gráficos)
    $stmtVendasDia = $pdo->prepare("
      SELECT 
          DATE(data_venda)                         AS data,
          COUNT(*)                                 AS quantidade,
          COALESCE(SUM(valor_total), 0)            AS valor_total,
          DAYNAME(data_venda)                      AS dia_semana
        FROM vendas
       WHERE empresa_id = :empresa_id
         AND (
              (:cpf <> '' AND REPLACE(REPLACE(REPLACE(REPLACE(cpf_responsavel, '.', ''), '-', ''), '/', ''), ' ', '') = :cpf)
           OR (:cpf = ''  AND responsavel = :responsavel)
         )
         AND DATE(data_venda) BETWEEN :data_inicial AND :data_final
       GROUP BY DATE(data_venda)
       ORDER BY DATE(data_venda)
  ");
    $stmtVendasDia->execute([
        ':empresa_id'   => $idSelecionado,
        ':cpf'          => $cpfUsuario,
        ':responsavel'  => $nomeUsuario,
        ':data_inicial' => $dataInicial,
        ':data_final'   => $dataFinal,
    ]);
    $vendasPorDia = $stmtVendasDia->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Formas de pagamento por dia da semana
    $stmtPagamentosDiaSemana = $pdo->prepare("
      SELECT 
          DAYNAME(data_venda)                      AS dia_semana,
          forma_pagamento,
          COUNT(*)                                 AS quantidade,
          COALESCE(SUM(valor_total), 0)            AS valor_total
        FROM vendas
       WHERE empresa_id = :empresa_id
         AND (
              (:cpf <> '' AND REPLACE(REPLACE(REPLACE(REPLACE(cpf_responsavel, '.', ''), '-', ''), '/', ''), ' ', '') = :cpf)
           OR (:cpf = ''  AND responsavel = :responsavel)
         )
         AND DATE(data_venda) BETWEEN :data_inicial AND :data_final
       GROUP BY DAYNAME(data_venda), forma_pagamento
       ORDER BY FIELD(DAYNAME(data_venda), 'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday')
  ");
    $stmtPagamentosDiaSemana->execute([
        ':empresa_id'   => $idSelecionado,
        ':cpf'          => $cpfUsuario,
        ':responsavel'  => $nomeUsuario,
        ':data_inicial' => $dataInicial,
        ':data_final'   => $dataFinal,
    ]);
    $pagamentosPorDiaSemana = $stmtPagamentosDiaSemana->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Aberturas de caixa (lista)
    $stmtAberturas = $pdo->prepare("
      SELECT 
          id,
          responsavel,
          numero_caixa,
          COALESCE(valor_abertura,0)     AS valor_abertura,
          COALESCE(valor_total,0)        AS valor_total,
          COALESCE(valor_sangrias,0)     AS valor_sangrias,
          COALESCE(valor_suprimentos,0)  AS valor_suprimentos,
          COALESCE(valor_liquido,0)      AS valor_liquido,
          abertura_datetime,
          fechamento_datetime,
          COALESCE(quantidade_vendas,0)  AS quantidade_vendas,
          status
        FROM aberturas
       WHERE empresa_id = :empresa_id
         AND (
              (:cpf <> '' AND REPLACE(REPLACE(REPLACE(REPLACE(cpf_responsavel, '.', ''), '-', ''), '/', ''), ' ', '') = :cpf)
           OR (:cpf = ''  AND responsavel = :responsavel)
         )
         AND DATE(abertura_datetime) BETWEEN :data_inicial AND :data_final
       ORDER BY abertura_datetime DESC
  ");
    $stmtAberturas->execute([
        ':empresa_id'   => $idSelecionado,
        ':cpf'          => $cpfUsuario,
        ':responsavel'  => $nomeUsuario,
        ':data_inicial' => $dataInicial,
        ':data_final'   => $dataFinal,
    ]);
    $aberturas = $stmtAberturas->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar dados: " . addslashes($e->getMessage()) . "');</script>";
    $resumoVendas = ['quantidade_vendas' => 0, 'valor_total' => 0];
    $movimentos = ['valor_sangrias' => 0, 'valor_suprimentos' => 0];
    $formasPagamento = [];
    $vendasPorDia = [];
    $aberturas = [];
    $pagamentosPorDiaSemana = [];
}

// ===================== Pré-processamento para os gráficos =====================
$totalVendas       = (float)($resumoVendas['valor_total'] ?? 0);
$quantidadeVendas  = (int)  ($resumoVendas['quantidade_vendas'] ?? 0);
$ticketMedio       = $quantidadeVendas > 0 ? $totalVendas / $quantidadeVendas : 0.0;
$valorSupr         = (float)($movimentos['valor_suprimentos'] ?? 0);
$valorSang         = (float)($movimentos['valor_sangrias'] ?? 0);
$valorLiquido      = $totalVendas + $valorSupr - $valorSang;

// Vendas por dia
$labelsDias = [];
$valoresDias = [];
$quantidadesDias = [];
foreach ($vendasPorDia as $venda) {
    $labelsDias[]      = date('d/m', strtotime($venda['data']));
    $valoresDias[]     = (float)$venda['valor_total'];
    $quantidadesDias[] = (int)$venda['quantidade'];
}

// Pizza de formas de pagamento (total período)
$dadosGraficoPizza = [];
foreach ($formasPagamento as $pagamento) {
    $dadosGraficoPizza[] = [
        'forma' => $pagamento['forma_pagamento'],
        'valor' => (float)$pagamento['valor_total']
    ];
}

// Por dia da semana (para o segundo card de pizza)
$dadosPorDiaSemana = [];
foreach ($pagamentosPorDiaSemana as $pag) {
    $dia = strtolower($pag['dia_semana']); // sunday..saturday
    if (!isset($dadosPorDiaSemana[$dia])) $dadosPorDiaSemana[$dia] = [];
    $dadosPorDiaSemana[$dia][] = [
        'forma' => $pag['forma_pagamento'],
        'valor' => (float)$pag['valor_total']
    ];
}

// Mapeamento de dias em português (uso no front)
$diasSemana = [
    'sunday'    => 'Domingo',
    'monday'    => 'Segunda-feira',
    'tuesday'   => 'Terça-feira',
    'wednesday' => 'Quarta-feira',
    'thursday'  => 'Quinta-feira',
    'friday'    => 'Sexta-feira',
    'saturday'  => 'Sábado'
];

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

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

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
                        <a href="./delivery/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-cart"></i>
                            <div data-i18n="Authentications">Delivery</div>
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

                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <!-- Place this tag where you want the button to render. -->
                            <!-- User -->
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <img src="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>" alt
                                            class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>" alt
                                                            class="w-px-40 h-auto rounded-circle" />
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <span class="fw-semibold d-block"><?= htmlspecialchars($nomeUsuario); ?></span>
                                                </div>
                                            </div>
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
                                    <i class="fa-solid fa-chart-line text-success mb-2" style="font-size:32px;"></i>
                                    <div class="fw-semibold">Total de Vendas</div>
                                    <h4 class="mb-1"><?= formatarMoeda($totalVendas) ?></h4>
                                    <small class="text-muted"><?= (int)$quantidadeVendas ?> vendas realizadas</small>
                                </div>
                            </div>
                        </div>

                        <!-- Card 2 -->
                        <div class="col-md-4 mb-3">
                            <div class="card text-center h-100">
                                <div class="card-body">
                                    <i class="fa-solid fa-coins text-primary mb-2" style="font-size:32px;"></i>
                                    <div class="fw-semibold">Valor Líquido</div>
                                    <h4 class="mb-1"><?= formatarMoeda($valorLiquido) ?></h4>
                                    <small class="text-muted">
                                        <?= formatarMoeda($valorSupr) ?> suprimentos -
                                        <?= formatarMoeda($valorSang) ?> sangrias
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Card 3 -->
                        <div class="col-md-4 mb-3">
                            <div class="card text-center h-100">
                                <div class="card-body">
                                    <i class="fa-solid fa-ticket-simple text-warning mb-2" style="font-size:32px;"></i>
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
                                                        <td colspan="9" class="text-center py-4">Nenhuma abertura de caixa encontrada no período selecionado</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($aberturas as $abertura): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars(date('d/m/Y', strtotime($abertura['abertura_datetime']))) ?></td>
                                                            <td><?= (int)$abertura['quantidade_vendas'] ?></td>
                                                            <td><?= formatarMoeda($abertura['valor_abertura']) ?></td>
                                                            <td><?= formatarMoeda($abertura['valor_total']) ?></td>
                                                            <td><?= formatarMoeda($abertura['valor_sangrias']) ?></td>
                                                            <td><?= formatarMoeda($abertura['valor_suprimentos']) ?></td>
                                                            <td><?= formatarMoeda($abertura['valor_liquido']) ?></td>
                                                            <td>
                                                                <span class="badge <?= $abertura['status'] === 'aberto' ? 'bg-success' : 'bg-danger' ?>">
                                                                    <?= htmlspecialchars(ucfirst($abertura['status'])) ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <a href="./detalheVendas.php?id=<?= urlencode($idSelecionado); ?>&chave=<?= htmlspecialchars((string)$abertura['id']) ?>"
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
                                        (<?= date('d/m/Y', strtotime($dataInicial)) ?> a <?= date('d/m/Y', strtotime($dataFinal)) ?>)
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
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="relatorioPagamentosDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                                Filtro
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="relatorioPagamentosDropdown">
                                                <li><a class="dropdown-item" href="?id=<?= $idSelecionado ?>&filtro=mes_atual">Este mês</a></li>
                                                <li><a class="dropdown-item" href="?id=<?= $idSelecionado ?>&filtro=3_meses">Últimos 3 meses</a></li>
                                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalPersonalizar">Personalizar</a></li>
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
                        <div class="modal fade" id="modalPersonalizar" tabindex="-1" aria-labelledby="modalPersonalizarLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <form method="GET" class="modal-content">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($idSelecionado) ?>">
                                    <input type="hidden" name="filtro" value="personalizado">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="modalPersonalizarLabel">Selecionar Período Personalizado</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label for="dataInicialModal" class="form-label">Data Inicial</label>
                                            <input type="date" class="form-control" id="dataInicialModal" name="de" required value="<?= htmlspecialchars($dataInicial) ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label for="dataFinalModal" class="form-label">Data Final</label>
                                            <input type="date" class="form-control" id="dataFinalModal" name="ate" required value="<?= htmlspecialchars($dataFinal) ?>">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" class="btn btn-primary">Filtrar</button>
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
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
                                            <?php
                                            $diaSelecionado = $_GET['dia_semana'] ?? 'todos';
                                            $nomesDias = [
                                                'todos'    => 'Todos os Dias',
                                                'sunday'   => 'Domingo',
                                                'monday'   => 'Segunda-Feira',
                                                'tuesday'  => 'Terça-Feira',
                                                'wednesday' => 'Quarta-Feira',
                                                'thursday' => 'Quinta-Feira',
                                                'friday'   => 'Sexta-Feira',
                                                'saturday' => 'Sábado'
                                            ];
                                            ?>
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownDiasSemana" data-bs-toggle="dropdown" aria-expanded="false">
                                                <?= $nomesDias[$diaSelecionado] ?? 'Selecione o dia' ?>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownDiasSemana">
                                                <li><a class="dropdown-item <?= ($diaSelecionado === 'todos') ? 'active' : '' ?>" href="?id=<?= $idSelecionado ?>&dia_semana=todos&de=<?= $dataInicial ?>&ate=<?= $dataFinal ?>">Todos os Dias</a></li>
                                                <li><a class="dropdown-item <?= ($diaSelecionado === 'monday') ? 'active' : '' ?>" href="?id=<?= $idSelecionado ?>&dia_semana=monday&de=<?= $dataInicial ?>&ate=<?= $dataFinal ?>">Segunda-Feira</a></li>
                                                <li><a class="dropdown-item <?= ($diaSelecionado === 'tuesday') ? 'active' : '' ?>" href="?id=<?= $idSelecionado ?>&dia_semana=tuesday&de=<?= $dataInicial ?>&ate=<?= $dataFinal ?>">Terça-Feira</a></li>
                                                <li><a class="dropdown-item <?= ($diaSelecionado === 'wednesday') ? 'active' : '' ?>" href="?id=<?= $idSelecionado ?>&dia_semana=wednesday&de=<?= $dataInicial ?>&ate=<?= $dataFinal ?>">Quarta-Feira</a></li>
                                                <li><a class="dropdown-item <?= ($diaSelecionado === 'thursday') ? 'active' : '' ?>" href="?id=<?= $idSelecionado ?>&dia_semana=thursday&de=<?= $dataInicial ?>&ate=<?= $dataFinal ?>">Quinta-Feira</a></li>
                                                <li><a class="dropdown-item <?= ($diaSelecionado === 'friday') ? 'active' : '' ?>" href="?id=<?= $idSelecionado ?>&dia_semana=friday&de=<?= $dataInicial ?>&ate=<?= $dataFinal ?>">Sexta-Feira</a></li>
                                                <li><a class="dropdown-item <?= ($diaSelecionado === 'saturday') ? 'active' : '' ?>" href="?id=<?= $idSelecionado ?>&dia_semana=saturday&de=<?= $dataInicial ?>&ate=<?= $dataFinal ?>">Sábado</a></li>
                                                <li><a class="dropdown-item <?= ($diaSelecionado === 'sunday') ? 'active' : '' ?>" href="?id=<?= $idSelecionado ?>&dia_semana=sunday&de=<?= $dataInicial ?>&ate=<?= $dataFinal ?>">Domingo</a></li>
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
                                        <div id="graficoPizzaPagamentoNoCard" style="height: 300px; max-width: 100%;"></div>
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
                    document.addEventListener('DOMContentLoaded', function() {
                        const dadosGraficoPizza = <?= json_encode($dadosGraficoPizza) ?>;
                        const dadosPorDiaSemana = <?= json_encode($dadosPorDiaSemana) ?>;
                        const labelsDias = <?= json_encode($labelsDias) ?>;
                        const valoresDias = <?= json_encode($valoresDias) ?>;
                        const quantidadesDias = <?= json_encode($quantidadesDias) ?>;
                        const diaSelecionado = '<?= $_GET['dia_semana'] ?? 'todos' ?>';

                        // Gráfico de Linha - Evolução Diária
                        const optionsLinha = {
                            series: [{
                                name: 'Valor de Vendas',
                                data: valoresDias
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
                                categories: labelsDias
                            },
                            yaxis: {
                                labels: {
                                    formatter: val => 'R$ ' + Number(val).toLocaleString('pt-BR', {
                                        minimumFractionDigits: 2
                                    })
                                }
                            },
                            tooltip: {
                                y: {
                                    formatter: val => 'R$ ' + Number(val).toLocaleString('pt-BR', {
                                        minimumFractionDigits: 2
                                    })
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
                        const chartLinha = new ApexCharts(document.querySelector("#evolucaoDiariaChart"), optionsLinha);
                        chartLinha.render();

                        // Gráfico de Pizza - Composição de Pagamento (total período)
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
                                    formatter: val => 'R$ ' + Number(val).toLocaleString('pt-BR', {
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
                                },
                                {
                                    breakpoint: 480,
                                    options: {
                                        chart: {
                                            height: 250
                                        },
                                        legend: {
                                            position: 'bottom'
                                        }
                                    }
                                }
                            ]
                        };
                        const chartPizza = new ApexCharts(document.querySelector("#graficoPizzaPagamento"), optionsPizza);
                        chartPizza.render();

                        // ========= Gráfico de Barras + Pizza por DIA SELECIONADO =========
                        let chartBarras = null;
                        let chartPizzaDia = null;

                        function filtrarPorDia(dia) {
                            if (dia === 'todos') {
                                return {
                                    labels: labelsDias,
                                    valores: valoresDias,
                                    quantidades: quantidadesDias,
                                    formasPagamento: dadosGraficoPizza // usa o total do período
                                };
                            }
                            // filtra por dia específico
                            const mapData = <?= json_encode($vendasPorDia) ?>;
                            const filtradas = mapData.filter(v => v.dia_semana && v.dia_semana.toLowerCase() === dia);
                            return {
                                labels: filtradas.map(v => {
                                    const d = new Date(v.data);
                                    const dd = String(d.getDate()).padStart(2, '0');
                                    const mm = String(d.getMonth() + 1).padStart(2, '0');
                                    return dd + '/' + mm;
                                }),
                                valores: filtradas.map(v => Number(v.valor_total || 0)),
                                quantidades: filtradas.map(v => Number(v.quantidade || 0)),
                                formasPagamento: (dadosPorDiaSemana[dia] || [])
                            };
                        }

                        function atualizarGraficosPorDia(dia) {
                            const f = filtrarPorDia(dia);

                            // Barras
                            if (chartBarras) {
                                chartBarras.updateOptions({
                                    xaxis: {
                                        categories: f.labels
                                    },
                                    series: [{
                                        data: f.quantidades
                                    }]
                                });
                            } else {
                                const optionsBarras = {
                                    series: [{
                                        name: 'Quantidade de Vendas',
                                        data: f.quantidades
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
                                        categories: f.labels
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

                            // Pizza por dia
                            const containerPizza = document.querySelector("#graficoPizzaPagamentoNoCard");
                            containerPizza.innerHTML = '<div></div>'; // limpa

                            if (!f.formasPagamento || f.formasPagamento.length === 0) {
                                containerPizza.innerHTML = `<div class="text-center py-4 text-muted">Nenhum dado disponível para o período selecionado</div>`;
                                if (chartPizzaDia) {
                                    chartPizzaDia.destroy();
                                    chartPizzaDia = null;
                                }
                                return;
                            }

                            const optionsPizzaDia = {
                                series: f.formasPagamento.map(item => Number(item.valor || 0)),
                                chart: {
                                    type: 'pie',
                                    height: 300
                                },
                                labels: f.formasPagamento.map(item => item.forma),
                                colors: ['#7367F0', '#28C76F', '#EA5455', '#FF9F43', '#00CFE8'],
                                tooltip: {
                                    y: {
                                        formatter: val => 'R$ ' + Number(val).toLocaleString('pt-BR', {
                                            minimumFractionDigits: 2
                                        })
                                    }
                                },
                                title: {
                                    text: (dia === 'todos' ? 'Todas as Formas de Pagamento' : dia),
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

                        atualizarGraficosPorDia(diaSelecionado);

                        // Responsividade
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
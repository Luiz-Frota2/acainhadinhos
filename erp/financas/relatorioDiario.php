<?php

session_start();
require_once '../../assets/php/conexao.php';

// ✅ Recupera o identificador vindo da URL
$idSelecionado = $_GET['id'] ?? '';

// ✅ Verifica se a pessoa está logada
if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id']) // adiciona verificação do id do usuário
) {
    header("Location: .././login.php?id=$idSelecionado");
    exit;
}

// ✅ Valida o tipo de empresa e o acesso permitido
if (str_starts_with($idSelecionado, 'principal_')) {
    if ($_SESSION['tipo_empresa'] !== 'principal' || $_SESSION['empresa_id'] != 1) {
        echo "<script>
              alert('Acesso negado!');
              window.location.href = '.././login.php?id=$idSelecionado';
          </script>";
        exit;
    }
    $id = 1;
} elseif (str_starts_with($idSelecionado, 'filial_')) {
    $idFilial = (int) str_replace('filial_', '', $idSelecionado);
    if ($_SESSION['tipo_empresa'] !== 'filial' || $_SESSION['empresa_id'] != $idFilial) {
        echo "<script>
              alert('Acesso negado!');
              window.location.href = '.././login.php?id=$idSelecionado';
          </script>";
        exit;
    }
    $id = $idFilial;
} else {
    echo "<script>
          alert('Empresa não identificada!');
          window.location.href = '.././login.php?id=$idSelecionado';
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
                            <li class="menu-item active"><a href="./relatorioDiario.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div>Diário</div>
                                </a></li>
                            <li class="menu-item"><a href="./relatorioMensal.php?id=<?= urlencode($idSelecionado); ?>"
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
            <!-- /MENU -->
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
                        <!-- /Search -->

                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <!-- Place this tag where you want the button to render. -->
                            <!-- User -->
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <img src="<?= htmlspecialchars($logoEmpresa) ?>" alt class="w-px-40 h-auto rounded-circle" />
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

                <!-- CONTEÚDO -->

                <div class="container-xxl flex-grow-1 container-p-y">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="fw-bold mb-0"><span class="text-muted fw-light">Financeiro /</span> Relatório Diário</h4>
                    </div>

                    <!-- RESUMO -->
                   <?php
// Conexão com o banco de dados (ajuste conforme sua configuração)
$host = 'localhost'; // ou o IP do servidor de banco de dados
$dbname = 'u920914488_ERP'; // Nome do banco de dados
$username = 'u920914488_ERP'; // Seu nome de usuário do banco de dados
$password = 'N8r=$&Wrs$'; // Sua senha do banco de dados

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}

// Funções para calcular os dados

/**
 * Calcula as entradas do dia (vendas + suprimentos)
 */
function calcularEntradasDia($pdo, $empresa_id) {
    $hoje = date('Y-m-d');
    
    // Total de vendas do dia
    $sqlVendas = "SELECT SUM(total) as total_vendas FROM venda_rapida 
                 WHERE DATE(data_venda) = :hoje AND empresa_id = :empresa_id";
    $stmtVendas = $pdo->prepare($sqlVendas);
    $stmtVendas->bindParam(':hoje', $hoje);
    $stmtVendas->bindParam(':empresa_id', $empresa_id);
    $stmtVendas->execute();
    $vendas = $stmtVendas->fetch(PDO::FETCH_ASSOC);
    $totalVendas = $vendas['total_vendas'] ?? 0;
    
    // Total de suprimentos do dia
    $sqlSuprimentos = "SELECT SUM(valor_suprimento) as total_suprimentos FROM suprimentos 
                      WHERE DATE(data_registro) = :hoje AND empresa_id = :empresa_id";
    $stmtSuprimentos = $pdo->prepare($sqlSuprimentos);
    $stmtSuprimentos->bindParam(':hoje', $hoje);
    $stmtSuprimentos->bindParam(':empresa_id', $empresa_id);
    $stmtSuprimentos->execute();
    $suprimentos = $stmtSuprimentos->fetch(PDO::FETCH_ASSOC);
    $totalSuprimentos = $suprimentos['total_suprimentos'] ?? 0;
    
    // Total de entradas
    $totalEntradas = $totalVendas + $totalSuprimentos;
    
    return [
        'total_entradas' => $totalEntradas,
        'total_vendas' => $totalVendas,
        'total_suprimentos' => $totalSuprimentos
    ];
}

/**
 * Calcula as saídas do dia (sangrias + despesas)
 */
function calcularSaidasDia($pdo, $empresa_id) {
    $hoje = date('Y-m-d');
    
    // Total de sangrias do dia
    $sqlSangrias = "SELECT SUM(valor) as total_sangrias FROM sangrias 
                   WHERE DATE(data_registro) = :hoje AND empresa_id = :empresa_id";
    $stmtSangrias = $pdo->prepare($sqlSangrias);
    $stmtSangrias->bindParam(':hoje', $hoje);
    $stmtSangrias->bindParam(':empresa_id', $empresa_id);
    $stmtSangrias->execute();
    $sangrias = $stmtSangrias->fetch(PDO::FETCH_ASSOC);
    $totalSangrias = $sangrias['total_sangrias'] ?? 0;
    
    // Total de despesas do dia (assumindo que há uma tabela de despesas)
    // Se não houver, você pode ajustar conforme sua estrutura
    $sqlDespesas = "SELECT SUM(valor) as total_despesas FROM despesas 
                   WHERE DATE(data_registro) = :hoje AND empresa_id = :empresa_id";
    $stmtDespesas = $pdo->prepare($sqlDespesas);
    $stmtDespesas->bindParam(':hoje', $hoje);
    $stmtDespesas->bindParam(':empresa_id', $empresa_id);
    $stmtDespesas->execute();
    $despesas = $stmtDespesas->fetch(PDO::FETCH_ASSOC);
    $totalDespesas = $despesas['total_despesas'] ?? 0;
    
    // Total de saídas
    $totalSaidas = $totalSangrias + $totalDespesas;
    
    return [
        'total_saidas' => $totalSaidas,
        'total_sangrias' => $totalSangrias,
        'total_despesas' => $totalDespesas
    ];
}

/**
 * Calcula o saldo em caixa
 */
function calcularSaldoCaixa($pdo, $empresa_id) {
    // Verifica se há caixa aberto
    $sqlCaixa = "SELECT * FROM aberturas 
                WHERE empresa_id = :empresa_id AND status = 'aberto' 
                ORDER BY abertura_datetime DESC LIMIT 1";
    $stmtCaixa = $pdo->prepare($sqlCaixa);
    $stmtCaixa->bindParam(':empresa_id', $empresa_id);
    $stmtCaixa->execute();
    $caixa = $stmtCaixa->fetch(PDO::FETCH_ASSOC);
    
    if ($caixa) {
        return [
            'saldo_caixa' => $caixa['valor_liquido'],
            'valor_meta' => 1500.00, // Você pode ajustar para pegar do banco se tiver meta configurada
            'percentual_meta' => ($caixa['valor_liquido'] / 1500) * 100
        ];
    }
    
    return [
        'saldo_caixa' => 0,
        'valor_meta' => 1500.00,
        'percentual_meta' => 0
    ];
}

/**
 * Obtém o resumo diário para a tabela
 */
function obterResumoDiario($pdo, $empresa_id, $limit = 10, $offset = 0) {
    $sql = "SELECT 
                DATE(abertura_datetime) as data,
                valor_total as entrada,
                (valor_sangrias + (SELECT COALESCE(SUM(valor), 0) FROM despesas 
                 WHERE DATE(data_registro) = DATE(abertura_datetime) AND empresa_id = a.empresa_id)) as saida,
                valor_liquido as saldo,
                responsavel,
                status,
                abertura_datetime
            FROM aberturas a
            WHERE empresa_id = :empresa_id AND status = 'fechado'
            ORDER BY abertura_datetime DESC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtém as últimas movimentações
 */
function obterUltimasMovimentacoes($pdo, $empresa_id, $limit = 5) {
    // Vendas
    $sqlVendas = "SELECT 
                    'Venda' as tipo,
                    CONCAT('Venda #', id) as descricao,
                    total as valor,
                    data_venda as data,
                    'success' as classe_cor
                 FROM venda_rapida
                 WHERE empresa_id = :empresa_id
                 ORDER BY data_venda DESC
                 LIMIT :limit";
    
    // Suprimentos
    $sqlSuprimentos = "SELECT 
                          'Suprimento' as tipo,
                          'Suprimento' as descricao,
                          valor_suprimento as valor,
                          data_registro as data,
                          'primary' as classe_cor
                       FROM suprimentos
                       WHERE empresa_id = :empresa_id
                       ORDER BY data_registro DESC
                       LIMIT :limit";
    
    // Sangrias
    $sqlSangrias = "SELECT 
                       'Sangria' as tipo,
                       'Sangria' as descricao,
                       valor as valor,
                       data_registro as data,
                       'danger' as classe_cor
                    FROM sangrias
                    WHERE empresa_id = :empresa_id
                    ORDER BY data_registro DESC
                    LIMIT :limit";
    
    // Despesas (assumindo que existe tabela despesas)
    $sqlDespesas = "SELECT 
                       'Despesa' as tipo,
                       CONCAT('Despesa - ', descricao) as descricao,
                       valor as valor,
                       data_registro as data,
                       'warning' as classe_cor
                    FROM despesas
                    WHERE empresa_id = :empresa_id
                    ORDER BY data_registro DESC
                    LIMIT :limit";
    
    // Executa todas as consultas
    $movimentacoes = [];
    
    foreach (['Vendas', 'Suprimentos', 'Sangrias', 'Despesas'] as $tipo) {
        $sqlVar = "sql$tipo";
        $stmt = $pdo->prepare($$sqlVar);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $movimentacoes[] = $row;
        }
    }
    
    // Ordena por data decrescente
    usort($movimentacoes, function($a, $b) {
        return strtotime($b['data']) - strtotime($a['data']);
    });
    
    // Retorna apenas o número limite
    return array_slice($movimentacoes, 0, $limit);
}

/**
 * Formata valores monetários
 */
function formatarMoeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

/**
 * Formata porcentagem
 */
function formatarPorcentagem($valor) {
    return number_format($valor, 0) . '%';
}

// Exemplo de uso (supondo que você tenha o ID da empresa)
$empresa_id = $idSelecionado || 'principal_1'; // Substitua pelo ID real da empresa

// Calcula os dados
$entradas = calcularEntradasDia($pdo, $empresa_id);
$saidas = calcularSaidasDia($pdo, $empresa_id);
$saldo = calcularSaldoCaixa($pdo, $empresa_id);
$resumoDiario = obterResumoDiario($pdo, $empresa_id);
$ultimasMovimentacoes = obterUltimasMovimentacoes($pdo, $empresa_id);
?>

<!-- HTML com os dados dinâmicos -->
<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="fw-semibold">Entradas do Dia</div>
                <h4 class="mb-1"><?= formatarMoeda($entradas['total_entradas']) ?></h4>
                <small class="text-success fw-semibold">+12% vs ontem</small>
                <div class="mt-3">
                    <span class="badge bg-label-primary">Vendas: <?= formatarMoeda($entradas['total_vendas']) ?></span>
                    <span class="badge bg-label-secondary ms-1">Suprimentos: <?= formatarMoeda($entradas['total_suprimentos']) ?></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="fw-semibold">Saídas do Dia</div>
                <h4 class="mb-1"><?= formatarMoeda($saidas['total_saidas']) ?></h4>
                <small class="text-danger fw-semibold">+5% vs ontem</small>
                <div class="mt-3">
                    <span class="badge bg-label-danger">Sangrias: <?= formatarMoeda($saidas['total_sangrias']) ?></span>
                    <span class="badge bg-label-warning ms-1">Despesas: <?= formatarMoeda($saidas['total_despesas']) ?></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <div class="fw-semibold">Saldo em Caixa</div>
                <h4 class="mb-1"><?= formatarMoeda($saldo['saldo_caixa']) ?></h4>
                <small class="text-success fw-semibold">+7% vs ontem</small>
                <div class="mt-3">
                    <span class="badge bg-label-info">Meta: <?= formatarMoeda($saldo['valor_meta']) ?></span>
                    <span class="badge bg-label-success ms-1"><?= formatarPorcentagem($saldo['percentual_meta']) ?> da meta</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- TABELA E DETALHES -->
<div class="row">
    <div class="col-md-8 mb-3">
        <div class="card">
            <div class="card-header text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Resumo Diário</h5>
            </div>
            <div class="table-responsive text-nowrap">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Data</th>
                            <th>Entrada</th>
                            <th>Saída</th>
                            <th>Saldo</th>
                            <th>Responsável</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resumoDiario as $registro): ?>
                        <tr>
                            <td><strong><?= date('d/m/Y', strtotime($registro['data'])) ?></strong></td>
                            <td><?= formatarMoeda($registro['entrada']) ?></td>
                            <td><?= formatarMoeda($registro['saida']) ?></td>
                            <td><strong><?= formatarMoeda($registro['saldo']) ?></strong></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar avatar-xs me-2">
                                        <span class="avatar-initial rounded-circle bg-label-primary">
                                            <?= substr($registro['responsavel'], 0, 1) ?>
                                        </span>
                                    </div>
                                    <?= $registro['responsavel'] ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-<?= $registro['status'] == 'fechado' ? 'success' : 'warning' ?>">
                                    <?= ucfirst($registro['status']) ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-icon">
                                    <i class="bx bx-show"></i>
                                </button>
                                <button class="btn btn-sm btn-icon">
                                    <i class="bx bx-printer"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <div class="text-muted">Mostrando 1 a <?= count($resumoDiario) ?> de <?= count($resumoDiario) ?> registros</div>
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item disabled">
                            <a class="page-link" href="#" tabindex="-1">Anterior</a>
                        </li>
                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                        <li class="page-item">
                            <a class="page-link" href="#">Próxima</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Últimas Movimentações</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($ultimasMovimentacoes as $movimentacao): ?>
                    <div class="list-group-item list-group-item-action">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-1"><?= $movimentacao['descricao'] ?></h6>
                                <small class="text-muted"><?= date('d/m - H:i', strtotime($movimentacao['data'])) ?></small>
                            </div>
                            <span class="text-<?= $movimentacao['classe_cor'] ?>">
                                <?= ($movimentacao['tipo'] == 'Sangria' || $movimentacao['tipo'] == 'Despesa') ? '-' : '+' ?>
                                <?= formatarMoeda($movimentacao['valor']) ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
           
            </div>

            <!-- Scripts para os gráficos (usando Chart.js) -->

        </div>
    </div>

    <!-- Scripts -->
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/js/main.js"></script>
</body>

</html>
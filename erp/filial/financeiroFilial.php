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
$usuario_id  = $_SESSION['usuario_id'];

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
    echo "<script>alert('Acesso negado!'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
    exit;
}

// ✅ Logo da empresa (fallback)
try {
    $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1");
    $stmt->bindParam(':id', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $sobre = $stmt->fetch(PDO::FETCH_ASSOC);
    $logoEmpresa = !empty($sobre['imagem']) ? "../../assets/img/empresa/" . $sobre['imagem'] : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png";
}
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>ERP — Financeiro</title>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($logoEmpresa) ?>" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <script src="../../assets/vendor/js/helpers.js"></script>
    <script src="../../assets/js/config.js"></script>
    <style>
        .table thead th {
            white-space: nowrap;
        }

        .toolbar {
            gap: .5rem;
        }

        .toolbar .form-select,
        .toolbar .form-control {
            max-width: 220px;
        }

        .kpi-card .kpi-label {
            font-size: .875rem;
            color: #667085;
        }

        .kpi-card .kpi-value {
            font-size: 1.4rem;
            font-weight: 700;
        }

        .kpi-card .kpi-sub {
            font-size: .825rem;
            color: #818181;
        }

        .progress-skinny {
            height: 8px;
        }

        .badge-soft {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }
    </style>
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">

            <!-- ====== ASIDE ====== -->
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
                    <!-- Dashboard -->
                    <li class="menu-item">
                        <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Dashboard</div>
                        </a>
                    </li>

                    <!-- Administração Filiais -->
                    <li class="menu-header small text-uppercase">
                        <span class="menu-header-text">Administração Filiais</span>
                    </li>

                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-building"></i>
                            <div>Filiais</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./filialAdicionada.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Adicionadas</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="menu-item ">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div>B2B - Matriz</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item"><a href="./contasFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Pagamentos Solic.</div>
                                </a></li>
                            <li class="menu-item"><a href="./produtosSolicitados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Produtos Solicitados</div>
                                </a></li>
                            <li class="menu-item"><a href="./produtosEnviados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Produtos Enviados</div>
                                </a></li>
                            <li class="menu-item"><a href="./transferenciasPendentes.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Transf. Pendentes</div>
                                </a></li>
                            <li class="menu-item"><a href="./historicoTransferencias.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Histórico Transf.</div>
                                </a></li>
                            <li class="menu-item"><a href="./estoqueMatriz.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Estoque Matriz</div>
                                </a></li>
                            <li class="menu-item"><a href="./relatoriosB2B.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Relatórios B2B</div>
                                </a></li>
                        </ul>
                    </li>

                    <!-- Relatórios -->
                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-bar-chart-alt-2"></i>
                            <div data-i18n="Relatorios">Relatórios</div>
                        </a>
                        <ul class="menu-sub active">
                            <li class="menu-item">
                                <a href="./VendasFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Vendas">Vendas por Filial</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./MaisVendidos.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="MaisVendidos">Mais Vendidos</div>
                                </a>
                            </li>
                            <li class="menu-item active">
                                <a href="./financeiroFilial.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Pedidos">Financeiro</div>
                                </a>
                            </li>

                        </ul>
                    </li>

                    <!-- Diversos -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
                    <li class="menu-item"><a href="../rh/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-group"></i>
                            <div>RH</div>
                        </a></li>
                    <li class="menu-item"><a href="../financas/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-dollar"></i>
                            <div>Finanças</div>
                        </a></li>
                    <li class="menu-item"><a href="../pdv/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-desktop"></i>
                            <div>PDV</div>
                        </a></li>
                    <li class="menu-item"><a href="../empresa/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div>Empresa</div>
                        </a></li>
                    <li class="menu-item"><a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-box"></i>
                            <div>Estoque</div>
                        </a></li>
                    <li class="menu-item"><a href="../filial/index.php?id=principal_1" class="menu-link"><i class="menu-icon tf-icons bx bx-building"></i>
                            <div>Filial</div>
                        </a></li>
                    <li class="menu-item"><a href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-group"></i>
                            <div>Usuários</div>
                        </a></li>
                    <li class="menu-item"><a href="https://wa.me/92991515710" target="_blank" class="menu-link"><i class="menu-icon tf-icons bx bx-support"></i>
                            <div>Suporte</div>
                        </a></li>
                </ul>
            </aside>
            <!-- ====== /ASIDE ====== -->

            <!-- Layout container -->
            <div class="layout-page">
                <!-- Navbar -->
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                            <i class="bx bx-menu bx-sm"></i>
                        </a>
                    </div>

                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <div class="navbar-nav align-items-center">
                            <div class="nav-item d-flex align-items-center">
                                <i class="bx bx-search fs-4 lh-0"></i>
                                <input type="text" class="form-control border-0 shadow-none" placeholder="Search..." aria-label="Search..." />
                            </div>
                        </div>

                        <ul class="navbar-nav flex-row align-items-center ms-auto">
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
                                    <li><a class="dropdown-item" href="./contaUsuario.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-user me-2"></i><span class="align-middle">Minha Conta</span></a></li>
                                    <li><a class="dropdown-item" href="#"><i class="bx bx-cog me-2"></i><span class="align-middle">Configurações</span></a></li>
                                    <li>
                                        <div class="dropdown-divider"></div>
                                    </li>
                                    <li><a class="dropdown-item" href="../logout.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-power-off me-2"></i><span class="align-middle">Sair</span></a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </nav>
                <!-- /Navbar -->

                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold mb-0">
                        <span class="text-muted fw-light"><a href="#">Relatórios</a> / </span>
                        Financeiro
                    </h4>
                    <h5 class="fw-bold mt-3 mb-3 custor-font">
                        <span class="text-muted fw-light">Recebíveis, fluxo de caixa e status por Filial — Mês Atual</span>
                    </h5>

                    <!-- ============================= -->
                    <!-- Filtros                       -->
                    <!-- ============================= -->
                    <div class="card mb-3">
                        <div class="card-body py-2">
                            <form class="w-100" method="get">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($idSelecionado) ?>">

                                <div class="row g-2 align-items-end">
                                   <label class="form-label mb-1">De</label>
                                    <input type="date" class="form-control form-control-sm" name="de" value="<?= h($de) ?>">
                                    <label class="form-label mb-1">Até</label>
                                    <input type="date" class="form-control form-control-sm" name="ate" value="<?= h($ate) ?>">

                                    </div>
                              

                                    <div class="col-12 col-sm-6 col-lg-3">
                                        <label for="status" class="form-label mb-1">Status</label>
                                        <select id="status" class="form-select form-select-sm" name="status">
                                            <option selected>Status: Todos</option>
                                            <option>Aprovado</option>
                                            <option>Pendente</option>
                                            <option>Reprovado</option>
                                        </select>
                                    </div>

                                    <div class="col-12 col-sm-6 col-lg-3">
                                        <label for="Filial" class="form-label mb-1">Filial</label>
                                        <select id="Filial" class="form-select form-select-sm" name="Filial">
                                            <option selected>Todas as Filiais</option>
                                            <option>Filial Centro</option>
                                            <option>Filial Norte</option>
                                            <option>Filial Sul</option>
                                        </select>
                                    </div>

                                    <!-- Ações principais -->
                                    <!-- Ações primárias -->
                                    <div class="col-12 col-sm-6 col-lg-3 mr-3">


                                        <div class="btn-toolbar" role="toolbar" aria-label="Exportar e imprimir">
                                            <div class="btn-group btn-group-sm me-2" role="group" aria-label="Exportar">
                                                <button type="button" class="btn btn-outline-dark dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="bx bx-download me-1"></i>
                                                    <span class="align-middle">Exportar</span>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><button class="dropdown-item" type="button"><i class="bx bx-file me-2"></i> XLSX</button></li>
                                                    <li><button class="dropdown-item" type="button"><i class="bx bx-data me-2"></i> CSV</button></li>
                                                    <li>
                                                        <hr class="dropdown-divider">
                                                    </li>
                                                    <li><button class="dropdown-item" type="button"><i class="bx bx-table me-2"></i> PDF (tabela)</button></li>
                                                </ul>
                                            </div>

                                            <div class="btn-group btn-group-sm" role="group" aria-label="Imprimir">
                                                <button class="btn btn-outline-dark" type="button" onclick="window.print()" data-bs-toggle="tooltip" data-bs-title="Imprimir página">
                                                    <i class="bx bx-printer me-1"></i>
                                                    <span class="align-middle">Imprimir</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>


         <?php
// ---------------------------------------------------------
// CÁLCULO PARA OS CARDS DO RESUMO FINANCEIRO
// ---------------------------------------------------------

try {
    $sql = "
        SELECT 
            sp.status,
            SUM(sp.valor) AS total
        FROM solicitacoes_pagamento sp
        INNER JOIN unidades u 
            ON u.id = REPLACE(sp.id_solicitante, 'unidade_', '')
        WHERE 
            u.tipo = 'Filial'
            AND u.status = 'Ativa'
        GROUP BY sp.status
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $cardData = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo '<p>Erro ao carregar dados dos cards: ' . $e->getMessage() . '</p>';
    $cardData = [];
}

// Estrutura para armazenar
$cards = [
    "aprovado"  => 0,
    "pendente"  => 0,
    "reprovado" => 0
];

// Preencher
foreach ($cardData as $row) {
    $cards[strtolower($row["status"])] = (float)$row["total"];
}

// Total geral
$totalGeralCards = $cards["aprovado"] + $cards["pendente"] + $cards["reprovado"];

// Função porcentagem
function p($valor, $total) {
    if ($total <= 0) return 0;
    return ($valor / $total) * 100;
}
?>

<!-- ============================= -->
<!-- KPIs principais -->
<!-- ============================= -->
<div class="row">
    
    <!-- FATURAMENTO TOTAL (TODOS OS STATUS) -->
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card kpi-card">
            <div class="card-body">
                <div class="kpi-label">Faturamento (por Período)</div>
                <div class="kpi-value">
                    R$ <?= number_format($totalGeralCards, 2, ',', '.') ?>
                </div>
                <div class="kpi-sub">Pedidos fechados</div>
            </div>
        </div>
    </div>

    <!-- APROVADO -->
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card kpi-card">
            <div class="card-body">
                <div class="kpi-label">Recebido (Aprovado)</div>
                <div class="kpi-value">
                    R$ <?= number_format($cards["aprovado"], 2, ',', '.') ?>
                </div>
                <div class="kpi-sub">
                    <?= number_format(p($cards["aprovado"], $totalGeralCards), 1, ',', '.') ?>% do total
                </div>
            </div>
        </div>
    </div>

    <!-- PENDENTE -->
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card kpi-card">
            <div class="card-body">
                <div class="kpi-label">Em Aberto (Pendente)</div>
                <div class="kpi-value">
                    R$ <?= number_format($cards["pendente"], 2, ',', '.') ?>
                </div>
                <div class="kpi-sub">
                    <?= number_format(p($cards["pendente"], $totalGeralCards), 1, ',', '.') ?>% do total
                </div>
            </div>
        </div>
    </div>

    <!-- REPROVADO -->
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card kpi-card">
            <div class="card-body">
                <div class="kpi-label">Reprovados</div>
                <div class="kpi-value">
                    R$ <?= number_format($cards["reprovado"], 2, ',', '.') ?>
                </div>
                <div class="kpi-sub">
                    <?= number_format(p($cards["reprovado"], $totalGeralCards), 1, ',', '.') ?>% do total
                </div>
            </div>
        </div>
    </div>

</div>

                  <?php
// ---------------------------------------------------------
// RECEBÍVEIS POR STATUS (FILIAIS ATIVAS) — RESUMO GERAL
// ---------------------------------------------------------

try {
    $sql = "
        SELECT 
            sp.status,
            COUNT(*) AS quantidade,
            SUM(sp.valor) AS total_valor
        FROM solicitacoes_pagamento sp
        INNER JOIN unidades u 
            ON u.id = REPLACE(sp.id_solicitante, 'unidade_', '')
        WHERE 
            u.tipo = 'Filial'
            AND u.status = 'Ativa'
        GROUP BY sp.status
        ORDER BY sp.status
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $statusData = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "<p>Erro ao carregar status: " . $e->getMessage() . "</p>";
    $statusData = [];
}

// Converter para estrutura indexada por status
$dados = [
    "aprovado"  => ["quantidade" => 0, "valor" => 0],
    "pendente"  => ["quantidade" => 0, "valor" => 0],
    "reprovado" => ["quantidade" => 0, "valor" => 0],
];

foreach ($statusData as $row) {
    $status = strtolower($row["status"]);
    $dados[$status]["quantidade"] = (int)$row["quantidade"];
    $dados[$status]["valor"]      = (float)$row["total_valor"];
}

// Total geral de valores
$totalGeral = 
    $dados["aprovado"]["valor"] + 
    $dados["pendente"]["valor"] + 
    $dados["reprovado"]["valor"];

// Função % (evita divisão por zero)
function percent($valor, $total) {
    if ($total <= 0) return 0;
    return ($valor / $total) * 100;
}
?>

<!-- ============================= -->
<!-- Recebíveis por Status -->
<!-- ============================= -->
<div class="card mb-3">
    <h5 class="card-header">Recebíveis por Status</h5>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Status</th>
                    <th class="text-end">Quantidade</th>
                    <th class="text-end">Valor (R$)</th>
                    <th style="min-width:180px;">% do Total</th>
                </tr>
            </thead>

            <tbody>

                <!-- Status: Aprovado -->
                <tr>
                    <td><span class="badge badge-soft bg-success text-white">Pago</span></td>
                    <td class="text-end"><?= $dados['aprovado']['quantidade'] ?></td>
                    <td class="text-end">
                        R$ <?= number_format($dados['aprovado']['valor'], 2, ',', '.') ?>
                    </td>

                    <?php $p_aprov = percent($dados['aprovado']['valor'], $totalGeral); ?>

                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="flex-grow-1">
                                <div class="progress progress-skinny">
                                    <div class="progress-bar bg-success" 
                                         style="width: <?= $p_aprov ?>%;">
                                    </div>
                                </div>
                            </div>
                            <div style="width:58px;" class="text-end">
                                <?= number_format($p_aprov, 1, ',', '.') ?>%
                            </div>
                        </div>
                    </td>
                </tr>

                <!-- Status: Pendente -->
                <tr>
                    <td><span class="badge badge-soft bg-warning text-dark">Em Aberto</span></td>

                    <td class="text-end"><?= $dados['pendente']['quantidade'] ?></td>

                    <td class="text-end">
                        R$ <?= number_format($dados['pendente']['valor'], 2, ',', '.') ?>
                    </td>

                    <?php $p_pend = percent($dados['pendente']['valor'], $totalGeral); ?>

                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="flex-grow-1">
                                <div class="progress progress-skinny">
                                    <div class="progress-bar bg-warning"
                                         style="width: <?= $p_pend ?>%;">
                                    </div>
                                </div>
                            </div>
                            <div style="width:58px;" class="text-end">
                                <?= number_format($p_pend, 1, ',', '.') ?>%
                            </div>
                        </div>
                    </td>
                </tr>

                <!-- Status: Reprovado -->
                <tr>
                    <td><span class="badge badge-soft bg-danger text-white">Reprovado</span></td>

                    <td class="text-end"><?= $dados['reprovado']['quantidade'] ?></td>

                    <td class="text-end">
                        R$ <?= number_format($dados['reprovado']['valor'], 2, ',', '.') ?>
                    </td>

                    <?php $p_rep = percent($dados['reprovado']['valor'], $totalGeral); ?>

                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="flex-grow-1">
                                <div class="progress progress-skinny">
                                    <div class="progress-bar bg-danger"
                                         style="width: <?= $p_rep ?>%;">
                                    </div>
                                </div>
                            </div>
                            <div style="width:58px;" class="text-end">
                                <?= number_format($p_rep, 1, ',', '.') ?>%
                            </div>
                        </div>
                    </td>
                </tr>

            </tbody>

            <tfoot>
                <tr>
                    <th>Total</th>
                    <th class="text-end">
                        <?= 
                            $dados['aprovado']['quantidade'] + 
                            $dados['pendente']['quantidade'] + 
                            $dados['reprovado']['quantidade']
                        ?>
                    </th>

                    <th class="text-end">
                        R$ <?= number_format($totalGeral, 2, ',', '.') ?>
                    </th>
                    <th></th>
                </tr>
            </tfoot>

        </table>
    </div>
</div>

                    <?php
// --------------------------------------------------------
// LISTAGEM DO FLUXO DE CAIXA DAS FILIAIS ATIVAS (FECHADOS)
// --------------------------------------------------------

try {
    $sql = "
        SELECT
            a.responsavel,
            a.valor_total,
            a.valor_sangrias,
            a.valor_liquido,
            a.quantidade_vendas,
            u.nome AS nome_filial
        FROM aberturas a
        INNER JOIN unidades u
            ON u.id = REPLACE(a.empresa_id, 'unidade_', '')
        WHERE 
            a.status = 'fechado'
            AND u.tipo = 'Filial'
            AND u.status = 'Ativa'
        ORDER BY a.fechamento_datetime DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $fluxo = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "<p>Erro ao carregar fluxo de caixa: " . $e->getMessage() . "</p>";
    $fluxo = [];
}

// Totais gerais
$totalEntradas = 0;
$totalSaidas = 0;
$totalSaldo = 0;
$totalVendas = 0;
?>

<!-- ============================= -->
<!-- Fluxo de Caixa (Resumo) -->
<!-- ============================= -->
<div class="card mb-3">
    <h5 class="card-header">Fluxo de Caixa — Resumo do Período</h5>

    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Responsavel</th>
                    <th class="text-end">Entradas (R$)</th>
                    <th class="text-end">Saídas (R$)</th>
                    <th class="text-end">Saldo (R$)</th>
                    <th class="text-end">Quantidade de Vnd</th>
                </tr>
            </thead>

            <tbody>
                <?php if (empty($fluxo)) : ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted">
                            Nenhum caixa fechado de filial ativa encontrado.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($fluxo as $f): ?>

                        <?php
                            $totalEntradas += $f['valor_total'];
                            $totalSaidas   += $f['valor_sangrias'];
                            $totalSaldo    += $f['valor_liquido'];
                            $totalVendas   += $f['quantidade_vendas'];
                        ?>

                        <tr>
                            <td><?= htmlspecialchars($f['responsavel']) ?></td>

                            <td class="text-end">
                                R$ <?= number_format($f['valor_total'], 2, ',', '.') ?>
                            </td>

                            <td class="text-end">
                                R$ <?= number_format($f['valor_sangrias'], 2, ',', '.') ?>
                            </td>

                            <td class="text-end">
                                R$ <?= number_format($f['valor_liquido'], 2, ',', '.') ?>
                            </td>

                            <td class="text-end"><?= $f['quantidade_vendas'] ?></td>
                        </tr>

                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>

            <tfoot>
                <tr>
                    <th>Total</th>

                    <th class="text-end">
                        R$ <?= number_format($totalEntradas, 2, ',', '.') ?>
                    </th>

                    <th class="text-end">
                        R$ <?= number_format($totalSaidas, 2, ',', '.') ?>
                    </th>

                    <th class="text-end">
                        R$ <?= number_format($totalSaldo, 2, ',', '.') ?>
                    </th>

                    <th class="text-end"><?= $totalVendas ?></th>
                </tr>
            </tfoot>

        </table>
    </div>
</div>


             <?php
// ------------------------------------------------
// LISTAR CONTAS FUTURAS (FILIAIS ATIVAS)
// ------------------------------------------------

try {
    $sql = "
        SELECT 
            c.id,
            c.descricao,
            c.valorpago,
            c.datatransacao,
            c.responsavel,
            c.statuss,
            u.nome AS nome_filial
        FROM contas c
        INNER JOIN unidades u
            ON u.id = REPLACE(c.id_selecionado, 'unidade_', '')
        WHERE 
            c.statuss = 'futura'
            AND u.tipo = 'Filial'
            AND u.status = 'Ativa'
        ORDER BY c.datatransacao ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $contasFuturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "<p>Erro ao carregar contas futuras: " . $e->getMessage() . "</p>";
    $contasFuturas = [];
}
?>

<!-- ============================= -->
<!-- Contas a Pagar (Futuras) -->
<!-- ============================= -->
<div class="card mb-3">
    <h5 class="card-header">Contas a pagar (Futura)</h5>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Filial</th>
                    <th>Descrição</th>
                    <th>Data Transação</th>
                    <th class="text-end">Valor (R$)</th>
                    <th>responsavel</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>
                <?php if (empty($contasFuturas)) : ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">
                            Nenhuma conta futura de filial ativa encontrada.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($contasFuturas as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['id']) ?></td>

                            <td><?= htmlspecialchars($c['nome_filial']) ?></td>

                            <td><?= htmlspecialchars($c['descricao']) ?></td>

                            <td><?= date('d/m/Y', strtotime($c['datatransacao'])) ?></td>

                            <td class="text-end">
                                R$ <?= number_format($c['valorpago'], 2, ',', '.') ?>
                            </td>

                            <td><?= htmlspecialchars($c['responsavel']) ?></td>

                            <td>
                                <span class="badge bg-warning text-dark">Futura</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>

        </table>
    </div>
</div>

                 <?php
// ------------------------------------------------
// LISTAR CONTAS PAGAS DE FILIAIS ATIVAS
// ------------------------------------------------

try {
    $sql = "
        SELECT 
            c.id,
            c.descricao,
            c.valorpago,
            c.datatransacao,
            c.responsavel,
            c.statuss,
            u.nome AS nome_filial
        FROM contas c
        INNER JOIN unidades u
            ON u.id = REPLACE(c.id_selecionado, 'unidade_', '')
        WHERE 
            c.statuss = 'pago'
            AND u.tipo = 'Filial'
            AND u.status = 'Ativa'
        ORDER BY c.datatransacao DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $contasPagas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "<p>Erro ao carregar contas pagas: " . $e->getMessage() . "</p>";
    $contasPagas = [];
}
?>

<!-- ============================= -->
<!-- Contas a Pagar / Contas Pagas -->
<!-- ============================= -->
<div class="card mb-3">
    <h5 class="card-header">Contas Pagas</h5>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Filial</th>
                    <th>Descrição</th>
                    <th>Data Transação</th>
                    <th class="text-end">Valor (R$)</th>
                    <th>Responsável</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>
                <?php if (empty($contasPagas)) : ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">
                            Nenhuma conta paga por filial ativa encontrada.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($contasPagas as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['id']) ?></td>

                            <td><?= htmlspecialchars($c['nome_filial']) ?></td>

                            <td><?= htmlspecialchars($c['descricao']) ?></td>

                            <td><?= date('d/m/Y', strtotime($c['datatransacao'])) ?></td>

                            <td class="text-end">
                                R$ <?= number_format($c['valorpago'], 2, ',', '.') ?>
                            </td>

                            <td><?= htmlspecialchars($c['responsavel']) ?></td>

                            <td>
                                <span class="badge bg-success">Pago</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>

        </table>
    </div>
</div>


                    <!-- ============================= -->
                   <?php
// ---------------------------------------------
// LISTAR PAGAMENTOS APROVADOS POR FILIAL ATIVA
// ---------------------------------------------

try {
    $sql = "
        SELECT 
            sp.ID,
            sp.valor,
            sp.descricao,
            sp.vencimento,
            sp.created_at,
            sp.comprovante_url,
            u.nome AS nome_filial
        FROM solicitacoes_pagamento sp
        INNER JOIN unidades u 
            ON u.id = REPLACE(sp.id_solicitante, 'unidade_', '')
        WHERE 
            sp.status = 'aprovado'
            AND u.tipo = 'Filial'
            AND u.status = 'Ativa'
        ORDER BY sp.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "<p>Erro ao carregar pagamentos: " . $e->getMessage() . "</p>";
    $pagamentos = [];
}

$totalGeral = 0;
?>

<!-- Pagamentos por Filial -->
<div class="card mb-3">
    <h5 class="card-header">Pagamentos por Filial — Resumo</h5>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Filial</th>
                    <th class="text-end">Valor</th>
                    <th class="text-end">Data de Emissão</th>
                    <th class="text-end">Vencimento</th>
                    <th class="text-end">Comprovante</th>
                    <th class="text-end">Descrição</th>
                </tr>
            </thead>

            <tbody>
                <?php if (empty($pagamentos)) : ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">
                            Nenhum pagamento aprovado de filial ativa encontrado.
                        </td>
                    </tr>
                <?php else: ?>

                    <?php foreach ($pagamentos as $pg): ?>
                        <?php $totalGeral += $pg['valor']; ?>

                        <tr>
                            <td><strong><?= htmlspecialchars($pg['nome_filial']) ?></strong></td>

                            <td class="text-end">
                                R$ <?= number_format($pg['valor'], 2, ',', '.') ?>
                            </td>

                            <td class="text-end">
                                <?= date('d/m/Y H:i', strtotime($pg['created_at'])) ?>
                            </td>

                            <td class="text-end">
                                <?= date('d/m/Y', strtotime($pg['vencimento'])) ?>
                            </td>

                            <td class="text-end">
                                <?php if (!empty($pg['comprovante_url'])): ?>
                                    <a href="/assets/php/matriz/<?= $pg['comprovante_url'] ?>" target="_blank">
                                        Abrir
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Sem arquivo</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-end">
                                <?= htmlspecialchars($pg['descricao']) ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>
            </tbody>

            <tfoot>
                <tr>
                    <th>Total</th>
                    <th class="text-end">
                        R$ <?= number_format($totalGeral, 2, ',', '.') ?>
                    </th>
                </tr>
            </tfoot>

        </table>
    </div>
</div>


                </div><!-- /container -->
            </div><!-- /Layout page -->
        </div><!-- /Layout container -->
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // tooltips bootstrap
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

            // spinner no Aplicar
            const form = document.querySelector('form[method="get"]');
            const btnAplicar = document.getElementById('btnAplicar');
            if (form && btnAplicar) {
                form.addEventListener('submit', function() {
                    btnAplicar.disabled = true;
                    btnAplicar.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processando...';
                });
            }
        });
    </script>
    <!-- Core JS -->
    <script src="../../js/saudacao.js"></script>
    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../assets/vendor/js/menu.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>
</body>

</html>
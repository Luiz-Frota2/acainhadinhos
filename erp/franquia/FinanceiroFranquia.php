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

                    <!-- Administração Franquias -->
                    <li class="menu-header small text-uppercase">
                        <span class="menu-header-text">Administração Franquias</span>
                    </li>

                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-building"></i>
                            <div>Franquias</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./franquiaAdicionada.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
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
                            <li class="menu-item"><a href="./contasFranquia.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
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
                    <li class="menu-item open active">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-bar-chart-alt-2"></i>
                            <div>Relatórios</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item"><a href="./VendasFranquias.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Vendas por Franquias</div>
                                </a></li>
                            <li class="menu-item"><a href="./MaisVendidos.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Mais Vendidos</div>
                                </a></li>

                            <li class="menu-item active"><a href="./FinanceiroFranquia.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Financeiro</div>
                                </a></li>
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
                        <span class="text-muted fw-light">Recebíveis, fluxo de caixa e status por franquia — Mês Atual</span>
                    </h5>

                    <!-- ============================= -->
                    <!-- Filtros                       -->
                    <!-- ============================= -->
                    <div class="card mb-3">
                        <div class="card-body py-2">
                            <form class="w-100" method="get">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($idSelecionado) ?>">

                                <div class="row g-2 align-items-end">
                                    <!-- Filtros -->
                                    <div class="col-12 col-sm-6 col-lg-3">
                                        <label for="periodo" class="form-label mb-1">Período</label>
                                        <select id="periodo" class="form-select form-select-sm" name="periodo">
                                            <option selected>Período: Mês Atual</option>
                                            <option>Últimos 30 dias</option>
                                            <option>Últimos 90 dias</option>
                                            <option>Este ano</option>
                                        </select>
                                    </div>

                                    <div class="col-12 col-sm-6 col-lg-3">
                                        <label for="status" class="form-label mb-1">Status</label>
                                        <select id="status" class="form-select form-select-sm" name="status">
                                            <option selected>Status: Todos</option>
                                            <option>Pagos</option>
                                            <option>Em Aberto</option>
                                            <option>Vencidos</option>
                                        </select>
                                    </div>

                                    <div class="col-12 col-sm-6 col-lg-3">
                                        <label for="franquia" class="form-label mb-1">Franquia</label>
                                        <select id="franquia" class="form-select form-select-sm" name="franquia">
                                            <option selected>Todas as Franquias</option>
                                            <option>Franquia Centro</option>
                                            <option>Franquia Norte</option>
                                            <option>Franquia Sul</option>
                                        </select>
                                    </div>

                                    <!-- Ações principais -->
                                    <!-- Ações primárias -->
                                    <div class="col-12 col-sm-6 col-lg-3">
                                       
                                
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


                    <!-- ============================= -->
                    <!-- KPIs principais               -->
                    <!-- ============================= -->
                    <div class="row">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card kpi-card">
                                <div class="card-body">
                                    <div class="kpi-label">Faturamento (Período)</div>
                                    <div class="kpi-value">R$ 128.450,00</div>
                                    <div class="kpi-sub">Pedidos fechados</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card kpi-card">
                                <div class="card-body">
                                    <div class="kpi-label">Recebido</div>
                                    <div class="kpi-value">R$ 103.900,00</div>
                                    <div class="kpi-sub">80,9% do total</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card kpi-card">
                                <div class="card-body">
                                    <div class="kpi-label">Em Aberto</div>
                                    <div class="kpi-value">R$ 18.750,00</div>
                                    <div class="kpi-sub">14,6% do total</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card kpi-card">
                                <div class="card-body">
                                    <div class="kpi-label">Vencidos</div>
                                    <div class="kpi-value">R$ 5.800,00</div>
                                    <div class="kpi-sub">4,5% do total</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ============================= -->
                    <!-- Recebíveis por Status         -->
                    <!-- ============================= -->
                    <div class="card mb-3">
                        <h5 class="card-header">Recebíveis por Status</h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th class="text-end">Qtde Títulos</th>
                                        <th class="text-end">Valor (R$)</th>
                                        <th style="min-width:180px;">% do Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><span class="badge badge-soft">Pago</span></td>
                                        <td class="text-end">142</td>
                                        <td class="text-end">R$ 103.900,00</td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="flex-grow-1">
                                                    <div class="progress progress-skinny">
                                                        <div class="progress-bar" style="width: 80.9%;" aria-valuenow="80.9" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                </div>
                                                <div style="width:58px;" class="text-end">80,9%</div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge badge-soft">Em Aberto</span></td>
                                        <td class="text-end">38</td>
                                        <td class="text-end">R$ 18.750,00</td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="flex-grow-1">
                                                    <div class="progress progress-skinny">
                                                        <div class="progress-bar" style="width: 14.6%;" aria-valuenow="14.6" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                </div>
                                                <div style="width:58px;" class="text-end">14,6%</div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge badge-soft">Vencido</span></td>
                                        <td class="text-end">12</td>
                                        <td class="text-end">R$ 5.800,00</td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="flex-grow-1">
                                                    <div class="progress progress-skinny">
                                                        <div class="progress-bar" style="width: 4.5%;" aria-valuenow="4.5" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                </div>
                                                <div style="width:58px;" class="text-end">4,5%</div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th>Total</th>
                                        <th class="text-end">192</th>
                                        <th class="text-end">R$ 128.450,00</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- ============================= -->
                    <!-- Fluxo de Caixa (Resumo)       -->
                    <!-- ============================= -->
                    <div class="card mb-3">
                        <h5 class="card-header">Fluxo de Caixa — Resumo do Período</h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Categoria</th>
                                        <th class="text-end">Entradas (R$)</th>
                                        <th class="text-end">Saídas (R$)</th>
                                        <th class="text-end">Saldo (R$)</th>
                                        <th>Obs.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Vendas B2B</td>
                                        <td class="text-end">R$ 103.900,00</td>
                                        <td class="text-end">—</td>
                                        <td class="text-end">R$ 103.900,00</td>
                                        <td>Baixas confirmadas</td>
                                    </tr>
                                    <tr>
                                        <td>Vendas PDV</td>
                                        <td class="text-end">R$ 42.500,00</td>
                                        <td class="text-end">—</td>
                                        <td class="text-end">R$ 42.500,00</td>
                                        <td>PIX + Cartão</td>
                                    </tr>
                                    <tr>
                                        <td>Despesas Fixas</td>
                                        <td class="text-end">—</td>
                                        <td class="text-end">R$ 24.300,00</td>
                                        <td class="text-end">- R$ 24.300,00</td>
                                        <td>Aluguel, folha, energia</td>
                                    </tr>
                                    <tr>
                                        <td>Compras/Estoque</td>
                                        <td class="text-end">—</td>
                                        <td class="text-end">R$ 11.800,00</td>
                                        <td class="text-end">- R$ 11.800,00</td>
                                        <td>Reposição insumos</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th>Total</th>
                                        <th class="text-end">R$ 146.400,00</th>
                                        <th class="text-end">R$ 36.100,00</th>
                                        <th class="text-end">R$ 110.300,00</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- ============================= -->
                    <!-- Contas a Receber              -->
                    <!-- ============================= -->
                    <div class="card mb-3">
                        <h5 class="card-header">Contas a Receber (Títulos)</h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Franquia</th>
                                        <th>Documento</th>
                                        <th>Emissão</th>
                                        <th>Vencimento</th>
                                        <th class="text-end">Valor (R$)</th>
                                        <th>Status</th>
                                        <th>Obs.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>001245</td>
                                        <td>Franquia Centro</td>
                                        <td>NF 345/2025</td>
                                        <td>05/09/2025</td>
                                        <td>20/09/2025</td>
                                        <td class="text-end">R$ 2.450,00</td>
                                        <td><span class="badge bg-success">Pago</span></td>
                                        <td>PIX confirmado</td>
                                    </tr>
                                    <tr>
                                        <td>001371</td>
                                        <td>Franquia Norte</td>
                                        <td>NF 412/2025</td>
                                        <td>12/09/2025</td>
                                        <td>27/09/2025</td>
                                        <td class="text-end">R$ 1.980,00</td>
                                        <td><span class="badge bg-warning text-dark">Em Aberto</span></td>
                                        <td>Ag. comprovante</td>
                                    </tr>
                                    <tr>
                                        <td>001402</td>
                                        <td>Franquia Sul</td>
                                        <td>NF 433/2025</td>
                                        <td>15/09/2025</td>
                                        <td>25/09/2025</td>
                                        <td class="text-end">R$ 3.120,00</td>
                                        <td><span class="badge bg-danger">Vencido</span></td>
                                        <td>Recontatar cliente</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ============================= -->
                    <!-- Contas a Pagar                -->
                    <!-- ============================= -->
                    <div class="card mb-3">
                        <h5 class="card-header">Contas a Pagar</h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Fornecedor</th>
                                        <th>Documento</th>
                                        <th>Competência</th>
                                        <th>Vencimento</th>
                                        <th class="text-end">Valor (R$)</th>
                                        <th>Status</th>
                                        <th>Obs.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>P-2099</td>
                                        <td>Energia Amazonas</td>
                                        <td>FAT 99821</td>
                                        <td>09/2025</td>
                                        <td>30/09/2025</td>
                                        <td class="text-end">R$ 3.480,00</td>
                                        <td><span class="badge bg-warning text-dark">Em Aberto</span></td>
                                        <td>-</td>
                                    </tr>
                                    <tr>
                                        <td>P-2107</td>
                                        <td>Fornecedor Embalagens</td>
                                        <td>NF 88219</td>
                                        <td>09/2025</td>
                                        <td>28/09/2025</td>
                                        <td class="text-end">R$ 5.900,00</td>
                                        <td><span class="badge bg-danger">Vencido</span></td>
                                        <td>Negociar multa</td>
                                    </tr>
                                    <tr>
                                        <td>P-2111</td>
                                        <td>Granola Norte Ltda</td>
                                        <td>NF 12911</td>
                                        <td>09/2025</td>
                                        <td>29/09/2025</td>
                                        <td class="text-end">R$ 2.420,00</td>
                                        <td><span class="badge bg-success">Pago</span></td>
                                        <td>PIX 26/09</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ============================= -->
                    <!-- Pagamentos por Franquia       -->
                    <!-- ============================= -->
                    <div class="card mb-3">
                        <h5 class="card-header">Pagamentos por Franquia — Resumo</h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Franquia</th>
                                        <th class="text-end">Recebido (R$)</th>
                                        <th class="text-end">Em Aberto (R$)</th>
                                        <th class="text-end">Vencido (R$)</th>
                                        <th class="text-end">Total (R$)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Franquia Centro</strong></td>
                                        <td class="text-end">R$ 45.200,00</td>
                                        <td class="text-end">R$ 6.100,00</td>
                                        <td class="text-end">R$ 1.100,00</td>
                                        <td class="text-end">R$ 52.400,00</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Franquia Norte</strong></td>
                                        <td class="text-end">R$ 36.700,00</td>
                                        <td class="text-end">R$ 7.900,00</td>
                                        <td class="text-end">R$ 2.300,00</td>
                                        <td class="text-end">R$ 46.900,00</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Franquia Sul</strong></td>
                                        <td class="text-end">R$ 21.900,00</td>
                                        <td class="text-end">R$ 4.750,00</td>
                                        <td class="text-end">R$ 2.400,00</td>
                                        <td class="text-end">R$ 29.050,00</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th>Total</th>
                                        <th class="text-end">R$ 103.800,00</th>
                                        <th class="text-end">R$ 18.750,00</th>
                                        <th class="text-end">R$ 5.800,00</th>
                                        <th class="text-end">R$ 128.350,00</th>
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
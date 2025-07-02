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
                            <li class="menu-item"><a href="./relatorioDiario.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div>Diário</div>
                                </a></li>
                            <li class="menu-item active"><a href="./relatorioMensal.php?id=<?= urlencode($idSelecionado); ?>"
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
            <!-- / Menu -->

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
                    </div>
                </nav>
                <!-- /Search -->
                <div class="container-xxl flex-grow-1 container-p-y">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="fw-bold mb-0"><span class="text-muted fw-light">Financeiro /</span> Relatório Semanal</h4>

                    </div>

                    <!-- RESUMO SEMANAL SLIM -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-2">
                            <div class="card card-slim text-center">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted d-block">Entradas</small>
                                            <h4 class="mb-0">R$ 12.540,00</h4>
                                        </div>
                                        <span class="badge bg-label-success">+8%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="card card-slim text-center ">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted d-block">Saídas</small>
                                            <h4 class="mb-0">R$ 5.230,00</h4>
                                        </div>
                                        <span class="badge bg-label-danger">+3%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="card card-slim text-center">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted d-block">Saldo</small>
                                            <h4 class="mb-0">R$ 7.310,00</h4>
                                        </div>
                                        <span class="badge bg-label-success">+12%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="card card-slim ">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted d-block">Dias Úteis</small>
                                            <h4 class="mb-0">5/7 dias</h4>
                                        </div>
                                        <span class="badge bg-label-info">Sem 26</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- DETALHES POR semana -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center p-3">
                            <h5 class="mb-0">Detalhes por Semana</h5>
                            <div>
                                <button class="btn btn-sm btn-outline-secondary me-2">
                                    <i class="bx bx-download"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary">
                                    <i class="bx bx-printer"></i>
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Semana</th>
                                        <th class="text-end">Entradas</th>
                                        <th class="text-end">Saídas</th>
                                        <th class="text-end">Saldo</th>
                                        <th class="text-end">Vendas</th>
                                        <th class="text-end">Ticket Médio</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>26 (24/06 - 30/06)</strong></td>
                                        <td class="text-end">R$ 12.540,00</td>
                                        <td class="text-end">R$ 5.230,00</td>
                                        <td class="text-end text-success">R$ 7.310,00</td>
                                        <td class="text-end">146</td>
                                        <td class="text-end">R$ 85,89</td>
                                        <td><span class="badge bg-success">Fechada</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>25 (17/06 - 23/06)</strong></td>
                                        <td class="text-end">R$ 11.620,00</td>
                                        <td class="text-end">R$ 5.080,00</td>
                                        <td class="text-end text-success">R$ 6.540,00</td>
                                        <td class="text-end">132</td>
                                        <td class="text-end">R$ 78,25</td>
                                        <td><span class="badge bg-success">Fechada</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>24 (10/06 - 16/06)</strong></td>
                                        <td class="text-end">R$ 10.850,00</td>
                                        <td class="text-end">R$ 4.950,00</td>
                                        <td class="text-end text-success">R$ 5.900,00</td>
                                        <td class="text-end">125</td>
                                        <td class="text-end">R$ 76,40</td>
                                        <td><span class="badge bg-success">Fechada</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>23 (03/06 - 09/06)</strong></td>
                                        <td class="text-end">R$ 9.780,00</td>
                                        <td class="text-end">R$ 4.750,00</td>
                                        <td class="text-end text-success">R$ 5.030,00</td>
                                        <td class="text-end">118</td>
                                        <td class="text-end">R$ 72,71</td>
                                        <td><span class="badge bg-success">Fechada</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>22 (27/05 - 02/06)</strong></td>
                                        <td class="text-end">R$ 8.950,00</td>
                                        <td class="text-end">R$ 4.600,00</td>
                                        <td class="text-end text-success">R$ 4.350,00</td>
                                        <td class="text-end">110</td>
                                        <td class="text-end">R$ 69,55</td>
                                        <td><span class="badge bg-success">Fechada</span></td>
                                    </tr>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th>Média Mensal</th>
                                        <th class="text-end">R$ 10.748,00</th>
                                        <th class="text-end">R$ 4.922,00</th>
                                        <th class="text-end text-success">R$ 5.826,00</th>
                                        <th class="text-end">126</th>
                                        <th class="text-end">R$ 76,56</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- RESUMO SEMANAL -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-header p-3">
                                    <h5 class="mb-0">Movimentações</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="list-group list-group-flush">
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Maior Venda</h6>
                                                    <small class="text-muted">#4621 - Terça (25/06)</small>
                                                </div>
                                                <span class="text-success fw-semibold">R$ 1.250,00</span>
                                            </div>
                                        </div>
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Maior Despesa</h6>
                                                    <small class="text-muted">Aluguel - Quinta (27/06)</small>
                                                </div>
                                                <span class="text-danger fw-semibold">R$ 1.000,00</span>
                                            </div>
                                        </div>
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Dia com Mais Vendas</h6>
                                                    <small class="text-muted">Terça (25/06) - 35 vendas</small>
                                                </div>
                                                <span class="text-primary fw-semibold">R$ 3.120,00</span>
                                            </div>
                                        </div>
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Ticket Médio Semanal</h6>
                                                    <small class="text-muted">Média por venda</small>
                                                </div>
                                                <span class="text-info fw-semibold">R$ 85,89</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-header p-3">
                                    <h5 class="mb-0">Resumo por Categoria</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="list-group list-group-flush">
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Vendas</h6>
                                                    <small class="text-muted">Total de transações</small>
                                                </div>
                                                <div>
                                                    <span class="fw-semibold">146</span>
                                                    <small class="text-muted ms-2">(R$ 7.750,00)</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Serviços</h6>
                                                    <small class="text-muted">Serviços prestados</small>
                                                </div>
                                                <div>
                                                    <span class="fw-semibold">42</span>
                                                    <small class="text-muted ms-2">(R$ 3.125,00)</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Despesas Fixas</h6>
                                                    <small class="text-muted">Aluguel, contas, etc</small>
                                                </div>
                                                <div>
                                                    <span class="fw-semibold">8</span>
                                                    <small class="text-muted ms-2">(R$ 2.850,00)</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Outras Receitas</h6>
                                                    <small class="text-muted">Suprimentos, etc</small>
                                                </div>
                                                <div>
                                                    <span class="fw-semibold">15</span>
                                                    <small class="text-muted ms-2">(R$ 1.665,00)</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <style>
                .card-slim {
                    border-radius: 0.375rem;
                    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
                    height: 90px !important;
                }

                .card-slim .card-body {
                    padding: 0.75rem;
                }
            </style>
            <!-- build:js assets/vendor/js/core.js -->

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
                function openEditModal() {
                    new bootstrap.Modal(document.getElementById('editContaModal')).show();
                }

                function openDeleteModal() {
                    new bootstrap.Modal(document.getElementById('deleteContaModal')).show();
                }
            </script>
</body>

</html>
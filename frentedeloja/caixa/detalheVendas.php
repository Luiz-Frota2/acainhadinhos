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
    !isset($_SESSION['nivel']) // Verifica se o nível está na sessão
) {
    header("Location: ../index.php?id=$idSelecionado");
    exit;
}

// ✅ Conexão com o banco de dados
require '../../assets/php/conexao.php';

$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id = $_SESSION['usuario_id'];
$tipoUsuarioSessao = $_SESSION['nivel']; // "Admin" ou "Comum"

try {
    // Verifica se é um usuário de contas_acesso (Admin) ou funcionarios_acesso
    if ($tipoUsuarioSessao === 'Admin') {
        // Buscar na tabela de contas_acesso
        $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    } else {
        // Buscar na tabela de funcionarios_acesso
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
    // Para principal, verifica se é admin ou se pertence à mesma empresa
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

    // Verifica se o usuário pertence à mesma unidade ou é admin da principal_1
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

// ✅ Buscar imagem da empresa para usar como favicon
$iconeEmpresa = '../../assets/img/favicon/favicon.ico'; // Ícone padrão

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
    // Não mostra erro para o usuário para não quebrar a página
}

// Buscar dados das vendas
$vendas = [];
$totalVendas = 0;
$produtosVendas = [];

try {
    // Busca os itens de venda com venda_id incluído
    $stmt = $pdo->prepare("
        SELECT 
            iv.id,
            iv.venda_id,
            iv.nome_produto, 
            iv.quantidade, 
            iv.preco_unitario, 
            iv.preco_total, 
            iv.categoria,
            vr.data_venda,
            vr.forma_pagamento,
            vr.total as total_venda
        FROM itens_venda iv
        JOIN venda_rapida vr ON iv.venda_id = vr.id
        WHERE iv.empresa_id = :empresa_id 
        AND iv.id_caixa = :id_caixa
        AND iv.cpf_responsavel = :cpf_responsavel
        ORDER BY iv.data_registro DESC
    ");
    $stmt->bindParam(':empresa_id', $idSelecionado);
    $stmt->bindParam(':id_caixa', $chaveCaixa);
    $stmt->bindParam(':cpf_responsavel', $cpfUsuario);
    $stmt->execute();
    $produtosVendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular total de vendas
    $stmtTotal = $pdo->prepare("
        SELECT SUM(total) as total_vendas 
        FROM venda_rapida 
        WHERE empresa_id = :empresa_id 
        AND id_caixa = :id_caixa
        AND cpf_responsavel = :cpf_responsavel
    ");
    $stmtTotal->bindParam(':empresa_id', $idSelecionado);
    $stmtTotal->bindParam(':id_caixa', $chaveCaixa);
    $stmtTotal->bindParam(':cpf_responsavel', $cpfUsuario);
    $stmtTotal->execute();
    $totalVendas = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total_vendas'] ?? 0;
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar vendas: " . addslashes($e->getMessage()) . "');</script>";
}

// Buscar dados das sangrias
$sangrias = [];
$totalSangrias = 0;

try {
    $stmt = $pdo->prepare("
        SELECT valor, valor_liquido, data_registro 
        FROM sangrias 
        WHERE empresa_id = :empresa_id 
        AND id_caixa = :id_caixa
        AND cpf_responsavel = :cpf_responsavel
        ORDER BY data_registro DESC
    ");
    $stmt->bindParam(':empresa_id', $idSelecionado);
    $stmt->bindParam(':id_caixa', $chaveCaixa);
    $stmt->bindParam(':cpf_responsavel', $cpfUsuario);
    $stmt->execute();
    $sangrias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($sangrias as $sangria) {
        $totalSangrias += $sangria['valor'];
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar sangrias: " . addslashes($e->getMessage()) . "');</script>";
}

// Buscar dados dos suprimentos
$suprimentos = [];
$totalSuprimentos = 0;

try {
    $stmt = $pdo->prepare("
        SELECT valor_suprimento, valor_liquido, data_registro 
        FROM suprimentos 
        WHERE empresa_id = :empresa_id 
        AND id_caixa = :id_caixa
        AND cpf_responsavel = :cpf_responsavel
        ORDER BY data_registro DESC
    ");
    $stmt->bindParam(':empresa_id', $idSelecionado);
    $stmt->bindParam(':id_caixa', $chaveCaixa);
    $stmt->bindParam(':cpf_responsavel', $cpfUsuario);
    $stmt->execute();
    $suprimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($suprimentos as $suprimento) {
        $totalSuprimentos += $suprimento['valor_suprimento'];
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar suprimentos: " . addslashes($e->getMessage()) . "');</script>";
}

// Extrair a data do primeiro registro
$dataRelatorio = "Data não identificada";
if (!empty($produtosVendas)) {
    $dataVenda = new DateTime($produtosVendas[0]['data_venda']);
    $dataRelatorio = $dataVenda->format('Y-m-d');
} elseif (!empty($sangrias)) {
    $dataSangria = new DateTime($sangrias[0]['data_registro']);
    $dataRelatorio = $dataSangria->format('Y-m-d');
} elseif (!empty($suprimentos)) {
    $dataSuprimento = new DateTime($suprimentos[0]['data_registro']);
    $dataRelatorio = $dataSuprimento->format('Y-m-d');
}

?>

<!DOCTYPE html>
<html lang="pt-br" class="light-style customizer-hide" dir="ltr" data-theme="theme-default"
    data-assets-path="../assets/" data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <!-- Favicon da empresa carregado dinamicamente -->
    <link rel="icon" type="image/x-icon"
        href="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>" />

    <title>ERP - PDV</title>

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
                        <a href="index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Dashboard</div>
                        </a>
                    </li>

                    <!-- CAIXA -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Frente de Caixa</span>
                    </li>

                    <!-- Operações de Caixa -->
                    <li class="menu-item  ">
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
                            <li class="menu-item ">
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
                            <li class="menu-item">
                                <a href="./relatorioVendas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Resumo de Vendas</div>
                                </a>
                            </li>
                            <li class="menu-item active">
                                <a href="./relatorioVendas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Detalhes da Venda</div>
                                </a>
                            </li>

                        </ul>
                    </li>
                    <!-- END CAIXA -->

                    </li>
                    <!-- Misc -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
                    <li class="menu-item">
                        <a href="../sistemadeponto/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-group"></i>
                            <div data-i18n="Authentications">SIstema de Ponto</div>
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
                                <input type="text" class="form-control border-0 shadow-none" placeholder="Search..."
                                    aria-label="Search..." />
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
                                                    <!-- Exibindo o nome e nível do usuário -->
                                                    <span class="fw-semibold d-block"><?php echo $nomeUsuario; ?></span>

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
                                            <span class="align-middle">My Profile</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <i class="bx bx-cog me-2"></i>
                                            <span class="align-middle">Settings</span>
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


                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold mb-0">
                        <span class="text-muted fw-light">
                            <a href="./relatorioVendas.php?id=<?= urlencode($idSelecionado) ?>">Relatório</a> /
                        </span>
                        Detalhes de Vendas - <?= date('d/m/Y', strtotime($dataRelatorio)) ?>
                    </h4>
                    <h5 class="fw-semibold mt-2 mb-4 text-muted">Visualize os detalhes das vendas do dia selecionado
                    </h5>

                    <!-- Tabela de Vendas (modificada) -->
                    <div class="row">
                        <div class="col-lg-12 mb-4 order-0">
                            <div class="card">
                                <h5 class="card-header">Detalhes de Vendas</h5>
                                <div class="table-responsive text-nowrap">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Produto</th>
                                                <th>Categoria</th>
                                                <th>Quantidade</th>
                                                <th>Valor Unitário</th>
                                                <th>Valor Total</th>
                                                <th>Forma de Pagamento</th>
                                                <th>Data/Hora</th>
                                            </tr>
                                        </thead>
                                        <tbody class="table-border-bottom-0">
                                            <?php if (empty($produtosVendas)): ?>
                                                <tr>
                                                    <td colspan="7" class="text-center">Nenhum produto vendido encontrado
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($produtosVendas as $produto):
                                                    $dataVenda = new DateTime($produto['data_venda']);
                                                ?>
                                                    <tr>
                                                        <td>#<?= isset($produto['venda_id']) ? htmlspecialchars($produto['venda_id']) : 'N/A' ?>
                                                        </td>
                                                        <td><?= htmlspecialchars($produto['nome_produto']) ?></td>
                                                        <td><?= htmlspecialchars($produto['categoria'] ?? 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($produto['quantidade']) ?></td>
                                                        <td>R$ <?= number_format($produto['preco_unitario'], 2, ',', '.') ?>
                                                        </td>
                                                        <td>R$ <?= number_format($produto['preco_total'], 2, ',', '.') ?></td>
                                                        <td><?= htmlspecialchars($produto['forma_pagamento']) ?></td>
                                                        <td><?= $dataVenda->format('d/m/Y H:i') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <tr>
                                                    <td colspan="4" class="text-end fw-bold">Total Geral</td>
                                                    <td colspan="3" class="fw-bold">R$
                                                        <?= number_format($totalVendas, 2, ',', '.') ?>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabela de Sangrias -->
                    <div class="row">
                        <div class="col-lg-12 mb-4 order-0">
                            <div class="card">
                                <h5 class="card-header">Detalhes das Sangrias</h5>
                                <div class="table-responsive text-nowrap">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Data da Retirada</th>
                                                <th>Hora da Retirada</th>
                                                <th>Valor no Caixa</th>
                                                <th>Valor da Retirada</th>
                                            </tr>
                                        </thead>
                                        <tbody class="table-border-bottom-0">
                                            <?php if (empty($sangrias)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">Nenhuma sangria encontrada</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($sangrias as $sangria):
                                                    $dataSangria = new DateTime($sangria['data_registro']);
                                                ?>
                                                    <tr>
                                                        <td><?= $dataSangria->format('d-m-Y') ?></td>
                                                        <td><?= $dataSangria->format('H:i') ?></td>
                                                        <td>R$ <?= number_format($sangria['valor_liquido'], 2, ',', '.') ?></td>
                                                        <td>R$ <?= number_format($sangria['valor'], 2, ',', '.') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <tr>
                                                    <td colspan="3" class="text-end fw-bold">Total</td>
                                                    <td class="fw-bold">R$ <?= number_format($totalSangrias, 2, ',', '.') ?>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabela de Suprimentos -->
                    <div class="row">
                        <div class="col-lg-12 mb-4 order-0">
                            <div class="card">
                                <h5 class="card-header">Detalhes dos Suprimentos</h5>
                                <div class="table-responsive text-nowrap">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Data da Entrada</th>
                                                <th>Hora da Entrada</th>
                                                <th>Valor no Caixa</th>
                                                <th>Valor da Entrada</th>
                                            </tr>
                                        </thead>
                                        <tbody class="table-border-bottom-0">
                                            <?php if (empty($suprimentos)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">Nenhum suprimento encontrado</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($suprimentos as $suprimento):
                                                    $dataSuprimento = new DateTime($suprimento['data_registro']);
                                                ?>
                                                    <tr>
                                                        <td><?= $dataSuprimento->format('d-m-Y') ?></td>
                                                        <td><?= $dataSuprimento->format('H:i') ?></td>
                                                        <td>R$ <?= number_format($suprimento['valor_liquido'], 2, ',', '.') ?>
                                                        </td>
                                                        <td>R$
                                                            <?= number_format($suprimento['valor_suprimento'], 2, ',', '.') ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <tr>
                                                    <td colspan="3" class="text-end fw-bold">Total</td>
                                                    <td class="fw-bold">R$
                                                        <?= number_format($totalSuprimentos, 2, ',', '.') ?>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
                <script src="../../assets/vendor/libs/popper/popper.js"></script>
                <script src="../../assets/vendor/js/bootstrap.js"></script>
                <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
                <script src="../../assets/vendor/js/menu.js"></script>
                <script src="../../assets/js/main.js"></script>
</body>

</html>
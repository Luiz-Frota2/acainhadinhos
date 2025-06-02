<?php
session_start();
require_once '../../assets/php/conexao.php';

// ✅ Recupera os parâmetros da URL
$idSelecionado = $_GET['id'] ?? ''; // Obtém o valor do parâmetro 'id' da URL, como 'filial_3'
$id_entrega = isset($_GET['idEntrega']) ? intval($_GET['idEntrega']) : 0; // Obtém o valor de 'idEntrega' da URL

// ✅ Verifica se o usuário está logado corretamente
if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id'])
) {
    header("Location: ../../erp/login.php?id=$idSelecionado");
    exit;
}

// ✅ Validação de acesso por tipo de empresa
if (str_starts_with($idSelecionado, 'principal_')) {
    if ($_SESSION['tipo_empresa'] !== 'principal' || $_SESSION['empresa_id'] != 1) {
        echo "<script>
                alert('Acesso negado!');
                window.location.href = '../../erp/login.php?id=$idSelecionado';
              </script>";
        exit;
    }
} elseif (str_starts_with($idSelecionado, 'filial_')) {
    $idFilial = (int) str_replace('filial_', '', $idSelecionado);
    if ($_SESSION['tipo_empresa'] !== 'filial' || $_SESSION['empresa_id'] != $idFilial) {
        echo "<script>
                alert('Acesso negado!');
                window.location.href = '../../erp/login.php?id=$idSelecionado';
              </script>";
        exit;
    }
} else {
    echo "<script>
            alert('Empresa não identificada!');
            window.location.href = '../../erp/login.php?id=$idSelecionado';
          </script>";
    exit;
}

// ✅ Buscar nome e nível do usuário logado
$nomeUsuario = 'Usuário';
$nivelUsuario = 'Comum';
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

// ✅ Buscar configurações de entrega
$precoTaxaUnica = '';
$taxa_unica_db = 0;
$sem_taxa = 0;
$result = [];
$taxas = [];

if ($id_entrega > 0) {
    try {
        // Consulta entrega_taxas (vinculada à empresa)
        $sql = "SELECT * FROM entrega_taxas WHERE id_entrega = :id_entrega AND idSelecionado = :idSelecionado LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_entrega', $id_entrega, PDO::PARAM_INT);
        $stmt->bindParam(':idSelecionado', $idSelecionado, PDO::PARAM_STR);  // Usando o idSelecionado
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $taxa_unica_db = intval($result['taxa_unica']);
            $sem_taxa = intval($result['sem_taxa']);
        }

        // Consulta entrega_taxas_unica (vinculada à empresa)
        $sqlUnica = "SELECT * FROM entrega_taxas_unica WHERE id_entrega = :id_entrega AND id_selecionado = :idSelecionado";
        $stmtUnica = $pdo->prepare($sqlUnica);
        $stmtUnica->bindParam(':id_entrega', $id_entrega, PDO::PARAM_INT);
        $stmtUnica->bindParam(':idSelecionado', $idSelecionado, PDO::PARAM_STR);  // Usando o idSelecionado
        $stmtUnica->execute();
        $taxas = $stmtUnica->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($taxas)) {
            $precoTaxaUnica = $taxas[0]['valor_taxa'];
        }
    } catch (PDOException $e) {
        echo "<script>alert('Erro ao carregar as taxas: " . addslashes($e->getMessage()) . "');</script>";
    }
}

?>


<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
    data-assets-path="../assets/" data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>ERP - Delivery</title>

    <meta name="description" content="" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($logoEmpresa) ?>" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
        rel="stylesheet" />

    <!-- Icons. Uncomment required icon fonts -->
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

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

                    <!--DELIVERY-->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Delivery</span></li>

                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons tf-icons bx bx-food-menu"></i>
                            <div data-i18n="Authentications">Cardápio</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item ">
                                <a href="./produtoAdicionados.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div data-i18n="Basic">Produtos Adicionados</div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons tf-icons bx bx-cog"></i>
                            <div data-i18n="Authentications">Configuração</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./deliveryRetirada.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Delivery e Retirada</div>
                                </a>
                            </li>
                        </ul>
                        <ul class="menu-sub">
                            <li class="menu-item active">
                                <a href="./taxaEntrega.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Taxa de Entrega</div>
                                </a>
                            </li>
                        </ul>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./formaPagamento.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Formas de Pagamento </div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx  bx-building"></i>
                            <div data-i18n="Authentications">Empresa</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./sobreEmpresa.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Sobre</div>
                                </a>
                            </li>
                        </ul>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./enderecoEmpresa.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Endereço</div>
                                </a>
                            </li>
                        </ul>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./horarioFuncionamento.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div data-i18n="Basic">Horário</div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons tf-icons bx  bx-trending-up"></i>
                            <div data-i18n="Authentications">Relatorios</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./listarPedidos.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Lista de Pedidos</div>
                                </a>
                            </li>
                        </ul>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./maisVendidos.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Mais vendidos</div>
                                </a>
                            </li>
                        </ul>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./relatorioClientes.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div data-i18n="Basic">Clientes</div>
                                </a>
                            </li>
                        </ul>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./relatorioVendas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Vendas</div>
                                </a>
                            </li>
                        </ul>
                        <!--END DELIVERY-->


                        <!-- Misc -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
                    <li class="menu-item">
                        <a href="../rh/index.php" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-group"></i>
                            <div data-i18n="Authentications">RH</div>
                        </a>
                    </li>
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
                        <a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-box"></i>
                            <div data-i18n="Authentications">Estoque</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../clientes/index.ph?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
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

                        <!-- /Search -->

                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <!-- Place this tag where you want the button to render. -->
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

                <!-- Content -->
                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a
                                href="./deliveryRetirada.php?id=<?= urlencode($idSelecionado); ?>">Configuração</a>/</span>Taxa
                        de Entrega</h4>

                    <h5 class="fw-bold mt-3 mb-3 custor-font"><span class="text-muted fw-light">Selecione as opções de
                            taxas de entrega</span></h5>


                    <div class="row">
                        <div class="col-12">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <div class="row mb-0 text-md-start">

                                        <form action="../../assets/php/delivery/adicionarTaxas.php" method="POST"
                                            id="taxaForm">
                                            <input type="hidden" name="id_entrega" value="<?php echo $id_entrega; ?>">
                                            <input type="hidden" name="idSelecionado"
                                                value="<?php echo htmlspecialchars($idSelecionado); ?>">

                                            <!-- Sem Taxa -->
                                            <div class="col-12 col-md-12 check-card">
                                                <div class="d-flex align-items-center">
                                                    <div>
                                                        <strong>Sem Taxa</strong>
                                                    </div>
                                                    <div class="d-flex align-items-center ms-auto">
                                                        <strong for="toggleSemTaxa" id="labelSemTaxa" class="me-2">
                                                            <?php echo isset($result['sem_taxa']) && $result['sem_taxa'] == 1 ? 'Ligado' : 'Desligado'; ?>
                                                        </strong>
                                                        <div class="form-check form-switch mt-2">
                                                            <input type="hidden" name="sem_taxa" value="0">
                                                            <input class="form-check-input check w-px-50 h-px-20"
                                                                type="checkbox" id="toggleSemTaxa" name="sem_taxa"
                                                                value="1" <?php echo isset($result['sem_taxa']) && $result['sem_taxa'] == 1 ? 'checked' : ''; ?>
                                                                onchange="toggleTaxas('sem_taxa')">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- /Sem Taxa -->

                                            <!-- Taxa Única -->
                                            <div class="col-12 col-md-12 check-card mt-2">
                                                <div class="d-flex align-items-center">
                                                    <div>
                                                        <strong>Taxa Única</strong>
                                                    </div>
                                                    <div class="d-flex align-items-center ms-auto">
                                                        <strong for="toggleTaxa" id="labelToggleTaxa" class="me-2">
                                                            <?php echo isset($result['taxa_unica']) && $result['taxa_unica'] == 1 ? 'Ligado' : 'Desligado'; ?>
                                                        </strong>
                                                        <div class="form-check form-switch mt-2">
                                                            <input type="hidden" name="taxa_unica" value="0">
                                                            <input class="form-check-input check w-px-50 h-px-20"
                                                                type="checkbox" id="toggleTaxa" name="taxa_unica"
                                                                value="1" <?php echo isset($result['taxa_unica']) && $result['taxa_unica'] == 1 ? 'checked' : ''; ?>
                                                                onchange="toggleTaxas('taxa_unica')">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- /Taxa Única -->
                                        </form>

                                        <script>
                                            // Função para alternar entre "Sem Taxa" e "Taxa Única"
                                            function toggleTaxas(checkbox) {
                                                if (checkbox === 'sem_taxa') {
                                                    document.getElementById('toggleTaxa').checked = false;
                                                }
                                                if (checkbox === 'taxa_unica') {
                                                    document.getElementById('toggleSemTaxa').checked = false;
                                                }

                                                // Envia o formulário automaticamente após a alteração
                                                document.getElementById('taxaForm').submit();
                                            }
                                        </script>

                                    </div>
                                </div>
                            </div>


                            <!-- Div de Adicionar Taxa e Lista de Taxas -->
                            <?php if ($taxa_unica_db == 1): ?>
                                <!-- O formulário será exibido se taxa_unica na tabela entrega_taxas for igual a 1 -->
                                <form action="../../assets/php/delivery/adicionarTaxaUnica.php" method="POST">
                                    <input type="hidden" name="id_entrega" value="<?php echo $id_entrega; ?>">
                                    <input type="hidden" name="idSelecionado"
                                        value="<?php echo htmlspecialchars($idSelecionado); ?>">
                                    <!-- Passando idSelecionado -->

                                    <div class="card mb-0">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-12 col-md-12">
                                                    <label class="form-label" for="basic-default-company">Valor da
                                                        Taxa</label>
                                                    <div class="input-group input-group-merge mb-3">
                                                        <span class="input-group-text">R$</span>
                                                        <!-- Se não houver valor, o campo será vazio ou 0 -->
                                                        <input type="text" name="precoTaxaUnica" id="basic-default-company"
                                                            class="form-control" placeholder="00"
                                                            aria-label="Amount (to the nearest dollar)"
                                                            value="<?php echo $precoTaxaUnica !== '' ? number_format($precoTaxaUnica, 2, ',', '.') : ''; ?>">
                                                    </div>
                                                    <button type="submit" class="btn col-12 btn-primary">
                                                        <i class="bx bx-plus"></i> Adicionar Taxa
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>

                                <!-- LISTA DE TAXA -->
                                <h5 class="py-3 mt-2 mb-2 custor-font">
                                    <span class="text-muted fw-light">Lista de Taxa</span>
                                </h5>

                                <div class="card">
                                    <h5 class="card-header">Lista</h5>
                                    <div class="table-responsive text-nowrap">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Valor da Taxa</th>
                                                    <th>Data</th>
                                                    <th>Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody class="table-border-bottom-0">
                                                <?php if (count($taxas) > 0): ?>
                                                    <?php foreach ($taxas as $taxa): ?>
                                                        <tr>
                                                            <td>R$ <?php echo number_format($taxa['valor_taxa'], 2, ',', '.'); ?>
                                                            </td>
                                                            <td><?php echo date('d/m/Y', strtotime($taxa['created_at'])); ?></td>
                                                            <td>
                                                                <!-- Ícone de Excluir com Modal -->
                                                                <button class="btn btn-link text-danger p-0" title="Excluir"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#deleteTaxModal_<?php echo $taxa['id']; ?>">
                                                                    <i class="tf-icons bx bx-trash"></i>
                                                                </button>

                                                                <!-- Modal de Exclusão -->
                                                                <div class="modal fade"
                                                                    id="deleteTaxModal_<?php echo $taxa['id']; ?>" tabindex="-1"
                                                                    aria-labelledby="deleteTaxModalLabel" aria-hidden="true">
                                                                    <div class="modal-dialog">
                                                                        <div class="modal-content">
                                                                            <div class="modal-header">
                                                                                <h5 class="modal-title" id="deleteTaxModalLabel">
                                                                                    Excluir Taxa</h5>
                                                                                <button type="button" class="btn-close"
                                                                                    data-bs-dismiss="modal"
                                                                                    aria-label="Close"></button>
                                                                            </div>
                                                                            <div class="modal-body">
                                                                                <p>Tem certeza de que deseja excluir esta taxa?</p>
                                                                                <a href="../../assets/php/delivery/excluirTaxaUnica.php?id_taxa=<?php echo $taxa['id']; ?>&id_entrega=<?php echo $taxa['id_entrega']; ?>&idSelecionado=<?php echo $idSelecionado; ?>"
                                                                                    class="btn btn-danger">Excluir</a>
                                                                                <button type="button" class="btn btn-secondary mx-2"
                                                                                    data-bs-dismiss="modal">Cancelar</button>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <!-- /Modal de Exclusão -->
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="3">Nenhuma taxa cadastrada.</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <!-- /LISTA DE TAXAS -->
                            <?php else: ?>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Core JS -->
    <!-- build:js assets/vendor/js/core.js -->
    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>

    <script src="../../assets/vendor/js/menu.js"></script>
    <!-- endbuild -->

    <!-- Vendors JS -->
    <script src="../../assets/js/delivery/taxaEntrega.js"></script>
    <script src="../../assets/vendor/libs/apex-charts/apexcharts.js"></script>

    <!-- Main JS -->
    <script src="../../assets/js/main.js"></script>

    <!-- Page JS -->
    <script src="../../assets/js/dashboards-analytics.js"></script>

    <!-- Place this tag in your head or just before your close body tag. -->
    <script async defer src="https://buttons.github.io/buttons.js"></script>
</body>

</html>
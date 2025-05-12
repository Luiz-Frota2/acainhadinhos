<?php

session_start();
require_once '../../assets/php/conexao.php';

// ✅ Recupera o identificador vindo da URL
$idSelecionado = $_GET['idSelecionado'] ?? ''; // Correção para garantir que o idSelecionado seja corretamente recuperado
$id_produto = $_GET['id'] ?? ''; // Recupera o ID do produto

// ✅ Verifica se a pessoa está logada
if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id']) // Verificação do ID do usuário
) {
    // Redireciona para a página de login com o ID da empresa como parâmetro
    header("Location: .././login.php?id=$idSelecionado");
    exit;
}

// ✅ Valida o tipo de empresa e o acesso permitido
if (str_starts_with($idSelecionado, 'principal_')) {
    // Se a empresa for principal
    if ($_SESSION['tipo_empresa'] !== 'principal' || $_SESSION['empresa_id'] != 1) {
        echo "<script>
            alert('Acesso negado! Você não tem permissão para acessar a empresa principal.');
            window.location.href = '.././login.php?id=$idSelecionado';
        </script>";
        exit;
    }
    $id = 1;
} elseif (str_starts_with($idSelecionado, 'filial_')) {
    // Se a empresa for filial
    $idFilial = (int) str_replace('filial_', '', $idSelecionado);
    if ($_SESSION['tipo_empresa'] !== 'filial' || $_SESSION['empresa_id'] != $idFilial) {
        echo "<script>
            alert('Acesso negado! Você não tem permissão para acessar esta filial.');
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

// ✅ Se chegou até aqui, o acesso está liberado
// ✅ Buscar nome e nível do usuário logado
$nomeUsuario = 'Usuário';
$nivelUsuario = 'Comum'; // Valor padrão
$usuario_id = $_SESSION['usuario_id']; // Obtém o ID do usuário da sessão

// Verifica se o ID do usuário existe na sessão
if (!$usuario_id) {
    echo "<script>alert('Sessão expirada ou usuário não logado!'); window.location.href='../login.php';</script>";
    exit();
}

try {
    // Busca os dados do usuário (nome e nível) no banco de dados
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

// ✅ Verifica se o id do produto foi passado e é válido
if (isset($id_produto) && !empty($id_produto)) {
    // Busca os dados do produto
    $sql = "SELECT * FROM adicionarProdutos WHERE id_produto = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_produto]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$produto) {
        echo "<script>alert('Produto não encontrado!'); window.history.back();</script>";
        exit();
    }
} else {
    echo "<script>alert('ID do produto não fornecido!'); window.history.back();</script>";
    exit();
}


?>

<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>ERP - Delivery</title>

    <meta name="description" content="" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../assets/img/favicon/logo.png" />

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

                        <span class="app-brand-text demo menu-text fw-bolder ms-2" style=" text-transform: capitalize;">Açaínhadinhos</span>

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
                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons tf-icons bx bx-food-menu"></i>
                            <div data-i18n="Authentications">Cardápio</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./produtoAdicionados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Produtos Adicionados</div>
                                </a>
                            </li>
                        </ul>
                        <ul class="menu-sub">
                            <li class="menu-item active">
                                <a href="#" class="menu-link">
                                    <div data-i18n="Basic">Editar Produto</div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="menu-item">
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
                                <a href="./horarioFuncionamento.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Horário</div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons tf-icons bx bx-trending-up"></i>
                            <div data-i18n="Authentications">Relatorios</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./listarPedidos.html?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Lista de Pedidos</div>
                                </a>
                            </li>
                        </ul>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./maisVendidos.html?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Mais vendidos</div>
                                </a>
                            </li>
                        </ul>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./relatorioClientes.html?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Clientes</div>
                                </a>
                            </li>
                        </ul>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./relatorioVendas.html?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Vendas</div>
                                </a>
                            </li>
                        </ul>
                        <!--END DELIVERY-->

                        <!-- Misc -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
                    <li class="menu-item">
                        <a href="../rh/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
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
                        <a href="./pdv/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
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

                        </div>
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
                                                        <img src="../assets/img/avatars/1.png" alt
                                                            class="w-px-40 h-auto rounded-circle" />
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <span class="fw-semibold d-block"><?= htmlspecialchars($nomeUsuario); ?></span>
                                                    <small class="text-muted"><?= htmlspecialchars($nivelUsuario); ?></small>
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
                                        <a class="dropdown-item" href=".././logout.php?id=<?= urlencode($idSelecionado); ?>">
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

                <!--Formulario-->

                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a
                                href="./produtoAdicionados.php?id=<?= urlencode($idSelecionado); ?>">Cardápio</a>/</span>Editar Produto</h4>
                    <h5 class="fw-bold mt-3 mb-3 custor-font"><span class="text-muted fw-light">Atualize as informações
                            do produto</span></h5>

                    <!-- Basic Layout -->
                    <div class="row">
                        <div class="col-xl">
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Formulário</h5>
                                </div>
                                <div class="card-body">

                                    <!-- Formulário de edição de produto -->
                                    <form action="../../assets/php/delivery/editarProduto.php" method="POST"
                                        enctype="multipart/form-data">
                                        <!-- Passando o idSelecionado como hidden -->
                                        <input type="hidden" name="idSelecionado"
                                            value="<?php echo htmlspecialchars($idSelecionado); ?>">
                                        <input type="hidden" name="id_produto"
                                            value="<?php echo $produto['id_produto']; ?>">

                                        <!-- Imagem e Nome do Produto -->
                                        <div class="d-flex align-items-center mb-4 form-container">
                                            <div class="position-relative">
                                                <label for="inputGroupFile02" class="position-relative">
                                                    <img id="previewImg" class="img-preview"
                                                        src="../../assets/img/uploads/<?php echo htmlspecialchars($produto['imagem_produto']); ?>">
                                                    <input type="file" class="d-none" name="imagemProduto"
                                                        id="inputGroupFile02" accept="image/*"
                                                        onchange="previewImage(event)" />
                                                    <div class="icon-overlay">
                                                        <i class="fas fa-image"></i>
                                                    </div>
                                                </label>
                                            </div>

                                            <div class="ms-3 mt-4 flex-grow-1">
                                                <label class="form-label" for="nomeProduto">Nome do Produto</label>
                                                <input type="text" class="form-control input-custom" name="nomeProduto"
                                                    value="<?php echo htmlspecialchars($produto['nome_produto']); ?>" />
                                            </div>
                                        </div>

                                        <!-- Outras entradas do formulário -->
                                        <div class="mb-3">
                                            <label class="form-label" for="basic-default-company">Quantidade</label>
                                            <input type="text" class="form-control" name="quantidadeProduto"
                                                value="<?php echo $produto['quantidade_produto']; ?>" />
                                        </div>

                                        <label class="form-label" for="basic-default-company">Preço</label>
                                        <div class="input-group input-group-merge mb-3">
                                            <span class="input-group-text">R$</span>
                                            <input type="text" name="precoProduto" class="form-control"
                                                value="<?php echo number_format($produto['preco_produto'], 2, ',', '.'); ?>"
                                                aria-label="Amount (to the nearest dollar)">
                                            <span class="input-group-text"></span>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label" for="basic-default-message">Descrição</label>
                                            <textarea id="basic-default-message" name="descricaoProduto"
                                                class="form-control"><?php echo htmlspecialchars($produto['descricao_produto']); ?></textarea>
                                        </div>

                                        <div class="d-flex custom-button">
                                            <button type="submit"
                                                class="btn btn-primary col-12 w-100 col-md-auto">Editar Produto</button>
                                        </div>
                                    </form>

                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <!--END Formulario-->


                <!-- Core JS -->
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

                <script src="../../assets/js/delivery/taxaEntrega.js"></script>

</body>

</html>
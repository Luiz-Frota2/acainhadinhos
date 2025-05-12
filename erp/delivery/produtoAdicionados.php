<?php
session_start();

// ✅ Recupera o identificador vindo da URL
$idSelecionado = $_GET['id'] ?? '';

// ✅ Verifica se a pessoa está logada
if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['usuario_id']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa'])
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

// ✅ Se chegou até aqui, o acesso está liberado

require_once '../../assets/php/conexao.php';

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

// ✅ Recupera o tipo e empresa_id com base no idSelecionado
if (str_starts_with($idSelecionado, 'principal_')) {
    $empresa_id = 1;
    $tipo = 'principal';
} elseif (str_starts_with($idSelecionado, 'filial_')) {
    $empresa_id = (int) str_replace('filial_', '', $idSelecionado);
    $tipo = 'filial';
} else {
    echo "<script>alert('Empresa não identificada!'); history.back();</script>";
    exit;
}

// ✅ Busca as categorias
try {
    $sql = "SELECT id_categoria, nome_categoria 
            FROM adicionarCategoria 
            WHERE empresa_id = :empresa_id AND tipo = :tipo";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt->bindParam(':tipo', $tipo, PDO::PARAM_STR);
    $stmt->execute();

    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Erro ao buscar categorias: " . $e->getMessage();
    exit;
}

// ✅ Busca o nome e o nível do usuário logado
$usuario_id = $_SESSION['usuario_id'] ?? null;
$nomeUsuario = 'Usuário';
$nivelUsuario = 'Comum';  // Valor padrão

if ($usuario_id) {
    try {
        $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
        $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
        $stmt->execute();
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario) {
            $nomeUsuario = $usuario['usuario'];
            $nivelUsuario = $usuario['nivel'];  // Atribui o nível do usuário
        }
    } catch (PDOException $e) {
        $nomeUsuario = 'Erro ao buscar usuário';
        $nivelUsuario = 'Erro ao buscar nível';
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Core CSS -->
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />

    <!-- Page CSS -->

    <!-- Helpers -->
    <script src="../../assets/js/delivery/delivery.js"></script>
    <script src="../../assets/vendor/js/helpers.js"></script>
    <!-- build:js assets/vendor/js/core.js -->
    <script src="../../assets/vendor/libs/jquery/jquery.js" defer></script>
    <script src="../../assets/vendor/libs/popper/popper.js" defer></script>
    <script src="../../assets/vendor/js/bootstrap.js" defer></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js" defer></script>
    <script src="../../assets/vendor/js/menu.js" defer></script>
    <script src="../../assets/vendor/libs/apex-charts/apexcharts.js" defer></script>
    <script src="../../assets/js/main.js" defer></script>
    <script src="../../assets/js/dashboards-analytics.js" defer></script>

    <script async defer src="https://buttons.github.io/buttons.js"></script>

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
                        <span class="app-brand-text demo menu-text fw-bolder ms-2">Açainhadinhos</span>
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
                            <li class="menu-item active">
                                <a href="./produtoAdicionados.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div data-i18n="Basic">Produtos Adicionados</div>
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
                                    <div data-i18n="Basic">Formas de Pagamentos </div>
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
                            <i class="menu-icon tf-icons tf-icons bx bx-trending-up"></i>
                            <div data-i18n="Authentications">Relatorios</div>
                        </a>
                        <ul class="menu-sub">

                            <li class="menu-item">
                                <a href="./maisVendidos.html?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
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
                                <a href="./relatorioClientes.html?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
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

                            <!-- nav-item -->
                            <div class="nav-item d-flex align-items-center">
                                <i class="bx bx-search fs-4 lh-0"></i>
                                <input type="text" id="searchProdutos" class="form-control border-0 shadow-none"
                                    placeholder="Pesquisar produtos..." aria-label="Search..." />
                            </div>

                            <!-- end nav-item -->

                        </div>
                        <!-- /Search -->

                        <!-- ul -->
                        <ul class="navbar-nav flex-row align-items-center ms-auto">

                            <!-- Place this tag where you want the button to render. -->
                            <!-- User -->
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);"
                                    data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <img src="../../assets/img/avatars/5.png" alt
                                            class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>

                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="../../assets/img/avatars/5.png" alt
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
                                        <a class="dropdown-item"
                                            href=".././logout.php?id=<?= urlencode($idSelecionado); ?>">
                                            <i class="bx bx-power-off me-2"></i>
                                            <span class="align-middle">Sair</span>
                                        </a>
                                    </li>

                                </ul>

                            </li>
                            <!--/ User -->

                        </ul>
                        <!--end ul -->

                    </div>

                </nav>
                <!-- / Navbar -->

                <!--PRODUTOS CADASTRADOS-->

                <div class="conteudo-inner">
                    <div class="container">
                        <div class="row">
                            <div class="col-12" id="categorias">

                                <div class="container-group mb-5">
                                    <h5 class="title-categoria mb-0 mt-5"><b><i
                                                class="fas fa-book-open"></i>&nbsp;Categorias do Cardápio</b></h5>

                                    <div class="accordion" id="categoriasMenu">

                                        <?php
                                        foreach ($categorias as $categoria):
                                            $sqlProdutos = "SELECT * FROM adicionarProdutos WHERE id_categoria = :id_categoria";
                                            $stmtProdutos = $pdo->prepare($sqlProdutos);
                                            $stmtProdutos->bindParam(':id_categoria', $categoria['id_categoria'], PDO::PARAM_INT);
                                            $stmtProdutos->execute();
                                            $produtos = $stmtProdutos->fetchAll(PDO::FETCH_ASSOC);

                                        ?>

                                            <div class="card mt-3">

                                                <div class="card-drag"
                                                    id="heading<?php echo $categoria['id_categoria']; ?>">
                                                    <div class="infos">
                                                        <a href="#" class="name mb-0" data-bs-toggle="collapse"
                                                            data-bs-target="#collapse<?php echo $categoria['id_categoria']; ?>"
                                                            aria-expanded="true">
                                                            <span class="me-2"><i class="fa-solid fa-bowl-food"></i></span>
                                                            <b><?php echo htmlspecialchars($categoria['nome_categoria']); ?></b>
                                                        </a>
                                                    </div>

                                                    <div class="row">
                                                        <div class="accordion-button d-flex align-items-center col-12">
                                                            <div class="action-icons">

                                                                <!-- Botão para editar Categoria -->
                                                                <a href="#" data-bs-toggle="modal"
                                                                    data-bs-target="#editCategoryModal_<?php echo $categoria['id_categoria']; ?>"
                                                                    data-id="<?php echo $categoria['id_categoria']; ?>">
                                                                    <i class="tf-icons bx bx-pencil"></i>
                                                                </a>

                                                                <!-- Botão para Copiar Categoria -->
                                                                <a href="#" data-bs-toggle="modal"
                                                                    data-bs-target="#copyCategoryModal_<?php echo $categoria['id_categoria']; ?>"
                                                                    class="copy-category"
                                                                    data-id="<?php echo $categoria['id_categoria']; ?>">
                                                                    <i class="tf-icons bx bx-clipboard"></i>
                                                                </a>

                                                                <!-- Botão para Exlcuir Categoria -->
                                                                <a href="#" data-bs-toggle="modal"
                                                                    data-bs-target="#categoryModal_<?php echo $categoria['id_categoria']; ?>"
                                                                    class="delete-category"
                                                                    data-id="<?php echo $categoria['id_categoria']; ?>">
                                                                    <i class="tf-icons bx bx-trash" id="deleteIcon"></i>
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- onde vai ficar as modal de categoria -->

                                                <!-- Modal de Editar Categoria -->
                                                <div class="modal fade"
                                                    id="editCategoryModal_<?php echo $categoria['id_categoria']; ?>"
                                                    tabindex="-1" aria-labelledby="editCategoryModalLabel"
                                                    aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="editCategoryModalLabel">Editar
                                                                    Categoria</h5>
                                                                <button type="button" class="btn-close"
                                                                    data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <form action="../../assets/php/delivery/editarCategoria.php"
                                                                    method="POST">
                                                                    <!-- ID da categoria -->
                                                                    <input type="hidden" name="id_categoria"
                                                                        value="<?php echo $categoria['id_categoria']; ?>">

                                                                    <!-- ID da empresa (principal_1 ou filial_2) -->
                                                                    <input type="hidden" name="idSelecionado"
                                                                        value="<?php echo htmlspecialchars($_GET['id'] ?? ''); ?>">

                                                                    <div class="mb-3">
                                                                        <label for="categoriaNome" class="form-label">Nome
                                                                            da Categoria</label>
                                                                        <input type="text" class="form-control"
                                                                            id="categoriaNome" name="nome_categoria"
                                                                            value="<?php echo htmlspecialchars($categoria['nome_categoria']); ?>"
                                                                            required>
                                                                    </div>

                                                                    <button type="submit" class="btn btn-primary">Salvar
                                                                        Alterações</button>
                                                                    <button type="button" class="btn btn-secondary mx-2"
                                                                        data-bs-dismiss="modal">Cancelar</button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- /Modal de Editar Categoria -->

                                                <!-- Modal de Copiar Categoria -->
                                                <div class="modal fade"
                                                    id="copyCategoryModal_<?php echo $categoria['id_categoria']; ?>"
                                                    tabindex="-1" aria-labelledby="copyCategoryModalLabel"
                                                    aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="copyCategoryModalLabel">Copiar
                                                                    Categoria</h5>
                                                                <button type="button" class="btn-close"
                                                                    data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Tem certeza de que deseja copiar a categoria
                                                                    "<?php echo htmlspecialchars($categoria['nome_categoria']); ?>"
                                                                    e seus produtos?</p>
                                                                <a href="../../assets/php/delivery/copiarCategoria.php?id=<?php echo $categoria['id_categoria']; ?>&idSelecionado=<?php echo urlencode($_GET['id']); ?>"
                                                                    class="btn btn-primary">
                                                                    Sim, copiar
                                                                </a>
                                                                <button type="button" class="btn btn-secondary mx-2"
                                                                    data-bs-dismiss="modal">Cancelar</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- /Modal de Copiar Categoria -->

                                                <!-- Modal de Exclusão de Categoria -->
                                                <div class="modal fade"
                                                    id="categoryModal_<?php echo $categoria['id_categoria']; ?>"
                                                    tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="categoryModalLabel">Excluir
                                                                    Categoria</h5>
                                                                <button type="button" class="btn-close"
                                                                    data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Tem certeza de que deseja excluir a categoria
                                                                    "<?php echo htmlspecialchars($categoria['nome_categoria']); ?>"?
                                                                </p>
                                                                <a href="../../assets/php/delivery/excluirCategoria.php?id=<?php echo $categoria['id_categoria']; ?>&idSelecionado=<?php echo urlencode($_GET['id']); ?>"
                                                                    class="btn btn-danger">
                                                                    Sim, excluir
                                                                </a>
                                                                <button type="button" class="btn btn-secondary mx-2"
                                                                    data-bs-dismiss="modal">Cancelar</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- /Modal de Exclusão de Categoria -->

                                                <!-- onde termina as modal de categoria -->

                                                <!-- Listagem de Produtos -->
                                                <div id="collapse<?php echo $categoria['id_categoria']; ?>"
                                                    class="collapse" data-parent="#categoriasMenu">

                                                    <div class="card-body">

                                                        <!-- Os produtos -->
                                                        <?php foreach ($produtos as $produto): ?>
                                                            <div
                                                                class="product-card mb-2 p-2 d-flex align-items-center position-relative">
                                                                <div class="pe-2">
                                                                    <i class="bi bi-grip-vertical"></i>
                                                                </div>
                                                                <img src="../../assets/img/uploads/<?php echo htmlspecialchars($produto['imagem_produto']); ?>"
                                                                    class="product-img mb-2">
                                                                <div class="product-info">
                                                                    <h6 class="fw-bold mb-1">
                                                                        <?php echo htmlspecialchars($produto['nome_produto']); ?>
                                                                    </h6>
                                                                    <p class="text-muted mb-1">
                                                                        <?php echo htmlspecialchars($produto['descricao_produto']); ?>
                                                                    </p>
                                                                    <p class="price mb-1">R$
                                                                        <?php echo number_format($produto['preco_produto'], 2, ',', '.'); ?>
                                                                    </p>
                                                                </div>
                                                                <div class="product-actions d-flex align-items-center mb-5">

                                                                    <?php
                                                                    $sqlTotalOpcionais = "
                                                                            SELECT 
                                                                                -- Contar os opcionais simples para o produto
                                                                                (SELECT COUNT(*) FROM opcionais WHERE id_produto = :id_produto) AS total_opcionais_simples,
                                                                                
                                                                                -- Contar as opções dentro das seleções para o produto
                                                                                (SELECT COUNT(*) FROM opcionais_opcoes WHERE id_selecao IN (SELECT id FROM opcionais_selecoes WHERE id_produto = :id_produto)) AS total_opcionais_opcoes,
                                                                                
                                                                                -- Somar o total de opcionais simples
                                                                                (SELECT SUM(preco) FROM opcionais WHERE id_produto = :id_produto) AS total_preco_opcionais_simples,
                                                                                
                                                                                -- Somar o total de preços das opções dentro das seleções
                                                                                (SELECT SUM(preco) FROM opcionais_opcoes WHERE id_selecao IN (SELECT id FROM opcionais_selecoes WHERE id_produto = :id_produto)) AS total_preco_opcionais_opcoes,
                                                                                
                                                                                -- Somar o total geral (contagem)
                                                                                (SELECT COUNT(*) FROM opcionais WHERE id_produto = :id_produto) 
                                                                                + (SELECT COUNT(*) FROM opcionais_opcoes WHERE id_selecao IN (SELECT id FROM opcionais_selecoes WHERE id_produto = :id_produto)) AS total_opcionais
                                                                        ";

                                                                    $stmtTotalOpcionais = $pdo->prepare($sqlTotalOpcionais);
                                                                    $stmtTotalOpcionais->bindParam(':id_produto', $produto['id_produto'], PDO::PARAM_INT);
                                                                    $stmtTotalOpcionais->execute();


                                                                    $resultOpcionais = $stmtTotalOpcionais->fetch(PDO::FETCH_ASSOC);
                                                                    $total_opcionais = $resultOpcionais['total_opcionais'];
                                                                    ?>

                                                                    <!-- Botão para Cadastrar Adicionais -->
                                                                    <a href="#" class="icon-action cadastrar-adicionais"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#addAdicionaisModal_<?php echo $produto['id_produto']; ?>"
                                                                        data-id="<?php echo $produto['id_produto']; ?>">
                                                                        <!-- Verifica se o total de opcionais é maior que 0 para mostrar o badge -->
                                                                        <?php if ($total_opcionais > 0): ?>
                                                                            <span
                                                                                class="badge-adicionais"><?php echo $total_opcionais; ?></span>
                                                                        <?php endif; ?>
                                                                        <i class="fas fa-layer-group"></i>
                                                                    </a>

                                                                    <!-- Botão de Editar Produto-->
                                                                    <a
                                                                        href="./editarProduto.php?id=<?php echo $produto['id_produto']; ?>&idSelecionado=<?php echo urlencode($_GET['id']); ?>">
                                                                        <i class="tf-icons bx bx-pencil"></i>
                                                                    </a>

                                                                    <!-- Botão para Copiar Produto -->
                                                                    <a href="#" data-bs-toggle="modal"
                                                                        data-bs-target="#copyProductModal_<?php echo $produto['id_produto']; ?>"
                                                                        class="copy-product"
                                                                        data-id="<?php echo $produto['id_produto']; ?>">
                                                                        <i class="tf-icons bx bx-clipboard"></i>
                                                                    </a>

                                                                    <!-- Botão para Excluir Produto -->
                                                                    <a href="#" data-bs-toggle="modal"
                                                                        data-bs-target="#deleteProductModal_<?php echo $produto['id_produto']; ?>"
                                                                        class="delete-product"
                                                                        data-id="<?php echo $produto['id_produto']; ?>">
                                                                        <i class="tf-icons bx bx-trash deleteIconProduto"></i>
                                                                    </a>

                                                                </div>

                                                                <!-- Exibe a quantidade no canto inferior direito -->
                                                                <div class="product-quantity"
                                                                    style="position: absolute; bottom: 10px; right: 10px;">
                                                                    <span
                                                                        class="badge bg-primary"><?php echo $produto['quantidade_produto']; ?>
                                                                        unidades</span>
                                                                </div>
                                                            </div>

                                                            <!-- onde vai ficar as modal de produtos -->

                                                            <div class="modal fade" id="addAdicionaisModal_<?php echo $produto['id_produto']; ?>" tabindex="-1" aria-labelledby="addAdicionaisModalLabel" aria-hidden="true">
                                                                <div class="modal-dialog modal-lg">
                                                                    <div class="modal-content">
                                                                        <div class="modal-header text-white">
                                                                            <h5 class="modal-title" id="addAdicionaisModalLabel">Cadastrar Adicionais</h5>
                                                                            <button type="button" class="btn-close text-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <!-- Botão Adicionar Novo Opcional -->
                                                                            <a href="./adicionarOpcional.php?id=<?php echo $produto['id_produto']; ?>&idSelecionado=<?php echo urlencode($idSelecionado); ?>"
                                                                                class="add-item-container">
                                                                                <div class="add-item mb-4 mt-0 d-flex align-items-center justify-content-center bg-light p-3 rounded">
                                                                                    <i class="tf-icons bx bx-plus me-2"></i>
                                                                                    <b>Cadastrar Opcional</b>
                                                                                </div>
                                                                            </a>

                                                                            <!-- Listagem de Opcionais Simples -->
                                                                            <?php
                                                                            // Buscando opcionais simples para o produto com base no id_selecionado
                                                                            $sql_opcionais = "SELECT * FROM opcionais WHERE id_produto = ? AND id_selecionado = ?";
                                                                            $stmt_opcionais = $pdo->prepare($sql_opcionais);
                                                                            $stmt_opcionais->execute([$produto['id_produto'], $idSelecionado]);
                                                                            $opcionais = $stmt_opcionais->fetchAll(PDO::FETCH_ASSOC);

                                                                            if (count($opcionais) > 0): ?>
                                                                                <h6 class="text-center text-primary mt-3"><b>Opcionais Simples</b></h6>
                                                                                <?php foreach ($opcionais as $opcional): ?>
                                                                                    <div class="d-flex justify-content-between align-items-center border p-3 mb-2 rounded">
                                                                                        <div>
                                                                                            <b class="text-dark"><?= htmlspecialchars($opcional['nome']) ?></b>
                                                                                            <div class="mt-1">
                                                                                                <span class="badge bg-success text-white">+ R$ <?= number_format($opcional['preco'], 2, ',', '.') ?></span>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="d-flex">
                                                                                            <a href="./editarOpcionalSimples.php?id=<?= $opcional['id'] ?>&idSelecionado=<?php echo urlencode($idSelecionado); ?>" class="btn btn-sm btn-outline-primary me-2">
                                                                                                <i class="bx bx-edit"></i>
                                                                                            </a>
                                                                                            <a href="../../assets/php/delivery/excluirOpcionalSimples.php?id=<?= $opcional['id'] ?>&idSelecionado=<?= urlencode($idSelecionado); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Tem certeza que deseja excluir este opcional?');">
                                                                                                <i class="bx bx-trash"></i>
                                                                                            </a>
                                                                                        </div>
                                                                                    </div>
                                                                                <?php endforeach; ?>
                                                                            <?php else: ?>
                                                                                <p class="text-center text-muted"></p>
                                                                            <?php endif; ?>

                                                                            <!-- Listagem de Seleções de Opcionais -->
                                                                            <?php
                                                                            // Buscando seleções de opcionais para o produto com base no id_selecionado
                                                                            $sql_selecoes = "SELECT * FROM opcionais_selecoes WHERE id_produto = ? AND id_selecionado = ?";
                                                                            $stmt_selecoes = $pdo->prepare($sql_selecoes);
                                                                            $stmt_selecoes->execute([$produto['id_produto'], $idSelecionado]);
                                                                            $selecoes = $stmt_selecoes->fetchAll(PDO::FETCH_ASSOC);

                                                                            if (count($selecoes) > 0): ?>
                                                                                <h6 class="text-center text-primary mt-4"><b>Seleções de Opcionais</b></h6>
                                                                                <?php foreach ($selecoes as $selecao): ?>
                                                                                    <div class="container-group border p-3 rounded mb-3">
                                                                                        <div class="d-flex flex-wrap justify-content-between align-items-center">
                                                                                            <div>
                                                                                                <p class="title-categoria text-secondary mb-1"><b><?= htmlspecialchars($selecao['titulo']) ?></b></p>
                                                                                                <span class="sub-title-categoria text-muted">Mínimo: <?= $selecao['minimo'] ?> | Máximo: <?= $selecao['maximo'] ?></span>
                                                                                            </div>
                                                                                            <div class="d-flex mt-2 mb-2">
                                                                                                <a href="./editarSelecao.php?id=<?= $selecao['id'] ?>&idSelecionado=<?php echo urlencode($idSelecionado); ?>" class="btn btn-sm btn-outline-primary me-2">
                                                                                                    <i class="bx bx-edit"></i> Editar
                                                                                                </a>
                                                                                                <a href="../../assets/php/delivery/excluirSelecao.php?id=<?= $selecao['id'] ?>&idSelecionado=<?php echo urlencode($idSelecionado); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Tem certeza que deseja excluir esta seleção?');">
                                                                                                    <i class="bx bx-trash"></i> Excluir
                                                                                                </a>
                                                                                            </div>
                                                                                        </div>

                                                                                        <!-- Listagem de Opcionais Dentro da Seleção -->
                                                                                        <?php
                                                                                        $sql_opcoes = "SELECT * FROM opcionais_opcoes WHERE id_selecao = ? AND id_selecionado = ?";
                                                                                        $stmt_opcoes = $pdo->prepare($sql_opcoes);
                                                                                        $stmt_opcoes->execute([$selecao['id'], $idSelecionado]);
                                                                                        $opcoes = $stmt_opcoes->fetchAll(PDO::FETCH_ASSOC);

                                                                                        if (count($opcoes) > 0): ?>
                                                                                            <div class="row mt-2">
                                                                                                <?php foreach ($opcoes as $opcao): ?>
                                                                                                    <div class="col-12 col-md-6">
                                                                                                        <div class="list-group-item d-flex flex-wrap justify-content-between align-items-center border rounded p-2 mb-2">
                                                                                                            <div>
                                                                                                                <b class="text-dark"><?= htmlspecialchars($opcao['nome']) ?></b>
                                                                                                                <div class="mt-1">
                                                                                                                    <span class="badge bg-success text-white">+ R$ <?= number_format($opcao['preco'], 2, ',', '.') ?></span>
                                                                                                                </div>
                                                                                                            </div>
                                                                                                            <div class="d-flex align-items-center">
                                                                                                                <a href="./editarOpcionalSelecao.php?id=<?= $opcao['id'] ?>&idSelecionado=<?php echo urlencode($idSelecionado); ?>" class="btn btn-sm btn-outline-primary me-2">
                                                                                                                    <i class="bx bx-edit"></i>
                                                                                                                </a>
                                                                                                                <a href="../../assets/php/delivery/excluirOpcionalSelecao.php?id=<?= $opcao['id'] ?>&idSelecionado=<?php echo urlencode($idSelecionado); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Tem certeza que deseja excluir este opcional?');">
                                                                                                                    <i class="bx bx-trash"></i>
                                                                                                                </a>
                                                                                                            </div>
                                                                                                        </div>
                                                                                                    </div>
                                                                                                <?php endforeach; ?>
                                                                                            </div>
                                                                                        <?php else: ?>
                                                                                            <p class="text-center text-muted"></p>
                                                                                        <?php endif; ?>
                                                                                    </div>
                                                                                <?php endforeach; ?>
                                                                            <?php else: ?>
                                                                                <p class="text-center text-muted"></p>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <!-- /Modal de Cadastrar Adicionais -->

                                                            <!-- Modal de Copiar Produto -->
                                                            <div class="modal fade"
                                                                id="copyProductModal_<?php echo $produto['id_produto']; ?>"
                                                                tabindex="-1" aria-labelledby="copyProductModalLabel"
                                                                aria-hidden="true">
                                                                <div class="modal-dialog">
                                                                    <div class="modal-content">
                                                                        <div class="modal-header">
                                                                            <h5 class="modal-title" id="copyProductModalLabel">
                                                                                Copiar Produto</h5>
                                                                            <button type="button" class="btn-close"
                                                                                data-bs-dismiss="modal"
                                                                                aria-label="Close"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <p>Tem certeza de que deseja copiar o produto
                                                                                "<?php echo htmlspecialchars($produto['nome_produto']); ?>"?
                                                                            </p>
                                                                            <a href="../../assets/php/delivery/copiarProduto.php?id=<?php echo $produto['id_produto']; ?>&empresa_id=<?php echo $idSelecionado; ?>"
                                                                                class="btn btn-primary">Sim, copiar</a>
                                                                            <button type="button" class="btn btn-secondary mx-2"
                                                                                data-bs-dismiss="modal">Cancelar</button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <!-- /Modal de Copiar Produto -->

                                                            <!-- Modal de Excluir Produto -->
                                                            <div class="modal fade"
                                                                id="deleteProductModal_<?php echo $produto['id_produto']; ?>"
                                                                tabindex="-1" aria-labelledby="deleteProductModalLabel"
                                                                aria-hidden="true">
                                                                <div class="modal-dialog">
                                                                    <div class="modal-content">
                                                                        <div class="modal-header">
                                                                            <h5 class="modal-title"
                                                                                id="deleteProductModalLabel">Excluir Produto
                                                                            </h5>
                                                                            <button type="button" class="btn-close"
                                                                                data-bs-dismiss="modal"
                                                                                aria-label="Close"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <p>Tem certeza de que deseja excluir o produto
                                                                                "<?php echo htmlspecialchars($produto['nome_produto']); ?>"?
                                                                            </p>
                                                                            <!-- Passando idSelecionado na URL para o script de exclusão -->
                                                                            <a href="../../assets/php/delivery/excluirProduto.php?id=<?php echo $produto['id_produto']; ?>&empresa_id=<?php echo $idSelecionado; ?>"
                                                                                class="btn btn-danger">Sim, excluir</a>
                                                                            <button type="button" class="btn btn-secondary mx-2"
                                                                                data-bs-dismiss="modal">Cancelar</button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <!-- /Modal de Excluir Produto -->

                                                            <!-- ONDE TERMINA A MODAL DE PRODUTOS -->

                                                        <?php endforeach; ?>
                                                        <!-- /Produtos -->

                                                        <!-- Botão Adicionar Novo Produto -->
                                                        <a href="./adicionarProduto.php?id_categoria=<?= $categoria['id_categoria']; ?>&id=<?= urlencode($idSelecionado); ?>"
                                                            class="add-item-container">
                                                            <div class="add-item mb-2 mt-4">
                                                                <i class="tf-icons bx bx-plus me-2"></i>Adicionar novo
                                                                produto
                                                            </div>
                                                        </a>

                                                    </div>

                                                </div>
                                                <!-- /Listagem de Produtos -->

                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Adicionar nova categoria -->
                                    <div id="addCategoryLink"
                                        class="mt-3 add-category justify-content-center d-flex text-center align-items-center"
                                        data-bs-toggle="modal" data-bs-target="#categoryModal">
                                        <i class="tf-icons bx bx-plus me-2"></i>
                                        <span>Adicionar nova categoria</span>
                                    </div>

                                    <!-- Modal de Adicionar Categoria -->
                                    <div class="modal fade" id="categoryModal" tabindex="-1"
                                        aria-labelledby="categoryModalLabel" aria-hidden="true">

                                        <!-- modal-dialong -->
                                        <div class="modal-dialog">

                                            <!-- modal-content -->
                                            <div class="modal-content">

                                                <!-- modal-header -->
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="categoryModalLabel">Nova Categoria</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                        aria-label="Close"></button>
                                                </div>
                                                <!-- end modal-header -->

                                                <!-- modal-body -->
                                                <div class="modal-body">

                                                    <!-- form -->
                                                    <form action="../../assets/php/delivery/adicionarCategoria.php"
                                                        method="POST">

                                                        <div class="mb-3">
                                                            <label for="newCategory" class="form-label">Nome da
                                                                Categoria</label>
                                                            <input type="text" id="newCategory" name="nomeCategoria"
                                                                class="form-control"
                                                                placeholder="Digite o nome da categoria" required>
                                                        </div>

                                                        <!-- Campo hidden com o idSelecionado -->
                                                        <input type="hidden" name="id"
                                                            value="<?= htmlspecialchars($idSelecionado) ?>">

                                                        <button type="submit" class="btn btn-primary">Adicionar
                                                            Categoria</button>

                                                    </form>
                                                    <!-- end form -->

                                                </div>
                                                <!-- end modal-body -->

                                            </div>
                                            <!-- end modal-content -->

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>
                <!-- END PRODUTOS CADASTRADOS -->

            </div>

        </div>

    </div>

    <script>
        document.getElementById("searchProdutos").addEventListener("input", function() {
            const termo = this.value.toLowerCase();
            const produtos = document.querySelectorAll(".product-card");
            const collapses = document.querySelectorAll('[id^="collapse"]');

            // Esconde todos os produtos inicialmente
            produtos.forEach((produto) => {
                const textoProduto = produto.textContent.toLowerCase();
                const corresponde = textoProduto.includes(termo);
                produto.style.display = corresponde ? "" : "none";
            });

            // Exibe/oculta os collapses com base nos produtos visíveis
            collapses.forEach((collapse) => {
                const produtosVisiveis = collapse.querySelectorAll(".product-card:not([style*='display: none'])");

                if (termo && produtosVisiveis.length > 0) {
                    collapse.classList.add("show");
                } else {
                    collapse.classList.remove("show");
                }
            });
        });
    </script>


</body>

</html>
<!DOCTYPE html>
<html
  lang="pt-br"
  class="light-style layout-menu-fixed"
  dir="ltr"
  data-theme="theme-default"
  data-assets-path="../assets/"
  data-template="vertical-menu-template-free"
>
  <head>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"
    />

    <title>ERP - Elétrica</title>

    <meta name="description" content="" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="./assets/img/favicon/logo.png" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
      rel="stylesheet"
    />

    <!-- Icons. Uncomment required icon fonts -->
    <link rel="stylesheet" href="../css/fontawesome.css" />
    <link rel="stylesheet" href="./assets/vendor/fonts/boxicons.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Core CSS -->
    <link rel="stylesheet" href="./assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="./assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="./assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="./assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <link rel="stylesheet" href="./assets/vendor/libs/apex-charts/apex-charts.css" />

    <!-- Page CSS -->

    <!-- Helpers -->
    <script src="./assets/js/delivery/delivery.js"></script>
    <script src="./assets/vendor/js/helpers.js"></script>
    <!-- build:js assets/vendor/js/core.js -->
    <script src="./assets/vendor/libs/jquery/jquery.js" defer></script>
    <script src="./assets/vendor/libs/popper/popper.js" defer></script>
    <script src="./assets/vendor/js/bootstrap.js" defer></script>
    <script src="./assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js" defer></script>
    <script src="./assets/vendor/js/menu.js" defer></script>
    <script src="./assets/vendor/libs/apex-charts/apexcharts.js" defer></script>
    <script src="./assets/js/main.js" defer></script>
    <script src="./assets/js/dashboards-analytics.js" defer></script>

    <script async defer src="https://buttons.github.io/buttons.js"></script>

    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="./assets/js/config.js"></script>
  </head>

<body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">

        <div class="layout-container">
            <!-- Menu -->

            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">

                <div class="app-brand demo">

                    <a href="./dashboard.html" class="app-brand-link">
                        <span class="app-brand-text demo menu-text fw-bolder ms-2">Elétrica</span>
                    </a>

                    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
                        <i class="bx bx-chevron-left bx-sm align-middle"></i>
                    </a>

                </div>

                <div class="menu-inner-shadow"></div>

                <ul class="menu-inner py-1">

                    <!-- Dashboard -->
                    <li class="menu-item">
                        <a href="./index.html" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Dashboard</div>
                        </a>
                    </li>
                
                    <!--DELIVERY-->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Delivery</span></li>

                        <li class="menu-item">

                            <a href="javascript:void(0);" class="menu-link menu-toggle">
                                <i class="menu-icon tf-icons tf-icons bx bx-cart"></i>
                                <div data-i18n="Authentications">Pedidos</div>
                            </a>

                            <ul class="menu-sub">
                                <li class="menu-item">
                                <a href="./listarPedidos.html" class="menu-link" >
                                    <div data-i18n="Basic">Lista de Pedidos</div>
                                </a>
                                </li>
                            </ul>
                        </li>

                        <li class="menu-item active open">
                            <a href="javascript:void(0);" class="menu-link menu-toggle">
                                <i class="menu-icon tf-icons tf-icons bx bx-food-menu"></i>
                                <div data-i18n="Authentications">Catálogo</div>
                            </a>
                            <ul class="menu-sub">
                                <li class="menu-item active">
                                    <a href="./produtoAdicionados.php" class="menu-link" >
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
                                <a href="./deliveryRetirada.html" class="menu-link" >
                                    <div data-i18n="Basic">Delivery e Retirada</div>
                                </a>
                            </li>

                        </ul>

                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./formaPagamento.html" class="menu-link" >
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
                                <a href="./sobreEmpresa.html" class="menu-link" >
                                    <div data-i18n="Basic">Sobre</div>
                                </a>
                            </li>
                        </ul>
                    
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./enderecoEmpresa.html" class="menu-link" >
                                    <div data-i18n="Basic">Endereço</div>
                                </a>
                            </li>
                        </ul>

                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./horarioFuncionamento.html" class="menu-link" >
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
                                <a href="./maisVendidos.html" class="menu-link" >
                                    <div data-i18n="Basic">Mais vendidos</div>
                                </a>
                            </li>

                        </ul>

                        <ul class="menu-sub">
                            <li class="menu-item">
                                    <a href="./relatorioClientes.html" class="menu-link" >
                                    <div data-i18n="Basic">Clientes</div>
                                </a>
                            </li>
                        </ul>

                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./relatorioVendas.html" class="menu-link" >
                                    <div data-i18n="Basic">Vendas</div>
                                </a>
                            </li>
                        </ul>
                    <!--END DELIVERY-->

                    <!-- Misc -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>

                        <li class="menu-item">
                            <a href="javascript:void(0);" class="menu-link menu-toggle">
                                <i class="menu-icon tf-icons bx bx-lock-open-alt"></i>
                                <div data-i18n="Authentications">Autenticação</div>
                            </a>

                            <ul class="menu-sub">

                                <li class="menu-item">
                                    <a href="../index.html" class="menu-link" target="_blank">
                                        <div data-i18n="Basic">Login</div>
                                    </a>
                                </li>

                                <li class="menu-item">
                                    <a href="../criarConta.html" class="menu-link" target="_blank">
                                        <div data-i18n="Basic">Cadastro</div>
                                    </a>
                                </li>

                                <li class="menu-item">
                                    <a href="../redefine.html" class="menu-link" target="_blank">
                                        <div data-i18n="Basic">Redefine Senha</div>
                                    </a>
                                </li>
                                
                            </ul>
                                
                        </li>

                        <li class="menu-item">

                            <a href="https://wa.me/92991515710" target="_blank" class="menu-link mb-4">
                                <i class="menu-icon tf-icons bx bx-collection"></i>
                                <div data-i18n="Basic">Suporte</div>
                            </a>

                        </li>

                    </li>
                    <!--END MISC-->

                </ul>
                
            </aside>
            <!-- / Menu -->

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

                        <!-- Search -->
                        <div class="navbar-nav align-items-center">

                            <!-- nav-item -->
                            <div class="nav-item d-flex align-items-center">
                                <i class="bx bx-search fs-4 lh-0"></i>
                                <input type="text" id="searchInput" class="form-control border-0 shadow-none" placeholder="Pesquisar produtos..." aria-label="Search..." onkeyup="filterProducts()"/>
                            </div>

                            <!-- end nav-item -->
                            
                        </div>
                        <!-- /Search -->

                        <!-- ul -->
                        <ul class="navbar-nav flex-row align-items-center ms-auto">

                            <!-- Place this tag where you want the button to render. -->
                            <!-- User -->
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <img src="./assets/img/avatars/1.png" alt class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>

                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="../assets/img/avatars/1.png" alt class="w-px-40 h-auto rounded-circle" />
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <span class="fw-semibold d-block">John Doe</span>
                                                    <small class="text-muted">Admin</small>
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
                                                <span class="flex-shrink-0 badge badge-center rounded-pill bg-danger w-px-20 h-px-20">4</span>
                                            </span>
                                        </a>
                                    </li>

                                    <li>
                                        <div class="dropdown-divider"></div>
                                    </li>

                                    <li>
                                        <a class="dropdown-item" href="index.html">
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
                                    <h5 class="title-categoria mb-0 mt-5"><b><i class="fas fa-book-open"></i>&nbsp;Categorias do Cardápio</b></h5>

                                    <?php
                                        require_once './assets/php/conexao.php';

                                        try {
                                            $sql = "SELECT id_categoria, nome_categoria, icone_categoria FROM adicionarcategoria";
                                            $stmt = $pdo->prepare($sql);
                                            $stmt->execute();
                                            $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        } catch (PDOException $e) {
                                            echo "Erro ao buscar categorias: " . $e->getMessage();
                                            exit;
                                        }
                                    ?>

                                    <div class="accordion" id="categoriasMenu">

                                        <?php
                                            foreach ($categorias as $categoria):
                                                $sqlProdutos = "SELECT * FROM adicionarprodutos WHERE id_categoria = :id_categoria";
                                                $stmtProdutos = $pdo->prepare($sqlProdutos);
                                                $stmtProdutos->bindParam(':id_categoria', $categoria['id_categoria'], PDO::PARAM_INT);
                                                $stmtProdutos->execute();
                                                $produtos = $stmtProdutos->fetchAll(PDO::FETCH_ASSOC);
                                            ?>

                                            <div class="card mt-3">

                                                <div class="card-drag" id="heading<?php echo $categoria['id_categoria']; ?>">
                                                    <div class="infos">
                                                        <a href="#" class="name mb-0" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $categoria['id_categoria']; ?>" aria-expanded="true">
                                                            <span class="me-2">  <i class="<?php echo htmlspecialchars($categoria['icone_categoria']); ?>"></i></span>
                                                            <b><?php echo htmlspecialchars($categoria['nome_categoria']); ?></b>
                                                        </a>
                                                    </div>

                                                    <div class="row">
                                                        <div class="accordion-button d-flex align-items-center col-12">
                                                            <div class="action-icons">

                                                                <!-- Botão para editar Categoria -->
                                                                <a href="#" data-bs-toggle="modal" data-bs-target="#editCategoryModal_<?php echo $categoria['id_categoria']; ?>" data-id="<?php echo $categoria['id_categoria']; ?>">
                                                                    <i class="tf-icons bx bx-pencil"></i>
                                                                </a>

                                                                <!-- Botão para Copiar Categoria -->
                                                                <a href="#" data-bs-toggle="modal" data-bs-target="#copyCategoryModal_<?php echo $categoria['id_categoria']; ?>" class="copy-category" data-id="<?php echo $categoria['id_categoria']; ?>">
                                                                    <i class="tf-icons bx bx-clipboard"></i>
                                                                </a>

                                                                <!-- Botão para Exlcuir Categoria -->
                                                                <a href="#" data-bs-toggle="modal" data-bs-target="#categoryModal_<?php echo $categoria['id_categoria']; ?>" class="delete-category" data-id="<?php echo $categoria['id_categoria']; ?>">
                                                                    <i class="tf-icons bx bx-trash" id="deleteIcon"></i>
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Modal de Editar Categoria -->
                                                <div class="modal fade" id="editCategoryModal_<?php echo $categoria['id_categoria']; ?>" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="editCategoryModalLabel">Editar Categoria</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <form action="./assets/php/delivery/editarCategoria.php" method="POST">
                                                                    <input type="hidden" name="id_categoria" value="<?php echo $categoria['id_categoria']; ?>">
                                                                    <div class="mb-3">
                                                                        <label for="categoriaNome" class="form-label">Nome da Categoria</label>
                                                                        <input type="text" class="form-control" id="categoriaNome" name="nome_categoria" value="<?php echo htmlspecialchars($categoria['nome_categoria']); ?>" required>
                                                                    </div>
                                                                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                                                                    <button type="button" class="btn btn-secondary mx-2" data-bs-dismiss="modal">Cancelar</button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- /Modal de Editar Categoria -->

                                                <!-- Modal de Copiar Categoria -->
                                                <div class="modal fade" id="copyCategoryModal_<?php echo $categoria['id_categoria']; ?>" tabindex="-1" aria-labelledby="copyCategoryModalLabel" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="copyCategoryModalLabel">Copiar Categoria</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Tem certeza de que deseja copiar a categoria "<?php echo htmlspecialchars($categoria['nome_categoria']); ?>"?</p>
                                                                <a href="./assets/php/delivery/copiarCategoria.php?id=<?php echo $categoria['id_categoria']; ?>" class="btn btn-primary">Sim, copiar</a>
                                                                <button type="button" class="btn btn-secondary mx-2" data-bs-dismiss="modal">Cancelar</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- /Modal de Copiar Categoria -->

                                                <!-- Modal de Exclusão de Categoria -->
                                                <div class="modal fade" id="categoryModal_<?php echo $categoria['id_categoria']; ?>" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="categoryModalLabel">Excluir Categoria</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Tem certeza de que deseja excluir a categoria "<?php echo htmlspecialchars($categoria['nome_categoria']); ?>"?</p>
                                                                <a href="./assets/php/delivery/excluirCategoria.php?id=<?php echo $categoria['id_categoria']; ?>" class="btn btn-danger">Sim, excluir</a>
                                                                <button type="button" class="btn btn-secondary mx-2" data-bs-dismiss="modal">Cancelar</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- /Modal de Exclusão de Categoria -->

                                                <!-- Listagem de Produtos -->
                                                <div id="collapse<?php echo $categoria['id_categoria']; ?>" class="collapse show" data-parent="#categoriasMenu">

                                                    <div class="card-body">

                                                        <!-- Os produtos -->
                                                        <?php foreach ($produtos as $produto): ?>
                                                            <div class="product-card mb-2 p-2 d-flex align-items-center position-relative">
                                                                <div class="pe-2">
                                                                    <i class="bi bi-grip-vertical"></i>
                                                                </div>
                                                                <img src="./assets/img/uploads/<?php echo htmlspecialchars($produto['imagem_produto']); ?>" class="product-img mb-2">
                                                                <div class="product-info">
                                                                    <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($produto['nome_produto']); ?></h6>
                                                                    <p class="text-muted mb-1"><?php echo htmlspecialchars($produto['descricao_produto']); ?></p>
                                                                    <p class="price mb-1">R$ <?php echo number_format($produto['preco_produto'], 2, ',', '.'); ?></p>
                                                                </div>
                                                                <div class="product-actions d-flex align-items-center mb-5">

                                                                     <!-- Botão para Cadastrar Adicionais -->
                                                                   

                                                                    <!-- Botão de Editar Produto-->
                                                                    <a href="./editarProduto.php?id=<?php echo $produto['id_produto']; ?>">
                                                                        <i class="tf-icons bx bx-pencil" data-tooltip="Editar"></i>
                                                                    </a>

                                                                    <!-- Botão para Copiar Produto -->
                                                                    <a href="#" data-bs-toggle="modal" data-bs-target="#copyProductModal_<?php echo $produto['id_produto']; ?>" class="copy-product" data-id="<?php echo $produto['id_produto']; ?>">
                                                                        <i class="tf-icons bx bx-clipboard" data-tooltip="Copiar"></i>
                                                                    </a>

                                                                    <!-- Botão para Excluir Produto -->
                                                                    <a href="#" data-bs-toggle="modal" data-bs-target="#deleteProductModal_<?php echo $produto['id_produto']; ?>" class="delete-product" data-id="<?php echo $produto['id_produto']; ?>">
                                                                        <i class="tf-icons bx bx-trash deleteIconProduto" data-tooltip="Excluir"></i>
                                                                    </a>

                                                                </div>
                    
                                                                <!-- Exibe a quantidade no canto inferior direito -->
                                                                <div class="product-quantity" style="position: absolute; bottom: 10px; right: 10px;">
                                                                    <span class="badge bg-primary"><?php echo $produto['quantidade_produto']; ?> unidades</span>
                                                                </div>
                                                            </div>

                                                            <!-- Modal de Cadastrar Adicionais -->
                                                         
                                                            <!-- /Modal de Cadastrar Adicionais -->

                                                            <!-- Modal de Copiar Produto -->
                                                            <div class="modal fade" id="copyProductModal_<?php echo $produto['id_produto']; ?>" tabindex="-1" aria-labelledby="copyProductModalLabel" aria-hidden="true">
                                                                <div class="modal-dialog">
                                                                    <div class="modal-content">
                                                                        <div class="modal-header">
                                                                            <h5 class="modal-title" id="copyProductModalLabel">Copiar Produto</h5>
                                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <p>Tem certeza de que deseja copiar o produto "<?php echo htmlspecialchars($produto['nome_produto']); ?>"?</p>
                                                                            <a href="./assets/php/delivery/copiarProduto.php?id=<?php echo $produto['id_produto']; ?>" class="btn btn-primary">Sim, copiar</a>
                                                                            <button type="button" class="btn btn-secondary mx-2" data-bs-dismiss="modal">Cancelar</button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <!-- /Modal de Copiar Produto -->

                                                            <!-- Modal de Excluir Produto -->
                                                            <div class="modal fade" id="deleteProductModal_<?php echo $produto['id_produto']; ?>" tabindex="-1" aria-labelledby="deleteProductModalLabel" aria-hidden="true">
                                                                <div class="modal-dialog">
                                                                    <div class="modal-content">
                                                                        <div class="modal-header">
                                                                            <h5 class="modal-title" id="deleteProductModalLabel">Excluir Produto</h5>
                                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <p>Tem certeza de que deseja excluir o produto "<?php echo htmlspecialchars($produto['nome_produto']); ?>"?</p>
                                                                            <a href="./assets/php/delivery/excluirProduto.php?id=<?php echo $produto['id_produto']; ?>" class="btn btn-danger">Sim, excluir</a>
                                                                            <button type="button" class="btn btn-secondary mx-2" data-bs-dismiss="modal">Cancelar</button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <!-- /Modal de Excluir Produto -->

                                                        <?php endforeach; ?>
                                                        <!-- /Produtos -->

                                                        
                    
                                                        <!-- Botão Adicionar Novo Produto -->
                                                        <a href="./adicionarProduto.php?id_categoria=<?php echo $categoria['id_categoria']; ?>" class="add-item-container">
                                                            <div class="add-item mb-2 mt-4">
                                                                <i class="tf-icons bx bx-plus me-2"></i>Adicionar novo produto
                                                            </div>
                                                        </a>
                    
                                                    </div>
                                                    
                                                </div>
                                                <!-- /Listagem de Produtos -->

                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Adicionar nova categoria -->
                                    <div id="addCategoryLink" class="mt-3 add-category justify-content-center d-flex text-center align-items-center" data-bs-toggle="modal" data-bs-target="#categoryModal">
                                        <i class="tf-icons bx bx-plus me-2"></i>
                                        <span>Adicionar nova categoria</span>
                                    </div>

                                    <!-- Modal de Adicionar Categoria -->
                                    <div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
                                        <!-- modal-dialog -->
                                        <div class="modal-dialog">

                                            <!-- modal-content -->
                                            <div class="modal-content">

                                                <!-- modal-header -->
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="categoryModalLabel">Nova Categoria</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <!-- end modal-header -->

                                                <!-- modal-body -->
                                                <div class="modal-body">

                                                    <!-- form -->
                                                    <form action="./assets/php/delivery/adicionarCategoria.php" method="POST">

                                                        <div class="mb-3">
                                                            <label for="newCategory" class="form-label">Nome da Categoria</label>
                                                            <input type="text" id="newCategory" name="nomeCategoria" class="form-control" placeholder="Digite o nome da categoria" required>
                                                        </div>

                                                        <!-- Campo para o ícone com select -->
                                                        <div class="mb-3">
                                                            <label for="categoryIcon" class="form-label">Ícone da Categoria</label>
                                                            <select id="categoryIcon" name="iconeCategoria" class="form-select" required>
                                                                <option value="fas fa-bolt">⚡ Cabos Elétricos</option>
                                                                <option value="fas fa-plug">🔌 Tomadas</option>
                                                                <option value="fas fa-screwdriver">🪛 Material Eletrico</option>
                                                            </select>
                                                            <small class="form-text text-muted">Escolha o ícone para a categoria.</small>
                                                        </div>

                                                        <button type="submit" class="btn btn-primary">Adicionar Categoria</button>

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
    function filterProducts() {
        // Obter o valor do input de pesquisa e converter para minúsculas
        var searchTerm = document.getElementById('searchInput').value.toLowerCase();

        // Obter todos os elementos com a classe 'product-card'
        var products = document.querySelectorAll('.product-card');

        // Iterar sobre os produtos e verificar se o nome ou descrição do produto contém o termo de pesquisa
        products.forEach(function(product) {
            var productName = product.querySelector('.product-info h6').textContent.toLowerCase();
            var productDescription = product.querySelector('.product-info p.text-muted').textContent.toLowerCase();

            // Verificar se o nome ou a descrição do produto contém o termo de pesquisa
            if (productName.includes(searchTerm) || productDescription.includes(searchTerm)) {
                product.style.display = ''; // Exibir o produto
            } else {
                product.style.display = 'none'; // Ocultar o produto
            }
        });
    }
</script>

    
</body> 
</html>
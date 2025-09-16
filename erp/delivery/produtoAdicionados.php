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

// ✅ Compatibilidade: str_starts_with (PHP < 8)
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        return $needle !== '' && strpos((string)$haystack, (string)$needle) === 0;
    }
}

// ✅ Helper
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// =====================
//   CONTEXTO DE USUÁRIO
// =====================
$nomeUsuario  = 'Usuário';
$nivelUsuario = 'Comum';
$usuario_id   = (int)$_SESSION['usuario_id'];

try {
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $nomeUsuario  = $usuario['usuario'] ?? 'Usuário';
        $nivelUsuario = $usuario['nivel']   ?? 'Comum';
    } else {
        echo "<script>alert('Usuário não encontrado.'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar usuário: " . h($e->getMessage()) . "'); history.back();</script>";
    exit;
}

// ================================
//   VALIDAÇÃO DO ACESSO DA EMPRESA
// ================================
$acessoPermitido  = false;
$idEmpresaSession = $_SESSION['empresa_id'] ?? '';
$tipoSession      = $_SESSION['tipo_empresa'] ?? '';

if ($idEmpresaSession === $idSelecionado) {
    // mesma empresa
    $acessoPermitido = true;
} elseif (str_starts_with($idSelecionado, 'principal_')) {
    // acesso à matriz
    $acessoPermitido = ($tipoSession === 'principal' && $idEmpresaSession === 'principal_1');
}

if (!$acessoPermitido) {
    echo "<script>
          alert('Acesso negado!');
          window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';
        </script>";
    exit;
}

// =====================
//   LOGO DA EMPRESA
// =====================
try {
    $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1");
    $stmt->bindValue(':id', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);
    $logoEmpresa = (!empty($empresaSobre['imagem']))
        ? "../../assets/img/empresa/" . $empresaSobre['imagem']
        : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png";
}

// ==============================================
//   BUSCA CATEGORIAS (por empresa_id = idSelecionado)
// ==============================================
$empresa_id = $idSelecionado;

try {
    $sql = "SELECT id_categoria, nome_categoria, data_cadastro
            FROM adicionarCategoria
           WHERE empresa_id = :empresa_id
        ORDER BY nome_categoria ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':empresa_id', $empresa_id, PDO::PARAM_STR);
    $stmt->execute();
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    echo "Erro ao buscar categorias: " . h($e->getMessage());
    exit;
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
    <link rel="icon" type="image/x-icon" href="<?= h($logoEmpresa) ?>" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />

    <!-- Icons -->
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <!-- Core CSS -->
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />

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

    <!-- Config -->
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

                    <!-- DELIVERY -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Delivery</span></li>

                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-food-menu"></i>
                            <div data-i18n="Authentications">Cardápio</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item active">
                                <a href="./produtoAdicionados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Produtos Adicionados</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-cog"></i>
                            <div data-i18n="Authentications">Configuração</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./deliveryRetirada.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Delivery e Retirada</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-trending-up"></i>
                            <div data-i18n="Authentications">Relatórios</div>
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
                                <a href="./relatorioVendas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Vendas</div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <!-- END DELIVERY -->

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
                        <a href="../empresa/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div data-i18n="Authentications">Empresa</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-box"></i>
                            <div data-i18n="Authentications">Estoque</div>
                        </a>
                    </li>

                    <?php
                    $tipoLogado = $_SESSION['tipo_empresa'] ?? '';
                    $idLogado   = $_SESSION['empresa_id'] ?? '';

                    if ($tipoLogado === 'principal') { ?>
                        <li class="menu-item">
                            <a href="../filial/index.php?id=principal_1" class="menu-link">
                                <i class="menu-icon tf-icons bx bx-building"></i>
                                <div data-i18n="Authentications">Filial</div>
                            </a>
                        </li>
                        <li class="menu-item">
                            <a href="../franquia/index.php?id=principal_1" class="menu-link">
                                <i class="menu-icon tf-icons bx bx-store"></i>
                                <div data-i18n="Authentications">Franquias</div>
                            </a>
                        </li>
                    <?php } elseif (in_array($tipoLogado, ['filial', 'franquia', 'unidade'])) { ?>
                        <li class="menu-item">
                            <a href="../matriz/index.php?id=<?= urlencode($idLogado) ?>" class="menu-link">
                                <i class="menu-icon tf-icons bx bx-cog"></i>
                                <div data-i18n="Authentications">Matriz</div>
                            </a>
                        </li>
                    <?php } ?>

                    <li class="menu-item">
                        <a href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-group"></i>
                            <div data-i18n="Authentications">Usuários</div>
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
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
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
                                <input type="text" id="searchProdutos" class="form-control border-0 shadow-none"
                                    placeholder="Pesquisar produtos..." aria-label="Search..." />
                            </div>
                        </div>
                        <!-- /Search -->

                        <!-- User -->
                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <img src="<?= h($logoEmpresa) ?>" alt class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="<?= h($logoEmpresa) ?>" alt class="w-px-40 h-auto rounded-circle" />
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <span class="fw-semibold d-block"><?= h($nomeUsuario); ?></span>
                                                    <small class="text-muted"><?= h($nivelUsuario); ?></small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                    <li>
                                        <div class="dropdown-divider"></div>
                                    </li>
                                    <li><a class="dropdown-item" href="#"><i class="bx bx-user me-2"></i><span class="align-middle">Minha Conta</span></a></li>
                                    <li><a class="dropdown-item" href="#"><i class="bx bx-cog me-2"></i><span class="align-middle">Configurações</span></a></li>
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
                                        <a class="dropdown-item" href="../logout.php?id=<?= urlencode($idSelecionado); ?>">
                                            <i class="bx bx-power-off me-2"></i><span class="align-middle">Sair</span>
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                        <!--/ User -->
                    </div>
                </nav>
                <!-- / Navbar -->

                <!-- PRODUTOS CADASTRADOS -->
                <div class="conteudo-inner">
                    <div class="container">
                        <div class="row">
                            <div class="col-12" id="categorias">

                                <div class="container-group mb-5">
                                    <h5 class="title-categoria mb-0 mt-5">
                                        <b><i class="fas fa-book-open"></i>&nbsp;Categorias do Cardápio</b>
                                    </h5>

                                    <div class="accordion" id="categoriasMenu">

                                        <?php
                                        // Loop de categorias
                                        foreach ($categorias as $categoria):

                                            // ================================================
                                            //   BUSCA PRODUTOS DA CATEGORIA **E** DA MESMA EMPRESA
                                            //   (evita aparecer produto de outra empresa)
                                            // ================================================
                                            $sqlProdutos = "
                                                            SELECT id_produto, nome_produto, quantidade_produto, preco_produto, imagem_produto,
                                                                COALESCE(descricao_produto, '') AS descricao_produto, data_cadastro, id_categoria, id_empresa
                                                            FROM adicionarProdutos
                                                            WHERE id_categoria = :id_categoria
                                                            AND id_empresa   = :id_empresa
                                                            ORDER BY nome_produto ASC
                                                        ";
                                            $stmtProdutos = $pdo->prepare($sqlProdutos);
                                            $stmtProdutos->bindValue(':id_categoria', $categoria['id_categoria'], PDO::PARAM_INT);
                                            $stmtProdutos->bindValue(':id_empresa',   $empresa_id,              PDO::PARAM_STR);
                                            $stmtProdutos->execute();
                                            $produtos = $stmtProdutos->fetchAll(PDO::FETCH_ASSOC);
                                        ?>

                                            <div class="card mt-3">
                                                <div class="card-drag" id="heading<?= (int)$categoria['id_categoria']; ?>">
                                                    <div class="infos d-flex align-items-center justify-content-between">
                                                        <a href="#"
                                                            class="name mb-0"
                                                            data-bs-toggle="collapse"
                                                            data-bs-target="#collapse<?= (int)$categoria['id_categoria']; ?>"
                                                            aria-expanded="false"
                                                            aria-controls="collapse<?= (int)$categoria['id_categoria']; ?>">
                                                            <span class="me-2"><i class="fa-solid fa-bowl-food"></i></span>
                                                            <b><?= h($categoria['nome_categoria']); ?></b>
                                                        </a>

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
                                                </div>

                                                <!-- Modal: Editar Categoria -->
                                                <div class="modal fade" id="editCategoryModal_<?= (int)$categoria['id_categoria']; ?>" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="editCategoryModalLabel">Editar Categoria</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <form action="../../assets/php/delivery/editarCategoria.php" method="POST">
                                                                    <input type="hidden" name="id_categoria" value="<?= (int)$categoria['id_categoria']; ?>">
                                                                    <input type="hidden" name="idSelecionado" value="<?= h($idSelecionado); ?>">

                                                                    <div class="mb-3">
                                                                        <label for="categoriaNome_<?= (int)$categoria['id_categoria']; ?>" class="form-label">Nome da Categoria</label>
                                                                        <input type="text" class="form-control"
                                                                            id="categoriaNome_<?= (int)$categoria['id_categoria']; ?>"
                                                                            name="nome_categoria"
                                                                            value="<?= h($categoria['nome_categoria']); ?>" required>
                                                                    </div>

                                                                    <button type="submit" class="btn btn-primary">Salvar</button>
                                                                    <button type="button" class="btn btn-secondary mx-2" data-bs-dismiss="modal">Cancelar</button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- /Modal: Editar Categoria -->

                                                <!-- Modal: Copiar Categoria -->
                                                <div class="modal fade" id="copyCategoryModal_<?= (int)$categoria['id_categoria']; ?>" tabindex="-1" aria-labelledby="copyCategoryModalLabel" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="copyCategoryModalLabel">Copiar Categoria</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Tem certeza de que deseja copiar a categoria "<b><?= h($categoria['nome_categoria']); ?></b>" e seus produtos?</p>
                                                                <a href="../../assets/php/delivery/copiarCategoria.php?id=<?= (int)$categoria['id_categoria']; ?>&idSelecionado=<?= urlencode($idSelecionado); ?>" class="btn btn-primary">
                                                                    Sim, copiar
                                                                </a>
                                                                <button type="button" class="btn btn-secondary mx-2" data-bs-dismiss="modal">Cancelar</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- /Modal: Copiar Categoria -->

                                                <!-- Modal: Excluir Categoria -->
                                                <div class="modal fade" id="categoryModal_<?= (int)$categoria['id_categoria']; ?>" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="categoryModalLabel">Excluir Categoria</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Tem certeza de que deseja excluir a categoria "<b><?= h($categoria['nome_categoria']); ?></b>"?</p>
                                                                <a href="../../assets/php/delivery/excluirCategoria.php?id=<?= (int)$categoria['id_categoria']; ?>&idSelecionado=<?= urlencode($idSelecionado); ?>" class="btn btn-danger">
                                                                    Sim, excluir
                                                                </a>
                                                                <button type="button" class="btn btn-secondary mx-2" data-bs-dismiss="modal">Cancelar</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- /Modal: Excluir Categoria -->

                                                <!-- Listagem de Produtos -->
                                                <div id="collapse<?= (int)$categoria['id_categoria']; ?>" class="collapse"
                                                    data-bs-parent="#categoriasMenu">
                                                    <div class="card-body">

                                                        <?php foreach ($produtos as $produto): ?>
                                                            <?php
                                                            // Fallback da imagem
                                                            $img = trim((string)$produto['imagem_produto']);
                                                            $imgPath = (!empty($img)) ? "../../assets/img/uploads/" . $img : "../../assets/img/favicon/logo.png";

                                                            // ============================
                                                            //   CONTAGEM DE OPCIONAIS (ID SELECIONADO)
                                                            // ============================
                                                            $sqlTotalOpcionais = "
                                                                                    SELECT
                                                                                        COALESCE((
                                                                                        SELECT COUNT(*) FROM opcionais
                                                                                        WHERE id_produto = :id_produto
                                                                                            AND id_selecionado = :id_sel
                                                                                        ), 0) +
                                                                                        COALESCE((
                                                                                        SELECT COUNT(*) FROM opcionais_opcoes
                                                                                        WHERE id_selecao IN (
                                                                                            SELECT id FROM opcionais_selecoes
                                                                                            WHERE id_produto = :id_produto
                                                                                                AND id_selecionado = :id_sel
                                                                                        )
                                                                                            AND id_selecionado = :id_sel
                                                                                        ), 0) AS total_opcionais
                                                                                    ";
                                                            $stmtTotal = $pdo->prepare($sqlTotalOpcionais);
                                                            $stmtTotal->bindValue(':id_produto', $produto['id_produto'], PDO::PARAM_INT);
                                                            $stmtTotal->bindValue(':id_sel', $idSelecionado, PDO::PARAM_STR);
                                                            $stmtTotal->execute();
                                                            $totalRow = $stmtTotal->fetch(PDO::FETCH_ASSOC);
                                                            $total_opcionais = (int)($totalRow['total_opcionais'] ?? 0);
                                                            ?>

                                                            <div class="product-card mb-2 p-2 d-flex align-items-center position-relative">
                                                                <div class="pe-2"><i class="bi bi-grip-vertical"></i></div>

                                                                <img src="<?= h($imgPath); ?>" class="product-img mb-2" alt="Imagem do produto">

                                                                <div class="product-info">
                                                                    <h6 class="fw-bold mb-1"><?= h($produto['nome_produto']); ?></h6>
                                                                    <?php if (!empty($produto['descricao_produto'])): ?>
                                                                        <p class="text-muted mb-1"><?= h($produto['descricao_produto']); ?></p>
                                                                    <?php endif; ?>
                                                                    <p class="price mb-1">R$ <?= number_format((float)$produto['preco_produto'], 2, ',', '.'); ?></p>
                                                                </div>

                                                                <div class="product-actions d-flex align-items-center mb-5">
                                                                    <!-- Cadastrar/Ver Adicionais -->
                                                                    <a href="#"
                                                                        class="icon-action cadastrar-adicionais position-relative"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#addAdicionaisModal_<?= (int)$produto['id_produto']; ?>"
                                                                        title="Opcionais">
                                                                        <?php if ($total_opcionais > 0): ?>
                                                                            <span class="badge-adicionais"><?= $total_opcionais; ?></span>
                                                                        <?php endif; ?>
                                                                        <i class="fas fa-layer-group"></i>
                                                                    </a>

                                                                    <!-- Editar Produto -->
                                                                    <a href="./editarProduto.php?id=<?= (int)$produto['id_produto']; ?>&idSelecionado=<?= urlencode($idSelecionado); ?>" title="Editar">
                                                                        <i class="tf-icons bx bx-pencil"></i>
                                                                    </a>

                                                                    <!-- Copiar Produto -->
                                                                    <a href="#" data-bs-toggle="modal"
                                                                        data-bs-target="#copyProductModal_<?= (int)$produto['id_produto']; ?>"
                                                                        title="Copiar">
                                                                        <i class="tf-icons bx bx-clipboard"></i>
                                                                    </a>

                                                                    <!-- Excluir Produto -->
                                                                    <a href="#" data-bs-toggle="modal"
                                                                        data-bs-target="#deleteProductModal_<?= (int)$produto['id_produto']; ?>"
                                                                        title="Excluir">
                                                                        <i class="tf-icons bx bx-trash deleteIconProduto"></i>
                                                                    </a>
                                                                </div>

                                                                <!-- Quantidade -->
                                                                <div class="product-quantity" style="position: absolute; bottom: 10px; right: 10px;">
                                                                    <span class="badge bg-primary"><?= (int)$produto['quantidade_produto']; ?> unidades</span>
                                                                </div>
                                                            </div>

                                                            <!-- MODAIS DE PRODUTOS -->

                                                            <!-- Modal: Adicionais -->
                                                            <div class="modal fade" id="addAdicionaisModal_<?= (int)$produto['id_produto']; ?>" tabindex="-1" aria-labelledby="addAdicionaisModalLabel" aria-hidden="true">
                                                                <div class="modal-dialog modal-lg">
                                                                    <div class="modal-content">
                                                                        <div class="modal-header text-white">
                                                                            <h5 class="modal-title" id="addAdicionaisModalLabel">Cadastrar Adicionais</h5>
                                                                            <button type="button" class="btn-close text-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                        </div>
                                                                        <div class="modal-body">

                                                                            <!-- Botão Adicionar Novo Opcional -->
                                                                            <a href="./adicionarOpcional.php?id=<?= (int)$produto['id_produto']; ?>&idSelecionado=<?= urlencode($idSelecionado); ?>"
                                                                                class="add-item-container">
                                                                                <div class="add-item mb-4 mt-0 d-flex align-items-center justify-content-center bg-light p-3 rounded">
                                                                                    <i class="tf-icons bx bx-plus me-2"></i>
                                                                                    <b>Cadastrar Opcional</b>
                                                                                </div>
                                                                            </a>

                                                                            <?php
                                                                            // Opcionais simples por produto e empresa
                                                                            $sql_opcionais = "SELECT id, nome, preco FROM opcionais WHERE id_produto = ? AND id_selecionado = ?";
                                                                            $stmt_opcionais = $pdo->prepare($sql_opcionais);
                                                                            $stmt_opcionais->execute([$produto['id_produto'], $idSelecionado]);
                                                                            $opcionais = $stmt_opcionais->fetchAll(PDO::FETCH_ASSOC);
                                                                            ?>

                                                                            <?php if (count($opcionais) > 0): ?>
                                                                                <h6 class="text-center text-primary mt-3"><b>Opcionais Simples</b></h6>
                                                                                <?php foreach ($opcionais as $opc): ?>
                                                                                    <div class="d-flex justify-content-between align-items-center border p-3 mb-2 rounded">
                                                                                        <div>
                                                                                            <b class="text-dark"><?= h($opc['nome']); ?></b>
                                                                                            <div class="mt-1">
                                                                                                <span class="badge bg-success text-white">+ R$ <?= number_format((float)$opc['preco'], 2, ',', '.'); ?></span>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="d-flex">
                                                                                            <a href="./editarOpcionalSimples.php?id=<?= (int)$opc['id']; ?>&idSelecionado=<?= urlencode($idSelecionado); ?>" class="btn btn-sm btn-outline-primary me-2">
                                                                                                <i class="bx bx-edit"></i>
                                                                                            </a>
                                                                                            <a href="../../assets/php/delivery/excluirOpcionalSimples.php?id=<?= (int)$opc['id']; ?>&idSelecionado=<?= urlencode($idSelecionado); ?>" class="btn btn-sm btn-outline-danger"
                                                                                                onclick="return confirm('Tem certeza que deseja excluir este opcional?');">
                                                                                                <i class="bx bx-trash"></i>
                                                                                            </a>
                                                                                        </div>
                                                                                    </div>
                                                                                <?php endforeach; ?>
                                                                            <?php endif; ?>

                                                                            <?php
                                                                            // Seleções de opcionais por produto e empresa
                                                                            $sql_selecoes = "SELECT id, titulo, minimo, maximo FROM opcionais_selecoes WHERE id_produto = ? AND id_selecionado = ?";
                                                                            $stmt_selecoes = $pdo->prepare($sql_selecoes);
                                                                            $stmt_selecoes->execute([$produto['id_produto'], $idSelecionado]);
                                                                            $selecoes = $stmt_selecoes->fetchAll(PDO::FETCH_ASSOC);
                                                                            ?>

                                                                            <?php if (count($selecoes) > 0): ?>
                                                                                <h6 class="text-center text-primary mt-4"><b>Seleções de Opcionais</b></h6>
                                                                                <?php foreach ($selecoes as $sel): ?>
                                                                                    <div class="container-group border p-3 rounded mb-3">
                                                                                        <div class="d-flex flex-wrap justify-content-between align-items-center">
                                                                                            <div>
                                                                                                <p class="title-categoria text-secondary mb-1"><b><?= h($sel['titulo']); ?></b></p>
                                                                                                <span class="sub-title-categoria text-muted">Mínimo: <?= (int)$sel['minimo']; ?> | Máximo: <?= (int)$sel['maximo']; ?></span>
                                                                                            </div>
                                                                                            <div class="d-flex mt-2 mb-2">
                                                                                                <a href="./editarSelecao.php?id=<?= (int)$sel['id']; ?>&idSelecionado=<?= urlencode($idSelecionado); ?>" class="btn btn-sm btn-outline-primary me-2">
                                                                                                    <i class="bx bx-edit"></i> Editar
                                                                                                </a>
                                                                                                <a href="../../assets/php/delivery/excluirSelecao.php?id=<?= (int)$sel['id']; ?>&idSelecionado=<?= urlencode($idSelecionado); ?>" class="btn btn-sm btn-outline-danger"
                                                                                                    onclick="return confirm('Tem certeza que deseja excluir esta seleção?');">
                                                                                                    <i class="bx bx-trash"></i> Excluir
                                                                                                </a>
                                                                                            </div>
                                                                                        </div>

                                                                                        <?php
                                                                                        $sql_opcoes = "SELECT id, nome, preco FROM opcionais_opcoes WHERE id_selecao = ? AND id_selecionado = ?";
                                                                                        $stmt_opcoes = $pdo->prepare($sql_opcoes);
                                                                                        $stmt_opcoes->execute([$sel['id'], $idSelecionado]);
                                                                                        $opcoes = $stmt_opcoes->fetchAll(PDO::FETCH_ASSOC);
                                                                                        ?>

                                                                                        <?php if (count($opcoes) > 0): ?>
                                                                                            <div class="row mt-2">
                                                                                                <?php foreach ($opcoes as $op): ?>
                                                                                                    <div class="col-12 col-md-6">
                                                                                                        <div class="list-group-item d-flex flex-wrap justify-content-between align-items-center border rounded p-2 mb-2">
                                                                                                            <div>
                                                                                                                <b class="text-dark"><?= h($op['nome']); ?></b>
                                                                                                                <div class="mt-1">
                                                                                                                    <span class="badge bg-success text-white">+ R$ <?= number_format((float)$op['preco'], 2, ',', '.'); ?></span>
                                                                                                                </div>
                                                                                                            </div>
                                                                                                            <div class="d-flex align-items-center">
                                                                                                                <a href="./editarOpcionalSelecao.php?id=<?= (int)$op['id']; ?>&idSelecionado=<?= urlencode($idSelecionado); ?>" class="btn btn-sm btn-outline-primary me-2">
                                                                                                                    <i class="bx bx-edit"></i>
                                                                                                                </a>
                                                                                                                <a href="../../assets/php/delivery/excluirOpcionalSelecao.php?id=<?= (int)$op['id']; ?>&idSelecionado=<?= urlencode($idSelecionado); ?>" class="btn btn-sm btn-outline-danger"
                                                                                                                    onclick="return confirm('Tem certeza que deseja excluir este opcional?');">
                                                                                                                    <i class="bx bx-trash"></i>
                                                                                                                </a>
                                                                                                            </div>
                                                                                                        </div>
                                                                                                    </div>
                                                                                                <?php endforeach; ?>
                                                                                            </div>
                                                                                        <?php endif; ?>
                                                                                    </div>
                                                                                <?php endforeach; ?>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <!-- /Modal: Adicionais -->

                                                            <!-- Modal: Copiar Produto -->
                                                            <div class="modal fade" id="copyProductModal_<?= (int)$produto['id_produto']; ?>" tabindex="-1" aria-labelledby="copyProductModalLabel" aria-hidden="true">
                                                                <div class="modal-dialog">
                                                                    <div class="modal-content">
                                                                        <div class="modal-header">
                                                                            <h5 class="modal-title" id="copyProductModalLabel">Copiar Produto</h5>
                                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <p>Tem certeza de que deseja copiar o produto "<b><?= h($produto['nome_produto']); ?></b>"?</p>
                                                                            <a href="../../assets/php/delivery/copiarProduto.php?id=<?= (int)$produto['id_produto']; ?>&empresa_id=<?= urlencode($idSelecionado); ?>" class="btn btn-primary">Sim, copiar</a>
                                                                            <button type="button" class="btn btn-secondary mx-2" data-bs-dismiss="modal">Cancelar</button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <!-- /Modal: Copiar Produto -->

                                                            <!-- Modal: Excluir Produto -->
                                                            <div class="modal fade" id="deleteProductModal_<?= (int)$produto['id_produto']; ?>" tabindex="-1" aria-labelledby="deleteProductModalLabel" aria-hidden="true">
                                                                <div class="modal-dialog">
                                                                    <div class="modal-content">
                                                                        <div class="modal-header">
                                                                            <h5 class="modal-title" id="deleteProductModalLabel">Excluir Produto</h5>
                                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <p>Tem certeza de que deseja excluir o produto "<b><?= h($produto['nome_produto']); ?></b>"?</p>
                                                                            <a href="../../assets/php/delivery/excluirProduto.php?id=<?= (int)$produto['id_produto']; ?>&empresa_id=<?= urlencode($idSelecionado); ?>" class="btn btn-danger">Sim, excluir</a>
                                                                            <button type="button" class="btn btn-secondary mx-2" data-bs-dismiss="modal">Cancelar</button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <!-- /Modal: Excluir Produto -->

                                                        <?php endforeach; ?>

                                                        <!-- Botão: Adicionar novo produto -->
                                                        <a href="./adicionarProduto.php?id_categoria=<?= (int)$categoria['id_categoria']; ?>&id=<?= urlencode($idSelecionado); ?>" class="add-item-container">
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
                                    <div id="addCategoryLink"
                                        class="mt-3 add-category justify-content-center d-flex text-center align-items-center"
                                        data-bs-toggle="modal" data-bs-target="#categoryModal">
                                        <i class="tf-icons bx bx-plus me-2"></i>
                                        <span>Adicionar nova categoria</span>
                                    </div>

                                    <!-- Modal: Nova Categoria -->
                                    <div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="categoryModalLabel">Nova Categoria</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <form action="../../assets/php/delivery/adicionarCategoria.php" method="POST">
                                                        <div class="mb-3">
                                                            <label for="newCategory" class="form-label">Nome da Categoria</label>
                                                            <input type="text" id="newCategory" name="nomeCategoria" class="form-control" placeholder="Digite o nome da categoria" required>
                                                        </div>
                                                        <input type="hidden" name="id" value="<?= h($idSelecionado) ?>">
                                                        <button type="submit" class="btn btn-primary">Adicionar Categoria</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- /Modal: Nova Categoria -->

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
        // Pesquisa instantânea
        document.getElementById("searchProdutos").addEventListener("input", function() {
            const termo = this.value.toLowerCase();
            const produtos = document.querySelectorAll(".product-card");
            const collapses = document.querySelectorAll('[id^="collapse"]');

            produtos.forEach((produto) => {
                const textoProduto = produto.textContent.toLowerCase();
                const corresponde = textoProduto.includes(termo);
                produto.style.display = corresponde ? "" : "none";
            });

            // Abre o acordeão da categoria se existir produto visível nela
            collapses.forEach((collapse) => {
                const produtosVisiveis = collapse.querySelectorAll(".product-card:not([style*='display: none'])");
                if (termo && produtosVisiveis.length > 0) {
                    collapse.classList.add("show");
                } else if (!termo) {
                    collapse.classList.remove("show");
                }
            });
        });
    </script>
</body>

</html>
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// ✅ Recupera o identificador vindo da URL OU da sessão
$idSelecionado = $_GET['id'] ?? '';

if (empty($idSelecionado) && isset($_SESSION['empresa_id'])) {
    $idSelecionado = $_SESSION['empresa_id']; // ex: principal_1, unidade_1 etc
}

// ✅ Filtro de categoria (opcional)
$categoriaFiltro = null;
if (isset($_GET['categoria']) && $_GET['categoria'] !== '') {
    $categoriaFiltro = (int) $_GET['categoria'];
}

// ✅ Verifica se a pessoa está logada
if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id']) ||
    !isset($_SESSION['nivel']) // Verifica se o nível está na sessão
) {
    header("Location: ../index.php?id=" . urlencode($idSelecionado));
    exit;
}

// ✅ Conexão com o banco de dados
require '../../assets/php/conexao.php';

$nomeUsuario        = 'Usuário';
$tipoUsuario        = 'Comum';
$usuario_id         = $_SESSION['usuario_id'];
$tipoUsuarioSessao  = $_SESSION['nivel'];        // "Admin" ou "Comum"
$empresaSessao      = $_SESSION['empresa_id'];   // ex: principal_1, unidade_1
$tipoEmpresaSessao  = $_SESSION['tipo_empresa']; // ex: principal, unidade, filial, franquia

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
        echo "<script>alert('Usuário não encontrado.'); window.location.href = './index.php?id=" . addslashes($idSelecionado) . "';</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar nome e tipo do usuário: " . addslashes($e->getMessage()) . "'); history.back();</script>";
    exit;
}

// ✅ Valida o tipo de empresa e o acesso permitido
$acessoPermitido = false;
$id = null;

// Admin da principal_1 pode acessar principal_1 e qualquer unidade_*
$ehAdminPrincipal1 = ($tipoUsuarioSessao === 'Admin' && $empresaSessao === 'principal_1');

if (str_starts_with($idSelecionado, 'principal_')) {
    if ($empresaSessao === $idSelecionado || $ehAdminPrincipal1) {
        $acessoPermitido = true;
        $id = 1;
    }
} elseif (str_starts_with($idSelecionado, 'unidade_')) {
    if ($empresaSessao === $idSelecionado || $ehAdminPrincipal1) {
        $acessoPermitido = true;
        $id = (int)str_replace('unidade_', '', $idSelecionado);
    }
} elseif (str_starts_with($idSelecionado, 'filial_') || str_starts_with($idSelecionado, 'franquia_')) {
    if ($empresaSessao === $idSelecionado) {
        $acessoPermitido = true;
        $id = $idSelecionado;
    }
} else {
    if (!empty($idSelecionado) && $empresaSessao === $idSelecionado) {
        $acessoPermitido = true;
        $id = $idSelecionado;
    }
}

if (!$acessoPermitido) {
    echo "<script>
        alert('Acesso negado!');
        window.location.href = '../index.php?id=" . addslashes($idSelecionado) . "';
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
}

// =====================================================
//  BUSCAR CATEGORIAS E PRODUTOS DO CARDÁPIO
// =====================================================
$categorias = [];
$produtos   = [];

try {
    // TABELA adicionarCategoria:
    // id_categoria, nome_categoria, empresa_id, tipo, data_cadastro
    $stmt = $pdo->prepare("
        SELECT id_categoria, nome_categoria, tipo
        FROM adicionarCategoria
        WHERE empresa_id = :empresa_id
        ORDER BY nome_categoria
    ");
    $stmt->bindParam(':empresa_id', $idSelecionado); // ex: principal_1
    $stmt->execute();
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Se der erro, só não exibe categorias
}

try {
    // Produtos com filtro opcional de categoria
    $sqlProdutos = "
        SELECT 
            p.id_produto,
            p.nome_produto,
            p.quantidade_produto,
            p.preco_produto,
            p.imagem_produto,
            p.descricao_produto,
            p.data_cadastro,
            p.id_categoria,
            p.id_empresa,
            c.nome_categoria,
            c.tipo AS tipo_categoria
        FROM adicionarProdutos p
        LEFT JOIN adicionarCategoria c 
               ON p.id_categoria = c.id_categoria
        WHERE p.id_empresa = :empresa_id
    ";

    if (!is_null($categoriaFiltro) && $categoriaFiltro > 0) {
        $sqlProdutos .= " AND p.id_categoria = :categoria_id ";
    }

    $sqlProdutos .= " ORDER BY p.data_cadastro DESC";

    $stmt = $pdo->prepare($sqlProdutos);
    $stmt->bindParam(':empresa_id', $idSelecionado);

    if (!is_null($categoriaFiltro) && $categoriaFiltro > 0) {
        $stmt->bindParam(':categoria_id', $categoriaFiltro, PDO::PARAM_INT);
    }

    $stmt->execute();
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar produtos: " . addslashes($e->getMessage()) . "');</script>";
}

// =====================================================
//  BUSCAR OPCIONAIS VINCULADOS AOS PRODUTOS
// =====================================================

// opcionais simples (tabela: opcionais)
$opcionaisSimplesPorProduto = [];

// seleções (grupos) por produto (tabela: opcionais_selecoes)
$selecoesPorProduto = [];

// opções por seleção (tabela: opcionais_opcoes)
$opcoesPorSelecao = [];

try {
    // Opcionais simples
    $stmt = $pdo->prepare("
        SELECT id, id_produto, id_selecionado, nome, preco
        FROM opcionais
        WHERE id_selecionado = :id_sel
    ");
    $stmt->bindParam(':id_sel', $idSelecionado);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $pid = (int)$row['id_produto'];
        if (!isset($opcionaisSimplesPorProduto[$pid])) {
            $opcionaisSimplesPorProduto[$pid] = [];
        }
        $opcionaisSimplesPorProduto[$pid][] = $row;
    }
} catch (PDOException $e) {
    // Se der erro, apenas não mostra opcionais simples
}

try {
    // Seleções (grupos de opcionais)
    $stmt = $pdo->prepare("
        SELECT id, id_produto, id_selecionado, titulo, minimo, maximo
        FROM opcionais_selecoes
        WHERE id_selecionado = :id_sel
    ");
    $stmt->bindParam(':id_sel', $idSelecionado);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $pid = (int)$row['id_produto'];
        if (!isset($selecoesPorProduto[$pid])) {
            $selecoesPorProduto[$pid] = [];
        }
        $selecoesPorProduto[$pid][] = $row;
    }
} catch (PDOException $e) {
    // Se der erro, apenas não mostra seleções
}

try {
    // Opções de cada seleção
    $stmt = $pdo->prepare("
        SELECT id, id_selecao, id_selecionado, nome, preco
        FROM opcionais_opcoes
        WHERE id_selecionado = :id_sel
    ");
    $stmt->bindParam(':id_sel', $idSelecionado);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $idSelecao = (int)$row['id_selecao'];
        if (!isset($opcoesPorSelecao[$idSelecao])) {
            $opcoesPorSelecao[$idSelecao] = [];
        }
        $opcoesPorSelecao[$idSelecao][] = $row;
    }
} catch (PDOException $e) {
    // Se der erro, apenas não mostra opções
}

// Função helper pra montar caminho da imagem do produto
function caminhoImagemProduto(?string $arquivo): string
{
    if (!$arquivo) {
        return '';
    }

    return '../../assets/img/uploads/' . $arquivo;
}
?>

<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
    data-assets-path="../../assets/" data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>ERP - Delivery</title>

    <meta name="description" content="" />

    <!-- Favicon da empresa carregado dinamicamente -->
    <link rel="icon" type="image/x-icon"
        href="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500;1,600&display=swap"
        rel="stylesheet" />

    <!-- Icons -->
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />

    <!-- Helpers -->
    <script src="../../assets/vendor/js/helpers.js"></script>
    <script src="../../assets/js/config.js"></script>

    <style>
        .modal-dialog-top {
            margin-top: 20px !important;
        }

        .img-produto-lista {
            width: 55px;
            height: 55px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #ddd;
        }

        .descricao-curta {
            max-width: 260px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .lista-opcionais-titulo {
            font-weight: 600;
            margin-top: 1rem;
            margin-bottom: 0.25rem;
        }

        .lista-opcionais-subtitulo {
            font-size: 0.85rem;
            color: #777;
        }

        .lista-opcionais {
            padding-left: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .lista-opcionais li {
            margin-bottom: 0.15rem;
        }
    </style>
</head>

<body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">

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

                    <!-- DELIVERY -->
                    <li class="menu-header small text-uppercase">
                        <span class="menu-header-text">Delivery</span>
                    </li>

                    <!-- Pedidos -->
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-cart"></i>
                            <div data-i18n="Authentications">Pedidos</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./pedidosDiarios.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Pedidos Diários</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./pedidosAceitos.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Pedidos Aceitos</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./pedidosACaminho.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Pedidos a Caminho</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./pedidosEntregues.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div data-i18n="Basic">Pedidos Entregues</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./pedidosCancelados.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div data-i18n="Basic">Pedidos Cancelados</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- Cardápio -->
                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-food-menu"></i>
                            <div data-i18n="Authentications">Cardápio</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item active">
                                <a href="./produtosAdicionados.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div data-i18n="Basic">Produtos Adicionados</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- Relatórios -->
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-trending-up"></i>
                            <div data-i18n="Authentications">Relatórios</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="#" class="menu-link">
                                    <div data-i18n="Basic">Lista de Pedidos</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="#" class="menu-link">
                                    <div data-i18n="Basic">Mais Vendidos</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="#" class="menu-link">
                                    <div data-i18n="Basic">Clientes</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="#" class="menu-link">
                                    <div data-i18n="Basic">Vendas</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- MISC -->
                    <li class="menu-header small text-uppercase">
                        <span class="menu-header-text">Diversos</span>
                    </li>
                    <li class="menu-item">
                        <a href="../caixa/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-barcode-reader"></i>
                            <div data-i18n="Basic">Caixa</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../sistemadeponto/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-cart"></i>
                            <div data-i18n="Basic">Sistema de Ponto</div>
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

                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
                    id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                            <i class="bx bx-menu bx-sm"></i>
                        </a>
                    </div>

                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <div class="navbar-nav align-items-center">
                            <div class="nav-item d-flex align-items-center">
                            </div>
                        </div>

                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);"
                                    data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <img src="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>"
                                            alt class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>"
                                                            alt class="w-px-40 h-auto rounded-circle" />
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <span
                                                        class="fw-semibold d-block"><?= htmlspecialchars($nomeUsuario); ?></span>
                                                </div>
                                            </div>
                                        </a>
                                    </li>

                                    <li>
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
                        </ul>
                    </div>
                </nav>

                <!-- / Navbar -->

                <!-- =====================================================
                     CONTEÚDO - PRODUTOS ADICIONADOS
                ====================================================== -->
                <div class="container-xxl flex-grow-1 container-p-y">

                    <h4 class="fw-bold mb-4">
                        <span class="text-muted fw-light">
                            <a href="#">Cardápio</a> /
                        </span>
                        Produtos Adicionados
                    </h4>

                    <div class="card">
                        <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                            <h5 class="mb-2 mb-md-0">Lista de Produtos do Cardápio</h5>

                            <div class="d-flex flex-column flex-md-row gap-2">
                                <!-- Filtro por categoria (FUNCIONAL) -->
                                <form method="get" class="d-flex flex-column flex-md-row gap-2">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($idSelecionado); ?>">
                                    <select name="categoria" class="form-select form-select-sm"
                                        onchange="this.form.submit()">
                                        <option value="">Todas as categorias</option>
                                        <?php foreach ($categorias as $cat): ?>
                                            <?php
                                            $idCat = (int)$cat['id_categoria'];
                                            $selected = ($categoriaFiltro === $idCat) ? 'selected' : '';
                                            ?>
                                            <option value="<?= $idCat; ?>" <?= $selected; ?>>
                                                <?= htmlspecialchars($cat['nome_categoria']); ?>
                                                (<?= htmlspecialchars($cat['tipo']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table text-nowrap mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Imagem</th>
                                        <th>Produto</th>
                                        <th>Categoria</th>
                                        <th>Preço</th>
                                        <th>Qtd</th>
                                        <th>Descrição</th>
                                        <th>Cadastrado em</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($produtos)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-4">
                                                Nenhum produto cadastrado para esta empresa.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($produtos as $produto): ?>
                                            <?php
                                            $idProd   = (int)$produto['id_produto'];
                                            $nomeProd = $produto['nome_produto'] ?? '';
                                            $catProd  = $produto['nome_categoria'] ?? 'Sem categoria';
                                            $tipoCat  = $produto['tipo_categoria'] ?? '';
                                            $preco    = number_format((float)$produto['preco_produto'], 2, ',', '.');
                                            $qtd      = (int)$produto['quantidade_produto'];
                                            $desc     = $produto['descricao_produto'] ?? '';
                                            $dataCad  = $produto['data_cadastro']
                                                ? date('d/m/Y H:i', strtotime($produto['data_cadastro']))
                                                : '-';

                                            $imgArquivo = $produto['imagem_produto'] ?? '';
                                            $imgCaminho = caminhoImagemProduto($imgArquivo);

                                            // Opcionais para este produto
                                            $opcionaisSimples = $opcionaisSimplesPorProduto[$idProd] ?? [];
                                            $selecoesProduto  = $selecoesPorProduto[$idProd] ?? [];
                                            ?>
                                            <tr>
                                                <td><?= $idProd; ?></td>
                                                <td>
                                                    <?php if (!empty($imgArquivo)): ?>
                                                        <img src="<?= htmlspecialchars($imgCaminho); ?>"
                                                            alt="<?= htmlspecialchars($nomeProd); ?>"
                                                            class="img-produto-lista">
                                                    <?php else: ?>
                                                        <span class="badge bg-label-secondary">Sem imagem</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($nomeProd); ?></td>
                                                <td>
                                                    <span class="badge bg-label-primary">
                                                        <?= htmlspecialchars($catProd); ?>
                                                    </span>
                                                    <?php if ($tipoCat): ?>
                                                        <small class="text-muted d-block">
                                                            tipo: <?= htmlspecialchars($tipoCat); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><strong>R$ <?= $preco; ?></strong></td>
                                                <td><?= $qtd; ?></td>
                                                <td>
                                                    <span class="descricao-curta" title="<?= htmlspecialchars($desc); ?>">
                                                        <?= htmlspecialchars($desc); ?>
                                                    </span>
                                                </td>
                                                <td><?= $dataCad; ?></td>
                                                <td>
                                                    <div class="d-flex flex-wrap gap-2">
                                                        <!-- Ver Detalhes (modal) -->
                                                        <button class="btn btn-sm btn-outline-secondary"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#detalhesProduto<?= $idProd; ?>">
                                                            Ver
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- MODAL DETALHES PRODUTO -->
                                            <div class="modal fade" id="detalhesProduto<?= $idProd; ?>">
                                                <div class="modal-dialog modal-dialog-scrollable modal-dialog-top">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">
                                                                Detalhes do Produto #<?= $idProd; ?>
                                                            </h5>
                                                            <button class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row g-3">
                                                                <div class="col-12 d-flex align-items-center gap-3 mb-3">
                                                                    <?php if (!empty($imgArquivo)): ?>
                                                                        <img src="<?= htmlspecialchars($imgCaminho); ?>"
                                                                            alt="<?= htmlspecialchars($nomeProd); ?>"
                                                                            class="img-produto-lista">
                                                                    <?php else: ?>
                                                                        <span class="badge bg-label-secondary">
                                                                            Sem imagem
                                                                        </span>
                                                                    <?php endif; ?>
                                                                    <div>
                                                                        <h6 class="mb-0"><?= htmlspecialchars($nomeProd); ?></h6>
                                                                        <small class="text-muted">
                                                                            Código: <?= $idProd; ?>
                                                                        </small>
                                                                    </div>
                                                                </div>

                                                                <div class="col-12">
                                                                    <label class="form-label mb-0">Categoria:</label><br>
                                                                    <span class="badge bg-label-primary">
                                                                        <?= htmlspecialchars($catProd); ?>
                                                                    </span>
                                                                    <?php if ($tipoCat): ?>
                                                                        <small class="text-muted d-block">
                                                                            tipo: <?= htmlspecialchars($tipoCat); ?>
                                                                        </small>
                                                                    <?php endif; ?>
                                                                </div>

                                                                <div class="col-md-6 col-12">
                                                                    <label class="form-label mb-0">Preço:</label><br>
                                                                    <strong>R$ <?= $preco; ?></strong>
                                                                </div>

                                                                <div class="col-md-6 col-12">
                                                                    <label class="form-label mb-0">Quantidade:</label><br>
                                                                    <span><?= $qtd; ?></span>
                                                                </div>

                                                                <div class="col-12">
                                                                    <label class="form-label">Descrição:</label>
                                                                    <p class="mb-0">
                                                                        <?= nl2br(htmlspecialchars($desc)); ?>
                                                                    </p>
                                                                </div>

                                                                <div class="col-12">
                                                                    <label class="form-label mb-0">Data de cadastro:</label><br>
                                                                    <span><?= $dataCad; ?></span>
                                                                </div>

                                                                <?php if (!empty($opcionaisSimples) || !empty($selecoesProduto)): ?>
                                                                    <div class="col-12">
                                                                        <hr>
                                                                        <h6>Opcionais / Adicionais</h6>
                                                                    </div>

                                                                    <?php if (!empty($opcionaisSimples)): ?>
                                                                        <div class="col-12">
                                                                            <div class="lista-opcionais-titulo">
                                                                                Adicionais simples:
                                                                            </div>
                                                                            <ul class="lista-opcionais">
                                                                                <?php foreach ($opcionaisSimples as $opc): ?>
                                                                                    <?php
                                                                                    $nomeOpc  = $opc['nome'] ?? '';
                                                                                    $precoOpc = isset($opc['preco'])
                                                                                        ? number_format((float)$opc['preco'], 2, ',', '.')
                                                                                        : '0,00';
                                                                                    ?>
                                                                                    <li>
                                                                                        <?= htmlspecialchars($nomeOpc); ?>
                                                                                        <span class="text-muted">
                                                                                            (+ R$ <?= $precoOpc; ?>)
                                                                                        </span>
                                                                                    </li>
                                                                                <?php endforeach; ?>
                                                                            </ul>
                                                                        </div>
                                                                    <?php endif; ?>

                                                                    <?php if (!empty($selecoesProduto)): ?>
                                                                        <div class="col-12">
                                                                            <div class="lista-opcionais-titulo">
                                                                                Grupos de seleção:
                                                                            </div>
                                                                            <?php foreach ($selecoesProduto as $sel): ?>
                                                                                <?php
                                                                                $idSelecao  = (int)$sel['id'];
                                                                                $tituloSel  = $sel['titulo'] ?? '';
                                                                                $minSel     = (int)$sel['minimo'];
                                                                                $maxSel     = (int)$sel['maximo'];
                                                                                $opcoesSel  = $opcoesPorSelecao[$idSelecao] ?? [];
                                                                                ?>
                                                                                <div class="mb-2">
                                                                                    <div>
                                                                                        <strong><?= htmlspecialchars($tituloSel); ?></strong><br>
                                                                                        <span class="lista-opcionais-subtitulo">
                                                                                            Mínimo: <?= $minSel; ?> |
                                                                                            Máximo: <?= $maxSel; ?>
                                                                                        </span>
                                                                                    </div>

                                                                                    <?php if (!empty($opcoesSel)): ?>
                                                                                        <ul class="lista-opcionais mt-1">
                                                                                            <?php foreach ($opcoesSel as $opc): ?>
                                                                                                <?php
                                                                                                $nomeOp  = $opc['nome'] ?? '';
                                                                                                $precoOp = isset($opc['preco'])
                                                                                                    ? number_format((float)$opc['preco'], 2, ',', '.')
                                                                                                    : '0,00';
                                                                                                ?>
                                                                                                <li>
                                                                                                    <?= htmlspecialchars($nomeOp); ?>
                                                                                                    <span class="text-muted">
                                                                                                        (+ R$ <?= $precoOp; ?>)
                                                                                                    </span>
                                                                                                </li>
                                                                                            <?php endforeach; ?>
                                                                                        </ul>
                                                                                    <?php else: ?>
                                                                                        <small class="text-muted">
                                                                                            Nenhuma opção cadastrada para este grupo.
                                                                                        </small>
                                                                                    <?php endif; ?>
                                                                                </div>
                                                                            <?php endforeach; ?>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                <?php endif; ?>

                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button class="btn btn-secondary" data-bs-dismiss="modal">
                                                                Fechar
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
                <!-- / Content -->

                <div class="content-backdrop fade"></div>
            </div>
            <!-- Content wrapper -->
        </div>
        <!-- / Layout page -->

    </div>

    <!-- Overlay -->
    <div class="layout-overlay layout-menu-toggle"></div>

    <!-- Core JS -->
    <script src="../../js/saudacao.js"></script>
    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../assets/vendor/js/menu.js"></script>

    <!-- Vendors JS -->
    <script src="../../assets/vendor/libs/apex-charts/apexcharts.js"></script>
    <script src="../../js/graficoDashboard.js"></script>

    <!-- Main JS -->
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/dashboards-analytics.js"></script>

    <script async defer src="https://buttons.github.io/buttons.js"></script>
</body>

</html>
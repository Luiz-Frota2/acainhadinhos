<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// Slug da empresa (ex.: principal_1, unidade_2)
$idSelecionado = $_GET['idSelecionado'] ?? '';
// ID do produto (numérico)
$id_produto = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$idSelecionado || $id_produto <= 0) {
    // parâmetros essenciais ausentes
    header("Location: .././login.php");
    exit;
}

// Conexão
require '../../assets/php/conexao.php';

// Polyfill str_starts_with (PHP < 8)
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        return $needle !== '' && strpos((string)$haystack, (string)$needle) === 0;
    }
}

// Helper
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// =====================
//   CONTEXTO DE USUÁRIO
// =====================
$nomeUsuario  = 'Usuário';
$nivelUsuario = 'Comum';
$usuario_id   = (int)($_SESSION['usuario_id'] ?? 0);

// Checagem de sessão
if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !$usuario_id
) {
    header("Location: .././login.php?id=" . urlencode($idSelecionado));
    exit;
}

// Carrega usuário
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

// Mesma empresa
if ($idEmpresaSession === $idSelecionado) {
    $acessoPermitido = true;
}
// Acesso à matriz
elseif (str_starts_with($idSelecionado, 'principal_')) {
    $acessoPermitido = ($tipoSession === 'principal' && $idEmpresaSession === 'principal_1');
}

if (!$acessoPermitido) {
    echo "<script>alert('Acesso negado!'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
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

// ==============================
//   BUSCAR PRODUTO DA EMPRESA
// ==============================
try {
    $sql = "SELECT id_produto, nome_produto, quantidade_produto, preco_produto, imagem_produto, 
                 COALESCE(descricao_produto,'') AS descricao_produto, id_categoria, id_empresa
            FROM adicionarProdutos
           WHERE id_produto = :id_produto
             AND id_empresa = :empresa
           LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->bindValue(':id_produto', $id_produto, PDO::PARAM_INT);
    $st->bindValue(':empresa',    $idSelecionado, PDO::PARAM_STR);
    $st->execute();
    $produto = $st->fetch(PDO::FETCH_ASSOC);

    if (!$produto) {
        echo "<script>alert('Produto não encontrado para esta empresa.'); window.location.href='./produtoAdicionados.php?id=" . urlencode($idSelecionado) . "';</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar produto: " . h($e->getMessage()) . "'); history.back();</script>";
    exit;
}

// Caminho de imagem (fallback)
$imgNome = trim((string)$produto['imagem_produto']);
$imgSrc  = $imgNome ? "../../assets/img/uploads/" . $imgNome : "../../assets/img/favicon/logo.png";
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/" data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>ERP - Delivery</title>
    <meta name="description" content="" />
    <link rel="icon" type="image/x-icon" href="<?= h($logoEmpresa) ?>" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />
    <script src="../../assets/vendor/js/helpers.js"></script>
    <script src="../../assets/js/config.js"></script>
    <style>
        .img-preview {
            width: 96px;
            height: 96px;
            border-radius: 10px;
            object-fit: cover;
            border: 1px solid #e5e7eb;
            background: #fafafa
        }

        .icon-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: .0;
            transition: .15s
        }

        label[for="inputGroupFile02"]:hover .icon-overlay {
            opacity: .9;
            background: rgba(0, 0, 0, .35);
            color: #fff;
            border-radius: 10px
        }
    </style>
</head>

<body>
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
                    <li class="menu-item">
                        <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Dashboard</div>
                        </a>
                    </li>

                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Delivery</span></li>
                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-food-menu"></i>
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

                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
                    <li class="menu-item"><a href="../rh/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-group"></i>
                            <div data-i18n="Authentications">RH</div>
                        </a></li>
                    <li class="menu-item"><a href="../financas/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-dollar"></i>
                            <div data-i18n="Authentications">Finanças</div>
                        </a></li>
                    <li class="menu-item"><a href="./pdv/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-desktop"></i>
                            <div data-i18n="Authentications">PDV</div>
                        </a></li>
                    <li class="menu-item"><a href="../empresa/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div data-i18n="Authentications">Empresa</div>
                        </a></li>
                    <li class="menu-item"><a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-box"></i>
                            <div data-i18n="Authentications">Estoque</div>
                        </a></li>

                    <?php
                    $tipoLogado = $_SESSION['tipo_empresa'] ?? '';
                    $idLogado   = $_SESSION['empresa_id'] ?? '';
                    if ($tipoLogado === 'principal') { ?>
                        <li class="menu-item"><a href="../filial/index.php?id=principal_1" class="menu-link"><i class="menu-icon tf-icons bx bx-building"></i>
                                <div data-i18n="Authentications">Filial</div>
                            </a></li>
                        <li class="menu-item"><a href="../franquia/index.php?id=principal_1" class="menu-link"><i class="menu-icon tf-icons bx bx-store"></i>
                                <div data-i18n="Authentications">Franquias</div>
                            </a></li>
                    <?php } elseif (in_array($tipoLogado, ['filial', 'franquia', 'unidade'])) { ?>
                        <li class="menu-item"><a href="../matriz/index.php?id=<?= urlencode($idLogado) ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-cog"></i>
                                <div data-i18n="Authentications">Matriz</div>
                            </a></li>
                    <?php } ?>

                    <li class="menu-item"><a href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-group"></i>
                            <div data-i18n="Authentications">Usuários</div>
                        </a></li>
                    <li class="menu-item"><a href="https://wa.me/92991515710" target="_blank" class="menu-link"><i class="menu-icon tf-icons bx bx-support"></i>
                            <div data-i18n="Basic">Suporte</div>
                        </a></li>
                </ul>
            </aside>
            <!-- /Menu -->

            <!-- Layout container -->
            <div class="layout-page">
                <!-- Navbar -->
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)"><i class="bx bx-menu bx-sm"></i></a>
                    </div>
                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online"><img src="<?= h($logoEmpresa) ?>" alt class="w-px-40 h-auto rounded-circle" /></div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online"><img src="<?= h($logoEmpresa) ?>" alt class="w-px-40 h-auto rounded-circle" /></div>
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
                                        <div class="dropdown-divider"></div>
                                    </li>
                                    <li><a class="dropdown-item" href="../logout.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-power-off me-2"></i><span class="align-middle">Sair</span></a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </nav>
                <!-- /Navbar -->

                <!-- Conteúdo -->
                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold mb-0">
                        <span class="text-muted fw-light">
                            <a href="./produtoAdicionados.php?id=<?= urlencode($idSelecionado); ?>">Cardápio</a> /
                        </span>
                        Editar Produto
                    </h4>
                    <h5 class="fw-bold mt-3 mb-3">
                        <span class="text-muted fw-light">Atualize as informações do produto</span>
                    </h5>

                    <div class="row">
                        <div class="col-xl">
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Formulário</h5>
                                </div>
                                <div class="card-body">
                                    <form action="../../assets/php/delivery/editarProduto.php" method="POST" enctype="multipart/form-data">
                                        <!-- IDs ocultos -->
                                        <input type="hidden" name="idSelecionado" value="<?= h($idSelecionado); ?>">
                                        <input type="hidden" name="id_produto" value="<?= (int)$produto['id_produto']; ?>">
                                        <input type="hidden" name="id_categoria" value="<?= (int)$produto['id_categoria']; ?>">
                                        <input type="hidden" name="imagemAtual" value="<?= h($produto['imagem_produto']); ?>">

                                        <!-- Imagem + Nome -->
                                        <div class="d-flex align-items-center mb-4 form-container">
                                            <div class="position-relative">
                                                <label for="inputGroupFile02" class="position-relative">
                                                    <img id="previewImg" class="img-preview" src="<?= h($imgSrc); ?>">
                                                    <input type="file" class="d-none" name="imagemProduto" id="inputGroupFile02" accept="image/*" onchange="previewImage(event)" />
                                                    <div class="icon-overlay"><i class="fas fa-image"></i></div>
                                                </label>
                                            </div>
                                            <div class="ms-3 mt-4 flex-grow-1">
                                                <label class="form-label" for="nomeProduto">Nome do Produto</label>
                                                <input type="text" class="form-control input-custom" id="nomeProduto" name="nomeProduto" value="<?= h($produto['nome_produto']); ?>" required />
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label" for="quantidadeProduto">Quantidade</label>
                                            <input type="text" class="form-control" id="quantidadeProduto" name="quantidadeProduto" value="<?= (int)$produto['quantidade_produto']; ?>" required />
                                        </div>

                                        <label class="form-label" for="precoProduto">Preço</label>
                                        <div class="input-group input-group-merge mb-3">
                                            <span class="input-group-text">R$</span>
                                            <input type="text" id="precoProduto" name="precoProduto" class="form-control" value="<?= number_format((float)$produto['preco_produto'], 2, ',', '.'); ?>" required>
                                            <span class="input-group-text"></span>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label" for="descricaoProduto">Descrição</label>
                                            <textarea id="descricaoProduto" name="descricaoProduto" class="form-control" rows="3"><?= h($produto['descricao_produto']); ?></textarea>
                                        </div>

                                        <div class="d-flex custom-button">
                                            <button type="submit" class="btn btn-primary col-12 w-100 col-md-auto">Salvar Alterações</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- /Conteúdo -->

            </div>
        </div>
    </div>

    <!-- Core JS -->
    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../assets/vendor/js/menu.js"></script>
    <script src="../../assets/vendor/libs/apex-charts/apexcharts.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/dashboards-analytics.js"></script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>

    <script>
        function previewImage(e) {
            const file = e.target.files[0];
            if (!file) return;
            const img = document.getElementById('previewImg');
            img.src = URL.createObjectURL(file);
        }
    </script>
</body>

</html>
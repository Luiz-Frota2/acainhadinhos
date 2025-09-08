<?php
session_start();
require_once '../../assets/php/conexao.php';

// Recupera o identificador vindo da URL
$idSelecionado = $_GET['id'] ?? '';

// Verifica se a pessoa está logada
if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id'])
) {
    header("Location: .././login.php?id=$idSelecionado");
    exit;
}

// Valida o tipo de empresa e o acesso permitido
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

// Buscar imagem da tabela sobre_empresa com base no idSelecionado
try {
    $sql = "SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_selecionado', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);

    $logoEmpresa = !empty($empresaSobre['imagem'])
        ? "../../assets/img/empresa/" . $empresaSobre['imagem']
        : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png";
}

// Buscar nome e nível do usuário logado
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

?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
    data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>ERP - B2B</title>
    <meta name="description" content="" />
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($logoEmpresa) ?>" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />
    <script src="../../assets/vendor/js/helpers.js"></script>
    <script src="../../assets/js/config.js"></script>
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- Menu -->
            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="app-brand-link">
                        <span class="app-brand-text demo menu-text fw-bolder ms-2"
                            style="text-transform: capitalize;">Açaínhadinhos</span>
                    </a>
                    <a href="javascript:void(0);"
                        class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
                        <i class="bx bx-chevron-left bx-sm align-middle"></i>
                    </a>
                </div>
                <div class="menu-inner-shadow"></div>
                <ul class="menu-inner py-1">
                    <!-- Dashboard -->
                    <li class="menu-item ">
                        <a href="index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Dashboard</div>
                        </a>
                    </li>

                    <!-- Administração -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">B2B</span></li>

                    <!---B2B-->
                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div data-i18n="B2B">B2B - Filial</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./produtosSolicitados.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div>Produtos Solicitados</div>
                                </a>
                            </li>
                            <li class="menu-item active">
                                <a href="#" class="menu-link">
                                    <div>Solicitar Produtos</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./produtosEnviados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Produtos Recebidos</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./novaSolicitacao.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Nova Solicitação</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./statusTransferencia.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div>Status da Transf.</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./estoqueFilial.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Estoque da Filial</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./solicitarPagamentoConta.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div>Solicitar Pagamento</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- Relatórios -->
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-bar-chart-alt-2"></i>
                            <div data-i18n="Relatorios">Relatórios</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./MaisVendidos.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="MaisVendidos">Mais Vendidos</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./vendasPeriodo.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Pedidos">Vendas por Período</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./CancelamentosFiliais.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div data-i18n="Cancelamentos">Cancelamentos</div>
                                </a>
                            </li>

                        </ul>
                    </li>

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
                        <a href="../pdv/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-desktop"></i>
                            <div data-i18n="Authentications">PDV</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../delivery/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-cart"></i>
                            <div data-i18n="Authentications">Delivery</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-box"></i>
                            <div data-i18n="Authentications">Estoque</div>
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
                            <div class="nav-item d-flex align-items-center">
                                <i class="bx bx-search fs-4 lh-0"></i>
                                <input type="text" class="form-control border-0 shadow-none" placeholder="Search..."
                                    aria-label="Search..." />
                            </div>
                        </div>
                        <!-- /Search -->
                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <!-- User -->
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);"
                                    data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <img src="<?= htmlspecialchars($logoEmpresa) ?>" alt
                                            class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="<?= htmlspecialchars($logoEmpresa) ?>" alt
                                                            class="w-px-40 h-auto rounded-circle" />
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
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
                                        <a class="dropdown-item" href="#"><i class="bx bx-user me-2"></i><span
                                                class="align-middle">My Profile</span></a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#"><i class="bx bx-cog me-2"></i><span
                                                class="align-middle">Settings</span></a>
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
                                        <a class="dropdown-item" href="index.php"><i
                                                class="bx bx-power-off me-2"></i><span
                                                class="align-middle">Sair</span></a>
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
                        <span class="text-muted fw-light"><a
                                href="./produtosSolicitados.php?id=<?= urlencode($idSelecionado); ?>">B2B</a>/</span>
                        Solicitar Produto
                    </h4>
                    <h5 class="fw-bold mt-3 mb-3 custor-font">
                        <span class="text-muted fw-light">Selecione um produto da Matriz e informe a quantidade
                            desejada</span>
                    </h5>

                    <div class="card">
                        <div class="card-body">

                            <form method="post" action="../../assets/php/matriz/adicionarSolicitacaoProduto.php">

                                <input type="hidden" name="id_selecionado"
                                    value="<?= htmlspecialchars($idSelecionado); ?>">

                                <div class="mb-3">
                                    <label for="empresa_origem" class="form-label">Solicitar da Empresa</label>
                                    <select class="form-select" id="empresa_origem" name="empresa_origem" required>
                                        <option value="">Selecione a empresa</option>
                                        <?php
                                        // Busca a empresa principal (id_selecionado = 'principal_1')
                                        try {
                                            $sqlEmpresa = "SELECT id_selecionado, nome_empresa FROM sobre_empresa WHERE id_selecionado = 'principal_1' LIMIT 1";
                                            $stmtEmpresa = $pdo->prepare($sqlEmpresa);
                                            $stmtEmpresa->execute();
                                            $empresa = $stmtEmpresa->fetch(PDO::FETCH_ASSOC);
                                            if ($empresa) {
                                                $idSelecionadoEmpresa = htmlspecialchars($empresa['id_selecionado']);
                                                $nomeEmpresa = htmlspecialchars($empresa['nome_empresa']);
                                                echo "<option value=\"$idSelecionadoEmpresa\">$nomeEmpresa</option>";
                                            }
                                        } catch (PDOException $e) {
                                            echo '<option disabled>Erro ao carregar empresa</option>';
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="produto" class="form-label">Produto disponível</label>
                                    <select class="form-select" id="produto" name="produto" required>
                                        <option value="">Selecione o produto</option>
                                        <?php
                                            try {
                                                $sql = "SELECT id, nome_produto, quantidade_produto 
                                                        FROM produtos_estoque 
                                                        WHERE empresa_id = :empresa_id";
                                                $empresaPrincipal = 'principal_1';
                                                $stmt = $pdo->prepare($sql);
                                                $stmt->bindParam(':empresa_id', $empresaPrincipal, PDO::PARAM_STR);
                                                $stmt->execute();
                                                $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                                if ($produtos) {
                                                    foreach ($produtos as $produto) {
                                                        $nome = htmlspecialchars($produto['nome_produto']);
                                                        $quantidade = htmlspecialchars($produto['quantidade_produto']);
                                                        $idProduto = (int) $produto['id'];
                                                        echo "<option value=\"$idProduto\">$nome (Disponível: $quantidade)</option>";
                                                    }
                                                }
                                            } catch (PDOException $e) {
                                                echo '<option disabled>Erro ao carregar produtos</option>';
                                            }
                                        ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="quantidade" class="form-label">Quantidade desejada</label>
                                    <input type="number" class="form-control" id="quantidade" name="quantidade" min="1"
                                        required>
                                </div>
                                <div class="mb-3">
                                    <label for="justificativa" class="form-label">Justificativa da solicitação</label>
                                    <textarea class="form-control" id="justificativa" name="justificativa" rows="3"
                                        required placeholder="Descreva o motivo da solicitação"></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary col-md-12">Solicitar Produto</button>
                            </form>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <!-- Core JS -->
    <script src="../../js/saudacao.js"></script>
    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../assets/vendor/js/menu.js"></script>
    <script src="../../assets/vendor/libs/apex-charts/apexcharts.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/dashboards-analytics.js"></script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>
</body>

</html>
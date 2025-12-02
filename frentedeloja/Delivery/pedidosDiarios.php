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

// ✅ Buscar nome e tipo do usuário logado
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id = $_SESSION['usuario_id'];

try {
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $nomeUsuario = $usuario['usuario'];
        $tipoUsuario = ucfirst($usuario['nivel']);
    }
} catch (PDOException $e) {}

// ✅ Valida o tipo de empresa e o acesso permitido
$acessoPermitido = false;
$idEmpresaSession = $_SESSION['empresa_id'];
$tipoSession = $_SESSION['tipo_empresa'];

if (str_starts_with($idSelecionado, 'principal_')) {
    $acessoPermitido = ($tipoSession === 'principal' && $idEmpresaSession === 'principal_1');
} elseif (str_starts_with($idSelecionado, 'filial_')) {
    $acessoPermitido = ($tipoSession === 'filial' && $idEmpresaSession === $idSelecionado);
} elseif (str_starts_with($idSelecionado, 'unidade_')) {
    $acessoPermitido = ($tipoSession === 'unidade' && $idEmpresaSession === $idSelecionado);
} elseif (str_starts_with($idSelecionado, 'franquia_')) {
    $acessoPermitido = ($tipoSession === 'franquia' && $idEmpresaSession === $idSelecionado);
}

if (!$acessoPermitido) {
    echo "<script>alert('Acesso negado!'); window.location.href='.././login.php?id=$idSelecionado';</script>";
    exit;
}

// ✅ Buscar imagem da empresa para usar como favicon
$iconeEmpresa = '../../assets/img/favicon/favicon.ico'; // Ícone padrão

try {
    $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1");
    $stmt->bindParam(':id', $idSelecionado);
    $stmt->execute();
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($empresa && !empty($empresa['imagem'])) {
        $iconeEmpresa = $empresa['imagem'];
    }
} catch (PDOException $e) {}

?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
    data-assets-path="../../assets/" data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ERP - Delivery</title>

    <link rel="icon" href="../../assets/img/empresa/<?php echo $iconeEmpresa ?>" />

    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />

    <script src="../../assets/vendor/js/helpers.js"></script>
    <script src="../../assets/js/config.js"></script>
</head>

<body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">

            <!-- MENU (NÃO MEXI EM NADA) -->
            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">

                <div class="app-brand demo">
                    <a href="./index.php?id=<?= $idSelecionado ?>" class="app-brand-link">
                        <span class="app-brand-text demo menu-text fw-bolder ms-2"
                            style=" text-transform: capitalize;">Açaínhadinhos</span>
                    </a>

                    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
                        <i class="bx bx-chevron-left bx-sm align-middle"></i>
                    </a>
                </div>

                <ul class="menu-inner py-1">

                    <li class="menu-item active">
                        <a href="index.php?id=<?= $idSelecionado ?>" class="menu-link">
                            <i class="menu-icon bx bx-home-circle"></i>
                            <div>Dashboard</div>
                        </a>
                    </li>

                    <li class="menu-header small text-uppercase">
                        <span class="menu-header-text">Delivery</span>
                    </li>

                    <li class="menu-item">
                        <a class="menu-link menu-toggle">
                            <i class="menu-icon bx bx-cart"></i>
                            <div>Pedidos</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item"><a class="menu-link"
                                    href="./pedidosDiarios.php?id=<?= $idSelecionado ?>">Pedidos Diários</a></li>
                            <li class="menu-item"><a class="menu-link"
                                    href="./pedidosAceitos.php?id=<?= $idSelecionado ?>">Pedidos Aceitos</a></li>
                            <li class="menu-item"><a class="menu-link"
                                    href="./pedidosACaminho.php?id=<?= $idSelecionado ?>">Pedidos a Caminho</a></li>
                            <li class="menu-item"><a class="menu-link"
                                    href="./pedidosEntregues.php?id=<?= $idSelecionado ?>">Pedidos Entregues</a></li>
                            <li class="menu-item"><a class="menu-link"
                                    href="./pedidosCancelados.php?id=<?= $idSelecionado ?>">Pedidos Cancelados</a></li>
                        </ul>
                    </li>

                </ul>
            </aside>

            <!-- Layout container -->
            <div class="layout-page">

                <!-- NAVBAR (NÃO MEXI EM NADA) -->
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached bg-navbar-theme">

                    <div class="navbar-nav-right d-flex align-items-center ms-auto">

                        <ul class="navbar-nav flex-row align-items-center">

                            <li class="nav-item dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <img src="../../assets/img/empresa/<?php echo $iconeEmpresa ?>" alt=""
                                            class="w-px-40 h-auto rounded-circle">
                                    </div>
                                </a>
                            </li>

                        </ul>

                    </div>
                </nav>

                <!-- ===========================================
                     CONTAINER — SOMENTE AQUI MEXI !!!
                ============================================ -->
                <div class="container-xxl flex-grow-1 container-p-y">

                    <!-- INÍCIO PEDIDOS -->
                    <h4 class="fw-bold py-3 mb-4">📦 Pedidos Diários</h4>

                    <div class="card">
                        <h5 class="card-header">Pedidos Recebidos Hoje</h5>

                        <div class="table-responsive text-nowrap">

                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Cliente</th>
                                        <th>Endereço</th>
                                        <th>Pagamento</th>
                                        <th>Total</th>
                                        <th>Hora</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <!-- EXEMPLO 1 -->
                                    <tr>
                                        <td>1033</td>
                                        <td>Ana Júlia</td>
                                        <td>Rua Rui Barbosa, 90</td>
                                        <td>Pix</td>
                                        <td><b>R$ 22,50</b></td>
                                        <td>13:10</td>

                                        <td style="max-width:220px;">

                                            <select class="form-select form-select-sm mb-1">
                                                <option selected>Selecionar...</option>
                                                <option value="1">Aceitar Pedido</option>
                                                <option value="2">Cancelar Pedido</option>
                                            </select>

                                            <button class="btn btn-primary btn-sm w-100" data-bs-toggle="modal"
                                                data-bs-target="#itens1033">
                                                Itens
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- MODAL -->
                                    <div class="modal fade" id="itens1033">
                                        <div class="modal-dialog">
                                            <div class="modal-content">

                                                <div class="modal-header">
                                                    <h5 class="modal-title">Itens do Pedido #1033</h5>
                                                    <button class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>

                                                <div class="modal-body">
                                                    <ul>
                                                        <li>1x Açaí Médio</li>
                                                        <li>1x Granola</li>
                                                        <li>1x Banana</li>
                                                    </ul>
                                                </div>

                                            </div>
                                        </div>
                                    </div>

                                    <!-- EXEMPLO 2 -->
                                    <tr>
                                        <td>1034</td>
                                        <td>Pedro Almeida</td>
                                        <td>Avenida Amazonas, 300</td>
                                        <td>Dinheiro</td>
                                        <td><b>R$ 18,00</b></td>
                                        <td>13:22</td>

                                        <td>

                                            <select class="form-select form-select-sm mb-1">
                                                <option selected>Selecionar...</option>
                                                <option value="1">Aceitar Pedido</option>
                                                <option value="2">Cancelar Pedido</option>
                                            </select>

                                            <button class="btn btn-primary btn-sm w-100" data-bs-toggle="modal"
                                                data-bs-target="#itens1034">
                                                Itens
                                            </button>

                                        </td>
                                    </tr>

                                    <div class="modal fade" id="itens1034">
                                        <div class="modal-dialog">
                                            <div class="modal-content">

                                                <div class="modal-header">
                                                    <h5 class="modal-title">Itens do Pedido #1034</h5>
                                                    <button class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>

                                                <div class="modal-body">
                                                    <ul>
                                                        <li>1x Açaí Pequeno</li>
                                                        <li>1x Leite Ninho</li>
                                                    </ul>
                                                </div>

                                            </div>
                                        </div>
                                    </div>

                                </tbody>

                            </table>

                        </div>

                    </div>
                    <!-- FIM PEDIDOS -->

                </div>
                <!-- / Content -->

                <div class="content-backdrop fade"></div>

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

<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// ================================
//     VERIFICAÇÕES (MANTIDAS)
// ================================
$idSelecionado = $_GET['id'] ?? '';

if (!$idSelecionado) {
    header("Location: .././login.php");
    exit;
}

if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id'])
) {
    header("Location: .././login.php?id=" . urlencode($idSelecionado));
    exit;
}

require '../../assets/php/conexao.php';

// ================================
//  USUÁRIO LOGADO (MANTIDO)
// ================================
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

// ================================
//  VALIDAÇÃO DE EMPRESA (MANTIDA)
// ================================
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

// ================================
//  ÍCONE DA EMPRESA (MANTIDO)
// ================================
$iconeEmpresa = '../../assets/img/favicon/favicon.ico';

try {
    $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1");
    $stmt->bindParam(':id', $idSelecionado);
    $stmt->execute();
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!empty($empresa['imagem'])) {
        $iconeEmpresa = $empresa['imagem'];
    }
} catch (PDOException $e) {}

?>

<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>ERP - Delivery</title>

    <link rel="icon" href="../../assets/img/empresa/<?php echo $iconeEmpresa; ?>" />

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
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">

            <!--
                ======================================
                MENU LATERAL (MANTIDO INTACTO)
                ======================================
            -->
            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">

                <div class="app-brand demo">
                    <a href="./index.php?id=<?= $idSelecionado ?>" class="app-brand-link">
                        <span class="app-brand-text demo fw-bolder ms-2">Açaínhadinhos</span>
                    </a>
                    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-xl-none">
                        <i class="bx bx-chevron-left bx-sm"></i>
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
                        <a class="menu-link menu-toggle" href="#">
                            <i class="menu-icon bx bx-cart"></i>
                            <div>Pedidos</div>
                        </a>

                        <ul class="menu-sub">
                            <li class="menu-item"><a class="menu-link" href="./pedidosDiarios.php?id=<?= $idSelecionado ?>">Pedidos Diários</a></li>
                            <li class="menu-item"><a class="menu-link" href="./pedidosAceitos.php?id=<?= $idSelecionado ?>">Pedidos Aceitos</a></li>
                            <li class="menu-item"><a class="menu-link" href="./pedidosACaminho.php?id=<?= $idSelecionado ?>">Pedidos a Caminho</a></li>
                            <li class="menu-item"><a class="menu-link" href="./pedidosEntregues.php?id=<?= $idSelecionado ?>">Pedidos Entregues</a></li>
                            <li class="menu-item"><a class="menu-link" href="./pedidosCancelados.php?id=<?= $idSelecionado ?>">Pedidos Cancelados</a></li>
                        </ul>
                    </li>

                    <li class="menu-item">
                        <a class="menu-link menu-toggle" href="#">
                            <i class="menu-icon bx bx-food-menu"></i>
                            <div>Cardápio</div>
                        </a>

                        <ul class="menu-sub">
                            <li class="menu-item"><a class="menu-link" href="./produtosAdicionados.php">Produtos Adicionados</a></li>
                        </ul>
                    </li>

                </ul>
            </aside>

            <!--
                ======================================
                CONTEÚDO (AQUI ENTRA OS PEDIDOS)
                ======================================
            -->
            <div class="layout-page">

                <!-- NAVBAR (MANTIDO) -->
                <nav class="layout-navbar navbar navbar-expand-xl align-items-center bg-navbar-theme px-3">
                    <div class="navbar-nav-right ms-auto">
                        <ul class="navbar-nav flex-row align-items-center">
                            <li class="nav-item dropdown-user">
                                <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <img src="../../assets/img/empresa/<?php echo $iconeEmpresa ?>" class="w-px-40 rounded-circle" />
                                    </div>
                                </a>
                            </li>
                        </ul>
                    </div>
                </nav>

                <!--
                ======================================
                PÁGINA DE PEDIDOS DIÁRIOS (HTML PURO)
                ======================================
                -->
                <div class="container-xxl flex-grow-1 container-p-y">

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

                                <tbody class="table-border-bottom-0">

                                    <!-- PEDIDO EXEMPLO -->
                                    <tr>
                                        <td>1021</td>
                                        <td>João Pedro</td>
                                        <td>Rua Amazonas, 50</td>
                                        <td>Pix</td>
                                        <td><b>R$ 23,90</b></td>
                                        <td>13:22</td>

                                        <td>
                                            <button class="btn btn-sm btn-success">Aceitar</button>
                                            <button class="btn btn-sm btn-danger">Cancelar</button>

                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                                data-bs-target="#modalItens1021">
                                                Itens
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- MODAL DO PEDIDO -->
                                    <div class="modal fade" id="modalItens1021" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">

                                                <div class="modal-header">
                                                    <h5 class="modal-title">Itens do Pedido #1021</h5>
                                                    <button class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>

                                                <div class="modal-body">
                                                    <ul>
                                                        <li>1x Açaí Médio</li>
                                                        <li>2x Leite Ninho</li>
                                                        <li>1x Morango Extra</li>
                                                    </ul>
                                                </div>

                                            </div>
                                        </div>
                                    </div>

                                    <!-- Caso queira mais pedidos, basta duplicar o bloco acima -->

                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

                <div class="content-backdrop fade"></div>

            </div>
        </div>
    </div>

    <!-- SCRIPTS -->
    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../assets/vendor/js/menu.js"></script>
    <script src="../../assets/js/main.js"></script>

</body>
</html>

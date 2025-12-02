<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

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

$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id = $_SESSION['usuario_id'];

try {
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    $stmt->bindParam(':id', $usuario_id);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $nomeUsuario = $usuario['usuario'];
        $tipoUsuario = ucfirst($usuario['nivel']);
    }
} catch (PDOException $e) {
}

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

$iconeEmpresa = '../../assets/img/favicon/favicon.ico';

try {
    $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1");
    $stmt->bindParam(':id', $idSelecionado);
    $stmt->execute();
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!empty($empresa['imagem'])) {
        $iconeEmpresa = $empresa['imagem'];
    }
} catch (PDOException $e) {
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />

    <title>ERP - Delivery</title>

    <link rel="icon" href="../../assets/img/empresa/<?php echo $iconeEmpresa ?>" />

    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <script src="../../assets/vendor/js/helpers.js"></script>
    <script src="../../assets/js/config.js"></script>
</head>

<body>

    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">

            <!-- MENU (não alterado) -->
            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="./index.php?id=<?= $idSelecionado ?>" class="app-brand-link">
                        <span class="app-brand-text demo menu-text fw-bolder">Açaínhadinhos</span>
                    </a>
                </div>

                <ul class="menu-inner py-1">
                    <li class="menu-item active">
                        <a href="index.php?id=<?= $idSelecionado ?>" class="menu-link">
                            <i class="menu-icon bx bx-home-circle"></i>
                            Dashboard
                        </a>
                    </li>

                    <li class="menu-header">Delivery</li>

                    <li class="menu-item">
                        <a class="menu-link menu-toggle">
                            <i class="menu-icon bx bx-cart"></i>
                            Pedidos
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./pedidosDiarios.php?id=<?= $idSelecionado ?>" class="menu-link">Pedidos Diários</a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </aside>

            <!-- NAVBAR (não alterada) -->
            <div class="layout-page">

                <nav class="layout-navbar container-xxl navbar navbar-expand-xl bg-navbar-theme">
                    <div class="navbar-nav-right ms-auto">
                        <img src="../../assets/img/empresa/<?php echo $iconeEmpresa ?>" class="w-px-40 rounded-circle" />
                    </div>
                </nav>

                <!-- ==========================================
                     💥 SOMENTE O CONTAINER FOI ALTERADO
                ========================================== -->
                <div class="container-xxl flex-grow-1 container-p-y">

                    <h4 class="fw-bold py-3 mb-4">📦 Pedidos Diários</h4>

                    <div class="card">
                        <h5 class="card-header">Pedidos Recebidos Hoje</h5>

                        <div class="table-responsive">

                            <!-- TABLE + NO-WRAP -->
                            <table class="table text-nowrap">
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

                                    <!-- PEDIDO 1 -->
                                    <tr>
                                        <td>1033</td>
                                        <td>Ana Júlia</td>
                                        <td>Rua Rui Barbosa, 90</td>
                                        <td>Pix</td>
                                        <td><b>R$ 22,50</b></td>
                                        <td>13:10</td>

                                        <td>
                                            <div class="d-flex gap-2">

                                                <!-- AÇÕES -->
                                                <button class="btn btn-secondary btn-sm"
                                                    data-bs-toggle="modal" data-bs-target="#acao1033">
                                                    Ações
                                                </button>

                                                <!-- ITENS -->
                                                <button class="btn btn-primary btn-sm"
                                                    data-bs-toggle="modal" data-bs-target="#itens1033">
                                                    Itens
                                                </button>

                                            </div>
                                        </td>
                                    </tr>

                                    <!-- MODAL AÇÕES -->
                                    <div class="modal fade" id="acao1033">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">

                                                <div class="modal-header">
                                                    <h5 class="modal-title">Ações do Pedido #1033</h5>
                                                    <button class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>

                                                <div class="modal-body text-center">

                                                    <p class="mb-3">
                                                        Escolha o que deseja fazer com este pedido:
                                                    </p>

                                                    <!-- RESPONSIVO: MOBILE empilha / DESKTOP lado a lado -->
                                                    <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center">

                                                        <button class="btn btn-success px-4 py-2">
                                                            Aceitar Pedido
                                                        </button>

                                                        <button class="btn btn-danger px-4 py-2">
                                                            Cancelar Pedido
                                                        </button>

                                                    </div>

                                                </div>

                                            </div>
                                        </div>
                                    </div>

                                    <!-- MODAL ITENS -->
                                    <div class="modal fade" id="itens1033">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">

                                                <div class="modal-header">
                                                    <h5 class="modal-title">Itens do Pedido #1033</h5>
                                                    <button class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>

                                                <div class="modal-body">
                                                    <ul>
                                                        <li>1x Açaí Médio</li>
                                                        <li>2x Leite Ninho</li>
                                                        <li>1x Banana</li>
                                                    </ul>
                                                </div>

                                            </div>
                                        </div>
                                    </div>

                                    <!-- PEDIDO 2 -->
                                    <tr>
                                        <td>1034</td>
                                        <td>Pedro Almeida</td>
                                        <td>Avenida Amazonas, 300</td>
                                        <td>Dinheiro</td>
                                        <td><b>R$ 18,00</b></td>
                                        <td>13:22</td>

                                        <td>
                                            <div class="d-flex gap-2">

                                                <button class="btn btn-secondary btn-sm"
                                                    data-bs-toggle="modal" data-bs-target="#acao1034">
                                                    Ações
                                                </button>

                                                <button class="btn btn-primary btn-sm"
                                                    data-bs-toggle="modal" data-bs-target="#itens1034">
                                                    Itens
                                                </button>

                                            </div>
                                        </td>
                                    </tr>

                                    <!-- MODAL AÇÕES 1034 -->
                                    <div class="modal fade" id="acao1034">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">

                                                <div class="modal-header">
                                                    <h5 class="modal-title">Ações do Pedido #1034</h5>
                                                    <button class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>

                                                <div class="modal-body text-center">

                                                    <p class="mb-3">Escolha uma ação:</p>

                                                    <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center">
                                                        <button class="btn btn-success px-4 py-2">Aceitar</button>
                                                        <button class="btn btn-danger px-4 py-2">Cancelar</button>
                                                    </div>

                                                </div>

                                            </div>
                                        </div>
                                    </div>

                                    <!-- MODAL ITENS 1034 -->
                                    <div class="modal fade" id="itens1034">
                                        <div class="modal-dialog modal-dialog-centered">
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

                </div>
                <!-- FIM CONTAINER ALTERADO -->

            </div>

        </div>
    </div>

    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/js/menu.js"></script>
    <script src="../../assets/js/main.js"></script>

</body>

</html>
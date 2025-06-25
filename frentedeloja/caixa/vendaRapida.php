<?php

session_start();
require_once '../../assets/php/conexao.php';

// ✅ Recupera o identificador vindo da URL
$idSelecionado = $_GET['id'] ?? '';

// ✅ Verifica se a pessoa está logada
if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id']) // adiciona verificação do id do usuário
) {
    header("Location: ../index.php?id=$idSelecionado");
    exit;
}

// ✅ Valida o tipo de empresa e o acesso permitido
if (str_starts_with($idSelecionado, 'principal_')) {
    if ($_SESSION['tipo_empresa'] !== 'principal' || $_SESSION['empresa_id'] != 1) {
        echo "<script>
              alert('Acesso negado!');
              window.location.href = '../index.php?id=$idSelecionado';
          </script>";
        exit;
    }
    $id = 1;
} elseif (str_starts_with($idSelecionado, 'filial_')) {
    $idFilial = (int) str_replace('filial_', '', $idSelecionado);
    if ($_SESSION['tipo_empresa'] !== 'filial' || $_SESSION['empresa_id'] != $idFilial) {
        echo "<script>
              alert('Acesso negado!');
              window.location.href = '../index.php?id=$idSelecionado';
          </script>";
        exit;
    }
    $id = $idFilial;
} else {
    echo "<script>
          alert('Empresa não identificada!');
          window.location.href = '../index.php?id=$idSelecionado';
      </script>";
    exit;
}

// ✅ Se chegou até aqui, o acesso está liberado

$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id = $_SESSION['usuario_id'];
$tipoUsuarioSessao = $_SESSION['nivel']; // "Admin" ou "Funcionario"

try {
    if ($tipoUsuarioSessao === 'Admin') {
        // Buscar na tabela de Admins
        $stmt = $pdo->prepare("SELECT usuario, nivel, cpf FROM contas_acesso WHERE id = :id");
    } else {
        // Buscar na tabela de Funcionários
        $stmt = $pdo->prepare("SELECT usuario, nivel, cpf FROM funcionarios_acesso WHERE id = :id");
    }

    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $nomeUsuario = $usuario['usuario'];
        $tipoUsuario = ucfirst($usuario['nivel']);
        $cpfUsuario = $usuario['cpf'];
    } else {
        echo "<script>alert('Usuário não encontrado.'); window.location.href = './index.php?id=$idSelecionado';</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar nome e tipo do usuário: " . $e->getMessage() . "'); history.back();</script>";
    exit;
}

// ✅ Buscar imagem da empresa (favicon)
$iconeEmpresa = '../assets/img/favicon/favicon.ico';

try {
    $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1");
    $stmt->bindParam(':id_selecionado', $idSelecionado);
    $stmt->execute();
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($empresa && !empty($empresa['imagem'])) {
        $iconeEmpresa = $empresa['imagem'];
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar ícone da empresa: " . addslashes($e->getMessage()) . "');</script>";
}

try {
    // Buscar todos os setores
    $sql = "SELECT * FROM estoque WHERE empresa_id = :empresa_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR); // Usa o idSelecionado
    $stmt->execute();
    $estoque = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Erro ao buscar produtos: " . $e->getMessage();
    exit;
}

// Supondo que esses dados venham da sessão ou variável de sessão
$responsavel = ucwords($nomeUsuario); // ou $_SESSION['usuario']
$empresa_id = htmlspecialchars($idSelecionado); // ou $_POST['empresa_id']

if (!$responsavel || !$empresa_id) {
    die("Erro: Dados de sessão ausentes.");
}

try {
    // Consulta baseada no CPF logado
    $stmt = $pdo->prepare("
        SELECT id 
        FROM aberturas 
        WHERE cpf_responsavel = :cpf_responsavel 
        AND empresa_id = :empresa_id 
        AND status = 'aberto'
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([
        'cpf_responsavel' => $cpfUsuario,
        'empresa_id' => $empresa_id
    ]);

    // Busca o resultado e verifica se existe algum ID
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<script>alert('Erro ao buscar abertura do caixa: " . addslashes($e->getMessage()) . "'); history.back();</script>";
    exit;
}

?>

<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
    data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>ERP - PDV</title>

    <meta name="description" content="" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon"
        href="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
        rel="stylesheet" />

    <!-- Icons. Uncomment required icon fonts -->
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />

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

                    <!-- CAIXA -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Frente de Caixa</span>
                    </li>

                    <!-- Operações de Caixa -->
                    <li class="menu-item ">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-barcode-reader"></i>
                            <div data-i18n="Caixa">Operações de Caixa</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./abrirCaixa.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Abrir Caixa</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./fecharCaixa.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Fechar Caixa</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./sangria.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Sangria</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./suprimento.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Suprimento</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- Vendas -->
                    <li class="menu-item active">
                        <a href="./vendaRapida.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-cart-alt"></i>
                            <div data-i18n="Vendas">Venda Rápida</div>
                        </a>
                    </li>

                    <!-- Cancelamento / Ajustes -->
                    <li class="menu-item">
                        <a href="./cancelarVenda.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-x-circle"></i>
                            <div data-i18n="Cancelamento">Cancelar Venda</div>
                        </a>
                    </li>


                    <!-- Relatórios -->
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-bar-chart-alt"></i>
                            <div data-i18n="Relatórios">Relatórios</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./relatorioVendas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Resumo de Vendas</div>
                                </a>
                            </li>

                        </ul>
                    </li>
                    <!-- END CAIXA -->

                    </li>
                    <!-- Misc -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
                    <li class="menu-item">
                        <a href="../sistemadeponto/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-group"></i>
                            <div data-i18n="Authentications">Sistema de Ponto</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../Delivery/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-cart"></i>
                            <div data-i18n="Basic">Delivery</div>
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
                                                        <img src="../../assets/img/avatars/1.png" alt
                                                            class="w-px-40 h-auto rounded-circle" />
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <!-- Exibindo o nome e nível do usuário -->
                                                    <span class="fw-semibold d-block"><?php echo $nomeUsuario; ?></span>
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
                                            <span class="align-middle">Minha conta</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <i class="bx bx-cog me-2"></i>
                                            <span class="align-middle">Configurações</span>
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
                                            href="../logout.php?id=<?= urlencode($idSelecionado); ?>">
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

                <div class="container-xxl flex-grow-1 container-p-y">
                    <div class="row justify-content-center"></div>
                    <div class="col-lg-8 col-md-10"></div>
                    <div class="d-flex align-items-center mb-3">
                        <h4 class="fw-bold mb-0 flex-grow-1">
                            <a href="./vendaRapida.php?id=<?= urlencode($idSelecionado); ?>"
                                class="text-decoration-none text-dark">Venda Rápida</a>
                        </h4>
                    </div>
                    <div class="mb-4">
                        <h5 class="fw-bold custor-font mb-1">
                            <span class="text-muted fw-light">Registre uma nova venda</span>
                        </h5>
                    </div>
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="app-brand justify-content-center mb-4">
                                <a href="#" class="app-brand-link gap-2">
                                    <span class="app-brand-text demo text-body fw-bolder">Venda Rápida</span>
                                </a>
                            </div>
                            <div id="avisoSemCaixa" class="alert alert-danger text-center" style="display: none;">
                                Nenhum caixa está aberto. Por favor, abra um caixa para continuar com a venda.
                            </div>

                            <!-- Formulário de Venda Rápida -->
                            <form method="POST"
                                action="../../assets/php/frentedeloja/vendaRapidaSubmit.php?id=<?= urlencode($idSelecionado); ?>">

                                <div class="row g-3 ms-3">
                                    <!-- Produtos Selecionados -->
                                    <div class="fixed-items mb-3 p-2 border rounded bg-light row" id="fixedDisplay"
                                        style="min-height:48px;"></div>
                                    <!-- /Produtos Selecionados -->
                                </div>

                                <style>
                                    .fixed-items {
                                        display: flex;
                                        flex-wrap: wrap;
                                        gap: 8px;
                                        align-items: center;
                                    }

                                    .fixed-item {
                                        background: #f5f5f9;
                                        border: 1px solid #d3d3e2;
                                        border-radius: 6px;
                                        padding: 4px 8px;
                                        display: flex;
                                        align-items: center;
                                        justify-content: space-between;
                                        width: 100%;
                                        font-size: 0.97em;
                                        margin-bottom: 2px;
                                        position: relative;
                                    }

                                    .fixed-item-content {
                                        flex-grow: 1;
                                        padding-right: 10px;
                                    }

                                    .fixed-item-actions {
                                        display: flex;
                                        gap: 4px;
                                    }

                                    .fixed-item .edit-btn {
                                        background: #1890ff;
                                        color: #fff;
                                        border: none;
                                        border-radius: 4px;
                                        width: 22px;
                                        height: 22px;
                                        font-size: 0.85em;
                                        line-height: 1;
                                        cursor: pointer;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        padding: 0;
                                        transition: background 0.2s;
                                    }

                                    .fixed-item .edit-btn:hover {
                                        background: #096dd9;
                                    }

                                    .fixed-item .remove-btn {
                                        background: #ff4d4f;
                                        color: #fff;
                                        border: none;
                                        border-radius: 4px;
                                        width: 22px;
                                        height: 22px;
                                        font-size: 0.85em;
                                        line-height: 1;
                                        cursor: pointer;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        padding: 0;
                                        transition: background 0.2s;
                                    }

                                    .fixed-item .remove-btn:hover {
                                        background: #d32f2f;
                                    }

                                    .quantidade-container {
                                        display: flex;
                                        align-items: center;
                                        gap: 8px;
                                        margin-bottom: 10px;
                                    }

                                    .quantidade-container label {
                                        margin-bottom: 0;
                                        font-weight: 500;
                                    }

                                    .quantidade-container input {
                                        width: 70px;
                                    }

                                    /* Modal de edição */
                                    .modal-edicao-quantidade {
                                        display: none;
                                        position: fixed;
                                        top: 0;
                                        left: 0;
                                        width: 100%;
                                        height: 100%;
                                        background-color: rgba(0, 0, 0, 0.5);
                                        z-index: 9999;
                                        justify-content: center;
                                        align-items: center;
                                    }

                                    .modal-content {
                                        background: white;
                                        padding: 20px;
                                        border-radius: 8px;
                                        width: 300px;
                                        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
                                        z-index: 10000;
                                    }

                                    .modal-header {
                                        border-bottom: 1px solid #eee;
                                        padding-bottom: 10px;
                                        margin-bottom: 15px;
                                    }

                                    .modal-buttons {
                                        display: flex;
                                        justify-content: flex-end;
                                        gap: 10px;
                                        margin-top: 15px;
                                    }

                                    .modal-overlay {
                                        position: fixed;
                                        top: 0;
                                        left: 0;
                                        right: 0;
                                        bottom: 0;
                                        background-color: rgba(0, 0, 0, 0.5);
                                        z-index: 9998;
                                    }
                                </style>

                                <!-- Modal de Edição de Quantidade -->
                                <div class="modal-edicao-quantidade" id="modalEdicao">
                                    <div class="modal-overlay"></div>
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="mb-0">Editar Quantidade</h5>
                                        </div>
                                        <div class="quantidade-container">
                                            <label for="quantidadeEdicao">Quantidade:</label>
                                            <input type="number" id="quantidadeEdicao" min="1" value="1"
                                                class="form-control form-control-sm">
                                        </div>
                                        <div class="modal-buttons">
                                            <button type="button" class="btn btn-secondary btn-sm"
                                                id="cancelarEdicao">Cancelar</button>
                                            <button type="button" class="btn btn-primary btn-sm"
                                                id="confirmarEdicao">Confirmar</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Forma de Pagamento -->
                                <div class="col-md-12 mb-4">
                                    <label for="forma_pagamento" class="form-label">* Forma de Pagamento</label>
                                    <select id="forma_pagamento" name="forma_pagamento" class="form-select" required>
                                        <option value="">Selecione...</option>
                                        <option value="Dinheiro">Dinheiro</option>
                                        <option value="Cartão de Crédito">Cartão de Crédito</option>
                                        <option value="Cartão de Débito">Cartão de Débito</option>
                                        <option value="Pix">PIX</option>
                                    </select>
                                </div>
                                <!-- /Forma de Pagamento -->

                                <!-- Seleção de Categoria -->
                                <div class="col-md-12 mb-2">
                                    <label class="form-label">Categoria</label>
                                    <select class="form-select" id="categoriaSelect">
                                        <option value="">Selecione uma categoria</option>
                                        <?php
                                        $categorias = [];
                                        foreach ($estoque as $estoques) {
                                            $categorias[$estoques['categoria_produto']][] = $estoques;
                                        }
                                        foreach ($categorias as $categoria => $produtos): ?>
                                            <option value="<?= htmlspecialchars($categoria) ?>">
                                                <?= htmlspecialchars($categoria) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <!-- /Seleção de Categoria -->

                                <!-- Seleção de Produto -->
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Produto</label>
                                    <select class="form-select" id="multiSelect" size="5">
                                        <option value="">Selecione uma categoria primeiro</option>
                                    </select>
                                </div>
                                <!-- /Seleção de Produto -->

                                <!-- Quantidade do Produto (inicialmente oculto) -->
                                <div class="col-md-12 mb-3" id="quantidadeContainer" style="display: none;">
                                    <div class="quantidade-container">
                                        <label for="quantidadeProduto" class="form-label">Quantidade:</label>
                                        <div class="input-group input-group-sm" style="width: 120px;">
                                            <input type="number" id="quantidadeProduto" min="1" value="1"
                                                class="form-control form-control-sm">
                                        </div>
                                    </div>
                                </div>
                                <!-- /Quantidade do Produto -->

                                <!-- Valor Total -->
                                <div class="col-md-12 mb-2">
                                    <label class="form-label">Valor Total</label><br>
                                    <input type="hidden" name="totalTotal" id="totalTotal" value="0.00">
                                    <span id="total" class="fw-bold fs-5">0,00</span>
                                </div>
                                <!-- /Valor Total -->

                                <!-- Remover Todos -->
                                <div class="col-12 text-end mb-4">
                                    <button id="removerTodosBtn" type="button"
                                        class="btn btn-danger btn-sm remove-produto">Remover Todos</button>
                                </div>
                                <!-- /Remover Todos -->

                                <!-- Adicionar Produto -->
                                <div class="col-12 d-grid mb-2">
                                    <button id="fixarBtn" disabled type="button" class="btn btn-outline-primary">
                                        <i class="tf-icons bx bx-plus"></i> Adicionar Produto
                                    </button>
                                </div>
                                <!-- /Adicionar Produto -->

                                <!-- Campos Ocultos e Finalizar -->
                                <div class="col-12">
                                    <?php
                                    if ($resultado) {
                                        $idAbertura = $resultado['id'];
                                        echo "<input type='hidden' id='id_caixa' name='id_caixa' value='$idAbertura' >";
                                    }
                                    ?>
                                    <input type="hidden" id="responsavel" name="responsavel"
                                        value="<?= ucwords($nomeUsuario); ?>">
                                    <input type="hidden" id="cpf" name="cpf" value="<?= ucwords($cpfUsuario); ?>">
                                    <input type="hidden" name="data_registro" id="data_registro">
                                    <input type="hidden" name="idSelecionado"
                                        value="<?php echo htmlspecialchars($idSelecionado); ?>" />
                                    <button type="submit" id="finalizarVendaBtn" disabled
                                        class="btn btn-primary w-100 mt-2">Finalizar Venda</button>
                                </div>
                                <!-- /Campos Ocultos e Finalizar -->

                            </form>
                            <!-- /Formulário de Venda Rápida -->

                            <script>
                                // Produtos agrupados por categoria
                                const produtosPorCategoria = <?php
                                $jsCategorias = [];
                                foreach ($categorias as $categoria => $produtos) {
                                    foreach ($produtos as $estoques) {
                                        $jsCategorias[$categoria][] = [
                                            'id' => $estoques['id'],
                                            'nome' => $estoques['nome_produto'],
                                            'preco' => $estoques['preco_produto'],
                                            'quantidade' => $estoques['quantidade_produto'],
                                            'categoria' => $categoria
                                        ];
                                    }
                                }
                                echo json_encode($jsCategorias);
                                ?>;

                                const categoriaSelect = document.getElementById('categoriaSelect');
                                const multiSelect = document.getElementById('multiSelect');
                                const fixarBtn = document.getElementById('fixarBtn');
                                const fixedDisplay = document.getElementById('fixedDisplay');
                                const totalDisplay = document.getElementById('total');
                                const removerTodosBtn = document.getElementById('removerTodosBtn');
                                const finalizarVendaBtn = document.getElementById('finalizarVendaBtn');
                                const formaPagamentoSelect = document.getElementById('forma_pagamento');
                                const quantidadeProdutoInput = document.getElementById('quantidadeProduto');
                                const quantidadeContainer = document.getElementById('quantidadeContainer');
                                const form = document.querySelector('form');
                                const fixedItems = new Map();
                                let selectedOption = null;
                                const nenhumProdutoLabel = document.getElementById('nenhumProdutoLabel');

                                // Elementos do modal de edição
                                const modalEdicao = document.getElementById('modalEdicao');
                                const modalOverlay = document.querySelector('.modal-overlay');
                                const quantidadeEdicaoInput = document.getElementById('quantidadeEdicao');
                                const cancelarEdicaoBtn = document.getElementById('cancelarEdicao');
                                const confirmarEdicaoBtn = document.getElementById('confirmarEdicao');
                                let produtoEmEdicao = null;

                                // Habilita/desabilita botão Adicionar Produto com base na forma de pagamento
                                formaPagamentoSelect.addEventListener('change', function () {
                                    fixarBtn.disabled = formaPagamentoSelect.value === "" || !selectedOption;
                                });

                                // Categoria -> Produtos
                                categoriaSelect.addEventListener('change', function () {
                                    const categoria = this.value;
                                    multiSelect.innerHTML = '';
                                    quantidadeContainer.style.display = 'none'; // Esconde o container de quantidade
                                    fixarBtn.disabled = true;

                                    if (!categoria || !produtosPorCategoria[categoria]) {
                                        multiSelect.innerHTML = '<option value="">Selecione uma categoria primeiro</option>';
                                        return;
                                    }

                                    produtosPorCategoria[categoria].forEach(produto => {
                                        const option = document.createElement('option');
                                        option.value = produto.id;
                                        option.dataset.nome = produto.nome;
                                        option.dataset.preco = produto.preco;
                                        option.dataset.categoria = categoria;
                                        option.dataset.estoque = produto.quantidade;
                                        option.textContent = `${produto.nome} - Qtd: ${produto.quantidade} - R$ ${parseFloat(produto.preco).toFixed(2).replace('.', ',')}` + (produto.quantidade < 15 ? ' - ESTOQUE BAIXO!' : '');
                                        multiSelect.appendChild(option);
                                    });
                                });

                                // Seleção de produto
                                multiSelect.addEventListener('change', () => {
                                    selectedOption = multiSelect.options[multiSelect.selectedIndex];

                                    if (selectedOption && selectedOption.value) {
                                        // Mostra o container de quantidade
                                        quantidadeContainer.style.display = 'block';

                                        // Atualiza o valor máximo do input de quantidade com base no estoque
                                        const estoqueDisponivel = parseInt(selectedOption.dataset.estoque);
                                        quantidadeProdutoInput.max = estoqueDisponivel;
                                        quantidadeProdutoInput.value = 1;

                                        // Habilita o botão se a forma de pagamento estiver selecionada
                                        fixarBtn.disabled = formaPagamentoSelect.value === "";
                                    } else {
                                        // Esconde o container de quantidade se nenhum produto estiver selecionado
                                        quantidadeContainer.style.display = 'none';
                                        fixarBtn.disabled = true;
                                    }
                                });

                                // Validação do input de quantidade
                                quantidadeProdutoInput.addEventListener('change', function () {
                                    if (!selectedOption) return;

                                    const estoqueDisponivel = parseInt(selectedOption.dataset.estoque);
                                    let quantidade = parseInt(this.value) || 1;

                                    if (quantidade < 1) {
                                        quantidade = 1;
                                        this.value = 1;
                                    }

                                    if (estoqueDisponivel > 0 && quantidade > estoqueDisponivel) {
                                        quantidade = estoqueDisponivel;
                                        this.value = estoqueDisponivel;
                                        alert(`Quantidade ajustada para o máximo disponível em estoque: ${estoqueDisponivel}`);
                                    }
                                });

                                // Adicionar produto fixado
                                fixarBtn.addEventListener('click', () => {
                                    if (formaPagamentoSelect.value === "") {
                                        alert('Por favor, selecione uma forma de pagamento antes de adicionar produtos.');
                                        return;
                                    }

                                    if (!selectedOption) return;

                                    const id = selectedOption.value;
                                    const nome = selectedOption.dataset.nome;
                                    const preco = parseFloat(selectedOption.dataset.preco);
                                    const categoria = selectedOption.dataset.categoria;
                                    const quantidade = parseInt(quantidadeProdutoInput.value) || 1;

                                    // Verifica se já existe o produto na lista
                                    if (fixedItems.has(id)) {
                                        // Atualiza a quantidade se o produto já estiver na lista
                                        const itemExistente = fixedItems.get(id);
                                        const novaQuantidade = itemExistente.quantidade + quantidade;
                                        const estoqueDisponivel = parseInt(selectedOption.dataset.estoque);

                                        if (novaQuantidade > estoqueDisponivel) {
                                            alert(`Quantidade total (${novaQuantidade}) excede o estoque disponível (${estoqueDisponivel}). Ajustado para o máximo possível.`);
                                            itemExistente.quantidade = estoqueDisponivel;
                                        } else {
                                            itemExistente.quantidade = novaQuantidade;
                                        }
                                    } else {
                                        // Adiciona novo produto à lista
                                        fixedItems.set(id, {
                                            nome,
                                            preco,
                                            quantidade,
                                            categoria,
                                            id
                                        });
                                    }

                                    updateFixedDisplay();
                                    finalizarVendaBtn.disabled = false;
                                    fixarBtn.disabled = true;
                                    selectedOption = null;
                                    multiSelect.selectedIndex = -1;
                                    quantidadeProdutoInput.value = 1;
                                    quantidadeContainer.style.display = 'none'; // Esconde o container após adicionar
                                });

                                // Atualiza exibição dos produtos fixados
                                function updateFixedDisplay() {
                                    fixedDisplay.innerHTML = '';
                                    if (fixedItems.size === 0) {
                                        fixedDisplay.textContent = 'Nenhum item fixado';
                                        if (nenhumProdutoLabel) nenhumProdutoLabel.style.display = '';
                                        totalDisplay.textContent = '0,00';
                                        document.getElementById('totalTotal').value = '0.00';
                                        finalizarVendaBtn.disabled = true;
                                        return;
                                    }
                                    if (nenhumProdutoLabel) nenhumProdutoLabel.style.display = 'none';

                                    fixedItems.forEach((item, id) => {
                                        const container = document.createElement('div');
                                        container.className = 'col-12 col-md-4';
                                        container.innerHTML = `
                                                        <div class="fixed-item">
                                                            <div class="fixed-item-content">${item.nome} (${item.quantidade}x)</div>
                                                            <div class="fixed-item-actions">
                                                                <button class="edit-btn" data-id="${id}" type="button">
                                                                    <i class="bx bx-edit"></i>
                                                                </button>
                                                                <button class="remove-btn" data-id="${id}" type="button">×</button>
                                                            </div>
                                                        </div>
                                                    `;

                                        // Adiciona evento para editar quantidade
                                        const editBtn = container.querySelector('.edit-btn');
                                        editBtn.addEventListener('click', (e) => {
                                            e.preventDefault();
                                            produtoEmEdicao = id;
                                            quantidadeEdicaoInput.value = fixedItems.get(id).quantidade;
                                            quantidadeEdicaoInput.max = getEstoqueMaximo(id);
                                            modalEdicao.style.display = 'flex';
                                            document.body.style.overflow = 'hidden'; // Impede rolagem da página
                                        });

                                        // Adiciona evento para remover produto
                                        const removeBtn = container.querySelector('.remove-btn');
                                        removeBtn.addEventListener('click', (e) => {
                                            e.preventDefault();
                                            fixedItems.delete(id);
                                            updateFixedDisplay();
                                        });

                                        fixedDisplay.appendChild(container);
                                    });
                                    calcularTotal();
                                }

                                // Eventos do modal de edição
                                cancelarEdicaoBtn.addEventListener('click', () => {
                                    modalEdicao.style.display = 'none';
                                    document.body.style.overflow = 'auto';
                                    produtoEmEdicao = null;
                                });

                                confirmarEdicaoBtn.addEventListener('click', () => {
                                    if (!produtoEmEdicao) return;

                                    const novaQuantidade = parseInt(quantidadeEdicaoInput.value) || 1;
                                    const estoqueMaximo = parseInt(quantidadeEdicaoInput.max);

                                    if (novaQuantidade < 1) {
                                        alert('A quantidade mínima é 1');
                                        return;
                                    }

                                    if (novaQuantidade > estoqueMaximo) {
                                        alert(`Quantidade máxima em estoque: ${estoqueMaximo}`);
                                        return;
                                    }

                                    const produto = fixedItems.get(produtoEmEdicao);
                                    produto.quantidade = novaQuantidade;
                                    updateFixedDisplay();
                                    modalEdicao.style.display = 'none';
                                    document.body.style.overflow = 'auto';
                                    produtoEmEdicao = null;
                                });

                                // Fechar modal ao clicar fora
                                modalOverlay.addEventListener('click', () => {
                                    modalEdicao.style.display = 'none';
                                    document.body.style.overflow = 'auto';
                                    produtoEmEdicao = null;
                                });

                                // Obtém o estoque máximo para um produto
                                function getEstoqueMaximo(idProduto) {
                                    for (const categoria in produtosPorCategoria) {
                                        const produto = produtosPorCategoria[categoria].find(p => p.id == idProduto);
                                        if (produto) {
                                            return produto.quantidade;
                                        }
                                    }
                                    return Infinity;
                                }

                                // Calcula o total
                                function calcularTotal() {
                                    let total = 0;
                                    fixedItems.forEach(item => {
                                        total += item.preco * item.quantidade;
                                    });
                                    totalDisplay.textContent = total.toFixed(2).replace('.', ',');
                                    document.getElementById('totalTotal').value = total.toFixed(2);
                                }

                                // Remover todos os produtos
                                removerTodosBtn.addEventListener('click', () => {
                                    if (fixedItems.size === 0) {
                                        alert('Nenhum produto para remover.');
                                        return;
                                    }
                                    if (confirm('Deseja remover todos os produtos fixados?')) {
                                        fixedItems.clear();
                                        updateFixedDisplay();
                                    }
                                });

                                // Finalizar venda
                                form.addEventListener('submit', function (e) {
                                    if (finalizarVendaBtn.disabled) {
                                        e.preventDefault();
                                        alert('Selecione ao menos um produto antes de finalizar a venda.');
                                        return;
                                    }

                                    if (fixedItems.size === 0) {
                                        e.preventDefault();
                                        alert('Selecione ao menos um produto antes de finalizar a venda.');
                                        return;
                                    }

                                    if (formaPagamentoSelect.value === "") {
                                        e.preventDefault();
                                        alert('Por favor, selecione uma forma de pagamento.');
                                        return;
                                    }

                                    // Remove inputs antigos
                                    document.querySelectorAll('.input-produto-dinamico').forEach(input => input.remove());

                                    // Adiciona os produtos fixados como inputs ocultos
                                    fixedItems.forEach((item, id) => {
                                        // Nome
                                        const inputNome = document.createElement('input');
                                        inputNome.type = 'hidden';
                                        inputNome.name = 'produtos[]';
                                        inputNome.value = item.nome;
                                        inputNome.classList.add('input-produto-dinamico');
                                        form.appendChild(inputNome);
                                        // Quantidade
                                        const inputQuantidade = document.createElement('input');
                                        inputQuantidade.type = 'hidden';
                                        inputQuantidade.name = 'quantidade[]';
                                        inputQuantidade.value = item.quantidade;
                                        inputQuantidade.classList.add('input-produto-dinamico');
                                        form.appendChild(inputQuantidade);
                                        // Preço
                                        const inputPreco = document.createElement('input');
                                        inputPreco.type = 'hidden';
                                        inputPreco.name = 'precos[]';
                                        inputPreco.value = item.preco;
                                        inputPreco.classList.add('input-produto-dinamico');
                                        form.appendChild(inputPreco);
                                        // ID do Produto
                                        const inputIdProduto = document.createElement('input');
                                        inputIdProduto.type = 'hidden';
                                        inputIdProduto.name = 'id_produto[]';
                                        inputIdProduto.value = item.id;
                                        inputIdProduto.classList.add('input-produto-dinamico');
                                        form.appendChild(inputIdProduto);
                                        // ID da Categoria (nome da categoria)
                                        const inputIdCategoria = document.createElement('input');
                                        inputIdCategoria.type = 'hidden';
                                        inputIdCategoria.name = 'id_categoria[]';
                                        inputIdCategoria.value = item.categoria;
                                        inputIdCategoria.classList.add('input-produto-dinamico');
                                        form.appendChild(inputIdCategoria);
                                    });
                                });

                                // Exibe aviso se não houver caixa aberto
                                document.addEventListener('DOMContentLoaded', function () {
                                    const idCaixa = document.getElementById('id_caixa');
                                    const aviso = document.getElementById('avisoSemCaixa');
                                    if (!idCaixa || !idCaixa.value.trim()) {
                                        form.style.display = 'none';
                                        if (aviso) aviso.style.display = 'block';
                                    }
                                    updateFixedDisplay(); // Garante que o label está correto ao carregar

                                    // Desabilita apenas o botão Adicionar Produto inicialmente
                                    fixarBtn.disabled = true;
                                    finalizarVendaBtn.disabled = true;
                                });

                                document.addEventListener("DOMContentLoaded", function () {
                                    const inputDataRegistro = document.getElementById("data_registro");

                                    // Função para formatar data/hora local como "YYYY-MM-DD HH:mm:ss"
                                    function formatarDataLocal(date) {
                                        const pad = num => String(num).padStart(2, '0');
                                        const ano = date.getFullYear();
                                        const mes = pad(date.getMonth() + 1);
                                        const dia = pad(date.getDate());
                                        const horas = pad(date.getHours());
                                        const minutos = pad(date.getMinutes());
                                        const segundos = pad(date.getSeconds());
                                        return `${ano}-${mes}-${dia} ${horas}:${minutos}:${segundos}`;
                                    }

                                    if (inputDataRegistro) {
                                        // Define data atual assim que o DOM carregar
                                        inputDataRegistro.value = formatarDataLocal(new Date());
                                    }

                                    if (form && inputDataRegistro) {
                                        form.addEventListener('submit', function () {
                                            inputDataRegistro.value = formatarDataLocal(new Date());
                                        });
                                    }
                                });
                            </script>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    </div>
    </div>
    </div>

</body>

<script src="../../assets/vendor/libs/jquery/jquery.js"></script>
<script src="../../assets/vendor/libs/popper/popper.js"></script>
<script src="../../assets/vendor/js/bootstrap.js"></script>
<script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
<script src="../../assets/vendor/js/menu.js"></script>
<script src="../../assets/js/main.js"></script>

</html>
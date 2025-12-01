<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// ✅ Recupera o identificador vindo da URL
$idSelecionado = $_GET['id'] ?? '';

// ✅ Verificações de sessão
if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id']) ||
    !isset($_SESSION['nivel']) || // nível na sessão
    !isset($_SESSION['usuario_cpf']) // CPF na sessão
) {
    header("Location: ../index.php?id=" . urlencode($idSelecionado));
    exit;
}

// ✅ Compat: str_starts_with para PHP < 8
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        return 0 === strncmp($haystack, $needle, strlen($needle));
    }
}

// ✅ Conexão com o banco
require '../../assets/php/conexao.php';

$nomeUsuario        = 'Usuário';
$tipoUsuario        = 'Comum';
$usuario_id         = $_SESSION['usuario_id'];
$usuario_cpf        = $_SESSION['usuario_cpf'];
$tipoUsuarioSessao  = $_SESSION['nivel']; // "Admin" ou "Comum"

// ✅ Busca nome/nível conforme origem (admin x funcionário)
try {
    if ($tipoUsuarioSessao === 'Admin') {
        $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    } else {
        $stmt = $pdo->prepare("SELECT usuario, nivel FROM funcionarios_acesso WHERE id = :id");
    }
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $nomeUsuario = $usuario['usuario'];
        $tipoUsuario = ucfirst($usuario['nivel']);
    } else {
        echo "<script>alert('Usuário não encontrado.'); window.location.href = './index.php?id=" . urlencode($idSelecionado) . "';</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar usuário: " . addslashes($e->getMessage()) . "'); history.back();</script>";
    exit;
}

// ✅ Valida empresa/escopo
if (str_starts_with($idSelecionado, 'principal_')) {
    $permitido = (
        $_SESSION['tipo_empresa'] === 'principal' ||
        ($tipoUsuarioSessao === 'Admin' && $_SESSION['empresa_id'] === 'principal_1')
    );
    if (!$permitido) {
        echo "<script>alert('Acesso negado!'); window.location.href = '../index.php?id=" . urlencode($idSelecionado) . "';</script>";
        exit;
    }
} elseif (str_starts_with($idSelecionado, 'unidade_')) {
    $permitido = (
        $_SESSION['empresa_id'] === $idSelecionado ||
        ($tipoUsuarioSessao === 'Admin' && $_SESSION['empresa_id'] === 'principal_1')
    );
    if (!$permitido) {
        echo "<script>alert('Acesso negado!'); window.location.href = '../index.php?id=" . urlencode($idSelecionado) . "';</script>";
        exit;
    }
} else {
    echo "<script>alert('Empresa não identificada!'); window.location.href = '../index.php?id=" . urlencode($idSelecionado) . "';</script>";
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

// ✅ Produtos (estoque) da empresa
try {
    $sql = "SELECT * FROM estoque WHERE empresa_id = :empresa_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $estoque = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Erro ao buscar produtos: " . $e->getMessage();
    exit;
}

// Agrupa categorias para o select
$categorias = [];
foreach ($estoque as $it) {
    $categorias[$it['categoria_produto']][] = $it;
}

// ✅ Checagem de CAIXA aberto para o CPF logado
try {
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
        'cpf_responsavel' => $usuario_cpf,
        'empresa_id'      => $idSelecionado
    ]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<script>alert('Erro ao verificar abertura do caixa: " . addslashes($e->getMessage()) . "'); history.back();</script>";
    exit;
}

$caixaAberto = $resultado && !empty($resultado['id']);
$idAbertura  = $caixaAberto ? (int)$resultado['id'] : null;

// Helpers de saída
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>ERP - PDV</title>
    <meta name="description" content="" />
    <!-- Favicon da empresa carregado dinamicamente -->
    <link rel="icon" type="image/x-icon" href="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />
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
                        <span class="app-brand-text demo menu-text fw-bolder ms-2" style="text-transform: capitalize;">Açaínhadinhos</span>
                    </a>
                    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
                        <i class="bx bx-chevron-left bx-sm align-middle"></i>
                    </a>
                </div>

                <div class="menu-inner-shadow"></div>

                <ul class="menu-inner py-1">
                    <li class="menu-item">
                        <a href="index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Dashboard</div>
                        </a>
                    </li>

                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Frente de Caixa</span></li>

                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-barcode-reader"></i>
                            <div data-i18n="Caixa">Operações de Caixa</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item"><a href="./abrirCaixa.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Abrir Caixa</div>
                                </a></li>
                            <li class="menu-item"><a href="./fecharCaixa.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Fechar Caixa</div>
                                </a></li>
                            <li class="menu-item"><a href="./sangria.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Sangria</div>
                                </a></li>
                            <li class="menu-item"><a href="./suprimento.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Suprimento</div>
                                </a></li>
                        </ul>
                    </li>

                    <li class="menu-item active">
                        <a href="./vendaRapida.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-cart-alt"></i>
                            <div data-i18n="Vendas">Venda Rápida</div>
                        </a>
                    </li>

                    <li class="menu-item"><a href="./cancelarVenda.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-x-circle"></i>
                            <div data-i18n="Cancelamento">Cancelar Venda</div>
                        </a></li>

                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle"><i class="menu-icon tf-icons bx bx-bar-chart-alt"></i>
                            <div data-i18n="Relatórios">Relatórios</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item"><a href="./relatorioVendas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Resumo de Vendas</div>
                                </a></li>
                        </ul>
                    </li>

                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
                    <li class="menu-item">
                        <a href="../sistemadeponto/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-group"></i>
                            <div data-i18n="Authentications">Sistema de Ponto</div>
                        </a> <a href="./delivery/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-cart"></i>
                            <div data-i18n="Authentications">Delivery</div>
                        </a>
                    </li>
                    <li class="menu-item"><a href="https://wa.me/92991515710" target="_blank" class="menu-link"><i class="menu-icon tf-icons bx bx-support"></i>
                            <div data-i18n="Basic">Suporte</div>
                        </a>
                    </li>
                </ul>
            </aside>
            <!-- / Menu -->

            <!-- Layout container -->
            <div class="layout-page">

                <!-- Navbar -->
                <nav
                    class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
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
                            </div>
                        </div>
                        <!-- /Search -->

                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <!-- Place this tag where you want the button to render. -->
                            <!-- User -->
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <img src="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>" alt
                                            class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>" alt
                                                            class="w-px-40 h-auto rounded-circle" />
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <span class="fw-semibold d-block"><?= htmlspecialchars($nomeUsuario); ?></span>
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
                            <!--/ User -->
                        </ul>

                    </div>
                </nav>
                <!-- / Navbar -->

                <div class="container-xxl flex-grow-1 container-p-y">
                    <div class="d-flex align-items-center mb-3">
                        <h4 class="fw-bold mb-0 flex-grow-1">
                            <a href="./vendaRapida.php?id=<?= urlencode($idSelecionado); ?>" class="text-decoration-none text-dark">Venda Rápida</a>
                        </h4>
                    </div>

                    <?php if (!$caixaAberto): ?>
                        <!-- ALERTA: sem caixa aberto -->
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <div class="alert alert-danger text-center mb-3">
                                    <strong>Nenhum caixa está aberto.</strong><br>
                                    Para efetuar a venda, você precisa abrir um caixa.
                                </div>
                                <div class="text-center">
                                    <a class="btn btn-primary" href="./abrirCaixa.php?id=<?= urlencode($idSelecionado); ?>">
                                        Abrir Caixa agora
                                    </a>
                                    <a class="btn btn-outline-secondary ms-2" href="./index.php?id=<?= urlencode($idSelecionado); ?>">
                                        Voltar ao Dashboard
                                    </a>
                                </div>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- INFO do caixa aberto -->
                        <div class="alert alert-success">
                            Caixa aberto.
                        </div>

                        <div class="mb-4">
                            <h5 class="fw-bold custor-font mb-1"><span class="text-muted fw-light">Registre uma nova venda</span></h5>
                        </div>

                        <div class="card shadow-sm">
                            <div class="card-body">
                                <div class="app-brand justify-content-center mb-4">
                                    <a href="#" class="app-brand-link gap-2">
                                        <span class="app-brand-text demo text-body fw-bolder">Venda Rápida</span>
                                    </a>
                                </div>

                                <!-- Formulário de Venda Rápida -->
                                <form method="POST" action="./vendaRapidaSubmit.php?id=<?= urlencode($idSelecionado); ?>" id="formVendaRapida">
                                    <div class="row g-3 ms-3">
                                        <!-- Produtos Selecionados -->
                                        <div class="fixed-items mb-3 p-2 border rounded bg-light row" id="fixedDisplay" style="min-height:48px;"></div>
                                    </div>

                                    <style>
                                        .fixed-items {
                                            display: flex;
                                            flex-wrap: wrap;
                                            gap: 8px;
                                            align-items: center
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
                                            font-size: .97em;
                                            margin-bottom: 2px;
                                            position: relative
                                        }

                                        .fixed-item-content {
                                            flex-grow: 1;
                                            padding-right: 10px
                                        }

                                        .fixed-item-actions {
                                            display: flex;
                                            gap: 4px
                                        }

                                        .fixed-item .edit-btn {
                                            background: #1890ff;
                                            color: #fff;
                                            border: none;
                                            border-radius: 4px;
                                            width: 22px;
                                            height: 22px;
                                            font-size: .85em;
                                            line-height: 1;
                                            cursor: pointer;
                                            display: flex;
                                            align-items: center;
                                            justify-content: center;
                                            padding: 0;
                                            transition: background .2s
                                        }

                                        .fixed-item .edit-btn:hover {
                                            background: #096dd9
                                        }

                                        .fixed-item .remove-btn {
                                            background: #ff4d4f;
                                            color: #fff;
                                            border: none;
                                            border-radius: 4px;
                                            width: 22px;
                                            height: 22px;
                                            font-size: .85em;
                                            line-height: 1;
                                            cursor: pointer;
                                            display: flex;
                                            align-items: center;
                                            justify-content: center;
                                            padding: 0;
                                            transition: background .2s
                                        }

                                        .fixed-item .remove-btn:hover {
                                            background: #d32f2f
                                        }

                                        .quantidade-container {
                                            display: flex;
                                            align-items: center;
                                            gap: 8px;
                                            margin-bottom: 10px
                                        }

                                        .quantidade-container input {
                                            width: 70px
                                        }

                                        .modal-edicao-quantidade {
                                            display: none;
                                            position: fixed;
                                            top: 0;
                                            left: 0;
                                            width: 100%;
                                            height: 100%;
                                            background-color: rgba(0, 0, 0, .5);
                                            z-index: 9999;
                                            justify-content: center;
                                            align-items: center
                                        }

                                        .modal-content {
                                            background: #fff;
                                            padding: 20px;
                                            border-radius: 8px;
                                            width: 300px;
                                            box-shadow: 0 4px 20px rgba(0, 0, 0, .15);
                                            z-index: 10000
                                        }

                                        .modal-header {
                                            border-bottom: 1px solid #eee;
                                            padding-bottom: 10px;
                                            margin-bottom: 15px
                                        }

                                        .modal-buttons {
                                            display: flex;
                                            justify-content: flex-end;
                                            gap: 10px;
                                            margin-top: 15px
                                        }

                                        .modal-overlay {
                                            position: fixed;
                                            top: 0;
                                            left: 0;
                                            right: 0;
                                            bottom: 0;
                                            background-color: rgba(0, 0, 0, .5);
                                            z-index: 9998
                                        }

                                        .troco-container {
                                            display: none;
                                            margin-top: 10px;
                                            padding: 10px;
                                            background-color: #f8f9fa;
                                            border-radius: 5px;
                                            border: 1px solid #dee2e6
                                        }

                                        .troco-valor {
                                            font-weight: bold;
                                            color: #28a745;
                                            font-size: 1.1em
                                        }

                                        /* 🔎 Lista de sugestões da busca */
                                        .sugestoes {
                                            position: absolute;
                                            top: 100%;
                                            left: 0;
                                            right: 0;
                                            z-index: 2000;
                                            display: none;
                                            background: #fff;
                                            border: 1px solid #e5e7eb;
                                            border-radius: 8px;
                                            box-shadow: 0 10px 25px rgba(0, 0, 0, .08);
                                            max-height: 260px;
                                            overflow: auto
                                        }

                                        .sugestoes .item {
                                            padding: 10px 12px;
                                            cursor: pointer;
                                            display: flex;
                                            justify-content: space-between;
                                            gap: 12px;
                                            align-items: center
                                        }

                                        .sugestoes .item:hover,
                                        .sugestoes .item.ativo {
                                            background: #f5f7fb
                                        }

                                        .sugestoes .info {
                                            display: flex;
                                            flex-direction: column
                                        }

                                        .sugestoes .titulo {
                                            font-weight: 600;
                                            font-size: .95em
                                        }

                                        .sugestoes .sub {
                                            font-size: .82em;
                                            color: #6b7280
                                        }

                                        .sugestoes .tag {
                                            font-size: .78em;
                                            border: 1px solid #e5e7eb;
                                            border-radius: 999px;
                                            padding: 2px 8px;
                                            white-space: nowrap
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
                                                <input type="number" id="quantidadeEdicao" min="1" value="1" class="form-control form-control-sm">
                                            </div>
                                            <div class="modal-buttons">
                                                <button type="button" class="btn btn-secondary btn-sm" id="cancelarEdicao">Cancelar</button>
                                                <button type="button" class="btn btn-primary btn-sm" id="confirmarEdicao">Confirmar</button>
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

                                    <!-- Valor recebido + troco (Dinheiro) -->
                                    <div class="col-md-12 mb-3" id="valorRecebidoContainer" style="display:none;">
                                        <label for="valor_recebido" class="form-label">Valor Recebido (R$)</label>
                                        <input type="number" id="valor_recebido" name="valor_recebido" min="0" step="0.01" class="form-control">
                                    </div>
                                    <div class="col-md-12 mb-3 troco-container" id="trocoContainer">
                                        <label class="form-label">Troco:</label>
                                        <span class="troco-valor" id="trocoValor">R$ 0,00</span>
                                        <input type="hidden" id="troco" name="troco" value="0">
                                    </div>

                                    <!-- 🔎 Busca por produto (NOME ou CÓDIGO) -->
                                    <div class="col-md-12 mb-2 position-relative">
                                        <label class="form-label">Pesquisar produto (nome ou código)</label>
                                        <input type="text" id="buscaProduto" class="form-control" placeholder="Digite ao menos 2 caracteres...">
                                        <div id="listaSugestoes" class="sugestoes"></div>
                                    </div>

                                    <!-- Categoria -->
                                    <div class="col-md-12 mb-2">
                                        <label class="form-label">Categoria</label>
                                        <select class="form-select" id="categoriaSelect">
                                            <option value="">Selecione uma categoria</option>
                                            <?php foreach ($categorias as $categoria => $produtos): ?>
                                                <option value="<?= h($categoria) ?>"><?= h($categoria) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <!-- Produto -->
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Produto</label>
                                        <select class="form-select" id="multiSelect" size="5">
                                            <option value="">Selecione uma categoria primeiro</option>
                                        </select>
                                    </div>

                                    <!-- Quantidade do Produto -->
                                    <div class="col-md-12 mb-3" id="quantidadeContainer" style="display:none;">
                                        <div class="quantidade-container">
                                            <label for="quantidadeProduto" class="form-label">Quantidade:</label>
                                            <div class="input-group input-group-sm" style="width:120px;">
                                                <input type="number" id="quantidadeProduto" min="1" value="1" class="form-control form-control-sm">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Valor Total -->
                                    <div class="col-md-12 mb-2">
                                        <label class="form-label">Valor Total</label><br>
                                        <input type="hidden" name="totalTotal" id="totalTotal" value="0.00">
                                        <span id="total" class="fw-bold fs-5">0,00</span>
                                    </div>

                                    <!-- Remover Todos -->
                                    <div class="col-12 text-end mb-4">
                                        <button id="removerTodosBtn" type="button" class="btn btn-danger btn-sm remove-produto">Remover Todos</button>
                                    </div>

                                    <!-- CPF do Cliente -->
                                    <div class="col-md-12 mb-3">
                                        <label for="cpf_cliente" class="form-label">CPF do Cliente (opcional)</label>
                                        <input type="text" id="cpf_cliente" name="cpf_cliente" class="form-control cpf-mask" placeholder="000.000.000-00">
                                    </div>

                                    <!-- Adicionar Produto -->
                                    <div class="col-12 d-grid mb-2">
                                        <button id="fixarBtn" disabled type="button" class="btn btn-outline-primary">
                                            <i class="tf-icons bx bx-plus"></i> Adicionar Produto
                                        </button>
                                    </div>

                                    <!-- Campos Ocultos + Finalizar -->
                                    <div class="col-12">
                                        <input type="hidden" id="id_caixa" name="id_caixa" value="<?= (int)$idAbertura ?>">
                                        <input type="hidden" id="responsavel" name="responsavel" value="<?= h(ucwords($nomeUsuario)); ?>">
                                        <input type="hidden" id="cpf" name="cpf" value="<?= h($usuario_cpf) ?>">
                                        <input type="hidden" name="data_registro" id="data_registro">
                                        <input type="hidden" name="empresa_id" value="<?= h($idSelecionado) ?>">
                                        <button type="submit" id="finalizarVendaBtn" disabled class="btn btn-primary w-100 mt-2">Finalizar Venda</button>
                                    </div>
                                </form>

                                <!-- JS do formulário (só carrega quando há caixa aberto) -->
                                <script>
                                    // Mapa de categoria -> produtos (já existente)
                                    const produtosPorCategoria = <?php
                                                                    $jsCategorias = [];
                                                                    foreach ($categorias as $categoria => $produtos) {
                                                                        foreach ($produtos as $p) {
                                                                            $jsCategorias[$categoria][] = [
                                                                                'id' => $p['id'],
                                                                                'nome' => $p['nome_produto'],
                                                                                'preco' => (float)$p['preco_produto'],
                                                                                'quantidade' => (int)$p['quantidade_produto'],
                                                                                'categoria' => $categoria,
                                                                                'ncm' => $p['ncm'],
                                                                                'cest' => $p['cest'],
                                                                                'cfop' => $p['cfop'],
                                                                                'origem' => $p['origem'],
                                                                                'tributacao' => $p['tributacao'],
                                                                                'unidade' => $p['unidade'],
                                                                                'informacoes_adicionais' => $p['informacoes_adicionais']
                                                                            ];
                                                                        }
                                                                    }
                                                                    echo json_encode($jsCategorias, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                                                    ?>;

                                    // 🔎 NOVO: array "flat" com todos os produtos (inclui código_produto)
                                    const todosProdutos = (function() {
                                        const arr = [];
                                        <?php foreach ($categorias as $categoria => $produtos): foreach ($produtos as $p): ?>
                                                arr.push({
                                                    id: <?= (int)$p['id'] ?>,
                                                    nome: <?= json_encode($p['nome_produto']) ?>,
                                                    codigo: <?= json_encode($p['codigo_produto']) ?>,
                                                    categoria: <?= json_encode($categoria) ?>,
                                                    preco: <?= json_encode((float)$p['preco_produto']) ?>,
                                                    quantidade: <?= (int)$p['quantidade_produto'] ?>,
                                                    ncm: <?= json_encode($p['ncm']) ?>,
                                                    cest: <?= json_encode($p['cest']) ?>,
                                                    cfop: <?= json_encode($p['cfop']) ?>,
                                                    origem: <?= json_encode($p['origem']) ?>,
                                                    tributacao: <?= json_encode($p['tributacao']) ?>,
                                                    unidade: <?= json_encode($p['unidade']) ?>,
                                                    informacoes: <?= json_encode($p['informacoes_adicionais']) ?>
                                                });
                                        <?php endforeach;
                                        endforeach; ?>
                                        return arr;
                                    })();

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
                                    const form = document.getElementById('formVendaRapida');
                                    const valorRecebidoContainer = document.getElementById('valorRecebidoContainer');
                                    const valorRecebidoInput = document.getElementById('valor_recebido');
                                    const trocoContainer = document.getElementById('trocoContainer');
                                    const trocoValor = document.getElementById('trocoValor');
                                    const trocoInput = document.getElementById('troco');

                                    // 🔎 refs da busca
                                    const buscaInput = document.getElementById('buscaProduto');
                                    const listaSugestoes = document.getElementById('listaSugestoes');

                                    const fixedItems = new Map();
                                    let selectedOption = null;

                                    // Util: normaliza (sem acentos, minúsculo)
                                    const norm = s => String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

                                    // Forma de pagamento: mostra/oculta troco
                                    formaPagamentoSelect.addEventListener('change', function() {
                                        if (this.value === 'Dinheiro') {
                                            valorRecebidoContainer.style.display = 'block';
                                            trocoContainer.style.display = 'block';
                                            calcularTroco();
                                        } else {
                                            valorRecebidoContainer.style.display = 'none';
                                            trocoContainer.style.display = 'none';
                                            trocoValor.textContent = 'R$ 0,00';
                                            trocoInput.value = '0';
                                        }
                                        fixarBtn.disabled = this.value === "" || !selectedOption;
                                    });

                                    valorRecebidoInput && valorRecebidoInput.addEventListener('input', calcularTroco);

                                    function calcularTroco() {
                                        if (formaPagamentoSelect.value !== 'Dinheiro') return;
                                        const total = parseFloat(document.getElementById('totalTotal').value) || 0;
                                        const recebido = parseFloat(valorRecebidoInput.value) || 0;
                                        if (recebido >= total) {
                                            const troco = recebido - total;
                                            trocoValor.textContent = 'R$ ' + troco.toFixed(2).replace('.', ',');
                                            trocoInput.value = troco.toFixed(2);
                                        } else {
                                            trocoValor.textContent = 'R$ 0,00';
                                            trocoInput.value = '0';
                                        }
                                    }

                                    // Categoria -> carrega produtos
                                    categoriaSelect.addEventListener('change', function() {
                                        const categoria = this.value;
                                        multiSelect.innerHTML = '';
                                        quantidadeContainer.style.display = 'none';
                                        fixarBtn.disabled = true;

                                        if (!categoria || !produtosPorCategoria[categoria]) {
                                            multiSelect.innerHTML = '<option value="">Selecione uma categoria primeiro</option>';
                                            return;
                                        }
                                        produtosPorCategoria[categoria].forEach(prod => {
                                            const option = document.createElement('option');
                                            option.value = prod.id;
                                            option.dataset.nome = prod.nome;
                                            option.dataset.preco = prod.preco;
                                            option.dataset.categoria = categoria;
                                            option.dataset.estoque = prod.quantidade;
                                            option.dataset.ncm = prod.ncm;
                                            option.dataset.cest = prod.cest;
                                            option.dataset.cfop = prod.cfop;
                                            option.dataset.origem = prod.origem;
                                            option.dataset.tributacao = prod.tributacao;
                                            option.dataset.unidade = prod.unidade;
                                            option.dataset.informacoes = prod.informacoes_adicionais;
                                            option.textContent = `${prod.nome} - Qtd: ${prod.quantidade} - R$ ${Number(prod.preco).toFixed(2).replace('.', ',')}` + (prod.quantidade < 15 ? ' - ESTOQUE BAIXO!' : '');
                                            multiSelect.appendChild(option);
                                        });
                                    });

                                    // Seleção manual no select de produtos
                                    multiSelect.addEventListener('change', () => {
                                        selectedOption = multiSelect.options[multiSelect.selectedIndex];
                                        if (selectedOption && selectedOption.value) {
                                            quantidadeContainer.style.display = 'block';
                                            const estoqueDisponivel = parseInt(selectedOption.dataset.estoque);
                                            quantidadeProdutoInput.max = estoqueDisponivel;
                                            quantidadeProdutoInput.value = 1;
                                            fixarBtn.disabled = formaPagamentoSelect.value === "";
                                        } else {
                                            quantidadeContainer.style.display = 'none';
                                            fixarBtn.disabled = true;
                                        }
                                    });

                                    // Valida quantidade
                                    quantidadeProdutoInput.addEventListener('change', function() {
                                        if (!selectedOption) return;
                                        const estoqueDisponivel = parseInt(selectedOption.dataset.estoque);
                                        let q = parseInt(this.value) || 1;
                                        if (q < 1) q = 1;
                                        if (estoqueDisponivel > 0 && q > estoqueDisponivel) {
                                            q = estoqueDisponivel;
                                            alert(`Quantidade ajustada para o máximo disponível em estoque: ${estoqueDisponivel}`);
                                        }
                                        this.value = q;
                                    });

                                    // Adicionar item
                                    fixarBtn.addEventListener('click', () => {
                                        if (formaPagamentoSelect.value === "") {
                                            alert('Selecione uma forma de pagamento antes de adicionar produtos.');
                                            return;
                                        }
                                        if (!selectedOption) return;

                                        const id = selectedOption.value;
                                        const nome = selectedOption.dataset.nome;
                                        const preco = parseFloat(selectedOption.dataset.preco);
                                        const categoria = selectedOption.dataset.categoria;
                                        const quantidade = parseInt(quantidadeProdutoInput.value) || 1;
                                        const ncm = selectedOption.dataset.ncm;
                                        const cest = selectedOption.dataset.cest;
                                        const cfop = selectedOption.dataset.cfop;
                                        const origem = selectedOption.dataset.origem;
                                        const tributacao = selectedOption.dataset.tributacao;
                                        const unidade = selectedOption.dataset.unidade;
                                        const informacoes = selectedOption.dataset.informacoes;

                                        if (fixedItems.has(id)) {
                                            const it = fixedItems.get(id);
                                            const novaQtd = it.quantidade + quantidade;
                                            const estoque = parseInt(selectedOption.dataset.estoque);
                                            it.quantidade = (novaQtd > estoque) ? estoque : novaQtd;
                                            if (novaQtd > estoque) alert(`Quantidade total (${novaQtd}) excede o estoque (${estoque}). Ajustado para o máximo.`);
                                        } else {
                                            fixedItems.set(id, {
                                                id,
                                                nome,
                                                preco,
                                                quantidade,
                                                categoria,
                                                ncm,
                                                cest,
                                                cfop,
                                                origem,
                                                tributacao,
                                                unidade,
                                                informacoes
                                            });
                                        }

                                        updateFixedDisplay();
                                        finalizarVendaBtn.disabled = false;
                                        fixarBtn.disabled = true;
                                        selectedOption = null;
                                        multiSelect.selectedIndex = -1;
                                        quantidadeProdutoInput.value = 1;
                                        quantidadeContainer.style.display = 'none';
                                        if (formaPagamentoSelect.value === 'Dinheiro') calcularTroco();
                                    });

                                    function updateFixedDisplay() {
                                        fixedDisplay.innerHTML = '';
                                        if (fixedItems.size === 0) {
                                            fixedDisplay.textContent = 'Nenhum item fixado';
                                            totalDisplay.textContent = '0,00';
                                            document.getElementById('totalTotal').value = '0.00';
                                            finalizarVendaBtn.disabled = true;
                                            return;
                                        }
                                        fixedItems.forEach((item, id) => {
                                            const container = document.createElement('div');
                                            container.className = 'col-12 col-md-4';
                                            container.innerHTML = `
                                                                <div class="fixed-item">
                                                                    <div class="fixed-item-content">${item.nome} (${item.quantidade}x)</div>
                                                                    <div class="fixed-item-actions">
                                                                        <button class="edit-btn" data-id="${id}" type="button"><i class="bx bx-edit"></i></button>
                                                                        <button class="remove-btn" data-id="${id}" type="button">×</button>
                                                                    </div>
                                                                </div>`;
                                            const editBtn = container.querySelector('.edit-btn');
                                            const removeBtn = container.querySelector('.remove-btn');

                                            editBtn.addEventListener('click', (e) => {
                                                e.preventDefault();
                                                produtoEmEdicao = id;
                                                quantidadeEdicaoInput.value = fixedItems.get(id).quantidade;
                                                quantidadeEdicaoInput.max = getEstoqueMaximo(id);
                                                modalEdicao.style.display = 'flex';
                                                document.body.style.overflow = 'hidden';
                                            });

                                            removeBtn.addEventListener('click', (e) => {
                                                e.preventDefault();
                                                fixedItems.delete(id);
                                                updateFixedDisplay();
                                                if (formaPagamentoSelect.value === 'Dinheiro') calcularTroco();
                                            });

                                            fixedDisplay.appendChild(container);
                                        });
                                        calcularTotal();
                                    }

                                    // Modal edição
                                    const modalEdicao = document.getElementById('modalEdicao');
                                    const modalOverlay = document.querySelector('.modal-overlay');
                                    const quantidadeEdicaoInput = document.getElementById('quantidadeEdicao');
                                    const cancelarEdicaoBtn = document.getElementById('cancelarEdicao');
                                    const confirmarEdicaoBtn = document.getElementById('confirmarEdicao');
                                    let produtoEmEdicao = null;

                                    cancelarEdicaoBtn.addEventListener('click', () => {
                                        modalEdicao.style.display = 'none';
                                        document.body.style.overflow = 'auto';
                                        produtoEmEdicao = null;
                                    });
                                    modalOverlay.addEventListener('click', () => {
                                        modalEdicao.style.display = 'none';
                                        document.body.style.overflow = 'auto';
                                        produtoEmEdicao = null;
                                    });
                                    confirmarEdicaoBtn.addEventListener('click', () => {
                                        if (!produtoEmEdicao) return;
                                        const nova = parseInt(quantidadeEdicaoInput.value) || 1;
                                        const max = parseInt(quantidadeEdicaoInput.max);
                                        if (nova < 1) return alert('Quantidade mínima é 1');
                                        if (nova > max) return alert(`Quantidade máxima em estoque: ${max}`);
                                        const p = fixedItems.get(produtoEmEdicao);
                                        p.quantidade = nova;
                                        updateFixedDisplay();
                                        modalEdicao.style.display = 'none';
                                        document.body.style.overflow = 'auto';
                                        produtoEmEdicao = null;
                                        if (formaPagamentoSelect.value === 'Dinheiro') calcularTroco();
                                    });

                                    function getEstoqueMaximo(idProduto) {
                                        for (const cat in produtosPorCategoria) {
                                            const p = produtosPorCategoria[cat].find(x => x.id == idProduto);
                                            if (p) return p.quantidade;
                                        }
                                        return Infinity;
                                    }

                                    function calcularTotal() {
                                        let total = 0;
                                        fixedItems.forEach(item => {
                                            total += item.preco * item.quantidade;
                                        });
                                        totalDisplay.textContent = total.toFixed(2).replace('.', ',');
                                        document.getElementById('totalTotal').value = total.toFixed(2);
                                        if (formaPagamentoSelect.value === 'Dinheiro') calcularTroco();
                                    }

                                    removerTodosBtn.addEventListener('click', () => {
                                        if (fixedItems.size === 0) return alert('Nenhum produto para remover.');
                                        if (confirm('Deseja remover todos os produtos fixados?')) {
                                            fixedItems.clear();
                                            updateFixedDisplay();
                                            if (formaPagamentoSelect.value === 'Dinheiro') calcularTroco();
                                        }
                                    });

                                    // Envio
                                    form.addEventListener('submit', function(e) {
                                        e.preventDefault();

                                        // Normaliza CPFs (responsável/cliente)
                                        try {
                                            const elCli = document.getElementById('cpf_cliente');
                                            if (elCli) elCli.value = String(elCli.value || '').replace(/\D+/g, '').slice(0, 11);
                                        } catch (_) {}

                                        if (fixedItems.size === 0) return alert('Selecione ao menos um produto antes de finalizar.');
                                        if (formaPagamentoSelect.value === "") return alert('Selecione uma forma de pagamento.');

                                        if (formaPagamentoSelect.value === 'Dinheiro') {
                                            const total = parseFloat(document.getElementById('totalTotal').value);
                                            const recebido = parseFloat(valorRecebidoInput.value) || 0;
                                            if (recebido < total) return alert('O valor recebido não pode ser menor que o total.');
                                        }

                                        // Remove inputs antigos
                                        document.querySelectorAll('.input-produto-dinamico').forEach(i => i.remove());

                                        // Adiciona itens
                                        fixedItems.forEach((item) => {
                                            const addHidden = (name, value) => {
                                                const input = document.createElement('input');
                                                input.type = 'hidden';
                                                input.name = name;
                                                input.value = value;
                                                input.classList.add('input-produto-dinamico');
                                                form.appendChild(input);
                                            };
                                            addHidden('produtos[]', item.nome);
                                            addHidden('quantidades[]', item.quantidade);
                                            addHidden('precos[]', item.preco);
                                            addHidden('id_produto[]', item.id);
                                            addHidden('categorias[]', item.categoria);
                                            addHidden('ncms[]', item.ncm);
                                            addHidden('cests[]', item.cest);
                                            addHidden('cfops[]', item.cfop);
                                            addHidden('origens[]', item.origem);
                                            addHidden('tributacoes[]', item.tributacao);
                                            addHidden('unidades[]', item.unidade);
                                            addHidden('informacoes[]', item.informacoes);
                                        });

                                        // JSON redundante de itens
                                        try {
                                            const itensArr = [];
                                            fixedItems.forEach((it) => itensArr.push({
                                                produto_id: Number(it.id),
                                                qtd: Number(it.quantidade),
                                                vun: Number(it.preco)
                                            }));
                                            let itensInput = document.getElementById('itensJson');
                                            if (!itensInput) {
                                                itensInput = document.createElement('input');
                                                itensInput.type = 'hidden';
                                                itensInput.name = 'itens';
                                                itensInput.id = 'itensJson';
                                                form.appendChild(itensInput);
                                            }
                                            itensInput.value = JSON.stringify(itensArr);
                                        } catch (e) {
                                            console.warn('Falha ao montar JSON itens', e);
                                        }

                                        document.getElementById('data_registro').value = new Date().toISOString();

                                        // trava botão
                                        finalizarVendaBtn.disabled = true;
                                        finalizarVendaBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processando...';

                                        form.submit();
                                    });

                                    // ============================
                                    // 🔎 NOVO: Busca com sugestões
                                    // ============================
                                    let idxAtivo = -1; // índice do item ativo (teclado)
                                    let itensAtuais = []; // último resultado renderizado

                                    const abrirSugestoes = () => {
                                        listaSugestoes.style.display = 'block';
                                    };
                                    const fecharSugestoes = () => {
                                        listaSugestoes.style.display = 'none';
                                        listaSugestoes.innerHTML = '';
                                        idxAtivo = -1;
                                        itensAtuais = [];
                                    };

                                    function renderSugestoes(lista) {
                                        itensAtuais = lista;
                                        if (!lista.length) {
                                            fecharSugestoes();
                                            return;
                                        }
                                        const html = lista.map((p, i) => `
                                                            <div class="item${i===0?' ativo':''}" data-id="${p.id}">
                                                                <div class="info">
                                                                    <span class="titulo">${p.nome}</span>
                                                                    <span class="sub">Cód: ${p.codigo || '-'} • Cat: ${p.categoria || '-'} • Estoque: ${p.quantidade}</span>
                                                                </div>
                                                                <span class="tag">R$ ${Number(p.preco).toFixed(2).replace('.', ',')}</span>
                                                            </div>
                                                        `).join('');
                                        listaSugestoes.innerHTML = html;
                                        abrirSugestoes();
                                        idxAtivo = 0;
                                    }

                                    function moverAtivo(delta) {
                                        if (!itensAtuais.length) return;
                                        const items = listaSugestoes.querySelectorAll('.item');
                                        items[idxAtivo]?.classList.remove('ativo');
                                        idxAtivo = (idxAtivo + delta + items.length) % items.length;
                                        items[idxAtivo]?.classList.add('ativo');
                                        // scroll into view
                                        const el = items[idxAtivo];
                                        const cont = listaSugestoes;
                                        if (el.offsetTop < cont.scrollTop) cont.scrollTop = el.offsetTop;
                                        else if (el.offsetTop + el.offsetHeight > cont.scrollTop + cont.clientHeight)
                                            cont.scrollTop = el.offsetTop - cont.clientHeight + el.offsetHeight;
                                    }

                                    function selecionarProdutoPorId(id) {
                                        const prod = todosProdutos.find(p => String(p.id) === String(id));
                                        if (!prod) return;

                                        // 1) Seleciona a categoria
                                        categoriaSelect.value = prod.categoria || '';
                                        categoriaSelect.dispatchEvent(new Event('change'));

                                        // 2) Seleciona o produto no select após carregar a lista
                                        setTimeout(() => {
                                            let foundIndex = -1;
                                            for (let i = 0; i < multiSelect.options.length; i++) {
                                                if (String(multiSelect.options[i].value) === String(prod.id)) {
                                                    foundIndex = i;
                                                    break;
                                                }
                                            }
                                            if (foundIndex >= 0) {
                                                multiSelect.selectedIndex = foundIndex;
                                                selectedOption = multiSelect.options[foundIndex];
                                                quantidadeContainer.style.display = 'block';
                                                const estoqueDisponivel = parseInt(selectedOption.dataset.estoque);
                                                quantidadeProdutoInput.max = isFinite(estoqueDisponivel) ? estoqueDisponivel : 999999;
                                                quantidadeProdutoInput.value = 1;
                                                fixarBtn.disabled = formaPagamentoSelect.value === "";
                                                buscaInput.value = prod.nome; // preenche o campo com a seleção
                                            }
                                        }, 0);

                                        fecharSugestoes();
                                    }

                                    // Clique em item da lista
                                    listaSugestoes.addEventListener('click', (e) => {
                                        const item = e.target.closest('.item');
                                        if (!item) return;
                                        selecionarProdutoPorId(item.dataset.id);
                                    });

                                    // Eventos do campo de busca
                                    buscaInput.addEventListener('input', () => {
                                        const q = norm(buscaInput.value);
                                        if (q.length < 2) {
                                            fecharSugestoes();
                                            return;
                                        }
                                        const results = todosProdutos.filter(p =>
                                            norm(p.nome).includes(q) || norm(p.codigo).includes(q)
                                        ).slice(0, 20);
                                        renderSugestoes(results);
                                    });

                                    buscaInput.addEventListener('keydown', (e) => {
                                        if (listaSugestoes.style.display !== 'block') return;
                                        if (e.key === 'ArrowDown') {
                                            e.preventDefault();
                                            moverAtivo(1);
                                        } else if (e.key === 'ArrowUp') {
                                            e.preventDefault();
                                            moverAtivo(-1);
                                        } else if (e.key === 'Enter') {
                                            e.preventDefault();
                                            const ativo = listaSugestoes.querySelectorAll('.item')[idxAtivo];
                                            if (ativo) selecionarProdutoPorId(ativo.dataset.id);
                                        } else if (e.key === 'Escape') {
                                            fecharSugestoes();
                                        }
                                    });

                                    // Fecha ao clicar fora
                                    document.addEventListener('click', (e) => {
                                        if (!listaSugestoes.contains(e.target) && e.target !== buscaInput) fecharSugestoes();
                                    });
                                </script>
                                <!-- /JS do formulário -->
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
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
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

// ✅ Compat: str_starts_with (PHP < 8)
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        return $needle !== '' && strpos((string)$haystack, (string)$needle) === 0;
    }
}

// ✅ Helper para escapar HTML
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// =====================
//   USUÁRIO LOGADO
// =====================
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id  = (int)($_SESSION['usuario_id'] ?? 0);

try {
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $nomeUsuario = $usuario['usuario'];
        $tipoUsuario = ucfirst($usuario['nivel']);
    } else {
        echo "<script>alert('Usuário não encontrado.'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar usuário: " . h($e->getMessage()) . "'); history.back();</script>";
    exit;
}

// ================================
//   VALIDAÇÃO DO ACESSO
// ================================
$acessoPermitido  = false;
$idEmpresaSession = $_SESSION['empresa_id'] ?? '';
$tipoSession      = $_SESSION['tipo_empresa'] ?? '';

if ($idEmpresaSession === $idSelecionado) {
    $acessoPermitido = true;
} elseif (str_starts_with($idSelecionado, 'principal_')) {
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
//   DADOS DA EMPRESA
// =====================
try {
    $stmt = $pdo->prepare("SELECT * FROM sobre_empresa WHERE id_selecionado = :idSelecionado LIMIT 1");
    $stmt->bindParam(':idSelecionado', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

    $nome_empresa   = $empresa['nome_empresa']   ?? '';
    $sobre_empresa  = $empresa['sobre_empresa']  ?? '';
    $imagem_empresa = $empresa['imagem']         ?? 'logo.png';

    $logoEmpresa = !empty($empresa['imagem'])
        ? "../../assets/img/empresa/" . $empresa['imagem']
        : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar os dados da empresa: " . h($e->getMessage()) . "'); history.back();</script>";
    exit;
}

// =====================
//   ID DA ENTREGA
// =====================
// 1) Tenta vir via GET (ex.: taxaEntrega.php?id=...&idEntrega=123)
$id_entrega = isset($_GET['idEntrega']) ? (int)$_GET['idEntrega'] : 0;

// 2) Se não veio, tenta buscar na tabela 'entregas' pela empresa.
// 3) Se ainda não existir, cria um registro padrão e usa o ID novo.
try {
    if ($id_entrega <= 0) {
        $stmt = $pdo->prepare("SELECT id_entrega FROM entregas WHERE id_empresa = :id_emp LIMIT 1");
        $stmt->bindValue(':id_emp', $idSelecionado, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $id_entrega = (int)$row['id_entrega'];
        } else {
            // Cria registro padrão de entrega para a empresa
            $ins = $pdo->prepare("INSERT INTO entregas (id_empresa, entrega, tempo_min, tempo_max) VALUES (:id_emp, 0, 0, 0)");
            $ins->bindValue(':id_emp', $idSelecionado, PDO::PARAM_STR);
            $ins->execute();
            $id_entrega = (int)$pdo->lastInsertId();
        }
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao buscar/criar entrega: " . h($e->getMessage()) . "'); history.back();</script>";
    exit;
}

// =====================
//   NÍVEL DO USUÁRIO (exibição)
// =====================
$nivelUsuario = 'Comum';
try {
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($usuario) {
        $nomeUsuario  = $usuario['usuario'];
        $nivelUsuario = $usuario['nivel'];
    }
} catch (PDOException $e) {
    $nomeUsuario  = 'Erro ao carregar nome';
    $nivelUsuario = 'Erro ao carregar nível';
}

// =====================
//   BUSCA CONFIG TAXAS
// =====================
$precoTaxaUnica = '';
$taxa_unica_db  = 0;
$sem_taxa       = 0;
$result         = [];
$taxas          = [];

try {
    // entrega_taxas (chave: id_entrega + idSelecionado)
    $sql = "SELECT * FROM entrega_taxas WHERE id_entrega = :id_entrega AND idSelecionado = :idSelecionado LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_entrega', $id_entrega, PDO::PARAM_INT);
    $stmt->bindParam(':idSelecionado', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $taxa_unica_db = (int)$result['taxa_unica'];
        $sem_taxa      = (int)$result['sem_taxa'];
    }

    // entrega_taxas_unica (chave: id_entrega + id_selecionado)
    $sqlUnica = "SELECT * FROM entrega_taxas_unica WHERE id_entrega = :id_entrega AND id_selecionado = :idSelecionado";
    $stmtUnica = $pdo->prepare($sqlUnica);
    $stmtUnica->bindParam(':id_entrega', $id_entrega, PDO::PARAM_INT);
    $stmtUnica->bindParam(':idSelecionado', $idSelecionado, PDO::PARAM_STR);
    $stmtUnica->execute();
    $taxas = $stmtUnica->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($taxas)) {
        $precoTaxaUnica = $taxas[0]['valor_taxa'];
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar as taxas: " . addslashes($e->getMessage()) . "');</script>";
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
    <link rel="icon" type="image/x-icon" href="<?= h($logoEmpresa) ?>" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
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
                        <span class="app-brand-text demo menu-text fw-bolder ms-2" style=" text-transform: capitalize;">Açaínhadinhos</span>
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
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons tf-icons bx bx-food-menu"></i>
                            <div data-i18n="Authentications">Cardápio</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item ">
                                <a href="./produtoAdicionados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Produtos Adicionados</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons tf-icons bx bx-cog"></i>
                            <div data-i18n="Authentications">Configuração</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./deliveryRetirada.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Delivery e Retirada</div>
                                </a>
                            </li>
                        </ul>
                        <ul class="menu-sub">
                            <li class="menu-item active">
                                <a href="./taxaEntrega.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Taxa de Entrega</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons tf-icons bx  bx-trending-up"></i>
                            <div data-i18n="Authentications">Relatorios</div>
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
                            <div data-i18n="Authentications">Usuários </div>
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
                    </div>
                </nav>
                <!-- / Navbar -->

                <!-- Content -->
                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold mb-0">
                        <span class="text-muted fw-light"><a href="./deliveryRetirada.php?id=<?= urlencode($idSelecionado); ?>">Configuração</a>/</span>
                        Taxa de Entrega
                    </h4>

                    <h5 class="fw-bold mt-3 mb-3 custor-font">
                        <span class="text-muted fw-light">Selecione as opções de taxas de entrega</span>
                    </h5>

                    <div class="row">
                        <div class="col-12">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <div class="row mb-0 text-md-start">
                                        <!-- Form switches Sem Taxa / Taxa Única -->
                                        <form action="../../assets/php/delivery/adicionarTaxas.php" method="POST" id="taxaForm">
                                            <input type="hidden" name="id_entrega" value="<?= (int)$id_entrega; ?>">
                                            <input type="hidden" name="idSelecionado" value="<?= h($idSelecionado); ?>">

                                            <!-- Sem Taxa -->
                                            <div class="col-12 col-md-12 check-card">
                                                <div class="d-flex align-items-center">
                                                    <div><strong>Sem Taxa</strong></div>
                                                    <div class="d-flex align-items-center ms-auto">
                                                        <strong id="labelSemTaxa" class="me-2">
                                                            <?= (isset($result['sem_taxa']) && (int)$result['sem_taxa'] === 1) ? 'Ligado' : 'Desligado'; ?>
                                                        </strong>
                                                        <div class="form-check form-switch mt-2">
                                                            <input type="hidden" name="sem_taxa" value="0">
                                                            <input class="form-check-input check w-px-50 h-px-20"
                                                                type="checkbox" id="toggleSemTaxa" name="sem_taxa" value="1"
                                                                <?= (isset($result['sem_taxa']) && (int)$result['sem_taxa'] === 1) ? 'checked' : ''; ?>
                                                                onchange="toggleTaxas('sem_taxa')">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Taxa Única -->
                                            <div class="col-12 col-md-12 check-card mt-2">
                                                <div class="d-flex align-items-center">
                                                    <div><strong>Taxa Única</strong></div>
                                                    <div class="d-flex align-items-center ms-auto">
                                                        <strong id="labelToggleTaxa" class="me-2">
                                                            <?= (isset($result['taxa_unica']) && (int)$result['taxa_unica'] === 1) ? 'Ligado' : 'Desligado'; ?>
                                                        </strong>
                                                        <div class="form-check form-switch mt-2">
                                                            <input type="hidden" name="taxa_unica" value="0">
                                                            <input class="form-check-input check w-px-50 h-px-20"
                                                                type="checkbox" id="toggleTaxa" name="taxa_unica" value="1"
                                                                <?= (isset($result['taxa_unica']) && (int)$result['taxa_unica'] === 1) ? 'checked' : ''; ?>
                                                                onchange="toggleTaxas('taxa_unica')">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>

                                        <script>
                                            function toggleTaxas(checkbox) {
                                                if (checkbox === 'sem_taxa') {
                                                    document.getElementById('toggleTaxa').checked = false;
                                                }
                                                if (checkbox === 'taxa_unica') {
                                                    document.getElementById('toggleSemTaxa').checked = false;
                                                }
                                                document.getElementById('taxaForm').submit();
                                            }
                                        </script>
                                    </div>
                                </div>
                            </div>

                            <?php if ((int)$taxa_unica_db === 1): ?>
                                <!-- Form de Taxa Única -->
                                <form action="../../assets/php/delivery/adicionarTaxaUnica.php" method="POST">
                                    <input type="hidden" name="id_entrega" value="<?= (int)$id_entrega; ?>">
                                    <input type="hidden" name="idSelecionado" value="<?= h($idSelecionado); ?>">

                                    <div class="card mb-0">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-12 col-md-12">
                                                    <label class="form-label">Valor da Taxa</label>
                                                    <div class="input-group input-group-merge mb-3">
                                                        <span class="input-group-text">R$</span>
                                                        <input type="text" name="precoTaxaUnica" class="form-control"
                                                            placeholder="00"
                                                            aria-label="Amount (to the nearest dollar)"
                                                            value="<?= ($precoTaxaUnica !== '' ? number_format((float)$precoTaxaUnica, 2, ',', '.') : ''); ?>">
                                                    </div>
                                                    <button type="submit" class="btn col-12 btn-primary">
                                                        <i class="bx bx-plus"></i> Adicionar Taxa
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>

                                <!-- LISTA DE TAXAS -->
                                <h5 class="py-3 mt-2 mb-2 custor-font">
                                    <span class="text-muted fw-light">Lista de Taxa</span>
                                </h5>

                                <div class="card">
                                    <h5 class="card-header">Lista</h5>
                                    <div class="table-responsive text-nowrap">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Valor da Taxa</th>
                                                    <th>Data</th>
                                                    <th>Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody class="table-border-bottom-0">
                                                <?php if (count($taxas) > 0): ?>
                                                    <?php foreach ($taxas as $taxa): ?>
                                                        <tr>
                                                            <td>R$ <?= number_format((float)$taxa['valor_taxa'], 2, ',', '.'); ?></td>
                                                            <td><?= date('d/m/Y', strtotime($taxa['created_at'])); ?></td>
                                                            <td>
                                                                <button class="btn btn-link text-danger p-0" title="Excluir"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#deleteTaxModal_<?= (int)$taxa['id']; ?>">
                                                                    <i class="tf-icons bx bx-trash"></i>
                                                                </button>

                                                                <!-- Modal de Exclusão -->
                                                                <div class="modal fade" id="deleteTaxModal_<?= (int)$taxa['id']; ?>" tabindex="-1" aria-labelledby="deleteTaxModalLabel" aria-hidden="true">
                                                                    <div class="modal-dialog">
                                                                        <div class="modal-content">
                                                                            <div class="modal-header">
                                                                                <h5 class="modal-title" id="deleteTaxModalLabel">Excluir Taxa</h5>
                                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                            </div>
                                                                            <div class="modal-body">
                                                                                <p>Tem certeza de que deseja excluir esta taxa?</p>
                                                                                <a href="../../assets/php/delivery/excluirTaxaUnica.php?id_taxa=<?= (int)$taxa['id']; ?>&id_entrega=<?= (int)$taxa['id_entrega']; ?>&idSelecionado=<?= urlencode($idSelecionado); ?>"
                                                                                    class="btn btn-danger">Excluir</a>
                                                                                <button type="button" class="btn btn-secondary mx-2" data-bs-dismiss="modal">Cancelar</button>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="3">Nenhuma taxa cadastrada.</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
                <!-- / Content -->
            </div>
        </div>
    </div>

    <!-- Core JS -->
    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../assets/vendor/js/menu.js"></script>

    <!-- Vendors JS -->
    <script src="../../assets/js/delivery/taxaEntrega.js"></script>
    <script src="../../assets/vendor/libs/apex-charts/apexcharts.js"></script>

    <!-- Main JS -->
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/dashboards-analytics.js"></script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>
</body>

</html>
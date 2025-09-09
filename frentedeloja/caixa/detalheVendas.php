<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// ======================== PARÂMETROS BÁSICOS ========================
$idSelecionado = $_GET['id'] ?? '';

if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id']) ||
    !isset($_SESSION['nivel'])
) {
    header("Location: ../index.php?id=" . urlencode($idSelecionado));
    exit;
}

require '../../assets/php/conexao.php';

// Helpers
function soDigitos(string $v): string
{
    return preg_replace('/\D+/', '', $v) ?? '';
}
function moeda($v): string
{
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}

// Sessão
$usuario_id        = (int)$_SESSION['usuario_id'];
$tipoUsuarioSessao = $_SESSION['nivel']; // Admin/Comum
$cpfUsuario        = soDigitos((string)($_SESSION['usuario_cpf'] ?? ($_SESSION['cpf'] ?? '')));

// id_caixa da URL: ?chave= ou ?id_caixa=
$idCaixa = isset($_GET['chave']) ? (int)$_GET['chave'] : (int)($_GET['id_caixa'] ?? 0);

// ======================== CARREGA USUÁRIO ==========================
try {
    if ($tipoUsuarioSessao === 'Admin') {
        $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    } else {
        $stmt = $pdo->prepare("SELECT usuario, nivel FROM funcionarios_acesso WHERE id = :id");
    }
    $stmt->execute([':id' => $usuario_id]);
    $usr = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$usr) {
        echo "<script>alert('Usuário não encontrado.'); location.href='./index.php?id=" . htmlspecialchars($idSelecionado, ENT_QUOTES) . "';</script>";
        exit;
    }
    $nomeUsuario = $usr['usuario'];
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar usuário: " . addslashes($e->getMessage()) . "'); history.back();</script>";
    exit;
}

// ======================== VALIDA EMPRESA ===========================
if (str_starts_with($idSelecionado, 'principal_')) {
    if ($_SESSION['tipo_empresa'] !== 'principal' && !($tipoUsuarioSessao === 'Admin' && $_SESSION['empresa_id'] === 'principal_1')) {
        echo "<script>alert('Acesso negado!'); location.href='../index.php?id=" . htmlspecialchars($idSelecionado, ENT_QUOTES) . "';</script>";
        exit;
    }
} elseif (str_starts_with($idSelecionado, 'unidade_')) {
    $acessoPermitido = ($_SESSION['empresa_id'] === $idSelecionado) || ($tipoUsuarioSessao === 'Admin' && $_SESSION['empresa_id'] === 'principal_1');
    if (!$acessoPermitido) {
        echo "<script>alert('Acesso negado!'); location.href='../index.php?id=" . htmlspecialchars($idSelecionado, ENT_QUOTES) . "';</script>";
        exit;
    }
} else {
    echo "<script>alert('Empresa não identificada!'); location.href='../index.php?id=" . htmlspecialchars($idSelecionado, ENT_QUOTES) . "';</script>";
    exit;
}

// ======================== ICONE EMPRESA (opcional) =================
$iconeEmpresa = '../../assets/img/favicon/favicon.ico';
try {
    $s = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1");
    $s->execute([':id' => $idSelecionado]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['imagem'])) $iconeEmpresa = $row['imagem'];
} catch (Throwable $e) {
    // silencioso
}

// ======================== DEDUZ id_caixa, SE NÃO VEIO ============
if ($idCaixa <= 0) {
    try {
        $q = "SELECT id_caixa
            FROM vendas
           WHERE empresa_id = :emp
             " . ($cpfUsuario ? "AND REPLACE(REPLACE(REPLACE(REPLACE(cpf_responsavel,'.',''),'-',''),'/',''),' ','') = :cpf" : "") . "
           ORDER BY data_venda DESC
           LIMIT 1";
        $st = $pdo->prepare($q);
        $bind = [':emp' => $idSelecionado];
        if ($cpfUsuario) $bind[':cpf'] = $cpfUsuario;
        $st->execute($bind);
        $x = $st->fetch(PDO::FETCH_ASSOC);
        if ($x && !empty($x['id_caixa'])) $idCaixa = (int)$x['id_caixa'];
    } catch (Throwable $e) {
    }
}

// Se mesmo assim não tiver id_caixa, não há o que detalhar
if ($idCaixa <= 0) {
?>
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="alert alert-warning text-center">
            Nenhum caixa identificado para detalhar. Volte ao <a href="./relatorioVendas.php?id=<?= urlencode($idSelecionado) ?>">Resumo de Vendas</a>.
        </div>
    </div>
<?php
    exit;
}

// ======================== BUSCAS PRINCIPAIS =======================
$produtosVendas   = [];
$totalVendas      = 0.0;
$sangrias         = [];
$totalSangrias    = 0.0;
$suprimentos      = [];
$totalSuprimentos = 0.0;

try {
    // Itens (JOIN com vendas; LEFT JOIN estoque para categoria/unidade)
    $sqlItens = "
    SELECT 
      iv.id,
      iv.venda_id,
      iv.produto_id,
      iv.produto_nome,
      iv.quantidade,
      iv.preco_unitario,
      (iv.quantidade * iv.preco_unitario) AS item_total,
      e.categoria_produto,
      e.unidade,
      v.forma_pagamento,
      v.data_venda,
      v.valor_total AS venda_total,
      v.status_nfce,
      v.chave_nfce
    FROM itens_venda iv
    JOIN vendas v       ON v.id = iv.venda_id
    LEFT JOIN estoque e ON e.id = iv.produto_id
    WHERE v.empresa_id = :emp
      AND v.id_caixa   = :caixa
      " . ($cpfUsuario ? "AND REPLACE(REPLACE(REPLACE(REPLACE(v.cpf_responsavel,'.',''),'-',''),'/',''),' ','') = :cpf" : "") . "
    ORDER BY v.data_venda DESC, iv.id ASC
  ";
    $st = $pdo->prepare($sqlItens);
    $bind = [':emp' => $idSelecionado, ':caixa' => $idCaixa];
    if ($cpfUsuario) $bind[':cpf'] = $cpfUsuario;
    $st->execute($bind);
    $produtosVendas = $st->fetchAll(PDO::FETCH_ASSOC);

    // Total das vendas do caixa
    $sqlTot = "
    SELECT COALESCE(SUM(valor_total),0) AS total_vendas
      FROM vendas
     WHERE empresa_id = :emp
       AND id_caixa   = :caixa
       " . ($cpfUsuario ? "AND REPLACE(REPLACE(REPLACE(REPLACE(cpf_responsavel,'.',''),'-',''),'/',''),' ','') = :cpf" : "") . "
  ";
    $st = $pdo->prepare($sqlTot);
    $st->execute($bind);
    $totalVendas = (float)($st->fetch(PDO::FETCH_ASSOC)['total_vendas'] ?? 0);

    // Sangrias
    $sqlS = "
    SELECT valor, valor_liquido, data_registro
      FROM sangrias
     WHERE empresa_id = :emp
       AND id_caixa   = :caixa
       " . ($cpfUsuario ? "AND REPLACE(REPLACE(REPLACE(REPLACE(cpf_responsavel,'.',''),'-',''),'/',''),' ','') = :cpf" : "") . "
     ORDER BY data_registro DESC
  ";
    $st = $pdo->prepare($sqlS);
    $st->execute($bind);
    $sangrias = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($sangrias as $s) $totalSangrias += (float)$s['valor'];

    // Suprimentos
    $sqlSup = "
    SELECT valor_suprimento, valor_liquido, data_registro
      FROM suprimentos
     WHERE empresa_id = :emp
       AND id_caixa   = :caixa
       " . ($cpfUsuario ? "AND REPLACE(REPLACE(REPLACE(REPLACE(cpf_responsavel,'.',''),'-',''),'/',''),' ','') = :cpf" : "") . "
     ORDER BY data_registro DESC
  ";
    $st = $pdo->prepare($sqlSup);
    $st->execute($bind);
    $suprimentos = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($suprimentos as $sp) $totalSuprimentos += (float)$sp['valor_suprimento'];
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar dados: " . addslashes($e->getMessage()) . "');</script>";
}

// ======================== DATA DO TÍTULO ==========================
$dataRelatorio = null;
if (!empty($produtosVendas)) {
    $dataRelatorio = (new DateTime($produtosVendas[0]['data_venda']))->format('Y-m-d');
} elseif (!empty($sangrias)) {
    $dataRelatorio = (new DateTime($sangrias[0]['data_registro']))->format('Y-m-d');
} elseif (!empty($suprimentos)) {
    $dataRelatorio = (new DateTime($suprimentos[0]['data_registro']))->format('Y-m-d');
}
$dtTitulo = $dataRelatorio ? date('d/m/Y', strtotime($dataRelatorio)) : '—';

// ======================== URL DANFE (iframe) ======================
$DANFE_BASE = './danfe_nfce.php';
$DANFE_BASE_ABS = (string)(new \SplFileInfo(__DIR__ . '/danfe_nfce.php'))->getBasename(); // apenas nome

?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style customizer-hide" dir="ltr" data-theme="theme-default"
    data-assets-path="../assets/" data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <!-- Favicon da empresa carregado dinamicamente -->
    <link rel="icon" type="image/x-icon"
        href="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>" />

    <title>ERP - PDV</title>

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
    <link href="https://cdn.jsdelivr.net/npm/boxicons/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">


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
                    <li class="menu-item  ">
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
                            <li class="menu-item ">
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
                    <li class="menu-item">
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
                    <li class="menu-item active open">
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
                            <li class="menu-item active">
                                <a href="./relatorioVendas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Detalhes da Venda</div>
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
                            <div data-i18n="Authentications">SIstema de Ponto</div>
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
                                            <span class="align-middle">My Profile</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <i class="bx bx-cog me-2"></i>
                                            <span class="align-middle">Settings</span>
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


                <!-- Content wrapper -->
                <div class="container-xxl flex-grow-1 container-p-y">

                    <h4 class="fw-bold mb-0">
                        <span class="text-muted fw-light">
                            <a href="./relatorioVendas.php?id=<?= urlencode($idSelecionado) ?>">Relatório</a> /
                        </span>
                        Detalhes de Vendas — <?= htmlspecialchars($dtTitulo) ?>
                    </h4>
                    <h5 class="fw-semibold mt-2 mb-4 text-muted">
                        Empresa: <span class="text-primary"><?= htmlspecialchars($idSelecionado) ?></span> • Caixa: <span class="text-primary">#<?= (int)$idCaixa ?></span>
                    </h5>

                    <!-- Cards -->
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3">
                            <div class="card text-center h-100">
                                <div class="card-body">
                                    <img src="../../assets/img/icons/unicons/chart-success.png" alt="Total Vendas" style="width:32px" class="mb-2">
                                    <div class="fw-semibold">Total de Vendas</div>
                                    <h4 class="mb-1"><?= moeda($totalVendas) ?></h4>
                                    <small class="text-muted">Caixa #<?= (int)$idCaixa ?></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card text-center h-100">
                                <div class="card-body">
                                    <img src="../../assets/img/icons/unicons/wallet-info.png" alt="Sangrias" style="width:32px" class="mb-2">
                                    <div class="fw-semibold">Total de Sangrias</div>
                                    <h4 class="mb-1"><?= moeda($totalSangrias) ?></h4>
                                    <small class="text-muted">Saídas de dinheiro</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card text-center h-100">
                                <div class="card-body">
                                    <img src="../../assets/img/icons/unicons/cc-primary.png" alt="Suprimentos" style="width:32px" class="mb-2">
                                    <div class="fw-semibold">Total de Suprimentos</div>
                                    <h4 class="mb-1"><?= moeda($totalSuprimentos) ?></h4>
                                    <small class="text-muted">Entradas no caixa</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabela: Itens vendidos -->
                    <div class="row">
                        <div class="col-lg-12 mb-4 order-0">
                            <div class="card">
                                <h5 class="card-header d-flex align-items-center justify-content-between">
                                    <span>Itens Vendidos</span>
                                    <a class="btn btn-secondary btn-sm" href="./relatorioVendas.php?id=<?= urlencode($idSelecionado) ?>">← Voltar ao Resumo</a>
                                </h5>
                                <div class="table-responsive text-nowrap">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID Venda</th>
                                                <th>Produto</th>
                                                <th>Categoria</th>
                                                <th>Un.</th>
                                                <th>Qtde</th>
                                                <th>V. Unitário</th>
                                                <th>V. Total (Item)</th>
                                                <th>Forma pgto.</th>
                                                <th>Data/Hora</th>
                                                <th>NFC-e</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($produtosVendas)): ?>
                                                <tr>
                                                    <td colspan="11" class="text-center py-4">Nenhum item vendido encontrado para este caixa.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($produtosVendas as $it):
                                                    $dt = new DateTime($it['data_venda']);
                                                    $status = $it['status_nfce'] ?: '—';
                                                    $chave  = $it['chave_nfce'] ?: '';
                                                    $temDanfe = !empty($chave) && in_array(strtolower($status), ['autorizada', '100', 'autorizado', 'aprovada'], true);

                                                    // URL do DANFE pronta para prefetch/uso
                                                    $urlDanfe = $DANFE_BASE . '?id=' . urlencode($idSelecionado) . '&venda_id=' . (int)$it['venda_id'] . '&chave=' . urlencode($chave);
                                                ?>
                                                    <tr>
                                                        <td>#<?= (int)$it['venda_id'] ?></td>
                                                        <td><?= htmlspecialchars($it['produto_nome']) ?></td>
                                                        <td><?= htmlspecialchars($it['categoria_produto'] ?? '—') ?></td>
                                                        <td><?= htmlspecialchars($it['unidade'] ?? 'UN') ?></td>
                                                        <td><?= (int)$it['quantidade'] ?></td>
                                                        <td><?= moeda($it['preco_unitario']) ?></td>
                                                        <td><?= moeda($it['item_total']) ?></td>
                                                        <td><?= htmlspecialchars($it['forma_pagamento']) ?></td>
                                                        <td><?= $dt->format('d/m/Y H:i') ?></td>
                                                        <td title="<?= htmlspecialchars($chave) ?>">
                                                            <span class="badge <?= $temDanfe ? 'bg-success' : 'bg-secondary' ?>">
                                                                <?= $temDanfe ? 'Autorizada' : htmlspecialchars($status) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if ($temDanfe): ?>
                                                                <button
                                                                    type="button"
                                                                    class="btn btn-outline-primary btn-sm ver-danfe"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#modalDanfe"
                                                                    data-url="<?= htmlspecialchars($urlDanfe) ?>"
                                                                    data-chave="<?= htmlspecialchars($chave) ?>"
                                                                    data-venda="<?= (int)$it['venda_id'] ?>"
                                                                    data-empresa="<?= htmlspecialchars($idSelecionado) ?>">
                                                                    Ver DANFE
                                                                </button>
                                                                <!-- Prefetch hint -->
                                                                <link rel="prefetch" href="<?= htmlspecialchars($urlDanfe) ?>" as="document">
                                                            <?php else: ?>
                                                                <button type="button" class="btn btn-outline-secondary btn-sm" disabled>Sem DANFE</button>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <tr>
                                                    <td colspan="6" class="text-end fw-bold">Total Geral de Vendas (Caixa)</td>
                                                    <td class="fw-bold"><?= moeda($totalVendas) ?></td>
                                                    <td colspan="4"></td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabela: Sangrias -->
                    <div class="row">
                        <div class="col-lg-12 mb-4 order-0">
                            <div class="card">
                                <h5 class="card-header">Sangrias</h5>
                                <div class="table-responsive text-nowrap">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Data</th>
                                                <th>Hora</th>
                                                <th>Saldo no Caixa (antes)</th>
                                                <th>Valor da Retirada</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($sangrias)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center py-3">Nenhuma sangria registrada neste caixa.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($sangrias as $s):
                                                    $d = new DateTime($s['data_registro']);
                                                ?>
                                                    <tr>
                                                        <td><?= $d->format('d/m/Y') ?></td>
                                                        <td><?= $d->format('H:i') ?></td>
                                                        <td><?= moeda($s['valor_liquido']) ?></td>
                                                        <td><?= moeda($s['valor']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <tr>
                                                    <td colspan="3" class="text-end fw-bold">Total</td>
                                                    <td class="fw-bold"><?= moeda($totalSangrias) ?></td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabela: Suprimentos -->
                    <div class="row">
                        <div class="col-lg-12 mb-4 order-0">
                            <div class="card">
                                <h5 class="card-header">Suprimentos</h5>
                                <div class="table-responsive text-nowrap">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Data</th>
                                                <th>Hora</th>
                                                <th>Saldo no Caixa (antes)</th>
                                                <th>Valor da Entrada</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($suprimentos)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center py-3">Nenhum suprimento registrado neste caixa.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($suprimentos as $sp):
                                                    $d = new DateTime($sp['data_registro']);
                                                ?>
                                                    <tr>
                                                        <td><?= $d->format('d/m/Y') ?></td>
                                                        <td><?= $d->format('H:i') ?></td>
                                                        <td><?= moeda($sp['valor_liquido']) ?></td>
                                                        <td><?= moeda($sp['valor_suprimento']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <tr>
                                                    <td colspan="3" class="text-end fw-bold">Total</td>
                                                    <td class="fw-bold"><?= moeda($totalSuprimentos) ?></td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- /Content wrapper -->

                <!-- MODAL DANFE -->
                <div class="modal fade" id="modalDanfe" tabindex="-1" aria-labelledby="modalDanfeLabel" aria-hidden="true">
                    <div class="modal-dialog modal-fullscreen-lg-down modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="modalDanfeLabel">DANFE NFC-e</h5>
                                <button type="button" class="btn btn-sm btn-outline-primary me-2" id="btnAbrirNovaGuia" style="display:none">Abrir em nova guia</button>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                            </div>
                            <div class="modal-body p-0" style="min-height:70vh; position:relative">
                                <div id="danfeLoader" class="d-flex align-items-center justify-content-center w-100 h-100" style="position:absolute; inset:0; background:#fff;">
                                    <div class="text-center">
                                        <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                                        <div class="mt-2">Carregando DANFE…</div>
                                    </div>
                                </div>
                                <iframe id="danfeFrame" src="about:blank" title="DANFE NFC-e" style="border:0; width:100%; height:70vh" loading="lazy" referrerpolicy="no-referrer"></iframe>
                            </div>
                            <div class="modal-footer">
                                <small class="text-muted me-auto" id="danfeInfo"></small>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Boost de performance do DANFE -->
                <script>
                    // Pré-carrega e cacheia HTML do DANFE e injeta via srcdoc (instantâneo)
                    (function() {
                        const danfeModal = document.getElementById('modalDanfe');
                        const danfeFrame = document.getElementById('danfeFrame');
                        const danfeLoader = document.getElementById('danfeLoader');
                        const danfeInfo = document.getElementById('danfeInfo');
                        const btnNovaGuia = document.getElementById('btnAbrirNovaGuia');

                        const danfeCache = new Map(); // url -> html
                        const queue = []; // URLs para prefetch
                        const MAX_PREFETCH = 4; // quantos prefetch por página

                        // Constrói <base> para corrigir URLs relativas quando usar srcdoc
                        function wrapWithBase(html, baseHref) {
                            const hasHead = /<head[^>]*>/i.test(html);
                            const baseTag = `<base href="${baseHref}">`;
                            if (hasHead) return html.replace(/<head[^>]*>/i, m => m + baseTag);
                            return `<!doctype html><html><head>${baseTag}</head><body>${html}</body></html>`;
                        }

                        function absoluteBaseFor(url) {
                            try {
                                const u = new URL(url, window.location.href);
                                // base = diretório do recurso
                                u.pathname = u.pathname.split('/').slice(0, -1).join('/') + '/';
                                u.search = '';
                                u.hash = '';
                                return u.toString();
                            } catch {
                                return window.location.origin + '/';
                            }
                        }

                        async function prefetch(url) {
                            if (danfeCache.has(url)) return;
                            try {
                                const res = await fetch(url, {
                                    credentials: 'same-origin',
                                    cache: 'reload'
                                });
                                if (!res.ok) return;
                                const txt = await res.text();
                                danfeCache.set(url, txt);
                            } catch (_) {
                                /* ignora erros de prefetch */
                            }
                        }

                        function schedulePrefetch(urls) {
                            let count = 0;
                            urls.slice(0, MAX_PREFETCH).forEach(u => {
                                if (!u) return;
                                if (queue.includes(u)) return;
                                queue.push(u);
                                const run = () => prefetch(u);
                                if ('requestIdleCallback' in window) {
                                    requestIdleCallback(run, {
                                        timeout: 1500
                                    });
                                } else {
                                    setTimeout(run, 300);
                                }
                            });
                        }

                        // Coleta URLs dos botões e programa prefetch quando entram no viewport
                        function collectUrlsAndObserve() {
                            const btns = Array.from(document.querySelectorAll('.ver-danfe[data-url]'));
                            const urls = btns.map(b => b.getAttribute('data-url')).filter(Boolean);
                            if ('IntersectionObserver' in window) {
                                const io = new IntersectionObserver((entries) => {
                                    entries.forEach(entry => {
                                        if (entry.isIntersecting) {
                                            const u = entry.target.getAttribute('data-url');
                                            if (u) schedulePrefetch([u]);
                                            io.unobserve(entry.target);
                                        }
                                    });
                                }, {
                                    rootMargin: '200px'
                                });
                                btns.forEach(b => io.observe(b));
                            } else {
                                schedulePrefetch(urls);
                            }
                        }

                        collectUrlsAndObserve();

                        // Abre modal (Bootstrap 5)
                        danfeModal.addEventListener('show.bs.modal', function(ev) {
                            const btn = ev.relatedTarget;
                            if (!btn) return;

                            const url = btn.getAttribute('data-url') || '';
                            const chave = btn.getAttribute('data-chave') || '';
                            const venda = btn.getAttribute('data-venda') || '';
                            const base = absoluteBaseFor(url);

                            danfeLoader.style.display = 'flex';
                            danfeInfo.textContent = `Chave: ${chave ? chave.replace(/(\d{4})/g,'$1 ').trim() : '—'} • Venda #${venda}`;
                            btnNovaGuia.style.display = 'inline-block';
                            btnNovaGuia.onclick = () => window.open(url, '_blank');

                            // Se já temos cache do HTML, injeta via srcdoc (instantâneo)
                            if (danfeCache.has(url)) {
                                const html = danfeCache.get(url);
                                try {
                                    danfeFrame.removeAttribute('src');
                                    danfeFrame.srcdoc = wrapWithBase(html, base);
                                    // sumir o loader logo (com pequeno atraso p/ UX)
                                    setTimeout(() => (danfeLoader.style.display = 'none'), 80);
                                    return;
                                } catch (_) {
                                    // fallback abaixo
                                }
                            }

                            // Fallback: carregar via src tradicional (será rápido se navegador cacheou)
                            danfeFrame.removeAttribute('srcdoc');
                            danfeFrame.src = url;
                        });

                        danfeFrame.addEventListener('load', function() {
                            setTimeout(() => {
                                danfeLoader.style.display = 'none';
                            }, 120);
                        });

                        danfeModal.addEventListener('hidden.bs.modal', function() {
                            danfeFrame.src = 'about:blank';
                            danfeFrame.removeAttribute('srcdoc');
                            danfeLoader.style.display = 'flex';
                            danfeInfo.textContent = '';
                            btnNovaGuia.style.display = 'none';
                            btnNovaGuia.onclick = null;
                        });
                    })();
                </script>

                <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
                <script src="../../assets/vendor/libs/popper/popper.js"></script>
                <script src="../../assets/vendor/js/bootstrap.js"></script>
                <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
                <script src="../../assets/vendor/js/menu.js"></script>
                <script src="../../assets/js/main.js"></script>
</body>

</html>
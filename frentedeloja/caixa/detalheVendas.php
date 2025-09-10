<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

/* ======================== PARÂMETROS / SESSÃO ======================== */
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

/* Helpers */
function soDigitos(string $v): string
{
    return preg_replace('/\D+/', '', $v) ?? '';
}
function moeda($v): string
{
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}

/* Sessão */
$usuario_id        = (int)$_SESSION['usuario_id'];
$tipoUsuarioSessao = $_SESSION['nivel']; // Admin/Comum
$cpfUsuario        = soDigitos((string)($_SESSION['usuario_cpf'] ?? ($_SESSION['cpf'] ?? '')));

/* id_caixa via ?chave= (compat) ou ?id_caixa= */
$idCaixa = isset($_GET['chave']) ? (int)$_GET['chave'] : (int)($_GET['id_caixa'] ?? 0);

/* ======================== CARREGA USUÁRIO ========================== */
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

/* ======================== VALIDA EMPRESA =========================== */
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

/* ======================== ICONE EMPRESA (opcional) ================= */
$iconeEmpresa = '../../assets/img/favicon/favicon.ico';
try {
    $s = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1");
    $s->execute([':id' => $idSelecionado]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['imagem'])) $iconeEmpresa = $row['imagem'];
} catch (Throwable $e) {
}

/* ======================== DEDUZ id_caixa SE FALTOU ================ */
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

if ($idCaixa <= 0) {
?>
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="alert alert-warning text-center">
            Nenhum caixa identificado para detalhar. Volte ao
            <a href="./relatorioVendas.php?id=<?= urlencode($idSelecionado) ?>">Resumo de Vendas</a>.
        </div>
    </div>
<?php
    exit;
}

/* ======================== BUSCAS PRINCIPAIS ======================= */
$produtosVendas   = [];
$totalVendas      = 0.0;
$sangrias         = [];
$totalSangrias    = 0.0;
$suprimentos      = [];
$totalSuprimentos = 0.0;

try {
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

/* ======================== DATA DO TÍTULO ========================== */
$dataRelatorio = null;
if (!empty($produtosVendas)) {
    $dataRelatorio = (new DateTime($produtosVendas[0]['data_venda']))->format('Y-m-d');
} elseif (!empty($sangrias)) {
    $dataRelatorio = (new DateTime($sangrias[0]['data_registro']))->format('Y-m-d');
} elseif (!empty($suprimentos)) {
    $dataRelatorio = (new DateTime($suprimentos[0]['data_registro']))->format('Y-m-d');
}
$dtTitulo = $dataRelatorio ? date('d/m/Y', strtotime($dataRelatorio)) : '—';

/* ======================== URL BASE DO DANFE ======================= */
$DANFE_BASE = './danfe_nfce.php';
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style customizer-hide" dir="ltr" data-theme="theme-default"
    data-assets-path="../assets/" data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover" />
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($iconeEmpresa) ?>" />
    <title>ERP - PDV</title>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />
    <link href="https://cdn.jsdelivr.net/npm/boxicons/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

    <script src="../../assets/vendor/js/helpers.js"></script>
    <script src="../../assets/js/config.js"></script>
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- Sidebar -->
            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="app-brand-link">
                        <span class="app-brand-text demo menu-text fw-bolder ms-2" style="text-transform:capitalize;">Açaínhadinhos</span>
                    </a>
                    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
                        <i class="bx bx-chevron-left bx-sm align-middle"></i>
                    </a>
                </div>
                <div class="menu-inner-shadow"></div>
                <ul class="menu-inner py-1">
                    <li class="menu-item"><a href="index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div>Dashboard</div>
                        </a></li>
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Frente de Caixa</span></li>
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle"><i class="menu-icon tf-icons bx bx-barcode-reader"></i>
                            <div>Operações de Caixa</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item"><a href="./abrirCaixa.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Abrir Caixa</div>
                                </a></li>
                            <li class="menu-item"><a href="./fecharCaixa.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Fechar Caixa</div>
                                </a></li>
                            <li class="menu-item"><a href="./sangria.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Sangria</div>
                                </a></li>
                            <li class="menu-item"><a href="./suprimento.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Suprimento</div>
                                </a></li>
                        </ul>
                    </li>
                    <li class="menu-item"><a href="./vendaRapida.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-cart-alt"></i>
                            <div>Venda Rápida</div>
                        </a></li>
                    <li class="menu-item"><a href="./cancelarVenda.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-x-circle"></i>
                            <div>Cancelar Venda</div>
                        </a></li>
                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle"><i class="menu-icon tf-icons bx bx-bar-chart-alt"></i>
                            <div>Relatórios</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item"><a href="./relatorioVendas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Resumo de Vendas</div>
                                </a></li>
                            <li class="menu-item active"><a href="#" class="menu-link">
                                    <div>Detalhes da Venda</div>
                                </a></li>
                        </ul>
                    </li>
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
                    <li class="menu-item"><a href="../sistemadeponto/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-group"></i>
                            <div>Sistema de Ponto</div>
                        </a></li>
                    <li class="menu-item"><a href="../Delivery/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-cart"></i>
                            <div>Delivery</div>
                        </a></li>
                    <li class="menu-item"><a href="https://wa.me/92991515710" target="_blank" class="menu-link"><i class="menu-icon tf-icons bx bx-support"></i>
                            <div>Suporte</div>
                        </a></li>
                </ul>
            </aside>
            <!-- /Sidebar -->

            <!-- Page -->
            <div class="layout-page">
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
                                                <span class="flex-shrink-0 badge badge-center rounded-pill bg-danger w-px-20 h-px-20">4</span>
                                            </span>
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

                <!-- Content -->
                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold mb-0">
                        <span class="text-muted fw-light"><a href="./relatorioVendas.php?id=<?= urlencode($idSelecionado) ?>">Relatório</a> / </span>
                        Detalhes de Vendas — <?= htmlspecialchars($dtTitulo) ?>
                    </h4>

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
                                                    $status = trim((string)($it['status_nfce'] ?? ''));
                                                    $chave  = trim((string)($it['chave_nfce'] ?? ''));
                                                    $statusLower = strtolower($status);
                                                    $temDanfe = !empty($chave) && ($statusLower === 'autorizada' || $statusLower === 'autorizado' || $statusLower === '100' || $statusLower === 'aprovada');

                                                    $urlDanfe = $DANFE_BASE . '?id=' . urlencode($idSelecionado) . '&venda_id=' . (int)$it['venda_id'];
                                                    if (!empty($chave)) $urlDanfe .= '&chave=' . urlencode($chave);
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
                                                                <?= $temDanfe ? 'Autorizada' : (!empty($status) ? htmlspecialchars($status) : '—') ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if ($temDanfe): ?>
                                                                <a href="<?= htmlspecialchars($urlDanfe) ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
                                                                    Ver DANFE
                                                                </a>
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
                                                <?php else: foreach ($sangrias as $s):
                                                    $d = new DateTime($s['data_registro']); ?>
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
                                                <?php else: foreach ($suprimentos as $sp):
                                                    $d = new DateTime($sp['data_registro']); ?>
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
                <!-- /Content -->

            </div> <!-- /layout-page -->
        </div> <!-- /layout-container -->
    </div> <!-- /layout-wrapper -->

    <!-- Scripts -->
    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../assets/vendor/js/menu.js"></script>
    <script src="../../assets/js/main.js"></script>
</body>

</html>
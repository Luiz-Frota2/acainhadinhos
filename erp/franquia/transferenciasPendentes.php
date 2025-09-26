<?php
// transferenciasPendentes.php
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
$usuario_id  = (int) $_SESSION['usuario_id'];

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
    echo "<script>alert('Erro ao carregar usuário: " . $e->getMessage() . "'); history.back();</script>";
    exit;
}

// ✅ Valida o tipo de empresa e o acesso permitido
$acessoPermitido   = false;
$idEmpresaSession  = $_SESSION['empresa_id'];
$tipoSession       = $_SESSION['tipo_empresa'];

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
    echo "<script>alert('Acesso negado!'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
    exit;
}

// ✅ Logo da empresa (fallback)
try {
    $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1");
    $stmt->bindParam(':id', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $sobre = $stmt->fetch(PDO::FETCH_ASSOC);
    $logoEmpresa = !empty($sobre['imagem']) ? "../../assets/img/empresa/" . $sobre['imagem'] : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png";
}

// =======================================================
// Helpers
// =======================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$mapStatus = [
    'aguardando'  => ['label' => 'Aguardando',   'class' => 'bg-label-secondary'],
    'enviado'     => ['label' => 'Enviado',      'class' => 'bg-label-warning'],
    'em_transito' => ['label' => 'Em trânsito',  'class' => 'bg-label-info'],
    'recebido'    => ['label' => 'Recebido',     'class' => 'bg-label-success'],
    'cancelado'   => ['label' => 'Cancelado',    'class' => 'bg-label-danger'],
];

// =======================================================
// Ações por POST (confirmar envio / recebimento / cancelar)
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao      = $_POST['acao'] ?? '';
    $idTransf  = (int) ($_POST['transferencia_id'] ?? 0);
    $tokenPost = $_POST['csrf_token'] ?? '';

    if (!$idTransf || !hash_equals($_SESSION['csrf_token'] ?? '', $tokenPost)) {
        echo "<script>alert('Requisição inválida.'); history.back();</script>";
        exit;
    }

    try {
        // TODO: ajuste a coluna que vincula a transferência ao contexto (ex.: id_selecionado / empresa_id / matriz_id)
        $pdo->beginTransaction();

        if ($acao === 'confirmar_envio') {
            $sql = "UPDATE transferencias_b2b 
              SET status = 'em_transito', enviado_em = COALESCE(enviado_em, NOW()), atualizado_em = NOW(), usuario_acao = :uid
              WHERE id = :id AND status IN ('aguardando','enviado')";
        } elseif ($acao === 'confirmar_recebimento') {
            $sql = "UPDATE transferencias_b2b 
              SET status = 'recebido', recebido_em = NOW(), atualizado_em = NOW(), usuario_acao = :uid
              WHERE id = :id AND status IN ('em_transito','enviado')";
        } elseif ($acao === 'cancelar') {
            $sql = "UPDATE transferencias_b2b 
              SET status = 'cancelado', atualizado_em = NOW(), usuario_acao = :uid
              WHERE id = :id AND status IN ('aguardando','enviado','em_transito')";
        } else {
            throw new Exception('Ação desconhecida.');
        }

        $st = $pdo->prepare($sql);
        $st->execute([':id' => $idTransf, ':uid' => $usuario_id]);

        $pdo->commit();
        header("Location: ./transferenciasPendentes.php?id=" . urlencode($idSelecionado) . "&ok=1");
        exit;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo "<script>alert('Falha ao processar ação: " . e($ex->getMessage()) . "'); history.back();</script>";
        exit;
    }
}

// =======================================================
// Filtros (GET)
// =======================================================
$fStatus   = $_GET['status']   ?? 'pendentes';
$fFilial   = trim($_GET['filial'] ?? '');
$fDataIni  = trim($_GET['data_ini'] ?? '');
$fDataFim  = trim($_GET['data_fim'] ?? '');
$busca     = trim($_GET['q'] ?? '');

// status considerados "pendentes"
$pendentesList = ['aguardando', 'enviado', 'em_transito'];

// =======================================================
// Fonte para <select> Filiais
// =======================================================
try {
    // TODO: ajuste o JOIN/WHERE de acordo com sua modelagem de franquias
    $filiais = $pdo->query("SELECT id, nome FROM franquias ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $filiais = [];
}

// =======================================================
// Consulta principal
// =======================================================
$sql = [];
$sql[] = "SELECT 
            t.id,
            t.codigo,
            t.filial_id,
            f.nome AS filial_nome,
            t.status,
            t.criado_em,
            t.enviado_em,
            t.recebido_em,
            COALESCE(SUM(i.qtd),0) AS qtd_total,
            COUNT(i.id) AS itens_total,
            t.obs
          FROM transferencias_b2b t
          JOIN franquias f ON f.id = t.filial_id
          LEFT JOIN transferencias_itens i ON i.transferencia_id = t.id";
$w = [];
$params = [];

// Apenas transferências do contexto atual (se houver uma coluna que vincule à matriz/empresa)
# TODO: se existir, descomente/ajuste a linha abaixo:
# $w[] = "t.id_selecionado = :idSel";
# $params[':idSel'] = $idSelecionado;

if ($fStatus === 'pendentes') {
    $w[] = "t.status IN ('" . implode("','", $pendentesList) . "')";
} elseif ($fStatus && $fStatus !== 'todas') {
    $w[] = "t.status = :st";
    $params[':st'] = $fStatus;
} // 'todas' não aplica filtro de status

if ($fFilial !== '') {
    $w[] = "t.filial_id = :fid";
    $params[':fid'] = (int)$fFilial;
}

if ($fDataIni !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fDataIni)) {
    $w[] = "DATE(t.criado_em) >= :di";
    $params[':di'] = $fDataIni;
}
if ($fDataFim !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fDataFim)) {
    $w[] = "DATE(t.criado_em) <= :df";
    $params[':df'] = $fDataFim;
}

if ($busca !== '') {
    // busca por código/observação/nome da filial
    $w[] = "(t.codigo LIKE :q OR t.obs LIKE :q OR f.nome LIKE :q)";
    $params[':q'] = "%{$busca}%";
}

if ($w) $sql[] = "WHERE " . implode(" AND ", $w);
$sql[] = "GROUP BY t.id, t.codigo, t.filial_id, f.nome, t.status, t.criado_em, t.enviado_em, t.recebido_em, t.obs";
$sql[] = "ORDER BY t.criado_em DESC";
$sqlStr = implode("\n", $sql);

try {
    $stmt = $pdo->prepare($sqlStr);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $linhas = [];
}

?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>ERP — Transferências Pendentes</title>
    <link rel="icon" type="image/x-icon" href="<?= e($logoEmpresa) ?>" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <script src="../../assets/vendor/js/helpers.js"></script>
    <script src="../../assets/js/config.js"></script>
    <style>
        .table thead th {
            white-space: nowrap;
        }

        .status-badge {
            font-size: .78rem;
        }

        .toolbar {
            gap: .5rem;
            flex-wrap: wrap;
        }

        .toolbar .form-select,
        .toolbar .form-control {
            max-width: 220px;
        }

        .badge-dot {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
        }

        .badge-dot::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            display: inline-block;
        }

        .actions .btn {
            margin-right: .25rem;
        }

        .table-responsive {
            overflow: auto;
        }
    </style>
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">

            <!-- ====== ASIDE (mantido do seu layout) ====== -->
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
                            <div>Dashboard</div>
                        </a>
                    </li>

                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Administração Franquias</span></li>

                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-building"></i>
                            <div>Franquias</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item"><a href="./franquiaAdicionada.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Adicionadas</div>
                                </a></li>
                        </ul>
                    </li>

                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div>B2B - Matriz</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item"><a href="./contasFranquia.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Pagamentos Solic.</div>
                                </a></li>
                            <li class="menu-item"><a href="./produtosSolicitados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Produtos Solicitados</div>
                                </a></li>
                            <li class="menu-item"><a href="./produtosEnviados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Produtos Enviados</div>
                                </a></li>
                            <li class="menu-item active"><a href="./transferenciasPendentes.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Transf. Pendentes</div>
                                </a></li>
                            <li class="menu-item"><a href="./historicoTransferencias.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Histórico Transf.</div>
                                </a></li>
                            <li class="menu-item"><a href="./estoqueMatriz.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Estoque Matriz</div>
                                </a></li>
                            <li class="menu-item"><a href="./relatoriosB2B.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Relatórios B2B</div>
                                </a></li>
                        </ul>
                    </li>

                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-bar-chart-alt-2"></i>
                            <div>Relatórios</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item"><a href="./VendasFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Vendas por Franquias</div>
                                </a></li>
                            <li class="menu-item"><a href="./MaisVendidos.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Mais Vendidos</div>
                                </a></li>
                            <li class="menu-item"><a href="./vendasPeriodo.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Vendas por Período</div>
                                </a></li>
                            <li class="menu-item"><a href="./FinanceiroFranquia.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Financeiro</div>
                                </a></li>
                        </ul>
                    </li>

                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
                    <li class="menu-item"><a href="../rh/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-group"></i>
                            <div>RH</div>
                        </a></li>
                    <li class="menu-item"><a href="../financas/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-dollar"></i>
                            <div>Finanças</div>
                        </a></li>
                    <li class="menu-item"><a href="../pdv/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-desktop"></i>
                            <div>PDV</div>
                        </a></li>
                    <li class="menu-item"><a href="../empresa/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div>Empresa</div>
                        </a></li>
                    <li class="menu-item"><a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-box"></i>
                            <div>Estoque</div>
                        </a></li>
                    <li class="menu-item"><a href="../filial/index.php?id=principal_1" class="menu-link"><i class="menu-icon tf-icons bx bx-building"></i>
                            <div>Filial</div>
                        </a></li>
                    <li class="menu-item"><a href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-group"></i>
                            <div>Usuários</div>
                        </a></li>
                    <li class="menu-item"><a href="https://wa.me/92991515710" target="_blank" class="menu-link"><i class="menu-icon tf-icons bx bx-support"></i>
                            <div>Suporte</div>
                        </a></li>
                </ul>
            </aside>
            <!-- ====== /ASIDE ====== -->

            <div class="layout-page">
                <!-- Navbar -->
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                            <i class="bx bx-menu bx-sm"></i>
                        </a>
                    </div>

                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <div class="navbar-nav align-items-center">
                            <div class="nav-item d-flex align-items-center">
                                <i class="bx bx-search fs-4 lh-0"></i>
                                <input type="text" class="form-control border-0 shadow-none" placeholder="Search..." aria-label="Search..." />
                            </div>
                        </div>

                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="avatar avatar-online">
                                        <img src="<?= htmlspecialchars($logoEmpresa, ENT_QUOTES) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownUser">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="<?= htmlspecialchars($logoEmpresa, ENT_QUOTES) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" />
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <span class="fw-semibold d-block"><?= htmlspecialchars($nomeUsuario, ENT_QUOTES); ?></span>
                                                    <small class="text-muted"><?= htmlspecialchars($tipoUsuario, ENT_QUOTES); ?></small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                    <li>
                                        <div class="dropdown-divider"></div>
                                    </li>
                                    <li><a class="dropdown-item" href="./contaUsuario.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-user me-2"></i><span class="align-middle">Minha Conta</span></a></li>
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

                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold mb-0">
                        <span class="text-muted fw-light"><a href="#">Franquias</a>/</span>
                        Transferências Pendentes
                    </h4>
                    <h5 class="fw-bold mt-3 mb-3 custor-font">
                        <span class="text-muted fw-light">Movimentações a concluir entre Matriz e Franquias</span>
                    </h5>



                    <!-- Tabela -->
                    <div class="card">
                        <h5 class="card-header">Lista de Transferências</h5>
                        <div class="table-responsive text-nowrap">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Franquia</th>
                                        <th>Itens</th>
                                        <th>Qtd</th>
                                        <th>Criado</th>
                                        <th>Envio</th>
                                        <th>Status</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="table-border-bottom-0">

                                    <!-- Exemplo quando não há registros -->
                                    <!--
        <tr>
          <td colspan="8" class="text-center text-muted">Nenhuma transferência encontrada.</td>
        </tr>
        -->

                                    <!-- Linha de exemplo 1 -->
                                    <tr>
                                        <td><strong>TR-1024</strong></td>
                                        <td>Franquia Centro</td>
                                        <td>5</td>
                                        <td>120</td>
                                        <td>26/09/2025 09:20</td>
                                        <td>-</td>
                                        <td><span class="badge bg-label-secondary status-badge">Aguardando</span></td>
                                        <td class="text-end actions">
                                            <button
                                                class="btn btn-sm btn-outline-secondary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalDetalhes"
                                                data-id="1024"
                                                data-codigo="TR-1024"
                                                data-filial="Franquia Centro"
                                                data-status="Aguardando">
                                                Detalhes
                                            </button>

                                            <form class="d-inline" method="post" action="#">
                                                <input type="hidden" name="csrf_token" value="TOKEN_AQUI">
                                                <input type="hidden" name="transferencia_id" value="1024">
                                                <input type="hidden" name="acao" value="confirmar_envio">
                                                <button class="btn btn-sm btn-warning">Confirmar envio</button>
                                            </form>

                                            <form class="d-inline" method="post" action="#">
                                                <input type="hidden" name="csrf_token" value="TOKEN_AQUI">
                                                <input type="hidden" name="transferencia_id" value="1024">
                                                <input type="hidden" name="acao" value="cancelar">
                                                <button class="btn btn-sm btn-outline-danger">Cancelar</button>
                                            </form>
                                        </td>
                                    </tr>

                                    <!-- Linha de exemplo 2 -->
                                    <tr>
                                        <td><strong>TR-1025</strong></td>
                                        <td>Franquia Norte</td>
                                        <td>3</td>
                                        <td>40</td>
                                        <td>25/09/2025 15:10</td>
                                        <td>25/09/2025 18:00</td>
                                        <td><span class="badge bg-label-warning status-badge">Enviado</span></td>
                                        <td class="text-end actions">
                                            <button
                                                class="btn btn-sm btn-outline-secondary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalDetalhes"
                                                data-id="1025"
                                                data-codigo="TR-1025"
                                                data-filial="Franquia Norte"
                                                data-status="Enviado">
                                                Detalhes
                                            </button>

                                            <form class="d-inline" method="post" action="#">
                                                <input type="hidden" name="csrf_token" value="TOKEN_AQUI">
                                                <input type="hidden" name="transferencia_id" value="1025">
                                                <input type="hidden" name="acao" value="confirmar_envio">
                                                <button class="btn btn-sm btn-warning">Confirmar envio</button>
                                            </form>

                                            <form class="d-inline" method="post" action="#">
                                                <input type="hidden" name="csrf_token" value="TOKEN_AQUI">
                                                <input type="hidden" name="transferencia_id" value="1025">
                                                <input type="hidden" name="acao" value="confirmar_recebimento">
                                                <button class="btn btn-sm btn-success">Marcar recebido</button>
                                            </form>

                                            <form class="d-inline" method="post" action="#">
                                                <input type="hidden" name="csrf_token" value="TOKEN_AQUI">
                                                <input type="hidden" name="transferencia_id" value="1025">
                                                <input type="hidden" name="acao" value="cancelar">
                                                <button class="btn btn-sm btn-outline-danger">Cancelar</button>
                                            </form>
                                        </td>
                                    </tr>

                                    <!-- Linha de exemplo 3 -->
                                    <tr>
                                        <td><strong>TR-1026</strong></td>
                                        <td>Franquia Sul</td>
                                        <td>2</td>
                                        <td>500</td>
                                        <td>20/09/2025 10:00</td>
                                        <td>21/09/2025 08:30</td>
                                        <td><span class="badge bg-label-info status-badge">Em trânsito</span></td>
                                        <td class="text-end actions">
                                            <button
                                                class="btn btn-sm btn-outline-secondary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalDetalhes"
                                                data-id="1026"
                                                data-codigo="TR-1026"
                                                data-filial="Franquia Sul"
                                                data-status="Em trânsito">
                                                Detalhes
                                            </button>

                                            <form class="d-inline" method="post" action="#">
                                                <input type="hidden" name="csrf_token" value="TOKEN_AQUI">
                                                <input type="hidden" name="transferencia_id" value="1026">
                                                <input type="hidden" name="acao" value="confirmar_recebimento">
                                                <button class="btn btn-sm btn-success">Marcar recebido</button>
                                            </form>

                                            <form class="d-inline" method="post" action="#">
                                                <input type="hidden" name="csrf_token" value="TOKEN_AQUI">
                                                <input type="hidden" name="transferencia_id" value="1026">
                                                <input type="hidden" name="acao" value="cancelar">
                                                <button class="btn btn-sm btn-outline-danger">Cancelar</button>
                                            </form>
                                        </td>
                                    </tr>

                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Modal Detalhes -->
                    <div class="modal fade" id="modalDetalhes" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Detalhes da Transferência</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row g-3 mb-2">
                                        <div class="col-md-4">
                                            <p><strong>Código:</strong> <span id="det-codigo">-</span></p>
                                        </div>
                                        <div class="col-md-4">
                                            <p><strong>Filial:</strong> <span id="det-filial">-</span></p>
                                        </div>
                                        <div class="col-md-4">
                                            <p><strong>Status:</strong> <span id="det-status">-</span></p>
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>SKU</th>
                                                    <th>Produto</th>
                                                    <th>Qtd</th>
                                                </tr>
                                            </thead>
                                            <tbody id="det-itens">
                                                <tr>
                                                    <td colspan="3" class="text-muted">Carregando...</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="mt-2">
                                        <strong>Observações:</strong>
                                        <div id="det-obs" class="text-muted">—</div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                                </div>
                            </div>
                        </div>
                    </div>


                </div><!-- /container -->
            </div><!-- /Layout page -->
        </div><!-- /Layout container -->
    </div>

    <!-- Core JS -->
    <script src="../../js/saudacao.js"></script>
    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../assets/vendor/js/menu.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>

    <script>
        // Carregar itens no modal (AJAX simples)
        const modal = document.getElementById('modalDetalhes');
        modal?.addEventListener('show.bs.modal', function(e) {
            const btn = e.relatedTarget;
            if (!btn) return;

            const id = btn.getAttribute('data-id');
            const codigo = btn.getAttribute('data-codigo');
            const filial = btn.getAttribute('data-filial');
            const status = btn.getAttribute('data-status');

            document.getElementById('det-codigo').textContent = codigo || '-';
            document.getElementById('det-filial').textContent = filial || '-';
            document.getElementById('det-status').textContent = status || '-';
            document.getElementById('det-itens').innerHTML = '<tr><td colspan="3" class="text-muted">Carregando...</td></tr>';
            document.getElementById('det-obs').textContent = '—';

            // Endpoint interno nesta mesma página (modo leve)
            fetch(`./transferenciasPendentes.php?ajax=itens&id=<?= urlencode($idSelecionado); ?>&t=` + encodeURIComponent(id), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(r => r.ok ? r.json() : Promise.reject())
                .then(data => {
                    const tBody = document.getElementById('det-itens');
                    if (!data || !Array.isArray(data.itens) || !data.itens.length) {
                        tBody.innerHTML = '<tr><td colspan="3" class="text-muted">Sem itens.</td></tr>';
                    } else {
                        tBody.innerHTML = data.itens.map(i => `
            <tr>
              <td>${(i.sku ?? '').toString().replaceAll('<','&lt;')}</td>
              <td>${(i.nome ?? '').toString().replaceAll('<','&lt;')}</td>
              <td>${parseInt(i.qtd ?? 0)}</td>
            </tr>
          `).join('');
                    }
                    document.getElementById('det-obs').textContent = (data.obs ?? '—');
                })
                .catch(() => {
                    document.getElementById('det-itens').innerHTML = '<tr><td colspan="3" class="text-danger">Falha ao carregar itens.</td></tr>';
                });
        });
    </script>

    <?php
    // =======================================================
    // Mini endpoint AJAX (?ajax=itens&t={id})
    // =======================================================
    if (($_GET['ajax'] ?? '') === 'itens') {
        header('Content-Type: application/json; charset=utf-8');
        $tId = (int)($_GET['t'] ?? 0);
        $out = ['itens' => [], 'obs' => ''];

        if ($tId > 0) {
            try {
                // Itens
                $si = $pdo->prepare("SELECT i.id, i.sku, i.nome, i.qtd 
                           FROM transferencias_itens i 
                           WHERE i.transferencia_id = :tid
                           ORDER BY i.nome ASC");
                $si->execute([':tid' => $tId]);
                $out['itens'] = $si->fetchAll(PDO::FETCH_ASSOC);

                // Observação do cabeçalho
                $so = $pdo->prepare("SELECT obs FROM transferencias_b2b WHERE id = :tid LIMIT 1");
                $so->execute([':tid' => $tId]);
                $out['obs'] = (string)($so->fetchColumn() ?? '');
            } catch (Throwable $e) {
                // mantém vazio
            }
        }

        echo json_encode($out, JSON_UNESCAPED_UNICODE);
        exit;
    }
    ?>
</body>

</html>
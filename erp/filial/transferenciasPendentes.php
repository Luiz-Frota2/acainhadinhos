<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// ========================= CSRF (gerar/validar) =========================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_check(?string $token): void {
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        echo "Falha de segurança (CSRF inválido).";
        exit;
    }
}

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
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ✅ Buscar nome e tipo do usuário logado
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id  = (int)$_SESSION['usuario_id'];

try {
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $nomeUsuario = $usuario['usuario'] ?? 'Usuário';
        $tipoUsuario = ucfirst((string)($usuario['nivel'] ?? 'Comum'));
    } else {
        echo "<script>alert('Usuário não encontrado.'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar usuário: " . htmlspecialchars($e->getMessage()) . "'); history.back();</script>";
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
    echo "<script>
          alert('Acesso negado!');
          window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';
        </script>";
    exit;
}

// ✅ Buscar logo da empresa
try {
    $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1");
    $stmt->bindParam(':id_selecionado', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);

    $logoEmpresa = (!empty($empresaSobre) && !empty($empresaSobre['imagem']))
        ? "../../assets/img/empresa/" . $empresaSobre['imagem']
        : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png"; // fallback
}

// ========================= Helpers =========================
function dtBr(?string $dt) {
    if (!$dt) return '-';
    $t = strtotime($dt); if (!$t) return '-';
    return date('d/m/Y H:i', $t);
}

// ========================= POST AÇÕES (status) =========================
$flashMsg = null; $flashOk = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $transferencia_id = (int)($_POST['transferencia_id'] ?? 0);
    csrf_check($_POST['csrf_token'] ?? null);

    if ($transferencia_id > 0 && in_array($acao, ['confirmar_envio','cancelar'], true)) {
        try {
            // Confere se a solicitação é da matriz atual
            $chk = $pdo->prepare("
                SELECT id, status, id_matriz
                  FROM solicitacoes_b2b
                 WHERE id = :id AND id_matriz = :matriz
                 LIMIT 1
            ");
            $chk->execute([':id'=>$transferencia_id, ':matriz'=>$idSelecionado]);
            $row = $chk->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $flashMsg = "Solicitação não encontrada para esta matriz.";
                $flashOk  = false;
            } else {
                // Regra simples: permitir transição a partir de 'aprovada' ou 'aguardando' (ajuste se quiser mais rígido)
                if ($acao === 'confirmar_envio') {
                    $novo = 'em_transito';
                } else { // cancelar
                    $novo = 'cancelar';
                }

                $upd = $pdo->prepare("UPDATE solicitacoes_b2b SET status = :s, atualizada_em = NOW() WHERE id = :id");
                $upd->execute([':s'=>$novo, ':id'=>$transferencia_id]);

                $flashOk  = true;
                $flashMsg = $acao === 'confirmar_envio'
                    ? "Transferência #{$transferencia_id} atualizada para EM TRÂNSITO."
                    : "Transferência #{$transferencia_id} atualizada para CANCELAR.";
            }
        } catch (PDOException $e) {
            $flashOk  = false;
            $flashMsg = "Erro ao atualizar: " . $e->getMessage();
        }
    } else {
        $flashOk  = false;
        $flashMsg = "Requisição inválida.";
    }
}

// ========================= AJAX: detalhes da solicitação =========================
if (($_GET['action'] ?? '') === 'detalhes') {
    header('Content-Type: application/json; charset=utf-8');
    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['ok'=>false,'msg'=>'ID inválido.']); exit;
    }

    try {
        // Cabeçalho da solicitação + filial
        $cab = $pdo->prepare("
            SELECT s.id, s.id_solicitante, s.status, s.observacoes, s.created_at, s.aprovada_em,
                   u.nome AS filial_nome
              FROM solicitacoes_b2b s
              JOIN unidades u
                ON u.id = CAST(REPLACE(s.id_solicitante, 'unidade_', '') AS UNSIGNED)
               AND u.empresa_id = :empresa
             WHERE s.id = :id
               AND s.id_matriz = :empresa
             LIMIT 1
        ");
        $cab->execute([':id'=>$id, ':empresa'=>$idSelecionado]);
        $head = $cab->fetch(PDO::FETCH_ASSOC);
        if (!$head) {
            echo json_encode(['ok'=>false,'msg'=>'Solicitação não encontrada.']); exit;
        }

        // Itens (tenta nome/código do produto de produtos_peca; se não houver, usa campos do item)
        $it = $pdo->prepare("
            SELECT 
                i.id,
                i.produto_id,
                i.codigo_produto    AS item_codigo,
                i.nome_produto      AS item_nome,
                i.quantidade,
                i.valor,
                p.codigo_produto    AS prod_codigo,
                p.nome_produto      AS prod_nome
            FROM solicitacoes_b2b_itens i
            LEFT JOIN produtos_peca p
                   ON p.id = i.produto_id
            WHERE i.solicitacao_id = :id
            ORDER BY i.id ASC
        ");
        $it->execute([':id'=>$id]);
        $raw = $it->fetchAll(PDO::FETCH_ASSOC);

        $itens = [];
        foreach ($raw as $r) {
            $codigo = $r['prod_codigo'] ?: $r['item_codigo'];
            $nome   = $r['prod_nome']   ?: $r['item_nome'];
            $itens[] = [
                'codigo' => $codigo ?: '-',
                'nome'   => $nome   ?: '-',
                'qtd'    => (float)$r['quantidade'],
                'valor'  => isset($r['valor']) ? (float)$r['valor'] : null,
            ];
        }

        echo json_encode([
            'ok'   => true,
            'head' => [
                'id'         => (int)$head['id'],
                'filial'     => $head['filial_nome'] ?? '-',
                'status'     => $head['status'] ?? '-',
                'observ'     => $head['observacoes'] ?? '',
                'created_at' => $head['created_at'] ?? null,
                'aprovada_em'=> $head['aprovada_em'] ?? null,
            ],
            'itens'=> $itens
        ]);
        exit;
    } catch (PDOException $e) {
        echo json_encode(['ok'=>false,'msg'=>'Erro ao buscar itens: '.$e->getMessage()]); exit;
    }
}

/* ==========================================================
   🟢 LISTAGEM — Solicitações aprovadas de Filiais c/ estoque
   - status = 'aprovada'
   - id_matriz = empresa (sessão/URL)
   - solicitante é Filial da mesma empresa (unidades.tipo = 'Filial')
   - id_solicitante no formato 'unidade_{id}'
   - deve haver estoque para o id_solicitante
   - agrega itens e quantidade via solicitacoes_b2b_itens
   ========================================================== */
$solicitacoes = [];
try {
    $sql = "
        SELECT
            s.id,
            s.id_solicitante,
            u.nome AS filial_nome,
            s.created_at,
            s.aprovada_em,
            s.status,
            COUNT(i.id)                    AS itens,
            COALESCE(SUM(i.quantidade),0)  AS qtd_total
        FROM solicitacoes_b2b s
        JOIN unidades u
          ON u.id = CAST(REPLACE(s.id_solicitante, 'unidade_', '') AS UNSIGNED)
         AND u.tipo = 'Filial'
         AND u.empresa_id = :empresa_id
        LEFT JOIN solicitacoes_b2b_itens i
          ON i.solicitacao_id = s.id
        WHERE s.status = 'aprovada'
          AND s.id_matriz = :empresa_id
          AND EXISTS (
                SELECT 1
                  FROM estoque e
                 WHERE e.empresa_id = s.id_solicitante
          )
        GROUP BY s.id, s.id_solicitante, u.nome, s.created_at, s.aprovada_em, s.status
        ORDER BY s.aprovada_em DESC, s.created_at DESC, s.id DESC
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':empresa_id' => $idSelecionado]);
    $solicitacoes = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $solicitacoes = [];
}

$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
    data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>ERP - Filial</title>

    <meta name="description" content="" />

    <!-- Favicon da empresa carregado dinamicamente -->
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($logoEmpresa) ?>" />

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

    <!-- Helpers -->
    <script src="../../assets/vendor/js/helpers.js"></script>

    <!-- Config -->
    <script src="../../assets/js/config.js"></script>

</head>

<style>
    .table thead th { white-space: nowrap; }
    .status-badge { font-size: .78rem; }
    .toolbar { gap: .5rem; flex-wrap: wrap; }
    .toolbar .form-select, .toolbar .form-control { max-width: 220px; }
    .badge-dot { display: inline-flex; align-items: center; gap: .4rem; }
    .badge-dot::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: currentColor; display: inline-block; }
    .actions .btn { margin-right: .25rem; }
    .table-responsive { overflow: auto; }
</style>

<body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- Menu -->

            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="app-brand-link">

                        <span class="app-brand-text demo menu-text fw-bolder ms-2">Açaínhadinhos</span>
                    </a>

                    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
                        <i class="bx bx-chevron-left bx-sm align-middle"></i>
                    </a>
                </div>

                <div class="menu-inner-shadow"></div>

                <ul class="menu-inner py-1">
                    <!-- Dashboard -->
                    <li class="menu-item">
                        <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Dashboard</div>
                        </a>
                    </li>

                    <!-- Administração de Filiais -->
                    <li class="menu-header small text-uppercase">
                        <span class="menu-header-text">Administração Filiais</span>
                    </li>

                    <!-- Adicionar Filial -->
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-building"></i>
                            <div data-i18n="Adicionar">Filiais</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./filialAdicionada.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Filiais">Adicionadas</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div data-i18n="B2B">B2B - Matriz</div>
                        </a>
                        <ul class="menu-sub active">
                            <!-- Contas das Filiais -->
                            <li class="menu-item">
                                <a href="./contasFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Pagamentos Solic.</div>
                                </a>
                            </li>

                            <!-- Produtos solicitados pelas filiais -->
                            <li class="menu-item">
                                <a href="./produtosSolicitados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Produtos Solicitados</div>
                                </a>
                            </li>

                            <!-- Produtos enviados pela matriz -->
                            <li class="menu-item">
                                <a href="./produtosEnviados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Produtos Enviados</div>
                                </a>
                            </li>

                            <!-- Transferências em andamento -->
                            <li class="menu-item active">
                                <a href="./transferenciasPendentes.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Transf. Pendentes</div>
                                </a>
                            </li>

                            <!-- Histórico de transferências -->
                            <li class="menu-item">
                                <a href="./historicoTransferencias.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Histórico Transf.</div>
                                </a>
                            </li>

                            <!-- Gestão de Estoque Central -->
                            <li class="menu-item">
                                <a href="./estoqueMatriz.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Estoque Matriz</div>
                                </a>
                            </li>

                            <!-- Relatórios e indicadores B2B -->
                            <li class="menu-item">
                                <a href="./relatoriosB2B.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Relatórios B2B</div>
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
                                <a href="./VendasFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Vendas">Vendas por Filial</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./MaisVendidosFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="MaisVendidos">Mais Vendidos</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./vendasPeriodoFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Pedidos">Vendas por Período</div>
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
                    <li class="menu-item">
                        <a href="../franquia/index.php?id=principal_1" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-store"></i>
                            <div data-i18n="Authentications">Franquias</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-group"></i>
                            <div data-i18n="Authentications">Usuários </div>
                        </a>
                    </li>
                    <li class="menu-item mb-5">
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
                            <!-- User -->
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
                                    <li><div class="dropdown-divider"></div></li>
                                    <li>
                                        <a class="dropdown-item" href="./contaUsuario.php?id=<?= urlencode($idSelecionado); ?>">
                                            <i class="bx bx-user me-2"></i>
                                            <span class="align-middle">Minha Conta</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <i class="bx bx-cog me-2"></i>
                                            <span class="align-middle">Configurações</span>
                                        </a>
                                    </li>
                                    <li><div class="dropdown-divider"></div></li>
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

                <!-- Content -->
                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold mb-0">
                        <span class="text-muted fw-light"><a href="#">Filiais</a>/</span>
                        Transferências Pendentes
                    </h4>
                    <h5 class="fw-bold mt-3 mb-3 custor-font">
                        <span class="text-muted fw-light">Movimentações a concluir entre Matriz e Filiais</span>
                    </h5>

                    <?php if ($flashMsg !== null): ?>
                        <div class="alert alert-<?= $flashOk ? 'success' : 'danger' ?> alert-dismissible" role="alert">
                            <?= htmlspecialchars($flashMsg) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Tabela -->
                    <div class="card">
                        <h5 class="card-header">Lista de Transferências</h5>
                        <div class="table-responsive text-nowrap">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Filial</th>
                                        <th>Itens</th>
                                        <th>Qtd</th>
                                        <th>Criado</th>
                                        <th>Status</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>

                                <tbody class="table-border-bottom-0">
                                <?php if (empty($solicitacoes)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            Nenhuma solicitação aprovada encontrada.
                                        </td>
                                    </tr>
                                <?php else: foreach ($solicitacoes as $row): ?>
                                    <tr>
                                        <td><strong><?= (int)$row['id'] ?></strong></td>
                                        <td><?= htmlspecialchars($row['filial_nome'] ?? '-') ?></td>
                                        <td><?= (int)$row['itens'] ?></td>
                                        <td><?= (int)$row['qtd_total'] ?></td>
                                        <td><?= dtBr($row['created_at']) ?></td>
                                        <td>
                                            <span class="badge bg-label-success status-badge">
                                                <?= htmlspecialchars(ucwords(str_replace('_',' ', (string)($row['status'] ?? 'aprovada')))) ?>
                                            </span>
                                        </td>
                                        <td class="text-end actions">
                                            <button
                                                class="btn btn-sm btn-outline-secondary btn-detalhes"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalDetalhes"
                                                data-id="<?= (int)$row['id'] ?>"
                                                data-codigo="TR-<?= (int)$row['id'] ?>">
                                                Detalhes
                                            </button>

                                            <form class="d-inline" method="post" action="?id=<?= urlencode($idSelecionado); ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                                <input type="hidden" name="transferencia_id" value="<?= (int)$row['id'] ?>">
                                                <input type="hidden" name="acao" value="confirmar_envio">
                                                <button class="btn btn-sm btn-warning" onclick="return confirm('Confirmar envio desta transferência?');">
                                                    Confirmar envio
                                                </button>
                                            </form>

                                            <form class="d-inline" method="post" action="?id=<?= urlencode($idSelecionado); ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                                <input type="hidden" name="transferencia_id" value="<?= (int)$row['id'] ?>">
                                                <input type="hidden" name="acao" value="cancelar">
                                                <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Cancelar esta transferência?');">
                                                    Cancelar
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
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
                                                    <th>Código</th>
                                                    <th>Produto</th>
                                                    <th>Qtd</th>
                                                    <th>Valor</th>
                                                </tr>
                                            </thead>
                                            <tbody id="det-itens">
                                                <tr>
                                                    <td colspan="4" class="text-muted">Carregando...</td>
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


                </div>
                <!-- / Content -->

                <!-- Footer -->
                <footer class="content-footer footer bg-footer-theme text-center">
                    <div class="container-xxl d-flex  py-2 flex-md-row flex-column justify-content-center">
                        <div class="mb-2 mb-md-0">
                            &copy;
                            <script>
                                document.write(new Date().getFullYear());
                            </script>
                            , <strong>Açaínhadinhos</strong>. Todos os direitos reservados.
                            Desenvolvido por <strong>CodeGeek</strong>.
                        </div>
                    </div>
                </footer>

                <!-- / Footer -->

                <div class="content-backdrop fade"></div>
            </div>
            <!-- Content wrapper -->
        </div>
        <!-- / Layout page -->

    </div>

    <!-- Overlay -->
    <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <!-- / Layout wrapper -->

    <!-- Core JS -->
    <!-- build:js assets/vendor/js/core.js -->
    <script src="../../js/saudacao.js"></script>
    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>

    <script src="../../assets/vendor/js/menu.js"></script>
    <!-- endbuild -->

    <!-- Vendors JS -->
    <script src="../../assets/vendor/libs/apex-charts/apexcharts.js"></script>

    <!-- Main JS -->
    <script src="../../assets/js/main.js"></script>

    <!-- Page JS -->
    <script src="../../assets/js/dashboards-analytics.js"></script>

    <!-- Detalhes via AJAX -->
    <script>
        (function() {
            const modal = document.getElementById('modalDetalhes');
            const spanCodigo = document.getElementById('det-codigo');
            const spanFilial = document.getElementById('det-filial');
            const spanStatus = document.getElementById('det-status');
            const tbodyItens = document.getElementById('det-itens');
            const detObs     = document.getElementById('det-obs');

            // Abre modal e carrega itens
            document.querySelectorAll('.btn-detalhes').forEach(function(btn){
                btn.addEventListener('click', function(){
                    const id = this.getAttribute('data-id');
                    const codigo = this.getAttribute('data-codigo');

                    // Reset placeholders
                    spanCodigo.textContent = codigo || '-';
                    spanFilial.textContent = '-';
                    spanStatus.textContent = '-';
                    detObs.textContent     = '—';
                    tbodyItens.innerHTML   = '<tr><td colspan="4" class="text-muted">Carregando...</td></tr>';

                    fetch(`?id=<?= urlencode($idSelecionado); ?>&action=detalhes&id=${encodeURIComponent(id)}`, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (!data.ok) {
                            tbodyItens.innerHTML = `<tr><td colspan="4" class="text-danger">${(data.msg||'Erro ao carregar')}</td></tr>`;
                            return;
                        }

                        spanFilial.textContent = data.head.filial || '-';
                        spanStatus.textContent = (data.head.status || '-').replace(/_/g,' ').replace(/\b\w/g, c => c.toUpperCase());
                        detObs.textContent     = data.head.observ && data.head.observ.trim() !== '' ? data.head.observ : '—';

                        if (!data.itens || data.itens.length === 0) {
                            tbodyItens.innerHTML = '<tr><td colspan="4" class="text-muted">Sem itens.</td></tr>';
                            return;
                        }

                        let html = '';
                        data.itens.forEach(function(it){
                            const v = typeof it.valor === 'number' ? it.valor.toFixed(2) : '-';
                            html += `
                                <tr>
                                    <td>${escapeHtml(it.codigo || '-')}</td>
                                    <td>${escapeHtml(it.nome   || '-')}</td>
                                    <td>${Number(it.qtd||0)}</td>
                                    <td>${v}</td>
                                </tr>
                            `;
                        });
                        tbodyItens.innerHTML = html;
                    })
                    .catch(() => {
                        tbodyItens.innerHTML = '<tr><td colspan="4" class="text-danger">Falha ao carregar.</td></tr>';
                    });
                });
            });

            // helper básico p/ evitar XSS em strings
            function escapeHtml(s) {
                return String(s)
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }
        })();
    </script>

    <!-- Place this tag in your head or just before your close body tag. -->
    <script async defer src="https://buttons.github.io/buttons.js"></script>
</body>

</html>

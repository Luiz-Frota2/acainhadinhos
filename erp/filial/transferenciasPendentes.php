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

/* ============================================
   🔸 MODO AJAX (DETALHES & ALTERAR STATUS + ESTOQUE)
   ============================================ */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'detalhes') {
    header('Content-Type: application/json; charset=utf-8');

    $sid = (int)($_GET['solicitacao_id'] ?? 0);
    if ($sid <= 0) {
        echo json_encode(['ok' => false, 'erro' => 'ID inválido']);
        exit;
    }

    try {
        $sqlItens = "
            SELECT 
                COALESCE(i.codigo_produto, '') AS codigo_produto,
                COALESCE(i.nome_produto, '')   AS nome_produto,
                COALESCE(i.quantidade, 0)      AS quantidade
            FROM solicitacoes_b2b_itens i
            WHERE i.solicitacao_id = :sid
            ORDER BY i.id ASC
        ";
        $st = $pdo->prepare($sqlItens);
        $st->execute([':sid' => $sid]);
        $itens = $st->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['ok' => true, 'itens' => $itens]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax'] ?? '') === 'status') {
    header('Content-Type: application/json; charset=utf-8');

    $sid  = (int)($_POST['transferencia_id'] ?? 0);
    $acao = $_POST['acao'] ?? '';

    if ($sid <= 0 || !in_array($acao, ['confirmar_envio', 'cancelar'], true)) {
        echo json_encode(['ok' => false, 'erro' => 'Parâmetros inválidos']);
        exit;
    }

    $novoStatus = $acao === 'confirmar_envio' ? 'em_transito' : 'cancelar';

    try {
        $pdo->beginTransaction();

        // 🔒 Bloqueia linha e carrega matriz/solicitante + status atual
        $sel = $pdo->prepare("SELECT id_matriz, id_solicitante, status FROM solicitacoes_b2b WHERE id = :id FOR UPDATE");
        $sel->execute([':id' => $sid]);
        $cab = $sel->fetch(PDO::FETCH_ASSOC);
        if (!$cab) {
            throw new RuntimeException('Solicitação não encontrada.');
        }

        $statusAtual     = $cab['status'] ?? '';
        $idMatriz        = $cab['id_matriz'] ?? '';
        $idSolicitante   = $cab['id_solicitante'] ?? '';

        // Só permite a ação a partir de 'aprovada' (mantendo seu critério de Aguardando)
        if ($statusAtual !== 'aprovada') {
            throw new RuntimeException('Solicitação não está aprovada ou já foi processada.');
        }

        // Atualiza status
        $up = $pdo->prepare("UPDATE solicitacoes_b2b SET status = :st, enviada_em = NOW() WHERE id = :id");
        $up->execute([':st' => $novoStatus, ':id' => $sid]);
        if ($up->rowCount() === 0) {
            throw new RuntimeException('Falha ao atualizar status.');
        }

        // Se for confirmar envio → movimenta estoque (MATRIZ - , FILIAL +)
        $movCount = 0;
        if ($acao === 'confirmar_envio') {
            // Itens da solicitação
            $stI = $pdo->prepare("
                SELECT COALESCE(codigo_produto,'') AS codigo_produto,
                       COALESCE(quantidade,0)      AS quantidade
                FROM solicitacoes_b2b_itens
                WHERE solicitacao_id = :sid
                ORDER BY id ASC
            ");
            $stI->execute([':sid' => $sid]);
            $itens = $stI->fetchAll(PDO::FETCH_ASSOC);

            foreach ($itens as $it) {
                $cod = trim((string)$it['codigo_produto']);
                $qtd = (int)$it['quantidade'];
                if ($qtd <= 0 || $cod === '') continue;

                // 🟣 Ajuste aqui se o nome da coluna no estoque NÃO for codigo_produto (ex.: sku)
                // MATRIZ: decrementa
                $upMat = $pdo->prepare("
                    UPDATE estoque 
                       SET quantidade = CASE 
                           WHEN quantidade >= :qtd THEN quantidade - :qtd 
                           ELSE 0 END
                     WHERE empresa_id = :emp AND codigo_produto = :cod
                     LIMIT 1
                ");
                $upMat->execute([
                    ':qtd' => $qtd,
                    ':emp' => $idMatriz,
                    ':cod' => $cod
                ]);

                // FILIAL: incrementa (update ou insert)
                $upFil = $pdo->prepare("
                    UPDATE estoque 
                       SET quantidade = quantidade + :qtd
                     WHERE empresa_id = :emp AND codigo_produto = :cod
                     LIMIT 1
                ");
                $upFil->execute([
                    ':qtd' => $qtd,
                    ':emp' => $idSolicitante,
                    ':cod' => $cod
                ]);

                if ($upFil->rowCount() === 0) {
                    // não existia — cria com a quantidade
                    $insFil = $pdo->prepare("
                        INSERT INTO estoque (empresa_id, codigo_produto, quantidade)
                        VALUES (:emp, :cod, :qtd)
                    ");
                    $insFil->execute([
                        ':emp' => $idSolicitante,
                        ':cod' => $cod,
                        ':qtd' => $qtd
                    ]);
                }

                $movCount++;
            }
        }

        $pdo->commit();

        echo json_encode([
            'ok' => true,
            'status' => $novoStatus,
            'movimentos' => $movCount
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
    }
    exit;
}

/* ==========================================================
   🟢 LISTAGEM — Solicitações aprovadas de Filiais c/ estoque
   - status = 'aprovada'
   - id_matriz = empresa (sessão/URL)
   - solicitante é Filial da mesma empresa (unidades.tipo = 'Filial')
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
              SELECT 1 FROM estoque e WHERE e.empresa_id = s.id_solicitante
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

// Helpers simples
function dtBr(?string $dt) {
    if (!$dt) return '-';
    $t = strtotime($dt); if (!$t) return '-';
    return date('d/m/Y H:i', $t);
}
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

    <!-- Icons -->
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
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- Sidebar (mantido) -->
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
                    <li class="menu-item">
                        <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Dashboard</div>
                        </a>
                    </li>

                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Administração Filiais</span></li>

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
                            <li class="menu-item">
                                <a href="./contasFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Pagamentos Solic.</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./produtosSolicitados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Produtos Solicitados</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./produtosEnviados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Produtos Enviados</div>
                                </a>
                            </li>
                            <li class="menu-item active">
                                <a href="./transferenciasPendentes.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Transf. Pendentes</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./historicoTransferencias.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Histórico Transf.</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./estoqueMatriz.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Estoque Matriz</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./relatoriosB2B.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Relatórios B2B</div>
                                </a>
                            </li>
                        </ul>
                    </li>

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
                </ul>
            </aside>
            <!-- / Sidebar -->

            <div class="layout-page">
                <!-- Navbar (mantida) -->
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                            <i class="bx bx-menu bx-sm"></i>
                        </a>
                    </div>
                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <div class="navbar-nav align-items-center"><div class="nav-item d-flex align-items-center"></div></div>
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
                                    <li><div class="dropdown-divider"></div></li>
                                    <li><a class="dropdown-item" href="./contaUsuario.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-user me-2"></i><span class="align-middle">Minha Conta</span></a></li>
                                    <li><a class="dropdown-item" href="#"><i class="bx bx-cog me-2"></i><span class="align-middle">Configurações</span></a></li>
                                    <li><div class="dropdown-divider"></div></li>
                                    <li><a class="dropdown-item" href="../logout.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-power-off me-2"></i><span class="align-middle">Sair</span></a></li>
                                </ul>
                            </li>
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

                    <!-- Tabela -->
                    <div class="card">
                        <h5 class="card-header">Lista de Transferências</h5>
                        <div class="table-responsive text-nowrap">
                            <table class="table table-hover" id="tabela-transferencias">
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
                                    <?php
                                        // status no banco é 'aprovada' → mostrar "Aguardando" cinza
                                        $statusTexto = 'Aguardando';
                                        $statusClasse = 'bg-label-secondary'; // cinza do tema
                                    ?>
                                    <tr data-row-id="<?= (int)$row['id'] ?>">
                                        <td><strong><?= (int)$row['id'] ?></strong></td>
                                        <td><?= htmlspecialchars($row['filial_nome'] ?? '-') ?></td>
                                        <td><?= (int)$row['itens'] ?></td>
                                        <td><?= (int)$row['qtd_total'] ?></td>
                                        <td><?= dtBr($row['created_at']) ?></td>
                                        <td>
                                            <span class="badge <?= $statusClasse ?> status-badge"><?= $statusTexto ?></span>
                                        </td>
                                        <td class="text-end actions">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-secondary btn-detalhes"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalDetalhes"
                                                data-id="<?= (int)$row['id'] ?>"
                                                data-codigo="TR-<?= (int)$row['id'] ?>"
                                                data-filial="<?= htmlspecialchars($row['filial_nome'] ?? '-') ?>"
                                                data-status="<?= $statusTexto ?>">
                                                Detalhes
                                            </button>

                                            <button
                                                type="button"
                                                class="btn btn-sm btn-warning btn-acao"
                                                data-acao="confirmar_envio"
                                                data-id="<?= (int)$row['id'] ?>">
                                                Confirmar envio
                                            </button>

                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-danger btn-acao"
                                                data-acao="cancelar"
                                                data-id="<?= (int)$row['id'] ?>">
                                                Cancelar
                                            </button>
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
                                                    <th>codigo do produto</th>
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

                    <!-- JS da LISTAGEM: carrega detalhes e envia ações -->
                    <script>
                        (function(){
                            const tabela = document.getElementById('tabela-transferencias');

                            // Abrir modal com detalhes
                            const modalEl = document.getElementById('modalDetalhes');
                            if (modalEl) {
                                modalEl.addEventListener('show.bs.modal', function (event) {
                                    const btn = event.relatedTarget;
                                    if (!btn) return;

                                    const id  = btn.getAttribute('data-id');
                                    const cod = btn.getAttribute('data-codigo') || '-';
                                    const fil = btn.getAttribute('data-filial') || '-';
                                    const sts = btn.getAttribute('data-status') || '-';

                                    document.getElementById('det-codigo').textContent = cod;
                                    document.getElementById('det-filial').textContent = fil;
                                    document.getElementById('det-status').textContent = sts;

                                    const tbody = document.getElementById('det-itens');
                                    tbody.innerHTML = '<tr><td colspan="3" class="text-muted">Carregando...</td></tr>';

                                    const url = new URL(window.location.href);
                                    url.searchParams.set('ajax', 'detalhes');
                                    url.searchParams.set('solicitacao_id', id);

                                    fetch(url.toString(), { credentials: 'same-origin' })
                                        .then(r => r.json())
                                        .then(data => {
                                            if (!data.ok) throw new Error(data.erro || 'Falha ao carregar itens');
                                            const itens = data.itens || [];
                                            if (!itens.length) {
                                                tbody.innerHTML = '<tr><td colspan="3" class="text-muted">Sem itens para esta solicitação.</td></tr>';
                                                return;
                                            }
                                            tbody.innerHTML = itens.map(it => {
                                                const cod = (it.codigo_produto || '—');
                                                const nome = (it.nome_produto || '—');
                                                const qtd  = (it.quantidade || 0);
                                                return `<tr>
                                                    <td>${cod}</td>
                                                    <td>${nome}</td>
                                                    <td>${qtd}</td>
                                                </tr>`;
                                            }).join('');
                                        })
                                        .catch(err => {
                                            tbody.innerHTML = `<tr><td colspan="3" class="text-danger">Erro: ${err.message}</td></tr>`;
                                        });
                                });
                            }

                            // Ações: confirmar envio / cancelar
                            tabela?.addEventListener('click', function(e){
                                const el = e.target.closest('.btn-acao');
                                if (!el) return;

                                const acao = el.getAttribute('data-acao');
                                const id = el.getAttribute('data-id');

                                if (!acao || !id) return;

                                if (acao === 'cancelar' && !confirm('Confirmar cancelamento desta transferência?')) return;
                                if (acao === 'confirmar_envio' && !confirm('Confirmar envio desta transferência?')) return;

                                const formData = new FormData();
                                formData.append('ajax', 'status');
                                formData.append('acao', acao);
                                formData.append('transferencia_id', id);

                                fetch(window.location.href, {
                                    method: 'POST',
                                    credentials: 'same-origin',
                                    body: formData
                                })
                                .then(r => r.json())
                                .then(data => {
                                    if (!data.ok) throw new Error(data.erro || 'Falha ao atualizar');

                                    // Remove a linha da tabela (deixou de ser "aprovada")
                                    const tr = e.target.closest('tr');
                                    if (tr && tr.parentNode) tr.parentNode.removeChild(tr);

                                    if (acao === 'confirmar_envio') {
                                        alert('Status atualizado para "em_transito" e estoque movimentado.');
                                    } else {
                                        alert('Status atualizado para "cancelar".');
                                    }
                                })
                                .catch(err => {
                                    alert('Erro: ' + err.message);
                                });
                            });

                            // Botão "Detalhes"
                            tabela?.addEventListener('click', function(e){
                                const btnDet = e.target.closest('.btn-detalhes');
                                if (!btnDet) return;
                            });
                        })();
                    </script>

                </div>
                <!-- / Content -->

                <!-- Footer -->
                <footer class="content-footer footer bg-footer-theme text-center">
                    <div class="container-xxl d-flex  py-2 flex-md-row flex-column justify-content-center">
                        <div class="mb-2 mb-md-0">
                            &copy; <script>document.write(new Date().getFullYear());</script>, <strong>Açaínhadinhos</strong>. Todos os direitos reservados.
                            Desenvolvido por <strong>CodeGeek</strong>.
                        </div>
                    </div>
                </footer>
                <!-- / Footer -->

                <div class="content-backdrop fade"></div>
            </div>
        </div>
    </div>

    <!-- Overlay -->
    <div class="layout-overlay layout-menu-toggle"></div>

    <!-- Core JS -->
    <script src="../../js/saudacao.js"></script>
    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../assets/vendor/js/menu.js"></script>
    <!-- Vendors JS -->
    <script src="../../assets/vendor/libs/apex-charts/apexcharts.js"></script>
    <!-- Main JS -->
    <script src="../../assets/js/main.js"></script>
    <!-- Page JS -->
    <script src="../../assets/js/dashboards-analytics.js"></script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>
</body>
</html>

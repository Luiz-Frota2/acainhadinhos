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

// ✅ Buscar nome e tipo do usuário logado
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
    } else {
        echo "<script>alert('Usuário não encontrado.'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar usuário: " . $e->getMessage() . "'); history.back();</script>";
    exit;
}

// ✅ Valida o tipo de empresa e o acesso permitido
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

    $logoEmpresa = !empty($empresaSobre['imagem'])
        ? "../../assets/img/empresa/" . $empresaSobre['imagem']
        : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png"; // fallback
}

/* =========================================================
   B2B: Nova Solicitação
   - Produtos virão do estoque da MATRIZ (empresa_id = 'principal_1')
   - Enviar no form o ID da UNIDADE solicitante (GET solicitante ou SESSION)
   ========================================================= */
$empresa_matriz_id = 'principal_1'; // <- matriz fixa
$solicitanteId = $_GET['solicitante'] ?? ($tipoSession !== 'principal' ? $idEmpresaSession : ''); // se a matriz estiver abrindo pra outra unidade, passe ?solicitante=unidade_x

// Carrega produtos do estoque da matriz
$produtos = [];
try {
    $sql = "SELECT 
                id, fornecedor_id, empresa_id, codigo_produto, nome_produto, categoria_produto,
                quantidade_produto, preco_produto, preco_custo, status_produto,
                ncm, cest, cfop, origem, tributacao, unidade, codigo_barras, informacoes_adicionais
            FROM estoque
            WHERE empresa_id = :empresa AND (status_produto IS NULL OR status_produto = '' OR LOWER(status_produto) IN ('ativo','disponivel'))
            ORDER BY nome_produto ASC";
    $st = $pdo->prepare($sql);
    $st->execute([':empresa' => $empresa_matriz_id]);
    $produtos = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $produtos = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
    data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>ERP - Matriz | Nova Solicitação</title>

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
    <script src="../../assets/js/config.js"></script>

    <style>
        /* Ajustes finos para a tabela/lista e resumo */
        .table thead th {
            white-space: nowrap;
        }

        .table tbody td {
            vertical-align: middle;
        }

        .sticky-actions {
            position: sticky;
            bottom: 0;
            z-index: 2;
        }

        .badge-soft {
            background: rgba(108, 124, 247, .12);
            color: #3f3d99;
            border-radius: 10px;
            padding: .25rem .5rem;
        }

        .qty-input {
            width: 90px;
        }

        .w-140 {
            width: 140px;
        }

        .truncate {
            max-width: 360px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .muted {
            color: #92a0b3;
        }

        .pointer {
            cursor: pointer;
        }
    </style>
</head>

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
                        <span class="menu-header-text">Administração</span>
                    </li>

                    <li class="menu-item open active">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div data-i18n="B2B">B2B - Matriz</div>
                        </a>
                        <ul class="menu-sub active">
                            <li class="menu-item">
                                <a href="./produtosSolicitados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Produtos Solicitados</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./produtosRecebidos.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Produtos Recebidos</div>
                                </a>
                            </li>
                            <li class="menu-item active">
                                <a href="./novaSolicitacao.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Nova Solicitação</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./statusTransferencia.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Status da Transf.</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./estoqueMatriz.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Estoque da Matriz</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./solicitarPagamentoConta.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Solicitar Pagamento</div>
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
                                <a href="./vendas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Vendas">Vendas por Filial</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./maisVendidos.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="MaisVendidos">Mais Vendidos</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./vendasPeriodo.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Pedidos">Vendas por Período</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- Diversos -->
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
                    $idLogado = $_SESSION['empresa_id'] ?? '';

                    if ($tipoLogado === 'principal') {
                    ?>
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
                    <?php
                    } elseif (in_array($tipoLogado, ['filial', 'franquia', 'unidade'])) {
                    ?>
                        <li class="menu-item">
                            <a href="../matriz/index.php?id=<?= urlencode($idLogado) ?>" class="menu-link">
                                <i class="menu-icon tf-icons bx bx-cog"></i>
                                <div data-i18n="Authentications">Matriz</div>
                            </a>
                        </li>
                    <?php
                    }
                    ?>
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
                <nav
                    class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
                    id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                            <i class="bx bx-menu bx-sm"></i>
                        </a>
                    </div>

                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <div class="navbar-nav align-items-center">
                            <div class="nav-item d-flex align-items-center">
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
                        </ul>

                    </div>
                </nav>
                <!-- / Navbar -->

                <!-- Content -->
                <div class="container-xxl flex-grow-1 container-p-y">

                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                        <div>
                            <h4 class="fw-bold mb-1">Nova Solicitação de Produto</h4>
                            <div class="text-muted">
                                Estoque da <span class="badge-soft">Matriz (<?= htmlspecialchars($empresa_matriz_id) ?>)</span>
                                <?php if ($solicitanteId): ?>
                                    &middot; Solicitante:
                                    <span class="badge-soft"><?= htmlspecialchars($solicitanteId) ?></span>
                                <?php else: ?>
                                    &middot; <span class="muted">Informe o solicitante via <code>?solicitante=unidade_x</code> ou o sistema usará o da sessão.</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <input id="searchInput" class="form-control" style="min-width:260px" type="text" placeholder="Pesquisar produto, código, categoria..." />
                        </div>
                    </div>

                    <form id="formSolicitacao" method="post" action="./actions/novaSolicitacao_Salvar.php">
                        <!-- Metadados da solicitação -->
                        <input type="hidden" name="id_matriz" value="<?= htmlspecialchars($empresa_matriz_id) ?>">
                        <input type="hidden" name="id_solicitante" value="<?= htmlspecialchars($solicitanteId ?: $idEmpresaSession) ?>">
                        <input type="hidden" name="id_pagina" value="<?= htmlspecialchars($idSelecionado) ?>">

                        <div class="card">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:40px;">
                                                <input type="checkbox" id="chkAll" class="form-check-input" title="Selecionar todos">
                                            </th>
                                            <th>Produto</th>
                                            <th class="d-none d-md-table-cell">Categoria</th>
                                            <th class="d-none d-lg-table-cell">Cód. Produto</th>
                                            <th>Estoque</th>
                                            <th class="d-none d-lg-table-cell">Unid.</th>
                                            <th class="text-end">Preço</th>
                                            <th class="text-center">Qtd. Solicitar</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>

                            <div class="table-responsive" style="max-height:60vh;">
                                <table class="table table-striped" id="tabelaProdutos">
                                    <tbody>
                                        <?php if (!empty($produtos)): ?>
                                            <?php foreach ($produtos as $p):
                                                $rowId = (int)$p['id'];
                                                $nome = $p['nome_produto'] ?? '';
                                                $cat  = $p['categoria_produto'] ?? '';
                                                $cod  = $p['codigo_produto'] ?? '';
                                                $estoque = (int)($p['quantidade_produto'] ?? 0);
                                                $un = $p['unidade'] ?? 'UN';
                                                $preco = (float)($p['preco_produto'] ?? 0);
                                            ?>
                                                <tr>
                                                    <td style="width:40px;">
                                                        <input type="checkbox" class="form-check-input chkItem" name="itens[<?= $rowId ?>][selecionado]" value="1">
                                                        <input type="hidden" name="itens[<?= $rowId ?>][produto_id]" value="<?= $rowId ?>">
                                                        <input type="hidden" name="itens[<?= $rowId ?>][nome]" value="<?= htmlspecialchars($nome) ?>">
                                                        <input type="hidden" name="itens[<?= $rowId ?>][codigo]" value="<?= htmlspecialchars($cod) ?>">
                                                        <input type="hidden" name="itens[<?= $rowId ?>][preco]" value="<?= number_format($preco, 2, '.', '') ?>">
                                                        <input type="hidden" name="itens[<?= $rowId ?>][unidade]" value="<?= htmlspecialchars($un) ?>">
                                                    </td>
                                                    <td>
                                                        <div class="fw-semibold truncate" title="<?= htmlspecialchars($nome) ?>">
                                                            <?= htmlspecialchars($nome) ?>
                                                        </div>
                                                        <div class="small muted">
                                                            Código: <span class="text-body"><?= htmlspecialchars($cod) ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="d-none d-md-table-cell"><?= htmlspecialchars($cat) ?></td>
                                                    <td class="d-none d-lg-table-cell"><?= htmlspecialchars($cod) ?></td>
                                                    <td>
                                                        <span class="badge bg-label-primary"><?= (int)$estoque ?></span>
                                                    </td>
                                                    <td class="d-none d-lg-table-cell"><?= htmlspecialchars($un) ?></td>
                                                    <td class="text-end">R$ <?= number_format($preco, 2, ',', '.') ?></td>
                                                    <td class="text-center">
                                                        <input type="number" min="0" step="1" class="form-control form-control-sm qty-input"
                                                            name="itens[<?= $rowId ?>][quantidade]" placeholder="0">
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-4">
                                                    Nenhum produto disponível no estoque da matriz.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="card-footer bg-white">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <button type="button" id="prevPage" class="btn btn-sm btn-outline-primary">Anterior</button>
                                        <div id="paginacao" class="d-inline-flex align-items-center"></div>
                                        <button type="button" id="nextPage" class="btn btn-sm btn-outline-primary">Próxima</button>
                                    </div>

                                    <div class="text-end ms-auto">
                                        <div class="small muted">Itens selecionados: <span id="countSel">0</span></div>
                                        <div class="fw-semibold">Valor estimado: <span id="valorTotal">R$ 0,00</span></div>
                                    </div>

                                    <button type="submit" id="btnEnviar" class="btn btn-primary w-140" disabled>
                                        <i class="bx bx-send me-1"></i> Enviar Solicitação
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>

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
                            Desenvolvido por <strong>Lucas Correa</strong>.
                        </div>
                    </div>
                </footer>

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
    <script src="../../assets/js/dashboards-analytics.js"></script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>

    <!-- Pesquisa + Paginação + Seleção -->
    <script>
        // ====== Selecionar todos ======
        const chkAll = document.getElementById('chkAll');
        const tableBody = document.querySelector('#tabelaProdutos tbody');
        const btnEnviar = document.getElementById('btnEnviar');
        const countSel = document.getElementById('countSel');
        const valorTotalSpan = document.getElementById('valorTotal');

        function recalcResumo() {
            let count = 0;
            let total = 0;
            const rows = Array.from(tableBody.querySelectorAll('tr'));
            rows.forEach(tr => {
                const chk = tr.querySelector('.chkItem');
                const qty = tr.querySelector('.qty-input');
                const precoHidden = tr.querySelector('input[name*="[preco]"]');
                if (!chk || !qty || !precoHidden) return;

                const selecionado = chk.checked;
                const qtd = parseInt(qty.value || '0', 10);
                const preco = parseFloat(precoHidden.value || '0');

                if (selecionado && qtd > 0) {
                    count++;
                    total += (qtd * preco);
                }
            });

            countSel.textContent = count;
            valorTotalSpan.textContent = total.toLocaleString('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            });
            btnEnviar.disabled = (count === 0);
        }

        if (chkAll) {
            chkAll.addEventListener('change', () => {
                const checks = tableBody.querySelectorAll('.chkItem');
                checks.forEach(c => c.checked = chkAll.checked);
                recalcResumo();
            });
        }

        tableBody.addEventListener('input', (e) => {
            if (e.target.classList.contains('qty-input')) {
                // se houver quantidade > 0 e não estiver marcado, marca automaticamente
                const tr = e.target.closest('tr');
                const chk = tr.querySelector('.chkItem');
                const v = parseInt(e.target.value || '0', 10);
                if (chk) chk.checked = v > 0;
                recalcResumo();
            }
        });

        tableBody.addEventListener('change', (e) => {
            if (e.target.classList.contains('chkItem')) {
                // se desmarcar, zera quantidade
                const tr = e.target.closest('tr');
                const qty = tr.querySelector('.qty-input');
                if (!e.target.checked && qty) qty.value = '';
                recalcResumo();
            }
        });

        // ====== Paginação + Filtro (padrão solicitado) ======
        const searchInput = document.getElementById('searchInput');
        const allRows = Array.from(document.querySelectorAll('#tabelaProdutos tbody tr'));
        const rowsPerPage = 10;
        let currentPage = 1;

        function renderTable() {
            const filtro = searchInput.value.trim().toLowerCase();

            // 1. Filtra as linhas com base no texto das colunas
            const filteredRows = allRows.filter(row => {
                if (!filtro) return true;
                return Array.from(row.cells).some(cell =>
                    cell.textContent.toLowerCase().includes(filtro)
                );
            });

            // 2. Calcula paginação
            const totalPages = Math.ceil(filteredRows.length / rowsPerPage) || 1;
            if (currentPage > totalPages) currentPage = totalPages;
            const startIndex = (currentPage - 1) * rowsPerPage;
            const endIndex = startIndex + rowsPerPage;

            // 3. Oculta todas as linhas
            allRows.forEach(row => row.style.display = 'none');

            // 4. Exibe apenas as filtradas da página atual
            filteredRows.slice(startIndex, endIndex).forEach(row => row.style.display = '');

            // 5. Renderiza botões de paginação
            const paginacao = document.getElementById('paginacao');
            paginacao.innerHTML = '';
            for (let i = 1; i <= totalPages; i++) {
                const btn = document.createElement('button');
                btn.className = 'btn btn-sm ' + (i === currentPage ? 'btn-primary' : 'btn-outline-primary');
                btn.style.marginRight = '5px';
                btn.textContent = i;
                btn.onclick = () => {
                    currentPage = i;
                    renderTable();
                };
                paginacao.appendChild(btn);
            }

            // 6. Atualiza botões de navegação
            document.getElementById('prevPage').disabled = currentPage === 1;
            document.getElementById('nextPage').disabled = currentPage === totalPages || totalPages === 0;
        }

        document.getElementById('prevPage').addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                renderTable();
            }
        });

        document.getElementById('nextPage').addEventListener('click', () => {
            currentPage++;
            renderTable();
        });

        searchInput.addEventListener('input', () => {
            currentPage = 1;
            renderTable();
        });

        // Validação no submit: pelo menos 1 item com qtd > 0
        document.getElementById('formSolicitacao').addEventListener('submit', (e) => {
            let ok = false;
            const rows = Array.from(tableBody.querySelectorAll('tr'));
            rows.forEach(tr => {
                const chk = tr.querySelector('.chkItem');
                const qty = tr.querySelector('.qty-input');
                if (chk && qty) {
                    const selecionado = chk.checked;
                    const qtd = parseInt(qty.value || '0', 10);
                    if (selecionado && qtd > 0) ok = true;
                }
            });
            if (!ok) {
                e.preventDefault();
                alert('Selecione pelo menos um item com quantidade maior que zero.');
            }
        });

        // Inicializa a tabela e o resumo
        renderTable();
        recalcResumo();
    </script>
</body>

</html>
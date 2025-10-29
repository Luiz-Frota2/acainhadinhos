<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

/* ==================== Sessão & parâmetros ==================== */
$idSelecionado = $_GET['id'] ?? '';
if (!$idSelecionado) {
    header("Location: .././login.php");
    exit;
}

if (!isset($_SESSION['usuario_logado'], $_SESSION['empresa_id'], $_SESSION['tipo_empresa'], $_SESSION['usuario_id'])) {
    header("Location: .././login.php?id=" . urlencode($idSelecionado));
    exit;
}

/* ==================== Conexão ==================== */
require '../../assets/php/conexao.php';

/* ==================== Usuário logado ==================== */
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id  = (int)$_SESSION['usuario_id'];

try {
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    $stmt->execute([':id' => $usuario_id]);
    if ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $nomeUsuario = $u['usuario'];
        $tipoUsuario = ucfirst($u['nivel']);
    } else {
        echo "<script>alert('Usuário não encontrado.'); location.href='.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar usuário: " . $e->getMessage() . "'); history.back();</script>";
    exit;
}

/* ==================== Permissão ==================== */
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
    echo "<script>alert('Acesso negado!'); location.href='.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
    exit;
}

/* ==================== Logo ==================== */
try {
    $s = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :i LIMIT 1");
    $s->execute([':i' => $idSelecionado]);
    $sobre = $s->fetch(PDO::FETCH_ASSOC);
    $logoEmpresa = !empty($sobre['imagem']) ? "../../assets/img/empresa/" . $sobre['imagem'] : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png";
}

/* ==================== Listagem das solicitações de pagamento ====================

Estrutura esperada (sugestão) da tabela solicitacoes_pagamento:
- id (PK, bigint)
- id_matriz (varchar)           -> ex.: 'principal_1'
- id_solicitante (varchar)      -> ex.: 'filial_3'
- status (enum): 'pendente','aprovada','paga','recusada','cancelada'
- fornecedor (varchar)
- documento (varchar)           -> nº NF/duplicata/boleto
- descricao (text)
- vencimento (date/datetime)
- valor (decimal(12,2))
- comprovante_url (varchar)     -> opcional (link do boleto/nota)
- created_at (datetime)
- aprovada_em (datetime, null)
- paga_em (datetime, null)
- recusada_em (datetime, null)
*/

$pagamentos = [];   // [id => {...}]
try {
    $sql = "SELECT id, id_matriz, id_solicitante, status, fornecedor, documento, descricao, vencimento, valor, comprovante_url,
                   created_at, aprovada_em, paga_em, recusada_em
            FROM solicitacoes_pagamento
            WHERE id_solicitante = :sol
            ORDER BY created_at DESC";
    $st  = $pdo->prepare($sql);
    $st->execute([':sol' => $idSelecionado]);
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $pid = (int)$r['id'];
        $pagamentos[$pid] = [
            'id'             => $pid,
            'status'         => (string)$r['status'],
            'fornecedor'     => (string)$r['fornecedor'],
            'documento'      => (string)$r['documento'],
            'descricao'      => (string)$r['descricao'],
            'vencimento'     => (string)$r['vencimento'],
            'valor'          => (float)$r['valor'],
            'comprovante'    => (string)($r['comprovante_url'] ?? ''),
            'created_at'     => (string)$r['created_at'],
            'aprovada_em'    => $r['aprovada_em'],
            'paga_em'        => $r['paga_em'],
            'recusada_em'    => $r['recusada_em'],
        ];
    }
} catch (PDOException $e) {
    // mantém vazio em caso de erro
}

/* ===== Helper PHP para badge soft ===== */
function badge_soft_class_pagamento($status)
{
    $s = strtolower((string)$status);
    return match ($s) {
        'pendente'  => 'badge-primary-soft',
        'aprovada'  => 'badge-info-soft',
        'paga'      => 'badge-success-soft',
        'recusada'  => 'badge-danger-soft',
        'cancelada' => 'badge-secondary-soft',
        default     => 'badge-secondary-soft',
    };
}
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>ERP - Solicitações de Pagamento</title>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($logoEmpresa) ?>" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />
    <script src="../../assets/vendor/js/helpers.js"></script>
    <script src="../../assets/js/config.js"></script>
    <style>
        .card {
            border-radius: 14px;
        }

        .table thead th {
            white-space: nowrap;
            font-weight: 600;
            color: #6b7280;
        }

        .table tbody td {
            vertical-align: middle;
        }

        .status-badge {
            text-transform: uppercase;
            letter-spacing: .02em;
            border-radius: 10px;
            padding: .30rem .55rem;
            font-size: .75rem;
            font-weight: 700;
            border: 1px solid transparent;
            display: inline-block;
        }

        .badge-primary-soft {
            color: #4f46e5;
            background: #eef2ff;
            border-color: #e0e7ff;
        }

        /* pendente */
        .badge-info-soft {
            color: #0ea5e9;
            background: #e0f2fe;
            border-color: #bae6fd;
        }

        /* aprovada */
        .badge-success-soft {
            color: #16a34a;
            background: #ecfdf5;
            border-color: #bbf7d0;
        }

        /* paga */
        .badge-danger-soft {
            color: #b91c1c;
            background: #fee2e2;
            border-color: #fecaca;
        }

        /* recusada */
        .badge-secondary-soft {
            color: #475569;
            background: #f1f5f9;
            border-color: #e2e8f0;
        }

        /* cancelada/outros */

        #paginacao button {
            margin-right: 5px;
        }

        td.col-desc {
            max-width: 420px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        td.col-forn-doc {
            max-width: 280px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- ===== SIDEBAR ===== -->
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
                            <div>Dashboard</div>
                        </a>
                    </li>

                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Administração</span></li>
                    <li class="menu-item open active">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div>B2B - Matriz</div>
                        </a>
                        <ul class="menu-sub active">
                            <li class="menu-item"><a class="menu-link" href="./produtosSolicitados.php?id=<?= urlencode($idSelecionado); ?>">
                                    <div>Produtos Solicitados</div>
                                </a></li>
                            <li class="menu-item"><a class="menu-link" href="./statusTransferencia.php?id=<?= urlencode($idSelecionado); ?>">
                                    <div>Status da Transf.</div>
                                </a></li>
                            <li class="menu-item"><a class="menu-link" href="./produtosRecebidos.php?id=<?= urlencode($idSelecionado); ?>">
                                    <div>Produtos Entregues</div>
                                </a></li>
                            <li class="menu-item"><a class="menu-link" href="./novaSolicitacao.php?id=<?= urlencode($idSelecionado); ?>">
                                    <div>Nova Solicitação</div>
                                </a></li>
                            <li class="menu-item"><a class="menu-link" href="./estoqueMatriz.php?id=<?= urlencode($idSelecionado); ?>">
                                    <div>Estoque da Matriz</div>
                                </a></li>
                            <li class="menu-item active"><a class="menu-link" href="./solicitarPagamentoConta.php?id=<?= urlencode($idSelecionado); ?>">
                                    <div>Solicitar Pagamento</div>
                                </a></li>
                        </ul>
                    </li>

                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
                    <li class="menu-item"><a class="menu-link" href="../rh/index.php?id=<?= urlencode($idSelecionado); ?>"><i class="menu-icon tf-icons bx bx-group"></i>
                            <div>RH</div>
                        </a></li>
                    <li class="menu-item"><a class="menu-link" href="../financas/index.php?id=<?= urlencode($idSelecionado); ?>"><i class="menu-icon tf-icons bx bx-dollar"></i>
                            <div>Finanças</div>
                        </a></li>
                    <li class="menu-item"><a class="menu-link" href="../pdv/index.php?id=<?= urlencode($idSelecionado); ?>"><i class="menu-icon tf-icons bx bx-desktop"></i>
                            <div>PDV</div>
                        </a></li>
                    <li class="menu-item"><a class="menu-link" href="../empresa/index.php?id=<?= urlencode($idSelecionado); ?>"><i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div>Empresa</div>
                        </a></li>
                    <li class="menu-item"><a class="menu-link" href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>"><i class="menu-icon tf-icons bx bx-box"></i>
                            <div>Estoque</div>
                        </a></li>
                    <?php
                    $tipoLogado = $_SESSION['tipo_empresa'] ?? '';
                    $idLogado   = $_SESSION['empresa_id']    ?? '';
                    if ($tipoLogado === 'principal') { ?>
                        <li class="menu-item"><a class="menu-link" href="../filial/index.php?id=principal_1"><i class="menu-icon tf-icons bx bx-building"></i>
                                <div>Filial</div>
                            </a></li>
                        <li class="menu-item"><a class="menu-link" href="../franquia/index.php?id=principal_1"><i class="menu-icon tf-icons bx bx-store"></i>
                                <div>Franquias</div>
                            </a></li>
                    <?php } elseif (in_array($tipoLogado, ['filial', 'franquia', 'unidade'])) { ?>
                        <li class="menu-item"><a class="menu-link" href="../matriz/index.php?id=<?= urlencode($idLogado) ?>"><i class="menu-icon tf-icons bx bx-cog"></i>
                                <div>Matriz</div>
                            </a></li>
                    <?php } ?>
                    <li class="menu-item"><a class="menu-link" href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>"><i class="menu-icon tf-icons bx bx-group"></i>
                            <div>Usuários</div>
                        </a></li>
                    <li class="menu-item"><a class="menu-link" target="_blank" href="https://wa.me/92991515710"><i class="menu-icon tf-icons bx bx-support"></i>
                            <div>Suporte</div>
                        </a></li>
                </ul>
            </aside>
            <!-- ===== /SIDEBAR ===== -->

            <div class="layout-page">
                <!-- NAVBAR -->
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)"><i class="bx bx-menu bx-sm"></i></a>
                    </div>
                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <!-- Search -->
                        <div class="navbar-nav align-items-center">
                            <div class="nav-item d-flex align-items-center">
                                <i class="bx bx-search fs-4 lh-0"></i>
                                <input type="text" id="searchInput" class="form-control border-0 shadow-none" placeholder="Pesquisar por #id, status, fornecedor, documento, vencimento..." />
                            </div>
                        </div>
                        <!-- /Search -->
                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="avatar avatar-online"><img src="<?= htmlspecialchars($logoEmpresa, ENT_QUOTES) ?>" class="w-px-40 h-auto rounded-circle" alt="Avatar" /></div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online"><img src="<?= htmlspecialchars($logoEmpresa, ENT_QUOTES) ?>" class="w-px-40 h-auto rounded-circle" alt="Avatar" /></div>
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
                                    <li>
                                        <div class="dropdown-divider"></div>
                                    </li>
                                    <li><a class="dropdown-item" href="../logout.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-power-off me-2"></i><span class="align-middle">Sair</span></a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </nav>
                <!-- /NAVBAR -->

                <!-- CONTENT -->
                <div class="container-xxl flex-grow-1 container-p-y">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="fw-bold mb-0">Solicitações de Pagamento</h4>
                      
                    </div>

                    <div class="card">
                        <div class="table-responsive text-nowrap">
                            <table class="table mb-0 text-nowrap" id="tabelaPagamentos">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Status</th>
                                        <th>Fornecedor / Documento</th>
                                        <th class="text-end">Valor</th>
                                        <th>Vencimento</th>
                                        <th>Criada em</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($pagamentos): foreach ($pagamentos as $pid => $p): ?>
                                            <tr data-pid="<?= (int)$pid ?>">
                                                <td><?= (int)$pid ?></td>
                                                <td>
                                                    <?php
                                                    $status = (string)$p['status'];
                                                    $statusTxt = strtoupper(str_replace('_', ' ', $status));
                                                    $badgeClass = badge_soft_class_pagamento($status);
                                                    ?>
                                                    <span class="status-badge <?= $badgeClass ?>"><?= htmlspecialchars($statusTxt, ENT_QUOTES) ?></span>
                                                </td>
                                                <td class="col-forn-doc">
                                                    <strong><?= htmlspecialchars($p['fornecedor'] ?: '—', ENT_QUOTES) ?></strong>
                                                    <?php if (!empty($p['documento'])): ?>
                                                        <div class="text-muted small">Doc.: <?= htmlspecialchars($p['documento'], ENT_QUOTES) ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">R$ <?= number_format((float)$p['valor'], 2, ',', '.') ?></td>
                                                <td><?= $p['vencimento'] ? date('d/m/Y', strtotime($p['vencimento'])) : '—' ?></td>
                                                <td><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-primary btnDetalhes" data-pid="<?= (int)$pid ?>">Detalhes</button>
                                                </td>
                                            </tr>
                                        <?php endforeach;
                                    else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">Nenhuma solicitação encontrada.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- Paginação -->
                        <div class="d-flex justify-content-start align-items-center gap-2 m-3">
                            <div>
                                <button id="prevPage" class="btn btn-sm btn-outline-primary">Anterior</button>
                                <div id="paginacao" class="btn-group"></div>
                                <button id="nextPage" class="btn btn-sm btn-outline-primary">Próximo</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MODAL DETALHES -->
                <div class="modal fade" id="modalDetalhes" tabindex="-1">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Detalhes da Solicitação <span id="modalPid"></span></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-2">
                                    <div>Status: <span class="status-badge" id="modalStatus"></span></div>
                                    <div class="text-muted">Criada em: <span id="modalCriada"></span></div>
                                    <div class="fw-semibold">Valor: <span id="modalValor"></span></div>
                                    <div>Vencimento: <span id="modalVenc"></span></div>
                                    <div>Fornecedor: <span id="modalForn"></span></div>
                                    <div>Documento: <span id="modalDoc"></span></div>
                                    <div class="mt-2">Descrição:</div>
                                    <div id="modalDesc" class="text-muted"></div>
                                    <div class="mt-2">Comprovante/Arquivo: <span id="modalComp"></span></div>

                                    <div class="row mt-3 g-2">
                                        <div class="col">
                                            <small class="text-muted">Aprovada em:</small>
                                            <div id="modalAprovada">—</div>
                                        </div>
                                        <div class="col">
                                            <small class="text-muted">Paga em:</small>
                                            <div id="modalPaga">—</div>
                                        </div>
                                        <div class="col">
                                            <small class="text-muted">Recusada em:</small>
                                            <div id="modalRecusada">—</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <!-- (somente listagem — ações de aprovar/pagar/recusar ficarão em outra etapa) -->
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Adicionar novo funcionário -->
                <div id="addsetor" class="mt-3 add-category justify-content-center d-flex text-center align-items-center"
                    onclick="window.location.href='novaSolicitacaoPagamento.php?id=<?= $idSelecionado ?>';"
                    style="cursor: pointer;">
                    <i class="tf-icons bx bx-plus me-2"></i>
                    <span>Solicitar novo Pagamento</span>
                </div>

                <!-- /MODAL -->

                <footer class="content-footer footer bg-footer-theme text-center">
                    <div class="container-xxl d-flex py-2 flex-md-row flex-column justify-content-center">
                        <div class="mb-2 mb-md-0">
                            &copy;<script>
                                document.write(new Date().getFullYear());
                            </script>, <strong>Açaínhadinhos</strong>.
                            Todos os direitos reservados. Desenvolvido por <strong>Lucas Correa</strong>.
                        </div>
                    </div>
                </footer>
                <div class="content-backdrop fade"></div>
            </div>
        </div>
    </div>

    <!-- Dados em JSON para detalhes (sem AJAX) -->
    <script id="dadosPagamentos" type="application/json">
        <?= json_encode(['lista' => array_values($pagamentos), 'map' => $pagamentos], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
    </script>

    <!-- JS -->
    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../assets/vendor/js/menu.js"></script>
    <script src="../../assets/vendor/libs/apex-charts/apex-charts.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/dashboards-analytics.js"></script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>

    <script>
        /* ===== Pesquisa + Paginação ===== */
        const searchInput = document.getElementById('searchInput');
        const allRows = Array.from(document.querySelectorAll('#tabelaPagamentos tbody tr'));
        const rowsPerPage = 10;
        let currentPage = 1;

        function renderTable() {
            const filtro = searchInput.value.trim().toLowerCase();

            const filteredRows = allRows.filter(row => {
                if (!filtro) return true;
                return Array.from(row.cells).some(cell => cell.textContent.toLowerCase().includes(filtro));
            });

            const totalPages = Math.ceil(filteredRows.length / rowsPerPage) || 1;
            if (currentPage > totalPages) currentPage = totalPages;
            const startIndex = (currentPage - 1) * rowsPerPage;
            const endIndex = startIndex + rowsPerPage;

            allRows.forEach(row => row.style.display = 'none');
            filteredRows.slice(startIndex, endIndex).forEach(row => row.style.display = '');

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

        // Inicializa a tabela
        renderTable();

        /* ===== Modal de Detalhes ===== */
        const dados = JSON.parse(document.getElementById('dadosPagamentos').textContent || '{}');
        const map = dados?.map || {};
        const lista = dados?.lista || [];

        function formatBRL(v) {
            return (Number(v) || 0).toLocaleString('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            });
        }

        function statusClassSoftPag(s) {
            s = String(s || '').toLowerCase();
            switch (s) {
                case 'pendente':
                    return 'badge-primary-soft';
                case 'aprovada':
                    return 'badge-info-soft';
                case 'paga':
                    return 'badge-success-soft';
                case 'recusada':
                    return 'badge-danger-soft';
                case 'cancelada':
                    return 'badge-secondary-soft';
                default:
                    return 'badge-secondary-soft';
            }
        }

        function statusUpper(s) {
            return String(s || '').replace('_', ' ').toUpperCase();
        }

        function safe(str) {
            return String(str ?? '')
                .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;').replaceAll("'", "&#039;");
        }

        function fmtDate(d) {
            if (!d) return '—';
            const dt = new Date(String(d).replace(' ', 'T'));
            if (isNaN(dt)) return '—';
            return dt.toLocaleDateString('pt-BR');
        }

        $(document).on('click', '.btnDetalhes', function() {
            const pid = parseInt(this.dataset.pid || this.getAttribute('data-pid'), 10);
            const cab = map[pid] || (lista.find(c => parseInt(c.id, 10) === pid)) || null;

            if (!cab) {
                $('#modalPid').text('');
                $('#modalStatus').attr('class', 'status-badge badge-secondary-soft').text('—');
                $('#modalCriada').text('—');
                $('#modalValor').text('—');
                $('#modalVenc').text('—');
                $('#modalForn').text('—');
                $('#modalDoc').text('—');
                $('#modalDesc').text('—');
                $('#modalComp').html('—');
                $('#modalAprovada').text('—');
                $('#modalPaga').text('—');
                $('#modalRecusada').text('—');
            } else {
                $('#modalPid').text('#' + pid);
                $('#modalStatus').attr('class', 'status-badge ' + statusClassSoftPag(cab.status)).text(statusUpper(cab.status));
                $('#modalCriada').text(fmtDate(cab.created_at));
                $('#modalValor').text(formatBRL(cab.valor));
                $('#modalVenc').text(fmtDate(cab.vencimento));
                $('#modalForn').text(safe(cab.fornecedor || '—'));
                $('#modalDoc').text(safe(cab.documento || '—'));
                $('#modalDesc').text(safe(cab.descricao || '—'));

                if (cab.comprovante) {
                    const url = safe(cab.comprovante);
                    $('#modalComp').html(`<a href="${url}" target="_blank" rel="noopener">Abrir arquivo</a>`);
                } else {
                    $('#modalComp').html('—');
                }

                $('#modalAprovada').text(fmtDate(cab.aprovada_em));
                $('#modalPaga').text(fmtDate(cab.paga_em));
                $('#modalRecusada').text(fmtDate(cab.recusada_em));
            }

            const modal = new bootstrap.Modal(document.getElementById('modalDetalhes'));
            modal.show();
        });
    </script>
</body>

</html>
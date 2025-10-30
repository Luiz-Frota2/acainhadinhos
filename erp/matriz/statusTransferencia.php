<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

/* ==================== Sessão & parâmetros ==================== */
$idSelecionado = $_GET['id'] ?? '';
if (!$idSelecionado) {
    echo "<script>alert('Parâmetro id não informado.'); history.back();</script>";
    exit;
}
if (!isset($_SESSION['usuario_logado'], $_SESSION['empresa_id'], $_SESSION['tipo_empresa'], $_SESSION['usuario_id'])) {
    echo "<script>alert('Sessão expirada. Faça login novamente.'); history.back();</script>";
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
        echo "<script>alert('Usuário não encontrado.'); history.back();</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar usuário: " . addslashes($e->getMessage()) . "'); history.back();</script>";
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
    echo "<script>alert('Acesso negado!'); history.back();</script>";
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

/* ==================== Listagem + Itens ==================== */
$solicitacoes = [];
$solicitacaoItens = [];

try {
    $sqlCab = "SELECT id, id_matriz, id_solicitante, status, total_estimado, created_at, aprovada_em, enviada_em, entregue_em
               FROM solicitacoes_b2b
               WHERE id_solicitante = :sol
               ORDER BY created_at DESC";
    $stCab = $pdo->prepare($sqlCab);
    $stCab->execute([':sol' => $idSelecionado]);
    while ($row = $stCab->fetch(PDO::FETCH_ASSOC)) {
        $sid = (int)$row['id'];
        $solicitacoes[$sid] = [
            'id'             => $sid,
            'id_solicitante' => (string)$row['id_solicitante'],
            'status'         => (string)$row['status'],
            'total'          => (float)$row['total_estimado'],
            'created_at'     => (string)$row['created_at'],
            'aprovada_em'    => $row['aprovada_em'],
            'enviada_em'     => $row['enviada_em'],
            'entregue_em'    => $row['entregue_em'],
            'qtd_total'      => 0,
            'produtos_str'   => '—',
        ];
        $solicitacaoItens[$sid] = [];
    }

    if ($solicitacoes) {
        $idsArray = array_map('intval', array_keys($solicitacoes));
        $ids = implode(',', $idsArray);

        $sqlIt = "SELECT solicitacao_id, produto_id, codigo_produto, nome_produto, unidade, preco_unitario, quantidade, subtotal
                  FROM solicitacoes_b2b_itens
                  WHERE solicitacao_id IN ($ids)
                  ORDER BY solicitacao_id ASC, id ASC";
        $stIt = $pdo->query($sqlIt);

        $qtdPorSid   = array_fill_keys($idsArray, 0);
        $nomesPorSid = array_fill_keys($idsArray, []);

        while ($it = $stIt->fetch(PDO::FETCH_ASSOC)) {
            $sid = (int)$it['solicitacao_id'];

            $solicitacaoItens[$sid][] = [
                'produto_id' => (int)$it['produto_id'],
                'codigo'     => (string)$it['codigo_produto'],
                'nome'       => (string)$it['nome_produto'],
                'unidade'    => (string)$it['unidade'],
                'preco'      => (float)$it['preco_unitario'],
                'quantidade' => (int)$it['quantidade'],
                'subtotal'   => (float)$it['subtotal']
            ];

            $qtdPorSid[$sid] += (int)$it['quantidade'];

            $nome = trim((string)$it['nome_produto']);
            if ($nome !== '' && !in_array($nome, $nomesPorSid[$sid], true)) {
                $nomesPorSid[$sid][] = $nome;
            }
        }

        foreach ($idsArray as $sid) {
            $solicitacoes[$sid]['qtd_total'] = (int)($qtdPorSid[$sid] ?? 0);

            $nomes  = $nomesPorSid[$sid] ?? [];
            $totalN = count($nomes);
            if ($totalN === 0) {
                $solicitacoes[$sid]['produtos_str'] = '—';
            } else {
                $preview = array_slice($nomes, 0, 3);
                $extra   = $totalN - count($preview);
                $str     = implode(', ', $preview);
                if ($extra > 0) $str .= " +{$extra}";
                $solicitacoes[$sid]['produtos_str'] = $str;
            }
        }
    }
} catch (PDOException $e) {
    // silencioso
}
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>ERP - Status de Transferência</title>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($logoEmpresa) ?>" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" />
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

        /* ====== BADGES (soft) ====== */
        .status-badge {
            text-transform: uppercase;
            letter-spacing: .02em;
            border-radius: 10px;
            padding: .3rem .55rem;
            font-size: .75rem;
            font-weight: 700;
            border: 1px solid transparent;
            display: inline-block;
        }

        /* primary (roxinho suave) */
        .badge-primary-soft {
            color: #4f46e5;
            background: #eef2ff;
            border-color: #e0e7ff;
        }

        /* success (verde suave) */
        .badge-success-soft {
            color: #16a34a;
            background: #ecfdf5;
            border-color: #bbf7d0;
        }

        /* warning (laranja suave) – caso precise */
        .badge-warning-soft {
            color: #b45309;
            background: #fff7ed;
            border-color: #fed7aa;
        }

        /* danger (vermelho suave) */
        .badge-danger-soft {
            color: #b91c1c;
            background: #fee2e2;
            border-color: #fecaca;
        }

        /* info (azul claro suave) – caso precise */
        .badge-info-soft {
            color: #0369a1;
            background: #e0f2fe;
            border-color: #bae6fd;
        }

        /* secondary (cinza suave) */
        .badge-secondary-soft {
            color: #475569;
            background: #f1f5f9;
            border-color: #e2e8f0;
        }

        #paginacao button {
            margin-right: 5px;
        }

        td.col-produtos {
            max-width: 420px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .actions-group .btn {
            border-radius: 8px;
        }

        .actions-group .btn+.btn {
            margin-left: 6px;
        }


        .meta-line {
            font-size: .86rem;
            color: #64748b;
        }

        .meta-line span {
            margin-right: .5rem;
        }

        .modal .modal-title {
            font-weight: 600;
        }

        .btn-primary {
            background: #635bff;
            border-color: #635bff;
        }

        .btn-primary:hover {
            background: #524cf2;
            border-color: #524cf2;
        }

        .btn-outline-secondary {
            color: #64748b;
            border-color: #cbd5e1;
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
                            <li class="menu-item active"><a class="menu-link" href="./statusTransferencia.php?id=<?= urlencode($idSelecionado); ?>">
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
                            <li class="menu-item"><a class="menu-link" href="./solicitarPagamentoConta.php?id=<?= urlencode($idSelecionado); ?>">
                                    <div>Pag. Solicitados</div>
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
                        <div class="navbar-nav align-items-center">
                            <div class="nav-item d-flex align-items-center">
                                <i class="bx bx-search fs-4 lh-0"></i>
                                <input type="text" id="searchInput" class="form-control border-0 shadow-none" placeholder="Pesquisar por #id, status, produto, data..." />
                            </div>
                        </div>
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
                    <h4 class="fw-bold mb-3">Produtos Solicitados</h4>

                    <div class="card">
                        <div class="table-responsive text-nowrap">
                            <table class="table mb-0 text-nowrap" id="tabelaSolicitacoes">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Status</th>
                                        <th>Produtos</th>
                                        <th>Quantidade</th>
                                        <th>Total</th>
                                        <th>Criada em</th>
                                        <th style="min-width:260px">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($solicitacoes): foreach ($solicitacoes as $sid => $s): ?>
                                            <tr data-sid="<?= (int)$sid ?>">
                                                <td><?= (int)$sid ?></td>
                                                <td>
                                                    <?php $status = (string)$s['status'];
                                                    $statusTxt = ucwords(str_replace('_', ' ', $status)); ?>
                                                    <span class="status-badge <?= htmlspecialchars($status, ENT_QUOTES) ?>"><?= htmlspecialchars($statusTxt, ENT_QUOTES) ?></span>
                                                </td>
                                                <td class="col-produtos"><?= htmlspecialchars((string)($s['produtos_str'] ?? '—'), ENT_QUOTES) ?></td>
                                                <td class="qtd-total"><?= (int)($s['qtd_total'] ?? 0) ?></td>
                                                <td class="total-estimado">R$ <?= number_format((float)$s['total'], 2, ',', '.') ?></td>
                                                <td class="criada-em"><?= date('d/m/Y', strtotime($s['created_at'])) ?></td>
                                                <td>
                                                    <div class="actions-group d-flex align-items-center">
                                                        <button type="button" class="btn btn-sm btn-outline-primary btnDetalhes" data-sid="<?= (int)$sid ?>">
                                                            <i class="bx bx-news me-1"></i> Detalhes
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-primary btnMudarStatus"
                                                            data-sid="<?= (int)$sid ?>"
                                                            data-solicitante="<?= htmlspecialchars($s['id_solicitante'], ENT_QUOTES) ?>"
                                                            data-status="<?= htmlspecialchars($status, ENT_QUOTES) ?>"
                                                            data-aprovada="<?= htmlspecialchars((string)$s['aprovada_em'] ?? '', ENT_QUOTES) ?>"
                                                            data-enviada="<?= htmlspecialchars((string)$s['enviada_em'] ?? '', ENT_QUOTES) ?>"
                                                            data-entregue="<?= htmlspecialchars((string)$s['entregue_em'] ?? '', ENT_QUOTES) ?>">
                                                            Mudar Status
                                                        </button>
                                                    </div>
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
                                <h5 class="modal-title">Detalhes da Solicitação <span id="modalSid"></span></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-2">
                                    <div>Status: <span class="status-badge" id="modalStatusBadge"></span></div>
                                    <div class="meta-line mt-1">
                                        <span>Aprov.: <span id="metaAprov"></span></span> ·
                                        <span>Env.: <span id="metaEnv"></span></span> ·
                                        <span>Entr.: <span id="metaEntr"></span></span>
                                    </div>
                                    <div class="text-muted">Criada em: <span id="modalCriada"></span></div>
                                    <div class="fw-semibold">Total Estimado: <span id="modalTotal"></span></div>
                                </div>
                                <div id="modalItensWrapper"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MODAL MUDAR STATUS -->
                <div class="modal fade" id="modalMudarStatus" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Mudar status da solicitação <span id="statusSid"></span> <small class="text-muted">(<span id="statusSolicitante"></span>)</small></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <label class="form-label">Ação</label>
                                <select id="selectStatus" class="form-select"></select>
                                <div id="noActionsHelp" class="form-text d-none">Sem ações disponíveis para este status.</div>
                                <div id="actionsHelp" class="form-text">As opções exibidas dependem do status atual da solicitação.</div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" class="btn btn-primary" id="btnSalvarStatus">Confirmar</button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /MODAIS -->

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

    <!-- Dados em JSON para detalhes -->
    <script id="dadosSolicitacoes" type="application/json">
        <?= json_encode([
            'cab'    => array_values($solicitacoes),
            'mapCab' => $solicitacoes,
            'itens'  => $solicitacaoItens
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
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
        /* ===== Paginação/Pesquisa ===== */
        const searchInput = document.getElementById('searchInput');
        const allRows = Array.from(document.querySelectorAll('#tabelaSolicitacoes tbody tr'));
        const rowsPerPage = 10;
        let currentPage = 1;

        function renderTable() {
            const filtro = searchInput.value.trim().toLowerCase();
            const filteredRows = allRows.filter(row => {
                if (!filtro) return true;
                return Array.from(row.cells).some(cell =>
                    cell.textContent.toLowerCase().includes(filtro)
                );
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
        renderTable();

        /* ===== Dados Locais ===== */
        const dados = JSON.parse(document.getElementById('dadosSolicitacoes').textContent || '{}');
        const itensPorId = dados?.itens || {};
        const cabPorLista = dados?.cab || [];
        const mapCabInit = dados?.mapCab || {};
        const mapCab = {};
        (cabPorLista || []).forEach(c => {
            mapCab[parseInt(c.id, 10)] = c;
        });
        Object.assign(mapCab, mapCabInit);

        function formatBRL(v) {
            return (parseFloat(v || 0)).toLocaleString('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            });
        }
        /* Retorna a classe visual (soft) para cada status */
        function statusClassSoft(s) {
            s = String(s || '').toLowerCase();
            switch (s) {
                case 'pendente':
                    return 'badge-primary-soft';
                case 'aprovada':
                    return 'badge-success-soft';
                case 'reprovada':
                    return 'badge-danger-soft';
                case 'em_transito':
                    return 'badge-primary-soft';
                case 'entregue':
                    return 'badge-success-soft';
                case 'cancelada':
                    return 'badge-secondary-soft';
                default:
                    return 'badge-secondary-soft';
            }
        }

        function titleCaseStatus(s) {
            return String(s || '').replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
        }

        function toBrDateTime(ts) {
            if (!ts) return '—';
            const d = new Date(String(ts).replace(' ', 'T'));
            if (isNaN(d.getTime())) return '—';
            const dd = d.toLocaleDateString('pt-BR', {
                day: '2-digit',
                month: '2-digit'
            });
            const hm = d.toLocaleTimeString('pt-BR', {
                hour: '2-digit',
                minute: '2-digit'
            });
            return dd + ' ' + hm;
        }

        function escapeHtml(str) {
            return String(str).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", "&#039;");
        }

        /* ===== Aplicar classes soft iniciais na tabela ===== */
        document.querySelectorAll('.status-badge').forEach(el => {
            const s = el.classList[1] || ''; // segunda classe contém o status bruto inserido no PHP
            el.className = 'status-badge ' + statusClassSoft(s);
        });

        /* ===== Modal Detalhes ===== */
        $(document).on('click', '.btnDetalhes', function() {
            const sid = parseInt(this.dataset.sid || this.getAttribute('data-sid'), 10);
            const cab = mapCab[sid] || (cabPorLista.find(c => parseInt(c.id, 10) === sid)) || null;
            const itens = itensPorId[sid] || [];

            if (!cab) {
                $('#modalSid').text('');
                $('#modalStatusBadge').attr('class', 'status-badge badge-secondary-soft').text('—');
                $('#modalCriada').text('—');
                $('#modalTotal').text('—');
                $('#metaAprov').text('—');
                $('#metaEnv').text('—');
                $('#metaEntr').text('—');
                $('#modalItensWrapper').html('<p class="text-danger mb-0">Solicitação não encontrada.</p>');
            } else {
                $('#modalSid').text('#' + sid);
                $('#modalStatusBadge').attr('class', 'status-badge ' + statusClassSoft(cab.status)).text(titleCaseStatus(cab.status));
                const dt = new Date(String(cab.created_at).replace(' ', 'T'));
                $('#modalCriada').text(dt.toLocaleDateString('pt-BR'));
                $('#modalTotal').text(formatBRL(parseFloat(cab.total_estimado ?? cab.total ?? 0)));
                $('#metaAprov').text(toBrDateTime(cab.aprovada_em));
                $('#metaEnv').text(toBrDateTime(cab.enviada_em));
                $('#metaEntr').text(toBrDateTime(cab.entregue_em));

                if (!itens.length) {
                    $('#modalItensWrapper').html('<p class="text-muted mb-0">Nenhum item nesta solicitação.</p>');
                } else {
                    let html = '<div class="table-responsive text-nowrap"><table class="table table-sm text-nowrap">';
                    html += '<thead class="table-light"><tr><th>Código</th><th>Produto</th><th>Qtd</th><th>Unid.</th><th>Preço</th><th>Subtotal</th></tr></thead><tbody>';
                    itens.forEach(i => {
                        html += `<tr>
                            <td>${escapeHtml(i.codigo || '')}</td>
                            <td>${escapeHtml(i.nome || '')}</td>
                            <td>${parseInt(i.quantidade || 0, 10)}</td>
                            <td>${escapeHtml(i.unidade || '')}</td>
                            <td>${formatBRL(parseFloat(i.preco || 0))}</td>
                            <td>${formatBRL(parseFloat(i.subtotal || 0))}</td>
                        </tr>`;
                    });
                    html += '</tbody></table></div>';
                    $('#modalItensWrapper').html(html);
                }
            }

            new bootstrap.Modal(document.getElementById('modalDetalhes')).show();
        });

        /* ===== Helpers de ações do modal de status ===== */
        function populateStatusActions(currentStatus) {
            const select = document.getElementById('selectStatus');
            const noActions = document.getElementById('noActionsHelp');
            const actionsHelp = document.getElementById('actionsHelp');
            const btn = document.getElementById('btnSalvarStatus');

            select.innerHTML = '';
            if (String(currentStatus) === 'entregue') {
                const opt = document.createElement('option');
                opt.textContent = 'Sem ações disponíveis';
                opt.value = '';
                opt.disabled = true;
                opt.selected = true;
                select.appendChild(opt);
                select.disabled = true;
                btn.disabled = true;
                noActions.classList.remove('d-none');
                actionsHelp.classList.add('d-none');
            } else {
                const opt = document.createElement('option');
                opt.textContent = 'Marcar Entregue';
                opt.value = 'entregue';
                opt.selected = true;
                select.appendChild(opt);
                select.disabled = false;
                btn.disabled = false;
                noActions.classList.add('d-none');
                actionsHelp.classList.remove('d-none');
            }
        }

        /* ===== Modal Mudar Status ===== */
        let currentSid = null,
            currentSolicitante = null,
            currentStatus = null;
        $(document).on('click', '.btnMudarStatus', function() {
            currentSid = parseInt(this.dataset.sid || this.getAttribute('data-sid'), 10);
            currentSolicitante = this.dataset.solicitante || '';
            currentStatus = (mapCab[currentSid]?.status) || (this.dataset.status || '');

            $('#statusSid').text('#' + currentSid);
            $('#statusSolicitante').text(currentSolicitante || '—');

            populateStatusActions(currentStatus);

            new bootstrap.Modal(document.getElementById('modalMudarStatus')).show();
        });

        /* ===== Endpoint resolver ===== */
        function endpointUrl() {
            return new URL('../../assets/php/matriz/processarStatus.php', window.location.href).toString();
        }

        /* ===== Salvar Status ===== */
        $('#btnSalvarStatus').on('click', async function() {
            if (!currentSid) return;
            if (String(currentStatus) === 'entregue') {
                // já entregue: não envia
                return;
            }

            const fd = new FormData();
            fd.append('action', 'update_status');
            fd.append('sid', String(currentSid));
            fd.append('status', 'entregue');
            fd.append('solicitante', currentSolicitante || '');

            try {
                const resp = await fetch(endpointUrl(), {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const raw = await resp.text();
                let data;
                try {
                    data = JSON.parse(raw);
                } catch (e) {
                    alert('Erro ao processar a resposta do servidor.');
                    history.back();
                    return;
                }
                if (!resp.ok || data.ok === false) {
                    alert(data?.msg || ('Erro HTTP ' + resp.status));
                    history.back();
                    return;
                }

                const d = data.data || {};
                mapCab[currentSid] = Object.assign({}, mapCab[currentSid] || {}, d);
                currentStatus = d.status || 'entregue';

                // Atualiza linha visual
                const tr = document.querySelector(`tr[data-sid="${currentSid}"]`);
                if (tr) {
                    const badge = tr.querySelector('.status-badge');
                    if (badge) {
                        badge.className = 'status-badge ' + statusClassSoft(d.status);
                        badge.textContent = titleCaseStatus(d.status);
                    }
                    const btn = tr.querySelector('.btnMudarStatus');
                    if (btn) {
                        btn.dataset.status = d.status || '';
                        btn.dataset.aprovada = d.aprovada_em || '';
                        btn.dataset.enviada = d.enviada_em || '';
                        btn.dataset.entregue = d.entregue_em || '';
                    }
                }

                // Mensagem de sucesso (inclui o id_solicitante)
                alert(`Solicitação #${currentSid} (${currentSolicitante}) marcada como ENTREGUE com sucesso!`);

                // Fecha modal
                const modalEl = document.getElementById('modalMudarStatus');
                (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide();

                // Se modal de detalhes estiver aberta, sincroniza
                if (document.getElementById('modalDetalhes').classList.contains('show')) {
                    const cab = mapCab[currentSid];
                    if (cab) {
                        $('#modalStatusBadge').attr('class', 'status-badge ' + statusClassSoft(cab.status)).text(titleCaseStatus(cab.status));
                        $('#metaEntr').text(toBrDateTime(cab.entregue_em));
                    }
                }
            } catch (err) {
                alert('Falha de rede ao atualizar o status.');
                history.back();
            }
        });
    </script>
</body>

</html>
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// ✅ id selecionado (obrigatório)
$idSelecionado = $_GET['id'] ?? '';
if (!$idSelecionado) {
    header("Location: .././login.php");
    exit;
}

// ✅ login básico
if (!isset($_SESSION['usuario_logado'], $_SESSION['empresa_id'], $_SESSION['tipo_empresa'], $_SESSION['usuario_id'])) {
    header("Location: .././login.php?id=" . urlencode($idSelecionado));
    exit;
}

// ✅ conexão
require '../../assets/php/conexao.php';

// ✅ usuário logado
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

// ✅ permissão de acesso
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

// ✅ logo empresa
try {
    $s = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :i LIMIT 1");
    $s->execute([':i' => $idSelecionado]);
    $sobre = $s->fetch(PDO::FETCH_ASSOC);
    $logoEmpresa = !empty($sobre['imagem']) ? "../../assets/img/empresa/" . $sobre['imagem'] : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png";
}

/* =========================================================
   B2B Nova Solicitação
   - Produtos: estoque da MATRIZ (principal_1)
   - Solicitante: ?solicitante=unidade_x  ou empresa da sessão
   ========================================================= */
$empresa_matriz_id = 'principal_1';
$solicitanteId = $_GET['solicitante'] ?? ($tipoSession !== 'principal' ? $idEmpresaSession : '');

$produtos = [];
try {
    $sql = "SELECT id, fornecedor_id, empresa_id, codigo_produto, nome_produto, categoria_produto,
                 quantidade_produto, preco_produto, preco_custo, status_produto,
                 ncm, cest, cfop, origem, tributacao, unidade, codigo_barras, informacoes_adicionais
          FROM estoque
          WHERE empresa_id = :e
            AND (status_produto IS NULL OR status_produto='' OR LOWER(status_produto) IN ('ativo','disponivel'))
          ORDER BY nome_produto ASC";
    $st = $pdo->prepare($sql);
    $st->execute([':e' => $empresa_matriz_id]);
    $produtos = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $produtos = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>ERP - Matriz | Nova Solicitação</title>
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
        /* ====== ajustes visuais da tela ====== */
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

        .col-check {
            width: 48px;
        }

        .col-estoque {
            width: 110px;
            text-align: center;
        }

        .col-unid {
            width: 90px;
            text-align: center;
        }

        .col-preco {
            width: 130px;
            text-align: right;
        }

        .col-qtd {
            width: 140px;
            text-align: center;
        }

        .qty-input {
            width: 88px;
            margin: 0 auto;
            text-align: center;
        }

        .truncate {
            max-width: 420px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .muted {
            color: #94a3b8;
        }

        .badge-soft {
            background: rgba(108, 124, 247, .12);
            color: #3f3d99;
            border-radius: 10px;
            padding: .25rem .5rem;
        }

        .toolbar {
            display: flex;
            align-items: center;
            gap: .75rem;
        }

        #searchInput {
            min-width: 260px;
        }

        .footer-actions {
            display: flex;
            align-items: center;
            gap: .5rem;
            flex-wrap: wrap;
        }

        .summary {
            text-align: right;
            margin-left: auto;
        }

        .summary .muted {
            font-size: .85rem;
        }

        .btn-w140 {
            width: 140px;
        }

        /* linhas mais compactas */
        #tabelaProdutos tbody tr>td {
            padding-top: .65rem;
            padding-bottom: .65rem;
        }
    </style>
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- ===== SIDEBAR ===== (mesmo do seu arquivo) -->
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
                            <li class="menu-item"><a class="menu-link" href="./produtosRecebidos.php?id=<?= urlencode($idSelecionado); ?>">
                                    <div>Produtos Recebidos</div>
                                </a></li>
                            <li class="menu-item active"><a class="menu-link" href="./novaSolicitacao.php?id=<?= urlencode($idSelecionado); ?>">
                                    <div>Nova Solicitação</div>
                                </a></li>
                            <li class="menu-item"><a class="menu-link" href="./statusTransferencia.php?id=<?= urlencode($idSelecionado); ?>">
                                    <div>Status da Transf.</div>
                                </a></li>
                            <li class="menu-item"><a class="menu-link" href="./estoqueMatriz.php?id=<?= urlencode($idSelecionado); ?>">
                                    <div>Estoque da Matriz</div>
                                </a></li>
                            <li class="menu-item"><a class="menu-link" href="./solicitarPagamentoConta.php?id=<?= urlencode($idSelecionado); ?>">
                                    <div>Solicitar Pagamento</div>
                                </a></li>
                        </ul>
                    </li>

                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-bar-chart-alt-2"></i>
                            <div>Relatórios</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item"><a class="menu-link" href="./vendas.php?id=<?= urlencode($idSelecionado); ?>">
                                    <div>Vendas por Filial</div>
                                </a></li>
                            <li class="menu-item"><a class="menu-link" href="./maisVendidos.php?id=<?= urlencode($idSelecionado); ?>">
                                    <div>Mais Vendidos</div>
                                </a></li>
                            <li class="menu-item"><a class="menu-link" href="./vendasPeriodo.php?id=<?= urlencode($idSelecionado); ?>">
                                    <div>Vendas por Período</div>
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
                        <div class="navbar-nav align-items-center"></div>
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
                <!-- /NAVBAR -->

                <!-- ===== CONTENT ===== -->
                <div class="container-xxl flex-grow-1 container-p-y">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                        <div>
                            <h4 class="fw-bold mb-1">Nova Solicitação de Produto</h4>
                            <div class="text-muted">
                                Estoque da <span class="badge-soft">Matriz (<?= htmlspecialchars($empresa_matriz_id) ?>)</span>
                                <?php if ($solicitanteId): ?>
                                    &middot; Solicitante: <a class="badge-soft" href="?id=<?= urlencode($idSelecionado) ?>&solicitante=<?= urlencode($solicitanteId) ?>"><?= htmlspecialchars($solicitanteId) ?></a>
                                <?php else: ?>
                                    &middot; <span class="muted">Solicitante: <?= htmlspecialchars($idEmpresaSession) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="toolbar">
                            <input id="searchInput" class="form-control" type="text" placeholder="Pesquisar produto, código, categoria…" />
                        </div>
                    </div>

                    <form id="formSolicitacao" method="post" action="./actions/novaSolicitacao_Salvar.php">
                        <input type="hidden" name="id_matriz" value="<?= htmlspecialchars($empresa_matriz_id) ?>">
                        <input type="hidden" name="id_solicitante" value="<?= htmlspecialchars($solicitanteId ?: $idEmpresaSession) ?>">
                        <input type="hidden" name="id_pagina" value="<?= htmlspecialchars($idSelecionado) ?>">

                        <div class="card">
                            <div class="table-responsive" style="max-height:60vh;">
                                <table class="table text-nowrap" id="tabelaProdutos">
                                    <thead>
                                        <tr>
                                            <th class="col-check"></th>
                                            <th>Produto</th>
                                            <th>Categoria</th>
                                            <th>Cód. Produto</th>
                                            <th class="col-estoque">Estoque</th>
                                            <th class="col-unid">Unid.</th>
                                            <th class="col-preco">Preço</th>
                                            <th class="col-qtd">Qtd. Solicitar</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($produtos): foreach ($produtos as $p):
                                                $rowId  = (int)$p['id'];
                                                $nome   = $p['nome_produto'] ?? '';
                                                $cat    = $p['categoria_produto'] ?? '';
                                                $cod    = $p['codigo_produto'] ?? '';
                                                $estq   = (int)($p['quantidade_produto'] ?? 0);
                                                $un     = $p['unidade'] ?? 'UN';
                                                $preco  = (float)($p['preco_produto'] ?? 0);
                                        ?>
                                                <tr>
                                                    <td class="col-check">
                                                        <input type="checkbox" class="form-check-input chkItem" name="itens[<?= $rowId ?>][selecionado]" value="1">
                                                        <input type="hidden" name="itens[<?= $rowId ?>][produto_id]" value="<?= $rowId ?>">
                                                        <input type="hidden" name="itens[<?= $rowId ?>][nome]" value="<?= htmlspecialchars($nome) ?>">
                                                        <input type="hidden" name="itens[<?= $rowId ?>][codigo]" value="<?= htmlspecialchars($cod) ?>">
                                                        <input type="hidden" name="itens[<?= $rowId ?>][preco]" value="<?= number_format($preco, 2, '.', '') ?>">
                                                        <input type="hidden" name="itens[<?= $rowId ?>][unidade]" value="<?= htmlspecialchars($un) ?>">
                                                    </td>
                                                    <td>
                                                        <div class="fw-semibold truncate" title="<?= htmlspecialchars($nome) ?>"><?= htmlspecialchars($nome) ?></div>
                                                        <div class="small muted">Código: <span class="text-body"><?= htmlspecialchars($cod) ?></span></div>
                                                    </td>
                                                    <td><?= htmlspecialchars($cat) ?></td>
                                                    <td><?= htmlspecialchars($cod) ?></td>
                                                    <td class="col-estoque"><span class="badge bg-label-primary"><?= $estq ?></span></td>
                                                    <td class="col-unid"><?= htmlspecialchars($un) ?></td>
                                                    <td class="col-preco">R$ <?= number_format($preco, 2, ',', '.') ?></td>
                                                    <td class="col-qtd">
                                                        <input type="number" min="0" step="1" class="form-control form-control-sm qty-input" name="itens[<?= $rowId ?>][quantidade]" placeholder="0">
                                                    </td>
                                                </tr>
                                            <?php endforeach;
                                        else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-4">Nenhum produto disponível no estoque da matriz.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="card-footer bg-white">
                                <div class="footer-actions">
                                    <button type="button" id="prevPage" class="btn btn-sm btn-outline-primary">Anterior</button>
                                    <div id="paginacao"></div>
                                    <button type="button" id="nextPage" class="btn btn-sm btn-outline-primary">Próxima</button>

                                    <div class="summary">
                                        <div class="muted">Itens selecionados: <span id="countSel">0</span></div>
                                        <div class="fw-semibold">Valor estimado: <span id="valorTotal">R$ 0,00</span></div>
                                    </div>

                                    <button type="submit" id="btnEnviar" class="btn btn-primary btn-w200 col-12 col-md-3" disabled>
                                        <i class="bx bx-send me-1"></i> Enviar Solicitação
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <!-- /CONTENT -->

                <footer class="content-footer footer bg-footer-theme text-center">
                    <div class="container-xxl d-flex py-2 flex-md-row flex-column justify-content-center">
                        <div class="mb-2 mb-md-0">
                            &copy;<script>
                                document.write(new Date().getFullYear());
                            </script>, <strong>Açaínhadinhos</strong>. Todos os direitos reservados.
                            Desenvolvido por <strong>Lucas Correa</strong>.
                        </div>
                    </div>
                </footer>
                <div class="content-backdrop fade"></div>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="../../js/saudacao.js"></script>
    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../assets/vendor/js/menu.js"></script>
    <script src="../../assets/vendor/libs/apex-charts/apex-charts.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/dashboards-analytics.js"></script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>

    <!-- ===== LÓGICA: seleção por item + filtro/paginação ===== -->
    <script>
        const tableBody = document.querySelector('#tabelaProdutos tbody');
        const btnEnviar = document.getElementById('btnEnviar');
        const countSel = document.getElementById('countSel');
        const valorTotalEl = document.getElementById('valorTotal');

        function recalcResumo() {
            let count = 0,
                total = 0;
            tableBody.querySelectorAll('tr').forEach(tr => {
                const chk = tr.querySelector('.chkItem');
                const qty = tr.querySelector('.qty-input');
                const price = tr.querySelector('input[name*="[preco]"]');
                if (!chk || !qty || !price) return;

                const selecionado = chk.checked;
                const qtd = parseInt(qty.value || '0', 10);
                const preco = parseFloat(price.value || '0');

                if (selecionado && qtd > 0) {
                    count++;
                    total += qtd * preco;
                }
            });
            countSel.textContent = count;
            valorTotalEl.textContent = total.toLocaleString('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            });
            btnEnviar.disabled = (count === 0);
        }

        // marca/desmarca item + valida quantidade
        tableBody.addEventListener('input', (e) => {
            if (e.target.classList.contains('qty-input')) {
                const tr = e.target.closest('tr');
                const chk = tr.querySelector('.chkItem');
                const v = parseInt(e.target.value || '0', 10);
                if (chk) chk.checked = v > 0; // marca só aquele produto
                recalcResumo();
            }
        });
        tableBody.addEventListener('change', (e) => {
            if (e.target.classList.contains('chkItem')) {
                const tr = e.target.closest('tr');
                const qty = tr.querySelector('.qty-input');
                if (!e.target.checked && qty) qty.value = '';
                recalcResumo();
            }
        });

        // ====== Filtro + Paginação (padrão que você enviou) ======
        const searchInput = document.getElementById('searchInput');
        const allRows = Array.from(document.querySelectorAll('#tabelaProdutos tbody tr'));
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

            allRows.forEach(r => r.style.display = 'none');
            filteredRows.slice(startIndex, endIndex).forEach(r => r.style.display = '');

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

        // validação no submit
        document.getElementById('formSolicitacao').addEventListener('submit', (e) => {
            let ok = false;
            tableBody.querySelectorAll('tr').forEach(tr => {
                const chk = tr.querySelector('.chkItem');
                const qty = tr.querySelector('.qty-input');
                if (chk && qty && chk.checked && parseInt(qty.value || '0', 10) > 0) ok = true;
            });
            if (!ok) {
                e.preventDefault();
                alert('Selecione pelo menos um item com quantidade > 0.');
            }
        });

        // start
        renderTable();
        recalcResumo();
    </script>
</body>

</html>
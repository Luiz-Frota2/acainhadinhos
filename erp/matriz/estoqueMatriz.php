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
    $acessoPermitido = ($tipoSession === 'principal' && $idEmpresaSession === $idSelecionado);
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

/* ==================== Resolver CNPJ da Matriz ==================== */
/**
 * Tenta obter o CNPJ da matriz por:
 * 1) empresas_peca.identificador == 'principal_1' (ou == $idSelecionado se já for principal)
 * 2) empresas_peca.id_selecionado == 'principal_1'
 * 3) config_empresa (chave 'principal_cnpj' ou tipo = 'principal')
 */
function resolveCnpjMatriz(PDO $pdo, string $idSelecionado): ?string {
    // se já está na principal, usar esse identificador
    $ident = str_starts_with($idSelecionado, 'principal_') ? $idSelecionado : 'principal_1';

    // 1) empresas_peca.identificador
    try {
        $q = $pdo->prepare("SELECT cnpj FROM empresas_peca WHERE identificador = :ident LIMIT 1");
        $q->execute([':ident' => $ident]);
        if ($r = $q->fetch(PDO::FETCH_ASSOC)) return preg_replace('/\D+/', '', (string)$r['cnpj']);
    } catch (Throwable $e) {}

    // 2) empresas_peca.id_selecionado
    try {
        $q = $pdo->prepare("SELECT cnpj FROM empresas_peca WHERE id_selecionado = :ident LIMIT 1");
        $q->execute([':ident' => $ident]);
        if ($r = $q->fetch(PDO::FETCH_ASSOC)) return preg_replace('/\D+/', '', (string)$r['cnpj']);
    } catch (Throwable $e) {}

    // 3) config_empresa: chave ou tipo
    try {
        $q = $pdo->query("SELECT valor FROM config_empresa WHERE chave = 'principal_cnpj' LIMIT 1");
        if ($r = $q->fetch(PDO::FETCH_ASSOC)) return preg_replace('/\D+/', '', (string)$r['valor']);
    } catch (Throwable $e) {}
    try {
        $q = $pdo->query("SELECT cnpj FROM config_empresa WHERE tipo = 'principal' LIMIT 1");
        if ($r = $q->fetch(PDO::FETCH_ASSOC)) return preg_replace('/\D+/', '', (string)$r['cnpj']);
    } catch (Throwable $e) {}

    return null; // não encontrado
}

$cnpjMatriz = resolveCnpjMatriz($pdo, $idSelecionado);

/* ==================== Buscar estoque da Matriz ====================

Tabelas-alvo padrão do seu projeto:
- produtos_peca (campos usuais: id, empresa_cnpj, sku, nome, unidade, preco_venda, ncm, cest, gtin, ... )
- mov_estoque_peca (campos: id, empresa_cnpj, produto_id, mov_tipo, quantidade, mov_data, obs, documento_ref, ...)

Regras de saldo:
saldo = entradas + ajustes_positivos - saídas - ajustes_negativos

mov_tipo esperado: 'entrada','saida','ajuste_positivo','ajuste_negativo'
*/
$produtos = []; // array de produtos com saldo
if ($cnpjMatriz) {
    try {
        // Busca os produtos da matriz
        $sql = "
            SELECT
                p.id,
                p.sku,
                p.nome,
                p.unidade,
                p.preco_venda,
                p.ncm,
                p.gtin,
                p.categoria_id,
                COALESCE(SUM(
                    CASE 
                        WHEN m.mov_tipo = 'entrada'          THEN m.quantidade
                        WHEN m.mov_tipo = 'ajuste_positivo'  THEN m.quantidade
                        WHEN m.mov_tipo = 'saida'            THEN -m.quantidade
                        WHEN m.mov_tipo = 'ajuste_negativo'  THEN -m.quantidade
                        ELSE 0
                    END
                ), 0) AS saldo_atual,
                MAX(m.mov_data) AS ultima_mov
            FROM produtos_peca p
            LEFT JOIN mov_estoque_peca m
                ON m.produto_id = p.id
               AND m.empresa_cnpj = :cnpj
            WHERE p.empresa_cnpj = :cnpj
            GROUP BY p.id, p.sku, p.nome, p.unidade, p.preco_venda, p.ncm, p.gtin, p.categoria_id
            ORDER BY p.nome ASC
        ";
        $st = $pdo->prepare($sql);
        $st->execute([':cnpj' => $cnpjMatriz]);
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $produtos[] = [
                'id'          => (int)$r['id'],
                'sku'         => (string)$r['sku'],
                'nome'        => (string)$r['nome'],
                'unidade'     => (string)$r['unidade'],
                'preco'       => (float)$r['preco_venda'],
                'ncm'         => (string)$r['ncm'],
                'gtin'        => (string)$r['gtin'],
                'categoria_id'=> $r['categoria_id'],
                'saldo'       => (float)$r['saldo_atual'],
                'ultima'      => $r['ultima_mov'],
            ];
        }
    } catch (PDOException $e) {
        // deixa vazio se der erro
    }
}

/* ===== Helpers ===== */
function badgeQntClass(float $q) {
    if ($q <= 0) return 'badge-danger-soft';
    if ($q <= 5) return 'badge-warning-soft';
    return 'badge-success-soft';
}
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>ERP - Estoque da Matriz</title>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($logoEmpresa) ?>" />
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
        .card { border-radius:14px; }
        .table thead th { white-space:nowrap; font-weight:600; color:#6b7280; }
        .table tbody td { vertical-align:middle; }
        .status-badge {
            text-transform: uppercase; letter-spacing: .02em; border-radius: 10px;
            padding: .30rem .55rem; font-size: .75rem; font-weight: 700; border:1px solid transparent; display:inline-block;
        }
        .badge-success-soft { color:#16a34a; background:#ecfdf5; border-color:#bbf7d0; }
        .badge-warning-soft { color:#a16207; background:#fef3c7; border-color:#fde68a; }
        .badge-danger-soft  { color:#b91c1c; background:#fee2e2; border-color:#fecaca; }

        #paginacao button { margin-right:5px; }
        td.col-nome { max-width:420px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        td.col-sku  { max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
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
                        <li class="menu-item"><a class="menu-link" href="./produtosSolicitados.php?id=<?= urlencode($idSelecionado); ?>"><div>Produtos Solicitados</div></a></li>
                        <li class="menu-item"><a class="menu-link" href="./statusTransferencia.php?id=<?= urlencode($idSelecionado); ?>"><div>Status da Transf.</div></a></li>
                        <li class="menu-item"><a class="menu-link" href="./produtosRecebidos.php?id=<?= urlencode($idSelecionado); ?>"><div>Produtos Entregues</div></a></li>
                        <li class="menu-item"><a class="menu-link" href="./novaSolicitacao.php?id=<?= urlencode($idSelecionado); ?>"><div>Nova Solicitação</div></a></li>
                        <li class="menu-item open active"><a class="menu-link" href="./estoqueMatriz.php?id=<?= urlencode($idSelecionado); ?>"><div>Estoque da Matriz</div></a></li>
                        <li class="menu-item"><a class="menu-link" href="./solicitarPagamentoConta.php?id=<?= urlencode($idSelecionado); ?>"><div>Solicitar Pagamento</div></a></li>
                    </ul>
                </li>

                <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
                <li class="menu-item"><a class="menu-link" href="../rh/index.php?id=<?= urlencode($idSelecionado); ?>"><i class="menu-icon tf-icons bx bx-group"></i><div>RH</div></a></li>
                <li class="menu-item"><a class="menu-link" href="../financas/index.php?id=<?= urlencode($idSelecionado); ?>"><i class="menu-icon tf-icons bx bx-dollar"></i><div>Finanças</div></a></li>
                <li class="menu-item"><a class="menu-link" href="../pdv/index.php?id=<?= urlencode($idSelecionado); ?>"><i class="menu-icon tf-icons bx bx-desktop"></i><div>PDV</div></a></li>
                <li class="menu-item"><a class="menu-link" href="../empresa/index.php?id=<?= urlencode($idSelecionado); ?>"><i class="menu-icon tf-icons bx bx-briefcase"></i><div>Empresa</div></a></li>
                <li class="menu-item"><a class="menu-link" href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>"><i class="menu-icon tf-icons bx bx-box"></i><div>Estoque</div></a></li>
                <?php
                $tipoLogado = $_SESSION['tipo_empresa'] ?? '';
                $idLogado   = $_SESSION['empresa_id']    ?? '';
                if ($tipoLogado === 'principal') { ?>
                    <li class="menu-item"><a class="menu-link" href="../filial/index.php?id=principal_1"><i class="menu-icon tf-icons bx bx-building"></i><div>Filial</div></a></li>
                    <li class="menu-item"><a class="menu-link" href="../franquia/index.php?id=principal_1"><i class="menu-icon tf-icons bx bx-store"></i><div>Franquias</div></a></li>
                <?php } elseif (in_array($tipoLogado, ['filial', 'franquia', 'unidade'])) { ?>
                    <li class="menu-item"><a class="menu-link" href="../matriz/index.php?id=<?= urlencode($idLogado) ?>"><i class="menu-icon tf-icons bx bx-cog"></i><div>Matriz</div></a></li>
                <?php } ?>
                <li class="menu-item"><a class="menu-link" href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>"><i class="menu-icon tf-icons bx bx-group"></i><div>Usuários</div></a></li>
                <li class="menu-item"><a class="menu-link" target="_blank" href="https://wa.me/92991515710"><i class="menu-icon tf-icons bx bx-support"></i><div>Suporte</div></a></li>
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
                            <input type="text" id="searchInput" class="form-control border-0 shadow-none" placeholder="Pesquisar por SKU, produto, NCM, GTIN..." />
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
                                <li><div class="dropdown-divider"></div></li>
                                <li><a class="dropdown-item" href="./contaUsuario.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-user me-2"></i><span class="align-middle">Minha Conta</span></a></li>
                                <li><div class="dropdown-divider"></div></li>
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
                    <h4 class="fw-bold mb-0">Estoque da Matriz</h4>
                    <?php if (!$cnpjMatriz): ?>
                        <span class="badge bg-label-danger">CNPJ da Matriz não encontrado</span>
                    <?php else: ?>
                        <span class="badge bg-label-primary">Matriz: <?= htmlspecialchars($cnpjMatriz, ENT_QUOTES) ?></span>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="table-responsive text-nowrap">
                        <table class="table mb-0 text-nowrap" id="tabelaEstoque">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>SKU</th>
                                    <th>Produto</th>
                                    <th>Unid.</th>
                                    <th class="text-end">Preço</th>
                                    <th class="text-end">Saldo</th>
                                    <th>Última Mov.</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($cnpjMatriz && $produtos): foreach ($produtos as $p): ?>
                                <?php
                                  $q = (float)$p['saldo'];
                                  $badge = badgeQntClass($q);
                                  $ultima = $p['ultima'] ? date('d/m/Y H:i', strtotime($p['ultima'])) : '—';
                                ?>
                                <tr data-pid="<?= (int)$p['id'] ?>">
                                    <td><?= (int)$p['id'] ?></td>
                                    <td class="col-sku"><?= htmlspecialchars($p['sku'] ?: '—', ENT_QUOTES) ?></td>
                                    <td class="col-nome"><?= htmlspecialchars($p['nome'] ?: '—', ENT_QUOTES) ?></td>
                                    <td><?= htmlspecialchars($p['unidade'] ?: '—', ENT_QUOTES) ?></td>
                                    <td class="text-end">R$ <?= number_format((float)$p['preco'], 2, ',', '.') ?></td>
                                    <td class="text-end">
                                        <span class="status-badge <?= $badge ?>"><?= number_format($q, 2, ',', '.') ?></span>
                                    </td>
                                    <td><?= $ultima ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary btnDetalhes" data-pid="<?= (int)$p['id'] ?>">Detalhes</button>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">Nenhum item encontrado para a matriz.</td></tr>
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
                            <h5 class="modal-title">Detalhes do Produto <span id="modalPid"></span></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-2">
                                <div>SKU: <span id="modalSku"></span></div>
                                <div>Produto: <span id="modalNome"></span></div>
                                <div>Unidade: <span id="modalUnid"></span></div>
                                <div>Preço: <span id="modalPreco"></span></div>
                                <div>NCM: <span id="modalNcm"></span></div>
                                <div>GTIN: <span id="modalGtin"></span></div>
                                <div class="mt-2">Saldo: <span id="modalSaldo"></span></div>
                                <div>Última Movimentação: <span id="modalUltima"></span></div>
                            </div>
                            <div id="modalMovsWrapper" class="mt-3">
                                <!-- (opcional) aqui poderia carregar últimas movimentações via AJAX no futuro -->
                                <small class="text-muted">Histórico resumido não implementado (somente listagem/visão geral nesta etapa).</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <!-- Ações futuras (solicitar transferência, etc.) -->
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /MODAL -->

            <footer class="content-footer footer bg-footer-theme text-center">
                <div class="container-xxl d-flex py-2 flex-md-row flex-column justify-content-center">
                    <div class="mb-2 mb-md-0">
                        &copy;<script>document.write(new Date().getFullYear());</script>, <strong>Açaínhadinhos</strong>.
                        Todos os direitos reservados. Desenvolvido por <strong>Lucas Correa</strong>.
                    </div>
                </div>
            </footer>
            <div class="content-backdrop fade"></div>
        </div>
    </div>
</div>

<!-- Dados em JSON para detalhes (sem AJAX) -->
<script id="dadosEstoque" type="application/json">
<?= json_encode([
    'cnpj' => $cnpjMatriz,
    'lista' => array_values($produtos)
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
</script>

<!-- JS -->
<script src="../../assets/vendor/libs/jquery/jquery.js"></script>
<script src="../../assets/vendor/libs/popper/popper.js"></script>
<script src="../../assets/vendor/js/bootstrap.js"></script>
<script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
<script src="../../assets/vendor/js/menu.js"></script>
<script src="../../assets/js/main.js"></script>

<script>
/* ===== Pesquisa + Paginação ===== */
const searchInput = document.getElementById('searchInput');
const allRows = Array.from(document.querySelectorAll('#tabelaEstoque tbody tr'));
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
        btn.onclick = () => { currentPage = i; renderTable(); };
        paginacao.appendChild(btn);
    }

    document.getElementById('prevPage').disabled = currentPage === 1;
    document.getElementById('nextPage').disabled = currentPage === totalPages || totalPages === 0;
}

document.getElementById('prevPage').addEventListener('click', () => { if (currentPage > 1) { currentPage--; renderTable(); } });
document.getElementById('nextPage').addEventListener('click', () => { currentPage++; renderTable(); });
searchInput.addEventListener('input', () => { currentPage = 1; renderTable(); });

// Inicializa a tabela
renderTable();

/* ===== Modal Detalhes (sem AJAX) ===== */
const dados = JSON.parse(document.getElementById('dadosEstoque').textContent || '{}');
const lista = dados?.lista || [];
const map = {};
lista.forEach(p => { map[parseInt(p.id,10)] = p; });

function badgeQntClass(q) {
    q = Number(q)||0;
    if (q <= 0) return 'badge-danger-soft';
    if (q <= 5) return 'badge-warning-soft';
    return 'badge-success-soft';
}
function fmtBRL(n) {
    return (Number(n)||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
}
function safe(s) {
    return String(s ?? '')
      .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
      .replaceAll('"','&quot;').replaceAll("'","&#039;");
}
function fmtDateTime(d) {
    if (!d) return '—';
    const dt = new Date(String(d).replace(' ','T'));
    if (isNaN(dt)) return '—';
    return dt.toLocaleDateString('pt-BR') + ' ' + dt.toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'});
}

$(document).on('click', '.btnDetalhes', function() {
    const pid = parseInt(this.dataset.pid || this.getAttribute('data-pid'), 10);
    const p = map[pid] || null;

    if (!p) {
        $('#modalPid').text('');
        $('#modalSku').text('—');
        $('#modalNome').text('—');
        $('#modalUnid').text('—');
        $('#modalPreco').text('—');
        $('#modalNcm').text('—');
        $('#modalGtin').text('—');
        $('#modalSaldo').attr('class','status-badge badge-danger-soft').text('—');
        $('#modalUltima').text('—');
    } else {
        $('#modalPid').text('#' + pid);
        $('#modalSku').text(safe(p.sku || '—'));
        $('#modalNome').text(safe(p.nome || '—'));
        $('#modalUnid').text(safe(p.unidade || '—'));
        $('#modalPreco').text(fmtBRL(p.preco));
        $('#modalNcm').text(safe(p.ncm || '—'));
        $('#modalGtin').text(safe(p.gtin || '—'));
        const saldo = Number(p.saldo)||0;
        $('#modalSaldo').attr('class','status-badge ' + badgeQntClass(saldo)).text(saldo.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2}));
        $('#modalUltima').text(fmtDateTime(p.ultima));
    }

    const modal = new bootstrap.Modal(document.getElementById('modalDetalhes'));
    modal.show();
});
</script>
</body>
</html>

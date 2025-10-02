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
   Listagem de Solicitações do Solicitante
   ========================================================= */
$solicitacoes = [];
try {
    $sql = "SELECT id, id_matriz, id_solicitante, status, total_estimado,
                 created_at, aprovada_em, enviada_em, entregue_em
          FROM solicitacoes_b2b
          WHERE id_solicitante = :sol
          ORDER BY created_at DESC";
    $st = $pdo->prepare($sql);
    $st->execute([':sol' => $idSelecionado]);
    $solicitacoes = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $solicitacoes = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>ERP - Minhas Solicitações</title>

    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($logoEmpresa) ?>" />
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/bootstrap/bootstrap.min.css" />

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

        .badge {
            border-radius: 10px;
            padding: .25rem .5rem;
            font-size: .8rem;
        }

        .badge-pendente {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-aprovada {
            background: #dcfce7;
            color: #166534;
        }

        .badge-reprovada {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-em_transito {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-entregue {
            background: #e0f2fe;
            color: #075985;
        }

        .badge-cancelada {
            background: #f3f4f6;
            color: #374151;
        }

        #paginacao button {
            margin-right: 5px;
        }
    </style>
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- MENU -->
            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="app-brand-link">
                        <span class="app-brand-text demo menu-text fw-bolder ms-2">Açaínhadinhos</span>
                    </a>
                </div>
                <div class="menu-inner-shadow"></div>
                <ul class="menu-inner py-1">
                    <li class="menu-item open active">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div>B2B - Matriz</div>
                        </a>
                        <ul class="menu-sub active">
                            <li class="menu-item active"><a class="menu-link" href="./solicitacoes.php?id=<?= urlencode($idSelecionado); ?>">
                                    <div>Minhas Solicitações</div>
                                </a></li>
                            <li class="menu-item"><a class="menu-link" href="./novaSolicitacao.php?id=<?= urlencode($idSelecionado); ?>">
                                    <div>Nova Solicitação</div>
                                </a></li>
                        </ul>
                    </li>
                </ul>
            </aside>
            <!-- /MENU -->

            <div class="layout-page">
                <!-- NAVBAR -->
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached bg-navbar-theme">
                    <div class="navbar-nav-right d-flex align-items-center w-100">
                        <div class="nav-item d-flex align-items-center">
                            <i class="bx bx-search fs-4 lh-0"></i>
                            <input type="text" id="searchInput" class="form-control border-0 shadow-none" placeholder="Pesquisar..." />
                        </div>
                    </div>
                </nav>

                <!-- CONTENT -->
                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold mb-3">Minhas Solicitações</h4>

                    <div class="card">
                        <div class="table-responsive">
                            <table class="table mb-0" id="tabelaSolicitacoes">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Status</th>
                                        <th>Total</th>
                                        <th>Criada</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($solicitacoes): foreach ($solicitacoes as $s): ?>
                                            <tr>
                                                <td><?= (int)$s['id'] ?></td>
                                                <td><span class="badge badge-<?= $s['status'] ?>"><?= ucfirst($s['status']) ?></span></td>
                                                <td>R$ <?= number_format($s['total_estimado'], 2, ',', '.') ?></td>
                                                <td><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary btnDetalhes" data-id="<?= $s['id'] ?>">Detalhes</button>
                                                </td>
                                            </tr>
                                        <?php endforeach;
                                    else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">Nenhuma solicitação encontrada.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer d-flex justify-content-between">
                            <button type="button" id="prevPage" class="btn btn-sm btn-outline-primary">Anterior</button>
                            <div id="paginacao"></div>
                            <button type="button" id="nextPage" class="btn btn-sm btn-outline-primary">Próxima</button>
                        </div>
                    </div>
                </div>

                <!-- MODAL DETALHES -->
                <div class="modal fade" id="modalDetalhes" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Detalhes da Solicitação</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div id="detalhesConteudo">Carregando...</div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>

    <script>
        const allRows = Array.from(document.querySelectorAll('#tabelaSolicitacoes tbody tr'));
        const rowsPerPage = 10;
        let currentPage = 1;

        function renderTable() {
            const filtro = document.getElementById('searchInput').value.trim().toLowerCase();
            const filteredRows = allRows.filter(row =>
                !filtro || row.textContent.toLowerCase().includes(filtro)
            );

            const totalPages = Math.ceil(filteredRows.length / rowsPerPage) || 1;
            if (currentPage > totalPages) currentPage = totalPages;

            allRows.forEach(r => r.style.display = 'none');
            filteredRows.slice((currentPage - 1) * rowsPerPage, currentPage * rowsPerPage).forEach(r => r.style.display = '');

            const paginacao = document.getElementById('paginacao');
            paginacao.innerHTML = '';
            for (let i = 1; i <= totalPages; i++) {
                const btn = document.createElement('button');
                btn.className = 'btn btn-sm ' + (i === currentPage ? 'btn-primary' : 'btn-outline-primary');
                btn.textContent = i;
                btn.onclick = () => {
                    currentPage = i;
                    renderTable();
                };
                paginacao.appendChild(btn);
            }

            document.getElementById('prevPage').disabled = currentPage === 1;
            document.getElementById('nextPage').disabled = currentPage === totalPages;
        }

        document.getElementById('searchInput').addEventListener('input', () => {
            currentPage = 1;
            renderTable();
        });
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

        renderTable();

        // Detalhes na modal
        $(document).on('click', '.btnDetalhes', function() {
            const id = $(this).data('id');
            $('#modalDetalhes').modal('show');
            $('#detalhesConteudo').html('Carregando...');
            $.get('solicitacaoDetalhes.php', {
                id: id
            }, function(res) {
                $('#detalhesConteudo').html(res);
            }).fail(() => $('#detalhesConteudo').html('<div class="text-danger">Erro ao carregar detalhes</div>'));
        });
    </script>
</body>

</html>
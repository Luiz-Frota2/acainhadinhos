<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

/* ==================== Sessão & parâmetros ==================== */
$idSelecionado = $_GET['id'] ?? ($_POST['id'] ?? '');
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
    $acessoPermitido = in_array($tipoSession, ['principal', 'filial', 'unidade', 'franquia']) && ($idEmpresaSession === $idSelecionado || $tipoSession === 'principal');
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

/* ==================== CSRF ==================== */
if (empty($_SESSION['csrf_pagamento'])) {
    $_SESSION['csrf_pagamento'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_pagamento'];
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>ERP - Nova Solicitação de Pagamento</title>
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
        .card {
            border-radius: 14px;
        }

        .form-section-title {
            font-weight: 700;
            font-size: 1rem;
            color: #374151;
        }

        .help {
            font-size: .8rem;
            color: #6b7280;
        }

        .required::after {
            content: " *";
            color: #ef4444;
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
                            <li class="menu-item"><a class="menu-link" href="./novaSolicitacaoPagamento.php?id=<?= urlencode($idSelecionado); ?>">
                                    <div>Nova Solicitação</div>
                                </a></li>
                            <li class="menu-item"><a class="menu-link" href="./estoqueMatriz.php?id=<?= urlencode($idSelecionado); ?>">
                                    <div>Estoque da Matriz</div>
                                </a></li>
                            <li class="menu-item"><a class="menu-link" href="./solicitarPagamentoConta.php?id=<?= urlencode($idSelecionado); ?>">
                                    <div>Pag. Solicitados</div>
                                </a></li>
                            <li class="menu-item open active">
                                <a class="menu-link" href="#">
                                    <div>Solicitar Pagamento</div>
                                </a>
                            </li>
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
                                    <li><a class="dropdown-item" href="../logout.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-power-off me-2"></i><span class="align-middle">Sair</span></a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </nav>
                <!-- /NAVBAR -->

                <!-- CONTENT -->
                <div class="container-xxl flex-grow-1 container-p-y">
                    <?php if (!empty($_SESSION['flash_msg'])): ?>
                        <div class="alert alert-success alert-dismissible" role="alert">
                            <?= htmlspecialchars($_SESSION['flash_msg'], ENT_QUOTES) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['flash_msg']); ?>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="fw-bold mb-0">Solicitações de Pagamento</h4>

                    </div>

                    <div class="card">
                        <div class="card-body">
                            <form
                                method="post"
                                enctype="multipart/form-data"
                                id="formNovaSolic"
                                novalidate
                                action="../../assets/php/matriz/novaSolicitacaoPagamentoSubmit.php">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($idSelecionado, ENT_QUOTES) ?>">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label required">Fornecedor</label>
                                        <input type="text" name="fornecedor" class="form-control" maxlength="120" required>
                                        <div class="help">Ex.: Nome da empresa ou pessoa a receber.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Documento (NF, Duplicata, Boleto)</label>
                                        <input type="text" name="documento" class="form-control" maxlength="60" placeholder="Ex.: NF 12345 / Boleto 98765">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label required">Vencimento</label>
                                        <input type="date" name="vencimento" class="form-control" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label required">Valor (R$)</label>
                                        <input type="text" name="valor" class="form-control" inputmode="decimal" placeholder="Ex.: 1.234,56" required>
                                        <div class="help">Use vírgula para centavos (ex.: 250,00).</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Arquivo (opcional)</label>
                                        <input type="file" name="arquivo" class="form-control" accept=".pdf,.png,.jpg,.jpeg,.xls,.xlsx">
                                        <div class="help">PDF/JPG/PNG/XLS/XLSX até 10MB.</div>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label">Descrição</label>
                                        <textarea name="descricao" rows="4" class="form-control" placeholder="Detalhe da conta, período de referência, observações..."></textarea>
                                    </div>
                                </div>

                                <div class="mt-4 d-flex gap-2">
                                    <a href="./solicitarPagamentoConta.php?id=<?= urlencode($idSelecionado); ?>" class="btn btn-secondary">
                                        <i class="bx bx-left-arrow-alt"></i> Voltar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bx bx-send"></i> Enviar Solicitação
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

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

    <!-- JS -->
    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../assets/vendor/js/menu.js"></script>
    <script src="../../assets/js/main.js"></script>

    <script>
        /** Validação simples no client para ajudar o usuário */
        (function() {
            const form = document.getElementById('formNovaSolic');
            form.addEventListener('submit', function(e) {
                const fornecedor = form.fornecedor.value.trim();
                if (!fornecedor) {
                    e.preventDefault();
                    alert('Informe o fornecedor.');
                    form.fornecedor.focus();
                    return false;
                }
                const valor = form.valor.value.trim();
                if (!valor) {
                    e.preventDefault();
                    alert('Informe o valor.');
                    form.valor.focus();
                    return false;
                }
                const venc = form.vencimento.value.trim();
                if (!venc) {
                    e.preventDefault();
                    alert('Informe o vencimento.');
                    form.vencimento.focus();
                    return false;
                }
                return true;
            });

            // máscara suave para valor
            const valorInput = document.querySelector('input[name="valor"]');
            valorInput.addEventListener('blur', () => {
                let v = valorInput.value.trim();
                if (!v) return;
                v = v.replace(/\./g, '').replace(',', '.');
                const n = parseFloat(v);
                if (!isNaN(n)) {
                    valorInput.value = n.toLocaleString('pt-BR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            });
        })();
    </script>
</body>

</html>
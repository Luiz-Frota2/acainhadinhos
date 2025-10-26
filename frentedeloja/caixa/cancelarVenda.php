<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// ✅ Recupera o identificador vindo da URL
$idSelecionado = $_GET['id'] ?? '';

// ✅ Verifica se a pessoa está logada
if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id']) ||
    !isset($_SESSION['nivel'])
) {
    header("Location: ./index.php?id=$idSelecionado");
    exit;
}

// ✅ Conexão com o banco de dados
require '../../assets/php/conexao.php';

/** Helpers **/
function soDigitos(string $v): string
{
    return preg_replace('/\D+/', '', $v) ?? '';
}

$nomeUsuario       = 'Usuário';
$tipoUsuario       = 'Comum';
$usuario_id        = (int)$_SESSION['usuario_id'];
$tipoUsuarioSessao = $_SESSION['nivel']; // "Admin" ou "Comum"

// ⛏️ Tentar obter CPF do usuário logado
$cpfUsuario = '';
if (!empty($_SESSION['cpf'])) {
    $cpfUsuario = soDigitos((string)$_SESSION['cpf']);
} else {
    try {
        if ($tipoUsuarioSessao === 'Admin') {
            $stmtCpf = $pdo->prepare("SELECT cpf FROM contas_acesso WHERE id = :id LIMIT 1");
        } else {
            $stmtCpf = $pdo->prepare("SELECT cpf FROM funcionarios_acesso WHERE id = :id LIMIT 1");
        }
        $stmtCpf->execute([':id' => $usuario_id]);
        $rowCpf = $stmtCpf->fetch(PDO::FETCH_ASSOC);
        if ($rowCpf && !empty($rowCpf['cpf'])) {
            $cpfUsuario = soDigitos((string)$rowCpf['cpf']);
        }
    } catch (Throwable $e) {
        // Mantém $cpfUsuario = ''
    }
}

try {
    if ($tipoUsuarioSessao === 'Admin') {
        $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    } else {
        $stmt = $pdo->prepare("SELECT usuario, nivel FROM funcionarios_acesso WHERE id = :id");
    }
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $nomeUsuario = $usuario['usuario'];
        $tipoUsuario = ucfirst($usuario['nivel']);
    } else {
        echo "<script>alert('Usuário não encontrado.'); window.location.href = './index.php?id=$idSelecionado';</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar nome e tipo do usuário: " . addslashes($e->Message()) . "'); history.back();</script>";
    exit;
}

// ✅ Valida o tipo de empresa e o acesso permitido
if (str_starts_with($idSelecionado, 'principal_')) {
    if (
        $_SESSION['tipo_empresa'] !== 'principal' &&
        !($tipoUsuarioSessao === 'Admin' && $_SESSION['empresa_id'] === 'principal_1')
    ) {
        echo "<script>alert('Acesso negado!'); window.location.href = './index.php?id=$idSelecionado';</script>";
        exit;
    }
    $id = 1;
} elseif (str_starts_with($idSelecionado, 'unidade_')) {
    $idUnidade = str_replace('unidade_', '', $idSelecionado);
    $acessoPermitido = ($_SESSION['empresa_id'] === $idSelecionado) ||
        ($tipoUsuarioSessao === 'Admin' && $_SESSION['empresa_id'] === 'principal_1');
    if (!$acessoPermitido) {
        echo "<script>alert('Acesso negado!'); window.location.href = './index.php?id=$idSelecionado';</script>";
        exit;
    }
    $id = $idUnidade;
} else {
    echo "<script>alert('Empresa não identificada!'); window.location.href = './index.php?id=$idSelecionado';</script>";
    exit;
}

// ✅ Buscar imagem da empresa para usar como favicon
$iconeEmpresa = '../../assets/img/favicon/favicon.ico'; // Ícone padrão

try {
    $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1");
    $stmt->bindParam(':id_selecionado', $idSelecionado);
    $stmt->execute();
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($empresa && !empty($empresa['imagem'])) {
        $iconeEmpresa = $empresa['imagem'];
    }
} catch (PDOException $e) {
    error_log("Erro ao carregar ícone da empresa: " . $e->getMessage());
}

/* ========= Buscar abertura aberta (opcional, se precisar do id_caixa) ========= */
$idAbertura = null;
try {
    $stmt = $pdo->prepare("
        SELECT id
          FROM aberturas
         WHERE empresa_id = :empresa_id
           AND cpf_responsavel = :cpf_responsavel
           AND status = 'aberto'
      ORDER BY id DESC
         LIMIT 1
    ");
    $stmt->execute([
        ':empresa_id'      => $idSelecionado,
        ':cpf_responsavel' => $cpfUsuario
    ]);
    $abertura = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($abertura) $idAbertura = (int)$abertura['id'];
} catch (PDOException $e) {
    error_log("Erro ao buscar abertura: " . $e->getMessage());
}

/* ========= Buscar VENDAS (não venda_rapida) ========= */
try {
    $sqlVendas = "
        SELECT id, valor_total, data_venda, forma_pagamento, status_nfce
          FROM vendas
         WHERE empresa_id = :empresa_id
           AND (
                (:cpf <> '' AND REPLACE(REPLACE(REPLACE(cpf_responsavel, '.', ''), '-', ''), '/', '') = :cpf)
             OR (:cpf = '' AND responsavel = :responsavel)
           )
      ORDER BY data_venda DESC
         LIMIT 300
    ";
    $stmt = $pdo->prepare($sqlVendas);
    $stmt->execute([
        ':empresa_id'  => $idSelecionado,
        ':cpf'         => $cpfUsuario,
        ':responsavel' => $nomeUsuario,
    ]);
    $vendas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Erro ao buscar vendas: " . htmlspecialchars($e->getMessage());
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">
<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>ERP - PDV</title>
    <meta name="description" content="" />
    <link rel="icon" type="image/x-icon" href="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />
    <script src="../../assets/vendor/js/helpers.js"></script>
    <script src="../../assets/js/config.js"></script>
</head>
<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- Menu -->
            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="app-brand-link">
                        <span class="app-brand-text demo menu-text fw-bolder ms-2" style=" text-transform: capitalize;">Açaínhadinhos</span>
                    </a>
                    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
                        <i class="bx bx-chevron-left bx-sm align-middle"></i>
                    </a>
                </div>

                <div class="menu-inner-shadow"></div>

                <ul class="menu-inner py-1">
                    <li class="menu-item">
                        <a href="index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Dashboard</div>
                        </a>
                    </li>

                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Frente de Caixa</span></li>

                    <li class="menu-item ">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-barcode-reader"></i>
                            <div data-i18n="Caixa">Operações de Caixa</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./abrirCaixa.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Abrir Caixa</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./fecharCaixa.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Fechar Caixa</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./sangria.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Sangria</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./suprimento.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Suprimento</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="menu-item">
                        <a href="./vendaRapida.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-cart-alt"></i>
                            <div data-i18n="Vendas">Venda Rápida</div>
                        </a>
                    </li>

                    <li class="menu-item active">
                        <a href="./cancelarVenda.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-x-circle"></i>
                            <div data-i18n="Cancelamento">Cancelar Venda</div>
                        </a>
                    </li>

                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-bar-chart-alt"></i>
                            <div data-i18n="Relatórios">Relatórios</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./relatorioVendas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Resumo de Vendas</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
                    <li class="menu-item">
                        <a href="../sistemadeponto/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-group"></i>
                            <div data-i18n="Authentications">Sistema de Ponto</div>
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
                            <div class="nav-item d-flex align-items-center"></div>
                        </div>

                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <img src="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>" alt class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>" alt class="w-px-40 h-auto rounded-circle" />
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <span class="fw-semibold d-block"><?= htmlspecialchars($nomeUsuario); ?></span>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                    <li><div class="dropdown-divider"></div></li>
                                    <li><a class="dropdown-item" href="#"><i class="bx bx-user me-2"></i><span class="align-middle">Minha conta</span></a></li>
                                    <li><a class="dropdown-item" href="#"><i class="bx bx-cog me-2"></i><span class="align-middle">Configurações</span></a></li>
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <span class="d-flex align-items-center align-middle">
                                                <i class="flex-shrink-0 bx bx-credit-card me-2"></i>
                                                <span class="flex-grow-1 align-middle">Billing</span>
                                                <span class="flex-shrink-0 badge badge-center rounded-pill bg-danger w-px-20 h-px-20">4</span>
                                            </span>
                                        </a>
                                    </li>
                                    <li><div class="dropdown-divider"></div></li>
                                    <li><a class="dropdown-item" href="../logout.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-power-off me-2"></i><span class="align-middle">Sair</span></a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </nav>

                <!-- CONTEÚDO PRINCIPAL -->
                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold mb-0">
                        <span class="text-muted fw-light">
                            <a href="./pontoRegistrado.php">Frente de Caixa</a> /
                        </span>
                        Cancelar Venda
                    </h4>
                    <h5 class="fw-semibold mt-2 mb-4 text-muted">Selecione uma venda para cancelar</h5>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive text-nowrap">
                                <?php if (empty($vendas)): ?>
                                    <div id="avisoSemCaixa" class="alert alert-danger text-center">
                                        Nenhuma venda encontrada. Por favor, abra um caixa para continuar com a venda.
                                    </div>
                                <?php else: ?>
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>#ID</th>
                                                <th>Forma de Pagamento</th>
                                                <th>Valor Total</th>
                                                <th>Data - Hora</th>
                                                <th>Status</th>
                                                <th>Ação</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($vendas as $venda): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($venda['id']) ?></td>
                                                    <td><?= htmlspecialchars($venda['forma_pagamento']) ?></td>
                                                    <td>R$ <?= number_format((float)$venda['valor_total'], 2, ',', '.') ?></td>
                                                    <td><?= htmlspecialchars(date('d/m/Y - H:i', strtotime($venda['data_venda']))) ?></td>
                                                    <td>
                                                        <?php
                                                        $status = $venda['status_nfce'] ?: 'Finalizada';
                                                        $badge = ($status === 'autorizada') ? 'bg-success' : (($status === 'cancelada') ? 'bg-danger' : 'bg-secondary');
                                                        ?>
                                                        <span class="badge <?= $badge ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
                                                    </td>
                                                    <td>
                                                        <!-- Botão para abrir a modal com as 3 opções -->
                                                        <button 
                                                            type="button" 
                                                            class="btn btn-danger btn-cancelar-venda"
                                                            data-venda-id="<?= (int)$venda['id'] ?>"
                                                            data-empresa-id="<?= htmlspecialchars($idSelecionado) ?>"
                                                            data-status="<?= htmlspecialchars($status) ?>"
                                                            title="Opções de cancelamento (NFC-e / venda interna)">
                                                            Cancelar
                                                        </button>
                                                        <!-- fim botão -->
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>

                                            <?php if (isset($idAbertura)): ?>
                                                <input type='hidden' id='id_caixa' name='id_caixa' value='<?= (int)$idAbertura ?>'>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- FIM CONTEÚDO PRINCIPAL -->
            </div>
        </div>
    </div>

    <!-- Modal Única: 3 opções de cancelamento (envio para ../nfce/cancelar_venda_processa.php) -->
    <div class="modal fade" id="modalCancelarVenda" tabindex="-1" aria-labelledby="modalCancelarVendaLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title fw-bold" id="modalCancelarVendaLabel">
              Cancelar venda <span id="cv-venda-id-badge" class="badge bg-label-danger ms-1">#</span>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>

          <div class="modal-body pt-2">
            <p class="mb-3 text-muted">
              Selecione o tipo de cancelamento para a <strong>venda <span id="cv-venda-id-text">-</span></strong>.
            </p>

            <!-- Opção 1: Interno -->
            <form id="form-cv-interno" class="mb-2" method="post" action="../nfce/cancelar_venda_processa.php">
              <input type="hidden" name="id" value="">
              <input type="hidden" name="venda_id" value="">
              <input type="hidden" name="acao" value="interno">
              <button type="submit" class="list-group-item list-group-item-action d-flex align-items-start w-100 btn btn-link p-0 text-start">
                <span class="d-flex align-items-start w-100 p-3">
                  <i class="bx bx-x-circle fs-3 me-3"></i>
                  <span>
                    <span class="fw-semibold d-block">Cancelar venda (interno)</span>
                    <small class="text-muted">Estorna a venda no sistema (estoque/financeiro), sem evento SEFAZ.</small>
                  </span>
                </span>
              </button>
            </form>

            <!-- Opção 2: Evento 110111 -->
            <form id="form-cv-110111" class="mb-2" method="post" action="../nfce/cancelar_venda_processa.php">
              <input type="hidden" name="id" value="">
              <input type="hidden" name="venda_id" value="">
              <input type="hidden" name="acao" value="110111">
              <button type="submit" class="list-group-item list-group-item-action d-flex align-items-start w-100 btn btn-link p-0 text-start">
                <span class="d-flex align-items-start w-100 p-3">
                  <i class="bx bx-file-minus fs-3 me-3"></i>
                  <span>
                    <span class="fw-semibold d-block">Cancelar NFC-e — Evento 110111</span>
                    <small class="text-muted">Envia o cancelamento oficial da NFC-e autorizada.</small>
                  </span>
                </span>
              </button>
            </form>

            <!-- Opção 3: Inutilização 110112 -->
            <form id="form-cv-110112" method="post" action="./cancelar_venda_processa_unico.php">
              <input type="hidden" name="id" value="">
              <input type="hidden" name="venda_id" value="">
              <input type="hidden" name="acao" value="110112">
              <button type="submit" class="list-group-item list-group-item-action d-flex align-items-start w-100 btn btn-link p-0 text-start">
                <span class="d-flex align-items-start w-100 p-3">
                  <i class="bx bx-block fs-3 me-3"></i>
                  <span>
                    <span class="fw-semibold d-block">Inutilizar numeração — 110112</span>
                    <small class="text-muted">Para numeração sem uso. Não cancela NFC-e já autorizada.</small>
                  </span>
                </span>
              </button>
            </form>

            <div class="alert alert-info mt-3 mb-0" role="alert">
              <i class="bx bx-info-circle me-2"></i>
              <strong>Status NFC-e:</strong> <span id="cv-status-nfce">—</span>
            </div>
          </div>

          <div class="modal-footer border-0 pt-0">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
          </div>
        </div>
      </div>
    </div>
    <!-- /Modal -->

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const idCaixa = document.getElementById('id_caixa');
            const form = document.querySelector('table');
            const aviso = document.getElementById('avisoSemCaixa');

            if (!idCaixa || !idCaixa.value.trim()) {
                if (form) form.style.display = 'none';
                if (aviso) aviso.style.display = 'block';
            }
        });
    </script>

    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../assets/vendor/js/menu.js"></script>
    <script src="../../assets/js/main.js"></script>

    <script>
    (function(){
      // Preenche hidden inputs dos 3 formulários e abre a modal
      document.addEventListener('click', function(ev){
        const btn = ev.target.closest('.btn-cancelar-venda');
        if (!btn) return;

        const vendaId   = btn.getAttribute('data-venda-id') || '';
        const empresaId = btn.getAttribute('data-empresa-id') || '';
        const status    = btn.getAttribute('data-status') || '-';

        document.getElementById('cv-venda-id-badge').textContent = '#' + vendaId;
        document.getElementById('cv-venda-id-text').textContent  = '#' + vendaId;
        document.getElementById('cv-status-nfce').textContent    = status;

        // Preenche <input hidden> de cada form
        ['form-cv-interno','form-cv-110111','form-cv-110112'].forEach(function(fid){
          const f = document.getElementById(fid);
          if (!f) return;
          const idInput     = f.querySelector('input[name="id"]');
          const vendaInput  = f.querySelector('input[name="venda_id"]');
          if (idInput)    idInput.value = empresaId;
          if (vendaInput) vendaInput.value = vendaId;
        });

        // Abre modal via Bootstrap
        const modalEl = document.getElementById('modalCancelarVenda');
        const bsModal = new bootstrap.Modal(modalEl, { backdrop: 'static' });
        bsModal.show();
      });
    })();
    </script>

<?php
  // Garante que a UI receba empresa_id e venda_id mesmo se vieram de sessão
  if (!isset($_REQUEST['id']) && !empty($empresaId))   $_REQUEST['id'] = $_GET['id'] = $empresaId;
  if (!isset($_REQUEST['venda_id']) && !empty($vendaId)) $_REQUEST['venda_id'] = $_GET['venda_id'] = (string)$vendaId;

  /* ========= inclui a UI de cancelamento (vários caminhos) ========= */
  $__cv_paths = [
    __DIR__ . '/cancelar_venda_ui.php',
    __DIR__ . '/../nfce/cancelar_venda_ui.php',
    __DIR__ . '/../frentedeloja/caixa/cancelar_venda_ui.php',
    __DIR__ . '/../modals/cancelar_venda_ui.php',
    __DIR__ . '/modals/cancelar_venda_ui.php',
  ];
  $__cv_included = false;
  foreach ($__cv_paths as $__p) {
    if (is_file($__p)) {
      include $__p;
      $__cv_included = true;
      break;
    }
  }

  /* ========= fallback minimal se o arquivo não existir ========= */
  if (!$__cv_included): ?>
    <div id="cv-overlay" class="fallback" style="display:none" aria-hidden="true">
      <div class="cv-modal" role="dialog" aria-modal="true" style="display:none">
        <h3>Cancelar venda</h3>
        <p>Interface de cancelamento padrão não encontrada.</p>
        <div class="cv-actions">
          <button type="button" class="btn" onclick="cvClose()">Fechar</button>
        </div>
      </div>
    </div>
    <script>
      if (typeof window.cvOpen !== 'function') {
        window.cvOpen = function() {
          var ov = document.getElementById('cv-overlay');
          var md = ov ? ov.querySelector('.cv-modal') : null;
          if (ov) {
            ov.style.display = 'grid';
            ov.removeAttribute('aria-hidden');
          }
          if (md) {
            md.style.display = 'block';
            md.focus && md.focus();
          }
        };
      }
      if (typeof window.cvClose !== 'function') {
        window.cvClose = function() {
          var ov = document.getElementById('cv-overlay');
          var md = ov ? ov.querySelector('.cv-modal') : null;
          if (md) md.style.display = 'none';
          if (ov) {
            ov.style.display = 'none';
            ov.setAttribute('aria-hidden', 'true');
          }
        };
      }
    </script>
  <?php endif; ?>

</body>
</html>

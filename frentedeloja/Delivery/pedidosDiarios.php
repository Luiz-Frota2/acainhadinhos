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
  !isset($_SESSION['nivel']) // Verifica se o nível está na sessão
) {
  header("Location: ../index.php?id=$idSelecionado");
  exit;
}

// ✅ Conexão com o banco de dados
require '../../assets/php/conexao.php';

$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id = $_SESSION['usuario_id'];
$tipoUsuarioSessao = $_SESSION['nivel']; // "Admin" ou "Comum"

try {
  // Verifica se é um usuário de contas_acesso (Admin) ou funcionarios_acesso
  if ($tipoUsuarioSessao === 'Admin') {
    // Buscar na tabela de contas_acesso
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
  } else {
    // Buscar na tabela de funcionarios_acesso
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
  echo "<script>alert('Erro ao carregar nome e tipo do usuário: " . addslashes($e->getMessage()) . "'); history.back();</script>";
  exit;
}

// ✅ Valida o tipo de empresa e o acesso permitido
if (str_starts_with($idSelecionado, 'principal_')) {
  // Para principal, verifica se é admin ou se pertence à mesma empresa
  if (
    $_SESSION['tipo_empresa'] !== 'principal' &&
    !($tipoUsuarioSessao === 'Admin' && $_SESSION['empresa_id'] === 'principal_1')
  ) {
    echo "<script>
            alert('Acesso negado!');
            window.location.href = '../index.php?id=$idSelecionado';
        </script>";
    exit;
  }
  $id = 1;
} elseif (str_starts_with($idSelecionado, 'unidade_')) {
  $idUnidade = str_replace('unidade_', '', $idSelecionado);

  // Verifica se o usuário pertence à mesma unidade ou é admin da principal_1
  $acessoPermitido = ($_SESSION['empresa_id'] === $idSelecionado) ||
    ($tipoUsuarioSessao === 'Admin' && $_SESSION['empresa_id'] === 'principal_1');

  if (!$acessoPermitido) {
    echo "<script>
            alert('Acesso negado!');
            window.location.href = '../index.php?id=$idSelecionado';
        </script>";
    exit;
  }
  $id = $idUnidade;
} else {
  echo "<script>
        alert('Empresa não identificada!');
        window.location.href = '../index.php?id=$idSelecionado';
    </script>";
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
  // Não mostra erro para o usuário para não quebrar a página
}

// ===============================
// 1. ATUALIZAR STATUS DO PEDIDO
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pedido_id'], $_POST['novo_status'])) {
    $pedidoId     = (int) $_POST['pedido_id'];
    $novoStatus   = $_POST['novo_status'] ?? '';
    $empresaSessao = $_SESSION['empresa_id'] ?? '';

    // status permitidos exatamente como estão na tabela
    $statusPermitidos = ['pendente', 'aceito', 'cancelado', 'entergue'];

    if ($pedidoId > 0 && in_array($novoStatus, $statusPermitidos, true) && !empty($empresaSessao)) {
        try {
            $stmtUpdate = $pdo->prepare("
                UPDATE rascunho 
                SET status = :status 
                WHERE id = :id 
                  AND empresa_id = :empresa_id
            ");
            $stmtUpdate->execute([
                ':status'      => $novoStatus,
                ':id'          => $pedidoId,
                ':empresa_id'  => $empresaSessao
            ]);
        } catch (PDOException $e) {
            error_log("Erro ao atualizar status do pedido: " . $e->getMessage());
        }
    }

    // Redireciona para evitar re-envio de formulário
    header("Location: pedidosDiarios.php?id=" . urlencode($idSelecionado));
    exit;
}

// ===============================
// 2. BUSCAR PEDIDOS PENDENTES DO DIA
// ===============================

// empresa_id do usuário logado
$empresaLogada = $idSelecionado ?? '';

// Se quiser vincular ao id da URL também, pode forçar aqui:
// $empresaLogada = $idSelecionado;

$pedidos = [];
$itensPorPedido = [];

if (!empty($empresaLogada)) {
    try {
        // Somente pedidos PENDENTES do DIA (data_pedido = hoje)
        $sqlPedidos = "
            SELECT 
                id,
                empresa_id,
                nome_cliente,
                telefone_cliente,
                endereco,
                forma_pagamento,
                detalhe_pagamento,
                total,
                taxa_entrega,
                data_pedido,
                status
            FROM rascunho
            WHERE empresa_id = :empresa_id
              AND status = 'pendente'
              AND DATE(data_pedido) = CURDATE()
            ORDER BY data_pedido DESC
        ";

        $stmtPedidos = $pdo->prepare($sqlPedidos);
        $stmtPedidos->execute([
            ':empresa_id' => $empresaLogada
        ]);

        $pedidos = $stmtPedidos->fetchAll(PDO::FETCH_ASSOC);

        // ===============================
        // 3. BUSCAR ITENS DE TODOS OS PEDIDOS DE UMA VEZ
        // ===============================
        if (!empty($pedidos)) {
            $idsPedidos = array_column($pedidos, 'id');

            // Monta placeholders para IN (?,?,?,...)
            $placeholders = implode(',', array_fill(0, count($idsPedidos), '?'));

            $sqlItens = "
                SELECT 
                    id,
                    empresa_id,
                    pedido_id,
                    nome_item,
                    quantidade,
                    preco_unitario,
                    observacao,
                    opcionais_json
                FROM rascunho_itens
                WHERE empresa_id = ?
                  AND pedido_id IN ($placeholders)
                ORDER BY id ASC
            ";

            $params = array_merge([$empresaLogada], $idsPedidos);

            $stmtItens = $pdo->prepare($sqlItens);
            $stmtItens->execute($params);

            while ($row = $stmtItens->fetch(PDO::FETCH_ASSOC)) {
                $pedidoId = (int) $row['pedido_id'];
                if (!isset($itensPorPedido[$pedidoId])) {
                    $itensPorPedido[$pedidoId] = [];
                }
                $itensPorPedido[$pedidoId][] = $row;
            }
        }
    } catch (PDOException $e) {
        error_log("Erro ao buscar pedidos: " . $e->getMessage());
    }
}


?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
    data-assets-path="../../assets/" data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>ERP - Delivery</title>

    <meta name="description" content="" />

    <!-- Favicon da empresa carregado dinamicamente -->
    <link rel="icon" type="image/x-icon"
        href="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>" />

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

    <!-- Page CSS -->

    <!-- Helpers -->
    <script src="../../assets/vendor/js/helpers.js"></script>

    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="../../assets/js/config.js"></script>
</head>

<body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">

            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="app-brand-link">
                        <span class="app-brand-text demo menu-text fw-bolder ms-2"
                            style=" text-transform: capitalize;">Açaínhadinhos</span>
                    </a>

                    <a href="javascript:void(0);"
                        class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
                        <i class="bx bx-chevron-left bx-sm align-middle"></i>
                    </a>
                </div>

                <div class="menu-inner-shadow"></div>

                <ul class="menu-inner py-1">
                    <!-- Dashboard -->
                    <li class="menu-item">
                        <a href="index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Dashboard</div>
                        </a>
                    </li>

                    <!-- DELIVERY -->
                    <li class="menu-header small text-uppercase">
                        <span class="menu-header-text">Delivery</span>
                    </li>

                    <!-- Pedidos -->
                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-cart"></i>
                            <div data-i18n="Authentications">Pedidos</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item active">
                                <a href="./pedidosDiarios.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Pedidos Diários</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./pedidosAceitos.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Pedidos Aceitos</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./pedidosACaminho.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Pedidos a Caminho</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./pedidosEntregues.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Pedidos Entregues</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./pedidosCancelados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Pedidos Cancelados</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- Cardápio -->
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-food-menu"></i>
                            <div data-i18n="Authentications">Cardápio</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./produtosAdicionados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Produtos Adicionados</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- Relatórios -->
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-trending-up"></i>
                            <div data-i18n="Authentications">Relatórios</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="#" class="menu-link">
                                    <div data-i18n="Basic">Lista de Pedidos</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="#" class="menu-link">
                                    <div data-i18n="Basic">Mais Vendidos</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="#" class="menu-link">
                                    <div data-i18n="Basic">Clientes</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="#" class="menu-link">
                                    <div data-i18n="Basic">Vendas< ?>/div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- MISC -->
                    <li class="menu-header small text-uppercase">
                        <span class="menu-header-text">Diversos</span>
                    </li>
                    <li class="menu-item">
                        <a href="../caixa/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-barcode-reader"></i>
                            <div data-i18n="Basic">Caixa</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../sistemadeponto/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-cart"></i>
                            <div data-i18n="Basic">Sistema de Ponto</div>
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

                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
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
                            <!-- Place this tag where you want the button to render. -->
                            <!-- User -->
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);"
                                    data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <img src="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>"
                                            alt class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>"
                                                            alt class="w-px-40 h-auto rounded-circle" />
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <span
                                                        class="fw-semibold d-block"><?= htmlspecialchars($nomeUsuario); ?></span>
                                                </div>
                                            </div>
                                        </a>
                                    </li>

                                    <li>
                                        <div class="dropdown-divider"></div>
                                    </li>
                                    <li>
                                        <a class="dropdown-item"
                                            href="../logout.php?id=<?= urlencode($idSelecionado); ?>">
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

                    <h4 class="fw-bold mb-4">
                        <span class="text-muted fw-light">
                            <a href="#">Delivery</a> /
                        </span>
                        Pedidos Diários
                    </h4>

                    <div class="card">
                        <h5 class="card-header">Pedidos Recebidos Hoje</h5>

                        <div class="table-responsive">
                            <table class="table text-nowrap">
                                       <thead>
            <tr>
                <th>#</th>
                <th>Cliente</th>
                <th>Endereço</th>
                <th>Pagamento</th>
                <th>Total</th>
                <th>Hora</th>
                <th>Ações</th>
            </tr>
        </thead>

        <tbody>
            <?php if (empty($pedidos)): ?>
                <tr>
                    <td colspan="7" class="text-center">
                        Nenhum pedido pendente recebido hoje.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($pedidos as $pedido): ?>
                    <?php
                        $idPedido   = (int) $pedido['id'];
                        $itens      = $itensPorPedido[$idPedido] ?? [];
                        $totalGeral = (float) $pedido['total'] + (float) $pedido['taxa_entrega'];
                    ?>
                    <tr>
                        <td>#<?= htmlspecialchars($idPedido); ?></td>

                        <td>
                            <?= htmlspecialchars($pedido['nome_cliente']); ?><br>
                            <small><?= htmlspecialchars($pedido['telefone_cliente']); ?></small>
                        </td>

                        <td><?= nl2br(htmlspecialchars($pedido['endereco'])); ?></td>

                        <td>
                            <?= htmlspecialchars($pedido['forma_pagamento']); ?><br>
                            <?php if (!empty($pedido['detalhe_pagamento'])): ?>
                                <small><?= htmlspecialchars($pedido['detalhe_pagamento']); ?></small>
                            <?php endif; ?>
                        </td>

                        <td><b>R$ <?= number_format($totalGeral, 2, ',', '.'); ?></b></td>

                        <td><?= date('H:i', strtotime($pedido['data_pedido'])); ?></td>

                        <td>
                            <div class="d-flex gap-2">
                                <!-- AÇÕES -->
                                <button class="btn btn-secondary btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#acao<?= $idPedido; ?>">
                                    Ações
                                </button>

                                <!-- ITENS -->
                                <button class="btn btn-primary btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#itens<?= $idPedido; ?>">
                                    Itens
                                </button>
                            </div>
                        </td>
                    </tr>

                    <!-- MODAL AÇÕES (DINÂMICO) -->
                    <div class="modal fade" id="acao<?= $idPedido; ?>">
                        <div class="modal-dialog modal-dialog-scrollable modal-dialog-top">
                            <div class="modal-content">

                                <div class="modal-header">
                                    <h5 class="modal-title">Ações do Pedido #<?= $idPedido; ?></h5>
                                    <button class="btn-close" data-bs-dismiss="modal"></button>
                                </div>

                                <div class="modal-body">
                                    <form method="post" action="pedidosDiarios.php?id=<?= urlencode($idSelecionado); ?>">
                                        <input type="hidden" name="pedido_id" value="<?= $idPedido; ?>">

                                        <label class="form-label">Selecione uma ação:</label>
                                        <select class="form-select" name="novo_status" required>
                                            <option value="" disabled selected>Selecionar ação...</option>

                                            <?php if ($pedido['status'] === 'pendente'): ?>
                                                <option value="aceito">Aceitar Pedido</option>
                                            <?php endif; ?>

                                            <?php if ($pedido['status'] !== 'cancelado'): ?>
                                                <option value="cancelado">Cancelar Pedido</option>
                                            <?php endif; ?>

                                            <?php if ($pedido['status'] !== 'entergue'): ?>
                                                <option value="entergue">Marcar como Entregue</option>
                                            <?php endif; ?>
                                        </select>

                                        <button type="submit" class="btn btn-primary mt-3 w-100">
                                            Confirmar
                                        </button>
                                    </form>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- MODAL ITENS (DINÂMICO) -->
                    <div class="modal fade" id="itens<?= $idPedido; ?>">
                        <div class="modal-dialog modal-dialog-scrollable modal-dialog-top">
                            <div class="modal-content">

                                <div class="modal-header">
                                    <h5 class="modal-title">Itens do Pedido #<?= $idPedido; ?></h5>
                                    <button class="btn-close" data-bs-dismiss="modal"></button>
                                </div>

                                <div class="modal-body">
                                    <?php if (empty($itens)): ?>
                                        <p>Este pedido não possui itens cadastrados.</p>
                                    <?php else: ?>
                                        <ul class="list-group">
                                            <?php foreach ($itens as $item): ?>
                                                <?php
                                                   $linha = (int) $item['quantidade'] . 'x ' . $item['nome_item'];
$valor = (float) $item['preco_unitario'];
$opcionaisTexto = '';

if (!empty($item['opcionais_json'])) {
    $opsDecoded = json_decode($item['opcionais_json'], true);

    $nomes = [];

    if (is_array($opsDecoded) && !empty($opsDecoded)) {

        // Se vier um único objeto associativo, transforma em array de 1
        if (array_keys($opsDecoded) !== range(0, count($opsDecoded) - 1)) {
            $opsDecoded = [$opsDecoded];
        }

        foreach ($opsDecoded as $op) {
            // Se não for array, é um valor simples (string, número, etc.)
            if (!is_array($op)) {
                $nomes[] = (string) $op;
                continue;
            }

            $capturado = false;

            // tenta pegar campos mais comuns
            foreach (['nome', 'nome_opcional', 'descricao', 'label', 'titulo'] as $campo) {
                if (isset($op[$campo]) && !is_array($op[$campo])) {
                    $nomes[] = (string) $op[$campo];
                    $capturado = true;
                    break;
                }
            }

            if ($capturado) {
                continue;
            }

            // se ainda não capturou, pega o primeiro valor escalar desse array
            foreach ($op as $valorBruto) {
                if (!is_array($valorBruto)) {
                    $nomes[] = (string) $valorBruto;
                    break;
                }
            }
        }
    }

    if (!empty($nomes)) {
        // aqui temos APENAS STRINGS dentro de $nomes
        $opcionaisTexto = implode(', ', $nomes);
    }
}

                                                ?>
                                                <li class="list-group-item">
                                                    <b><?= htmlspecialchars($linha); ?></b><br>
                                                    <small>R$ <?= number_format($valor, 2, ',', '.'); ?></small>

                                                    <?php if (!empty($opcionaisTexto)): ?>
                                                        <br><small><b>Opcionais:</b> <?= htmlspecialchars($opcionaisTexto); ?></small>
                                                    <?php endif; ?>

                                                    <?php if (!empty($item['observacao'])): ?>
                                                        <br><small><b>Obs:</b> <?= nl2br(htmlspecialchars($item['observacao'])); ?></small>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>

                            </div>
                        </div>
                    </div>

                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>

                            </table>

                        </div>
                    </div>

                </div>
                <!-- / Content -->

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
    <script src="../../js/graficoDashboard.js"></script>

    <!-- Main JS -->
    <script src="../../assets/js/main.js"></script>

    <!-- Page JS -->
    <script src="../../assets/js/dashboards-analytics.js"></script>

    <!-- Place this tag in your head or just before your close body tag. -->
    <script async defer src="https://buttons.github.io/buttons.js"></script>
</body>

</html>
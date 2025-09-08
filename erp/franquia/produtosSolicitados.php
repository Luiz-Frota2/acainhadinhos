<?php
session_start();
require_once '../../assets/php/conexao.php';

// Recupera o identificador vindo da URL
$idSelecionado = $_GET['id'] ?? '';

// Verifica se a pessoa está logada
if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id'])
) {
    header("Location: .././login.php?id=$idSelecionado");
    exit;
}

// Valida o tipo de empresa e o acesso permitido
if (str_starts_with($idSelecionado, 'principal_')) {
    if ($_SESSION['tipo_empresa'] !== 'principal' || $_SESSION['empresa_id'] != 1) {
        echo "<script>
              alert('Acesso negado!');
              window.location.href = '.././login.php?id=$idSelecionado';
          </script>";
        exit;
    }
    $id = 1;
} elseif (str_starts_with($idSelecionado, 'filial_')) {
    $idFilial = (int) str_replace('filial_', '', $idSelecionado);
    if ($_SESSION['tipo_empresa'] !== 'filial' || $_SESSION['empresa_id'] != $idFilial) {
        echo "<script>
              alert('Acesso negado!');
              window.location.href = '.././login.php?id=$idSelecionado';
          </script>";
        exit;
    }
    $id = $idFilial;
} else {
    echo "<script>
          alert('Empresa não identificada!');
          window.location.href = '.././login.php?id=$idSelecionado';
      </script>";
    exit;
}

// Buscar imagem da tabela sobre_empresa
try {
    $sql = "SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_selecionado', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);

    $logoEmpresa = !empty($empresaSobre['imagem'])
        ? "../../assets/img/empresa/" . $empresaSobre['imagem']
        : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png";
}

// Buscar nome e nível do usuário logado
$nomeUsuario = 'Usuário';
$nivelUsuario = 'Comum';
$usuario_id = $_SESSION['usuario_id'];

try {
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $nomeUsuario = $usuario['usuario'];
        $nivelUsuario = $usuario['nivel'];
    }
} catch (PDOException $e) {
    $nomeUsuario = 'Erro ao carregar nome';
    $nivelUsuario = 'Erro ao carregar nível';
}

// Buscar solicitações de produtos - versão modificada
$solicitacoes = [];
try {
    // Consulta tanto solicitações feitas POR esta empresa quanto PARA esta empresa
    $sql = "SELECT sp.*, 
                   p.nome_produto, 
                   fo.nome as nome_filial_origem,
                   fd.nome as nome_filial_destino
            FROM solicitacoes_produtos sp
            JOIN produtos_estoque p ON sp.produto_id = p.id
            LEFT JOIN filiais fo ON sp.empresa_origem = CONCAT('filial_', fo.id_filial)
            LEFT JOIN filiais fd ON sp.empresa_destino = CONCAT('filial_', fd.id_filial)
            WHERE sp.empresa_origem = :id_selecionado OR sp.empresa_destino = :id_selecionado
            ORDER BY sp.data_solicitacao DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_selecionado', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $solicitacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro ao buscar solicitações: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
    data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>ERP - B2B</title>

    <meta name="description" content="" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($logoEmpresa) ?>" />

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

            <!-- Layout container -->
            <div class="layout-container">

                <!-- Menu -->
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
                            <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                <i class="menu-icon tf-icons bx bx-home-circle"></i>
                                <div data-i18n="Analytics">Dashboard</div>
                            </a>
                        </li>

                        <!-- Administração de Filiais -->
                        <li class="menu-header small text-uppercase">
                            <span class="menu-header-text">Administração Filiais</span>
                        </li>

                        <!-- Adicionar Filial -->
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
                            <ul class="menu-sub">
                                <!-- Contas das Filiais -->
                                <li class="menu-item">
                                    <a href="./contasFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                        <div>Pagamentos Solic.</div>
                                    </a>
                                </li>

                                <!-- Produtos solicitados pelas filiais -->
                                <li class="menu-item active">
                                    <a href="./produtosSolicitados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                        <div>Produtos Solicitados</div>
                                    </a>
                                </li>

                                <!-- Produtos enviados pela matriz -->
                                <li class="menu-item">
                                    <a href="./produtosEnviados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                        <div>Produtos Enviados</div>
                                    </a>
                                </li>

                                <!-- Transferências em andamento -->
                                <li class="menu-item">
                                    <a href="./transferenciasPendentes.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                        <div>Transf. Pendentes</div>
                                    </a>
                                </li>

                                <!-- Histórico de transferências -->
                                <li class="menu-item">
                                    <a href="./historicoTransferencias.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                        <div>Histórico Transf.</div>
                                    </a>
                                </li>

                                <!-- Gestão de Estoque Central -->
                                <li class="menu-item">
                                    <a href="./estoqueMatriz.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                        <div>Estoque Matriz</div>
                                    </a>
                                </li>

                                <!-- Configurações de Política de Envio -->
                                <li class="menu-item">
                                    <a href="./politicasEnvio.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                        <div>Política de Envio</div>
                                    </a>
                                </li>

                                <!-- Relatórios e indicadores B2B -->
                                <li class="menu-item">
                                    <a href="./relatoriosB2B.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                        <div>Relatórios B2B</div>
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
                                    <a href="./VendasFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                        <div data-i18n="Vendas">Vendas por Filial</div>
                                    </a>
                                </li>
                                <li class="menu-item">
                                    <a href="./MaisVendidos.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                        <div data-i18n="MaisVendidos">Mais Vendidos</div>
                                    </a>
                                </li>
                                <li class="menu-item">
                                    <a href="./vendasPeriodo.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                        <div data-i18n="Pedidos">Vendas por Período</div>
                                    </a>
                                </li>
                                <li class="menu-item">
                                    <a href="./CancelamentosFiliais.php?id=<?= urlencode($idSelecionado); ?>"
                                        class="menu-link">
                                        <div data-i18n="Cancelamentos">Cancelamentos</div>
                                    </a>
                                </li>

                            </ul>
                        </li>
                        <!--END DELIVERY-->

                        <!-- Misc -->
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
                            <a href="../delivery/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                                <i class="menu-icon tf-icons bx bx-cart"></i>
                                <div data-i18n="Authentications">Delivery</div>
                            </a>
                        </li>
                        <li class="menu-item">
                            <a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                                <i class="menu-icon tf-icons bx bx-box"></i>
                                <div data-i18n="Authentications">Estoque</div>
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
                        <!--/MISC-->
                    </ul>
                </aside>
                <!-- / Menu -->

                <!-- layout-page -->
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
                                    <i class="bx bx-search fs-4 lh-0"></i>
                                    <input type="text" class="form-control border-0 shadow-none" placeholder="Search..."
                                        aria-label="Search..." />
                                </div>
                            </div>
                            <!-- /Search -->

                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="#">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0 me-3">
                                                <div class="avatar avatar-online">
                                                    <img src="<?= htmlspecialchars($logoEmpresa) ?>" alt
                                                        class="w-px-40 h-auto rounded-circle" />
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <!-- Exibindo o nome e nível do usuário -->
                                                <span class="fw-semibold d-block"><?php echo $nomeUsuario; ?></span>
                                                <small class="text-muted"><?php echo $nivelUsuario; ?></small>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                                <li>
                                    <div class="dropdown-divider"></div>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="#">
                                        <i class="bx bx-user me-2"></i>
                                        <span class="align-middle">Minha Conta</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="#">
                                        <i class="bx bx-cog me-2"></i>
                                        <span class="align-middle">Configurções</span>
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
                        </div>

                    </nav>
                    <!-- / Navbar -->

                    <!-- container-xxl -->
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a
                                    href="#">B2B</a>/</span> Produtos Solicitados</h4>
                        <h5 class="fw-bold mt-3 mb-3 custor-font"><span class="text-muted fw-light">Visualize e gerencie as
                                Solicitações de Produtos das Filiais</span></h5>

                        <!-- Restante do HTML permanece similar, mas com ajustes na exibição -->
                        <div class="card">
                            <h5 class="card-header">Histórico Completo de Solicitações</h5>
                            <div class="card">
                                <div class="table-responsive text-nowrap">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Produto</th>
                                                <th>Origem</th>
                                                <th>Destino</th>
                                                <th>Quantidade</th>
                                                <th>Data</th>
                                                <th>Status</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody class="table-border-bottom-0">
                                            <?php if (empty($solicitacoes)): ?>
                                                <tr>
                                                    <td colspan="7" class="text-center">Nenhuma solicitação encontrada</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($solicitacoes as $solicitacao): ?>
                                                    <?php
                                                    $badgeClass = match ($solicitacao['status']) {
                                                        'aprovada' => 'bg-success',
                                                        'recusada' => 'bg-danger',
                                                        'entregue' => 'bg-info',
                                                        default => 'bg-warning'
                                                    };

                                                    // Determina os nomes de origem e destino
                                                    $origem = $solicitacao['empresa_origem'] === 'principal_1'
                                                        ? 'Matriz'
                                                        : ($solicitacao['nome_filial_origem'] ?? 'Filial Desconhecida');

                                                    $destino = $solicitacao['empresa_destino'] === 'principal_1'
                                                        ? 'Matriz'
                                                        : ($solicitacao['nome_filial_destino'] ?? 'Filial Desconhecida');
                                                    ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($solicitacao['nome_produto']) ?></td>
                                                        <td><?= htmlspecialchars($origem) ?></td>
                                                        <td><?= htmlspecialchars($destino) ?></td>
                                                        <td><?= htmlspecialchars($solicitacao['quantidade']) ?> un.</td>
                                                        <td><?= date('d/m/Y H:i', strtotime($solicitacao['data_solicitacao'])) ?>
                                                        </td>
                                                        <td><span
                                                                class="badge <?= $badgeClass ?>"><?= ucfirst($solicitacao['status']) ?></span>
                                                        </td>
                                                        <td>
                                                            <button class="btn btn-link text-info p-0" title="Visualizar"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#modalSolicitacao<?= $solicitacao['id'] ?>">
                                                                <i class="tf-icons bx bx-show"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <?php foreach ($solicitacoes as $solicitacao): ?>
                            <!-- Modal para cada solicitação -->
                            <div class="modal fade" id="modalSolicitacao<?= $solicitacao['id'] ?>" tabindex="-1"
                                aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Detalhes da Solicitação #<?= $solicitacao['id'] ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <strong>Origem:</strong><br>
                                                    <?= htmlspecialchars($origem) ?>
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Destino:</strong><br>
                                                    <?= htmlspecialchars($destino) ?>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <strong>Produto:</strong><br>
                                                <?= htmlspecialchars($solicitacao['nome_produto']) ?>
                                            </div>

                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <strong>Quantidade:</strong><br>
                                                    <?= htmlspecialchars($solicitacao['quantidade']) ?> unidades
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Status:</strong><br>
                                                    <span
                                                        class="badge <?= $badgeClass ?>"><?= ucfirst($solicitacao['status']) ?></span>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <strong>Justificativa:</strong><br>
                                                <?= nl2br(htmlspecialchars($solicitacao['justificativa'])) ?>
                                            </div>

                                            <?php if (!empty($solicitacao['resposta_matriz'])): ?>
                                                <div class="mb-3">
                                                    <strong>Resposta:</strong><br>
                                                    <?= nl2br(htmlspecialchars($solicitacao['resposta_matriz'])) ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($solicitacao['status'] === 'pendente' && $_SESSION['tipo_empresa'] === 'principal'): ?>
                                                <form action="../../assets/php/filial/confirmarSolicitacao.php" method="POST">
                                                    <input type="hidden" name="id" value="<?= $solicitacao['id'] ?>">

                                                    <input type="hidden" name="empresa_destino"
                                                        value="<?= htmlspecialchars($solicitacao['empresa_destino']) ?>">

                                                    <input type="text" name="id_selecionado"
                                                        value="<?= htmlspecialchars($idSelecionado); ?>">

                                                    <div class="mb-3">
                                                        <label class="form-label">Resposta:</label>
                                                        <textarea name="resposta" class="form-control" rows="3" required></textarea>
                                                    </div>

                                                    <div class="d-flex justify-content-between">
                                                        <button type="submit" name="acao" value="recusar"
                                                            class="btn btn-danger">Recusar</button>
                                                        <button type="submit" name="acao" value="aprovar"
                                                            class="btn btn-success">Aprovar</button>
                                                    </div>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <!-- Modal de Exclusão -->
                            
                    </div>
                    <!-- /container-xxl -->

                </div>
                <!-- / Layout page -->

            </div>
            <!-- / Layout container -->

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

        <!-- Main JS -->
        <script src="../../assets/js/main.js"></script>

        <!-- Page JS -->
        <script src="../../assets/js/dashboards-analytics.js"></script>

        <!-- Place this tag in your head or just before your close body tag. -->
        <script async defer src="https://buttons.github.io/buttons.js"></script>

    </body>

</html>
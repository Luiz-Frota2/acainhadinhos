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
    !isset($_SESSION['usuario_id']) // Verifica se o ID do usuário está na sessão
) {
    header("Location: ../index.php?id=$idSelecionado");
    exit;
}

// ✅ Conexão com o banco de dados
require '../../assets/php/conexao.php';

$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id = $_SESSION['usuario_id'];
$tipoUsuarioSessao = $_SESSION['nivel']; // "Admin" ou "Funcionario"

try {
    if ($tipoUsuarioSessao === 'Admin') {
        // Buscar na tabela de Admins
        $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    } else {
        // Buscar na tabela de Funcionários
        $stmt = $pdo->prepare("SELECT usuario, nivel, cpf FROM funcionarios_acesso WHERE id = :id");
    }

    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $nomeUsuario = $usuario['usuario'];
        $tipoUsuario = ucfirst($usuario['nivel']);
        if (isset($usuario['cpf'])) {
            $cpfUsuario = $usuario['cpf'];
        }
    } else {
        echo "<script>alert('Usuário não encontrado.'); window.location.href = './index.php?id=$idSelecionado';</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar nome e tipo do usuário: " . $e->getMessage() . "'); history.back();</script>";
    exit;
}

// ✅ Função para buscar o nome do funcionário pelo CPF
function obterNomeFuncionario($pdo, $cpf)
{
    try {
        $stmt = $pdo->prepare("SELECT nome AND cpf FROM funcionarios_acesso WHERE cpf = :cpf");
        $stmt->bindParam(':cpf', $cpf, PDO::PARAM_STR);
        $stmt->execute();
        $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($funcionario && !empty($funcionario['nome']) || !empty($funcionario['cpf'])) {
            return $funcionario['nome'];

        } else {
            return 'Funcionário não identificado';
        }
    } catch (PDOException $e) {
        return 'Erro ao buscar nome';
    }
}

// ✅ Aplica a função se for funcionário
if (!empty($cpfUsuario)) {
    $nomeFuncionario = obterNomeFuncionario($pdo, $cpfUsuario);
}

// ✅ Valida o tipo de empresa e o acesso permitido
if (str_starts_with($idSelecionado, 'principal_')) {
    if ($_SESSION['tipo_empresa'] !== 'principal' || $_SESSION['empresa_id'] != 1) {
        echo "<script>
            alert('Acesso negado!');
            window.location.href = '../index.php?id=$idSelecionado';
        </script>";
        exit;
    }
    $id = 1;
} elseif (str_starts_with($idSelecionado, 'filial_')) {
    $idFilial = (int) str_replace('filial_', '', $idSelecionado);
    if ($_SESSION['tipo_empresa'] !== 'filial' || $_SESSION['empresa_id'] != $idFilial) {
        echo "<script>
            alert('Acesso negado!');
            window.location.href = '../index.php?id=$idSelecionado';
        </script>";
        exit;
    }
    $id = $idFilial;
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
    echo "<script>alert('Erro ao carregar ícone da empresa: " . addslashes($e->getMessage()) . "');</script>";
}

?>


<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
    data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>ERP - Caixa</title>

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
                        <a href="index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Dashboard</div>
                        </a>
                    </li>

                    <!-- CAIXA -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Frente de Caixa</span>
                    </li>

                    <!-- Operações de Caixa -->
                    <li class="menu-item  ">
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
                            <li class="menu-item ">
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

                    <!-- Vendas -->
                    <li class="menu-item">
                        <a href="./vendaRapida.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-cart-alt"></i>
                            <div data-i18n="Vendas">Venda Rápida</div>
                        </a>
                    </li>

                    <!-- Cancelamento / Ajustes -->
                    <li class="menu-item">
                        <a href="./cancelarVenda.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-x-circle"></i>
                            <div data-i18n="Cancelamento">Cancelar Venda</div>
                        </a>
                    </li>

                    <!-- Relatórios -->
                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-bar-chart-alt"></i>
                            <div data-i18n="Relatórios">Relatórios</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item active">
                                <a href="./relatorioVendas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Resumo de Vendas</div>
                                </a>
                            </li>

                        </ul>
                    </li>
                    <!-- END CAIXA -->

                    </li>
                    <!-- Misc -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
                    <li class="menu-item">
                        <a href="../sistemadeponto/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-group"></i>
                            <div data-i18n="Authentications">SIstema de Ponto</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../Delivery/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-cart"></i>
                            <div data-i18n="Basic">Delivery</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="https://wa.me/92991515710" target="_blank" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-support"></i>
                            <div data-i18n="Basic">Suporte</div>
                        </a>
                    </li>
                    <!--/MISC-->
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
                                <i class="bx bx-search fs-4 lh-0"></i>
                                <input type="text" class="form-control border-0 shadow-none" placeholder="Search..."
                                    aria-label="Search..." />
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
                                        <img src="../../assets/img/avatars/1.png" alt
                                            class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="../../assets/img/avatars/1.png" alt
                                                            class="w-px-40 h-auto rounded-circle" />
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
                                        <a class="dropdown-item" href="#">
                                            <i class="bx bx-user me-2"></i>
                                            <span class="align-middle">Minha conta</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <i class="bx bx-cog me-2"></i>
                                            <span class="align-middle">Configurações</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <span class="d-flex align-items-center align-middle">
                                                <i class="flex-shrink-0 bx bx-credit-card me-2"></i>
                                                <span class="flex-grow-1 align-middle">Billing</span>
                                                <span
                                                    class="flex-shrink-0 badge badge-center rounded-pill bg-danger w-px-20 h-px-20">4</span>
                                            </span>
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
                <?php
                try {
                    // Buscar todos os setores
                    $sql = "SELECT * FROM aberturas WHERE empresa_id = :empresa_id AND cpf = :cpf";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR); // Usa o idSelecionado
                    $stmt->bindParam(':cpf', $cpfUsuario, PDO::PARAM_STR);
                    $stmt->execute();
                    $aberturas = $stmt->fetchAll(PDO::FETCH_ASSOC);



                } catch (PDOException $e) {
                    echo "Erro ao buscar produtos: " . $e->getMessage();
                    exit;
                }


                ?>

                <!-- Content wrapper -->

                <!-- Content -->
                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold mb-0">
                        <span class="text-muted fw-light">
                            <a href="./index.php?id=<?= urlencode($idSelecionado); ?>">Relatório</a> /
                        </span>
                        Resumo de Vendas
                    </h4>
                    </h4>
                    <h5 class="fw-semibold mt-2 mb-4 text-muted">Visualize os Resumos de Vendas</h5>
                    <?php if (!empty($aberturas)): ?>
                        <div class="row">
                            <div class="col-lg-12 mb-4 order-0">
                                <div class="card">
                                    <div>
                                        <div class="card">
                                            <h5 class="card-header">Resumo de Vendas</h5>
                                            <div class="card">
                                                <div class="table-responsive text-nowrap">
                                                    <table class="table table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th>Data</th>
                                                                <th>Quant. de Vendas</th>
                                                                <th>Valor inicial do Caixa</th>
                                                                <th>Valor Total</th>
                                                                <th>Valor da Sangria</th>
                                                                <th>Valor do Suprimento</th>
                                                                <th>Valor Líquido</th>
                                                                <th>Status</th>
                                                                <th>Detalhes</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="table-border-bottom-0">
                                                            <?php foreach ($aberturas as $abertura): ?>
                                                                <tr>
                                                                    <input type="hidden" name="" id=""
                                                                        value="<?= htmlspecialchars($abertura['id']) ?>">
                                                                    <td><?= htmlspecialchars($abertura['data_fechamento']) ?>
                                                                    </td>
                                                                    <td><?= htmlspecialchars($abertura['quantidade_venda']) ?>
                                                                    </td>
                                                                    <td>R$
                                                                        <?= number_format($abertura['valor_abertura'], 2, ',', '.') ?>
                                                                    </td>
                                                                    <td>R$
                                                                        <?= number_format($abertura['valor_total'], 2, ',', '.') ?>
                                                                    </td>
                                                                    <td>R$
                                                                        <?= number_format($abertura['valor_sangrias'], 2, ',', '.') ?>
                                                                    </td>
                                                                    <td>R$
                                                                        <?= number_format($abertura['valor_suprimento'], 2, ',', '.') ?>
                                                                    </td>
                                                                    <td>R$
                                                                        <?= number_format($abertura['valor_liquido'], 2, ',', '.') ?>
                                                                    </td>
                                                                    <td>
                                                                        <?php if ($abertura['status_abertura'] == 'aberto'): ?>
                                                                            <span class="dge bg-label-success me-1">Aberto</span>
                                                                        <?php elseif ($abertura['status_abertura'] == 'fechado'): ?>
                                                                            <span class="badge bg-label-danger me-1">Fechado</span>
                                                                        <?php else: ?>
                                                                            <span class="badge bg-warning"> nao identificada</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td>
                                                                        <a
                                                                            href="./detalheVendas.php?id=<?= urlencode($idSelecionado); ?>&chave=<?= htmlspecialchars($abertura['id']) ?>">
                                                                            <i
                                                                                class="bx bx-show-alt cursor-pointer text-primary"></i>
                                                                        </a>
                                                                    </td>

                                                                </tr>
                                                            <?php endforeach; ?>


                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
                        $total_vendas = 0;
                        $valor_liquido = 0;
                        $descontos = 0;
                        $total_quantidade = 0;

                        foreach ($aberturas as $abertura) {
                            $total_vendas += $abertura['valor_total'];
                            $valor_liquido += $abertura['valor_liquido'];
                            $total_quantidade += $abertura['quantidade_venda'];
                            $descontos += ($abertura['valor_total'] - $abertura['valor_liquido']);
                        }

                        $ticket_medio = $total_quantidade > 0 ? $valor_liquido / $total_quantidade : 0;

                        $total_atual = $liquido_atual = $qtd_atual = $total_passado = $liquido_passado = $qtd_passado = 0;

                        foreach ($aberturas as $abertura) {
                            $data = strtotime($abertura['data_fechamento']);
                            $inicioSemanaAtual = strtotime('monday this week');
                            $inicioSemanaPassada = strtotime('monday last week');
                            $fimSemanaPassada = strtotime('sunday last week');

                            if ($data >= $inicioSemanaAtual) {
                                $total_atual += $abertura['valor_total'];
                                $liquido_atual += $abertura['valor_liquido'];
                                $qtd_atual += $abertura['quantidade_venda'];
                            } elseif ($data >= $inicioSemanaPassada && $data <= $fimSemanaPassada) {
                                $total_passado += $abertura['valor_total'];
                                $liquido_passado += $abertura['valor_liquido'];
                                $qtd_passado += $abertura['quantidade_venda'];
                            }
                        }

                        // Ticket médio
                        $ticket_atual = $qtd_atual > 0 ? $liquido_atual / $qtd_atual : 0;
                        $ticket_passado = $qtd_passado > 0 ? $liquido_passado / $qtd_passado : 0;

                        // Diferenças percentuais
                        function diferenca_percentual($atual, $passado)
                        {
                            if ($passado == 0)
                                return $atual > 0 ? 100 : 0;
                            return (($atual - $passado) / $passado) * 100;
                        }

                        $percent_total = diferenca_percentual($total_atual, $total_passado);
                        $percent_liquido = diferenca_percentual($liquido_atual, $liquido_passado);
                        $percent_ticket = diferenca_percentual($ticket_atual, $ticket_passado);

                        ?>

                        <div class="row mb-3">
                            <!-- Card: Total de Vendas -->
                            <div class="col-md-3 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="avatar flex-shrink-0">
                                                <img src="../../assets/img/icons/unicons/chart-success.png"
                                                    alt="Total Vendas" class="rounded">
                                            </div>
                                        </div>
                                        <span>Total de Vendas</span>
                                        <h3 class="card-title mb-1">R$ <?= number_format($total_vendas, 2, ',', '.') ?></h3>
                                        <small
                                            class="<?= $percent_total >= 0 ? 'text-success' : 'text-danger' ?> fw-semibold">
                                            <i
                                                class="bx <?= $percent_total >= 0 ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt' ?>"></i>
                                            <?= round($percent_total, 1) ?>% em relação à semana passada
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- Card: Valor Líquido -->
                            <div class="col-md-3 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="avatar flex-shrink-0">
                                                <img src="../../assets/img/icons/unicons/wallet-info.png"
                                                    alt="Valor Líquido" class="rounded">
                                            </div>
                                        </div>
                                        <span>Valor Líquido</span>
                                        <h3 class="card-title mb-1">R$ <?= number_format($valor_liquido, 2, ',', '.') ?>
                                        </h3>
                                        <small
                                            class="<?= $percent_liquido >= 0 ? 'text-success' : 'text-danger' ?> fw-semibold">
                                            <i
                                                class="bx <?= $percent_liquido >= 0 ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt' ?>"></i>
                                            <?= round($percent_liquido, 1) ?>% em relação à semana passada
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- Card: Descontos Aplicados -->
                            <div class="col-md-3 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="avatar flex-shrink-0">
                                                <img src="../../assets/img/icons/unicons/percentage.png" alt="Descontos"
                                                    class="rounded">
                                            </div>
                                        </div>
                                        <span>Descontos</span>
                                        <h3 class="card-title mb-1">R$ <?= number_format($descontos, 2, ',', '.') ?></h3>
                                        <small class="text-muted">Acumulados na semana</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Card: Ticket Médio -->
                            <div class="col-md-3 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="avatar flex-shrink-0">
                                                <img src="../../assets/img/icons/unicons/cc-primary.png" alt="Ticket Médio"
                                                    class="rounded">
                                            </div>
                                        </div>
                                        <span>Ticket Médio</span>
                                        <h3 class="card-title mb-1">R$ <?= number_format($ticket_medio, 2, ',', '.') ?></h3>
                                        <small
                                            class="<?= $percent_ticket >= 0 ? 'text-success' : 'text-danger' ?> fw-semibold">
                                            <i
                                                class="bx <?= $percent_ticket >= 0 ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt' ?>"></i>
                                            <?= round($percent_ticket, 1) ?>% em relação à semana passada
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
                        $labels = [];
                        $valores = [];
                        $quantidades = [];
                        foreach ($aberturas as $abertura) {
                            $labels[] = date('d/m', strtotime($abertura['data_fechamento']));
                            $valores[] = (float) $abertura['valor_total'];
                            $quantidades[] = (int) $abertura['quantidade_venda'];
                        }

                        $json_labels = json_encode($labels);
                        $json_valores = json_encode($valores);
                        $json_quantidades = json_encode($quantidades);

                        // Conexão com banco aberturas (ajuste usuário/senha conforme necessário)
                        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);

                        // Consulta agrupando as formas de pagamento (ex: Pix, Cartão, Dinheiro etc.)
                        $sql = "SELECT forma_pagamento, COUNT(*) as quantidade FROM vendarapida GROUP BY forma_pagamento";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute();
                        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        // Prepara dados para o gráfico
                        $labels_pizza = [];
                        $valores_pizza = [];

                        foreach ($resultados as $row) {
                            $labels_pizza[] = $row['forma_pagamento'];
                            $valores_pizza[] = (int) $row['quantidade'];
                        }

                        // Codifica para JSON 
                        $json_labels_pizza = json_encode($labels_pizza);
                        $json_valores_pizza = json_encode($valores_pizza);

                        // Conectar ao banco
                        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
                        $filtro = $_GET['filtro'] ?? 'todos';
                        $where = '';
                        $params = [];

                        if ($filtro === 'mes') {
                            $where = "WHERE MONTH(data_fechamento) = MONTH(CURDATE()) AND YEAR(data_fechamento) = YEAR(CURDATE())";
                        } elseif ($filtro === '3meses') {
                            $where = "WHERE data_fechamento >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
                        } elseif ($filtro === 'personalizado' && isset($_GET['de'], $_GET['ate'])) {
                            $where = "WHERE data_fechamento BETWEEN :de AND :ate";
                            $params[':de'] = $_GET['de'];
                            $params[':ate'] = $_GET['ate'];
                        }

                        // Conectar ao banco
                        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                        $filtro = $_GET['filtro'] ?? 'todos';
                        $where = '';
                        $params = [];

                        if ($filtro === 'mes') {
                            $where = "WHERE MONTH(datas) = MONTH(CURDATE()) AND YEAR(datas) = YEAR(CURDATE())";
                        } elseif ($filtro === '3meses') {
                            $where = "WHERE datas >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
                        } elseif ($filtro === 'personalizado' && isset($_GET['de'], $_GET['ate'])) {
                            $where = "WHERE datas BETWEEN :de AND :ate";
                            $params[':de'] = $_GET['de'];
                            $params[':ate'] = $_GET['ate'];
                        }


                        $sqlAberturas = "SELECT * FROM vendarapida $where ORDER BY datas DESC";
                        $stmtAberturas = $pdo->prepare($sqlAberturas);
                        $stmtAberturas->execute($params);
                        $aberturas = $stmtAberturas->fetchAll(PDO::FETCH_ASSOC);

                        // Consulta agrupada por forma_pagamento
                        $sqlPagamentos = "SELECT forma_pagamento, COUNT(*) AS total_pagamentos, SUM(total) AS total
                         FROM vendarapida 
                         $where 
                         GROUP BY forma_pagamento";
                        $stmtPagamentos = $pdo->prepare($sqlPagamentos);
                        $stmtPagamentos->execute($params);
                        $pagamentos = $stmtPagamentos->fetchAll(PDO::FETCH_ASSOC);

                        ?>

                        <!-- Gráficos Detalhados -->
                        <div class="row mb-4">
                            <!-- Gráfico de Linha: Evolução Diária -->
                            <div class="col-md-8 mb-4 order-1">
                                <div class="card">
                                    <h5 class="card-header">Evolução das Vendas (Últimos Meses)</h5>
                                    <div class="card-body">
                                        <div id="evolucaoDiariaChart"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Gráfico de Pizza: Formas de Pagamento -->
                            <div class="col-md-4 mb-4 order-2">
                                <div class="card">
                                    <h5 class="card-header">Composição de Pagamento</h5>
                                    <div class="card-body">
                                        <div id="graficoPizzaPagamento"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <!-- Gráfico de Barras: Quantidade por Dia -->
                            <div class="col-md-8 mb-4 order-3">
                                <div class="card">
                                    <h5 class="card-header">Volume de Vendas por Dia</h5>
                                    <div class="card-body">
                                        <div id="graficoBarrasQuantidade"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Relatório de Tipos de Pagamento -->
                            <div class="col-md-6 col-lg-4 order-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-header d-flex align-items-center justify-content-between">
                                        <h5 class="card-title m-0 me-2">Relatório de Pagamentos</h5>
                                        <div class="dropdown">
                                            <button class="btn p-0" type="button" id="relatorioPagamentosDropdown"
                                                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <i class="bx bx-dots-vertical-rounded"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end"
                                                aria-labelledby="relatorioPagamentosDropdown">
                                                <a class="dropdown-item"
                                                    href="?filtro=mes&id=<?= htmlspecialchars($idSelecionado) ?>">Este
                                                    mês</a>
                                                <a class="dropdown-item"
                                                    href="?filtro=3meses&id=<?= htmlspecialchars($idSelecionado) ?>">Últimos
                                                    3 meses</a>
                                                <a class="dropdown-item" href="#" data-bs-toggle="modal"
                                                    data-bs-target="#modalPersonalizar">Personalizar</a>
                                            </div>

                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <ul class="p-0 m-0">
                                            <?php foreach ($pagamentos as $pagamento): ?>
                                                <li class="d-flex mb-4 pb-1">
                                                    <div class="avatar flex-shrink-0 me-3">
                                                        <?php
                                                        $icone = 'transaction.png';
                                                        $forma = strtolower($pagamento['forma_pagamento']);
                                                        if (strpos($forma, 'crédito') !== false)
                                                            $icone = '../../assets/img/icons/unicons/cc-success.png';
                                                        elseif (strpos($forma, 'débito') !== false)
                                                            $icone = '../../assets/img/icons/unicons/cc-warning.png';
                                                        elseif (strpos($forma, 'pix') !== false)
                                                            $icone = '../../assets/img/icons/unicons/paypal.png';
                                                        elseif (strpos($forma, 'dinheiro') !== false)
                                                            $icone = '../../assets/img/icons/unicons/wallet.png';
                                                        ?>
                                                        <img src="<?= $icone ?>" class="rounded" />
                                                    </div>
                                                    <div
                                                        class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                        <div class="me-2">
                                                            <small
                                                                class="text-muted d-block mb-1"><?= htmlspecialchars(ucwords($pagamento['forma_pagamento'])) ?></small>
                                                            <h6 class="mb-0"><?= $pagamento['total_pagamentos'] ?> pagamentos
                                                            </h6>
                                                        </div>
                                                        <div class="user-progress d-flex align-items-center gap-1">
                                                            <h6 class="mb-0">R$
                                                                <?= number_format($pagamento['total'], 2, ',', '.') ?>
                                                            </h6>
                                                        </div>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <!--/ Relatório de Tipos de Pagamento -->
                        </div>
                        <!-- Footer -->
                        <footer class="content-footer footer bg-footer-theme text-center">
                            <div class="container-xxl d-flex  py-2 flex-md-row flex-column justify-content-center">
                                <div class="mb-2 mb-md-0">
                                    &copy;
                                    <script>
                                        document.write(new Date().getFullYear());
                                    </script>
                                    , <strong>Açaínhadinhos</strong>. Todos os direitos reservados.
                                    Desenvolvido por <strong>CodeGeek</strong>.
                                </div>
                            </div>
                        </footer>

                        <!-- / Footer -->
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>


    <!-- / Layout wrapper -->

    <!-- Inclua ApexCharts antes dos scripts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <script>
        const labels = <?= $json_labels ?>;
        const valores = <?= $json_valores ?>;
        const quantidades = <?= $json_quantidades ?>;

        // Gráfico de Linha (Evolução das Vendas)
        const optionsLinha = {
            chart: { type: 'line', height: 350 },
            series: [{ name: "Vendas", data: valores }],
            xaxis: { categories: labels },
            stroke: { curve: 'smooth', width: 3 },
            colors: ['#696CFF'],
            markers: { size: 5, colors: ['#696CFF'], strokeWidth: 2 }
        };
        new ApexCharts(document.querySelector("#evolucaoDiariaChart"), optionsLinha).render();

        // Gráfico de Barras (Quantidade)
        const optionsBarra = {
            chart: { type: 'bar', height: 350 },
            series: [{ name: "Vendas", data: quantidades }],
            xaxis: { categories: labels },
            plotOptions: { bar: { borderRadius: 6, columnWidth: '45%' } },
            colors: ['#00C9A7'],
            tooltip: { y: { formatter: val => val + " vendas" } }
        };
        new ApexCharts(document.querySelector("#graficoBarrasQuantidade"), optionsBarra).render();

        // Gráfico de Pizza: Formas de Pagamento

        const pizzaLabels = <?= $json_labels_pizza ?>;
        const pizzaData = <?= $json_valores_pizza ?>;


        const optionsPizza = {
            chart: {
                type: 'pie',
                height: 250
            },
            labels: pizzaLabels,
            series: pizzaData,
            colors: ['#00C9A7', '#FFB547', '#FF6B6B', '#845EC2', '#FFC75F', '#0081CF'], // Pode adicionar mais cores se necessário
            legend: {
                position: 'bottom'
            },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return val + " pagamentos";
                    }
                }
            }
        };

        new ApexCharts(document.querySelector("#graficoPizzaPagamento"), optionsPizza).render();

    </script>

    <!-- Modal de Personalização -->
    <div class="modal fade" id="modalPersonalizar" tabindex="-1" aria-labelledby="modalPersonalizarLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="GET" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalPersonalizarLabel">Selecionar Período Personalizado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="filtro" value="personalizado">
                    <div class="mb-3">
                        <label for="dataInicial" class="form-label">Data Inicial</label>
                        <input type="date" class="form-control" id="dataInicial" name="de" required>
                    </div>
                    <div class="mb-3">
                        <label for="dataFinal" class="form-label">Data Final</label>
                        <input type="date" class="form-control" id="dataFinal" name="ate" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>



    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../assets/vendor/js/menu.js"></script>
    <script src="../../assets/js/main.js"></script>
</body>

</html>
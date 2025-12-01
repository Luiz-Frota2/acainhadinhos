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
    !isset($_SESSION['nivel']) || // Verifica se o nível está na sessão
    !isset($_SESSION['usuario_cpf']) // Verifica se o CPF está na sessão
) {
    header("Location: ../index.php?id=$idSelecionado");
    exit;
}

// ✅ Conexão com o banco de dados
require '../../assets/php/conexao.php';

$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id = $_SESSION['usuario_id'];
$usuario_cpf = $_SESSION['usuario_cpf']; // Recupera o CPF da sessão
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

// ✅ Obter nome do funcionário usando o CPF da sessão
$nomeFuncionario = obterNomeFuncionario($pdo, $usuario_cpf);

// ✅ Buscar atestados do usuário logado
$atestados = [];
try {
    $stmtAtestados = $pdo->prepare("SELECT * FROM atestados WHERE cpf_usuario = :cpf AND id_empresa = :empresa_id ORDER BY data_atestado DESC");
    $stmtAtestados->bindParam(':cpf', $usuario_cpf, PDO::PARAM_STR);
    $stmtAtestados->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
    $stmtAtestados->execute();
    $atestados = $stmtAtestados->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro ao buscar atestados: " . $e->getMessage());
    // Você pode adicionar uma mensagem de erro para o usuário se desejar
}

// ✅ Função para buscar o nome do funcionário pelo CPF
function obterNomeFuncionario($pdo, $cpf)
{
    try {
        $stmt = $pdo->prepare("SELECT nome FROM funcionarios WHERE cpf = :cpf");
        $stmt->bindParam(':cpf', $cpf, PDO::PARAM_STR);
        $stmt->execute();
        $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($funcionario && !empty($funcionario['nome'])) {
            return $funcionario['nome'];
        } else {
            return 'Funcionário não identificado';
        }
    } catch (PDOException $e) {
        error_log("Erro ao buscar nome do funcionário: " . $e->getMessage());
        return 'Erro ao buscar nome';
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

    <title>ERP - Sistema de Ponto</title>

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
                    <a href="./dashboard.php?id=<?= urlencode($idSelecionado); ?>" class="app-brand-link">

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

                    <!--link Diversos-->
                    <!-- Cabeçalho da seção -->
                    <li class="menu-header small text-uppercase">
                        <span class="menu-header-text">Sistema de Ponto</span>
                    </li>

                    <!-- Menu: Registro de Ponto -->
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-time"></i>
                            <div data-i18n="Ponto">Registro de Ponto</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./registrarPonto.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Registrar Ponto</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- Menu: Atestados -->
                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-file"></i>
                            <div data-i18n="Atestados">Atestados</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item active">
                                <a href="./atestadosEnviados.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div>Atestado Enviados </div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-trending-up"></i>
                            <div data-i18n="Ponto">Relatório</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./bancodeHoras.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Banco de Horas</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./pontoRegistrado.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Pontos Registrados</div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <!--/Diversos-->

                    <!-- Misc -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
                    <li class="menu-item">
                        <a href="../caixa/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-barcode-reader"></i>
                            <div data-i18n="Basic">Caixa</div>
                        </a>
                        <a href="../delivery/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-cart"></i>
                            <div data-i18n="Authentications">Delivery</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="https://wa.me/92991515710" target="_blank" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-support"></i>
                            <div data-i18n="Basic">Suporte</div>
                        </a>
                    </li>
                    <!--END MISC-->
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
                                        <img src="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>" alt
                                            class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>" alt
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
                <!-- Content wrapper -->
                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a
                                href="AtestadosEnviados.php?id=<?= urlencode($idSelecionado); ?>">Atestados</a>/</span>Atestados
                        Enviados</h4>
                    <h5 class="fw-bold mt-3 mb-3 custor-font"><span class="text-muted fw-light">Visualize todos os
                            Atestados Enviados</span></h5>

                    <!-- Tabela de Atestados -->
                    <div class="card">
                        <h5 class="card-header">
                            Atestados de
                            <?= isset($atestados[0]['nome_funcionario']) ? htmlspecialchars($atestados[0]['nome_funcionario']) : 'Funcionário' ?>
                        </h5>
                        <div class="card">
                            <div class="table-responsive text-nowrap">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Data de Envio</th>
                                            <th>Data do Atestado</th>
                                            <th>Dias Afastado</th>
                                            <th>Médico</th>
                                            <th>Observações</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody class="table-border-bottom-0">
                                        <?php if (!empty($atestados)): ?>
                                            <?php foreach ($atestados as $index => $atestado): ?>
                                                <tr>
                                                    <td><?= date('d/m/Y', strtotime($atestado['data_envio'])); ?></td>
                                                    <td><?= date('d/m/Y', strtotime($atestado['data_atestado'])); ?></td>
                                                    <td><?= htmlspecialchars($atestado['dias_afastado']); ?></td>
                                                    <td><?= htmlspecialchars($atestado['medico']); ?></td>
                                                    <td>
                                                        <?php
                                                        $status = $atestado['status_atestado'] ?? 'pendente';
                                                        $badgeClass = 'bg-secondary';
                                                        $statusLabel = ucfirst($status);

                                                        if ($status === 'válido') {
                                                            $badgeClass = 'bg-success';
                                                        } elseif ($status === 'inválido') {
                                                            $badgeClass = 'bg-danger';
                                                        } elseif ($status === 'pendente') {
                                                            $badgeClass = 'bg-warning';
                                                        }
                                                        ?>
                                                        <span class="badge <?= $badgeClass; ?>">
                                                            <?= htmlspecialchars($statusLabel); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <!-- Botão Visualizar -->
                                                        <button type="button" class="btn btn-link text-muted p-0"
                                                            title="Visualizar" data-bs-toggle="modal"
                                                            data-bs-target="#detalhesAtestadoModal<?= $index ?>"
                                                            data-observacoes="<?= htmlspecialchars($atestado['observacoes'] ?? '') ?>"
                                                            data-atestado="<?= htmlspecialchars($atestado['imagem_atestado'] ?? '') ?>">
                                                            <i class="bx bx-show"></i>
                                                        </button>

                                                        <!-- Modal de Detalhes do Atestado -->
                                                        <div class="modal fade" id="detalhesAtestadoModal<?= $index ?>"
                                                            tabindex="-1"
                                                            aria-labelledby="detalhesAtestadoModalLabel<?= $index ?>"
                                                            aria-hidden="true">
                                                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title"
                                                                            id="detalhesAtestadoModalLabel<?= $index ?>">
                                                                            Detalhes do Atestado</h5>
                                                                        <button type="button" class="btn-close"
                                                                            data-bs-dismiss="modal"
                                                                            aria-label="Fechar"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <div class="mb-3">
                                                                            <label class="form-label"><strong>Observações:</strong></label>
                                                                            <div id="modalObservacoes<?= $index ?>"
                                                                                class="rounded p-2 bg-light"></div>
                                                                        </div>
                                                                        <div>
                                                                            <label class="form-label"><strong>Imagem do
                                                                                    Atestado:</strong></label>
                                                                            <div id="modalImagemAtestado<?= $index ?>"
                                                                                class="text-center"></div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary"
                                                                            data-bs-dismiss="modal">Fechar</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <script>
                                                            document.addEventListener('DOMContentLoaded', function() {
                                                                var modal = document.getElementById('detalhesAtestadoModal<?= $index ?>');
                                                                if (modal) {
                                                                    modal.addEventListener('show.bs.modal', function(event) {
                                                                        var button = event.relatedTarget;
                                                                        var observacoes = button.getAttribute('data-observacoes') || '';
                                                                        var imagemAtestado = button.getAttribute('data-atestado') || '';

                                                                        document.getElementById('modalObservacoes<?= $index ?>').textContent = observacoes;

                                                                        var imagemDiv = document.getElementById('modalImagemAtestado<?= $index ?>');
                                                                        imagemDiv.innerHTML = '';
                                                                        if (imagemAtestado) {
                                                                            var img = document.createElement('img');
                                                                            img.src = '../../assets/img/atestados/' + imagemAtestado;
                                                                            img.alt = 'Imagem do Atestado';
                                                                            img.className = 'img-fluid rounded';
                                                                            img.style.maxHeight = '400px';
                                                                            imagemDiv.appendChild(img);
                                                                        } else {
                                                                            imagemDiv.innerHTML = '<span class="text-muted">Nenhuma imagem disponível.</span>';
                                                                        }
                                                                    });
                                                                }
                                                            });
                                                        </script>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center">Nenhum atestado encontrado.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div id="addCategoryLink"
                        class="mt-3 add-category justify-content-center d-flex text-center align-items-center"
                        onclick="window.location.href='./adicionarAtestado.php?id=<?= urlencode($idSelecionado); ?>';"
                        style="cursor: pointer;">
                        <i class="tf-icons bx bx-plus me-2"></i>
                        <span>Adicionar novo Atestado</span>
                    </div>
                </div>

                <footer class="content-footer footer bg-footer-theme text-center">
                    <div class="container-xxl d-flex  py-2 flex-md-row flex-column justify-content-center">
                        <div class="mb-2 mb-md-0">
                            &copy;
                            <script>
                                document.write(new Date().getFullYear());
                            </script>
                            , <strong>Açainhadinhos</strong>. Todos os direitos reservados.
                            Desenvolvido por <strong>CodeGeek</strong>.
                        </div>
                    </div>
                </footer>
            </div>
        </div>
    </div>
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
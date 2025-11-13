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

// ⛏️ Tentar obter CPF do usuário logado (evita "Undefined variable $cpfUsuario")
$cpfUsuario = '';
if (!empty($_SESSION['cpf'])) {
    $cpfUsuario = soDigitos((string)$_SESSION['cpf']);
} else {
    // Tenta buscar na base dependendo do tipo
    try {
        if ($tipoUsuarioSessao === 'Admin') {
            // Se a tabela tiver coluna cpf
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
        // Silencia caso a coluna/consulta não exista. Mantém $cpfUsuario = ''.
    }
}

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
            window.location.href = './index.php?id=$idSelecionado';
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
            window.location.href = './index.php?id=$idSelecionado';
        </script>";
        exit;
    }
    $id = $idUnidade;
} else {
    echo "<script>
        alert('Empresa não identificada!');
        window.location.href = './index.php?id=$idSelecionado';
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

// ✅ Consulta o saldo do caixa (com base no CPF)
$empresaId = htmlspecialchars($idSelecionado);
$responsavel = htmlspecialchars($nomeUsuario);
$valorLiquido = 0.00;
$mensagem = '';

try {
    $sql = "SELECT valor_liquido 
            FROM aberturas 
            WHERE empresa_id = :empresa_id 
              AND cpf_responsavel = :cpf_responsavel 
              AND status = 'aberto' 
            ORDER BY id DESC 
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':empresa_id' => $empresaId,
        ':cpf_responsavel' => $cpfUsuario
    ]);
    $aberturas = $stmt->fetch(PDO::FETCH_ASSOC);
    $valorLiquido = $aberturas ? (float) $aberturas['valor_liquido'] : 0.00;

    $mensagem = $valorLiquido <= 0
        ? "<span class='text-danger fw-bold'>Saldo insuficiente para sangria.</span>"
        : "<span class='text-success'>Saldo disponível para sangria.</span>";
} catch (PDOException $e) {
    $mensagem = "<span class='text-danger'>Erro ao buscar saldo do caixa.</span>";
}

// ✅ Buscar ID da abertura do caixa com base no CPF
try {
    $stmt = $pdo->prepare("
        SELECT id 
        FROM aberturas 
        WHERE cpf_responsavel = :cpf_responsavel 
          AND empresa_id = :empresa_id 
          AND status = 'aberto'
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([
        ':cpf_responsavel' => $cpfUsuario,
        ':empresa_id' => $empresaId
    ]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    $idAbertura = $resultado ? $resultado['id'] : null;
} catch (PDOException $e) {
    $idAbertura = null;
    $mensagem = "<span class='text-danger'>Erro ao buscar ID do caixa.</span>";
}


?>

<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
    data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>ERP - PDV</title>

    <meta name="description" content="" />

    <!-- Favicon da empresa carregado dinamicamente -->
    <link rel="icon" type="image/x-icon" href="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>" />

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
                    <li class="menu-item active open">
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
                            <li class="menu-item active">
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
                    <!-- END CAIXA -->

                    </li>
                    <!-- Misc -->
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
                    <!--/MISC-->
                </ul>
            </aside>
            <!-- / Menu -->

            <!-- Layout container -->
            <div class="layout-page">
                <!-- Navbar -->

                <nav
                    class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
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
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
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
                                                    <span class="fw-semibold d-block"><?= htmlspecialchars($nomeUsuario); ?></span>
                                                </div>
                                            </div>
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
                            </li>
                            <!--/ User -->
                        </ul>

                    </div>
                </nav>

                <!-- / Navbar -->

                <!-- CONTEÚDO PRINCIPAL -->
                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold mb-0">
                        <span class="text-muted fw-light">
                            <a href="#">Operações de Caixa</a> /
                        </span>
                        Sangria
                    </h4>
                    <h5 class="fw-semibold mt-2 mb-4 text-muted">Registrar retirada de valores do caixa</h5>

                    <div class="card">
                        <div class="card-body">
                            <div id="avisoSemCaixa" class="alert alert-danger text-center" style="display: none;">
                                Nenhum caixa está aberto. Por favor, abra um caixa para continuar com a venda.
                            </div>

                            <form
                                action="../../assets/php/frentedeloja/processarSangria.php?id=<?= urlencode($idSelecionado); ?>"
                                method="POST" onsubmit="return confirmarSangria();">

                                <div class="mb-3">
                                    <label for="valor" class="form-label">Valor da Sangria (R$)</label>
                                    <input type="number" step="0.01" class="form-control" name="valor" id="valor"
                                        required>
                                </div>

                                <div class="mb-3">
                                    <label for="saldo_caixa" class="form-label">Saldo do Caixa</label>
                                    <input type="number" step="0.01" class="form-control" name="saldo_caixa"
                                        id="saldo_caixa" value="<?= number_format($valorLiquido, 2, '.', '') ?>"
                                        readonly>
                                    <div class="form-text mt-1"><?= $mensagem ?></div>
                                </div>
                                <input type="hidden" name="idSelecionado"
                                    value="<?php echo htmlspecialchars($idSelecionado); ?>" />

                                <input type="hidden" id="responsavel" name="responsavel"
                                    value="<?= ucwords($nomeUsuario); ?>">

                                <input type="hidden" name="data_registro" id="data_registro">

                                <input type="hidden" id="cpf" name="cpf" value="<?= ucwords($cpfUsuario); ?>">

                                <?php
                                // Exibe o campo id_caixa se houver resultado
                                if ($resultado && isset($resultado['id'])) {
                                    $idAbertura = $resultado['id'];
                                    echo "<input type='hidden' id='id_caixa' name='id_caixa' value='$idAbertura' >";
                                }
                                ?>
                                <div class="mb-3">
                                    <button class="btn btn-primary d-grid w-100" type="submit">Registrar
                                        Sangria</button>
                                </div>
                            </form>

                        </div>
                    </div>
                </div>
                <!-- FIM CONTEÚDO PRINCIPAL -->

            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const idCaixa = document.getElementById('id_caixa');
            const form = document.querySelector('form');
            const aviso = document.getElementById('avisoSemCaixa');
            const inputDataRegistro = document.getElementById('data_registro');

            // Oculta o formulário se id_caixa estiver vazio ou não existir
            if (!idCaixa || !idCaixa.value.trim()) {
                if (form) form.style.display = 'none';
                if (aviso) aviso.style.display = 'block';
            }

            // Função para formatar data/hora local como "YYYY-MM-DD HH:mm:ss"
            function formatarDataLocal(date) {
                const pad = num => String(num).padStart(2, '0');
                const ano = date.getFullYear();
                const mes = pad(date.getMonth() + 1);
                const dia = pad(date.getDate());
                const horas = pad(date.getHours());
                const minutos = pad(date.getMinutes());
                const segundos = pad(date.getSeconds());
                return `${ano}-${mes}-${dia} ${horas}:${minutos}:${segundos}`;
            }

            // Define data atual ao carregar a página
            if (inputDataRegistro) {
                inputDataRegistro.value = formatarDataLocal(new Date());
            }

            // Atualiza data_registro no momento da submissão
            if (form && inputDataRegistro) {
                form.addEventListener('submit', function() {
                    inputDataRegistro.value = formatarDataLocal(new Date());
                });
            }
        });
    </script>
    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../assets/vendor/js/menu.js"></script>
    <script src="../../assets/js/main.js"></script>
</body>

</html>
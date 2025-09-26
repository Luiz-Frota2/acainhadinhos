<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// ✅ Recupera o identificador vindo da URL
$idSelecionado = $_GET['id'] ?? '';

if (!$idSelecionado) {
    header("Location: .././login.php");
    exit;
}

// ✅ Verifica se a pessoa está logada
if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id'])
) {
    header("Location: .././login.php?id=" . urlencode($idSelecionado));
    exit;
}

// ✅ Conexão com o banco de dados
require '../../assets/php/conexao.php';

// ✅ Buscar nome e tipo do usuário logado
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id = $_SESSION['usuario_id'];

try {
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $nomeUsuario = $usuario['usuario'];
        $tipoUsuario = ucfirst($usuario['nivel']);
    } else {
        echo "<script>alert('Usuário não encontrado.'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar usuário: " . $e->getMessage() . "'); history.back();</script>";
    exit;
}

// ✅ Valida o tipo de empresa e o acesso permitido
$acessoPermitido = false;
$idEmpresaSession = $_SESSION['empresa_id'];
$tipoSession = $_SESSION['tipo_empresa'];

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
    echo "<script>
          alert('Acesso negado!');
          window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';
        </script>";
    exit;
}

// ✅ Buscar logo da empresa
try {
    $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1");
    $stmt->bindParam(':id_selecionado', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);

    $logoEmpresa = !empty($empresaSobre['imagem'])
        ? "../../assets/img/empresa/" . $empresaSobre['imagem']
        : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png"; // fallback
}

?>

<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
    data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>ERP - Estoque</title>

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

                    <!-- DASHBOARD -->
                    <li class="menu-item">
                        <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Dashboard</div>
                        </a>
                    </li>

                    <!-- SEÇÃO ADMINISTRATIVO -->
                    <li class="menu-header small text-uppercase">
                        <span class="menu-header-text">Estoque</span>
                    </li>

                    <!-- ESTOQUE COM SUBMENU -->
                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-box"></i>
                            <div data-i18n="Basic">Produtos</div>
                        </a>
                        <ul class="menu-sub">
                            <!-- Produtos Adicionados: Cadastro ou listagem de produtos adicionados -->
                            <li class="menu-item ">
                                <a href="./produtosAdicionados.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div data-i18n="Basic">Produtos Adicionados</div>
                                </a>
                            </li>
                            <li class="menu-item active">
                                <a href="./adicionarProduto.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Adicionar Produto </div>
                                </a>
                            </li>
                        </ul>
                    </li>


                    <!-- SUBMENU: RELATÓRIOS -->
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-file"></i>
                            <div data-i18n="Authentications">Relatórios</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./estoqueAlto.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="BaixoEstoque">Estoque Alto</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./estoqueBaixo.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="BaixoEstoque">Estoque Baixo</div>
                                </a>
                            </li>
                        </ul>
                    </li>
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
                        <a href="../empresa/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div data-i18n="Authentications">Empresa</div>
                        </a>
                    </li>

                    <?php
                    $tipoLogado = $_SESSION['tipo_empresa'] ?? '';
                    $idLogado = $_SESSION['empresa_id'] ?? '';

                    // Se for matriz (principal), mostrar links para filial, franquia e unidade
                    if ($tipoLogado === 'principal') {
                    ?>
                        <li class="menu-item">
                            <a href="../filial/index.php?id=principal_1" class="menu-link">
                                <i class="menu-icon tf-icons bx bx-building"></i>
                                <div data-i18n="Authentications">Filial</div>
                            </a>
                        </li>
                        <li class="menu-item">
                            <a href="../franquia/index.php?id=principal_1" class="menu-link">
                                <i class="menu-icon tf-icons bx bx-store"></i>
                                <div data-i18n="Authentications">Franquias</div>
                            </a>
                        </li>
                    <?php
                    } elseif (in_array($tipoLogado, ['filial', 'franquia', 'unidade'])) {
                        // Se for filial, franquia ou unidade, mostra link para matriz
                    ?>
                        <li class="menu-item">
                            <a href="../matriz/index.php?id=<?= urlencode($idLogado) ?>" class="menu-link">
                                <i class="menu-icon tf-icons bx bx-cog"></i>
                                <div data-i18n="Authentications">Matriz</div>
                            </a>
                        </li>
                    <?php
                    }
                    ?>
                    <li class="menu-item">
                        <a href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-group"></i>
                            <div data-i18n="Authentications">Usuários </div>
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
                            <!-- User -->
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="avatar avatar-online">
                                        <img src="<?= htmlspecialchars($logoEmpresa, ENT_QUOTES) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownUser">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="<?= htmlspecialchars($logoEmpresa, ENT_QUOTES) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" />
                                                    </div>
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
                                    <li>
                                        <a class="dropdown-item" href="./contaUsuario.php?id=<?= urlencode($idSelecionado); ?>">
                                            <i class="bx bx-user me-2"></i>
                                            <span class="align-middle">Minha Conta</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <i class="bx bx-cog me-2"></i>
                                            <span class="align-middle">Configurações</span>
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


                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold py-3 mb-4"><span class="fw-light" style="color: #696cff !important;"><a
                                href="./produtosAdicionados.php?id=<?= urlencode($idSelecionado); ?>">PDV</a></span>/Adicionar
                        Produto</h4>

                    <div class="card">
                        <h5 class="card-header">Adicionar Produto</h5>
                        <div class="card-body">

                            <form id="addContaForm" action="../../assets/php/pdv/adicionarEstoque.php" method="POST">
                                <!-- Campo oculto para passar o idSelecionado -->
                                <input type="hidden" name="idSelecionado" value="<?php echo htmlspecialchars($idSelecionado); ?>" />

                                <div class="row">
                                    <!-- Dados básicos do produto -->
                                    <div class="mb-3 col-12 col-md-6">
                                        <label for="codigo_produto" class="form-label">Código do Produto (GTIN/EAN)</label>
                                        <input type="text" class="form-control" id="codigo_produto" name="codigo_produto"
                                            placeholder="Ex: 7891234567890 (código de barras)" required />
                                    </div>

                                    <div class="mb-3 col-12 col-md-6">
                                        <label for="nome_produto" class="form-label">Nome do Produto*</label>
                                        <input type="text" class="form-control" id="nome_produto" name="nome_produto"
                                            placeholder="Nome completo do produto para NFC-e" required />
                                    </div>

                                    <!-- Dados fiscais -->
                                    <div class="mb-3 col-12 col-md-4">
                                        <label for="ncm_produto" class="form-label">NCM*</label>
                                        <input type="text" class="form-control" id="ncm_produto" name="ncm_produto"
                                            placeholder="Código NCM (8 dígitos)" required />
                                    </div>

                                    <div class="mb-3 col-12 col-md-4">
                                        <label for="cest_produto" class="form-label">CEST (opcional)</label>
                                        <input type="text" class="form-control" id="cest_produto" name="cest_produto"
                                            placeholder="Código CEST (7 dígitos)" />
                                    </div>

                                    <div class="mb-3 col-12 col-md-4">
                                        <label for="cfop_produto" class="form-label">CFOP*</label>
                                        <input type="text" class="form-control" id="cfop_produto" name="cfop_produto"
                                            placeholder="Ex: 5102" required />
                                    </div>

                                    <!-- Dados tributários -->
                                    <div class="mb-3 col-12 col-md-4">
                                        <label for="origem_produto" class="form-label">Origem*</label>
                                        <select class="form-select" id="origem_produto" name="origem_produto" required>
                                            <option value="">Selecione...</option>
                                            <option value="0">0 - Nacional</option>
                                            <option value="1">1 - Estrangeira (importação direta)</option>
                                            <option value="2">2 - Estrangeira (adquirida no mercado interno)</option>
                                        </select>
                                    </div>

                                    <div class="mb-3 col-12 col-md-4">
                                        <label for="tributacao_produto" class="form-label">Tributação*</label>
                                        <select class="form-select" id="tributacao_produto" name="tributacao_produto" required>
                                            <option value="">Selecione...</option>
                                            <option value="00">00 - Tributada integralmente</option>
                                            <option value="20">20 - Com redução de base de cálculo</option>
                                            <option value="40">40 - Isenta</option>
                                            <option value="41">41 - Não tributada</option>
                                            <option value="60">60 - ICMS cobrado anteriormente</option>
                                        </select>
                                    </div>

                                    <div class="mb-3 col-12 col-md-4">
                                        <label for="unidade_produto" class="form-label">Unidade*</label>
                                        <select class="form-select" id="unidade_produto" name="unidade_produto" required>
                                            <option value="">Selecione...</option>
                                            <option value="UN">UN - Unidade</option>
                                            <option value="PC">PC - Peça</option>
                                            <option value="KG">KG - Quilograma</option>
                                            <option value="LT">LT - Litro</option>
                                            <option value="CX">CX - Caixa</option>
                                        </select>
                                    </div>

                                    <!-- Dados comerciais -->
                                    <div class="mb-3 col-12 col-md-6">
                                        <label for="categoria_produto" class="form-label">Categoria*</label>
                                        <input type="text" class="form-control" id="categoria_produto"
                                            name="categoria_produto" placeholder="Informe a categoria" required />
                                    </div>

                                    <div class="mb-3 col-12 col-md-6">
                                        <label for="quantidade_produto" class="form-label">Quantidade*</label>
                                        <input type="number" step="0.01" class="form-control" id="quantidade_produto"
                                            name="quantidade_produto" placeholder="Ex: 1.00" required />
                                    </div>

                                    <div class="mb-3 col-12 col-md-4">
                                        <label for="preco_produto" class="form-label">Preço Unitário (R$)*</label>
                                        <input type="text" class="form-control money" id="preco_produto" name="preco_produto"
                                            placeholder="Ex: 10,99" required />
                                    </div>

                                    <div class="mb-3 col-12 col-md-4">
                                        <label for="preco_custo" class="form-label">Preço de Custo (R$)</label>
                                        <input type="text" class="form-control money" id="preco_custo" name="preco_custo"
                                            placeholder="Ex: 7,50" />
                                    </div>

                                    <div class="mb-3 col-12 col-md-4">
                                        <label for="status_produto" class="form-label">Status*</label>
                                        <select class="form-select" id="status_produto" name="status_produto" required>
                                            <option value=""></option>
                                            <option value="ativo">Ativo</option>
                                            <option value="inativo">Inativo</option>
                                            <option value="estoque_alto">Estoque Alto</option>
                                            <option value="estoque_baixo">Estoque Baixo</option>
                                        </select>
                                    </div>

                                    <!-- Campos adicionados que estavam faltando -->
                                    <div class="mb-3 col-12 col-md-6">
                                        <label for="codigo_barras" class="form-label">Código de Barras</label>
                                        <input type="text" class="form-control" id="codigo_barras" name="codigo_barras"
                                            placeholder="Código de barras do produto" />
                                    </div>

                                    <div class="mb-3 col-12 col-md-6">
                                        <label for="codigo_anp" class="form-label">Código ANP (para combustíveis)</label>
                                        <input type="text" class="form-control" id="codigo_anp" name="codigo_anp"
                                            placeholder="Código ANP (se aplicável)" />
                                    </div>

                                    <div class="mb-3 col-12 col-md-4">
                                        <label for="peso_bruto" class="form-label">Peso Bruto (kg)</label>
                                        <input type="number" step="0.001" class="form-control" id="peso_bruto" name="peso_bruto"
                                            placeholder="Peso bruto em kg" />
                                    </div>

                                    <div class="mb-3 col-12 col-md-4">
                                        <label for="peso_liquido" class="form-label">Peso Líquido (kg)</label>
                                        <input type="number" step="0.001" class="form-control" id="peso_liquido" name="peso_liquido"
                                            placeholder="Peso líquido em kg" />
                                    </div>

                                    <div class="mb-3 col-12 col-md-4">
                                        <label for="aliquota_icms" class="form-label">Alíquota ICMS (%)</label>
                                        <input type="number" step="0.01" class="form-control" id="aliquota_icms" name="aliquota_icms"
                                            placeholder="Ex: 18.00" />
                                    </div>

                                    <div class="mb-3 col-12 col-md-6">
                                        <label for="aliquota_pis" class="form-label">Alíquota PIS (%)</label>
                                        <input type="number" step="0.01" class="form-control" id="aliquota_pis" name="aliquota_pis"
                                            placeholder="Ex: 1.65" />
                                    </div>

                                    <div class="mb-3 col-12 col-md-6">
                                        <label for="aliquota_cofins" class="form-label">Alíquota COFINS (%)</label>
                                        <input type="number" step="0.01" class="form-control" id="aliquota_cofins" name="aliquota_cofins"
                                            placeholder="Ex: 7.60" />
                                    </div>

                                    <!-- Informações adicionais para NFC-e -->
                                    <div class="mb-3 col-12">
                                        <label for="informacoes_adicionais" class="form-label">Informações Adicionais (NFC-e)</label>
                                        <textarea class="form-control" id="informacoes_adicionais" name="informacoes_adicionais"
                                            rows="2" placeholder="Informações que aparecerão na NFC-e"></textarea>
                                    </div>

                                    <div class="d-flex custom-button">
                                        <button type="submit" class="btn btn-primary col-12 w-100 col-md-auto">Salvar Produto</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>


            </div>
            <!-- Content wrapper -->
        </div>
        <!-- / Layout page -->

    </div>

    <!-- Overlay -->

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
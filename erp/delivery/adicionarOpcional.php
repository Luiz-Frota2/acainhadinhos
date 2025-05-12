<?php
session_start();
require_once '../../assets/php/conexao.php';

// Recupera o identificador da empresa (idSelecionado) e do produto (id)
$idSelecionado = $_GET['idSelecionado'] ?? '';
$id_produto = $_GET['id'] ?? '';

// Verifica se os parâmetros necessários foram passados corretamente
if (empty($idSelecionado)) {
    echo "<script>
            alert('Erro: idSelecionado não fornecido.');
            window.location.href = '.././login.php'; // Redireciona para onde deseja
          </script>";
    exit;
}

if (empty($id_produto)) {
    echo "<script>
            alert('Erro: id_produto não fornecido.');
            window.location.href = '.././login.php'; // Redireciona para onde deseja
          </script>";
    exit;
}

// Verifica se a pessoa está logada
if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id']) // Adiciona verificação do id do usuário
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
    $id = 1; // Principal
} elseif (str_starts_with($idSelecionado, 'filial_')) {
    $idFilial = (int) str_replace('filial_', '', $idSelecionado);
    if ($_SESSION['tipo_empresa'] !== 'filial' || $_SESSION['empresa_id'] != $idFilial) {
        echo "<script>
                alert('Acesso negado!');
                window.location.href = '.././login.php?id=$idSelecionado';
            </script>";
        exit;
    }
    $id = $idFilial; // Filial
} else {
    echo "<script>
          alert('Empresa não identificada!');
          window.location.href = '.././login.php?id=$idSelecionado';
      </script>";
    exit;
}

// Se chegou até aqui, o acesso está liberado
// Buscar nome e nível do usuário logado
$nomeUsuario = 'Usuário';
$nivelUsuario = 'Comum'; // Valor padrão
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

?>

<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
    data-assets-path="../assets/" data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>ERP - Delivery</title>

    <meta name="description" content="" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../assets/img/favicon/logo.png" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
        rel="stylesheet" />

    <!-- Icons. Uncomment required icon fonts -->
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

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

    <!-- Layout-wrapper -->
    <div class="layout-wrapper layout-content-navbar">

        <!-- layout-container -->
        <div class="layout-container">

            <!-- Menu -->
            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="app-brand-link">

                        <span class="app-brand-text demo menu-text fw-bolder ms-2" style=" text-transform: capitalize;">Açaínhadinhos</span>

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

                    <!--DELIVERY-->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Delivery</span></li>
                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons tf-icons bx bx-food-menu"></i>
                            <div data-i18n="Authentications">Cardápio</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./produtoAdicionados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Produtos Adicionados</div>
                                </a>
                            </li>
                        </ul>
                        <ul class="menu-sub">
                            <li class="menu-item active">
                                <a href="#" class="menu-link">
                                    <div data-i18n="Basic">Adicionar Opcional</div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons tf-icons bx bx-cog"></i>
                            <div data-i18n="Authentications">Configuração</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./deliveryRetirada.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Delivery e Retirada</div>
                                </a>
                            </li>
                        </ul>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./formaPagamento.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Formas de Pagamento </div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx  bx-building"></i>
                            <div data-i18n="Authentications">Empresa</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./sobreEmpresa.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Sobre</div>
                                </a>
                            </li>
                        </ul>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./enderecoEmpresa.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Endereço</div>
                                </a>
                            </li>
                        </ul>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./horarioFuncionamento.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Horário</div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons tf-icons bx bx-trending-up"></i>
                            <div data-i18n="Authentications">Relatorios</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./listarPedidos.html?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Lista de Pedidos</div>
                                </a>
                            </li>
                        </ul>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./maisVendidos.html?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Mais vendidos</div>
                                </a>
                            </li>
                        </ul>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./relatorioClientes.html?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Clientes</div>
                                </a>
                            </li>
                        </ul>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./relatorioVendas.html?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Vendas</div>
                                </a>
                            </li>
                        </ul>
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
                        <a href="./pdv/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-desktop"></i>
                            <div data-i18n="Authentications">PDV</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-box"></i>
                            <div data-i18n="Authentications">Estoque</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../clientes/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-user"></i>
                            <div data-i18n="Authentications">Clientes</div>
                        </a>
                    </li>
                    <?php
                    $isFilial = str_starts_with($idSelecionado, 'filial_');
                    $link = $isFilial
                        ? '../matriz/index.php?id=' . urlencode($idSelecionado)
                        : '../filial/index.php?id=principal_1';
                    $titulo = $isFilial ? 'Matriz' : 'Filial';
                    ?>

                    <li class="menu-item">
                        <a href="<?= $link ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-cog"></i>
                            <div data-i18n="Authentications"><?= $titulo ?></div>
                        </a>
                    </li>
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
            <!-- /Menu -->

            <!-- Layout-page -->
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
                                                    <span class="fw-semibold d-block"><?= htmlspecialchars($nomeUsuario) ?></span>
                                                    <small class="text-muted"><?= htmlspecialchars($nivelUsuario) ?></small>
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
                                            <span class="align-middle">My Profile</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <i class="bx bx-cog me-2"></i>
                                            <span class="align-middle">Settings</span>
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
                                        <a class="dropdown-item" href="index.php">
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

                <!--Formulario-->

                <!-- Content -->
                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold py-3 mb-0"><span class="text-muted fw-light"><a
                                href="./produtoAdicionados.php?id=<?= urlencode($idSelecionado); ?>">Cardápio</a>/</span>Adicionar Opcional</h4>

                    <h5 class="fw-bold mt-2 mb-3 custor-font">
                        <span class="text-muted fw-light">Adicione a opção extra ao seu produto</span>
                    </h5>

                    <!-- Basic Layout -->
                    <div class="row">
                        <div class="col-xl">
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Formulário de Opção extra</h5>
                                </div>
                                <div class="card-body">

                                    <!-- Formulário de Cadastro de Opcional -->
                                    <form action="../../assets/php/delivery/cadastrarOpcional.php" method="POST">
                                        <!-- Campo oculto para passar o id_produto -->
                                        <input type="hidden" name="id_produto" value="<?php echo htmlspecialchars($id_produto); ?>">

                                        <!-- Campo oculto para passar o idSelecionado -->
                                        <input type="hidden" name="id_selecionado" value="<?php echo htmlspecialchars($idSelecionado); ?>">

                                        <p class="fw-bold">Selecione o tipo de opcional:</p>
                                        <div class="d-flex gap-3">
                                            <div class="form-check none-boxshadow">
                                                <input type="radio" class="form-check-input" name="tipoOpcional" id="opcionalSimples" value="opcionalSimples" checked onclick="alternarOpcional()">
                                                <label class="form-check-label" for="opcionalSimples">Opcional Simples</label>
                                            </div>
                                            <div class="form-check none-boxshadow">
                                                <input type="radio" class="form-check-input" name="tipoOpcional" id="selecaoOpcoes" value="selecaoOpcoes" onclick="alternarOpcional()">
                                                <label class="form-check-label" for="selecaoOpcoes">Seleção de Opções</label>
                                            </div>
                                        </div>

                                        <!-- Seção para Opcional Simples -->
                                        <div id="OpcionalSimples" class="mt-4">
                                            <div class="row">
                                                <div class="col-9">
                                                    <label for="txtNomeSimples" class="form-label"><b>Nome:</b></label>
                                                    <input id="txtNomeSimples" type="text" name="txtNomeSimples" class="form-control" placeholder="Ex: Bacon">
                                                </div>
                                                <div class="col-3">
                                                    <label for="txtPrecoSimples" class="form-label"><b>Preço (R$):</b></label>
                                                    <input id="txtPrecoSimples" type="text" name="txtPrecoSimples" class="form-control" placeholder="0,00">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Seção para Seleção de Opções -->
                                        <div id="SelecaoOpcoes" class="mt-4" style="display: none;">
                                            <label for="txtTituloSecao" class="form-label"><b>Título da seção:</b></label>
                                            <input id="txtTituloSecao" type="text" name="txtTituloSecao" class="form-control" placeholder="Ex: Deseja borda recheada?">

                                            <div class="row mt-3">
                                                <div class="col-6">
                                                    <label for="txtMinimoOpcao" class="form-label"><b>Mínimo:</b></label>
                                                    <input id="txtMinimoOpcao" type="number" name="txtMinimoOpcao" min="0" class="form-control" placeholder="0">
                                                </div>
                                                <div class="col-6">
                                                    <label for="txtMaximoOpcao" class="form-label"><b>Máximo:</b></label>
                                                    <input id="txtMaximoOpcao" type="number" name="txtMaximoOpcao" min="1" class="form-control" placeholder="0">
                                                </div>
                                            </div>

                                            <div class="mt-3">
                                                <label class="form-label"><b>Informe as opções:</b></label>
                                                <div id="listaOpcoesSelecao"></div>
                                                <button type="button" class="btn btn-outline-primary mt-3" onclick="adicionarOpcao()">
                                                    <i class="fas fa-plus-circle"></i> Adicionar opção
                                                </button>
                                            </div>
                                        </div>

                                        <div class="d-flex custom-button mt-4">
                                            <button type="submit" class="btn btn-primary col-12 w-100 col-md-auto">Salvar Opcional</button>
                                        </div>
                                    </form>

                                    <script>
                                        // Função para alternar entre as seções de "Opcional Simples" e "Seleção de Opções"
                                        function alternarOpcional() {
                                            const opcionalSimples = document.getElementById('OpcionalSimples');
                                            const selecaoOpcoes = document.getElementById('SelecaoOpcoes');
                                            const chkSimples = document.getElementById('opcionalSimples');

                                            if (chkSimples.checked) {
                                                opcionalSimples.style.display = "block";
                                                selecaoOpcoes.style.display = "none";
                                            } else {
                                                opcionalSimples.style.display = "none";
                                                selecaoOpcoes.style.display = "block";
                                            }
                                        }

                                        // Função para adicionar uma nova opção para "Seleção de Opções"
                                        function adicionarOpcao() {
                                            const listaOpcoes = document.getElementById('listaOpcoesSelecao');

                                            // Cria a estrutura de uma nova linha de opções
                                            const divRow = document.createElement('div');
                                            divRow.className = "row mt-2 align-items-end";

                                            // Nome da opção
                                            const divColNome = document.createElement('div');
                                            divColNome.className = "col-8";
                                            divColNome.innerHTML = `
                                                <label class="form-label"><b>Nome:</b></label>
                                                <input type="text" name="opcaoNome[]" class="form-control" placeholder="Ex: Queijo extra">
                                                `;

                                            // Preço da opção
                                            const divColPreco = document.createElement('div');
                                            divColPreco.className = "col-3";
                                            divColPreco.innerHTML = `
                                                <label class="form-label"><b>Preço (R$):</b></label>
                                                <input type="text" name="opcaoPreco[]" class="form-control" placeholder="0,00">
                                                `;

                                            // Botão para remover a opção
                                            const divColRemove = document.createElement('div');
                                            divColRemove.className = "col-1 d-flex align-items-center";
                                            divColRemove.innerHTML = `
                                                <button type="button" class="btn btn-danger btn-sm" onclick="removerOpcao(this)">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                                `;

                                            // Adiciona as colunas à linha
                                            divRow.appendChild(divColNome);
                                            divRow.appendChild(divColPreco);
                                            divRow.appendChild(divColRemove);

                                            // Adiciona a linha à lista de opções
                                            listaOpcoes.appendChild(divRow);
                                        }

                                        // Função para remover uma opção
                                        function removerOpcao(button) {
                                            const row = button.closest('.row');
                                            row.remove();
                                        }
                                    </script>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- /Layout-wrapper -->


    <!-- Core JS -->
    <!-- build:js assets/vendor/js/core.js -->
    <script src="../../assets/js/delivery/delivery.js"></script>
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
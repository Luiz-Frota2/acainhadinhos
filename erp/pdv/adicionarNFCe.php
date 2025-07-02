<?php

session_start();
require_once '../../assets/php/conexao.php';

// ✅ Recupera o identificador vindo da URL
$idSelecionado = $_GET['id'] ?? '';

// ✅ Verifica se a pessoa está logada
if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id']) // adiciona verificação do id do usuário
) {
    header("Location: .././login.php?id=$idSelecionado");
    exit;
}

// ✅ Valida o tipo de empresa e o acesso permitido
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

// ✅ Buscar imagem da tabela sobre_empresa com base no idSelecionado
try {
    $sql = "SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_selecionado', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);

    $logoEmpresa = !empty($empresaSobre['imagem'])
        ? "../../assets/img/empresa/" . $empresaSobre['imagem']
        : "../../assets/img/favicon/logo.png"; // fallback padrão
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png"; // fallback em caso de erro
}

// ✅ Se chegou até aqui, o acesso está liberado

// ✅ Buscar nome e nível do usuário logado
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
    data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>ERP - PDV</title>

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
                        <span class="menu-header-text">Administrativo</span>
                    </li>

                    <!-- SUBMENU: SEFAZ -->
                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-file"></i>
                            <div data-i18n="Authentications">SEFAZ</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item active">
                                <a href="./adicionarNFCe.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">NFC-e</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./sefazSAT.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">SAT</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./sefazConsulta.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Consulta</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- SUBMENU: CAIXA -->
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-user"></i>
                            <div data-i18n="Authentications">Caixas</div>
                        </a>
                        <ul class="menu-sub">
                            <!-- Caixa Aberto: Visualização de caixas abertos -->
                            <li class="menu-item">
                                <a href="./caixasAberto.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Caixas Aberto</div>
                                </a>
                            </li>
                            <!-- Caixa Fechado: Histórico ou controle de caixas encerrados -->
                            <li class="menu-item">
                                <a href="./caixasFechado.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Caixas Fechado</div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <!-- ESTOQUE COM SUBMENU -->
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-box"></i>
                            <div data-i18n="Basic">Estoque</div>
                        </a>
                        <ul class="menu-sub">
                            <!-- Produtos Adicionados: Cadastro ou listagem de produtos adicionados -->
                            <li class="menu-item ">
                                <a href="./produtosAdicionados.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div data-i18n="Basic">Produtos Adicionados</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./adicionarProduto.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Adicionar Produto </div>
                                </a>
                            </li>
                            <!-- Estoque Baixo -->
                            <li class="menu-item">
                                <a href="./estoqueBaixo.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Estoque Baixo</div>
                                </a>
                            </li>
                            <!-- Estoque Alto -->
                            <li class="menu-item">
                                <a href="./estoqueAlto.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Estoque Alto</div>
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
                            <!-- Relatório Operacional: Desempenho de operações -->
                            <li class="menu-item">
                                <a href="./relatorioOperacional.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div data-i18n="Basic">Operacional</div>
                                </a>
                            </li>
                            <!-- Relatório de Vendas: Estatísticas e resumo de vendas -->
                            <li class="menu-item">
                                <a href="./relatorioVendas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Vendas</div>
                                </a>
                            </li>
                        </ul>

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
                                        <img src="<?= htmlspecialchars($logoEmpresa) ?>" alt
                                            class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
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


                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold py-3 mb-4"><span class="fw-light" style="color: #696cff !important;"><a
                                href="./produtosAdicionados.php?id=<?= urlencode($idSelecionado); ?>">PDV</a></span>/Adicionar
                        Integração</h4>

                    <!-- / Content -->
                    <div class="card">
                        <h5 class="card-header">Configuração da Integração NFC-e</h5>
                        <div class="card-body">

                            <form action="../../assets/php/pdv/adicionarIntegracaoNFCe.php" method="POST" id="formIntegracaoNfce">

                                <input type="hidden" name="empresa_id" value="<?= htmlspecialchars($idSelecionado) ?>">

                                <div class="row">
                                    <!-- Dados da Empresa -->
                                    <div class="mb-3 col-md-6">
                                        <label for="cnpj">CNPJ da Empresa</label>
                                        <input type="text" class="form-control" name="cnpj" id="cnpj" required
                                            placeholder="00.000.000/0001-00" oninput="formatarCNPJ(this)">
                                        <div class="invalid-feedback">Por favor, insira um CNPJ válido.</div>
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="razao_social">Razão Social</label>
                                        <input type="text" class="form-control" name="razao_social" id="razao_social"
                                            required maxlength="60">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="nome_fantasia">Nome Fantasia</label>
                                        <input type="text" class="form-control" name="nome_fantasia" id="nome_fantasia"
                                            required maxlength="60">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="inscricao_estadual">Inscrição Estadual</label>
                                        <input type="text" class="form-control" name="inscricao_estadual" id="inscricao_estadual"
                                            required>
                                        <div class="invalid-feedback">Inscrição Estadual é obrigatória.</div>
                                    </div>

                                    <!-- Endereço -->
                                    <div class="mb-3 col-md-6">
                                        <label for="cep">CEP</label>
                                        <input type="text" class="form-control" name="cep" id="cep" required
                                            placeholder="00000-000" oninput="formatarCEP(this)">
                                        <div class="invalid-feedback">CEP inválido.</div>
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="logradouro">Logradouro</label>
                                        <input type="text" class="form-control" name="logradouro" id="logradouro"
                                            required>
                                    </div>

                                    <div class="mb-3 col-md-4">
                                        <label for="numero_endereco">Número</label>
                                        <input type="text" class="form-control" name="numero_endereco" id="numero_endereco"
                                            required>
                                    </div>

                                    <div class="mb-3 col-md-4">
                                        <label for="complemento">Complemento</label>
                                        <input type="text" class="form-control" name="complemento" id="complemento">
                                    </div>

                                    <div class="mb-3 col-md-4">
                                        <label for="bairro">Bairro</label>
                                        <input type="text" class="form-control" name="bairro" id="bairro" required>
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="cidade">Cidade</label>
                                        <input type="text" class="form-control" name="cidade" id="cidade" required>
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="uf">UF</label>
                                        <select name="uf" id="uf" class="form-select" required>
                                            <option value="">Selecione</option>
                                            <option value="AC">AC</option>
                                            <option value="AL">AL</option>
                                            <option value="AP">AP</option>
                                            <option value="AM">AM</option>
                                            <option value="BA">BA</option>
                                            <option value="CE">CE</option>
                                            <option value="DF">DF</option>
                                            <option value="ES">ES</option>
                                            <option value="GO">GO</option>
                                            <option value="MA">MA</option>
                                            <option value="MT">MT</option>
                                            <option value="MS">MS</option>
                                            <option value="MG">MG</option>
                                            <option value="PA">PA</option>
                                            <option value="PB">PB</option>
                                            <option value="PR">PR</option>
                                            <option value="PE">PE</option>
                                            <option value="PI">PI</option>
                                            <option value="RJ">RJ</option>
                                            <option value="RN">RN</option>
                                            <option value="RS">RS</option>
                                            <option value="RO">RO</option>
                                            <option value="RR">RR</option>
                                            <option value="SC">SC</option>
                                            <option value="SP">SP</option>
                                            <option value="SE">SE</option>
                                            <option value="TO">TO</option>
                                        </select>
                                    </div>

                                    <!-- Configurações NFC-e -->
                                    <div class="mb-3 col-md-6">
                                        <label for="token_api">Token da API (Focus, TecnoSpeed, etc.)</label>
                                        <input type="password" class="form-control" name="token_api" id="token_api"
                                            required>
                                        <small class="text-muted">Mantenha este token em segredo.</small>
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="certificado_digital">Certificado Digital (arquivo .pfx)</label>
                                        <input type="file" class="form-control" name="certificado_digital" id="certificado_digital"
                                            accept=".pfx">
                                        <small class="text-muted">Necessário para emissão direta com a SEFAZ.</small>
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="senha_certificado">Senha do Certificado Digital</label>
                                        <input type="password" class="form-control" name="senha_certificado" id="senha_certificado">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="ambiente">Ambiente</label>
                                        <select name="ambiente" id="ambiente" class="form-select" required>
                                            <option value="">Selecione</option>
                                            <option value="1">Produção</option>
                                            <option value="2">Homologação</option>
                                        </select>
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="serie">Série da Nota</label>
                                        <input type="number" class="form-control" name="serie" id="serie" value="1"
                                            required min="1" max="999">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="numero">Número Inicial da Nota</label>
                                        <input type="number" class="form-control" name="numero" id="numero" value="1"
                                            required min="1">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="regime_tributario">Regime Tributário</label>
                                        <select name="regime_tributario" id="regime_tributario" class="form-select"
                                            required>
                                            <option value="">Selecione</option>
                                            <option value="1">Simples Nacional</option>
                                            <option value="2">Simples Nacional - excesso sublimite</option>
                                            <option value="3">Regime Normal</option>
                                        </select>
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="csc">Código de Segurança do Contribuinte (CSC)</label>
                                        <input type="text" class="form-control" name="csc" id="csc">
                                        <small class="text-muted">Obrigatório para geração do QR Code.</small>
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="id_token">ID do Token</label>
                                        <input type="text" class="form-control" name="id_token" id="id_token">
                                        <small class="text-muted">Identificador do CSC (normalmente 000001).</small>
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="timeout">Timeout da Conexão (segundos)</label>
                                        <input type="number" class="form-control" name="timeout" id="timeout" value="30"
                                            min="10" max="120">
                                    </div>

                                    <!-- Configurações Avançadas -->
                                    <div class="mb-3 col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="contingencia" id="contingencia">
                                            <label class="form-check-label" for="contingencia">
                                                Ativar modo contingência automático
                                            </label>
                                        </div>
                                    </div>

                                    <div class="mb-3 col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="salvar_xml" id="salvar_xml" checked>
                                            <label class="form-check-label" for="salvar_xml">
                                                Salvar XMLs localmente
                                            </label>
                                        </div>
                                    </div>

                                    <div class="mb-3 col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="envio_email" id="envio_email">
                                            <label class="form-check-label" for="envio_email">
                                                Enviar NFC-e por e-mail automaticamente
                                            </label>
                                        </div>
                                    </div>

                                    <div class="mb-3 col-12">
                                        <button type="submit" class="btn btn-primary w-100" id="btnSalvarConfig">Salvar Configuração</button>
                                    </div>
                                </div>
                            </form>

                            <script>
                                // Funções de formatação e validação
                                function formatarCNPJ(input) {
                                    let value = input.value.replace(/\D/g, '');

                                    if (value.length > 14) {
                                        value = value.substring(0, 14);
                                    }

                                    if (value.length > 12) {
                                        value = value.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
                                    } else if (value.length > 8) {
                                        value = value.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})/, '$1.$2.$3/$4');
                                    } else if (value.length > 5) {
                                        value = value.replace(/^(\d{2})(\d{3})(\d{3})/, '$1.$2.$3');
                                    } else if (value.length > 2) {
                                        value = value.replace(/^(\d{2})(\d{3})/, '$1.$2');
                                    }

                                    input.value = value;
                                }

                                function formatarCEP(input) {
                                    let value = input.value.replace(/\D/g, '');

                                    if (value.length > 8) {
                                        value = value.substring(0, 8);
                                    }

                                    if (value.length > 5) {
                                        value = value.replace(/^(\d{5})(\d{3})/, '$1-$2');
                                    }

                                    input.value = value;

                                    // Buscar endereço se CEP estiver completo
                                    if (value.length === 9) {
                                        buscarEnderecoPorCEP(value);
                                    }
                                }

                                function buscarEnderecoPorCEP(cep) {
                                    // Remover máscara
                                    cep = cep.replace(/\D/g, '');

                                    if (cep.length !== 8) return;

                                    fetch(`https://viacep.com.br/ws/${cep}/json/`)
                                        .then(response => response.json())
                                        .then(data => {
                                            if (!data.erro) {
                                                document.getElementById('logradouro').value = data.logradouro || '';
                                                document.getElementById('bairro').value = data.bairro || '';
                                                document.getElementById('cidade').value = data.localidade || '';
                                                document.getElementById('uf').value = data.uf || '';
                                                document.getElementById('complemento').value = data.complemento || '';
                                            } else {
                                                alert('CEP não encontrado!');
                                            }
                                        })
                                        .catch(error => {
                                            console.error('Erro ao buscar CEP:', error);
                                        });
                                }

                                // Validação do formulário
                                document.getElementById('formIntegracaoNfce').addEventListener('submit', function(event) {
                                    let isValid = true;

                                    // Validar CNPJ
                                    const cnpj = document.getElementById('cnpj').value.replace(/\D/g, '');
                                    if (cnpj.length !== 14) {
                                        document.getElementById('cnpj').classList.add('is-invalid');
                                        isValid = false;
                                    } else {
                                        document.getElementById('cnpj').classList.remove('is-invalid');
                                    }

                                    // Validar CEP
                                    const cep = document.getElementById('cep').value.replace(/\D/g, '');
                                    if (cep.length !== 8) {
                                        document.getElementById('cep').classList.add('is-invalid');
                                        isValid = false;
                                    } else {
                                        document.getElementById('cep').classList.remove('is-invalid');
                                    }

                                    if (!isValid) {
                                        event.preventDefault();
                                        alert('Por favor, corrija os campos destacados antes de enviar.');
                                    } else {
                                        document.getElementById('btnSalvarConfig').disabled = true;
                                        document.getElementById('btnSalvarConfig').innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Salvando...';
                                    }
                                });
                            </script>

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
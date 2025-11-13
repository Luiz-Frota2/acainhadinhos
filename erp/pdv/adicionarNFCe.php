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

// ✅ Função compat: str_starts_with (se rodar em PHP < 8)
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        return 0 === strncmp($haystack, $needle, strlen($needle));
    }
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

/* =================== Buscar integração existente =================== */
$integracao = [];
try {
    $st = $pdo->prepare("SELECT * FROM integracao_nfce WHERE empresa_id = :emp LIMIT 1");
    $st->execute([':emp' => $idSelecionado]);
    $integracao = $st->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $integracao = [];
}

/* =================== Defaults + merge =================== */
$defaults = [
    'cnpj' => '',
    'razao_social' => '',
    'nome_fantasia' => '',
    'inscricao_estadual' => '',
    'inscricao_municipal' => '',
    'cep' => '',
    'logradouro' => '',
    'numero_endereco' => '',
    'complemento' => '',
    'bairro' => '',
    'cidade' => '',
    'uf' => '',
    'codigo_uf' => '',
    'codigo_municipio' => '',
    'telefone' => '',
    'certificado_digital' => '',
    'senha_certificado' => '',
    'ambiente' => '',
    'regime_tributario' => '',
    'serie_nfce' => 1,
    'ultimo_numero_nfce' => 1,
    'csc' => '',
    'csc_id' => '',
    'tipo_emissao' => 1,
    'finalidade' => 1,
    'ind_pres' => 1,
    'tipo_impressao' => 4
];
$dados = array_merge($defaults, $integracao);

/* Helpers view */
function sel($a, $b)
{
    return (string)$a === (string)$b ? 'selected' : '';
}
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
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
    <link rel="icon" type="image/x-icon" href="<?= h($logoEmpresa) ?>" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
        rel="stylesheet" />

    <!-- Icons -->
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />

    <!-- Helpers -->
    <script src="../../assets/vendor/js/helpers.js"></script>
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
                        <span class="menu-header-text">PDV</span>
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
                                <a href="./sefazStatus.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Status</div>
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
                            <li class="menu-item">
                                <a href="./caixasAberto.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Caixas Aberto</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./caixasFechado.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Caixas Fechado</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- RELATÓRIOS -->
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-file"></i>
                            <div data-i18n="Authentications">Relatórios</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./relatorioOperacional.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Basic">Operacional</div>
                                </a>
                            </li>
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
                        <a href="../empresa/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div data-i18n="Authentications">Empresa</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-box"></i>
                            <div data-i18n="Authentications">Estoque</div>
                        </a>
                    </li>
                    <?php
                    $tipoLogado = $_SESSION['tipo_empresa'] ?? '';
                    $idLogado = $_SESSION['empresa_id'] ?? '';

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
                    ?>
                        <li class="menu-item">
                            <a href="../matriz/index.php?id=<?= urlencode($idLogado) ?>" class="menu-link">
                                <i class="menu-icon tf-icons bx bx-cog"></i>
                                <div data-i18n="Authentications">Matriz</div>
                            </a>
                        </li>
                    <?php } ?>
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
                        <div class="navbar-nav align-items-center">
                            <div class="nav-item d-flex align-items-center">

                            </div>
                        </div>

                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="avatar avatar-online">
                                        <img src="<?= h($logoEmpresa) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownUser">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="<?= h($logoEmpresa) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" />
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <span class="fw-semibold d-block"><?= h($nomeUsuario); ?></span>
                                                    <small class="text-muted"><?= h($tipoUsuario); ?></small>
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
                <!-- / Navbar -->

                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold py-3 mb-4">
                        <span class="fw-light" style="color: #696cff !important;"><a href="#">PDV</a></span>/Adicionar Integração
                    </h4>

                    <!-- Card status opcional -->
                    <div class="alert <?= $integracao ? 'alert-success' : 'alert-secondary'; ?>">
                        <?= $integracao ? 'Edição da integração existente.' : 'Nenhuma integração encontrada. Preencha para criar.'; ?>
                        <?php if (!empty($dados['certificado_digital'])): ?>
                            <br><small>Certificado atual: <code><?= h($dados['certificado_digital']) ?></code></small>
                        <?php endif; ?>
                    </div>

                    <!-- / Content -->
                    <div class="card">
                        <h5 class="card-header">Configuração da Integração NFC-e</h5>
                        <div class="card-body">

                            <form action="../../assets/php/pdv/adicionarIntegracaoNFCe.php" method="POST" id="formIntegracaoNfce" enctype="multipart/form-data">
                                <input type="hidden" name="empresa_id" value="<?= h($idSelecionado) ?>">
                                <input type="hidden" name="modelo" value="65">
                                <input type="hidden" name="versao" value="4.00">

                                <div class="row">
                                    <!-- Dados da Empresa -->
                                    <div class="mb-3 col-md-6">
                                        <label for="cnpj">CNPJ da Empresa</label>
                                        <input type="text" class="form-control" name="cnpj" id="cnpj" required
                                            placeholder="00.000.000/0001-00" oninput="formatarCNPJ(this)"
                                            value="<?= h($dados['cnpj']) ?>">
                                        <div class="invalid-feedback">Por favor, insira um CNPJ válido.</div>
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="razao_social">Razão Social</label>
                                        <input type="text" class="form-control" name="razao_social" id="razao_social"
                                            required maxlength="255" value="<?= h($dados['razao_social']) ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="nome_fantasia">Nome Fantasia</label>
                                        <input type="text" class="form-control" name="nome_fantasia" id="nome_fantasia"
                                            required maxlength="255" value="<?= h($dados['nome_fantasia']) ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="inscricao_estadual">Inscrição Estadual</label>
                                        <input type="text" class="form-control" name="inscricao_estadual" id="inscricao_estadual"
                                            required maxlength="20" value="<?= h($dados['inscricao_estadual']) ?>">
                                        <div class="invalid-feedback">Inscrição Estadual é obrigatória.</div>
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="inscricao_municipal">Inscrição Municipal</label>
                                        <input type="text" class="form-control" name="inscricao_municipal" id="inscricao_municipal"
                                            maxlength="20" value="<?= h($dados['inscricao_municipal']) ?>">
                                    </div>

                                    <!-- Endereço -->
                                    <div class="mb-3 col-md-6">
                                        <label for="cep">CEP</label>
                                        <input type="text" class="form-control" name="cep" id="cep" required
                                            placeholder="00000-000" oninput="formatarCEP(this)" maxlength="9"
                                            value="<?= h($dados['cep']) ?>">
                                        <div class="invalid-feedback">CEP inválido.</div>
                                    </div>

                                    <div class="mb-3 col-md-8">
                                        <label for="logradouro">Logradouro</label>
                                        <input type="text" class="form-control" name="logradouro" id="logradouro"
                                            required maxlength="255" value="<?= h($dados['logradouro']) ?>">
                                    </div>

                                    <div class="mb-3 col-md-4">
                                        <label for="numero_endereco">Número</label>
                                        <input type="text" class="form-control" name="numero_endereco" id="numero_endereco"
                                            required maxlength="20" value="<?= h($dados['numero_endereco']) ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="complemento">Complemento</label>
                                        <input type="text" class="form-control" name="complemento" id="complemento"
                                            maxlength="255" value="<?= h($dados['complemento']) ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="bairro">Bairro</label>
                                        <input type="text" class="form-control" name="bairro" id="bairro" required
                                            maxlength="100" value="<?= h($dados['bairro']) ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="cidade">Cidade</label>
                                        <input type="text" class="form-control" name="cidade" id="cidade" required
                                            maxlength="100" value="<?= h($dados['cidade']) ?>">
                                    </div>

                                    <div class="mb-3 col-md-3">
                                        <label for="uf">UF</label>
                                        <select name="uf" id="uf" class="form-select" required>
                                            <option value="">Selecione</option>
                                            <?php
                                            $ufs = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
                                            foreach ($ufs as $ufOpt) {
                                                echo '<option value="' . h($ufOpt) . '" ' . sel($dados['uf'], $ufOpt) . '>' . h($ufOpt) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>

                                    <div class="mb-3 col-md-3">
                                        <label for="codigo_uf">Código UF (IBGE)</label>
                                        <input type="number" class="form-control" name="codigo_uf" id="codigo_uf"
                                            readonly value="<?= h($dados['codigo_uf']) ?>" placeholder="Ex.: 35 p/ SP">
                                        <small class="text-muted">Preenchido automaticamente pela UF.</small>
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="codigo_municipio">Código do Município (IBGE)</label>
                                        <input type="text" class="form-control" name="codigo_municipio" id="codigo_municipio"
                                            required maxlength="7" pattern="[0-9]{7}" value="<?= h($dados['codigo_municipio']) ?>">
                                        <small class="text-muted">Código de 7 dígitos do IBGE para o município.</small>
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="telefone">Telefone</label>
                                        <input type="text" class="form-control" name="telefone" id="telefone"
                                            placeholder="(00) 0000-0000" oninput="formatarTelefone(this)" maxlength="15"
                                            value="<?= h($dados['telefone']) ?>">
                                    </div>

                                    <!-- Configurações NFC-e -->
                                    <div class="mb-3 col-md-6">
                                        <label for="certificado_digital">Certificado Digital (arquivo .pfx/.p12)</label>
                                        <input type="file" class="form-control" name="certificado_digital" id="certificado_digital" accept=".pfx,.p12">
                                        <?php if (!empty($dados['certificado_digital'])): ?>
                                            <small class="text-muted">Atual: <code><?= h($dados['certificado_digital']) ?></code></small>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="senha_certificado">Senha do Certificado Digital</label>
                                        <input type="password" class="form-control" name="senha_certificado" id="senha_certificado" maxlength="255" placeholder="<?= $integracao ? 'Deixe em branco para manter' : '' ?>">
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="ambiente">Ambiente</label>
                                        <select name="ambiente" id="ambiente" class="form-select" required>
                                            <option value="">Selecione</option>
                                            <option value="1" <?= sel($dados['ambiente'], 1) ?>>Produção</option>
                                            <option value="2" <?= sel($dados['ambiente'], 2) ?>>Homologação</option>
                                        </select>
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="tipo_emissao">Tipo de Emissão</label>
                                        <select name="tipo_emissao" id="tipo_emissao" class="form-select" required>
                                            <option value="">Selecione</option>
                                            <option value="1" <?= sel($dados['tipo_emissao'], 1) ?>>Normal</option>
                                            <option value="2" <?= sel($dados['tipo_emissao'], 2) ?>>Contingência FS</option>
                                            <option value="3" <?= sel($dados['tipo_emissao'], 3) ?>>Contingência SCAN</option>
                                            <option value="4" <?= sel($dados['tipo_emissao'], 4) ?>>Contingência DPEC</option>
                                            <option value="5" <?= sel($dados['tipo_emissao'], 5) ?>>Contingência FS-DA</option>
                                        </select>
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="regime_tributario">Regime Tributário</label>
                                        <select name="regime_tributario" id="regime_tributario" class="form-select" required>
                                            <option value="">Selecione</option>
                                            <option value="1" <?= sel($dados['regime_tributario'], 1) ?>>Simples Nacional</option>
                                            <option value="2" <?= sel($dados['regime_tributario'], 2) ?>>Simples Nacional - excesso sublimite</option>
                                            <option value="3" <?= sel($dados['regime_tributario'], 3) ?>>Regime Normal</option>
                                        </select>
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="finalidade">Finalidade da Emissão</label>
                                        <select name="finalidade" id="finalidade" class="form-select" required>
                                            <option value="1" <?= sel($dados['finalidade'], 1) ?>>Normal</option>
                                            <option value="2" <?= sel($dados['finalidade'], 2) ?>>Complementar</option>
                                            <option value="3" <?= sel($dados['finalidade'], 3) ?>>Ajuste</option>
                                            <option value="4" <?= sel($dados['finalidade'], 4) ?>>Devolução</option>
                                        </select>
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="ind_pres">Indicador de Presença</label>
                                        <select name="ind_pres" id="ind_pres" class="form-select" required>
                                            <option value="0" <?= sel($dados['ind_pres'], 0) ?>>Não se aplica</option>
                                            <option value="1" <?= sel($dados['ind_pres'], 1) ?>>Operação presencial</option>
                                            <option value="2" <?= sel($dados['ind_pres'], 2) ?>>Operação não presencial, internet</option>
                                            <option value="3" <?= sel($dados['ind_pres'], 3) ?>>Operação não presencial, teleatendimento</option>
                                            <option value="4" <?= sel($dados['ind_pres'], 4) ?>>NFC-e em operação com entrega em domicílio</option>
                                            <option value="5" <?= sel($dados['ind_pres'], 5) ?>>Operação presencial, fora do estabelecimento</option>
                                            <option value="9" <?= sel($dados['ind_pres'], 9) ?>>Operação não presencial, outros</option>
                                        </select>
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="tipo_impressao">Tipo de Impressão DANFE</label>
                                        <select name="tipo_impressao" id="tipo_impressao" class="form-select" required>
                                            <option value="4" <?= sel($dados['tipo_impressao'], 4) ?>>NFC-e</option>
                                            <option value="5" <?= sel($dados['tipo_impressao'], 5) ?>>NFC-e em mensagem eletrônica</option>
                                        </select>
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="serie_nfce">Série da NFC-e</label>
                                        <input type="number" class="form-control" name="serie_nfce" id="serie_nfce"
                                            value="<?= h($dados['serie_nfce']) ?>" required min="1" max="999">
                                        <small class="text-muted">Normalmente começa com 1.</small>
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="ultimo_numero_nfce">Último número da NFC-e</label>
                                        <input type="number" class="form-control" name="ultimo_numero_nfce" id="ultimo_numero_nfce"
                                            value="<?= h($dados['ultimo_numero_nfce']) ?>" min="0">
                                        <small class="text-muted">Informe o último número emitido (o próximo será este + 1).</small>
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="csc">Código de Segurança do Contribuinte (CSC)</label>
                                        <input type="text" class="form-control" name="csc" id="csc" maxlength="255" value="<?= h($dados['csc']) ?>">
                                        <small class="text-muted">Obrigatório para geração do QR Code.</small>
                                    </div>

                                    <div class="mb-3 col-md-6">
                                        <label for="csc_id">ID do CSC</label>
                                        <input type="text" class="form-control" name="csc_id" id="csc_id" maxlength="20" value="<?= h($dados['csc_id']) ?>">
                                        <small class="text-muted">Identificador do CSC (normalmente 000001).</small>
                                    </div>

                                    <div class="mb-3 col-12">
                                        <button type="submit" class="btn btn-primary w-100" id="btnSalvarConfig">Salvar Configuração</button>
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

    <!-- Core JS -->
    <script src="../../js/saudacao.js"></script>
    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../assets/vendor/js/menu.js"></script>
    <script src="../../assets/vendor/libs/apex-charts/apexcharts.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/dashboards-analytics.js"></script>

    <!-- Mascaras simples + Código UF automático -->
    <script>
        const MAP_UF_IBGE = {
            "RO": 11,
            "AC": 12,
            "AM": 13,
            "RR": 14,
            "PA": 15,
            "AP": 16,
            "TO": 17,
            "MA": 21,
            "PI": 22,
            "CE": 23,
            "RN": 24,
            "PB": 25,
            "PE": 26,
            "AL": 27,
            "SE": 28,
            "BA": 29,
            "MG": 31,
            "ES": 32,
            "RJ": 33,
            "SP": 35,
            "PR": 41,
            "SC": 42,
            "RS": 43,
            "MS": 50,
            "MT": 51,
            "GO": 52,
            "DF": 53
        };

        function somenteNumeros(s) {
            return (s || '').replace(/\D+/g, '');
        }

        function formatarCNPJ(inp) {
            let v = somenteNumeros(inp.value).slice(0, 14);
            v = v.replace(/^(\d{2})(\d)/, "$1.$2")
                .replace(/^(\d{2})\.(\d{3})(\d)/, "$1.$2.$3")
                .replace(/\.(\d{3})(\d)/, ".$1/$2")
                .replace(/(\d{4})(\d)/, "$1-$2");
            inp.value = v;
        }

        function formatarCEP(inp) {
            let v = somenteNumeros(inp.value).slice(0, 8);
            v = v.replace(/^(\d{5})(\d{1,3})/, "$1-$2");
            inp.value = v;
        }

        function formatarTelefone(inp) {
            let v = somenteNumeros(inp.value).slice(0, 11);
            if (v.length <= 10) {
                v = v.replace(/^(\d{2})(\d)/, "($1) $2")
                    .replace(/(\d{4})(\d)/, "$1-$2");
            } else {
                v = v.replace(/^(\d{2})(\d)/, "($1) $2")
                    .replace(/(\d{5})(\d)/, "$1-$2");
            }
            inp.value = v;
        }

        function atualizarCodigoUF() {
            const uf = (document.getElementById('uf').value || '').toUpperCase();
            const codigo = MAP_UF_IBGE[uf] || '';
            document.getElementById('codigo_uf').value = codigo;
        }
        document.getElementById('uf').addEventListener('change', atualizarCodigoUF);
        // Ajusta no load
        atualizarCodigoUF();

        // Evita erro se alguém chamar buscarDadosCNPJ
        function buscarDadosCNPJ() {
            /* noop */
        }
    </script>
</body>

</html>
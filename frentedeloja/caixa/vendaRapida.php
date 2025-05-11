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
    header("Location: ../index.php?id=$idSelecionado");
    exit;
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

// ✅ Se chegou até aqui, o acesso está liberado

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
    echo "<script>alert('Erro ao carregar nome e tipo do usuário: " . $e->getMessage() . "'); history.back();</script>";
    exit;
}

// ✅ Buscar imagem da empresa para usar como favicon
$iconeEmpresa = '../assets/img/favicon/favicon.ico'; // Ícone padrão

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
    <link rel="icon" type="image/x-icon" href="../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>" />

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

                        <span class="app-brand-text demo menu-text fw-bolder ms-2">Açainhadinhos</span>
                    </a>

                    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
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
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Frente de Caixa</span></li>

                    <!-- Operações de Caixa -->
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

                    <!-- Vendas -->
                    <li class="menu-item active">
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
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <img src="../../assets/img/avatars/1.png" alt class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="../../assets/img/avatars/1.png" alt class="w-px-40 h-auto rounded-circle" />
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <!-- Exibindo o nome e nível do usuário -->
                                                    <span class="fw-semibold d-block"><?php echo $nomeUsuario; ?></span>
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
                                                <span class="flex-shrink-0 badge badge-center rounded-pill bg-danger w-px-20 h-px-20">4</span>
                                            </span>
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
                <?php
                try {
                    // Buscar todos os setores
                    $sql = "SELECT * FROM estoque WHERE empresa_id = :empresa_id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR); // Usa o idSelecionado
                    $stmt->execute();
                    $estoque = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    echo "Erro ao buscar produtos: " . $e->getMessage();
                    exit;
                }


                // Supondo que esses dados venham da sessão ou variável de sessão
                $responsavel = ucwords($nomeUsuario); // ou $_SESSION['usuario']
                $empresa_id = htmlspecialchars($idSelecionado); // ou $_POST['empresa_id']

                if (!$responsavel || !$empresa_id) {
                    die("Erro: Dados de sessão ausentes.");
                }

                try {
                    // Prepare a consulta SQL para buscar o ID baseado no responsável, empresa e status_abertura = 'aberto'
                    $stmt = $pdo->prepare("
        SELECT id 
        FROM aberturas 
        WHERE responsavel = :responsavel 
          AND empresa_id = :empresa_id 
          AND status_abertura = 'aberto'
        ORDER BY id DESC 
        LIMIT 1
    ");
                    $stmt->execute([
                        'responsavel' => $responsavel,
                        'empresa_id' => $empresa_id
                    ]);

                    // Busca o resultado e verifica se existe algum ID
                    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);



                ?>

                    <!-- / Navbar -->

                    <div class="container-xxl flex-grow-1 container-p-y">
                        <h4 class="fw-bold mb-0">
                            <span class="text-muted fw-light"><a href="./bancodeHoras.php">Venda Rápida</a></span>
                        </h4>
                        <h5 class="fw-bold mt-3 mb-3 custor-font">
                            <span class="text-muted fw-light">Registre uma nova venda</span>
                        </h5>

                        <div class="card">
                            <div class="card-body">
                                <div class="app-brand justify-content-center mb-4">
                                    <a href="#" class="app-brand-link gap-2">
                                        <span class="app-brand-text demo text-body fw-bolder">Venda Rápida</span>
                                    </a>
                                </div>
                                <div id="avisoSemCaixa" class="alert alert-danger text-center" style="display: none;">
                                    Nenhum caixa está aberto. Por favor, abra um caixa para continuar com a venda.
                                </div>


                                <form method="POST" action="../../assets/php/frentedeloja/login/vendaRapidaSubmit.php?id=<?= urlencode($idSelecionado); ?>">
                                    <!-- Campos ocultos -->

                                    <div id="produtos-container">
                                        <div class="produto-item border rounded p-3 mb-3">
                                            <div class="fixed-items" id="fixedDisplay">
                                                <label class="form-label">Nenhum Produto Selecionado</label>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Produto</label>
                                                <select class="form-select" id="multiSelect" multiple size="5">
                                                    <?php foreach ($estoque as $estoques): ?>
                                                        <option value="<?= $estoques['id'] ?>" data-nome="<?= htmlspecialchars($estoques['nome_produto']) ?>" data-preco="<?= $estoques['preco_produto'] ?>"><?= htmlspecialchars($estoques['nome_produto']) ?> - R$ <?= number_format($estoques['preco_produto'], 2, ',', '.') ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3 total">

                                                <label class="form-label">Valor Total</label><br>
                                                <input type="hidden" name="totalTotal" id="totalTotal" value="R$ 0.00">
                                                <span id="total">0.00</span>
                                            </div>
                                            <div class="mb-3">
                                                <label for="forma_pagamento" class="form-label">Forma de Pagamento</label>
                                                <select id="forma_pagamento" name="forma_pagamento" class="form-select" required>
                                                    <option value="">Selecione...</option>
                                                    <option value="Dinheiro">Dinheiro</option>
                                                    <option value="Cartão de Crédito">Cartão de Crédito</option>
                                                    <option value="Cartão de Débito">Cartão de Débito</option>
                                                    <option value="Pix">PIX</option>

                                                </select>
                                            </div>

                                            <div class="text-end">
                                                <button id="removerTodosBtn" type="button" class="btn btn-danger btn-sm remove-produto">Remover</button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-grid mb-3">
                                        <button id="fixarBtn" disabled type="button" class="btn btn-outline-primary">
                                            <i class="tf-icons bx bx-plus"></i> Adicionar Produto
                                        </button>
                                    </div>

                                    <div class="mb-3">
                                    <?php
                                    if ($resultado) {
                                        $idAbertura = $resultado['id'];
                                        echo "<input type='hidden' id='id_caixa' name='id_caixa' value='$idAbertura' >";
                                    } else {
                                        echo "";
                                    }
                                } catch (PDOException $e) {
                                    echo "Erro ao buscar ID: " . $e->getMessage();
                                }
                                    ?>
                                    <input type="hidden" id="responsavel" name="responsavel" value="<?= ucwords($nomeUsuario); ?>">
                                    <input type="hidden" name="idSelecionado" value="<?php echo htmlspecialchars($idSelecionado); ?>" />
                                    <button type="button" id="finalizarVendaBtn" class="btn btn-primary w-100">Finalizar Venda</button>

                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

            </div>
        </div>
    </div>

</body>

<script src="../../assets/vendor/libs/jquery/jquery.js"></script>
<script src="../../assets/vendor/libs/popper/popper.js"></script>
<script src="../../assets/vendor/js/bootstrap.js"></script>
<script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
<script src="../../assets/vendor/js/menu.js"></script>
<script src="../../assets/js/main.js"></script>

</html>
<script>
    const select = document.getElementById('multiSelect');
    const fixarBtn = document.getElementById('fixarBtn');
    const fixedDisplay = document.getElementById('fixedDisplay');
    const totalDisplay = document.getElementById('total');

    const fixedItems = new Map();

    let selectedOption = null;

    select.addEventListener('change', () => {
        selectedOption = select.options[select.selectedIndex];
        fixarBtn.disabled = false;
    });

    fixarBtn.addEventListener('click', () => {
        if (!selectedOption) return;

        const id = selectedOption.value;
        const nome = selectedOption.dataset.nome;
        const preco = parseFloat(selectedOption.dataset.preco);

        if (!fixedItems.has(id)) {
            fixedItems.set(id, {
                nome,
                preco,
                quantidade: 1
            });
            updateFixedDisplay();
        }

        fixarBtn.disabled = true;
        selectedOption = null;
        select.selectedIndex = -1;
    });

    function updateFixedDisplay() {
        fixedDisplay.innerHTML = '';

        if (fixedItems.size === 0) {
            fixedDisplay.textContent = 'Nenhum item fixado';
            totalDisplay.textContent = '0.00';
            return;
        }

        fixedItems.forEach((item, id) => {
            const container = document.createElement('span');
            container.className = 'fixed-item';

            const textNode = document.createTextNode(`${item.nome}`);


            const input = document.createElement('input');
            input.type = 'number';
            input.min = 1;
            input.value = item.quantidade;
            input.addEventListener('input', () => {
                const novaQuantidade = parseInt(input.value) || 1;
                item.quantidade = novaQuantidade;
                calcularTotal();
            });

            const btn = document.createElement('button');
            btn.textContent = '×';
            btn.className = 'remove-btn';
            btn.onclick = () => {
                fixedItems.delete(id);
                updateFixedDisplay();
            };

            container.appendChild(textNode);
            container.appendChild(input);
            container.appendChild(btn);
            fixedDisplay.appendChild(container);

        });

        calcularTotal();
    }

    function calcularTotal() {
        let total = 0;
        fixedItems.forEach(item => {
            total += item.preco * item.quantidade;
        });
        totalDisplay.textContent = total.toFixed(2);
        document.getElementById('totalTotal').value = total.toFixed(2);
    }

    const finalizarVendaBtn = document.getElementById('finalizarVendaBtn');
    const form = document.querySelector('form');

    finalizarVendaBtn.addEventListener('click', (event) => {
        event.preventDefault();

        if (fixedItems.size === 0) {
            alert('Selecione ao menos um produto antes de finalizar a venda.');
            return;
        }

        // Sincroniza os valores dos inputs visuais com os dados antes de enviar
        const spans = document.querySelectorAll('.fixed-item');
        spans.forEach(span => {
            const nomeProduto = span.querySelector('input[type="number"]').previousSibling.textContent.trim();
            const quantidadeInput = span.querySelector('input[type="number"]');
            const novaQuantidade = parseInt(quantidadeInput.value) || 1;

            // Atualiza o objeto fixedItems com a nova quantidade
            fixedItems.forEach((item, id) => {
                if (item.nome === nomeProduto) {
                    item.quantidade = novaQuantidade;
                }
            });
        });

        // Remove inputs antigos
        document.querySelectorAll('.input-produto-dinamico').forEach(input => input.remove());

        // Adiciona os produtos fixados como inputs ocultos
        fixedItems.forEach((item, id) => {
            // Nome
            const inputNome = document.createElement('input');
            inputNome.type = 'hidden';
            inputNome.name = 'produtos[]';
            inputNome.value = item.nome;
            inputNome.classList.add('input-produto-dinamico');
            form.appendChild(inputNome);

            // Quantidade
            const inputQuantidade = document.createElement('input');
            inputQuantidade.type = 'hidden';
            inputQuantidade.name = 'quantidade[]';
            inputQuantidade.value = item.quantidade;
            inputQuantidade.classList.add('input-produto-dinamico');
            form.appendChild(inputQuantidade);

            // Preço
            const inputPreco = document.createElement('input');
            inputPreco.type = 'hidden';
            inputPreco.name = 'precos[]';
            inputPreco.value = item.preco;
            inputPreco.classList.add('input-produto-dinamico');
            form.appendChild(inputPreco);
        });

        form.submit(); // Agora sim, envia de forma controlada
    });


    const removerTodosBtn = document.getElementById('removerTodosBtn');

    removerTodosBtn.addEventListener('click', () => {
        if (fixedItems.size === 0) {
            alert('Nenhum produto para remover.');
            return;
        }

        if (confirm('Deseja remover todos os produtos fixados?')) {
            fixedItems.clear();
            updateFixedDisplay();
        }
    });


    document.addEventListener('DOMContentLoaded', function() {
        const idCaixa = document.getElementById('id_caixa');
        const form = document.querySelector('form');
        const aviso = document.getElementById('avisoSemCaixa');

        if (!idCaixa || !idCaixa.value.trim()) {
            form.style.display = 'none'; // Oculta o formulário
            aviso.style.display = 'block'; // Exibe o alerta
        }
    });

    document.addEventListener("DOMContentLoaded", function() {
        const formaPagamentoSelect = document.getElementById("forma_pagamento");
        const finalizarBtn = document.getElementById("finalizarVendaBtn");

        function verificarFormaPagamento() {
            if (formaPagamentoSelect.value === "") {
                finalizarBtn.disabled = true;
            } else {
                finalizarBtn.disabled = false;
            }
        }

        // Verifica ao carregar a página
        verificarFormaPagamento();

        // Verifica toda vez que o usuário mudar a forma de pagamento
        formaPagamentoSelect.addEventListener("change", verificarFormaPagamento);
    });
</script>


<style>
    h2 {
        margin-top: 20px;
    }

    .fixed-items {
        margin-bottom: 10px;
        padding: 10px;
        background-color: #c8e6c9;
        border: 1px solid rgb(146, 100, 231);
        border-radius: 5px;
    }

    .fixed-item {
        background-color: rgb(146, 100, 231);
        ;
        color: white;
        padding: 5px 10px;
        margin: 5px 5px 0 0;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        position: relative;
        flex-wrap: wrap;
    }

    .fixed-item input {
        margin-left: 10px;
        width: 50px;
        border-radius: 5px;
        border: none;
        padding: 3px;
    }

    .remove-btn {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #f44336;
        border: none;
        color: white;
        border-radius: 50%;
        width: 16px;
        height: 16px;
        font-size: 12px;
        cursor: pointer;
        line-height: 14px;
        padding: 0;
    }

    .total {
        margin-top: 20px;
        font-size: 1.2em;
        font-weight: bold;
    }

    select {
        width: 100%;
        padding: 10px;
        font-size: 16px;
    }

    #fixarBtn {
        margin-top: 10px;
        padding: 10px 15px;
        font-size: 16px;
        background-color: rgb(146, 100, 231);
        ;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
    }

    #fixarBtn:disabled {
        background-color: #ccc;
        cursor: not-allowed;
    }

    @media (max-width: 600px) {
        .fixed-item {
            flex-direction: column;
            align-items: flex-start;
        }

        .fixed-item input {
            margin-top: 5px;
            margin-left: 0;
        }

        #fixarBtn {
            width: 100%;
        }
    }
</style>
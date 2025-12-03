<?php
session_start();

require './assets/php/conexao.php';
/* ===========================================
   1. PEGAR EMPRESA E PRODUTO DA URL
   =========================================== */
$empresaID   = $_GET['empresa'] ?? null;

if (!$empresaID) {
    die('Empresa não informada.');
}


/* ===========================================
   2. BUSCAR DADOS DA EMPRESA (NOME + LOGO)
   =========================================== */
$nomeEmpresa   = 'Açaidinhos';
$imagemEmpresa = './assets/img/favicon/logo.png';

try {
    $sql = "SELECT nome_empresa, imagem 
            FROM sobre_empresa 
            WHERE id_selecionado = :id 
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $empresaID);
    $stmt->execute();
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($empresa) {
        if (!empty($empresa['nome_empresa'])) {
            $nomeEmpresa = $empresa['nome_empresa'];
        }
        if (!empty($empresa['imagem'])) {
            // caminho da logo da empresa
            $imagemEmpresa = './assets/img/empresa/' . $empresa['imagem'];
        }
    }
} catch (PDOException $e) {
    // Se der erro, mantém padrão
}


// Calcula total
$total_pedido = 0.0;
if (!empty($_SESSION['carrinho']) && is_array($_SESSION['carrinho'])) {
    foreach ($_SESSION['carrinho'] as $it) {
        $total_pedido += isset($it['preco']) ? (float)$it['preco'] : 0;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Açaidinhos - Carrinho</title>

    <link rel="stylesheet" href="./assets/css/cardapio/animate.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/cardapio/bootstrap.min.css" />
    <link rel="stylesheet" href="./assets/css/cardapio/main.css" />

    <style>
        /* ====== BOTTOM SHEET DA FORMA DE PAGAMENTO ====== */
        #modalPagamento {
            padding: 0 !important;
        }

        #modalPagamento .modal-dialog {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            margin: 0;
            width: 100%;
            max-width: 100%;
            display: block;
            transform: translateY(100%);
            transition: transform 0.3s ease-out;
            pointer-events: auto;
        }

        /* Quando a modal abre, ela sobe de baixo pra cima */
        #modalPagamento.show .modal-dialog {
            transform: translateY(0);
        }

        #modalPagamento .modal-content {
            border-radius: 16px 16px 0 0;
            border: none;
            box-shadow: 0 -5px 25px rgba(0, 0, 0, 0.25);
        }

        #modalPagamento .modal-header {
            border-bottom: none;
            padding-top: 0.75rem;
            padding-bottom: 0.25rem;
            text-align: center;
        }

        #modalPagamento .modal-header .modal-title {
            width: 100%;
            text-align: center;
            font-weight: 600;
            font-size: 1rem;
        }

        #modalPagamento .btn-close {
            position: absolute;
            right: 1rem;
            top: 0.75rem;
        }

        #modalPagamento .modal-body {
            max-height: 60vh;
            overflow-y: auto;
            padding-bottom: 1rem;
        }

        #modalPagamento .container-check {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 0.75rem 1rem;
        }

        #modalPagamento .container-check + .container-check {
            margin-top: 0.5rem;
        }

        #modalPagamento .modal-footer {
            border-top: none;
            padding-top: 0.5rem;
            padding-bottom: 1rem;
        }

        @media (min-width: 768px) {
            #modalPagamento .modal-dialog {
                max-width: 420px;
                left: 50%;
                right: auto;
                transform: translate(-50%, 100%);
            }

            #modalPagamento.show .modal-dialog {
                transform: translate(-50%, 0);
            }
        }
    </style>

</head>
<body>

<div class="bg-top pedido"></div>

<header class="width-fix mt-5">
    <div class="card">
        <div class="d-flex align-items-center">
            <a href="./cardapio.php?empresa=<?= urlencode($empresaID) ?>" class="container-voltar">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="infos text-center">
                <h1 class="mb-0"><b>Seu carrinho</b></h1>
            </div>
        </div>
    </div>
</header>

<section class="carrinho width-fix mt-4">

    <div class="card card-address mb-3">
        <div class="img-icon-details">
            <i class="fas fa-cart-plus"></i>
        </div>
        <div class="infos">
            <?php if (!empty($_SESSION['carrinho'])): ?>
                <p class="name mb-0"><b>Itens no seu carrinho</b></p>
                <span class="text mb-0">Finalize seu pedido</span>
            <?php else: ?>
                <p class="name mb-0"><b>Seu carrinho está vazio</b></p>
                <span class="text mb-0">Adicione itens ao carrinho</span>
            <?php endif; ?>
        </div>
        <div class="icon-edit">
            <i class="fas fa-store"></i>
        </div>
    </div>

    <?php if (!empty($_SESSION['carrinho']) && is_array($_SESSION['carrinho'])): ?>
        <?php foreach ($_SESSION['carrinho'] as $idx => $item): ?>
            <?php
            $nome       = $item['nome']         ?? '';
            $preco      = isset($item['preco']) ? (float)$item['preco'] : 0;
            $quant      = isset($item['quant']) ? (int)$item['quant'] : 1;
            $obs        = trim($item['observacao'] ?? '');
            $opcSimples = is_array($item['opc_simples'] ?? null) ? $item['opc_simples'] : [];
            $opcSelecao = is_array($item['opc_selecao'] ?? null) ? $item['opc_selecao'] : [];
            ?>
            <div class="card mb-2 pr-0">
                <div class="container-detalhes">
                    <div class="detalhes-produto">
                        <div class="infos-produto">
                            <p class="name mb-0">
                                <b><?= $quant ?>x <?= htmlspecialchars($nome) ?></b>
                            </p>
                            <p class="price mb-0">
                                <b>R$ <?= number_format($preco, 2, ',', '.') ?></b>
                            </p>
                        </div>

                        <!-- OPCIONAIS SIMPLES -->
                        <?php if (!empty($opcSimples)): ?>
                            <?php foreach ($opcSimples as $op): ?>
                                <?php
                                $opNome  = $op['nome']  ?? '';
                                $opPreco = isset($op['preco']) ? (float)$op['preco'] : 0;
                                ?>
                                <div class="infos-produto">
                                    <p class="name-opcional mb-0">
                                        + <?= htmlspecialchars($opNome) ?>
                                    </p>
                                    <p class="price-opcional mb-0">
                                        + R$ <?= number_format($opPreco, 2, ',', '.') ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <!-- OPCIONAIS DAS SELEÇÕES -->
                        <?php if (!empty($opcSelecao)): ?>
                            <?php foreach ($opcSelecao as $op): ?>
                                <?php
                                $opNome  = $op['nome']  ?? '';
                                $opPreco = isset($op['preco']) ? (float)$op['preco'] : 0;
                                ?>
                                <div class="infos-produto">
                                    <p class="name-opcional mb-0">
                                        + <?= htmlspecialchars($opNome) ?>
                                    </p>
                                    <p class="price-opcional mb-0">
                                        + R$ <?= number_format($opPreco, 2, ',', '.') ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <!-- OBSERVAÇÃO -->
                        <?php if ($obs !== ''): ?>
                            <div class="infos-produto">
                                <p class="obs-opcional mb-0">
                                    Observação: <?= htmlspecialchars($obs) ?>
                                </p>
                            </div>
                        <?php endif; ?>

                    </div>

                    <!-- AÇÕES DO ITEM (remover) -->
                    <form action="remove_from_cart.php?empresa=<?= urlencode($empresaID) ?>" method="post" style="margin-top:5px;">
                        <input type="hidden" name="index" value="<?= $idx ?>">
                        <div class="detalhes-produto-edit">
                            <button type="submit" class="btn btn-link text-danger p-0" title="Excluir">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="card mb-2 pr-0">
            <div class="container-detalhes">
                <div class="detalhes-produto">
                    <div class="infos-produto">
                        <p class="name mb-0"><b>Nenhum item no carrinho.</b></p>
                        <p class="price mb-0">Adicione itens para finalizar o pedido.</p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

</section>

<section class="opcionais width-fix mt-5 pb-5">

    <!-- Tipo de entrega (mantido simples) -->
    <div class="container-group mb-5">
        <span class="badge">Obrigatório</span>
        <p class="title-categoria mb-0"><b>Escolha uma opção</b></p>
        <span class="sub-title-categoria">Como quer receber o pedido?</span>

        <div class="card card-opcionais mt-2">
            <div class="infos-produto-opcional">
                <p class="name mb-0"><b>Entrega (60-90min)</b></p>
            </div>
            <div class="checks">
                <label class="container-check">
                    <input type="checkbox" />
                    <span class="checkmark"></span>
                </label>
            </div>
        </div>

        <div class="card card-opcionais mt-2">
            <div class="infos-produto-opcional">
                <p class="name mb-0"><b>Retirar no estabelecimento</b></p>
            </div>
            <div class="checks">
                <label class="container-check">
                    <input type="checkbox" />
                    <span class="checkmark"></span>
                </label>
            </div>
        </div>
    </div>

    <!-- ENDEREÇO (com modal) -->
    <div class="container-group mb-5">
        <span class="badge">Obrigatório</span>

        <p class="title-categoria mb-0"><b>Qual o seu endereço?</b></p>
        <span class="sub-title-categoria">Informe o endereço da entrega</span>

        <!-- Card quando nenhum endereço -->
        <div class="card card-select mt-2" id="card-address-empty">
            <div class="infos-produto-opcional">
                <p class="mb-0 color-primary">
                    <i class="fas fa-plus-circle"></i>&nbsp; Nenhum endereço selecionado
                </p>
            </div>
        </div>

        <!-- Card com endereço preenchido -->
        <div class="card card-address mt-2 d-none" id="card-address-filled">
            <div class="img-icon-details">
                <i class="fas fa-map-marked-alt"></i>
            </div>
            <div class="infos">
                <p class="name mb-0"><b id="resumo_endereco_linha1"></b></p>
                <span class="text mb-0" id="resumo_endereco_linha2"></span>
            </div>
            <div class="icon-edit">
                <i class="fas fa-pencil-alt" id="btn-edit-endereco"></i>
            </div>
        </div>

        <input type="hidden" id="endereco_texto" name="endereco_texto" />
    </div>

    <!-- Nome -->
    <div class="container-group mb-5">
        <span class="badge">Obrigatório</span>
        <p class="title-categoria mb-0"><b>Nome e Sobrenome</b></p>
        <span class="sub-title-categoria">Como vamos te chamar?</span>
        <input type="text" class="form-control mt-2" id="cliente_nome" name="cliente_nome"
               placeholder="* Informe o nome e sobrenome">
    </div>

    <!-- Telefone -->
    <div class="container-group mb-5">
        <span class="badge">Obrigatório</span>
        <p class="title-categoria mb-0"><b>Número do seu celular</b></p>
        <span class="sub-title-categoria">Para contato sobre o pedido</span>
        <input type="text" class="form-control mt-2" id="cliente_telefone" name="cliente_telefone"
               placeholder="(00) 00000-0000">
    </div>

    <!-- Pagamento (com modal) -->
    <div class="container-group mb-5">
        <span class="badge">Obrigatório</span>
        <p class="title-categoria mb-0"><b>Como você prefere pagar?</b></p>
        <span class="sub-title-categoria">Pagamento na entrega</span>

        <!-- Card quando nenhuma forma selecionada -->
        <div class="card card-select mt-2" id="card-pagamento-empty">
            <div class="infos-produto-opcional">
                <p class="mb-0 color-primary">
                    <i class="fas fa-plus-circle"></i>&nbsp; Nenhuma forma selecionada
                </p>
            </div>
        </div>

        <!-- Card com forma selecionada -->
        <div class="card card-address mt-2 d-none" id="card-pagamento-filled">
            <div class="img-icon-details">
                <i class="fas fa-credit-card"></i>
            </div>
            <div class="infos">
                <p class="name mb-0"><b id="resumo_pagamento_linha1"></b></p>
                <span class="text mb-0" id="resumo_pagamento_linha2"></span>
            </div>
            <div class="icon-edit">
                <i class="fas fa-pencil-alt" id="btn-edit-pagamento"></i>
            </div>
        </div>

        <input type="hidden" id="pagamento_texto" name="pagamento_texto" />
    </div>

</section>

<?php $valor_total_formatado = number_format($total_pedido, 2, ',', '.'); ?>
<button type="button" id="btn-finalizar-pedido"
        class="btn btn-yellow btn-full"
        <?= empty($_SESSION['carrinho']) ? 'disabled' : '' ?>>
    Fazer pedido <span id="valor_total_pedido">R$ <?= $valor_total_formatado ?></span>
</button>

<!-- MODAL ENDEREÇO -->
<div class="modal fade" id="modalEndereco" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Endereço de entrega</h5>
                <button type="button" class="btn-close" id="btn-close-endereco" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label mb-1">Rua / Avenida</label>
                    <input type="text" class="form-control" id="endereco_rua" placeholder="Ex: Rua Projetada A" />
                </div>
                <div class="row">
                    <div class="col-6 mb-2">
                        <label class="form-label mb-1">Número</label>
                        <input type="text" class="form-control" id="endereco_numero" placeholder="Ex: 123" />
                    </div>
                    <div class="col-6 mb-2">
                        <label class="form-label mb-1">Bairro</label>
                        <input type="text" class="form-control" id="endereco_bairro" placeholder="Ex: Centro" />
                    </div>
                </div>
                <div class="row">
                    <div class="col-7 mb-2">
                        <label class="form-label mb-1">Cidade</label>
                        <input type="text" class="form-control" id="endereco_cidade" placeholder="Ex: Coari-AM" />
                    </div>
                    <div class="col-5 mb-2">
                        <label class="form-label mb-1">CEP</label>
                        <input type="text" class="form-control" id="endereco_cep" placeholder="XXXXX-XXX" />
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label mb-1">Complemento</label>
                    <input type="text" class="form-control" id="endereco_complemento"
                           placeholder="Casa, apto, ponto de referência..." />
                </div>
                <div class="mb-0">
                    <label class="form-label mb-1">Referência</label>
                    <input type="text" class="form-control" id="endereco_referencia"
                           placeholder="Ex: Próximo à praça, escola..." />
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" id="btn-cancelar-endereco">Cancelar</button>
                <button type="button" class="btn btn-yellow btn-sm" id="btn-salvar-endereco">Salvar endereço</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PAGAMENTO (SEM modal-dialog-centered) -->
<div class="modal fade" id="modalPagamento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Forma de pagamento</h5>
                <button type="button" class="btn-close" id="btn-close-pagamento" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="container-check w-100">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Dinheiro</span>
                            <input type="radio" name="forma_pagamento" value="Dinheiro" id="pag_dinheiro" />
                        </div>
                        <span class="checkmark"></span>
                    </label>
                </div>
                <div class="mb-2" id="grupo_troco" style="display:none;">
                    <label class="form-label mb-1">Troco para quanto?</label>
                    <input type="text" class="form-control" id="pag_troco" placeholder="Ex: 50,00" />
                </div>
                <div class="mb-2">
                    <label class="container-check w-100">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Cartão (crédito/débito)</span>
                            <input type="radio" name="forma_pagamento" value="Cartão" id="pag_cartao" />
                        </div>
                        <span class="checkmark"></span>
                    </label>
                </div>
                <div class="mb-0">
                    <label class="container-check w-100">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Pix</span>
                            <input type="radio" name="forma_pagamento" value="Pix" id="pag_pix" />
                        </div>
                        <span class="checkmark"></span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" id="btn-cancelar-pagamento">Cancelar</button>
                <button type="button" class="btn btn-yellow btn-sm" id="btn-salvar-pagamento">Salvar forma de pagamento</button>
            </div>
        </div>
    </div>
</div>

<section class="menu-bottom disabled hidden" id="menu-bottom-closed">
    <p class="mb-0"><b>Loja fechada no momento.</b></p>
</section>

<script type="text/javascript" src="./js/bootstrap.bundle.min.js"></script>

<script type="text/javascript">
const carrinhoPHP   = <?php echo json_encode($_SESSION['carrinho'] ?? []); ?>;
const totalPedidoPHP = <?php echo json_encode($total_pedido); ?>;

document.addEventListener('DOMContentLoaded', function () {

    // --------- ENDEREÇO ----------
    const cardAddressEmpty   = document.getElementById('card-address-empty');
    const cardAddressFilled  = document.getElementById('card-address-filled');
    const resumoEnderecoL1   = document.getElementById('resumo_endereco_linha1');
    const resumoEnderecoL2   = document.getElementById('resumo_endereco_linha2');
    const enderecoTextoInput = document.getElementById('endereco_texto');
    const modalEnderecoEl    = document.getElementById('modalEndereco');
    const btnFinalizar       = document.getElementById('btn-finalizar-pedido');

    let modalEndereco        = null;
    if (typeof bootstrap !== 'undefined' && modalEnderecoEl) {
        modalEndereco = new bootstrap.Modal(modalEnderecoEl);
    }

    function abrirModalEndereco() {
        if (modalEndereco) modalEndereco.show();
        else if (modalEnderecoEl) modalEnderecoEl.style.display = 'block';
    }
    function fecharModalEndereco() {
        if (modalEndereco) modalEndereco.hide();
        else if (modalEnderecoEl) modalEnderecoEl.style.display = 'none';
    }

    if (cardAddressEmpty) {
        cardAddressEmpty.style.cursor = 'pointer';
        cardAddressEmpty.addEventListener('click', abrirModalEndereco);
    }
    const btnEditEndereco = document.getElementById('btn-edit-endereco');
    if (btnEditEndereco) {
        btnEditEndereco.style.cursor = 'pointer';
        btnEditEndereco.addEventListener('click', abrirModalEndereco);
    }

    const btnSalvarEndereco   = document.getElementById('btn-salvar-endereco');
    const btnCancelarEndereco = document.getElementById('btn-cancelar-endereco');
    const btnCloseEndereco    = document.getElementById('btn-close-endereco');

    function salvarEndereco() {
        const rua   = (document.getElementById('endereco_rua') || {}).value || '';
        const num   = (document.getElementById('endereco_numero') || {}).value || '';
        const bairro= (document.getElementById('endereco_bairro') || {}).value || '';
        const cid   = (document.getElementById('endereco_cidade') || {}).value || '';
        const cep   = (document.getElementById('endereco_cep') || {}).value || '';
        const compl = (document.getElementById('endereco_complemento') || {}).value || '';
        const ref   = (document.getElementById('endereco_referencia') || {}).value || '';

        if (!rua.trim() || !num.trim() || !bairro.trim() || !cid.trim()) {
            alert('Preencha rua, número, bairro e cidade.');
            return;
        }

        const linha1 = rua + ', ' + num + ' - ' + bairro;
        let linha2   = cid;
        if (cep.trim()) {
            linha2 += ' - ' + cep;
        }

        let textoEndereco = linha1 + ' - ' + linha2;
        if (compl.trim()) textoEndereco += ' | Compl.: ' + compl.trim();
        if (ref.trim())   textoEndereco += ' | Ref.: ' + ref.trim();

        if (resumoEnderecoL1) resumoEnderecoL1.textContent = linha1;
        if (resumoEnderecoL2) resumoEnderecoL2.textContent = linha2;
        if (enderecoTextoInput) enderecoTextoInput.value   = textoEndereco;

        if (cardAddressEmpty) cardAddressEmpty.classList.add('d-none');
        if (cardAddressFilled) cardAddressFilled.classList.remove('d-none');

        fecharModalEndereco();
    }

    if (btnSalvarEndereco)   btnSalvarEndereco.addEventListener('click', salvarEndereco);
    if (btnCancelarEndereco) btnCancelarEndereco.addEventListener('click', fecharModalEndereco);
    if (btnCloseEndereco)    btnCloseEndereco.addEventListener('click', fecharModalEndereco);

    // --------- PAGAMENTO ----------
    const cardPagamentoEmpty   = document.getElementById('card-pagamento-empty');
    const cardPagamentoFilled  = document.getElementById('card-pagamento-filled');
    const resumoPagamentoL1    = document.getElementById('resumo_pagamento_linha1');
    const resumoPagamentoL2    = document.getElementById('resumo_pagamento_linha2');
    const pagamentoTextoInput  = document.getElementById('pagamento_texto');

    const modalPagamentoEl = document.getElementById('modalPagamento');
    let modalPagamento     = null;
    if (typeof bootstrap !== 'undefined' && modalPagamentoEl) {
        modalPagamento = new bootstrap.Modal(modalPagamentoEl);
    }

    function abrirModalPagamento() {
        // Ao abrir a modal de pagamento, escondemos o botão "Fazer pedido"
        if (btnFinalizar) btnFinalizar.classList.add('d-none');

        if (modalPagamento) modalPagamento.show();
        else if (modalPagamentoEl) modalPagamentoEl.style.display = 'block';
    }
    function fecharModalPagamento() {
        // Ao fechar a modal, exibimos novamente o botão "Fazer pedido"
        if (btnFinalizar) btnFinalizar.classList.remove('d-none');

        if (modalPagamento) modalPagamento.hide();
        else if (modalPagamentoEl) modalPagamentoEl.style.display = 'none';
    }

    if (cardPagamentoEmpty) {
        cardPagamentoEmpty.style.cursor = 'pointer';
        cardPagamentoEmpty.addEventListener('click', abrirModalPagamento);
    }
    const btnEditPagamento = document.getElementById('btn-edit-pagamento');
    if (btnEditPagamento) {
        btnEditPagamento.style.cursor = 'pointer';
        btnEditPagamento.addEventListener('click', abrirModalPagamento);
    }

    const btnSalvarPagamento   = document.getElementById('btn-salvar-pagamento');
    const btnCancelarPagamento = document.getElementById('btn-cancelar-pagamento');
    const btnClosePagamento    = document.getElementById('btn-close-pagamento');

    const inputDinheiro = document.getElementById('pag_dinheiro');
    const inputCartao   = document.getElementById('pag_cartao');
    const inputPix      = document.getElementById('pag_pix');
    const grupoTroco    = document.getElementById('grupo_troco');
    const inputTroco    = document.getElementById('pag_troco');

    function atualizarVisibilidadeTroco() {
        if (inputDinheiro && inputDinheiro.checked) {
            if (grupoTroco) grupoTroco.style.display = 'block';
        } else {
            if (grupoTroco) grupoTroco.style.display = 'none';
            if (inputTroco) inputTroco.value = '';
        }
    }

    if (inputDinheiro) inputDinheiro.addEventListener('change', atualizarVisibilidadeTroco);
    if (inputCartao)   inputCartao.addEventListener('change', atualizarVisibilidadeTroco);
    if (inputPix)      inputPix.addEventListener('change', atualizarVisibilidadeTroco);

    function salvarPagamento() {
        let metodo = '';
        let detalhe = '';

        if (inputDinheiro && inputDinheiro.checked) {
            metodo = 'Dinheiro';
            if (inputTroco && inputTroco.value.trim()) {
                detalhe = 'Troco para: R$ ' + inputTroco.value.trim();
            } else {
                detalhe = 'Levar troco, se necessário.';
            }
        } else if (inputCartao && inputCartao.checked) {
            metodo = 'Cartão (crédito/débito)';
            detalhe = 'Levar maquininha.';
        } else if (inputPix && inputPix.checked) {
            metodo = 'Pix';
            detalhe = 'Cobrar chave na entrega.';
        }

        if (!metodo) {
            alert('Selecione uma forma de pagamento.');
            return;
        }

        if (resumoPagamentoL1) resumoPagamentoL1.textContent = metodo;
        if (resumoPagamentoL2) resumoPagamentoL2.textContent = detalhe;
        if (pagamentoTextoInput) pagamentoTextoInput.value = metodo + (detalhe ? ' - ' + detalhe : '');

        if (cardPagamentoEmpty) cardPagamentoEmpty.classList.add('d-none');
        if (cardPagamentoFilled) cardPagamentoFilled.classList.remove('d-none');

        fecharModalPagamento();
    }

    if (btnSalvarPagamento)   btnSalvarPagamento.addEventListener('click', salvarPagamento);
    if (btnCancelarPagamento) btnCancelarPagamento.addEventListener('click', fecharModalPagamento);
    if (btnClosePagamento)    btnClosePagamento.addEventListener('click', fecharModalPagamento);

    // --------- FINALIZAR PEDIDO / WHATSAPP + RASCUNHO ----------
    if (btnFinalizar) {
        btnFinalizar.addEventListener('click', function () {
            if (!carrinhoPHP || !Array.isArray(carrinhoPHP) || carrinhoPHP.length === 0) {
                alert('Seu carrinho está vazio.');
                return;
            }

            const nome      = (document.getElementById('cliente_nome') || {}).value || '';
            const telefone  = (document.getElementById('cliente_telefone') || {}).value || '';
            const endereco  = (document.getElementById('endereco_texto') || {}).value || '';
            const pagamento = (document.getElementById('pagamento_texto') || {}).value || '';

            if (!nome.trim()) {
                alert('Informe o seu nome.');
                return;
            }
            if (!telefone.trim()) {
                alert('Informe o número do seu celular.');
                return;
            }
            if (!endereco.trim()) {
                alert('Selecione e salve um endereço de entrega.');
                return;
            }
            if (!pagamento.trim()) {
                alert('Selecione e salve uma forma de pagamento.');
                return;
            }

            // Forma/detalhe de pagamento para gravar no banco
            let formaPagamento   = '';
            let detalhePagamento = '';

            if (inputDinheiro && inputDinheiro.checked) {
                formaPagamento = 'Dinheiro';
                if (inputTroco && inputTroco.value.trim()) {
                    detalhePagamento = 'Troco para: R$ ' + inputTroco.value.trim();
                } else {
                    detalhePagamento = 'Levar troco, se necessário.';
                }
            } else if (inputCartao && inputCartao.checked) {
                formaPagamento = 'Cartão (crédito/débito)';
                detalhePagamento = 'Levar maquininha.';
            } else if (inputPix && inputPix.checked) {
                formaPagamento = 'Pix';
                detalhePagamento = 'Cobrar chave na entrega.';
            } else {
                // fallback: grava o texto completo se por algum motivo o rádio não estiver marcado
                formaPagamento = pagamento;
                detalhePagamento = '';
            }

            let texto = '';
            texto += 'NOVO PEDIDO - AÇAIDINHOS \n';
            texto += 'Cliente: ' + nome.trim() + '\n';
            texto += 'Telefone: ' + telefone.trim() + '\n\n';

            texto += 'ITENS DO PEDIDO:\n';

            carrinhoPHP.forEach(function (item, idx) {
                if (!item) return;

                const nomeItem  = item.nome || ('Item ' + (idx + 1));
                const quantItem = item.quant || 1;
                const precoItemNum = Number(item.preco || 0);
                const precoItemTxt = 'R$ ' + precoItemNum.toFixed(2).replace('.', ',');

                texto += '- ' + quantItem + 'x ' + nomeItem + ' - ' + precoItemTxt + '\n';

                // Opcionais simples
                if (Array.isArray(item.opc_simples) && item.opc_simples.length > 0) {
                    texto += '  Adicionais:\n';
                    item.opc_simples.forEach(function (opc) {
                        if (!opc) return;
                        const n = opc.nome || '';
                        const pNum = Number(opc.preco || 0);
                        const pTxt = 'R$ ' + pNum.toFixed(2).replace('.', ',');
                        texto += '   - ' + n + ' (+' + pTxt + ')\n';
                    });
                }

                // Opcionais de seleção
                if (Array.isArray(item.opc_selecao) && item.opc_selecao.length > 0) {
                    if (!(Array.isArray(item.opc_simples) && item.opc_simples.length > 0)) {
                        texto += '  Adicionais:\n';
                    }
                    item.opc_selecao.forEach(function (opc) {
                        if (!opc) return;
                        const n = opc.nome || '';
                        const pNum = Number(opc.preco || 0);
                        const pTxt = 'R$ ' + pNum.toFixed(2).replace('.', ',');
                        texto += '   - ' + n + ' (+' + pTxt + ')\n';
                    });
                }

                // Observação
                if (item.observacao) {
                    texto += '  Observação: ' + item.observacao + '\n';
                }

                texto += '\n';
            });

            // Total
            if (totalPedidoPHP !== null && totalPedidoPHP !== undefined) {
                const totalNum = Number(totalPedidoPHP) || 0;
                texto += 'TOTAL:\n';
                texto += 'R$ ' + totalNum.toFixed(2).replace('.', ',') + '\n\n';
            }

            // Endereço
            texto += 'ENDEREÇO DE ENTREGA:\n';
            texto += endereco + '\n\n';

            // Pagamento
            texto += 'FORMA DE PAGAMENTO:\n';
            texto += pagamento + '\n\n';

            texto += 'Enviado automaticamente pelo sistema.';

            // --------- SALVA RASCUNHO NO BANCO ANTES DO WHATSAPP ----------
            const dados = new FormData();
            dados.append('nome', nome.trim());
            dados.append('telefone', telefone.trim());
            dados.append('endereco', endereco.trim());
            dados.append('forma_pagamento', formaPagamento);
            dados.append('detalhe_pagamento', detalhePagamento);
            dados.append('total', String(totalPedidoPHP || 0));
            dados.append('itens_json', JSON.stringify(carrinhoPHP || []));

            fetch('salvar_rascunho.php', {
                method: 'POST',
                body: dados
            })
            .then(function (response) {
                // Mesmo se der erro no JSON, segue pro WhatsApp
                return response.json().catch(function () {
                    return {};
                });
            })
            .then(function (data) {
                const numeroWhatsapp = '559791434585';
                const url = 'https://wa.me/' + numeroWhatsapp + '?text=' + encodeURIComponent(texto);
                window.open(url, '_blank');
            })
            .catch(function (erro) {
                console.error('Falha ao salvar rascunho:', erro);
                const numeroWhatsapp = '559791434585';
                const url = 'https://wa.me/' + numeroWhatsapp + '?text=' + encodeURIComponent(texto);
                window.open(url, '_blank');
            });
        });
    }
});
</script>

<script type="text/javascript" src="./js/item.js"></script>

</body>
</html>

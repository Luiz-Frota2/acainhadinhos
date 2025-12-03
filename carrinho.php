<?php
session_start();

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
    <title>Carrinho - Açaidinhos</title>

    <link rel="stylesheet" href="./assets/css/cardapio/bootstrap.min.css" />
    <link rel="stylesheet" href="./assets/css/cardapio/main.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

</head>

<body>

<header class="header-top width-fix">
    <p class="title mb-1"><b>Meu carrinho</b></p>
    <p class="sub-title mb-0">Confira os itens antes de finalizar.</p>
</header>

<section class="width-fix mt-3">
    <?php if (!empty($_SESSION['carrinho']) && is_array($_SESSION['carrinho'])): ?>
        <?php foreach ($_SESSION['carrinho'] as $index => $item): ?>
            <?php
                $nome  = $item['nome']  ?? '';
                $preco = isset($item['preco']) ? (float)$item['preco'] : 0;
                $quant = isset($item['quant']) ? (int)$item['quant'] : 1;
                $obs   = $item['observacao'] ?? '';

                $opcSimples = $item['opc_simples'] ?? [];
                $opcSelecao = $item['opc_selecao'] ?? [];
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

                        <!-- OPCIONAIS DE SELEÇÃO -->
                        <?php if (!empty($opcSelecao)): ?>
                            <?php if (empty($opcSimples)): ?>
                                <div class="infos-produto">
                                    <p class="name-opcional mb-0">
                                        Adicionais:
                                    </p>
                                </div>
                            <?php endif; ?>
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
                        <?php if (!empty($obs)): ?>
                            <div class="infos-produto mt-1">
                                <p class="name-opcional mb-0">
                                    <b>Obs:</b> <?= nl2br(htmlspecialchars($obs)) ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

    <?php else: ?>
        <div class="card card-empty">
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
                    <input type="radio" name="tipo_entrega" checked />
                    <span class="checkmark"></span>
                </label>
            </div>
        </div>
    </div>

    <!-- ENDEREÇO -->
    <div class="container-group mb-5">
        <span class="badge">Obrigatório</span>
        <p class="title-categoria mb-0"><b>Endereço de entrega</b></p>
        <span class="sub-title-categoria">Clique para adicionar o endereço completo.</span>

        <!-- Card endereço vazio -->
        <div class="card card-address mt-2" id="card-address-empty">
            <div class="icon" style="background:#eee;">
                <i class="fas fa-map-marker-alt" style="color:#666;"></i>
            </div>
            <div class="infos">
                <p class="name mb-0"><b>Adicionar endereço</b></p>
                <span class="text mb-0">Clique para informar rua, número, bairro...</span>
            </div>
        </div>

        <!-- Card endereço preenchido -->
        <div class="card card-address mt-2 d-none" id="card-address-filled">
            <div class="icon" style="background:#6c2dc7;color:#fff;">
                <i class="fas fa-map-marker-alt"></i>
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
        <p class="title-categoria mb-0"><b>Celular / WhatsApp</b></p>
        <span class="sub-title-categoria">Nós podemos entrar em contato por qual número?</span>
        <input type="text" class="form-control mt-2" id="cliente_telefone" name="cliente_telefone"
               placeholder="* Informe o número com DDD">
    </div>

    <!-- FORMA DE PAGAMENTO -->
    <div class="container-group mb-5">
        <span class="badge">Obrigatório</span>
        <p class="title-categoria mb-0"><b>Forma de pagamento</b></p>
        <span class="sub-title-categoria">Clique para selecionar a forma de pagamento.</span>

        <!-- Card pagamento vazio -->
        <div class="card card-address mt-2" id="card-pagamento-empty">
            <div class="icon" style="background:#eee;">
                <i class="fas fa-wallet" style="color:#666;"></i>
            </div>
            <div class="infos">
                <p class="name mb-0"><b>Selecionar pagamento</b></p>
                <span class="text mb-0">Clique para escolher como deseja pagar.</span>
            </div>
        </div>

        <!-- Card pagamento preenchido -->
        <div class="card card-address mt-2 d-none" id="card-pagamento-filled">
            <div class="icon" style="background:#6c2dc7;color:#fff;">
                <i class="fas fa-wallet"></i>
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
        <!-- Campos extras para gravar de forma separada no banco -->
        <input type="hidden" id="pagamento_metodo" name="pagamento_metodo" />
        <input type="hidden" id="pagamento_detalhe" name="pagamento_detalhe" />
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
                    <label class="form-label mb-1">Rua</label>
                    <input type="text" class="form-control" id="end_rua" placeholder="Ex: Rua Manaus">
                </div>
                <div class="mb-2">
                    <label class="form-label mb-1">Número</label>
                    <input type="text" class="form-control" id="end_numero" placeholder="Ex: 123">
                </div>
                <div class="mb-2">
                    <label class="form-label mb-1">Bairro</label>
                    <input type="text" class="form-control" id="end_bairro" placeholder="Ex: Centro">
                </div>
                <div class="mb-2">
                    <label class="form-label mb-1">Cidade</label>
                    <input type="text" class="form-control" id="end_cidade" placeholder="Ex: Coari">
                </div>
                <div class="mb-2">
                    <label class="form-label mb-1">CEP</label>
                    <input type="text" class="form-control" id="end_cep" placeholder="Ex: 69460-000">
                </div>
                <div class="mb-2">
                    <label class="form-label mb-1">Complemento</label>
                    <input type="text" class="form-control" id="end_complemento" placeholder="Ex: Casa azul, apto...">
                </div>
                <div class="mb-2">
                    <label class="form-label mb-1">Ponto de referência</label>
                    <input type="text" class="form-control" id="end_referencia" placeholder="Ex: Próximo à escola...">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" id="btn-cancelar-endereco">Cancelar</button>
                <button class="btn btn-yellow" id="btn-salvar-endereco">Salvar endereço</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PAGAMENTO -->
<div class="modal fade" id="modalPagamento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Forma de pagamento</h5>
                <button type="button" class="btn-close" id="btn-close-pagamento" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="pagamento" id="pag_dinheiro" value="dinheiro">
                    <label class="form-check-label" for="pag_dinheiro">
                        Dinheiro
                    </label>
                </div>

                <div class="mb-2" id="grupo_troco" style="display:none;">
                    <label class="form-label mb-1">Troco para quanto?</label>
                    <input type="text" class="form-control" id="pag_troco" placeholder="Ex: R$ 50,00">
                </div>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="pagamento" id="pag_cartao" value="cartao">
                    <label class="form-check-label" for="pag_cartao">
                        Cartão (crédito/débito)
                    </label>
                </div>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="pagamento" id="pag_pix" value="pix">
                    <label class="form-check-label" for="pag_pix">
                        Pix
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" id="btn-cancelar-pagamento">Cancelar</button>
                <button class="btn btn-yellow" id="btn-salvar-pagamento">Salvar pagamento</button>
            </div>
        </div>
    </div>
</div>

<section class="loja-status-bottom width-fix">
    <p class="mb-0"><b>Loja aberta agora!</b></p>
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
        cardAddressEmpty.addEventListener('click', abrirModalEndereco);
    }
    const btnEditEndereco = document.getElementById('btn-edit-endereco');
    if (btnEditEndereco) {
        btnEditEndereco.addEventListener('click', abrirModalEndereco);
    }

    const btnSalvarEndereco   = document.getElementById('btn-salvar-endereco');
    const btnCancelarEndereco = document.getElementById('btn-cancelar-endereco');
    const btnCloseEndereco    = document.getElementById('btn-close-endereco');

    const inputRua   = document.getElementById('end_rua');
    const inputNum   = document.getElementById('end_numero');
    const inputBairro= document.getElementById('end_bairro');
    const inputCidade= document.getElementById('end_cidade');
    const inputCEP   = document.getElementById('end_cep');
    const inputCompl = document.getElementById('end_complemento');
    const inputRef   = document.getElementById('end_referencia');

    function salvarEndereco() {
        const rua   = (inputRua   && inputRua.value)   ? inputRua.value   : '';
        const num   = (inputNum   && inputNum.value)   ? inputNum.value   : '';
        const bairro= (inputBairro&& inputBairro.value)? inputBairro.value: '';
        const cid   = (inputCidade&& inputCidade.value)? inputCidade.value: '';
        const cep   = (inputCEP   && inputCEP.value)   ? inputCEP.value   : '';
        const compl = (inputCompl && inputCompl.value) ? inputCompl.value : '';
        const ref   = (inputRef   && inputRef.value)   ? inputRef.value   : '';

        if (!rua.trim() || !num.trim() || !bairro.trim() || !cid.trim()) {
            alert('Preencha, pelo menos, Rua, Número, Bairro e Cidade.');
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
    const pagamentoMetodoInput = document.getElementById('pagamento_metodo');
    const pagamentoDetalheInput = document.getElementById('pagamento_detalhe');

    const modalPagamentoEl = document.getElementById('modalPagamento');
    let modalPagamento     = null;
    if (typeof bootstrap !== 'undefined' && modalPagamentoEl) {
        modalPagamento = new bootstrap.Modal(modalPagamentoEl);
    }

    function abrirModalPagamento() {
        if (modalPagamento) modalPagamento.show();
        else if (modalPagamentoEl) modalPagamentoEl.style.display = 'block';
    }
    function fecharModalPagamento() {
        if (modalPagamento) modalPagamento.hide();
        else if (modalPagamentoEl) modalPagamentoEl.style.display = 'none';
    }

    if (cardPagamentoEmpty) {
        cardPagamentoEmpty.style.cursor = 'pointer';
        cardPagamentoEmpty.addEventListener('click', abrirModalPagamento);
    }
    const btnEditPagamento = document.getElementById('btn-edit-pagamento')
    if (btnEditPagamento) {
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
        if (pagamentoMetodoInput)  pagamentoMetodoInput.value  = metodo;
        if (pagamentoDetalheInput) pagamentoDetalheInput.value = detalhe;

        if (cardPagamentoEmpty) cardPagamentoEmpty.classList.add('d-none');
        if (cardPagamentoFilled) cardPagamentoFilled.classList.remove('d-none');

        fecharModalPagamento();
    }

    if (btnSalvarPagamento)   btnSalvarPagamento.addEventListener('click', salvarPagamento);
    if (btnCancelarPagamento) btnCancelarPagamento.addEventListener('click', fecharModalPagamento);
    if (btnClosePagamento)    btnClosePagamento.addEventListener('click', fecharModalPagamento);

    // --------- FINALIZAR PEDIDO / WHATSAPP ----------
    const btnFinalizar = document.getElementById('btn-finalizar-pedido');
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
            const formaPagamento   = (document.getElementById('pagamento_metodo') || {}).value || '';
            const detalhePagamento = (document.getElementById('pagamento_detalhe') || {}).value || '';

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

            let texto = '';
            texto += 'NOVO PEDIDO - AÇAIDINHOS \\n';
            texto += 'Cliente: ' + nome.trim() + '\\n';
            texto += 'Telefone: ' + telefone.trim() + '\\n\\n';

            texto += 'ITENS DO PEDIDO:\\n';

            carrinhoPHP.forEach(function (item, idx) {
                if (!item) return;

                const nomeItem  = item.nome || ('Item ' + (idx + 1));
                const quantItem = item.quant || 1;
                const precoItemNum = Number(item.preco || 0);
                const precoItemTxt = 'R$ ' + precoItemNum.toFixed(2).replace('.', ',');

                texto += '- ' + quantItem + 'x ' + nomeItem + ' - ' + precoItemTxt + '\\n';

                // Opcionais simples
                if (Array.isArray(item.opc_simples) && item.opc_simples.length > 0) {
                    texto += '  Adicionais:\\n';
                    item.opc_simples.forEach(function (opc) {
                        if (!opc) return;
                        const n = opc.nome || '';
                        const pNum = Number(opc.preco || 0);
                        const pTxt = 'R$ ' + pNum.toFixed(2).replace('.', ',');
                        texto += '   - ' + n + ' (+' + pTxt + ')\\n';
                    });
                }

                // Opcionais de seleção
                if (Array.isArray(item.opc_selecao) && item.opc_selecao.length > 0) {
                    if (!(Array.isArray(item.opc_simples) && item.opc_simples.length > 0)) {
                        texto += '  Adicionais:\\n';
                    }
                    item.opc_selecao.forEach(function (opc) {
                        if (!opc) return;
                        const n = opc.nome || '';
                        const pNum = Number(opc.preco || 0);
                        const pTxt = 'R$ ' + pNum.toFixed(2).replace('.', ',');
                        texto += '   - ' + n + ' (+' + pTxt + ')\\n';
                    });
                }

                // Observação
                if (item.observacao) {
                    texto += '  Observação: ' + item.observacao + '\\n';
                }

                texto += '\\n';
            });

            // Total
            if (totalPedidoPHP !== null && totalPedidoPHP !== undefined) {
                const totalNum = Number(totalPedidoPHP) || 0;
                texto += 'TOTAL:\\n';
                texto += 'R$ ' + totalNum.toFixed(2).replace('.', ',') + '\\n\\n';
            }

            // Endereço
            texto += 'ENDEREÇO DE ENTREGA:\\n';
            texto += endereco + '\\n\\n';

            // Pagamento
            texto += 'FORMA DE PAGAMENTO:\\n';
            texto += pagamento + '\\n\\n';

            texto += 'Enviado automaticamente pelo sistema.';

            // ----- PRIMEIRO SALVA NO BANCO (rascunho/rascunho_itens) -----
            const dados = new FormData();
            dados.append('nome', nome.trim());
            dados.append('telefone', telefone.trim());
            dados.append('endereco', endereco.trim());
            dados.append('forma_pagamento', formaPagamento || pagamento.trim());
            dados.append('detalhe_pagamento', detalhePagamento);
            dados.append('total', String(totalPedidoPHP || 0));
            dados.append('itens_json', JSON.stringify(carrinhoPHP || []));

            fetch('salvar_rascunho.php', {
                method: 'POST',
                body: dados
            })
            .then(function (response) {
                // Mesmo se der algum erro no backend, vamos tentar seguir com o WhatsApp
                return response.json().catch(function () {
                    return {};
                });
            })
            .then(function (data) {
                const numeroWhatsapp = '559791434585'; // 55 + 97 + 981434585
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

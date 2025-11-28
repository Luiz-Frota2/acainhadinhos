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
    <title>Açaidinhos - Carrinho</title>

    <link rel="stylesheet" href="./assets/css/cardapio/animate.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/cardapio/bootstrap.min.css" />
    <link rel="stylesheet" href="./assets/css/cardapio/main.css" />
</head>
<body>

<div class="bg-top pedido"></div>

<header class="width-fix mt-5">
    <div class="card">
        <div class="d-flex align-items-center">
            <a href="./index.php" class="container-voltar">
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

                        <?php if (!empty($opcSimples)): ?>
                            <?php foreach ($opcSimples as $op): ?>
                                <div class="infos-produto">
                                    <p class="name-opcional mb-0">+ <?= htmlspecialchars($op['nome']) ?></p>
                                    <p class="price-opcional mb-0">+ R$ <?= number_format($op['preco'], 2, ',', '.') ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if (!empty($opcSelecao)): ?>
                            <?php foreach ($opcSelecao as $op): ?>
                                <div class="infos-produto">
                                    <p class="name-opcional mb-0">+ <?= htmlspecialchars($op['nome']) ?></p>
                                    <p class="price-opcional mb-0">+ R$ <?= number_format($op['preco'], 2, ',', '.') ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if ($obs !== ''): ?>
                            <div class="infos-produto">
                                <p class="obs-opcional mb-0">
                                    Observação: <?= htmlspecialchars($obs) ?>
                                </p>
                            </div>
                        <?php endif; ?>

                    </div>

                    <form action="remove_from_cart.php" method="post" style="margin-top:5px;">
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

    <!-- Tipo de entrega -->
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

    <!-- ENDEREÇO -->
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

        <!-- Card quando preenchido -->
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

    <!-- PAGAMENTO -->
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

        <!-- Card quando preenchido -->
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
        class="btn btn-yellow btn-full">
    Fazer pedido <span id="valor_total_pedido">R$ <?= $valor_total_formatado ?></span>
</button>


<!-- =============== MODAL ENDEREÇO =============== -->

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
                           placeholder="Ex: Próximo à praça..." />
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" id="btn-cancelar-endereco">Cancelar</button>
                <button type="button" class="btn btn-yellow btn-sm" id="btn-salvar-endereco">Salvar endereço</button>
            </div>
        </div>
    </div>
</div>


<!-- =============== MODAL PAGAMENTO =============== -->

<div class="modal fade" id="modalPagamento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
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

const carrinhoPHP = <?php echo json_encode($_SESSION['carrinho'] ?? []); ?>;
const totalPedidoPHP = <?php echo json_encode($total_pedido); ?>;

document.addEventListener('DOMContentLoaded', function () {

    /* =======================================================
       MODAL ENDEREÇO
    ======================================================= */
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
        modalEndereco ? modalEndereco.show() : (modalEnderecoEl.style.display = 'block');
    }
    function fecharModalEndereco() {
        modalEndereco ? modalEndereco.hide() : (modalEnderecoEl.style.display = 'none');
    }

    if (cardAddressEmpty) {
        cardAddressEmpty.style.cursor = 'pointer';
        cardAddressEmpty.onclick = abrirModalEndereco;
    }
    const btnEditEndereco = document.getElementById('btn-edit-endereco');
    if (btnEditEndereco) {
        btnEditEndereco.style.cursor = 'pointer';
        btnEditEndereco.onclick = abrirModalEndereco;
    }

    const btnSalvarEndereco   = document.getElementById('btn-salvar-endereco');
    const btnCancelarEndereco = document.getElementById('btn-cancelar-endereco');
    const btnCloseEndereco    = document.getElementById('btn-close-endereco');

    function salvarEndereco() {
        const rua   = document.getElementById('endereco_rua').value.trim();
        const num   = document.getElementById('endereco_numero').value.trim();
        const bairro= document.getElementById('endereco_bairro').value.trim();
        const cid   = document.getElementById('endereco_cidade').value.trim();
        const cep   = document.getElementById('endereco_cep').value.trim();
        const compl = document.getElementById('endereco_complemento').value.trim();
        const ref   = document.getElementById('endereco_referencia').value.trim();

        if (!rua || !num || !bairro || !cid) {
            alert('Preencha rua, número, bairro e cidade.');
            return;
        }

        const linha1 = rua + ', ' + num + ' - ' + bairro;
        let linha2 = cid + (cep ? ' - ' + cep : '');

        let textoEndereco = linha1 + ' - ' + linha2;
        if (compl) textoEndereco += ' | Compl.: ' + compl;
        if (ref) textoEndereco += ' | Ref.: ' + ref;

        resumoEnderecoL1.textContent = linha1;
        resumoEnderecoL2.textContent = linha2;
        enderecoTextoInput.value = textoEndereco;

        cardAddressEmpty.classList.add('d-none');
        cardAddressFilled.classList.remove('d-none');

        fecharModalEndereco();
    }

    btnSalvarEndereco && btnSalvarEndereco.addEventListener('click', salvarEndereco);
    btnCancelarEndereco && btnCancelarEndereco.addEventListener('click', fecharModalEndereco);
    btnCloseEndereco && btnCloseEndereco.addEventListener('click', fecharModalEndereco);



    /* =======================================================
       MODAL PAGAMENTO
    ======================================================= */
    const cardPagamentoEmpty   = document.getElementById('card-pagamento-empty');
    const cardPagamentoFilled  = document.getElementById('card-pagamento-filled');
    const resumoPagamentoL1    = document.getElementById('resumo_pagamento_linha1');
    const resumoPagamentoL2    = document.getElementById('resumo_pagamento_linha2');
    const pagamentoTextoInput  = document.getElementById('pagamento_texto');

    const modalPagamentoEl     = document.getElementById('modalPagamento');
    let modalPagamento         = null;

    if (typeof bootstrap !== 'undefined' && modalPagamentoEl) {
        modalPagamento = new bootstrap.Modal(modalPagamentoEl);
    }

    function abrirModalPagamento() {
        modalPagamento ? modalPagamento.show() : (modalPagamentoEl.style.display = 'block');
    }
    function fecharModalPagamento() {
        modalPagamento ? modalPagamento.hide() : (modalPagamentoEl.style.display = 'none');
    }

    cardPagamentoEmpty.onclick = abrirModalPagamento;
    document.getElementById('btn-edit-pagamento').onclick = abrirModalPagamento;

    const inputDinheiro = document.getElementById('pag_dinheiro');
    const inputCartao   = document.getElementById('pag_cartao');
    const inputPix      = document.getElementById('pag_pix');
    const grupoTroco    = document.getElementById('grupo_troco');
    const inputTroco    = document.getElementById('pag_troco');

    function atualizarTroco() {
        if (inputDinheiro.checked) {
            grupoTroco.style.display = 'block';
        } else {
            grupoTroco.style.display = 'none';
            inputTroco.value = '';
        }
    }

    inputDinheiro.onclick = atualizarTroco;
    inputCartao.onclick   = atualizarTroco;
    inputPix.onclick      = atualizarTroco;

    function salvarPagamento() {
        let metodo = '';
        let detalhe = '';

        if (inputDinheiro.checked) {
            metodo = 'Dinheiro';
            detalhe = inputTroco.value.trim()
                ? ('Troco para: R$ ' + inputTroco.value.trim())
                : 'Levar troco.';
        }
        else if (inputCartao.checked) {
            metodo = 'Cartão (crédito/débito)';
            detalhe = 'Levar maquininha.';
        }
        else if (inputPix.checked) {
            metodo = 'Pix';
            detalhe = 'Cobrar chave na entrega.';
        }

        if (!metodo) {
            alert('Selecione a forma de pagamento.');
            return;
        }

        resumoPagamentoL1.textContent = metodo;
        resumoPagamentoL2.textContent = detalhe;
        pagamentoTextoInput.value = metodo + ' - ' + detalhe;

        cardPagamentoEmpty.classList.add('d-none');
        cardPagamentoFilled.classList.remove('d-none');

        fecharModalPagamento();
    }

    document.getElementById('btn-salvar-pagamento').onclick = salvarPagamento;
    document.getElementById('btn-cancelar-pagamento').onclick = fecharModalPagamento;
    document.getElementById('btn-close-pagamento').onclick = fecharModalPagamento;



    /* =======================================================
       FINALIZAR PEDIDO -> GRAVAÇÃO NO BANCO + WHATSAPP
    ======================================================= */

    const btnFinalizar = document.getElementById('btn-finalizar-pedido');

    btnFinalizar.addEventListener('click', function () {

        if (!carrinhoPHP || carrinhoPHP.length === 0) {
            alert('Seu carrinho está vazio.');
            return;
        }

        const nome      = document.getElementById('cliente_nome').value.trim();
        const telefone  = document.getElementById('cliente_telefone').value.trim();
        const endereco  = document.getElementById('endereco_texto').value.trim();
        const pagamento = document.getElementById('pagamento_texto').value.trim();

        if (!nome)      return alert('Informe seu nome.');
        if (!telefone)  return alert('Informe seu número de celular.');
        if (!endereco)  return alert('Informe seu endereço.');
        if (!pagamento) return alert('Selecione a forma de pagamento.');

        // Convertendo itens para formato do banco
        let itens = [];
        carrinhoPHP.forEach(item => {
            itens.push({
                nome: item.nome,
                quant: item.quant,
                preco_unit: item.preco,
                observacao: item.observacao,
                opcionais: [
                    ...(item.opc_simples ?? []),
                    ...(item.opc_selecao ?? [])
                ]
            });
        });

   fetch("finalizar_pedido.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({
        nome: nome,
        telefone: telefone,
        endereco: endereco,
        pagamento: pagamento,
        detalhe_pagamento: "",
        total: totalPedidoPHP,
        itens_json: JSON.stringify(itens)
    })
})
.then(resp => resp.text()) // <- ALTERADO
.then(txt => {
    console.log("RETORNO DO SERVIDOR:", txt);

    try {
        let ret = JSON.parse(txt);
        if (ret.status === "ok") {
            window.open(ret.redirect, "_blank");
            window.location.href = "pedido.php?id=" + ret.pedido_id;
        } else {
            alert("Erro: " + ret.erro);
        }
    } catch (e) {
        alert("Retorno inválido do servidor. Veja o console.");
    }
});

});
</script>

<script type="text/javascript" src="./js/item.js"></script>

</body>
</html>

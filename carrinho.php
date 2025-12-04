<?php
session_start();
require './assets/php/conexao.php';

/* ===========================================
   0. MENSAGEM (FLASH) DA SESSÃO
   =========================================== */
$flashMsg  = $_SESSION['flash_msg']  ?? null;
$flashTipo = $_SESSION['flash_tipo'] ?? 'sucesso';
unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']);

/* ===========================================
   1. PEGAR EMPRESA DA URL
   =========================================== */
$empresaID = $_GET['empresa'] ?? null;

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
            $imagemEmpresa = './assets/img/empresa/' . $empresa['imagem'];
        }
    }
} catch (PDOException $e) {
    // mantém padrão se der erro
}

/* ===========================================
   3. CALCULAR TOTAL DO CARRINHO
   =========================================== */
$total_pedido = 0.0;
if (!empty($_SESSION['carrinho']) && is_array($_SESSION['carrinho'])) {
    foreach ($_SESSION['carrinho'] as $it) {
        $total_pedido += isset($it['preco']) ? (float)$it['preco'] : 0;
    }
}

/* ===========================================
   4. CONFIGURAÇÕES ENTREGA / RETIRADA
   =========================================== */
$temEntrega        = false;
$textoEntrega      = '';
$taxaEntregaValor  = 0.0;
$modoTaxaEntrega   = 'nenhum';

$temRetirada       = false;
$textoRetirada     = '';

try {
    // ENTREGAS
    $sql = "SELECT id_entrega, entrega, tempo_min, tempo_max
            FROM entregas
            WHERE id_empresa = :emp
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':emp', $empresaID);
    $stmt->execute();
    $entCfg = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($entCfg && (int)$entCfg['entrega'] === 1) {
        $temEntrega = true;
        $idEntrega  = (int)$entCfg['id_entrega'];

        $tMin = (int)$entCfg['tempo_min'];
        $tMax = (int)$entCfg['tempo_max'];
        if ($tMin > 0 && $tMax > 0) {
            $textoEntrega = 'Entrega (' . $tMin . '-' . $tMax . 'min)';
        } else {
            $textoEntrega = 'Entrega';
        }

        // TAXA
        $sql = "SELECT sem_taxa, taxa_unica, idSelecionado
                FROM entrega_taxas
                WHERE id_entrega = :id
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $idEntrega, PDO::PARAM_INT);
        $stmt->execute();
        $taxCfg = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($taxCfg) {
            $semTaxa   = (int)$taxCfg['sem_taxa']   === 1;
            $taxaUnica = (int)$taxCfg['taxa_unica'] === 1;

            if ($semTaxa) {
                $modoTaxaEntrega  = 'sem_taxa';
                $taxaEntregaValor = 0.0;
            } elseif ($taxaUnica) {
                $modoTaxaEntrega = 'taxa_unica';

                $sql = "SELECT valor_taxa
                        FROM entrega_taxas_unica
                        WHERE id_entrega = :id
                          AND taxa_unica = 1
                        LIMIT 1";
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':id', $idEntrega, PDO::PARAM_INT);
                $stmt->execute();
                $unica = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($unica && isset($unica['valor_taxa'])) {
                    $taxaEntregaValor = (float)$unica['valor_taxa'];
                }
            }
        }
    }

    // RETIRADA
    $sql = "SELECT retirada, tempo_min, tempo_max
            FROM configuracoes_retirada
            WHERE id_empresa = :emp
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':emp', $empresaID);
    $stmt->execute();
    $retCfg = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($retCfg && (int)$retCfg['retirada'] === 1) {
        $temRetirada = true;
        $tMin = (int)$retCfg['tempo_min'];
        $tMax = (int)$retCfg['tempo_max'];
        if ($tMin > 0 && $tMax > 0) {
            $textoRetirada = 'Retirar no estabelecimento (' . $tMin . '-' . $tMax . 'min)';
        } else {
            $textoRetirada = 'Retirar no estabelecimento';
        }
    }
} catch (PDOException $e) {
    // se der erro, só não exibe
}

/* ===========================================
   5. FORMAS DE PAGAMENTO DA EMPRESA
   =========================================== */
$formasPagamento = [
    'dinheiro' => false,
    'pix'      => false,
    'debito'   => false,
    'credito'  => false,
];

try {
    $sql = "SELECT dinheiro, pix, cartaoDebito, cartaoCredito
            FROM formas_pagamento
            WHERE empresa_id = :emp
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':emp', $empresaID);
    $stmt->execute();
    $fp = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($fp) {
        $formasPagamento['dinheiro'] = !empty($fp['dinheiro']);
        $formasPagamento['pix']      = !empty($fp['pix']);
        $formasPagamento['debito']   = !empty($fp['cartaoDebito']);
        $formasPagamento['credito']  = !empty($fp['cartaoCredito']);
    }
} catch (PDOException $e) {
}

$temCartao = $formasPagamento['debito'] || $formasPagamento['credito'];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($nomeEmpresa) ?> - Carrinho</title>

    <link rel="shortcut icon" href="<?= htmlspecialchars($imagemEmpresa) ?>" type="image/x-icon">

    <link rel="stylesheet" href="./assets/css/cardapio/animate.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/cardapio/bootstrap.min.css" />
    <link rel="stylesheet" href="./assets/css/cardapio/main.css" />

    <style>
        /* TOAST - MENSAGEM COM EFEITO DESCENDO */
        .toast-msg {
            position: fixed;
            top: -120px;
            right: 20px;
            max-width: 360px;
            background: #28a745;
            color: #fff;
            padding: 14px 18px;
            border-radius: 10px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            text-align: center;
            font-size: 14px;
        }

        .toast-msg-error {
            background: #dc3545;
        }

        .toast-show {
            animation: slideDownToast 0.35s ease-out forwards;
        }

        .toast-hide {
            animation: slideUpToast 0.3s ease-in forwards;
        }

        @keyframes slideDownToast {
            from {
                top: -120px;
                opacity: 0;
            }

            to {
                top: 20px;
                opacity: 1;
            }
        }

        @keyframes slideUpToast {
            from {
                top: 20px;
                opacity: 1;
            }

            to {
                top: -120px;
                opacity: 0;
            }
        }

        @media (max-width: 768px) {
            .toast-msg {
                left: 50%;
                right: auto;
                top: 35%;
                transform: translateX(-50%);
                width: 80%;
            }

            @keyframes slideDownToast {
                from {
                    top: 0%;
                    opacity: 0;
                }

                to {
                    top: 35%;
                    opacity: 1;
                }
            }

            @keyframes slideUpToast {
                from {
                    top: 35%;
                    opacity: 1;
                }

                to {
                    top: 0%;
                    opacity: 0;
                }
            }
        }
    </style>
</head>

<body>

    <?php if (!empty($flashMsg)): ?>
        <div id="toast-msg" class="toast-msg <?= $flashTipo === 'erro' ? 'toast-msg-error' : '' ?>">
            <?= htmlspecialchars($flashMsg) ?>
        </div>
    <?php endif; ?>

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

    <!-- ================== CARRINHO ================== -->
    <section class="carrinho width-fix mt-4">

        <div class="card card-address mb-3">
            <div class="img-icon-details">
                <i class="fas fa-cart-plus"></i>
            </div>
            <div class="infos">
                <?php if (!empty($_SESSION['carrinho'])): ?>
                    <p class="name mb-0"><b>Itens no seu carrinho</b></p>
                    <span class="text mb-0">Revise e finalize o pedido.</span>
                <?php else: ?>
                    <p class="name mb-0"><b>Seu carrinho está vazio</b></p>
                    <span class="text mb-0">Adicione itens ao carrinho.</span>
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

                            <!-- OPCIONAIS DE SELEÇÃO -->
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

                        <!-- REMOVER ITEM -->
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

        <!-- TOTAL (somente itens) -->
        <div class="card mb-2 mt-3">
            <div class="detalhes-produto">
                <div class="infos-produto">
                    <p class="name-total mb-0"><b>Total dos itens</b></p>
                    <p class="price-total mb-0">
                        <b id="lblTotalCarrinho">
                            R$ <?= number_format($total_pedido, 2, ',', '.') ?>
                        </b>
                    </p>
                </div>
            </div>
        </div>

        <?php if ($temEntrega && $taxaEntregaValor > 0): ?>
            <div class="card mb-2 d-none" id="card-taxa-entrega">
                <div class="detalhes-produto">
                    <div class="infos-produto">
                        <p class="name mb-0"><b>Taxa de entrega (única)</b></p>
                        <p class="price mb-0">
                            <b id="lblTaxaEntrega">R$ <?= number_format($taxaEntregaValor, 2, ',', '.') ?></b>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </section>

    <!-- ================== DADOS DO PEDIDO ================== -->
    <section class="opcionais width-fix mt-5 pb-5">

        <!-- TIPO DE ENTREGA / RETIRADA -->
        <div class="container-group mb-5">
            <span class="badge">Obrigatório</span>
            <p class="title-categoria mb-0"><b>Escolha uma opção</b></p>
            <span class="sub-title-categoria">Como quer receber o pedido?</span>

            <?php if ($temEntrega): ?>
                <div class="card card-opcionais mt-2">
                    <div class="infos-produto-opcional">
                        <p class="name mb-0"><b><?= htmlspecialchars($textoEntrega) ?></b></p>
                    </div>
                    <div class="checks">
                        <label class="container-check">
                            <input type="checkbox" id="chk_tipo_entrega" />
                            <span class="checkmark"></span>
                        </label>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($temRetirada): ?>
                <div class="card card-opcionais mt-2">
                    <div class="infos-produto-opcional">
                        <p class="name mb-0"><b><?= htmlspecialchars($textoRetirada) ?></b></p>
                    </div>
                    <div class="checks">
                        <label class="container-check">
                            <input type="checkbox" id="chk_tipo_retirada" />
                            <span class="checkmark"></span>
                        </label>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$temEntrega && !$temRetirada): ?>
                <p class="mt-2 text-muted">Nenhuma configuração de entrega/retirada cadastrada para esta empresa.</p>
            <?php endif; ?>
        </div>

        <!-- ENDEREÇO -->
        <div class="container-group mb-5">
            <span class="badge">Obrigatório</span>
            <p class="title-categoria mb-0"><b>Qual o seu endereço?</b></p>
            <span class="sub-title-categoria">Informe o endereço da entrega</span>

            <div class="card card-select mt-2" id="card-address-empty">
                <div class="infos-produto-opcional">
                    <p class="mb-0 color-primary">
                        <i class="fas fa-plus-circle"></i>&nbsp; Nenhum endereço selecionado
                    </p>
                </div>
            </div>

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

        <!-- NOME -->
        <div class="container-group mb-5">
            <span class="badge">Obrigatório</span>
            <p class="title-categoria mb-0"><b>Nome e Sobrenome</b></p>
            <span class="sub-title-categoria">Como vamos te chamar?</span>
            <input type="text" class="form-control mt-2" id="cliente_nome" name="cliente_nome"
                placeholder="* Informe o nome e sobrenome">
        </div>

        <!-- TELEFONE -->
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

            <div class="card card-select mt-2" id="card-pagamento-empty">
                <div class="infos-produto-opcional">
                    <p class="mb-0 color-primary">
                        <i class="fas fa-plus-circle"></i>&nbsp; Nenhuma forma selecionada
                    </p>
                </div>
            </div>

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

    <!-- ============ MODAL ENDEREÇO (BOOTSTRAP) ============ -->
    <!-- (mantive igual ao seu, só não repito aqui para não estourar token; use o mesmo bloco que você já tinha) -->
    <!-- ... TODO bloco de modalEndereco e modalPagamento permanece igual ao seu anterior ... -->

    <section class="menu-bottom disabled hidden" id="menu-bottom-closed">
        <p class="mb-0"><b>Loja fechada no momento.</b></p>
    </section>

    <script src="./js/bootstrap.bundle.min.js"></script>

    <script>
        const carrinhoPHP = <?php echo json_encode($_SESSION['carrinho'] ?? []); ?>;
        const totalPedidoPHP = <?php echo json_encode($total_pedido); ?>;
        const taxaEntregaPHP = <?php echo json_encode($taxaEntregaValor); ?>;
        const empresaID = <?php echo json_encode($empresaID); ?>;

        const STORAGE_ENDERECO_KEY = 'pedido_endereco_' + empresaID;
        const STORAGE_PAGAMENTO_KEY = 'pedido_pagamento_' + empresaID;

        let formaPagamentoSelecionada = '';
        let detalhePagamentoSelecionado = '';

        document.addEventListener('DOMContentLoaded', function() {
            // ====== FUNÇÃO TOAST REUTILIZÁVEL ======
            function showToast(msg, isError = false, redirectUrl = null, redirectDelay = 2200) {
                let toast = document.getElementById('toast-msg');
                if (!toast) {
                    toast = document.createElement('div');
                    toast.id = 'toast-msg';
                    toast.className = 'toast-msg';
                    document.body.appendChild(toast);
                }

                toast.textContent = msg;
                toast.classList.remove('toast-hide', 'toast-msg-error');

                if (isError) {
                    toast.classList.add('toast-msg-error');
                }

                toast.classList.add('toast-show');

                setTimeout(() => {
                    toast.classList.remove('toast-show');
                    toast.classList.add('toast-hide');
                }, 1500);

                if (redirectUrl) {
                    setTimeout(() => {
                        window.location.href = redirectUrl;
                    }, redirectDelay || 2200);
                }
            }

            // Se veio mensagem de sessão (remover item, etc.), anima ela
            const toastInicial = document.getElementById('toast-msg');
            if (toastInicial && toastInicial.textContent.trim() !== '') {
                showToast(toastInicial.textContent.trim(), toastInicial.classList.contains('toast-msg-error'));
            }

            const baseTotalItens = Number(totalPedidoPHP || 0);

            const chkEntrega = document.getElementById('chk_tipo_entrega');
            const chkRetirada = document.getElementById('chk_tipo_retirada');
            const cardTaxaEntrega = document.getElementById('card-taxa-entrega');
            const spanTotalBotao = document.getElementById('valor_total_pedido');

            function calcularTotalPedido() {
                let total = baseTotalItens;
                const taxa = Number(taxaEntregaPHP || 0);

                if (chkEntrega && chkEntrega.checked && taxa > 0) {
                    total += taxa;
                }
                return total;
            }

            function atualizarResumoTotal() {
                const total = calcularTotalPedido();

                if (spanTotalBotao) {
                    spanTotalBotao.textContent = 'R$ ' + total.toFixed(2).replace('.', ',');
                }

                const taxa = Number(taxaEntregaPHP || 0);
                if (cardTaxaEntrega) {
                    if (chkEntrega && chkEntrega.checked && taxa > 0) {
                        cardTaxaEntrega.classList.remove('d-none');
                    } else {
                        cardTaxaEntrega.classList.add('d-none');
                    }
                }
            }

            // ENTREGA/RETIRADA
            if (chkEntrega && chkRetirada) {
                chkEntrega.addEventListener('change', () => {
                    if (chkEntrega.checked) chkRetirada.checked = false;
                    atualizarResumoTotal();
                });
                chkRetirada.addEventListener('change', () => {
                    if (chkRetirada.checked) chkEntrega.checked = false;
                    atualizarResumoTotal();
                });
            }

            // ====== (Mantenha aqui todo o resto do seu JS de endereço, pagamento, etc. IGUAL estava) ======
            // Por causa de limite de caracteres eu não repito tudo, mas você pode colar o JS que já tinha
            // (endereço, localStorage, modalPagamento, etc.) exatamente como estava antes desta parte.

            // === FINALIZAR / WHATSAPP ===
            const btnFinalizar = document.getElementById('btn-finalizar-pedido');
            if (btnFinalizar) {
                btnFinalizar.addEventListener('click', function() {
                    if (!carrinhoPHP || !Array.isArray(carrinhoPHP) || carrinhoPHP.length === 0) {
                        showToast('Seu carrinho está vazio.', true);
                        return;
                    }

                    const nome = (document.getElementById('cliente_nome') || {}).value || '';
                    const telefone = (document.getElementById('cliente_telefone') || {}).value || '';
                    const endereco = (document.getElementById('endereco_texto') || {}).value || '';
                    const pagamento = (document.getElementById('pagamento_texto') || {}).value || '';

                    const tipoEntregaCheck = document.getElementById('chk_tipo_entrega');
                    const tipoRetiradaCheck = document.getElementById('chk_tipo_retirada');
                    let modoEntrega = '';
                    if (tipoEntregaCheck && tipoEntregaCheck.checked) modoEntrega = 'Entrega';
                    if (tipoRetiradaCheck && tipoRetiradaCheck.checked) modoEntrega = 'Retirada';

                    if (!modoEntrega) {
                        showToast('Selecione Entrega ou Retirada.', true);
                        return;
                    }

                    if (!nome.trim()) {
                        showToast('Informe o seu nome.', true);
                        return;
                    }
                    if (!telefone.trim()) {
                        showToast('Informe o número do seu celular.', true);
                        return;
                    }

                    if (modoEntrega === 'Entrega' && !endereco.trim()) {
                        showToast('Selecione e salve um endereço de entrega.', true);
                        return;
                    }

                    if (!pagamento.trim() || !formaPagamentoSelecionada) {
                        showToast('Selecione uma forma de pagamento.', true);
                        return;
                    }

                    let texto = '';
                    texto += 'NOVO PEDIDO - <?= addslashes($nomeEmpresa) ?>\n';
                    texto += 'Tipo: ' + modoEntrega + '\n';
                    texto += 'Cliente: ' + nome.trim() + '\n';
                    texto += 'Telefone: ' + telefone.trim() + '\n\n';
                    texto += 'ITENS DO PEDIDO:\n';

                    carrinhoPHP.forEach(function(item, idx) {
                        if (!item) return;
                        const nomeItem = item.nome || ('Item ' + (idx + 1));
                        const quantItem = item.quant || 1;
                        const precoItemNum = Number(item.preco || 0);
                        const precoItemTxt = 'R$ ' + precoItemNum.toFixed(2).replace('.', ',');

                        texto += '- ' + quantItem + 'x ' + nomeItem + ' - ' + precoItemTxt + '\n';

                        if (Array.isArray(item.opc_simples) && item.opc_simples.length > 0) {
                            texto += '  Adicionais:\n';
                            item.opc_simples.forEach(function(opc) {
                                if (!opc) return;
                                const n = opc.nome || '';
                                const pNum = Number(opc.preco || 0);
                                const pTxt = 'R$ ' + pNum.toFixed(2).replace('.', ',');
                                texto += '   - ' + n + ' (+' + pTxt + ')\n';
                            });
                        }

                        if (Array.isArray(item.opc_selecao) && item.opc_selecao.length > 0) {
                            if (!(Array.isArray(item.opc_simples) && item.opc_simples.length > 0)) {
                                texto += '  Adicionais:\n';
                            }
                            item.opc_selecao.forEach(function(opc) {
                                if (!opc) return;
                                const n = opc.nome || '';
                                const pNum = Number(opc.preco || 0);
                                const pTxt = 'R$ ' + pNum.toFixed(2).replace('.', ',');
                                texto += '   - ' + n + ' (+' + pTxt + ')\n';
                            });
                        }

                        if (item.observacao) {
                            texto += '  Observação: ' + item.observacao + '\n';
                        }

                        texto += '\n';
                    });

                    const taxaNum = Number(taxaEntregaPHP || 0);
                    const totalItensNum = baseTotalItens;
                    const totalFinalNum = calcularTotalPedido();

                    texto += 'TOTAL DOS ITENS:\n';
                    texto += 'R$ ' + totalItensNum.toFixed(2).replace('.', ',') + '\n';

                    if (modoEntrega === 'Entrega' && taxaNum > 0) {
                        texto += 'TAXA DE ENTREGA:\n';
                        texto += 'R$ ' + taxaNum.toFixed(2).replace('.', ',') + '\n';
                    }

                    texto += 'TOTAL FINAL:\n';
                    texto += 'R$ ' + totalFinalNum.toFixed(2).replace('.', ',') + '\n\n';

                    let enderecoFinal = endereco.trim();
                    if (!enderecoFinal && modoEntrega === 'Retirada') {
                        enderecoFinal = 'Retirada no estabelecimento.';
                    }

                    texto += 'ENDEREÇO:\n' + enderecoFinal + '\n\n';
                    texto += 'FORMA DE PAGAMENTO:\n' + pagamento + '\n\n';
                    texto += 'Enviado automaticamente pelo sistema.';

                    const dados = new FormData();
                    dados.append('nome', nome.trim());
                    dados.append('telefone', telefone.trim());
                    dados.append('endereco', enderecoFinal);
                    dados.append('forma_pagamento', formaPagamentoSelecionada);
                    dados.append('detalhe_pagamento', detalhePagamentoSelecionado);
                    dados.append('total', String(totalFinalNum || 0));
                    dados.append('itens_json', JSON.stringify(carrinhoPHP || []));

                    let taxaParaSalvar = 0;
                    if (modoEntrega === 'Entrega') {
                        taxaParaSalvar = Number(taxaEntregaPHP || 0);
                    }
                    dados.append('taxa_entrega', String(taxaParaSalvar));

                    fetch('salvar_rascunho.php?empresa=<?= urlencode($empresaID) ?>', {
                            method: 'POST',
                            body: dados
                        })
                        .then(function(response) {
                            return response.json().catch(function() {
                                return null;
                            });
                        })
                        .then(function(resposta) {
                            if (!resposta || !resposta.status || resposta.status !== 'ok') {
                                // ERRO NO SERVIDOR -> NÃO VAI PRO WPP, NÃO LIMPA CARRINHO
                                showToast('Erro ao finalizar o pedido. Tente novamente.', true);
                                return;
                            }

                            // SUCESSO: abre WhatsApp, mostra toast e redireciona pro pedido.php
                            const numeroWhatsapp = '559791434585';
                            const url = 'https://wa.me/' + numeroWhatsapp + '?text=' + encodeURIComponent(texto);
                            window.open(url, '_blank');

                            showToast('Pedido enviado com sucesso!', false, 'pedido.php?empresa=<?= urlencode($empresaID) ?>', 2200);
                        })
                        .catch(function() {
                            // ERRO DE REDE / FETCH
                            showToast('Erro de comunicação. Verifique sua conexão e tente novamente.', true);
                        });
                });
            }

            atualizarResumoTotal();
        });

        // === Funções de modal de pagamento/endereço (mantenha as mesmas que você já tem) ===
        function abrirModalPagamento() {
            const el = document.getElementById('modalPagamento');
            if (el) el.classList.remove('hidden');
        }

        function fecharModalPagamento() {
            const el = document.getElementById('modalPagamento');
            if (el) el.classList.add('hidden');
        }

        function selecionarFormaPagamento(tipo, detalheForcado) {
            // ... (mantenha o que você já tinha aqui)
        }
    </script>

    <script src="./js/item.js"></script>

</body>

</html>
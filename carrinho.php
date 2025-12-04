<?php
session_start();
require './assets/php/conexao.php';

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
   (usando entregas, entrega_taxas, entrega_taxas_unica,
    configuracoes_retirada)
   =========================================== */
$temEntrega        = false;
$textoEntrega      = '';
$taxaEntregaValor  = 0.0;
$modoTaxaEntrega   = 'nenhum'; // sem_taxa | taxa_unica | nenhum

$temRetirada       = false;
$textoRetirada     = '';

try {
    // ================= ENTREGAS =================
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

        // Texto da entrega
        $tMin = (int)$entCfg['tempo_min'];
        $tMax = (int)$entCfg['tempo_max'];
        if ($tMin > 0 && $tMax > 0) {
            $textoEntrega = 'Entrega (' . $tMin . '-' . $tMax . 'min)';
        } else {
            $textoEntrega = 'Entrega';
        }

        // ----- CONFIGURAÇÃO DA TAXA -----
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
                // Sem taxa, mesmo que exista registro na tabela de valor
                $modoTaxaEntrega  = 'sem_taxa';
                $taxaEntregaValor = 0.0;
            } elseif ($taxaUnica) {
                $modoTaxaEntrega = 'taxa_unica';

                // Busca valor da taxa única
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

    // ================= RETIRADA =================
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
    // segue sem formas, se der erro
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
            <!-- Card da taxa de entrega aparece SOMENTE quando marcar "Entrega" -->
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
                            placeholder="Próximo à praça, escola..." />
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" id="btn-cancelar-endereco">Cancelar</button>
                    <button type="button" class="btn btn-yellow btn-sm" id="btn-salvar-endereco">Salvar endereço</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ MODAL PAGAMENTO (BACKDROP CENTRAL) ============ -->
    <div class="modal-actions hidden" id="modalPagamento">
        <div class="backdrop" onclick="fecharModalPagamento()"></div>
        <div class="width-fix container-modal-actions">
            <?php if ($formasPagamento['pix']): ?>
                <a href="#!" onclick="selecionarFormaPagamento('Pix')">Pix</a>
            <?php endif; ?>

            <?php if ($formasPagamento['dinheiro']): ?>
                <a href="#!" onclick="selecionarFormaPagamento('Dinheiro')">Dinheiro</a>
            <?php endif; ?>

            <?php if ($temCartao): ?>
                <a href="#!" onclick="selecionarFormaPagamento('Cartão')">Cartão</a>
            <?php endif; ?>

            <a href="#!" class="color-red" onclick="selecionarFormaPagamento('')">Remover</a>
        </div>
    </div>

    <section class="menu-bottom disabled hidden" id="menu-bottom-closed">
        <p class="mb-0"><b>Loja fechada no momento.</b></p>
    </section>

    <script src="./js/bootstrap.bundle.min.js"></script>

    <script>
        // Dados do PHP
        const carrinhoPHP    = <?php echo json_encode($_SESSION['carrinho'] ?? []); ?>;
        const totalPedidoPHP = <?php echo json_encode($total_pedido); ?>;
        const taxaEntregaPHP = <?php echo json_encode($taxaEntregaValor); ?>;
        const empresaID      = <?php echo json_encode($empresaID); ?>;

        // Keys para localStorage (por empresa)
        const STORAGE_ENDERECO_KEY  = 'pedido_endereco_'  + empresaID;
        const STORAGE_PAGAMENTO_KEY = 'pedido_pagamento_' + empresaID;

        // Globais de pagamento
        let formaPagamentoSelecionada   = '';
        let detalhePagamentoSelecionado = '';

        document.addEventListener('DOMContentLoaded', function() {
            const baseTotalItens = Number(totalPedidoPHP || 0);

            // ========= ELEMENTOS GERAIS =========
            const chkEntrega      = document.getElementById('chk_tipo_entrega');
            const chkRetirada     = document.getElementById('chk_tipo_retirada');
            const cardTaxaEntrega = document.getElementById('card-taxa-entrega');
            const spanTotalBotao  = document.getElementById('valor_total_pedido');

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

            // ========= TIPO ENTREGA / RETIRADA =========
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

            // ========= ENDEREÇO =========
            const cardAddressEmpty   = document.getElementById('card-address-empty');
            const cardAddressFilled  = document.getElementById('card-address-filled');
            const resumoEnderecoL1   = document.getElementById('resumo_endereco_linha1');
            const resumoEnderecoL2   = document.getElementById('resumo_endereco_linha2');
            const enderecoTextoInput = document.getElementById('endereco_texto');

            const modalEnderecoEl = document.getElementById('modalEndereco');
            let modalEndereco = null;
            if (typeof bootstrap !== 'undefined' && modalEnderecoEl) {
                modalEndereco = new bootstrap.Modal(modalEnderecoEl);
            }

            function abrirModalEndereco() {
                if (modalEndereco) modalEndereco.show();
            }

            function fecharModalEndereco() {
                if (modalEndereco) modalEndereco.hide();
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

            function montarResumoEndereco(rua, num, bairro, cid, cep, compl, ref) {
                const linha1 = rua + ', ' + num + ' - ' + bairro;
                let linha2 = cid;
                if (cep.trim()) {
                    linha2 += ' - ' + cep;
                }

                let textoEndereco = linha1 + ' - ' + linha2;
                if (compl.trim()) textoEndereco += ' | Compl.: ' + compl.trim();
                if (ref.trim())   textoEndereco += ' | Ref.: '   + ref.trim();

                if (resumoEnderecoL1) resumoEnderecoL1.textContent = linha1;
                if (resumoEnderecoL2) resumoEnderecoL2.textContent = linha2;
                if (enderecoTextoInput) enderecoTextoInput.value   = textoEndereco;

                if (cardAddressEmpty)  cardAddressEmpty.classList.add('d-none');
                if (cardAddressFilled) cardAddressFilled.classList.remove('d-none');
            }

            function salvarEndereco() {
                const rua  = (document.getElementById('endereco_rua')  || {}).value || '';
                const num  = (document.getElementById('endereco_numero') || {}).value || '';
                const bairro = (document.getElementById('endereco_bairro') || {}).value || '';
                const cid  = (document.getElementById('endereco_cidade') || {}).value || '';
                const cep  = (document.getElementById('endereco_cep')   || {}).value || '';
                const compl = (document.getElementById('endereco_complemento') || {}).value || '';
                const ref   = (document.getElementById('endereco_referencia')  || {}).value || '';

                if (!rua.trim() || !num.trim() || !bairro.trim() || !cid.trim()) {
                    alert('Preencha rua, número, bairro e cidade.');
                    return;
                }

                montarResumoEndereco(rua, num, bairro, cid, cep, compl, ref);

                // Salva no localStorage
                try {
                    localStorage.setItem(STORAGE_ENDERECO_KEY, JSON.stringify({
                        rua: rua,
                        numero: num,
                        bairro: bairro,
                        cidade: cid,
                        cep: cep,
                        complemento: compl,
                        referencia: ref
                    }));
                } catch (e) {}

                fecharModalEndereco();
            }

            if (btnSalvarEndereco)   btnSalvarEndereco.addEventListener('click', salvarEndereco);
            if (btnCancelarEndereco) btnCancelarEndereco.addEventListener('click', fecharModalEndereco);
            if (btnCloseEndereco)    btnCloseEndereco.addEventListener('click', fecharModalEndereco);

            // Carregar endereço do localStorage (se existir)
            (function carregarEnderecoLocalStorage() {
                try {
                    const raw = localStorage.getItem(STORAGE_ENDERECO_KEY);
                    if (!raw) return;
                    const dados = JSON.parse(raw) || {};
                    const rua   = dados.rua || '';
                    const num   = dados.numero || '';
                    const bairro = dados.bairro || '';
                    const cid   = dados.cidade || '';
                    const cep   = dados.cep || '';
                    const compl = dados.complemento || '';
                    const ref   = dados.referencia || '';

                    if (rua && num && bairro && cid) {
                        if (document.getElementById('endereco_rua'))         document.getElementById('endereco_rua').value         = rua;
                        if (document.getElementById('endereco_numero'))      document.getElementById('endereco_numero').value      = num;
                        if (document.getElementById('endereco_bairro'))      document.getElementById('endereco_bairro').value      = bairro;
                        if (document.getElementById('endereco_cidade'))      document.getElementById('endereco_cidade').value      = cid;
                        if (document.getElementById('endereco_cep'))         document.getElementById('endereco_cep').value         = cep;
                        if (document.getElementById('endereco_complemento')) document.getElementById('endereco_complemento').value = compl;
                        if (document.getElementById('endereco_referencia'))  document.getElementById('endereco_referencia').value  = ref;

                        montarResumoEndereco(rua, num, bairro, cid, cep, compl, ref);
                    }
                } catch (e) {}
            })();

            // ========= PAGAMENTO =========
            const cardPagamentoEmpty  = document.getElementById('card-pagamento-empty');
            const cardPagamentoFilled = document.getElementById('card-pagamento-filled');
            const resumoPagamentoL1   = document.getElementById('resumo_pagamento_linha1');
            const resumoPagamentoL2   = document.getElementById('resumo_pagamento_linha2');
            const pagamentoTextoInput = document.getElementById('pagamento_texto');

            if (cardPagamentoEmpty) {
                cardPagamentoEmpty.style.cursor = 'pointer';
                cardPagamentoEmpty.addEventListener('click', abrirModalPagamento);
            }
            const btnEditPagamento = document.getElementById('btn-edit-pagamento');
            if (btnEditPagamento) {
                btnEditPagamento.style.cursor = 'pointer';
                btnEditPagamento.addEventListener('click', abrirModalPagamento);
            }

            // Carregar forma de pagamento do localStorage
            (function carregarPagamentoLocalStorage() {
                try {
                    const raw = localStorage.getItem(STORAGE_PAGAMENTO_KEY);
                    if (!raw) return;
                    const dados   = JSON.parse(raw) || {};
                    const forma   = dados.forma   || '';
                    const detalhe = dados.detalhe || '';

                    if (forma) {
                        selecionarFormaPagamento(forma, detalhe);
                    }
                } catch (e) {}
            })();

            // ========= FINALIZAR / WHATSAPP =========
            const btnFinalizar = document.getElementById('btn-finalizar-pedido');
            if (btnFinalizar) {
                btnFinalizar.addEventListener('click', function() {
                    if (!carrinhoPHP || !Array.isArray(carrinhoPHP) || carrinhoPHP.length === 0) {
                        alert('Seu carrinho está vazio.');
                        return;
                    }

                    const nome      = (document.getElementById('cliente_nome')      || {}).value || '';
                    const telefone  = (document.getElementById('cliente_telefone')  || {}).value || '';
                    const endereco  = (document.getElementById('endereco_texto')    || {}).value || '';
                    const pagamento = (document.getElementById('pagamento_texto')   || {}).value || '';

                    const tipoEntregaCheck  = document.getElementById('chk_tipo_entrega');
                    const tipoRetiradaCheck = document.getElementById('chk_tipo_retirada');
                    let modoEntrega = '';
                    if (tipoEntregaCheck && tipoEntregaCheck.checked) modoEntrega = 'Entrega';
                    if (tipoRetiradaCheck && tipoRetiradaCheck.checked) modoEntrega = 'Retirada';

                    if (!modoEntrega) {
                        alert('Selecione Entrega ou Retirada.');
                        return;
                    }

                    if (!nome.trim()) {
                        alert('Informe o seu nome.');
                        return;
                    }
                    if (!telefone.trim()) {
                        alert('Informe o número do seu celular.');
                        return;
                    }

                    // Endereço obrigatório apenas se for ENTREGA
                    if (modoEntrega === 'Entrega' && !endereco.trim()) {
                        alert('Selecione e salve um endereço de entrega.');
                        return;
                    }

                    if (!pagamento.trim() || !formaPagamentoSelecionada) {
                        alert('Selecione uma forma de pagamento.');
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

                        const nomeItem     = item.nome || ('Item ' + (idx + 1));
                        const quantItem    = item.quant || 1;
                        const precoItemNum = Number(item.preco || 0);
                        const precoItemTxt = 'R$ ' + precoItemNum.toFixed(2).replace('.', ',');

                        texto += '- ' + quantItem + 'x ' + nomeItem + ' - ' + precoItemTxt + '\n';

                        if (Array.isArray(item.opc_simples) && item.opc_simples.length > 0) {
                            texto += '  Adicionais:\n';
                            item.opc_simples.forEach(function(opc) {
                                if (!opc) return;
                                const n   = opc.nome  || '';
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
                                const n   = opc.nome || '';
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

                    const taxaNum       = Number(taxaEntregaPHP || 0);
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

                    fetch('salvar_rascunho.php?empresa=<?= urlencode($empresaID) ?>', {
                            method: 'POST',
                            body: dados
                        })
                        .then(function(response) {
                            return response.json().catch(function() {
                                return {};
                            });
                        })
                        .then(function() {
                            const numeroWhatsapp = '559791434585';
                            const url = 'https://wa.me/' + numeroWhatsapp + '?text=' + encodeURIComponent(texto);
                            window.open(url, '_blank');
                        })
                        .catch(function() {
                            const numeroWhatsapp = '559791434585';
                            const url = 'https://wa.me/' + numeroWhatsapp + '?text=' + encodeURIComponent(texto);
                            window.open(url, '_blank');
                        });
                });
            }

            // Atualiza total na carga inicial
            atualizarResumoTotal();
        });

        // ========= MODAL PAGAMENTO (BACKDROP) =========
        function abrirModalPagamento() {
            const el = document.getElementById('modalPagamento');
            if (el) el.classList.remove('hidden');
        }

        function fecharModalPagamento() {
            const el = document.getElementById('modalPagamento');
            if (el) el.classList.add('hidden');
        }

        // tipo: 'Pix', 'Dinheiro', 'Cartão' ou ''
        // detalheForcado: usado quando vem do localStorage (para não pedir troco novamente)
        function selecionarFormaPagamento(tipo, detalheForcado) {
            const cardEmpty  = document.getElementById('card-pagamento-empty');
            const cardFilled = document.getElementById('card-pagamento-filled');
            const lbl1       = document.getElementById('resumo_pagamento_linha1');
            const lbl2       = document.getElementById('resumo_pagamento_linha2');
            const inputHidden = document.getElementById('pagamento_texto');

            if (!tipo) {
                formaPagamentoSelecionada   = '';
                detalhePagamentoSelecionado = '';

                if (cardEmpty)  cardEmpty.classList.remove('d-none');
                if (cardFilled) cardFilled.classList.add('d-none');
                if (lbl1) lbl1.textContent = '';
                if (lbl2) lbl2.textContent = '';
                if (inputHidden) inputHidden.value = '';

                try {
                    localStorage.removeItem(STORAGE_PAGAMENTO_KEY);
                } catch (e) {}

                fecharModalPagamento();
                return;
            }

            formaPagamentoSelecionada = tipo;

            if (detalheForcado !== undefined && detalheForcado !== null && detalheForcado !== '') {
                detalhePagamentoSelecionado = detalheForcado;
            } else {
                if (tipo === 'Pix') {
                    detalhePagamentoSelecionado = 'Pagamento via Pix na entrega.';
                } else if (tipo === 'Dinheiro') {
                    let troco = prompt('Qual o valor do troco? (deixe em branco se não precisar)');
                    if (troco && troco.trim() !== '' && !isNaN(troco.replace(',', '.'))) {
                        const tNum = parseFloat(troco.replace(',', '.'));
                        detalhePagamentoSelecionado = 'Dinheiro - troco para R$ ' + tNum.toFixed(2).replace('.', ',');
                    } else {
                        detalhePagamentoSelecionado = 'Pagamento em dinheiro, sem troco informado.';
                    }
                } else if (tipo === 'Cartão') {
                    detalhePagamentoSelecionado = 'Cartão (débito/crédito) na entrega.';
                } else {
                    detalhePagamentoSelecionado = '';
                }
            }

            if (cardEmpty)  cardEmpty.classList.add('d-none');
            if (cardFilled) cardFilled.classList.remove('d-none');
            if (lbl1) lbl1.textContent = formaPagamentoSelecionada;
            if (lbl2) lbl2.textContent = detalhePagamentoSelecionado;

            if (inputHidden) {
                let txt = formaPagamentoSelecionada;
                if (detalhePagamentoSelecionado) {
                    txt += ' - ' + detalhePagamentoSelecionado;
                }
                inputHidden.value = txt;
            }

            try {
                localStorage.setItem(STORAGE_PAGAMENTO_KEY, JSON.stringify({
                    forma: formaPagamentoSelecionada,
                    detalhe: detalhePagamentoSelecionado
                }));
            } catch (e) {}

            fecharModalPagamento();
        }
    </script>

    <script src="./js/item.js"></script>

</body>

</html>

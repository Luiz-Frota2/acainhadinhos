<?php
require_once './painel/assets/php/conexao.php'; // Conectar ao banco de dados

session_start(); // Iniciar a sessão

// Recuperar o ID do carrinho a partir do cookie
$idCarrinho = isset($_COOKIE['id_carrinho']) ? $_COOKIE['id_carrinho'] : null;

// Verificar se o ID do carrinho está presente
if (!$idCarrinho) {

}

// Preparar a consulta para trazer os itens do carrinho junto com os detalhes do produto
$query = "
    SELECT 
        c.id_produto, 
        p.nome_produto, 
        c.quantidade, 
        c.preco, 
        p.preco_produto,
        p.imagem_produto
    FROM 
        carrinhotemporario c
    JOIN 
        adicionarprodutos p ON c.id_produto = p.id_produto
    WHERE 
        c.id_carrinho = :idCarrinho
";

$stmt = $pdo->prepare($query);
$stmt->bindParam(':idCarrinho', $idCarrinho, PDO::PARAM_STR);
$stmt->execute();

$produtosCarrinho = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular o total
$total = 0;
foreach ($produtosCarrinho as $produto) {
    $total += $produto['quantidade'] * $produto['preco_produto'];
}
$totalFormatado = number_format($total, 2, ',', '.');

// Verificar exclusão de produto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_produto'])) {
    $idProduto = $_POST['id_produto'];
    $deleteQuery = "DELETE FROM carrinhotemporario WHERE id_produto = :id_produto AND id_carrinho = :idCarrinho";
    $deleteStmt = $pdo->prepare($deleteQuery);
    $deleteStmt->bindParam(':id_produto', $idProduto, PDO::PARAM_INT);
    $deleteStmt->bindParam(':idCarrinho', $idCarrinho, PDO::PARAM_STR);
    $deleteStmt->execute();
    // Redireciona para evitar o envio múltiplo do formulário
    header("Location: carrinho.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eletrica - Carrinho</title>

    <link rel="stylesheet" href="./css/cardapio/animate.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="./css/cardapio/bootstrap.min.css" />
    <link rel="stylesheet" href="./css/cardapio/main.css" />
    <link rel="stylesheet" href="./css/modal.css">

    <script>
        function confirmarExclusao(event, form) {
            event.preventDefault(); // Impede o envio imediato do formulário
            let confirmacao = confirm("Você deseja remover esse produto do carrinho?");
            if (confirmacao) {
                form.submit(); // Envia o formulário se o usuário confirmar
            }
        }
    </script>
</head>

<body>

<div class="bg-top pedido"></div>

<header class="width-fix mt-3">
    <div class="card">
        <div class="d-flex">
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
    <?php if (empty($produtosCarrinho)): ?>
        <div class="card card-address">
            <div class="img-icon-details">
                <i class="fas fa-cart-plus"></i>
            </div>
            <div class="infos">
                <p class="name mb-0"><b>Seu carrinho está vazio</b></p>
                <span class="text mb-0">
                    Volte ao cardápio, selecione os itens e adicione ao seu carrinho.
                </span>
            </div>
        </div>

        <a href="./index.php#cardapio" class="btn btn-yellow btn-volta" style="text-align: center !;">
                                       Voltar Para o Catálogo
        </a>
        <?php else: ?>
            <?php foreach ($produtosCarrinho as $produto): ?>
                <div class="card mb-2 pr-0">
                    <div class="container-detalhes">
                        <div class="detalhes-produto">
                            <div class="infos-produto">
                                <input readonly style="border: 0px solid; outline: none;" id="produto" class="name" value="<?= $produto['quantidade'] ?>x <?= $produto['nome_produto'] ?>" >
                                <input readonly  style="border: 0px solid; outline: none; text-align:right; "  id="preco" class="price" value="R$ <?= number_format($produto['preco_produto'], 2, ',', '.') ?>" >
                            </div>      
                        </div>
                        <div class="detalhes-produto-edit">
                            <form method="POST" action="" onsubmit="confirmarExclusao(event, this)">
                                <input type="hidden" name="id_produto" value="<?= $produto['id_produto'] ?>">
                                <button type="submit" class="delete-icon" style="background: none; border: none;">
                                    <i class="fas fa-trash" style="color: var(--color-red);"></i>
                                </button>
                           
                    
                        </div>
                    </div>
                </div>
        <?php endforeach; ?>

        <div class="card mb-2">
            <div class="detalhes-produto">
                <div class="infos-produto">
                    <p class="name-total mb-0"><b>Total</b></p>
                    <input readonly  style="border: 0px solid; outline: none; text-align:right; font-weight: bold; " class="price-total mb-0" id="valorTotal" value="R$ <?= $totalFormatado ?>">
                </div>
            </div>
        </div>

        <section class="opcionais  mt-4 pb-5">


            <div class="container-group mb-3">

                <span class="badge">Obrigatório</span>
                <!-- Informações de Endereço -->
                <p class="title-categoria mb-0" id="textoEndereco"><b>Qual o seu endereço?</b></p>
                <span class="sub-title-categoria" id="subTextoEndereco">Informe o endereço da entrega</span>
                <div class="card card-select select mt-2" id="abrirModal">
                    <div class="infos-produto-opcional">
                        <p class="mb-0 color-primary" id="enderecoSelecionado">
                            <i class="fas fa-plus-circle"></i>&nbsp; Nenhum endereço selecionado
                        </p>
                    </div>
                </div>

                <!-- Modal -->
                <div id="modalEndereco" class="modal fade centered-modal" tabindex="-1" data-backdrop="static" aria-modal="true" role="dialog" style="display: none;">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><b>Endereço</b></h5>
                                <button type="button" class="btn btn-white btn-sm" data-bs-dismiss="modal" aria-label="Close">
                                    <i class="fas fa-times"></i>&nbsp; Fechar
                                </button>
                            </div>
                            <div class="modal-body">
                                <div class="row mb-5">
                                    <div class="col-12">
                                        <div class="form-group">
                                            <p class="title-categoria mb-0 mt-4"><b>Endereço:</b></p>
                                            <input id="txtEndereco" type="text" class="form-control">
                                        </div>
                                    </div>

                                    <div class="col-8">
                                        <div class="form-group">
                                            <p class="title-categoria mb-0 mt-4"><b>Bairro:</b></p>
                                            <input id="txtBairro" type="text" class="form-control">
                                        </div>
                                    </div>

                                    <div class="col-4">
                                        <div class="form-group">
                                            <p class="title-categoria mb-0 mt-4"><b>Número:</b></p>
                                            <input id="txtNumero" type="text" class="form-control">
                                        </div>
                                    </div>

                                    <div class="col-8">
                                        <div class="form-group">
                                            <p class="title-categoria mb-0 mt-4"><b>Cidade:</b></p>
                                            <input id="txtCidade" type="text" class="form-control">
                                        </div>
                                    </div>

                                    <div class="col-4">
                                        <div class="form-group">
                                            <p class="title-categoria mb-0 mt-4"><b>UF:</b></p>
                                            <input id="txtUf" type="text" class="form-control">
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="form-group">
                                            <p class="title-categoria mb-0 mt-4"><b>Complemento:</b></p>
                                            <input id="txtComplemento" type="text" class="form-control">
                                        </div>
                                    </div>
                                </div>                 

                                <div class="footer-btn mt-0 mb-3" style="text-align: right;">
                                    <a class="btn btn-yellow btn-sm" id="salvarEndereco" onclick="salvarEndereco()">
                                        <i class="fas fa-check"></i>&nbsp; Salvar Endereço
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Overlay para escurecer o fundo -->
                <div id="overlay" style="display: none;"></div>

                </div>
            <div class="container-group mb-5">
                <div id="dadosPreenchidos">
                </div>  
            </div>

            <section class="opcionais pb-5">
            <!-- Campos de nome e telefone -->
                <div class="container-group mb-5">
                    <span class="badge">Obrigatório</span>
                    <p class="title-categoria mb-0"><b>Nome e Sobrenome</b></p>
                    <span class="sub-title-categoria">Como vamos te chamar?</span>
                    <input type="text" class="form-control mt-2" id="nome" placeholder="* Informe o nome e sobrenome" />
                </div>

                <div class="container-group mb-5">
                    <span class="badge">Obrigatório</span>
                    <p class="title-categoria mb-0"><b>Número do seu celular</b></p>
                    <span class="sub-title-categoria">Para mais informações do pedido</span>
                    <input type="text" class="form-control mt-2" placeholder="(00) 0000-0000" />
                </div>

                <!-- Escolher forma de pagamento -->
                <div class="container-group mb-5">
                    <span class="badge">Obrigatório</span>
                    <p class="title-categoria mb-0" id="txt_pagamento"><b>Como você prefere pagar?</b></p>
                    <span class="sub-title-categoria" id="sub_txt_pagamento">* Pagamento na entrega</span>
                    <div class="card card-select mt-2" id="card-select">
                        <div class="infos-produto-opcional">
                            <p class="mb-0 color-primary">
                                <i class="fas fa-plus-circle"></i>&nbsp; Nenhuma forma selecionada
                            </p>
                        </div>
                    </div>

                    <div class="width-fix container-modal-actions hidden" id="modal-actions">
                        <a href="#" data-pagamento="pix" data-icone="pix-icon">Pix</a>
                        <a href="#" data-pagamento="dinheiro" data-icone="dinheiro-icon">Dinheiro</a>
                        <a href="#" data-pagamento="cartao-credito" data-icone="cartao-credito-icon">Cartão de Crédito</a>
                        <a href="#" data-pagamento="cartao-debito" data-icone="cartao-debito-icon">Cartão de Débito</a>
                        <a class="color-red" id="remover" data-pagamento="remover" data-icone="remover-icon">Remover</a>
                    </div>


                    <div class="card card-address mt-2" id="cardforma" >
                        <div class="img-icon-details">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <div class="infos">
                            <p class="name mb-0"><b>Selecione uma forma de pagamento</b></p>
                        </div>
                        <div class="icon-edit" id="editar" >
                            <i class="fas fa-pencil-alt"></i>
                        </div>
                    </div>
                </div>
            </section>
        </section>

        <a type="button" class="btn btn-yellow btn-full" onclick="finalizarCompra()">
            Fazer pedido <span>R$ <?= $totalFormatado ?></span>
        </a>
        </form>
    <?php endif; ?>
</section>
<script>
    let produtos = [
    <?php foreach ($produtosCarrinho as $index => $produto): ?>
    {lista: '<?= $produto['quantidade'] ?> x <?= $produto['nome_produto'] ?>',
        preco: '<?= number_format($produto['preco_produto'], 2, ',', '.') ?>'}
        <?= ($index !== array_key_last($produtosCarrinho)) ? ',' : '' ?>
    <?php endforeach; ?>
];
    function finalizarCompra() {
            
            let endereco = document.getElementById("txtEndereco").value;
            let numero = document.getElementById("txtNumero").value;
            let complemento = document.getElementById("txtComplemento").value;
            let bairro = document.getElementById("txtBairro").value;
            let cidade = document.getElementById("txtCidade").value;
            let uf = document.getElementById("txtUf").value;
            let total = document.getElementById("valorTotal").value;
            let pagamento = document.getElementById('forma_pagamento').value;
            let descricao = document.getElementById('descricao').value;
            let nome = document.getElementById('nome').value;
            
            if (!endereco || !numero || !complemento || !bairro || !cidade || !uf || !pagamento || !total || !descricao || !nome){
                alert("Por favor, preencha seu nome e endereço.");
                return;
            }
            let listaProdutos = '';
        produtos.forEach(prod => {
            listaProdutos += `- ${prod.lista} | Preço: R$ ${prod.preco}\n`;
        });

            let mensagem = `*Itens:*
${listaProdutos}  
*Pagamento:*
- ${pagamento}
   -${descricao}
   
*Entrega:*
- Nome: ${nome}
- Endereço: ${endereco}, Nº ${numero} - ${complemento}, ${bairro}, ${cidade}-${uf}

*Total:* ${total}`;
            
            let numeroWhatsApp = "5592991515710"; // coloca o  número aqui pô
            let url = `https://wa.me/${numeroWhatsApp}?text=${encodeURIComponent(mensagem)}`;
            window.open(url, "_blank");
        }
    <!-- JavaScript para controlar a abertura e fechamento da modal -->
    
   
          //envio de mensagem
     
        // Abertura da modal ao clicar no card
        document.getElementById("abrirModal").addEventListener("click", function() {
            // Exibe o modal
            document.getElementById("modalEndereco").style.display = "flex";
            // Exibe o overlay (fundo escuro)
            document.getElementById("overlay").style.display = "block";
        });

        // Fechar a modal ao clicar no botão de fechar
        document.querySelector("[data-bs-dismiss='modal']").addEventListener("click", function() {
            // Oculta o modal
            document.getElementById("modalEndereco").style.display = "none";
            // Oculta o overlay
            document.getElementById("overlay").style.display = "none";
        });

        // Fechar a modal ao clicar no overlay
        document.getElementById("overlay").addEventListener("click", function() {
            // Oculta o modal
            document.getElementById("modalEndereco").style.display = "none";
            // Oculta o overlay
            document.getElementById("overlay").style.display = "none";
        });
document.addEventListener("DOMContentLoaded", function() {
    const cardSelect = document.getElementById('card-select');
    const modalActions = document.getElementById('modal-actions');
    const linksPagamento = modalActions.querySelectorAll('a');

 
    // Quando clicar em uma opção de pagamento
    linksPagamento.forEach(link => {
        link.addEventListener('click', function(event) {
            event.preventDefault();
            const formaPagamento = this.getAttribute('data-pagamento');

            if (formaPagamento === 'remover') {
                cardforma.innerHTML = `
                        <div class="infos">
                            <p class="name mb-0"><b>Selecione uma forma de pagamento</b></p>
                        </div>
                        <div class="icon-edit">
                            <i class="fas fa-pencil-alt"></i>
                        </div>
                `;
            } 
            else if(formaPagamento === 'pix') {
                cardforma.innerHTML =`
                       <div class="img-icon-details">
                            <i class="fas fa-qrcode"></i>
                        </div>
                        <div class="infos">
                            <input readonly style="border: 0px solid; outline: none;" id="forma_pagamento" class="name mb-0" value='${formaPagamento.replace('-', ' ').toUpperCase()}'>
                            <input readonly style="border: 0px solid; outline: none;" class="text mb-0" id="descricao" value="Levar QR Code">
                            </div>
                        <div class="icon-edit">
                            <i class="fas fa-pencil-alt"></i>
                        </div>
                
                `;
            }         
            else if(formaPagamento === 'dinheiro') {
                cardforma.innerHTML =`
                      <div class="img-icon-details">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="infos">
                            <input readonly style="border: 0px solid; outline: none;" id="forma_pagamento" class="name mb-0" value='${formaPagamento.replace('-', ' ').toUpperCase()}'>
                            <input readonly style="border: 0px solid; outline: none;" class="text mb-0" id="descricao" value="Levar Troco">
                        </div>
                        <div class="icon-edit">
                            <i class="fas fa-pencil-alt"></i>
                        </div>
                
                `;
            } else  {
                cardforma.innerHTML = `
                          <div class="img-icon-details">
                            <i class="fas fa-credit-card"></i>
                        </div>
                       <div class="infos">
                            <input readonly style="border: 0px solid; outline: none;" id="forma_pagamento" class="name mb-0" value='${formaPagamento.replace('-', ' ').toUpperCase()}'>
                            <input readonly style="border: 0px solid; outline: none;" class="text mb-0" id="descricao" value="Levar Maquininha">
                        </div>
                        <div class="icon-edit">
                            <i class="fas fa-pencil-alt"></i>
                        </div>
                `;
            }

            // Esconde o modal após seleção
            modalActions.classList.add('hidden');
            document.getElementById('txt_pagamento').style.display = 'none';
            document.getElementById('sub_txt_pagamento').style.display = 'none';
            document.getElementById('card-select').style.display = 'none';
        });
    });
});

    const icones = {
        pix: 'fas fa-qrcode',
        dinheiro: 'fas fa-money-bill-wave',
    };
    const icone = document.createElement('i');
                icone.className = iconeClasse;
                imgIconDetails.appendChild(icone);

        function salvarEndereco() {
    // Obtém os valores dos campos do modal
    var endereco = document.getElementById('txtEndereco').value;
    var bairro = document.getElementById('txtBairro').value;
    var numero = document.getElementById('txtNumero').value;
    var cidade = document.getElementById('txtCidade').value;
    var uf = document.getElementById('txtUf').value;
    var complemento = document.getElementById('txtComplemento').value;

    // Validação para garantir que os campos obrigatórios sejam preenchidos
    if (!endereco || !bairro || !numero || !cidade || !uf) {
        alert("Por favor, preencha todos os campos obrigatórios.");
        return;
    }

    // Concatenando o endereço (endereco, numero, bairro)
    var enderecoCompleto = `${endereco}, ${numero}, ${bairro}`;

    // Concatenando cidade/UF e complemento (se existir)
    var cidadeUf = `${cidade}-${uf} / 12345-678`;  // Ajuste o CEP se necessário
    if (complemento) {
        cidadeUf += ` ${complemento}`;
    }

    // Criando o cartão dinâmico no JavaScript com a estrutura fornecida
    var cardEndereco = document.createElement('div');
    cardEndereco.classList.add('card', 'card-address', 'mt-2');
    
    // Criando a parte do ícone do mapa
    var imgIconDetails = document.createElement('div');
    imgIconDetails.classList.add('img-icon-details');
    var iconMap = document.createElement('i');
    iconMap.classList.add('fas', 'fa-map-marked-alt');
    imgIconDetails.appendChild(iconMap);
    cardEndereco.appendChild(imgIconDetails);

    // Criando a parte das informações do endereço
    var infos = document.createElement('div');
    infos.classList.add('infos');
    var enderecoElement = document.createElement('p');
    enderecoElement.classList.add('name', 'mb-0');
    enderecoElement.innerHTML = `<b>${enderecoCompleto}</b>`;
    var cidadeUfElement = document.createElement('span');
    cidadeUfElement.classList.add('text', 'mb-0');
    cidadeUfElement.innerHTML = cidadeUf;
    infos.appendChild(enderecoElement);
    infos.appendChild(cidadeUfElement);
    cardEndereco.appendChild(infos);

    // Criando a parte do ícone de editar
    var iconEdit = document.createElement('div');
    iconEdit.classList.add('icon-edit');
    var editIcon = document.createElement('i');
    editIcon.classList.add('fas', 'fa-pencil-alt');
    iconEdit.appendChild(editIcon);
    cardEndereco.appendChild(iconEdit);

    // Encontrando o contêiner de dados preenchidos
    var dadosPreenchidosContainer = document.getElementById('dadosPreenchidos');

    // Limpar o conteúdo anterior
    dadosPreenchidosContainer.innerHTML = '';  // Limpa o conteúdo atual

    // Adiciona o novo cartão gerado no contêiner
    dadosPreenchidosContainer.appendChild(cardEndereco);

    // Escondendo o texto "Qual o seu endereço?" e a "card-select"
    document.getElementById('textoEndereco').style.display = 'none';
    document.getElementById('subTextoEndereco').style.display = 'none';
    document.getElementById('abrirModal').style.display = 'none';

    

    // Exibindo o novo cartão
    document.getElementById('cardEndereco').style.display = 'block';

    // Fechar o modal
    $('#modalEndereco').modal('hide');
}

    </script>

    <script type="text/javascript" src="./js/bootstrap.bundle.min.js"></script>
    <script type="text/javascript" src="./js/item.js"></script>

</body>
</html>

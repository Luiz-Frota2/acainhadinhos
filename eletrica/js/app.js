
document.addEventListener("DOMContentLoaded", function() {
    // Ícone de pesquisa
    const searchIcon = document.getElementById('search-icon');
    // Campo de pesquisa
    const searchInput = document.getElementById('search-input');
    // Links do menu
    const menuLinks = document.getElementById('menu-links');

    searchIcon.addEventListener('click', function() {
        // Verifica se o campo de pesquisa está oculto e alterna as classes
        if (searchInput.classList.contains('hidden')) {
            searchInput.classList.remove('hidden');
            setTimeout(() => {
                searchInput.classList.add('expanded');
            }, 10); // Atraso pequeno para garantir que a animação aconteça
        } else {
            searchInput.classList.remove('expanded');
            searchInput.classList.add('hidden');
        }

        // Alterna a visibilidade dos links
        if (searchInput.classList.contains('hidden')) {
            menuLinks.classList.remove('hidden'); // Exibe os links novamente
        } else {
            menuLinks.classList.add('hidden'); // Oculta os links
        }
    });
});

// Função para exibir produtos de uma categoria específica
function mostrarProdutos(idCategoria) {
    // Ocultar todos os produtos
    const todosProdutos = document.querySelectorAll('.produto');
    todosProdutos.forEach(produto => produto.style.display = 'none');

    // Exibir produtos da categoria selecionada
    const produtosCategoria = document.querySelectorAll(`.produto[data-categoria-id="${idCategoria}"]`);
    produtosCategoria.forEach(produto => produto.style.display = 'block');
}

// Função para exibir todos os produtos
function verMaisProdutos() {
    const todosProdutos = document.querySelectorAll('.produto');
    todosProdutos.forEach(produto => produto.style.display = 'block');
}

function redirecionarParaProduto(idProduto) {
    // Redireciona para a página item.php passando o id_produto na URL
    window.location.href = 'item.php?id_produto=' + idProduto;
}


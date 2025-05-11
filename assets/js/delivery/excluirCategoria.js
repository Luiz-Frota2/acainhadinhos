// Espera o DOM carregar
document.addEventListener('DOMContentLoaded', function () {
    // Captura todos os links de exclusão
    const deleteLinks = document.querySelectorAll('.delete-category');

    // Adiciona um evento de clique para cada link
    deleteLinks.forEach(link => {
        link.addEventListener('click', function (event) {
            // Evita o comportamento padrão de link
            event.preventDefault();

            // Captura o ID da categoria que está armazenado no atributo data-id
            const categoryId = link.getAttribute('data-id');

            // Preenche o campo oculto do formulário com o ID da categoria
            const categoryIdInput = document.getElementById('categoryId');
            categoryIdInput.value = categoryId;
        });
    });
});
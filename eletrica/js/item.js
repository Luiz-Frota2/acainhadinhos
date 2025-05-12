  // Seleciona o card, a modal e o link "Remover"
  const cardSelect = document.getElementById('card-select');
  const modalActions = document.getElementById('modal-actions');
  const remover = document.getElementById('remover');

  // Adiciona um evento de clique no card para mostrar ou esconder a modal
  cardSelect.addEventListener('click', function() {
      modalActions.classList.toggle('hidden');
  });

  // Adiciona um evento de clique no link "Remover" para fechar a modal
  remover.addEventListener('click', function() {
      modalActions.classList.add('hidden');
  });

 // Seleciona o card de endereço e a modal de endereço
  const cardSelect1 = document.getElementById('abrirModal');
  const modalEndereco = document.getElementById('modalEndereco');

  // Adiciona um evento de clique na div com id 'select' para abrir a modal
  cardSelect1.addEventListener('click', function() {
      modalEndereco.style.display = 'block'; // Exibe a modal
      modalEndereco.classList.add('show'); // Adiciona a classe 'show' para ativar a animação de fade (Bootstrap)
  });

  // Fecha a modal quando o botão "Fechar" for clicado
  const closeModalButton = document.querySelector('[data-bs-dismiss="modal"]');
  closeModalButton.addEventListener('click', function() {
      modalEndereco.style.display = 'none'; // Esconde a modal
      modalEndereco.classList.remove('show'); // Remove a classe 'show' para esconder a animação de fade
  });

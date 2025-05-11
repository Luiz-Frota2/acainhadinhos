/**
 * Main
 */

'use strict';

let menu, animate;

(function () {
  // Initialize menu
  //-----------------

  let layoutMenuEl = document.querySelectorAll('#layout-menu');
  layoutMenuEl.forEach(function (element) {
    menu = new Menu(element, {
      orientation: 'vertical',
      closeChildren: false
    });
    // Change parameter to true if you want scroll animation
    window.Helpers.scrollToActive((animate = false));
    window.Helpers.mainMenu = menu;
  });

  // Initialize menu togglers and bind click on each
  let menuToggler = document.querySelectorAll('.layout-menu-toggle');
  menuToggler.forEach(item => {
    item.addEventListener('click', event => {
      event.preventDefault();
      window.Helpers.toggleCollapsed();
    });
  });

  // Display menu toggle (layout-menu-toggle) on hover with delay
  let delay = function (elem, callback) {
    let timeout = null;
    elem.onmouseenter = function () {
      // Set timeout to be a timer which will invoke callback after 300ms (not for small screen)
      if (!Helpers.isSmallScreen()) {
        timeout = setTimeout(callback, 300);
      } else {
        timeout = setTimeout(callback, 0);
      }
    };

    elem.onmouseleave = function () {
      // Clear any timers set to timeout
      document.querySelector('.layout-menu-toggle').classList.remove('d-block');
      clearTimeout(timeout);
    };
  };
  if (document.getElementById('layout-menu')) {
    delay(document.getElementById('layout-menu'), function () {
      // not for small screen
      if (!Helpers.isSmallScreen()) {
        document.querySelector('.layout-menu-toggle').classList.add('d-block');
      }
    });
  }

  // Display in main menu when menu scrolls
  let menuInnerContainer = document.getElementsByClassName('menu-inner'),
    menuInnerShadow = document.getElementsByClassName('menu-inner-shadow')[0];
  if (menuInnerContainer.length > 0 && menuInnerShadow) {
    menuInnerContainer[0].addEventListener('ps-scroll-y', function () {
      if (this.querySelector('.ps__thumb-y').offsetTop) {
        menuInnerShadow.style.display = 'block';
      } else {
        menuInnerShadow.style.display = 'none';
      }
    });
  }

  // Init helpers & misc
  // --------------------

  // Init BS Tooltip
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });

  // Accordion active class
  const accordionActiveFunction = function (e) {
    if (e.type == 'show.bs.collapse' || e.type == 'show.bs.collapse') {
      e.target.closest('.accordion-item').classList.add('active');
    } else {
      e.target.closest('.accordion-item').classList.remove('active');
    }
  };

  const accordionTriggerList = [].slice.call(document.querySelectorAll('.accordion'));
  const accordionList = accordionTriggerList.map(function (accordionTriggerEl) {
    accordionTriggerEl.addEventListener('show.bs.collapse', accordionActiveFunction);
    accordionTriggerEl.addEventListener('hide.bs.collapse', accordionActiveFunction);
  });

  // Auto update layout based on screen size
  window.Helpers.setAutoUpdate(true);

  // Toggle Password Visibility
  window.Helpers.initPasswordToggle();

  // Speech To Text
  window.Helpers.initSpeechToText();

  // Manage menu expanded/collapsed with templateCustomizer & local storage
  //------------------------------------------------------------------

  // If current layout is horizontal OR current window screen is small (overlay menu) than return from here
  if (window.Helpers.isSmallScreen()) {
    return;
  }

  // If current layout is vertical and current window screen is > small

  // Auto update menu collapsed/expanded based on the themeConfig
  window.Helpers.setCollapsed(true, false);
})();




// Captura o ícone de excluir
// Adiciona o evento de clique no ícone de excluir
const deleteIcon = document.getElementById('deleteIcon');

deleteIcon.addEventListener('click', function(event) {
  // Impede que o evento de clique se propague para o acordeão ou outros elementos
  event.stopPropagation();

  // Exibe o toast de confirmação
  var toastEl = new bootstrap.Toast(document.getElementById('deleteToast'));
  toastEl.show();
});



// Cancelar exclusão ao clicar em "Não"
document.getElementById('cancelDelete').addEventListener('click', function() {
  // Apenas fecha o toast, sem fazer nada
  var toastEl = new bootstrap.Toast(document.getElementById('deleteToast'));
  toastEl.hide();
});


// Adicionar evento de clique no link "Adicionar nova categoria"
document.getElementById('addCategoryLink').addEventListener('click', function(event) {
  event.preventDefault(); // Impede o link de redirecionar
  
  // Exibe o toast com o input
  var toastEl = new bootstrap.Toast(document.getElementById('categoryToast'));
  toastEl.show();
  
  // Aqui não estamos escondendo a div original "Adicionar nova categoria"
  // A div permanece visível
});

// Adiciona o evento de clique no ícone de excluir
const deleteIconProduto = document.getElementById('deleteIconProduto');

deleteIcon.addEventListener('click', function(event) {
  // Impede que o evento de clique se propague para o acordeão ou outros elementos
  event.stopPropagation();

  // Exibe o toast de confirmação
  var toastEl = new bootstrap.Toast(document.getElementById('deleteToastProduto'));
  toastEl.show();
});

// Adicionar evento para confirmação de exclusão
document.getElementById('confirmDelete').addEventListener('click', function() {
  // Aqui você pode adicionar a lógica para excluir o produto, por exemplo, uma requisição para a API

  // Esconde o toast após a exclusão
  var toastEl = new bootstrap.Toast(document.getElementById('deleteToastProduto'));
  toastEl.hide();

  // Ação de exclusão (exemplo de log ou redirecionamento)
  alert("Produto excluído com sucesso!");
});

// Cancelar exclusão ao clicar em "Não"
document.getElementById('cancelDelete').addEventListener('click', function() {
  // Apenas fecha o toast, sem fazer nada
  var toastEl = new bootstrap.Toast(document.getElementById('deleteToast'));
  toastEl.hide();
});
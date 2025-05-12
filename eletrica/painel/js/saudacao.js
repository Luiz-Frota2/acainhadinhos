function saudacao() {
    let hora = new Date().getHours();
    
    if (hora >= 5 && hora < 12) {
      return "Olá, Bom dia!";
    } else if (hora >= 12 && hora < 18) {
      return "Olá, Boa tarde!";
    } else {
      return "Olá, Boa noite!";
    }
  }

  // Modifica o texto do elemento com a classe "saudacao"
  document.querySelector('.saudacao').textContent = saudacao();
function saudacao(setor) {
  const hora = new Date().getHours();
  let saudacao;

  if (hora >= 5 && hora < 12) {
      saudacao = "Olá, bom dia!";
  } else if (hora >= 12 && hora < 18) {
      saudacao = "Olá, boa tarde!";
  } else {
      saudacao = "Olá, boa noite!";
  }

  return `${saudacao} Seja bem-vindo ao ${setor}!`;
}

// Obtém o setor a partir do atributo data-setor no HTML
const elementoSaudacao = document.querySelector('.saudacao');
if (elementoSaudacao) {
  const setor = elementoSaudacao.dataset.setor || ""; // Padrão caso não tenha setor definido
  elementoSaudacao.textContent = saudacao(setor);
}

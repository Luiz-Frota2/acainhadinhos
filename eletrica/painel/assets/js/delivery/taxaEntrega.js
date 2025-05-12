document.addEventListener("DOMContentLoaded", function () {
    function handleToggleChange(toggleId, labelId, divClass) {
        const toggle = document.getElementById(toggleId);
        const label = document.getElementById(labelId);

        // Verifique se o toggle e o label existem no DOM antes de adicionar o event listener
        if (toggle && label) {
            toggle.addEventListener("change", function () {
                if (this.checked) {
                    label.textContent = "Ligado";
                    // Se divClass for fornecido, manipula as divs correspondentes
                    if (divClass) {
                        const divs = document.querySelectorAll(divClass);
                        divs.forEach(div => div.classList.remove("hidden"));
                    }
                } else {
                    label.textContent = "Desligado";
                    // Se divClass for fornecido, manipula as divs correspondentes
                    if (divClass) {
                        const divs = document.querySelectorAll(divClass);
                        divs.forEach(div => div.classList.add("hidden"));
                    }
                }
            });
        }
    }

    // Chame a função para os diferentes toggles
    handleToggleChange("toggleEntrega", "labelToggleEntrega", ".taxa");
    handleToggleChange("toggleRetirada", "labelToggle", ".inputemp");
    handleToggleChange("toggleTaxa", "labelToggleTaxa", ".card-taxa");
    handleToggleChange("toggleSemTaxa", "labelSemTaxa"); 
    handleToggleChange("opcaoDinheiro", "labelToggle1"); 
    handleToggleChange("opcaoPix", "labelToggle2"); 
    handleToggleChange("opcaoCartaoDebito", "labelToggle3"); 
    handleToggleChange("opcaoCartaoCretido", "labelToggle4"); 
});

function previewImage(event) {
    var input = event.target;
    
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        
        reader.onload = function (e) {
            var previewImg = document.getElementById('previewImg');
            previewImg.src = e.target.result;
            previewImg.style.display = 'block'; // Garante que a imagem fique visível
        };
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Função para alternar a visibilidade do 'funtionCard' ao clicar no 'addHorario'
document.getElementById("addHorario").addEventListener("click", function() {
    var funtionCard = document.getElementById("funtionCard"); // Seleciona o elemento com a class 'funtionCard'
    
    // Verifica se o 'funtionCard' está escondido (com a classe 'hidden')
    if (funtionCard.classList.contains("hidden")) {
        funtionCard.classList.remove("hidden"); // Remove a classe 'hidden' para exibir
    } else {
        funtionCard.classList.add("hidden"); // Adiciona a classe 'hidden' para ocultar
    }
});

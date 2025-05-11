document.addEventListener("DOMContentLoaded", function () {
    const cepInput = document.getElementById("basic-default-cep");

    if (cepInput) {
        // Formata o CEP enquanto digita
        cepInput.addEventListener("input", function () {
            formatarCep(this);
        });

        // Busca automaticamente quando o CEP estiver completo
        cepInput.addEventListener("keyup", function () {
            let cepLimpo = this.value.replace(/\D/g, ''); // Remove caracteres não numéricos
            if (cepLimpo.length === 8) {
                buscarCep(cepLimpo);
            }
        });
    }
});

// Função para formatar o CEP (ex: 69460000 → 69460-000)
function formatarCep(campo) {
    let cep = campo.value.replace(/\D/g, ''); // Remove caracteres não numéricos

    if (cep.length > 5) {
        campo.value = cep.substring(0, 5) + '-' + cep.substring(5, 8);
    } else {
        campo.value = cep;
    }
}

// Função para buscar o CEP e preencher os campos automaticamente
function buscarCep(cep) {
    fetch(`https://viacep.com.br/ws/${cep}/json/`)
        .then(response => response.json())
        .then(data => {
            if (!data.erro) {
                document.getElementById("basic-default-cidade").value = data.localidade || "";
                document.getElementById("basic-default-uf").value = data.uf || "";
                document.getElementById("basic-default-endereco").value = data.logradouro || "";
                document.getElementById("basic-default-bairro").value = data.bairro || "";
            } else {
                alert("CEP não encontrado!");
            }
        })
        .catch(() => alert("Erro ao buscar o CEP!"));
}

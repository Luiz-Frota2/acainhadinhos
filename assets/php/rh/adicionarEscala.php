<?php
// Inclui o arquivo de conexão
require '../conexao.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recupera os dados do formulário
    $nomeEscala = trim($_POST["nome_escala"]);
    $idSelecionado = $_POST["id_selecionado"];  // Recupera o ID selecionado do campo oculto

    try {
        // Preparar a query SQL para inserir os dados na tabela escalas
        $sql = "INSERT INTO escalas (nome_escala, empresa_id) VALUES (:nome_escala, :empresa_id)";
        $stmt = $pdo->prepare($sql);
        
        // Vincula os parâmetros
        $stmt->bindParam(":nome_escala", $nomeEscala, PDO::PARAM_STR);
        $stmt->bindParam(":empresa_id", $idSelecionado, PDO::PARAM_STR);  // Passa o ID da empresa (principal ou filial)

        // Executar e exibir mensagem de sucesso
        if ($stmt->execute()) {
            echo "<script>
                    alert('Escala cadastrada com sucesso!');
                    window.location.href = '../../../erp/rh/escalaAdicionadas.php?id=" . urlencode($idSelecionado) . "';
                  </script>";
            exit();
        } else {
            echo "<script>
                    alert('Erro ao cadastrar escala.');
                    history.back();
                  </script>";
        }
    } catch (PDOException $e) {
        echo "<script>
                alert('Erro no banco de dados: " . addslashes($e->getMessage()) . "');
                history.back();
              </script>";
    }
}
?>



<?php
// Inclusão da conexão externa
require_once '../conexao.php';

// Verificar se os dados do formulário foram enviados
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Pegando os dados do formulário
    $nomeCategoria = $_POST['nomeCategoria'];
    $iconeCategoria = $_POST['iconeCategoria']; // Obter o ícone selecionado

    // Verificar se o campo nomeCategoria não está vazio
    if (!empty($nomeCategoria) && !empty($iconeCategoria)) {
        try {
            // Verificar se a categoria já existe no banco de dados
            $sqlCheck = "SELECT COUNT(*) FROM adicionarcategoria WHERE nome_categoria = :nomeCategoria";
            $stmtCheck = $pdo->prepare($sqlCheck);
            $stmtCheck->bindParam(':nomeCategoria', $nomeCategoria, PDO::PARAM_STR);
            $stmtCheck->execute();

            // Obter o resultado da contagem
            $categoriaExistente = $stmtCheck->fetchColumn();

            // Se a categoria já existe, retornar uma mensagem
            if ($categoriaExistente > 0) {
                // Exibe a mensagem de erro e volta para a página anterior
                echo '<script>alert("Já existe uma categoria cadastrada com esse nome!"); history.back();</script>';
            } else {
                // Preparar a consulta SQL para inserir os dados no banco de dados
                $sql = "INSERT INTO adicionarcategoria (nome_categoria, icone_categoria) VALUES (:nomeCategoria, :iconeCategoria)";
                
                // Preparar a declaração
                $stmt = $pdo->prepare($sql);
                
                // Vincular os parâmetros
                $stmt->bindParam(':nomeCategoria', $nomeCategoria, PDO::PARAM_STR);
                $stmt->bindParam(':iconeCategoria', $iconeCategoria, PDO::PARAM_STR);

                // Executar a declaração
                $stmt->execute();

                // Se a inserção for bem-sucedida, redirecionar ou exibir mensagem de sucesso
                echo '<script>alert("Categoria adicionada com sucesso!"); window.location.href = "../../../produtoAdicionados.php";</script>';
            }

        } catch (PDOException $e) {
            // Captura o erro e exibe a mensagem
            echo "Erro ao adicionar a categoria: " . $e->getMessage();
        }
    } else {
        echo "Por favor, preencha todos os campos.";
    }
}
?>

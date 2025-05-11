<?php

// Incluir o arquivo de conexão com o banco de dados
include '../conexao.php'; // Altere o caminho conforme necessário

// Verificar se os parâmetros 'id' e 'idSelecionado' estão na URL
if (isset($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['idSelecionado'])) {
    // Capturar o ID da URL
    $id = (int) $_GET['id'];  // Garantir que o ID seja um número inteiro
    $idSelecionado = $_GET['idSelecionado'];  // Captura o idSelecionado

    try {
        // Iniciar a transação para garantir integridade dos dados
        $pdo->beginTransaction();

        // 1. Deletar as opções relacionadas da tabela opcionais_opcoes
        $stmt_opcoes = $pdo->prepare("DELETE FROM opcionais_opcoes WHERE id_selecao = :id");
        $stmt_opcoes->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt_opcoes->execute();

        // 2. Deletar a seleção da tabela opcionais_selecoes
        $stmt_selecao = $pdo->prepare("DELETE FROM opcionais_selecoes WHERE id = :id");
        $stmt_selecao->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt_selecao->execute();

        // Se ambas as queries foram executadas com sucesso, comitar a transação
        $pdo->commit();

        // Sucesso na exclusão, exibir mensagem e redirecionar, incluindo o idSelecionado na URL
        echo "<script>
                alert('Seleção e suas opções foram excluídas com sucesso!');
                window.location.href = '../../../erp/delivery/produtoAdicionados.php?id=" . urlencode($idSelecionado) . "'; // Redireciona com o idSelecionado
              </script>";
        exit();
    } catch (PDOException $e) {
        // Se ocorrer algum erro, reverter a transação
        $pdo->rollBack();
        
        // Exibir mensagem de erro
        echo "<script>
                alert('Erro ao excluir a seleção ou as opções. Tente novamente!');
                window.history.back(); // Voltar para a página anterior
              </script>";
    }
} else {
    // Se o ID ou o idSelecionado não estiver presente ou forem inválidos
    echo "<script>
            alert('ID inválido ou ID Selecionado não fornecido!');
            window.history.back(); // Voltar para a página anterior
          </script>";
}
?>

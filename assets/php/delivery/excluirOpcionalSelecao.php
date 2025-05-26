<?php
// Incluir o arquivo de conexão com o banco de dados
include '../conexao.php'; // Altere o caminho conforme necessário

// Verificar se os parâmetros 'id' e 'idSelecionado' estão na URL
if (isset($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['idSelecionado'])) {
    // Capturar o ID da URL
    $id = (int) $_GET['id'];  // Garantir que o ID seja um número inteiro
    $idSelecionado = $_GET['idSelecionado'];  // Captura o idSelecionado

    try {
        // Preparar a consulta para excluir o registro
        $stmt = $pdo->prepare("DELETE FROM opcionais_opcoes WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        // Executar a consulta
        if ($stmt->execute()) {
            // Sucesso na exclusão, exibir mensagem e redirecionar com idSelecionado
            echo "<script>
                    alert('O opcional foi excluído com sucesso!');
                    window.location.href = '../../../erp/delivery/produtoAdicionados.php?id=" . urlencode($idSelecionado) . "'; // Redireciona com o idSelecionado
                  </script>";
            exit();
        } else {
            echo "<script>
                    alert('Erro ao excluir o opcional. Tente novamente!');
                    window.history.back(); // Voltar para a página anterior
                  </script>";
        }
    } catch (PDOException $e) {
        // Tratar erro de conexão
        echo "<script>
                alert('Erro de conexão com o banco de dados: " . addslashes($e->getMessage()) . "');
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


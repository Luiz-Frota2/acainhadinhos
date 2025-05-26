<?php
// Incluir o arquivo de conexão com o banco
require '../conexao.php';

// Verificar se o parâmetro id e empresa_id estão presentes na URL
if (isset($_GET['id']) && isset($_GET['empresa_id'])) {
    $idEstoque = $_GET['id'];
    $empresaId = $_GET['empresa_id'];

    // Validar se o id do estoque é numérico e se o empresa_id é uma string válida
    if (is_numeric($idEstoque) && !empty($empresaId)) {
        try {
            // Preparar a query para excluir o produto do estoque
            $sql = "DELETE FROM estoque WHERE id = :idEstoque AND empresa_id = :empresaId";
            $stmt = $pdo->prepare($sql);

            // Bind dos parâmetros
            $stmt->bindParam(':idEstoque', $idEstoque, PDO::PARAM_INT);
            $stmt->bindParam(':empresaId', $empresaId, PDO::PARAM_STR);

            // Executar a exclusão
            if ($stmt->execute()) {
                // Redirecionar após a exclusão
                echo "<script>
                        alert('Produto excluído com sucesso!');
                        window.location.href = '../../../erp/pdv/produtosAdicionados.php?id=" . urlencode($empresaId) . "';
                      </script>";
            } else {
                // Caso o delete falhe
                echo "<script>
                        alert('Erro ao excluir o produto.');
                        history.back();
                      </script>";
            }
        } catch (PDOException $e) {
            // Captura erros no banco de dados
            echo "<script>
                    alert('Erro no banco de dados: " . addslashes($e->getMessage()) . "');
                    history.back();
                  </script>";
        }
    } else {
        // Se os parâmetros não forem válidos
        echo "<script>
                alert('Parâmetros inválidos.');
                history.back();
              </script>";
    }
} else {
    // Caso os parâmetros não sejam encontrados
    echo "<script>
            alert('Parâmetros ausentes.');
            history.back();
          </script>";
}
?>

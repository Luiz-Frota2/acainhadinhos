<?php

require_once '../conexao.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_categoria']) && isset($_POST['nome_categoria'])) {
    $id_categoria = intval($_POST['id_categoria']);
    $novo_nome_categoria = trim($_POST['nome_categoria']);

    try {
        // Verifica se o novo nome já existe em outra categoria
        $sql = "SELECT COUNT(*) FROM adicionarcategoria WHERE nome_categoria = ? AND id_categoria != ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$novo_nome_categoria, $id_categoria]);
        $existe = $stmt->fetchColumn();

        if ($existe > 0) {
            // Se o nome já existir, retorna uma mensagem e impede a atualização
            echo "<script>
                    alert('Erro: Já existe uma categoria com este nome!');
                    window.history.back();
                  </script>";
            exit();
        }

        // Atualiza o nome da categoria no banco de dados
        $sql = "UPDATE adicionarcategoria SET nome_categoria = ? WHERE id_categoria = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$novo_nome_categoria, $id_categoria]);

        // Exibe mensagem de sucesso e redireciona
        echo "<script>
                alert('Categoria atualizada com sucesso!');
                window.location.href='../../../produtoAdicionados.php';
              </script>";
        exit();
    } catch (Exception $e) {
        echo "<script>
                alert('Erro ao atualizar categoria: " . addslashes($e->getMessage()) . "');
                window.history.back();
              </script>";
        exit();
    }
} else {
    echo "<script>
            alert('Dados inválidos para atualização!');
            window.history.back();
          </script>";
    exit();
}
?>

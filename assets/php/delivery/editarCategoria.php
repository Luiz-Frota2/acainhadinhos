<?php

require_once '../conexao.php';

if (
    $_SERVER["REQUEST_METHOD"] == "POST" &&
    isset($_POST['id_categoria']) &&
    isset($_POST['nome_categoria']) &&
    isset($_POST['idSelecionado'])
) {
    $id_categoria = intval($_POST['id_categoria']);
    $novo_nome_categoria = trim($_POST['nome_categoria']);
    $idSelecionado = $_POST['idSelecionado'];

    try {
        // Verifica se o novo nome já existe em outra categoria da mesma empresa e tipo
        $sql = "SELECT COUNT(*) FROM adicionarCategoria WHERE nome_categoria = ? AND id_categoria != ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$novo_nome_categoria, $id_categoria]);
        $existe = $stmt->fetchColumn();

        if ($existe > 0) {
            echo "<script>
                    alert('Erro: Já existe uma categoria com este nome!');
                    window.history.back();
                  </script>";
            exit();
        }

        // Atualiza o nome da categoria
        $sql = "UPDATE adicionarCategoria SET nome_categoria = ? WHERE id_categoria = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$novo_nome_categoria, $id_categoria]);

        // Redireciona com o ID da empresa (principal/filial)
        echo "<script>
                alert('Categoria atualizada com sucesso!');
                window.location.href='../../../erp/delivery/produtoAdicionados.php?id=" . urlencode($idSelecionado) . "';
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

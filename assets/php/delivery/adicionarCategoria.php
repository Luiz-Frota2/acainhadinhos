<?php

require_once '../conexao.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nomeCategoria = trim($_POST['nomeCategoria'] ?? '');
    $idSelecionado = $_POST['id'] ?? '';

    // Validação básica
    if (empty($nomeCategoria) || empty($idSelecionado)) {
        echo '<script>alert("Preencha todos os campos!"); history.back();</script>';
        exit;
    }

    // Determina tipo e ID da empresa
    if (str_starts_with($idSelecionado, 'principal_')) {
        $empresa_id = 1;
        $tipo = 'principal';
    } elseif (str_starts_with($idSelecionado, 'filial_')) {
        $empresa_id = (int) str_replace('filial_', '', $idSelecionado);
        $tipo = 'filial';
    } else {
        echo "<script>alert('Empresa não identificada!'); history.back();</script>";
        exit;
    }

    try {
        // Verificar se já existe uma categoria com esse nome para a mesma empresa
        $sqlCheck = "SELECT COUNT(*) FROM adicionarCategoria 
                     WHERE nome_categoria = :nomeCategoria AND empresa_id = :empresa_id AND tipo = :tipo";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->bindParam(':nomeCategoria', $nomeCategoria);
        $stmtCheck->bindParam(':empresa_id', $empresa_id);
        $stmtCheck->bindParam(':tipo', $tipo);
        $stmtCheck->execute();

        $categoriaExistente = $stmtCheck->fetchColumn();

        if ($categoriaExistente > 0) {
            echo '<script>alert("Já existe uma categoria com esse nome para esta empresa!"); history.back();</script>';
        } else {
            // Inserção com empresa
            $sql = "INSERT INTO adicionarCategoria (nome_categoria, empresa_id, tipo) 
                    VALUES (:nomeCategoria, :empresa_id, :tipo)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':nomeCategoria', $nomeCategoria);
            $stmt->bindParam(':empresa_id', $empresa_id);
            $stmt->bindParam(':tipo', $tipo);
            $stmt->execute();

            echo "<script>alert('Categoria adicionada com sucesso!'); 
                  window.location.href = '../../../erp/delivery/produtoAdicionados.php?id={$idSelecionado}';</script>";
        }

    } catch (PDOException $e) {
        echo "Erro ao adicionar a categoria: " . $e->getMessage();
    }
}
?>

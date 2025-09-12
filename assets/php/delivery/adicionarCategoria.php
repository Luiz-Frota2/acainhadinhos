<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once '../conexao.php';

/* Fallback para PHP < 8 */
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return $needle !== '' && strpos($haystack, $needle) === 0;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nomeCategoria = trim($_POST['nomeCategoria'] ?? '');
    // No formulário, o campo vem como "id"
    $idSelecionado = trim($_POST['id'] ?? '');

    // Validação básica
    if ($nomeCategoria === '' || $idSelecionado === '') {
        echo '<script>alert("Preencha todos os campos!"); history.back();</script>';
        exit;
    }

    // Determina tipo e chave da empresa
    // Agora usamos o slug completo (ex.: "principal_1", "unidade_2", "filial_3", "franquia_7")
    $empresa_id = $idSelecionado; // gravamos exatamente o slug
    if (str_starts_with($idSelecionado, 'principal_')) {
        $tipo = 'principal';
    } elseif (str_starts_with($idSelecionado, 'filial_')) {
        $tipo = 'filial';
    } elseif (str_starts_with($idSelecionado, 'unidade_')) {
        $tipo = 'unidade';
    } elseif (str_starts_with($idSelecionado, 'franquia_')) {
        $tipo = 'franquia';
    } else {
        echo "<script>alert('Empresa não identificada!'); history.back();</script>";
        exit;
    }

    try {
        // Verificar duplicidade (case-insensitive) por empresa + tipo
        $sqlCheck = "SELECT COUNT(*) 
                       FROM adicionarCategoria 
                      WHERE empresa_id = :empresa_id 
                        AND tipo = :tipo
                        AND TRIM(LOWER(nome_categoria)) = TRIM(LOWER(:nomeCategoria))";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->bindParam(':empresa_id', $empresa_id, PDO::PARAM_STR);
        $stmtCheck->bindParam(':tipo', $tipo, PDO::PARAM_STR);
        $stmtCheck->bindParam(':nomeCategoria', $nomeCategoria, PDO::PARAM_STR);
        $stmtCheck->execute();

        if ((int)$stmtCheck->fetchColumn() > 0) {
            echo '<script>alert("Já existe uma categoria com esse nome para esta empresa!"); history.back();</script>';
            exit;
        }

        // Inserção
        $sqlIns = "INSERT INTO adicionarCategoria (nome_categoria, empresa_id, tipo) 
                   VALUES (:nomeCategoria, :empresa_id, :tipo)";
        $stmt = $pdo->prepare($sqlIns);
        $stmt->bindParam(':nomeCategoria', $nomeCategoria, PDO::PARAM_STR);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_STR);
        $stmt->bindParam(':tipo', $tipo, PDO::PARAM_STR);
        $stmt->execute();

        echo "<script>
                alert('Categoria adicionada com sucesso!');
                window.location.href = '../../../erp/delivery/produtoAdicionados.php?id=" . rawurlencode($idSelecionado) . "';
              </script>";
        exit;

    } catch (PDOException $e) {
        // Log opcional
        error_log('Erro ao adicionar categoria: ' . $e->getMessage());
        echo "<script>alert('Erro ao adicionar a categoria: " . addslashes($e->getMessage()) . "'); history.back();</script>";
        exit;
    }
} else {
    echo '<script>alert("Requisição inválida."); history.back();</script>';
    exit;
}
?>
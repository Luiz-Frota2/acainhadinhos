<?php
session_start();

// Inclui o arquivo de conexão PDO externo
require_once '../conexao.php';

// Verifica se o método de requisição é POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Recebe os dados do formulário
        $empresa_id = filter_input(INPUT_POST, 'empresa_id', FILTER_SANITIZE_STRING);
        $fornecedor = filter_input(INPUT_POST, 'fornecedor', FILTER_SANITIZE_STRING);
        $produto = filter_input(INPUT_POST, 'produto', FILTER_SANITIZE_STRING);
        $quantidade = filter_input(INPUT_POST, 'quantidade', FILTER_SANITIZE_NUMBER_INT);
        $valor = filter_input(INPUT_POST, 'valor', FILTER_SANITIZE_STRING);
        $dataPedido = filter_input(INPUT_POST, 'dataPedido', FILTER_SANITIZE_STRING);
        $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

        // Validações básicas
        if (empty($empresa_id)) {
            echo "<script>alert('Empresa não identificada.'); history.back();</script>";
            exit();
        }

        if (empty($fornecedor) || empty($produto) || empty($quantidade) || empty($valor) || empty($dataPedido) || empty($status)) {
            echo "<script>alert('Todos os campos são obrigatórios.'); history.back();</script>";
            exit();
        }

        // Formata o valor para o padrão do banco de dados (substitui ponto de milhar e usa ponto decimal)
        $valorFormatado = str_replace(['.', ','], ['', '.'], $valor);

        // Prepara a query SQL para inserção
        $sql = "INSERT INTO pedidos (empresa_id, fornecedor, produto, quantidade, valor, data_pedido, status) 
                VALUES (:empresa_id, :fornecedor, :produto, :quantidade, :valor, :dataPedido, :status)";
        
        $stmt = $pdo->prepare($sql);

        // Bind dos parâmetros
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_STR); // Agora tratado como string
        $stmt->bindParam(':fornecedor', $fornecedor, PDO::PARAM_STR);
        $stmt->bindParam(':produto', $produto, PDO::PARAM_STR);
        $stmt->bindParam(':quantidade', $quantidade, PDO::PARAM_INT);
        $stmt->bindParam(':valor', $valorFormatado);
        $stmt->bindParam(':dataPedido', $dataPedido, PDO::PARAM_STR);
        $stmt->bindParam(':status', $status, PDO::PARAM_STR);

        // Executa a query
        if ($stmt->execute()) {
            // Obtém o ID do último pedido inserido
            $lastInsertId = $pdo->lastInsertId();

            // Retorna mensagem de sucesso com o ID do pedido
            echo "<script>
                alert('Pedido adicionado com sucesso!');
                window.location.href = '../../../erp/financas/gestaoPedidos.php?id=$empresa_id';
            </script>";
            exit();
        } else {
            throw new Exception("Erro ao executar a query.");
        }
    } catch (Exception $e) {
        // Retorna mensagem de erro
        echo "<script>alert('Erro ao adicionar pedido: " . addslashes($e->getMessage()) . "'); history.back();</script>";
        exit();
    }
} else {
    // Se não for POST, retorna mensagem de erro
    echo "<script>alert('Método de requisição inválido.'); history.back();</script>";
    exit();
}
?>

<?php
session_start();

// Inclui a conexão com o banco de dados
require_once('../conexao.php');

// Verifica se o formulário foi enviado via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Recebe e limpa os dados do formulário
        $empresa_id         = trim($_POST['empresa_id']);
        $nome_fornecedor    = trim($_POST['nome_fornecedor']);
        $cnpj_fornecedor    = trim($_POST['cnpj_fornecedor']);
        $email_fornecedor   = trim($_POST['email_fornecedor']);
        $telefone_fornecedor= trim($_POST['telefone_fornecedor']);
        $endereco_fornecedor= trim($_POST['endereco_fornecedor']);

        // Validação básica (opcional, mas recomendado)
        if (empty($empresa_id) || empty($nome_fornecedor) || empty($cnpj_fornecedor)) {
            throw new Exception('Preencha todos os campos obrigatórios.');
        }

        // Prepara a query SQL para inserção
        $sql = "INSERT INTO fornecedores (
                    empresa_id, nome_fornecedor, cnpj_fornecedor, email_fornecedor, telefone_fornecedor, endereco_fornecedor
                ) VALUES (
                    :empresa_id, :nome_fornecedor, :cnpj_fornecedor, :email_fornecedor, :telefone_fornecedor, :endereco_fornecedor
                )";

        // Prepara a declaração
        $stmt = $pdo->prepare($sql);

        // Faz o bind dos parâmetros
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->bindParam(':nome_fornecedor', $nome_fornecedor);
        $stmt->bindParam(':cnpj_fornecedor', $cnpj_fornecedor);
        $stmt->bindParam(':email_fornecedor', $email_fornecedor);
        $stmt->bindParam(':telefone_fornecedor', $telefone_fornecedor);
        $stmt->bindParam(':endereco_fornecedor', $endereco_fornecedor);

        // Executa a query
        if ($stmt->execute()) {
            echo '<script>
                alert("Fornecedor adicionado com sucesso!");
                window.location.href = "../../../erp/estoque/fornecedoresAdicionados.php?id=' . urlencode($empresa_id) . '";
            </script>';
        } else {
            throw new Exception("Erro ao executar a inserção.");
        }

    } catch (Exception $e) {
        echo '<script>
            alert("Erro: ' . str_replace('"', "'", $e->getMessage()) . '");
            history.back();
        </script>';
    }
} else {
    echo '<script>
        alert("Acesso inválido.");
        history.back();
    </script>';
}
exit();
?>

<?php
require_once '../conexao.php';

// Verifica se a requisição é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<script>alert('Acesso inválido!'); history.back();</script>";
    exit();
}

// Valida os dados do formulário
$empresa_destino = $_POST['id_selecionado'] ?? '';
$empresa_origem = $_POST['empresa_origem'] ?? '';
$produto_id = $_POST['produto'] ?? 0;
$quantidade = $_POST['quantidade'] ?? 0;
$justificativa = $_POST['justificativa'] ?? '';

// Sanitização básica
$empresa_destino = htmlspecialchars(strip_tags($empresa_destino));
$empresa_origem = htmlspecialchars(strip_tags($empresa_origem));
$produto_id = (int)$produto_id;
$quantidade = (int)$quantidade;
$justificativa = htmlspecialchars(strip_tags($justificativa));

// Validação dos dados
if (empty($empresa_destino) || empty($empresa_origem) || $produto_id <= 0 || $quantidade <= 0 || empty($justificativa)) {
    echo "<script>alert('Todos os campos são obrigatórios!'); history.back();</script>";
    exit();
}

try {
    // Verifica se o produto existe na empresa de origem
    $sqlVerifica = "SELECT quantidade_produto FROM produtos_estoque 
                   WHERE id = ? AND empresa_id = ?";
    $stmtVerifica = $pdo->prepare($sqlVerifica);
    $stmtVerifica->execute([$produto_id, $empresa_origem]);
    $produto = $stmtVerifica->fetch(PDO::FETCH_ASSOC);

    if (!$produto) {
        echo "<script>alert('Produto não encontrado na empresa de origem!'); history.back();</script>";
        exit();
    }

    if ($produto['quantidade_produto'] < $quantidade) {
        echo "<script>alert('Quantidade indisponível! Disponível: ".$produto['quantidade_produto']."'); history.back();</script>";
        exit();
    }

    // Insere a solicitação
    $sqlInsert = "INSERT INTO solicitacoes_produtos (
                    empresa_origem, 
                    empresa_destino, 
                    produto_id, 
                    quantidade, 
                    justificativa, 
                    status,
                    data_solicitacao
                 ) VALUES (?, ?, ?, ?, ?, 'pendente', NOW())";

    $stmtInsert = $pdo->prepare($sqlInsert);
    $success = $stmtInsert->execute([
        $empresa_origem,
        $empresa_destino,
        $produto_id,
        $quantidade,
        $justificativa
    ]);

    if ($success) {
        // Redireciona com empresa_id na URL
        $redirectUrl = '../../../erp/matriz/solicitarProduto.php?id=' . urlencode($empresa_destino);
        echo "<script>alert('Solicitação registrada com sucesso!'); 
              window.location.href='".$redirectUrl."';</script>";
    } else {
        echo "<script>alert('Erro ao registrar solicitação!'); history.back();</script>";
    }

} catch (PDOException $e) {
    echo "<script>alert('Erro no sistema: ".addslashes($e->getMessage())."'); history.back();</script>";
    exit();
}

?>
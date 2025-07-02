<?php
require_once '../conexao.php';

// Verifica se os dados foram enviados via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['acao'])) {
    echo "<script>alert('Requisição inválida!'); history.back();</script>";
    exit;
}

// Captura os dados do formulário
$solicitacao_id = $_POST['id'] ?? null;
$acao = $_POST['acao'] ?? '';
$resposta = trim($_POST['resposta'] ?? '');
$empresa_destino = $_POST['empresa_destino'] ?? '';
$id_selecionado = $_POST['id_selecionado'] ?? '';

// Validações básicas
if (empty($solicitacao_id)) {
    echo "<script>alert('ID da solicitação não informado!'); history.back();</script>";
    exit;
}

if (empty($resposta)) {
    echo "<script>alert('A resposta é obrigatória!'); history.back();</script>";
    exit;
}

if (!in_array($acao, ['aprovar', 'recusar'])) {
    echo "<script>alert('Ação inválida!'); history.back();</script>";
    exit;
}

try {
    $pdo->beginTransaction();

    // Atualiza a solicitação (tanto para aprovação quanto recusa)
    $novo_status = $acao === 'aprovar' ? 'aprovada' : 'recusada';
    
    $sqlUpdateSolicitacao = "UPDATE solicitacoes_produtos 
                           SET status = :status, 
                               resposta_matriz = :resposta,
                               data_resposta = NOW()
                           WHERE id = :id";
    $stmt = $pdo->prepare($sqlUpdateSolicitacao);
    $stmt->bindParam(':status', $novo_status);
    $stmt->bindParam(':resposta', $resposta);
    $stmt->bindParam(':id', $solicitacao_id);
    $stmt->execute();

    // Processamento específico para APROVAÇÃO
    if ($acao === 'aprovar') {
        // Busca os dados completos da solicitação
        $sqlSolicitacao = "SELECT sp.produto_id, sp.quantidade, sp.empresa_origem, sp.empresa_destino,
                                  pe.nome_produto, pe.fornecedor_produto, pe.status_produto
                          FROM solicitacoes_produtos sp
                          JOIN produtos_estoque pe ON sp.produto_id = pe.id
                          WHERE sp.id = :id";
        $stmt = $pdo->prepare($sqlSolicitacao);
        $stmt->bindParam(':id', $solicitacao_id);
        $stmt->execute();
        $dadosSolicitacao = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dadosSolicitacao) {
            throw new Exception("Solicitação não encontrada");
        }

        $produto_id = $dadosSolicitacao['produto_id'];
        $quantidade = $dadosSolicitacao['quantidade'];
        $empresa_origem = $dadosSolicitacao['empresa_origem'];
        $empresa_destino = $dadosSolicitacao['empresa_destino'];
        $nome_produto = $dadosSolicitacao['nome_produto'];

        // Verifica se a matriz tem estoque suficiente
        $sqlEstoqueMatriz = "SELECT quantidade_produto FROM produtos_estoque 
                            WHERE id = :produto_id AND empresa_id = 'principal_1' 
                            FOR UPDATE";
        $stmt = $pdo->prepare($sqlEstoqueMatriz);
        $stmt->bindParam(':produto_id', $produto_id);
        $stmt->execute();
        $estoqueMatriz = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$estoqueMatriz) {
            throw new Exception("Produto não encontrado no estoque da matriz");
        }

        if ($estoqueMatriz['quantidade_produto'] < $quantidade) {
            throw new Exception("Estoque insuficiente na matriz. Disponível: {$estoqueMatriz['quantidade_produto']}, solicitado: $quantidade");
        }

        // Reduz o estoque da matriz
        $sqlReduzMatriz = "UPDATE produtos_estoque 
                          SET quantidade_produto = quantidade_produto - :quantidade
                          WHERE id = :produto_id AND empresa_id = 'principal_1'";
        $stmt = $pdo->prepare($sqlReduzMatriz);
        $stmt->bindParam(':quantidade', $quantidade);
        $stmt->bindParam(':produto_id', $produto_id);
        $stmt->execute();

        // Verifica se o produto já existe no destino
        $sqlVerificaDestino = "SELECT id, quantidade_produto FROM produtos_estoque 
                              WHERE nome_produto = :nome_produto
                              AND empresa_id = :empresa_destino
                              LIMIT 1";
        $stmt = $pdo->prepare($sqlVerificaDestino);
        $stmt->bindParam(':nome_produto', $nome_produto);
        $stmt->bindParam(':empresa_destino', $empresa_destino);
        $stmt->execute();
        $produtoDestino = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($produtoDestino) {
            // Atualiza o estoque existente no destino
            $sqlAtualizaDestino = "UPDATE produtos_estoque 
                                  SET quantidade_produto = quantidade_produto + :quantidade
                                  WHERE id = :id";
            $stmt = $pdo->prepare($sqlAtualizaDestino);
            $stmt->bindParam(':quantidade', $quantidade);
            $stmt->bindParam(':id', $produtoDestino['id']);
            $stmt->execute();
        } else {
            // Cria um novo registro no destino
            $sqlCopiaProduto = "INSERT INTO produtos_estoque 
                               (nome_produto, fornecedor_produto, quantidade_produto, status_produto, empresa_id)
                               VALUES (:nome_produto, :fornecedor, :quantidade, :status, :empresa_destino)";
            $stmt = $pdo->prepare($sqlCopiaProduto);
            $stmt->bindParam(':nome_produto', $nome_produto);
            $stmt->bindParam(':fornecedor', $dadosSolicitacao['fornecedor_produto']);
            $stmt->bindParam(':quantidade', $quantidade);
            $stmt->bindParam(':status', $dadosSolicitacao['status_produto']);
            $stmt->bindParam(':empresa_destino', $empresa_destino);
            $stmt->execute();
        }

    }

    $pdo->commit();

    // Resposta de sucesso com redirecionamento
    echo "<script>
            alert('Solicitação {$novo_status} com sucesso!');
            window.location.href = '../../../erp/filial/produtosSolicitados.php?id={$id_selecionado}';
          </script>";
    exit;

} catch (PDOException $e) {
    $pdo->rollBack();
    echo "<script>
            alert('Erro no banco de dados: " . addslashes($e->getMessage()) . "');
            history.back();
          </script>";
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<script>
            alert('" . addslashes($e->getMessage()) . "');
            history.back();
          </script>";
    exit;
}

?>

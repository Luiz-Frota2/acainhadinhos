<?php
session_start();
require_once '../conexao.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Receber o campo de data/hora enviado pelo formulário
    $dataRegistro = $_POST['data_registro'] ?? date('Y-m-d H:i:s'); // Se não enviar, usa o horário do servidor

    // Os outros dados do formulário (produtos, quantidades, etc)
    $produtos      = $_POST['produtos'] ?? [];
    $quantidade    = $_POST['quantidade'] ?? [];
    $precos        = $_POST['precos'] ?? [];
    $idProdutos    = $_POST['id_produto'] ?? [];
    $idCategorias  = $_POST['id_categoria'] ?? [];

    $total         = isset($_POST['totalTotal']) ? floatval($_POST['totalTotal']) : 0.00;
    $empresa_id    = $_POST['idSelecionado'] ?? '';
    $id_caixa      = $_POST['id_caixa'] ?? '';
    $responsavel   = $_POST['responsavel'] ?? '';
    $cpf           = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
    $pagamento     = $_POST['forma_pagamento'] ?? '';

    // Formatando produtos para venda_rapida
    $itensFormatados = [];
    for ($i = 0; $i < count($produtos); $i++) {
        $nome  = $produtos[$i];
        $qtd   = intval($quantidade[$i]);
        $preco = floatval($precos[$i]);
        $itensFormatados[] = "$nome (x$qtd) - R$" . number_format($preco, 2, ',', '.');
    }
    $nomesProdutos = implode(', ', $itensFormatados);

    try {
        $pdo->beginTransaction();

        // 1. Inserir na tabela venda_rapida incluindo data_venda
        $stmt = $pdo->prepare("
            INSERT INTO venda_rapida 
                (produtos, total, empresa_id, id_caixa, forma_pagamento, cpf_responsavel, responsavel, data_venda) 
            VALUES 
                (:produtos, :total, :empresa_id, :id_caixa, :forma_pagamento, :cpf_responsavel, :responsavel, :data_venda)
        ");
        $stmt->execute([
            ':produtos'         => $nomesProdutos,
            ':total'            => $total,
            ':empresa_id'       => $empresa_id,
            ':id_caixa'         => $id_caixa,
            ':forma_pagamento'  => $pagamento,
            ':cpf_responsavel'  => $cpf,
            ':responsavel'      => $responsavel,
            ':data_venda'       => $dataRegistro,
        ]);

        $vendaId = $pdo->lastInsertId();

        // 2. Inserir os itens vendidos incluindo data_registro
        for ($i = 0; $i < count($produtos); $i++) {
            $nome        = $produtos[$i];
            $qtd         = intval($quantidade[$i]);
            $preco       = floatval($precos[$i]);
            $precoTotal  = $qtd * $preco;
            $idProduto   = $idProdutos[$i] ?? null;
            $idCategoria = $idCategorias[$i] ?? null;

            $stmtItem = $pdo->prepare("
                INSERT INTO itens_venda 
                    (venda_id, id_caixa, empresa_id, nome_produto, quantidade, preco_unitario, preco_total, cpf_responsavel, responsavel, id_produto, categoria, data_registro) 
                VALUES 
                    (:venda_id, :id_caixa, :empresa_id, :nome_produto, :quantidade, :preco_unitario, :preco_total, :cpf_responsavel, :responsavel, :id_produto, :categoria, :data_registro)
            ");
            $stmtItem->execute([
                ':venda_id'        => $vendaId,
                ':id_caixa'        => $id_caixa,
                ':empresa_id'      => $empresa_id,
                ':nome_produto'    => $nome,
                ':quantidade'      => $qtd,
                ':preco_unitario'  => $preco,
                ':preco_total'     => $precoTotal,
                ':cpf_responsavel' => $cpf,
                ':responsavel'     => $responsavel,
                ':id_produto'      => $idProduto,
                ':categoria'       => $idCategoria,
                ':data_registro'   => $dataRegistro,
            ]);

            // Atualizar estoque
            $stmtEstoque = $pdo->prepare("
                UPDATE estoque 
                SET quantidade_produto = quantidade_produto - :quantidade
                WHERE empresa_id = :empresa_id
                  AND codigo_produto = :codigo_produto
                  AND categoria_produto = :categoria_produto
            ");
            $stmtEstoque->execute([
                ':quantidade'        => $qtd,
                ':empresa_id'        => $empresa_id,
                ':codigo_produto'    => $idProduto,
                ':categoria_produto' => $idCategoria
            ]);
        }

        // 3. Atualizar caixa (aberturas)
        $stmtUpdate = $pdo->prepare("
            UPDATE aberturas 
            SET 
                quantidade_vendas = quantidade_vendas + 1,
                valor_total = valor_total + :total
            WHERE cpf_responsavel = :cpf
              AND responsavel = :responsavel
              AND empresa_id = :empresa_id 
              AND status = 'aberto'
        ");
        $stmtUpdate->execute([
            ':total'       => $total,
            ':cpf'         => $cpf,
            ':responsavel' => $responsavel,
            ':empresa_id'  => $empresa_id
        ]);

        $pdo->commit();

        echo "<script>
                alert('Venda registrada com sucesso!');
                window.location.href = '../../../../frentedeloja/caixa/vendaRapida.php?id=" . urlencode($empresa_id) . "';
              </script>";
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>
                alert('Erro ao registrar venda: " . addslashes($e->getMessage()) . "');
                history.back();
              </script>";
    }
}

?>

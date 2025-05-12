<?php

require_once '../conexao.php';

if (isset($_GET['id'])) {
    $id_categoria = intval($_GET['id']); 

    try {
        // Inicia a transação
        $pdo->beginTransaction();

        // Função para gerar nomes únicos
        function gerarNomeUnico($pdo, $tabela, $campo, $nome_base, $extensao = '') {
            $contador = 1;
            $novo_nome = $nome_base . " - Cópia" . $extensao;
            
            while (true) {
                $sql = "SELECT COUNT(*) FROM $tabela WHERE $campo = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$novo_nome]);
                $existe = $stmt->fetchColumn();
                
                if ($existe == 0) break;
                
                $contador++;
                $novo_nome = "$nome_base - Cópia($contador)$extensao";
            }
            return $novo_nome;
        }

        // 1️⃣ Copiar a Categoria
        $sql = "SELECT * FROM adicionarcategoria WHERE id_categoria = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_categoria]);
        $categoria = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($categoria) {
            $novo_nome_categoria = gerarNomeUnico($pdo, 'adicionarcategoria', 'nome_categoria', $categoria['nome_categoria']);
            
            $sql = "INSERT INTO adicionarCategoria (nome_categoria) VALUES (?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$novo_nome_categoria]);
            $id_categoria_nova = $pdo->lastInsertId();

            // 2️⃣ Copiar os Produtos
            $sql = "SELECT * FROM adicionarProdutos WHERE id_categoria = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_categoria]);
            $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($produtos as $produto) {
                $novo_nome_produto = gerarNomeUnico($pdo, 'adicionarprodutos', 'nome_produto', $produto['nome_produto']);
                
                // Modifica o nome da imagem para evitar conflitos
                if ($produto['imagem_produto']) {
                    $extensao = '.' . pathinfo($produto['imagem_produto'], PATHINFO_EXTENSION);
                    $nome_base_imagem = pathinfo($produto['imagem_produto'], PATHINFO_FILENAME);
                    $novo_nome_imagem_produto = gerarNomeUnico($pdo, 'adicionarProdutos', 'imagem_produto', $nome_base_imagem, $extensao);
                    
                    if (file_exists("../../img/uploads/" . $produto['imagem_produto'])) {
                        $origem = "../../img/uploads/" . $produto['imagem_produto'];
                        $destino = "../../img/uploads/" . $novo_nome_imagem_produto;
                        copy($origem, $destino);
                    }
                } else {
                    $novo_nome_imagem_produto = null;
                }

                $sql = "INSERT INTO adicionarprodutos (nome_produto, quantidade_produto, preco_produto, imagem_produto, descricao_produto, id_categoria) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $novo_nome_produto,
                    $produto['quantidade_produto'],
                    $produto['preco_produto'],
                    $novo_nome_imagem_produto,
                    $produto['descricao_produto'],
                    $id_categoria_nova
                ]);
            }
        }

        $pdo->commit();
        echo "<script>alert('Categoria e produtos copiados com sucesso!'); window.location.href='../../../produtoAdicionados.php';</script>";
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('Erro ao copiar categoria: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
        exit();
    }

} else {
    echo "<script>alert('ID da categoria não fornecido!'); window.history.back();</script>";
    exit();
}

?>
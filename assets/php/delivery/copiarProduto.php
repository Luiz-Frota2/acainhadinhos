<?php

require_once '../conexao.php';

// ✅ Recupera o identificador do produto e o idSelecionado
$id_produto = intval($_GET['id']);
$idSelecionado = $_GET['empresa_id'] ?? ''; // Passando o idSelecionado como parâmetro

if (isset($id_produto)) {
    try {
        $pdo->beginTransaction();

        // Função para gerar nomes únicos corretamente
        function gerarNomeUnico($pdo, $tabela, $campo, $nome_base, $extensao = '') {
            $contador = 1;
            $novo_nome = "$nome_base - Cópia$extensao";
            
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

        // 1️⃣ Copiar o Produto
        $sql = "SELECT * FROM adicionarProdutos WHERE id_produto = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_produto]);
        $produto = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($produto) {
            $novo_nome_produto = gerarNomeUnico($pdo, 'adicionarProdutos', 'nome_produto', $produto['nome_produto']);

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

            // Insere o novo produto na mesma categoria
            $sql = "INSERT INTO adicionarProdutos (nome_produto, quantidade_produto, preco_produto, imagem_produto, descricao_produto, id_categoria) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $novo_nome_produto,
                $produto['quantidade_produto'],
                $produto['preco_produto'],
                $novo_nome_imagem_produto,
                $produto['descricao_produto'],
                $produto['id_categoria']
            ]);
            $id_produto_novo = $pdo->lastInsertId(); // ID do novo produto

            // 2️⃣ Copiar os Opcionais do Produto
            $sql_opcionais = "SELECT * FROM opcionais WHERE id_produto = ?";
            $stmt_opcionais = $pdo->prepare($sql_opcionais);
            $stmt_opcionais->execute([$id_produto]);
            $opcionais = $stmt_opcionais->fetchAll(PDO::FETCH_ASSOC);

            foreach ($opcionais as $opcional) {
                $sql_insert_opcional = "INSERT INTO opcionais (id_produto, nome, preco) VALUES (?, ?, ?)";
                $stmt_insert_opcional = $pdo->prepare($sql_insert_opcional);
                $stmt_insert_opcional->execute([
                    $id_produto_novo, // Associando ao novo produto
                    $opcional['nome'],
                    $opcional['preco']
                ]);
            }

            // 3️⃣ Copiar as Seleções de Opcionais
            $sql_selecoes = "SELECT * FROM opcionais_selecoes WHERE id_produto = ?";
            $stmt_selecoes = $pdo->prepare($sql_selecoes);
            $stmt_selecoes->execute([$id_produto]);
            $selecoes = $stmt_selecoes->fetchAll(PDO::FETCH_ASSOC);

            foreach ($selecoes as $selecao) {
                $sql_insert_selecao = "INSERT INTO opcionais_selecoes (id_produto, titulo, minimo, maximo) 
                                       VALUES (?, ?, ?, ?)";
                $stmt_insert_selecao = $pdo->prepare($sql_insert_selecao);
                $stmt_insert_selecao->execute([
                    $id_produto_novo, // Associando ao novo produto
                    $selecao['titulo'],
                    $selecao['minimo'],
                    $selecao['maximo']
                ]);
                $id_selecao_nova = $pdo->lastInsertId(); // ID da nova seleção

                // 4️⃣ Copiar as Opções de Seleção
                $sql_opcoes = "SELECT * FROM opcionais_opcoes WHERE id_selecao = ?";
                $stmt_opcoes = $pdo->prepare($sql_opcoes);
                $stmt_opcoes->execute([$selecao['id']]);
                $opcoes = $stmt_opcoes->fetchAll(PDO::FETCH_ASSOC);

                foreach ($opcoes as $opcao) {
                    $sql_insert_opcao = "INSERT INTO opcionais_opcoes (id_selecao, nome, preco) VALUES (?, ?, ?)";
                    $stmt_insert_opcao = $pdo->prepare($sql_insert_opcao);
                    $stmt_insert_opcao->execute([
                        $id_selecao_nova, // Associando à nova seleção
                        $opcao['nome'],
                        $opcao['preco']
                    ]);
                }
            }
        }

        $pdo->commit();
        echo "<script>alert('Produto copiado com sucesso!'); window.location.href='../../../erp/delivery/produtoAdicionados.php?id=$idSelecionado';</script>";
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('Erro ao copiar produto: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
        exit();
    }

} else {
    echo "<script>alert('ID do produto não fornecido!'); window.history.back();</script>";
    exit();
}

?>

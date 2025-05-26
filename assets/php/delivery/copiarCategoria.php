<?php

require_once '../conexao.php';

$id_categoria = isset($_GET['id']) ? intval($_GET['id']) : null;
$idSelecionado = $_GET['idSelecionado'] ?? '';

if (!$id_categoria || !$idSelecionado) {
    echo "<script>alert('Dados incompletos!'); history.back();</script>";
    exit;
}

if (str_starts_with($idSelecionado, 'principal_')) {
    $empresa_id = 1;
    $tipo_empresa = 'principal';
} elseif (str_starts_with($idSelecionado, 'filial_')) {
    $empresa_id = (int) str_replace('filial_', '', $idSelecionado);
    $tipo_empresa = 'filial';
} else {
    echo "<script>alert('Empresa não identificada!'); history.back();</script>";
    exit;
}

try {
    $pdo->beginTransaction();

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

    // Copiar a Categoria
    $sql = "SELECT * FROM adicionarCategoria WHERE id_categoria = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_categoria]);
    $categoria = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($categoria) {
        $novo_nome_categoria = gerarNomeUnico($pdo, 'adicionarCategoria', 'nome_categoria', $categoria['nome_categoria']);

        $sql = "INSERT INTO adicionarCategoria (nome_categoria, empresa_id, tipo) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$novo_nome_categoria, $empresa_id, $tipo_empresa]);
        $id_categoria_nova = $pdo->lastInsertId();

        // Copiar os Produtos
        $sql = "SELECT * FROM adicionarProdutos WHERE id_categoria = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_categoria]);
        $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($produtos as $produto) {
            $novo_nome_produto = gerarNomeUnico($pdo, 'adicionarProdutos', 'nome_produto', $produto['nome_produto']);

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

            $sql = "INSERT INTO adicionarProdutos 
                    (nome_produto, quantidade_produto, preco_produto, imagem_produto, descricao_produto, id_categoria, id_empresa) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([ 
                $novo_nome_produto,
                $produto['quantidade_produto'],
                $produto['preco_produto'],
                $novo_nome_imagem_produto,
                $produto['descricao_produto'],
                $id_categoria_nova,
                $empresa_id
            ]);
            $id_produto_novo = $pdo->lastInsertId();

            // Copiar opcionais
            $sql_opcionais = "SELECT * FROM opcionais WHERE id_produto = ?";
            $stmt_opcionais = $pdo->prepare($sql_opcionais);
            $stmt_opcionais->execute([$produto['id_produto']]);
            $opcionais = $stmt_opcionais->fetchAll(PDO::FETCH_ASSOC);

            foreach ($opcionais as $opcional) {
                $sql_insert_opcional = "INSERT INTO opcionais (id_produto, nome, preco) VALUES (?, ?, ?)";
                $stmt_insert_opcional = $pdo->prepare($sql_insert_opcional);
                $stmt_insert_opcional->execute([
                    $id_produto_novo,
                    $opcional['nome'],
                    $opcional['preco']
                ]);
            }

            // Copiar seleções e opções
            $sql_selecoes = "SELECT * FROM opcionais_selecoes WHERE id_produto = ?";
            $stmt_selecoes = $pdo->prepare($sql_selecoes);
            $stmt_selecoes->execute([$produto['id_produto']]);
            $selecoes = $stmt_selecoes->fetchAll(PDO::FETCH_ASSOC);

            foreach ($selecoes as $selecao) {
                $sql_insert_selecao = "INSERT INTO opcionais_selecoes (id_produto, titulo, minimo, maximo) VALUES (?, ?, ?, ?)";
                $stmt_insert_selecao = $pdo->prepare($sql_insert_selecao);
                $stmt_insert_selecao->execute([
                    $id_produto_novo,
                    $selecao['titulo'],
                    $selecao['minimo'],
                    $selecao['maximo']
                ]);
                $id_selecao_nova = $pdo->lastInsertId();

                $sql_opcoes = "SELECT * FROM opcionais_opcoes WHERE id_selecao = ?";
                $stmt_opcoes = $pdo->prepare($sql_opcoes);
                $stmt_opcoes->execute([$selecao['id']]);
                $opcoes = $stmt_opcoes->fetchAll(PDO::FETCH_ASSOC);

                foreach ($opcoes as $opcao) {
                    $sql_insert_opcao = "INSERT INTO opcionais_opcoes (id_selecao, nome, preco) VALUES (?, ?, ?)";
                    $stmt_insert_opcao = $pdo->prepare($sql_insert_opcao);
                    $stmt_insert_opcao->execute([
                        $id_selecao_nova,
                        $opcao['nome'],
                        $opcao['preco']
                    ]);
                }
            }
        }
    }

    $pdo->commit();
    echo "<script>
        alert('Categoria e produtos copiados com sucesso!');
        window.location.href='../../../erp/delivery/produtoAdicionados.php?id=$idSelecionado';
    </script>";
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    echo "<script>alert('Erro ao copiar categoria: " . addslashes($e->getMessage()) . "'); history.back();</script>";
    exit;
}
?>

<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../conexao.php';

// --------- ENTRADA ----------
$id_categoria  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$idSelecionado = $_GET['idSelecionado'] ?? '';

if ($id_categoria <= 0 || $idSelecionado === '') {
    echo "<script>alert('Dados incompletos!'); history.back();</script>";
    exit;
}

// empresa de destino = página atual
$empresa_id = $idSelecionado;

// --------- HELPERS ----------
function gerarNomeUnico(PDO $pdo, $tabela, $campo, $nome_base, $extensao = '')
{
    $contador  = 1;
    $novo_nome = "{$nome_base} - Cópia{$extensao}";

    while (true) {
        $sql  = "SELECT COUNT(*) FROM {$tabela} WHERE {$campo} = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$novo_nome]);
        if ((int)$stmt->fetchColumn() === 0) break;
        $contador++;
        $novo_nome = "{$nome_base} - Cópia({$contador}){$extensao}";
    }
    return $novo_nome;
}

try {
    $pdo->beginTransaction();

    // --------- CATEGORIA ORIGEM ----------
    $sql  = "SELECT * FROM adicionarCategoria WHERE id_categoria = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_categoria]);
    $categoria = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$categoria) {
        throw new Exception('Categoria não encontrada.');
    }

    // --------- INSERE NOVA CATEGORIA (DESTINO) ----------
    $novo_nome_categoria = gerarNomeUnico($pdo, 'adicionarCategoria', 'nome_categoria', $categoria['nome_categoria']);

    // sua tabela NÃO tem 'tipo', então só nome + empresa_id
    $sql = "INSERT INTO adicionarCategoria (nome_categoria, empresa_id)
            VALUES (?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$novo_nome_categoria, $empresa_id]);
    $id_categoria_nova = (int)$pdo->lastInsertId();

    // --------- BUSCA PRODUTOS DA CATEGORIA ORIGEM ----------
    $sql  = "SELECT * FROM adicionarProdutos WHERE id_categoria = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_categoria]);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // diretório físico das imagens (a partir de assets/php/delivery/)
    $uploadsDir = rtrim(str_replace('\\', '/', __DIR__ . '/../../img/uploads/'), '/') . '/';

    foreach ($produtos as $produto) {
        // Nome único do produto
        $novo_nome_produto = gerarNomeUnico($pdo, 'adicionarProdutos', 'nome_produto', $produto['nome_produto']);

        // ----- Copiar imagem -----
        $novo_nome_imagem_produto = null;
        if (!empty($produto['imagem_produto'])) {
            // por padrão, mantém o nome original (evita NULL caso a cópia falhe)
            $novo_nome_imagem_produto = $produto['imagem_produto'];

            $extensao         = '.' . pathinfo($produto['imagem_produto'], PATHINFO_EXTENSION);
            $nome_base_imagem = pathinfo($produto['imagem_produto'], PATHINFO_FILENAME);
            $nome_copia_img   = gerarNomeUnico($pdo, 'adicionarProdutos', 'imagem_produto', $nome_base_imagem, $extensao);

            $origem  = $uploadsDir . $produto['imagem_produto'];
            $destino = $uploadsDir . $nome_copia_img;

            if (is_file($origem)) {
                // tenta copiar; se der certo, usa o nome novo
                if (@copy($origem, $destino)) {
                    $novo_nome_imagem_produto = $nome_copia_img;
                }
            }
            // se o arquivo original não existir, ficamos com o nome original (não vira NULL)
        }

        // Insere produto já amarrando ao id_categoria novo E à empresa destino
        $sql = "INSERT INTO adicionarProdutos
                (nome_produto, quantidade_produto, preco_produto, imagem_produto, descricao_produto, id_categoria, id_empresa)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $novo_nome_produto,
            $produto['quantidade_produto'],
            $produto['preco_produto'],
            $novo_nome_imagem_produto,          // nunca NULL à toa
            $produto['descricao_produto'],
            $id_categoria_nova,
            $empresa_id                         // <- empresa destino
        ]);
        $id_produto_novo = (int)$pdo->lastInsertId();

        // ---------- OPCIONAIS SIMPLES ----------
        // pegar só os opcionais no escopo desta empresa (idSelecionado)
        $sql  = "SELECT * FROM opcionais WHERE id_produto = ? AND id_selecionado = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$produto['id_produto'], $idSelecionado]);
        $opcionais = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($opcionais as $opcional) {
            $sql = "INSERT INTO opcionais (id_produto, nome, preco, id_selecionado)
                    VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $id_produto_novo,
                $opcional['nome'],
                $opcional['preco'],
                $idSelecionado
            ]);
        }

        // ---------- SELEÇÕES ----------
        $sql  = "SELECT * FROM opcionais_selecoes WHERE id_produto = ? AND id_selecionado = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$produto['id_produto'], $idSelecionado]);
        $selecoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($selecoes as $selecao) {
            $sql = "INSERT INTO opcionais_selecoes (id_produto, titulo, minimo, maximo, id_selecionado)
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $id_produto_novo,
                $selecao['titulo'],
                $selecao['minimo'],
                $selecao['maximo'],
                $idSelecionado
            ]);
            $id_selecao_nova = (int)$pdo->lastInsertId();

            // ---------- OPÇÕES DAS SELEÇÕES ----------
            $sql  = "SELECT * FROM opcionais_opcoes WHERE id_selecao = ? AND id_selecionado = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$selecao['id'], $idSelecionado]);
            $opcoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($opcoes as $opcao) {
                $sql = "INSERT INTO opcionais_opcoes (id_selecao, nome, preco, id_selecionado)
                        VALUES (?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $id_selecao_nova,
                    $opcao['nome'],
                    $opcao['preco'],
                    $idSelecionado
                ]);
            }
        }
    }

    $pdo->commit();
    echo "<script>
        alert('Categoria e produtos copiados com sucesso!');
        window.location.href='../../../erp/delivery/produtoAdicionados.php?id=" . rawurlencode($idSelecionado) . "';
    </script>";
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "<script>alert('Erro ao copiar categoria: " . addslashes($e->getMessage()) . "'); history.back();</script>";
    exit;
}
?>
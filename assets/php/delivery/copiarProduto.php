<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../conexao.php';

// --- Helpers ---
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/**
 * Gera um nome único com sufixo " - Cópia" e " - Cópia(n)" respeitando (opcionalmente) um filtro por empresa.
 * $extensao deve incluir o ponto (ex.: ".jpg") ou vazio.
 */
function gerarNomeUnico(
    PDO $pdo,
    string $tabela,
    string $campo,
    string $nome_base,
    string $extensao = '',
    ?string $filtroColuna = null,
    ?string $filtroValor = null
): string {
    $contador = 0;
    do {
        $suf = $contador === 0 ? ' - Cópia' : " - Cópia($contador)";
        $novo = "{$nome_base}{$suf}{$extensao}";

        $sql = "SELECT COUNT(*) FROM {$tabela} WHERE {$campo} = ?";
        $params = [$novo];

        if ($filtroColuna !== null) {
            $sql .= " AND {$filtroColuna} = ?";
            $params[] = $filtroValor;
        }

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $existe = (int)$st->fetchColumn();

        if ($existe === 0) {
            return $novo;
        }
        $contador++;
    } while (true);
}

// --- Entrada ---
$id_produto     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$idSelecionado  = $_GET['empresa_id'] ?? ''; // slug da empresa (ex.: principal_1, unidade_2, ...)

if ($id_produto <= 0 || $idSelecionado === '') {
    echo "<script>alert('Parâmetros inválidos.'); history.back();</script>";
    exit;
}

try {
    $pdo->beginTransaction();

    // 1) Busca produto e valida que pertence à empresa informada
    $sql = "SELECT * FROM adicionarProdutos WHERE id_produto = :id AND id_empresa = :emp LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id_produto, ':emp' => $idSelecionado]);
    $produto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$produto) {
        $pdo->rollBack();
        echo "<script>alert('Produto não encontrado para esta empresa.'); window.location.href='../../../erp/delivery/produtoAdicionados.php?id=" . rawurlencode($idSelecionado) . "';</script>";
        exit;
    }

    // 2) Nome do novo produto (único por empresa)
    $novo_nome_produto = gerarNomeUnico(
        $pdo,
        'adicionarProdutos',
        'nome_produto',
        $produto['nome_produto'],
        '',
        'id_empresa',
        $idSelecionado
    );

    // 3) Copiar imagem (se houver) e gerar nome único da imagem (por empresa)
    $novo_nome_imagem_produto = null;
    $imgAtual = trim((string)($produto['imagem_produto'] ?? ''));
    if ($imgAtual !== '') {
        $ext = strtolower(pathinfo($imgAtual, PATHINFO_EXTENSION));
        $nomeBase = pathinfo($imgAtual, PATHINFO_FILENAME);

        // O primeiro candidato vira "<base> - Cópia.ext" e, se existir, incrementa (n)
        $novo_nome_imagem_produto = gerarNomeUnico(
            $pdo,
            'adicionarProdutos',
            'imagem_produto',
            $nomeBase,
            $ext ? ('.' . $ext) : '',
            'id_empresa',
            $idSelecionado
        );

        $src = __DIR__ . "/../../img/uploads/" . basename($imgAtual);
        $dst = __DIR__ . "/../../img/uploads/" . $novo_nome_imagem_produto;

        if (is_file($src)) {
            if (!@copy($src, $dst)) {
                // Se falhar a cópia da imagem, ainda assim copiamos o produto sem imagem
                $novo_nome_imagem_produto = null;
            }
        } else {
            $novo_nome_imagem_produto = null;
        }
    }

    // 4) Inserir NOVO produto (inclui id_empresa!)
    $sqlIns = "INSERT INTO adicionarProdutos
                  (nome_produto, quantidade_produto, preco_produto, imagem_produto, descricao_produto, id_categoria, id_empresa)
               VALUES
                  (:nome, :qtd, :preco, :img, :desc, :cat, :emp)";
    $stIns = $pdo->prepare($sqlIns);
    $stIns->execute([
        ':nome'  => $novo_nome_produto,
        ':qtd'   => (int)$produto['quantidade_produto'],
        ':preco' => $produto['preco_produto'],
        ':img'   => $novo_nome_imagem_produto,
        ':desc'  => $produto['descricao_produto'],
        ':cat'   => $produto['id_categoria'],
        ':emp'   => $idSelecionado,            // <<<<<<<<<< AQUI vai o id_empresa
    ]);
    $id_produto_novo = (int)$pdo->lastInsertId();

    // 5) Copiar Opcionais Simples (filtra por produto E empresa)
    $sql_opc = "SELECT id, nome, preco FROM opcionais WHERE id_produto = :p AND id_selecionado = :emp";
    $st_opc = $pdo->prepare($sql_opc);
    $st_opc->execute([':p' => $id_produto, ':emp' => $idSelecionado]);
    $opcionais = $st_opc->fetchAll(PDO::FETCH_ASSOC);

    if ($opcionais) {
        $sql_ins_opc = "INSERT INTO opcionais (id_produto, nome, preco, id_selecionado)
                        VALUES (:p, :n, :pr, :emp)";
        $st_ins_opc = $pdo->prepare($sql_ins_opc);
        foreach ($opcionais as $op) {
            $st_ins_opc->execute([
                ':p'   => $id_produto_novo,
                ':n'   => $op['nome'],
                ':pr'  => $op['preco'],
                ':emp' => $idSelecionado,         // <<<<<< id_selecionado
            ]);
        }
    }

    // 6) Copiar Seleções de Opcionais (e suas opções), filtrando por empresa
    $sql_sel = "SELECT id, titulo, minimo, maximo
                  FROM opcionais_selecoes
                 WHERE id_produto = :p AND id_selecionado = :emp";
    $st_sel = $pdo->prepare($sql_sel);
    $st_sel->execute([':p' => $id_produto, ':emp' => $idSelecionado]);
    $selecoes = $st_sel->fetchAll(PDO::FETCH_ASSOC);

    if ($selecoes) {
        $sql_ins_sel = "INSERT INTO opcionais_selecoes (id_produto, titulo, minimo, maximo, id_selecionado)
                        VALUES (:p, :t, :min, :max, :emp)";
        $st_ins_sel = $pdo->prepare($sql_ins_sel);

        $sql_opcoes = "SELECT id, nome, preco
                         FROM opcionais_opcoes
                        WHERE id_selecao = :s AND id_selecionado = :emp";
        $st_opcoes = $pdo->prepare($sql_opcoes);

        $sql_ins_opcao = "INSERT INTO opcionais_opcoes (id_selecao, nome, preco, id_selecionado)
                          VALUES (:s, :n, :pr, :emp)";
        $st_ins_opcao = $pdo->prepare($sql_ins_opcao);

        foreach ($selecoes as $sel) {
            // cria a nova seleção
            $st_ins_sel->execute([
                ':p'   => $id_produto_novo,
                ':t'   => $sel['titulo'],
                ':min' => $sel['minimo'],
                ':max' => $sel['maximo'],
                ':emp' => $idSelecionado,         // <<<<<< id_selecionado
            ]);
            $id_selecao_nova = (int)$pdo->lastInsertId();

            // carrega e replica as opções dessa seleção
            $st_opcoes->execute([':s' => $sel['id'], ':emp' => $idSelecionado]);
            $opcoes = $st_opcoes->fetchAll(PDO::FETCH_ASSOC);

            foreach ($opcoes as $op) {
                $st_ins_opcao->execute([
                    ':s'   => $id_selecao_nova,
                    ':n'   => $op['nome'],
                    ':pr'  => $op['preco'],
                    ':emp' => $idSelecionado,      // <<<<<< id_selecionado
                ]);
            }
        }
    }

    $pdo->commit();
    echo "<script>alert('Produto copiado com sucesso!'); window.location.href='../../../erp/delivery/produtoAdicionados.php?id=" . rawurlencode($idSelecionado) . "';</script>";
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "<script>alert('Erro ao copiar produto: " . h($e->getMessage()) . "'); history.back();</script>";
    exit;
}

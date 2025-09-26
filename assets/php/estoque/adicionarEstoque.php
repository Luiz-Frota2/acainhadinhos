<?php
// Inclui o arquivo de conexão
require '../conexao.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Empresa (ex.: principal_1, filial_...)
    $empresa_id = $_POST['idSelecionado'] ?? '';

    // --- Fornecedor (obrigatório) ---
    $fornecedor_id = isset($_POST['fornecedor_id']) ? (int)$_POST['fornecedor_id'] : 0;
    if ($fornecedor_id <= 0) {
        echo "<script>alert('Selecione um fornecedor.'); history.back();</script>";
        exit;
    }

    // --- Recebe dados ---
    $codigo     = trim($_POST["codigo_produto"] ?? '');
    $nome       = trim($_POST["nome_produto"] ?? '');
    $categoria  = trim($_POST["categoria_produto"] ?? '');
    $quantidade = trim($_POST["quantidade_produto"] ?? '');
    // Converte “R$ 1.234,56” -> “1234.56”
    $preco      = isset($_POST["preco_produto"]) ? str_replace(['R$', ' ', '.', ','], ['', '', '', '.'], trim($_POST["preco_produto"])) : '';
    $preco_custo= isset($_POST["preco_custo"]) ? str_replace(['R$', ' ', '.', ','], ['', '', '', '.'], trim($_POST["preco_custo"])) : null;
    $statuss    = trim($_POST["status_produto"] ?? '');

    $ncm        = trim($_POST["ncm_produto"] ?? '');
    $cest       = trim($_POST["cest_produto"] ?? '');
    $cfop       = trim($_POST["cfop_produto"] ?? '');
    $origem     = trim($_POST["origem_produto"] ?? '');        // pode ser "0"
    $tributacao = trim($_POST["tributacao_produto"] ?? '');
    $unidade    = trim($_POST["unidade_produto"] ?? '');

    $codigo_barras   = trim($_POST["codigo_barras"] ?? '');
    $codigo_anp      = trim($_POST["codigo_anp"] ?? '');
    $peso_bruto      = isset($_POST["peso_bruto"]) ? str_replace(',', '.', trim($_POST["peso_bruto"])) : null;
    $peso_liquido    = isset($_POST["peso_liquido"]) ? str_replace(',', '.', trim($_POST["peso_liquido"])) : null;
    $aliquota_icms   = isset($_POST["aliquota_icms"]) ? str_replace(',', '.', trim($_POST["aliquota_icms"])) : null;
    $aliquota_pis    = isset($_POST["aliquota_pis"]) ? str_replace(',', '.', trim($_POST["aliquota_pis"])) : null;
    $aliquota_cofins = isset($_POST["aliquota_cofins"]) ? str_replace(',', '.', trim($_POST["aliquota_cofins"])) : null;
    $informacoes_adicionais = trim($_POST["informacoes_adicionais"] ?? '');

    // --- Validação correta de obrigatórios (sem quebrar para "0") ---
    $obrigatorios = [
        'Código do produto'   => $codigo,
        'Nome do produto'     => $nome,
        'Categoria'           => $categoria,
        'Quantidade'          => $quantidade,
        'Preço unitário'      => $preco,
        'Status'              => $statuss,
        'NCM'                 => $ncm,
        'CFOP'                => $cfop,
        'Origem'              => $origem,      // aqui "0" é válido; só barra se for string vazia
        'Tributação'          => $tributacao,
        'Unidade'             => $unidade,
    ];

    foreach ($obrigatorios as $campo => $valor) {
        if ($valor === '') {
            echo "<script>alert('Preencha o campo obrigatório: {$campo}.'); history.back();</script>";
            exit;
        }
    }

    // --- Valida fornecedor pertence à empresa ---
    try {
        $chk = $pdo->prepare("SELECT 1 FROM fornecedores WHERE id = :fid AND empresa_id = :emp LIMIT 1");
        $chk->execute([':fid' => $fornecedor_id, ':emp' => $empresa_id]);
        if (!$chk->fetchColumn()) {
            echo "<script>alert('Fornecedor inválido para esta empresa.'); history.back();</script>";
            exit;
        }
    } catch (PDOException $e) {
        echo "<script>alert('Erro ao validar fornecedor: " . addslashes($e->getMessage()) . "'); history.back();</script>";
        exit;
    }

    // --- Insert ---
    try {
        $sql = "INSERT INTO estoque (
                    empresa_id, fornecedor_id, codigo_produto, nome_produto, categoria_produto,
                    quantidade_produto, preco_produto, preco_custo, status_produto,
                    ncm, cest, cfop, origem, tributacao, unidade, codigo_barras,
                    codigo_anp, peso_bruto, peso_liquido, aliquota_icms,
                    aliquota_pis, aliquota_cofins, informacoes_adicionais
                ) VALUES (
                    :empresa_id, :fornecedor_id, :codigo_produto, :nome_produto, :categoria_produto,
                    :quantidade_produto, :preco_produto, :preco_custo, :status_produto,
                    :ncm, :cest, :cfop, :origem, :tributacao, :unidade, :codigo_barras,
                    :codigo_anp, :peso_bruto, :peso_liquido, :aliquota_icms,
                    :aliquota_pis, :aliquota_cofins, :informacoes_adicionais
                )";

        $stmt = $pdo->prepare($sql);

        $stmt->bindParam(":empresa_id", $empresa_id);
        $stmt->bindParam(":fornecedor_id", $fornecedor_id, PDO::PARAM_INT);
        $stmt->bindParam(":codigo_produto", $codigo);
        $stmt->bindParam(":nome_produto", $nome);
        $stmt->bindParam(":categoria_produto", $categoria);
        $stmt->bindParam(":quantidade_produto", $quantidade);
        $stmt->bindParam(":preco_produto", $preco);
        $stmt->bindParam(":preco_custo", $preco_custo);
        $stmt->bindParam(":status_produto", $statuss);
        $stmt->bindParam(":ncm", $ncm);
        $stmt->bindParam(":cest", $cest);
        $stmt->bindParam(":cfop", $cfop);
        $stmt->bindParam(":origem", $origem);
        $stmt->bindParam(":tributacao", $tributacao);
        $stmt->bindParam(":unidade", $unidade);
        $stmt->bindParam(":codigo_barras", $codigo_barras);
        $stmt->bindParam(":codigo_anp", $codigo_anp);
        $stmt->bindParam(":peso_bruto", $peso_bruto);
        $stmt->bindParam(":peso_liquido", $peso_liquido);
        $stmt->bindParam(":aliquota_icms", $aliquota_icms);
        $stmt->bindParam(":aliquota_pis", $aliquota_pis);
        $stmt->bindParam(":aliquota_cofins", $aliquota_cofins);
        $stmt->bindParam(":informacoes_adicionais", $informacoes_adicionais);

        if ($stmt->execute()) {
            echo "<script>
                    alert('Produto adicionado com sucesso');
                    window.location.href = '../../../erp/estoque/produtosAdicionados.php?id=" . urlencode($empresa_id) . "';
                  </script>";
            exit;
        } else {
            echo "<script>alert('Erro ao cadastrar produto.'); history.back();</script>";
        }
    } catch (PDOException $e) {
        echo "<script>alert('Erro no banco de dados: " . addslashes($e->getMessage()) . "'); history.back();</script>";
    }
}
?>
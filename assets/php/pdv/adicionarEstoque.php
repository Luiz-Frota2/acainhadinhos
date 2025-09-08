<?php
// Inclui o arquivo de conexão
require '../conexao.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Pega o ID da empresa enviado pelo formulário
    $empresa_id = $_POST['idSelecionado'] ?? '';

    // Recebe os dados básicos do formulário
    $codigo = trim($_POST["codigo_produto"]);
    $nome = trim($_POST["nome_produto"]);
    $categoria = trim($_POST["categoria_produto"]);
    $quantidade = trim($_POST["quantidade_produto"]);
    $preco = str_replace(['R$', '.', ','], ['', '', '.'], trim($_POST["preco_produto"]));
    $preco_custo = isset($_POST["preco_custo"]) ? str_replace(['R$', '.', ','], ['', '', '.'], trim($_POST["preco_custo"])) : null;
    $statuss = trim($_POST["status_produto"]);
    
    // Recebe os dados fiscais para NFC-e
    $ncm = trim($_POST["ncm_produto"]);
    $cest = isset($_POST["cest_produto"]) ? trim($_POST["cest_produto"]) : null;
    $cfop = trim($_POST["cfop_produto"]);
    $origem = trim($_POST["origem_produto"]);
    $tributacao = trim($_POST["tributacao_produto"]);
    $unidade = trim($_POST["unidade_produto"]);
    
    // Novos campos adicionados
    $codigo_barras = isset($_POST["codigo_barras"]) ? trim($_POST["codigo_barras"]) : null;
    $codigo_anp = isset($_POST["codigo_anp"]) ? trim($_POST["codigo_anp"]) : null;
    $peso_bruto = isset($_POST["peso_bruto"]) ? str_replace(',', '.', trim($_POST["peso_bruto"])) : null;
    $peso_liquido = isset($_POST["peso_liquido"]) ? str_replace(',', '.', trim($_POST["peso_liquido"])) : null;
    $aliquota_icms = isset($_POST["aliquota_icms"]) ? str_replace(',', '.', trim($_POST["aliquota_icms"])) : null;
    $aliquota_pis = isset($_POST["aliquota_pis"]) ? str_replace(',', '.', trim($_POST["aliquota_pis"])) : null;
    $aliquota_cofins = isset($_POST["aliquota_cofins"]) ? str_replace(',', '.', trim($_POST["aliquota_cofins"])) : null;
    $informacoes_adicionais = isset($_POST["informacoes_adicionais"]) ? trim($_POST["informacoes_adicionais"]) : null;

    try {
        // Atualiza a query para incluir todos os campos
        $sql = "INSERT INTO estoque (
                    empresa_id, codigo_produto, nome_produto, categoria_produto,
                    quantidade_produto, preco_produto, preco_custo, status_produto,
                    ncm, cest, cfop, origem, tributacao, unidade, codigo_barras,
                    codigo_anp, peso_bruto, peso_liquido, aliquota_icms,
                    aliquota_pis, aliquota_cofins, informacoes_adicionais
                ) VALUES (
                    :empresa_id, :codigo_produto, :nome_produto, :categoria_produto,
                    :quantidade_produto, :preco_produto, :preco_custo, :status_produto,
                    :ncm, :cest, :cfop, :origem, :tributacao, :unidade, :codigo_barras,
                    :codigo_anp, :peso_bruto, :peso_liquido, :aliquota_icms,
                    :aliquota_pis, :aliquota_cofins, :informacoes_adicionais
                )";
        
        $stmt = $pdo->prepare($sql);
        
        // Bind dos parâmetros
        $stmt->bindParam(":empresa_id", $empresa_id);
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

        // Executar e exibir mensagem de sucesso
        if ($stmt->execute()) {
            echo "<script>
                    alert('Produto adicionado com sucesso');
                    window.location.href = '../../../erp/pdv/produtosAdicionados.php?id=" . urlencode($empresa_id) . "';
                  </script>";
            exit();
        } else {
            echo "<script>
                    alert('Erro ao cadastrar produto.');
                    history.back();
                  </script>";
        }
    } catch (PDOException $e) {
        echo "<script>
                alert('Erro no banco de dados: " . addslashes($e->getMessage()) . "');
                history.back();
              </script>";
    }
}
?>
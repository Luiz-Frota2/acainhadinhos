<?php
// Inclui o arquivo de conexão
require '../conexao.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recebe os dados básicos do formulário
    $id = $_POST["id"] ?? null;
    $empresa_id = $_POST["empresa_id"] ?? '';
    
    // Dados básicos do produto
    $codigo_produto = trim($_POST["codigo_produto"] ?? '');
    $nome_produto = trim($_POST["nome_produto"] ?? '');
    $categoria_produto = trim($_POST["categoria_produto"] ?? '');
    $quantidade_produto = str_replace(',', '.', trim($_POST["quantidade_produto"] ?? ''));
    $preco_produto = str_replace(['R$', '.', ','], ['', '', '.'], trim($_POST["preco_produto"] ?? ''));
    $preco_custo = isset($_POST["preco_custo"]) ? str_replace(['R$', '.', ','], ['', '', '.'], trim($_POST["preco_custo"])) : null;
    $status_produto = trim($_POST["status_produto"] ?? '');
    
    // Dados fiscais para NFC-e
    $ncm = trim($_POST["ncm"] ?? '');
    $cfop = trim($_POST["cfop"] ?? '');
    $cest = isset($_POST["cest"]) ? trim($_POST["cest"]) : null;
    $origem = trim($_POST["origem"] ?? '0');
    $tributacao = trim($_POST["tributacao"] ?? '00');
    $unidade = trim($_POST["unidade"] ?? 'UN');
    $informacoes_adicionais = isset($_POST["informacoes_adicionais"]) ? trim($_POST["informacoes_adicionais"]) : null;

    // Verifica campos obrigatórios
    $camposObrigatorios = [
        $id, $empresa_id, $codigo_produto, $nome_produto, 
        $categoria_produto, $quantidade_produto, $preco_produto, 
        $status_produto, $ncm, $cfop, $origem, $tributacao, $unidade
    ];
    
    foreach ($camposObrigatorios as $campo) {
        if (empty($campo)) {
            echo "<script>
                    alert('Preencha todos os campos obrigatórios corretamente.');
                    history.back();
                  </script>";
            exit;
        }
    }

    try {
        // Query de atualização com todos os campos NFC-e
        $sql = "UPDATE estoque SET 
                codigo_produto = :codigo_produto, 
                nome_produto = :nome_produto, 
                categoria_produto = :categoria_produto, 
                quantidade_produto = :quantidade_produto, 
                preco_produto = :preco_produto, 
                preco_custo = :preco_custo,
                status_produto = :status_produto,
                ncm = :ncm,
                cfop = :cfop,
                cest = :cest,
                origem = :origem,
                tributacao = :tributacao,
                unidade = :unidade,
                informacoes_adicionais = :informacoes_adicionais,
                empresa_id = :empresa_id 
                WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        
        // Bind dos parâmetros
        $stmt->bindParam(":codigo_produto", $codigo_produto, PDO::PARAM_STR);
        $stmt->bindParam(":nome_produto", $nome_produto, PDO::PARAM_STR);
        $stmt->bindParam(":categoria_produto", $categoria_produto, PDO::PARAM_STR);
        $stmt->bindParam(":quantidade_produto", $quantidade_produto);
        $stmt->bindParam(":preco_produto", $preco_produto);
        $stmt->bindParam(":preco_custo", $preco_custo);
        $stmt->bindParam(":status_produto", $status_produto, PDO::PARAM_STR);
        $stmt->bindParam(":ncm", $ncm, PDO::PARAM_STR);
        $stmt->bindParam(":cfop", $cfop, PDO::PARAM_STR);
        $stmt->bindParam(":cest", $cest);
        $stmt->bindParam(":origem", $origem, PDO::PARAM_STR);
        $stmt->bindParam(":tributacao", $tributacao, PDO::PARAM_STR);
        $stmt->bindParam(":unidade", $unidade, PDO::PARAM_STR);
        $stmt->bindParam(":informacoes_adicionais", $informacoes_adicionais);
        $stmt->bindParam(":empresa_id", $empresa_id, PDO::PARAM_STR);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            echo "<script>
                    alert('Produto atualizado com sucesso!');
                    window.location.href = '../../../erp/pdv/produtosAdicionados.php?id=" . urlencode($empresa_id) . "';
                  </script>";
            exit;
        } else {
            echo "<script>
                    alert('Erro ao atualizar produto');
                    history.back();
                  </script>";
            exit;
        }
    } catch (PDOException $e) {
        echo "<script>
                alert('Erro no banco de dados: " . addslashes($e->getMessage()) . "');
                history.back();
              </script>";
        exit;
    }
} else {
    echo "<script>
            alert('Requisição inválida.');
            history.back();
          </script>";
    exit;
}
?>
<?php

require_once '../conexao.php';
// Verifica se a sessão está iniciada
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $valor_suprimento = floatval($_POST["valor_suprimento"] ?? 0);
    $saldoCaixa = floatval($_POST["saldo_caixa"] ?? 0);
    $empresa_id = $_POST["idSelecionado"] ?? '';
    $responsavel = $_POST["responsavel"] ?? '';
    $id_caixa = $_POST["id_caixa"] ?? '';

    $valor_liquido = $saldoCaixa + $valor_suprimento;

    try {
        // 1. Inserir a sangria
        $sql = "INSERT INTO suprimentos (valor_suprimento, empresa_id, id_caixa, valor_liquido, responsavel) 
                VALUES (:valor_suprimento, :empresa_id, :id_caixa, :valor_liquido, :responsavel)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":valor_suprimento", $valor_suprimento);
        $stmt->bindParam(":empresa_id", $empresa_id);
        $stmt->bindParam(":id_caixa", $id_caixa);
        $stmt->bindParam(":valor_liquido", $valor_liquido);
        $stmt->bindParam(":responsavel", $responsavel);
        $stmt->execute();

        // 2. Somar todas as sangrias do mesmo caixa, empresa, responsável e status 'aberto'
        $somaStmt = $pdo->prepare("
            SELECT SUM(valor_suprimento) AS total_suprimentos
            FROM suprimentos
            WHERE empresa_id = :empresa_id 
              AND id_caixa = :id_caixa 
              AND responsavel = :responsavel
        ");
        $somaStmt->execute([
            ':empresa_id' => $empresa_id,
            ':id_caixa' => $id_caixa,
            ':responsavel' => $responsavel
        ]);
        $resultadoSoma = $somaStmt->fetch(PDO::FETCH_ASSOC);
        $total_suprimentos = $resultadoSoma['total_suprimentos'] ?? 0;

        // 3. Atualizar a tabela aberturas
        $updateStmt = $pdo->prepare("
            UPDATE aberturas 
            SET valor_liquido = :valor_liquido,
                valor_suprimento = :total_suprimentos
            WHERE id = :id_caixa 
              AND empresa_id = :empresa_id 
              AND responsavel = :responsavel 
              AND status_abertura = 'aberto'
        ");
        $updateStmt->execute([
            ':valor_liquido' => $valor_liquido,
            ':total_suprimentos' => $total_suprimentos,
            ':id_caixa' => $id_caixa,
            ':empresa_id' => $empresa_id,
            ':responsavel' => $responsavel
        ]);

        // 4. Redirecionar com sucesso
        echo "<script>
                alert('Suprimentos registrada e abertura atualizada com sucesso!');
                window.location.href = '../../../../frentedeloja/caixa/index.php?id=" . urlencode($empresa_id) . "';
              </script>";
        exit();

    } catch (PDOException $e) {
        echo "<script>
                alert('Erro no banco de dados: " . addslashes($e->getMessage()) . "');
                history.back();
              </script>";
    }
}
?>

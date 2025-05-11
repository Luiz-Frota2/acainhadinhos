<?php
$host = 'localhost';
$dbname = 'u920914488_ERP';
$username = 'u920914488_ERP';
$password = 'K5yJv;lVIKc>';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $valor_sangria = floatval($_POST["valor"] ?? 0);
    $saldoCaixa = floatval($_POST["saldo_caixa"] ?? 0);
    $empresa_id = $_POST["idSelecionado"] ?? '';
    $responsavel = $_POST["responsavel"] ?? '';
    $id_caixa = $_POST["id_caixa"] ?? '';

    $valor_liquido = $saldoCaixa - $valor_sangria;

    try {
        // 1. Inserir a sangria
        $sql = "INSERT INTO sangrias (valor, empresa_id, id_caixa, valor_liquido, responsavel) 
                VALUES (:valor, :empresa_id, :id_caixa, :valor_liquido, :responsavel)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":valor", $valor_sangria);
        $stmt->bindParam(":empresa_id", $empresa_id);
        $stmt->bindParam(":id_caixa", $id_caixa);
        $stmt->bindParam(":valor_liquido", $valor_liquido);
        $stmt->bindParam(":responsavel", $responsavel);
        $stmt->execute();

        // 2. Somar todas as sangrias do mesmo caixa, empresa, responsável e status 'aberto'
        $somaStmt = $pdo->prepare("
            SELECT SUM(valor) AS total_sangrias 
            FROM sangrias 
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
        $total_sangrias = $resultadoSoma['total_sangrias'] ?? 0;

        // 3. Atualizar a tabela aberturas
        $updateStmt = $pdo->prepare("
            UPDATE aberturas 
            SET valor_liquido = :valor_liquido,
                valor_sangrias = :total_sangrias
            WHERE id = :id_caixa 
              AND empresa_id = :empresa_id 
              AND responsavel = :responsavel 
              AND status_abertura = 'aberto'
        ");
        $updateStmt->execute([
            ':valor_liquido' => $valor_liquido,
            ':total_sangrias' => $total_sangrias,
            ':id_caixa' => $id_caixa,
            ':empresa_id' => $empresa_id,
            ':responsavel' => $responsavel
        ]);

        // 4. Redirecionar com sucesso
        echo "<script>
                alert('Sangria registrada e abertura atualizada com sucesso!');
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

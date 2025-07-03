<?php

require_once '../conexao.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $valor_suprimento = floatval($_POST["valor_suprimento"] ?? 0);
    $saldoCaixa = floatval($_POST["saldo_caixa"] ?? 0);
    $empresa_id = $_POST["idSelecionado"] ?? '';
    $responsavel = $_POST["responsavel"] ?? '';
    $cpf = preg_replace('/\D/', '', $_POST["cpf"] ?? '');
    $id_caixa = intval($_POST["id_caixa"] ?? 0);
    $data_registro = $_POST["data_registro"] ?? null;

    try {
        $pdo->beginTransaction();

        // 1. Inserir o suprimento (associado ao caixa atual)
        $valor_liquido = $saldoCaixa + $valor_suprimento;

        $stmt = $pdo->prepare("
            INSERT INTO suprimentos 
            (valor_suprimento, empresa_id, id_caixa, valor_liquido, responsavel, cpf_responsavel, data_registro) 
            VALUES 
            (:valor_suprimento, :empresa_id, :id_caixa, :valor_liquido, :responsavel, :cpf, :data_registro)
        ");
        $stmt->execute([
            ':valor_suprimento' => $valor_suprimento,
            ':empresa_id' => $empresa_id,
            ':id_caixa' => $id_caixa,
            ':valor_liquido' => $valor_liquido,
            ':responsavel' => $responsavel,
            ':cpf' => $cpf,
            ':data_registro' => $data_registro
        ]);

        // 2. Soma total de suprimentos apenas de caixas abertos
        $somaStmt = $pdo->prepare("
            SELECT SUM(s.valor_suprimento) AS total_suprimentos
            FROM suprimentos s
            JOIN aberturas a ON s.id_caixa = a.id
            WHERE s.empresa_id = :empresa_id 
              AND s.cpf_responsavel = :cpf
              AND a.status = 'aberto'
        ");
        $somaStmt->execute([
            ':empresa_id' => $empresa_id,
            ':cpf' => $cpf
        ]);
        $resultadoSoma = $somaStmt->fetch(PDO::FETCH_ASSOC);
        $total_suprimentos = floatval($resultadoSoma['total_suprimentos'] ?? 0);

        // 3. Atualizar todas as aberturas em aberto desse CPF e empresa
        $updateStmt = $pdo->prepare("
            UPDATE aberturas 
            SET valor_suprimentos = :total_suprimentos
            WHERE empresa_id = :empresa_id
              AND cpf_responsavel = :cpf
              AND status = 'aberto'
        ");
        $updateStmt->execute([
            ':total_suprimentos' => $total_suprimentos,
            ':empresa_id' => $empresa_id,
            ':cpf' => $cpf
        ]);

        $pdo->commit();

        echo "<script>
            alert('Suprimento registrado e todas aberturas atualizadas com sucesso!');
            window.location.href = '../../../../frentedeloja/caixa/index.php?id=" . urlencode($empresa_id) . "';
        </script>";
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        echo "<script>
            alert('Erro ao processar o suprimento: " . addslashes($e->getMessage()) . "');
            history.back();
        </script>";
    }
}
?>
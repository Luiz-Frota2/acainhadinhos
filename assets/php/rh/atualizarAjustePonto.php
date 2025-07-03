<?php
require_once '../conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Receber e sanitizar os dados
    $cpf = $_POST['cpf'] ?? '';
    $empresa_id = $_POST['empresa_id'] ?? '';
    $data = $_POST['data'] ?? '';
    
    // Converter campos vazios para NULL
    $entrada = !empty($_POST['editEntrada']) ? $_POST['editEntrada'] : null;
    $saida_intervalo = !empty($_POST['editSaidaIntervalo']) ? $_POST['editSaidaIntervalo'] : null;
    $retorno_intervalo = !empty($_POST['editRetornoIntervalo']) ? $_POST['editRetornoIntervalo'] : null;
    $saida_final = !empty($_POST['editSaidaFinal']) ? $_POST['editSaidaFinal'] : null;

    // Verificar se todos os campos obrigatórios estão presentes
    if (empty($cpf) || empty($empresa_id) || empty($data)) {
        die("<script>
                alert('Dados incompletos para atualização!');
                history.back();
            </script>");
    }

    try {
        // Verificar se o registro existe
        $sqlCheck = "SELECT id FROM pontos 
                    WHERE cpf = :cpf 
                    AND data = :data 
                    AND empresa_id = :empresa_id
                    LIMIT 1";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->bindParam(':cpf', $cpf);
        $stmtCheck->bindParam(':data', $data);
        $stmtCheck->bindParam(':empresa_id', $empresa_id);
        $stmtCheck->execute();

        if ($stmtCheck->rowCount() === 0) {
            die("<script>
                    alert('Registro de ponto não encontrado!');
                    history.back();
                </script>");
        }

        // Verifica tolerância de 10 minutos apenas para entrada
        if ($entrada) {
            // Busca horário padrão de entrada do funcionário
            $sqlFunc = "SELECT entrada FROM funcionarios 
                        WHERE cpf = :cpf 
                        AND empresa_id = :empresa_id
                        LIMIT 1";
            $stmtFunc = $pdo->prepare($sqlFunc);
            $stmtFunc->bindParam(':cpf', $cpf);
            $stmtFunc->bindParam(':empresa_id', $empresa_id);
            $stmtFunc->execute();
            $func = $stmtFunc->fetch(PDO::FETCH_ASSOC);

            if ($func && $func['entrada']) {
                // Calcula diferença em minutos
                $entradaDiff = abs(strtotime($entrada) - strtotime($func['entrada'])) / 60;

                if ($entradaDiff <= 10) {
                    // Zera horas_pendentes na tabela pontos
                    $sqlPend = "UPDATE pontos 
                                SET horas_pendentes = NULL 
                                WHERE cpf = :cpf 
                                AND data = :data 
                                AND empresa_id = :empresa_id";
                    $stmtPend = $pdo->prepare($sqlPend);
                    $stmtPend->bindParam(':cpf', $cpf);
                    $stmtPend->bindParam(':data', $data);
                    $stmtPend->bindParam(':empresa_id', $empresa_id);
                    $stmtPend->execute();
                }
            }
        }

        // Atualizar os dados do ponto
        $sql = "UPDATE pontos SET
                    entrada = :entrada,
                    saida_intervalo = :saida_intervalo,
                    retorno_intervalo = :retorno_intervalo,
                    saida_final = :saida_final
                WHERE cpf = :cpf 
                AND data = :data 
                AND empresa_id = :empresa_id";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':entrada', $entrada);
        $stmt->bindParam(':saida_intervalo', $saida_intervalo);
        $stmt->bindParam(':retorno_intervalo', $retorno_intervalo);
        $stmt->bindParam(':saida_final', $saida_final);
        $stmt->bindParam(':cpf', $cpf);
        $stmt->bindParam(':data', $data);
        $stmt->bindParam(':empresa_id', $empresa_id);

        $stmt->execute();

        // Verificar se alguma linha foi afetada
        if ($stmt->rowCount() > 0) {
            echo "<script>
                    alert('Registro de ponto atualizado com sucesso!');
                    window.location.href = '../../../erp/rh/pontosIndividuaisMes.php?id=" . urlencode($empresa_id) . "&cpf=" . urlencode($cpf) . "';
                </script>";
        } else {
            echo "<script>
                    alert('Nenhum dado foi alterado. Verifique se os valores são diferentes dos atuais.');
                    history.back();
                </script>";
        }
    } catch (PDOException $e) {
        echo "<script>
                alert('Erro ao atualizar ponto: " . addslashes($e->getMessage()) . "');
                history.back();
            </script>";
    }
} else {
    echo "<script>
            alert('Método de requisição inválido!');
            window.location.href = '../../../erp/rh/pontosIndividuaisMes.php';
        </script>";
}
?>
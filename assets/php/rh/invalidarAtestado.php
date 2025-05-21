<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Conectar ao banco de dados (ajustar conforme sua configuração)
require_once('../conexao.php');

// Recebe os dados do formulário
$atestado_id = $_POST['atestado_id'];
$empresa_id = $_POST['empresa_id'];
$cpf = $_POST['cpf'];

// Recupera o atestado com base no ID
$stmt = $pdo->prepare("SELECT data_atestado, dias_afastado FROM atestados WHERE id = :atestado_id");
$stmt->bindParam(':atestado_id', $atestado_id, PDO::PARAM_INT);
$stmt->execute();

// Verifica se o atestado existe
if ($stmt->rowCount() > 0) {
    $atestado = $stmt->fetch(PDO::FETCH_ASSOC);
    $data_atestado = $atestado['data_atestado'];
    $dias_afastado = $atestado['dias_afastado'];

    // Calcula o intervalo de datas
    $data_inicio = date('Y-m-d', strtotime($data_atestado));
    $data_fim = date('Y-m-d', strtotime($data_atestado . " +$dias_afastado days"));

    // Busca os registros de ponto do funcionário dentro do período de afastamento
    $stmt = $pdo->prepare("
        SELECT * FROM registros_ponto 
        WHERE cpf = :cpf 
        AND data BETWEEN :data_inicio AND :data_fim
    ");
    $stmt->bindParam(':cpf', $cpf, PDO::PARAM_STR);
    $stmt->bindParam(':data_inicio', $data_inicio, PDO::PARAM_STR);
    $stmt->bindParam(':data_fim', $data_fim, PDO::PARAM_STR);
    $stmt->execute();

    // Verifica se existem registros de ponto dentro do período
    if ($stmt->rowCount() > 0) {
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($registros as $registro) {
            // Inicializa a variável de horas pendentes
            $horas_pendentes = 0;  // Inicializa com 0 horas

            // Calcula a diferença entre entrada e saída
            $hora_entrada = strtotime($registro['entrada']);
            $hora_saida = strtotime($registro['saida']);
            
            if ($hora_entrada && $hora_saida) {
                $diferenca = $hora_saida - $hora_entrada;
                
                // Calcula as horas e minutos
                $horas = floor($diferenca / 3600); // Horas
                $minutos = floor(($diferenca % 3600) / 60); // Minutos
                
                // Soma ao total de horas pendentes
                $horas_pendentes = $horas + ($minutos / 60);
            }

            // Converte as horas e minutos pendentes para o formato TIME (H:i:s)
            // Arredonda as horas e minutos para garantir que seja exibido de forma correta
            $horas_pendentes_rounded = round($horas_pendentes * 60) / 60; // Arredonda corretamente para horas e minutos
            $horas_pendentes_time = date('H:i:s', mktime(floor($horas_pendentes_rounded), floor(($horas_pendentes_rounded * 60) % 60), 0));

            // Atualiza os registros de ponto com as horas pendentes e marca o status como "Invalidado"
            $updateStmt = $pdo->prepare("
                UPDATE registros_ponto 
                SET status = 'Invalidado', horas_pendentes = :horas_pendentes
                WHERE id = :registro_id
            ");
            $updateStmt->bindParam(':horas_pendentes', $horas_pendentes_time);
            $updateStmt->bindParam(':registro_id', $registro['id'], PDO::PARAM_INT);
            $updateStmt->execute();
        }
    }

    // Mensagem de sucesso sem enviar status na URL
    echo "<script>
            alert('Atestado invalidado com sucesso! Horário pendente calculado e registros de ponto atualizados.');
            window.location.href = '../../../erp/rh/atestadosFuncionarios.php?id=" . urlencode($empresa_id) . "';
          </script>";
    exit(); // Certifique-se de terminar o script aqui após o redirecionamento
} else {
    // Caso o atestado não seja encontrado
    echo "<script>
            alert('Erro ao invalidar o atestado. Atestado não encontrado.');
            window.location.href = '../../../erp/rh/atestadosFuncionarios.php?id=" . urlencode($empresa_id) . "';
          </script>";
    exit();
}
?>

<?php

require '../conexao.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recupera o valor enviado via input hidden
    $idSelecionado = $_POST['idSelecionado'] ?? '';
    
    // O empresa_id será exatamente o valor recebido (principal_1, filial_1, etc)
    $empresa_id = $idSelecionado;

    foreach ($_POST['dias_de'] as $index => $dia_de) {
        $id = !empty($_POST['id'][$index]) ? $_POST['id'][$index] : null;
        $dia_ate = $_POST['dias_ate'][$index] ?? null;

        // Trata os horários para enviar NULL se estiverem vazios
        $primeira_hora = !empty($_POST['horario_primeira_hora'][$index]) ? $_POST['horario_primeira_hora'][$index] : null;
        $termino_primeiro_turno = !empty($_POST['horario_termino_primeiro_turno'][$index]) ? $_POST['horario_termino_primeiro_turno'][$index] : null;
        $comeco_segundo_turno = !empty($_POST['horario_comeco_segundo_turno'][$index]) ? $_POST['horario_comeco_segundo_turno'][$index] : null;
        $termino_segundo_turno = !empty($_POST['horario_termino_segundo_turno'][$index]) ? $_POST['horario_termino_segundo_turno'][$index] : null;

        if (!empty($dia_de) && !empty($primeira_hora) && !empty($termino_primeiro_turno)) {
            if ($id) {
                // Atualiza o horário existente
                $stmt = $pdo->prepare("UPDATE horarios_funcionamento 
                                       SET dia_de = ?, dia_ate = ?, primeira_hora = ?, termino_primeiro_turno = ?, 
                                           comeco_segundo_turno = ?, termino_segundo_turno = ?, empresa_id = ?
                                       WHERE id = ?");
                $stmt->execute([
                    $dia_de, 
                    $dia_ate, 
                    $primeira_hora, 
                    $termino_primeiro_turno, 
                    $comeco_segundo_turno, 
                    $termino_segundo_turno, 
                    $empresa_id, 
                    $id
                ]);
            } else {
                // Insere novo horário
                $stmt = $pdo->prepare("INSERT INTO horarios_funcionamento 
                                       (dia_de, dia_ate, primeira_hora, termino_primeiro_turno, comeco_segundo_turno, termino_segundo_turno, empresa_id) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $dia_de, 
                    $dia_ate, 
                    $primeira_hora, 
                    $termino_primeiro_turno, 
                    $comeco_segundo_turno, 
                    $termino_segundo_turno, 
                    $empresa_id
                ]);
            }
        }
    }

    echo "<script>alert('Dados salvos com sucesso!'); window.location.href='../../../erp/delivery/horarioFuncionamento.php?id=$idSelecionado';</script>";
    exit();
} else {
    echo "<script>alert('Erro ao processar os dados!'); history.back();</script>";
    exit();
}

?>
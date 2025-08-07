<?php
require_once '../conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cpf = $_POST['cpf'] ?? '';
    $empresa_id = $_POST['empresa_id'] ?? '';
    $data_br = $_POST['data'] ?? '';

    $data_obj = DateTime::createFromFormat('d/m/Y', $data_br);
    if (!$data_obj) {
        die("<script>
                alert('Formato de data inválido! Use DD/MM/AAAA');
                history.back();
            </script>");
    }
    $data_formatada = $data_obj->format('Y-m-d');

    function formatarHora($hora) {
        if (empty($hora)) return null;
        if (preg_match('/^\d{2}:\d{2}$/', $hora)) {
            return $hora . ':00';
        }
        return $hora;
    }

    $entrada           = formatarHora($_POST['entrada'] ?? null);
    $saida_intervalo   = formatarHora($_POST['saida_intervalo'] ?? null);
    $retorno_intervalo = formatarHora($_POST['retorno_intervalo'] ?? null);
    $saida_final       = formatarHora($_POST['saida_final'] ?? null);

    if (empty($cpf) || empty($empresa_id) || empty($data_br)) {
        die("<script>
                alert('Dados incompletos para atualização!');
                history.back();
            </script>");
    }

    try {
        $sqlCheck = "SELECT id, entrada, saida_intervalo, retorno_intervalo, saida_final 
                     FROM pontos 
                     WHERE cpf = :cpf 
                     AND data = :data 
                     AND empresa_id = :empresa_id
                     LIMIT 1";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->bindParam(':cpf', $cpf);
        $stmtCheck->bindParam(':data', $data_formatada);
        $stmtCheck->bindParam(':empresa_id', $empresa_id);
        $stmtCheck->execute();

        $registroAtual = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$registroAtual) {
            die("<script>
                    alert('Registro não encontrado!');
                    history.back();
                </script>");
        }

        // Função para comparar horários ignorando segundos
        function horarioSemSegundos($hora) {
            if (!$hora) return null;
            return substr($hora, 0, 5); // Retorna apenas HH:MM
        }

        $alteracoes = [];
        if (horarioSemSegundos($entrada) !== horarioSemSegundos($registroAtual['entrada'])) $alteracoes['entrada'] = $entrada;
        if (horarioSemSegundos($saida_intervalo) !== horarioSemSegundos($registroAtual['saida_intervalo'])) $alteracoes['saida_intervalo'] = $saida_intervalo;
        if (horarioSemSegundos($retorno_intervalo) !== horarioSemSegundos($registroAtual['retorno_intervalo'])) $alteracoes['retorno_intervalo'] = $retorno_intervalo;
        if (horarioSemSegundos($saida_final) !== horarioSemSegundos($registroAtual['saida_final'])) $alteracoes['saida_final'] = $saida_final;

        if (empty($alteracoes)) {
            echo "<script>
                    alert('Nenhum dado alterado!');
                    window.location.href = '../../../erp/rh/pontosIndividuaisMes.php?id=" . urlencode($empresa_id) . "&cpf=" . urlencode($cpf) . "';
                </script>";
            exit;
        }

        $sql = "UPDATE pontos SET ";
        $params = [];

        foreach ($alteracoes as $campo => $valor) {
            $sql .= "$campo = :$campo, ";
            $params[":$campo"] = $valor;
        }

        $sql = rtrim($sql, ', ') . " WHERE cpf = :cpf AND data = :data AND empresa_id = :empresa_id";
        $params[':cpf'] = $cpf;
        $params[':data'] = $data_formatada;
        $params[':empresa_id'] = $empresa_id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() > 0) {
            echo "<script>
                    alert('Registro atualizado com sucesso!');
                    window.location.href = '../../../erp/rh/pontosIndividuaisMes.php?id=" . urlencode($empresa_id) . "&cpf=" . urlencode($cpf) . "';
                </script>";
        } else {
            echo "<script>
                    alert('Nenhum dado alterado!');
                    window.location.href = '../../../erp/rh/pontosIndividuaisMes.php?id=" . urlencode($empresa_id) . "&cpf=" . urlencode($cpf) . "';
                </script>";
        }

    } catch (PDOException $e) {
        echo "<script>
                alert('Erro ao atualizar: " . addslashes($e->getMessage()) . "');
                history.back();
            </script>";
    }
} else {
    echo "<script>
            alert('Requisição inválida!');
            window.location.href = '../../../erp/rh/pontosIndividuaisMes.php';
        </script>";
}
?>
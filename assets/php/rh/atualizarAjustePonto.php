<?php
require_once '../conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Receber e sanitizar os dados
    $cpf = $_POST['cpf'] ?? '';
    $empresa_id = $_POST['empresa_id'] ?? '';
    $data_br = $_POST['data'] ?? '';
    
    // Converter data para formato do banco (Y-m-d)
    $data_obj = DateTime::createFromFormat('d/m/Y', $data_br);
    if (!$data_obj) {
        die("<script>
                alert('Formato de data inválido! Use DD/MM/AAAA');
                history.back();
            </script>");
    }
    $data_formatada = $data_obj->format('Y-m-d');

    // Função para formatar horários
    function formatarHora($hora) {
        if (empty($hora)) return null;
        if (preg_match('/^\d{2}:\d{2}$/', $hora)) {
            return $hora . ':00';
        }
        return $hora;
    }

    // Converter e formatar campos de tempo
    $entrada = formatarHora($_POST['entrada'] ?? null);
    $saida_intervalo = formatarHora($_POST['saida_intervalo'] ?? null);
    $retorno_intervalo = formatarHora($_POST['retorno_intervalo'] ?? null);
    $saida_final = formatarHora($_POST['saida_final'] ?? null);

    // Verificar campos obrigatórios
    if (empty($cpf) || empty($empresa_id) || empty($data_br)) {
        die("<script>
                alert('Dados incompletos para atualização!');
                history.back();
            </script>");
    }

    try {
        // Verificar se o registro existe
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

        // Verificar alterações
        $alteracoes = [];
        if ($entrada != $registroAtual['entrada']) $alteracoes['entrada'] = $entrada;
        if ($saida_intervalo != $registroAtual['saida_intervalo']) $alteracoes['saida_intervalo'] = $saida_intervalo;
        if ($retorno_intervalo != $registroAtual['retorno_intervalo']) $alteracoes['retorno_intervalo'] = $retorno_intervalo;
        if ($saida_final != $registroAtual['saida_final']) $alteracoes['saida_final'] = $saida_final;

        if (empty($alteracoes)) {
            echo "<script>
                    alert('Nenhuma alteração detectada!');
                    window.location.href = '../../../erp/rh/pontosIndividuaisMes.php?id=" . urlencode($empresa_id) . "&cpf=" . urlencode($cpf) . "';
                </script>";
            exit;
        }

        // Construir e executar a query
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

        // Redirecionar após sucesso
        if ($stmt->rowCount() > 0) {
            echo "<script>
                    alert('Registro atualizado com sucesso!');
                history.back();';
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
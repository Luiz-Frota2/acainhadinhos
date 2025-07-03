<?php
require_once '../conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Verificar dados recebidos
    error_log("Dados recebidos no POST: " . print_r($_POST, true));
    
    // Receber e sanitizar os dados
    $cpf = $_POST['cpf'] ?? '';
    $empresa_id = $_POST['empresa_id'] ?? '';
    $data_br = $_POST['data'] ?? ''; // Data no formato d/m/Y
    
    // Converter data para formato do banco (Y-m-d)
    $data_obj = DateTime::createFromFormat('d/m/Y', $data_br);
    if (!$data_obj) {
        die(json_encode([
            'status' => 'error',
            'message' => 'Formato de data inválido! Use DD/MM/AAAA'
        ]));
    }
    $data_formatada = $data_obj->format('Y-m-d');

    // Função para formatar horários
    function formatarHora($hora) {
        if (empty($hora)) return null;
        
        // Se já estiver no formato HH:MM
        if (preg_match('/^\d{2}:\d{2}$/', $hora)) {
            return $hora . ':00'; // Adiciona os segundos
        }
        
        return $hora;
    }

    // Converter e formatar campos de tempo
    $entrada = formatarHora($_POST['entrada'] ?? null);
    $saida_intervalo = formatarHora($_POST['saida_intervalo'] ?? null);
    $retorno_intervalo = formatarHora($_POST['retorno_intervalo'] ?? null);
    $saida_final = formatarHora($_POST['saida_final'] ?? null);

    // Verificar se todos os campos obrigatórios estão presentes
    if (empty($cpf) || empty($empresa_id) || empty($data_br)) {
        die(json_encode([
            'status' => 'error',
            'message' => 'Dados incompletos para atualização! CPF, Empresa ID e Data são obrigatórios.'
        ]));
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
            die(json_encode([
                'status' => 'error',
                'message' => "Registro não encontrado com os parâmetros:\nCPF: $cpf\nData: $data_br\nEmpresa ID: $empresa_id"
            ]));
        }

        // Verificar se há alterações
        $alteracoes = [];
        if ($entrada != $registroAtual['entrada']) $alteracoes['entrada'] = $entrada;
        if ($saida_intervalo != $registroAtual['saida_intervalo']) $alteracoes['saida_intervalo'] = $saida_intervalo;
        if ($retorno_intervalo != $registroAtual['retorno_intervalo']) $alteracoes['retorno_intervalo'] = $retorno_intervalo;
        if ($saida_final != $registroAtual['saida_final']) $alteracoes['saida_final'] = $saida_final;

        if (empty($alteracoes)) {
            die(json_encode([
                'status' => 'info',
                'message' => 'Nenhuma alteração detectada. Os valores são iguais aos atuais.'
            ]));
        }

        // Construir a query dinamicamente
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

        // Executar a atualização
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Verificar se a atualização foi bem-sucedida
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Registro de ponto atualizado com sucesso!',
                'changes' => $alteracoes
            ]);
        } else {
            echo json_encode([
                'status' => 'warning',
                'message' => 'A atualização foi executada, mas nenhuma linha foi afetada.'
            ]);
        }

    } catch (PDOException $e) {
        error_log("Erro ao atualizar ponto: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Erro ao atualizar ponto: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Método de requisição inválido!'
    ]);
}
?>
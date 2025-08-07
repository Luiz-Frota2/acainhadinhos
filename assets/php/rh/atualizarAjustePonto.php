<?php
require_once '../conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cpf = $_POST['cpf'] ?? '';
    $empresa_id = $_POST['empresa_id'] ?? '';
    $data_input = $_POST['data'] ?? '';
    $ponto_id = $_POST['ponto_id'] ?? null;

    if (!$cpf || !$empresa_id || !$data_input || !$ponto_id) {
        die("<script>
                alert('Dados incompletos!');
                history.back();
            </script>");
    }

    // Normaliza data (pode vir como d/m/Y ou Y-m-d)
    if (strpos($data_input, '/') !== false) {
        $data_obj = DateTime::createFromFormat('d/m/Y', $data_input);
    } else {
        $data_obj = DateTime::createFromFormat('Y-m-d', $data_input);
    }

    if (!$data_obj) {
        die("<script>alert('Data inválida'); history.back();</script>");
    }

    $data_formatada = $data_obj->format('Y-m-d');

    // Função para formatar hora como HH:MM:SS
    function formatarHora($hora)
    {
        if (empty($hora)) return null;
        if (preg_match('/^\d{2}:\d{2}$/', $hora)) return $hora . ':00';
        return $hora;
    }

    $entrada = formatarHora($_POST['entrada'] ?? '');
    $saida_intervalo = formatarHora($_POST['saida_intervalo'] ?? '');
    $retorno_intervalo = formatarHora($_POST['retorno_intervalo'] ?? '');
    $saida_final = formatarHora($_POST['saida_final'] ?? '');

    try {
        // Buscar dados atuais
        $sqlCheck = "SELECT entrada, saida_intervalo, retorno_intervalo, saida_final 
                     FROM pontos 
                     WHERE id = :id AND cpf = :cpf AND data = :data AND empresa_id = :empresa_id";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute([
            ':id' => $ponto_id,
            ':cpf' => $cpf,
            ':data' => $data_formatada,
            ':empresa_id' => $empresa_id
        ]);

        $registroAtual = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$registroAtual) {
            die("<script>alert('Registro não encontrado!'); history.back();</script>");
        }

        // Verificar se houve mudança real (comparando HH:MM)
        $alteracoes = [];
        if (substr($entrada, 0, 5) !== substr($registroAtual['entrada'], 0, 5)) {
            $alteracoes['entrada'] = $entrada;
        }
        if (substr($saida_intervalo, 0, 5) !== substr($registroAtual['saida_intervalo'], 0, 5)) {
            $alteracoes['saida_intervalo'] = $saida_intervalo;
        }
        if (substr($retorno_intervalo, 0, 5) !== substr($registroAtual['retorno_intervalo'], 0, 5)) {
            $alteracoes['retorno_intervalo'] = $retorno_intervalo;
        }
        if (substr($saida_final, 0, 5) !== substr($registroAtual['saida_final'], 0, 5)) {
            $alteracoes['saida_final'] = $saida_final;
        }

        if (empty($alteracoes)) {
            echo "<script>
                    alert('Nenhuma alteração detectada!');
                            history.back();
                  </script>";
            exit;
        }

        // Montar SQL de atualização
        $sqlUpdate = "UPDATE pontos SET ";
        $params = [];

        foreach ($alteracoes as $campo => $valor) {
            $sqlUpdate .= "$campo = :$campo, ";
            $params[":$campo"] = $valor;
        }

        $sqlUpdate = rtrim($sqlUpdate, ', ') . " WHERE id = :id";
        $params[':id'] = $ponto_id;

        $stmt = $pdo->prepare($sqlUpdate);
        $stmt->execute($params);

        echo "<script>
                alert('Registro atualizado com sucesso!');
                history.back();
              </script>";
    } catch (PDOException $e) {
        echo "<script>
                alert('Erro ao atualizar: " . addslashes($e->getMessage()) . "');
                history.back();
              </script>";
    }
} else {
    echo "<script>
            alert('Requisição inválida!');
                history.back();
          </script>";
}

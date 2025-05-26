<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../../assets/php/conexao.php';
date_default_timezone_set('America/Sao_Paulo');

$cpf = $_POST['cpf'] ?? null;
$data = $_POST['data'] ?? null;
$horaAtual = $_POST['hora_atual'] ?? null;
$acao = $_POST['acao'] ?? null;
$fotoBase64 = $_POST['fotoBase64'] ?? null;
$localizacao = $_POST['localizacao'] ?? null;
$empresa_id = $_POST['id_selecionado'] ?? null;

// Verifica dados obrigatórios
if (!$cpf || !$data || !$horaAtual || !$acao || !$empresa_id) {
    echo "<script>alert('Dados insuficientes para processar.'); history.back();</script>";
    exit;
}

// Decodifica a imagem base64, se enviada
$fotoBinaria = null;
if ($fotoBase64) {
    $fotoBinaria = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $fotoBase64));
}

// Buscar nome do funcionário
$sqlFuncionario = "SELECT nome FROM funcionarios WHERE cpf = ?";
$stmtFuncionario = $pdo->prepare($sqlFuncionario);
$stmtFuncionario->execute([$cpf]);
$funcionario = $stmtFuncionario->fetch(PDO::FETCH_ASSOC);

if (!$funcionario) {
    echo "<script>alert('Funcionário não encontrado.'); window.location.href='../../sistemadeponto/index.php?id=$empresa_id';</script>";
    exit;
}

$nomeFuncionario = $funcionario['nome'];

// Buscar registro de ponto do dia
$sqlBusca = "SELECT * FROM pontos WHERE cpf = ? AND data = ? AND empresa_id = ?";
$stmtBusca = $pdo->prepare($sqlBusca);
$stmtBusca->execute([$cpf, $data, $empresa_id]);
$registro = $stmtBusca->fetch(PDO::FETCH_ASSOC);

// Buscar horários de referência
$sqlHorario = "SELECT entrada, saida_intervalo, retorno_intervalo, saida_final FROM funcionarios WHERE cpf = ?";
$stmtHorario = $pdo->prepare($sqlHorario);
$stmtHorario->execute([$cpf]);
$horarioRef = $stmtHorario->fetch(PDO::FETCH_ASSOC);

if (!$horarioRef) {
    echo "<script>alert('Horário de referência não encontrado.'); window.location.href='../../sistemadeponto/index.php?id=$empresa_id';</script>";
    exit;
}

function excedeuTolerancia($horaRef, $horaAtual)
{
    $ref = strtotime($horaRef);
    $atual = strtotime($horaAtual);
    return ($atual - $ref) > 600; // 600 segundos = 10 minutos, mas seu código usa 10min? (Você usa 600, que são 10 min, se quiser 20 min, use 1200)
}

switch ($acao) {
    case 'entrada':
        if ($registro && $registro['entrada']) {
            echo "<script>alert('Entrada já registrada.'); history.back();</script>";
            exit;
        }

        $campos = [
            'cpf' => $cpf,
            'nome' => $nomeFuncionario,
            'data' => $data,
            'entrada' => $horaAtual,
            'foto_entrada' => $fotoBinaria,
            'localizacao_entrada' => $localizacao,
            'empresa_id' => $empresa_id
        ];

        if (excedeuTolerancia($horarioRef['entrada'], $horaAtual)) {
            $campos['horas_pendentes'] = gmdate("H:i:s", strtotime($horaAtual) - strtotime($horarioRef['entrada']));
        }

        if ($registro) {
            $sqlUpdate = "UPDATE pontos SET entrada = :entrada, foto_entrada = :foto_entrada, localizacao_entrada = :localizacao_entrada, horas_pendentes = COALESCE(horas_pendentes, :horas_pendentes) WHERE id = :id AND empresa_id = :empresa_id";
            $stmt = $pdo->prepare($sqlUpdate);
            $campos['id'] = $registro['id'];
        } else {
            $sqlInsert = "INSERT INTO pontos (cpf, nome, data, entrada, foto_entrada, localizacao_entrada, horas_pendentes, empresa_id) VALUES (:cpf, :nome, :data, :entrada, :foto_entrada, :localizacao_entrada, :horas_pendentes, :empresa_id)";
            $stmt = $pdo->prepare($sqlInsert);
            if (!isset($campos['horas_pendentes'])) {
                $campos['horas_pendentes'] = null;
            }
        }

        $stmt->execute($campos);
        echo "<script>alert('Entrada registrada com sucesso!'); window.location.href='../../sistemadeponto/index.php?id=$empresa_id';</script>";
        break;

    case 'saida_intervalo':
        if (!$registro || $registro['saida_intervalo']) {
            echo "<script>alert('Saída para intervalo já registrada ou entrada não encontrada.'); history.back();</script>";
            exit;
        }

        if (strtotime($horaAtual) < strtotime($horarioRef['saida_intervalo'])) {
            echo "<script>alert('Ainda não é hora de sair para o intervalo.'); history.back();</script>";
            exit;
        }

        $sqlUpdate = "UPDATE pontos SET saida_intervalo = :saida_intervalo, foto_saida_intervalo = :foto_saida_intervalo, localizacao_saida_intervalo = :localizacao_saida_intervalo WHERE id = :id AND empresa_id = :empresa_id";
        $stmt = $pdo->prepare($sqlUpdate);
        $stmt->execute([
            'saida_intervalo' => $horaAtual,
            'foto_saida_intervalo' => $fotoBinaria,
            'localizacao_saida_intervalo' => $localizacao,
            'id' => $registro['id'],
            'empresa_id' => $empresa_id
        ]);

        echo "<script>alert('Saída para intervalo registrada com sucesso!'); window.location.href='../../sistemadeponto/index.php?id=$empresa_id';</script>";
        break;

    case 'retorno_intervalo':
        if (!$registro || $registro['retorno_intervalo']) {
            echo "<script>alert('Retorno de intervalo já registrado ou entrada não encontrada.'); history.back();</script>";
            exit;
        }

        if (!$registro['saida_intervalo']) {
            echo "<script>alert('Você precisa registrar a saída para o intervalo primeiro.'); history.back();</script>";
            exit;
        }

        $horasPendentes = null;

        if (excedeuTolerancia($horarioRef['retorno_intervalo'], $horaAtual)) {
            $diferencaSegundos = strtotime($horaAtual) - strtotime($horarioRef['retorno_intervalo']);
            $novaPendencia = $diferencaSegundos;

            if (!empty($registro['horas_pendentes'])) {
                $partes = explode(':', $registro['horas_pendentes']);
                $totalSegundosExistente = ($partes[0] * 3600) + ($partes[1] * 60) + $partes[2];
                $novaPendencia += $totalSegundosExistente;
            }

            $horasPendentes = gmdate("H:i:s", $novaPendencia);
        } else {
            $horasPendentes = $registro['horas_pendentes'];
        }

        $sqlUpdate = "UPDATE pontos 
                      SET retorno_intervalo = :retorno_intervalo, 
                          foto_retorno_intervalo = :foto_retorno_intervalo, 
                          localizacao_retorno_intervalo = :localizacao_retorno_intervalo, 
                          horas_pendentes = :horas_pendentes 
                      WHERE id = :id AND empresa_id = :empresa_id";

        $stmt = $pdo->prepare($sqlUpdate);
        $stmt->execute([
            'retorno_intervalo' => $horaAtual,
            'foto_retorno_intervalo' => $fotoBinaria,
            'localizacao_retorno_intervalo' => $localizacao,
            'horas_pendentes' => $horasPendentes,
            'id' => $registro['id'],
            'empresa_id' => $empresa_id
        ]);

        echo "<script>alert('Retorno de intervalo registrado com sucesso!'); window.location.href='../../sistemadeponto/index.php?id=$empresa_id';</script>";
        break;

    case 'saida_final':
        // Se não encontrou o registro na data informada, tenta buscar o registro do dia anterior
        if (!$registro) {
            $dataAnterior = date('Y-m-d', strtotime($data . ' -1 day'));
            $stmtBusca->execute([$cpf, $dataAnterior, $empresa_id]);
            $registro = $stmtBusca->fetch(PDO::FETCH_ASSOC);
            $data = $dataAnterior; // atualiza para inserir a saída na data correta
        }

        if (!$registro || !$registro['entrada']) {
            echo "<script>alert('Registro de entrada não encontrado.'); history.back();</script>";
            exit;
        }

        // Corrige referência de saída final (considera que pode ser depois da meia-noite)
        $saidaFinalEsperada = strtotime($horarioRef['saida_final']);
        $horaAtualTimestamp = strtotime($horaAtual);

        // Se a saída esperada é menor que a entrada, considera que passa da meia-noite
        if (strtotime($horarioRef['saida_final']) < strtotime($horarioRef['entrada'])) {
            $saidaFinalEsperada = strtotime($data . ' ' . $horarioRef['saida_final'] . ' +1 day');
        } else {
            $saidaFinalEsperada = strtotime($data . ' ' . $horarioRef['saida_final']);
        }

        // Se atual for menor que a entrada, considera que também passou da meia-noite
        if ($horaAtualTimestamp < strtotime($registro['entrada'])) {
            $horaAtualTimestamp = strtotime($data . ' ' . $horaAtual . ' +1 day');
        } else {
            $horaAtualTimestamp = strtotime($data . ' ' . $horaAtual);
        }

        // Calcula hora extra, se houver
        $horaExtra = null;
        if ($horaAtualTimestamp > $saidaFinalEsperada) {
            $horaExtra = gmdate("H:i:s", $horaAtualTimestamp - $saidaFinalEsperada);
        }

        $sqlUpdate = "UPDATE pontos 
                      SET saida_final = :saida_final, 
                          foto_saida_final = :foto_saida_final, 
                          localizacao_saida_final = :localizacao_saida_final, 
                          hora_extra = :hora_extra 
                      WHERE id = :id AND empresa_id = :empresa_id";

        $stmt = $pdo->prepare($sqlUpdate);
        $stmt->execute([
            'saida_final' => date('H:i:s', $horaAtualTimestamp),
            'foto_saida_final' => $fotoBinaria,
            'localizacao_saida_final' => $localizacao,
            'hora_extra' => $horaExtra,
            'id' => $registro['id'],
            'empresa_id' => $empresa_id
        ]);

        echo "<script>alert('Saída final registrada com sucesso!'); window.location.href='../../sistemadeponto/index.php?id=$empresa_id';</script>";
        break;

    default:
        echo "<script>alert('Ação inválida.'); history.back();</script>";
        break;
}

?>

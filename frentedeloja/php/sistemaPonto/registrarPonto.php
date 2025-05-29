<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../../assets/php/conexao.php';

$cpf = $_POST['cpf'];
$data = $_POST['data'];
$horaAtual = $_POST['hora_atual'];
$acao = $_POST['acao'];
$fotoBase64 = $_POST['fotoBase64'];
$localizacao = $_POST['localizacao'];
$empresa_id = $_POST['id_selecionado'];

// Validação obrigatória de localização e foto
if (empty($localizacao) || empty($fotoBase64)) {
    echo "<script>alert('É obrigatório registrar a localização e a foto para registrar o ponto.'); history.back();</script>";
    exit;
}

$fotoBinaria = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $fotoBase64));

// Buscar nome do funcionário e escala
$sqlFuncionario = "SELECT nome, dia_inicio, dia_folga FROM funcionarios WHERE cpf = ?";
$stmtFuncionario = $pdo->prepare($sqlFuncionario);
$stmtFuncionario->execute([$cpf]);
$funcionario = $stmtFuncionario->fetch(PDO::FETCH_ASSOC);

if (!$funcionario) {
    echo "<script>alert('Funcionário não encontrado.'); window.location.href='../../sistemadeponto/index.php?id=$empresa_id';</script>";
    exit;
}

$nomeFuncionario = $funcionario['nome'];
$diaInicio = $funcionario['dia_inicio'];
$diaFolga = $funcionario['dia_folga'];

// Função para obter o nome do dia da semana em português
function nomeDiaSemana($data)
{
    $dias = ['domingo', 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado'];
    return $dias[date('w', strtotime($data))];
}

// Função para obter o próximo dia da semana a partir de um dia base
function proximoDia($diaAtual, $diasDepois = 1)
{
    $dias = ['domingo', 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado'];
    $idx = array_search($diaAtual, $dias);
    $novoIdx = ($idx + $diasDepois) % 7;
    return $dias[$novoIdx];
}

// Verifica se hoje é folga
$hojeDiaSemana = nomeDiaSemana($data);
if ($acao == 'entrada' && $hojeDiaSemana == $diaFolga) {
    echo "<script>alert('Hoje é sua folga! Não é permitido registrar ponto.'); window.location.href='../../sistemadeponto/index.php?id=$empresa_id';</script>";
    exit;
}

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
    return ($atual - $ref) > 600;
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

        if (!isset($campos['horas_pendentes'])) {
            $campos['horas_pendentes'] = null;
        }

        if ($registro) {
            $sqlUpdate = "UPDATE pontos SET entrada = :entrada, foto_entrada = :foto_entrada, localizacao_entrada = :localizacao_entrada, horas_pendentes = COALESCE(horas_pendentes, :horas_pendentes) WHERE id = :id AND empresa_id = :empresa_id";
            $stmt = $pdo->prepare($sqlUpdate);
            $campos['id'] = $registro['id'];
        } else {
            $sqlInsert = "INSERT INTO pontos (cpf, nome, data, entrada, foto_entrada, localizacao_entrada, horas_pendentes, empresa_id) VALUES (:cpf, :nome, :data, :entrada, :foto_entrada, :localizacao_entrada, :horas_pendentes, :empresa_id)";
            $stmt = $pdo->prepare($sqlInsert);
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
        if (!$registro) {
            // Tenta buscar registro do dia anterior se a hora atual for entre 21:00 e 05:59
            $horaAtualTimestamp = strtotime($horaAtual);
            if (
                ($horaAtualTimestamp !== false && $horaAtualTimestamp < strtotime('06:00:00')) ||
                ($horaAtualTimestamp !== false && $horaAtualTimestamp >= strtotime('21:00:00'))
            ) {
                // Se for entre 21:00 e 23:59, considera o dia atual, mas se não encontrou registro, tenta o anterior
                // Se for entre 00:00 e 05:59, tenta o registro do dia anterior
                $dataAnterior = date('Y-m-d', strtotime($data . ' -1 day'));
                $sqlBuscaAnterior = "SELECT * FROM pontos WHERE cpf = ? AND data = ? AND empresa_id = ?";
                $stmtBuscaAnterior = $pdo->prepare($sqlBuscaAnterior);
                $stmtBuscaAnterior->execute([$cpf, $dataAnterior, $empresa_id]);
                $registroAnterior = $stmtBuscaAnterior->fetch(PDO::FETCH_ASSOC);

                if ($registroAnterior) {
                    $registro = $registroAnterior;
                    $dataRegistro = $dataAnterior;
                } else {
                    $dataRegistro = $data;
                }
            } else {
                $dataRegistro = $data;
            }
        } else {
            $dataRegistro = $data;
        }

        if (!$registro) {
            echo "<script>alert('Registro de entrada não encontrado.'); history.back();</script>";
            exit;
        }

        // Verifica se amanhã é folga
        $dataAmanha = date('Y-m-d', strtotime($dataRegistro . ' +1 day'));
        $diaAmanha = nomeDiaSemana($dataAmanha);

        $isVesperaFolga = ($diaAmanha == $diaFolga);

        // Mensagem e registro de folga se for véspera da folga
        if ($isVesperaFolga) {
            // Insere folga
            $sqlFolga = "INSERT INTO folgas (cpf, nome, data_folga) VALUES (?, ?, ?)";
            $stmtFolga = $pdo->prepare($sqlFolga);
            $stmtFolga->execute([$cpf, $nomeFuncionario, $dataAmanha]);
            $msgFolga = "Atenção: Amanhã ({$diaFolga}) é sua folga! Aproveite seu descanso.";
        } else {
            $msgFolga = "";
        }

        // Registro normal da saída final
        if (empty($registro['saida_intervalo']) && empty($registro['retorno_intervalo'])) {
            if (strtotime($horaAtual) >= strtotime($horarioRef['saida_final'])) {
                $horaExtra = strtotime($horaAtual) > strtotime($horarioRef['saida_final']) ?
                    gmdate("H:i:s", strtotime($horaAtual) - strtotime($horarioRef['saida_final'])) : null;

                $sqlUpdate = "UPDATE pontos SET saida_final = :saida_final, foto_saida_final = :foto_saida_final, localizacao_saida_final = :localizacao_saida_final, hora_extra = :hora_extra WHERE id = :id AND empresa_id = :empresa_id";
                $stmt = $pdo->prepare($sqlUpdate);
                $stmt->execute([
                    'saida_final' => $horaAtual,
                    'foto_saida_final' => $fotoBinaria,
                    'localizacao_saida_final' => $localizacao,
                    'hora_extra' => $horaExtra,
                    'id' => $registro['id'],
                    'empresa_id' => $empresa_id
                ]);
            } else {
                echo "<script>alert('Você só pode registrar a saída após o fim do expediente.'); history.back();</script>";
                exit;
            }
        } elseif (!empty($registro['saida_intervalo']) && empty($registro['retorno_intervalo'])) {
            if (excedeuTolerancia($horarioRef['saida_final'], $horaAtual)) {
                echo "<script>alert('Você excedeu a tolerância de 10 minutos para a saída final.'); window.location.href='../../sistemadeponto/index.php?id=$empresa_id';</script>";
                exit;
            }
            echo "<script>alert('Você deve registrar o retorno de intervalo antes da saída final.'); history.back();</script>";
            exit;
        } else {
            if (strtotime($horaAtual) >= strtotime($horarioRef['saida_final'])) {
                $horaExtra = strtotime($horaAtual) > strtotime($horarioRef['saida_final']) ?
                    gmdate("H:i:s", strtotime($horaAtual) - strtotime($horarioRef['saida_final'])) : null;

                $sqlUpdate = "UPDATE pontos SET saida_final = :saida_final, foto_saida_final = :foto_saida_final, localizacao_saida_final = :localizacao_saida_final, hora_extra = :hora_extra WHERE id = :id AND empresa_id = :empresa_id";
                $stmt = $pdo->prepare($sqlUpdate);
                $stmt->execute([
                    'saida_final' => $horaAtual,
                    'foto_saida_final' => $fotoBinaria,
                    'localizacao_saida_final' => $localizacao,
                    'hora_extra' => $horaExtra,
                    'id' => $registro['id'],
                    'empresa_id' => $empresa_id
                ]);
            } else {
                echo "<script>alert('Você só pode registrar a saída após o fim do expediente.'); history.back();</script>";
                exit;
            }
        }

        // Atualiza escala 5x1 após saída final
        // Próximo dia início é o dia após a folga, próxima folga é 5 dias depois
        if ($isVesperaFolga) {
            $novoDiaInicio = proximoDia($diaFolga, 1); // Dia após a folga
            $novoDiaFolga = proximoDia($novoDiaInicio, 5); // 5 dias depois do novo início
            $sqlUpdateFunc = "UPDATE funcionarios SET dia_inicio = ?, dia_folga = ? WHERE cpf = ?";
            $stmtFunc = $pdo->prepare($sqlUpdateFunc);
            $stmtFunc->execute([$novoDiaInicio, $novoDiaFolga, $cpf]);
        }

        $msgFinal = "Saída final registrada com sucesso!";
        if ($msgFolga) $msgFinal .= "\\n$msgFolga";
        echo "<script>alert('$msgFinal'); window.location.href='../../sistemadeponto/index.php?id=$empresa_id';</script>";
        break;

    default:
        echo "<script>alert('Ação inválida.'); history.back();</script>";
        break;
}

?>
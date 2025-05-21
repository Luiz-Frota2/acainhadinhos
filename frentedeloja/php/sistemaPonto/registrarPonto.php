<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../../../assets/php/conexao.php';

// Verifica se a requisição é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<script>alert('Requisição inválida.'); history.back();</script>";
    exit;
}

// Recebe dados do formulário e sanitiza
$data = $_POST['data'] ?? date('Y-m-d');
$horaAtual = $_POST['hora_atual'] ?? date('H:i:s');
$cpf = trim($_POST['cpf'] ?? '');
$acao = trim($_POST['acao'] ?? '');
$empresa_id = trim($_POST['id_selecionado'] ?? '');
$foto_base64 = $_POST['fotoBase64'] ?? '';
$localizacao = trim($_POST['localizacao'] ?? '');

// Valida campos obrigatórios
if (empty($cpf) || empty($acao) || empty($empresa_id) || empty($localizacao)) {
    echo "<script>alert('Dados incompletos! Verifique se todos os campos foram preenchidos corretamente.'); history.back();</script>";
    exit;
}

// Processa a imagem base64
$foto_base64 = str_replace(['data:image/jpeg;base64,','data:image/png;base64,',' '],['','','+'],$foto_base64);
$foto_binaria = base64_decode($foto_base64);
if ($foto_binaria === false) {
    echo "<script>alert('Erro ao processar a imagem.'); history.back();</script>";
    exit;
}

// Busca funcionário pelo CPF e empresa
$stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE cpf = :cpf AND empresa_id = :empresa_id LIMIT 1");
$stmt->execute([':cpf' => $cpf, ':empresa_id' => $empresa_id]);
$func = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$func) {
    echo "<script>alert('Funcionário não encontrado.'); history.back();</script>";
    exit;
}

// Traduz dia da semana para português
$diaSemana = strtolower(date('l', strtotime($data)));
$diasTraduzidos = [
    'sunday'=>'domingo','monday'=>'segunda','tuesday'=>'terca',
    'wednesday'=>'quarta','thursday'=>'quinta','friday'=>'sexta','saturday'=>'sabado'
];
$diaTraduzido = $diasTraduzidos[$diaSemana] ?? '';

// Verifica se trabalha hoje
$diasSemanaArr = ['domingo','segunda','terca','quarta','quinta','sexta','sabado'];
$indexAtual   = array_search($diaTraduzido, $diasSemanaArr);
$indexInicio  = array_search(strtolower($func['dia_inicio']), $diasSemanaArr);
$indexTermino = array_search(strtolower($func['dia_termino']), $diasSemanaArr);

$trabalhaHoje = false;
if ($indexInicio !== false && $indexTermino !== false && $indexAtual !== false) {
    if ($indexInicio <= $indexTermino) {
        if ($indexAtual >= $indexInicio && $indexAtual <= $indexTermino) {
            $trabalhaHoje = true;
        }
    } else {
        if ($indexAtual >= $indexInicio || $indexAtual <= $indexTermino) {
            $trabalhaHoje = true;
        }
    }
}

if (!$trabalhaHoje) {
    echo "<script>alert('Hoje o funcionário não trabalha.'); history.back();</script>";
    exit;
}

// Define entrada e saída esperadas conforme turno e horário atual
$horaAtualHora = (int)date('H', strtotime($horaAtual));
if (strtolower($func['escala'] ?? '') === 'noturno') {
    $entradaEsperada = $func['hora_entrada_segundo_turno'];
    $saidaEsperada  = $func['hora_saida_segundo_turno'];
} else {
    if ($horaAtualHora >= 8 && $horaAtualHora < 13) {
        $entradaEsperada = $func['hora_entrada_primeiro_turno'];
        $saidaEsperada  = $func['hora_saida_primeiro_turno'];
    } elseif ($horaAtualHora >= 13 && $horaAtualHora < 19) {
        $entradaEsperada = $func['hora_entrada_segundo_turno'];
        $saidaEsperada  = $func['hora_saida_segundo_turno'];
    } else {
        $entradaEsperada = $func['hora_entrada_primeiro_turno'];
        $saidaEsperada  = $func['hora_saida_primeiro_turno'];
    }
}

// Calcula tolerância de 10 minutos na entrada
$entradaTolerada = date('H:i:s', strtotime('+10 minutes', strtotime($entradaEsperada)));

$status = '';
$horasPendentes = '00:00:00';
$horaExtra      = '00:00:00';

if ($acao === 'entrada') {
    // Verifica registro existente no turno
    $stmt = $pdo->prepare("
        SELECT * FROM registros_ponto
        WHERE cpf = :cpf
          AND empresa_id = :empresa_id
          AND data = :data
          AND entrada BETWEEN :ini AND :fim
    ");
    $stmt->execute([
        ':cpf'=>$cpf,':empresa_id'=>$empresa_id,':data'=>$data,
        ':ini'=>$entradaEsperada,':fim'=>$saidaEsperada
    ]);
    if ($stmt->fetch()) {
        echo "<script>alert('Entrada já registrada neste turno.'); history.back();</script>";
        exit;
    }

    // Define status de chegada
    if (strtotime($horaAtual) > strtotime($entradaTolerada)) {
        $atraso = strtotime($horaAtual) - strtotime($entradaTolerada);
        $horasPendentes = gmdate("H:i:s", $atraso);
        $status = 'pendente';
    } else {
        $status = 'ok';
    }

    // Insere entrada
    $stmt = $pdo->prepare("
        INSERT INTO registros_ponto
        (empresa_id, cpf, data, entrada, status, horas_pendentes, hora_extra,
         foto_entrada, localizacao_entrada)
        VALUES
        (:empresa_id, :cpf, :data, :entrada, :status, :pendente, :extra,
         :foto, :loc)
    ");
    $stmt->execute([
        ':empresa_id'=>$empresa_id,':cpf'=>$cpf,':data'=>$data,
        ':entrada'=>$horaAtual,':status'=>$status,':pendente'=>$horasPendentes,
        ':extra'=>$horaExtra,':foto'=>$foto_binaria,':loc'=>$localizacao
    ]);

} elseif ($acao === 'saida') {
    // Busca último registro com saída vazia (do dia atual)
    $stmt = $pdo->prepare("
        SELECT * FROM registros_ponto
        WHERE cpf = :cpf 
          AND empresa_id = :empresa_id
          AND data = :data
          AND saida IS NULL
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([
        ':cpf' => $cpf,
        ':empresa_id' => $empresa_id,
        ':data' => $data
    ]);
    $reg = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reg) {
        echo "<script>alert('Nenhuma entrada encontrada para hoje.'); history.back();</script>";
        exit;
    }

    // Verifica se tem segundo turno
    $temSegundoTurno = !empty($func['hora_entrada_segundo_turno']) && !empty($func['hora_saida_segundo_turno']);

    // Decide qual é a saída permitida agora (considerando primeiro turno)
    $horaSaidaPermitida = $func['hora_saida_primeiro_turno'];

    if (strtotime($horaAtual) < strtotime($horaSaidaPermitida)) {
        echo "<script>alert('Não é possível registrar saída antes do horário mínimo permitido.'); history.back();</script>";
        exit;
    }

    // Se for primeiro turno e funcionário tem dois turnos, saída final será do segundo turno
    $saidaFinal = $temSegundoTurno ? $func['hora_saida_segundo_turno'] : $func['hora_saida_primeiro_turno'];

    // Define início do turno para cálculo, considerando se entrou no segundo turno
    $inicioTurno = $temSegundoTurno
        ? (strtotime($reg['entrada']) >= strtotime($func['hora_entrada_segundo_turno']) 
            ? $func['hora_entrada_segundo_turno'] 
            : $func['hora_entrada_primeiro_turno'])
        : $func['hora_entrada_primeiro_turno'];

    // Calcula duração trabalhada e tempo esperado (em segundos)
    $duracaoTrabalhadaSeg = strtotime($horaAtual) - strtotime($reg['entrada']);
    $tempoEsperadoSeg = strtotime($saidaFinal) - strtotime($inicioTurno);

    // Ajuste de status e cálculo de horas pendentes e extras
    if ($reg['status'] === 'pendente') {
        $compensado = $duracaoTrabalhadaSeg >= $tempoEsperadoSeg;
        $status = $compensado ? 'compensado' : 'pendente';
        $horasPendentes = $compensado ? '00:00:00' : gmdate("H:i:s", $tempoEsperadoSeg - $duracaoTrabalhadaSeg);
        $horaExtra = ($compensado && !$temSegundoTurno) 
            ? gmdate("H:i:s", $duracaoTrabalhadaSeg - $tempoEsperadoSeg) 
            : '00:00:00';
    } else {
        $status = 'ok';
        $horasPendentes = '00:00:00';
        $horaExtra = ($duracaoTrabalhadaSeg > $tempoEsperadoSeg) 
            ? gmdate("H:i:s", $duracaoTrabalhadaSeg - $tempoEsperadoSeg) 
            : '00:00:00';
    }

    // Atualiza registro com saída, status e foto/loc
    $stmt = $pdo->prepare("
        UPDATE registros_ponto SET
            saida = :saida,
            status = :status,
            horas_pendentes = :pendente,
            hora_extra = :extra,
            foto_saida = :foto,
            localizacao_saida = :loc
        WHERE id = :id
    ");
    $stmt->execute([
        ':saida' => $horaAtual,
        ':status' => $status,
        ':pendente' => $horasPendentes,
        ':extra' => $horaExtra,
        ':foto' => $foto_binaria,
        ':loc' => $localizacao,
        ':id' => $reg['id']
    ]);
} else {
    echo "<script>alert('Ação inválida.'); history.back();</script>";
    exit;
}

echo "<script>alert('Registro realizado com sucesso!'); window.location.href='../../index.php';</script>";
exit;

?>
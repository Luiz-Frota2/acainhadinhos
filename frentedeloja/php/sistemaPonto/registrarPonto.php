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

// Processa a imagem base64 corretamente removendo prefixo e espaços
$foto_base64 = str_replace(['data:image/jpeg;base64,', 'data:image/png;base64,', ' '], ['', '', '+'], $foto_base64);
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
    'sunday'    => 'domingo',
    'monday'    => 'segunda',
    'tuesday'   => 'terca',
    'wednesday' => 'quarta',
    'thursday'  => 'quinta',
    'friday'    => 'sexta',
    'saturday'  => 'sabado'
];
$diaTraduzido = $diasTraduzidos[$diaSemana] ?? '';

// Valida se funcionário trabalha no dia atual, conforme dias início e término
$diasSemana = ['domingo', 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado'];
$indexAtual   = array_search($diaTraduzido, $diasSemana);
$indexInicio  = array_search(strtolower($func['dia_inicio']), $diasSemana);
$indexTermino = array_search(strtolower($func['dia_termino']), $diasSemana);

$trabalhaHoje = false;
if ($indexInicio !== false && $indexTermino !== false && $indexAtual !== false) {
    if ($indexInicio <= $indexTermino) {
        // Caso normal, ex: segunda a sexta
        if ($indexAtual >= $indexInicio && $indexAtual <= $indexTermino) {
            $trabalhaHoje = true;
        }
    } else {
        // Caso ciclo passe pela semana, ex: sexta a terça
        if ($indexAtual >= $indexInicio || $indexAtual <= $indexTermino) {
            $trabalhaHoje = true;
        }
    }
}

if (!$trabalhaHoje) {
    echo "<script>alert('Hoje o funcionário não trabalha.'); history.back();</script>";
    exit;
}

// Define turno e horários esperados conforme escala e hora atual
$escala = strtolower($func['escala']);
$horaAtualHora = (int)date('H', strtotime($horaAtual));

if ($escala === 'noturno') {
    $entradaEsperada = $func['hora_entrada_segundo_turno'];
    $saidaEsperada = $func['hora_saida_segundo_turno'];
} else {
    if ($horaAtualHora >= 8 && $horaAtualHora < 13) {
        $entradaEsperada = $func['hora_entrada_primeiro_turno'];
        $saidaEsperada = $func['hora_saida_primeiro_turno'];
    } elseif ($horaAtualHora >= 13 && $horaAtualHora < 19) {
        $entradaEsperada = $func['hora_entrada_segundo_turno'];
        $saidaEsperada = $func['hora_saida_segundo_turno'];
    } else {
        // Fora do horário padrão, considera primeiro turno
        $entradaEsperada = $func['hora_entrada_primeiro_turno'];
        $saidaEsperada = $func['hora_saida_primeiro_turno'];
    }
}

// Entrada tolerada (exemplo 10 minutos de tolerância)
$entradaTolerada = date('H:i:s', strtotime('+10 minutes', strtotime($entradaEsperada)));

$status = '';
$horasPendentes = '00:00:00';
$horaExtra = '00:00:00';

if ($acao === 'entrada') {
    // Verifica se já existe registro de entrada para o turno do dia
    $stmt = $pdo->prepare("SELECT * FROM registros_ponto 
                           WHERE cpf = :cpf AND empresa_id = :empresa_id AND data = :data 
                           AND entrada BETWEEN :inicio_turno AND :fim_turno");
    $stmt->execute([
        ':cpf' => $cpf,
        ':empresa_id' => $empresa_id,
        ':data' => $data,
        ':inicio_turno' => $entradaEsperada,
        ':fim_turno' => $saidaEsperada
    ]);
    $registroExistente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($registroExistente) {
        echo "<script>alert('Você já registrou a entrada para este turno.'); history.back();</script>";
        exit;
    }

    // Verifica atraso e define status
    if (strtotime($horaAtual) > strtotime($entradaTolerada)) {
        $atrasoSegundos = strtotime($horaAtual) - strtotime($entradaTolerada);
        $horasPendentes = gmdate("H:i:s", $atrasoSegundos);
        $status = 'pendente';
    } else {
        $status = 'ok';
        $horasPendentes = '00:00:00';
    }

    // Insere registro de entrada
    $stmt = $pdo->prepare("INSERT INTO registros_ponto 
        (empresa_id, cpf, data, entrada, status, horas_pendentes, hora_extra, foto_entrada, localizacao_entrada)
        VALUES (:empresa_id, :cpf, :data, :entrada, :status, :pendente, :extra, :foto_entrada, :localizacao_entrada)");
    $stmt->execute([
        ':empresa_id' => $empresa_id,
        ':cpf'        => $cpf,
        ':data'       => $data,
        ':entrada'    => $horaAtual,
        ':status'     => $status,
        ':pendente'   => $horasPendentes,
        ':extra'      => $horaExtra,
        ':foto_entrada' => $foto_binaria,
        ':localizacao_entrada' => $localizacao
    ]);
} elseif ($acao === 'saida') {
    // Só permite saída se horário atual >= horário esperado de saída
    if (strtotime($horaAtual) >= strtotime($saidaEsperada)) {
        // Busca registro do dia para atualizar saída
        $stmt = $pdo->prepare("SELECT * FROM registros_ponto 
                               WHERE cpf = :cpf AND data = :data AND empresa_id = :empresa_id 
                               ORDER BY id DESC LIMIT 1");
        $stmt->execute([':cpf' => $cpf, ':data' => $data, ':empresa_id' => $empresa_id]);
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$registro) {
            echo "<script>alert('Entrada não registrada anteriormente.'); history.back();</script>";
            exit;
        }

        // Calcula duração, tempo esperado, compensação e horas extras
        $entrada = $registro['entrada'];
        $duracaoSegundos = strtotime($horaAtual) - strtotime($entrada);
        $tempoEsperadoSegundos = strtotime($saidaEsperada) - strtotime($entradaEsperada);
        $compensou = $duracaoSegundos >= $tempoEsperadoSegundos;

        if ($registro['status'] === 'pendente') {
            $status = $compensou ? 'compensado' : 'pendente';
            $horasPendentes = $compensou ? '00:00:00' : gmdate("H:i:s", $tempoEsperadoSegundos - $duracaoSegundos);
            $horaExtra = ($compensou && $duracaoSegundos > $tempoEsperadoSegundos) ? gmdate("H:i:s", $duracaoSegundos - $tempoEsperadoSegundos) : '00:00:00';
        } else {
            $status = 'ok';
            $horasPendentes = '00:00:00';
            $horaExtra = ($duracaoSegundos > $tempoEsperadoSegundos) ? gmdate("H:i:s", $duracaoSegundos - $tempoEsperadoSegundos) : '00:00:00';
        }

        // Atualiza registro de saída
        $stmt = $pdo->prepare("UPDATE registros_ponto 
                               SET saida = :saida, status = :status, horas_pendentes = :pendente, hora_extra = :extra,
                                   foto_saida = :foto_saida, localizacao_saida = :localizacao_saida
                               WHERE id = :id");
        $stmt->execute([
            ':saida'    => $horaAtual,
            ':status'   => $status,
            ':pendente' => $horasPendentes,
            ':extra'    => $horaExtra,
            ':id'       => $registro['id'],
            ':foto_saida' => $foto_binaria,
            ':localizacao_saida' => $localizacao
        ]);
    } else {
        echo "<script>alert('Você não pode registrar a saída antes do horário esperado.'); history.back();</script>";
        exit;
    }
} else {
    echo "<script>alert('Ação inválida.'); history.back();</script>";
    exit;
}

// Sucesso no registro
echo "<script>alert('Ponto registrado com sucesso!'); window.location.href = '../../sistemadeponto/pontoRegistrado.php?id=$empresa_id';</script>";
exit;
?>

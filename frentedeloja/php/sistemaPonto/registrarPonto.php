<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../../../assets/php/conexao.php';

$data = $_POST['data'] ?? date('Y-m-d');
$horaAtual = $_POST['hora_atual'] ?? date('H:i:s');
$cpf = $_POST['cpf'] ?? '';
$acao = $_POST['acao'] ?? '';
$empresa_id = $_POST['id_selecionado'] ?? '';

if (!$cpf || !$acao || !$empresa_id) {
  echo "<script>alert('Dados incompletos!'); history.back();</script>";
  exit;
}

// Busca funcionário
$stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE cpf = :cpf AND empresa_id = :empresa_id LIMIT 1");
$stmt->execute([':cpf' => $cpf, ':empresa_id' => $empresa_id]);
$func = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$func) {
  echo "<script>alert('Funcionário não encontrado.'); history.back();</script>";
  exit;
}

// Verifica se o funcionário trabalha hoje
$diaSemana = strtolower(date('l'));
$diasTraduzidos = [
  'sunday'    => 'domingo',
  'monday'    => 'segunda',
  'tuesday'   => 'terca',
  'wednesday' => 'quarta',
  'thursday'  => 'quinta',
  'friday'    => 'sexta',
  'saturday'  => 'sabado'
];
$diaTraduzido = $diasTraduzidos[$diaSemana];

$diasSemana = ['domingo', 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado'];
$indexAtual   = array_search($diaTraduzido, $diasSemana);
$indexInicio  = array_search(strtolower($func['dia_inicio']), $diasSemana);
$indexTermino = array_search(strtolower($func['dia_termino']), $diasSemana);

// Verifica intervalo do ciclo de trabalho
if ($indexInicio <= $indexTermino) {
  if ($indexAtual < $indexInicio || $indexAtual > $indexTermino) {
    echo "<script>alert('Hoje o funcionário não trabalha.'); history.back();</script>";
    exit;
  }
} else {
  if ($indexAtual < $indexInicio && $indexAtual > $indexTermino) {
    echo "<script>alert('Hoje o funcionário não trabalha.'); history.back();</script>";
    exit;
  }
}

// Define turno atual com base na hora
$escala = strtolower($func['escala']);
$horaAtualHora = (int)date('H', strtotime($horaAtual));

if ($escala == 'noturno') {
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
    $entradaEsperada = $func['hora_entrada_primeiro_turno'];
    $saidaEsperada = $func['hora_saida_primeiro_turno'];
  }
}

// Define entrada tolerada
$entradaTolerada = date('H:i:s', strtotime('+10 minutes', strtotime($entradaEsperada)));

$status = '';
$horasPendentes = '00:00:00';
$horaExtra = '00:00:00';

if ($acao === 'entrada') {

  // VERIFICAÇÃO: Já registrou entrada neste turno?
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

  // Verifica atraso
  if ($horaAtual > $entradaTolerada) {
    $atraso = strtotime($horaAtual) - strtotime($entradaEsperada);
    $horasPendentes = gmdate("H:i:s", $atraso);
    $status = 'pendente';
  } else {
    $status = 'ok';
  }

  // Registra entrada
  $stmt = $pdo->prepare("INSERT INTO registros_ponto (empresa_id, cpf, data, entrada, status, horas_pendentes, hora_extra)
                         VALUES (:empresa_id, :cpf, :data, :entrada, :status, :pendente, :extra)");
  $stmt->execute([
    ':empresa_id' => $empresa_id,
    ':cpf'        => $cpf,
    ':data'       => $data,
    ':entrada'    => $horaAtual,
    ':status'     => $status,
    ':pendente'   => $horasPendentes,
    ':extra'      => $horaExtra
  ]);

} elseif ($acao === 'saida') {
  if ($horaAtual >= $saidaEsperada) {
    $stmt = $pdo->prepare("SELECT * FROM registros_ponto 
                           WHERE cpf = :cpf AND data = :data AND empresa_id = :empresa_id 
                           ORDER BY id DESC LIMIT 1");
    $stmt->execute([':cpf' => $cpf, ':data' => $data, ':empresa_id' => $empresa_id]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($registro) {
      $entrada = $registro['entrada'];
      $duracao = strtotime($horaAtual) - strtotime($entrada);
      $tempoEsperado = strtotime($saidaEsperada) - strtotime($entradaEsperada);
      $compensou = $duracao >= $tempoEsperado;

      if ($registro['status'] === 'pendente') {
        $status = $compensou ? 'compensado' : 'pendente';
        $horasPendentes = $compensou ? '00:00:00' : gmdate("H:i:s", $tempoEsperado - $duracao);
        $horaExtra = $compensou && $duracao > $tempoEsperado ? gmdate("H:i:s", $duracao - $tempoEsperado) : '00:00:00';
      } else {
        $status = 'ok';
        $horasPendentes = '00:00:00';
        $horaExtra = $duracao > $tempoEsperado ? gmdate("H:i:s", $duracao - $tempoEsperado) : '00:00:00';
      }

      $stmt = $pdo->prepare("UPDATE registros_ponto 
                             SET saida = :saida, status = :status, horas_pendentes = :pendente, hora_extra = :extra 
                             WHERE id = :id");
      $stmt->execute([
        ':saida'    => $horaAtual,
        ':status'   => $status,
        ':pendente' => $horasPendentes,
        ':extra'    => $horaExtra,
        ':id'       => $registro['id']
      ]);
    } else {
      echo "<script>alert('Entrada não registrada anteriormente.'); history.back();</script>";
      exit;
    }
  } else {
    echo "<script>alert('Você não pode registrar a saída antes do horário esperado.'); history.back();</script>";
    exit;
  }
}

echo "<script>alert('Ponto registrado com sucesso!'); window.location.href = '../../sistemadeponto/pontoRegistrado.php?id=$empresa_id';</script>";
?>

<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require '../../../assets/php/conexao.php';

$idAtestado = $_POST['id_atestado'] ?? '';
$cpfUsuario = $_POST['cpfUsuario'] ?? '';
$nomeFuncionario = $_POST['nomeFuncionario'] ?? '';
$dataAtestado = $_POST['dataAtestado'] ?? '';
$diasAfastado = $_POST['diasAfastado'] ?? '';
$idSelecionado = $_POST['idSelecionado'] ?? '';

if (empty($idAtestado)) {
    echo "<script>alert('ID do atestado não informado.'); history.back();</script>";
    exit;
}

$idEmpresaFinal = (strpos($idSelecionado, 'principal_') === 0 || strpos($idSelecionado, 'filial_') === 0)
    ? $idSelecionado
    : (($idSelecionado == '1') ? "principal_1" : "filial_" . $idSelecionado);

// Atualiza o status do atestado para inválido
try {
    $stmt = $pdo->prepare("UPDATE atestados SET status_atestado = 'inválido' WHERE id = :id");
    $stmt->bindParam(':id', $idAtestado, PDO::PARAM_INT);
    $stmt->execute();
} catch (PDOException $e) {
    echo "<script>alert('Erro ao atualizar o status do atestado: " . addslashes($e->getMessage()) . "'); history.back();</script>";
    exit;
}

// Busca os horários reais do funcionário
try {
    $stmtFuncionario = $pdo->prepare("SELECT entrada, saida_intervalo, retorno_intervalo, saida_final FROM funcionarios WHERE cpf = :cpf AND empresa_id = :empresa_id LIMIT 1");
    $stmtFuncionario->bindParam(':cpf', $cpfUsuario);
    $stmtFuncionario->bindParam(':empresa_id', $idEmpresaFinal);
    $stmtFuncionario->execute();
    $func = $stmtFuncionario->fetch(PDO::FETCH_ASSOC);

    if (!$func) {
        echo "<script>alert('Funcionário não encontrado para o CPF e empresa informados.'); history.back();</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao buscar dados do funcionário: " . addslashes($e->getMessage()) . "'); history.back();</script>";
    exit;
}

// Converte horários para DateTime, tratando possíveis nulos
try {
    $entrada = new DateTime($func['entrada']);
    $saida_intervalo = new DateTime($func['saida_intervalo']);
    $retorno_intervalo = new DateTime($func['retorno_intervalo']);
    $saida_final = new DateTime($func['saida_final']);
} catch (Exception $e) {
    echo "<script>alert('Erro ao processar os horários do funcionário: " . addslashes($e->getMessage()) . "'); history.back();</script>";
    exit;
}

// Calcula horas pendentes
$tempoTrabalhoTotal = $entrada->diff($saida_final);
$tempoIntervalo = $saida_intervalo->diff($retorno_intervalo);

$minutosTrabalhoTotal = $tempoTrabalhoTotal->h * 60 + $tempoTrabalhoTotal->i;
$minutosIntervalo = $tempoIntervalo->h * 60 + $tempoIntervalo->i;
$minutosPendentes = $minutosTrabalhoTotal - $minutosIntervalo;

$horasPendentesTime = sprintf('%02d:%02d:00', intdiv($minutosPendentes, 60), $minutosPendentes % 60);

// Atualiza os registros de ponto existentes para o intervalo de dias do afastamento
try {
    $dataInicio = new DateTime($dataAtestado);

    for ($i = 0; $i < (int)$diasAfastado; $i++) {
        $dataPonto = $dataInicio->format('Y-m-d');

        $stmtUpdate = $pdo->prepare("
            UPDATE pontos
            SET horas_pendentes = :horas_pendentes
            WHERE cpf = :cpf AND data = :data AND empresa_id = :empresa_id
        ");

        $stmtUpdate->bindParam(':horas_pendentes', $horasPendentesTime);
        $stmtUpdate->bindParam(':cpf', $cpfUsuario);
        $stmtUpdate->bindParam(':data', $dataPonto);
        $stmtUpdate->bindParam(':empresa_id', $idEmpresaFinal);
        $stmtUpdate->execute();

        $dataInicio->modify('+1 day');
    }

    echo "<script>
        alert('Atestado invalidado e registros de ponto atualizados com sucesso!');
        window.location.href = '../../../erp/rh/atestadosFuncionarios.php?id=$idSelecionado';
    </script>";
} catch (PDOException $e) {
    echo "<script>alert('Erro ao atualizar registros de ponto: " . addslashes($e->getMessage()) . "'); history.back();</script>";
    exit;
}
?>

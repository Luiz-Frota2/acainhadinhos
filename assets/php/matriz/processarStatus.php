<?php
// ../../assets/php/matriz/processar_mudar_status.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Método inválido.']);
    exit;
}

if (!isset($_SESSION['usuario_logado'], $_SESSION['empresa_id'], $_SESSION['tipo_empresa'], $_SESSION['usuario_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

require_once __DIR__ . '/../conexao.php';

$sid          = (int)($_POST['sid'] ?? 0);
$status       = strtolower(trim((string)($_POST['status'] ?? '')));
$solicitanteP = trim((string)($_POST['solicitante'] ?? ''));

if ($sid <= 0 || $status !== 'entregue' || $solicitanteP === '') {
    echo json_encode(['ok' => false, 'msg' => 'Parâmetros inválidos.']);
    exit;
}

/* ====== Permissão de quem está logado para operar sobre o solicitante enviado ====== */
$tipoSession      = $_SESSION['tipo_empresa'] ?? '';
$idEmpresaSession = $_SESSION['empresa_id'] ?? '';

$acessoPermitido = false;
if (str_starts_with($solicitanteP, 'principal_')) {
    $acessoPermitido = ($tipoSession === 'principal' && $idEmpresaSession === 'principal_1');
} elseif (str_starts_with($solicitanteP, 'filial_')) {
    $acessoPermitido = ($tipoSession === 'filial' && $idEmpresaSession === $solicitanteP);
} elseif (str_starts_with($solicitanteP, 'unidade_')) {
    $acessoPermitido = ($tipoSession === 'unidade' && $idEmpresaSession === $solicitanteP);
} elseif (str_starts_with($solicitanteP, 'franquia_')) {
    $acessoPermitido = ($tipoSession === 'franquia' && $idEmpresaSession === $solicitanteP);
}

if (!$acessoPermitido) {
    echo json_encode(['ok' => false, 'msg' => 'Acesso negado para este solicitante.']);
    exit;
}

try {
    // Confere se a solicitação existe e pertence ao solicitante
    $chk = $pdo->prepare("
        SELECT id, status, id_solicitante 
        FROM solicitacoes_b2b 
        WHERE id = :sid
        LIMIT 1
    ");
    $chk->execute([':sid' => $sid]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['ok' => false, 'msg' => 'Solicitação não encontrada.']);
        exit;
    }
    if ((string)$row['id_solicitante'] !== $solicitanteP) {
        echo json_encode(['ok' => false, 'msg' => 'Solicitante da solicitação não confere.']);
        exit;
    }

    // Atualiza status para "entregue"
    $up = $pdo->prepare("
        UPDATE solicitacoes_b2b SET 
            status = 'entregue',
            entregue_em = CASE 
                WHEN (entregue_em IS NULL OR entregue_em = '0000-00-00 00:00:00') 
                THEN NOW() ELSE entregue_em 
            END
        WHERE id = :sid AND id_solicitante = :sol
        LIMIT 1
    ");
    $ok = $up->execute([':sid' => $sid, ':sol' => $solicitanteP]);
    if (!$ok) {
        echo json_encode(['ok' => false, 'msg' => 'Falha ao atualizar.']);
        exit;
    }

    $ref = $pdo->prepare("
        SELECT id, status, id_solicitante, aprovada_em, enviada_em, entregue_em, created_at, total_estimado
        FROM solicitacoes_b2b
        WHERE id = :sid
        LIMIT 1
    ");
    $ref->execute([':sid' => $sid]);
    $novos = $ref->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['ok' => true, 'data' => $novos]);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()]);
}

?>
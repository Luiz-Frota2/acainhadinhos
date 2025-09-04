<?php
// folga_Salvar.php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);
session_start();

/**
 * Conexão PDO — tenta alguns caminhos comuns.
 * Se você já tem um caminho certo, pode deixar só ele.
 */
$pdo = null;
$paths = [
    __DIR__ . '/../../assets/php/conexao.php',
    __DIR__ . '/../assets/php/conexao.php',
    __DIR__ . '/assets/php/conexao.php',
    __DIR__ . '/../../dist/dashboard/php/conexao.php',
    __DIR__ . '/../php/conexao.php',
    __DIR__ . '/../../php/conexao.php',
];
foreach ($paths as $p) {
    if (is_file($p)) { require_once $p; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Conexão indisponível.');
}

/* ===== Helpers ===== */
function only_digits(string $v): string { return preg_replace('/\D+/', '', $v) ?? ''; }
function back_with(string $url, array $params): void {
    $qs = http_build_query($params);
    header('Location: ' . $url . (str_contains($url, '?') ? "&$qs" : "?$qs"));
    exit;
}

/* ===== Entradas =====
   - empresa_id e cpf virão pela URL (GET)
   - data_folga vem do POST da modal
*/
$empresaId = trim((string)($_GET['id'] ?? $_POST['id'] ?? ''));
$cpfRaw    = trim((string)($_GET['cpf'] ?? $_POST['cpf'] ?? ''));
$dataStr   = trim((string)($_POST['data_folga'] ?? ''));

$paginaRetorno = './folgasIndividuaisAdicionar.php';

if ($empresaId === '' || $cpfRaw === '' || $dataStr === '') {
    back_with($paginaRetorno, [
        'id'  => $empresaId,
        'cpf' => $cpfRaw,
        'err' => 1,
        'msg' => 'Empresa, CPF e data são obrigatórios.'
    ]);
}

// Normaliza
$cpfDigits = only_digits($cpfRaw);
if ($cpfDigits === '' || strlen($cpfDigits) < 11) {
    back_with($paginaRetorno, [
        'id'  => $empresaId,
        'cpf' => $cpfRaw,
        'err' => 1,
        'msg' => 'CPF inválido.'
    ]);
}

try {
    $d = new DateTime($dataStr);
    $dataFolga = $d->format('Y-m-d');
} catch (Throwable $e) {
    back_with($paginaRetorno, [
        'id'  => $empresaId,
        'cpf' => $cpfRaw,
        'err' => 1,
        'msg' => 'Data da folga inválida.'
    ]);
}

/* ===== Busca do nome do funcionário (usa CPF; tenta filtrar por empresa se existir) ===== */
$nomeFuncionario = null;

// 1) tenta funcionarios por cpf+empresa (se a coluna existir)
try {
    $sql = "SELECT nome FROM funcionarios WHERE (REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = :cpf) AND empresa_id = :empresa LIMIT 1";
    $st  = $pdo->prepare($sql);
    $st->execute([':cpf' => $cpfDigits, ':empresa' => $empresaId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['nome'])) $nomeFuncionario = $row['nome'];
} catch (Throwable $e) {
    // segue pro fallback
}

// 2) tenta funcionarios só por cpf
if (!$nomeFuncionario) {
    try {
        $sql = "SELECT nome FROM funcionarios 
                WHERE REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = :cpf
                LIMIT 1";
        $st  = $pdo->prepare($sql);
        $st->execute([':cpf' => $cpfDigits]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['nome'])) $nomeFuncionario = $row['nome'];
    } catch (Throwable $e) { /* continua */ }
}

// 3) tenta reaproveitar último nome já usado em folgas
if (!$nomeFuncionario) {
    try {
        $sql = "SELECT nome FROM folgas
                WHERE REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = :cpf
                ORDER BY id DESC LIMIT 1";
        $st  = $pdo->prepare($sql);
        $st->execute([':cpf' => $cpfDigits]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['nome'])) $nomeFuncionario = $row['nome'];
    } catch (Throwable $e) { /* continua */ }
}

// 4) contas_acesso como último recurso (se existir)
if (!$nomeFuncionario) {
    try {
        $sql = "SELECT usuario AS nome FROM contas_acesso
                WHERE REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = :cpf
                LIMIT 1";
        $st  = $pdo->prepare($sql);
        $st->execute([':cpf' => $cpfDigits]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['nome'])) $nomeFuncionario = $row['nome'];
    } catch (Throwable $e) { /* continua */ }
}

if (!$nomeFuncionario) {
    back_with($paginaRetorno, [
        'id'  => $empresaId,
        'cpf' => $cpfRaw,
        'err' => 1,
        'msg' => 'Não foi possível localizar o nome do funcionário para este CPF.'
    ]);
}

/* ===== Duplicidade: mesma data + CPF =====
   Observação: a tabela `folgas` (conforme seu SQL) NÃO tem empresa_id.
   Logo, a verificação é apenas por CPF+data.
*/
try {
    $check = $pdo->prepare("
        SELECT COUNT(*) FROM folgas
        WHERE REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = :cpf
          AND data_folga = :data
    ");
    $check->execute([':cpf' => $cpfDigits, ':data' => $dataFolga]);
    if ((int)$check->fetchColumn() > 0) {
        back_with($paginaRetorno, [
            'id'  => $empresaId,
            'cpf' => $cpfRaw,
            'err' => 1,
            'msg' => 'Já existe uma folga cadastrada para este CPF nesta data.'
        ]);
    }
} catch (Throwable $e) {
    back_with($paginaRetorno, [
        'id'  => $empresaId,
        'cpf' => $cpfRaw,
        'err' => 1,
        'msg' => 'Falha ao verificar duplicidade.'
    ]);
}

/* ===== Inserção =====
   Tabela folgas possui: id, cpf, nome, data_folga
*/
try {
    $ins = $pdo->prepare("
        INSERT INTO folgas (cpf, nome, data_folga)
        VALUES (:cpf, :nome, :data)
    ");
    // Armazeno CPF apenas dígitos (padronizado)
    $ok = $ins->execute([
        ':cpf'  => $cpfDigits,
        ':nome' => $nomeFuncionario,
        ':data' => $dataFolga,
    ]);

    if (!$ok) {
        back_with($paginaRetorno, [
            'id'  => $empresaId,
            'cpf' => $cpfRaw,
            'err' => 1,
            'msg' => 'Não foi possível cadastrar a folga.'
        ]);
    }

    back_with($paginaRetorno, [
        'id'  => $empresaId,
        'cpf' => $cpfRaw,
        'ok'  => 1,
        'msg' => 'Folga cadastrada com sucesso.'
    ]);

} catch (Throwable $e) {
    back_with($paginaRetorno, [
        'id'  => $empresaId,
        'cpf' => $cpfRaw,
        'err' => 1,
        'msg' => 'Erro ao inserir a folga.'
    ]);
}

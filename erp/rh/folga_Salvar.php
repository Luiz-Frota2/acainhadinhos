<?php
// folga_Salvar.php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);
session_start();

require_once '../../assets/php/conexao.php'; // deve definir $pdo (PDO)

// === Helpers ===
function only_digits(string $v): string { return preg_replace('/\D+/', '', $v) ?? ''; }
function back_with(string $url, array $params): void {
    $qs = http_build_query($params);
    header("Location: {$url}" . (str_contains($url, '?') ? "&{$qs}" : "?{$qs}"));
    exit;
}

// === Entrada (POST da modal) ===
$empresaId = trim((string)($_POST['id'] ?? ''));      // vem como hidden na sua modal (idSelecionado)
$cpf       = only_digits((string)($_POST['cpf'] ?? ''));
$dataStr   = trim((string)($_POST['data_folga'] ?? ''));

// Página de retorno (onde está sua modal)
$paginaRetorno = './folgasIndividuaisAdicionar.php';

// Validações básicas
if ($cpf === '' || $dataStr === '') {
    back_with($paginaRetorno, [
        'id'  => $empresaId,
        'cpf' => $cpf,
        'err' => 1,
        'msg' => 'Dados insuficientes: empresa, CPF e data são obrigatórios.'
    ]);
}

// Normaliza data (YYYY-MM-DD)
try {
    $d = new DateTime($dataStr);
    $dataFolga = $d->format('Y-m-d');
} catch (Throwable $e) {
    back_with($paginaRetorno, [
        'id'  => $empresaId,
        'cpf' => $cpf,
        'err' => 1,
        'msg' => 'Data da folga inválida.'
    ]);
}

// === Buscar nome do funcionário ===
// 1) Tenta na tabela funcionarios (recomendado)
// 2) Se não achar, tenta última referência de nome na própria folgas (fallback)
// 3) Se ainda não achar, tenta contas_acesso por CPF (último recurso)
$nomeFuncionario = null;

try {
    // 1) funcionarios
    $sql = "SELECT nome FROM funcionarios WHERE cpf = :cpf AND (empresa_id = :empresa_id OR :empresa_id = :empresa_id) LIMIT 1";
    $st  = $pdo->prepare($sql);
    $st->execute([':cpf' => $cpf, ':empresa_id' => $empresaId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['nome'])) {
        $nomeFuncionario = $row['nome'];
    }

    // 2) folgas (fallback)
    if (!$nomeFuncionario) {
        $sql = "SELECT nome FROM folgas WHERE cpf = :cpf AND empresa_id = :empresa_id ORDER BY id DESC LIMIT 1";
        $st  = $pdo->prepare($sql);
        $st->execute([':cpf' => $cpf, ':empresa_id' => $empresaId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['nome'])) {
            $nomeFuncionario = $row['nome'];
        }
    }

    // 3) contas_acesso (fallback final, se houver esse vínculo por CPF)
    if (!$nomeFuncionario) {
        $sql = "SELECT usuario AS nome FROM contas_acesso WHERE REPLACE(cpf, '.', '') = :cpf OR cpf = :cpf LIMIT 1";
        $st  = $pdo->prepare($sql);
        $st->execute([':cpf' => $cpf]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['nome'])) {
            $nomeFuncionario = $row['nome'];
        }
    }
} catch (Throwable $e) {
    // não quebra, só segue para tratar abaixo
}

if (!$nomeFuncionario) {
    back_with($paginaRetorno, [
        'id'  => $empresaId,
        'cpf' => $cpf,
        'err' => 1,
        'msg' => 'Não foi possível localizar o nome do funcionário para o CPF informado.'
    ]);
}

// === Evita duplicidade: mesma data + cpf + empresa ===
try {
    $check = $pdo->prepare("SELECT COUNT(*) FROM folgas WHERE cpf = :cpf AND data_folga = :data AND empresa_id = :empresa_id");
    $check->execute([
        ':cpf'        => $cpf,
        ':data'       => $dataFolga,
        ':empresa_id' => $empresaId,
    ]);
    $jaExiste = (int)$check->fetchColumn() > 0;

    if ($jaExiste) {
        back_with($paginaRetorno, [
            'id'  => $empresaId,
            'cpf' => $cpf,
            'err' => 1,
            'msg' => 'Já existe uma folga cadastrada para este CPF nesta data.'
        ]);
    }
} catch (Throwable $e) {
    back_with($paginaRetorno, [
        'id'  => $empresaId,
        'cpf' => $cpf,
        'err' => 1,
        'msg' => 'Falha ao verificar duplicidade de folga.'
    ]);
}

// === Inserção ===
// Observação importante: mantive apenas colunas estáveis (id é AUTO_INCREMENT).
// Se sua tabela tiver colunas extras obrigatórias, ajuste os INSERTs abaixo.
try {
    $sql = "INSERT INTO folgas (cpf, nome, data_folga) 
            VALUES (:cpf, :nome, :data_folga)";
    $ins = $pdo->prepare($sql);
    $ok  = $ins->execute([
        ':cpf'        => $cpf,
        ':nome'       => $nomeFuncionario,
        ':data_folga' => $dataFolga,

    ]);

    if (!$ok) {
        back_with($paginaRetorno, [
            'id'  => $empresaId,
            'cpf' => $cpf,
            'err' => 1,
            'msg' => 'Não foi possível cadastrar a folga.'
        ]);
    }

    // Sucesso
    back_with($paginaRetorno, [
        'id'  => $empresaId,
        'cpf' => $cpf,
        'ok'  => 1,
        'msg' => 'Folga cadastrada com sucesso.'
    ]);

} catch (Throwable $e) {
    back_with($paginaRetorno, [
        'id'  => $empresaId,
        'cpf' => $cpf,
        'err' => 1,
        'msg' => 'Erro ao cadastrar a folga no banco.'
    ]);
}

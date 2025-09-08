<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
header('Content-Type: text/html; charset=utf-8');

/* ========= Helpers ========= */
function getBodyJson(): array {
    $ct  = $_SERVER['CONTENT_TYPE'] ?? '';
    $raw = file_get_contents('php://input');
    if ($raw && stripos($ct, 'application/json') !== false) {
        try { $j = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); if (is_array($j)) return $j; } catch (Throwable $e) {}
    }
    return [];
}
function val($arr, $key, $default=''){ return isset($arr[$key]) ? trim((string)$arr[$key]) : $default; }
function onlyDigits($s){ return preg_replace('/\D+/', '', (string)$s); }
function redirVendaRapida($empresaId, $ok, $modelo, $msg=''){
    $empresaId = urlencode((string)$empresaId);
    $modelo    = urlencode((string)$modelo);
    $status    = $ok ? 'ok' : 'erro';
    $qs = "id={$empresaId}&cancel={$status}&modelo={$modelo}";
    if ($msg !== '') $qs .= '&msg='.urlencode($msg);
    header("Location: ../frentedeloja/caixa/vendaRapida.php?{$qs}");
    exit;
}

/* ========= Entrada ========= */
$in = array_merge($_POST, getBodyJson());

$modelo           = val($in, 'modelo');            // 'por_chave' | 'por_motivo' | 'por_substituicao'
$empresa_id       = val($in, 'empresa_id', $_GET['id'] ?? $_GET['empresa_id'] ?? '');
$chave            = onlyDigits(val($in, 'chave'));
$last4            = onlyDigits(val($in, 'last4'));
$motivo           = val($in, 'motivo');
$chaveSubstituta  = onlyDigits(val($in, 'chave_substituta'));

/* ========= Conexão ========= */
$pdo = null;
$candidates = [
    __DIR__ . '/assets/conexao.php',
    __DIR__ . '/../assets/conexao.php',
    __DIR__ . '/../assets/php/conexao.php',
    __DIR__ . '/../../assets/conexao.php',
    __DIR__ . '/../../assets/php/conexao.php',
    $_SERVER['DOCUMENT_ROOT'] . '/assets/conexao.php',
    $_SERVER['DOCUMENT_ROOT'] . '/assets/php/conexao.php',
    __DIR__ . '/../conexao/conexao.php',
    __DIR__ . '/../../conexao/conexao.php',
];
foreach ($candidates as $p) {
    if (is_file($p)) {
        require_once $p;
        if (isset($pdo) && $pdo instanceof PDO) break;
    }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $host = getenv('DB_HOST'); $db = getenv('DB_NAME'); $user = getenv('DB_USER'); $pass = getenv('DB_PASS');
    if ($host && $db && $user !== false) {
        $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";
        try { $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); } catch (Throwable $e) {}
    }
}
if (!($pdo instanceof PDO)) {
    redirVendaRapida($empresa_id, false, $modelo ?: 'desconhecido', 'Sem conexão com o banco.');
}

/* ========= Descobrir empresa via chave (se faltar) ========= */
if (!$empresa_id && $chave) {
    try {
        $st = $pdo->prepare("SELECT empresa_id FROM vendas WHERE chave_nfce = :ch LIMIT 1");
        $st->execute([':ch' => $chave]);
        $empresa_id = (string)($st->fetchColumn() ?: '');
    } catch (Throwable $e) {}
}

/* ========= Deduz modelo se não veio ========= */
if (!$modelo) {
    if ($chave && $last4 && !$chaveSubstituta) $modelo = 'por_chave';
    elseif ($chave && $chaveSubstituta)        $modelo = 'por_substituicao';
    elseif ($motivo)                            $modelo = 'por_motivo';
}

/* ========= Validações ========= */
if ($modelo === 'por_chave') {
    if (strlen($chave) !== 44) {
        redirVendaRapida($empresa_id, false, $modelo, 'Chave inválida (44 dígitos).');
    }
    if (strlen($last4) !== 4 || substr($chave, -4) !== $last4) {
        redirVendaRapida($empresa_id, false, $modelo, 'Confirmação (últimos 4) não confere.');
    }
} elseif ($modelo === 'por_motivo') {
    if ($motivo === '') {
        redirVendaRapida($empresa_id, false, $modelo, 'Motivo não informado.');
    }
} elseif ($modelo === 'por_substituicao') {
    if (strlen($chave) !== 44) {
        redirVendaRapida($empresa_id, false, $modelo, 'Chave (original) inválida (44 dígitos).');
    }
    if (strlen($chaveSubstituta) !== 44) {
        redirVendaRapida($empresa_id, false, $modelo, 'Chave substituta inválida (44 dígitos).');
    }
    if ($chaveSubstituta === $chave) {
        redirVendaRapida($empresa_id, false, $modelo, 'A chave substituta deve ser diferente da chave a cancelar.');
    }
} else {
    redirVendaRapida($empresa_id, false, $modelo ?: 'desconhecido', 'Modelo de cancelamento inválido.');
}
if (!$empresa_id) {
    redirVendaRapida($empresa_id, false, $modelo, 'empresa_id ausente.');
}

/* ========= SUA integração SEFAZ ========= */
function runCancel($modelo, $empresa_id, $chave, $motivo, $chaveSubstituta){
    // implemente os eventos 110111 / 110112 conforme seu emissor
    return true;
}
$ok = runCancel($modelo, $empresa_id, $chave, $motivo, $chaveSubstituta);
if (!$ok) {
    redirVendaRapida($empresa_id, false, $modelo, 'Falha ao cancelar na SEFAZ.');
}

/* ========= Utilitários de BD ========= */
function carregarVenda(PDO $pdo, string $empresa_id, string $chave): ?array {
    $sql = "SELECT id, empresa_id, valor_total, numero_caixa, abertura_id
              FROM vendas
             WHERE empresa_id = :emp AND chave_nfce = :ch
             ORDER BY id DESC
             LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':emp'=>$empresa_id, ':ch'=>$chave]);
    $v = $st->fetch(PDO::FETCH_ASSOC);
    return $v ?: null;
}
function atualizarAberturaPorVenda(PDO $pdo, array $venda): void {
    $valorVenda   = (float)($venda['valor_total'] ?? 0);
    $empresa_id   = (string)$venda['empresa_id'];
    $aberturaId   = $venda['abertura_id'] ?? null;
    $numeroCaixa  = $venda['numero_caixa'] ?? null;

    if ($valorVenda <= 0) return; // nada a ajustar

    if ($aberturaId) {
        $sql = "UPDATE aberturas
                   SET valor_total = GREATEST(0, valor_total - :v),
                       quantidade_vendas = GREATEST(0, quantidade_vendas - 1)
                 WHERE id = :id AND empresa_id = :emp";
        $st  = $pdo->prepare($sql);
        $st->execute([':v'=>$valorVenda, ':id'=>$aberturaId, ':emp'=>$empresa_id]);
        return;
    }

    // fallback por caixa aberto
    if ($numeroCaixa !== null) {
        $sql = "UPDATE aberturas
                   SET valor_total = GREATEST(0, valor_total - :v),
                       quantidade_vendas = GREATEST(0, quantidade_vendas - 1)
                 WHERE empresa_id = :emp
                   AND numero_caixa = :cx
                   AND status = 'aberto'
                 ORDER BY id DESC
                 LIMIT 1";
        $st  = $pdo->prepare($sql);
        $st->execute([':v'=>$valorVenda, ':emp'=>$empresa_id, ':cx'=>$numeroCaixa]);
        return;
    }

    // último recurso: abertura mais recente da empresa
    $sql = "UPDATE aberturas
               SET valor_total = GREATEST(0, valor_total - :v),
                   quantidade_vendas = GREATEST(0, quantidade_vendas - 1)
             WHERE empresa_id = :emp
             ORDER BY id DESC
             LIMIT 1";
    $st  = $pdo->prepare($sql);
    $st->execute([':v'=>$valorVenda, ':emp'=>$empresa_id]);
}

/* ========= Cancelar: repor estoque + ajustar abertura + remover venda ========= */
try {
    $venda = carregarVenda($pdo, $empresa_id, $chave);
    if (!$venda) {
        redirVendaRapida($empresa_id, false, $modelo, 'Venda não encontrada para a chave informada.');
    }

    $pdo->beginTransaction();

    // 1) Itens → repor estoque (somar de volta)
    $selItens = $pdo->prepare("
        SELECT produto_id, quantidade
          FROM itens_venda
         WHERE venda_id = :id
    ");
    $selItens->execute([':id' => $venda['id']]);
    $itens = $selItens->fetchAll(PDO::FETCH_ASSOC);

    if ($itens) {
        // trava linha de estoque e repõe
        $lockEst = $pdo->prepare("
            SELECT id, empresa_id
              FROM estoque
             WHERE id = :id
             FOR UPDATE
        ");
        $updEst = $pdo->prepare("
            UPDATE estoque
               SET quantidade_produto = quantidade_produto + :qtd
             WHERE id = :id AND empresa_id = :emp
        ");
        foreach ($itens as $it) {
            $pid = (int)$it['produto_id'];
            $qtd = (float)$it['quantidade'];

            $lockEst->execute([':id' => $pid]);
            $row = $lockEst->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new RuntimeException("Produto {$pid} não encontrado no estoque para devolução.");
            }
            if ((string)$row['empresa_id'] !== (string)$empresa_id) {
                throw new RuntimeException("Produto {$pid} pertence a outra empresa.");
            }
            $updEst->execute([':qtd' => $qtd, ':id' => $pid, ':emp' => $empresa_id]);
        }
    }

    // 2) Ajustar abertura (tirar valor da venda e 1 venda)
    atualizarAberturaPorVenda($pdo, $venda);

    // 3) Apagar itens e venda
    $di = $pdo->prepare("DELETE FROM itens_venda WHERE venda_id = :id");
    $di->execute([':id' => $venda['id']]);

    $dv = $pdo->prepare("DELETE FROM vendas WHERE id = :id");
    $dv->execute([':id' => $venda['id']]);

    $pdo->commit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    redirVendaRapida($empresa_id, false, $modelo, 'Erro ao cancelar: '.$e->getMessage());
}

/* ========= Redireciona ========= */
redirVendaRapida($empresa_id, true, $modelo, 'Cancelado com sucesso.');

?>
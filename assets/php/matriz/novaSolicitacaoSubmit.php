<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

session_start();

/* ==========
   Regras
========== */
function js_alert_back(string $msg): void {
    echo "<script>alert(".json_encode($msg, JSON_UNESCAPED_UNICODE)."); history.back();</script>";
    exit;
}
function js_alert_go(string $msg, string $url): void {
    echo "<script>alert(".json_encode($msg, JSON_UNESCAPED_UNICODE)."); window.location.href=".json_encode($url).";</script>";
    exit;
}

/* ==========
   Sessão & parâmetros
========== */
$idSelecionado = $_POST['id_pagina'] ?? '';
if (!$idSelecionado) {
    echo "<script>alert('Sessão expirada. Faça login novamente.'); window.location.href='../../../erp/login.php';</script>";
    exit;
}

if (!isset($_SESSION['usuario_logado'], $_SESSION['empresa_id'], $_SESSION['tipo_empresa'], $_SESSION['usuario_id'])) {
    echo "<script>alert('Acesso negado. Faça login novamente.'); window.location.href='../../../erp/login.php?id=".htmlspecialchars($idSelecionado, ENT_QUOTES)."';</script>";
    exit;
}

$usuarioId  = (int)$_SESSION['usuario_id'];
$tipoSessao = (string)$_SESSION['tipo_empresa'];
$empSessao  = (string)$_SESSION['empresa_id'];

$idMatriz      = trim((string)($_POST['id_matriz'] ?? ''));
$idSolicitante = trim((string)($_POST['id_solicitante'] ?? ''));
$itensPost     = $_POST['itens'] ?? null;

if ($idMatriz !== 'principal_1') {
    js_alert_back('Matriz inválida para solicitação.');
}
if (!$idSolicitante) {
    // fallback para a empresa da sessão
    $idSolicitante = $empSessao;
}

/* ==========
   Conexão
========== */
require __DIR__ . '/../conexao.php'; // ../../assets/php/conexao.php (este arquivo está em assets/php/matriz/)

/* ==========
   Validação dos itens
========== */
if (!is_array($itensPost) || empty($itensPost)) {
    js_alert_back('Nenhum item recebido.');
}

// Normaliza e filtra itens válidos
$itensValidos = [];
foreach ($itensPost as $idx => $it) {
    $sel = isset($it['selecionado']) && (string)$it['selecionado'] === '1';
    $qtd = isset($it['quantidade']) ? (int)$it['quantidade'] : 0;

    if (!$sel || $qtd <= 0) continue;

    $produtoId = isset($it['produto_id']) ? (int)$it['produto_id'] : 0;
    if ($produtoId <= 0) continue;

    $nome   = trim((string)($it['nome'] ?? ''));
    $codigo = trim((string)($it['codigo'] ?? ''));
    $un     = trim((string)($it['unidade'] ?? 'UN'));
    $preco  = (float)($it['preco'] ?? 0);

    $itensValidos[] = [
        'produto_id' => $produtoId,
        'nome'       => $nome,
        'codigo'     => $codigo,
        'unidade'    => $un,
        'preco'      => $preco,
        'quantidade' => $qtd,
    ];
}

if (empty($itensValidos)) {
    js_alert_back('Selecione ao menos um item com quantidade maior que zero.');
}

/* ==========
   Confere estoque da MATRIZ (principal_1)
========== */
$ids = array_column($itensValidos, 'produto_id');
$placeholders = implode(',', array_fill(0, count($ids), '?'));

try {
    // Busca estoque atual dos produtos na matriz
    $sql = "SELECT id, quantidade_produto, preco_produto, unidade
            FROM estoque
            WHERE empresa_id = 'principal_1' AND id IN ($placeholders)";
    $st  = $pdo->prepare($sql);
    $st->execute($ids);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $mapEstoque = [];
    foreach ($rows as $r) {
        $mapEstoque[(int)$r['id']] = [
            'quantidade' => (int)($r['quantidade_produto'] ?? 0),
            'preco'      => (float)($r['preco_produto'] ?? 0),
            'unidade'    => (string)($r['unidade'] ?? 'UN')
        ];
    }

    // valida cada item
    foreach ($itensValidos as $iv) {
        $pid = (int)$iv['produto_id'];
        if (!isset($mapEstoque[$pid])) {
            js_alert_back("Produto ID {$pid} não existe no estoque da matriz.");
        }
        $qtdDisponivel = $mapEstoque[$pid]['quantidade'];
        if ($iv['quantidade'] > $qtdDisponivel) {
            js_alert_back("Estoque insuficiente para o produto código {$iv['codigo']}. Disponível: {$qtdDisponivel}.");
        }
        // opcional: usar preço do banco, priorizando o atual
        if (empty($iv['preco']) || $iv['preco'] <= 0) {
            $iv['preco'] = $mapEstoque[$pid]['preco'];
        }
    }

} catch (PDOException $e) {
    js_alert_back('Falha ao validar estoque: ' . $e->getMessage());
}

/* ==========
   Insere solicitação (transação)
   Tabelas esperadas:
   - solicitacoes_b2b (id, id_matriz, id_solicitante, criado_por_usuario_id, status, total_estimado, created_at)
   - solicitacoes_b2b_itens (id, solicitacao_id, produto_id, codigo_produto, nome_produto, unidade, preco_unitario, quantidade, subtotal)
========== */
try {
    $pdo->beginTransaction();

    // total estimado
    $totalEstimado = 0.0;
    foreach ($itensValidos as $iv) {
        $totalEstimado += ((float)$iv['preco']) * ((int)$iv['quantidade']);
    }

    // cabeçalho
    $sqlCab = "INSERT INTO solicitacoes_b2b
               (id_matriz, id_solicitante, criado_por_usuario_id, status, total_estimado, created_at)
               VALUES (:matriz, :sol, :uid, 'pendente', :total, NOW())";
    $stCab = $pdo->prepare($sqlCab);
    $stCab->execute([
        ':matriz' => $idMatriz,
        ':sol'    => $idSolicitante,
        ':uid'    => $usuarioId,
        ':total'  => $totalEstimado
    ]);
    $solicitacaoId = (int)$pdo->lastInsertId();

    // itens
    $sqlItem = "INSERT INTO solicitacoes_b2b_itens
                (solicitacao_id, produto_id, codigo_produto, nome_produto, unidade, preco_unitario, quantidade, subtotal)
                VALUES (:sid, :pid, :cod, :nome, :un, :preco, :qtd, :sub)";
    $stItem = $pdo->prepare($sqlItem);

    foreach ($itensValidos as $iv) {
        $subtotal = ((float)$iv['preco']) * ((int)$iv['quantidade']);
        $stItem->execute([
            ':sid'   => $solicitacaoId,
            ':pid'   => (int)$iv['produto_id'],
            ':cod'   => (string)$iv['codigo'],
            ':nome'  => (string)$iv['nome'],
            ':un'    => (string)$iv['unidade'],
            ':preco' => number_format((float)$iv['preco'], 2, '.', ''),
            ':qtd'   => (int)$iv['quantidade'],
            ':sub'   => number_format($subtotal, 2, '.', '')
        ]);
    }

    $pdo->commit();

    // sucesso -> vai para a lista de solicitados
    $go = "../../../erp/matriz/produtosSolicitados.php?id=" . rawurlencode($idSelecionado);
    js_alert_go("Solicitação #{$solicitacaoId} criada com sucesso!", $go);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    js_alert_back('Erro ao salvar a solicitação: ' . $e->getMessage());
}

?>
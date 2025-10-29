<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

session_start();

// ====== Função de retorno simples (redirect com mensagem) ======
function back(string $id, bool $ok, string $msg): void {
    $loc = 'cancelarVenda.php?id=' . urlencode($id) . '&ok=' . ($ok ? '1' : '0') . '&msg=' . urlencode($msg);
    header("Location: $loc");
    exit;
}

// ====== Checagens básicas de sessão/entrada ======
$id         = $_POST['id']        ?? '';
$venda_id   = $_POST['venda_id']  ?? '';
$acao       = $_POST['acao']      ?? '';

$id       = is_string($id) ? trim($id) : '';
$venda_id = (string)(is_scalar($venda_id) ? $venda_id : '');
$acao     = is_string($acao) ? strtolower(trim($acao)) : '';

if ($id === '' || $venda_id === '' || $acao === '') {
    back($id ?: 'principal_1', false, 'Parâmetros ausentes: id, venda_id, acao.');
}

// Se quiser manter a mesma validação de login:
if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['usuario_id']) ||
    !isset($_SESSION['nivel'])
) {
    back($id, false, 'Sessão expirada. Faça login novamente.');
}

// ====== Conexão DB ======
require '../../assets/php/conexao.php';

// ====== Busca venda e status atual ======
try {
    $sql = "SELECT id, empresa_id, status_nfce, forma_pagamento, valor_total, data_venda
            FROM vendas
            WHERE id = :id_venda AND empresa_id = :empresa_id
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([
        ':id_venda'   => $venda_id,
        ':empresa_id' => $id
    ]);
    $venda = $st->fetch(PDO::FETCH_ASSOC);
    if (!$venda) {
        back($id, false, "Venda #{$venda_id} não encontrada para esta empresa.");
    }
} catch (Throwable $e) {
    back($id, false, 'Erro ao buscar a venda: ' . $e->getMessage());
}

// Status normalizado
$statusAtual = $venda['status_nfce'] ?? '';
$statusNorm  = strtolower(trim((string)$statusAtual));
if ($statusNorm === '') $statusNorm = 'finalizada'; // compat

// ====== Regras de cancelamento (mínimas) ======
// interno  : permitido quando NÃO há NFC-e autorizada (status != 'autorizada')
// 110111   : permitido APENAS quando status == 'autorizada'
// 110112   : inutilização não se aplica para uma venda já utilizada; bloquear aqui.

try {
    $pdo->beginTransaction();

    if ($acao === 'interno') {
        if ($statusNorm === 'autorizada') {
            // Impedir cancelamento interno se já houve autorização SEFAZ
            $pdo->rollBack();
            back($id, false, "Venda #{$venda_id} possui NFC-e autorizada. Utilize o evento 110111.");
        }

        // Aqui você pode executar estorno interno (estoque/financeiro) se houver rotinas próprias.
        // Exemplo mínimo: marcar status_nfce = 'cancelada_interna'
        $up = $pdo->prepare("UPDATE vendas SET status_nfce = 'cancelada_interna' WHERE id = :id AND empresa_id = :empresa_id");
        $up->execute([':id' => $venda_id, ':empresa_id' => $id]);

        $pdo->commit();
        back($id, true, "Venda #{$venda_id} cancelada internamente com sucesso.");

    } elseif ($acao === '110111') {
        if ($statusNorm !== 'autorizada') {
            $pdo->rollBack();
            back($id, false, "Evento 110111 requer NFC-e autorizada. Status atual: {$statusNorm}.");
        }

        // Aqui normalmente você chamaria a SEFAZ (evento 110111) e gravaria o retorno.
        // Exemplo mínimo: marcar status_nfce = 'cancelada'
        $up = $pdo->prepare("UPDATE vendas SET status_nfce = 'cancelada' WHERE id = :id AND empresa_id = :empresa_id");
        $up->execute([':id' => $venda_id, ':empresa_id' => $id]);

        $pdo->commit();
        back($id, true, "NFC-e da venda #{$venda_id} cancelada (110111) com sucesso.");

    } elseif ($acao === '110112') {
        // Inutilização é para numeração não utilizada (sem uso). Bloqueamos aqui por segurança.
        $pdo->rollBack();
        back($id, false, "Inutilização (110112) não se aplica a uma venda já registrada. Utilize apenas para numeração sem uso.");

    } else {
        $pdo->rollBack();
        back($id, false, 'Ação inválida. Use: interno, 110111 ou 110112.');
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    back($id, false, 'Falha no processamento: ' . $e->getMessage());
}

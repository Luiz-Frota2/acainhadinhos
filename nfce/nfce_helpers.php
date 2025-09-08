<?php
// nfce_helpers.php
declare(strict_types=1);

/**
 * Mantém só dígitos (útil para chave NFC-e).
 */
function so_digitos(?string $v): ?string {
    if ($v === null) return null;
    $v = preg_replace('/\D+/', '', $v);
    return $v !== '' ? $v : null;
}

/**
 * Extrai a chave (44 dígitos) a partir do retorno/protocolo ou do XML completo.
 * $ret pode ser array/obj do NFePHP (protNFe/infProt) ou string XML.
 */
function extrair_chave_nfce($ret, ?string $xmlAssinado = null): ?string {
    // 1) Tente por objeto/array padrão do NFePHP
    if (is_object($ret)) {
        // Exemplos comuns: $ret->protNFe->infProt->chNFe  ou $ret->infProt->chNFe
        $possiveis = [
            $ret->protNFe->infProt->chNFe ?? null,
            $ret->infProt->chNFe ?? null,
        ];
        foreach ($possiveis as $c) {
            $c = so_digitos((string)$c);
            if ($c && strlen($c) === 44) return $c;
        }
    }
    if (is_array($ret)) {
        $c = $ret['protNFe']['infProt']['chNFe'] ?? $ret['infProt']['chNFe'] ?? null;
        $c = so_digitos($c);
        if ($c && strlen($c) === 44) return $c;
    }

    // 2) Fallback: procure no XML assinado (Id="NFeXXXXXXXX...")
    if ($xmlAssinado) {
        if (preg_match('/Id="NFe(\d{44})"/', $xmlAssinado, $m)) {
            return $m[1];
        }
    }

    return null;
}

/**
 * Atualiza a venda com chave e status.
 * $status sugeridos: 'autorizada', 'rejeitada', 'cancelada', 'pendente'
 */
function salvar_chave_nfce(PDO $pdo, int $vendaId, string $empresaId, string $chave, string $status = 'autorizada'): bool {
    $chave = so_digitos($chave) ?? '';
    if (strlen($chave) !== 44) return false;

    $sql = "UPDATE vendas
               SET chave_nfce = :chave, status_nfce = :status
             WHERE id = :id AND empresa_id = :empresa_id";
    $st  = $pdo->prepare($sql);
    return $st->execute([
        ':chave'      => $chave,
        ':status'     => strtolower($status),
        ':id'         => $vendaId,
        ':empresa_id' => $empresaId,
    ]);
}

/**
 * Só atualiza o status (para rejeição/cancelamento).
 */
function atualizar_status_nfce(PDO $pdo, int $vendaId, string $empresaId, string $status): bool {
    $sql = "UPDATE vendas
               SET status_nfce = :status
             WHERE id = :id AND empresa_id = :empresa_id";
    $st  = $pdo->prepare($sql);
    return $st->execute([
        ':status'     => strtolower($status),
        ':id'         => $vendaId,
        ':empresa_id' => $empresaId,
    ]);
}

<?php

require_once '../conexao.php';

try {
    // ✅ Recupera o identificador da empresa enviado no form
    $idSelecionado = $_POST['idSelecionado'] ?? '';
    $idEmpresa = null;

    if (str_starts_with($idSelecionado, 'principal_')) {
        $idEmpresa = 1;
    } elseif (str_starts_with($idSelecionado, 'filial_')) {
        $idFilial = (int) str_replace('filial_', '', $idSelecionado);
        if (!filter_var($idFilial, FILTER_VALIDATE_INT)) {
            throw new Exception("ID da filial inválido.");
        }
        $idEmpresa = $idFilial;
    } else {
        throw new Exception("ID da empresa inválido.");
    }

    // ✅ Mapeia os campos válidos
    $camposValidos = [
        'dinheiro' => 'Dinheiro',
        'pix' => 'Pix',
        'cartaoDebito' => 'Cartão de Débito',
        'cartaoCredito' => 'Cartão de Crédito'
    ];

    // ✅ Verifica qual campo foi enviado
    $campo = null;
    $valor = null;
    $nomeCampo = null;

    foreach ($camposValidos as $key => $nome) {
        if (isset($_POST[$key])) {
            $campo = $key;
            $valor = $_POST[$key] == '1' ? 1 : 0;
            $nomeCampo = $nome;
            break;
        }
    }

    if (!$campo) {
        throw new Exception("Nenhuma forma de pagamento válida foi enviada.");
    }

    // ✅ Verifica se já existe um registro para essa empresa
    $sqlCheck = "SELECT COUNT(*) FROM formas_pagamento WHERE empresa_id = :empresa_id";
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute([':empresa_id' => $idEmpresa]);
    $registroExiste = $stmtCheck->fetchColumn();

    if ($registroExiste == 0) {
        // ✅ Insere novo registro zerado
        $sqlInsert = "INSERT INTO formas_pagamento (empresa_id, dinheiro, pix, cartaoDebito, cartaoCredito)
                      VALUES (:empresa_id, 0, 0, 0, 0)";
        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->execute([':empresa_id' => $idEmpresa]);
    }

    // ✅ Atualiza o campo específico
    if (!array_key_exists($campo, $camposValidos)) {
        throw new Exception("Campo de pagamento inválido.");
    }

    $sqlUpdate = "UPDATE formas_pagamento SET {$campo} = :valor WHERE empresa_id = :empresa_id";
    $stmtUpdate = $pdo->prepare($sqlUpdate);
    $stmtUpdate->execute([
        ':valor' => $valor,
        ':empresa_id' => $idEmpresa
    ]);

    echo "<script>
        alert('Forma de pagamento \"$nomeCampo\" atualizada com sucesso!');
        window.location.href = '../../../erp/delivery/formaPagamento.php?id=" . htmlspecialchars($idSelecionado) . "';
    </script>";
    exit;

} catch (Exception $e) {
    echo "<script>alert('Erro: " . addslashes($e->getMessage()) . "'); history.back();</script>";
} catch (PDOException $e) {
    echo "<script>alert('Erro ao salvar formas de pagamento!'); history.back();</script>";
}
?>

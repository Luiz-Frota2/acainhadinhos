<?php
require_once '../conexao.php'; // Conexão com o banco de dados

try {
    // Obtém o ID da empresa (enviado pelo formulário)
    $idSelecionado = isset($_POST['id_selecionado']) ? trim($_POST['id_selecionado']) : '';
    
    // Obtém os dados do formulário
    $cnpj = isset($_POST['empresa_cnpj']) ? trim($_POST['empresa_cnpj']) : '';
    $cep = isset($_POST['empresa_cep']) ? trim($_POST['empresa_cep']) : '';
    $endereco = isset($_POST['empresa_endereco']) ? trim($_POST['empresa_endereco']) : '';
    $bairro = isset($_POST['empresa_bairro']) ? trim($_POST['empresa_bairro']) : '';
    $numero = isset($_POST['empresa_numero']) ? trim($_POST['empresa_numero']) : '';
    $cidade = isset($_POST['empresa_cidade']) ? trim($_POST['empresa_cidade']) : '';
    $complemento = isset($_POST['empresa_complemento']) ? trim($_POST['empresa_complemento']) : '';
    $uf = isset($_POST['empresa_uf']) ? trim($_POST['empresa_uf']) : '';

    // Verifica se já existe um endereço para esta empresa (usando o id_selecionado)
    $sql = "SELECT id FROM endereco_empresa WHERE empresa_id = :empresa_id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':empresa_id' => $idSelecionado]);
    $enderecoExistente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($enderecoExistente) {
        // Se já existe, faz o UPDATE
        $sql = "UPDATE endereco_empresa SET 
                    cnpj = :cnpj,
                    cep = :cep, 
                    endereco = :endereco, 
                    bairro = :bairro, 
                    numero = :numero, 
                    cidade = :cidade, 
                    complemento = :complemento, 
                    uf = :uf,
                    data_atualizacao = CURRENT_TIMESTAMP
                WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cnpj' => $cnpj,
            ':cep' => $cep,
            ':endereco' => $endereco,
            ':bairro' => $bairro,
            ':numero' => $numero,
            ':cidade' => $cidade,
            ':complemento' => $complemento,
            ':uf' => $uf,
            ':id' => $enderecoExistente['id']
        ]);

        echo "<script>alert('Endereço atualizado com sucesso!'); window.location.href='../../../erp/delivery/enderecoEmpresa.php?id=$idSelecionado';</script>";
    } else {
        // Se não existe, faz o INSERT com o id_selecionado como empresa_id
        $sql = "INSERT INTO endereco_empresa 
                (empresa_id, cnpj, cep, endereco, bairro, numero, cidade, complemento, uf) 
                VALUES (:empresa_id, :cnpj, :cep, :endereco, :bairro, :numero, :cidade, :complemento, :uf)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':empresa_id' => $idSelecionado,
            ':cnpj' => $cnpj,
            ':cep' => $cep,
            ':endereco' => $endereco,
            ':bairro' => $bairro,
            ':numero' => $numero,
            ':cidade' => $cidade,
            ':complemento' => $complemento,
            ':uf' => $uf
        ]);

        echo "<script>alert('Endereço cadastrado com sucesso!'); window.location.href='../../../erp/delivery/enderecoEmpresa.php?id=$idSelecionado';</script>";
    }
} catch (Exception $e) {
    echo "<script>alert('Erro ao salvar o endereço: " . addslashes($e->getMessage()) . "'); history.back();</script>";
}

?>
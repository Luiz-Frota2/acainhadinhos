<?php
require_once '../conexao.php'; // Conexão com o banco de dados

try {
    // Obtém o ID da empresa (enviado pelo formulário)
    $idSelecionado = isset($_POST['id_selecionado']) ? trim($_POST['id_selecionado']) : '';

    // Extrai o ID numérico da empresa
    if (str_starts_with($idSelecionado, 'principal_')) {
        $empresa_id = 1;
    } elseif (str_starts_with($idSelecionado, 'filial_')) {
        $empresa_id = (int) str_replace('filial_', '', $idSelecionado);
    } else {
        throw new Exception('ID da empresa inválido.');
    }

    // Obtém os dados do formulário
    $cep = isset($_POST['empresa_cep']) ? trim($_POST['empresa_cep']) : '';
    $endereco = isset($_POST['empresa_endereco']) ? trim($_POST['empresa_endereco']) : '';
    $bairro = isset($_POST['empresa_bairro']) ? trim($_POST['empresa_bairro']) : '';
    $numero = isset($_POST['empresa_numero']) ? trim($_POST['empresa_numero']) : '';
    $cidade = isset($_POST['empresa_cidade']) ? trim($_POST['empresa_cidade']) : '';
    $complemento = isset($_POST['empresa_complemento']) ? trim($_POST['empresa_complemento']) : '';
    $uf = isset($_POST['empresa_uf']) ? trim($_POST['empresa_uf']) : '';

    // Verifica se já existe um endereço para esta empresa
    $sql = "SELECT id FROM endereco_empresa WHERE empresa_id = :empresa_id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':empresa_id' => $empresa_id]);
    $enderecoExistente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($enderecoExistente) {
        // Se já existe, faz o UPDATE
        $sql = "UPDATE endereco_empresa SET 
                    cep = :cep, 
                    endereco = :endereco, 
                    bairro = :bairro, 
                    numero = :numero, 
                    cidade = :cidade, 
                    complemento = :complemento, 
                    uf = :uf 
                WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
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
        // Se não existe, faz o INSERT com empresa_id
        $sql = "INSERT INTO endereco_empresa (empresa_id, cep, endereco, bairro, numero, cidade, complemento, uf) 
                VALUES (:empresa_id, :cep, :endereco, :bairro, :numero, :cidade, :complemento, :uf)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':empresa_id' => $empresa_id,
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

<?php
require_once '../conexao.php'; // Caminho pode variar conforme estrutura do seu projeto

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idSelecionado = $_POST['idSelecionado']; // Captura o ID do formulário
    $nome = $_POST['nomeFilial'];
    $cnpj = $_POST['cnpjFilial'];
    $telefone = $_POST['telefoneFilial'];
    $email = $_POST['emailFilial'];
    $responsavel = $_POST['responsavelFilial'];
    $endereco = $_POST['enderecoFilial'];
    $dataAbertura = $_POST['dataAberturaFilial'];
    $status = $_POST['statusFilial'];

    try {
        $sql = "INSERT INTO unidades (nome, cnpj, telefone, email, responsavel, endereco, data_abertura, status)
                VALUES (:nome, :cnpj, :telefone, :email, :responsavel, :endereco, :data_abertura, :status)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nome' => $nome,
            ':cnpj' => $cnpj,
            ':telefone' => $telefone,
            ':email' => $email,
            ':responsavel' => $responsavel,
            ':endereco' => $endereco,
            ':data_abertura' => $dataAbertura,
            ':status' => $status
        ]);

        // Mantendo seu script original com a mensagem, apenas adicionando o ID na URL
        echo "<script>
                alert('Filial cadastrada com sucesso!'); 
                window.location.href='../../../erp/filial/filialAdicionada.php?id=" . urlencode($idSelecionado) . "';
              </script>";
    } catch (PDOException $e) {
        echo "<script>alert('Erro ao cadastrar filial: " . $e->getMessage() . "'); window.history.back();</script>";
    }
} else {
    echo "<script>alert('Requisição inválida.'); window.history.back();</script>";
}
?>
<?php
require_once '../conexao.php'; // Ajuste conforme a estrutura do seu projeto

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idSelecionado     = $_POST['idSelecionado']; // ID da empresa matriz
    $nome              = $_POST['nomeUnidade'];
    $tipo              = $_POST['tipoUnidade'];
    $cnpj              = $_POST['cnpjUnidade'];
    $telefone          = $_POST['telefoneUnidade'];
    $email             = $_POST['emailUnidade'];
    $responsavel       = $_POST['responsavelUnidade'];
    $endereco          = $_POST['enderecoUnidade'];
    $dataAbertura      = $_POST['dataAberturaUnidade'];
    $status            = $_POST['statusUnidade'];

    try {
        $sql = "INSERT INTO unidades (
                    empresa_id, nome, tipo, cnpj, telefone, email, responsavel, endereco, data_abertura, status
                ) VALUES (
                    :empresa_id, :nome, :tipo, :cnpj, :telefone, :email, :responsavel, :endereco, :data_abertura, :status
                )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':empresa_id'     => $idSelecionado,
            ':nome'           => $nome,
            ':tipo'           => $tipo,
            ':cnpj'           => $cnpj,
            ':telefone'       => $telefone,
            ':email'          => $email,
            ':responsavel'    => $responsavel,
            ':endereco'       => $endereco,
            ':data_abertura'  => $dataAbertura,
            ':status'         => $status
        ]);

        echo "<script>
                alert('Unidade cadastrada com sucesso!');
                window.location.href='../../../erp/filial/filialAdicionada.php?id=" . urlencode($idSelecionado) . "';
              </script>";
    } catch (PDOException $e) {
        echo "<script>
                alert('Erro ao cadastrar unidade: " . $e->getMessage() . "');
                window.history.back();
              </script>";
    }
} else {
    echo "<script>
            alert('Requisição inválida.');
            window.history.back();
          </script>";
}

?>

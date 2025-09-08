<?php
require_once '../conexao.php';

try {
    $nome_empresa = isset($_POST['nome_empresa']) ? trim($_POST['nome_empresa']) : '';
    $sobre_empresa = isset($_POST['sobre_empresa']) ? trim($_POST['sobre_empresa']) : '';
    $idSelecionado = isset($_POST['idSelecionado']) ? trim($_POST['idSelecionado']) : '';

    if (empty($idSelecionado)) {
        echo "<script>alert('Erro: Identificador da empresa não informado.'); history.back();</script>";
        exit;
    }

    $uploadDir = "../../img/empresa/";

    // Garante que só existe UM registro com esse idSelecionado
    $sql = "SELECT id, imagem FROM sobre_empresa WHERE id_selecionado = :idSelecionado LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':idSelecionado' => $idSelecionado]);
    $empresaExistente = $stmt->fetch(PDO::FETCH_ASSOC);

    $novoNomeImagem = $empresaExistente['imagem'] ?? '';

    // Se foi enviada nova imagem
    if (!empty($_FILES['imagem_empresa']['name'])) {
        $imagem = $_FILES['imagem_empresa'];
        $extensao = strtolower(pathinfo($imagem['name'], PATHINFO_EXTENSION));
        $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($extensao, $extensoesPermitidas)) {
            echo "<script>alert('Formato de imagem inválido! Use JPG, JPEG, PNG ou GIF.'); history.back();</script>";
            exit;
        }

        $novoNomeImagem = uniqid('empresa_') . "." . $extensao;
        $caminhoImagem = $uploadDir . $novoNomeImagem;

        if (!move_uploaded_file($imagem['tmp_name'], $caminhoImagem)) {
            echo "<script>alert('Erro ao fazer upload da imagem.'); history.back();</script>";
            exit;
        }

        // Remove imagem antiga, se existir
        if ($empresaExistente && !empty($empresaExistente['imagem'])) {
            $imagemAntiga = $uploadDir . $empresaExistente['imagem'];
            if (file_exists($imagemAntiga)) {
                unlink($imagemAntiga);
            }
        }
    }

    if ($empresaExistente) {
        // Atualiza a empresa existente
        $sql = "UPDATE sobre_empresa 
                SET nome_empresa = :nome, sobre_empresa = :sobre, imagem = :imagem 
                WHERE id_selecionado = :idSelecionado";
    } else {
        // Insere nova empresa
        $sql = "INSERT INTO sobre_empresa (nome_empresa, sobre_empresa, imagem, id_selecionado) 
                VALUES (:nome, :sobre, :imagem, :idSelecionado)";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nome' => $nome_empresa,
        ':sobre' => $sobre_empresa,
        ':imagem' => $novoNomeImagem,
        ':idSelecionado' => $idSelecionado
    ]);

    echo "<script>alert('Dados da empresa salvos com sucesso!'); window.location.href='../../../erp/empresa/sobreEmpresa.php?id=$idSelecionado';</script>";
} catch (PDOException $e) {
    echo "<script>alert('Erro ao salvar os dados: " . addslashes($e->getMessage()) . "'); history.back();</script>";
}
?>

<?php

include "../conexao.php";

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Processar os dados enviados pelo formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Recuperar o id_produto, o tipo de opcional e o id_selecionado
    $id_produto = $_POST['id_produto'];
    $tipoOpcional = $_POST['tipoOpcional'];
    $idSelecionado = $_POST['id_selecionado']; // Recebe o id_selecionado

    // Definir a URL para redirecionamento em caso de sucesso, com o idSelecionado
    $redirectUrl = "../../../erp/delivery/produtoAdicionados.php?id=" . urlencode($idSelecionado);

    if ($tipoOpcional == 'opcionalSimples') {
        // Inserir opcional simples
        $nomeSimples = $_POST['txtNomeSimples'];
        $precoSimples = $_POST['txtPrecoSimples'];

        // Preparar o SQL para inserir opcional simples com o id_selecionado
        $sql = "INSERT INTO opcionais (id_produto, nome, preco, id_selecionado) 
                VALUES (:id_produto, :nome, :preco, :id_selecionado)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_produto', $id_produto);
        $stmt->bindParam(':nome', $nomeSimples);
        $stmt->bindParam(':preco', $precoSimples);
        $stmt->bindParam(':id_selecionado', $idSelecionado); // Bind do id_selecionado

        if ($stmt->execute()) {
            echo '<script>alert("Opcional Simples inserido com sucesso!"); window.location.href = "' . $redirectUrl . '";</script>';
        } else {
            echo '<script>alert("Erro ao inserir opcional simples."); history.back();</script>';
        }

    } elseif ($tipoOpcional == 'selecaoOpcoes') {
        // Inserir seleção de opções
        $tituloSecao = $_POST['txtTituloSecao'];
        $minimoOpcao = $_POST['txtMinimoOpcao'];
        $maximoOpcao = $_POST['txtMaximoOpcao'];

        // Preparar o SQL para inserir a seleção de opções com o id_selecionado
        $sql = "INSERT INTO opcionais_selecoes (id_produto, titulo, minimo, maximo, id_selecionado) 
                VALUES (:id_produto, :titulo, :minimo, :maximo, :id_selecionado)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_produto', $id_produto);
        $stmt->bindParam(':titulo', $tituloSecao);
        $stmt->bindParam(':minimo', $minimoOpcao);
        $stmt->bindParam(':maximo', $maximoOpcao);
        $stmt->bindParam(':id_selecionado', $idSelecionado); // Bind do id_selecionado

        if ($stmt->execute()) {
            // Recuperar o ID da seleção de opções recém-criada
            $idSelecao = $pdo->lastInsertId();

            // Inserir as opções dentro dessa seleção
            if (isset($_POST['opcaoNome']) && isset($_POST['opcaoPreco'])) {
                $opcaoNomes = $_POST['opcaoNome'];
                $opcaoPrecos = $_POST['opcaoPreco'];

                $erroAoInserirOpcao = false;

                for ($i = 0; $i < count($opcaoNomes); $i++) {
                    $nomeOpcao = $opcaoNomes[$i];
                    $precoOpcao = $opcaoPrecos[$i];

                    // Inserir as opções para essa seleção com o id_selecionado
                    $sqlOpcao = "INSERT INTO opcionais_opcoes (id_selecao, nome, preco, id_selecionado) 
                                 VALUES (:id_selecao, :nome, :preco, :id_selecionado)";
                    $stmtOpcao = $pdo->prepare($sqlOpcao);
                    $stmtOpcao->bindParam(':id_selecao', $idSelecao);
                    $stmtOpcao->bindParam(':nome', $nomeOpcao);
                    $stmtOpcao->bindParam(':preco', $precoOpcao);
                    $stmtOpcao->bindParam(':id_selecionado', $idSelecionado); // Bind do id_selecionado

                    if (!$stmtOpcao->execute()) {
                        $erroAoInserirOpcao = true;
                    }
                }

                if ($erroAoInserirOpcao) {
                    echo '<script>alert("Erro ao inserir uma ou mais opções!"); history.back();</script>';
                } else {
                    echo '<script>alert("Seleção de opções e suas opções inseridas com sucesso!"); window.location.href = "' . $redirectUrl . '";</script>';
                }
            } else {
                echo '<script>alert("Nenhuma opção foi fornecida."); history.back();</script>';
            }
        } else {
            echo '<script>alert("Erro ao inserir a seleção de opções."); history.back();</script>';
        }
    } else {
        echo '<script>alert("Tipo de opcional inválido."); history.back();</script>';
    }
}
?>

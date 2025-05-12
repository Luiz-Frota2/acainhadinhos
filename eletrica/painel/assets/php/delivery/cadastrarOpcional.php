<?php

include "../conexao.php";

// Processar os dados enviados pelo formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Recuperar o id_produto e o tipo de opcional
    $id_produto = $_POST['id_produto'];
    $tipoOpcional = $_POST['tipoOpcional'];

    // Definir a URL para redirecionamento em caso de sucesso
    $redirectUrl = "../../../erp/delivery/produtoAdicionados.php";

    if ($tipoOpcional == 'opcionalSimples') {
        // Inserir opcional simples
        $nomeSimples = $_POST['txtNomeSimples'];
        $precoSimples = $_POST['txtPrecoSimples'];

        // Preparar o SQL para inserir opcional simples
        $sql = "INSERT INTO opcionais (id_produto, nome, preco) VALUES (:id_produto, :nome, :preco)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_produto', $id_produto);
        $stmt->bindParam(':nome', $nomeSimples);
        $stmt->bindParam(':preco', $precoSimples);

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

        // Preparar o SQL para inserir a seleção de opções
        $sql = "INSERT INTO opcionais_selecoes (id_produto, titulo, minimo, maximo) VALUES (:id_produto, :titulo, :minimo, :maximo)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_produto', $id_produto);
        $stmt->bindParam(':titulo', $tituloSecao);
        $stmt->bindParam(':minimo', $minimoOpcao);
        $stmt->bindParam(':maximo', $maximoOpcao);

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

                    // Inserir as opções para essa seleção
                    $sqlOpcao = "INSERT INTO opcionais_opcoes (id_selecao, nome, preco) VALUES (:id_selecao, :nome, :preco)";
                    $stmtOpcao = $pdo->prepare($sqlOpcao);
                    $stmtOpcao->bindParam(':id_selecao', $idSelecao);
                    $stmtOpcao->bindParam(':nome', $nomeOpcao);
                    $stmtOpcao->bindParam(':preco', $precoOpcao);

                    if (!$stmtOpcao->execute()) {
                        $erroAoInserirOpcao = true;
                    }
                }

                if ($erroAoInserirOpcao) {
                    echo '<script>alert("Erro ao inserir uma ou mais opções!"); history.back();</script>';
                } else {
                    echo '<script>alert("Seleção de opções e suas opções inseridas com sucesso!"); window.location.href = "../../../erp/delivery/produtoAdicionados.php";</script>';
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

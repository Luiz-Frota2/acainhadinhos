<?php
require '../conexao.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Coleta dos dados do formulário
    $empresa_id = trim($_POST["empresa_id"]);
    $nome_funcionario = trim($_POST["nome"]);
    $data_nascimento = trim($_POST["data_nascimento"]);
    $cpf_funcionario = trim($_POST["cpf"]);
    $rg_funcionario = trim($_POST["rg"]);
    $cargo_funcionario = trim($_POST["cargo"]);
    $setor_funcionario = trim($_POST["setor"]);
    $salario_funcionario = trim($_POST["salario"]);
    $escala_funcionario = trim($_POST["escala"]);
    $dia_inicio = trim($_POST["dia_inicio"]);
    $dia_termino = trim($_POST["dia_termino"]);
    $hora_entrada_1 = trim($_POST["hora_entrada_primeiro_turno"]);
    $hora_saida_1 = trim($_POST["hora_saida_primeiro_turno"]);
    $hora_entrada_2 = trim($_POST["hora_entrada_segundo_turno"]);
    $hora_saida_2 = trim($_POST["hora_saida_segundo_turno"]);
    $email_funcionario = trim($_POST["email"]);
    $telefone_funcionario = trim($_POST["telefone"]);
    $endereco_funcionario = trim($_POST["endereco"]);
    $cidade_funcionario = trim($_POST["cidade"]);

    // Se os campos do segundo turno estiverem vazios, define como NULL
    if (empty($hora_entrada_2)) {
        $hora_entrada_2 = NULL;
    }
    if (empty($hora_saida_2)) {
        $hora_saida_2 = NULL;
    }

    try {
        // Verifica se o CPF já está cadastrado
        $checkCpfSql = "SELECT COUNT(*) FROM funcionarios WHERE cpf = :cpf";
        $stmtCheckCpf = $pdo->prepare($checkCpfSql);
        $stmtCheckCpf->bindParam(":cpf", $cpf_funcionario, PDO::PARAM_STR);
        $stmtCheckCpf->execute();

        $cpfCount = $stmtCheckCpf->fetchColumn();

        if ($cpfCount > 0) {
            echo "<script>
                alert('Este CPF já está cadastrado no sistema.');
                history.back();
            </script>";
            exit();
        }

        // Query de inserção com campos para 2 turnos
        $sql = "INSERT INTO funcionarios (
            empresa_id, nome, data_nascimento, cpf, rg,
            cargo, setor, salario, escala,
            dia_inicio, dia_termino, 
            hora_entrada_primeiro_turno, hora_saida_primeiro_turno,
            hora_entrada_segundo_turno, hora_saida_segundo_turno,
            email, telefone, endereco, cidade
        ) VALUES (
            :empresa_id, :nome, :data_nascimento, :cpf, :rg,
            :cargo, :setor, :salario, :escala,
            :dia_inicio, :dia_termino, 
            :hora_entrada_1, :hora_saida_1,
            :hora_entrada_2, :hora_saida_2,
            :email, :telefone, :endereco, :cidade
        )";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":empresa_id", $empresa_id);
        $stmt->bindParam(":nome", $nome_funcionario);
        $stmt->bindParam(":data_nascimento", $data_nascimento);
        $stmt->bindParam(":cpf", $cpf_funcionario);
        $stmt->bindParam(":rg", $rg_funcionario);
        $stmt->bindParam(":cargo", $cargo_funcionario);
        $stmt->bindParam(":setor", $setor_funcionario);
        $stmt->bindParam(":salario", $salario_funcionario);
        $stmt->bindParam(":escala", $escala_funcionario);
        $stmt->bindParam(":dia_inicio", $dia_inicio);
        $stmt->bindParam(":dia_termino", $dia_termino);
        $stmt->bindParam(":hora_entrada_1", $hora_entrada_1);
        $stmt->bindParam(":hora_saida_1", $hora_saida_1);
        $stmt->bindParam(":hora_entrada_2", $hora_entrada_2);
        $stmt->bindParam(":hora_saida_2", $hora_saida_2);
        $stmt->bindParam(":email", $email_funcionario);
        $stmt->bindParam(":telefone", $telefone_funcionario);
        $stmt->bindParam(":endereco", $endereco_funcionario);
        $stmt->bindParam(":cidade", $cidade_funcionario);

        if ($stmt->execute()) {
            echo "<script>
                alert('Funcionário cadastrado com sucesso!');
                window.location.href = '../../../erp/rh/funcionarioAdicionados.php?id={$empresa_id}';
            </script>";
            exit();
        } else {
            echo "<script>
                alert('Erro ao cadastrar funcionário.');
                history.back();
            </script>";
        }
    } catch (PDOException $e) {
        echo "<script>
            alert('Erro no banco de dados: " . addslashes($e->getMessage()) . "');
            history.back();
        </script>";
    }
}
?>

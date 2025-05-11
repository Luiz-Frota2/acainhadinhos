<?php
require '../conexao.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST["id"] ?? null;
    $empresa_id = trim($_POST["empresa_id"] ?? '');
    $nome = trim($_POST["nome"] ?? '');
    $data_nascimento = trim($_POST["data_nascimento"] ?? '');
    $cpf = trim($_POST["cpf"] ?? '');
    $rg = trim($_POST["rg"] ?? '');
    $cargo = trim($_POST["cargo"] ?? '');
    $setor = trim($_POST["setor"] ?? '');
    $salario = trim($_POST["salario"] ?? '');
    $escala = trim($_POST["escala"] ?? '');
    $dia_inicio = trim($_POST["dia_inicio"] ?? '');
    $dia_termino = trim($_POST["dia_termino"] ?? '');
    $hora_entrada_primeiro_turno = trim($_POST["hora_entrada_primeiro_turno"] ?? '');
    $hora_saida_primeiro_turno = trim($_POST["hora_saida_primeiro_turno"] ?? '');
    $hora_entrada_segundo_turno = trim($_POST["hora_entrada_segundo_turno"] ?? '');
    $hora_saida_segundo_turno = trim($_POST["hora_saida_segundo_turno"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $telefone = trim($_POST["telefone"] ?? '');
    $endereco = trim($_POST["endereco"] ?? '');
    $cidade = trim($_POST["cidade"] ?? '');

    // Verifica se todos os campos obrigatórios estão preenchidos
    if (
        empty($empresa_id) || empty($nome) || empty($data_nascimento) || empty($cpf) || empty($rg) ||
        empty($cargo) || empty($setor) || empty($salario) || empty($escala) ||
        empty($dia_inicio) || empty($dia_termino) ||
        empty($hora_entrada_primeiro_turno) || empty($hora_saida_primeiro_turno) ||
        empty($hora_entrada_segundo_turno) || empty($hora_saida_segundo_turno) ||
        empty($email) || empty($telefone) || empty($endereco) || empty($cidade)
    ) {
        echo "<script>
                alert('Preencha todos os campos obrigatórios.');
                history.back();
              </script>";
        exit;
    }

    try {
        $sql = "UPDATE funcionarios SET 
                    empresa_id = :empresa_id,
                    nome = :nome,
                    data_nascimento = :data_nascimento,
                    cpf = :cpf,
                    rg = :rg,
                    cargo = :cargo,
                    setor = :setor,
                    salario = :salario,
                    escala = :escala,
                    dia_inicio = :dia_inicio,
                    dia_termino = :dia_termino,
                    hora_entrada_primeiro_turno = :hora_entrada_primeiro_turno,
                    hora_saida_primeiro_turno = :hora_saida_primeiro_turno,
                    hora_entrada_segundo_turno = :hora_entrada_segundo_turno,
                    hora_saida_segundo_turno = :hora_saida_segundo_turno,
                    email = :email,
                    telefone = :telefone,
                    endereco = :endereco,
                    cidade = :cidade
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(":empresa_id", $empresa_id);
        $stmt->bindParam(":nome", $nome);
        $stmt->bindParam(":data_nascimento", $data_nascimento);
        $stmt->bindParam(":cpf", $cpf);
        $stmt->bindParam(":rg", $rg);
        $stmt->bindParam(":cargo", $cargo);
        $stmt->bindParam(":setor", $setor);
        $stmt->bindParam(":salario", $salario);
        $stmt->bindParam(":escala", $escala);
        $stmt->bindParam(":dia_inicio", $dia_inicio);
        $stmt->bindParam(":dia_termino", $dia_termino);
        $stmt->bindParam(":hora_entrada_primeiro_turno", $hora_entrada_primeiro_turno);
        $stmt->bindParam(":hora_saida_primeiro_turno", $hora_saida_primeiro_turno);
        $stmt->bindParam(":hora_entrada_segundo_turno", $hora_entrada_segundo_turno);
        $stmt->bindParam(":hora_saida_segundo_turno", $hora_saida_segundo_turno);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":telefone", $telefone);
        $stmt->bindParam(":endereco", $endereco);
        $stmt->bindParam(":cidade", $cidade);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            echo "<script>
                    alert('Funcionário atualizado com sucesso!');
                    window.location.href = '../../../erp/rh/funcionarioAdicionados.php?id={$empresa_id}';
                  </script>";
            exit;
        } else {
            echo "<script>
                    alert('Erro ao atualizar funcionário.');
                    history.back();
                  </script>";
        }
    } catch (PDOException $e) {
        echo "<script>
                alert('Erro no banco de dados: " . addslashes($e->getMessage()) . "');
                history.back();
              </script>";
    }
} else {
    echo "<script>
            alert('Requisição inválida.');
            history.back();
          </script>";
}
?>

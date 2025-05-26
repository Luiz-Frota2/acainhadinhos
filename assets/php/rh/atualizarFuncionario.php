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
    $dia_folga = trim($_POST["dia_folga"] ?? '');
    $entrada = trim($_POST["entrada"] ?? '');
    $saida_intervalo = trim($_POST["saida_intervalo"] ?? '');
    $retorno_intervalo = trim($_POST["retorno_intervalo"] ?? '');
    $saida_final = trim($_POST["saida_final"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $telefone = trim($_POST["telefone"] ?? '');
    $endereco = trim($_POST["endereco"] ?? '');
    $cidade = trim($_POST["cidade"] ?? '');

    // Verifica apenas nome e cpf
    if (empty($nome) || empty($cpf)) {
        echo "<script>
                alert('Nome e CPF são obrigatórios.');
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
                    dia_folga = :dia_folga,
                    entrada = :entrada,
                    saida_intervalo = :saida_intervalo,
                    retorno_intervalo = :retorno_intervalo,
                    saida_final = :saida_final,
                    email = :email,
                    telefone = :telefone,
                    endereco = :endereco,
                    cidade = :cidade
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);

        // Campos obrigatórios
        $stmt->bindParam(":nome", $nome);
        $stmt->bindParam(":cpf", $cpf);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);

        // Campos opcionais: se vazio, enviar como NULL
        $campos_opcionais = [
            "empresa_id" => $empresa_id,
            "data_nascimento" => $data_nascimento,
            "rg" => $rg,
            "cargo" => $cargo,
            "setor" => $setor,
            "salario" => $salario,
            "escala" => $escala,
            "dia_inicio" => $dia_inicio,
            "dia_folga" => $dia_folga,
            "entrada" => $entrada,
            "saida_intervalo" => $saida_intervalo,
            "retorno_intervalo" => $retorno_intervalo,
            "saida_final" => $saida_final,
            "email" => $email,
            "telefone" => $telefone,
            "endereco" => $endereco,
            "cidade" => $cidade
        ];

        foreach ($campos_opcionais as $campo => $valor) {
            if ($valor === '') {
                $stmt->bindValue(":$campo", null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(":$campo", $valor);
            }
        }

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

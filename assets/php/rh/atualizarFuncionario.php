<?php
require '../conexao.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect form data with null coalescing operator for safety
    $id = $_POST["id"] ?? null;
    $empresa_id = trim($_POST["empresa_id"] ?? '');
    $nome = trim($_POST["nome"] ?? '');
    $data_nascimento = trim($_POST["data_nascimento"] ?? '');
    $cpf = trim($_POST["cpf"] ?? '');
    // Remove dots and dash from CPF
    $cpf = str_replace(['.', '-'], '', $cpf);
    $rg = trim($_POST["rg"] ?? '');
    $pis = trim($_POST["pis"] ?? '');
    $matricula = trim($_POST["matricula"] ?? '');
    $data_admissao = trim($_POST["data_admissao"] ?? '');
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

    // Validate required fields
    if (empty($nome) || empty($cpf) || empty($id)) {
        echo "<script>
                alert('ID, Nome e CPF são obrigatórios.');
                history.back();
              </script>";
        exit;
    }

    // Validate CPF length (11 digits)
    if (strlen($cpf) != 11 || !is_numeric($cpf)) {
        echo "<script>
                alert('CPF deve conter 11 dígitos numéricos.');
                history.back();
              </script>";
        exit;
    }

    try {
        // Check if CPF already exists for another employee
        $checkCpfSql = "SELECT COUNT(*) FROM funcionarios WHERE cpf = :cpf AND id != :id";
        $stmtCheckCpf = $pdo->prepare($checkCpfSql);
        $stmtCheckCpf->bindParam(":cpf", $cpf, PDO::PARAM_STR);
        $stmtCheckCpf->bindParam(":id", $id, PDO::PARAM_INT);
        $stmtCheckCpf->execute();

        if ($stmtCheckCpf->fetchColumn() > 0) {
            echo "<script>
                    alert('Este CPF já está cadastrado para outro funcionário.');
                    history.back();
                  </script>";
            exit;
        }

        // Format salary to decimal
        $salario = $salario === '' ? null : number_format((float) str_replace(',', '.', $salario), 2, '.', '');

        // Prepare SQL update statement with new fields
        $sql = "UPDATE funcionarios SET 
                    empresa_id = :empresa_id,
                    nome = :nome,
                    data_nascimento = :data_nascimento,
                    cpf = :cpf,
                    rg = :rg,
                    pis = :pis,
                    matricula = :matricula,
                    data_admissao = :data_admissao,
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

        // Bind required parameters
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->bindParam(":nome", $nome);
        $stmt->bindParam(":cpf", $cpf);

        // Bind optional parameters with NULL handling
        $optionalFields = [
            ":empresa_id" => $empresa_id,
            ":data_nascimento" => $data_nascimento,
            ":rg" => $rg,
            ":pis" => $pis,
            ":matricula" => $matricula,
            ":data_admissao" => $data_admissao,
            ":cargo" => $cargo,
            ":setor" => $setor,
            ":salario" => $salario,
            ":escala" => $escala,
            ":dia_inicio" => $dia_inicio,
            ":dia_folga" => $dia_folga,
            ":entrada" => $entrada,
            ":saida_intervalo" => $saida_intervalo,
            ":retorno_intervalo" => $retorno_intervalo,
            ":saida_final" => $saida_final,
            ":email" => $email,
            ":telefone" => $telefone,
            ":endereco" => $endereco,
            ":cidade" => $cidade
        ];

        foreach ($optionalFields as $param => $value) {
            if (empty($value)) {
                $stmt->bindValue($param, null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue($param, $value);
            }
        }

        // Execute the update
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
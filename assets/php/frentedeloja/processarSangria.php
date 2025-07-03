<?php

require_once '../conexao.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $valor_sangria = floatval($_POST["valor"] ?? 0);
    $empresa_id = $_POST["idSelecionado"] ?? '';
    $responsavel = $_POST["responsavel"] ?? '';
    $id_caixa = $_POST["id_caixa"] ?? '';
    $cpf_form = preg_replace('/\D/', '', $_POST["cpf"] ?? '');
    $data_registro = $_POST["data_registro"] ?? null;

    try {
        // 1. Buscar dados da abertura pelo CPF
        $stmt = $pdo->prepare("
            SELECT valor_total, valor_suprimentos, valor_sangrias, cpf_responsavel
            FROM aberturas 
            WHERE id = :id_caixa 
              AND empresa_id = :empresa_id 
              AND cpf_responsavel = :cpf_responsavel
              AND status = 'aberto'
        ");
        $stmt->execute([
            ':id_caixa' => $id_caixa,
            ':empresa_id' => $empresa_id,
            ':cpf_responsavel' => $cpf_form
        ]);

        $abertura = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$abertura) {
            throw new Exception("Abertura não encontrada ou CPF inválido.");
        }

        // 2. Calcular valor_liquido atual
        $valor_total = floatval($abertura['valor_total']);
        $valor_suprimentos = floatval($abertura['valor_suprimentos']);
        $valor_sangrias = floatval($abertura['valor_sangrias']);

        $valor_liquido_atual = $valor_total + $valor_suprimentos - $valor_sangrias;

        // 3. Verificar se o valor da sangria é permitido
        if ($valor_sangria > $valor_liquido_atual) {
            echo "<script>
                    alert('Valor da sangria excede o valor disponível no caixa (R$ " . number_format($valor_liquido_atual, 2, ',', '.') . ").');
                    history.back();
                  </script>";
            exit();
        }

        $data_registro = $_POST["data_registro"] ?? null;

        if ($data_registro) {
            $data_registro_formatada = date('Y-m-d H:i:s', strtotime($data_registro));
        } else {
            $data_registro_formatada = date('Y-m-d H:i:s'); // agora
        }


        // 4. Calcular novo total de sangrias e novo valor_liquido
        $novo_total_sangrias = $valor_sangrias + $valor_sangria;
        $novo_valor_liquido = $valor_total + $valor_suprimentos - $novo_total_sangrias;

        // 5. Inserir a sangria
        $sql = "INSERT INTO sangrias (
            valor, empresa_id, id_caixa, valor_liquido, responsavel, cpf_responsavel, data_registro
        ) VALUES (
            :valor, :empresa_id, :id_caixa, :valor_liquido, :responsavel, :cpf_responsavel, :data_registro
        )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':valor' => $valor_sangria,
            ':empresa_id' => $empresa_id,
            ':id_caixa' => $id_caixa,
            ':valor_liquido' => $novo_valor_liquido,
            ':responsavel' => $responsavel,
            ':cpf_responsavel' => $cpf_form,
            ':data_registro' => $data_registro_formatada
        ]);


        // 6. Atualizar aberturas com o novo valor de sangrias
        $update = $pdo->prepare("
            UPDATE aberturas 
            SET valor_sangrias = :valor_sangrias
            WHERE id = :id_caixa 
              AND empresa_id = :empresa_id 
              AND cpf_responsavel = :cpf_responsavel
              AND status = 'aberto'
        ");
        $update->execute([
            ':valor_sangrias' => $novo_total_sangrias,
            ':id_caixa' => $id_caixa,
            ':empresa_id' => $empresa_id,
            ':cpf_responsavel' => $cpf_form
        ]);

        // 7. Sucesso
        echo "<script>
                alert('Sangria registrada com sucesso!');
                window.location.href = '../../../../frentedeloja/caixa/index.php?id=" . urlencode($empresa_id) . "';
              </script>";
        exit();
    } catch (Exception $e) {
        echo "<script>
                alert('Erro: " . addslashes($e->getMessage()) . "');
                history.back();
              </script>";
    }
}

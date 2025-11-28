<?php
// folga_Salvar.php
require '../../assets/php/conexao.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // -------------- CAPTURA DOS NOVOS CAMPOS ---------------
    $tipo_folga = isset($_POST["tipo_folga"]) ? trim($_POST["tipo_folga"]) : "";
    $motivo_folga = isset($_POST["motivo_folga"]) ? trim($_POST["motivo_folga"]) : "";

    if ($tipo_folga !== "normal" && $tipo_folga !== "ferias") {
        echo "<script>alert('Selecione um tipo de folga válido.'); history.back();</script>";
        exit();
    }

    // Se for férias → motivo obrigatório
    if ($tipo_folga === "ferias" && empty($motivo_folga)) {
        echo "<script>alert('Informe o motivo da folga por férias.'); history.back();</script>";
        exit();
    }

    // -------------------------------------------------------

    $empresa_id = isset($_POST["id"]) ? trim($_POST["id"])
        : (isset($_POST["id_selecionado"]) ? trim($_POST["id_selecionado"])
            : (isset($_GET["id"]) ? trim($_GET["id"]) : null));

    $cpf_raw    = isset($_POST["cpf"]) ? trim($_POST["cpf"]) : (isset($_GET["cpf"]) ? trim($_GET["cpf"]) : "");
    $data_folga = isset($_POST["data_folga"]) ? trim($_POST["data_folga"]) : "";

    // Validações
    if (empty($empresa_id) || empty($cpf_raw) || empty($data_folga)) {
        echo "<script>alert('Empresa, CPF e data são obrigatórios.'); history.back();</script>";
        exit();
    }

    $cpf = preg_replace('/\D+/', '', $cpf_raw);
    if (strlen($cpf) < 11) {
        echo "<script>alert('CPF inválido.'); history.back();</script>";
        exit();
    }

    try {
        $dt = new DateTime($data_folga);
        $data_sql = $dt->format('Y-m-d');
    } catch (Exception $e) {
        echo "<script>alert('Data da folga inválida.'); history.back();</script>";
        exit();
    }

    try {
        // ---------------- LOCALIZAÇÃO DO NOME ----------------
        $nome = null;

        $sql = "SELECT nome FROM funcionarios
                WHERE REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = :cpf
                  AND empresa_id = :empresa
                LIMIT 1";
        $st = $pdo->prepare($sql);
        $st->execute([':cpf' => $cpf, ':empresa' => $empresa_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['nome'])) $nome = $row['nome'];

        if (!$nome) {
            $sql = "SELECT nome FROM funcionarios
                    WHERE REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = :cpf
                    LIMIT 1";
            $st = $pdo->prepare($sql);
            $st->execute([':cpf' => $cpf]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['nome'])) $nome = $row['nome'];
        }

        if (!$nome) {
            $sql = "SELECT nome FROM folgas
                    WHERE REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = :cpf
                    ORDER BY id DESC
                    LIMIT 1";
            $st = $pdo->prepare($sql);
            $st->execute([':cpf' => $cpf]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['nome'])) $nome = $row['nome'];
        }

        if (!$nome) {
            $sql = "SELECT usuario AS nome FROM contas_acesso
                    WHERE REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = :cpf
                    LIMIT 1";
            $st = $pdo->prepare($sql);
            $st->execute([':cpf' => $cpf]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['nome'])) $nome = $row['nome'];
        }

        if (!$nome) {
            echo "<script>alert('Não foi possível localizar o nome do funcionário.'); history.back();</script>";
            exit();
        }

        // ---------------- CHECA DUPLICIDADE ----------------
        $dup = $pdo->prepare("
            SELECT COUNT(*) FROM folgas
            WHERE REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = :cpf
              AND data_folga = :data
        ");
        $dup->execute([':cpf' => $cpf, ':data' => $data_sql]);

        if ((int)$dup->fetchColumn() > 0) {
            echo "<script>alert('Já existe uma folga nesta data.'); history.back();</script>";
            exit();
        }

        // ---------------- INSERÇÃO NOVA ----------------
        $ins = $pdo->prepare("
            INSERT INTO folgas (cpf, nome, data_folga, tipo_folga, motivo)
            VALUES (:cpf, :nome, :data_folga, :tipo, :motivo)
        ");

        $ok = $ins->execute([
            ':cpf'        => $cpf,
            ':nome'       => $nome,
            ':data_folga' => $data_sql,
            ':tipo'       => $tipo_folga,
            ':motivo'     => $motivo_folga
        ]);

        if ($ok) {
            echo "<script>
                    alert('Folga cadastrada com sucesso!');
                    window.location.href = './ajusteFolga.php?id=" . rawurlencode($empresa_id) . "&cpf=" . rawurlencode($cpf_raw) . "';
                  </script>";
            exit();
        } else {
            echo "<script>alert('Erro ao cadastrar a folga.'); history.back();</script>";
            exit();
        }

    } catch (Exception $e) {
        echo "<script>alert('Erro: " . addslashes($e->getMessage()) . "'); history.back();</script>";
        exit();
    }
}
?>

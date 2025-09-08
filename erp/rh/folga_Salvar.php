<?php
// folga_Salvar.php
// Inclui o arquivo de conexão (ajuste o caminho se precisar)
require '../../assets/php/conexao.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Coleta dos dados
    $empresa_id = isset($_POST["id"]) ? trim($_POST["id"])
        : (isset($_POST["id_selecionado"]) ? trim($_POST["id_selecionado"])
            : (isset($_GET["id"]) ? trim($_GET["id"]) : null));

    $cpf_raw    = isset($_POST["cpf"]) ? trim($_POST["cpf"]) : (isset($_GET["cpf"]) ? trim($_GET["cpf"]) : "");
    $data_folga = isset($_POST["data_folga"]) ? trim($_POST["data_folga"]) : "";

    // Validações básicas
    if (empty($empresa_id) || empty($cpf_raw) || empty($data_folga)) {
        echo "<script>
                alert('Empresa, CPF e data são obrigatórios.');
                history.back();
              </script>";
        exit();
    }

    // Normaliza CPF (somente dígitos)
    $cpf = preg_replace('/\D+/', '', $cpf_raw);
    if (strlen($cpf) < 11) {
        echo "<script>
                alert('CPF inválido.');
                history.back();
              </script>";
        exit();
    }

    // Normaliza data
    try {
        $dt = new DateTime($data_folga);
        $data_sql = $dt->format('Y-m-d');
    } catch (Exception $e) {
        echo "<script>
                alert('Data da folga inválida.');
                history.back();
              </script>";
        exit();
    }

    try {
        // ===== Busca do nome do funcionário =====
        $nome = null;

        // 1) Tenta funcionarios por CPF + empresa_id
        try {
            $sql = "SELECT nome FROM funcionarios
                    WHERE REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = :cpf
                      AND empresa_id = :empresa
                    LIMIT 1";
            $st = $pdo->prepare($sql);
            $st->execute([':cpf' => $cpf, ':empresa' => $empresa_id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['nome'])) $nome = $row['nome'];
        } catch (Exception $e) { /* segue */
        }

        // 2) Só por CPF (sem empresa)
        if (!$nome) {
            $sql = "SELECT nome FROM funcionarios
                    WHERE REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = :cpf
                    LIMIT 1";
            $st = $pdo->prepare($sql);
            $st->execute([':cpf' => $cpf]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['nome'])) $nome = $row['nome'];
        }

        // 3) Último nome já usado em folgas
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

        // 4) contas_acesso como último recurso
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
            echo "<script>
                    alert('Não foi possível localizar o nome do funcionário para este CPF.');
                    history.back();
                  </script>";
            exit();
        }

        // ===== Duplicidade (CPF + data) =====
        $dup = $pdo->prepare("
            SELECT COUNT(*) FROM folgas
            WHERE REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = :cpf
              AND data_folga = :data
        ");
        $dup->execute([':cpf' => $cpf, ':data' => $data_sql]);
        if ((int)$dup->fetchColumn() > 0) {
            echo "<script>
                    alert('Já existe uma folga cadastrada para este CPF nesta data.');
                    history.back();
                  </script>";
            exit();
        }

        // ===== Inserção =====
        $ins = $pdo->prepare("INSERT INTO folgas (cpf, nome, data_folga) VALUES (:cpf, :nome, :data)");
        $ok  = $ins->execute([
            ':cpf'  => $cpf,
            ':nome' => $nome,
            ':data' => $data_sql
        ]);

        if ($ok) {
            echo "<script>
                    alert('Folga cadastrada com sucesso!');
                    window.location.href = './ajusteFolga.php?id=" . rawurlencode($empresa_id) . "';
                  </script>";
            exit();
        } else {
            echo "<script>
                    alert('Erro ao cadastrar a folga.');
                    history.back();
                  </script>";
            exit();
        }
    } catch (Exception $e) {
        echo "<script>
                alert('Erro: " . addslashes($e->getMessage()) . "');
                history.back();
              </script>";
        exit();
    }
}

?>
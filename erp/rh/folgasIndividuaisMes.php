<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

require '../../assets/php/conexao.php'; // deve definir $conn (PDO)

if (!isset($_SESSION['empresa_id'])) {
    die('Empresa não logada.');
}

$empresaId = $_SESSION['empresa_id'];
$mesAtual = date('m');
$anoAtual = date('Y');

$sql = "
    SELECT 
        f.cpf,
        f.nome,
        COUNT(*) AS total_folgas
    FROM 
        folgas f
    INNER JOIN 
        pontos p ON f.cpf = p.cpf AND f.data_folga = p.data AND p.empresa_id = :empresa_id
    WHERE 
        MONTH(f.data_folga) = :mes AND YEAR(f.data_folga) = :ano
    GROUP BY 
        f.cpf, f.nome
    ORDER BY 
        f.nome
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':empresa_id' => $empresaId,
    ':mes' => $mesAtual,
    ':ano' => $anoAtual
]);

$folgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Folgas Individuais do Mês</title>
    <style>
        table { border-collapse: collapse; width: 80%; margin: 20px auto; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ccc; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h2 style="text-align: center;">Folgas Individuais - <?= date('F Y') ?></h2>

    <table>
        <thead>
            <tr>
                <th>CPF</th>
                <th>Nome</th>
                <th>Total de Folgas no Mês</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($folgas) > 0): ?>
                <?php foreach ($folgas as $linha): ?>
                    <tr>
                        <td><?= htmlspecialchars($linha['cpf']) ?></td>
                        <td><?= htmlspecialchars($linha['nome']) ?></td>
                        <td><?= $linha['total_folgas'] ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" style="text-align: center;">Nenhuma folga registrada neste mês.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>

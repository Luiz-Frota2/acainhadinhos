<?php 
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// === Conexão (mantendo seu include) ===
require '../../assets/php/conexao.php'; // pode definir $pdo OU $conn (PDO)
$pdo = $pdo ?? (isset($conn) && $conn instanceof PDO ? $conn : null);
if (!$pdo || !($pdo instanceof PDO)) {
    die('Conexão indisponível.');
}

// === Sessão da empresa (mantido) ===
if (!isset($_SESSION['empresa_id'])) {
    die('Empresa não logada.');
}
$empresaId = (string)$_SESSION['empresa_id'];

/* ===========================================================
   PROCESSO: CADASTRAR FOLGA + REDIRECIONAR (sem mexer no layout)
   - Espera POST com: cpf, nome, data_folga (YYYY-MM-DD)
   - Em qualquer resultado (ok/erro), faz redirect e encerra
   - Se quiser escolher o destino, envie um campo hidden _redirect
  =========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lê dados sem alterar layout
    $cpfRaw = trim((string)($_POST['cpf'] ?? ''));
    $nome   = trim((string)($_POST['nome'] ?? ''));
    $data   = trim((string)($_POST['data_folga'] ?? ''));

    // Normaliza CPF (apenas dígitos)
    $cpf = preg_replace('/\D+/', '', $cpfRaw) ?? '';

    // Validação simples
    $erros = [];
    if ($cpf === '' || strlen($cpf) !== 11) $erros[] = 'CPF inválido.';
    if ($nome === '') $erros[] = 'Nome é obrigatório.';
    if ($data === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        $erros[] = 'Data inválida. Use AAAA-MM-DD.';
    } else {
        [$yy, $mm, $dd] = explode('-', $data);
        if (!checkdate((int)$mm, (int)$dd, (int)$yy)) {
            $erros[] = 'Data inexistente.';
        }
    }

    try {
        if (empty($erros)) {
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Evita duplicidade: mesmo CPF + data
            $sqlDup = "SELECT 1 FROM folgas WHERE cpf = :cpf AND data_folga = :data LIMIT 1";
            $stDup  = $pdo->prepare($sqlDup);
            $stDup->execute([':cpf' => $cpf, ':data' => $data]);

            if (!$stDup->fetchColumn()) {
                // Insere (tabela: folgas -> cpf, nome, data_folga)
                $sqlIns = "INSERT INTO folgas (cpf, nome, data_folga) VALUES (:cpf, :nome, :data)";
                $stIns  = $pdo->prepare($sqlIns);
                $stIns->execute([':cpf' => $cpf, ':nome' => $nome, ':data' => $data]);
                // opcional: $id = (int)$pdo->lastInsertId();
            }
            // Se já existia, apenas segue para redirecionar (sem mexer na tela)
        }
    } catch (Throwable $e) {
        // Em erro, apenas segue para redirecionar também (sem imprimir nada)
        // Você pode logar $e->getMessage() se quiser
    }

    // Redireciona SEM alterar layout
    $dest = (string)($_POST['_redirect'] ?? '');
    if ($dest === '') {
        // volta para a própria página
        $dest = $_SERVER['PHP_SELF'];
        // opcional: incluir querystring de status sem exibir no layout
        // $dest .= (strpos($dest, '?') !== false ? '&' : '?') . 'saved=1';
    }
    header('Location: ' . $dest);
    exit;
}

// =========================
// LISTAGEM: FOLGAS DO MÊS
// (mantido SEM alterar layout)
// =========================
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
                        <td><?= (int)$linha['total_folgas'] ?></td>
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

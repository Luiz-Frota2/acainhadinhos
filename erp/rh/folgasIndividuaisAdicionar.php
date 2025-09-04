<?php 
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

require '../../assets/php/conexao.php'; // deve definir $pdo OU $conn (PDO)

// Normaliza a variável de conexão para $pdo (fallback se vier como $conn)
$pdo = $pdo ?? (isset($conn) && $conn instanceof PDO ? $conn : null);
if (!$pdo || !($pdo instanceof PDO)) {
    die('Conexão indisponível.');
}

if (!isset($_SESSION['empresa_id'])) {
    die('Empresa não logada.');
}

$empresaId = (string)$_SESSION['empresa_id'];

/* =========================
   PROCESSO: CADASTRAR FOLGA
   - Espera POST com: cpf, nome, data_folga (YYYY-MM-DD)
   - Valida campos
   - Evita duplicidade (cpf + data_folga)
   - Insere em folgas (cpf, nome, data_folga)
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Aceita tanto requisições normais quanto AJAX; não altera layout da página
    $cpfRaw = trim((string)($_POST['cpf'] ?? ''));
    $nome   = trim((string)($_POST['nome'] ?? ''));
    $data   = trim((string)($_POST['data_folga'] ?? ''));

    // Normaliza CPF (somente dígitos)
    $cpf = preg_replace('/\D+/', '', $cpfRaw) ?? '';

    $erros = [];

    if ($cpf === '' || strlen($cpf) !== 11) {
        $erros[] = 'Informe um CPF válido (11 dígitos).';
    }
    if ($nome === '') {
        $erros[] = 'Informe o nome.';
    }
    if ($data === '') {
        $erros[] = 'Informe a data da folga.';
    } else {
        $validaData = false;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            [$yy, $mm, $dd] = explode('-', $data);
            $validaData = checkdate((int)$mm, (int)$dd, (int)$yy);
        }
        if (!$validaData) {
            $erros[] = 'Data inválida. Use o formato AAAA-MM-DD.';
        }
    }

    if (empty($erros)) {
        try {
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Evita duplicidade
            $sqlDup = "SELECT 1 FROM folgas WHERE cpf = :cpf AND data_folga = :data LIMIT 1";
            $stDup = $pdo->prepare($sqlDup);
            $stDup->execute([':cpf' => $cpf, ':data' => $data]);

            if ($stDup->fetchColumn()) {
                // Mantém layout; nenhuma alteração visual imposta — apenas define uma flag para uso opcional
                $ok = false;
                $msg = 'Já existe folga cadastrada para este CPF nesta data.';
            } else {
                // Insere
                $sqlIns = "INSERT INTO folgas (cpf, nome, data_folga) VALUES (:cpf, :nome, :data)";
                $stIns = $pdo->prepare($sqlIns);
                $stIns->execute([
                    ':cpf'  => $cpf,
                    ':nome' => $nome,
                    ':data' => $data,
                ]);

                $ok = true;
                $msg = 'Folga cadastrada com sucesso.';
            }
        } catch (Throwable $e) {
            $ok = false;
            $msg = 'Erro ao salvar: ' . $e->getMessage();
        }
    } else {
        $ok = false;
        $msg = implode(' ', $erros);
    }

    // Se quiser usar via AJAX, ative o cabeçalho a seguir.
    // Detecta AJAX pelo header padrão.
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && stripos((string)$_SERVER['HTTP_X_REQUESTED_WITH'], 'xmlhttprequest') !== false;
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => (bool)$ok, 'message' => (string)$msg], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // Para submissão normal (não AJAX), segue renderizando a mesma página sem alterar o layout.
    // Se preferir redirecionar, descomente:
    // header('Location: '.$_SERVER['PHP_SELF'].'?ok='.(int)$ok.'&msg='.urlencode((string)$msg));
    // exit;
}

/* =========================
   LISTAGEM: FOLGAS DO MÊS
   (mantido SEM alterar layout)
   ========================= */
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
        pontos p ON f.cpf = p.cpf 
                 AND f.data_folga = p.data 
                 AND p.empresa_id = :empresa_id
    WHERE 
        MONTH(f.data_folga) = :mes 
        AND YEAR(f.data_folga) = :ano
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

// Helper de saída segura
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
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
    <!-- Mantido o mesmo título e estrutura -->
    <h2 style="text-align: center;">Folgas Individuais - <?= date('F Y') ?></h2>

    <!-- Opcional: mostrar mensagem sem interferir no layout principal (não altera tabela/estilos) -->
    <?php if (isset($msg) && $msg !== ''): ?>
      <div style="width:80%;margin:0 auto 10px auto;font:14px/1.4 Arial;">
        <span style="color:<?= !empty($ok) ? '#067d06' : '#b00020' ?>"><?= h($msg) ?></span>
      </div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>CPF</th>
                <th>Nome</th>
                <th>Total de Folgas no Mês</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($folgas)): ?>
                <?php foreach ($folgas as $linha): ?>
                    <tr>
                        <td><?= h($linha['cpf']) ?></td>
                        <td><?= h($linha['nome']) ?></td>
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

    <!-- 
      IMPORTANTE:
      - Este arquivo NÃO adiciona novos elementos de layout (formulário/modal).
      - Para cadastrar via esta página sem alterar layout, envie um POST (por exemplo via AJAX/fetch)
        com os campos: cpf, nome, data_folga (YYYY-MM-DD) para esta MESMA URL.
      - Exemplo JS (opcional, não inserido para não mexer no layout):
        fetch(location.href, {
          method: 'POST',
          headers: {'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},
          body: new URLSearchParams({cpf:'12345678901', nome:'Fulano', data_folga:'2025-09-05'})
        }).then(r=>r.json()).then(console.log);
    -->
</body>
</html>

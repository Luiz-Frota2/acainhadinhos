<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// inclui conexão
require_once "../../assets/php/conexao.php";

// -------------------------
// CAPTURA PARÂMETROS
// -------------------------
$idSelecionado = $_GET['id'] ?? '';
$inicioFiltro  = $_GET['inicio'] ?? date("Y-m-01");
$fimFiltro     = $_GET['fim'] ?? date("Y-m-t");

// converte datas para período completo
$inicioDatetime = $inicioFiltro . " 00:00:00";
$fimDatetime    = $fimFiltro . " 23:59:59";

// -------------------------
// CONSULTAS PRINCIPAIS
// -------------------------
try {
    // filiais da empresa selecionada
    $stmt = $pdo->prepare("SELECT nome FROM unidades WHERE id = ?");
    $stmt->execute([$idSelecionado]);
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
    $empresaNome = $empresa['nome'] ?? 'Filial não encontrada';

    // resumo de vendas
    $sqlResumo = $pdo->prepare("
        SELECT COUNT(*) AS total_pedidos,
               SUM(valor_total) AS total_faturamento
        FROM vendas
        WHERE empresa_id = CONCAT('unidade_', ?)
          AND data_venda BETWEEN ? AND ?
    ");
    $sqlResumo->execute([$idSelecionado, $inicioDatetime, $fimDatetime]);
    $resumo = $sqlResumo->fetch(PDO::FETCH_ASSOC);

    $totalPedidos = (int)($resumo['total_pedidos'] ?? 0);
    $faturamento  = (float)($resumo['total_faturamento'] ?? 0.0);
    $ticketMedio  = $totalPedidos > 0 ? ($faturamento / $totalPedidos) : 0;

    // produtos vendidos
    $sqlProdutos = $pdo->prepare("
        SELECT iv.codigo_produto, iv.nome_produto,
               SUM(iv.quantidade) AS total_quantidade,
               SUM(iv.subtotal) AS total_valor
        FROM itens_venda iv
        INNER JOIN vendas v ON v.id = iv.venda_id
        WHERE v.empresa_id = CONCAT('unidade_', ?)
          AND v.data_venda BETWEEN ? AND ?
        GROUP BY iv.codigo_produto, iv.nome_produto
        ORDER BY total_quantidade DESC
        LIMIT 100
    ");
    $sqlProdutos->execute([$idSelecionado, $inicioDatetime, $fimDatetime]);
    $produtos = $sqlProdutos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao carregar relatório: " . $e->getMessage());
}

// -------------------------
// HTML DE IMPRESSÃO
// -------------------------
$titulo = "Relatório de Vendas — " . htmlspecialchars($empresaNome);
$periodoTexto = date("d/m/Y", strtotime($inicioFiltro)) . " a " . date("d/m/Y", strtotime($fimFiltro));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title><?= $titulo ?></title>
    <style>
        @page { margin: 18mm; }
        body {
            font-family: "Public Sans", Arial, sans-serif;
            color: #222;
            font-size: 12px;
            margin: 20mm;
        }
        h1, h2 { margin: 0; }
        .empresa-info {
            text-align: center;
            margin-bottom: 20px;
        }
        .empresa-info h1 {
            font-size: 18px;
            font-weight: 600;
        }
        .empresa-info small {
            color: #666;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            font-size: 12px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 6px 8px;
        }
        th {
            background: #f3f3f3;
            text-align: left;
        }
        footer {
            position: fixed;
            bottom: 10mm;
            text-align: center;
            color: #888;
            font-size: 11px;
        }
        .no-print, .logo, img.logo, header img { display: none !important; }
    </style>
</head>
<body>
    <div class="empresa-info">
        <h1><?= htmlspecialchars($titulo) ?></h1>
        <small>Período: <?= htmlspecialchars($periodoTexto) ?></small>
    </div>

    <h2>Resumo do Período</h2>
    <table>
        <thead>
            <tr><th>Métrica</th><th>Valor</th></tr>
        </thead>
        <tbody>
            <tr><td>Total de Pedidos</td><td><?= number_format($totalPedidos, 0, ',', '.') ?></td></tr>
            <tr><td>Faturamento Total (R$)</td><td>R$ <?= number_format($faturamento, 2, ',', '.') ?></td></tr>
            <tr><td>Ticket Médio (R$)</td><td>R$ <?= number_format($ticketMedio, 2, ',', '.') ?></td></tr>
        </tbody>
    </table>

    <h2>Produtos Vendidos</h2>
    <table>
        <thead>
            <tr><th>SKU</th><th>Produto</th><th>Quantidade</th><th>Subtotal (R$)</th></tr>
        </thead>
        <tbody>
            <?php if (!empty($produtos)): ?>
                <?php foreach ($produtos as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['codigo_produto']) ?></td>
                        <td><?= htmlspecialchars($p['nome_produto']) ?></td>
                        <td><?= (int)$p['total_quantidade'] ?></td>
                        <td>R$ <?= number_format($p['total_valor'], 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4" style="text-align:center;">Nenhum produto encontrado no período.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <footer>
        Relatório gerado em <?= date("d/m/Y H:i") ?>
    </footer>

    <script>
        window.onload = function() {
            // abre diálogo de impressão
            setTimeout(() => window.print(), 400);
        };

        window.onafterprint = function() {
            // retorna à página de vendas da filial
            window.location.href = "../../vendas_por_filial.php?id=<?= urlencode($idSelecionado) ?>";
        };
    </script>
</body>
</html>

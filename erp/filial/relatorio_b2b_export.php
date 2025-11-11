<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Ajuste o caminho caso necessário
require_once "../../assets/php/conexao.php";

// tenta carregar PhpSpreadsheet — se não, tratamos mais abaixo
$hasPhpSpreadsheet = false;
try {
    if (file_exists(__DIR__ . "/../../vendor/autoload.php")) {
        require_once __DIR__ . "/../../vendor/autoload.php";
        $hasPhpSpreadsheet = class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class);
    }
} catch (\Throwable $e) {
    $hasPhpSpreadsheet = false;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// -------------------------
// CAPTURA PARÂMETROS
// -------------------------
$tipo = $_GET['tipo'] ?? 'print'; // print / csv / xlsx

$filialFiltroId = isset($_GET['status']) && $_GET['status'] !== '' ? intval($_GET['status']) : null;
$inicioFiltro = isset($_GET['codigo']) && $_GET['codigo'] !== '' ? trim($_GET['codigo']) : '';
$fimFiltro    = isset($_GET['categoria']) && $_GET['categoria'] !== '' ? trim($_GET['categoria']) : '';
$idSelecionado = $_GET['id'] ?? '';

// logo opcional: caminho relativo ao servidor (se quiser mostrar no header impresso)
$logoPath = $_GET['logo'] ?? ''; // ex: '/assets/img/logo.png'

// datas padrão
if ($inicioFiltro === "") $inicioFiltro = date("Y-m-01");
if ($fimFiltro === "")    $fimFiltro    = date("Y-m-t");

$inicioDatetime = $inicioFiltro . " 00:00:00";
$fimDatetime    = $fimFiltro    . " 23:59:59";

// -------------------------
// FUNÇÕES AUX
// -------------------------
function placeholders(array $arr): string {
    if (empty($arr)) return "";
    return implode(",", array_fill(0, count($arr), "?"));
}
function filialKeysFromIds(array $ids): array {
    return array_map(fn($id) => "unidade_" . $id, $ids);
}

// -------------------------
// CARREGA FILIAIS (aplica filtro se houver)
// -------------------------
try {
    if ($filialFiltroId !== null) {
        $stmt = $pdo->prepare("SELECT id, nome FROM unidades WHERE tipo = 'Filial' AND id = ? ORDER BY nome");
        $stmt->execute([$filialFiltroId]);
    } else {
        $stmt = $pdo->query("SELECT id, nome FROM unidades WHERE tipo = 'Filial' ORDER BY nome");
    }
    $filiais = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $filiais = [];
}

$filiaisIds = array_column($filiais, 'id');
$filialKeys = !empty($filiaisIds) ? filialKeysFromIds($filiaisIds) : [];

// -------------------------
// Função calcularPeriodo (reaproveita lógica)
function calcularPeriodo(PDO $pdo, string $inicioDT, string $fimDT, array $filialKeys) {
    if (empty($filialKeys)) {
        return ["pedidos" => 0, "itens" => 0, "faturamento" => 0, "ticket" => 0];
    }
    $inFiliais = implode(",", array_fill(0, count($filialKeys), "?"));
    $sql = "SELECT id FROM solicitacoes_b2b WHERE id_solicitante IN ($inFiliais) AND created_at BETWEEN ? AND ?";
    $params = array_merge($filialKeys, [$inicioDT, $fimDT]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $idsPedidos = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($idsPedidos)) {
        return ["pedidos" => 0, "itens" => 0, "faturamento" => 0, "ticket" => 0];
    }
    $inPedidos = implode(",", array_fill(0, count($idsPedidos), "?"));
    $sql2 = "SELECT SUM(quantidade) AS totalItens, SUM(subtotal) AS totalFaturamento FROM solicitacoes_b2b_itens WHERE solicitacao_id IN ($inPedidos)";
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute($idsPedidos);
    $dados = $stmt2->fetch(PDO::FETCH_ASSOC);
    $totalItens = (int)($dados['totalItens'] ?? 0);
    $totalFaturamento = (float)($dados['totalFaturamento'] ?? 0.0);
    $totalPedidos = count($idsPedidos);
    $ticket = $totalPedidos > 0 ? ($totalFaturamento / $totalPedidos) : 0;
    return ["pedidos" => $totalPedidos, "itens" => $totalItens, "faturamento" => $totalFaturamento, "ticket" => $ticket];
}

// -------------------------
// CALCULA RESUMO ATUAL / ANTERIOR (30 dias)
// -------------------------
$inicioAnterior = date("Y-m-d", strtotime($inicioFiltro . " -30 days"));
$fimAnterior    = date("Y-m-d", strtotime($fimFiltro . " -30 days"));

$atual = calcularPeriodo($pdo, $inicioDatetime, $fimDatetime, $filialKeys);
$anterior = calcularPeriodo($pdo, $inicioAnterior . " 00:00:00", $fimAnterior . " 23:59:59", $filialKeys);

function variacao($atual, $anterior) {
    if ($anterior <= 0) return 0;
    return (($atual - $anterior) / $anterior) * 100;
}

// monta array resumo (para exportar)
$resumo = [
    "Pedidos B2B"        => [$atual["pedidos"], variacao($atual["pedidos"], $anterior["pedidos"])],
    "Itens Solicitados"  => [$atual["itens"], variacao($atual["itens"], $anterior["itens"])],
    "Faturamento Estimado" => [$atual["faturamento"], variacao($atual["faturamento"], $anterior["faturamento"])],
    "Ticket Médio"       => [$atual["ticket"], variacao($atual["ticket"], $anterior["ticket"])]
];

// -------------------------
// MONTAR LISTA COMPLETA DE FILIAIS (NÃO PAGINADA) PARA EXPORT
// -------------------------
$listaFiliaisExport = [];
$totalFaturamentoGeral = 0.0;

foreach ($filiais as $f) {
    $empresaKey = "unidade_" . $f["id"];
    $nomeFilial = $f["nome"];

    $sqlV = $pdo->prepare("
        SELECT COUNT(*) AS pedidos, SUM(valor_total) AS total_faturamento
        FROM vendas
        WHERE empresa_id = ?
        AND data_venda BETWEEN ? AND ?
    ");
    $sqlV->execute([$empresaKey, $inicioDatetime, $fimDatetime]);
    $dados = $sqlV->fetch(PDO::FETCH_ASSOC);
    $pedidos = (int)($dados["pedidos"] ?? 0);
    $faturamento = (float)($dados["total_faturamento"] ?? 0.0);

    $sqlItens = $pdo->prepare("
        SELECT SUM(iv.quantidade) AS total_itens
        FROM itens_venda iv
        INNER JOIN vendas v ON v.id = iv.venda_id
        WHERE v.empresa_id = ?
        AND v.data_venda BETWEEN ? AND ?
    ");
    $sqlItens->execute([$empresaKey, $inicioDatetime, $fimDatetime]);
    $dadosItens = $sqlItens->fetch(PDO::FETCH_ASSOC);
    $itens = (int)($dadosItens["total_itens"] ?? 0);

    $ticket = $pedidos > 0 ? ($faturamento / $pedidos) : 0;

    $listaFiliaisExport[] = [
        "nome" => $nomeFilial,
        "pedidos" => $pedidos,
        "itens" => $itens,
        "faturamento" => $faturamento,
        "ticket" => $ticket
    ];

    $totalFaturamentoGeral += $faturamento;
}
foreach ($listaFiliaisExport as $i => $row) {
    $listaFiliaisExport[$i]['perc'] = $totalFaturamentoGeral > 0 ? ($row['faturamento'] / $totalFaturamentoGeral) * 100 : 0;
}

// -------------------------
// PRODUTOS MAIS SOLICITADOS
// -------------------------
$produtosLista = [];
if (!empty($filialKeys)) {
    $inFiliais = placeholders($filialKeys);
    $sqlSolic = $pdo->prepare("SELECT id FROM solicitacoes_b2b WHERE id_solicitante IN ($inFiliais) AND created_at BETWEEN ? AND ?");
    $params = array_merge($filialKeys, [$inicioDatetime, $fimDatetime]);
    $sqlSolic->execute($params);
    $solicitacoesIds = $sqlSolic->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($solicitacoesIds)) {
        $inSolic = placeholders($solicitacoesIds);
        $sqlItens = $pdo->prepare("
            SELECT codigo_produto, nome_produto, SUM(quantidade) AS total_quantidade, COUNT(DISTINCT solicitacao_id) AS total_pedidos
            FROM solicitacoes_b2b_itens
            WHERE solicitacao_id IN ($inSolic)
            GROUP BY codigo_produto, nome_produto
            ORDER BY total_quantidade DESC
            LIMIT 100
        ");
        $sqlItens->execute($solicitacoesIds);
        $produtosLista = $sqlItens->fetchAll(PDO::FETCH_ASSOC);
        $totalGeral = array_sum(array_column($produtosLista, "total_quantidade"));
        foreach ($produtosLista as $i => $prod) {
            $produtosLista[$i]["perc"] = $totalGeral > 0 ? ($prod["total_quantidade"] / $totalGeral) * 100 : 0;
        }
    }
}

// -------------------------
// PAGAMENTOS X ENTREGAS (RESUMO)
// -------------------------
$pendQtd = $pendValor = 0;
$aprovQtd = $aprovValor = 0;
$reprovQtd = $reprovValor = 0;
$remessasEnviadas = $remessasConcluidas = 0;

if (!empty($filialKeys)) {
    $inFiliais = placeholders($filialKeys);

    $sqlPend = $pdo->prepare("SELECT COUNT(*) AS qtd, SUM(valor) AS total FROM solicitacoes_pagamento WHERE id_solicitante IN ($inFiliais) AND status = 'pendente' AND created_at BETWEEN ? AND ?");
    $sqlPend->execute(array_merge($filialKeys, [$inicioDatetime, $fimDatetime]));
    $r = $sqlPend->fetch(PDO::FETCH_ASSOC);
    $pendQtd = (int)($r['qtd'] ?? 0); $pendValor = (float)($r['total'] ?? 0.0);

    $sqlAprov = $pdo->prepare("SELECT COUNT(*) AS qtd, SUM(valor) AS total FROM solicitacoes_pagamento WHERE id_solicitante IN ($inFiliais) AND status = 'aprovado' AND created_at BETWEEN ? AND ?");
    $sqlAprov->execute(array_merge($filialKeys, [$inicioDatetime, $fimDatetime]));
    $r = $sqlAprov->fetch(PDO::FETCH_ASSOC);
    $aprovQtd = (int)($r['qtd'] ?? 0); $aprovValor = (float)($r['total'] ?? 0.0);

    $sqlReprov = $pdo->prepare("SELECT COUNT(*) AS qtd, SUM(valor) AS total FROM solicitacoes_pagamento WHERE id_solicitante IN ($inFiliais) AND status = 'reprovado' AND created_at BETWEEN ? AND ?");
    $sqlReprov->execute(array_merge($filialKeys, [$inicioDatetime, $fimDatetime]));
    $r = $sqlReprov->fetch(PDO::FETCH_ASSOC);
    $reprovQtd = (int)($r['qtd'] ?? 0); $reprovValor = (float)($r['total'] ?? 0.0);

    $sqlAprovadoCount = $pdo->prepare("SELECT COUNT(*) FROM solicitacoes_pagamento WHERE id_solicitante IN ($inFiliais) AND status = 'aprovado' AND created_at BETWEEN ? AND ?");
    $sqlAprovadoCount->execute(array_merge($filialKeys, [$inicioDatetime, $fimDatetime]));
    $remessasEnviadas = (int)$sqlAprovadoCount->fetchColumn();

    $sqlReprovadoCount = $pdo->prepare("SELECT COUNT(*) FROM solicitacoes_pagamento WHERE id_solicitante IN ($inFiliais) AND status = 'reprovado' AND created_at BETWEEN ? AND ?");
    $sqlReprovadoCount->execute(array_merge($filialKeys, [$inicioDatetime, $fimDatetime]));
    $remessasConcluidas = (int)$sqlReprovadoCount->fetchColumn();
}

// -------------------------
// ROTAS: CSV / XLSX / PRINT
// -------------------------

// ------------------------- CSV (A) — UM ÚNICO ARQUIVO COM SEÇÕES
// -------------------------
if ($tipo === 'csv') {
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=relatorio_b2b.csv");
    // força BOM para Excel
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');

    // Cabeçalho
    fputcsv($out, ["Relatório B2B - Filiais"]);
    fputcsv($out, ["Período:", $inicioFiltro . " até " . $fimFiltro]);
    fputcsv($out, []);

    // Seção 1 - Resumo
    fputcsv($out, ["--- Resumo do Período ---"]);
    fputcsv($out, ["Métrica","Valor","Variação (%)","Obs"]);
    foreach ($resumo as $metric => $vals) {
        $valor = $vals[0];
        $var = $vals[1];
        $obs = match ($metric) {
            "Pedidos B2B" => "Somente solicitações feitas por filiais",
            "Itens Solicitados" => "Total somado dos itens solicitados",
            "Faturamento Estimado" => "Subtotal total",
            "Ticket Médio" => "Faturamento / número de pedidos",
            default => ""
        };
        // resumo: manter números brutos em campos numéricos; variação com ponto decimal (CSV)
        $valorOut = is_numeric($valor) ? (string)$valor : $valor;
        $varOut = is_numeric($var) ? number_format($var,1,'.','') : $var;
        fputcsv($out, [$metric, $valorOut, $varOut, $obs]);
    }
    fputcsv($out, []);

    // Seção 2 - Filiais
    fputcsv($out, ["--- Vendas / Pedidos por Filial ---"]);
    fputcsv($out, ["Filial","Pedidos","Itens","Faturamento","Ticket Médio","% do Total"]);
    foreach ($listaFiliaisExport as $row) {
        fputcsv($out, [
            $row['nome'],
            $row['pedidos'],
            $row['itens'],
            // CSV: manter ponto como separador decimal para compatibilidade com Excel (PT-BR pode usar ; separator; but we keep standard)
            number_format($row['faturamento'],2,'.',''),
            number_format($row['ticket'],2,'.',''),
            number_format($row['perc'],1,'.','')
        ]);
    }
    fputcsv($out, []);

    // Seção 3 - Produtos
    fputcsv($out, ["--- Produtos Mais Solicitados ---"]);
    fputcsv($out, ["SKU","Produto","Quantidade","Pedidos","Participação (%)"]);
    if (!empty($produtosLista)) {
        foreach ($produtosLista as $p) {
            fputcsv($out, [
                $p['codigo_produto'],
                $p['nome_produto'],
                (int)$p['total_quantidade'],
                (int)$p['total_pedidos'],
                number_format($p['perc'],1,'.','')
            ]);
        }
    } else {
        fputcsv($out, ["Nenhum produto solicitado por filiais no período."]);
    }
    fputcsv($out, []);

    // Seção 4 - Pagamentos x Entregas
    fputcsv($out, ["--- Pagamentos x Entregas (Resumo) ---"]);
    fputcsv($out, ["Métrica","Quantidade","Valor (R$)","Status"]);
    fputcsv($out, ["Pagamentos Solicitados",$pendQtd, number_format($pendValor,2,'.',''), "Pendente"]);
    fputcsv($out, ["Remessa Concluida",$aprovQtd, number_format($aprovValor,2,'.',''), "Aprovado"]);
    fputcsv($out, ["Remessa Reprovada",$reprovQtd, number_format($reprovValor,2,'.',''), "Reprovado"]);

    fclose($out);
    exit;
}

// ------------------------- XLSX (B) — TUDO EM UMA ABA
// -------------------------
if ($tipo === 'xlsx') {
    // se PhpSpreadsheet não disponível, respondemos com CSV alternativo
    if (!$hasPhpSpreadsheet) {
        // fallback: envia CSV com aviso
        header("Content-Type: text/csv; charset=utf-8");
        header("Content-Disposition: attachment; filename=relatorio_b2b_fallback.csv");
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output','w');
        fputcsv($out, ["Atenção: PhpSpreadsheet não encontrado. Gerando CSV como fallback."]);
        fputcsv($out, []);
        // reutiliza rotina csv: resumo breve + filiais
        fputcsv($out, ["Período:", $inicioFiltro . " até " . $fimFiltro]);
        fputcsv($out, []);
        fputcsv($out, ["-- Resumo --"]);
        foreach ($resumo as $k => $v) fputcsv($out, [$k, $v[0], number_format($v[1],1,'.','')]);
        fputcsv($out, []);
        fputcsv($out, ["-- Filiais --"]);
        fputcsv($out, ["Filial","Pedidos","Itens","Faturamento","Ticket Médio","%"]);
        foreach ($listaFiliaisExport as $l) fputcsv($out, [$l['nome'],$l['pedidos'],$l['itens'], number_format($l['faturamento'],2,'.',''), number_format($l['ticket'],2,'.',''), number_format($l['perc'],1,'.','')]);
        fclose($out);
        exit;
    }

    // monta spreadsheet (tudo em uma aba)
    $spread = new Spreadsheet();
    $sheet = $spread->getActiveSheet();
    $sheet->setTitle("Relatório B2B");

    $row = 1;
    // Título
    $sheet->setCellValue("A{$row}", "Relatório B2B - Filiais");
    $sheet->mergeCells("A{$row}:F{$row}");
    $row += 2;

    // Período
    $sheet->setCellValue("A{$row}", "Período:");
    $sheet->setCellValue("B{$row}", $inicioFiltro . " até " . $fimFiltro);
    $row += 2;

    // Seção Resumo
    $sheet->setCellValue("A{$row}", "Resumo do Período");
    $row++;
    $sheet->fromArray(["Métrica","Valor","Variação (%)","Obs"], NULL, "A{$row}");
    $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
    $row++;
    foreach ($resumo as $metric => $vals) {
        $obs = match ($metric) {
            "Pedidos B2B" => "Somente solicitações feitas por filiais",
            "Itens Solicitados" => "Total somado dos itens solicitados",
            "Faturamento Estimado" => "Subtotal total",
            "Ticket Médio" => "Faturamento / número de pedidos",
            default => ""
        };
        // garantir que valores numéricos sejam escritos como números
        $sheet->setCellValue("A{$row}", $metric);
        $sheet->setCellValue("B{$row}", is_numeric($vals[0]) ? (float)$vals[0] : $vals[0]);
        $sheet->setCellValue("C{$row}", is_numeric($vals[1]) ? (float)$vals[1] : $vals[1]);
        $sheet->setCellValue("D{$row}", $obs);
        $row++;
    }
    $row++;

    // Seção Filiais
    $sheet->setCellValue("A{$row}", "Vendas / Pedidos por Filial");
    $row++;
    $sheet->fromArray(["Filial","Pedidos","Itens","Faturamento","Ticket Médio","% do Total"], NULL, "A{$row}");
    $sheet->getStyle("A{$row}:F{$row}")->getFont()->setBold(true);
    $row++;
    foreach ($listaFiliaisExport as $l) {
        $sheet->setCellValue("A{$row}", $l['nome']);
        $sheet->setCellValue("B{$row}", (int)$l['pedidos']);
        $sheet->setCellValue("C{$row}", (int)$l['itens']);
        $sheet->setCellValue("D{$row}", (float)$l['faturamento']);
        $sheet->setCellValue("E{$row}", (float)$l['ticket']);
        $sheet->setCellValue("F{$row}", (float)$l['perc']);
        $row++;
    }
    $row++;

    // Seção Produtos
    $sheet->setCellValue("A{$row}", "Produtos Mais Solicitados");
    $row++;
    $sheet->fromArray(["SKU","Produto","Quantidade","Pedidos","Participação (%)"], NULL, "A{$row}");
    $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
    $row++;
    if (!empty($produtosLista)) {
        foreach ($produtosLista as $p) {
            $sheet->setCellValue("A{$row}", $p['codigo_produto']);
            $sheet->setCellValue("B{$row}", $p['nome_produto']);
            $sheet->setCellValue("C{$row}", (int)$p['total_quantidade']);
            $sheet->setCellValue("D{$row}", (int)$p['total_pedidos']);
            $sheet->setCellValue("E{$row}", (float)$p['perc']);
            $row++;
        }
    } else {
        $sheet->setCellValue("A{$row}", "Nenhum produto solicitado por filiais no período.");
        $row++;
    }
    $row++;

    // Seção Pagamentos
    $sheet->setCellValue("A{$row}", "Pagamentos x Entregas (Resumo)");
    $row++;
    $sheet->fromArray(["Métrica","Quantidade","Valor (R$)","Status"], NULL, "A{$row}");
    $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
    $row++;
    $sheet->setCellValue("A{$row}", "Pagamentos Solicitados"); $sheet->setCellValue("B{$row}", (int)$pendQtd); $sheet->setCellValue("C{$row}", (float)$pendValor); $sheet->setCellValue("D{$row}", "Pendente"); $row++;
    $sheet->setCellValue("A{$row}", "Remessa Concluida"); $sheet->setCellValue("B{$row}", (int)$aprovQtd); $sheet->setCellValue("C{$row}", (float)$aprovValor); $sheet->setCellValue("D{$row}", "Aprovado"); $row++;
    $sheet->setCellValue("A{$row}", "Remessa Reprovada"); $sheet->setCellValue("B{$row}", (int)$reprovQtd); $sheet->setCellValue("C{$row}", (float)$reprovValor); $sheet->setCellValue("D{$row}", "Reprovado"); $row++;

    // auto width
    foreach (range('A','F') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // output
    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    header("Content-Disposition: attachment; filename=relatorio_b2b.xlsx");
    $writer = new Xlsx($spread);
    $writer->save("php://output");
    exit;
}

// ------------------------- PRINT / PDF (Impressão) — Estilo Empresarial (B)
// -------------------------
if ($tipo === 'print') {
    // Monta html de impressão com cabeçalho empresarial
    $titulo = "Relatório B2B - Filiais";
    $periodoTexto = date("d/m/Y", strtotime($inicioFiltro)) . " a " . date("d/m/Y", strtotime($fimFiltro));
    // logoPath (se informado) será usado; cuidado com permissões para exibir na janela aberta

    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="utf-8">
        <title><?= htmlspecialchars($titulo) ?></title>
        <style>
            body{ font-family: "Arial",sans-serif; margin:20mm; color:#222; }
            header { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
            header .logo { max-height:60px; }
            header .empresa { text-align:right; font-size:14px; }
            h1 { font-size:18px; margin:0; }
            .meta { font-size:12px; color:#555; }
            .section-title { background:#f3f3f3; padding:8px; font-weight:700; margin-top:18px; margin-bottom:6px; border:1px solid #e0e0e0; }
            table { width:100%; border-collapse:collapse; margin-bottom:12px; font-size:12px; }
            th, td { border:1px solid #cfcfcf; padding:6px 8px; text-align:left; }
            th { background:#efefef; font-weight:700; }
            footer { position:fixed; bottom:10mm; left:0; right:0; text-align:center; font-size:11px; color:#666; }
            .small { font-size:11px; color:#666; }
            @page { margin: 20mm; }
            @media print {
                footer { position: fixed; bottom: 10mm; }
                .no-print { display:none; }
            }
        </style>
    </head>
    <body>
        <header>
            <div class="brand">
                <?php if ($logoPath && file_exists($_SERVER['DOCUMENT_ROOT'] . $logoPath)): ?>
                    <img src="<?= htmlspecialchars($logoPath) ?>" class="logo" alt="logo">
                <?php else: ?>
                    <div style="font-weight:700; font-size:16px;">Sua Empresa</div>
                <?php endif; ?>
                <div class="small">Relatório gerado automaticamente</div>
            </div>

            <div class="empresa">
                <h1><?= htmlspecialchars($titulo) ?></h1>
                <div class="meta"><?= htmlspecialchars($periodoTexto) ?></div>
            </div>
        </header>

        <!-- SEÇÃO 1: RESUMO -->
        <div class="section-title">Resumo do Período</div>
        <table>
            <thead>
                <tr><th>Métrica</th><th>Valor</th><th>Variação</th><th>Obs</th></tr>
            </thead>
            <tbody>
                <?php foreach ($resumo as $metric => $vals): ?>
                    <tr>
                        <td><?= htmlspecialchars($metric) ?></td>
                        <td style="text-align:right;">
                            <?php
                                $v = $vals[0];
                                if (is_numeric($v) && (float)$v == (int)$v) echo number_format((int)$v,0,',','.');
                                else if (is_numeric($v)) echo number_format($v,2,',','.');
                                else echo htmlspecialchars($v);
                            ?>
                        </td>
                        <td style="text-align:right;"><?= number_format($vals[1],1,',','.') ?>%</td>
                        <td>
                            <?php
                            echo match ($metric) {
                                "Pedidos B2B" => "Somente solicitações feitas por filiais",
                                "Itens Solicitados" => "Total somado dos itens solicitados",
                                "Faturamento Estimado" => "Subtotal total",
                                "Ticket Médio" => "Faturamento / número de pedidos",
                                default => ""
                            };
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- SEÇÃO 2: FILIAIS -->
        <div class="section-title">Vendas / Pedidos por Filial</div>
        <table>
            <thead>
                <tr>
                    <th>Filial</th><th>Pedidos</th><th>Itens</th><th>Faturamento (R$)</th><th>Ticket Médio (R$)</th><th>% do Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($listaFiliaisExport as $l): ?>
                    <tr>
                        <td><?= htmlspecialchars($l['nome']) ?></td>
                        <td style="text-align:right;"><?= (int)$l['pedidos'] ?></td>
                        <td style="text-align:right;"><?= (int)$l['itens'] ?></td>
                        <td style="text-align:right;">R$ <?= number_format($l['faturamento'],2,',','.') ?></td>
                        <td style="text-align:right;">R$ <?= number_format($l['ticket'],2,',','.') ?></td>
                        <td style="text-align:center;"><?= number_format($l['perc'],1,',','.') ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- SEÇÃO 3: PRODUTOS -->
        <div class="section-title">Produtos Mais Solicitados</div>
        <table>
            <thead>
                <tr><th>SKU</th><th>Produto</th><th>Quantidade</th><th>Pedidos</th><th>Participação</th></tr>
            </thead>
            <tbody>
                <?php if (!empty($produtosLista)): ?>
                    <?php foreach ($produtosLista as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['codigo_produto']) ?></td>
                            <td><?= htmlspecialchars($p['nome_produto']) ?></td>
                            <td style="text-align:right;"><?= (int)$p['total_quantidade'] ?></td>
                            <td style="text-align:right;"><?= (int)$p['total_pedidos'] ?></td>
                            <td style="text-align:center;"><?= number_format($p['perc'],1,',','.') ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center;">Nenhum produto solicitado por filiais no período.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- SEÇÃO 4: PAGAMENTOS -->
        <div class="section-title">Pagamentos x Entregas (Resumo)</div>
        <table>
            <thead><tr><th>Métrica</th><th>Quantidade</th><th>Valor (R$)</th><th>Status</th></tr></thead>
            <tbody>
                <tr><td>Pagamentos Solicitados</td><td style="text-align:right;"><?= $pendQtd ?></td><td style="text-align:right;">R$ <?= number_format($pendValor,2,',','.') ?></td><td>Pendente</td></tr>
                <tr><td>Remessa Concluida</td><td style="text-align:right;"><?= $aprovQtd ?></td><td style="text-align:right;">R$ <?= number_format($aprovValor,2,',','.') ?></td><td>Aprovado</td></tr>
                <tr><td>Remessa Reprovada</td><td style="text-align:right;"><?= $reprovQtd ?></td><td style="text-align:right;">R$ <?= number_format($reprovValor,2,',','.') ?></td><td>Reprovado</td></tr>
            </tbody>
        </table>

        <footer>
            <?= htmlspecialchars(date('d/m/Y H:i')) ?> — Relatório gerado automaticamente
        </footer>

        <script>
            // auto print, e ao confirmar OU cancelar: volta para a aba que abriu e fecha
            window.onload = function() {
                setTimeout(() => window.print(), 300);
            };
            window.onafterprint = function() {
                try {
                    if (window.opener && !window.opener.closed) {
                        // recarrega a página principal para garantir estado + filtros
                        window.opener.location.reload();
                        window.opener.focus();
                    }
                } catch (e) {
                    // ignore cross-origin issues
                }
                // fecha a aba de impressão
                window.close();
            };
        </script>

    </body>
    </html>
    <?php
    exit;
}

// caso nenhum tipo bateu, retorna 400
http_response_code(400);
echo "Parâmetro 'tipo' inválido. Use tipo=print|csv|xlsx";
exit;

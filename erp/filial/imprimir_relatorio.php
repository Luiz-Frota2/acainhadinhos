<?php
session_start();
require_once "../../assets/php/conexao.php";
require "../../vendor/autoload.php";
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

ini_set('display_errors', 1);
error_reporting(E_ALL);

// ================================
// CAPTURAR TIPO (print / csv / xlsx)
// ================================
$tipo = $_GET['tipo'] ?? 'print';

// ================================
// CAPTURAR FILTROS
// ================================
$filialFiltroId = $_GET['status'] ?? '';
$inicioFiltro   = $_GET['codigo'] ?? '';
$fimFiltro      = $_GET['categoria'] ?? '';

if ($inicioFiltro === "") $inicioFiltro = date("Y-m-01");
if ($fimFiltro === "")    $fimFiltro    = date("Y-m-t");

$inicioDatetime = $inicioFiltro . " 00:00:00";
$fimDatetime    = $fimFiltro    . " 23:59:59";

// ================================
// CARREGAR FILIAIS
// ================================
if ($filialFiltroId !== "") {
    $stmt = $pdo->prepare("SELECT id, nome FROM unidades WHERE tipo='Filial' AND id = ?");
    $stmt->execute([$filialFiltroId]);
} else {
    $stmt = $pdo->query("SELECT id, nome FROM unidades WHERE tipo='Filial'");
}
$filiais = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================
// COLETAR DADOS
// ================================
$lista = [];

foreach($filiais as $f){
    $empresaKey = "unidade_" . $f['id'];

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS pedidos, SUM(valor_total) AS fat
        FROM vendas
        WHERE empresa_id = ?
        AND data_venda BETWEEN ? AND ?
    ");
    $stmt->execute([$empresaKey,$inicioDatetime,$fimDatetime]);
    $v = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt2 = $pdo->prepare("
        SELECT SUM(iv.quantidade) AS itens
        FROM itens_venda iv
        JOIN vendas v ON v.id = iv.venda_id
        WHERE v.empresa_id=?
        AND v.data_venda BETWEEN ? AND ?
    ");
    $stmt2->execute([$empresaKey,$inicioDatetime,$fimDatetime]);
    $it = $stmt2->fetch(PDO::FETCH_ASSOC);

    $ped = $v['pedidos'] ?? 0;
    $fat = $v['fat'] ?? 0;
    $itens = $it['itens'] ?? 0;

    $lista[] = [
        "nome"        => $f["nome"],
        "pedidos"     => $ped,
        "itens"       => $itens,
        "faturamento" => $fat,
        "ticket"      => $ped > 0 ? $fat / $ped : 0
    ];
}

// ==================================================================
// =========================== CSV =================================
// ==================================================================
if ($tipo === "csv") {

    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=relatorio_b2b.csv");

    $output = fopen("php://output", "w");

    fputcsv($output, ["Relatório B2B"]);
    fputcsv($output, ["Período:", $inicioFiltro . " até " . $fimFiltro]);
    fputcsv($output, []);
    fputcsv($output, ["Filial","Pedidos","Itens","Faturamento","Ticket Médio"]);

    foreach($lista as $l){
        fputcsv($output, [
            $l["nome"],
            $l["pedidos"],
            $l["itens"],
            number_format($l["faturamento"],2,'.',''),
            number_format($l["ticket"],2,'.','')
        ]);
    }

    fclose($output);
    exit;
}

// ==================================================================
// =========================== XLSX ================================
// ==================================================================
if ($tipo === "xlsx") {

    $spread = new Spreadsheet();
    $sheet  = $spread->getActiveSheet();
    $sheet->setTitle("Relatório B2B");

    $sheet->setCellValue("A1", "Relatório B2B - Filiais");
    $sheet->mergeCells("A1:E1");

    $sheet->setCellValue("A2", "Período:");
    $sheet->setCellValue("B2", $inicioFiltro . " até " . $fimFiltro);

    $sheet->setCellValue("A4","Filial");
    $sheet->setCellValue("B4","Pedidos");
    $sheet->setCellValue("C4","Itens");
    $sheet->setCellValue("D4","Faturamento");
    $sheet->setCellValue("E4","Ticket Médio");

    $sheet->getStyle("A4:E4")->getFont()->setBold(true);

    $linha = 5;
    foreach($lista as $l){
        $sheet->fromArray([
            $l["nome"],
            $l["pedidos"],
            $l["itens"],
            $l["faturamento"],
            $l["ticket"]
        ], NULL, "A{$linha}");
        $linha++;
    }

    foreach (range('A','E') as $col)
        $sheet->getColumnDimension($col)->setAutoSize(true);

    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    header("Content-Disposition: attachment; filename=relatorio_b2b.xlsx");

    $writer = new Xlsx($spread);
    $writer->save("php://output");
    exit;
}

// ==================================================================
// =========================== IMPRESSÃO ============================
// ==================================================================
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Relatório B2B - Impressão</title>

<style>
body{
    font-family: Arial;
    margin: 25px;
}
.table{
    width:100%;
    border-collapse: collapse;
}
.table th,.table td{
    border:1px solid #777;
    padding:6px;
    font-size:13px;
}
.table th{
    background:#efefef;
}
</style>

</head>
<body>

<h1 style="text-align:center;">Relatório B2B - Filiais</h1>
<div style="text-align:center; margin-bottom:20px;">
    Período: <?= date("d/m/Y", strtotime($inicioFiltro)) ?> 
    até <?= date("d/m/Y", strtotime($fimFiltro)) ?><br>
</div>

<table class="table">
<tr>
<th>Filial</th>
<th>Pedidos</th>
<th>Itens</th>
<th>Faturamento</th>
<th>Ticket</th>
</tr>
<?php foreach($lista as $l): ?>
<tr>
<td><?= $l["nome"] ?></td>
<td><?= $l["pedidos"] ?></td>
<td><?= $l["itens"] ?></td>
<td>R$ <?= number_format($l["faturamento"],2,',','.') ?></td>
<td>R$ <?= number_format($l["ticket"],2,',','.') ?></td>
</tr>
<?php endforeach; ?>
</table>

<script>
window.onload = function() {
    setTimeout(() => window.print(), 300);
    window.onafterprint = function(){
        if (window.opener && !window.opener.closed) {
            window.opener.location.reload();
            window.opener.focus();
        }
        window.close();
    };
};
</script>

</body>
</html>

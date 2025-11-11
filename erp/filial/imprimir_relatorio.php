<?php
session_start();
require_once "../conexao.php"; // ajuste o caminho
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Captura todos os filtros
$filialFiltroId = $_GET['status'] ?? '';
$inicioFiltro = $_GET['codigo'] ?? '';
$fimFiltro = $_GET['categoria'] ?? '';
$idSelecionado = $_GET['id'] ?? '';

// Datas padrão
if ($inicioFiltro === "") $inicioFiltro = date("Y-m-01");
if ($fimFiltro === "")    $fimFiltro    = date("Y-m-t");

$inicioDatetime = $inicioFiltro . " 00:00:00";
$fimDatetime    = $fimFiltro    . " 23:59:59";

// ========================
// CARREGA DADOS NOVAMENTE
// ========================
function filialKeysFromIds(array $ids): array {
    return array_map(fn($i) => "unidade_" . $i, $ids);
}

$filiais = [];
try {
    if ($filialFiltroId !== '') {
        $stmt = $pdo->prepare("SELECT id, nome FROM unidades WHERE tipo='Filial' AND id = ?");
        $stmt->execute([$filialFiltroId]);
    } else {
        $stmt = $pdo->query("SELECT id, nome FROM unidades WHERE tipo='Filial'");
    }
    $filiais = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(Exception $e){
    $filiais = [];
}

$filialKeys = filialKeysFromIds(array_column($filiais,'id'));

// === Função cálculo período ===
function calcularPeriodo(PDO $pdo, $ini, $fim, $filialKeys){
    if (empty($filialKeys)) return ["pedidos"=>0,"itens"=>0,"faturamento"=>0,"ticket"=>0];

    $in = implode(",", array_fill(0,count($filialKeys),"?"));

    $sql = "SELECT id FROM solicitacoes_b2b
            WHERE id_solicitante IN ($in)
            AND created_at BETWEEN ? AND ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($filialKeys,[$ini,$fim]));
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!$ids) return ["pedidos"=>0,"itens"=>0,"faturamento"=>0,"ticket"=>0];

    $in2 = implode(",", array_fill(0,count($ids),"?"));
    $sql2 = "SELECT SUM(quantidade) AS itens, SUM(subtotal) AS fat
             FROM solicitacoes_b2b_itens
             WHERE solicitacao_id IN ($in2)";

    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute($ids);
    $d = $stmt2->fetch(PDO::FETCH_ASSOC);

    $totalPedidos = count($ids);
    $totalItens = $d['itens'] ?? 0;
    $fat = $d['fat'] ?? 0;
    
    return [
        "pedidos"=>$totalPedidos,
        "itens"=>$totalItens,
        "faturamento"=>$fat,
        "ticket"=>$totalPedidos>0 ? $fat/$totalPedidos : 0
    ];
}

$inicioAnt = date("Y-m-d", strtotime($inicioFiltro . " -30 days")) . " 00:00:00";
$fimAnt    = date("Y-m-d", strtotime($fimFiltro    . " -30 days")) . " 23:59:59";

$atual = calcularPeriodo($pdo, $inicioDatetime, $fimDatetime, $filialKeys);
$antes = calcularPeriodo($pdo, $inicioAnt, $fimAnt, $filialKeys);

function variacao($a,$b){ return $b<=0?0:(($a-$b)/$b)*100; }

// =============================
// COLETA TABELA FILIAIS
// =============================
$lista = [];
$totalFatur = 0;

foreach($filiais as $f){
    $empresaKey = "unidade_" . $f['id'];

    // vendas
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS pedidos, SUM(valor_total) AS fat
        FROM vendas
        WHERE empresa_id = ?
        AND data_venda BETWEEN ? AND ?
    ");
    $stmt->execute([$empresaKey,$inicioDatetime,$fimDatetime]);
    $v = $stmt->fetch(PDO::FETCH_ASSOC);

    // itens
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
        "nome"=>$f["nome"],
        "pedidos"=>$ped,
        "itens"=>$itens,
        "faturamento"=>$fat,
        "ticket"=>$ped>0?$fat/$ped:0
    ];

    $totalFatur += $fat;
}

foreach($lista as $i=>$l){
    $lista[$i]['perc'] = $totalFatur>0?($l['faturamento']/$totalFatur)*100:0;
}

// =======================================================
// ESTILO ESPECÍFICO PARA IMPRESSÃO
// =======================================================
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Relatório B2B - Impressão</title>

<style>
body{
    font-family: Arial, sans-serif;
    margin: 25px;
}
h1,h2,h3{
    margin: 0 0 10px 0;
    padding: 0;
}
.table{
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 25px;
}
.table th,.table td{
    border: 1px solid #777;
    padding: 6px;
    font-size: 13px;
}
.table th{
    background: #efefef;
}
.header{
    text-align:center;
    margin-bottom:20px;
}
.small{
    font-size:13px;
    color:#555;
}
.section-title{
    background:#ddd;
    padding:8px;
    font-weight:bold;
    margin-top:25px;
}
</style>

</head>
<body>

<div class="header">
    <h1>Relatório B2B - Filiais</h1>
    <div class="small">
        Período: <?= date("d/m/Y", strtotime($inicioFiltro)) ?> 
        a <?= date("d/m/Y", strtotime($fimFiltro)) ?><br>
        Filial: <?= $filialFiltroId ? $filiais[0]['nome'] : "Todas" ?>
    </div>
</div>

<!-- =========================== -->
<!-- RESUMO DO PERÍODO -->
<!-- =========================== -->
<div class="section-title">Resumo do Período</div>
<table class="table">
<tr><th>Métrica</th><th>Valor</th><th>Variação</th><th>Obs</th></tr>
<tr>
<td>Pedidos</td>
<td><?= $atual["pedidos"] ?></td>
<td><?= number_format(variacao($atual["pedidos"],$antes["pedidos"]),1,',','.') ?>%</td>
<td>Solicitações B2B</td>
</tr>
<tr>
<td>Itens</td>
<td><?= $atual["itens"] ?></td>
<td><?= number_format(variacao($atual["itens"],$antes["itens"]),1,',','.') ?>%</td>
<td>Total de itens</td>
</tr>
<tr>
<td>Faturamento</td>
<td>R$ <?= number_format($atual["faturamento"],2,',','.') ?></td>
<td><?= number_format(variacao($atual["faturamento"],$antes["faturamento"]),1,',','.') ?>%</td>
<td>Subtotal geral</td>
</tr>
<tr>
<td>Ticket Médio</td>
<td>R$ <?= number_format($atual["ticket"],2,',','.') ?></td>
<td><?= number_format(variacao($atual["ticket"],$antes["ticket"]),1,',','.') ?>%</td>
<td>Faturamento ÷ pedidos</td>
</tr>
</table>

<!-- =========================== -->
<!-- TABELA FILIAIS -->
<!-- =========================== -->
<div class="section-title">Vendas / Pedidos por Filial</div>
<table class="table">
<tr>
<th>Filial</th><th>Pedidos</th><th>Itens</th><th>Faturamento</th><th>Ticket</th><th>% Total</th>
</tr>
<?php foreach($lista as $l): ?>
<tr>
<td><strong><?= $l["nome"] ?></strong></td>
<td><?= $l["pedidos"] ?></td>
<td><?= $l["itens"] ?></td>
<td>R$ <?= number_format($l["faturamento"],2,',','.') ?></td>
<td>R$ <?= number_format($l["ticket"],2,',','.') ?></td>
<td><?= number_format($l["perc"],1,',','.') ?>%</td>
</tr>
<?php endforeach; ?>
</table>

<script>
window.print();
</script>

</body>
</html>

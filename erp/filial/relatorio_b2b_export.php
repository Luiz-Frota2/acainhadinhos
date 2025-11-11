<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ---------------------------------------------------------------
//  CONEXÃO COM O BANCO
// ---------------------------------------------------------------
require_once "../../assets/php/conexao.php";

// ---------------------------------------------------------------
//  TENTA CARREGAR PhpSpreadsheet (SE EXISTIR)
// ---------------------------------------------------------------
$hasPhpSpreadsheet = false;
try {
    $vendorPath = __DIR__ . "/../../vendor/autoload.php";
    if (file_exists($vendorPath)) {
        require_once $vendorPath;
        if (class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            $hasPhpSpreadsheet = true;
        }
    }
} catch (Throwable $e) {
    $hasPhpSpreadsheet = false;
}

if ($hasPhpSpreadsheet) {
    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
}

// ---------------------------------------------------------------
//  CAPTURA DE PARÂMETROS
// ---------------------------------------------------------------
$tipo = $_GET['tipo'] ?? 'print';   // print | csv | xlsx

$filialFiltroId = isset($_GET['status']) && $_GET['status'] !== ''
    ? intval($_GET['status'])
    : null;

$inicioFiltro = !empty($_GET['codigo']) ? trim($_GET['codigo']) : "";
$fimFiltro    = !empty($_GET['categoria']) ? trim($_GET['categoria']) : "";
$idSelecionado = $_GET['id'] ?? "";

// ---------------------------------------------------------------
//  DATAS PADRÃO
// ---------------------------------------------------------------
if ($inicioFiltro === "") $inicioFiltro = date("Y-m-01");
if ($fimFiltro === "")    $fimFiltro    = date("Y-m-t");

$inicioDatetime = $inicioFiltro . " 00:00:00";
$fimDatetime    = $fimFiltro    . " 23:59:59";

// ---------------------------------------------------------------
//  LOGO OPCIONAL P/ IMPRESSÃO
// ---------------------------------------------------------------
$logoPath = $_GET['logo'] ?? ""; // exemplo: /assets/img/logo.png

// ---------------------------------------------------------------
//  FUNÇÕES DE APOIO
// ---------------------------------------------------------------
function placeholders(array $arr): string {
    if (empty($arr)) return "";
    return implode(",", array_fill(0, count($arr), "?"));
}

function filialKeysFromIds(array $ids): array {
    return array_map(fn($id) => "unidade_" . $id, $ids);
}

// ---------------------------------------------------------------
//  CARREGAR FILIAIS (com ou sem filtro)
// ---------------------------------------------------------------
try {
    if ($filialFiltroId !== null) {
        $stmt = $pdo->prepare("
            SELECT id, nome
            FROM unidades
            WHERE tipo = 'Filial'
              AND id = ?
            ORDER BY nome
        ");
        $stmt->execute([$filialFiltroId]);
    } else {
        $stmt = $pdo->query("
            SELECT id, nome
            FROM unidades
            WHERE tipo = 'Filial'
            ORDER BY nome
        ");
    }

    $filiais = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $filiais = [];
}

$filiaisIds = array_column($filiais, 'id');
$filialKeys = !empty($filiaisIds) ? filialKeysFromIds($filiaisIds) : [];

// ---------------------------------------------------------------
//  FUNÇÃO PARA CALCULAR DADOS DO PERÍODO
// ---------------------------------------------------------------
function calcularPeriodo(PDO $pdo, string $inicioDT, string $fimDT, array $filialKeys): array {

    if (empty($filialKeys)) {
        return [
            "pedidos" => 0,
            "itens" => 0,
            "faturamento" => 0,
            "ticket" => 0
        ];
    }

    $inFiliais = placeholders($filialKeys);

    // Busca pedidos do período
    $sql = "
        SELECT id
        FROM solicitacoes_b2b
        WHERE id_solicitante IN ($inFiliais)
          AND created_at BETWEEN ? AND ?
    ";

    $params = array_merge($filialKeys, [$inicioDT, $fimDT]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $idsPedidos = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($idsPedidos)) {
        return [
            "pedidos" => 0,
            "itens" => 0,
            "faturamento" => 0,
            "ticket" => 0
        ];
    }

    $inPedidos = placeholders($idsPedidos);

    $sql2 = "
        SELECT
            SUM(quantidade) AS totalItens,
            SUM(subtotal)   AS totalFaturamento
        FROM solicitacoes_b2b_itens
        WHERE solicitacao_id IN ($inPedidos)
    ";

    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute($idsPedidos);

    $dados = $stmt2->fetch(PDO::FETCH_ASSOC);

    $totalItens = (int)($dados["totalItens"] ?? 0);
    $totalFaturamento = (float)($dados["totalFaturamento"] ?? 0.0);
    $totalPedidos = count($idsPedidos);

    $ticket = $totalPedidos > 0 ? ($totalFaturamento / $totalPedidos) : 0;

    return [
        "pedidos" => $totalPedidos,
        "itens" => $totalItens,
        "faturamento" => $totalFaturamento,
        "ticket" => $ticket
    ];
}

// ====================================================================
//  PARTE 2 — CÁLCULO DE PERÍODOS, RESUMO, FILIAIS, PRODUTOS, PAGAMENTOS
// ====================================================================

// ---------------------------------------------------------------
//  PERÍODO ANTERIOR (últimos 30 dias)
// ---------------------------------------------------------------
$inicioAnterior = date("Y-m-d", strtotime($inicioFiltro . " -30 days"));
$fimAnterior    = date("Y-m-d", strtotime($fimFiltro . " -30 days"));

// Cálculo dos períodos
$atual    = calcularPeriodo($pdo, $inicioDatetime, $fimDatetime, $filialKeys);
$anterior = calcularPeriodo(
    $pdo,
    $inicioAnterior . " 00:00:00",
    $fimAnterior    . " 23:59:59",
    $filialKeys
);

// ---------------------------------------------------------------
//  FUNÇÃO DE VARIAÇÃO
// ---------------------------------------------------------------
function variacao(float $atual, float $anterior): float {
    if ($anterior <= 0) return 0;
    return (($atual - $anterior) / $anterior) * 100;
}

// ---------------------------------------------------------------
//  MONTAR ARRAY DO RESUMO
// ---------------------------------------------------------------
$resumo = [
    "Pedidos B2B"         => [$atual["pedidos"],      variacao($atual["pedidos"],      $anterior["pedidos"])],
    "Itens Solicitados"   => [$atual["itens"],        variacao($atual["itens"],        $anterior["itens"])],
    "Faturamento Estimado"=> [$atual["faturamento"],  variacao($atual["faturamento"],  $anterior["faturamento"])],
    "Ticket Médio"        => [$atual["ticket"],       variacao($atual["ticket"],       $anterior["ticket"])]
];

// ====================================================================
//  MONTAR LISTA COMPLETA DE FILIAIS (EXPORTAÇÃO SEM PAGINAÇÃO)
// ====================================================================
$listaFiliaisExport = [];
$totalFaturamentoGeral = 0;

foreach ($filiais as $f) {

    $empresaKey = "unidade_" . $f["id"];
    $nomeFilial = $f["nome"];

    // -----------------------------------------------------------
    //  VENDAS
    // -----------------------------------------------------------
    $sqlV = $pdo->prepare("
        SELECT 
            COUNT(*) AS pedidos,
            SUM(valor_total) AS total_faturamento
        FROM vendas
        WHERE empresa_id = ?
          AND data_venda BETWEEN ? AND ?
    ");

    $sqlV->execute([$empresaKey, $inicioDatetime, $fimDatetime]);
    $dados = $sqlV->fetch(PDO::FETCH_ASSOC);

    $pedidos     = (int)($dados["pedidos"] ?? 0);
    $faturamento = (float)($dados["total_faturamento"] ?? 0.0);

    // -----------------------------------------------------------
    //  ITENS DAS VENDAS
    // -----------------------------------------------------------
    $sqlItens = $pdo->prepare("
        SELECT 
            SUM(iv.quantidade) AS total_itens
        FROM itens_venda iv
        INNER JOIN vendas v ON v.id = iv.venda_id
        WHERE v.empresa_id = ?
          AND v.data_venda BETWEEN ? AND ?
    ");

    $sqlItens->execute([$empresaKey, $inicioDatetime, $fimDatetime]);

    $dadosItens = $sqlItens->fetch(PDO::FETCH_ASSOC);

    $itens = (int)($dadosItens["total_itens"] ?? 0);
    $ticket = $pedidos > 0 ? ($faturamento / $pedidos) : 0;

    // -----------------------------------------------------------
    //  ADICIONA À LISTA COMPLETA
    // -----------------------------------------------------------
    $listaFiliaisExport[] = [
        "nome"        => $nomeFilial,
        "pedidos"     => $pedidos,
        "itens"       => $itens,
        "faturamento" => $faturamento,
        "ticket"      => $ticket
    ];

    $totalFaturamentoGeral += $faturamento;
}

// ---------------------------------------------------------------
//  CALCULA PERCENTUAL DE FATURAMENTO DAS FILIAIS
// ---------------------------------------------------------------
foreach ($listaFiliaisExport as $i => $row) {
    $listaFiliaisExport[$i]["perc"] =
        $totalFaturamentoGeral > 0
        ? ($row["faturamento"] / $totalFaturamentoGeral) * 100
        : 0;
}

// ====================================================================
//  PRODUTOS MAIS SOLICITADOS
// ====================================================================
$produtosLista = [];

if (!empty($filialKeys)) {

    $inFiliais = placeholders($filialKeys);

    $sqlSolic = $pdo->prepare("
        SELECT id
        FROM solicitacoes_b2b
        WHERE id_solicitante IN ($inFiliais)
          AND created_at BETWEEN ? AND ?
    ");

    $params = array_merge($filialKeys, [$inicioDatetime, $fimDatetime]);
    $sqlSolic->execute($params);

    $solicitacoesIds = $sqlSolic->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($solicitacoesIds)) {

        $inSolic = placeholders($solicitacoesIds);

        $sqlItens = $pdo->prepare("
            SELECT 
                codigo_produto, 
                nome_produto,
                SUM(quantidade) AS total_quantidade,
                COUNT(DISTINCT solicitacao_id) AS total_pedidos
            FROM solicitacoes_b2b_itens
            WHERE solicitacao_id IN ($inSolic)
            GROUP BY codigo_produto, nome_produto
            ORDER BY total_quantidade DESC
            LIMIT 100
        ");

        $sqlItens->execute($solicitacoesIds);
        $produtosLista = $sqlItens->fetchAll(PDO::FETCH_ASSOC);

        // Percentual
        $totalGeral = array_sum(array_column($produtosLista, "total_quantidade"));

        foreach ($produtosLista as $i => $prod) {
            $produtosLista[$i]["perc"] =
                $totalGeral > 0
                ? ($prod["total_quantidade"] / $totalGeral) * 100
                : 0;
        }
    }
}

// ====================================================================
//  PAGAMENTOS X ENTREGAS
// ====================================================================
$pendQtd = $pendValor = 0;
$aprovQtd = $aprovValor = 0;
$reprovQtd = $reprovValor = 0;

if (!empty($filialKeys)) {

    $inFiliais = placeholders($filialKeys);

    // --------------------------
    //  Pendentes
    // --------------------------
    $sqlPend = $pdo->prepare("
        SELECT COUNT(*) AS qtd, SUM(valor) AS total
        FROM solicitacoes_pagamento
        WHERE id_solicitante IN ($inFiliais)
          AND status = 'pendente'
          AND created_at BETWEEN ? AND ?
    ");

    $sqlPend->execute(array_merge($filialKeys, [$inicioDatetime, $fimDatetime]));
    $r = $sqlPend->fetch(PDO::FETCH_ASSOC);

    $pendQtd   = (int)($r["qtd"] ?? 0);
    $pendValor = (float)($r["total"] ?? 0.0);

    // --------------------------
    //  Aprovados
    // --------------------------
    $sqlAprov = $pdo->prepare("
        SELECT COUNT(*) AS qtd, SUM(valor) AS total
        FROM solicitacoes_pagamento
        WHERE id_solicitante IN ($inFiliais)
          AND status = 'aprovado'
          AND created_at BETWEEN ? AND ?
    ");

    $sqlAprov->execute(array_merge($filialKeys, [$inicioDatetime, $fimDatetime]));
    $r = $sqlAprov->fetch(PDO::FETCH_ASSOC);

    $aprovQtd   = (int)($r["qtd"] ?? 0);
    $aprovValor = (float)($r["total"] ?? 0.0);

    // --------------------------
    //  Reprovados
    // --------------------------
    $sqlReprov = $pdo->prepare("
        SELECT COUNT(*) AS qtd, SUM(valor) AS total
        FROM solicitacoes_pagamento
        WHERE id_solicitante IN ($inFiliais)
          AND status = 'reprovado'
          AND created_at BETWEEN ? AND ?
    ");

    $sqlReprov->execute(array_merge($filialKeys, [$inicioDatetime, $fimDatetime]));
    $r = $sqlReprov->fetch(PDO::FETCH_ASSOC);

    $reprovQtd   = (int)($r["qtd"] ?? 0);
    $reprovValor = (float)($r["total"] ?? 0.0);
}
// ================================================================
//  PARTE 3 — EXPORTAÇÃO CSV
// ================================================================

if ($tipo === 'csv') {

    // ------------------------------------------------------------
    // Cabeçalhos do CSV
    // ------------------------------------------------------------
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=relatorio_b2b.csv");

    // BOM UTF-8 (necessário para acentos no Excel Windows)
    echo "\xEF\xBB\xBF";

    // abre saída
    $out = fopen("php://output", "w");

    // helper para escrever linha usando ';'
    $write = function(array $row) use ($out) {
        fputcsv($out, $row, ";");
    };

    // ------------------------------------------------------------
    //  TÍTULO / PERÍODO
    // ------------------------------------------------------------
    $write(["Relatório B2B - Filiais"]);
    $write([]);
    $write(["Período", "$inicioFiltro até $fimFiltro"]);
    $write([]);

    // ------------------------------------------------------------
    //  RESUMO DO PERÍODO
    // ------------------------------------------------------------
    $write(["==== Resumo do Período ===="]);
    $write(["Métrica", "Valor", "Variação (%)", "Obs"]);

    foreach ($resumo as $metric => $vals) {

        $obs = match ($metric) {
            "Pedidos B2B"         => "Solicitações feitas por filiais",
            "Itens Solicitados"   => "Itens totais solicitados",
            "Faturamento Estimado"=> "Subtotal geral",
            "Ticket Médio"        => "Faturamento / pedidos",
            default               => ""
        };

        $valor     = is_numeric($vals[0]) ? str_replace(".", ",", (string)$vals[0]) : $vals[0];
        $variacao  = number_format($vals[1], 2, ',', '.');

        $write([$metric, $valor, $variacao . "%", $obs]);
    }

    $write([]);

    // ------------------------------------------------------------
    //  FILIAIS
    // ------------------------------------------------------------
    $write(["==== Vendas / Pedidos por Filial ===="]);
    $write(["Filial", "Pedidos", "Itens", "Faturamento", "Ticket Médio", "% do Total"]);

    foreach ($listaFiliaisExport as $l) {
        $write([
            $l['nome'],
            $l['pedidos'],
            $l['itens'],
            number_format($l['faturamento'], 2, ',', '.'),
            number_format($l['ticket'], 2, ',', '.'),
            number_format($l['perc'], 2, ',', '.') . "%"
        ]);
    }

    $write([]);

    // ------------------------------------------------------------
    //  PRODUTOS MAIS SOLICITADOS
    // ------------------------------------------------------------
    $write(["==== Produtos Mais Solicitados ===="]);
    $write(["SKU", "Produto", "Quantidade", "Pedidos", "Participação (%)"]);

    if (!empty($produtosLista)) {

        foreach ($produtosLista as $p) {
            $write([
                $p["codigo_produto"],
                $p["nome_produto"],
                $p["total_quantidade"],
                $p["total_pedidos"],
                number_format($p["perc"], 2, ',', '.') . "%"
            ]);
        }

    } else {
        $write(["Nenhum produto encontrado no período."]);
    }

    $write([]);

    // ------------------------------------------------------------
    //  PAGAMENTOS X ENTREGAS
    // ------------------------------------------------------------
    $write(["==== Pagamentos x Entregas ===="]);
    $write(["Métrica", "Quantidade", "Valor", "Status"]);

    $write(["Pagamentos Solicitados", $pendQtd, number_format($pendValor, 2, ',', '.'), "Pendente"]);
    $write(["Remessa Concluída",     $aprovQtd, number_format($aprovValor, 2, ',', '.'), "Aprovado"]);
    $write(["Remessa Reprovada",     $reprovQtd, number_format($reprovValor, 2, ',', '.'), "Reprovado"]);

    fclose($out);
    exit;
}
// ================================================================
// PARTE 4 — EXPORTAÇÃO XLSX (PhpSpreadsheet quando disponível + fallback)
// ================================================================
if ($tipo === 'xlsx') {

    $filename = "relatorio_b2b.xlsx";

    // ---------------------------
    // 1) SE PhpSpreadsheet ESTIVER DISPONÍVEL — usa ele
    // ---------------------------
    if (!empty($hasPhpSpreadsheet) && $hasPhpSpreadsheet) {
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle("Relatório B2B");

            $rowIndex = 1;

            // helper para escrever uma linha
            $writeRow = function($sheet, $rowIndex, array $data) {
                $colIndex = 1;
                foreach ($data as $v) {
                    $cell = $sheet->getCellByColumnAndRow($colIndex, $rowIndex);
                    if (is_numeric($v)) {
                        // força número
                        $cell->setValueExplicit((float)$v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                    } else {
                        // string
                        $cell->setValueExplicit((string)$v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    }
                    $colIndex++;
                }
            };

            // Cabeçalho e período
            $writeRow($sheet, $rowIndex++, ["Relatório B2B - Filiais"]);
            $rowIndex++;
            $writeRow($sheet, $rowIndex++, ["Período:", "$inicioFiltro até $fimFiltro"]);
            $rowIndex++;

            // Resumo
            $writeRow($sheet, $rowIndex++, ["==== Resumo do Período ===="]);
            $writeRow($sheet, $rowIndex++, ["Métrica", "Valor", "Variação (%)", "Obs"]);
            foreach ($resumo as $metric => $vals) {
                $obs = match ($metric) {
                    "Pedidos B2B" => "Solicitações feitas por filiais",
                    "Itens Solicitados" => "Soma total dos itens",
                    "Faturamento Estimado" => "Subtotal geral",
                    "Ticket Médio" => "Faturamento / pedidos",
                    default => ""
                };
                $writeRow($sheet, $rowIndex++, [$metric, $vals[0], $vals[1], $obs]);
            }

            $rowIndex++;

            // Filiais
            $writeRow($sheet, $rowIndex++, ["==== Vendas / Pedidos por Filial ===="]);
            $writeRow($sheet, $rowIndex++, ["Filial", "Pedidos", "Itens", "Faturamento", "Ticket Médio", "% Total"]);
            foreach ($listaFiliaisExport as $l) {
                $writeRow($sheet, $rowIndex++, [
                    $l['nome'],
                    $l['pedidos'],
                    $l['itens'],
                    $l['faturamento'],
                    $l['ticket'],
                    $l['perc']
                ]);
            }

            $rowIndex++;

            // Produtos
            $writeRow($sheet, $rowIndex++, ["==== Produtos Mais Solicitados ===="]);
            $writeRow($sheet, $rowIndex++, ["SKU", "Produto", "Quantidade", "Pedidos", "Participação (%)"]);
            if (!empty($produtosLista)) {
                foreach ($produtosLista as $p) {
                    $writeRow($sheet, $rowIndex++, [
                        $p['codigo_produto'],
                        $p['nome_produto'],
                        $p['total_quantidade'],
                        $p['total_pedidos'],
                        $p['perc']
                    ]);
                }
            } else {
                $writeRow($sheet, $rowIndex++, ["Nenhum produto encontrado no período."]);
            }

            $rowIndex++;

            // Pagamentos
            $writeRow($sheet, $rowIndex++, ["==== Pagamentos x Entregas ===="]);
            $writeRow($sheet, $rowIndex++, ["Métrica", "Quantidade", "Valor", "Status"]);
            $writeRow($sheet, $rowIndex++, ["Pagamentos Solicitados", $pendQtd, $pendValor, "Pendente"]);
            $writeRow($sheet, $rowIndex++, ["Remessa Concluída",     $aprovQtd, $aprovValor, "Aprovado"]);
            $writeRow($sheet, $rowIndex++, ["Remessa Reprovada",     $reprovQtd, $reprovValor, "Reprovado"]);

            // Ajusta largura automática para primeiras N colunas (segurança: limitar a 50)
            $maxAuto = 20;
            for ($col = 1; $col <= $maxAuto; $col++) {
                try {
                    $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
                } catch (Throwable $e) {
                    // ignora se algo falhar
                }
            }

            // Envia o arquivo
            header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            header("Cache-Control: max-age=0");

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save("php://output");
            exit;

        } catch (Throwable $e) {
            // Se algo falhar com PhpSpreadsheet, caímos para o fallback manual
            // (não exibimos o erro detalhado em produção; aqui para debug local, podemos logar)
            // error_log($e->getMessage());
        }
    }

    // ---------------------------
    // 2) FALLBACK MANUAL: gera .xlsx "à mão" (ZIP + XML)
    //    — versão segura / corrigida que não corrompe arquivo
    // ---------------------------

    $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        echo "Erro ao criar arquivo temporário XLSX.";
        exit;
    }

    // [Content_Types].xml
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="xml" ContentType="application/xml"/>
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
    <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
    <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>');

    // _rels/.rels
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

    // xl/workbook.xml
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Relatório B2B" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>');

    // xl/_rels/workbook.xml.rels
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>');

    // xl/styles.xml (básico)
    $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <fonts count="1"><font><sz val="11"/></font></fonts>
    <fills count="1"><fill/></fills>
    <borders count="1"><border/></borders>
    <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
    <cellXfs count="1"><xf xfId="0" numFmtId="0"/></cellXfs>
</styleSheet>');

    // docProps/app.xml
    $zip->addFromString('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"
            xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
    <Application>PHP XLSX Generator</Application>
    <DocSecurity>0</DocSecurity>
    <ScaleCrop>false</ScaleCrop>
    <HeadingPairs>
        <vt:vector size="2" baseType="variant">
            <vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant>
            <vt:variant><vt:i4>1</vt:i4></vt:variant>
        </vt:vector>
    </HeadingPairs>
    <TitlesOfParts>
        <vt:vector size="1" baseType="lpstr">
            <vt:lpstr>Relatório B2B</vt:lpstr>
        </vt:vector>
    </TitlesOfParts>
    <Company></Company>
    <LinksUpToDate>false</LinksUpToDate>
</Properties>');

    // docProps/core.xml
    $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"
                   xmlns:dc="http://purl.org/dc/elements/1.1/"
                   xmlns:dcterms="http://purl.org/dc/terms/"
                   xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <dc:creator>Sistema</dc:creator>
    <cp:lastModifiedBy>Sistema</cp:lastModifiedBy>
    <dcterms:created xsi:type="dcterms:W3CDTF">'.date("c").'</dcterms:created>
    <dcterms:modified xsi:type="dcterms:W3CDTF">'.date("c").'</dcterms:modified>
</cp:coreProperties>');

    // ---------- Montar rows (valores sem formatação regional) ----------
    $rows = [];

    $rows[] = ["Relatório B2B - Filiais"];
    $rows[] = [];
    $rows[] = ["Período:", "$inicioFiltro até $fimFiltro"];
    $rows[] = [];
    $rows[] = ["==== Resumo do Período ===="];
    $rows[] = ["Métrica", "Valor", "Variação (%)", "Obs"];

    foreach ($resumo as $m => $v) {
        $obs = match ($m) {
            "Pedidos B2B" => "Solicitações feitas por filiais",
            "Itens Solicitados" => "Total somado dos itens",
            "Faturamento Estimado" => "Subtotal geral",
            "Ticket Médio" => "Faturamento / pedidos",
            default => ""
        };
        $rows[] = [$m, $v[0], $v[1], $obs];
    }

    $rows[] = [];
    $rows[] = ["==== Vendas / Pedidos por Filial ===="];
    $rows[] = ["Filial", "Pedidos", "Itens", "Faturamento", "Ticket Médio", "% do Total"];

    foreach ($listaFiliaisExport as $l) {
        $rows[] = [
            $l['nome'],
            $l['pedidos'],
            $l['itens'],
            $l['faturamento'],
            $l['ticket'],
            $l['perc']
        ];
    }

    $rows[] = [];
    $rows[] = ["==== Produtos Mais Solicitados ===="];
    $rows[] = ["SKU", "Produto", "Quantidade", "Pedidos", "Participação (%)"];

    if (count($produtosLista) > 0) {
        foreach ($produtosLista as $p) {
            $rows[] = [
                $p['codigo_produto'],
                $p['nome_produto'],
                $p['total_quantidade'],
                $p['total_pedidos'],
                $p['perc']
            ];
        }
    } else {
        $rows[] = ["Nenhum produto no período."];
    }

    $rows[] = [];
    $rows[] = ["==== Pagamentos x Entregas ===="];
    $rows[] = ["Métrica", "Quantidade", "Valor", "Status"];
    $rows[] = ["Pagamentos Solicitados", $pendQtd, $pendValor, "Pendente"];
    $rows[] = ["Remessa Concluída", $aprovQtd, $aprovValor, "Aprovado"];
    $rows[] = ["Remessa Reprovada", $reprovQtd, $reprovValor, "Reprovado"];

    // helpers locais
    $excel_col_letter = function($index) {
        $letters = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letters = chr(65 + $mod) . $letters;
            $index = (int)(($index - 1) / 26);
        }
        return $letters;
    };
    $normalize_number_for_xlsx = function($v) {
        if ($v === null) return null;
        if (is_int($v) || is_float($v)) return (string)$v;
        $v = trim((string)$v);
        // converte "1.234,56" -> "1234.56"
        if (preg_match('/^\-?[\d\.]+,\d+$/', $v)) {
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        } else {
            $v = str_replace(["\xc2\xa0", " "], '', $v);
        }
        return is_numeric($v) ? (string)$v : null;
    };

    // montar sheetData XML
    $sheetXml = '<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
           xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheetData>';

    $r = 1;
    foreach ($rows as $row) {
        $sheetXml .= "<row r=\"{$r}\">";
        $c = 1;
        foreach ($row as $value) {
            $col = $excel_col_letter($c);
            $num = $normalize_number_for_xlsx($value);
            if ($num !== null) {
                $num = (string)$num;
                // número
                $sheetXml .= "<c r=\"{$col}{$r}\" t=\"n\"><v>{$num}</v></c>";
            } else {
                // texto — escapar com ENT_XML1
                $v = htmlspecialchars((string)$value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
                $sheetXml .= "<c r=\"{$col}{$r}\" t=\"inlineStr\"><is><t>{$v}</t></is></c>";
            }
            $c++;
        }
        $sheetXml .= "</row>";
        $r++;
    }

    $sheetXml .= "</sheetData></worksheet>";

    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

    // fecha zip
    $zip->close();

    // envia
    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}
// ================================================================
// PARTE 5 — PRINT / HTML (Relatório pronto para imprimir / salvar PDF)
// ================================================================
if ($tipo === 'print') {

    $titulo = "Relatório B2B - Filiais";
    $periodoTexto = date("d/m/Y", strtotime($inicioFiltro)) . " a " . date("d/m/Y", strtotime($fimFiltro));
    // Evita XSS
    $safeTitulo = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
    $safePeriodo = htmlspecialchars($periodoTexto, ENT_QUOTES, 'UTF-8');
    $safeLogoPath = htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8');

    ?>
    <!doctype html>
    <html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= $safeTitulo ?></title>
        <style>
            :root{
                --bg:#ffffff;
                --muted:#6b7280;
                --border:#e6e6e6;
                --heading-bg:#f4f6f8;
                --font-sans: "Inter", "Arial", "Helvetica", sans-serif;
            }
            html,body{height:100%; margin:0; padding:0; background:var(--bg); color:#212121; font-family:var(--font-sans); -webkit-print-color-adjust:exact;}
            .container{max-width:1100px; margin:16px auto; padding:12px;}
            header{display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px;}
            .brand{display:flex; align-items:center; gap:12px;}
            .brand img{max-height:60px; display:block;}
            .brand .company{font-weight:700; font-size:16px;}
            .meta{font-size:13px; color:var(--muted);}
            h1{margin:0; font-size:18px;}
            .section-title{background:var(--heading-bg); padding:8px 10px; font-weight:700; border:1px solid var(--border); margin-top:14px; margin-bottom:6px;}
            table{width:100%; border-collapse:collapse; margin-bottom:12px; font-size:13px;}
            th,td{border:1px solid var(--border); padding:8px 10px; text-align:left; vertical-align:top;}
            th{background:#fafafa; font-weight:700;}
            tfoot td{font-weight:700;}
            .small{font-size:12px; color:var(--muted);}
            footer.print-footer{margin-top:18px; text-align:center; font-size:12px; color:var(--muted);}
            @media print{
                body{margin:0;}
                .no-print{display:none;}
                header, footer.print-footer{position:static;}
            }
            /* tabela responsiva para telas pequenas */
            @media (max-width:720px){
                table, thead, tbody, th, td, tr{display:block;}
                thead tr{display:none;}
                tr{margin-bottom:8px; border:1px solid var(--border); padding:6px;}
                td{border:none; padding:6px;}
                td::before{font-weight:700; display:block;}
            }
            .actions{display:flex; gap:8px; margin-bottom:10px;}
            .btn{padding:8px 12px; border:1px solid var(--border); background:#fff; cursor:pointer; border-radius:6px; font-size:13px;}
        </style>
    </head>
    <body>
    <div class="container">
        <div class="no-print actions">
            <button class="btn" onclick="window.print()">Imprimir / Salvar PDF</button>
            <button class="btn" onclick="window.location.href=document.referrer || window.location.pathname">Voltar</button>
        </div>

        <header>
            <div class="brand">
                <?php if ($logoPath && file_exists($_SERVER['DOCUMENT_ROOT'] . $logoPath)): ?>
                    <img src="<?= $safeLogoPath ?>" alt="logo">
                <?php else: ?>
                    <div class="company">Sua Empresa</div>
                <?php endif; ?>
                <div class="small">Relatório gerado automaticamente</div>
            </div>

            <div style="text-align:right;">
                <h1><?= $safeTitulo ?></h1>
                <div class="meta"><?= $safePeriodo ?></div>
            </div>
        </header>

        <!-- SEÇÃO 1: RESUMO -->
        <div class="section-title">Resumo do Período</div>
        <table aria-label="Resumo do período">
            <thead>
                <tr><th>Métrica</th><th>Valor</th><th>Variação</th><th>Observação</th></tr>
            </thead>
            <tbody>
                <?php foreach ($resumo as $metric => $vals): ?>
                    <tr>
                        <td><?= htmlspecialchars($metric, ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php
                                $v = $vals[0];
                                if (is_numeric($v) && (float)$v == (int)$v) {
                                    echo (int)$v;
                                } elseif (is_numeric($v)) {
                                    echo number_format((float)$v, 2, ',', '.');
                                } else {
                                    echo htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
                                }
                            ?>
                        </td>
                        <td><?= number_format((float)$vals[1], 2, ',', '.') ?>%</td>
                        <td>
                            <?php
                                echo match ($metric) {
                                    "Pedidos B2B" => "Solicitações feitas por filiais",
                                    "Itens Solicitados" => "Total somado dos itens",
                                    "Faturamento Estimado" => "Subtotal geral",
                                    "Ticket Médio" => "Faturamento / pedidos",
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
        <table aria-label="Vendas por filial">
            <thead>
                <tr><th>Filial</th><th>Pedidos</th><th>Itens</th><th>Faturamento (R$)</th><th>Ticket Médio (R$)</th><th>% do Total</th></tr>
            </thead>
            <tbody>
                <?php if (!empty($listaFiliaisExport)): ?>
                    <?php foreach ($listaFiliaisExport as $l): ?>
                        <tr>
                            <td><?= htmlspecialchars($l['nome'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= (int)$l['pedidos'] ?></td>
                            <td><?= (int)$l['itens'] ?></td>
                            <td>R$ <?= number_format((float)$l['faturamento'], 2, ',', '.') ?></td>
                            <td>R$ <?= number_format((float)$l['ticket'], 2, ',', '.') ?></td>
                            <td><?= number_format((float)$l['perc'], 2, ',', '.') ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center;">Nenhuma filial encontrada no período.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- SEÇÃO 3: PRODUTOS -->
        <div class="section-title">Produtos Mais Solicitados</div>
        <table aria-label="Produtos mais solicitados">
            <thead><tr><th>SKU</th><th>Produto</th><th>Quantidade</th><th>Pedidos</th><th>Participação</th></tr></thead>
            <tbody>
                <?php if (!empty($produtosLista)): ?>
                    <?php foreach ($produtosLista as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['codigo_produto'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($p['nome_produto'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= (int)$p['total_quantidade'] ?></td>
                            <td><?= (int)$p['total_pedidos'] ?></td>
                            <td><?= number_format((float)$p['perc'], 2, ',', '.') ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center;">Nenhum produto solicitado por filiais no período.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- SEÇÃO 4: PAGAMENTOS -->
        <div class="section-title">Pagamentos x Entregas (Resumo)</div>
        <table aria-label="Pagamentos">
            <thead><tr><th>Métrica</th><th>Quantidade</th><th>Valor (R$)</th><th>Status</th></tr></thead>
            <tbody>
                <tr><td>Pagamentos Solicitados</td><td><?= (int)$pendQtd ?></td><td>R$ <?= number_format((float)$pendValor, 2, ',', '.') ?></td><td>Pendente</td></tr>
                <tr><td>Remessa Concluída</td><td><?= (int)$aprovQtd ?></td><td>R$ <?= number_format((float)$aprovValor, 2, ',', '.') ?></td><td>Aprovado</td></tr>
                <tr><td>Remessa Reprovada</td><td><?= (int)$reprovQtd ?></td><td>R$ <?= number_format((float)$reprovValor, 2, ',', '.') ?></td><td>Reprovado</td></tr>
            </tbody>
        </table>

        <footer class="print-footer">
            <?= htmlspecialchars(date('d/m/Y H:i'), ENT_QUOTES, 'UTF-8') ?> — Relatório gerado automaticamente
        </footer>
    </div>

    <script>
        // tenta abrir o diálogo de impressão automaticamente (opcional)
        (function(){
            try {
                // delay curto para garantir render
                setTimeout(function(){
                    // não forçar fechar a janela automaticamente (evita problemas com navegadores)
                    // chama apenas print para o usuário confirmar
                    //window.print();
                }, 250);
            } catch(e) { /* ignore */ }
        })();
    </script>
    </body>
    </html>
    <?php
    exit;
}
// ======================================================================
// PARTE 6 — VERIFICAÇÕES FINAIS + ERROS + RETORNO PADRÃO
// ======================================================================

// -----------------------------------------------
// VALIDAR TIPO DE EXPORTAÇÃO / AÇÃO
// -----------------------------------------------
$tiposPermitidos = ["csv", "xlsx", "print"];

if (!in_array($tipo, $tiposPermitidos)) {

    http_response_code(400);

    ?>
    <!doctype html>
    <html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <title>Erro - Tipo inválido</title>
        <style>
            body {
                background: #f8f8f8;
                font-family: Arial, sans-serif;
                padding: 40px;
            }
            .card {
                max-width: 480px;
                margin: auto;
                background: #fff;
                padding: 20px 25px;
                border-radius: 8px;
                box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            }
            h2 { margin-top: 0; }
            .muted { color: #6c757d; }
            a { color: #007bff; }
        </style>
    </head>
    <body>
        <div class="card">
            <h2>❌ Tipo de exportação inválido</h2>
            <p class="muted">
                O tipo informado (<strong><?= htmlspecialchars($tipo) ?></strong>) não é reconhecido.
            </p>
            <p>Use um dos seguintes tipos válidos:</p>
            <ul>
                <li>csv</li>
                <li>xlsx</li>
                <li>print</li>
            </ul>
            <p>
                <a href="javascript:history.back()">Voltar</a>
            </p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// -----------------------------------------------
// SE CHEGOU AQUI SEM EXPORTAR NADA → PRODUTO FINAL
// -----------------------------------------------
http_response_code(500);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Erro interno</title>
    <style>
        body {
            background:#f2f2f2;
            font-family: Arial, sans-serif;
            padding:40px;
        }
        .card {
            max-width:480px;
            margin:auto;
            background:#fff;
            padding:20px 25px;
            border-radius:8px;
            box-shadow:0 3px 10px rgba(0,0,0,0.1);
        }
        h2 { margin-top:0; }
        .muted { color:#6c757d; }
        code {
            background:#ececec;
            padding:3px 5px;
            border-radius:4px;
        }
    </style>
</head>
<body>
    <div class="card">
        <h2>⚠️ Erro inesperado</h2>
        <p class="muted">
            A exportação não pôde ser concluída.
        </p>

        <p>Possíveis causas:</p>
        <ul>
            <li>Erro em consultas SQL</li>
            <li>Dados incompletos ou inconsistentes</li>
            <li>Falta de permissão para gerar arquivos temporários</li>
            <li>Problemas com o PhpSpreadsheet</li>
        </ul>

        <h3>Se estiver tentando gerar XLSX:</h3>
        <p>
            Instale o PhpSpreadsheet com:
        </p>
        <pre><code>composer require phpoffice/phpspreadsheet</code></pre>

        <p style="margin-top:15px;">
            <a href="javascript:history.back()">Voltar</a>
        </p>
    </div>
</body>
</html>
<?php
exit;
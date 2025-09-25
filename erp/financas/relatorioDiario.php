<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// ✅ Recupera o identificador vindo da URL
$idSelecionado = $_GET['id'] ?? '';

// ✅ Conexão com o banco de dados (precisa estar ANTES do bloco AJAX)
require '../../assets/php/conexao.php';

// === AJAX: itens do caixa (vendas + sangrias + suprimentos) ===
if (isset($_GET['ajax']) && $_GET['ajax'] === 'itens_caixa') {
    header('Content-Type: application/json; charset=utf-8');

    $empresa_id = $_GET['id'] ?? $idSelecionado ?? '';
    $idCaixa = (int)($_GET['id_caixa'] ?? 0);

    if ($idCaixa <= 0 || empty($empresa_id)) {
        echo json_encode(['ok' => false, 'msg' => 'Parâmetros inválidos']);
        exit;
    }

    try {
        // Garantir $pdo válido
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            throw new RuntimeException('Conexão indisponível.');
        }

        $st = $pdo->prepare("SELECT id, empresa_id, responsavel, abertura_datetime,
                                    COALESCE(fechamento_datetime, NOW()) AS fechamento_datetime
                             FROM aberturas
                             WHERE id = :id AND empresa_id = :eid");
        $st->execute([':id' => $idCaixa, ':eid' => $empresa_id]);
        $ab = $st->fetch(PDO::FETCH_ASSOC);

        if (!$ab) {
            // Fallback por data + responsável
            $resp = $_GET['resp'] ?? '';
            $data = $_GET['data'] ?? '';
            if ($resp !== '' && $data !== '') {
                $st2 = $pdo->prepare("SELECT id, empresa_id, responsavel, abertura_datetime,
                                            COALESCE(fechamento_datetime, NOW()) AS fechamento_datetime
                                      FROM aberturas
                                      WHERE empresa_id = :eid
                                        AND DATE(abertura_datetime) = :dt
                                        AND responsavel = :resp
                                      ORDER BY abertura_datetime DESC
                                      LIMIT 1");
                $st2->execute([':eid' => $empresa_id, ':dt' => $data, ':resp' => $resp]);
                $ab = $st2->fetch(PDO::FETCH_ASSOC);
            }
            if (!$ab) {
                echo json_encode(['ok' => false, 'msg' => 'Caixa não encontrado']);
                exit;
            }
        }

        $ini = $ab['abertura_datetime'];
        $fim = $ab['fechamento_datetime'];

        $try = function (array $sqls) use ($pdo, $empresa_id, $ini, $fim) {
            foreach ($sqls as $sql) {
                try {
                    $st = $pdo->prepare($sql);
                    $st->execute([':eid' => $empresa_id, ':ini' => $ini, ':fim' => $fim]);
                    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
                    if (is_array($rows)) return $rows;
                } catch (Throwable $e) {
                    // tenta próxima variação
                }
            }
            return [];
        };

        $sqlItens = [
            // 1) seu schema peca
            "SELECT vi.id,
                    vi.venda_id,
                    COALESCE(vi.nome_produto, p.nome, p.descricao, CONCAT('Produto #', vi.produto_id)) AS produto,
                    COALESCE(vi.total, vi.valor_total, vi.valor, (vi.preco*vi.quantidade), (vi.preco_unitario*vi.quantidade)) AS valor,
                    COALESCE(vi.created_at, v.data_venda, v.created_at) AS datahora
             FROM venda_itens_peca vi
             JOIN vendas_peca v ON v.id = vi.venda_id
             LEFT JOIN produtos_peca p ON p.id = vi.produto_id
             WHERE v.empresa_id = :eid AND COALESCE(v.data_venda, v.created_at) BETWEEN :ini AND :fim
             ORDER BY datahora ASC",

            // 2) variação itens_venda/vendas_peca
            "SELECT iv.id, iv.venda_id,
                    COALESCE(iv.nome_produto, p.nome, p.descricao, CONCAT('Produto #', iv.produto_id)) AS produto,
                    COALESCE(iv.total, iv.valor_total, iv.valor, (iv.preco*iv.quantidade), (iv.preco_unitario*iv.quantidade)) AS valor,
                    COALESCE(iv.created_at, v.data_venda, v.created_at) AS datahora
             FROM itens_venda iv
             JOIN vendas_peca v ON v.id = iv.venda_id
             LEFT JOIN produtos p ON p.id = iv.produto_id
             WHERE v.empresa_id = :eid AND COALESCE(v.data_venda, v.created_at) BETWEEN :ini AND :fim
             ORDER BY datahora ASC",

            // 3) variação vendas (sem _peca)
            "SELECT vi.id,
                    vi.venda_id,
                    COALESCE(vi.nome_produto, p.nome, p.descricao, CONCAT('Produto #', vi.produto_id)) AS produto,
                    COALESCE(vi.total, vi.valor_total, vi.valor, (vi.preco*vi.quantidade), (vi.preco_unitario*vi.quantidade)) AS valor,
                    COALESCE(vi.created_at, v.data_venda) AS datahora
             FROM venda_itens_peca vi
             JOIN vendas v ON v.id = vi.venda_id
             LEFT JOIN produtos_peca p ON p.id = vi.produto_id
             WHERE v.empresa_id = :eid AND v.data_venda BETWEEN :ini AND :fim
             ORDER BY datahora ASC",

            "SELECT iv.id, iv.venda_id,
                    COALESCE(iv.nome_produto, p.nome, p.descricao, CONCAT('Produto #', iv.produto_id)) AS produto,
                    COALESCE(iv.total, iv.valor_total, iv.valor, (iv.preco*iv.quantidade), (iv.preco_unitario*iv.quantidade)) AS valor,
                    COALESCE(iv.created_at, v.data_venda) AS datahora
             FROM itens_venda iv
             JOIN vendas v ON v.id = iv.venda_id
             LEFT JOIN produtos p ON p.id = iv.produto_id
             WHERE v.empresa_id = :eid AND v.data_venda BETWEEN :ini AND :fim
             ORDER BY datahora ASC",

            "SELECT vi.id, vi.venda_id,
                    COALESCE(vi.nome_produto, p.nome, p.descricao, CONCAT('Produto #', vi.produto_id)) AS produto,
                    COALESCE(vi.total, vi.valor_total, vi.valor, (vi.preco*vi.quantidade), (vi.preco_unitario*vi.quantidade)) AS valor,
                    COALESCE(vi.created_at, v.data_venda) AS datahora
             FROM vendas_itens vi
             JOIN vendas v ON v.id = vi.venda_id
             LEFT JOIN produtos p ON p.id = vi.produto_id
             WHERE v.empresa_id = :eid AND v.data_venda BETWEEN :ini AND :fim
             ORDER BY datahora ASC",

            "SELECT vi.id, vi.venda_id,
                    COALESCE(vi.nome_produto, CONCAT('Produto #', vi.produto_id)) AS produto,
                    COALESCE(vi.total, vi.valor_total, vi.valor, (vi.preco*vi.quantidade), (vi.preco_unitario*vi.quantidade)) AS valor,
                    COALESCE(vi.created_at, v.data_venda) AS datahora
             FROM venda_itens vi
             JOIN vendas v ON v.id = vi.venda_id
             WHERE v.empresa_id = :eid AND v.data_venda BETWEEN :ini AND :fim
             ORDER BY datahora ASC",
        ];
        $itens = $try($sqlItens);

        $sqlSupr = [
            "SELECT id, COALESCE(valor, valor_suprimento) AS valor, data_registro AS datahora
             FROM suprimentos
             WHERE empresa_id = :eid AND data_registro BETWEEN :ini AND :fim
             ORDER BY datahora ASC"
        ];
        $supr = $try($sqlSupr);

        $sqlSang = [
            "SELECT id, valor, data_registro AS datahora
             FROM sangrias
             WHERE empresa_id = :eid AND data_registro BETWEEN :ini AND :fim
             ORDER BY datahora ASC"
        ];
        $sang = $try($sqlSang);

        $totV = array_sum(array_map(fn($r) => (float)($r['valor'] ?? 0), $itens));
        $totS = array_sum(array_map(fn($r) => (float)($r['valor'] ?? 0), $sang));
        $totU = array_sum(array_map(fn($r) => (float)($r['valor'] ?? 0), $supr));

        echo json_encode([
            'ok' => true,
            'caixa' => [
                'id' => (int)$ab['id'],
                'responsavel' => (string)$ab['responsavel'],
                'inicio' => $ini,
                'fim' => $fim
            ],
            'itens' => $itens,
            'sangrias' => $sang,
            'suprimentos' => $supr,
            'totais' => ['vendas' => $totV, 'sangrias' => $totS, 'suprimentos' => $totU]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => 'Erro ao carregar itens']);
    }
    exit;
}

if (!$idSelecionado) {
    header("Location: .././login.php");
    exit;
}

// ✅ Verifica se a pessoa está logada
if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id'])
) {
    header("Location: .././login.php?id=" . urlencode($idSelecionado));
    exit;
}

// ✅ Buscar nome e tipo do usuário logado
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id = (int)$_SESSION['usuario_id'];

try {
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    $stmt->bindValue(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $nomeUsuario = $usuario['usuario'];
        $tipoUsuario = ucfirst($usuario['nivel']);
    } else {
        echo "<script>alert('Usuário não encontrado.'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar usuário: " . $e->getMessage() . "'); history.back();</script>";
    exit;
}

// ✅ Valida o tipo de empresa e o acesso permitido
$acessoPermitido = false;
$idEmpresaSession = $_SESSION['empresa_id'];
$tipoSession = $_SESSION['tipo_empresa'];

if (str_starts_with($idSelecionado, 'principal_')) {
    $acessoPermitido = ($tipoSession === 'principal' && $idEmpresaSession === 'principal_1');
} elseif (str_starts_with($idSelecionado, 'filial_')) {
    $acessoPermitido = ($tipoSession === 'filial' && $idEmpresaSession === $idSelecionado);
} elseif (str_starts_with($idSelecionado, 'unidade_')) {
    $acessoPermitido = ($tipoSession === 'unidade' && $idEmpresaSession === $idSelecionado);
} elseif (str_starts_with($idSelecionado, 'franquia_')) {
    $acessoPermitido = ($tipoSession === 'franquia' && $idEmpresaSession === $idSelecionado);
}

if (!$acessoPermitido) {
    echo "<script>
          alert('Acesso negado!');
          window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';
        </script>";
    exit;
}

// ✅ Buscar imagem da tabela sobre_empresa com base no idSelecionado
try {
    $sql = "SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_selecionado', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);

    $logoEmpresa = !empty($empresaSobre['imagem'])
        ? "../../assets/img/empresa/" . $empresaSobre['imagem']
        : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png";
}

// ===== BLOCO DINÂMICO: RELATÓRIO DIÁRIO =====
date_default_timezone_set('America/Manaus');
$empresa_id = $idSelecionado;

function obterResumoDiarioLista(PDO $pdo, string $empresa_id): array
{
    $hoje = date('Y-m-d');

    $sql = "SELECT id, responsavel, status, abertura_datetime, fechamento_datetime,
                   valor_total, valor_suprimentos, valor_sangrias, valor_liquido
            FROM aberturas
            WHERE empresa_id = :eid
              AND DATE(abertura_datetime) = :hoje
            ORDER BY abertura_datetime DESC";
    $st = $pdo->prepare($sql);
    $st->execute([':eid' => $empresa_id, ':hoje' => $hoje]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $itens = [];
    if ($rows) {
        foreach ($rows as $r) {
            $entradas = (float)$r['valor_total'] + (float)$r['valor_suprimentos'];
            $saidas   = (float)$r['valor_sangrias'];
            $saldo    = (float)$r['valor_liquido'];
            $data     = $r['abertura_datetime'] ? date('d/m/Y H:i', strtotime($r['abertura_datetime'])) : date('d/m/Y');

            $itens[] = [
                'id_caixa'    => (int)$r['id'],
                'data'        => $data,
                'entradas'    => $entradas,
                'saidas'      => $saidas,
                'saldo'       => $saldo,
                'responsavel' => (string)$r['responsavel'],
                'status'      => (string)$r['status'],
            ];
        }
    } else {
        // Consolidado do dia (sem aberturas)
        $st = $pdo->prepare("SELECT COALESCE(SUM(valor_total),0) FROM vendas WHERE empresa_id=:eid AND DATE(data_venda)=:hoje");
        $st->execute([':eid' => $empresa_id, ':hoje' => $hoje]);
        $vendasDia = (float)($st->fetchColumn() ?: 0);

        $st = $pdo->prepare("SELECT COALESCE(SUM(valor_suprimento),0) FROM suprimentos WHERE empresa_id=:eid AND DATE(data_registro)=:hoje");
        $st->execute([':eid' => $empresa_id, ':hoje' => $hoje]);
        $suprDia = (float)($st->fetchColumn() ?: 0);

        $st = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM sangrias WHERE empresa_id=:eid AND DATE(data_registro)=:hoje");
        $st->execute([':eid' => $empresa_id, ':hoje' => $hoje]);
        $sangDia = (float)($st->fetchColumn() ?: 0);

        $entradas = $vendasDia + $suprDia;
        $saidas   = $sangDia;
        $saldo    = $entradas - $saidas;

        $itens[] = [
            'id_caixa'    => null,
            'data'        => date('d/m/Y'),
            'entradas'    => $entradas,
            'saidas'      => $saidas,
            'saldo'       => $saldo,
            'responsavel' => '—',
            'status'      => '—',
        ];
    }

    return $itens;
}

function fmtBR($v)
{
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}

// Prepara dados JSON para o JS
$__listaResumoHoje = obterResumoDiarioLista($pdo, $empresa_id);
$__listaResumoHoje_json = htmlspecialchars(json_encode($__listaResumoHoje, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');

/**
 * Calcula as entradas do dia (vendas + suprimentos)
 */
function calcularEntradasDia($pdo, $empresa_id)
{
    $hoje = date('Y-m-d');

    $sqlVendas = "SELECT SUM(valor_total) as total_vendas FROM vendas 
                  WHERE DATE(data_venda) = :hoje AND empresa_id = :empresa_id";
    $stmtVendas = $pdo->prepare($sqlVendas);
    $stmtVendas->bindParam(':hoje', $hoje);
    $stmtVendas->bindParam(':empresa_id', $empresa_id);
    $stmtVendas->execute();
    $vendas = $stmtVendas->fetch(PDO::FETCH_ASSOC);
    $totalVendas = (float)($vendas['total_vendas'] ?? 0);

    $sqlSuprimentos = "SELECT SUM(valor_suprimento) as total_suprimentos FROM suprimentos 
                       WHERE DATE(data_registro) = :hoje AND empresa_id = :empresa_id";
    $stmtSuprimentos = $pdo->prepare($sqlSuprimentos);
    $stmtSuprimentos->bindParam(':hoje', $hoje);
    $stmtSuprimentos->bindParam(':empresa_id', $empresa_id);
    $stmtSuprimentos->execute();
    $suprimentos = $stmtSuprimentos->fetch(PDO::FETCH_ASSOC);
    $totalSuprimentos = (float)($suprimentos['total_suprimentos'] ?? 0);

    $totalEntradas = $totalVendas + $totalSuprimentos;

    return [
        'total_entradas'   => $totalEntradas,
        'total_vendas'     => $totalVendas,
        'total_suprimentos' => $totalSuprimentos
    ];
}

/**
 * Calcula as saídas do dia (apenas sangrias)
 */
function calcularSaidasDia($pdo, $empresa_id)
{
    $hoje = date('Y-m-d');

    $sqlSangrias = "SELECT SUM(valor) as total_sangrias FROM sangrias 
                    WHERE DATE(data_registro) = :hoje AND empresa_id = :empresa_id";
    $stmtSangrias = $pdo->prepare($sqlSangrias);
    $stmtSangrias->bindParam(':hoje', $hoje);
    $stmtSangrias->bindParam(':empresa_id', $empresa_id);
    $stmtSangrias->execute();
    $sangrias = $stmtSangrias->fetch(PDO::FETCH_ASSOC);
    $totalSangrias = (float)($sangrias['total_sangrias'] ?? 0);

    return [
        'total_saidas'   => $totalSangrias,
        'total_sangrias' => $totalSangrias,
        'total_despesas' => 0
    ];
}

/**
 * Calcula o saldo em caixa
 */
function calcularSaldoCaixa($pdo, $empresa_id)
{
    $sqlCaixa = "SELECT * FROM aberturas 
                 WHERE empresa_id = :empresa_id AND status = 'aberto' 
                 ORDER BY abertura_datetime DESC LIMIT 1";
    $stmtCaixa = $pdo->prepare($sqlCaixa);
    $stmtCaixa->bindParam(':empresa_id', $empresa_id);
    $stmtCaixa->execute();
    $caixa = $stmtCaixa->fetch(PDO::FETCH_ASSOC);

    if ($caixa) {
        $liq = (float)$caixa['valor_liquido'];
        return [
            'saldo_caixa'     => $liq,
            'valor_meta'      => 1500.00,
            'percentual_meta' => ($liq > 0 ? ($liq / 1500) * 100 : 0)
        ];
    }

    return [
        'saldo_caixa'     => 0,
        'valor_meta'      => 1500.00,
        'percentual_meta' => 0
    ];
}

/**
 * Obtém o resumo diário para a tabela
 */
function obterResumoDiario($pdo, $empresa_id, $limit = 10, $offset = 0)
{
    $sql = "SELECT 
                a.id AS id_caixa,
                DATE(a.abertura_datetime)    AS data,
                a.valor_total                 AS entrada,
                a.valor_sangrias              AS saida,
                a.valor_liquido               AS saldo,
                a.responsavel                 AS responsavel,
                a.status                      AS status,
                a.abertura_datetime           AS abertura_datetime,
                a.fechamento_datetime         AS fechamento_datetime
            FROM aberturas a
            WHERE a.empresa_id = :empresa_id
            ORDER BY a.abertura_datetime DESC
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtém as últimas movimentações
 */
function obterUltimasMovimentacoes($pdo, $empresa_id, $limit = 5)
{
    $sqlVendas = "SELECT 
                    'Venda' as tipo,
                    CONCAT('Venda #', id) as descricao,
                    valor_total as valor,
                    data_venda as data,
                    'success' as classe_cor
                  FROM vendas
                  WHERE empresa_id = :empresa_id
                  ORDER BY data_venda DESC
                  LIMIT :limit";

    $sqlSuprimentos = "SELECT 
                        'Suprimento' as tipo,
                        'Suprimento' as descricao,
                        valor_suprimento as valor,
                        data_registro as data,
                        'primary' as classe_cor
                       FROM suprimentos
                       WHERE empresa_id = :empresa_id
                       ORDER BY data_registro DESC
                       LIMIT :limit";

    $sqlSangrias = "SELECT 
                      'Sangria' as tipo,
                      'Sangria' as descricao,
                      valor as valor,
                      data_registro as data,
                      'danger' as classe_cor
                    FROM sangrias
                    WHERE empresa_id = :empresa_id
                    ORDER BY data_registro DESC
                    LIMIT :limit";

    $movimentacoes = [];

    foreach (['Vendas', 'Suprimentos', 'Sangrias'] as $tipo) {
        $sqlVar = "sql$tipo";
        $stmt = $pdo->prepare($$sqlVar);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $movimentacoes[] = $row;
        }
    }

    usort($movimentacoes, function ($a, $b) {
        return strtotime($b['data']) - strtotime($a['data']);
    });

    return array_slice($movimentacoes, 0, $limit);
}

$entradas            = calcularEntradasDia($pdo, $empresa_id);
$saidas              = calcularSaidasDia($pdo, $empresa_id);
$saldo               = calcularSaldoCaixa($pdo, $empresa_id);
$resumoDiario        = obterResumoDiario($pdo, $empresa_id);
$ultimasMovimentacoes = obterUltimasMovimentacoes($pdo, $empresa_id);
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
    data-assets-path="../../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>ERP - Finanças</title>
    <meta name="description" content="" />

    <!-- Favicon da empresa -->
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($logoEmpresa) ?>" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Icons -->
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />

    <!-- Helpers -->
    <script src="../../assets/vendor/js/helpers.js"></script>

    <!-- Config -->
    <script src="../../assets/js/config.js"></script>
</head>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- MENU -->
            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="app-brand-link">
                        <span class="app-brand-text demo menu-text fw-bolder ms-2" style="text-transform: capitalize;">Açaínhadinhos</span>
                    </a>
                    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
                        <i class="bx bx-chevron-left bx-sm align-middle"></i>
                    </a>
                </div>

                <div class="menu-inner-shadow"></div>

                <ul class="menu-inner py-1">
                    <!-- Dashboard -->
                    <li class="menu-item">
                        <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Dashboard</div>
                        </a>
                    </li>

                    <!-- Finanças -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Finanças</span></li>
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-list-check"></i>
                            <div data-i18n="Authentications">Contas</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item"><a href="./contasAdicionadas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Adicionadas</div>
                                </a></li>
                            <li class="menu-item"><a href="./contasFuturos.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Futuras</div>
                                </a></li>
                            <li class="menu-item"><a href="./contasPagas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Pagas</div>
                                </a></li>
                            <li class="menu-item"><a href="./contasPendentes.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Pendentes</div>
                                </a></li>
                        </ul>
                    </li>

                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-dollar"></i>
                            <div data-i18n="Authentications">Relatórios</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item active"><a href="./relatorioDiario.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Diário</div>
                                </a></li>
                            <li class="menu-item"><a href="./relatorioMensal.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Mensal</div>
                                </a></li>
                            <li class="menu-item"><a href="./relatorioAnual.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Anual</div>
                                </a></li>
                        </ul>
                    </li>

                    <!-- Diversos -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>

                    <li class="menu-item">
                        <a href="../rh/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-group"></i>
                            <div data-i18n="Authentications">RH</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../pdv/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-desktop"></i>
                            <div data-i18n="Authentications">PDV</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../empresa/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div data-i18n="Authentications">Empresa</div>
                        </a>
                    </li>

                    <li class="menu-item">
                        <a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-box"></i>
                            <div data-i18n="Authentications">Estoque</div>
                        </a>
                    </li>

                    <?php
                    $tipoLogado = $_SESSION['tipo_empresa'] ?? '';
                    $idLogado = $_SESSION['empresa_id'] ?? '';

                    // Se for matriz (principal), mostrar links para filial, franquia e unidade
                    if ($tipoLogado === 'principal') {
                    ?>
                        <li class="menu-item">
                            <a href="../filial/index.php?id=principal_1" class="menu-link">
                                <i class="menu-icon tf-icons bx bx-building"></i>
                                <div data-i18n="Authentications">Filial</div>
                            </a>
                        </li>
                        <li class="menu-item">
                            <a href="../franquia/index.php?id=principal_1" class="menu-link">
                                <i class="menu-icon tf-icons bx bx-store"></i>
                                <div data-i18n="Authentications">Franquias</div>
                            </a>
                        </li>
                    <?php
                    } elseif (in_array($tipoLogado, ['filial', 'franquia', 'unidade'])) {
                        // Se for filial, franquia ou unidade, mostra link para matriz
                    ?>
                        <li class="menu-item">
                            <a href="../matriz/index.php?id=<?= urlencode($idLogado) ?>" class="menu-link">
                                <i class="menu-icon tf-icons bx bx-cog"></i>
                                <div data-i18n="Authentications">Matriz</div>
                            </a>
                        </li>
                    <?php
                    }
                    ?>
                    <li class="menu-item">
                        <a href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-group"></i>
                            <div data-i18n="Authentications">Usuários </div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="https://wa.me/92991515710" target="_blank" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-support"></i>
                            <div data-i18n="Basic">Suporte</div>
                        </a>
                    </li>
                </ul>
            </aside>
            <!-- /MENU -->

            <div class="layout-page">
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                            <i class="bx bx-menu bx-sm"></i>
                        </a>
                    </div>

                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <div class="navbar-nav align-items-center">
                            <div class="nav-item d-flex align-items-center"></div>
                        </div>

                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <!-- User -->
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="#" data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <img src="<?= htmlspecialchars($logoEmpresa) ?>" alt class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="<?= htmlspecialchars($logoEmpresa) ?>" alt class="w-px-40 h-auto rounded-circle" />
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <span class="fw-semibold d-block"><?= htmlspecialchars($nomeUsuario) ?></span>
                                                    <small class="text-muted"><?= htmlspecialchars($tipoUsuario) ?></small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                    <li>
                                        <div class="dropdown-divider"></div>
                                    </li>
                                    <li><a class="dropdown-item" href="#"><i class="bx bx-user me-2"></i><span class="align-middle">Minha Conta</span></a></li>
                                    <li><a class="dropdown-item" href="#"><i class="bx bx-cog me-2"></i><span class="align-middle">Configurações</span></a></li>
                                    <li>
                                        <div class="dropdown-divider"></div>
                                    </li>
                                    <li><a class="dropdown-item" href="../logout.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-power-off me-2"></i><span class="align-middle">Sair</span></a></li>
                                </ul>
                            </li>
                            <!--/ User -->
                        </ul>
                    </div>
                </nav>

                <!-- CONTEÚDO -->
                <div class="container-xxl flex-grow-1 container-p-y">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="fw-bold mb-0"><span class="text-muted fw-light">Financeiro /</span> Relatório Diário</h4>
                    </div>

                    <!-- Cards resumo -->
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3">
                            <div class="card text-center h-100">
                                <div class="card-body">
                                    <div class="fw-semibold">Entradas do Dia</div>
                                    <h4 class="mb-1"><?= fmtBR($entradas['total_entradas']) ?></h4>
                                    <small class="text-success fw-semibold">+12% vs ontem</small>
                                    <div class="mt-3">
                                        <span class="badge bg-label-primary">Vendas: <?= fmtBR($entradas['total_vendas']) ?></span>
                                        <span class="badge bg-label-secondary ms-1">Suprimentos: <?= fmtBR($entradas['total_suprimentos']) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card text-center h-100">
                                <div class="card-body">
                                    <div class="fw-semibold">Saídas do Dia</div>
                                    <h4 class="mb-1"><?= fmtBR($saidas['total_saidas']) ?></h4>
                                    <small class="text-danger fw-semibold">+5% vs ontem</small>
                                    <div class="mt-3">
                                        <span class="badge bg-label-danger">Sangrias: <?= fmtBR($saidas['total_sangrias']) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card text-center h-100">
                                <div class="card-body">
                                    <div class="fw-semibold">Saldo em Caixa</div>
                                    <h4 class="mb-1"><?= fmtBR($saldo['saldo_caixa']) ?></h4>
                                    <small class="text-success fw-semibold">+7% vs ontem</small>
                                    <div class="mt-3">
                                        <span class="badge bg-label-info">Meta: <?= fmtBR($saldo['valor_meta']) ?></span>
                                        <span class="badge bg-label-success ms-1"><?= number_format($saldo['percentual_meta'], 0) ?>% da meta</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabela e Lateral -->
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <div class="card">
                                <div class="card-header text-white d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Resumo Diário</h5>
                                </div>
                                <div class="table-responsive text-nowrap">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Data</th>
                                                <th>Entrada</th>
                                                <th>Saída</th>
                                                <th>Saldo</th>
                                                <th>Responsável</th>
                                                <th>Status</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($resumoDiario as $registro): ?>
                                                <tr>
                                                    <td><strong><?= date('d/m/Y', strtotime($registro['data'])) ?></strong></td>
                                                    <td><?= fmtBR($registro['entrada']) ?></td>
                                                    <td><?= fmtBR($registro['saida']) ?></td>
                                                    <td><strong><?= fmtBR($registro['saldo']) ?></strong></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar avatar-xs me-2">
                                                                <span class="avatar-initial rounded-circle bg-label-primary">
                                                                    <?= htmlspecialchars(mb_substr($registro['responsavel'], 0, 1)) ?>
                                                                </span>
                                                            </div>
                                                            <?= htmlspecialchars($registro['responsavel']) ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= $registro['status'] == 'fechado' ? 'success' : 'warning' ?>">
                                                            <?= ucfirst(htmlspecialchars($registro['status'])) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-icon" title="Imprimir">
                                                            <i class="bx bx-printer"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="card-footer d-flex justify-content-between">
                                    <div class="text-muted">Mostrando 1 a <?= count($resumoDiario) ?> de <?= count($resumoDiario) ?> registros</div>
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination pagination-sm mb-0">
                                            <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1">Anterior</a></li>
                                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                            <li class="page-item"><a class="page-link" href="#">Próxima</a></li>
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="mb-0">Últimas Movimentações</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($ultimasMovimentacoes as $movimentacao): ?>
                                            <div class="list-group-item list-group-item-action">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <h6 class="mb-1"><?= htmlspecialchars($movimentacao['descricao']) ?></h6>
                                                        <small class="text-muted"><?= date('d/m - H:i', strtotime($movimentacao['data'])) ?></small>
                                                    </div>
                                                    <span class="text-<?= $movimentacao['classe_cor'] ?>">
                                                        <?= ($movimentacao['tipo'] == 'Sangria') ? '-' : '+' ?>
                                                        <?= fmtBR($movimentacao['valor']) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /CONTEÚDO -->
            </div>
        </div>
    </div>

    <!-- Vendors JS -->
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>

    <!-- Core JS -->
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <!-- ✅ Essencial para o sidebar abrir/fechar -->
    <script src="../../assets/vendor/js/menu.js"></script>
    <script src="../../assets/js/main.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            try {
                var dados = JSON.parse('<?= $__listaResumoHoje_json ?? "[]" ?>') || [];
                // Procura um card com título "Resumo Diário"
                var cards = document.querySelectorAll('.card');
                var alvo = null;
                for (var i = 0; i < cards.length; i++) {
                    var h = cards[i].querySelector('.card-header h5, .card-header h4, .card-header h6');
                    if (h && h.textContent.trim().toLowerCase() === 'resumo diário') {
                        alvo = cards[i];
                        break;
                    }
                }
                if (!alvo) return;

                var body = alvo.querySelector('.card-body');
                if (!body) {
                    body = document.createElement('div');
                    body.className = 'card-body';
                    alvo.appendChild(body);
                }

                var ul = document.createElement('ul');
                ul.className = 'list-group list-group-flush';

                var head = document.createElement('li');
                head.className = 'list-group-item p-2 small fw-semibold text-muted';
                head.innerHTML = '<div class="row">' +
                    '<div class="col-2">Data</div>' +
                    '<div class="col-2 text-end">Entrada</div>' +
                    '<div class="col-2 text-end">Saída</div>' +
                    '<div class="col-2 text-end">Saldo</div>' +
                    '<div class="col-2">Responsável</div>' +
                    '<div class="col-2">Status</div>' +
                    '</div>';
                ul.appendChild(head);

                dados.forEach(function(it) {
                    var li = document.createElement('li');
                    li.className = 'list-group-item p-2';
                    var saldoClass = (parseFloat(it.saldo || 0) >= 0) ? 'text-success' : 'text-danger';
                    li.innerHTML = '<div class="row align-items-center">' +
                        '<div class="col-2"><span>' + (it.data || '') + '</span></div>' +
                        '<div class="col-2 text-end"><span>' + (new Intl.NumberFormat("pt-BR", {
                            style: "currency",
                            currency: "BRL"
                        }).format(it.entradas || 0)) + '</span></div>' +
                        '<div class="col-2 text-end"><span>' + (new Intl.NumberFormat("pt-BR", {
                            style: "currency",
                            currency: "BRL"
                        }).format(it.saidas || 0)) + '</span></div>' +
                        '<div class="col-2 text-end"><span class="' + saldoClass + '">' + (new Intl.NumberFormat("pt-BR", {
                            style: "currency",
                            currency: "BRL"
                        }).format(it.saldo || 0)) + '</span></div>' +
                        '<div class="col-2"><span>' + (it.responsavel || "") + '</span></div>' +
                        '<div class="col-2 d-flex align-items-center justify-content-between">' +
                        '<span>' + (it.status || "") + '</span>' +
                        '<div class="btn-group btn-group-sm">' +
                        '<button type="button" class="btn btn-outline-secondary" title="Imprimir" onclick="window.print()"><i class="bx bx-printer"></i></button>' +
                        '<button type="button" class="btn btn-outline-secondary" title="Baixar CSV" onclick="(function(){downloadCSV([it])})()"><i class="bx bx-download"></i></button>' +
                        '</div>' +
                        '</div>' +
                        '</div>';
                    li.it = it;
                    ul.appendChild(li);
                });

                window.downloadCSV = function(list) {
                    try {
                        var arr = list || dados;
                        var csv = 'Data;Entrada;Saída;Saldo;Responsável;Status\n';
                        arr.forEach(function(x) {
                            csv += [
                                (x.data || ''),
                                (x.entradas || 0).toString().replace('.', ','),
                                (x.saidas || 0).toString().replace('.', ','),
                                (x.saldo || 0).toString().replace('.', ','),
                                (x.responsavel || ''),
                                (x.status || '')
                            ].join(';') + '\n';
                        });
                        var blob = new Blob(['\ufeff' + csv], {
                            type: 'text/csv;charset=utf-8;'
                        });
                        var link = document.createElement('a');
                        link.href = URL.createObjectURL(blob);
                        link.download = 'resumo-diario.csv';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    } catch (e) {
                        console.error(e);
                    }
                };

                body.innerHTML = '';
                body.appendChild(ul);
            } catch (e) {
                console.warn('Resumo Diário não pôde ser renderizado:', e);
            }
        });
    </script>

    <!-- Modal Itens do Caixa -->
    <div class="modal fade" id="modalItensCaixa" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        Itens vendidos — <span id="mic-resp"></span>
                        <small class="text-muted d-block" id="mic-periodo"></small>
                    </h5>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="mic-print">
                        <i class="bx bx-printer"></i> Imprimir
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div id="mic-conteudo">
                        <h6 class="mb-2">Itens de venda</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped" id="mic-tb-itens">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Produto</th>
                                        <th class="text-end">Valor</th>
                                        <th>Data/Hora</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="2" class="text-end">Total</th>
                                        <th class="text-end" id="mic-tot-vendas">R$ 0,00</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="row g-3 mt-3">
                            <div class="col-md-6">
                                <h6 class="mb-2">Suprimentos</h6>
                                <table class="table table-sm table-striped" id="mic-tb-supr">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th class="text-end">Valor</th>
                                            <th>Data/Hora</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                    <tfoot>
                                        <tr>
                                            <th class="text-end" colspan="1">Total</th>
                                            <th class="text-end" id="mic-tot-supr">R$ 0,00</th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6 class="mb-2">Sangrias</h6>
                                <table class="table table-sm table-striped" id="mic-tb-sang">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th class="text-end">Valor</th>
                                            <th>Data/Hora</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                    <tfoot>
                                        <tr>
                                            <th class="text-end" colspan="1">Total</th>
                                            <th class="text-end" id="mic-tot-sang">R$ 0,00</th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <small class="me-auto text-muted">Dica: use a coluna “Ações” do Resumo Diário.</small>
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function() {
            var EMPRESA_ID = <?= json_encode($idSelecionado ?? ($_GET['id'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

            function ensureHtml2Canvas() {
                return new Promise(function(res, rej) {
                    if (window.html2canvas) return res(window.html2canvas);
                    var s = document.createElement('script');
                    s.src = "https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js";
                    s.onload = function() {
                        res(window.html2canvas);
                    };
                    s.onerror = function() {
                        rej(new Error('Falha ao carregar html2canvas'));
                    };
                    document.head.appendChild(s);
                });
            }

            function br(v) {
                v = parseFloat(v || 0);
                return v.toLocaleString('pt-BR', {
                    style: 'currency',
                    currency: 'BRL'
                });
            }

            function fillTable(tbody, rows, render) {
                tbody.innerHTML = '';
                rows.forEach(function(r, i) {
                    var tr = document.createElement('tr');
                    tr.innerHTML = render(r, i + 1);
                    tbody.appendChild(tr);
                });
            }

            async function abrirModalItens(idCaixa) {
                try {
                    const url = new URL(window.location.href);
                    url.searchParams.set('ajax', 'itens_caixa');
                    url.searchParams.set('id_caixa', idCaixa);
                    if (EMPRESA_ID) url.searchParams.set('id', EMPRESA_ID);

                    const r = await fetch(url.toString(), {
                        cache: 'no-store'
                    });
                    const j = await r.json();
                    if (!j.ok) {
                        alert(j.msg || 'Não foi possível carregar os itens');
                        return;
                    }

                    document.getElementById('mic-resp').textContent = 'Responsável: ' + (j.caixa.responsavel || '-');
                    const ini = new Date(j.caixa.inicio);
                    const fim = new Date(j.caixa.fim);
                    document.getElementById('mic-periodo').textContent =
                        'Período: ' + ini.toLocaleString('pt-BR') + ' — ' + fim.toLocaleString('pt-BR');

                    fillTable(document.querySelector('#mic-tb-itens tbody'), j.itens,
                        (r, i) => (`<td>${i}</td><td>${(r.produto||'-')}</td><td class="text-end">${br(r.valor)}</td><td>${new Date(r.datahora).toLocaleString('pt-BR')}</td>`));
                    document.getElementById('mic-tot-vendas').textContent = br(j.totais.vendas);

                    fillTable(document.querySelector('#mic-tb-supr tbody'), j.suprimentos,
                        (r, i) => (`<td>${i}</td><td class="text-end">${br(r.valor)}</td><td>${new Date(r.datahora).toLocaleString('pt-BR')}</td>`));
                    document.getElementById('mic-tot-supr').textContent = br(j.totais.suprimentos);

                    fillTable(document.querySelector('#mic-tb-sang tbody'), j.sangrias,
                        (r, i) => (`<td>${i}</td><td class="text-end">${br(r.valor)}</td><td>${new Date(r.datahora).toLocaleString('pt-BR')}</td>`));
                    document.getElementById('mic-tot-sang').textContent = br(j.totais.sangrias);

                    new bootstrap.Modal(document.getElementById('modalItensCaixa')).show();
                } catch (e) {
                    console.error(e);
                    alert('Erro ao abrir itens do caixa.');
                }
            }

            // Delegação — se futuramente você colocar um botão .btn-visualizar-caixa com data-id-caixa
            document.addEventListener('click', function(ev) {
                const btn = ev.target.closest('.btn-visualizar-caixa');
                if (!btn) return;
                ev.preventDefault();
                const id = parseInt(btn.getAttribute('data-id-caixa') || '0', 10);
                if (id > 0) abrirModalItens(id);
            });

            // Botão imprimir do modal
            document.getElementById('mic-print').addEventListener('click', async function() {
                try {
                    await ensureHtml2Canvas();
                    const alvo = document.getElementById('mic-tb-itens');
                    const canvas = await html2canvas(alvo, {
                        scale: 2,
                        useCORS: true
                    });
                    const dataUrl = canvas.toDataURL('image/png');
                    const w = window.open('', '_blank');
                    w.document.write('<html><head><title>Impressão</title><meta charset="utf-8"></head><body style="margin:0">');
                    w.document.write('<img src="' + dataUrl + '" style="width:100%;display:block"/>');
                    w.document.write('</body></html>');
                    w.document.close();
                    w.focus();
                    w.print();
                } catch (e) {
                    console.error(e);
                    window.print();
                }
            });

            // Ícone de impressora no card
            document.addEventListener('click', async function(e) {
                const icon = e.target.closest('.bx-printer');
                if (!icon) return;
                const btn = icon.closest('button, a');
                if (!btn) return;
                const card = btn.closest('.card');
                if (!card) return;
                const table = card.querySelector('table');
                if (!table) return;
                e.preventDefault();
                try {
                    await ensureHtml2Canvas();
                    const canvas = await html2canvas(table, {
                        scale: 2,
                        useCORS: true
                    });
                    const dataUrl = canvas.toDataURL('image/png');
                    const w = window.open('', '_blank');
                    w.document.write('<html><head><title>Impressão</title><meta charset="utf-8"></head><body style="margin:0">');
                    w.document.write('<img src="' + dataUrl + '" style="width:100%;display:block"/>');
                    w.document.write('</body></html>');
                    w.document.close();
                    w.focus();
                    w.print();
                } catch (err) {
                    console.error(err);
                    window.print();
                }
            });
        })();
    </script>
</body>

</html>
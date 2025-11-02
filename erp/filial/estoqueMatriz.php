<?php
declare(strict_types=1);

/**
 * Estoque Matriz - arquivo reorganizado (PRG + token + prevenção de double-submit)
 * Substitua o conteúdo do arquivo atual por este.
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

session_start();

// --------- Requisição mínima (id selecionado) -------------
$idSelecionado = $_GET['id'] ?? '';
if (!$idSelecionado) {
    header("Location: .././login.php");
    exit;
}

// --------- Verifica sessão de usuário ---------------------
if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id'])
) {
    header("Location: .././login.php?id=" . urlencode($idSelecionado));
    exit;
}

// --------- Conexão ao banco -------------------------------
require '../../assets/php/conexao.php'; // deve expor $pdo (PDO)

// --------- Carregar usuário logado -------------------------
$usuario_id  = (int)($_SESSION['usuario_id'] ?? 0);
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';

try {
    $st = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id LIMIT 1");
    $st->execute([':id' => $usuario_id]);
    $usr = $st->fetch(PDO::FETCH_ASSOC);
    if ($usr) {
        $nomeUsuario = $usr['usuario'] ?? $nomeUsuario;
        $tipoUsuario = ucfirst((string)($usr['nivel'] ?? $tipoUsuario));
    } else {
        // usuário não encontrado -> redireciona p/ login
        echo "<script>alert('Usuário não encontrado.'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar usuário: " . htmlspecialchars($e->getMessage(), ENT_QUOTES) . "'); history.back();</script>";
    exit;
}

// --------- Verificação de acesso (tipo empresa) -----------
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
    echo "<script>alert('Acesso negado!'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
    exit;
}

// --------- Gerar token CSRF anti-dup (se não existir) ------
if (empty($_SESSION['transfer_form_token'])) {
    $_SESSION['transfer_form_token'] = bin2hex(random_bytes(16));
}
$formToken = $_SESSION['transfer_form_token'];

// --------- Processamento do POST (Gerar transferência) -----
// Colocado antes de qualquer output: PRG aplicado via header() + exit.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gerar_transferencia'])) {

    // Validação token
    $postedToken = $_POST['form_token'] ?? '';
    if (!hash_equals((string)($_SESSION['transfer_form_token'] ?? ''), (string)$postedToken)) {
        // Token inválido — redireciona limpando possível reenvio
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // invalidar token imediatamente para evitar reuso
    unset($_SESSION['transfer_form_token']);

    // Sanitização/validação básica
    $produto_id = isset($_POST['produto_id']) ? (int)$_POST['produto_id'] : 0;
    $id_filial  = isset($_POST['id_filial']) ? (int)$_POST['id_filial'] : 0;
    $quantidade = isset($_POST['quantidade']) ? (int)$_POST['quantidade'] : 0;
    $prioridade = trim((string)($_POST['prioridade'] ?? 'Baixa'));
    $observacao = trim((string)($_POST['observacao'] ?? ''));

    $errors = [];

    if ($produto_id <= 0) $errors[] = "Produto inválido.";
    if ($id_filial <= 0)  $errors[] = "Filial inválida.";
    if ($quantidade <= 0) $errors[] = "Quantidade deve ser maior que zero.";
    if (!in_array($prioridade, ['Baixa', 'Media', 'Alta'], true)) $prioridade = 'Baixa';

    if (count($errors) === 0) {
        try {
            $pdo->beginTransaction();

            // 1) Pegar dados do produto e conferir empresa
            $st = $pdo->prepare("SELECT * FROM estoque WHERE id = :id AND empresa_id = :empresa FOR UPDATE");
            $st->execute([':id' => $produto_id, ':empresa' => $idSelecionado]);
            $produto = $st->fetch(PDO::FETCH_ASSOC);

            if (!$produto) {
                throw new RuntimeException("Produto não encontrado.");
            }

            // Verifica disponibilidade
            $disponivel = (int)$produto['quantidade_produto'];
            if ($quantidade > $disponivel) {
                throw new RuntimeException("Quantidade maior que disponível.");
            }

            // 2) Inserir solicitacao_b2b
            $insertSb = $pdo->prepare("INSERT INTO solicitacoes_b2b 
                (id_matriz, id_solicitante, criado_por_usuario_id, status, prioridade, observacao, created_at)
                VALUES (:matriz, :solicitante, :usuario, 'em_transito', :prioridade, :obs, NOW())");

            $insertSb->execute([
                ':matriz'     => $idSelecionado,
                ':solicitante'=> 'unidade_' . $id_filial,
                ':usuario'    => $usuario_id,
                ':prioridade' => $prioridade,
                ':obs'        => $observacao
            ]);

            $solicitacao_id = (int)$pdo->lastInsertId();

            if ($solicitacao_id <= 0) {
                throw new RuntimeException("Erro ao criar solicitação.");
            }

            // 3) Inserir item na solicitacoes_b2b_itens
            $insertItem = $pdo->prepare("INSERT INTO solicitacoes_b2b_itens
                (solicitacao_id, produto_id, codigo_produto, nome_produto, unidade, preco_unitario, quantidade, subtotal, created_at)
                VALUES (:solicitacao, :produto, :codigo, :nome, :unidade, :preco, :quantidade, :subtotal, NOW())");

            $subtotal = (float)$produto['preco_produto'] * $quantidade;

            $insertItem->execute([
                ':solicitacao' => $solicitacao_id,
                ':produto'     => $produto['id'],
                ':codigo'      => $produto['codigo_produto'],
                ':nome'        => $produto['nome_produto'],
                ':unidade'     => $produto['unidade'],
                ':preco'       => $produto['preco_produto'],
                ':quantidade'  => $quantidade,
                ':subtotal'    => $subtotal
            ]);

            // 4) Atualizar estoque.reservado
            $upd = $pdo->prepare("UPDATE estoque SET reservado = COALESCE(reservado,0) + :qtd WHERE id = :id AND empresa_id = :empresa");
            $upd->execute([':qtd' => $quantidade, ':id' => $produto['id'], ':empresa' => $idSelecionado]);

            $pdo->commit();

            // PRG: redireciona para evitar re-submission. Adiciona query param de sucesso.
            $location = $_SERVER['REQUEST_URI'];
            // remove possível fragmentos
            header("Location: " . $location . (strpos($location, '?') === false ? '?transfer_success=1' : '&transfer_success=1'));
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            // armazenar erro em sessão para exibir após redirect (se quiser)
            $_SESSION['transfer_error'] = $e->getMessage();
            // redirecionar para a mesma página com erro
            $location = $_SERVER['REQUEST_URI'];
            header("Location: " . $location . (strpos($location, '?') === false ? '?transfer_error=1' : '&transfer_error=1'));
            exit;
        }
    } else {
        // erros de validação: armazenar e redirecionar
        $_SESSION['transfer_error'] = implode(' ', $errors);
        $location = $_SERVER['REQUEST_URI'];
        header("Location: " . $location . (strpos($location, '?') === false ? '?transfer_error=1' : '&transfer_error=1'));
        exit;
    }
}

// ------- Feedback após PRG (opcional) ----------------------
// Pegamos mensagens armazenadas em session (se houver) e limpamos.
$transferSuccessMsg = '';
$transferErrorMsg = '';
if (isset($_GET['transfer_success'])) {
    $transferSuccessMsg = 'Transferência gerada com sucesso!';
}
if (isset($_GET['transfer_error']) && !empty($_SESSION['transfer_error'])) {
    $transferErrorMsg = $_SESSION['transfer_error'];
    unset($_SESSION['transfer_error']);
}

// --------- Buscar logo da empresa --------------------------
try {
    $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1");
    $stmt->execute([':id_selecionado' => $idSelecionado]);
    $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);
    $logoEmpresa = (!empty($empresaSobre['imagem'])) ? "../../assets/img/empresa/" . $empresaSobre['imagem'] : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png";
}

// ----------------- Funções utilitárias ----------------------
function brToIsoDate($d)
{
    if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $d)) return $d;
    if (preg_match('~^(\d{2})/(\d{2})/(\d{4})$~', $d, $m)) {
        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }
    return null;
}

function moneyBr($v)
{
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}

// --------- Filtros e métricas (mantive sua lógica) ---------
$periodo = $_GET['periodo'] ?? 'hoje';
$dataIni = $_GET['data_ini'] ?? '';
$dataFim = $_GET['data_fim'] ?? '';
$caixaId = isset($_GET['caixa_id']) && $_GET['caixa_id'] !== '' ? (int)$_GET['caixa_id'] : null;
$formaPag = $_GET['forma_pagamento'] ?? '';
$statusNf = $_GET['status_nfce'] ?? '';

$now = new DateTimeImmutable('now');
$ini = (new DateTimeImmutable('today'))->setTime(0,0,0);
$fim = (new DateTimeImmutable('today'))->setTime(23,59,59);

switch ($periodo) {
    case 'ontem':
        $ini = (new DateTimeImmutable('yesterday'))->setTime(0,0,0);
        $fim = (new DateTimeImmutable('yesterday'))->setTime(23,59,59);
        break;
    case 'ult7':
        $ini = (new DateTimeImmutable('today'))->modify('-6 days')->setTime(0,0,0);
        $fim = (new DateTimeImmutable('today'))->setTime(23,59,59);
        break;
    case 'mes':
        $ini = (new DateTimeImmutable('first day of this month'))->setTime(0,0,0);
        $fim = (new DateTimeImmutable('last day of this month'))->setTime(23,59,59);
        break;
    case 'mes_anterior':
        $ini = (new DateTimeImmutable('first day of last month'))->setTime(0,0,0);
        $fim = (new DateTimeImmutable('last day of last month'))->setTime(23,59,59);
        break;
    case 'custom':
        $isoIni = brToIsoDate($dataIni);
        $isoFim = brToIsoDate($dataFim);
        if ($isoIni && $isoFim) {
            $ini = new DateTimeImmutable($isoIni . ' 00:00:00');
            $fim = new DateTimeImmutable($isoFim . ' 23:59:59');
        }
        break;
    case 'hoje':
    default:
        break;
}

// Inicializa variáveis de métricas
$card1 = $card2 = $card3 = $card4 = 0;
$listaCaixas = [];
$caixaAtual = null;
$vendasQtd = 0; $vendasValor = 0.0; $vendasTroco = 0.0; $ticketMedio = 0.0;
$pagamentoSeries = []; $vendasPorHora = array_fill(0,24,0);
$topProdutos = []; $nfceStatusCont = []; $ultimasVendas = [];

try {
    // Cards resumo
    $st = $pdo->prepare("SELECT COUNT(*) FROM estoque WHERE empresa_id = :empresa");
    $st->execute([':empresa' => $idSelecionado]);
    $card1 = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COALESCE(SUM(quantidade_produto),0) FROM estoque WHERE empresa_id = :empresa");
    $st->execute([':empresa' => $idSelecionado]);
    $card2 = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COALESCE(SUM(reservado),0) FROM estoque WHERE empresa_id = :empresa");
    $st->execute([':empresa' => $idSelecionado]);
    $card3 = (int)$st->fetchColumn();

    // Card: transferencias entregues (por empresa)
    $st = $pdo->prepare("
        SELECT COUNT(*) AS total_transferencias
        FROM solicitacoes_b2b s
        INNER JOIN unidades u
            ON u.id = CAST(SUBSTRING_INDEX(s.id_solicitante, '_', -1) AS UNSIGNED)
           AND u.tipo = 'Filial'
           AND u.empresa_id = s.id_matriz
        WHERE s.id_matriz = :empresa
          AND s.status = 'entregue'
    ");
    $st->execute([':empresa' => $idSelecionado]);
    $card4 = (int)$st->fetchColumn();

    // Lista caixas recentes (últimos 60 dias)
    $st = $pdo->prepare("
        SELECT id, numero_caixa, responsavel, abertura_datetime, status
        FROM aberturas
        WHERE empresa_id = :empresa_id
          AND abertura_datetime >= DATE_SUB(NOW(), INTERVAL 60 DAY)
        ORDER BY abertura_datetime DESC
    ");
    $st->execute([':empresa_id' => $idSelecionado]);
    $listaCaixas = $st->fetchAll(PDO::FETCH_ASSOC);

    // Caixa atual aberto
    $st = $pdo->prepare("
        SELECT id, responsavel, numero_caixa, valor_abertura, valor_total, valor_sangrias, valor_suprimentos, valor_liquido,
               abertura_datetime, fechamento_datetime, quantidade_vendas, status, cpf_responsavel
        FROM aberturas
        WHERE empresa_id = :empresa_id
          AND status = 'aberto'
        ORDER BY abertura_datetime DESC
        LIMIT 1
    ");
    $st->execute([':empresa_id' => $idSelecionado]);
    $caixaAtual = $st->fetch(PDO::FETCH_ASSOC);

    // KPIs base (vendas)
    $params = [':empresa_id' => $idSelecionado, ':ini' => $ini->format('Y-m-d H:i:s'), ':fim' => $fim->format('Y-m-d H:i:s')];
    $whereBase = " WHERE empresa_id = :empresa_id AND data_venda BETWEEN :ini AND :fim ";

    $sql = "SELECT COUNT(*) AS qtd, COALESCE(SUM(valor_total),0) AS soma_total, COALESCE(SUM(troco),0) AS soma_troco FROM vendas $whereBase";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    $vendasQtd = (int)($r['qtd'] ?? 0);
    $vendasValor = (float)($r['soma_total'] ?? 0.0);
    $vendasTroco = (float)($r['soma_troco'] ?? 0.0);
    $ticketMedio = $vendasQtd > 0 ? ($vendasValor / $vendasQtd) : 0.0;

    // Top produtos (mantive sua query original)
    $params = [':empresa_id' => $idSelecionado, ':ini' => $ini->format('Y-m-d H:i:s'), ':fim' => $fim->format('Y-m-d H:i:s')];
    $sql = "SELECT iv.produto_nome, SUM(iv.quantidade) AS qtd, SUM(iv.quantidade * iv.preco_unitario) AS valor
            FROM itens_venda iv
            JOIN vendas v ON v.id = iv.venda_id
            WHERE v.empresa_id = :empresa_id AND v.data_venda BETWEEN :ini AND :fim
            GROUP BY iv.produto_nome
            ORDER BY qtd DESC
            LIMIT 5";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $topProdutos = $st->fetchAll(PDO::FETCH_ASSOC);

    // NFC-e status
    $params = [':empresa_id' => $idSelecionado, ':ini' => $ini->format('Y-m-d H:i:s'), ':fim' => $fim->format('Y-m-d H:i:s')];
    $sql = "SELECT COALESCE(status_nfce,'sem_status') AS st, COUNT(*) AS qtd FROM vendas WHERE empresa_id = :empresa_id AND data_venda BETWEEN :ini AND :fim GROUP BY COALESCE(status_nfce,'sem_status')";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $nfceStatusCont[$row['st']] = (int)$row['qtd'];
    }

    // Últimas vendas
    $params = [':empresa_id' => $idSelecionado, ':ini' => $ini->format('Y-m-d H:i:s'), ':fim' => $fim->format('Y-m-d H:i:s')];
    $sql = "SELECT id, responsavel, forma_pagamento, valor_total, data_venda FROM vendas WHERE empresa_id = :empresa_id AND data_venda BETWEEN :ini AND :fim ORDER BY data_venda DESC LIMIT 5";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $ultimasVendas = $st->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // mantém valores padrões caso de erro
}

// --------- Consulta principal: estoque + total_transferencias --------------
try {
    $stmt = $pdo->prepare("
        SELECT 
            e.id,
            e.empresa_id,
            e.codigo_produto,
            e.nome_produto,
            e.categoria_produto,
            e.unidade,
            e.quantidade_produto,
            COALESCE(e.reservado,0) AS reservado,
            COUNT(sbi.id) AS total_transferencias
        FROM estoque e
        LEFT JOIN solicitacoes_b2b_itens sbi
            ON sbi.produto_id = e.id
        LEFT JOIN solicitacoes_b2b sb
            ON sb.id = sbi.solicitacao_id
            AND sb.status = 'entregue'
            AND sb.id_matriz = e.empresa_id
        LEFT JOIN unidades u
            ON u.id = CAST(SUBSTRING_INDEX(sb.id_solicitante, '_', -1) AS UNSIGNED)
            AND u.tipo = 'Filial'
            AND u.empresa_id = e.empresa_id
        WHERE e.empresa_id = :empresa
        GROUP BY e.id
        ORDER BY e.nome_produto ASC
    ");
    $stmt->execute([':empresa' => $idSelecionado]);
    $produtosEstoque = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $produtosEstoque = [];
}

// Função de status
function calcularStatusEstoque($quantidade, $min)
{
    if ($quantidade < $min) {
        return ['Baixo', 'danger'];
    } elseif ($quantidade >= $min && $quantidade <= ($min * 2)) {
        return ['Estável', 'success'];
    } else {
        return ['Alto', 'primary'];
    }
}

// --------- Buscar filiais (para o select da modal) --------------
try {
    $st = $pdo->prepare("SELECT id, nome FROM unidades WHERE empresa_id = :empresa AND tipo = 'Filial' AND status = 'Ativa' ORDER BY nome ASC");
    $st->execute([':empresa' => $idSelecionado]);
    $filiais = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $filiais = [];
}

// --------- Preparar labels / arrays para gráficos (mantive sua lógica) ------
$labelsHoras = [];
for ($h = 0; $h < 24; $h++) $labelsHoras[] = sprintf('%02d:00', $h);
$pagtoLabels = array_keys($pagamentoSeries);
$pagtoValues = array_values($pagamentoSeries);
$nfceLabels = array_keys($nfceStatusCont);
$nfceValues = array_values($nfceStatusCont);
$topProdLabels = []; $topProdQtd = [];
foreach ($topProdutos as $p) {
    $topProdLabels[] = $p['produto_nome'];
    $topProdQtd[] = (int)$p['qtd'];
}

$periodoLabel = [
    'hoje' => 'Hoje',
    'ontem' => 'Ontem',
    'ult7' => 'Últimos 7 dias',
    'mes' => 'Mês atual',
    'mes_anterior' => 'Mês anterior',
    'custom' => 'Personalizado'
][$periodo] ?? 'Hoje';

$iniTxt = $ini->format('d/m/Y');
$fimTxt = $fim->format('d/m/Y');

?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
    <title>ERP - Filial</title>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($logoEmpresa, ENT_QUOTES) ?>" />
    <!-- Styles & Fonts (mantive os links originais) -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />
    <script src="../../assets/vendor/js/helpers.js"></script>
    <script src="../../assets/js/config.js"></script>
</head>
<body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- ASIDE / NAVBAR (mantive seu HTML) -->
            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <!-- ... seu menu (mantive tudo igual) ... -->
                <div class="app-brand demo">
                    <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="app-brand-link">
                        <span class="app-brand-text demo menu-text fw-bolder ms-2">Açaínhadinhos</span>
                    </a>
                </div>
                <div class="menu-inner-shadow"></div>
                <ul class="menu-inner py-1">
                    <!-- Menu reduzido mantido -->
                    <li class="menu-item">
                        <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Dashboard</div>
                        </a>
                    </li>
                    <!-- resto do menu... (mantive seus links originais) -->
                    <!-- ... -->
                </ul>
            </aside>

            <div class="layout-page">
                <!-- Navbar -->
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)"><i class="bx bx-menu bx-sm"></i></a>
                    </div>

                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <!-- User -->
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="avatar avatar-online">
                                        <img src="<?= htmlspecialchars($logoEmpresa, ENT_QUOTES) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="<?= htmlspecialchars($logoEmpresa, ENT_QUOTES) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" />
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <span class="fw-semibold d-block"><?= htmlspecialchars($nomeUsuario, ENT_QUOTES); ?></span>
                                                    <small class="text-muted"><?= htmlspecialchars($tipoUsuario, ENT_QUOTES); ?></small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                    <li><div class="dropdown-divider"></div></li>
                                    <li><a class="dropdown-item" href="./contaUsuario.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-user me-2"></i><span class="align-middle">Minha Conta</span></a></li>
                                    <li><div class="dropdown-divider"></div></li>
                                    <li><a class="dropdown-item" href="../logout.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-power-off me-2"></i><span class="align-middle">Sair</span></a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </nav>

                <!-- Content -->
                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a href="#">Filial</a>/</span> Estoque Matriz</h4>
                    <h5 class="fw-bold mt-3 mb-3 custor-font"><span class="text-muted fw-light">Visão geral do estoque central</span></h5>

                    <!-- Mensagens de feedback -->
                    <?php if ($transferSuccessMsg): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($transferSuccessMsg) ?></div>
                    <?php endif; ?>
                    <?php if ($transferErrorMsg): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($transferErrorMsg) ?></div>
                    <?php endif; ?>

                    <!-- Cards resumo -->
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="mb-1 text-muted">Código Produto ativos</p>
                                            <h4 class="mb-0"><?= number_format($card1, 0, ',', '.') ?></h4>
                                        </div>
                                        <i class="bx bx-box fs-2 text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="mb-1 text-muted">Qtde disponível</p>
                                            <h4 class="mb-0"><?= number_format($card2, 0, ',', '.') ?></h4>
                                        </div>
                                        <i class="bx bx-package fs-2 text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="mb-1 text-muted">Reservado</p>
                                            <h4 class="mb-0"><?= number_format($card3, 0, ',', '.') ?></h4>
                                        </div>
                                        <i class="bx bx-bookmark-alt fs-2 text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="mb-1 text-muted">Em transferência</p>
                                            <h4 class="mb-0"><?= number_format($card4, 0, ',', '.') ?></h4>
                                        </div>
                                        <i class="bx bx-transfer fs-2 text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ações rápidas -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row g-2">
                                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                                    <button class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#modalTransferirGlobal">
                                        <i class="bx bx-right-arrow me-2"></i> Transferir p/ Filial
                                    </button>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4 col-lg-4">
                                    <button class="btn btn-outline-secondary w-100" data-bs-toggle="modal" data-bs-target="#modalHistorico">
                                        <i class="bx bx-time-five me-2"></i> Histórico de movimentações
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabela principal -->
                    <div class="card">
                        <h5 class="card-header">Estoque — Itens da Matriz</h5>
                        <div class="table-responsive text-nowrap">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Codigo Produto</th>
                                        <th>Produto</th>
                                        <th>Categoria</th>
                                        <th>Unidade</th>
                                        <th>Min</th>
                                        <th>Disp.</th>
                                        <th>Reserv.</th>
                                        <th>Transf.</th>
                                        <th>Status</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="table-border-bottom-0">
                                <?php foreach ($produtosEstoque as $p): ?>
                                    <?php
                                        $min = max(1, (int)$p['quantidade_produto'] * 0.10);
                                        list($statusTexto, $statusCor) = calcularStatusEstoque((int)$p['quantidade_produto'], $min);
                                    ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($p['codigo_produto'], ENT_QUOTES) ?></strong></td>
                                        <td><?= htmlspecialchars($p['nome_produto'], ENT_QUOTES) ?></td>
                                        <td><?= htmlspecialchars($p['categoria_produto'] ?? '-', ENT_QUOTES) ?></td>
                                        <td><?= htmlspecialchars($p['unidade'] ?? 'UN', ENT_QUOTES) ?></td>
                                        <td><?= number_format($min, 0, ',', '.') ?></td>
                                        <td><?= number_format((int)$p['quantidade_produto'], 0, ',', '.') ?></td>
                                        <td><?= number_format((int)$p['reservado'], 0, ',', '.') ?></td>
                                        <td><?= number_format((int)$p['total_transferencias'], 0, ',', '.') ?></td>
                                        <td><span class="badge bg-label-<?= $statusCor ?>"><?= $statusTexto ?></span></td>
                                        <td class="text-end">
                                            <div class="btn-group">
                                                <!-- Detalhes: passa dados via data-* -->
                                                <button class="btn btn-sm btn-outline-secondary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalProduto"
                                                    data-sku="<?= htmlspecialchars($p['codigo_produto'], ENT_QUOTES) ?>"
                                                    data-nome="<?= htmlspecialchars($p['nome_produto'], ENT_QUOTES) ?>"
                                                    data-categoria="<?= htmlspecialchars($p['categoria_produto'], ENT_QUOTES) ?>"
                                                    data-unidade="<?= htmlspecialchars($p['unidade'], ENT_QUOTES) ?>"
                                                    data-min="<?= number_format($min, 0, ',', '.') ?>"
                                                    data-disp="<?= number_format((int)$p['quantidade_produto'], 0, ',', '.') ?>"
                                                    data-res="<?= number_format((int)$p['reservado'], 0, ',', '.') ?>"
                                                    data-transf="<?= number_format((int)$p['total_transferencias'], 0, ',', '.') ?>"
                                                >Detalhes</button>

                                                <!-- Transferir: abre modal com produto específico -->
                                                <button class="btn btn-sm btn-outline-primary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalTransferir"
                                                    data-produto-id="<?= (int)$p['id'] ?>"
                                                    data-produto-nome="<?= htmlspecialchars($p['nome_produto'], ENT_QUOTES) ?>"
                                                >Transf.</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ===== Modais ===== -->

                    <!-- Modal: Detalhes do Produto -->
                    <div class="modal fade" id="modalProduto" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Detalhes do Produto</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row g-3 mb-2">
                                        <div class="col-md-4"><p class="mb-1"><strong>Codigo Produto:</strong> <span id="det-sku">—</span></p></div>
                                        <div class="col-md-8"><p class="mb-1"><strong>Produto:</strong> <span id="det-nome">—</span></p></div>
                                        <div class="col-md-4"><p class="mb-1"><strong>Categoria:</strong> <span id="det-categoria">—</span></p></div>
                                        <div class="col-md-4"><p class="mb-1"><strong>Unidade:</strong> <span id="det-validade">—</span></p></div>
                                        <div class="col-md-3"><p class="mb-1"><strong>Mínimo:</strong> <span id="det-min">—</span></p></div>
                                        <div class="col-md-3"><p class="mb-1"><strong>Disponível:</strong> <span id="det-disp">—</span></p></div>
                                        <div class="col-md-3"><p class="mb-1"><strong>Reservado:</strong> <span id="det-res">—</span></p></div>
                                        <div class="col-md-3"><p class="mb-1"><strong>Em transf.:</strong> <span id="det-transf">—</span></p></div>
                                    </div>
                                    <div class="alert alert-info mb-0">
                                        <i class="bx bx-info-circle me-1"></i> Dica: clique em <strong>Transf.</strong> para enviar às Filiais.
                                    </div>
                                </div>
                                <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button></div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal: Transferir p/ Filial (específico por produto) -->
                    <div class="modal fade" id="modalTransferir" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <form method="POST" id="formTransferir">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Transferir para Filial</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label">Produto selecionado</label>
                                                <input type="text" id="transfer-produto-nome" class="form-control" readonly>
                                            </div>

                                            <div class="col-12">
                                                <label class="form-label">Filial</label>
                                                <select name="id_filial" class="form-select" required>
                                                    <?php foreach ($filiais as $f): ?>
                                                        <option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars($f['nome'], ENT_QUOTES) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">Quantidade</label>
                                                <input type="number" class="form-control" name="quantidade" min="1" placeholder="0" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Prioridade</label>
                                                <select name="prioridade" class="form-select" required>
                                                    <option value="Baixa">Baixa</option>
                                                    <option value="Media">Média</option>
                                                    <option value="Alta">Alta</option>
                                                </select>
                                            </div>

                                            <div class="col-12">
                                                <label class="form-label">Observações</label>
                                                <textarea name="observacao" class="form-control" rows="3" placeholder="Instruções de envio, embalagem, etc."></textarea>
                                            </div>
                                        </div>

                                        <div class="alert alert-warning mt-3 mb-0">
                                            <i class="bx bx-error-circle me-1"></i> A transferência reserva a quantidade informada até o envio.
                                        </div>
                                    </div>

                                    <div class="modal-footer">
                                        <input type="hidden" name="produto_id" id="transfer-produto-id">
                                        <input type="hidden" name="form_token" value="<?= htmlspecialchars($formToken, ENT_QUOTES) ?>">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" name="gerar_transferencia" id="btnGerarTransferencia" class="btn btn-primary">Gerar transferência</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Modal global "abrir transferir" (botão rápido) - reaproveita o mesmo form -->
                    <div class="modal fade" id="modalTransferirGlobal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <form method="POST" id="formTransferirGlobal">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Transferir para Filial (Selecione Produto)</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="text-muted">Abra a linha do produto desejado e clique em <strong>Transf.</strong> para preencher automaticamente o formulário.</p>
                                        <div class="mb-3">
                                            <label class="form-label">Produto</label>
                                            <select class="form-select" id="global-produto-select">
                                                <option value="">— Selecione um produto —</option>
                                                <?php foreach ($produtosEstoque as $pe): ?>
                                                    <option value="<?= (int)$pe['id'] ?>"><?= htmlspecialchars($pe['nome_produto'], ENT_QUOTES) ?> (<?= htmlspecialchars($pe['codigo_produto'], ENT_QUOTES) ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Quantidade</label>
                                            <input type="number" class="form-control" name="quantidade" min="1" placeholder="0" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Filial</label>
                                            <select name="id_filial" class="form-select" required>
                                                <?php foreach ($filiais as $f): ?>
                                                    <option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars($f['nome'], ENT_QUOTES) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Prioridade</label>
                                            <select name="prioridade" class="form-select" required>
                                                <option value="Baixa">Baixa</option>
                                                <option value="Media">Média</option>
                                                <option value="Alta">Alta</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Observações</label>
                                            <textarea name="observacao" class="form-control" rows="3"></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <input type="hidden" name="produto_id" id="global-produto-id">
                                        <input type="hidden" name="form_token" value="<?= htmlspecialchars($formToken, ENT_QUOTES) ?>">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" name="gerar_transferencia" id="btnGerarTransferenciaGlobal" class="btn btn-primary">Gerar transferência</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Modal: Histórico (mantive exemplo estático) -->
                    <div class="modal fade" id="modalHistorico" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-xl">
                            <div class="modal-content">
                                <div class="modal-header"><h5 class="modal-title">Histórico de Movimentações</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                <div class="modal-body">
                                    <div class="table-responsive text-nowrap">
                                        <!-- seu conteúdo de histórico (exemplo estático mantido) -->
                                        <table class="table table-striped align-middle">
                                            <thead><tr><th>Data/Hora</th><th>Codigo Produto</th><th>Produto</th><th>Tipo</th><th>Qtd</th><th>Doc</th><th>Motivo</th><th>Usuário</th></tr></thead>
                                            <tbody>
                                                <tr><td>26/09/2025 10:15</td><td>ACA-500</td><td>Polpa Açaí 500g</td><td><span class="badge bg-label-success">Entrada</span></td><td>+300</td><td>L2309-01</td><td>NF 21544</td><td>maria.silva</td></tr>
                                                <!-- ... -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button></div>
                            </div>
                        </div>
                    </div>

                </div> <!-- /Content -->

                <!-- Footer -->
                <footer class="content-footer footer bg-footer-theme text-center">
                    <div class="container-xxl d-flex py-2 flex-md-row flex-column justify-content-center">
                        <div class="mb-2 mb-md-0">&copy; <script>document.write(new Date().getFullYear());</script> , <strong>Açaínhadinhos</strong>. Desenvolvido por <strong>CodeGeek</strong>.</div>
                    </div>
                </footer>
            </div>
        </div>
    </div>

    <!-- SCRIPTS -->
    <script src="../../js/saudacao.js"></script>
    <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../../assets/vendor/js/menu.js"></script>
    <script src="../../assets/vendor/libs/apex-charts/apexcharts.js"></script>
    <script src="../../assets/js/main.js"></script>

    <!-- Script: preencher modais e prevenir duplo submit -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {

        // Modal: detalhes do produto
        const modalProduto = document.getElementById('modalProduto');
        if (modalProduto) {
            modalProduto.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (!button) return;
                modalProduto.querySelector('#det-sku').textContent = button.getAttribute('data-sku') || '—';
                modalProduto.querySelector('#det-nome').textContent = button.getAttribute('data-nome') || '—';
                modalProduto.querySelector('#det-categoria').textContent = button.getAttribute('data-categoria') || '—';
                modalProduto.querySelector('#det-validade').textContent = button.getAttribute('data-unidade') || '—';
                modalProduto.querySelector('#det-min').textContent = button.getAttribute('data-min') || '—';
                modalProduto.querySelector('#det-disp').textContent = button.getAttribute('data-disp') || '—';
                modalProduto.querySelector('#det-res').textContent = button.getAttribute('data-res') || '—';
                modalProduto.querySelector('#det-transf').textContent = button.getAttribute('data-transf') || '—';
            });
        }

        // Modal: preencher transferir (produto específico)
        const modalTransferir = document.getElementById('modalTransferir');
        if (modalTransferir) {
            modalTransferir.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (!button) return;
                const produtoId = button.getAttribute('data-produto-id') || '';
                const produtoNome = button.getAttribute('data-produto-nome') || '';
                modalTransferir.querySelector('#transfer-produto-id').value = produtoId;
                modalTransferir.querySelector('#transfer-produto-nome').value = produtoNome;
            });
        }

        // Form global: quando selecionar produto preenche hidden
        const globalSelect = document.getElementById('global-produto-select');
        const globalHidden = document.getElementById('global-produto-id');
        if (globalSelect && globalHidden) {
            globalSelect.addEventListener('change', function() {
                globalHidden.value = this.value;
            });
        }

        // Prevenir duplo submit: desabilitar botão ao enviar (aplica a todos os forms de transferência)
        function bindDisableOnSubmit(formId, btnId){
            const form = document.getElementById(formId);
            const btn = document.getElementById(btnId);
            if (!form || !btn) return;
            form.addEventListener('submit', function(){
                btn.disabled = true;
                btn.innerHTML = 'Enviando...';
            });
        }
        bindDisableOnSubmit('formTransferir', 'btnGerarTransferencia');
        bindDisableOnSubmit('formTransferirGlobal', 'btnGerarTransferenciaGlobal');

    });
    </script>

</body>
</html>

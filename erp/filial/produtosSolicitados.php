<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
ob_start();
date_default_timezone_set('America/Manaus');

/* =========================
   DETECÇÃO DE ROTA AJAX
   ========================= */
$isAjax = (
  (isset($_GET['acao']) && $_GET['acao'] === 'detalhes') ||
  (isset($_POST['acao']) && in_array($_POST['acao'], ['aprovar','reprovar'], true))
);
if ($isAjax) {
    // para rotas ajax, não mostrar notices na resposta
    ini_set('display_errors', '0');
}

/* =========================
   CONTEXTO / AUTENTICAÇÃO
   ========================= */
$idSelecionado = $_GET['id'] ?? '';
if (!$idSelecionado) {
    header("Location: .././login.php");
    exit;
}

if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id'])
) {
    header("Location: .././login.php?id=" . urlencode($idSelecionado));
    exit;
}

/* =========================
   CONEXÃO
   ========================= */
require_once __DIR__ . '/../../assets/php/conexao.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo "Erro: conexão indisponível.";
    exit;
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* =========================
   HELPERS
   ========================= */
if (!function_exists('json_exit')) {
    function json_exit(array $payload, int $statusCode = 200): void {
        while (ob_get_level()) { @ob_end_clean(); }
        ini_set('display_errors', '0');
        header_remove('X-Powered-By');
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        http_response_code($statusCode);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
if (!function_exists('h'))   { function h(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('dtbr')){ function dtbr(?string $dt): string { if(!$dt) return '—'; $t=strtotime($dt); return $t?date('d/m/Y H:i',$t):'—'; } }

/* =========================
   DESCOBERTA DE COLUNAS (itens)
   ========================= */
if (!function_exists('descobrirColunasItens')) {
    function descobrirColunasItens(PDO $pdo): array {
        $temItens = false; $cols = [];
        try { $rs=$pdo->query("SHOW TABLES LIKE 'solicitacoes_b2b_itens'"); $temItens=(bool)$rs->fetchColumn(); } catch(Throwable $e){}
        if (!$temItens) return [false,null,null,null];
        try {
            $st=$pdo->query("SHOW COLUMNS FROM solicitacoes_b2b_itens");
            while($c=$st->fetch(PDO::FETCH_ASSOC)) $cols[]=$c['Field'];
        } catch(Throwable $e){ return [false,null,null,null]; }

        $colCod=null; foreach(['produto_codigo','codigo_produto','sku','codigo','qr_code','cod_produto','id_produto'] as $c) if(in_array($c,$cols,true)){ $colCod=$c; break; }
        $colQtd=null; foreach(['quantidade','qtd','qtde','quantidade_solicitada','qtd_solicitada'] as $c) if(in_array($c,$cols,true)){ $colQtd=$c; break; }
        $colNome=null; foreach(['nome_produto','descricao_produto','produto_nome'] as $c) if(in_array($c,$cols,true)){ $colNome=$c; break; }

        if(!$colCod && !$colQtd && !$colNome) return [false,null,null,null];
        return [true,$colCod,$colQtd,$colNome];
    }
}

/* =========================
   DETALHES (HTML parcial)
   ========================= */
if (!function_exists('render_detalhes_pedido')) {
    function render_detalhes_pedido(PDO $pdo, string $empresaIdMatriz, int $id): string {
        [$temItens,$colCod,$colQtd,$colNome] = descobrirColunasItens($pdo);

        $selectItem = "NULL AS item_qr_code, NULL AS item_nome, NULL AS item_qtd";
        $joins = "";
        if ($temItens) {
            $joins .= "
              LEFT JOIN (
                SELECT si1.solicitacao_id, MAX(si1.id) AS _pick_id
                FROM solicitacoes_b2b_itens si1
                WHERE si1.solicitacao_id = :id
                GROUP BY si1.solicitacao_id
              ) pick ON pick.solicitacao_id = s.id
              LEFT JOIN solicitacoes_b2b_itens si ON si.id = pick._pick_id
            ";
            if ($colCod) {
                $joins .= " LEFT JOIN estoque e ON e.codigo_produto = si.`{$colCod}` AND e.empresa_id = s.id_matriz ";
                $selectItem = " e.codigo_produto AS item_qr_code, e.nome_produto AS item_nome, ".($colQtd? "si.`{$colQtd}`":"NULL")." AS item_qtd ";
            } elseif ($colNome) {
                $joins .= " LEFT JOIN estoque e ON e.nome_produto = si.`{$colNome}` AND e.empresa_id = s.id_matriz ";
                $selectItem = " e.codigo_produto AS item_qr_code, e.nome_produto AS item_nome, ".($colQtd? "si.`{$colQtd}`":"NULL")." AS item_qtd ";
            } else {
                $selectItem = " NULL AS item_qr_code, NULL AS item_nome, ".($colQtd? "si.`{$colQtd}`":"NULL")." AS item_qtd ";
            }
        }

        $sql = "
          SELECT
            s.id AS pedido_id,
            s.status,
            s.prioridade,
            s.observacao AS obs,
            s.created_at AS criado_em,
            u.nome       AS filial_nome,
            {$selectItem}
          FROM solicitacoes_b2b s
          LEFT JOIN unidades u
            ON u.id = CAST(SUBSTRING_INDEX(s.id_solicitante, '_', -1) AS UNSIGNED)
           AND u.empresa_id = s.id_matriz
          {$joins}
          WHERE s.id_matriz = :empresa
            AND s.id = :id
            AND s.id_solicitante LIKE 'unidade\\_%'
            AND u.tipo = 'filial'
          LIMIT 1
        ";
        $st = $pdo->prepare($sql);
        $st->execute([':empresa'=>$empresaIdMatriz, ':id'=>$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) return "<div class='text-danger'>Não encontrado.</div>";

        ob_start(); ?>
        <div class="row g-3">
          <div class="col-md-6">
            <p><strong>Filial:</strong> <?=h($r['filial_nome'] ?: '—')?></p>
            <p><strong>Qr code:</strong> <?=h($r['item_qr_code'] ?: '—')?></p>
            <p><strong>Produto:</strong> <?=h($r['item_nome'] ?: '—')?></p>
          </div>
          <div class="col-md-6">
            <p><strong>Qtd:</strong> <?= $r['item_qtd']!==null ? (int)$r['item_qtd'] : '—' ?></p>
            <p><strong>Prioridade:</strong> <?=h($r['prioridade'] ?: '—')?></p>
            <p><strong>Status:</strong> <?=h(ucfirst($r['status'] ?: '—'))?></p>
          </div>
          <div class="col-12">
            <p><strong>Observações:</strong> <?=h($r['obs'] ?: '—')?></p>
          </div>
        </div>
        <?php
        return (string)ob_get_clean();
    }
}

/* =========================
   GATEWAY AJAX
   ========================= */
$empresaIdMatrizAjax = $_SESSION['empresa_id'] ?? '';

/* GET /detalhes — usa pedido_id para não colidir com id=empresa na URL */
if (isset($_GET['acao']) && $_GET['acao'] === 'detalhes') {
    while (ob_get_level()) { @ob_end_clean(); }
    ini_set('display_errors','0');

    if (!$empresaIdMatrizAjax) { http_response_code(401); echo "<div class='text-danger'>Sessão expirada.</div>"; exit; }

    $pedidoId = (int)($_GET['pedido_id'] ?? 0);
    if ($pedidoId <= 0) { http_response_code(400); echo "<div class='text-danger'>ID inválido.</div>"; exit; }

    echo render_detalhes_pedido($pdo, $empresaIdMatrizAjax, $pedidoId);
    exit;
}

/* POST /aprovar | /reprovar */
if (isset($_POST['acao']) && in_array($_POST['acao'], ['aprovar','reprovar'], true)) {
    $acao = $_POST['acao'];
    $pedidoId = (int)($_POST['pedido_id'] ?? 0);
    $motivo = trim((string)($_POST['motivo'] ?? ''));

    if (!$empresaIdMatrizAjax) json_exit(['ok'=>false,'msg'=>'Sessão expirada (empresa).']);
    if ($pedidoId <= 0)        json_exit(['ok'=>false,'msg'=>'ID inválido.']);

    try {
        $cols = [];
        try {
            $cst = $pdo->query("SHOW COLUMNS FROM solicitacoes_b2b");
            while ($c = $cst->fetch(PDO::FETCH_ASSOC)) $cols[$c['Field']] = true;
        } catch (Throwable $e) {}

        $colObservacao = isset($cols['observacao']) ? 'observacao' : (isset($cols['observao']) ? 'observao' : null);

        $st = $pdo->prepare("
            SELECT id, status,
                   ".(isset($cols['observacao']) ? "observacao" : "NULL AS observacao").",
                   ".(isset($cols['observao'])   ? "observao"   : "NULL AS observao")."
              FROM solicitacoes_b2b
             WHERE id = :id AND id_matriz = :emp
             LIMIT 1
        ");
        $st->execute([':id'=>$pedidoId, ':emp'=>$empresaIdMatrizAjax]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) json_exit(['ok'=>false,'msg'=>'Solicitação não encontrada para esta empresa.']);

        $statusAtual = strtolower((string)($row['status'] ?? ''));
        if ($acao==='aprovar'  && $statusAtual==='aprovada')  json_exit(['ok'=>true,'msg'=>'Já aprovada.','status'=>'aprovada']);
        if ($acao==='reprovar' && $statusAtual==='reprovada') json_exit(['ok'=>true,'msg'=>'Já reprovada.','status'=>'reprovada']);

        $novo   = ($acao==='aprovar' ? 'aprovada' : 'reprovada');
        $set    = "status = :novo";
        $params = [':novo'=>$novo, ':id'=>$pedidoId, ':emp'=>$empresaIdMatrizAjax];

        if (isset($cols['updated_at'])) $set .= ", updated_at = NOW()";
        if ($acao === 'aprovar') {
            if (isset($cols['aprovado_em'])) $set .= ", aprovado_em = NOW()";
            elseif (isset($cols['aprovada_em'])) $set .= ", aprovada_em = NOW()";
        }
        if ($acao === 'reprovar' && $motivo !== '' && $colObservacao) {
            $obsAtual = trim((string)($row[$colObservacao] ?? ''));
            if ($obsAtual !== '') $obsAtual .= " | ";
            $obsAtual .= "Reprovado: " . $motivo;
            $set .= ", {$colObservacao} = :obs";
            $params[':obs'] = $obsAtual;
        }

        $up = $pdo->prepare("UPDATE solicitacoes_b2b SET $set WHERE id=:id AND id_matriz=:emp LIMIT 1");
        $up->execute($params);

        json_exit(['ok'=>true,'msg'=>'Atualizado com sucesso.','status'=>$novo]);
    } catch (Throwable $e) {
        json_exit(['ok'=>false,'msg'=>'Falha no processamento.']);
    }
}

/* =========================
   DADOS DO USUÁRIO (navbar)
   ========================= */
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id  = (int)$_SESSION['usuario_id'];

try {
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $nomeUsuario = $usuario['usuario'] ?? 'Usuário';
        $tipoUsuario = ucfirst((string)($usuario['nivel'] ?? 'Comum'));
    } else {
        echo "<script>alert('Usuário não encontrado.'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar usuário: " . htmlspecialchars($e->getMessage()) . "'); history.back();</script>";
    exit;
}

/* =========================
   CONTROLE DE ACESSO
   ========================= */
$acessoPermitido   = false;
$idEmpresaSession  = $_SESSION['empresa_id'];
$tipoSession       = $_SESSION['tipo_empresa'];

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

/* =========================
   LOGO DA EMPRESA
   ========================= */
try {
    $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1");
    $stmt->bindParam(':id_selecionado', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);

    $logoEmpresa = (!empty($empresaSobre) && !empty($empresaSobre['imagem']))
        ? "../../assets/img/empresa/" . $empresaSobre['imagem']
        : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png";
}

/* ==========================================================
   >>>>>>>  FILTROS DE STATUS  <<<<<<<
   - Somente: pendente | aprovada | reprovada | (todos) que inclui APENAS esses 3
   ========================================================== */
$status = $_GET['status'] ?? '';           // '', 'pendente', 'aprovada', 'reprovada'
$de     = trim($_GET['de'] ?? '');
$ate    = trim($_GET['ate'] ?? '');
$q      = trim($_GET['q'] ?? '');

$validStatus = ['pendente','aprovada','reprovada'];

$where  = [];
$params = [':empresa' => $idEmpresaSession];

$where[] = "s.id_matriz = :empresa";
$where[] = "s.id_solicitante LIKE 'unidade\\_%'";
$where[] = "u.tipo = 'filial'";

/* status: se vazio => incluir APENAS os três */
if ($status !== '' && in_array($status, $validStatus, true)) {
    $where[] = "s.status = :st";
    $params[':st'] = $status;
} else {
    $where[] = "s.status IN ('pendente','aprovada','reprovada')";
}

/* período por created_at (opcional) */
if ($de !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $de)) {
    $where[] = "DATE(s.created_at) >= :de";
    $params[':de'] = $de;
}
if ($ate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) {
    $where[] = "DATE(s.created_at) <= :ate";
    $params[':ate'] = $ate;
}

/* busca livre (opcional) */
if ($q !== '') {
    $where[] = "(u.nome LIKE :q OR s.id_solicitante LIKE :q OR s.observacao LIKE :q)";
    $params[':q'] = "%{$q}%";
}

/* =========================
   LISTAGEM
   ========================= */
[$temItens,$colCod,$colQtd,$colNome] = descobrirColunasItens($pdo);

$selectBase = "
  s.id AS pedido_id,
  s.status,
  s.prioridade AS item_prioridade,
  s.observacao AS obs,
  s.created_at AS criado_em,
  u.nome       AS filial_nome
";
$selectItem = "NULL AS item_qr_code, NULL AS item_nome, NULL AS item_qtd";
$joins = "LEFT JOIN unidades u
            ON u.id = CAST(SUBSTRING_INDEX(s.id_solicitante, '_', -1) AS UNSIGNED)
           AND u.empresa_id = s.id_matriz";

if ($temItens) {
  $joins .= "
    LEFT JOIN (
      SELECT si1.solicitacao_id, MAX(si1.id) AS _pick_id
      FROM solicitacoes_b2b_itens si1
      GROUP BY si1.solicitacao_id
    ) pick ON pick.solicitacao_id = s.id
    LEFT JOIN solicitacoes_b2b_itens si ON si.id = pick._pick_id
  ";
  if ($colCod) {
    $joins .= " LEFT JOIN estoque e ON e.codigo_produto = si.`{$colCod}` AND e.empresa_id = s.id_matriz ";
    $selectItem = " e.codigo_produto AS item_qr_code, e.nome_produto AS item_nome, ".($colQtd?"si.`{$colQtd}`":"NULL")." AS item_qtd ";
  } elseif ($colNome) {
    $joins .= " LEFT JOIN estoque e ON e.nome_produto = si.`{$colNome}` AND e.empresa_id = s.id_matriz ";
    $selectItem = " e.codigo_produto AS item_qr_code, e.nome_produto AS item_nome, ".($colQtd?"si.`{$colQtd}`":"NULL")." AS item_qtd ";
  } else {
    $selectItem = " NULL AS item_qr_code, NULL AS item_nome, ".($colQtd?"si.`{$colQtd}`":"NULL")." AS item_qtd ";
  }
}

$whereSql = implode(' AND ', $where);

$sql = "
  SELECT {$selectBase}, {$selectItem}
  FROM solicitacoes_b2b s
  {$joins}
  WHERE {$whereSql}
  ORDER BY s.created_at DESC, s.id DESC
  LIMIT 300
";
$stmt = $pdo->prepare($sql);
foreach ($params as $k=>$v) $stmt->bindValue($k, $v);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ERP - Filial</title>

    <link rel="icon" type="image/x-icon" href="<?= h($logoEmpresa) ?>" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />
    <script src="../../assets/vendor/js/helpers.js"></script>
    <script src="../../assets/js/config.js"></script>
    <style>.status-badge{text-transform:capitalize}</style>
</head>

<body>
y>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- Menu -->

            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="app-brand-link">

                        <span class="app-brand-text demo menu-text fw-bolder ms-2">Açaínhadinhos</span>
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

                    <!-- Administração de Filiais -->
                    <li class="menu-header small text-uppercase">
                        <span class="menu-header-text">Administração Filiais</span>
                    </li>

                    <!-- Adicionar Filial -->
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-building"></i>
                            <div data-i18n="Adicionar">Filiais</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./filialAdicionada.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Filiais">Adicionadas</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-briefcase"></i>
                            <div data-i18n="B2B">B2B - Matriz</div>
                        </a>
                        <ul class="menu-sub active">
                            <!-- Contas das Filiais -->
                            <li class="menu-item">
                                <a href="./contasFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Pagamentos Solic.</div>
                                </a>
                            </li>

                            <!-- Produtos solicitados pelas filiais -->
                            <li class="menu-item">
                                <a href="./produtosSolicitados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Produtos Solicitados</div>
                                </a>
                            </li>

                            <!-- Produtos enviados pela matriz -->
                            <li class="menu-item">
                                <a href="./produtosEnviados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Produtos Enviados</div>
                                </a>
                            </li>

                            <!-- Transferências em andamento -->
                            <li class="menu-item">
                                <a href="./transferenciasPendentes.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Transf. Pendentes</div>
                                </a>
                            </li>

                            <!-- Histórico de transferências -->
                            <li class="menu-item">
                                <a href="./historicoTransferencias.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Histórico Transf.</div>
                                </a>
                            </li>

                            <!-- Gestão de Estoque Central -->
                            <li class="menu-item active">
                                <a href="./estoqueMatriz.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Estoque Matriz</div>
                                </a>
                            </li>

                            <!-- Relatórios e indicadores B2B -->
                            <li class="menu-item">
                                <a href="./relatoriosB2B.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Relatórios B2B</div>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- Relatórios -->
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-bar-chart-alt-2"></i>
                            <div data-i18n="Relatorios">Relatórios</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="./VendasFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Vendas">Vendas por Filial</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./MaisVendidosFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="MaisVendidos">Mais Vendidos</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./vendasPeriodoFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Pedidos">Vendas por Período</div>
                                </a>
                            </li>

                        </ul>
                    </li>

                    <!--END DELIVERY-->

                    <!-- Misc -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
                    <li class="menu-item">
                        <a href="../rh/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-group"></i>
                            <div data-i18n="Authentications">RH</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../financas/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-dollar"></i>
                            <div data-i18n="Authentications">Finanças</div>
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
                        <a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-box"></i>
                            <div data-i18n="Authentications">Estoque</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../franquia/index.php?id=principal_1" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-store"></i>
                            <div data-i18n="Authentications">Franquias</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-group"></i>
                            <div data-i18n="Authentications">Usuários </div>
                        </a>
                    </li>
                    <li class="menu-item mb-5">
                        <a href="https://wa.me/92991515710" target="_blank" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-support"></i>
                            <div data-i18n="Basic">Suporte</div>
                        </a>
                    </li>
                    <!--/MISC-->
                </ul>
            </aside>
            <!-- / Menu -->

            <!-- Layout container -->
            <div class="layout-page">
                <!-- Navbar -->

                <nav
                    class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
                    id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                            <i class="bx bx-menu bx-sm"></i>
                        </a>
                    </div>

                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <!-- Search -->
                        <div class="navbar-nav align-items-center">
                            <div class="nav-item d-flex align-items-center">
                            </div>
                        </div>
                        <!-- /Search -->

                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <!-- User -->
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="avatar avatar-online">
                                        <img src="<?= htmlspecialchars($logoEmpresa, ENT_QUOTES) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownUser">
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
                                    <li>
                                        <div class="dropdown-divider"></div>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="./contaUsuario.php?id=<?= urlencode($idSelecionado); ?>">
                                            <i class="bx bx-user me-2"></i>
                                            <span class="align-middle">Minha Conta</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <i class="bx bx-cog me-2"></i>
                                            <span class="align-middle">Configurações</span>
                                        </a>
                                    </li>
                                    <li>
                                        <div class="dropdown-divider"></div>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="../logout.php?id=<?= urlencode($idSelecionado); ?>">
                                            <i class="bx bx-power-off me-2"></i>
                                            <span class="align-middle">Sair</span>
                                        </a>
                                    </li>
                                </ul>
                            </li>
                            <!--/ User -->
                        </ul>

                    </div>
                </nav>

                <!-- / Navbar -->

      <div class="container-xxl flex-grow-1 container-p-y">
        <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a href="#">Filiais</a>/</span>Produtos Solicitados</h4>
        <h5 class="fw-bold mt-3 mb-3"><span class="text-muted fw-light">Pedidos de produtos enviados pelas Filiais</span></h5>

        <!-- ===== Filtros ===== -->
        <form class="card mb-3" method="get" id="filtroForm">
          <input type="hidden" name="id" value="<?= h($idSelecionado) ?>">
          <div class="card-body">
            <div class="row g-3 align-items-end">
              <div class="col-12 col-md-auto">
                <label class="form-label mb-1">Status</label>
                <select class="form-select form-select-sm" name="status">
                  <option value="">Todos (pendente/aprovada/reprovada)</option>
                  <option value="pendente"  <?= $status==='pendente'  ? 'selected' : '' ?>>Pendente</option>
                  <option value="aprovada"  <?= $status==='aprovada'  ? 'selected' : '' ?>>Aprovada</option>
                  <option value="reprovada" <?= $status==='reprovada' ? 'selected' : '' ?>>Reprovada</option>
                </select>
              </div>

              <div class="col-12 col-md-auto">
                <label class="form-label mb-1">De</label>
                <input type="date" class="form-control form-control-sm" name="de" value="<?= h($de) ?>">
              </div>

              <div class="col-12 col-md-auto">
                <label class="form-label mb-1">Até</label>
                <input type="date" class="form-control form-control-sm" name="ate" value="<?= h($ate) ?>">
              </div>

              <div class="col-12 col-md">
                <label class="form-label mb-1">Buscar</label>
                <input type="text" class="form-control form-control-sm" name="q" value="<?= h($q) ?>"
                       placeholder="Filial, id_solicitante (ex.: unidade_3) ou observação…">
              </div>

              <div class="col-12 col-md-auto d-flex gap-2">
                <button class="btn btn-sm btn-primary" type="submit"><i class="bx bx-filter-alt me-1"></i> Filtrar</button>
                <a class="btn btn-sm btn-outline-secondary" href="?id=<?= urlencode($idSelecionado) ?>"><i class="bx bx-eraser me-1"></i> Limpar</a>
              </div>
            </div>
            <div class="small text-muted mt-2">Resultados: <strong><?= count($rows) ?></strong> registros</div>
          </div>
        </form>

        <!-- Tabela -->
        <div class="card">
          <h5 class="card-header">Lista de Produtos Solicitados</h5>
          <div class="table-responsive text-nowrap">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th># Pedido</th>
                  <th>Filial</th>
                  <th>Qr code</th>
                  <th>Produto</th>
                  <th>Qtd</th>
                  <th>Prioridade</th>
                  <th>Solicitado em</th>
                  <th>Status</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody class="table-border-bottom-0">
              <?php if (!$rows): ?>
                <tr><td colspan="9" class="text-center text-muted">Nenhum registro encontrado.</td></tr>
              <?php else: foreach ($rows as $r):
                $pedidoId = (int)$r['pedido_id'];
                $qr   = $r['item_qr_code'] ?? null;
                $prod = $r['item_nome'] ?? null;
                $pri  = $r['item_prioridade'] ?: 'media';
                $sts  = strtolower((string)($r['status'] ?? 'pendente')); // pendente | aprovada | reprovada
                $fil  = $r['filial_nome'] ?: '—';
                $dt   = dtbr($r['criado_em']);

                $badgePri = (function($p){
                  $p = strtolower($p);
                  if ($p==='alta')   return '<span class="badge bg-label-danger status-badge">Alta</span>';
                  if ($p==='media'||$p==='média') return '<span class="badge bg-label-warning status-badge">Média</span>';
                  if ($p==='baixa')  return '<span class="badge bg-label-success status-badge">Baixa</span>';
                  return '<span class="badge bg-label-secondary status-badge">'.h(ucfirst($p)).'</span>';
                })($pri);

                $badgeSts = (function($s){
                  return match ($s) {
                    'pendente'  => '<span class="badge bg-label-warning status-badge">Pendente</span>',
                    'aprovada'  => '<span class="badge bg-label-primary status-badge">Aprovada</span>',
                    'reprovada' => '<span class="badge bg-label-danger status-badge">Reprovada</span>',
                    default     => '<span class="badge bg-label-secondary status-badge">'.h(ucfirst($s)).'</span>',
                  };
                })($sts);

                // === Regra de ações ===
                $mostrarAprovarReprovar = ($sts === 'pendente'); // Se aprovada/reprovada => somente Detalhes
              ?>
                <tr data-pedido="<?= $pedidoId ?>">
                  <td># <?= h((string)$pedidoId) ?></td>
                  <td><strong><?= h($fil) ?></strong></td>
                  <td><?= $qr !== null && $qr !== '' ? h($qr) : '—' ?></td>
                  <td><?= $prod !== null && $prod !== '' ? h($prod) : '—' ?></td>
                  <td><?= $r['item_qtd']!==null ? (int)$r['item_qtd'] : '—' ?></td>
                  <td><?= $badgePri ?></td>
                  <td><?= h($dt) ?></td>
                  <td class="td-status"><?= $badgeSts ?></td>
                  <td>
                    <?php if ($mostrarAprovarReprovar): ?>
                      <button class="btn btn-sm btn-outline-primary btn-aprovar"
                              data-bs-toggle="modal" data-bs-target="#modalAtender"
                              data-pedido="<?= $pedidoId ?>">Aprovar</button>

                      <button class="btn btn-sm btn-outline-danger btn-reprovar"
                              data-bs-toggle="modal" data-bs-target="#modalCancelar"
                              data-pedido="<?= $pedidoId ?>">Reprovar</button>
                    <?php endif; ?>

                    <button class="btn btn-sm btn-outline-secondary btn-detalhes"
                            data-bs-toggle="modal" data-bs-target="#modalDetalhes"
                            data-pedido="<?= $pedidoId ?>">Detalhes</button>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Modal Detalhes -->
        <div class="modal fade" id="modalDetalhes" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Detalhes do Pedido</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
              </div>
              <div class="modal-body">
                <div id="detalhesConteudo"><div class="text-muted">Carregando...</div></div>
              </div>
              <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Reprovar -->
        <div class="modal fade" id="modalCancelar" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <form id="formReprovar" class="modal-content" method="post" novalidate>
              <div class="modal-header">
                <h5 class="modal-title">Cancelar (Reprovar) Pedido</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="pedido_id" id="cancelarPedidoId" />
                <label class="form-label">Motivo (opcional)</label>
                <textarea class="form-control" name="motivo" rows="3" placeholder="Descreva o motivo do cancelamento..."></textarea>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Voltar</button>
                <button type="button" id="btnConfirmReprovar" class="btn btn-danger">Confirmar Reprovação</button>
              </div>
            </form>
          </div>
        </div>

        <!-- Modal Aprovar -->
        <div class="modal fade" id="modalAtender" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <form id="formAprovar" class="modal-content" method="post" novalidate>
              <div class="modal-header">
                <h5 class="modal-title">Aprovar Pedido</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="pedido_id" id="aprovarPedidoId" />
                <p class="mb-0 text-muted">Confirma a aprovação deste pedido?</p>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Voltar</button>
                <button type="button" id="btnConfirmAprovar" class="btn btn-primary">Confirmar Aprovação</button>
              </div>
            </form>
          </div>
        </div>

      </div> <!-- /container -->

      <!-- Footer -->
      <footer class="content-footer footer bg-footer-theme text-center">
        <div class="container-xxl d-flex py-2 flex-md-row flex-column justify-content-center">
          <div class="mb-2 mb-md-0">
            &copy;<script>document.write(new Date().getFullYear());</script>, <strong>Açaínhadinhos</strong>.
            Todos os direitos reservados. Desenvolvido por <strong>CodeGeek</strong>.
          </div>
        </div>
      </footer>
      <div class="content-backdrop fade"></div>
    </div>
  </div>
</div>

<!-- Core JS -->
<script src="../../js/saudacao.js"></script>
<script src="../../assets/vendor/libs/jquery/jquery.js"></script>
<script src="../../assets/vendor/libs/popper/popper.js"></script>
<script src="../../assets/vendor/js/bootstrap.js"></script>
<script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
<script src="../../assets/vendor/js/menu.js"></script>

<!-- Vendors JS -->
<script src="../../assets/vendor/libs/apex-charts/apexcharts.js"></script>

<!-- Main JS -->
<script src="../../assets/js/main.js"></script>
<script src="../../assets/js/dashboards-analytics.js"></script>

<script>
(function () {
  // Util: parse JSON seguro
  function parseJsonSafe(txt) { try { return JSON.parse(txt); } catch { return null; } }

  function setRowStatus(pedidoId, novoStatus) {
    const tr = document.querySelector(`tr[data-pedido="${pedidoId}"]`);
    if (!tr) return;
    const tdStatus = tr.querySelector('.td-status');
    let badge = '';
    switch (novoStatus) {
      case 'aprovada':  badge = '<span class="badge bg-label-primary status-badge">Aprovada</span>'; break;
      case 'reprovada': badge = '<span class="badge bg-label-danger status-badge">Reprovada</span>'; break;
      case 'pendente':  badge = '<span class="badge bg-label-warning status-badge">Pendente</span>'; break;
      default:          badge = '<span class="badge bg-label-secondary status-badge">'+(novoStatus||'—')+'</span>';
    }
    if (tdStatus) tdStatus.innerHTML = badge;

    // Se virou aprovada/reprovada, remover Aprovar/Reprovar; manter Detalhes
    if (novoStatus === 'aprovada' || novoStatus === 'reprovada') {
      tr.querySelectorAll('.btn-aprovar, .btn-reprovar').forEach(btn => btn.remove());
    }
  }

  function hideModalById(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (window.bootstrap && bootstrap.Modal && typeof bootstrap.Modal.getOrCreateInstance === 'function') {
      bootstrap.Modal.getOrCreateInstance(el).hide();
    } else {
      el.querySelector('[data-bs-dismiss="modal"]')?.click();
    }
  }

  // Preenche IDs nas modais (delegado para carregar também após filtros)
  document.addEventListener('click', (ev) => {
    const t = ev.target;
    if (t.closest('.btn-aprovar')) {
      const btn = t.closest('.btn-aprovar');
      document.getElementById('aprovarPedidoId').value = btn.dataset.pedido;
    }
    if (t.closest('.btn-reprovar')) {
      const btn = t.closest('.btn-reprovar');
      document.getElementById('cancelarPedidoId').value = btn.dataset.pedido;
    }
  });

  // Botão "Detalhes" — usa URL atual e injeta acao=detalhes&pedido_id=...
  document.addEventListener('click', async (ev) => {
    const target = ev.target.closest('.btn-detalhes');
    if (!target) return;
    const pedidoId = target.dataset.pedido;
    const alvo = document.getElementById('detalhesConteudo');
    if (alvo) alvo.innerHTML = '<div class="text-muted">Carregando...</div>';

    const url = new URL(window.location.href);
    url.searchParams.set('acao', 'detalhes');
    url.searchParams.set('pedido_id', String(pedidoId)); // não colide com ?id=empresa

    try {
      const resp = await fetch(url.toString(), { credentials: 'same-origin' });
      if (!resp.ok) {
        if (alvo) alvo.innerHTML = `<div class="text-danger">Falha ao carregar detalhes (HTTP ${resp.status}).</div>`;
        return;
      }
      const html = await resp.text();
      if (alvo) alvo.innerHTML = html;
    } catch (e) {
      if (alvo) alvo.innerHTML = '<div class="text-danger">Falha de rede ao carregar detalhes.</div>';
      console.error(e);
    }
  });

  async function postAjax(fd) {
    let resp;
    try {
      resp = await fetch(window.location.href, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
    } catch (netErr) {
      alert('Erro de rede. Verifique sua conexão.');
      console.error(netErr);
      return null;
    }
    const txt = await resp.text();
    const j = parseJsonSafe(txt);
    if (!j) { console.error('Resposta não-JSON:', txt); alert(`Resposta inválida do servidor (HTTP ${resp.status}).`); return null; }
    return j;
  }

  // Confirmar Aprovação
  document.getElementById('btnConfirmAprovar')?.addEventListener('click', async (ev) => {
    const btn = ev.currentTarget;
    const form = document.getElementById('formAprovar');
    const pedidoId = form.querySelector('[name="pedido_id"]').value || '';
    if (!pedidoId) { alert('Pedido inválido.'); return; }

    btn.disabled = true; btn.classList.add('disabled');

    const fd = new FormData();
    fd.append('acao', 'aprovar');
    fd.append('pedido_id', pedidoId);

    const j = await postAjax(fd);
    if (j && j.ok) {
      setRowStatus(pedidoId, j.status || 'aprovada');
      hideModalById('modalAtender');
      // Recarrega para refletir paginação/filtro se necessário
      window.location.reload();
    } else {
      alert((j && j.msg) || 'Não foi possível aprovar.');
      btn.disabled = false; btn.classList.remove('disabled');
    }
  });

  // Confirmar Reprovação
  document.getElementById('btnConfirmReprovar')?.addEventListener('click', async (ev) => {
    const btn = ev.currentTarget;
    const form = document.getElementById('formReprovar');
    const pedidoId = form.querySelector('[name="pedido_id"]').value || '';
    const motivo   = form.querySelector('[name="motivo"]').value || '';
    if (!pedidoId) { alert('Pedido inválido.'); return; }

    btn.disabled = true; btn.classList.add('disabled');

    const fd = new FormData();
    fd.append('acao', 'reprovar');
    fd.append('pedido_id', pedidoId);
    fd.append('motivo', motivo);

    const j = await postAjax(fd);
    if (j && j.ok) {
      setRowStatus(pedidoId, j.status || 'reprovada');
      hideModalById('modalCancelar');
      window.location.reload();
    } else {
      alert((j && j.msg) || 'Não foi possível reprovar.');
      btn.disabled = false; btn.classList.remove('disabled');
    }
  });
})();
</script>
</body>
</html>

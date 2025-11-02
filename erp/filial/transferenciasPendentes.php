<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
date_default_timezone_set('America/Manaus');

// ✅ id selecionado
$idSelecionado = $_GET['id'] ?? '';
if (!$idSelecionado) { header("Location: .././login.php"); exit; }

// ✅ sessão
if (
  !isset($_SESSION['usuario_logado']) ||
  !isset($_SESSION['empresa_id']) ||
  !isset($_SESSION['tipo_empresa']) ||
  !isset($_SESSION['usuario_id'])
) {
  header("Location: .././login.php?id=" . urlencode($idSelecionado));
  exit;
}

// ✅ conexão
require '../../assets/php/conexao.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ✅ usuário logado
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id  = (int)$_SESSION['usuario_id'];
try {
  $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
  $stmt->execute([':id'=>$usuario_id]);
  if ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $nomeUsuario = $u['usuario'] ?? 'Usuário';
    $tipoUsuario = ucfirst((string)($u['nivel'] ?? 'Comum'));
  } else {
    echo "<script>alert('Usuário não encontrado.');location.href='.././login.php?id=".urlencode($idSelecionado)."';</script>"; exit;
  }
} catch(PDOException $e){
  echo "<script>alert('Erro ao carregar usuário: ".htmlspecialchars($e->getMessage())."');history.back();</script>"; exit;
}

// ✅ permissão de acesso
$acesso = false;
$idEmp  = $_SESSION['empresa_id'];
$tipo   = $_SESSION['tipo_empresa'];
if (str_starts_with($idSelecionado,'principal_')) $acesso = ($tipo==='principal' && $idEmp==='principal_1');
elseif (str_starts_with($idSelecionado,'filial_')) $acesso = ($tipo==='filial' && $idEmp===$idSelecionado);
elseif (str_starts_with($idSelecionado,'unidade_')) $acesso = ($tipo==='unidade' && $idEmp===$idSelecionado);
elseif (str_starts_with($idSelecionado,'franquia_')) $acesso = ($tipo==='franquia' && $idEmp===$idSelecionado);
if (!$acesso) { echo "<script>alert('Acesso negado!');location.href='.././login.php?id=".urlencode($idSelecionado)."';</script>"; exit; }

// ✅ logo
try {
  $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1");
  $stmt->execute([':id'=>$idSelecionado]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  $logoEmpresa = (!empty($row['imagem'])) ? "../../assets/img/empresa/".$row['imagem'] : "../../assets/img/favicon/logo.png";
} catch(PDOException $e){ $logoEmpresa = "../../assets/img/favicon/logo.png"; }

/* ============================================
   AJAX: detalhes
   ============================================ */
if (isset($_GET['ajax']) && $_GET['ajax']==='detalhes') {
  header('Content-Type: application/json; charset=utf-8');
  $sid = (int)($_GET['solicitacao_id'] ?? 0);
  if ($sid<=0) { echo json_encode(['ok'=>false,'erro'=>'ID inválido']); exit; }

  try {
    $cab = $pdo->prepare("
      SELECT s.id, s.id_matriz, s.id_solicitante, s.status, s.observacao,
             s.created_at, s.aprovada_em, s.enviada_em, s.entregue_em,
             u.nome AS filial_nome
        FROM solicitacoes_b2b s
        JOIN unidades u
          ON u.id = CAST(REPLACE(s.id_solicitante,'unidade_','') AS UNSIGNED)
       WHERE s.id = :sid LIMIT 1
    ");
    $cab->execute([':sid'=>$sid]);
    $cabecalho = $cab->fetch(PDO::FETCH_ASSOC);

    $it = $pdo->prepare("
      SELECT COALESCE(i.codigo_produto,'') codigo_produto,
             COALESCE(i.nome_produto,'')   nome_produto,
             COALESCE(i.quantidade,0)      quantidade,
             COALESCE(i.unidade,'UN')      unidade
        FROM solicitacoes_b2b_itens i
       WHERE i.solicitacao_id = :sid
       ORDER BY i.id ASC
    ");
    $it->execute([':sid'=>$sid]);
    $itens = $it->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['ok'=>true,'cabecalho'=>$cabecalho,'itens'=>$itens]);
  } catch(PDOException $e){ echo json_encode(['ok'=>false,'erro'=>$e->getMessage()]); }
  exit;
}

/* ============================================
   POST AJAX: atualizar status (confirmar_envio / cancelar)
   ============================================ */
if ($_SERVER['REQUEST_METHOD']==='POST' && (($_POST['ajax'] ?? '')==='status')) {
  header('Content-Type: application/json; charset=utf-8');
  $sid  = (int)($_POST['transferencia_id'] ?? 0);
  $acao = $_POST['acao'] ?? '';
  if ($sid<=0 || !in_array($acao,['confirmar_envio','cancelar'],true)) {
    echo json_encode(['ok'=>false,'erro'=>'Parâmetros inválidos']); exit;
  }

  try {
    // precisa estar 'aprovada'
    $chk = $pdo->prepare("SELECT id, id_matriz, id_solicitante, status FROM solicitacoes_b2b WHERE id=:id AND id_matriz=:m LIMIT 1");
    $chk->execute([':id'=>$sid, ':m'=>$idSelecionado]);
    $sol = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$sol) { echo json_encode(['ok'=>false,'erro'=>'Solicitação não encontrada.']); exit; }
    if ($sol['status']!=='aprovada') { echo json_encode(['ok'=>false,'erro'=>'Solicitação não está aprovada.']); exit; }

    if ($acao==='confirmar_envio') {
      // coleta itens
      $it = $pdo->prepare("SELECT codigo_produto, nome_produto, quantidade FROM solicitacoes_b2b_itens WHERE solicitacao_id=:sid");
      $it->execute([':sid'=>$sid]);
      $itens = $it->fetchAll(PDO::FETCH_ASSOC);
      if (!$itens) { echo json_encode(['ok'=>false,'erro'=>'Solicitação sem itens.']); exit; }

      $idFilial = $sol['id_solicitante']; if (!$idFilial) { echo json_encode(['ok'=>false,'erro'=>'Filial não identificada.']); exit; }

      $pdo->beginTransaction();
      foreach ($itens as $ix) {
        $codigo = trim((string)$ix['codigo_produto']);
        $qtd    = (int)$ix['quantidade'];
        if ($qtd<=0) { $pdo->rollBack(); echo json_encode(['ok'=>false,'erro'=>"Quantidade inválida ({$codigo})."]); exit; }

        // produto na matriz
        $qM = $pdo->prepare("SELECT * FROM estoque WHERE empresa_id=:emp AND codigo_produto=:cod LIMIT 1");
        $qM->execute([':emp'=>$idSelecionado, ':cod'=>$codigo]);
        $rowM = $qM->fetch(PDO::FETCH_ASSOC);
        if (!$rowM) { $pdo->rollBack(); echo json_encode(['ok'=>false,'erro'=>"Produto {$codigo} não encontrado na matriz."]); exit; }
        if ((int)$rowM['quantidade_produto'] < $qtd) { $pdo->rollBack(); echo json_encode(['ok'=>false,'erro'=>"Saldo insuficiente de {$codigo}."]); exit; }

        // baixa matriz
        $pdo->prepare("UPDATE estoque SET quantidade_produto = quantidade_produto - :q, updated_at=CURRENT_TIMESTAMP WHERE id=:id")
            ->execute([':q'=>$qtd, ':id'=>(int)$rowM['id']]);

        // entrada filial (soma/insere)
        $qF = $pdo->prepare("SELECT id, quantidade_produto FROM estoque WHERE empresa_id=:emp AND codigo_produto=:cod LIMIT 1");
        $qF->execute([':emp'=>$idFilial, ':cod'=>$codigo]);
        if ($rowF = $qF->fetch(PDO::FETCH_ASSOC)) {
          $pdo->prepare("UPDATE estoque SET quantidade_produto = quantidade_produto + :q, updated_at=CURRENT_TIMESTAMP WHERE id=:id")
              ->execute([':q'=>$qtd, ':id'=>(int)$rowF['id']]);
        } else {
          $pdo->prepare("
            INSERT INTO estoque (
              empresa_id, fornecedor_id, codigo_produto, nome_produto, categoria_produto,
              quantidade_produto, preco_produto, preco_custo, status_produto, ncm, cest, cfop,
              origem, tributacao, unidade, codigo_barras, codigo_anp, informacoes_adicionais,
              peso_bruto, peso_liquido, aliquota_icms, aliquota_pis, aliquota_cofins, created_at, updated_at
            ) VALUES (
              :empresa_id, :fornecedor_id, :codigo_produto, :nome_produto, :categoria_produto,
              :quantidade_produto, :preco_produto, :preco_custo, :status_produto, :ncm, :cest, :cfop,
              :origem, :tributacao, :unidade, :codigo_barras, :codigo_anp, :informacoes_adicionais,
              :peso_bruto, :peso_liquido, :aliquota_icms, :aliquota_pis, :aliquota_cofins, NOW(), NOW()
            )
          ")->execute([
            ':empresa_id'=>$idFilial,
            ':fornecedor_id'=>$rowM['fornecedor_id'] ?? null,
            ':codigo_produto'=>$rowM['codigo_produto'],
            ':nome_produto'=>$rowM['nome_produto'] ?? $ix['nome_produto'] ?? 'Produto transferido',
            ':categoria_produto'=>$rowM['categoria_produto'] ?? null,
            ':quantidade_produto'=>$qtd,
            ':preco_produto'=>$rowM['preco_produto'] ?? null,
            ':preco_custo'=>$rowM['preco_custo'] ?? null,
            ':status_produto'=>$rowM['status_produto'] ?? 'ativo',
            ':ncm'=>$rowM['ncm'] ?? null,
            ':cest'=>$rowM['cest'] ?? null,
            ':cfop'=>$rowM['cfop'] ?? null,
            ':origem'=>$rowM['origem'] ?? null,
            ':tributacao'=>$rowM['tributacao'] ?? null,
            ':unidade'=>$rowM['unidade'] ?? 'UN',
            ':codigo_barras'=>$rowM['codigo_barras'] ?? null,
            ':codigo_anp'=>$rowM['codigo_anp'] ?? null,
            ':informacoes_adicionais'=>$rowM['informacoes_adicionais'] ?? null,
            ':peso_bruto'=>$rowM['peso_bruto'] ?? null,
            ':peso_liquido'=>$rowM['peso_liquido'] ?? null,
            ':aliquota_icms'=>$rowM['aliquota_icms'] ?? null,
            ':aliquota_pis'=>$rowM['aliquota_pis'] ?? null,
            ':aliquota_cofins'=>$rowM['aliquota_cofins'] ?? null,
          ]);
        }
      }

      // muda para em_transito (+ enviada_em se existir)
      $cols = [];
      try { $res = $pdo->query("SHOW COLUMNS FROM solicitacoes_b2b"); while($c=$res->fetch(PDO::FETCH_ASSOC)) $cols[$c['Field']]=true; } catch(Throwable $e){}
      $sql = "UPDATE solicitacoes_b2b SET status='em_transito', updated_at=CURRENT_TIMESTAMP".
             (isset($cols['enviada_em']) ? ", enviada_em=NOW()" : "").
             " WHERE id=:id AND status='aprovada'";
      $up  = $pdo->prepare($sql); $up->execute([':id'=>$sid]);
      if ($up->rowCount()===0) { $pdo->rollBack(); echo json_encode(['ok'=>false,'erro'=>'Falha ao mudar para em_transito.']); exit; }

      $pdo->commit();
      echo json_encode(['ok'=>true,'status'=>'em_transito']); exit;

    } else { // cancelar
      $up = $pdo->prepare("UPDATE solicitacoes_b2b SET status='cancelada', updated_at=CURRENT_TIMESTAMP WHERE id=:id AND status='aprovada'");
      $up->execute([':id'=>$sid]);
      if ($up->rowCount()===0) { echo json_encode(['ok'=>false,'erro'=>'Não foi possível cancelar.']); exit; }
      echo json_encode(['ok'=>true,'status'=>'cancelada']); exit;
    }
  } catch(PDOException $e){
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['ok'=>false,'erro'=>$e->getMessage()]);
  }
  exit;
}

/* ==========================================================
   🔎 FILTRO de status (Aguardando=aprovada | Em trânsito=em_transito | Todos=ambos)
   ========================================================== */
function h(?string $v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$statusFiltro = strtolower(trim($_GET['status'] ?? '')); // '', 'aprovada', 'em_transito'
$mapRotulo = [
  'aprovada'     => 'Aguardando',
  'em_transito'  => 'Em trânsito'
];

$where  = ["s.id_matriz = :empresa_id"];
$params = [':empresa_id'=>$idSelecionado];

// somente estes três estados para a listagem
if ($statusFiltro === 'aprovada') {
  $where[] = "s.status = 'aprovada'";
} elseif ($statusFiltro === 'em_transito') {
  $where[] = "s.status = 'em_transito'";
} else { // 'todos' ou vazio
  $where[] = "s.status IN ('aprovada','em_transito')";
}

/* ==========================================================
   📋 LISTAGEM
   ========================================================== */
$solicitacoes = [];
try {
  $whereSql = implode(' AND ',$where);
  $sql = "
    SELECT
      s.id,
      s.id_solicitante,
      u.nome AS filial_nome,
      s.created_at,
      s.aprovada_em,
      s.enviada_em,
      s.status,
      COUNT(i.id)                   AS itens,
      COALESCE(SUM(i.quantidade),0) AS qtd_total,
      COALESCE(s.enviada_em, s.aprovada_em, s.created_at) AS mov_em
    FROM solicitacoes_b2b s
    JOIN unidades u
      ON u.id = CAST(REPLACE(s.id_solicitante,'unidade_','') AS UNSIGNED)
     AND u.tipo IN ('Filial','filial')
     AND u.empresa_id = s.id_matriz
    LEFT JOIN solicitacoes_b2b_itens i
      ON i.solicitacao_id = s.id
    WHERE {$whereSql}
    GROUP BY s.id, s.id_solicitante, u.nome, s.created_at, s.aprovada_em, s.enviada_em, s.status
    ORDER BY mov_em DESC, s.id DESC
    LIMIT 300
  ";
  $st = $pdo->prepare($sql);
  foreach($params as $k=>$v) $st->bindValue($k,$v);
  $st->execute();
  $solicitacoes = $st->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e){ $solicitacoes = []; }

// helper de data
function dtBr(?string $dt){ if(!$dt) return '-'; $t=strtotime($dt); return $t?date('d/m/Y H:i',$t):'-'; }
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>ERP - Filial</title>
  <link rel="icon" type="image/x-icon" href="<?= h($logoEmpresa) ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com" /><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
  <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="../../assets/css/demo.css" />
  <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />
  <script src="../../assets/vendor/js/helpers.js"></script>
  <script src="../../assets/js/config.js"></script>
  <style>
    .table thead th { white-space: nowrap; }
    .status-badge { font-size:.78rem; }
  </style>
</head>
<body>
<div class="layout-wrapper layout-content-navbar">
<div class="layout-container">

  <!-- Sidebar (mantido igual ao seu) -->
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
      <li class="menu-item">
        <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
          <i class="menu-icon tf-icons bx bx-home-circle"></i><div>Dashboard</div>
        </a>
      </li>

      <li class="menu-header small text-uppercase"><span class="menu-header-text">Administração Filiais</span></li>

      <li class="menu-item active open">
        <a href="javascript:void(0);" class="menu-link menu-toggle">
          <i class="menu-icon tf-icons bx bx-briefcase"></i><div>B2B - Matriz</div>
        </a>
        <ul class="menu-sub active">
          <li class="menu-item"><a href="./contasFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><div>Pagamentos Solic.</div></a></li>
          <li class="menu-item"><a href="./produtosSolicitados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><div>Produtos Solicitados</div></a></li>
          <li class="menu-item"><a href="./produtosEnviados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><div>Produtos Enviados</div></a></li>
          <li class="menu-item active"><a href="./transferenciasPendentes.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><div>Transf. Pendentes</div></a></li>
          <li class="menu-item"><a href="./historicoTransferencias.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><div>Histórico Transf.</div></a></li>
          <li class="menu-item"><a href="./estoqueMatriz.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><div>Estoque Matriz</div></a></li>
          <li class="menu-item"><a href="./relatoriosB2B.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><div>Relatórios B2B</div></a></li>
        </ul>
      </li>

      <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
      <li class="menu-item"><a href="../rh/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-group"></i><div>RH</div></a></li>
      <li class="menu-item"><a href="../financas/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-dollar"></i><div>Finanças</div></a></li>
      <li class="menu-item"><a href="../pdv/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-desktop"></i><div>PDV</div></a></li>
      <li class="menu-item"><a href="../empresa/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-briefcase"></i><div>Empresa</div></a></li>
      <li class="menu-item"><a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-box"></i><div>Estoque</div></a></li>
      <li class="menu-item"><a href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-group"></i><div>Usuários </div></a></li>
      <li class="menu-item mb-5"><a href="https://wa.me/92991515710" target="_blank" class="menu-link"><i class="menu-icon tf-icons bx bx-support"></i><div>Suporte</div></a></li>
    </ul>
  </aside>

  <!-- Página -->
  <div class="layout-page">
    <!-- Navbar (mantida) -->
    <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
      <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)"><i class="bx bx-menu bx-sm"></i></a>
      </div>
      <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
        <div class="navbar-nav align-items-center"><div class="nav-item d-flex align-items-center"></div></div>
        <ul class="navbar-nav flex-row align-items-center ms-auto">
          <li class="nav-item navbar-dropdown dropdown-user dropdown">
            <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false">
              <div class="avatar avatar-online"><img src="<?= h($logoEmpresa) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" /></div>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownUser">
              <li>
                <a class="dropdown-item" href="#">
                  <div class="d-flex">
                    <div class="flex-shrink-0 me-3"><div class="avatar avatar-online"><img src="<?= h($logoEmpresa) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" /></div></div>
                    <div class="flex-grow-1"><span class="fw-semibold d-block"><?= h($nomeUsuario) ?></span><small class="text-muted"><?= h($tipoUsuario) ?></small></div>
                  </div>
                </a>
              </li>
              <li><div class="dropdown-divider"></div></li>
              <li><a class="dropdown-item" href="./contaUsuario.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-user me-2"></i><span class="align-middle">Minha Conta</span></a></li>
              <li><a class="dropdown-item" href="#"><i class="bx bx-cog me-2"></i><span class="align-middle">Configurações</span></a></li>
              <li><div class="dropdown-divider"></div></li>
              <li><a class="dropdown-item" href="../logout.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-power-off me-2"></i><span class="align-middle">Sair</span></a></li>
            </ul>
          </li>
        </ul>
      </div>
    </nav>

    <!-- Conteúdo -->
    <div class="container-xxl flex-grow-1 container-p-y">
      <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a href="#">Filiais</a>/</span>Transferências Pendentes</h4>
      <h5 class="fw-bold mt-3 mb-3"><span class="text-muted fw-light">Filtre por <strong>Aguardando</strong> (aprovada) ou <strong>Em trânsito</strong>.</span></h5>

      <!-- Filtros -->
      <form class="card mb-3" method="get" id="filtroForm">
        <input type="hidden" name="id" value="<?= h($idSelecionado) ?>">
        <div class="card-body">
          <div class="row g-3 align-items-end">
            <div class="col-12 col-md-auto">
              <label class="form-label mb-1">Status</label>
              <select name="status" class="form-select form-select-sm">
                <option value=""               <?= $statusFiltro==='' ? 'selected':'' ?>>Todos (Aguardando + Em trânsito)</option>
                <option value="aprovada"       <?= $statusFiltro==='aprovada' ? 'selected':'' ?>>Aguardando</option>
                <option value="em_transito"    <?= $statusFiltro==='em_transito' ? 'selected':'' ?>>Em trânsito</option>
              </select>
            </div>
            <div class="col-12 col-md-auto d-flex gap-2">
              <button class="btn btn-sm btn-primary" type="submit"><i class="bx bx-filter-alt me-1"></i> Filtrar</button>
              <a class="btn btn-sm btn-outline-secondary" href="?id=<?= urlencode($idSelecionado) ?>"><i class="bx bx-eraser me-1"></i> Limpar</a>
            </div>
          </div>
        </div>
      </form>

      <!-- Tabela -->
      <div class="card">
        <h5 class="card-header">Lista de Transferências</h5>
        <div class="table-responsive text-nowrap">
          <table class="table table-hover" id="tabela-transferencias">
            <thead>
              <tr>
                <th>#</th>
                <th>Filial</th>
                <th>Itens</th>
                <th>Qtd</th>
                <th>Movimentado em</th>
                <th>Status</th>
                <th class="text-end">Ações</th>
              </tr>
            </thead>
            <tbody class="table-border-bottom-0">
            <?php if (empty($solicitacoes)): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">Nenhuma transferência encontrada.</td></tr>
            <?php else: foreach ($solicitacoes as $row): ?>
              <?php
                $statusDb = strtolower((string)$row['status']);
                if ($statusDb==='aprovada') {
                  $badge = '<span class="badge bg-label-secondary status-badge">Aguardando</span>';
                  $statusTexto = 'Aguardando';
                } elseif ($statusDb==='em_transito') {
                  $badge = '<span class="badge bg-label-warning status-badge">Em trânsito</span>';
                  $statusTexto = 'Em trânsito';
                } else {
                  $badge = '<span class="badge bg-label-secondary status-badge">'.h($row['status']).'</span>';
                  $statusTexto = ucfirst(str_replace('_',' ',$row['status']));
                }
                $mostrarAcoes = ($statusDb==='aprovada'); // ações apenas enquanto está aguardando (opcional)
              ?>
              <tr data-row-id="<?= (int)$row['id'] ?>">
                <td><strong><?= (int)$row['id'] ?></strong></td>
                <td><?= h($row['filial_nome'] ?? '-') ?></td>
                <td><?= (int)$row['itens'] ?></td>
                <td><?= (int)$row['qtd_total'] ?></td>
                <td><?= dtBr($row['mov_em']) ?></td>
                <td><?= $badge ?></td>
                <td class="text-end">
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary btn-detalhes"
                    data-bs-toggle="modal"
                    data-bs-target="#modalDetalhes"
                    data-id="<?= (int)$row['id'] ?>"
                    data-codigo="TR-<?= (int)$row['id'] ?>"
                    data-filial="<?= h($row['filial_nome'] ?? '-') ?>"
                    data-status="<?= h($statusTexto) ?>">
                    Detalhes
                  </button>

                  <?php if ($mostrarAcoes): ?>
                    <button type="button" class="btn btn-sm btn-warning btn-acao" data-acao="confirmar_envio" data-id="<?= (int)$row['id'] ?>">Confirmar envio</button>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-acao" data-acao="cancelar" data-id="<?= (int)$row['id'] ?>">Cancelar</button>
                  <?php endif; ?>
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
              <h5 class="modal-title">Detalhes da Transferência</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
              <div class="row g-3 mb-2">
                <div class="col-md-4"><p><strong>Código:</strong> <span id="det-codigo">-</span></p></div>
                <div class="col-md-4"><p><strong>Filial:</strong> <span id="det-filial">-</span></p></div>
                <div class="col-md-4"><p><strong>Status:</strong> <span id="det-status">-</span></p></div>
              </div>

              <div class="table-responsive">
                <table class="table">
                  <thead><tr><th>Código</th><th>Produto</th><th>Qtd</th></tr></thead>
                  <tbody id="det-itens"><tr><td colspan="3" class="text-muted">Carregando...</td></tr></tbody>
                </table>
              </div>

              <div class="mt-2">
                <strong>Observações:</strong>
                <div id="det-obs" class="text-muted">—</div>
              </div>
            </div>
            <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button></div>
          </div>
        </div>
      </div>

      <!-- JS da listagem -->
      <script>
        (function(){
          const tabela = document.getElementById('tabela-transferencias');

          // modal detalhes
          const modalEl = document.getElementById('modalDetalhes');
          if (modalEl) {
            modalEl.addEventListener('show.bs.modal', function (event) {
              const btn = event.relatedTarget; if (!btn) return;
              const id  = btn.getAttribute('data-id');
              const cod = btn.getAttribute('data-codigo') || '-';
              const fil = btn.getAttribute('data-filial') || '-';
              const sts = btn.getAttribute('data-status') || '-';
              document.getElementById('det-codigo').textContent = cod;
              document.getElementById('det-filial').textContent = fil;
              document.getElementById('det-status').textContent = sts;

              const tbody = document.getElementById('det-itens');
              tbody.innerHTML = '<tr><td colspan="3" class="text-muted">Carregando...</td></tr>';

              const url = new URL(window.location.href);
              url.searchParams.set('ajax','detalhes');
              url.searchParams.set('solicitacao_id', id);
              fetch(url.toString(), { credentials: 'same-origin' })
                .then(r=>r.json())
                .then(data=>{
                  if(!data.ok) throw new Error(data.erro || 'Falha ao carregar itens');
                  const itens = data.itens || [];
                  tbody.innerHTML = itens.length
                    ? itens.map(it=>`<tr><td>${it.codigo_produto || '—'}</td><td>${it.nome_produto || '—'}</td><td>${it.quantidade || 0}</td></tr>`).join('')
                    : '<tr><td colspan="3" class="text-muted">Sem itens para esta solicitação.</td></tr>';
                  document.getElementById('det-obs').textContent = (data.cabecalho && data.cabecalho.observacao) ? data.cabecalho.observacao : '—';
                })
                .catch(err=>{ tbody.innerHTML = `<tr><td colspan="3" class="text-danger">Erro: ${err.message}</td></tr>`; });
            });
          }

          // ações
          tabela?.addEventListener('click', function(e){
            const el = e.target.closest('.btn-acao'); if (!el) return;
            const acao = el.getAttribute('data-acao');
            const id   = el.getAttribute('data-id');
            if (!acao || !id) return;
            if (acao==='cancelar' && !confirm('Confirmar cancelamento desta transferência?')) return;
            if (acao==='confirmar_envio' && !confirm('Confirmar envio desta transferência?')) return;

            const fd = new FormData();
            fd.append('ajax','status');
            fd.append('acao',acao);
            fd.append('transferencia_id',id);

            fetch(window.location.href, { method:'POST', credentials:'same-origin', body:fd })
              .then(r=>r.json())
              .then(data=>{
                if(!data.ok) throw new Error(data.erro || 'Falha ao atualizar');
                alert(data.status==='em_transito'
                  ? 'Atualizado para Em trânsito (estoques ajustados).'
                  : 'Solicitação cancelada.'
                );
                window.location.reload(true);
              })
              .catch(err=> alert('Erro: ' + err.message));
          });
        })();
      </script>

    </div>
    <!-- / Conteúdo -->

    <!-- Footer -->
    <footer class="content-footer footer bg-footer-theme text-center">
      <div class="container-xxl d-flex py-2 flex-md-row flex-column justify-content-center">
        <div class="mb-2 mb-md-0">
          &copy; <script>document.write(new Date().getFullYear());</script>, <strong>Açaínhadinhos</strong>. Todos os direitos reservados.
          Desenvolvido por <strong>CodeGeek</strong>.
        </div>
      </div>
    </footer>
  </div>
  <!-- / layout-page -->

  <div class="content-backdrop fade"></div>
</div>
</div>

<!-- Overlay -->
<div class="layout-overlay layout-menu-toggle"></div>

<!-- JS base -->
<script src="../../js/saudacao.js"></script>
<script src="../../assets/vendor/libs/jquery/jquery.js"></script>
<script src="../../assets/vendor/libs/popper/popper.js"></script>
<script src="../../assets/vendor/js/bootstrap.js"></script>
<script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
<script src="../../assets/vendor/js/menu.js"></script>
<script src="../../assets/vendor/libs/apex-charts/apex-charts.js"></script>
<script src="../../assets/js/main.js"></script>
<script src="../../assets/js/dashboards-analytics.js"></script>
<script async defer src="https://buttons.github.io/buttons.js"></script>
</body>
</html>

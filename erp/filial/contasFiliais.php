<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
date_default_timezone_set('America/Manaus');

/* ================= Helpers ================= */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function moneyBr($v){ return 'R$ ' . number_format((float)$v, 2, ',', '.'); }
function dtBr(?string $dt){ if(!$dt) return '—'; $t=strtotime($dt); return $t?date('d/m/Y H:i', $t):'—'; }

/* ================ Sessão/ID selecionado ================ */
$idSelecionado = $_GET['id'] ?? '';
if (!$idSelecionado) { header("Location: .././login.php"); exit; }

if (
  !isset($_SESSION['usuario_logado']) ||
  !isset($_SESSION['empresa_id']) ||
  !isset($_SESSION['tipo_empresa']) ||
  !isset($_SESSION['usuario_id'])
){
  header("Location: .././login.php?id=" . urlencode($idSelecionado));
  exit;
}

/* ================ Conexão ================ */
require '../../assets/php/conexao.php';
if (!isset($pdo) || !($pdo instanceof PDO)) { http_response_code(500); echo "Erro: conexão indisponível."; exit; }
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ================ Usuário logado ================ */
$nomeUsuario='Usuário'; $tipoUsuario='Comum'; $usuario_id=(int)$_SESSION['usuario_id'];
try{
  $st=$pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id=:id");
  $st->execute([':id'=>$usuario_id]);
  if($u=$st->fetch(PDO::FETCH_ASSOC)){
    $nomeUsuario=$u['usuario']??'Usuário';
    $tipoUsuario=ucfirst((string)($u['nivel']??'Comum'));
  } else {
    echo "<script>alert('Usuário não encontrado.'); window.location.href='.././login.php?id=".e($idSelecionado)."';</script>"; exit;
  }
}catch(Throwable $e){ echo "<script>alert('Erro ao carregar usuário.'); history.back();</script>"; exit; }

/* ================ Autorização ================ */
$acessoPermitido=false;
$idEmpresaSession  = $_SESSION['empresa_id'];
$tipoSession       = $_SESSION['tipo_empresa'];

if (str_starts_with($idSelecionado, 'principal_')) {
  $acessoPermitido = ($tipoSession==='principal' && $idEmpresaSession==='principal_1');
} elseif (str_starts_with($idSelecionado, 'filial_')) {
  $acessoPermitido = ($tipoSession==='filial' && $idEmpresaSession===$idSelecionado);
} elseif (str_starts_with($idSelecionado, 'unidade_')) {
  $acessoPermitido = ($tipoSession==='unidade' && $idEmpresaSession===$idSelecionado);
} elseif (str_starts_with($idSelecionado, 'franquia_')) {
  $acessoPermitido = ($tipoSession==='franquia' && $idEmpresaSession===$idSelecionado);
}
if(!$acessoPermitido){
  echo "<script>alert('Acesso negado!');window.location.href='.././login.php?id=".e($idSelecionado)."';</script>"; exit;
}

/* ================ Logo da empresa ================ */
try{
  $s=$pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado=:id LIMIT 1");
  $s->execute([':id'=>$idSelecionado]);
  $empresaSobre=$s->fetch(PDO::FETCH_ASSOC);
  $logoEmpresa = (!empty($empresaSobre['imagem'])) ? "../../assets/img/empresa/".$empresaSobre['imagem'] : "../../assets/img/favicon/logo.png";
}catch(Throwable $e){ $logoEmpresa="../../assets/img/favicon/logo.png"; }

/* ==========================================================
   ENDPOINT INTERNO (MESMO ARQUIVO) — APROVAR/REPROVAR
   POST: ajax=alterar_status, id=..., acao=aprovar|reprovar, motivo (opcional)
   ========================================================== */
if (($_POST['ajax'] ?? '') === 'alterar_status') {
  header('Content-Type: application/json; charset=utf-8');

  try{
    $idSol = (int)($_POST['id'] ?? 0);
    $acao  = strtolower(trim((string)($_POST['acao'] ?? '')));
    $motivo= trim((string)($_POST['motivo'] ?? ''));

    if($idSol<=0 || !in_array($acao, ['aprovar','reprovar'], true)){
      echo json_encode(['ok'=>false,'erro'=>'Parâmetros inválidos.']); exit;
    }

    // Validar se a solicitação é desta matriz e de uma UNIDADE do tipo FILIAL
    $sql = "
      SELECT sp.ID, sp.status
      FROM solicitacoes_pagamento sp
      JOIN unidades u
        ON u.id = CAST(REPLACE(sp.id_solicitante,'unidade_','') AS UNSIGNED)
       AND u.tipo = 'Filial'
       AND u.empresa_id = :matriz
      WHERE sp.ID = :id AND sp.id_matriz = :matriz
      LIMIT 1
    ";
    $chk = $pdo->prepare($sql);
    $chk->execute([':id'=>$idSol, ':matriz'=>$idSelecionado]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);
    if(!$row){ echo json_encode(['ok'=>false,'erro'=>'Registro inválido para esta matriz/filial.']); exit; }

    if(($row['status'] ?? '') !== 'pendente'){
      echo json_encode(['ok'=>false,'erro'=>'Somente solicitações pendentes podem ser alteradas.']); exit;
    }

    $novo = ($acao==='aprovar') ? 'aprovado' : 'reprovado';
    $upd = $pdo->prepare("
      UPDATE solicitacoes_pagamento
         SET status = :novo,
             observacao_motivo = :motivo,
             updated_at = NOW()
       WHERE ID = :id
       LIMIT 1
    ");
    $upd->execute([':novo'=>$novo, ':motivo'=>$motivo!==''?$motivo:null, ':id'=>$idSol]);

    echo json_encode(['ok'=>true,'status'=>$novo]); exit;

  }catch(Throwable $e){
    echo json_encode(['ok'=>false,'erro'=>$e->getMessage()]); exit;
  }
}

/* ==========================================================
   LISTAGEM: apenas solicitações de FILIAIS desta matriz
   (JOIN em unidades.tipo='Filial' e unidades.empresa_id = idSelecionado)
   ========================================================== */
$lista = [];
try{
  $sql = "
    SELECT
      sp.ID,
      sp.id_matriz,
      sp.id_solicitante,
      sp.status,
      sp.solicitante_nome,
      sp.descricao,
      sp.valor,
      sp.vencimento,
      sp.documento,
      sp.comprovante_url,
      sp.created_at,
      sp.updated_at,
      sp.observacao_motivo,
      u.nome AS filial_nome
    FROM solicitacoes_pagamento sp
    JOIN unidades u
      ON u.id = CAST(REPLACE(sp.id_solicitante,'unidade_','') AS UNSIGNED)
     AND u.tipo = 'Filial'
     AND u.empresa_id = :matriz
    WHERE sp.id_matriz = :matriz
    ORDER BY sp.created_at DESC, sp.ID DESC
  ";
  $st=$pdo->prepare($sql);
  $st->execute([':matriz'=>$idSelecionado]);
  $lista=$st->fetchAll(PDO::FETCH_ASSOC);
}catch(Throwable $e){
  $lista=[];
}
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
  <title>ERP - Filial</title>
  <link rel="icon" type="image/x-icon" href="<?= e($logoEmpresa) ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
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
    .status-badge{font-size:.78rem}
    .table thead th{white-space:nowrap}
    .btn-xxs{padding:.15rem .4rem;font-size:.72rem;line-height:1;border-radius:.2rem}
  </style>
</head>
<body>
<div class="layout-wrapper layout-content-navbar">
  <div class="layout-container">
    <!-- Menu lateral -->
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
        <li class="menu-item"><a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-home-circle"></i><div>Dashboard</div></a></li>

        <li class="menu-header small text-uppercase"><span class="menu-header-text">Administração Filiais</span></li>
        <li class="menu-item active open">
          <a href="javascript:void(0);" class="menu-link menu-toggle">
            <i class="menu-icon tf-icons bx bx-briefcase"></i><div>B2B - Matriz</div>
          </a>
          <ul class="menu-sub active">
            <li class="menu-item active"><a href="./contasFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><div>Pagamentos Solic.</div></a></li>
            <li class="menu-item"><a href="./produtosSolicitados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><div>Produtos Solicitados</div></a></li>
            <li class="menu-item"><a href="./produtosEnviados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><div>Produtos Enviados</div></a></li>
            <li class="menu-item"><a href="./transferenciasPendentes.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><div>Transf. Pendentes</div></a></li>
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
        <li class="menu-item"><a href="../franquia/index.php?id=principal_1" class="menu-link"><i class="menu-icon tf-icons bx bx-store"></i><div>Franquias</div></a></li>
        <li class="menu-item"><a href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-group"></i><div>Usuários</div></a></li>
        <li class="menu-item mb-5"><a href="https://wa.me/92991515710" target="_blank" class="menu-link"><i class="menu-icon tf-icons bx bx-support"></i><div>Suporte</div></a></li>
      </ul>
    </aside>
    <!-- / Menu lateral -->

    <div class="layout-page">
      <!-- Navbar -->
      <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
        <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
          <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)"><i class="bx bx-menu bx-sm"></i></a>
        </div>
        <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
          <div class="navbar-nav align-items-center"><div class="nav-item d-flex align-items-center"></div></div>
          <ul class="navbar-nav flex-row align-items-center ms-auto">
            <li class="nav-item navbar-dropdown dropdown-user dropdown">
              <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="avatar avatar-online"><img src="<?= e($logoEmpresa) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" /></div>
              </a>
              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownUser">
                <li>
                  <a class="dropdown-item" href="#">
                    <div class="d-flex">
                      <div class="flex-shrink-0 me-3"><div class="avatar avatar-online"><img src="<?= e($logoEmpresa) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" /></div></div>
                      <div class="flex-grow-1">
                        <span class="fw-semibold d-block"><?= e($nomeUsuario) ?></span>
                        <small class="text-muted"><?= e($tipoUsuario) ?></small>
                      </div>
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
      <!-- / Navbar -->

      <!-- Content -->
      <div class="container-xxl flex-grow-1 container-p-y">
        <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a href="#">Filiais</a>/</span> Pagamentos Solicitados</h4>
        <h5 class="fw-bold mt-3 mb-3 custor-font"><span class="text-muted fw-light">Solicitações de pagamento das FILIAIS</span></h5>

        <div class="card">
          <h5 class="card-header">Lista de Pagamentos Solicitados</h5>
          <div class="table-responsive text-nowrap">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Filial</th>
                  <th>Solicitante</th>
                  <th>Descrição</th>
                  <th>Valor</th>
                  <th>Vencimento</th>
                  <th>Documento</th>
                  <th>Status</th>
                  <th class="text-end">Ações</th>
                </tr>
              </thead>
              <tbody class="table-border-bottom-0" id="tb-body">
              <?php if(empty($lista)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">Nenhuma solicitação encontrada.</td></tr>
              <?php else: foreach($lista as $r):
                $id     = (int)$r['ID'];
                $filial = $r['filial_nome'] ?? '—';
                $sol    = $r['solicitante_nome'] ?? '—';
                $desc   = $r['descricao'] ?? '—';
                $valor  = $r['valor'] ?? 0;
                $venc   = $r['vencimento'] ?? null;
                $doc    = $r['documento'] ?? $r['comprovante_url'] ?? null;
                $status = strtolower((string)$r['status']);
                $badge  = $status==='pendente' ? 'bg-label-warning' : ($status==='aprovado' ? 'bg-label-success' : 'bg-label-danger');
              ?>
                <tr id="row-<?= $id ?>">
                  <td><?= $id ?></td>
                  <td><strong><?= e($filial) ?></strong></td>
                  <td><?= e($sol) ?></td>
                  <td><?= e($desc) ?></td>
                  <td><?= moneyBr($valor) ?></td>
                  <td><?= $venc ? e(date('d/m/Y', strtotime($venc))) : '—' ?></td>
                  <td>
                    <?php if($doc): ?>
                      <a href="<?= e($doc) ?>" target="_blank" class="text-primary">Abrir</a>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                  <td><span id="st-<?= $id ?>" class="badge <?= $badge ?> status-badge"><?= e(ucfirst($status)) ?></span></td>
                  <td class="text-end">
                    <div id="btns-<?= $id ?>">
                      <?php if($status==='pendente'): ?>
                        <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalAprovar" data-id="<?= $id ?>">Aprovar</button>
                        <button class="btn btn-sm btn-outline-danger"  data-bs-toggle="modal" data-bs-target="#modalRecusar" data-id="<?= $id ?>">Recusar</button>
                      <?php endif; ?>
                      <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalDetalhes"
                              data-id="<?= $id ?>"
                              data-filial="<?= e($filial) ?>"
                              data-solicitante="<?= e($sol) ?>"
                              data-descricao="<?= e($desc) ?>"
                              data-valor="<?= moneyBr($valor) ?>"
                              data-venc="<?= $venc ? e(date('d/m/Y', strtotime($venc))) : '—' ?>"
                              data-doc="<?= e((string)$doc) ?>"
                              data-status="<?= e(ucfirst($status)) ?>"
                              data-obs="<?= e((string)($r['observacao_motivo'] ?? '')) ?>">
                        Detalhes
                      </button>
                    </div>
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
              <div class="modal-header"><h5 class="modal-title">Detalhes da Solicitação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
              <div class="modal-body">
                <div class="row g-3">
                  <div class="col-md-6">
                    <p><strong>Filial:</strong> <span id="d-filial">—</span></p>
                    <p><strong>Solicitante:</strong> <span id="d-sol">—</span></p>
                    <p><strong>Descrição:</strong> <span id="d-desc">—</span></p>
                  </div>
                  <div class="col-md-6">
                    <p><strong>Valor:</strong> <span id="d-valor">—</span></p>
                    <p><strong>Vencimento:</strong> <span id="d-venc">—</span></p>
                    <p><strong>Status:</strong> <span id="d-sta">—</span></p>
                  </div>
                  <div class="col-12">
                    <p><strong>Documento:</strong> <a id="d-doc" href="#" target="_blank">—</a></p>
                    <p><strong>Observação/Motivo:</strong> <span id="d-obs">—</span></p>
                  </div>
                </div>
              </div>
              <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button></div>
            </div>
          </div>
        </div>

        <!-- Modal Aprovar -->
        <div class="modal fade" id="modalAprovar" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header"><h5 class="modal-title">Aprovar Solicitação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
              <div class="modal-body">
                Confirmar aprovação deste pagamento?
                <input type="hidden" id="apr-id" value="">
              </div>
              <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-success" id="btn-confirm-aprovar">Confirmar</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Recusar -->
        <div class="modal fade" id="modalRecusar" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header"><h5 class="modal-title">Recusar Solicitação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
              <div class="modal-body">
                <label class="form-label">Motivo (opcional)</label>
                <textarea class="form-control" rows="3" placeholder="Descreva o motivo..." id="rec-motivo"></textarea>
                <input type="hidden" id="rec-id" value="">
              </div>
              <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-danger" id="btn-confirm-recusar">Confirmar Recusa</button>
              </div>
            </div>
          </div>
        </div>

      </div>
      <!-- / Content -->

      <footer class="content-footer footer bg-footer-theme text-center">
        <div class="container-xxl d-flex py-2 flex-md-row flex-column justify-content-center">
          <div class="mb-2 mb-md-0">
            &copy; <script>document.write(new Date().getFullYear());</script>, <strong>Açaínhadinhos</strong>. Todos os direitos reservados.
            Desenvolvido por <strong>CodeGeek</strong>.
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
<script src="../../assets/vendor/libs/apex-charts/apexcharts.js"></script>
<script src="../../assets/js/main.js"></script>
<script async defer src="https://buttons.github.io/buttons.js"></script>

<script>
(function(){
  const modalDet = document.getElementById('modalDetalhes');
  modalDet?.addEventListener('show.bs.modal', (ev)=>{
    const btn = ev.relatedTarget;
    if(!btn) return;
    const g = (a)=>btn.getAttribute(a) || '—';
    document.getElementById('d-filial').textContent = g('data-filial');
    document.getElementById('d-sol').textContent    = g('data-solicitante');
    document.getElementById('d-desc').textContent   = g('data-descricao');
    document.getElementById('d-valor').textContent  = g('data-valor');
    document.getElementById('d-venc').textContent   = g('data-venc');
    document.getElementById('d-sta').textContent    = g('data-status');
    const doc = g('data-doc');
    const a = document.getElementById('d-doc');
    if(doc && doc !== '—'){ a.textContent='Abrir'; a.href = doc; a.target='_blank'; }
    else { a.textContent='—'; a.removeAttribute('href'); a.removeAttribute('target'); }
    document.getElementById('d-obs').textContent    = btn.getAttribute('data-obs') || '—';
  });

  // Guarda o ID nos modais de ação
  document.getElementById('modalAprovar')?.addEventListener('show.bs.modal', (ev)=>{
    const id = ev.relatedTarget?.getAttribute('data-id') || '';
    document.getElementById('apr-id').value = id;
  });
  document.getElementById('modalRecusar')?.addEventListener('show.bs.modal', (ev)=>{
    const id = ev.relatedTarget?.getAttribute('data-id') || '';
    document.getElementById('rec-id').value = id;
    document.getElementById('rec-motivo').value = '';
  });

  // POST helper
  async function postForm(dataObj){
    const form = new FormData();
    for(const [k,v] of Object.entries(dataObj)) form.append(k, v ?? '');
    const r = await fetch(location.href, { method:'POST', body: form, credentials: 'same-origin' });
    const txt = await r.text();
    let json;
    try {
      json = JSON.parse(txt);
    } catch(e) {
      throw new Error((txt || 'Resposta não-JSON').slice(0,400));
    }
    if(!json.ok) throw new Error(json.erro || 'Falha ao processar.');
    return json;
  }

  // Confirmar Aprovação
  document.getElementById('btn-confirm-aprovar')?.addEventListener('click', async ()=>{
    const id = document.getElementById('apr-id').value;
    try{
      const res = await postForm({ajax:'alterar_status', id, acao:'aprovar'});
      atualizarLinha(id, res.status);
      bootstrap.Modal.getInstance(document.getElementById('modalAprovar'))?.hide();
    }catch(err){ alert('Erro: ' + err.message); }
  });

  // Confirmar Recusa
  document.getElementById('btn-confirm-recusar')?.addEventListener('click', async ()=>{
    const id = document.getElementById('rec-id').value;
    const motivo = document.getElementById('rec-motivo').value;
    try{
      const res = await postForm({ajax:'alterar_status', id, acao:'reprovar', motivo});
      atualizarLinha(id, res.status);
      bootstrap.Modal.getInstance(document.getElementById('modalRecusar'))?.hide();
    }catch(err){ alert('Erro: ' + err.message); }
  });

  function atualizarLinha(id, status){
    status = String(status||'').toLowerCase();
    const stEl = document.getElementById('st-'+id);
    if(stEl){
      stEl.textContent = status.charAt(0).toUpperCase()+status.slice(1);
      stEl.classList.remove('bg-label-warning','bg-label-success','bg-label-danger');
      stEl.classList.add(status==='aprovado'?'bg-label-success':(status==='reprovado'?'bg-label-danger':'bg-label-warning'));
    }
    const btns = document.getElementById('btns-'+id);
    if(btns){
      // remove aprovar/recusar e deixa só Detalhes (que já existe)
      btns.querySelectorAll('button').forEach(b=>{
        const txt = (b.textContent||'').trim().toLowerCase();
        if(txt==='aprovar' || txt==='recusar'){ b.remove(); }
      });
    }
  }
})();
</script>
</body>
</html>

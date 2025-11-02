<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start());
date_default_timezone_set('America/Manaus');

/* ================= Sessão & parâmetros ================= */
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

/* ================= Conexão ================= */
require '../../assets/php/conexao.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo "Erro: conexão indisponível.";
    exit;
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ================= Usuário logado ================= */
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id  = (int)$_SESSION['usuario_id'];

try {
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    $stmt->execute([':id' => $usuario_id]);
    if ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $nomeUsuario = $u['usuario'] ?? 'Usuário';
        $tipoUsuario = ucfirst((string)($u['nivel'] ?? 'Comum'));
    } else {
        echo "<script>alert('Usuário não encontrado.'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao carregar usuário: " . htmlspecialchars($e->getMessage()) . "'); history.back();</script>";
    exit;
}

/* ================= Validação de acesso ================= */
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
    echo "<script>alert('Acesso negado!'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
    exit;
}

/* ================= Logo empresa ================= */
try {
    $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1");
    $stmt->execute([':id' => $idSelecionado]);
    $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);
    $logoEmpresa = (!empty($empresaSobre) && !empty($empresaSobre['imagem']))
        ? "../../assets/img/empresa/" . $empresaSobre['imagem']
        : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png";
}

/* ================= Utilitários ================= */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function moneyBr($v){ return 'R$ ' . number_format((float)$v, 2, ',', '.'); }
function dtBr(?string $iso){
    if (!$iso) return '—';
    $t = strtotime($iso); if (!$t) return '—';
    return date('d/m/Y', $t);
}

/* ================= Lista: SOMENTE Filiais =================
   - Puxa somente registros cuja unidade solicitante é do tipo 'Filial'
   - Garante que a unidade pertence à MESMA matriz (empresa_id = :matriz)
=============================================================*/
$rows = [];
try {
    $sql = "
      SELECT
        s.id,
        s.id_solicitante,
        s.descricao,
        s.valor,
        s.vencimento,
        s.documento,
        s.status,
        s.criado_em,
        u.nome AS filial_nome
      FROM solicitacoes_pagamento s
      JOIN unidades u
        ON u.id = CAST(REPLACE(s.id_solicitante,'unidade_','') AS UNSIGNED)
       AND u.tipo = 'Filial'
       AND u.empresa_id = :matriz
      WHERE s.id_matriz = :matriz
      ORDER BY s.criado_em DESC, s.id DESC
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':matriz' => $idSelecionado]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $rows = [];
}

?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>ERP - Filial | Pagamentos Solicitados</title>
    <link rel="icon" type="image/x-icon" href="<?= e($logoEmpresa) ?>" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />
    <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <script src="../../assets/vendor/js/helpers.js"></script>
    <script src="../../assets/js/config.js"></script>
    <style>
        .status-badge{font-size:.78rem;}
        .table thead th{white-space:nowrap;}
        .table td{vertical-align:middle;}
    </style>
</head>
<body>
<div class="layout-wrapper layout-content-navbar">
<div class="layout-container">

    <!-- Menu (mantido igual ao seu) -->
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
                <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-home-circle"></i><div>Dashboard</div></a>
            </li>

            <li class="menu-header small text-uppercase"><span class="menu-header-text">Administração Filiais</span></li>

            <li class="menu-item active open">
                <a href="javascript:void(0);" class="menu-link menu-toggle"><i class="menu-icon tf-icons bx bx-briefcase"></i><div>B2B - Matriz</div></a>
                <ul class="menu-sub active">
                    <li class="menu-item active"><a href="./contasFiliais.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><div>Pagamentos Solic.</div></a></li>
                    <li class="menu-item"><a href="./produtosSolicitados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><div>Produtos Solicitados</div></a></li>
                    <li class="menu-item"><a href="./produtosEnviados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><div>Produtos Enviados</div></a></li>
                    <li class="menu-item"><a href="./transferenciasPendentes.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><div>Transf. Pendentes</div></a></li>
                    <li class="menu-item"><a href="./historicoTransferencias.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><div>Histórico Transf.</div></a></li>
                </ul>
            </li>

            <li class="menu-item"><a href="../financas/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-dollar"></i><div>Finanças</div></a></li>
            <li class="menu-item"><a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-box"></i><div>Estoque</div></a></li>
            <li class="menu-item"><a href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-group"></i><div>Usuários</div></a></li>
        </ul>
    </aside>
    <!-- / Menu -->

    <div class="layout-page">
        <!-- Navbar resumida -->
        <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
            <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)"><i class="bx bx-menu bx-sm"></i></a>
            </div>
            <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                <ul class="navbar-nav flex-row align-items-center ms-auto">
                    <li class="nav-item navbar-dropdown dropdown-user dropdown">
                        <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                            <div class="avatar avatar-online"><img src="<?= e($logoEmpresa) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" /></div>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Content -->
        <div class="container-xxl flex-grow-1 container-p-y">
            <h4 class="fw-bold mb-0">
                <span class="text-muted fw-light"><a href="#">Filiais</a>/</span> Pagamentos Solicitados
            </h4>
            <h5 class="fw-bold mt-3 mb-3 custor-font">
                <span class="text-muted fw-light">Somente solicitações de pagamento de **Filiais** desta Matriz</span>
            </h5>

            <div class="card">
                <h5 class="card-header">Lista de Pagamentos</h5>
                <div class="table-responsive text-nowrap">
                    <table class="table table-hover" id="tblPagamentos">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Filial</th>
                                <th>Descrição</th>
                                <th>Valor</th>
                                <th>Vencimento</th>
                                <th>Documento</th>
                                <th>Status</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="table-border-bottom-0">
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Nenhuma solicitação encontrada.</td></tr>
                        <?php else: foreach ($rows as $r):
                            $id     = (int)$r['id'];
                            $status = strtolower($r['status'] ?? 'pendente');
                            $badge  = [
                                'pendente'  => 'bg-label-warning',
                                'aprovado'  => 'bg-label-success',
                                'reprovado' => 'bg-label-danger',
                            ][$status] ?? 'bg-label-secondary';

                            $showActions = ($status === 'pendente');
                            $docLabel = $r['documento'] ? basename($r['documento']) : '—';
                        ?>
                            <tr id="row-<?= $id ?>"
                                data-id="<?= $id ?>"
                                data-filial="<?= e($r['filial_nome'] ?? '-') ?>"
                                data-descricao="<?= e($r['descricao'] ?? '-') ?>"
                                data-valor="<?= (float)($r['valor'] ?? 0) ?>"
                                data-vencimento="<?= e($r['vencimento'] ?? '') ?>"
                                data-documento="<?= e($r['documento'] ?? '') ?>"
                                data-status="<?= e($status) ?>"
                            >
                                <td><strong><?= $id ?></strong></td>
                                <td><?= e($r['filial_nome'] ?? '-') ?></td>
                                <td><?= e($r['descricao'] ?? '-') ?></td>
                                <td><?= moneyBr($r['valor'] ?? 0) ?></td>
                                <td><?= dtBr($r['vencimento'] ?? null) ?></td>
                                <td>
                                    <?php if (!empty($r['documento'])): ?>
                                        <a href="<?= e($r['documento']) ?>" target="_blank" class="text-primary"><?= e($docLabel) ?></a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= $badge ?> status-badge" id="badge-<?= $id ?>"><?= ucfirst($status) ?></span></td>
                                <td class="text-end">
                                    <div class="btn-group" role="group" id="grp-<?= $id ?>">
                                        <?php if ($showActions): ?>
                                            <button class="btn btn-sm btn-outline-success" onclick="alterarStatus(<?= $id ?>,'aprovar')">Aprovar</button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="alterarStatus(<?= $id ?>,'reprovar')">Recusar</button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="abrirDetalhes(<?= $id ?>)">Detalhes</button>
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
                        <div class="modal-header">
                            <h5 class="modal-title">Detalhes da Solicitação</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <p><strong>Filial:</strong> <span id="det-filial">—</span></p>
                                    <p><strong>Descrição:</strong> <span id="det-desc">—</span></p>
                                    <p><strong>Valor:</strong> <span id="det-valor">—</span></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Vencimento:</strong> <span id="det-venc">—</span></p>
                                    <p><strong>Status:</strong> <span id="det-status">—</span></p>
                                    <p><strong>Documento:</strong> <span id="det-doc">—</span></p>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /content -->

        <footer class="content-footer footer bg-footer-theme text-center">
            <div class="container-xxl d-flex  py-2 flex-md-row flex-column justify-content-center">
                <div class="mb-2 mb-md-0">
                    &copy; <script>document.write(new Date().getFullYear());</script>, <strong>Açaínhadinhos</strong>. Todos os direitos reservados.
                    Desenvolvido por <strong>CodeGeek</strong>.
                </div>
            </div>
        </footer>
        <div class="content-backdrop fade"></div>
    </div><!-- /layout-page -->
</div><!-- /layout-container -->
</div><!-- /layout-wrapper -->

<!-- Core JS -->
<script src="../../assets/vendor/libs/jquery/jquery.js"></script>
<script src="../../assets/vendor/libs/popper/popper.js"></script>
<script src="../../assets/vendor/js/bootstrap.js"></script>
<script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
<script src="../../assets/vendor/js/menu.js"></script>
<script src="../../assets/js/main.js"></script>

<script>
const MAT_ID = <?= json_encode($idSelecionado) ?>;

// formata BR
function moneyBr(v){ 
  const n = Number(v||0);
  return n.toLocaleString('pt-BR', {style:'currency', currency:'BRL'});
}
function dtBr(iso){
  if(!iso) return '—';
  const d = new Date(String(iso).replace(' ','T'));
  if (isNaN(d)) return iso;
  const dd = String(d.getDate()).padStart(2,'0');
  const mm = String(d.getMonth()+1).padStart(2,'0');
  const yyyy = d.getFullYear();
  return `${dd}/${mm}/${yyyy}`;
}

// abre modal detalhes usando atributos da própria linha
function abrirDetalhes(id){
  const tr = document.getElementById('row-'+id);
  if(!tr) return;

  const filial = tr.dataset.filial || '—';
  const desc   = tr.dataset.descricao || '—';
  const valor  = Number(tr.dataset.valor || 0);
  const venc   = tr.dataset.vencimento || '';
  const doc    = tr.dataset.documento || '';
  const st     = tr.dataset.status || '—';

  document.getElementById('det-filial').textContent = filial;
  document.getElementById('det-desc').textContent   = desc;
  document.getElementById('det-valor').textContent  = moneyBr(valor);
  document.getElementById('det-venc').textContent   = dtBr(venc);
  document.getElementById('det-status').textContent = st.charAt(0).toUpperCase()+st.slice(1);

  if(doc){
    const label = doc.split('/').pop();
    document.getElementById('det-doc').innerHTML = `<a href="${doc}" target="_blank">${label}</a>`;
  }else{
    document.getElementById('det-doc').textContent = '—';
  }

  const modal = new bootstrap.Modal(document.getElementById('modalDetalhes'));
  modal.show();
}

// aprovar / reprovar (AJAX)
function alterarStatus(id, acao){
  if(acao !== 'aprovar' && acao !== 'reprovar') return;
  const confirmMsg = acao === 'aprovar' ? 'Confirmar aprovação deste pagamento?' : 'Confirmar recusa deste pagamento?';
  if(!confirm(confirmMsg)) return;

  fetch('./actions/pagamento_AlterarStatus.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ id: String(id), acao: acao, id_matriz: MAT_ID })
  })
  .then(r => r.json())
  .then(data => {
    if(!data || !data.ok){
      alert(data && data.erro ? data.erro : 'Falha ao alterar status.');
      return;
    }
    // atualiza UI
    const novo = data.novo_status; // 'aprovado' ou 'reprovado'
    const tr   = document.getElementById('row-'+id);
    const badge= document.getElementById('badge-'+id);
    const grp  = document.getElementById('grp-'+id);

    if(tr) tr.dataset.status = novo;
    if(badge){
      let cls = 'bg-label-secondary';
      if(novo==='aprovado') cls='bg-label-success';
      else if(novo==='reprovado') cls='bg-label-danger';
      badge.className = 'badge '+cls+' status-badge';
      badge.textContent = novo.charAt(0).toUpperCase()+novo.slice(1);
    }
    if(grp){
      // remove botões de aprovar/recusar e mantém só Detalhes
      const btns = Array.from(grp.querySelectorAll('button'));
      btns.forEach(b=>{
        if(/Aprovar|Recusar/i.test(b.textContent)) b.remove();
      });
    }
  })
  .catch(err => {
    alert('Erro: '+ err.message);
  });
}
</script>
</body>
</html>

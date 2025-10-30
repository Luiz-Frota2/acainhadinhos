<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

/* ==================== Sessão & parâmetros ==================== */
$idSelecionado = $_GET['id'] ?? '';
if (!$idSelecionado) {
  header("Location: .././login.php");
  exit;
}

/* ==================== Login obrigatório ==================== */
if (
  !isset($_SESSION['usuario_logado']) ||
  !isset($_SESSION['empresa_id']) ||
  !isset($_SESSION['tipo_empresa']) ||
  !isset($_SESSION['usuario_id'])
) {
  header("Location: .././login.php?id=" . urlencode($idSelecionado));
  exit;
}

/* ==================== Conexão ==================== */
require '../../assets/php/conexao.php';

/* ==================== Usuário logado ==================== */
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id  = (int)$_SESSION['usuario_id'];

try {
  $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
  $stmt->execute([':id' => $usuario_id]);
  if ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $nomeUsuario = $u['usuario'];
    $tipoUsuario = ucfirst($u['nivel']);
  } else {
    echo "<script>alert('Usuário não encontrado.'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
    exit;
  }
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar usuário: " . $e->getMessage() . "'); history.back();</script>";
  exit;
}

/* ==================== Permissão (Matriz) ==================== */
$acessoPermitido   = false;
$idEmpresaSession  = $_SESSION['empresa_id'];
$tipoSession       = $_SESSION['tipo_empresa'];

if (str_starts_with($idSelecionado, 'principal_')) {
  // Matriz acessa suas solicitações
  $acessoPermitido = ($tipoSession === 'principal' && $idEmpresaSession === $idSelecionado);
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

/* ==================== Logo ==================== */
try {
  $s = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :i LIMIT 1");
  $s->execute([':i' => $idSelecionado]);
  $sobre = $s->fetch(PDO::FETCH_ASSOC);
  $logoEmpresa = !empty($sobre['imagem']) ? "../../assets/img/empresa/" . $sobre['imagem'] : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
  $logoEmpresa = "../../assets/img/favicon/logo.png";
}

/* ==================== Filtros ==================== */
$status     = $_GET['status']     ?? '';       // pendente/aprovado/reprovado
$tipoUnidade = $_GET['tipo']       ?? '';       // Franquia/Filial
$dtIni      = $_GET['venc_ini']   ?? '';       // YYYY-MM-DD
$dtFim      = $_GET['venc_fim']   ?? '';       // YYYY-MM-DD
$q          = trim($_GET['q']     ?? '');      // texto livre

$params = [':id_matriz' => $idSelecionado];
$where  = ["sp.id_matriz = :id_matriz"];

// status
if ($status !== '' && in_array($status, ['pendente', 'aprovado', 'reprovado'], true)) {
  $where[] = "sp.status = :status";
  $params[':status'] = $status;
}

// tipo (Franquia/Filial) — vem da tabela unidades
if ($tipoUnidade !== '' && in_array($tipoUnidade, ['Franquia', 'Filial'], true)) {
  $where[] = "u.tipo = :tipo";
  $params[':tipo'] = $tipoUnidade;
}

// intervalo de vencimento
if ($dtIni !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dtIni)) {
  $where[] = "sp.vencimento >= :vini";
  $params[':vini'] = $dtIni;
}
if ($dtFim !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dtFim)) {
  $where[] = "sp.vencimento <= :vfim";
  $params[':vfim'] = $dtFim;
}

// busca textual
if ($q !== '') {
  $where[] = "(sp.fornecedor LIKE :q OR sp.documento LIKE :q OR sp.descricao LIKE :q)";
  $params[':q'] = "%$q%";
}

// monta SQL
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "
  SELECT
    sp.ID,
    sp.id_matriz,
    sp.id_solicitante,
    sp.status,
    sp.fornecedor,
    sp.documento,
    sp.descricao,
    sp.vencimento,
    sp.valor,
    sp.comprovante_url,
    sp.created_at,
    sp.updated_at,
    u.id         AS unidade_id,
    u.nome       AS unidade_nome,
    u.tipo       AS unidade_tipo
  FROM solicitacoes_pagamento sp
  LEFT JOIN unidades u
    ON u.id = CAST(SUBSTRING_INDEX(sp.id_solicitante, '_', -1) AS UNSIGNED)
  $whereSql
  ORDER BY sp.created_at DESC, sp.ID DESC
";

$rows = [];
try {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar solicitações: " . htmlspecialchars($e->getMessage(), ENT_QUOTES) . "'); history.back();</script>";
  exit;
}

// função helper para badge
function badgeStatus(string $s): string
{
  $s = strtolower($s);
  if ($s === 'aprovado')  return '<span class="badge bg-label-success status-badge">Aprovado</span>';
  if ($s === 'reprovado') return '<span class="badge bg-label-danger status-badge">Reprovado</span>';
  return '<span class="badge bg-label-warning status-badge">Pendente</span>';
}
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
  <title>ERP — Pagamentos Solicitados</title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($logoEmpresa) ?>" />
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
    .table thead th {
      white-space: nowrap;
    }

    .status-badge {
      font-size: .78rem;
    }

    .toolbar {
      gap: .5rem;
      flex-wrap: wrap;
    }

    .toolbar .form-select,
    .toolbar .form-control {
      max-width: 220px;
    }

    .truncate {
      max-width: 360px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      display: inline-block;
      vertical-align: bottom;
    }
  </style>
</head>

<body>
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
      <!-- ====== ASIDE ====== -->
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
          <li class="menu-item">
            <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
              <i class="menu-icon tf-icons bx bx-home-circle"></i>
              <div>Dashboard</div>
            </a>
          </li>

          <li class="menu-header small text-uppercase"><span class="menu-header-text">Administração Franquias</span></li>

          <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-building"></i>
              <div>Franquias</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item"><a href="./franquiaAdicionada.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Adicionadas</div>
                </a></li>
            </ul>
          </li>

          <li class="menu-item active open">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-briefcase"></i>
              <div>B2B - Matriz</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item active">
                <a href="#" class="menu-link">
                  <div>Pagamentos Solic.</div>
                </a>
              </li>
              <li class="menu-item"><a href="./produtosSolicitados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Produtos Solicitados</div>
                </a></li>
              <li class="menu-item"><a href="./produtosEnviados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Produtos Enviados</div>
                </a></li>
              <li class="menu-item"><a href="./transferenciasPendentes.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Transf. Pendentes</div>
                </a></li>
              <li class="menu-item"><a href="./historicoTransferencias.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Histórico Transf.</div>
                </a></li>
              <li class="menu-item"><a href="./estoqueMatriz.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Estoque Matriz</div>
                </a></li>
              <li class="menu-item"><a href="./relatoriosB2B.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Relatórios B2B</div>
                </a></li>
            </ul>
          </li>

          <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
          <li class="menu-item"><a href="../rh/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-group"></i>
              <div>RH</div>
            </a></li>
          <li class="menu-item"><a href="../financas/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-dollar"></i>
              <div>Finanças</div>
            </a></li>
          <li class="menu-item"><a href="../pdv/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-desktop"></i>
              <div>PDV</div>
            </a></li>
          <li class="menu-item"><a href="../empresa/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-briefcase"></i>
              <div>Empresa</div>
            </a></li>
          <li class="menu-item"><a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-box"></i>
              <div>Estoque</div>
            </a></li>
          <li class="menu-item"><a href="../filial/index.php?id=principal_1" class="menu-link"><i class="menu-icon tf-icons bx bx-building"></i>
              <div>Filial</div>
            </a></li>
          <li class="menu-item"><a href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-group"></i>
              <div>Usuários</div>
            </a></li>
          <li class="menu-item"><a href="https://wa.me/92991515710" target="_blank" class="menu-link"><i class="menu-icon tf-icons bx bx-support"></i>
              <div>Suporte</div>
            </a></li>
        </ul>
      </aside>
      <!-- ====== /ASIDE ====== -->

      <div class="layout-page">
        <!-- Navbar -->
        <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
          <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
            <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)"><i class="bx bx-menu bx-sm"></i></a>
          </div>
          <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
            <div class="navbar-nav align-items-center">
              <div class="nav-item d-flex align-items-center">
                <i class="bx bx-file fs-4 lh-0"></i>
                <span class="ms-2">Pagamentos Solicitados</span>
              </div>
            </div>
            <ul class="navbar-nav flex-row align-items-center ms-auto">
              <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false">
                  <div class="avatar avatar-online"><img src="<?= htmlspecialchars($logoEmpresa, ENT_QUOTES) ?>" class="w-px-40 h-auto rounded-circle" alt="Avatar" /></div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li>
                    <a class="dropdown-item" href="#">
                      <div class="d-flex">
                        <div class="flex-shrink-0 me-3">
                          <div class="avatar avatar-online"><img src="<?= htmlspecialchars($logoEmpresa, ENT_QUOTES) ?>" class="w-px-40 h-auto rounded-circle" alt="Avatar" /></div>
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
                  <li><a class="dropdown-item" href="./contaUsuario.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-user me-2"></i><span class="align-middle">Minha Conta</span></a></li>
                  <li>
                    <div class="dropdown-divider"></div>
                  </li>
                  <li><a class="dropdown-item" href="../logout.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-power-off me-2"></i><span class="align-middle">Sair</span></a></li>
                </ul>
              </li>
            </ul>
          </div>
        </nav>
        <!-- /Navbar -->

        <div class="container-xxl flex-grow-1 container-p-y">
          <h4 class="fw-bold mb-0">
            <span class="text-muted fw-light"><a href="#">Franquias</a>/</span>
            Pagamentos Solicitados
          </h4>
          <h5 class="fw-bold mt-3 mb-3">
            <span class="text-muted fw-light">Visualize e gerencie as solicitações de pagamento das unidades</span>
          </h5>

          <!-- Toolbar / Filtros -->
          <div class="card mb-3">
            <div class="card-body">
              <form class="row g-2 align-items-end toolbar" method="get">
                <input type="hidden" name="id" value="<?= htmlspecialchars($idSelecionado, ENT_QUOTES) ?>">

                <div class="col-auto">
                  <label class="form-label mb-1">Tipo</label>
                  <select name="tipo" class="form-select">
                    <option value="">Todos</option>
                    <option value="Franquia" <?= $tipoUnidade === 'Franquia' ? 'selected' : ''; ?>>Franquia</option>
                    <option value="Filial" <?= $tipoUnidade === 'Filial'  ? 'selected' : ''; ?>>Filial</option>
                  </select>
                </div>

                <div class="col-auto">
                  <label class="form-label mb-1">Status</label>
                  <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="pendente" <?= $status === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                    <option value="aprovado" <?= $status === 'aprovado' ? 'selected' : ''; ?>>Aprovado</option>
                    <option value="reprovado" <?= $status === 'reprovado' ? 'selected' : ''; ?>>Reprovado</option>
                  </select>
                </div>

                <div class="col-auto">
                  <label class="form-label mb-1">Venc. de</label>
                  <input type="date" name="venc_ini" value="<?= htmlspecialchars($dtIni, ENT_QUOTES) ?>" class="form-control">
                </div>
                <div class="col-auto">
                  <label class="form-label mb-1">até</label>
                  <input type="date" name="venc_fim" value="<?= htmlspecialchars($dtFim, ENT_QUOTES) ?>" class="form-control">
                </div>

                <div class="col-auto">
                  <label class="form-label mb-1">Busca</label>
                  <input type="text" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES) ?>" class="form-control" placeholder="fornecedor, doc, descrição">
                </div>

                <div class="col-auto">
                  <button class="btn btn-primary"><i class="bx bx-search"></i> Filtrar</button>
                  <a class="btn btn-outline-secondary" href="?id=<?= urlencode($idSelecionado) ?>"><i class="bx bx-reset"></i> Limpar</a>
                </div>
              </form>
            </div>
          </div>

          <!-- Tabela -->
          <div class="card">
            <h5 class="card-header">Lista de Pagamentos Solicitados</h5>
            <div class="table-responsive text-nowrap">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Unidade</th>
                    <th>Tipo</th>
                    <th>Fornecedor</th>
                    <th>Documento</th>
                    <th>Valor</th>
                    <th>Vencimento</th>
                    <th>Anexo</th>
                    <th>Status</th>
                    <th>Criado em</th>
                  </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                  <?php if (!$rows): ?>
                    <tr>
                      <td colspan="10" class="text-center text-muted py-4">Nenhuma solicitação encontrada.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                      <tr>
                        <td><?= (int)$r['ID'] ?></td>
                        <td>
                          <?php
                          $unNome = $r['unidade_nome'] ?: '—';
                          $idSol  = $r['id_solicitante'];
                          echo '<strong>' . htmlspecialchars($unNome, ENT_QUOTES) . '</strong>';
                          echo '<div class="text-muted small">' . htmlspecialchars($idSol, ENT_QUOTES) . '</div>';
                          ?>
                        </td>
                        <td><?= htmlspecialchars($r['unidade_tipo'] ?? '—', ENT_QUOTES) ?></td>
                        <td class="truncate" title="<?= htmlspecialchars($r['fornecedor'], ENT_QUOTES) ?>"><?= htmlspecialchars($r['fornecedor'], ENT_QUOTES) ?></td>
                        <td class="truncate" title="<?= htmlspecialchars($r['documento'] ?: '—', ENT_QUOTES) ?>"><?= htmlspecialchars($r['documento'] ?: '—', ENT_QUOTES) ?></td>
                        <td><?php
                            $v = (float)$r['valor'];
                            echo 'R$ ' . number_format($v, 2, ',', '.');
                            ?></td>
                        <td><?php
                            $venc = $r['vencimento'];
                            echo $venc ? date('d/m/Y', strtotime($venc)) : '—';
                            ?></td>
                        <td>
                          <?php if (!empty($r['comprovante_url'])): ?>
                            <a href="<?= htmlspecialchars($r['comprovante_url'], ENT_QUOTES) ?>" target="_blank" class="text-primary">
                              abrir
                            </a>
                          <?php else: ?>
                            <span class="text-muted">—</span>
                          <?php endif; ?>
                        </td>
                        <td><?= badgeStatus($r['status']) ?></td>
                        <td><?php
                            $c = $r['created_at'];
                            echo $c ? date('d/m/Y H:i', strtotime($c)) : '—';
                            ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

        </div><!-- /container -->
      </div><!-- /layout-page -->
    </div><!-- /layout-container -->
  </div>

  <!-- JS -->
  <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../../assets/vendor/libs/popper/popper.js"></script>
  <script src="../../assets/vendor/js/bootstrap.js"></script>
  <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="../../assets/vendor/js/menu.js"></script>
  <script src="../../assets/js/main.js"></script>
</body>

</html>
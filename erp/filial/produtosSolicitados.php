<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

declare(strict_types=1);
session_start();

/* ==================== Parâmetros & Sessão ==================== */
$idSelecionado = $_GET['id'] ?? '';
if (!$idSelecionado) {
  header("Location: .././login.php");
  exit;
}

if (
  !isset($_SESSION['usuario_logado']) ||
  !isset($_SESSION['empresa_id'])     ||
  !isset($_SESSION['tipo_empresa'])   ||
  !isset($_SESSION['usuario_id'])
) {
  header("Location: .././login.php?id=" . urlencode($idSelecionado));
  exit;
}

/* ==================== Conexão ==================== */
require '../../assets/php/conexao.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
  http_response_code(500);
  echo "Erro: conexão indisponível.";
  exit;
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ==================== Usuário ==================== */
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id  = (int)$_SESSION['usuario_id'];

try {
  $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
  $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
  $stmt->execute();
  if ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $nomeUsuario = $u['usuario'] ?? 'Usuário';
    $tipoUsuario = ucfirst((string)($u['nivel'] ?? 'Comum'));
  } else {
    echo "<script>alert('Usuário não encontrado.'); window.location.href = '.././login.php?id=" . htmlspecialchars($idSelecionado) . "';</script>";
    exit;
  }
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar usuário: " . htmlspecialchars($e->getMessage()) . "'); history.back();</script>";
  exit;
}

/* ==================== Autorização por empresa ==================== */
$acessoPermitido  = false;
$idEmpresaSession = $_SESSION['empresa_id'];
$tipoSession      = $_SESSION['tipo_empresa'];

if (str_starts_with($idSelecionado, 'principal_')) {
  $acessoPermitido = ($tipoSession === 'principal' && $idEmpresaSession === $idSelecionado);
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
    window.location.href = '.././login.php?id=" . htmlspecialchars($idSelecionado) . "';
  </script>";
  exit;
}

/* ==================== Logo da empresa (mantido) ==================== */
$logoEmpresa = null;
try {
  $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1");
  $stmt->execute([':id' => $idSelecionado]);
  $logoEmpresa = $stmt->fetchColumn() ?: null;
} catch (PDOException $e) {
  // silencioso
}

/* ==================== Helpers ==================== */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

function status_badge_class(string $status): string {
  // mapeamento para as mesmas cores do layout (Sneat)
  return match ($status) {
    'pendente'    => 'bg-label-warning',
    'aprovada'    => 'bg-label-success',
    'reprovada'    => 'bg-label-danger',
    'em_transito' => 'bg-label-info',
    'entregue'    => 'bg-label-primary',
    'cancelada'   => 'bg-label-secondary',
    default       => 'bg-label-secondary',
  };
}

function prioridade_badge_class(?string $prioridade): string {
  // fallback se tabela/coluna de prioridade existir
  $p = strtolower((string)$prioridade);
  return match ($p) {
    'alta'   => 'bg-label-danger',
    'media'  => 'bg-label-warning',
    'baixa'  => 'bg-label-success',
    default  => 'bg-label-secondary',
  };
}

function id_to_int(?string $empresaSel): ?int {
  if (!$empresaSel) return null;
  if (preg_match('/_(\d+)$/', $empresaSel, $m)) return (int)$m[1];
  return null;
}

/* ==================== POST (Aprovar / Reprovar) ==================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'], $_POST['pedido_id'])) {
  $acao     = $_POST['acao'];
  $pedidoId = (int)$_POST['pedido_id'];

  // Restringe o update ao escopo da empresa atual
  $filtroColuna = str_starts_with($idSelecionado, 'principal_') ? 'id_matriz' : 'id_solicitante';
  $filtroValor  = $idSelecionado;

  try {
    if ($acao === 'aprovar') {
      $sql = "UPDATE solicitacoes_b2b
              SET status = 'aprovada', aprovada_em = NOW(), updated_at = NOW()
              WHERE id = :id AND {$filtroColuna} = :empresa";
    } elseif ($acao === 'reprovar') {
      $sql = "UPDATE solicitacoes_b2b
              SET status = 'reprovada', updated_at = NOW()
              WHERE id = :id AND {$filtroColuna} = :empresa";
    } else {
      throw new RuntimeException('Ação inválida.');
    }
    $st = $pdo->prepare($sql);
    $st->execute([
      ':id'      => $pedidoId,
      ':empresa' => $filtroValor
    ]);

    // Evita reenvio de formulário
    header("Location: ./produtosSolicitados.php?id=" . urlencode($idSelecionado));
    exit;
  } catch (Throwable $e) {
    echo "<script>alert('Falha ao atualizar status: " . e($e->getMessage()) . "');</script>";
  }
}

/* ==================== Descobrir se existe tabela de itens ==================== */
$temTabelaItens = false;
try {
  $chk = $pdo->query("SHOW TABLES LIKE 'solicitacoes_b2b_itens'")->fetchColumn();
  $temTabelaItens = (bool)$chk;
} catch (Throwable $e) {
  $temTabelaItens = false;
}

/* ==================== Descobrir se existe coluna de itens JSON ==================== */
$colunaItensJson = null;
try {
  $cols = $pdo->query("SHOW COLUMNS FROM solicitacoes_b2b")->fetchAll(PDO::FETCH_COLUMN, 0);
  foreach ($cols as $c) {
    if (in_array($c, ['itens', 'itens_json'], true)) {
      $colunaItensJson = $c;
      break;
    }
  }
} catch (Throwable $e) {
  $colunaItensJson = null;
}

/* ==================== Consulta principal (Solicitações) ==================== */
$filtroColuna = str_starts_with($idSelecionado, 'principal_') ? 's.id_matriz' : 's.id_solicitante';
$sqlSolic =
  "SELECT 
      s.id,
      s.id_matriz,
      s.id_solicitante,
      s.status,
      s.total_estimado,
      s.created_at,
      s.updated_at,
      s.aprovada_em,
      s.enviada_em,
      s.entregue_em
      " . ($colunaItensJson ? ", s.`{$colunaItensJson}`" : "") . "
   FROM solicitacoes_b2b s
   WHERE {$filtroColuna} = :empresa
   ORDER BY s.created_at DESC";

$solicitacoes = [];
try {
  $st = $pdo->prepare($sqlSolic);
  $st->execute([':empresa' => $idSelecionado]);
  $solicitacoes = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $solicitacoes = [];
}

/* ==================== Resolver nome da Filial/Unidade (unidades.id = número do id_solicitante) ==================== */
$cacheUnidades = [];
function nomeUnidade(PDO $pdo, array &$cache, ?string $id_solicitante): string {
  if (!$id_solicitante) return '—';
  $num = id_to_int($id_solicitante);
  if (!$num) return '—';
  if (isset($cache[$num])) return $cache[$num];

  try {
    $st = $pdo->prepare("SELECT nome FROM unidades WHERE id = :id LIMIT 1");
    $st->execute([':id' => $num]);
    $nome = $st->fetchColumn();
    $cache[$num] = $nome ?: '—';
    return $cache[$num];
  } catch (Throwable $e) {
    return '—';
  }
}

/* ==================== Buscar itens por solicitação ==================== */
function buscarItensPedido(PDO $pdo, array $rowSolic, bool $temTabelaItens, ?string $colunaItensJson): array {
  $itens = [];

  if ($temTabelaItens) {
    try {
      $sql = "SELECT 
                i.id,
                i.solicitacao_id,
                i.produto_id,
                i.quantidade,
                i.prioridade,
                i.status AS status_item
              FROM solicitacoes_b2b_itens i
              WHERE i.solicitacao_id = :sid
              ORDER BY i.id ASC";
      $st = $pdo->prepare($sql);
      $st->execute([':sid' => (int)$rowSolic['id']]);
      $itens = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
      $itens = [];
    }
  } elseif ($colunaItensJson && !empty($rowSolic[$colunaItensJson])) {
    // fallback: itens em JSON na própria solicitacao
    try {
      $json = json_decode((string)$rowSolic[$colunaItensJson], true);
      if (is_array($json)) {
        foreach ($json as $j) {
          $itens[] = [
            'id'            => $j['id']            ?? null,
            'solicitacao_id'=> $rowSolic['id'],
            'produto_id'    => $j['produto_id']    ?? null,
            'quantidade'    => $j['quantidade']    ?? null,
            'prioridade'    => $j['prioridade']    ?? null,
            'status_item'   => $j['status']        ?? null,
          ];
        }
      }
    } catch (Throwable $e) {
      $itens = [];
    }
  }

  return $itens;
}

/* ==================== Completar itens com dados do estoque (código/nome) ==================== */
function enriquecerItensComEstoque(PDO $pdo, array $itens, string $empresaDoEstoque): array {
  if (!$itens) return [];

  // coleta ids de produto
  $ids = array_values(array_unique(array_filter(array_map(
    fn($x) => isset($x['produto_id']) ? (int)$x['produto_id'] : null, $itens
  ))));

  $map = [];
  if ($ids) {
    try {
      // estoque.empresa_id = unidade/filial que solicitou
      $in = implode(',', array_fill(0, count($ids), '?'));
      $sql = "SELECT id, nome, codigo_produto
              FROM estoque
              WHERE empresa_id = ? AND id IN ($in)";
      $st = $pdo->prepare($sql);

      $bind = array_merge([$empresaDoEstoque], $ids);
      $st->execute($bind);

      while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $map[(int)$r['id']] = [
          'nome'          => $r['nome'] ?? '—',
          'codigo_produto'=> $r['codigo_produto'] ?? '—',
        ];
      }
    } catch (Throwable $e) {
      // silencia
    }
  }

  foreach ($itens as &$it) {
    $pid = isset($it['produto_id']) ? (int)$it['produto_id'] : null;
    $it['produto_nome']   = $pid && isset($map[$pid]) ? $map[$pid]['nome'] : '—';
    $it['codigo_produto'] = $pid && isset($map[$pid]) ? $map[$pid]['codigo_produto'] : '—';
  }
  unset($it);
  return $itens;
}

/* ==================== HTML ==================== */
?>
<!DOCTYPE html>
<html lang="pt-BR" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../../assets/" data-template="vertical-menu-template-free">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Produtos Solicitados</title>

  <!-- FAVICON -->
  <link rel="icon" type="image/x-icon" href="../../assets/img/favicon/favicon.ico" />

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;700&display=swap" rel="stylesheet"/>

  <!-- Icons & Core CSS do template (mantidos) -->
  <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
  <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="../../assets/css/demo.css" />
  <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />

  <script src="../../assets/vendor/js/helpers.js"></script>
  <script src="../../assets/js/config.js"></script>

  <style>
    .status-badge { font-weight: 600; }
    .table-hover tbody tr:hover { background: rgba(0,0,0,0.02); }
  </style>
</head>

<body>
  <!-- Layout wrapper (mantido) -->
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">

      <!-- Menu lateral (mantido) -->
      <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
        <div class="app-brand demo">
          <a href="../dashboard.php?id=<?= urlencode($idSelecionado); ?>" class="app-brand-link">
            <span class="app-brand-logo demo">
              <?php if ($logoEmpresa): ?>
                <img src="../../upload/<?= e($logoEmpresa) ?>" alt="Logo" style="height:36px;">
              <?php else: ?>
                <img src="../../assets/img/logo.svg" alt="Logo" style="height:36px;">
              <?php endif; ?>
            </span>
            <span class="app-brand-text demo menu-text fw-bolder ms-2">Painel</span>
          </a>
        </div>
        <div class="menu-inner-shadow"></div>

        <ul class="menu-inner py-1">
          <li class="menu-header small text-uppercase"><span class="menu-header-text">B2B</span></li>

          <li class="menu-item active">
            <a href="./produtosSolicitados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
              <div>Produtos Solicitados</div>
            </a>
          </li>
          <li class="menu-item">
            <a href="./produtosEnviados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
              <div>Produtos Enviados</div>
            </a>
          </li>
          <li class="menu-item">
            <a href="./transferenciasPendentes.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
              <div>Transf. Pendentes</div>
            </a>
          </li>
        </ul>
      </aside>
      <!-- / Menu lateral -->

      <!-- Conteúdo -->
      <div class="layout-page">
        <!-- Navbar (mantido) -->
        <nav class="layout-navbar navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
          <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
            <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
              <i class="bx bx-menu bx-sm"></i>
            </a>
          </div>
          <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
            <div class="navbar-nav align-items-center">
              <span class="fw-semibold">Bem-vindo, <?= e($nomeUsuario) ?></span>
            </div>
          </div>
        </nav>

        <!-- Content wrapper -->
        <div class="content-wrapper">
          <!-- Content -->
          <div class="container-xxl flex-grow-1 container-p-y">

            <div class="row mb-4">
              <div class="col">
                <h4 class="fw-bold">Produtos Solicitados</h4>
                <p class="text-muted mb-0">Listagem dinâmica do banco de dados.</p>
              </div>
            </div>

            <!-- Card Tabela -->
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
                      <th>Data</th>
                      <th>Status</th>
                      <th style="width:220px">Ações</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!$solicitacoes): ?>
                      <tr>
                        <td colspan="9" class="text-center text-muted">Nenhuma solicitação encontrada.</td>
                      </tr>
                    <?php else: ?>

                    <?php foreach ($solicitacoes as $s): 
                      $filialNome = nomeUnidade($pdo, $cacheUnidades, $s['id_solicitante']);
                      $statusCls  = status_badge_class((string)$s['status']);
                      $dataCriado = $s['created_at'] ? date('d/m/Y', strtotime((string)$s['created_at'])) : '—';

                      // Itens da solicitação
                      $itens = buscarItensPedido($pdo, $s, $temTabelaItens, $colunaItensJson);
                      $itens = enriquecerItensComEstoque($pdo, $itens, (string)$s['id_solicitante']);

                      // Se não houver itens, ainda assim exibir uma linha do pedido
                      if (!$itens) {
                        $itens = [[
                          'id'              => null,
                          'solicitacao_id'  => $s['id'],
                          'produto_id'      => null,
                          'quantidade'      => null,
                          'prioridade'      => null,
                          'status_item'     => null,
                          'produto_nome'    => '—',
                          'codigo_produto'  => '—',
                        ]];
                      }

                      // modal id único por pedido
                      $modalId = 'modalDetalhes-' . (int)$s['id'];
                    ?>

                      <?php foreach ($itens as $idx => $it): 
                        $prioridadeCls = prioridade_badge_class($it['prioridade'] ?? '');
                      ?>
                        <tr>
                          <td>#<?= (int)$s['id'] ?></td>
                          <td><strong><?= e($filialNome) ?></strong></td>
                          <td><?= e((string)($it['codigo_produto'] ?? '—')) ?></td>
                          <td><?= e((string)($it['produto_nome']   ?? '—')) ?></td>
                          <td><?= $it['quantidade'] !== null ? (int)$it['quantidade'] : '—' ?></td>
                          <td><span class="badge <?= $prioridadeCls ?> status-badge"><?= e(ucfirst((string)($it['prioridade'] ?? '—'))) ?></span></td>
                          <td><?= e($dataCriado) ?></td>
                          <td><span class="badge <?= $statusCls ?> status-badge"><?= e(ucfirst((string)$s['status'])) ?></span></td>
                          <td>
                            <div class="d-flex gap-2">
                              <form method="post" class="m-0 p-0">
                                <input type="hidden" name="pedido_id" value="<?= (int)$s['id'] ?>">
                                <input type="hidden" name="acao" value="aprovar">
                                <button type="submit" class="btn btn-sm btn-success">Aprovar</button>
                              </form>
                              <form method="post" class="m-0 p-0">
                                <input type="hidden" name="pedido_id" value="<?= (int)$s['id'] ?>">
                                <input type="hidden" name="acao" value="reprovar">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Reprovar</button>
                              </form>
                              <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#<?= e($modalId) ?>">Detalhes</button>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>

                      <!-- Modal Detalhes do Pedido -->
                      <div class="modal fade" id="<?= e($modalId) ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                          <div class="modal-content">
                            <div class="modal-header">
                              <h5 class="modal-title">Detalhes do Pedido #<?= (int)$s['id'] ?></h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                            </div>
                            <div class="modal-body">
                              <div class="mb-3">
                                <div><strong>Filial:</strong> <?= e($filialNome) ?></div>
                                <div><strong>Status:</strong> <span class="badge <?= $statusCls ?> status-badge"><?= e(ucfirst((string)$s['status'])) ?></span></div>
                                <div><strong>Data da Solicitação:</strong> <?= e($dataCriado) ?></div>
                                <?php if (!empty($s['total_estimado'])): ?>
                                  <div><strong>Total Estimado:</strong> R$ <?= number_format((float)$s['total_estimado'], 2, ',', '.') ?></div>
                                <?php endif; ?>
                              </div>
                              <div class="table-responsive">
                                <table class="table table-sm">
                                  <thead>
                                    <tr>
                                      <th>#</th>
                                      <th>Código</th>
                                      <th>Produto</th>
                                      <th>Qtd</th>
                                      <th>Prioridade</th>
                                      <th>Status (item)</th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                  <?php foreach ($itens as $k => $it): 
                                    $pBadge = prioridade_badge_class($it['prioridade'] ?? '');
                                    $sItem  = $it['status_item'] ?? '—';
                                    $sItemC = status_badge_class((string)$sItem);
                                  ?>
                                    <tr>
                                      <td><?= (int)$s['id'] ?>-<?= $k+1 ?></td>
                                      <td><?= e((string)($it['codigo_produto'] ?? '—')) ?></td>
                                      <td><?= e((string)($it['produto_nome']   ?? '—')) ?></td>
                                      <td><?= $it['quantidade'] !== null ? (int)$it['quantidade'] : '—' ?></td>
                                      <td><span class="badge <?= $pBadge ?> status-badge"><?= e(ucfirst((string)($it['prioridade'] ?? '—'))) ?></span></td>
                                      <td><span class="badge <?= $sItemC ?> status-badge"><?= e(ucfirst((string)$sItem)) ?></span></td>
                                    </tr>
                                  <?php endforeach; ?>
                                  </tbody>
                                </table>
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            </div>
                          </div>
                        </div>
                      </div>
                      <!-- /Modal Detalhes -->

                    <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <!-- /Card Tabela -->

          </div>
          <!-- / Content -->

          <!-- Footer -->
          <footer class="content-footer footer bg-footer-theme text-center">
            <div class="container-xxl d-flex py-2 flex-md-row flex-column justify-content-center">
              <div class="mb-2 mb-md-0">
                &copy; <script>document.write(new Date().getFullYear());</script>, <strong>Açaínhadinhos</strong>. Todos os direitos reservados.
                Desenvolvido por <strong>Code</strong>.
              </div>
            </div>
          </footer>
          <!-- / Footer -->

          <div class="content-backdrop fade"></div>
        </div>
        <!-- / Content wrapper -->
      </div>
      <!-- / Layout page -->
    </div>
    <!-- / Layout container -->

    <!-- Overlay -->
    <div class="layout-overlay layout-menu-toggle"></div>
  </div>
  <!-- / Layout wrapper -->

  <!-- Core JS -->
  <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../../assets/vendor/libs/popper/popper.js"></script>
  <script src="../../assets/vendor/js/bootstrap.js"></script>
  <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="../../assets/vendor/js/menu.js"></script>

  <!-- Vendors JS -->
  <script src="../../assets/vendor/libs/apex-charts/apexcharts.js"></script>

  <!-- Main JS -->
  <script src="../../assets/js/main.js"></script>
  <script async defer src="https://buttons.github.io/buttons.js"></script>
</body>
</html>

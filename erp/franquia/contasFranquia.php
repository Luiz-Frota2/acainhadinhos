<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// ✅ Recupera o identificador vindo da URL
$idSelecionado = $_GET['id'] ?? '';

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

// ✅ Conexão com o banco de dados
require '../../assets/php/conexao.php';

// ✅ Buscar nome e tipo do usuário logado
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id = $_SESSION['usuario_id'];

try {
  $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
  $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
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

// ✅ Buscar logo da empresa (fallback no favicon)
try {
  $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1");
  $stmt->bindParam(':id_selecionado', $idSelecionado, PDO::PARAM_STR);
  $stmt->execute();
  $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);

  $logoEmpresa = !empty($empresaSobre['imagem'])
    ? "../../assets/img/empresa/" . $empresaSobre['imagem']
    : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
  $logoEmpresa = "../../assets/img/favicon/logo.png";
}

// ✅ Abas: define a aba ativa via ?tab= (pagamentos, solicitados, enviados, pendentes, historico, estoque, politica, relatorios)
$tabsValidas = ['pagamentos','solicitados','enviados','pendentes','historico','estoque','politica','relatorios'];
$tab = $_GET['tab'] ?? 'pagamentos';
if (!in_array($tab, $tabsValidas, true)) $tab = 'pagamentos';

?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">
<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
  <title>ERP - Franquias (Hub)</title>
  <meta name="description" content="Gestão B2B de Franquias - Hub único com abas." />
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($logoEmpresa) ?>" />
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
    .tab-card .card-header { border-bottom: 0; padding-bottom: 0; }
    .nav-tabs .nav-link { border-radius: .5rem .5rem 0 0; }
    .table thead th { white-space: nowrap; }
    .status-badge { font-size: .78rem; }
    .add-category{ background: #f5f5f9; border: 1px dashed #c7c7d1; border-radius: 10px; padding: 10px 14px; cursor: pointer;}
    .table-responsive { overflow-y: hidden; }
  </style>
</head>

<body>
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
      <!-- Menu -->
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

          <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Administração Franquias</span>
          </li>

          <!-- 🔥 Tudo em uma única página (esta) -->
          <li class="menu-item active open">
            <a href="./franquiasHub.php?id=<?= urlencode($idSelecionado); ?>&tab=pagamentos" class="menu-link">
              <i class="menu-icon tf-icons bx bx-building"></i>
              <div>Franquias (Hub)</div>
            </a>
          </li>

          <!-- Demais módulos -->
          <li class="menu-item">
            <a href="../rh/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-group"></i>
              <div>RH</div>
            </a>
          </li>
          <li class="menu-item">
            <a href="../financas/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-dollar"></i>
              <div>Finanças</div>
            </a>
          </li>
          <li class="menu-item">
            <a href="../pdv/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-desktop"></i>
              <div>PDV</div>
            </a>
          </li>
          <li class="menu-item">
            <a href="../empresa/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-briefcase"></i>
              <div>Empresa</div>
            </a>
          </li>
          <li class="menu-item">
            <a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-box"></i>
              <div>Estoque</div>
            </a>
          </li>
          <li class="menu-item">
            <a href="../filial/index.php?id=principal_1" class="menu-link">
              <i class="menu-icon tf-icons bx bx-building"></i>
              <div>Filial</div>
            </a>
          </li>
          <li class="menu-item">
            <a href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-group"></i>
              <div>Usuários</div>
            </a>
          </li>
          <li class="menu-item">
            <a href="https://wa.me/92991515710" target="_blank" class="menu-link">
              <i class="menu-icon tf-icons bx bx-support"></i>
              <div>Suporte</div>
            </a>
          </li>
        </ul>
      </aside>
      <!-- /Menu -->

      <!-- Layout container -->
      <div class="layout-page">
        <!-- Navbar -->
        <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
          <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
            <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
              <i class="bx bx-menu bx-sm"></i>
            </a>
          </div>

          <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
            <div class="navbar-nav align-items-center">
              <div class="nav-item d-flex align-items-center">
                <i class="bx bx-search fs-4 lh-0"></i>
                <input type="text" class="form-control border-0 shadow-none" placeholder="Search..." aria-label="Search..." />
              </div>
            </div>

            <ul class="navbar-nav flex-row align-items-center ms-auto">
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
                  <li><div class="dropdown-divider"></div></li>
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
                  <li><div class="dropdown-divider"></div></li>
                  <li>
                    <a class="dropdown-item" href="../logout.php?id=<?= urlencode($idSelecionado); ?>">
                      <i class="bx bx-power-off me-2"></i>
                      <span class="align-middle">Sair</span>
                    </a>
                  </li>
                </ul>
              </li>
            </ul>

          </div>
        </nav>
        <!-- /Navbar -->

        <div class="container-xxl flex-grow-1 container-p-y">
          <h4 class="fw-bold mb-0">
            <span class="text-muted fw-light"><a href="#">Franquias</a>/</span>
            Hub B2B
          </h4>
          <h5 class="fw-bold mt-3 mb-3 custor-font">
            <span class="text-muted fw-light">Tudo de Franquias em uma única página</span>
          </h5>

          <!-- Abas -->
          <div class="card tab-card">
            <div class="card-header pb-0">
              <ul class="nav nav-tabs card-header-tabs" id="franquiasTabs" role="tablist">
                <li class="nav-item"><a class="nav-link <?= $tab==='pagamentos'?'active':'' ?>" href="?id=<?= urlencode($idSelecionado) ?>&tab=pagamentos" role="tab">Pagamentos Solicitados</a></li>
                <li class="nav-item"><a class="nav-link <?= $tab==='solicitados'?'active':'' ?>" href="?id=<?= urlencode($idSelecionado) ?>&tab=solicitados" role="tab">Produtos Solicitados</a></li>
                <li class="nav-item"><a class="nav-link <?= $tab==='enviados'?'active':'' ?>" href="?id=<?= urlencode($idSelecionado) ?>&tab=enviados" role="tab">Produtos Enviados</a></li>
                <li class="nav-item"><a class="nav-link <?= $tab==='pendentes'?'active':'' ?>" href="?id=<?= urlencode($idSelecionado) ?>&tab=pendentes" role="tab">Transf. Pendentes</a></li>
                <li class="nav-item"><a class="nav-link <?= $tab==='historico'?'active':'' ?>" href="?id=<?= urlencode($idSelecionado) ?>&tab=historico" role="tab">Histórico Transf.</a></li>
                <li class="nav-item"><a class="nav-link <?= $tab==='estoque'?'active':'' ?>" href="?id=<?= urlencode($idSelecionado) ?>&tab=estoque" role="tab">Estoque Matriz</a></li>
                <li class="nav-item"><a class="nav-link <?= $tab==='politica'?'active':'' ?>" href="?id=<?= urlencode($idSelecionado) ?>&tab=politica" role="tab">Política de Envio</a></li>
                <li class="nav-item"><a class="nav-link <?= $tab==='relatorios'?'active':'' ?>" href="?id=<?= urlencode($idSelecionado) ?>&tab=relatorios" role="tab">Relatórios B2B</a></li>
              </ul>
            </div>

            <div class="card-body">
              <?php if ($tab==='pagamentos'): ?>
                <h5 class="card-title mb-3">Pagamentos Solicitados</h5>
                <div class="table-responsive">
                  <table class="table table-hover">
                    <thead>
                      <tr>
                        <th>#</th>
                        <th>Filial</th>
                        <th>Solicitante</th>
                        <th>Tipo</th>
                        <th>Valor</th>
                        <th>Vencimento</th>
                        <th>Status</th>
                        <th>Ações</th>
                      </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                      <tr>
                        <td>1001</td>
                        <td><strong>Franquia Centro</strong></td>
                        <td>João Silva</td>
                        <td>Fatura Produtos</td>
                        <td>R$ 1.250,00</td>
                        <td>10/10/2025</td>
                        <td><span class="badge bg-label-warning me-1 status-badge">Pendente</span></td>
                        <td>
                          <button class="btn btn-sm btn-outline-success">Aprovar</button>
                          <button class="btn btn-sm btn-outline-danger">Recusar</button>
                          <button class="btn btn-sm btn-outline-secondary">Detalhes</button>
                        </td>
                      </tr>
                      <tr>
                        <td>1002</td>
                        <td><strong>Franquia Norte</strong></td>
                        <td>Maria Costa</td>
                        <td>Serviço Logístico</td>
                        <td>R$ 320,00</td>
                        <td>08/10/2025</td>
                        <td><span class="badge bg-label-info me-1 status-badge">Em Análise</span></td>
                        <td>
                          <button class="btn btn-sm btn-outline-success">Aprovar</button>
                          <button class="btn btn-sm btn-outline-danger">Recusar</button>
                          <button class="btn btn-sm btn-outline-secondary">Detalhes</button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

              <?php elseif ($tab==='solicitados'): ?>
                <h5 class="card-title mb-3">Produtos Solicitados</h5>
                <div class="table-responsive">
                  <table class="table table-hover">
                    <thead>
                      <tr>
                        <th># Pedido</th>
                        <th>Filial</th>
                        <th>SKU</th>
                        <th>Produto</th>
                        <th>Qtd</th>
                        <th>Prioridade</th>
                        <th>Status</th>
                        <th>Ações</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td>PS-3021</td>
                        <td>Franquia Centro</td>
                        <td>ACA-500</td>
                        <td>Polpa Açaí 500g</td>
                        <td>120</td>
                        <td><span class="badge bg-label-danger status-badge">Alta</span></td>
                        <td><span class="badge bg-label-warning status-badge">Aguardando</span></td>
                        <td><button class="btn btn-sm btn-outline-primary">Atender</button></td>
                      </tr>
                    </tbody>
                  </table>
                </div>

              <?php elseif ($tab==='enviados'): ?>
                <h5 class="card-title mb-3">Produtos Enviados</h5>
                <div class="table-responsive">
                  <table class="table table-hover">
                    <thead>
                      <tr>
                        <th># Remessa</th>
                        <th>Filial</th>
                        <th>Itens</th>
                        <th>Volumes</th>
                        <th>Transportadora</th>
                        <th>Envio</th>
                        <th>Previsão</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td>ENV-8891</td>
                        <td>Franquia Norte</td>
                        <td>3</td>
                        <td>8</td>
                        <td>LogX</td>
                        <td>24/09/2025</td>
                        <td>27/09/2025</td>
                        <td><span class="badge bg-label-info status-badge">Em Trânsito</span></td>
                      </tr>
                    </tbody>
                  </table>
                </div>

              <?php elseif ($tab==='pendentes'): ?>
                <h5 class="card-title mb-3">Transferências Pendentes</h5>
                <div class="table-responsive">
                  <table class="table table-hover">
                    <thead>
                      <tr>
                        <th>#</th>
                        <th>Origem</th>
                        <th>Destino</th>
                        <th>SKU</th>
                        <th>Produto</th>
                        <th>Qtd</th>
                        <th>Solicitado em</th>
                        <th>Ações</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td>T-114</td>
                        <td>Matriz</td>
                        <td>Franquia Centro</td>
                        <td>ACA-1KG</td>
                        <td>Polpa Açaí 1kg</td>
                        <td>40</td>
                        <td>22/09/2025</td>
                        <td>
                          <button class="btn btn-sm btn-outline-primary">Iniciar</button>
                          <button class="btn btn-sm btn-outline-secondary">Detalhes</button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

              <?php elseif ($tab==='historico'): ?>
                <h5 class="card-title mb-3">Histórico de Transferências</h5>
                <div class="table-responsive">
                  <table class="table table-hover">
                    <thead>
                      <tr>
                        <th>#</th>
                        <th>Origem</th>
                        <th>Destino</th>
                        <th>Itens</th>
                        <th>Volumes</th>
                        <th>Saída</th>
                        <th>Entrega</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td>T-100</td>
                        <td>Matriz</td>
                        <td>Franquia Norte</td>
                        <td>5</td>
                        <td>10</td>
                        <td>10/09/2025</td>
                        <td>12/09/2025</td>
                        <td><span class="badge bg-label-success status-badge">Concluída</span></td>
                      </tr>
                    </tbody>
                  </table>
                </div>

              <?php elseif ($tab==='estoque'): ?>
                <h5 class="card-title mb-3">Estoque Matriz</h5>
                <div class="table-responsive">
                  <table class="table table-hover">
                    <thead>
                      <tr>
                        <th>SKU</th>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th>Qtd Disponível</th>
                        <th>Qtd Reservada</th>
                        <th>Ponto de Pedido</th>
                        <th>Ações</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td>ACA-500</td>
                        <td>Polpa Açaí 500g</td>
                        <td>Insumos</td>
                        <td>1.240</td>
                        <td>120</td>
                        <td>300</td>
                        <td><button class="btn btn-sm btn-outline-secondary">Detalhes</button></td>
                      </tr>
                    </tbody>
                  </table>
                </div>

              <?php elseif ($tab==='politica'): ?>
                <h5 class="card-title mb-3">Política de Envio</h5>
                <div class="table-responsive">
                  <table class="table table-hover">
                    <thead>
                      <tr>
                        <th>Regra</th>
                        <th>Descrição</th>
                        <th>Valor/Condição</th>
                        <th>Ativo</th>
                        <th>Ações</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td>Frete Grátis</td>
                        <td>Pedidos acima de R$ 2.000,00</td>
                        <td>R$ 2.000,00</td>
                        <td><span class="badge bg-label-success">Sim</span></td>
                        <td><button class="btn btn-sm btn-outline-primary">Editar</button></td>
                      </tr>
                    </tbody>
                  </table>
                </div>

              <?php elseif ($tab==='relatorios'): ?>
                <h5 class="card-title mb-3">Relatórios B2B</h5>
                <div class="table-responsive">
                  <table class="table table-hover">
                    <thead>
                      <tr>
                        <th>Relatório</th>
                        <th>Período</th>
                        <th>Última Geração</th>
                        <th>Status</th>
                        <th>Ações</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td>Vendas por Franquia</td>
                        <td>Set/2025</td>
                        <td>25/09/2025 16:32</td>
                        <td><span class="badge bg-label-info">Disponível</span></td>
                        <td>
                          <button class="btn btn-sm btn-outline-secondary">Visualizar</button>
                          <button class="btn btn-sm btn-outline-primary">Exportar</button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- CTA opcional -->
          <div class="mt-3 add-category justify-content-center d-flex text-center align-items-center"
               onclick="location.href='franquiasHub.php?id=<?= urlencode($idSelecionado); ?>&tab=pagamentos';">
            <i class="tf-icons bx bx-refresh me-2"></i>
            <span>Recarregar Hub de Franquias</span>
          </div>
        </div>
        <!-- /container -->
      </div>
      <!-- /Layout page -->
    </div>
    <!-- /Layout container -->
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
</body>
</html>

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
$usuario_id  = $_SESSION['usuario_id'];

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

// ✅ Logo da empresa (fallback)
try {
  $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1");
  $stmt->bindParam(':id', $idSelecionado, PDO::PARAM_STR);
  $stmt->execute();
  $sobre = $stmt->fetch(PDO::FETCH_ASSOC);
  $logoEmpresa = !empty($sobre['imagem']) ? "../../assets/img/empresa/" . $sobre['imagem'] : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
  $logoEmpresa = "../../assets/img/favicon/logo.png";
}
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
  <title>ERP — Produtos Solicitados</title>
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
    .table thead th { white-space: nowrap; }
    .status-badge { font-size: .78rem; }
    .toolbar { gap: .5rem; }
    .toolbar .form-select, .toolbar .form-control { max-width: 220px; }
    .badge-dot { display:inline-flex; align-items:center; gap:.4rem; }
    .badge-dot::before { content:''; width:8px; height:8px; border-radius:50%; background: currentColor; display:inline-block; }
  </style>
</head>
<body>
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">

      <!-- ====== ASIDE (EXATAMENTE COMO SOLICITADO) ====== -->
      <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
        <div class="app-brand demo">
          <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="app-brand-link">

            <span class="app-brand-text demo menu-text fw-bolder ms-2"
              style=" text-transform: capitalize;">Açaínhadinhos</span>

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
            <span class="menu-header-text">Administração Franquias</span>
          </li>

          <!-- Adicionar Filial -->
          <li class="menu-item ">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-building"></i>
              <div data-i18n="Adicionar">Franquias</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item">
                <a href="./franquiaAdicionada.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
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
            <ul class="menu-sub">
              <!-- Contas das Filiais -->
              <li class="menu-item">
                <a href="./contasFranquia.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Pagamentos Solic.</div>
                </a>
              </li>

              <!-- Produtos solicitados pelas filiais -->
              <li class="menu-item">
                <a href="./produtosSolicitados.php?id=<?= urlencode($idSelecionado); ?>"class="menu-link">
                  <div>Produtos Solicitados</div>
                </a>
              </li>

              <!-- Produtos enviados pela matriz -->
              <li class="menu-item active">
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
              <li class="menu-item">
                <a href="./estoqueMatriz.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Estoque Matriz</div>
                </a>
              </li>

              <!-- Configurações de Política de Envio -->
              <li class="menu-item">
                <a href="./politicasEnvio.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Política de Envio</div>
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
                  <div data-i18n="Vendas">Vendas por Franquias</div>
                </a>
              </li>

              <li class="menu-item">
                <a href="./MaisVendidos.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="MaisVendidos">Mais Vendidos</div>
                </a>
              </li>

              <li class="menu-item">
                <a href="./vendasPeriodo.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Pedidos">Vendas por Período</div>
                </a>
              </li>

              <li class="menu-item">
                <a href="./FinanceiroFranquia.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Financeiro">Financeiro</div>
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
            <a href="../filial/index.php?id=principal_1" class="menu-link">
              <i class="menu-icon tf-icons bx bx-building"></i>
              <div data-i18n="Authentications">Filial</div>
            </a>
          </li>
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
          <!--/MISC-->
        </ul>
      </aside>
      <!-- ====== /ASIDE ====== -->

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
                  <li><a class="dropdown-item" href="./contaUsuario.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-user me-2"></i><span class="align-middle">Minha Conta</span></a></li>
                  <li><a class="dropdown-item" href="#"><i class="bx bx-cog me-2"></i><span class="align-middle">Configurações</span></a></li>
                  <li><div class="dropdown-divider"></div></li>
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
            Produtos Enviados
          </h4>
          <h5 class="fw-bold mt-3 mb-3 custor-font">
            <span class="text-muted fw-light">Produtos Enviado para as Franquias</span>
          </h5>

          <!-- Toolbar / Filtros (HTML estático por enquanto) -->
       

          <!-- Tabela (HTML mock) -->
          <div class="card">
            <h5 class="card-header">Lista de Produtos Enviadoa</h5>
            <div class="table-responsive text-nowrap">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th># Pedido</th>
                    <th>Franquia</th>
                    <th>SKU</th>
                    <th>Produto</th>
                    <th>Qtd</th>
                    <th>Prioridade</th>
                    <th>Enviado em</th>
                    <th>Status</th>
                    <th>Ações</th>
                  </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                  <tr>
                    <td>PS-3021</td>
                    <td><strong>Franquia Centro</strong></td>
                    <td>ACA-500</td>
                    <td>Polpa Açaí 500g</td>
                    <td>120</td>
                    <td><span class="badge bg-label-danger status-badge">Alta</span></td>
                    <td>25/09/2025</td>
                    <td><span class="badge bg-label-warning status-badge">Enviado</span></td>
                    <td>
                      <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalDetalhes">Detalhes</button>
                    </td>
                  </tr>
                  <tr>
                    <td>PS-3022</td>
                    <td><strong>Franquia Norte</strong></td>
                    <td>ACA-1KG</td>
                    <td>Polpa Açaí 1kg</td>
                    <td>40</td>
                    <td><span class="badge bg-label-warning status-badge">Média</span></td>
                    <td>24/09/2025</td>
                    <td><span class="badge bg-label-warning status-badge">Enviado</span></td>
                    <td>
                      <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalDetalhes">Detalhes</button>
                    </td>
                  </tr>
                  <tr>
                    <td>PS-3023</td>
                    <td><strong>Franquia Sul</strong></td>
                    <td>COPO-300</td>
                    <td>Copo 300ml</td>
                    <td>500</td>
                    <td><span class="badge bg-label-info status-badge">Baixa</span></td>
                    <td>20/09/2025</td>
                    <td><span class="badge bg-label-success status-badge">Recebido</span></td>
                    <td>
                      <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalDetalhes">Detalhes</button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Modais mock -->
          <div class="modal fade" id="modalDetalhes" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">Detalhes do Pedido</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <p><strong>Filial:</strong> Franquia Centro</p>
                      <p><strong>SKU:</strong> ACA-500</p>
                      <p><strong>Produto:</strong> Polpa Açaí 500g</p>
                    </div>
                    <div class="col-md-6">
                      <p><strong>Qtd:</strong> 120</p>
                      <p><strong>Prioridade:</strong> Alta</p>
                      <p><strong>Status:</strong> Aguardando</p>
                    </div>
                    <div class="col-12">
                      <p><strong>Observações:</strong> Repor estoque para fim de semana.</p>
                      <p><strong>Anexos:</strong> <span class="text-muted">—</span></p>
                    </div>
                  </div>
                </div>
                <div class="modal-footer">
                  <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
              </div>
            </div>
          </div>
       

        </div><!-- /container -->
      </div><!-- /Layout page -->
    </div><!-- /Layout container -->
  </div>

  <!-- Core JS -->
  <script src="../../js/saudacao.js"></script>
  <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../../assets/vendor/libs/popper/popper.js"></script>
  <script src="../../assets/vendor/js/bootstrap.js"></script>
  <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="../../assets/vendor/js/menu.js"></script>
  <script src="../../assets/js/main.js"></script>
  <script async defer src="https://buttons.github.io/buttons.js"></script>
</body>
</html>

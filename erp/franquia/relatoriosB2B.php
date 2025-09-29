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
  <title>ERP — Relatórios B2B</title>
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
    .toolbar { gap:.5rem; }
    .toolbar .form-select, .toolbar .form-control { max-width: 220px; }
  </style>
</head>
<body>
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">

      <!-- ====== ASIDE (EXATAMENTE COMO VOCÊ PADRONIZOU) ====== -->
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

              <!-- Produtos solicitados -->
              <li class="menu-item">
                <a href="./produtosSolicitados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Produtos Solicitados</div>
                </a>
              </li>

              <!-- Produtos enviados -->
              <li class="menu-item">
                <a href="./produtosEnviados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Produtos Enviados</div>
                </a>
              </li>

              <!-- Transferências -->
              <li class="menu-item">
                <a href="./transferenciasPendentes.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Transf. Pendentes</div>
                </a>
              </li>
              <li class="menu-item">
                <a href="./historicoTransferencias.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Histórico Transf.</div>
                </a>
              </li>

              <!-- Estoque & Políticas -->
              <li class="menu-item">
                <a href="./estoqueMatriz.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Estoque Matriz</div>
                </a>
              </li>
              <!-- ✅ Relatórios B2B (ATIVO) -->
              <li class="menu-item active">
                <a href="./relatoriosB2B.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Relatórios B2B</div>
                </a>
              </li>
            </ul>
          </li>

          <!-- Relatórios (gerais) -->
          <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-bar-chart-alt-2"></i>
              <div data-i18n="Relatorios">Relatórios</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item">
                <a href="./VendasFranquias.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Vendas">Vendas por Franquias</div>
                </a>
              </li>
              <li class="menu-item">
                <a href="./MaisVendidos.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="MaisVendidos">Mais Vendidos</div>
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
            Relatórios B2B
          </h4>
          <h5 class="fw-bold mt-3 mb-3 custor-font">
            <span class="text-muted fw-light">Indicadores e resumos do canal B2B</span>
          </h5>

          <!-- Filtros (HTML estático por enquanto) -->
          <div class="card mb-3">
            <div class="card-body d-flex flex-wrap toolbar">
              <select class="form-select me-2">
                <option>Período: Mês Atual</option>
                <option>Últimos 30 dias</option>
                <option>Últimos 90 dias</option>
                <option>Este ano</option>
              </select>
              <select class="form-select me-2">
                <option>Todas as Franquias</option>
                <option>Franquia Centro</option>
                <option>Franquia Norte</option>
                <option>Franquia Sul</option>
              </select>
              <button class="btn btn-outline-secondary me-2"><i class="bx bx-filter-alt me-1"></i> Aplicar</button>
              <div class="ms-auto d-flex gap-2">
                <button class="btn btn-outline-dark"><i class="bx bx-file me-1"></i> Exportar XLSX</button>
                <button class="btn btn-outline-dark"><i class="bx bx-download me-1"></i> Exportar CSV</button>
                <button class="btn btn-outline-dark"><i class="bx bx-printer me-1"></i> Imprimir</button>
              </div>
            </div>
          </div>

          <!-- Resumo Mensal -->
          <div class="card mb-3">
            <h5 class="card-header">Resumo do Período</h5>
            <div class="table-responsive">
              <table class="table table-striped table-hover">
                <thead>
                  <tr>
                    <th>Métrica</th>
                    <th>Valor</th>
                    <th>Variação</th>
                    <th>Obs.</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>Pedidos B2B</td>
                    <td>184</td>
                    <td><span class="text-success">+12,3%</span></td>
                    <td>Alta puxada por Centro</td>
                  </tr>
                  <tr>
                    <td>Itens Solicitados</td>
                    <td>5.430</td>
                    <td><span class="text-success">+8,1%</span></td>
                    <td>SKU ACA-500 liderando</td>
                  </tr>
                  <tr>
                    <td>Faturamento Estimado</td>
                    <td>R$ 128.450,00</td>
                    <td><span class="text-danger">-3,5%</span></td>
                    <td>Ticket menor no Sul</td>
                  </tr>
                  <tr>
                    <td>Ticket Médio</td>
                    <td>R$ 698,10</td>
                    <td><span class="text-danger">-5,2%</span></td>
                    <td>Mix com mais descartáveis</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Vendas por Franquia -->
          <div class="card mb-3">
            <h5 class="card-header">Vendas / Pedidos por Franquia</h5>
            <div class="table-responsive">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>Franquia</th>
                    <th>Pedidos</th>
                    <th>Itens</th>
                    <th>Faturamento (R$)</th>
                    <th>Ticket Médio (R$)</th>
                    <th>% do Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td><strong>Franquia Centro</strong></td>
                    <td>74</td>
                    <td>2.140</td>
                    <td>R$ 58.700,00</td>
                    <td>R$ 793,24</td>
                    <td>45%</td>
                  </tr>
                  <tr>
                    <td><strong>Franquia Norte</strong></td>
                    <td>58</td>
                    <td>1.720</td>
                    <td>R$ 41.320,00</td>
                    <td>R$ 712,41</td>
                    <td>32%</td>
                  </tr>
                  <tr>
                    <td><strong>Franquia Sul</strong></td>
                    <td>52</td>
                    <td>1.570</td>
                    <td>R$ 28.430,00</td>
                    <td>R$ 546,73</td>
                    <td>23%</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Produtos Mais Solicitados -->
          <div class="card mb-3">
            <h5 class="card-header">Produtos Mais Solicitados</h5>
            <div class="table-responsive">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>SKU</th>
                    <th>Produto</th>
                    <th>Quantidade</th>
                    <th>Pedidos</th>
                    <th>Participação</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>ACA-500</td>
                    <td>Polpa Açaí 500g</td>
                    <td>1.980</td>
                    <td>96</td>
                    <td>36%</td>
                  </tr>
                  <tr>
                    <td>ACA-1KG</td>
                    <td>Polpa Açaí 1kg</td>
                    <td>1.210</td>
                    <td>64</td>
                    <td>22%</td>
                  </tr>
                  <tr>
                    <td>COPO-300</td>
                    <td>Copo 300ml</td>
                    <td>1.050</td>
                    <td>51</td>
                    <td>19%</td>
                  </tr>
                  <tr>
                    <td>COLH-PP</td>
                    <td>Colher PP</td>
                    <td>890</td>
                    <td>40</td>
                    <td>16%</td>
                  </tr>
                  <tr>
                    <td>GRAN-200</td>
                    <td>Granola 200g</td>
                    <td>300</td>
                    <td>18</td>
                    <td>7%</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- SLA de Atendimento de Pedidos -->
          <div class="card mb-3">
            <h5 class="card-header">SLA de Atendimento</h5>
            <div class="table-responsive">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>Faixa de SLA</th>
                    <th>Pedidos</th>
                    <th>SLA Médio</th>
                    <th>Dentro do Prazo</th>
                    <th>Observações</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>0–24h</td>
                    <td>98</td>
                    <td>12h</td>
                    <td>92%</td>
                    <td>Meta batida</td>
                  </tr>
                  <tr>
                    <td>24–48h</td>
                    <td>56</td>
                    <td>31h</td>
                    <td>78%</td>
                    <td>Gargalo separação</td>
                  </tr>
                  <tr>
                    <td>> 48h</td>
                    <td>30</td>
                    <td>54h</td>
                    <td>40%</td>
                    <td>Revisar logística Norte</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Pagamentos x Entregas (resumo simples) -->
          <div class="card mb-3">
            <h5 class="card-header">Pagamentos x Entregas (Resumo)</h5>
            <div class="table-responsive">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>Métrica</th>
                    <th>Quantidade</th>
                    <th>Valor (R$)</th>
                    <th>Status Principal</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>Pagamentos Solicitados</td>
                    <td>62</td>
                    <td>R$ 72.300,00</td>
                    <td>Pendente/Análise</td>
                  </tr>
                  <tr>
                    <td>Remessas Enviadas</td>
                    <td>48</td>
                    <td>—</td>
                    <td>Em Trânsito</td>
                  </tr>
                  <tr>
                    <td>Remessas Concluídas</td>
                    <td>39</td>
                    <td>—</td>
                    <td>Entregue</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

        </div>
        <!-- /container -->
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

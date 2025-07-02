<?php
session_start();
require_once '../../assets/php/conexao.php';

// ✅ Recupera o identificador vindo da URL
$idSelecionado = $_GET['id'] ?? '';

// ✅ Verifica se a pessoa está logada
if (
  !isset($_SESSION['usuario_logado']) ||
  !isset($_SESSION['empresa_id']) ||
  !isset($_SESSION['tipo_empresa']) ||
  !isset($_SESSION['usuario_id'])
) {
  header("Location: .././login.php?id=$idSelecionado");
  exit;
}

// ✅ Valida o tipo de empresa e o acesso permitido
if (str_starts_with($idSelecionado, 'principal_')) {
  if ($_SESSION['tipo_empresa'] !== 'principal' || $_SESSION['empresa_id'] != 1) {
    echo "<script>
              alert('Acesso negado!');
              window.location.href = '.././login.php?id=$idSelecionado';
          </script>";
    exit;
  }
  $id = 1;
} elseif (str_starts_with($idSelecionado, 'filial_')) {
  $idFilial = (int) str_replace('filial_', '', $idSelecionado);
  if ($_SESSION['tipo_empresa'] !== 'filial' || $_SESSION['empresa_id'] != $idFilial) {
    echo "<script>
              alert('Acesso negado!');
              window.location.href = '.././login.php?id=$idSelecionado';
          </script>";
    exit;
  }
  $id = $idFilial;
} else {
  echo "<script>
          alert('Empresa não identificada!');
          window.location.href = '.././login.php?id=$idSelecionado';
      </script>";
  exit;
}

// ✅ Buscar imagem da tabela sobre_empresa
try {
  $sql = "SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1";
  $stmt = $pdo->prepare($sql);
  $stmt->bindParam(':id_selecionado', $idSelecionado, PDO::PARAM_STR);
  $stmt->execute();
  $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);

  $logoEmpresa = !empty($empresaSobre['imagem'])
    ? "../../assets/img/empresa/" . $empresaSobre['imagem']
    : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
  $logoEmpresa = "../../assets/img/favicon/logo.png";
}

// ✅ Buscar nome e nível do usuário logado
$nomeUsuario = 'Usuário';
$nivelUsuario = 'Comum';
$usuario_id = $_SESSION['usuario_id'];

try {
  $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
  $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
  $stmt->execute();
  $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($usuario) {
    $nomeUsuario = $usuario['usuario'];
    $nivelUsuario = $usuario['nivel'];
  }
} catch (PDOException $e) {
  $nomeUsuario = 'Erro ao carregar nome';
  $nivelUsuario = 'Erro ao carregar nível';
}

// ✅ Buscar dados estatísticos da empresa
$totalFuncionarios = 0;
$taxaAbsenteismo = 0;
$distribuicaoSetores = [];
$ultimosRegistros = [];
$horasTrabalhadas = [];
$bancoHoras = 0;
$pontosAdicionados = 0;
$frequenciaMensal = [];
$funcionariosAtrasados = [];

// Filtros para os últimos registros
$filtroRegistros = $_GET['filtro_registros'] ?? 'hoje';

try {
  // Total de funcionários
  $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM funcionarios WHERE empresa_id = :empresa_id");
  $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
  $stmt->execute();
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  $totalFuncionarios = $result['total'] ?? 0;

  // Taxa de absenteísmo (simplificado)
  $hoje = date('Y-m-d');
  $stmt = $pdo->prepare("SELECT COUNT(DISTINCT cpf) as ausentes FROM pontos WHERE empresa_id = :empresa_id AND data = :hoje AND entrada IS NULL");
  $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
  $stmt->bindParam(':hoje', $hoje, PDO::PARAM_STR);
  $stmt->execute();
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  $ausentes = $result['ausentes'] ?? 0;
  $taxaAbsenteismo = $totalFuncionarios > 0 ? round(($ausentes / $totalFuncionarios) * 100, 2) : 0;

  // Distribuição por setor
  $stmt = $pdo->prepare("SELECT setor, COUNT(*) as total FROM funcionarios WHERE empresa_id = :empresa_id GROUP BY setor");
  $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
  $stmt->execute();
  $distribuicaoSetores = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Últimos registros de ponto com filtros
  $sqlUltimosRegistros = "SELECT nome, data, entrada, saida_final FROM pontos WHERE empresa_id = :empresa_id";

  switch ($filtroRegistros) {
    case 'hoje':
      $sqlUltimosRegistros .= " AND data = CURDATE()";
      break;
    case 'ontem':
      $sqlUltimosRegistros .= " AND data = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
      break;
    case 'semana':
      $sqlUltimosRegistros .= " AND data BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND CURDATE()";
      break;
    case 'ano':
      $sqlUltimosRegistros .= " AND YEAR(data) = YEAR(CURDATE())";
      break;
  }

  $sqlUltimosRegistros .= " ORDER BY data DESC, entrada DESC LIMIT 5";

  $stmt = $pdo->prepare($sqlUltimosRegistros);
  $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
  $stmt->execute();
  $ultimosRegistros = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Horas trabalhadas nos últimos 12 meses (inteiro)
  $currentYear = date('Y');
  for ($i = 1; $i <= 12; $i++) {
    $stmt = $pdo->prepare("SELECT SUM(HOUR(TIMEDIFF(saida_final, entrada))) as total_horas 
                          FROM pontos 
                          WHERE empresa_id = :empresa_id 
                          AND MONTH(data) = :mes 
                          AND YEAR(data) = :ano 
                          AND entrada IS NOT NULL 
                          AND saida_final IS NOT NULL");
    $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
    $stmt->bindParam(':mes', $i, PDO::PARAM_INT);
    $stmt->bindParam(':ano', $currentYear, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $horasTrabalhadas[] = $result['total_horas'] ?? 0;
  }

  // Banco de horas (simplificado)
  $stmt = $pdo->prepare("SELECT SEC_TO_TIME(SUM(TIME_TO_SEC(hora_extra))) as total FROM pontos WHERE empresa_id = :empresa_id AND YEAR(data) = YEAR(CURDATE())");
  $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
  $stmt->execute();
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  $bancoHoras = $result['total'] ?? '00:00:00';

  // Pontos adicionados (simplificado - contagem de registros este ano)
  $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pontos WHERE empresa_id = :empresa_id AND YEAR(data) = YEAR(CURDATE())");
  $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
  $stmt->execute();
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  $pontosAdicionados = $result['total'] ?? 0;

  // Frequência mensal (últimos 12 meses)
  for ($i = 1; $i <= 12; $i++) {
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT cpf) as total FROM pontos WHERE empresa_id = :empresa_id AND MONTH(data) = :mes AND YEAR(data) = :ano");
    $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
    $stmt->bindParam(':mes', $i, PDO::PARAM_INT);
    $stmt->bindParam(':ano', $currentYear, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $frequenciaMensal[] = $result['total'] ?? 0;
  }

  // Funcionários atrasados (entrada após 10 minutos de tolerância)
  $stmt = $pdo->prepare("SELECT nome, data, entrada 
                        FROM pontos 
                        WHERE empresa_id = :empresa_id 
                        AND TIME(entrada) > '08:10:00' 
                        AND TIME(entrada) < '18:00:00'
                        AND data = CURDATE()
                        ORDER BY entrada DESC");
  $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
  $stmt->execute();
  $funcionariosAtrasados = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
  error_log("Erro ao buscar dados estatísticos: " . $e->getMessage());
}

// Preparar dados para os gráficos
$labelsMeses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dec'];

// Converter banco de horas para formato legível
$bancoHorasArray = explode(':', $bancoHoras);
$bancoHorasFormatado = $bancoHorasArray[0] . 'h ' . $bancoHorasArray[1] . 'min';

// Preparar dados para o gráfico de setores
$setoresChartLabels = [];
$setoresChartData = [];
foreach ($distribuicaoSetores as $setor) {
  $setoresChartLabels[] = $setor['setor'];
  $setoresChartData[] = $setor['total'];
}

// Se não houver dados de setores, usar valores padrão
if (empty($setoresChartLabels)) {
  $setoresChartLabels = ['Administrativo', 'Operacional', 'Vendas', 'Suporte'];
  $setoresChartData = [12, 30, 10, 6];
}
?>

<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
  data-assets-path="../assets/">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>ERP - Recursos Humanos</title>

  <meta name="description" content="" />

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($logoEmpresa) ?>" />

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
    rel="stylesheet" />

  <!-- Icons. Uncomment required icon fonts -->
  <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />

  <!-- Core CSS -->
  <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="../../assets/css/demo.css" />

  <!-- Vendors CSS -->
  <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

  <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />

  <!-- Page CSS -->
  <!-- Helpers -->
  <script src="../../assets/vendor/js/helpers.js"></script>

  <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
  <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
  <script src="../../assets/js/config.js"></script>
</head>

<body>
  <!-- Layout wrapper -->
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
      <!-- Menu -->

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
          <li class="menu-item active">
            <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
              <i class="menu-icon tf-icons bx bx-home-circle"></i>
              <div data-i18n="Analytics">Dashboard</div>
            </a>
          </li>

          <!-- Recursos Humanos (RH) -->
          <li class="menu-header small text-uppercase"><span class="menu-header-text">Recursos Humanos</span></li>

          <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-buildings"></i>
              <div data-i18n="Authentications">Setores</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item">
                <a href="./setoresAdicionados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Adicionados</div>
                </a>
              </li>
            </ul>
          </li>
          <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-user-plus"></i>
              <div data-i18n="Authentications">Funcionários</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item">
                <a href="./funcionarioAdicionados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Adicionados </div>
                </a>
              </li>

            </ul>
          </li>

          <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-time"></i>
              <div data-i18n="Sistema de Ponto">Sistema de Ponto</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item">
                <a href="./escalaAdicionadas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Escalas e Configuração"> Escalas Adicionadas</div>
                </a>
              </li>
              <li class="menu-item">
                <a href="./adicionarPonto.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Registro de Ponto Eletrônico">Adicionar Ponto</div>
                </a>
              </li>
              <li class="menu-item ">
                <a href="./ajustePonto.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Registro de Ponto Eletrônico">Ajuste de Ponto</div>
                </a>
              </li>
              <li class="menu-item">
                <a href="./atestadosFuncionarios.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Atestados</div>
                </a>
              </li>

            </ul>
          </li>
          <!-- Menu Relatórios -->
          <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-trending-up"></i>
              <div data-i18n="Relatórios">Relatórios</div>
            </a>

            <ul class="menu-sub">
              <li class="menu-item">
                <a href="./relatorio.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Visualização Geral">Visualização Geral</div>
                </a>
              </li>
              <li class="menu-item">
                <a href="./bancoHoras.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Ajuste de Horários e Banco de Horas">Banco de Horas</div>
                </a>
              </li>
              <li class="menu-item">
                <a href="./frequencia.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Ajuste de Horários e Banco de Horas">Frequência</div>
                </a>
              </li>
              <li class="menu-item">
                <a href="./frequenciaGeral.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Ajuste de Horários e Banco de Horas">Frequência Geral</div>
                </a>
              </li>
            </ul>
          </li>

          <!-- Misc -->
          <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
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
            <a href="../delivery/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-cart"></i>
              <div data-i18n="Authentications">Delivery</div>
            </a>
          </li>
          <li class="menu-item">
            <a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-box"></i>
              <div data-i18n="Authentications">Estoque</div>
            </a>
          </li>
          <li class="menu-item">
            <a href="../clientes/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-user"></i>
              <div data-i18n="Authentications">Clientes</div>
            </a>
          </li>
          <?php
          $isFilial = str_starts_with($idSelecionado, 'filial_');
          $link = $isFilial
            ? '../matriz/index.php?id=' . urlencode($idSelecionado)
            : '../filial/index.php?id=principal_1';
          $titulo = $isFilial ? 'Matriz' : 'Filial';
          ?>

          <li class="menu-item">
            <a href="<?= $link ?>" class="menu-link">
              <i class="menu-icon tf-icons bx bx-cog"></i>
              <div data-i18n="Authentications"><?= $titulo ?></div>
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
              <!-- Place this tag where you want the button to render. -->
              <!-- User -->
              <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                  <div class="avatar avatar-online">
                    <img src="<?= htmlspecialchars($logoEmpresa) ?>" alt class="w-px-40 h-auto rounded-circle" />
                  </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li>
                    <a class="dropdown-item" href="#">
                      <div class="d-flex">
                        <div class="flex-shrink-0 me-3">
                          <div class="avatar avatar-online">
                            <img src="<?= htmlspecialchars($logoEmpresa) ?>" alt
                              class="w-px-40 h-auto rounded-circle" />
                          </div>
                        </div>
                        <div class="flex-grow-1">
                          <!-- Exibindo o nome e nível do usuário -->
                          <span class="fw-semibold d-block"><?php echo $nomeUsuario; ?></span>
                          <small class="text-muted"><?php echo $nivelUsuario; ?></small>
                        </div>
                      </div>
                    </a>
                  </li>
                  <li>
                    <div class="dropdown-divider"></div>
                  </li>
                  <li>
                    <a class="dropdown-item" href="#">
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

        <!-- Content -->
        <div class="container-xxl flex-grow-1 container-p-y">
          <div class="row">
            <div class="col-lg-8 mb-4 order-0">
              <div class="card">
                <div class="d-flex align-items-end row">
                  <div class="col-sm-7">
                    <div class="card-body">
                      <h5 class="card-title saudacao text-primary" data-setor="RH"></h5>
                      <p class="mb-4">Bem-vindo ao painel de controle. Aqui você pode acompanhar todas as métricas
                        importantes da sua empresa.</p>
                    </div>
                  </div>
                  <div class="col-sm-5 text-center text-sm-left">
                    <div class="card-body pb-0 px-0 px-md-4">
                      <img src="../../assets/img/illustrations/man-with-laptop-light.png" height="154"
                        alt="View Badge User" data-app-dark-img="illustrations/man-with-laptop-dark.png"
                        data-app-light-img="illustrations/man-with-laptop-light.png" />
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-4 col-md-4 order-1">
              <div class="row">

                <div class="col-lg-6 col-md-12 col-6 mb-4 d-flex align-items-stretch" height="170">
                  <div class="card w-100">
                    <div class="card-body">
                      <div class="card-title d-flex align-items-start justify-content-between">
                        <div class="avatar flex-shrink-0 me-3">
                          <span class="avatar-initial rounded bg-label-info">
                            <i class="bx bx-user"></i>
                          </span>
                        </div>
                        <div class="dropdown">
                          <button class="btn p-0" type="button" id="cardOpt3" data-bs-toggle="dropdown"
                            aria-haspopup="true" aria-expanded="false">
                            <i class="bx bx-dots-vertical-rounded"></i>
                          </button>
                          <div class="dropdown-menu dropdown-menu-end" aria-labelledby="cardOpt3">
                            <a class="dropdown-item" href="javascript:void(0);">Ver Mais</a>
                          </div>
                        </div>
                      </div>
                      <span class="fw-semibold d-block mb-1">Total de Funcionários</span>
                      <h3 class="card-title mb-2"><?= $totalFuncionarios ?></h3>
                      <small class="text-success fw-semibold"><i class="bx bx-up-arrow-alt"></i>
                        +<?= round($totalFuncionarios * 0.1) ?> novos</small>
                    </div>
                  </div>
                </div>

                <div class="col-lg-6 col-md-12 col-6 mb-4 d-flex align-items-stretch h-100">
                  <div class="card w-100">
                    <div class="card-body">
                      <div class="card-title d-flex align-items-start justify-content-between">
                        <div class="avatar flex-shrink-0 me-3">
                          <span class="avatar-initial rounded bg-label-danger">
                            <i class="bx bx-time"></i>
                          </span>
                        </div>
                        <div class="dropdown">
                          <button class="btn p-0" type="button" id="cardOpt6" data-bs-toggle="dropdown"
                            aria-haspopup="true" aria-expanded="false">
                            <i class="bx bx-dots-vertical-rounded"></i>
                          </button>
                          <div class="dropdown-menu dropdown-menu-end" aria-labelledby="cardOpt6">
                            <a class="dropdown-item" href="javascript:void(0);">Ver Mais</a>
                          </div>
                        </div>
                      </div>
                      <span>Taxa de Absenteísmo</span>
                      <h3 class="card-title text-nowrap mb-1">+<?= $taxaAbsenteismo ?>%</h3>
                      <small class="text-danger fw-semibold"><i class="bx bx-up-arrow-alt"></i>
                        +<?= round($taxaAbsenteismo * 0.1, 2) ?>%</small>
                    </div>
                  </div>
                </div>

              </div>
            </div>

            <!-- Total Revenue -->
            <div class="col-12 col-lg-8 order-2 order-md-3 order-lg-2 mb-4">
              <div class="card">
                <div class="row row-bordered g-0">
                  <div class="col-md-12">
                    <h5 class="card-header m-0 me-2 pb-3">Horas Trabalhadas (<?= date('Y') ?>)</h5>
                    <div id="totalRevenueChart" class="px-2"></div>
                  </div>
                </div>
              </div>
            </div>

            <!--/ Total Revenue -->
            <div class="col-12 col-md-8 col-lg-4 order-3 order-md-2">
              <div class="row">
                <div class="col-12 mb-4">
                  <div class="card">
                    <div class="card-body">
                      <div class="d-flex justify-content-between flex-sm-row flex-column gap-3">
                        <div class="d-flex flex-sm-column flex-row align-items-start justify-content-between">
                          <div class="card-title">
                            <h5 class="text-nowrap mb-2">Banco de Horas</h5>
                            <span class="badge bg-label-warning rounded-pill">Ano <?= date('Y') ?></span>
                          </div>
                          <div class="mt-sm-auto">
                            <h3 class="mb-0"><?= $bancoHorasFormatado ?></h3>
                          </div>
                        </div>
                        <div id="bankHoursChart"></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col-12 mb-4">
                  <div class="card">
                    <div class="card-body">
                      <div class="d-flex justify-content-between flex-sm-row flex-column gap-3">
                        <div class="d-flex flex-sm-column flex-row align-items-start justify-content-between">
                          <div class="card-title">
                            <h5 class="text-nowrap mb-2">Pontos Registrados</h5>
                            <span class="badge bg-label-warning rounded-pill">Ano <?= date('Y') ?></span>
                          </div>
                          <div class="">
                            <h3 class="mb-0"><?= $pontosAdicionados ?></h3>
                          </div>
                        </div>
                        <div id="profileReportChart2"></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="row">
            <!-- Order Statistics -->
            <div class="col-md-6 col-lg-4 col-xl-4 order-0 mb-4">
              <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between pb-0">
                  <div class="card-title mb-0">
                    <h5 class="m-0 me-2">Distribuição por Setor</h5>
                    <small class="text-muted">Total: <?= $totalFuncionarios ?> funcionários</small>
                  </div>
                  <div class="dropdown">
                    <button class="btn p-0" type="button" id="orederStatistics" data-bs-toggle="dropdown"
                      aria-haspopup="true" aria-expanded="false">
                      <i class="bx bx-dots-vertical-rounded"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="orederStatistics">
                      <a class="dropdown-item" href="javascript:void(0);">Selecionar Tudo</a>
                      <a class="dropdown-item" href="javascript:void(0);">Atualizar</a>
                    </div>
                  </div>
                </div>
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex flex-column align-items-center gap-1">
                      <h2 class="mb-2"><?= $totalFuncionarios ?></h2>
                      <span>Funcionários Totais</span>
                    </div>
                    <div id="orderStatisticsChart"></div>
                  </div>
                  <ul class="p-0 m-0">
                    <?php foreach ($distribuicaoSetores as $index => $setor): ?>
                      <li class="d-flex mb-4 pb-1">
                        <div class="avatar flex-shrink-0 me-3">
                          <span
                            class="avatar-initial rounded bg-label-<?= ['primary', 'success', 'warning', 'info'][$index % 4] ?>">
                            <i class="bx bx-<?= ['user', 'store', 'cart', 'support'][$index % 4] ?>"></i>
                          </span>
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                          <div class="me-2">
                            <h6 class="mb-0"><?= htmlspecialchars($setor['setor']) ?></h6>
                            <small class="text-muted"><?= $setor['total'] ?> funcionários</small>
                          </div>
                          <div class="user-progress">
                            <small class="fw-semibold"><?= round(($setor['total'] / $totalFuncionarios) * 100) ?>%</small>
                          </div>
                        </div>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              </div>
            </div>
            <!--/ Order Statistics -->

            <!-- Expense Overview -->
            <div class="col-md-6 col-lg-4 order-1 mb-4">
              <div class="card h-100">
                <div class="card-header">
                  <ul class="nav nav-pills" role="tablist">
                    <li class="nav-item">
                      <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab"
                        data-bs-target="#navs-tabs-line-card-frequencia" aria-controls="navs-tabs-line-card-frequencia"
                        aria-selected="true">
                        Frequências
                      </button>
                    </li>
                    <li class="nav-item">
                      <button type="button" class="nav-link" role="tab" data-bs-toggle="tab"
                        data-bs-target="#navs-tabs-line-card-atrasos" aria-controls="navs-tabs-line-card-atrasos">
                        Atrasos
                      </button>
                    </li>
                  </ul>
                </div>
                <div class="card-body px-0">
                  <div class="tab-content p-0">
                    <div class="tab-pane fade show active" id="navs-tabs-line-card-frequencia" role="tabpanel">
                      <div class="d-flex p-4 pt-3">
                        <div class="avatar flex-shrink-0 me-3">
                          <span class="avatar-initial rounded bg-label-info">
                            <i class="bx bx-calendar text-black"></i>
                          </span>
                        </div>
                        <div>
                          <small class="text-muted d-block">Total Frequência</small>
                          <div class="d-flex align-items-center">
                            <h6 class="mb-0 me-1"><?= array_sum($frequenciaMensal) ?></h6>
                          </div>
                        </div>
                      </div>
                      <div id="incomeChart"></div>
                    </div>
                    <div class="tab-pane fade" id="navs-tabs-line-card-atrasos" role="tabpanel">
                      <div class="d-flex p-4 pt-3">
                        <div class="avatar flex-shrink-0 me-3">
                          <span class="avatar-initial rounded bg-label-danger">
                            <i class="bx bx-time-five text-black"></i>
                          </span>
                        </div>
                        <div>
                          <small class="text-muted d-block">Atrasos Hoje</small>
                          <div class="d-flex align-items-center">
                            <h6 class="mb-0 me-1"><?= count($funcionariosAtrasados) ?></h6>
                          </div>
                        </div>
                      </div>
                      <ul class="p-0 m-0">
                        <?php foreach ($funcionariosAtrasados as $index => $atrasado):
                          $entrada = $atrasado['entrada'] ? date('H:i', strtotime($atrasado['entrada'])) : '--:--';
                          $bgColors = ['primary', 'success', 'warning', 'info', 'secondary'];
                          ?>
                          <li class="d-flex mb-4 pb-1">
                            <div class="avatar flex-shrink-0 me-3">
                              <span class="avatar-initial rounded bg-label-<?= $bgColors[$index % count($bgColors)] ?>">
                                <i class="bx bx-user"></i>
                              </span>
                            </div>
                            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                              <div class="me-2">
                                <h6 class="mb-0"><?= htmlspecialchars($atrasado['nome']) ?></h6>
                                <small class="text-muted d-block mb-1">Entrada: <?= $entrada ?></small>
                              </div>
                            </div>
                          </li>
                        <?php endforeach; ?>
                        <?php if (empty($funcionariosAtrasados)): ?>
                          <li class="d-flex mb-4 pb-1">
                            <div class="w-100 text-center text-muted py-2">
                              Nenhum atraso registrado hoje
                            </div>
                          </li>
                        <?php endif; ?>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!--/ Expense Overview -->

            <!-- Transactions -->
            <div class="col-md-6 col-lg-4 order-2 mb-4">
              <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                  <h5 class="card-title m-0 me-2">Últimos Registros</h5>
                  <div class="dropdown">
                    <button class="btn p-0" type="button" id="ultimosRegistrosID" data-bs-toggle="dropdown"
                      aria-haspopup="true" aria-expanded="false">
                      <i class="bx bx-dots-vertical-rounded"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="ultimosRegistrosID">
                      <a class="dropdown-item" href="?id=<?= $idSelecionado ?>&filtro_registros=hoje">Hoje</a>
                      <a class="dropdown-item" href="?id=<?= $idSelecionado ?>&filtro_registros=ontem">Ontem</a>
                      <a class="dropdown-item" href="?id=<?= $idSelecionado ?>&filtro_registros=semana">Últimos 7
                        dias</a>
                      <a class="dropdown-item" href="?id=<?= $idSelecionado ?>&filtro_registros=ano">Ano</a>
                    </div>
                  </div>
                </div>
                <div class="card-body">
                  <ul class="p-0 m-0">
                    <?php foreach ($ultimosRegistros as $index => $registro):
                      $entrada = $registro['entrada'] ? date('H:i', strtotime($registro['entrada'])) : '--:--';
                      $saida = $registro['saida_final'] ? date('H:i', strtotime($registro['saida_final'])) : '--:--';
                      $dataFormatada = date('d/m', strtotime($registro['data']));
                      $bgColors = ['primary', 'success', 'warning', 'info', 'secondary'];
                      ?>
                      <li class="d-flex mb-4 pb-1">
                        <div class="avatar flex-shrink-0 me-3">
                          <span class="avatar-initial rounded bg-label-<?= $bgColors[$index % count($bgColors)] ?>">
                            <i class="bx bx-user"></i>
                          </span>
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                          <div class="me-2">
                            <h6 class="mb-0"><?= htmlspecialchars($registro['nome']) ?></h6>
                            <small class="text-muted d-block mb-1">Entrada: <?= $entrada ?> | Saída: <?= $saida ?></small>
                          </div>
                          <div class="user-progress d-flex align-items-center gap-1">
                            <span class="text-muted"><?= $dataFormatada ?></span>
                          </div>
                        </div>
                      </li>
                    <?php endforeach; ?>
                    <?php if (empty($ultimosRegistros)): ?>
                      <li class="d-flex mb-4 pb-1">
                        <div class="w-100 text-center text-muted py-2">
                          Nenhum registro encontrado
                        </div>
                      </li>
                    <?php endif; ?>
                  </ul>
                </div>
              </div>
            </div>
            <!--/ Transactions -->
          </div>
        </div>
        <!-- / Content -->

        <!-- Footer -->
        <footer class="content-footer footer bg-footer-theme text-center">
          <div class="container-xxl d-flex  py-2 flex-md-row flex-column justify-content-center">
            <div class="mb-2 mb-md-0">
              &copy;
              <script>
                document.write(new Date().getFullYear());
              </script>
              , <strong>Açaínhadinhos</strong>. Todos os direitos reservados.
              Desenvolvido por <strong>Lucas Correa</strong>.
            </div>
          </div>
        </footer>

        <!-- / Footer -->

        <div class="content-backdrop fade"></div>
      </div>
      <!-- Content wrapper -->
    </div>
    <!-- / Layout page -->

  </div>


  <!-- Overlay -->
  <div class="layout-overlay layout-menu-toggle"></div>
  </div>

  <script>
    // Aguarde o DOM estar totalmente carregado
    document.addEventListener('DOMContentLoaded', function () {
      // Verifique se ApexCharts está disponível
      if (typeof ApexCharts === 'undefined') {
        console.error('ApexCharts não foi carregado corretamente');
        return;
      }

      // Total Revenue Chart - Gráfico de horas trabalhadas
      const totalRevenueEl = document.getElementById('totalRevenueChart');
      if (totalRevenueEl) {
        const totalRevenueChart = new ApexCharts(totalRevenueEl, {
          series: [{
            name: 'Horas',
            data: <?= json_encode($horasTrabalhadas) ?>
          }],
          chart: {
            type: 'bar',
            height: 350,
            toolbar: {
              show: false
            }
          },
          plotOptions: {
            bar: {
              borderRadius: 8,
              columnWidth: '40%'
            }
          },
          dataLabels: {
            enabled: false
          },
          colors: [config.colors.primary],
          stroke: {
            width: 2,
            colors: ['transparent']
          },
          grid: {
            borderColor: '#e0e0e0',
            strokeDashArray: 4
          },
          xaxis: {
            categories: <?= json_encode($labelsMeses) ?>,
            axisBorder: {
              show: false
            },
            axisTicks: {
              show: false
            }
          },
          yaxis: {
            title: {
              text: 'Horas'
            },
            labels: {
              formatter: function (val) {
                return Math.round(val); // Garante números inteiros
              }
            }
          },
          tooltip: {
            y: {
              formatter: function (val) {
                return val + " horas";
              }
            }
          }
        });
        totalRevenueChart.render();
      }

      // Order Statistics Chart - Gráfico de distribuição por setor
      const orderStatisticsEl = document.getElementById('orderStatisticsChart');
      if (orderStatisticsEl) {
        const orderStatisticsChart = new ApexCharts(orderStatisticsEl, {
          chart: {
            type: 'donut',
            height: 120,
            width: 130
          },
          labels: <?= json_encode($setoresChartLabels) ?>,
          series: <?= json_encode($setoresChartData) ?>,
          colors: [
            config.colors.primary,
            config.colors.success,
            config.colors.warning,
            config.colors.info
          ],
          stroke: {
            width: 0
          },
          dataLabels: {
            enabled: false
          },
          legend: {
            show: false
          },
          plotOptions: {
            pie: {
              donut: {
                labels: {
                  show: true,
                  name: {
                    show: false
                  },
                  value: {
                    fontSize: '1.5rem',
                    fontFamily: 'Public Sans',
                    color: '#2d3748',
                    offsetY: 0,
                    formatter: function (val) {
                      return val;
                    }
                  },
                  total: {
                    show: true,
                    label: 'Total',
                    color: '#718096',
                    formatter: function () {
                      return '<?= $totalFuncionarios ?>';
                    }
                  }
                }
              }
            }
          }
        });
        orderStatisticsChart.render();
      }

      // Income Chart - Gráfico de frequência
      const incomeChartEl = document.getElementById('incomeChart');
      if (incomeChartEl) {
        const incomeChart = new ApexCharts(incomeChartEl, {
          series: [{
            name: 'Frequência',
            data: <?= json_encode($frequenciaMensal) ?>
          }],
          chart: {
            type: 'area',
            height: 215,
            sparkline: {
              enabled: false
            },
            toolbar: {
              show: false
            }
          },
          colors: [config.colors.primary],
          fill: {
            type: 'gradient',
            gradient: {
              shadeIntensity: 0.6,
              opacityFrom: 0.4,
              opacityTo: 0.2,
              stops: [0, 95, 100]
            }
          },
          stroke: {
            width: 2,
            curve: 'smooth'
          },
          xaxis: {
            categories: <?= json_encode($labelsMeses) ?>,
            axisBorder: {
              show: false
            },
            axisTicks: {
              show: false
            }
          },
          yaxis: {
            show: false,
            min: 0
          },
          tooltip: {
            y: {
              formatter: function (val) {
                return val + " registros";
              }
            }
          }
        });
        incomeChart.render();
      }

      // Profile Report Chart 2 - Gráfico de pontos adicionados (linha simples)
      const profileReportChart2El = document.getElementById('profileReportChart2');
      if (profileReportChart2El) {
        const profileReportChart2 = new ApexCharts(profileReportChart2El, {
          series: [{
            name: 'Pontos',
            data: [5, 8, 12, 7, 10, 6, 9]
          }],
          chart: {
            type: 'line',
            height: 80,
            sparkline: { enabled: true },
            toolbar: { show: false }
          },
          stroke: {
            width: 4,
            curve: 'smooth'
          },
          colors: [config.colors.warning],
          dataLabels: { enabled: false },
          grid: { show: false },
          xaxis: { labels: { show: false }, axisBorder: { show: false }, axisTicks: { show: false } },
          yaxis: { show: false },
          tooltip: {
            y: {
              formatter: function (val) {
                return val + " pontos";
              }
            }
          }
        });
        profileReportChart2.render();
      }

      // Bank Hours Chart - Gráfico de banco de horas (linha simples)
      const bankHoursChartEl = document.getElementById('bankHoursChart');
      if (bankHoursChartEl) {
        const bankHoursChart = new ApexCharts(bankHoursChartEl, {
          series: [{
            name: 'Banco de Horas',
            data: [2, 4, 6, 8, 12, 18, 24]
          }],
          chart: {
            type: 'line',
            height: 80,
            sparkline: { enabled: true },
            toolbar: { show: false }
          },
          stroke: {
            width: 4,
            curve: 'smooth'
          },
          colors: [config.colors.info],
          dataLabels: { enabled: false },
          grid: { show: false },
          axis: { labels: { show: false }, axisBorder: { show: false }, axisTicks: { show: false } },
          yaxis: { show: false },
          tooltip: {
            y: {
              formatter: function (val) {
                return val + "h";
              }
            }
          }
        });
        bankHoursChart.render();
      }

    });
  </script>

  <script src="../../js/saudacao.js"></script>
  <script src="../../assets/vendor/libs/popper/popper.js"></script>
  <script src="../../assets/vendor/js/bootstrap.js"></script>
  <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>

  <script src="../../assets/vendor/js/menu.js"></script>
  <!-- endbuild -->

  <!-- Vendors JS -->
  <script src="../../assets/vendor/libs/apex-charts/apexcharts.js"></script>

  <!-- Main JS -->
  <script src="../../assets/js/main.js"></script>


  <!-- Place this tag in your head or just before your close body tag. -->
  <script async defer src="https://buttons.github.io/buttons.js"></script>
</body>

</html>
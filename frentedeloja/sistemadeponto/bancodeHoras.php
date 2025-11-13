<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// ✅ Recupera o identificador vindo da URL
$idSelecionado = $_GET['id'] ?? '';

// ✅ Verifica se a pessoa está logada
if (
  !isset($_SESSION['usuario_logado']) ||
  !isset($_SESSION['empresa_id']) ||
  !isset($_SESSION['tipo_empresa']) ||
  !isset($_SESSION['usuario_id']) ||
  !isset($_SESSION['nivel']) || // Verifica se o nível está na sessão
  !isset($_SESSION['usuario_cpf']) // Adicionado verificação do CPF na sessão
) {
  header("Location: ../index.php?id=$idSelecionado");
  exit;
}

// ✅ Conexão com o banco de dados
require '../../assets/php/conexao.php';

$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id = $_SESSION['usuario_id'];
$cpfUsuario = $_SESSION['usuario_cpf']; // Adicionado para pegar o CPF da sessão
$tipoUsuarioSessao = $_SESSION['nivel']; // "Admin" ou "Comum"

try {
  // Verifica se é um usuário de contas_acesso (Admin) ou funcionarios_acesso
  if ($tipoUsuarioSessao === 'Admin') {
    // Buscar na tabela de contas_acesso
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
  } else {
    // Buscar na tabela de funcionarios_acesso
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM funcionarios_acesso WHERE id = :id");
  }

  $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
  $stmt->execute();
  $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($usuario) {
    $nomeUsuario = $usuario['usuario'];
    $tipoUsuario = ucfirst($usuario['nivel']);
  } else {
    echo "<script>alert('Usuário não encontrado.'); window.location.href = './index.php?id=$idSelecionado';</script>";
    exit;
  }
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar nome e tipo do usuário: " . addslashes($e->getMessage()) . "'); history.back();</script>";
  exit;
}

// ✅ Valida o tipo de empresa e o acesso permitido
if (str_starts_with($idSelecionado, 'principal_')) {
  // Para principal, verifica se é admin ou se pertence à mesma empresa
  if ($_SESSION['tipo_empresa'] !== 'principal' && 
      !($tipoUsuarioSessao === 'Admin' && $_SESSION['empresa_id'] === 'principal_1')) {
    echo "<script>
            alert('Acesso negado!');
            window.location.href = '../index.php?id=$idSelecionado';
        </script>";
    exit;
  }
  $id = 1;
} elseif (str_starts_with($idSelecionado, 'unidade_')) {
  $idUnidade = str_replace('unidade_', '', $idSelecionado);
  
  // Verifica se o usuário pertence à mesma unidade ou é admin da principal_1
  $acessoPermitido = ($_SESSION['empresa_id'] === $idSelecionado) || 
                    ($tipoUsuarioSessao === 'Admin' && $_SESSION['empresa_id'] === 'principal_1');
  
  if (!$acessoPermitido) {
    echo "<script>
            alert('Acesso negado!');
            window.location.href = '../index.php?id=$idSelecionado';
        </script>";
    exit;
  }
  $id = $idUnidade;
} else {
  echo "<script>
        alert('Empresa não identificada!');
        window.location.href = '../index.php?id=$idSelecionado';
    </script>";
  exit;
}

// ✅ Buscar imagem da empresa para usar como favicon
$iconeEmpresa = '../../assets/img/favicon/favicon.ico'; // Ícone padrão

try {
  $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1");
  $stmt->bindParam(':id_selecionado', $idSelecionado);
  $stmt->execute();
  $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($empresa && !empty($empresa['imagem'])) {
    $iconeEmpresa = $empresa['imagem'];
  }
} catch (PDOException $e) {
  error_log("Erro ao carregar ícone da empresa: " . $e->getMessage());
  // Não mostra erro para o usuário para não quebrar a página
}

// Funções auxiliares de tempo
function timeToMinutes($time) {
  if (!$time || $time === '00:00:00' || $time === null) return 0;
  list($h, $m, $s) = explode(':', $time);
  return $h * 60 + $m + round($s / 60);
}

function minutesToHM($min) {
  $h = floor($min / 60);
  $m = $min % 60;
  return sprintf('%02dh %02dm', $h, $m);
}

// Função auxiliar para formatar horário ou exibir '--:--' caso nulo
function formatTimeOrDash($time) {
  return ($time && $time !== '00:00:00') ? date('H:i', strtotime($time)) : '--:--';
}

// Buscar apenas os pontos DO FUNCIONÁRIO LOGADO e DESSA EMPRESA
try {
  $sql = "
    SELECT 
      p.*,
      f.nome,
      f.dia_inicio, f.dia_folga,
      f.entrada         AS f_entrada,
      f.saida_intervalo AS f_saida_intervalo,
      f.retorno_intervalo AS f_retorno_intervalo,
      f.saida_final     AS f_saida_final
    FROM pontos p
    LEFT JOIN funcionarios f
      ON p.cpf = f.cpf
     AND p.empresa_id = f.empresa_id
    WHERE p.empresa_id = :empresa_id
      AND p.cpf        = :cpf
    ORDER BY p.data DESC
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
  $stmt->bindParam(':cpf', $cpfUsuario, PDO::PARAM_STR);
  $stmt->execute();
  $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  die("Erro ao buscar registros: " . $e->getMessage());
}

// Agrupar por mês/ano
$dadosAgrupados = [];
foreach ($registros as $r) {
  $mesAno = date('m/Y', strtotime($r['data']));
  if (!isset($dadosAgrupados[$mesAno])) {
    // Preparar escala e dias úteis
    $du = 0; // você pode calcular dias úteis se precisar
    $refE  = $r['f_entrada']         ?: $r['entrada'];
    $refSI = $r['f_saida_intervalo'] ?: $r['saida_intervalo'];
    $refR  = $r['f_retorno_intervalo'] ?: $r['retorno_intervalo'];
    $refS  = $r['f_saida_final']     ?: $r['saida_final'];

    $dadosAgrupados[$mesAno] = [
      'nome'            => $r['nome'],
      'mes_ano'         => $mesAno,
      'minTrabalhados'  => 0,
      'minPendentes'    => 0,
      'minExtras'       => 0,
      'dia_inicio'      => $r['dia_inicio'],
      'dia_folga'       => $r['dia_folga'],
      'entrada'         => $refE,
      'saida_intervalo' => $refSI,
      'retorno_intervalo' => $refR,
      'saida_final'     => $refS,
    ];
  }

  // acumula minutos trabalhados (descontando intervalo)
  $m = 0;
  // Caso tenha entrada e saida_final, calcula o tempo total
  if ($r['entrada'] && $r['saida_final']) {
    if ($r['saida_intervalo'] && $r['retorno_intervalo']) {
      // Tem intervalo: soma entrada até saida_intervalo + retorno_intervalo até saida_final
      $m += timeToMinutes($r['saida_intervalo']) - timeToMinutes($r['entrada']);
      $m += timeToMinutes($r['saida_final']) - timeToMinutes($r['retorno_intervalo']);
    } else {
      // Sem intervalo ou um deles é NULL: calcula direto de entrada até saida_final
      $m += timeToMinutes($r['saida_final']) - timeToMinutes($r['entrada']);
    }
  }

  $dadosAgrupados[$mesAno]['minTrabalhados'] += max(0, $m); // evita somar valores negativos
  $dadosAgrupados[$mesAno]['minPendentes']   += timeToMinutes($r['horas_pendentes']);
  $dadosAgrupados[$mesAno]['minExtras']      += timeToMinutes($r['hora_extra']);
}

// Formatar horas finais
foreach ($dadosAgrupados as &$d) {
  $p = $d['minPendentes'];
  $e = $d['minExtras'];
  if ($e > $p) {
    $d['minLiquidaExtra'] = $e - $p;
    $d['minLiquidaPend']  = 0;
  } else {
    $d['minLiquidaPend']  = $p - $e;
    $d['minLiquidaExtra'] = 0;
  }
  $d['horas_trabalhadas']       = minutesToHM($d['minTrabalhados']);
  $d['hora_extra_liquida']      = minutesToHM($d['minLiquidaExtra']);
  $d['horas_pendentes_liquida'] = minutesToHM($d['minLiquidaPend']);
}
unset($d);

?>

<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
  data-assets-path="../../assets/" data-template="vertical-menu-template-free">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>ERP - Sistema de Ponto</title>

  <meta name="description" content="" />

  <!-- Favicon da empresa carregado dinamicamente -->
  <link rel="icon" type="image/x-icon" href="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>" />

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
          <a href="./dashboard.php?id=<?= urlencode($idSelecionado); ?>" class="app-brand-link">

            <span class="app-brand-text demo menu-text fw-bolder ms-2" style=" text-transform: capitalize;">Açaínhadinhos</span>
          </a>

          <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
            <i class="bx bx-chevron-left bx-sm align-middle"></i>
          </a>
        </div>

        <div class="menu-inner-shadow"></div>

        <ul class="menu-inner py-1">
          <!-- Dashboard -->
          <li class="menu-item">
            <a href="index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
              <i class="menu-icon tf-icons bx bx-home-circle"></i>
              <div data-i18n="Analytics">Dashboard</div>
            </a>
          </li>

          <!--link Diversos-->
          <!-- Cabeçalho da seção -->
          <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Sistema de Ponto</span>
          </li>

          <!-- Menu: Registro de Ponto -->
          <li class="menu-item ">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-time"></i>
              <div data-i18n="Ponto">Registro de Ponto</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item">
                <a href="./registrarPonto.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Registrar Ponto</div>
                </a>
              </li>
            </ul>
          </li>
          <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-file"></i>
              <div data-i18n="Atestados">Atestados</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item">
                <a href="./atestadosEnviados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Atestado Enviados </div>
                </a>
              </li>
            </ul>
          </li>
          <li class="menu-item active open">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-trending-up"></i>
              <div data-i18n="Ponto">Relatório</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item active">
                <a href="./bancodeHoras.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Banco de Horas</div>
                </a>
              </li>
              <li class="menu-item">
                <a href="./pontoRegistrado.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Pontos Registrados</div>
                </a>
              </li>
            </ul>
          </li>
          <!--/Diversos-->

          <!-- Misc -->
          <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
          <li class="menu-item">
            <a href="../caixa/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
              <i class="menu-icon tf-icons bx bx-barcode-reader"></i>
              <div data-i18n="Basic">Caixa</div>
            </a>
          </li>
          <li class="menu-item">
            <a href="https://wa.me/92991515710" target="_blank" class="menu-link">
              <i class="menu-icon tf-icons bx bx-support"></i>
              <div data-i18n="Basic">Suporte</div>
            </a>
          </li>
          <!--END MISC-->
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
                <i class="bx bx-search fs-4 lh-0"></i>
                <input type="text" id="searchInput" class="form-control border-0 shadow-none"
                  placeholder="Pesquisar funcionário..." aria-label="Pesquisar..." />
              </div>
            </div>
            <!-- /Search -->

            <ul class="navbar-nav flex-row align-items-center ms-auto">
              <!-- Place this tag where you want the button to render. -->
              <!-- User -->
              <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                  <div class="avatar avatar-online">
                    <img src="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>" alt class="w-px-40 h-auto rounded-circle" />
                  </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li>
                    <a class="dropdown-item" href="#">
                      <div class="d-flex">
                        <div class="flex-shrink-0 me-3">
                          <div class="avatar avatar-online">
                            <img src="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>" alt class="w-px-40 h-auto rounded-circle" />
                          </div>
                        </div>
                        <div class="flex-grow-1">
                          <span class="fw-semibold d-block"><?= htmlspecialchars($nomeUsuario); ?></span>
                        </div>
                      </div>
                    </a>
                  </li>
                  <li>
                    <div class="dropdown-divider"></div>
                  </li>

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
          <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a href="#">Sistema de Ponto</a>/</span>Banco de
            Horas</h4>
          <h5 class="fw-bold mt-3 mb-3 custor-font"><span class="text-muted fw-light">Visualize o seu Banco de
              Horas</span></h5>

          <div class="card mb-4">
            <h5 class="card-header">
              Banco de Horas – <?= htmlspecialchars($nomeUsuario) ?>
            </h5>
            <div class="card-body">
              <div class="table-responsive text-nowrap">
                <table class="table table-hover" id="tabelaBancoHoras">
                  <thead>
                    <tr>
                      <th>Funcionário</th>
                      <th>Mês</th>
                      <th>Horas Trabalhadas</th>
                      <th>Horas Extras</th>
                      <th>Horas Pendentes</th>
                      <th>Ações</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php $idx = 0;
                    foreach ($dadosAgrupados as $mes => $d): $idx++; ?>
                      <tr>
                        <td><?= htmlspecialchars($d['nome']) ?></td>
                        <td><?= $d['mes_ano'] ?></td>
                        <td><?= $d['horas_trabalhadas'] ?></td>
                        <td><?= $d['hora_extra_liquida'] ?></td>
                        <td><?= $d['horas_pendentes_liquida'] ?></td>
                        <td>
                          <button class="btn btn-primary btn-sm"
                            data-bs-toggle="modal"
                            data-bs-target="#modalUnificado<?= $idx ?>">
                            Visualizar
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <div class="d-flex gap-2 mt-3">
                <button id="prevPageHoras" class="btn btn-outline-primary btn-sm">&laquo; Anterior</button>
                <div id="paginacaoHoras" class="d-flex gap-1"></div>
                <button id="nextPageHoras" class="btn btn-outline-primary btn-sm">Próxima &raquo;</button>
              </div>
            </div>
          </div>

          <?php $idx = 0;
          foreach ($dadosAgrupados as $mes => $d): $idx++; ?>
            <div class="modal fade" id="modalUnificado<?= $idx ?>" tabindex="-1" aria-labelledby="modalUnificadoLabel<?= $idx ?>" aria-hidden="true">
              <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="modalUnificadoLabel<?= $idx ?>">
                      Resumo – <?= htmlspecialchars($d['nome']) ?> (<?= $d['mes_ano'] ?>)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <h6 class="fw-bold">Escala</h6>
                    <p><strong>Entrada:</strong> <?= date('H:i', strtotime($d['entrada'])) ?></p>
                    <p><strong>Saída Intervalo:</strong> <?= !empty($d['saida_intervalo']) ? date('H:i', strtotime($d['saida_intervalo'])) : '--:--' ?></p>
                    <p><strong>Retorno Intervalo:</strong> <?= !empty($d['retorno_intervalo']) ? date('H:i', strtotime($d['retorno_intervalo'])) : '--:--' ?></p>
                    <p><strong>Saída Final:</strong> <?= date('H:i', strtotime($d['saida_final'])) ?></p>
                    <hr>
                    <h6 class="fw-bold">Detalhes do Banco de Horas</h6>
                    <p><strong>Mês/Ano:</strong> <?= $d['mes_ano'] ?></p>
                    <p><strong>Horas Trabalhadas:</strong> <?= $d['horas_trabalhadas'] ?></p>
                    <p><strong>Horas Extras:</strong> <?= $d['hora_extra_liquida'] ?></p>
                    <p><strong>Horas Pendentes:</strong> <?= $d['horas_pendentes_liquida'] ?></p>
                  </div>
                  <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>

          <script>
            const searchInput = document.getElementById('searchInput');
            const allRows = Array.from(document.querySelectorAll('#tabelaBancoHoras tbody tr'));
            const prevBtn = document.getElementById('prevPageHoras');
            const nextBtn = document.getElementById('nextPageHoras');
            const pageContainer = document.getElementById('paginacaoHoras');
            const perPage = 10;
            let currentPage = 1;

            function renderTable() {
              const filter = searchInput.value.trim().toLowerCase();
              const filteredRows = allRows.filter(row => {
                if (!filter) return true;
                return Array.from(row.cells).some(td =>
                  td.textContent.toLowerCase().includes(filter)
                );
              });

              const totalPages = Math.ceil(filteredRows.length / perPage) || 1;
              currentPage = Math.min(Math.max(1, currentPage), totalPages);

              // Hide all, then show slice
              allRows.forEach(r => r.style.display = 'none');
              filteredRows.slice((currentPage - 1) * perPage, currentPage * perPage)
                .forEach(r => r.style.display = '');

              // Render page buttons
              pageContainer.innerHTML = '';
              for (let i = 1; i <= totalPages; i++) {
                const btn = document.createElement('button');
                btn.textContent = i;
                btn.className = 'btn btn-sm ' + (i === currentPage ? 'btn-primary' : 'btn-outline-primary');
                btn.style.marginRight = '4px';
                btn.onclick = () => {
                  currentPage = i;
                  renderTable();
                };
                pageContainer.appendChild(btn);
              }

              prevBtn.disabled = currentPage === 1;
              nextBtn.disabled = currentPage === totalPages;
            }

            prevBtn.addEventListener('click', () => {
              if (currentPage > 1) {
                currentPage--;
                renderTable();
              }
            });
            nextBtn.addEventListener('click', () => {
              currentPage++;
              renderTable();
            });
            searchInput.addEventListener('input', () => {
              currentPage = 1;
              renderTable();
            });

            document.addEventListener('DOMContentLoaded', renderTable);
          </script>

        </div>
        <!-- / Content -->
      </div>
    </div>
  </div>


  <!-- Core JS -->
  <!-- build:js assets/vendor/js/core.js -->
  <script src="../../js/saudacao.js"></script>
  <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../../assets/vendor/libs/popper/popper.js"></script>
  <script src="../../assets/vendor/js/bootstrap.js"></script>
  <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>

  <script src="../../assets/vendor/js/menu.js"></script>
  <!-- endbuild -->

  <!-- Vendors JS -->
  <script src="../../assets/vendor/libs/apex-charts/apexcharts.js"></script>
  <script src="../../js/graficoDashboard.js"></script>

  <!-- Main JS -->
  <script src="../../assets/js/main.js"></script>

  <!-- Page JS -->
  <script src="../../assets/js/dashboards-analytics.js"></script>

  <!-- Place this tag in your head or just before your close body tag. -->
  <script async defer src="https://buttons.github.io/buttons.js"></script>

</body>

</html>
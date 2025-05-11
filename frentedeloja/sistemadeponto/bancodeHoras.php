<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

$idSelecionado = $_GET['id'] ?? '';

if (
  !isset($_SESSION['usuario_logado']) ||
  !isset($_SESSION['empresa_id']) ||
  !isset($_SESSION['tipo_empresa']) ||
  !isset($_SESSION['usuario_id'])
) {
  header("Location: ../index.php?id=$idSelecionado");
  exit;
}

require '../../assets/php/conexao.php';

$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id = $_SESSION['usuario_id'];
$tipoUsuarioSessao = $_SESSION['nivel'];

try {
  if ($tipoUsuarioSessao === 'Admin') {
    $stmt = $pdo->prepare("SELECT usuario, nivel, cpf FROM contas_acesso WHERE id = :id");
  } else {
    $stmt = $pdo->prepare("SELECT usuario, nivel, cpf FROM funcionarios_acesso WHERE id = :id");
  }

  $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
  $stmt->execute();
  $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($usuario) {
    $nomeUsuario = $usuario['usuario'];
    $tipoUsuario = ucfirst($usuario['nivel']);
    $cpfUsuario = $usuario['cpf'];
  } else {
    echo "<script>alert('Usuário não encontrado.'); window.location.href = './index.php?id=$idSelecionado';</script>";
    exit;
  }
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar nome e tipo do usuário: " . $e->getMessage() . "'); history.back();</script>";
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
  echo "<script>alert('Erro ao carregar ícone da empresa: " . addslashes($e->getMessage()) . "');</script>";
}

$nomeFuncionario = '';
$escalaFuncionario = '';
$totalCumprir = '0h 0m'; // Total a cumprir agora é "0h 0m"
if (!empty($cpfUsuario)) {
  try {
    $stmtNome = $pdo->prepare("SELECT nome, escala, hora_entrada_primeiro_turno, hora_saida_primeiro_turno, hora_entrada_segundo_turno, hora_saida_segundo_turno FROM funcionarios WHERE empresa_id = :empresa_id AND cpf = :cpf");
    $stmtNome->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
    $stmtNome->bindParam(':cpf', $cpfUsuario, PDO::PARAM_STR);
    $stmtNome->execute();
    $funcionario = $stmtNome->fetch(PDO::FETCH_ASSOC);

    if ($funcionario) {
      $nomeFuncionario = $funcionario['nome'];
      $escalaFuncionario = $funcionario['escala'];

      $entrada1 = new DateTime($funcionario['hora_entrada_primeiro_turno']);
      $saida1 = new DateTime($funcionario['hora_saida_primeiro_turno']);
      $total1 = $entrada1->diff($saida1);

      $horasTotais = $total1->h;
      $minutosTotais = $total1->i;

      if (!empty($funcionario['hora_entrada_segundo_turno']) && !empty($funcionario['hora_saida_segundo_turno'])) {
        $entrada2 = new DateTime($funcionario['hora_entrada_segundo_turno']);
        $saida2 = new DateTime($funcionario['hora_saida_segundo_turno']);
        $total2 = $entrada2->diff($saida2);
        $horasTotais += $total2->h;
        $minutosTotais += $total2->i;
      }

      if ($minutosTotais >= 60) {
        $horasTotais += floor($minutosTotais / 60);
        $minutosTotais %= 60;
      }

      $totalCumprir = "{$horasTotais}h {$minutosTotais}m";
    } else {
      $nomeFuncionario = 'Funcionário não identificado';
    }
  } catch (PDOException $e) {
    $nomeFuncionario = 'Erro ao buscar nome';
  }
}

try {
  $stmtPonto = $pdo->prepare("SELECT * FROM registros_ponto WHERE empresa_id = :empresa_id AND cpf = :cpf ORDER BY data DESC");
  $stmtPonto->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
  $stmtPonto->bindParam(':cpf', $cpfUsuario, PDO::PARAM_STR);
  $stmtPonto->execute();
  $pontos = $stmtPonto->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar registros de ponto: " . $e->getMessage() . "'); history.back();</script>";
  exit;
}

function calcularDiferencaTotal($horasTrabalhadasDia, $totalCumprir)
{
  preg_match('/(\d+)h\s*(\d+)m/', $totalCumprir, $match);
  $totalHoras = (int) $match[1];
  $totalMinutos = (int) $match[2];

  $horasTotaisTrabalhadas = 0;
  $minutosTotaisTrabalhados = 0;

  foreach ($horasTrabalhadasDia as $info) {
    $horasTotaisTrabalhadas += $info['horas'];
    $minutosTotaisTrabalhados += $info['minutos'];
  }

  $trabalhadasMin = $horasTotaisTrabalhadas * 60 + $minutosTotaisTrabalhados;
  $cumprirMin = $totalHoras * 60 + $totalMinutos;

  $diferenca = $trabalhadasMin - $cumprirMin;

  $absDiff = abs($diferenca);
  $h = floor($absDiff / 60);
  $m = $absDiff % 60;

  return ($diferenca < 0 ? "-" : "+") . "{$h}h {$m}m";
}

$horasTrabalhadasDia = [];
foreach ($pontos as $registro) {
  $data = $registro['data'];
  if (!isset($horasTrabalhadasDia[$data])) {
    $horasTrabalhadasDia[$data] = ['horas' => 0, 'minutos' => 0];
  }

  if (!empty($registro['entrada']) && !empty($registro['saida'])) {
    $entrada = new DateTime($registro['entrada']);
    $saida = new DateTime($registro['saida']);

    $horaPrevista1 = isset($entrada1) ? clone $entrada1 : null;
    $tolerancia = new DateInterval('PT10M');

    if ($horaPrevista1 && $entrada <= (clone $horaPrevista1)->add($tolerancia)) {
      $entrada = $horaPrevista1;
    }

    $intervalo = $entrada->diff($saida);

    $horasTrabalhadasDia[$data]['horas'] += $intervalo->h;
    $horasTrabalhadasDia[$data]['minutos'] += $intervalo->i;

    if ($horasTrabalhadasDia[$data]['minutos'] >= 60) {
      $horasTrabalhadasDia[$data]['horas'] += floor($horasTrabalhadasDia[$data]['minutos'] / 60);
      $horasTrabalhadasDia[$data]['minutos'] %= 60;
    }
  }
}

$diasSemana = [
  'Monday' => 'Segunda-feira',
  'Tuesday' => 'Terça-feira',
  'Wednesday' => 'Quarta-feira',
  'Thursday' => 'Quinta-feira',
  'Friday' => 'Sexta-feira',
  'Saturday' => 'Sábado',
  'Sunday' => 'Domingo'
];

$totalDiferencaHoras = 0; // Variável para armazenar a diferença total de horas
$totalHorasTrabalhadasFinal = 0; // Variável para armazenar o total final de horas trabalhadas
$totalMinutosTrabalhadosFinal = 0; // Variável para armazenar os minutos finais

ksort($horasTrabalhadasDia);
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

            <span class="app-brand-text demo menu-text fw-bolder ms-2">Açainhadinhos</span>
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
            <a href="../Delivery/index.php?id=<?= urlencode($idSelecionado); ?>"" class=" menu-link">
              <i class="menu-icon tf-icons bx bx-cart"></i>
              <div data-i18n="Basic">Delivery</div>
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
              </div>
            </div>
            <!-- /Search -->

            <ul class="navbar-nav flex-row align-items-center ms-auto">
              <!-- Place this tag where you want the button to render. -->
              <!-- User -->
              <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                  <div class="avatar avatar-online">
                    <img src="../../assets/img/avatars/1.png" alt class="w-px-40 h-auto rounded-circle" />
                  </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li>
                    <a class="dropdown-item" href="#">
                      <div class="d-flex">
                        <div class="flex-shrink-0 me-3">
                          <div class="avatar avatar-online">
                            <img src="../../assets/img/avatars/1.png" alt class="w-px-40 h-auto rounded-circle" />
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
                  <li>
                    <a class="dropdown-item" href="#">
                      <i class="bx bx-user me-2"></i>
                      <span class="align-middle">My Profile</span>
                    </a>
                  </li>
                  <li>
                    <a class="dropdown-item" href="#">
                      <i class="bx bx-cog me-2"></i>
                      <span class="align-middle">Settings</span>
                    </a>
                  </li>
                  <li>
                    <a class="dropdown-item" href="#">
                      <span class="d-flex align-items-center align-middle">
                        <i class="flex-shrink-0 bx bx-credit-card me-2"></i>
                        <span class="flex-grow-1 align-middle">Billing</span>
                        <span class="flex-shrink-0 badge badge-center rounded-pill bg-danger w-px-20 h-px-20">4</span>
                      </span>
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

        <div class="container-xxl flex-grow-1 container-p-y">
          <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a href="#">Sistema de Ponto</a>/</span>Banco de
            Horas</h4>
          <h5 class="fw-bold mt-3 mb-3 custor-font"><span class="text-muted fw-light">Visualize todos os que Você
              Registrou</span></h5>

          <!-- Exibição da Tabela de Banco de Horas -->
          <div class="card">
            <h5 class="card-header">Banco de Horas - <?php echo htmlspecialchars($nomeFuncionario); ?></h5>
            <div class="card-body">
              <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                  <thead>
                    <tr>
                      <th>Data</th>
                      <th>Dia da Semana</th>
                      <th>Total a Cumprir</th>
                      <th>Horas Trabalhadas</th>
                      <th>Diferença</th>
                      <th>Escala</th>
                    </tr>
                  </thead>
                  <tbody class="table-border-bottom-0">
                    <?php
                    foreach ($horasTrabalhadasDia as $data => $info) {
                      $diaSemana = (new DateTime($data))->format('l');
                      $diaSemanaPtBr = $diasSemana[$diaSemana] ?? $diaSemana;

                      // Calcular a diferença de horas trabalhadas para o total a cumprir
                      $diferenca = calcularDiferencaTotal([$data => $info], $totalCumprir);

                      // Atualizar a diferença total
                      preg_match('/([+-])(\d+)h\s*(\d+)m/', $diferenca, $match);
                      if ($match) {
                        $sinal = $match[1]; // "+" ou "-"
                        $horasDiferenca = (int) $match[2];
                        $minutosDiferenca = (int) $match[3];

                        // Acumular a diferença total
                        if ($sinal === '+') {
                          $totalDiferencaHoras += $horasDiferenca * 60 + $minutosDiferenca; // Convertendo para minutos
                        } else {
                          $totalDiferencaHoras -= $horasDiferenca * 60 + $minutosDiferenca; // Convertendo para minutos
                        }
                      }

                      // Define a classe de cor para a diferença
                      $diferencaCor = (strpos($diferenca, '-') === 0) ? 'text-danger' : 'text-success';

                      echo "<tr>
                              <td>" . date('d/m/Y', strtotime($data)) . "</td>
                              <td>$diaSemanaPtBr</td>
                              <td>$totalCumprir</td>
                              <td>{$info['horas']}h {$info['minutos']}m</td>
                              <td class='$diferencaCor'>$diferenca</td>
                              <td>$escalaFuncionario</td>
                            </tr>";

                      // Acumulando as horas trabalhadas
                      $totalHorasTrabalhadasFinal += $info['horas'];
                      $totalMinutosTrabalhadosFinal += $info['minutos'];
                    }

                    // Corrigir o cálculo final de horas e minutos
                    $totalHorasTrabalhadasFinal += floor($totalMinutosTrabalhadosFinal / 60);
                    $totalMinutosTrabalhadosFinal %= 60;

                    // Calcular o total final de horas e minutos
                    $finalDiferenca = $totalDiferencaHoras;
                    $finalHoras = floor(abs($finalDiferenca) / 60);
                    $finalMinutos = abs($finalDiferenca) % 60;

                    $finalTexto = ($finalDiferenca < 0 ? "-" : "+") . "{$finalHoras}h {$finalMinutos}m";
                    $finalCor = ($finalDiferenca < 0) ? 'text-danger' : 'text-success';
                    ?>

                  </tbody>
                  <tfoot>
                    <?php if ($nomeFuncionario !== ''): ?>
                      <tr>
                        <th colspan="3" style="text-align: right;">Totais:</th>
                        <th><?php echo "{$totalHorasTrabalhadasFinal}h {$totalMinutosTrabalhadosFinal}m"; ?></th>
                        <th colspan="2" class="<?php echo $finalCor; ?>"><?php echo $finalTexto; ?></th>
                      </tr>
                    <?php endif; ?>
                  </tfoot>

                </table>
              </div>
            </div>
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
              , <strong>Açainhadinhos</strong>. Todos os direitos reservados.
              Desenvolvido por
              <a href="https://wa.me/92991515710" target="_blank"
                style="text-decoration: none; color: inherit;"><strong>
                  Lucas Correa
                </strong>.</a>

            </div>
          </div>
        </footer>
        <!-- / Footer -->
      </div>
    </div>
  </div>


  <!-- Scripts -->
  <script src="../../../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../../../assets/vendor/libs/popper/popper.js"></script>
  <script src="../../../assets/vendor/js/bootstrap.js"></script>
  <script src="../../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="../../../assets/vendor/js/menu.js"></script>
  <script src="../../../assets/js/main.js"></script>

  <!-- Script para preencher data e hora e controlar os botões -->
  <script>
    function atualizarDataHora() {
      const data = new Date();
      document.getElementById('data').value = data.toLocaleDateString('pt-BR');
      document.getElementById('hora').value = data.toLocaleTimeString('pt-BR');
    }

    function registrarEntrada() {
      alert("Entrada registrada com sucesso!");
      document.getElementById('btnEntrada').style.display = 'none';
      document.getElementById('btnSaida').style.display = 'inline-block';
    }

    function registrarSaida() {
      alert("Saída registrada com sucesso!");
      document.getElementById('btnSaida').style.display = 'none';
    }

    atualizarDataHora();
    setInterval(atualizarDataHora, 1000); // Atualiza hora a cada segundo
  </script>
</body>

</html>
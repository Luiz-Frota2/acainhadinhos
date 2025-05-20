<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

require_once '../../assets/php/conexao.php';

$idSelecionado = $_GET['id'] ?? '';
if (
  !isset($_SESSION['usuario_logado']) ||
  !isset($_SESSION['empresa_id']) ||
  !isset($_SESSION['tipo_empresa']) ||
  !isset($_SESSION['usuario_id'])
) {
  header("Location: .././login.php?id=$idSelecionado");
  exit;
}

if (str_starts_with($idSelecionado, 'principal_')) {
  if ($_SESSION['tipo_empresa'] !== 'principal' || $_SESSION['empresa_id'] != 1) {
    header("Location: .././login.php?id=$idSelecionado");
    exit;
  }
  $empresa_id = 1;
} elseif (str_starts_with($idSelecionado, 'filial_')) {
  $empresa_id = (int) str_replace('filial_', '', $idSelecionado);
  if ($_SESSION['tipo_empresa'] !== 'filial' || $_SESSION['empresa_id'] != $empresa_id) {
    header("Location: .././login.php?id=$idSelecionado");
    exit;
  }
} else {
  header("Location: .././login.php?id=$idSelecionado");
  exit;
}

// Buscar imagem da empresa
try {
  $sql = "SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1";
  $stmt = $pdo->prepare($sql);
  $stmt->bindParam(':id_selecionado', $idSelecionado);
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
$nivelUsuario = 'Comum'; // Valor padrão
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

function calcularHoras($inicio, $fim)
{
  return (strtotime($fim) - strtotime($inicio)) / 3600;
}

function contarDiasUteis($ano, $mes, $diasPermitidos)
{
  $diasNoMes = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
  $uteis = 0;
  for ($dia = 1; $dia <= $diasNoMes; $dia++) {
    $data = "$ano-$mes-$dia";
    $diaSemana = strtolower(date('l', strtotime($data)));
    if (in_array($diaSemana, $diasPermitidos)) {
      $uteis++;
    }
  }
  return $uteis;
}

$stmt = $pdo->prepare("
  SELECT r.*, f.nome, f.dia_inicio, f.dia_termino,
         f.hora_entrada_primeiro_turno, f.hora_saida_primeiro_turno,
         f.hora_entrada_segundo_turno, f.hora_saida_segundo_turno
  FROM registros_ponto r
  JOIN funcionarios f ON r.cpf = f.cpf
  WHERE r.empresa_id = :empresa_id
");
$stmt->bindParam(':empresa_id', $idSelecionado);
$stmt->execute();
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dadosAgrupados = [];

foreach ($registros as $registro) {
  $cpf = $registro['cpf'];
  $nome = $registro['nome'];
  $data = $registro['data'];
  $mes = date('m', strtotime($data));
  $ano = date('Y', strtotime($data));
  $chave = "$cpf|$mes|$ano";

  if (!isset($dadosAgrupados[$chave])) {
    $diasSemana = ['domingo', 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado'];
    $diaInicio = array_search($registro['dia_inicio'], $diasSemana);
    $diaTermino = array_search($registro['dia_termino'], $diasSemana);
    $diasPermitidos = [];

    $i = $diaInicio;
    do {
      $diasPermitidos[] = $diasSemana[$i];
      if ($i == $diaTermino)
        break;
      $i = ($i + 1) % 7;
    } while (true);

    $diasUteis = contarDiasUteis($ano, $mes, $diasPermitidos);

    $horasTurno1 = calcularHoras($registro['hora_entrada_primeiro_turno'], $registro['hora_saida_primeiro_turno']);
    $horasTurno2 = 0;
    if (!empty($registro['hora_entrada_segundo_turno']) && !empty($registro['hora_saida_segundo_turno'])) {
      $horasTurno2 = calcularHoras($registro['hora_entrada_segundo_turno'], $registro['hora_saida_segundo_turno']);
    }

    $horasDevidas = ($horasTurno1 + $horasTurno2) * $diasUteis;

    $dadosAgrupados[$chave] = [
      'nome' => $nome,
      'mes' => "$mes/$ano",
      'horas_trabalhadas' => 0,
      'horas_devidas' => round($horasDevidas, 2),
      'horas_extras' => 0,
      'cpf' => $cpf,
      'dia_inicio' => $registro['dia_inicio'],
      'dia_termino' => $registro['dia_termino'],
      'turno1_entrada' => $registro['hora_entrada_primeiro_turno'],
      'turno1_saida' => $registro['hora_saida_primeiro_turno'],
      'turno2_entrada' => $registro['hora_entrada_segundo_turno'],
      'turno2_saida' => $registro['hora_saida_segundo_turno']
    ];
  }

  if ($registro['entrada'] && $registro['saida']) {
    $dadosAgrupados[$chave]['horas_trabalhadas'] += calcularHoras($registro['entrada'], $registro['saida']);
  }

  if (!empty($registro['hora_extra']) && $registro['hora_extra'] !== '00:00:00') {
    $tempo = strtotime($registro['hora_extra']) - strtotime('00:00:00');
    $dadosAgrupados[$chave]['horas_extras'] += $tempo / 3600;
  }
}

function formatarHoras($horasFloat)
{
  $horas = floor($horasFloat);
  $minutos = round(($horasFloat - $horas) * 60);
  return sprintf("%02dh %02dm", $horas, $minutos);
}

function formatarHorasMinutos($horas)
{
  return formatarHoras($horas);
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
  <!-- Icons -->
  <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
  <!-- Core CSS -->
  <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="../../assets/css/demo.css" />
  <!-- Vendors CSS -->
  <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <!-- Helpers -->
  <script src="../../assets/vendor/js/helpers.js"></script>
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
          <li class="menu-item">
            <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
              <i class="menu-icon tf-icons bx bx-home-circle"></i>
              <div data-i18n="Analytics">Dashboard</div>
            </a>
          </li>

          <!-- Recursos Humanos (RH) -->
          <li class="menu-header small text-uppercase"><span class="menu-header-text">Recursos Humanos</span></li>

          <li class="menu-item  ">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-buildings"></i>
              <div data-i18n="Authentications">Setores</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item ">
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

          <!-- Menu Sistema de Ponto -->
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
          <li class="menu-item active open">
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
              <li class="menu-item active">
                <a href="./bancoHoras.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Ajuste de Horários e Banco de Horas">Banco de Horas</div>
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
                      <span class="align-middle">COnfigurações</span>
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
          <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a href="#">Sistema de Ponto</a>/</span>Ajuste de
            Ponto</h4>
          <h5 class="fw-bold mt-3 mb-3 custor-font"><span class="text-muted fw-light">Visualize e ajuste os pontos
              registrados</span></h5>

          <!-- Card da tabela -->
          <div class="card">
            <h5 class="card-header">Lista de Banco de Horas</h5>
            <div class="table-responsive text-nowrap">
              <table class="table table-hover">
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
                <tbody class="table-border-bottom-0" id="tabelaBancoHoras">
                  <?php foreach ($dadosAgrupados as $key => $dados): ?>
                    <?php
                    $horasExtras = $dados['horas_extras'];
                    $horasPendentes = max(0, $dados['horas_devidas'] - $dados['horas_trabalhadas']);
                    ?>
                    <tr>
                      <td><strong><?= htmlspecialchars($dados['nome']) ?></strong></td>
                      <td><?= $dados['mes'] ?></td>
                      <td hidden><?= ucfirst($dados['dia_inicio']) ?></td>
                      <td hidden><?= ucfirst($dados['dia_termino']) ?></td>
                      <td><?= formatarHorasMinutos($dados['horas_trabalhadas']) ?></td>
                      <td><?= formatarHorasMinutos($horasExtras) ?></td>
                      <td><?= formatarHorasMinutos($horasPendentes) ?></td>
                      <td>
                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                          data-bs-target="#modal_<?= md5($key) ?>">Visualizar</button>
                      </td>
                    </tr>

                    <!-- Modal de detalhes -->
                    <div class="modal fade" id="modal_<?= md5($key) ?>" tabindex="-1"
                      aria-labelledby="modalLabel_<?= md5($key) ?>" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel_<?= md5($key) ?>">Escala de
                              <?= htmlspecialchars($dados['nome']) ?>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                          </div>
                          <div class="modal-body">
                            <p><strong>Dia início:</strong> <?= ucfirst($dados['dia_inicio']) ?></p>
                            <p><strong>Dia término:</strong> <?= ucfirst($dados['dia_termino']) ?></p>
                            <p><strong>1º Turno:</strong>
                              <?= date('H:i', strtotime($dados['turno1_entrada'])) ?> às
                              <?= date('H:i', strtotime($dados['turno1_saida'])) ?>
                            </p>

                            <p><strong>2º Turno:</strong>
                              <?= $dados['turno2_entrada'] && $dados['turno2_saida']
                                ? date('H:i', strtotime($dados['turno2_entrada'])) . ' às ' . date('H:i', strtotime($dados['turno2_saida']))
                                : 'Não definido' ?>
                            </p>
                          </div>
                        </div>
                      </div>
                    </div>

                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <!-- Controles de paginação -->
            <div class="d-flex justify-content-start align-items-center gap-2 m-3">
              <button class="btn btn-sm btn-outline-primary" id="prevPageHoras">&laquo; Anterior</button>
              <div id="paginacaoHoras" class="mx-2"></div>
              <button class="btn btn-sm btn-outline-primary" id="nextPageHoras">Próxima &raquo;</button>
            </div>
          </div>

          <!-- Script de pesquisa e paginação -->
          <script>
            const searchInput = document.getElementById('searchInput');
            const linhasHoras = Array.from(document.querySelectorAll('#tabelaBancoHoras tr'));
            const rowsPerPageHoras = 10;
            let currentPageHoras = 1;

            function renderTabelaBancoHoras() {
              const filtro = searchInput.value.toLowerCase();
              const linhasFiltradas = linhasHoras.filter(linha => {
                const tds = linha.querySelectorAll('td');
                return Array.from(tds).some(td => td.textContent.toLowerCase().includes(filtro));
              });

              const totalPages = Math.ceil(linhasFiltradas.length / rowsPerPageHoras);
              const inicio = (currentPageHoras - 1) * rowsPerPageHoras;
              const fim = inicio + rowsPerPageHoras;

              linhasHoras.forEach(linha => linha.style.display = 'none');
              linhasFiltradas.slice(inicio, fim).forEach(linha => linha.style.display = '');

              const paginacao = document.getElementById('paginacaoHoras');
              paginacao.innerHTML = '';
              for (let i = 1; i <= totalPages; i++) {
                const btn = document.createElement('button');
                btn.textContent = i;
                btn.className = 'btn btn-sm ' + (i === currentPageHoras ? 'btn-primary' : 'btn-outline-primary');
                btn.style.marginRight = '6px'; // Espaçamento entre botões
                btn.addEventListener('click', () => {
                  currentPageHoras = i;
                  renderTabelaBancoHoras();
                });
                paginacao.appendChild(btn);
              }


              document.getElementById('prevPageHoras').disabled = currentPageHoras === 1;
              document.getElementById('nextPageHoras').disabled = currentPageHoras === totalPages || totalPages === 0;
            }

            searchInput.addEventListener('input', () => {
              currentPageHoras = 1;
              renderTabelaBancoHoras();
            });

            document.getElementById('prevPageHoras').addEventListener('click', () => {
              if (currentPageHoras > 1) {
                currentPageHoras--;
                renderTabelaBancoHoras();
              }
            });

            document.getElementById('nextPageHoras').addEventListener('click', () => {
              const filtro = searchInput.value.toLowerCase();
              const linhasFiltradas = linhasHoras.filter(linha => {
                const tds = linha.querySelectorAll('td');
                return Array.from(tds).some(td => td.textContent.toLowerCase().includes(filtro));
              });
              const totalPages = Math.ceil(linhasFiltradas.length / rowsPerPageHoras);
              if (currentPageHoras < totalPages) {
                currentPageHoras++;
                renderTabelaBancoHoras();
              }
            });

            // Inicializa a exibição
            renderTabelaBancoHoras();
          </script>

        </div>
      </div>
    </div>
  </div>
  </div>


  <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../../assets/vendor/libs/popper/popper.js"></script>
  <script src="../../assets/vendor/js/bootstrap.js"></script>
  <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="../../assets/vendor/js/menu.js"></script>
  <script src="../../assets/vendor/libs/apex-charts/apexcharts.js"></script>
  <script src="../../assets/js/main.js"></script>
</body>

</html>
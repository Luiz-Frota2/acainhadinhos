<?php
session_start();
require_once '../../assets/php/conexao.php';
date_default_timezone_set('America/Manaus');

// CORREÇÃO: capturar os valores da URL corretamente
$idSelecionado = $_GET['empresa_id'] ?? '';
$cpfFuncionario = $_GET['cpf'] ?? '';

if (
  !isset($_SESSION['usuario_logado']) ||
  !isset($_SESSION['empresa_id']) ||
  !isset($_SESSION['tipo_empresa']) ||
  !isset($_SESSION['usuario_id'])
) {
  header("Location: ../login.php?empresa_id=$idSelecionado");
  exit;
}

// Verifica tipo de empresa
$tipoEsperado = str_starts_with($idSelecionado, 'principal_') ? 'principal' : 'filial';
$numeroEsperado = (int) filter_var($idSelecionado, FILTER_SANITIZE_NUMBER_INT);

if ($_SESSION['tipo_empresa'] !== $tipoEsperado || $_SESSION['empresa_id'] != $numeroEsperado) {
  echo "<script>alert('Acesso negado!'); window.location.href = '../login.php?empresa_id=$idSelecionado';</script>";
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

// Buscar dados do usuário logado
$nomeUsuario = 'Usuário';
$nivelUsuario = 'Comum';
$usuario_id = $_SESSION['usuario_id'];

try {
  $stmt = $pdo->prepare("SELECT usuario, nivel, cpf FROM contas_acesso WHERE id = :id");
  $stmt->bindParam(':id', $usuario_id);
  $stmt->execute();
  $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($usuario) {
    $nomeUsuario = $usuario['usuario'];
    $nivelUsuario = $usuario['nivel'];
    $cpfUsuario = $usuario['cpf'];
  }
} catch (PDOException $e) {
  $nomeUsuario = 'Erro ao carregar';
  $nivelUsuario = 'Erro';
}

// Buscar registros de ponto apenas do CPF informado
try {
  $stmt = $pdo->prepare("
    SELECT r.*, f.nome AS nome_funcionario
    FROM registros_ponto r
    INNER JOIN funcionarios f ON r.cpf = f.cpf
    WHERE r.empresa_id = :eid AND r.cpf = :cpf
    ORDER BY r.data DESC, r.entrada ASC
  ");
  $stmt->execute([
    ':eid' => $idSelecionado,
    ':cpf' => $cpfFuncionario
  ]);
  $pontos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar registros: {$e->getMessage()}'); history.back();</script>";
  exit;
}

// Agrupar os pontos por data e CPF
$pontosAgrupados = [];
$diasSemana = [
  'Monday' => 'Segunda-feira',
  'Tuesday' => 'Terça-feira',
  'Wednesday' => 'Quarta-feira',
  'Thursday' => 'Quinta-feira',
  'Friday' => 'Sexta-feira',
  'Saturday' => 'Sábado',
  'Sunday' => 'Domingo'
];

foreach ($pontos as $rec) {
  $cpf = $rec['cpf'];
  $data = $rec['data'];
  $entTS = strtotime($rec['entrada']);
  $saiTS = $rec['saida'] ? strtotime($rec['saida']) : null;

  if (!isset($pontosAgrupados[$cpf][$data])) {
    $pontosAgrupados[$cpf][$data] = [
      'nome_funcionario' => $rec['nome_funcionario'],
      'dia_semana' => $diasSemana[(new DateTime($data))->format('l')],
      'entrada_am' => '-',
      'saida_am' => '-',
      'entrada_pm' => '-',
      'saida_pm' => '-',
      'foto_entrada_am' => null,
      'foto_saida_am' => null,
      'loc_entrada_am' => null,
      'loc_saida_am' => null,
      'foto_entrada_pm' => null,
      'foto_saida_pm' => null,
      'loc_entrada_pm' => null,
      'loc_saida_pm' => null,
    ];
  }

  if ($entTS < strtotime('12:00:00')) {
    $pontosAgrupados[$cpf][$data]['entrada_am'] = date('H:i', $entTS);
    $pontosAgrupados[$cpf][$data]['foto_entrada_am'] = $rec['foto_entrada'];
    $pontosAgrupados[$cpf][$data]['loc_entrada_am'] = $rec['localizacao_entrada'];
    if ($saiTS) {
      $pontosAgrupados[$cpf][$data]['saida_am'] = date('H:i', $saiTS);
      $pontosAgrupados[$cpf][$data]['foto_saida_am'] = $rec['foto_saida'];
      $pontosAgrupados[$cpf][$data]['loc_saida_am'] = $rec['localizacao_saida'];
    }
  } else {
    $pontosAgrupados[$cpf][$data]['entrada_pm'] = date('H:i', $entTS);
    $pontosAgrupados[$cpf][$data]['foto_entrada_pm'] = $rec['foto_entrada'];
    $pontosAgrupados[$cpf][$data]['loc_entrada_pm'] = $rec['localizacao_entrada'];
    if ($saiTS) {
      $pontosAgrupados[$cpf][$data]['saida_pm'] = date('H:i', $saiTS);
      $pontosAgrupados[$cpf][$data]['foto_saida_pm'] = $rec['foto_saida'];
      $pontosAgrupados[$cpf][$data]['loc_saida_pm'] = $rec['localizacao_saida'];
    }
  }
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
          <li class="menu-item">
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
              <div data-i18n="Authentications">Sistema de Ponto</div>
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
                <a href="#" class="menu-link">
                  <div data-i18n="Visualização Geral">Pontos Registrados</div>
                </a>
              </li>
              <li class="menu-item">
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
          <li class="menu-item">
            <a href="../filial/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-cog"></i>
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
                <input type="text" id="searchInput" class="form-control border-0 shadow-none" placeholder="Pesquisar..."
                  aria-label="Pesquisar..." />
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
                      <span class="align-middle">Configurações</span>
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
          <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a href="./relatorio.php?id=<?= urlencode($idSelecionado); ?>">Relatório</a>/</span>Visualização
            Geral</h4>
          <h5 class="fw-bold mt-3 mb-3 custor-font"><span class="text-muted fw-light">Visualize todos os
              Registros</span></h5>

          <!-- Card com tabela e paginação -->
          <div class="card">
            <h5 class="card-header">
                Registros de pontos - <?= htmlspecialchars($pontos[0]['nome_funcionario'] ?? 'Funcionário não encontrado') ?>
            </h5>

            <div class="table-responsive text-nowrap">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>Funcionário</th>
                    <th>Data</th>
                    <th>Dia</th>
                    <th>Entrada AM</th>
                    <th>Entrada Intervalo</th>
                    <th>Saída Intervalo</th>
                    <th>Saída PM</th>
                    <th>Fotos</th>
                    <th>Mapa</th>
                  </tr>
                </thead>
                <tbody id="tabelaFuncionarios" class="table-border-bottom-0">
                  <?php foreach ($pontosAgrupados as $cpf => $datas): ?>
                    <?php foreach ($datas as $data => $t): ?>
                      <?php $temDoisTurnos = $t['entrada_am'] !== '-' && $t['entrada_pm'] !== '-'; ?>
                      <tr>
                        <td><?= htmlspecialchars($t['nome_funcionario']) ?></td>
                        <td><?= date('d/m/Y', strtotime($data)) ?></td>
                        <td><?= $t['dia_semana'] ?></td>

                        <?php if ($temDoisTurnos): ?>
                          <td><?= $t['entrada_am'] ?></td>
                          <td><?= $t['saida_am'] ?></td>

                        <?php else: ?>
                          <td colspan="4">-</td>
                        <?php endif; ?>

                        <td><?= $t['entrada_pm'] ?></td>
                        <td><?= $t['saida_pm'] ?></td>

                        <td class="text-center">
                          <i class="bx bx-camera text-primary" style="cursor:pointer" data-bs-toggle="modal"
                            data-bs-target="#modalFoto" data-fotoam="<?= base64_encode($t['foto_entrada_am'] ?? '') ?>"
                            data-fotoam-s="<?= base64_encode($t['foto_saida_am'] ?? '') ?>"
                            data-fotopm="<?= base64_encode($t['foto_entrada_pm'] ?? '') ?>"
                            data-fotopm-s="<?= base64_encode($t['foto_saida_pm'] ?? '') ?>">
                          </i>
                        </td>
                        <td class="text-center">
                          <i class="bx bx-map text-success" style="cursor:pointer" data-bs-toggle="modal"
                            data-bs-target="#modalMapa" data-locam="<?= htmlspecialchars($t['loc_entrada_am'] ?? '') ?>"
                            data-locam-s="<?= htmlspecialchars($t['loc_saida_am'] ?? '') ?>"
                            data-locpm="<?= htmlspecialchars($t['loc_entrada_pm'] ?? '') ?>"
                            data-locpm-s="<?= htmlspecialchars($t['loc_saida_pm'] ?? '') ?>">
                          </i>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endforeach; ?>
                </tbody>

              </table>
            </div>

            <!-- Controles de paginação -->
            <div class="d-flex justify-content-start align-items-center gap-2 m-3">
              <button class="btn btn-sm btn-outline-primary" id="prevPage">&laquo; Anterior</button>
              <div id="paginacao" class="d-flex"></div>
              <button class="btn btn-sm btn-outline-primary" id="nextPage">Próxima &raquo;</button>
            </div>
          </div>

          <!-- Modal de Fotos -->
          <div class="modal fade" id="modalFoto" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">Fotos do Registro</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body d-flex flex-column flex-md-row justify-content-around align-items-center gap-3">
                  <div class="text-center">
                    <p><strong>Entrada AM</strong></p>
                    <img id="imgEntradaAM" class="img-fluid rounded border" style="max-height:300px">
                    <p><strong>Saída AM</strong></p>
                    <img id="imgSaidaAM" class="img-fluid rounded border" style="max-height:300px">
                  </div>
                  <div class="text-center">
                    <p><strong>Entrada PM</strong></p>
                    <img id="imgEntradaPM" class="img-fluid rounded border" style="max-height:300px">
                    <p><strong>Saída PM</strong></p>
                    <img id="imgSaidaPM" class="img-fluid rounded border" style="max-height:300px">
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Modal de Mapa -->
          <div class="modal fade" id="modalMapa" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">Localização do Registro</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body d-flex flex-column flex-md-row justify-content-around align-items-center gap-3">
                  <div class="text-center">
                    <p><strong>AM - Entrada</strong></p>
                    <iframe id="mapEntradaAM" width="100%" height="250" style="max-width:300px;border:0;"
                      loading="lazy"></iframe>
                    <p><strong>AM - Saída</strong></p>
                    <iframe id="mapSaidaAM" width="100%" height="250" style="max-width:300px;border:0;"
                      loading="lazy"></iframe>
                  </div>
                  <div class="text-center">
                    <p><strong>PM - Entrada</strong></p>
                    <iframe id="mapEntradaPM" width="100%" height="250" style="max-width:300px;border:0;"
                      loading="lazy"></iframe>
                    <p><strong>PM - Saída</strong></p>
                    <iframe id="mapSaidaPM" width="100%" height="250" style="max-width:300px;border:0;"
                      loading="lazy"></iframe>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <script>
            const searchInput = document.getElementById('searchInput');
            const allRows = Array.from(document.querySelectorAll('#tabelaFuncionarios tr'));
            const rowsPerPage = 10;
            let currentPage = 1;

            function renderTable() {
              // 1) Filtrar pelas colunas de texto do <td>, usando o valor do searchInput
              const filtro = searchInput.value.trim().toLowerCase();
              const filtered = allRows.filter(row => {
                if (!filtro) return true;
                return Array.from(row.cells).some(td =>
                  td.textContent.toLowerCase().includes(filtro)
                );
              });

              // 2) Paginação sobre filtered
              const totalPages = Math.ceil(filtered.length / rowsPerPage);
              const start = (currentPage - 1) * rowsPerPage;
              const end = start + rowsPerPage;

              // 3) Esconder todas
              allRows.forEach(r => r.style.display = 'none');
              // 4) Mostrar só o slice atual
              filtered.slice(start, end).forEach(r => r.style.display = '');

              // 5) Renderizar botões de página
              const pg = document.getElementById('paginacao');
              pg.innerHTML = '';
              for (let i = 1; i <= totalPages; i++) {
                const btn = document.createElement('button');
                btn.textContent = i;
                btn.className = 'btn btn-sm ' + (i === currentPage ? 'btn-primary' : 'btn-outline-primary');
                btn.style.marginRight = '6px';
                btn.onclick = () => {
                  currentPage = i;
                  renderTable();
                };
                pg.appendChild(btn);
              }

              // 6) Ajusta estado dos Next/Prev
              document.getElementById('prevPage').disabled = currentPage === 1;
              document.getElementById('nextPage').disabled = currentPage === totalPages || totalPages === 0;
            }

            // Eventos de paginação
            document.getElementById('prevPage').addEventListener('click', () => {
              if (currentPage > 1) {
                currentPage--;
                renderTable();
              }
            });
            document.getElementById('nextPage').addEventListener('click', () => {
              currentPage++;
              renderTable();
            });

            // Pesquisa em tempo real
            searchInput.addEventListener('input', () => {
              currentPage = 1;
              renderTable();
            });

            // Inicia a tabela
            renderTable();

            // popula modal de fotos
            document.querySelectorAll('[data-bs-target="#modalFoto"]').forEach(icon => {
              icon.addEventListener('click', () => {
                document.getElementById('imgEntradaAM').src = icon.dataset.fotoam ? 'data:image/jpeg;base64,' + icon.dataset.fotoam : '';
                document.getElementById('imgSaidaAM').src = icon.dataset.fotoamS ? 'data:image/jpeg;base64,' + icon.dataset.fotoamS : '';
                document.getElementById('imgEntradaPM').src = icon.dataset.fotopm ? 'data:image/jpeg;base64,' + icon.dataset.fotopm : '';
                document.getElementById('imgSaidaPM').src = icon.dataset.fotopmS ? 'data:image/jpeg;base64,' + icon.dataset.fotopmS : '';
              });
            });

            // popula modal de mapa
            document.querySelectorAll('[data-bs-target="#modalMapa"]').forEach(icon => {
              icon.addEventListener('click', () => {
                document.getElementById('mapEntradaAM').src = icon.dataset.locam ? `https://www.google.com/maps?q=${icon.dataset.locam}&output=embed` : '';
                document.getElementById('mapSaidaAM').src = icon.dataset.locamS ? `https://www.google.com/maps?q=${icon.dataset.locamS}&output=embed` : '';
                document.getElementById('mapEntradaPM').src = icon.dataset.locpm ? `https://www.google.com/maps?q=${icon.dataset.locpm}&output=embed` : '';
                document.getElementById('mapSaidaPM').src = icon.dataset.locpmS ? `https://www.google.com/maps?q=${icon.dataset.locpmS}&output=embed` : '';
              });
            });
          </script>


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

          <!-- Main JS -->
          <script src="../../assets/js/main.js"></script>

          <!-- Page JS -->
          <script src="../../assets/js/dashboards-analytics.js"></script>

          <!-- Place this tag in your head or just before your close body tag. -->
          <script async defer src="https://buttons.github.io/buttons.js"></script>
</body>

</html>
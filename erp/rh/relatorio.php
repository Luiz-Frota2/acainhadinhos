<?php
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

// Verifica tipo de empresa
if (str_starts_with($idSelecionado, 'principal_')) {
  if ($_SESSION['tipo_empresa'] !== 'principal' || $_SESSION['empresa_id'] != 1) {
    echo "<script>alert('Acesso negado!'); window.location.href = '.././login.php?id=$idSelecionado';</script>";
    exit;
  }
  $id = 1;
} elseif (str_starts_with($idSelecionado, 'filial_')) {
  $idFilial = (int) str_replace('filial_', '', $idSelecionado);
  if ($_SESSION['tipo_empresa'] !== 'filial' || $_SESSION['empresa_id'] != $idFilial) {
    echo "<script>alert('Acesso negado!'); window.location.href = '.././login.php?id=$idSelecionado';</script>";
    exit;
  }
  $id = $idFilial;
} else {
  echo "<script>alert('Empresa não identificada!'); window.location.href = '.././login.php?id=$idSelecionado';</script>";
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

// Buscar registros de ponto com nome do funcionário + foto/localização
try {
  $stmtPonto = $pdo->prepare("
    SELECT r.*, f.nome AS nome_funcionario
    FROM registros_ponto r
    INNER JOIN funcionarios f ON r.cpf = f.cpf
    WHERE r.empresa_id = :empresa_id
    ORDER BY r.data DESC, r.entrada ASC
  ");
  $stmtPonto->bindParam(':empresa_id', $idSelecionado);
  $stmtPonto->execute();
  $pontos = $stmtPonto->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar registros de ponto: " . $e->getMessage() . "'); history.back();</script>";
  exit;
}

// Agrupamento
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

foreach ($pontos as $registro) {
  $cpf = $registro['cpf'];
  $nomeFuncionario = $registro['nome_funcionario'];
  $data = $registro['data'];
  $entrada = strtotime($registro['entrada']);
  $saida = $registro['saida'] ? strtotime($registro['saida']) : null;

  // Novo: campos adicionais
  $fotoEntrada = $registro['foto_entrada'] ?? null;
  $fotoSaida = $registro['foto_saida'] ?? null;
  $localEntrada = $registro['localizacao_entrada'] ?? null;
  $localSaida = $registro['localizacao_saida'] ?? null;

  if (!isset($pontosAgrupados[$cpf])) {
    $pontosAgrupados[$cpf] = [];
  }

  if (!isset($pontosAgrupados[$cpf][$data])) {
    $pontosAgrupados[$cpf][$data] = [
      'nome_funcionario' => $nomeFuncionario,
      'dia_semana' => $diasSemana[(new DateTime($data))->format('l')],
      'entrada_am' => '-',
      'saida_am' => '-',
      'entrada_pm' => '-',
      'saida_pm' => '-',
      'entrada_noite' => '-',
      'saida_noite' => '-',
      // Adicionais
      'foto_entrada' => $fotoEntrada,
      'foto_saida' => $fotoSaida,
      'localizacao_entrada' => $localEntrada,
      'localizacao_saida' => $localSaida,
    ];
  }

  if ($entrada < strtotime('12:00:00')) {
    $pontosAgrupados[$cpf][$data]['entrada_am'] = date('H:i', $entrada);
    if ($saida)
      $pontosAgrupados[$cpf][$data]['saida_am'] = date('H:i', $saida);
  } elseif ($entrada < strtotime('18:00:00')) {
    $pontosAgrupados[$cpf][$data]['entrada_pm'] = date('H:i', $entrada);
    if ($saida)
      $pontosAgrupados[$cpf][$data]['saida_pm'] = date('H:i', $saida);
  } else {
    $pontosAgrupados[$cpf][$data]['entrada_noite'] = date('H:i', $entrada);
    if ($saida)
      $pontosAgrupados[$cpf][$data]['saida_noite'] = date('H:i', $saida);
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

  <title>ERP - Administração</title>

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
              <li class="menu-item active">
                <a href="#" class="menu-link">
                  <div data-i18n="Visualização Geral">Visualização Geral</div>
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
          <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a href="#">Relatório</a>/</span>Visualização
            Geral</h4>
          <h5 class="fw-bold mt-3 mb-3 custor-font"><span class="text-muted fw-light">Visualize todos os
              Registros</span></h5>

          <!-- Card com tabela e paginação -->
          <div class="card">
            <h5 class="card-header">Registros de pontos</h5>
            <div class="table-responsive text-nowrap">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>Funcionário</th>
                    <th>Data</th>
                    <th>Dia da Semana</th>
                    <th>Entrada AM</th>
                    <th>Saída AM</th>
                    <th>Entrada PM</th>
                    <th>Saída PM</th>
                    <th>Entrada Noite</th>
                    <th>Saída Noite</th>
                    <th>Foto</th>
                    <th>Mapa</th>
                  </tr>
                </thead>
                <tbody id="tabelaFuncionarios" class="table-border-bottom-0">
                  <?php foreach ($pontosAgrupados as $cpf => $dias): ?>
                    <?php foreach ($dias as $data => $turnos): ?>
                      <tr>
                        <td><?= htmlspecialchars($turnos['nome_funcionario']) ?></td>
                        <td><?= date('d/m/Y', strtotime($data)) ?></td>
                        <td><?= $turnos['dia_semana'] ?></td>
                        <td><?= $turnos['entrada_am'] ?></td>
                        <td><?= $turnos['saida_am'] ?></td>
                        <td><?= $turnos['entrada_pm'] ?></td>
                        <td><?= $turnos['saida_pm'] ?></td>
                        <td><?= $turnos['entrada_noite'] ?></td>
                        <td><?= $turnos['saida_noite'] ?></td>
                        <td>
                          <i class="bx bx-camera cursor-pointer text-primary" data-bs-toggle="modal"
                            data-bs-target="#modalFoto"
                            data-fotoentrada="<?= base64_encode($turnos['foto_entrada'] ?? '') ?>"
                            data-fotosaida="<?= base64_encode($turnos['foto_saida'] ?? '') ?>">
                          </i>
                        </td>
                        <td>
                          <i class="bx bx-map cursor-pointer text-success" data-bs-toggle="modal"
                            data-bs-target="#modalMapa"
                            data-localentrada="<?= htmlspecialchars($turnos['localizacao_entrada'] ?? '') ?>"
                            data-localsaida="<?= htmlspecialchars($turnos['localizacao_saida'] ?? '') ?>">
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
                    <p><strong>Entrada</strong></p>
                    <img id="imgEntrada" src="" alt="Foto Entrada" class="img-fluid rounded border"
                      style="max-height: 300px;">
                  </div>
                  <div class="text-center">
                    <p><strong>Saída</strong></p>
                    <img id="imgSaida" src="" alt="Foto Saída" class="img-fluid rounded border"
                      style="max-height: 300px;">
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
                    <p><strong>Entrada</strong></p>
                    <iframe id="mapEntrada" width="100%" height="250" style="max-width: 300px; border:0;"
                      loading="lazy"></iframe>
                  </div>
                  <div class="text-center">
                    <p><strong>Saída</strong></p>
                    <iframe id="mapSaida" width="100%" height="250" style="max-width: 300px; border:0;"
                      loading="lazy"></iframe>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Script de Pesquisa, Paginação e Modais -->
          <script>
            const searchInput = document.getElementById('searchInput');
            const linhas = Array.from(document.querySelectorAll('#tabelaFuncionarios tr'));
            const rowsPerPage = 10;
            let currentPage = 1;

            function renderTable() {
              const filtro = searchInput ? searchInput.value.toLowerCase() : '';
              const linhasFiltradas = linhas.filter(linha =>
                Array.from(linha.querySelectorAll('td')).some(td =>
                  td.textContent.toLowerCase().includes(filtro)
                )
              );

              const totalPages = Math.ceil(linhasFiltradas.length / rowsPerPage);
              const inicio = (currentPage - 1) * rowsPerPage;
              const fim = inicio + rowsPerPage;

              linhas.forEach(linha => linha.style.display = 'none');
              linhasFiltradas.slice(inicio, fim).forEach(linha => linha.style.display = '');

              const paginacao = document.getElementById('paginacao');
              paginacao.innerHTML = '';
              for (let i = 1; i <= totalPages; i++) {
                const btn = document.createElement('button');
                btn.textContent = i;
                btn.className = 'btn btn-sm ' + (i === currentPage ? 'btn-primary' : 'btn-outline-primary');
                btn.addEventListener('click', () => {
                  currentPage = i;
                  renderTable();
                });
                paginacao.appendChild(btn);
              }

              document.getElementById('prevPage').disabled = currentPage === 1;
              document.getElementById('nextPage').disabled = currentPage === totalPages;
            }

            if (searchInput) {
              searchInput.addEventListener('input', () => {
                currentPage = 1;
                renderTable();
              });
            }

            document.getElementById('prevPage').addEventListener('click', () => {
              if (currentPage > 1) {
                currentPage--;
                renderTable();
              }
            });

            document.getElementById('nextPage').addEventListener('click', () => {
              const filtro = searchInput ? searchInput.value.toLowerCase() : '';
              const linhasFiltradas = linhas.filter(linha =>
                Array.from(linha.querySelectorAll('td')).some(td =>
                  td.textContent.toLowerCase().includes(filtro)
                )
              );
              const totalPages = Math.ceil(linhasFiltradas.length / rowsPerPage);
              if (currentPage < totalPages) {
                currentPage++;
                renderTable();
              }
            });

            renderTable();

            // Popula modal de foto
            document.querySelectorAll('[data-bs-target="#modalFoto"]').forEach(icon => {
              icon.addEventListener('click', () => {
                const entrada = icon.getAttribute('data-fotoentrada');
                const saida = icon.getAttribute('data-fotosaida');
                document.getElementById('imgEntrada').src = entrada ? 'data:image/jpeg;base64,' + entrada : '';
                document.getElementById('imgSaida').src = saida ? 'data:image/jpeg;base64,' + saida : '';
              });
            });

            // Popula modal de mapa
            document.querySelectorAll('[data-bs-target="#modalMapa"]').forEach(icon => {
              icon.addEventListener('click', () => {
                const entrada = icon.getAttribute('data-localentrada');
                const saida = icon.getAttribute('data-localsaida');
                document.getElementById('mapEntrada').src = entrada ? `https://www.google.com/maps?q=${entrada}&output=embed` : '';
                document.getElementById('mapSaida').src = saida ? `https://www.google.com/maps?q=${saida}&output=embed` : '';
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
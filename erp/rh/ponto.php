<?php
session_start();
require_once '../../assets/php/conexao.php';
date_default_timezone_set('America/Manaus');

// Captura parâmetros da URL
$idSelecionado = $_GET['empresa_id'] ?? '';
$cpfFuncionario = $_GET['cpf'] ?? '';

// Verifica sessão
if (
  !isset($_SESSION['usuario_logado']) ||
  !isset($_SESSION['empresa_id']) ||
  !isset($_SESSION['tipo_empresa']) ||
  !isset($_SESSION['usuario_id'])
) {
  header("Location: ../login.php?empresa_id=$idSelecionado");
  exit;
}

// ✅ Buscar imagem da tabela sobre_empresa com base no idSelecionado
try {
  $sql = "SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1";
  $stmt = $pdo->prepare($sql);
  $stmt->bindParam(':id_selecionado', $idSelecionado, PDO::PARAM_STR);
  $stmt->execute();
  $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);

  $logoEmpresa = !empty($empresaSobre['imagem'])
    ? "../../assets/img/empresa/" . $empresaSobre['imagem']
    : "../../assets/img/favicon/logo.png"; // fallback padrão
} catch (PDOException $e) {
  $logoEmpresa = "../../assets/img/favicon/logo.png"; // fallback em caso de erro
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

// Verifica tipo de empresa esperado (principal ou filial)
$tipoEsperado = str_starts_with($idSelecionado, 'principal_') ? 'principal' : 'filial';
$numeroEsperado = (int) filter_var($idSelecionado, FILTER_SANITIZE_NUMBER_INT);

// Validação de acesso
if ($_SESSION['tipo_empresa'] !== $tipoEsperado || $_SESSION['empresa_id'] != $numeroEsperado) {
  echo "<script>alert('Acesso negado!'); window.location.href = '../login.php?empresa_id=$idSelecionado';</script>";
  exit;
}

try {
  $sql = "SELECT 
            p.data,
            p.nome AS registro_nome,        -- nome do ponto (caso use)
            f.nome AS nome_funcionario,     -- nome do funcionário
            p.entrada,
            p.foto_entrada,
            p.localizacao_entrada,
            p.saida_intervalo,
            p.foto_saida_intervalo,
            p.localizacao_saida_intervalo,
            p.retorno_intervalo,
            p.foto_retorno_intervalo,
            p.localizacao_retorno_intervalo,
            p.saida_final,
            p.foto_saida_final,
            p.localizacao_saida_final
          FROM pontos p
          INNER JOIN funcionarios f ON p.cpf = f.cpf
          WHERE p.cpf = :cpf 
            AND p.empresa_id = :empresa_id
          ORDER BY p.data DESC";

  $stmt = $pdo->prepare($sql);
  $stmt->bindParam(':cpf', $cpfFuncionario);
  $stmt->bindParam(':empresa_id', $idSelecionado);
  $stmt->execute();

  $pontos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  die("Erro ao buscar pontos: " . $e->getMessage());
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
                <a href="./adicionarPonto.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Registro de Ponto Eletrônico">Adicionar Ponto</div>
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
              <li class="menu-item ">
                <a href="./frequencia.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Ajuste de Horários e Banco de Horas">Frequência</div>
                </a>
              </li>
              <li class="menu-item">
                <a href="./frequenciaIndividual.php?id=<?= urlencode($idSelecionado); ?>"
                  class="menu-link">
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
                    <img src="<?= htmlspecialchars($logoEmpresa) ?>" alt class="w-px-40 h-auto rounded-circle" />
                  </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li>
                    <a class="dropdown-item" href="#">
                      <div class="d-flex">
                        <div class="flex-shrink-0 me-3">
                          <div class="avatar avatar-online">
                            <img src="<?= htmlspecialchars($logoEmpresa) ?>" alt class="w-px-40 h-auto rounded-circle" />
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
        <div class="container-xxl flex-grow-1 container-p-y">
          <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a href="./relatorio.php?id=<?= urlencode($idSelecionado); ?>">Relatório</a>/</span>Visualização
            Geral</h4>
          <h5 class="fw-bold mt-3 mb-3 custor-font"><span class="text-muted fw-light">Visualize todos os
              Registros</span></h5>

          <!-- Card com tabela e paginação -->
          <div class="card">
            <h5 class="card-header">
              Registros de pontos –
              <?= htmlspecialchars($pontos[0]['nome_funcionario'] ?? 'Funcionário não encontrado') ?>
            </h5>

            <div class="table-responsive text-nowrap">
              <table class="table" id="tabelaFuncionarios">
                <thead>
                  <tr>
                    <th>Data</th>
                    <th>Funcionário</th>
                    <th>Entrada AM</th>
                    <th>Saída Intervalo</th>
                    <th>Retorno Intervalo</th>
                    <th>Saída PM</th>
                    <th>Fotos</th>
                    <th>Localizações</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (count($pontos) > 0): ?>
                    <?php foreach ($pontos as $registro): ?>
                      <tr>
                        <!-- Data no formato DD/MM/AAAA -->
                        <td>
                          <?= date('d/m/Y', strtotime($registro['data'])) ?>
                        </td>

                        <td><?= htmlspecialchars($registro['nome_funcionario']) ?></td>

                        <!-- Horários formatados como HH:MM -->
                        <td>
                          <?= !empty($registro['entrada'])
                            ? date('H:i', strtotime($registro['entrada']))
                            : '-' ?>
                        </td>
                        <td>
                          <?= !empty($registro['saida_intervalo'])
                            ? date('H:i', strtotime($registro['saida_intervalo']))
                            : '-' ?>
                        </td>
                        <td>
                          <?= !empty($registro['retorno_intervalo'])
                            ? date('H:i', strtotime($registro['retorno_intervalo']))
                            : '-' ?>
                        </td>
                        <td>
                          <?= !empty($registro['saida_final'])
                            ? date('H:i', strtotime($registro['saida_final']))
                            : '-' ?>
                        </td>

                        <td class="text-center">
                          <button
                            class="btn btn-sm btn-info"
                            data-bs-toggle="modal"
                            data-bs-target="#modalFoto"
                            data-fotoam="<?= base64_encode($registro['foto_entrada'] ?? '') ?>"
                            data-fotoamS="<?= base64_encode($registro['foto_saida_intervalo'] ?? '') ?>"
                            data-fotopm="<?= base64_encode($registro['foto_retorno_intervalo'] ?? '') ?>"
                            data-fotopmS="<?= base64_encode($registro['foto_saida_final'] ?? '') ?>">
                            📷
                          </button>
                        </td>

                        <td class="text-center">
                          <button
                            class="btn btn-sm btn-primary"
                            data-bs-toggle="modal"
                            data-bs-target="#modalMapa"
                            data-locam="<?= htmlspecialchars($registro['localizacao_entrada']) ?>"
                            data-locamS="<?= htmlspecialchars($registro['localizacao_saida_intervalo']) ?>"
                            data-locpm="<?= htmlspecialchars($registro['localizacao_retorno_intervalo']) ?>"
                            data-locpmS="<?= htmlspecialchars($registro['localizacao_saida_final']) ?>">
                            🗺️
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="8" class="text-center">Nenhum registro encontrado para este CPF e empresa.</td>
                    </tr>
                  <?php endif; ?>
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

          <!-- Modal Fotos -->
          <div class="modal fade" id="modalFoto" tabindex="-1" aria-labelledby="modalFotoLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">Fotos do Ponto</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                  <div class="row g-3">
                    <div class="col-12 col-md-6">
                      <h6>Entrada AM</h6>
                      <div class="ratio ratio-4x3">
                        <img id="fotoEntradaAM" src="" alt="Foto Entrada AM" class="img-fluid rounded" style="object-fit: cover;">
                      </div>
                      <h6 class="mt-3">Saída Intervalo</h6>
                      <div class="ratio ratio-4x3">
                        <img id="fotoSaidaIntervalo" src="" alt="" class="img-fluid rounded" style="object-fit: cover;">
                      </div>
                    </div>
                    <div class="col-12 col-md-6">
                      <h6>Retorno Intervalo</h6>
                      <div class="ratio ratio-4x3">
                        <img id="fotoRetornoIntervalo" src="" alt="Foto Retorno Intervalo" class="img-fluid rounded" style="object-fit: cover;">
                      </div>
                      <h6 class="mt-3">Saída Final</h6>
                      <div class="ratio ratio-4x3">
                        <img id="fotoSaidaFinal" src="" alt="Foto Saída Final" class="img-fluid rounded" style="object-fit: cover;">
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>


          <!-- Modal do Mapa -->
          <div class="modal fade" id="modalMapa" tabindex="-1" aria-labelledby="modalMapaLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">Localizações do Ponto</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                  <div class="row g-3">
                    <div class="col-12 col-md-6">
                      <h6>Entrada AM</h6>
                      <div class="ratio ratio-4x3">
                        <iframe id="mapEntradaAM" frameborder="0" allowfullscreen loading="lazy" style="border:0;"></iframe>
                      </div>
                    </div>
                    <div class="col-12 col-md-6">
                      <h6>Saída Intervalo</h6>
                      <div class="ratio ratio-4x3">
                        <iframe id="mapSaidaAM" frameborder="0" allowfullscreen loading="lazy" style="border:0;"></iframe>
                      </div>
                    </div>
                    <div class="col-12 col-md-6">
                      <h6>Retorno Intervalo</h6>
                      <div class="ratio ratio-4x3">
                        <iframe id="mapEntradaPM" frameborder="0" allowfullscreen loading="lazy" style="border:0;"></iframe>
                      </div>
                    </div>
                    <div class="col-12 col-md-6">
                      <h6>Saída Final</h6>
                      <div class="ratio ratio-4x3">
                        <iframe id="mapSaidaPM" frameborder="0" allowfullscreen loading="lazy" style="border:0;"></iframe>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Scripts -->

          <!-- Certifique-se que você tenha incluído o CSS e JS do Leaflet no seu HTML, por exemplo: -->
          <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
          <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

          <script>
            // Modal de Fotos
            const modalFoto = document.getElementById('modalFoto');
            modalFoto.addEventListener('show.bs.modal', function(event) {
              const button = event.relatedTarget;
              const fotoEntradaAM = button.getAttribute('data-fotoam') || '';
              const fotoSaidaIntervalo = button.getAttribute('data-fotoamS') || '';
              const fotoRetornoIntervalo = button.getAttribute('data-fotopm') || '';
              const fotoSaidaFinal = button.getAttribute('data-fotopmS') || '';

              document.getElementById('fotoEntradaAM').src = fotoEntradaAM ? `data:image/jpeg;base64,${fotoEntradaAM}` : 'https://via.placeholder.com/300x200?text=Sem+Foto';
              document.getElementById('fotoSaidaIntervalo').src = fotoSaidaIntervalo ? `data:image/jpeg;base64,${fotoSaidaIntervalo}` : 'https://via.placeholder.com/300x200?text=Sem+Foto';
              document.getElementById('fotoRetornoIntervalo').src = fotoRetornoIntervalo ? `data:image/jpeg;base64,${fotoRetornoIntervalo}` : 'https://via.placeholder.com/300x200?text=Sem+Foto';
              document.getElementById('fotoSaidaFinal').src = fotoSaidaFinal ? `data:image/jpeg;base64,${fotoSaidaFinal}` : 'https://via.placeholder.com/300x200?text=Sem+Foto';
            });

            // Modal de Mapas (Google Maps embed)
            const modalMapa = document.getElementById('modalMapa');
            modalMapa.addEventListener('show.bs.modal', function(event) {
              const button = event.relatedTarget;
              const locam = button.getAttribute('data-locam') || '';
              const locamS = button.getAttribute('data-locamS') || '';
              const locpm = button.getAttribute('data-locpm') || '';
              const locpmS = button.getAttribute('data-locpmS') || '';

              function isValidLatLng(str) {
                if (!str) return false;
                const parts = str.split(',');
                if (parts.length !== 2) return false;
                return !isNaN(parseFloat(parts[0])) && !isNaN(parseFloat(parts[1]));
              }

              document.getElementById('mapEntradaAM').src = isValidLatLng(locam) ? `https://www.google.com/maps?q=${locam}&output=embed` : '';
              document.getElementById('mapSaidaAM').src = isValidLatLng(locamS) ? `https://www.google.com/maps?q=${locamS}&output=embed` : '';
              document.getElementById('mapEntradaPM').src = isValidLatLng(locpm) ? `https://www.google.com/maps?q=${locpm}&output=embed` : '';
              document.getElementById('mapSaidaPM').src = isValidLatLng(locpmS) ? `https://www.google.com/maps?q=${locpmS}&output=embed` : '';
            });

            modalMapa.addEventListener('hidden.bs.modal', () => {
              ['mapEntradaAM', 'mapSaidaAM', 'mapEntradaPM', 'mapSaidaPM'].forEach(id => {
                document.getElementById(id).src = '';
              });
            });

            // Busca em tempo real + Paginação
            const searchInput = document.getElementById('searchInput');
            const allRows = Array.from(document.querySelectorAll('#tabelaFuncionarios tbody tr'));
            const rowsPerPage = 10;
            let currentPage = 1;

            function renderTable() {
              const filtro = searchInput.value.trim().toLowerCase();
              const filtered = allRows.filter(row => {
                return !filtro || Array.from(row.cells).some(td => td.textContent.toLowerCase().includes(filtro));
              });

              const totalPages = Math.ceil(filtered.length / rowsPerPage) || 1;
              currentPage = Math.min(currentPage, totalPages);

              const start = (currentPage - 1) * rowsPerPage;
              const end = start + rowsPerPage;

              allRows.forEach(r => r.style.display = 'none');
              filtered.slice(start, end).forEach(r => r.style.display = '');

              const pg = document.getElementById('paginacao');
              pg.innerHTML = '';
              for (let i = 1; i <= totalPages; i++) {
                const btn = document.createElement('button');
                btn.textContent = i;
                btn.className = `btn btn-sm ${i === currentPage ? 'btn-primary' : 'btn-outline-primary'}`;
                btn.addEventListener('click', () => {
                  currentPage = i;
                  renderTable();
                });
                pg.appendChild(btn);
              }

              document.getElementById('prevPage').disabled = currentPage === 1;
              document.getElementById('nextPage').disabled = currentPage === totalPages;
            }

            document.getElementById('prevPage').addEventListener('click', () => {
              if (currentPage > 1) currentPage--, renderTable();
            });
            document.getElementById('nextPage').addEventListener('click', () => {
              currentPage++, renderTable();
            });
            searchInput.addEventListener('input', () => {
              currentPage = 1;
              renderTable();
            });

            // Inicializa ao carregar
            document.addEventListener('DOMContentLoaded', renderTable);
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
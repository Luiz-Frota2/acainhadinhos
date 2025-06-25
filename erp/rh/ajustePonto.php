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
  !isset($_SESSION['usuario_id']) // adiciona verificação do id do usuário
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

// ✅ Se chegou até aqui, o acesso está liberado

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

try {
  $stmt = $pdo->prepare("
    SELECT 
      p.id AS ponto_id,
      p.cpf,
      f.nome AS nome_funcionario,
      p.data,
      p.entrada,
      p.saida_intervalo,
      p.retorno_intervalo,
      p.saida_final
    FROM pontos p
    LEFT JOIN funcionarios f ON f.cpf = p.cpf
    WHERE p.empresa_id = :empresa_id
    ORDER BY p.data DESC
  ");
  $stmt->bindParam(':empresa_id', $idSelecionado);
  $stmt->execute();
  $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  echo "<tr><td colspan='7'>Erro ao buscar pontos: " . $e->getMessage() . "</td></tr>";
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
          <li class="menu-item active open">
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
              <li class="menu-item active">
                <a href="#" class="menu-link">
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
                <input type="text" class="form-control border-0 shadow-none" id="searchInput"
                  placeholder="Buscar pontos..." aria-label="Search..." />
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
                      <span class="align-middle">Configrações</span>
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

          <!-- Tabela de Ajuste de Ponto -->

          <div class="card">
            <h5 class="card-header">Lista de Registros de Ponto</h5>
            <div class="table-responsive text-nowrap">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>Funcionário</th>
                    <th>Data</th>
                    <th>Entrada</th>
                    <th>Saída para Intervalo</th>
                    <th>Entrada do Intervalo</th>
                    <th>Saída</th>
                    <th>Ações</th>
                  </tr>
                </thead>
                <tbody class="table-border-bottom-0" id="tabelaAjuste">
                  <?php foreach ($registros as $registro): ?>
                    <tr>
                      <td><strong><?= htmlspecialchars($registro['nome_funcionario'] ?? 'Desconhecido') ?></strong></td>
                      <td><?= date('d/m/Y', strtotime($registro['data'])) ?></td>
                      <td>
                        <?= !empty($registro['entrada']) ? date('H:i', strtotime($registro['entrada'])) : '--:--' ?>
                      </td>
                      <td>
                        <?= !empty($registro['saida_intervalo']) ? date('H:i', strtotime($registro['saida_intervalo'])) : '--:--' ?>
                      </td>
                      <td>
                        <?= !empty($registro['retorno_intervalo']) ? date('H:i', strtotime($registro['retorno_intervalo'])) : '--:--' ?>
                      </td>
                      <td>
                        <?= !empty($registro['saida_final']) ? date('H:i', strtotime($registro['saida_final'])) : '--:--' ?>
                      </td>

                      <td>
                        <button class="btn btn-link text-primary p-0" title="Editar" data-bs-toggle="modal"
                          data-bs-target="#editModal-<?= $registro['ponto_id'] ?>">
                          <i class="tf-icons bx bx-edit"></i>
                        </button>

                        <!-- Modal -->
                        <div class="modal fade" id="editModal-<?= $registro['ponto_id'] ?>" tabindex="-1"
                          aria-labelledby="editModalLabel-<?= $registro['ponto_id'] ?>" aria-hidden="true">
                          <div class="modal-dialog">
                            <div class="modal-content">
                              <form method="POST" action="../../assets/php/rh/atualizarAjustePonto.php">
                                <input type="hidden" name="id" value="<?= $registro['ponto_id'] ?>">
                                <input type="hidden" name="cpf" value="<?= $registro['cpf'] ?>">
                                <input type="hidden" name="empresa_id" value="<?= $idSelecionado ?>">
                                <input type="hidden" name="data" value="<?= $registro['data'] ?>">

                                <div class="modal-header">
                                  <h5 class="modal-title">Editar Ponto</h5>
                                  <button type="button" class="btn-close" data-bs-dismiss="modal"
                                    aria-label="Fechar"></button>
                                </div>

                                <div class="modal-body">
                                  <div class="mb-3">
                                    <label for="entrada" class="form-label">Entrada</label>
                                    <input type="time" class="form-control" name="entrada"
                                      value="<?= !empty($registro['entrada']) ? date('H:i', strtotime($registro['entrada'])) : '' ?>">
                                  </div>
                                  <div class="mb-3">
                                    <label for="saida_intervalo" class="form-label">Saída para Intervalo</label>
                                    <input type="time" class="form-control" name="saida_intervalo"
                                      value="<?= !empty($registro['saida_intervalo']) ? date('H:i', strtotime($registro['saida_intervalo'])) : '' ?>">
                                  </div>
                                  <div class="mb-3">
                                    <label for="retorno_intervalo" class="form-label">Entrada do Intervalo</label>
                                    <input type="time" class="form-control" name="retorno_intervalo"
                                      value="<?= !empty($registro['retorno_intervalo']) ? date('H:i', strtotime($registro['retorno_intervalo'])) : '' ?>">
                                  </div>
                                  <div class="mb-3">
                                    <label for="saida_final" class="form-label">Saída</label>
                                    <input type="time" class="form-control" name="saida_final"
                                      value="<?= !empty($registro['saida_final']) ? date('H:i', strtotime($registro['saida_final'])) : '' ?>">
                                  </div>
                                </div>

                                <div class="modal-footer">
                                  <button type="button" class="btn btn-secondary"
                                    data-bs-dismiss="modal">Cancelar</button>
                                  <button type="submit" class="btn btn-primary">Salvar</button>
                                </div>
                              </form>
                            </div>
                          </div>
                        </div>

                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>

              </table>
            </div>

            <!-- Paginação -->
            <div class="d-flex justify-content-start align-items-center gap-2 m-3">
              <div>
                <button id="prevPage" class="btn btn-sm btn-outline-primary">Anterior</button>
                <div id="paginacao" class="btn-group"></div>
                <button id="nextPage" class="btn btn-sm btn-outline-primary">Próximo</button>
              </div>
            </div>

          </div>
        </div>

        <script>
          const searchInput = document.getElementById('searchInput');
          const linhas = Array.from(document.querySelectorAll('#tabelaAjuste tr'));
          const rowsPerPage = 20;
          let currentPage = 1;

          function renderTable() {
            const filtro = searchInput.value.toLowerCase();
            const linhasFiltradas = linhas.filter(linha => {
              return Array.from(linha.querySelectorAll('td')).some(td =>
                td.textContent.toLowerCase().includes(filtro)
              );
            });

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

              // Adiciona espaçamento horizontal entre os botões
              btn.style.marginRight = "6px";

              btn.className = 'btn btn-sm ' + (i === currentPage ? 'btn-primary' : 'btn-outline-primary');
              btn.addEventListener('click', () => {
                currentPage = i;
                renderTable();
              });
              paginacao.appendChild(btn);
            }

            document.getElementById('prevPage').disabled = currentPage === 1;
            document.getElementById('nextPage').disabled = currentPage === totalPages || totalPages === 0;
          }

          searchInput.addEventListener('input', () => {
            currentPage = 1;
            renderTable();
          });

          document.getElementById('prevPage').addEventListener('click', () => {
            if (currentPage > 1) {
              currentPage--;
              renderTable();
            }
          });

          document.getElementById('nextPage').addEventListener('click', () => {
            const filtro = searchInput.value.toLowerCase();
            const linhasFiltradas = linhas.filter(linha => {
              return Array.from(linha.querySelectorAll('td')).some(td =>
                td.textContent.toLowerCase().includes(filtro)
              );
            });
            const totalPages = Math.ceil(linhasFiltradas.length / rowsPerPage);
            if (currentPage < totalPages) {
              currentPage++;
              renderTable();
            }
          });

          renderTable();
        </script>

        <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
        <script src="../../assets/vendor/libs/popper/popper.js"></script>
        <script src="../../assets/vendor/js/bootstrap.js"></script>
        <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
        <script src="../../assets/vendor/js/menu.js"></script>
        <script src="../../assets/vendor/libs/apex-charts/apexcharts.js"></script>
        <script src="../../assets/js/main.js"></script>
</body>

</html>
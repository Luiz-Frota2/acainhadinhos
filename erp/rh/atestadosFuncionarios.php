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
  $id = 'principal_1';  // Formato como está na tabela
} elseif (str_starts_with($idSelecionado, 'filial_')) {
  $idFilial = (int) str_replace('filial_', '', $idSelecionado);
  if ($_SESSION['tipo_empresa'] !== 'filial' || $_SESSION['empresa_id'] != $idFilial) {
    echo "<script>
              alert('Acesso negado!');
              window.location.href = '.././login.php?id=$idSelecionado';
          </script>";
    exit;
  }
  $id = "filial_$idFilial";  // Formato como está na tabela
} else {
  echo "<script>
          alert('Empresa não identificada!');
          window.location.href = '.././login.php?id=$idSelecionado';
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
  echo "<script>alert('Erro ao carregar ícone da empresa: " . addslashes($e->getMessage()) . "');</script>";
}

// ✅ Buscar nome e CPF do usuário logado
$nomeUsuario = 'Usuário';
$cpfUsuario = ''; // Valor padrão
$usuario_id = $_SESSION['usuario_id'];

try {
  $stmt = $pdo->prepare("SELECT usuario, cpf FROM contas_acesso WHERE id = :id");
  $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
  $stmt->execute();
  $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($usuario) {
    $nomeUsuario = $usuario['usuario'];
    $cpfUsuario = $usuario['cpf']; // Recupera o CPF do usuário logado
  }
} catch (PDOException $e) {
  $nomeUsuario = 'Erro ao carregar nome';
  $cpfUsuario = 'Erro ao carregar CPF';
}

// ✅ Recupera os atestados da empresa logada, filtrando pelo CPF do funcionário
$atestados = [];
// ✅ Verificar e trazer os atestados do banco de dados
try {
  // Ajuste na consulta SQL
  $stmt = $pdo->prepare("
      SELECT 
          a.id, 
          a.nome_funcionario, 
          a.data_envio, 
          a.data_atestado, 
          a.dias_afastado, 
          a.medico, 
          a.observacoes, 
          a.imagem_atestado, 
          f.nome AS funcionario_nome
      FROM atestados a
      JOIN funcionarios f ON f.cpf = a.cpf_usuario
      WHERE a.id_empresa = :id_empresa
  ");
  $stmt->bindParam(':id_empresa', $id, PDO::PARAM_STR);
  $stmt->execute();

  // Verifica se encontrou resultados
  if ($stmt->rowCount() > 0) {
    $atestados = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $atestados = []; // Caso não encontre dados
  }
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar os atestados: " . addslashes($e->getMessage()) . "');</script>";
}

?>


<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
  data-assets-path="../assets/">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
  <title>ERP - Atestados</title>
  <meta name="description" content="" />

  <!-- Favicon da empresa carregado dinamicamente -->
  <link rel="icon" type="image/x-icon" href="../../assets/img/empresa/<?php echo htmlspecialchars($iconeEmpresa); ?>" />
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
                  <div data-i18n="Escalas e Configuração">Adicionadas</div>
                </a>
              </li>
              <li class="menu-item">
                <a href="./ajustePonto.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Registro de Ponto Eletrônico">Ajuste de Ponto</div>
                </a>
              </li>
              <li class="menu-item active">
                <a href="./atestadosFuncionarios.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Atestados</div>
                </a>
              </li>

            </ul>
          </li>

          <!-- Menu Relatórios -->
          <li class="menu-item  ">
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
              <li class="menu-item ">
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
                <input type="text" class="form-control border-0 shadow-none" id="searchInput" placeholder="Pesquisar..."
                  aria-label="Search..." />
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
                    <a class="dropdown-item" href="../logout.php?= urlencode($idSelecionado); ?>">
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
          <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a href="#">Sistema de Ponto</a>/</span>Atestados
          </h4>
          <h5 class="fw-bold mt-3 mb-3 custor-font"><span class="text-muted fw-light">Visualize e ajuste os pontos
              registrados</span></h5>

          <!-- Tabela Banco de Horas -->
          <div class="card">
            <h5 class="card-header">Atestados Recebidos</h5>

            <!-- Tabela de Atestados -->
            <div class="">
              <div class="table-responsive text-nowrap">
                <table class="table table-hover" id="tabelaAtestados">
                  <thead>
                    <tr>
                      <th>Funcionário</th>
                      <th>Data de Envio</th>
                      <th>Data do Atestado</th>
                      <th>Dias Afastado</th>
                      <th>Médico</th>
                      <th>Observações</th>
                      <th>Ações</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($atestados as $atestado): ?>
                      <tr>
                        <td><?= htmlspecialchars($atestado['nome_funcionario']) ?></td>
                        <td><?= (new DateTime($atestado['data_envio']))->format('d/m/Y') ?></td>
                        <td><?= (new DateTime($atestado['data_atestado']))->format('d/m/Y') ?></td>
                        <td><?= htmlspecialchars($atestado['dias_afastado']) ?></td>
                        <td><?= htmlspecialchars($atestado['medico']) ?></td>
                        <td><?= htmlspecialchars($atestado['observacoes']) ?></td>
                        <td>
                          <button class="btn btn-link text-muted p-0" title="Visualizar" data-bs-toggle="modal"
                            data-bs-target="#detalhesAtestadoModal"
                            data-funcionario="<?= htmlspecialchars($atestado['nome_funcionario']) ?>"
                            data-dataenvio="<?= htmlspecialchars($atestado['data_envio']) ?>"
                            data-dataatestado="<?= htmlspecialchars($atestado['data_atestado']) ?>"
                            data-diasafastado="<?= htmlspecialchars($atestado['dias_afastado']) ?>"
                            data-medico="<?= htmlspecialchars($atestado['medico']) ?>"
                            data-observacoes="<?= htmlspecialchars($atestado['observacoes']) ?>"
                            data-atestado="<?= htmlspecialchars($atestado['imagem_atestado']) ?>">
                            <i class="fas fa-eye"></i>
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Controles de paginação -->
            <div class="d-flex justify-content-start align-items-center gap-2 m-3">
              <button class="btn btn-sm btn-outline-primary" id="prevPageAtestados">&laquo; Anterior</button>
              <div id="paginacaoAtestados" class="mx-2"></div>
              <button class="btn btn-sm btn-outline-primary" id="nextPageAtestados">Próxima &raquo;</button>
            </div>

            <!-- Modal de Detalhes -->
            <div class="modal fade" id="detalhesAtestadoModal" tabindex="-1" aria-labelledby="detalhesAtestadoLabel"
              aria-hidden="true">
              <div class="modal-dialog modal-lg"> <!-- opcionalmente maior -->
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="detalhesAtestadoLabel">Detalhes do Atestado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                  </div>
                  <div class="modal-body">
                    <h6><strong>Funcionário:</strong> <span id="modalFuncionario"></span></h6>
                    <h6><strong>Data de Envio:</strong> <span id="modalDataEnvio"></span></h6>
                    <h6><strong>Data do Atestado:</strong> <span id="modalDataAtestado"></span></h6>
                    <h6><strong>Dias Afastado:</strong> <span id="modalDiasAfastado"></span></h6>
                    <h6><strong>Médico:</strong> <span id="modalMedico"></span></h6>
                    <div class="mt-3">
                      <h6><strong>Observações:</strong></h6>
                      <p id="modalObservacoes"></p>
                    </div>
                    <div class="mt-3">
                      <h6><strong>Última Atualização:</strong> <span id="modalUltimaAtualizacao"></span></h6>
                    </div>
                    <div class="mt-4 text-center">
                      <h6><strong>Atestado:</strong></h6>
                      <img id="modalAtestadoImg" src="" alt="Imagem do Atestado" class="img-fluid rounded"
                        style="max-height: 400px;">
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <script>
              // Modal
              const modal = document.getElementById('detalhesAtestadoModal');
              modal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                document.getElementById('modalFuncionario').textContent = button.getAttribute('data-funcionario');
                document.getElementById('modalDataEnvio').textContent = new Date(button.getAttribute('data-dataenvio')).toLocaleDateString();
                document.getElementById('modalDataAtestado').textContent = new Date(button.getAttribute('data-dataatestado')).toLocaleDateString();
                document.getElementById('modalDiasAfastado').textContent = button.getAttribute('data-diasafastado');
                document.getElementById('modalMedico').textContent = button.getAttribute('data-medico');
                document.getElementById('modalObservacoes').textContent = button.getAttribute('data-observacoes');
                document.getElementById('modalUltimaAtualizacao').textContent = new Date().toLocaleString();
                document.getElementById('modalAtestadoImg').src = "../../assets/img/atestados/" + button.getAttribute('data-atestado');
              });

              // Variáveis
              const linhasPorPagina = 10;
              let currentPage = 1;

              // Função para obter apenas as linhas visíveis
              function getLinhasVisiveis() {
                return Array.from(document.querySelectorAll('#tabelaAtestados tbody tr'))
                  .filter(linha => linha.style.display !== 'none');
              }

              // Paginação
              function atualizarPaginacao() {
                const linhasVisiveis = getLinhasVisiveis();
                const totalPaginas = Math.ceil(linhasVisiveis.length / linhasPorPagina);

                linhasVisiveis.forEach(linha => linha.style.display = 'none');

                linhasVisiveis.forEach((linha, index) => {
                  if (index >= (currentPage - 1) * linhasPorPagina && index < currentPage * linhasPorPagina) {
                    linha.style.display = '';
                  }
                });

                document.getElementById('paginacaoAtestados').textContent = `Página ${currentPage} de ${totalPaginas || 1}`;
                document.getElementById('prevPageAtestados').disabled = currentPage === 1;
                document.getElementById('nextPageAtestados').disabled = currentPage >= totalPaginas;
              }

              document.getElementById('prevPageAtestados').addEventListener('click', () => {
                if (currentPage > 1) {
                  currentPage--;
                  atualizarPaginacao();
                }
              });

              document.getElementById('nextPageAtestados').addEventListener('click', () => {
                const totalPaginas = Math.ceil(getLinhasVisiveis().length / linhasPorPagina);
                if (currentPage < totalPaginas) {
                  currentPage++;
                  atualizarPaginacao();
                }
              });

              // Pesquisa
              document.getElementById('searchInput').addEventListener('input', function () {
                const filtro = this.value.toLowerCase();
                const todasLinhas = document.querySelectorAll('#tabelaAtestados tbody tr');

                todasLinhas.forEach(linha => {
                  const colunas = linha.querySelectorAll('td');
                  let corresponde = false;

                  colunas.forEach(coluna => {
                    if (coluna.textContent.toLowerCase().includes(filtro)) {
                      corresponde = true;
                    }
                  });

                  linha.style.display = corresponde ? '' : 'none';
                });

                currentPage = 1;
                atualizarPaginacao();
              });

              // Inicializa
              document.addEventListener('DOMContentLoaded', () => {
                atualizarPaginacao();
              });
            </script>

          </div>

        </div>

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
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
$usuario_id = $_SESSION['usuario_id'];

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
$acessoPermitido = false;
$idEmpresaSession = $_SESSION['empresa_id'];
$tipoSession = $_SESSION['tipo_empresa'];

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
  echo "<script>
          alert('Acesso negado!');
          window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';
        </script>";
  exit;
}

// ✅ Buscar logo da empresa
try {
  $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1");
  $stmt->bindParam(':id_selecionado', $idSelecionado, PDO::PARAM_STR);
  $stmt->execute();
  $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);

  $logoEmpresa = !empty($empresaSobre['imagem'])
    ? "../../assets/img/empresa/" . $empresaSobre['imagem']
    : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
  $logoEmpresa = "../../assets/img/favicon/logo.png"; // fallback
}

// ✅ Buscar funcionários da empresa/filial específica
try {
  $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE empresa_id = :empresa_id");
  $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR); // Usa diretamente o ID selecionado
  $stmt->execute();
  $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  echo "Erro ao buscar funcionários: " . $e->getMessage();
  exit;
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
  <link href="https://cdn.jsdelivr.net/npm/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">


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
          <li class="menu-item active open">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-user-plus"></i>
              <div data-i18n="Authentications">Funcionários</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item active">
                <a href="#" class="menu-link">
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
                <a href="./ajusteFolga.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Registro de Ponto Eletrônico">Ajuste de folga</div>
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
              <li class="menu-item ">
                <a href="./frequencia.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Ajuste de Horários e Banco de Horas">Frequência</div>
                </a>
              </li>
              <li class="menu-item">
                <a href="./frequenciaGeral.php?id=<?= urlencode($idSelecionado); ?>"
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

          <?php
          $tipoLogado = $_SESSION['tipo_empresa'] ?? '';
          $idLogado = $_SESSION['empresa_id'] ?? '';

          // Se for matriz (principal), mostrar links para filial, franquia e unidade
          if ($tipoLogado === 'principal') {
          ?>
            <li class="menu-item">
              <a href="../filial/index.php?id=principal_1" class="menu-link">
                <i class="menu-icon tf-icons bx bx-building"></i>
                <div data-i18n="Authentications">Filial</div>
              </a>
            </li>
            <li class="menu-item">
              <a href="../franquia/index.php?id=principal_1" class="menu-link">
                <i class="menu-icon tf-icons bx bx-store"></i>
                <div data-i18n="Authentications">Franquias</div>
              </a>
            </li>
          <?php
          } elseif (in_array($tipoLogado, ['filial', 'franquia', 'unidade'])) {
            // Se for filial, franquia ou unidade, mostra link para matriz
          ?>
            <li class="menu-item">
              <a href="../matriz/index.php?id=<?= urlencode($idLogado) ?>" class="menu-link">
                <i class="menu-icon tf-icons bx bx-cog"></i>
                <div data-i18n="Authentications">Matriz</div>
              </a>
            </li>
          <?php
          }
          ?>
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
                <input type="text" id="searchInput" class="form-control border-0 shadow-none" placeholder="Search..."
                  aria-label="Search..." />
              </div>
            </div>
            <!-- /Search -->

            <ul class="navbar-nav flex-row align-items-center ms-auto">
              <!-- User -->
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
          <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a href="#">Funcionários</a>/</span>Adicionados
          </h4>
          <h5 class="fw-bold mt-3 mb-3 custor-font"><span class="text-muted fw-light">Visualize os Funcionário
              Adicionados da sua Empresa</span></h5>

          <div class="card mt-3">
            <h5 class="card-header">Lista de Funcionários</h5>
            <div class="table-responsive text-nowrap">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>Nome</th>
                    <th>Setor</th>
                    <th>Função</th>
                    <th>Ações</th>
                  </tr>
                </thead>
                <tbody id="tabelaFuncionarios" class="table-border-bottom-0">
                  <?php if (count($funcionarios) > 0): ?>
                    <?php foreach ($funcionarios as $funcionario): ?>
                      <tr>
                        <input type="hidden" value="<?= htmlspecialchars($funcionario['id']) ?>">
                        <td><?= htmlspecialchars($funcionario['nome']) ?></td>
                        <td><?= htmlspecialchars($funcionario['setor']) ?></td>
                        <td><?= htmlspecialchars($funcionario['cargo']) ?></td>
                        <td>
                          <!-- Editar -->
                          <button class="btn btn-link text-primary p-0" title="Editar"
                            onclick="window.location.href='editarFuncionario.php?id=<?= $funcionario['id'] ?>&idSelecionado=<?= $idSelecionado ?>'">
                            <i class="tf-icons bx bx-edit"></i>
                          </button>

                          <span class="mx-2">|</span>

                          <!-- Visualizar -->
                          <button class="btn btn-link text-muted p-0" title="Visualizar" data-bs-toggle="modal"
                            data-bs-target="#modalVisualizar_<?= $funcionario['id'] ?>">
                            <i class="fas fa-eye"></i>
                          </button>

                          <!-- Modal Visualizar Funcionário -->
                          <div class="modal fade" id="modalVisualizar_<?= $funcionario['id'] ?>" tabindex="-1"
                            aria-labelledby="modalVisualizarLabel_<?= $funcionario['id'] ?>" aria-hidden="true">
                            <div class="modal-dialog">
                              <div class="modal-content">
                                <div class="modal-header">
                                  <h5 class="modal-title" id="modalVisualizarLabel_<?= $funcionario['id'] ?>">
                                    Detalhes do Funcionário
                                  </h5>
                                  <button type="button" class="btn-close" data-bs-dismiss="modal"
                                    aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                  <div class="mb-2">
                                    <strong>Nome:</strong>
                                    <span style="word-break: break-word; white-space: pre-line; display: inline;">
                                      <?= htmlspecialchars($funcionario['nome']) ?>
                                    </span>
                                  </div>
                                  <div class="mb-2">
                                    <strong>Escala:</strong>
                                    <?= htmlspecialchars($funcionario['escala'] ?? 'Não informado') ?>
                                  </div>
                                  <div class="mb-2">
                                    <strong>Entrada:</strong>
                                    <?= !empty($funcionario['entrada']) ? date('H:i', strtotime($funcionario['entrada'])) : 'Não informado' ?>
                                  </div>
                                  <div class="mb-2">
                                    <strong>Saída Intervalo:</strong>
                                    <?= !empty($funcionario['saida_intervalo']) ? date('H:i', strtotime($funcionario['saida_intervalo'])) : 'Não informado' ?>
                                  </div>
                                  <div class="mb-2">
                                    <strong>Retorno Intervalo:</strong>
                                    <?= !empty($funcionario['retorno_intervalo']) ? date('H:i', strtotime($funcionario['retorno_intervalo'])) : 'Não informado' ?>
                                  </div>
                                  <div class="mb-2">
                                    <strong>Saída Final:</strong>
                                    <?= !empty($funcionario['saida_final']) ? date('H:i', strtotime($funcionario['saida_final'])) : 'Não informado' ?>
                                  </div>
                                </div>
                                <div class="modal-footer">
                                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                                </div>
                              </div>
                            </div>
                          </div>

                          <span class="mx-2">|</span>

                          <!-- Excluir -->
                          <button class="btn btn-link text-danger p-0" title="Excluir" data-bs-toggle="modal"
                            data-bs-target="#modalExcluir_<?= $funcionario['id'] ?>">
                            <i class="tf-icons bx bx-trash"></i>
                          </button>

                          <!-- Modal de Exclusão -->
                          <div class="modal fade" id="modalExcluir_<?= $funcionario['id'] ?>" tabindex="-1"
                            aria-labelledby="modalExcluirLabel_<?= $funcionario['id'] ?>" aria-hidden="true">
                            <div class="modal-dialog">
                              <div class="modal-content">
                                <div class="modal-header">
                                  <h5 class="modal-title" id="modalExcluirLabel_<?= $funcionario['id'] ?>">Excluir
                                    Funcionário</h5>
                                  <button type="button" class="btn-close" data-bs-dismiss="modal"
                                    aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                  <p>Tem certeza de que deseja excluir este funcionário?</p>
                                  <a href="../../assets/php/rh/excluirFuncionario.php?id=<?= $funcionario['id'] ?>&idSelecionado=<?= $idSelecionado ?>"
                                    class="btn btn-danger">Sim, excluir</a>
                                  <button type="button" class="btn btn-secondary mx-2"
                                    data-bs-dismiss="modal">Cancelar</button>
                                </div>
                              </div>
                            </div>
                          </div>
                          <!-- /Modal -->
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="4">Nenhum funcionário encontrado para essa empresa/filial.</td>
                    </tr>
                  <?php endif; ?>
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

          <!-- ✅ Script de Pesquisa e Paginação -->
          <script>
            const searchInput = document.getElementById('searchInput');
            const linhas = Array.from(document.querySelectorAll('#tabelaFuncionarios tr'));
            const rowsPerPage = 10;
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

          <!-- Adicionar novo funcionário -->
          <div id="addsetor" class="mt-3 add-category justify-content-center d-flex text-center align-items-center"
            onclick="window.location.href='adicionarFuncionario.php?id=<?= $idSelecionado ?>';"
            style="cursor: pointer;">
            <i class="tf-icons bx bx-plus me-2"></i>
            <span>Adicionar novo Funcionário</span>
          </div>

        </div>
      </div>
    </div>
  </div>

  <!-- build:js assets/vendor/js/core.js -->


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
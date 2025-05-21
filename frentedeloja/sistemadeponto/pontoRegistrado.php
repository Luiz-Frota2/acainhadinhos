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
  !isset($_SESSION['usuario_id']) // Verifica se o ID do usuário está na sessão
) {
  header("Location: ../index.php?id=$idSelecionado");
  exit;
}

// ✅ Conexão com o banco de dados
require '../../assets/php/conexao.php';

$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id = $_SESSION['usuario_id'];
$tipoUsuarioSessao = $_SESSION['nivel']; // "Admin" ou "Funcionario"

try {
  if ($tipoUsuarioSessao === 'Admin') {
    // Buscar na tabela de Admins
    $stmt = $pdo->prepare("SELECT usuario, nivel, cpf FROM contas_acesso WHERE id = :id");
  } else {
    // Buscar na tabela de Funcionários
    $stmt = $pdo->prepare("SELECT usuario, nivel, cpf FROM funcionarios_acesso WHERE id = :id");
  }

  $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
  $stmt->execute();
  $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($usuario) {
    $nomeUsuario = $usuario['usuario'];
    $tipoUsuario = ucfirst($usuario['nivel']);
    $cpfUsuario = $usuario['cpf']; // Captura o CPF do usuário logado
  } else {
    echo "<script>alert('Usuário não encontrado.'); window.location.href = './index.php?id=$idSelecionado';</script>";
    exit;
  }
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar nome e tipo do usuário: " . $e->getMessage() . "'); history.back();</script>";
  exit;
}

// ✅ Buscar nome do funcionário na tabela `funcionarios`
$nomeFuncionario = '';
if (!empty($cpfUsuario)) {
  try {
    $stmtNome = $pdo->prepare("SELECT nome FROM funcionarios WHERE cpf = :cpf");
    $stmtNome->bindParam(':cpf', $cpfUsuario, PDO::PARAM_STR);
    $stmtNome->execute();
    $funcionario = $stmtNome->fetch(PDO::FETCH_ASSOC);

    if ($funcionario && !empty($funcionario['nome'])) {
      $nomeFuncionario = $funcionario['nome'];
    } else {
      $nomeFuncionario = 'Funcionário não identificado';
    }
  } catch (PDOException $e) {
    $nomeFuncionario = 'Erro ao buscar nome';
  }
}

// ✅ Valida o tipo de empresa e o acesso permitido
if (str_starts_with($idSelecionado, 'principal_')) {
  if ($_SESSION['tipo_empresa'] !== 'principal' || $_SESSION['empresa_id'] != 1) {
    echo "<script>
            alert('Acesso negado!');
            window.location.href = '../index.php?id=$idSelecionado';
        </script>";
    exit;
  }
  $id = 1;
} elseif (str_starts_with($idSelecionado, 'filial_')) {
  $idFilial = (int) str_replace('filial_', '', $idSelecionado);
  if ($_SESSION['tipo_empresa'] !== 'filial' || $_SESSION['empresa_id'] != $idFilial) {
    echo "<script>
            alert('Acesso negado!');
            window.location.href = '../index.php?id=$idSelecionado';
        </script>";
    exit;
  }
  $id = $idFilial;
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
  echo "<script>alert('Erro ao carregar ícone da empresa: " . addslashes($e->getMessage()) . "');</script>";
}

// ✅ Consulta registros de ponto por CPF e empresa_id (tipo VARCHAR)
try {
  $stmtPonto = $pdo->prepare("
    SELECT * FROM registros_ponto 
    WHERE cpf = :cpf AND empresa_id = :empresa_id 
    ORDER BY data DESC
  ");
  $stmtPonto->bindParam(':cpf', $cpfUsuario, PDO::PARAM_STR);
  $stmtPonto->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
  $stmtPonto->execute();
  $pontos = $stmtPonto->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar registros de ponto: " . $e->getMessage() . "'); history.back();</script>";
  exit;
}

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
          <a href="./index.php2?id=<?= urlencode($idSelecionado); ?>" class="app-brand-link">

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
          <li class="menu-item">
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
              <li class="menu-item">
                <a href="./bancodeHoras.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Banco de Horas</div>
                </a>
              </li>
              <li class="menu-item active">
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
            <a href="../caixa/index.php?id=<?= urldecode($idSelecionado); ?> " class="menu-link">
              <i class="menu-icon tf-icons bx bx-barcode-reader"></i>
              <div data-i18n="Basic">Caixa</div>
            </a>
          </li>
          <li class="menu-item">
            <a href="../Delivery/index.php?id=<?= urldecode($idSelecionado); ?>" class="menu-link">
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
          <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a href="#">Sistema de
                Ponto</a>/</span>Pontos Registrados</h4>
          <h5 class="fw-bold mt-3 mb-3 custor-font"><span class="text-muted fw-light">Visualize todos os que Você
              Registrou</span></h5>

          <!-- ✅ Exibição da Tabela de Registros de Ponto -->
          <div class="card">
            <h5 class="card-header">
              <?php
              if ($_SESSION['empresa_id'] == $id) {
                if (!empty($pontos)) {
                  echo "Pontos de " . htmlspecialchars($nomeFuncionario);
                } else {
                  echo "Funcionário não identificado";
                }
              } else {
                echo "Pontos";
              }
              ?>
            </h5>

            <div class="card">
              <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                  <thead>
                    <tr>
                      <th>Data</th>
                      <th>Dia da Semana</th>
                      <th>Entrada AM</th>
                      <th>Saída AM</th>
                      <th>Entrada PM</th>
                      <th>Saída PM</th>
                      <th>Entrada Noite</th>
                      <th>Saída Noite</th>
                    </tr>
                  </thead>
                  <tbody id="tabelaFuncionarios" class="table-border-bottom-0">
                    <?php
                    $diasSemana = [
                      'Monday' => 'Segunda-feira',
                      'Tuesday' => 'Terça-feira',
                      'Wednesday' => 'Quarta-feira',
                      'Thursday' => 'Quinta-feira',
                      'Friday' => 'Sexta-feira',
                      'Saturday' => 'Sábado',
                      'Sunday' => 'Domingo'
                    ];

                    $pontosPorDia = [];
                    foreach ($pontos as $registro) {
                      $data = $registro['data'];
                      $entrada = strtotime($registro['entrada']);
                      $saida = $registro['saida'] ? strtotime($registro['saida']) : null;

                      if (!isset($pontosPorDia[$data])) {
                        $pontosPorDia[$data] = [
                          'dia_semana' => $diasSemana[(new DateTime($data))->format('l')],
                          'entrada_am' => '-',
                          'saida_am' => '-',
                          'entrada_pm' => '-',
                          'saida_pm' => '-',
                          'entrada_noite' => '-',
                          'saida_noite' => '-',
                        ];
                      }

                      if ($entrada < strtotime('12:00:00')) {
                        $pontosPorDia[$data]['entrada_am'] = date('H:i', $entrada);
                        if ($saida)
                          $pontosPorDia[$data]['saida_am'] = date('H:i', $saida);
                      } elseif ($entrada < strtotime('18:00:00')) {
                        $pontosPorDia[$data]['entrada_pm'] = date('H:i', $entrada);
                        if ($saida)
                          $pontosPorDia[$data]['saida_pm'] = date('H:i', $saida);
                      } else {
                        $pontosPorDia[$data]['entrada_noite'] = date('H:i', $entrada);
                        if ($saida)
                          $pontosPorDia[$data]['saida_noite'] = date('H:i', $saida);
                      }
                    }

                    foreach ($pontosPorDia as $data => $turnos): ?>
                      <tr>
                        <td><?php echo date('d/m/Y', strtotime($data)); ?></td>
                        <td><?php echo $turnos['dia_semana']; ?></td>
                        <td><?php echo $turnos['entrada_am']; ?></td>
                        <td><?php echo $turnos['saida_am']; ?></td>
                        <td><?php echo $turnos['entrada_pm']; ?></td>
                        <td><?php echo $turnos['saida_pm']; ?></td>
                        <td><?php echo $turnos['entrada_noite']; ?></td>
                        <td><?php echo $turnos['saida_noite']; ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <!-- ✅ Paginação -->
              <div class="d-flex justify-content-start align-items-center gap-2 m-3">
                <button class="btn btn-sm btn-outline-primary" id="prevPage">Anterior</button>
                <div id="paginacao" class="btn-group"></div>
                <button class="btn btn-sm btn-outline-primary" id="nextPage">Próximo</button>
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

            // Inicializa a tabela
            renderTable();
          </script>

        </div>
        <!-- Footer -->
        <footer class="content-footer footer bg-footer-theme text-center">
          <div class="container-xxl d-flex  py-2 flex-md-row flex-column justify-content-center">
            <div class="mb-2 mb-md-0">
              &copy;
              <script>
                document.write(new Date().getFullYear());
              </script>
              , <strong>Açainhadinhos</strong>. Todos os direitos reservados.
              Desenvolvido por <strong>CodeGeek</strong>.
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
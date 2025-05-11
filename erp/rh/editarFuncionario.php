<?php
session_start();
require_once '../../assets/php/conexao.php';

// ✅ Recupera os parâmetros da URL
$idSelecionado = $_GET['idSelecionado'] ?? '';
$funcionario_id = $_GET['id'] ?? '';

// ✅ Verifica se o usuário está logado
if (
  empty($_SESSION['usuario_logado']) ||
  empty($_SESSION['empresa_id']) ||
  empty($_SESSION['tipo_empresa']) ||
  empty($_SESSION['usuario_id'])
) {
  header("Location: ../login.php?id=$idSelecionado");
  exit;
}

// ✅ Valida o tipo de empresa e define o ID real
if (str_starts_with($idSelecionado, 'principal_')) {
  if ($_SESSION['tipo_empresa'] !== 'principal' || $_SESSION['empresa_id'] != 1) {
    echo "<script>
                alert('Acesso negado!');
                window.location.href = '../login.php?id=$idSelecionado';
              </script>";
    exit;
  }
  $empresa_id = 'principal_1';
} elseif (str_starts_with($idSelecionado, 'filial_')) {
  $idFilial = (int) str_replace('filial_', '', $idSelecionado);
  if ($_SESSION['tipo_empresa'] !== 'filial' || $_SESSION['empresa_id'] != $idFilial) {
    echo "<script>
                alert('Acesso negado!');
                window.location.href = '../login.php?id=$idSelecionado';
              </script>";
    exit;
  }
  $empresa_id = 'filial_' . $idFilial;
} else {
  echo "<script>
            alert('Empresa não identificada!');
            window.location.href = '../login.php?id=$idSelecionado';
          </script>";
  exit;
}

// ✅ Buscar imagem da empresa
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

// ✅ Dados do usuário logado
$nomeUsuario = 'Usuário';
$nivelUsuario = 'Comum';
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

// ✅ Carregando dados do funcionário
$funcionario = [];
if (!empty($funcionario_id)) {
  try {
    $stmtFuncionario = $pdo->prepare("SELECT * FROM funcionarios WHERE id = :id AND empresa_id = :empresa_id");
    $stmtFuncionario->bindParam(':id', $funcionario_id, PDO::PARAM_INT);
    $stmtFuncionario->bindParam(':empresa_id', $empresa_id, PDO::PARAM_STR);
    $stmtFuncionario->execute();
    $funcionario = $stmtFuncionario->fetch(PDO::FETCH_ASSOC);
  } catch (PDOException $e) {
    echo "Erro ao carregar funcionário: " . $e->getMessage();
    exit;
  }
}

// ✅ Carregando setores da empresa
$setores = [];
try {
  $stmtSetores = $pdo->prepare("SELECT nome FROM setores WHERE id_selecionado = :empresa_id");
  $stmtSetores->bindParam(':empresa_id', $empresa_id, PDO::PARAM_STR);
  $stmtSetores->execute();
  $setores = $stmtSetores->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  echo "Erro ao carregar setores: " . $e->getMessage();
  exit;
}

// ✅ Carregando escalas da empresa
$escalas = [];
try {
  $stmtEscalas = $pdo->prepare("SELECT nome_escala FROM escalas WHERE empresa_id = :empresa_id");
  $stmtEscalas->bindParam(':empresa_id', $empresa_id, PDO::PARAM_STR);
  $stmtEscalas->execute();
  $escalas = $stmtEscalas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  echo "Erro ao carregar escalas: " . $e->getMessage();
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

            <span class="app-brand-text demo menu-text fw-bolder ms-2" style="text-transform: none;">Açainhadinhos</span>
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
          <li class="menu-item active open">
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
            <ul class="menu-sub">
              <li class="menu-item active">
                <a href="#" class="menu-link">
                  <div data-i18n="Basic">Editar Funcionário </div>
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
          <h4 class="fw-bold mb-0"><span class="text-muted fw-light"><a
                href="./funcionarioAdicionados.php?id=<?= urlencode($idSelecionado); ?>">Funcionários</a>/</span>Adicionar
            Funcionário</h4>
          <h5 class="fw-bold mt-3 mb-3 custor-font"><span class="text-muted fw-light">Adicione o Funcionário da sua
              Empresa </span></h5>

          <!-- Basic Layout -->
          <div class="row">
            <div class="col-xl">
              <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h5 class="mb-0">Cadastro de Funcionário</h5>
                </div>
                <div class="card-body">

                  <form id="formFuncionario" autocomplete="off" action="../../assets/php/rh/atualizarFuncionario.php"
                    method="POST">
                    <input type="hidden" name="empresa_id" value="<?= htmlspecialchars($empresa_id) ?>" />
                    <input type="hidden" name="id" value="<?= htmlspecialchars($funcionario['id']) ?>" />

                    <!-- Etapa 1 - Dados Pessoais -->
                    <div class="step step-1">
                      <h6>Dados Pessoais</h6>
                      <div class="row">
                        <div class="mb-3 col-md-6">
                          <label class="form-label">Nome Completo</label>
                          <input type="text" class="form-control" name="nome"
                            value="<?= htmlspecialchars($funcionario['nome']) ?>" required />
                        </div>
                        <div class="mb-3 col-md-6">
                          <label class="form-label">Data de Nascimento</label>
                          <input type="date" class="form-control" name="data_nascimento"
                            value="<?= htmlspecialchars($funcionario['data_nascimento']) ?>" required />
                        </div>
                      </div>
                      <div class="row">
                        <div class="mb-3 col-md-6">
                          <label class="form-label">CPF</label>
                          <input type="text" class="form-control" name="cpf"
                            value="<?= htmlspecialchars($funcionario['cpf']) ?>" required />
                        </div>
                        <div class="mb-3 col-md-6">
                          <label class="form-label">RG</label>
                          <input type="text" class="form-control" name="rg"
                            value="<?= htmlspecialchars($funcionario['rg']) ?>" required />
                        </div>
                      </div>
                      <div class="d-flex justify-content-end mt-3">
                        <button type="button" class="btn btn-primary next-step col-md-3">Próximo</button>
                      </div>
                    </div>

                    <!-- Etapa 2 - Informações Profissionais -->
                    <div class="step step-2 d-none">
                      <h6>Informações Profissionais</h6>

                      <div class="row">
                        <div class="col-12 col-md-6 mb-3">
                          <label class="form-label" for="cargo">Cargo</label>
                          <input type="text" class="form-control input-custom" name="cargo" id="cargo"
                            value="<?= htmlspecialchars($funcionario['cargo']) ?>" placeholder="Informe o cargo"
                            required />
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                          <label class="form-label" for="setor">Setor</label>
                          <select class="form-control input-custom" name="setor" id="setor" required>
                            <option value="" disabled <?= empty($funcionario['setor']) ? 'selected' : '' ?>>Selecione o
                              Setor</option>
                            <?php foreach ($setores as $setor): ?>
                              <option value="<?= htmlspecialchars($setor['nome']) ?>"
                                <?= $funcionario['setor'] == $setor['nome'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($setor['nome']) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                      </div>

                      <div class="row">
                        <div class="col-12 col-md-6 mb-3">
                          <label class="form-label" for="salario">Salário</label>
                          <input type="text" class="form-control input-custom" name="salario" id="salario"
                            value="<?= htmlspecialchars($funcionario['salario']) ?>" placeholder="Informe o salário"
                            required />
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                          <label class="form-label" for="escala">Escala</label>
                          <select class="form-control input-custom" name="escala" id="escala" required>
                            <option value="" disabled <?= empty($funcionario['escala']) ? 'selected' : '' ?>>Selecione a
                              escala</option>
                            <?php foreach ($escalas as $escala): ?>
                              <option value="<?= htmlspecialchars($escala['nome_escala']) ?>"
                                <?= $funcionario['escala'] == $escala['nome_escala'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($escala['nome_escala']) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                      </div>

                      <div class="row">
                        <div class="col-md-6 col-12 mb-3">
                          <label for="dia_inicio" class="form-label">De</label>
                          <select id="dia_inicio" name="dia_inicio" class="form-control" required>
                            <?php
                            $dias = ["domingo", "segunda", "terca", "quarta", "quinta", "sexta", "sabado"];
                            foreach ($dias as $dia) {
                              $selected = $funcionario['dia_inicio'] == $dia ? 'selected' : '';
                              echo "<option value=\"$dia\" $selected>" . ucfirst($dia) . "</option>";
                            }
                            ?>
                          </select>
                        </div>
                        <div class="col-md-6 col-12 mb-3">
                          <label for="dia_termino" class="form-label">Até</label>
                          <select id="dia_termino" name="dia_termino" class="form-control" required>
                            <?php
                            foreach ($dias as $dia) {
                              $selected = $funcionario['dia_termino'] == $dia ? 'selected' : '';
                              echo "<option value=\"$dia\" $selected>" . ucfirst($dia) . "</option>";
                            }
                            ?>
                          </select>
                        </div>
                      </div>

                      <div class="row">
                        <div class="col-md-6 col-12 mb-3">
                          <label for="hora_entrada_primeiro_turno" class="form-label">Hora de Entrada (1° Turno)</label>
                          <input type="time" id="hora_entrada_primeiro_turno" name="hora_entrada_primeiro_turno"
                            class="form-control"
                            value="<?= htmlspecialchars($funcionario['hora_entrada_primeiro_turno'] ?? '') ?>" />
                        </div>
                        <div class="col-md-6 col-12 mb-3">
                          <label for="hora_saida_primeiro_turno" class="form-label">Hora de Saída (1° Turno)</label>
                          <input type="time" id="hora_saida_primeiro_turno" name="hora_saida_primeiro_turno"
                            class="form-control"
                            value="<?= htmlspecialchars($funcionario['hora_saida_primeiro_turno'] ?? '') ?>" />
                        </div>
                      </div>

                      <div class="row">
                        <div class="col-md-6 col-12 mb-3">
                          <label for="hora_entrada_segundo_turno" class="form-label">Hora de Entrada (2° Turno)</label>
                          <input type="time" id="hora_entrada_segundo_turno" name="hora_entrada_segundo_turno"
                            class="form-control"
                            value="<?= htmlspecialchars($funcionario['hora_entrada_segundo_turno'] ?? '') ?>" />
                        </div>
                        <div class="col-md-6 col-12 mb-3">
                          <label for="hora_saida_segundo_turno" class="form-label">Hora de Saída (2° Turno)</label>
                          <input type="time" id="hora_saida_segundo_turno" name="hora_saida_segundo_turno"
                            class="form-control"
                            value="<?= htmlspecialchars($funcionario['hora_saida_segundo_turno'] ?? '') ?>" />
                        </div>
                      </div>

                      <div class="row mt-3 justify-content-between">
                        <div class="col-6 col-md-3 d-grid mb-3">
                          <button type="button" class="btn btn-secondary prev-step">Voltar</button>
                        </div>
                        <div class="col-6 col-md-3 d-grid mb-3 ms-md-auto">
                          <button type="button" class="btn btn-primary next-step">Próximo</button>
                        </div>
                      </div>
                    </div>

                    <!-- Etapa 3 - Contato e Endereço -->
                    <div class="step step-3 d-none">
                      <h6>Contato e Endereço</h6>
                      <div class="row">
                        <div class="mb-3 col-md-6">
                          <label class="form-label">E-mail</label>
                          <input type="email" class="form-control" name="email"
                            value="<?= htmlspecialchars($funcionario['email']) ?>" required />
                        </div>
                        <div class="mb-3 col-md-6">
                          <label class="form-label">Telefone</label>
                          <input type="tel" class="form-control" name="telefone"
                            value="<?= htmlspecialchars($funcionario['telefone']) ?>" required />
                        </div>
                      </div>
                      <div class="row">
                        <div class="mb-3 col-md-6">
                          <label class="form-label">Endereço</label>
                          <input type="text" class="form-control" name="endereco"
                            value="<?= htmlspecialchars($funcionario['endereco']) ?>" required />
                        </div>
                        <div class="mb-3 col-md-6">
                          <label class="form-label">Cidade</label>
                          <input type="text" class="form-control" name="cidade"
                            value="<?= htmlspecialchars($funcionario['cidade']) ?>" required />
                        </div>
                      </div>
                      <div class="row justify-content-between mt-3">
                        <div class="col-6 col-md-3 d-grid mb-3">
                          <button type="button" class="btn btn-secondary prev-step">Voltar</button>
                        </div>
                        <div class="col-6 col-md-3 d-grid mb-3 ms-md-auto">
                          <button type="submit" class="btn btn-primary">Atualizar</button>
                        </div>
                      </div>
                    </div>
                  </form>

                  <script>
                    document.addEventListener("DOMContentLoaded", function() {
                      const nextButtons = document.querySelectorAll('.next-step');
                      const prevButtons = document.querySelectorAll('.prev-step');
                      const steps = document.querySelectorAll('.step');
                      let currentStep = 0;

                      function showStep(stepIndex) {
                        steps.forEach((step, index) => {
                          step.classList.toggle('d-none', index !== stepIndex);
                        });
                      }

                      showStep(currentStep);

                      nextButtons.forEach(button => {
                        button.addEventListener('click', function() {
                          if (currentStep < steps.length - 1) {
                            currentStep++;
                            showStep(currentStep);
                          }
                        });
                      });

                      prevButtons.forEach(button => {
                        button.addEventListener('click', function() {
                          if (currentStep > 0) {
                            currentStep--;
                            showStep(currentStep);
                          }
                        });
                      });
                    });
                  </script>

                </div>
              </div>
            </div>
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
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

// ✅ Iniciar a conexão para pegar a imagem da empresa
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

// ✅ Buscar escalas e setores com base no idSelecionado
$escalas = [];
$setores = [];

try {
  // Buscar escalas filtradas pelo idSelecionado (empresa_id)
  $stmt = $pdo->prepare("SELECT nome_escala, data_escala FROM escalas WHERE empresa_id = :empresa_id");
  $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
  $stmt->execute();
  $escalas = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Buscar setores filtrados pelo idSelecionado (id_selecionado)
  $stmt = $pdo->prepare("SELECT nome, gerente FROM setores WHERE id_selecionado = :id_selecionado");
  $stmt->bindParam(':id_selecionado', $idSelecionado, PDO::PARAM_STR);
  $stmt->execute();
  $setores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  echo "Erro ao buscar escalas ou setores: " . $e->getMessage();
  exit;
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
                  <div data-i18n="Basic">Adicionar Funcionário </div>
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
                  <div data-i18n="Escalas e Configuração">Escalas Adicionadas</div>
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

                  <form id="formFuncionario" autocomplete="off" action="../../assets/php/rh/adicionarFuncionario.php"
                    method="POST" enctype="multipart/form-data">
                    <!-- Campo oculto para ID da empresa -->
                    <input type="hidden" name="empresa_id" value="<?= htmlspecialchars($idSelecionado) ?>" />

                    <!-- Etapa 1 - Dados Pessoais -->
                    <div class="step step-1">
                      <h6>Dados Pessoais</h6>
                      <div class="row">
                        <div class="mb-3 col-12 col-md-6">
                          <label class="form-label" for="nome">Nome Completo</label>
                          <input type="text" class="form-control input-custom" name="nome" id="nome"
                            placeholder="Informe o nome completo" required />
                        </div>
                        <div class="mb-3 col-12 col-md-6">
                          <label class="form-label" for="data_nascimento">Data de Nascimento</label>
                          <input type="date" class="form-control input-custom" name="data_nascimento"
                            id="data_nascimento" required />
                        </div>
                      </div>

                      <div class="row">
                        <div class="mb-3 col-12 col-md-6">
                          <label class="form-label" for="cpf">CPF</label>
                          <input type="text" class="form-control input-custom" name="cpf" id="cpf"
                            placeholder="Informe o CPF" required />
                        </div>
                        <div class="mb-3 col-12 col-md-6">
                          <label class="form-label" for="rg">RG</label>
                          <input type="text" class="form-control input-custom" name="rg" id="rg"
                            placeholder="Informe o RG"  />
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
                            placeholder="Informe o cargo" required />
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                          <label class="form-label" for="setor">Setor</label>
                          <select class="form-control input-custom" name="setor" id="setor" required>
                            <option value="" selected>Selecione o Setor</option>
                            <?php foreach ($setores as $setor): ?>
                              <option value="<?= $setor['nome'] ?>"><?= htmlspecialchars($setor['nome']) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                      </div>

                      <div class="row">
                        <div class="col-12 col-md-6 mb-3">
                          <label class="form-label" for="salario">Salário</label>
                          <input type="text" class="form-control input-custom" name="salario" id="salario"
                            placeholder="Informe o salário" required />
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                          <label class="form-label" for="escala">Escala</label>
                          <select class="form-control input-custom" name="escala" id="escala" required>
                            <option value="" selected>Selecione a escala</option>
                            <?php foreach ($escalas as $escala): ?>
                              <option value="<?= $escala['nome_escala'] ?>"><?= htmlspecialchars($escala['nome_escala']) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                      </div>
                      <div class="row">
                        <div class="col-md-6 col-12 mb-3">
                          <label for="dia_inicio" class="form-label">De</label>
                          <select id="dia_inicio" name="dia_inicio" class="form-control">
                            <option value="">Selecione um Dia</option>
                            <option value="domingo">Domingo</option>
                            <option value="segunda">Segunda-feira</option>
                            <option value="terca">Terça-feira</option>
                            <option value="quarta">Quarta-feira</option>
                            <option value="quinta">Quinta-feira</option>
                            <option value="sexta">Sexta-feira</option>
                            <option value="sabado">Sábado</option>
                          </select>
                        </div>
                        <div class="col-md-6 col-12 mb-3">
                          <label for="dia_termino" class="form-label">Até</label>
                          <select id="dia_termino" name="dia_termino" class="form-control">
                            <option value="">Selecione um Dia</option>
                            <option value="domingo">Domingo</option>
                            <option value="segunda">Segunda-feira</option>
                            <option value="terca">Terça-feira</option>
                            <option value="quarta">Quarta-feira</option>
                            <option value="quinta">Quinta-feira</option>
                            <option value="sexta">Sexta-feira</option>
                            <option value="sabado">Sábado</option>
                          </select>
                        </div>
                      </div>

                      <div class="row">
                        <div class="col-md-6 col-12 mb-3">
                          <label for="entrada" class="form-label">Início / Entrada</label>
                          <input type="time" id="entrada" name="entrada" class="form-control" />
                        </div>
                        <div class="col-md-6 col-12 mb-3">
                          <label for="saida_intervalo" class="form-label">Saída para Intervalo</label>
                          <input type="time" id="saida_intervalo" name="saida_intervalo" class="form-control" />
                        </div>
                      </div>

                      <div class="row">
                        <div class="col-md-6 col-12 mb-3">
                          <label for="retorno_intervalo" class="form-label">Retorno do Intervalo</label>
                          <input type="time" id="retorno_intervalo" name="retorno_intervalo" class="form-control" />
                        </div>
                        <div class="col-md-6 col-12 mb-3">
                          <label for="saida_final" class="form-label">Fim / Saída</label>
                          <input type="time" id="saida_final" name="saida_final" class="form-control" />
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
                        <div class="mb-3 col-12 col-md-6">
                          <label class="form-label" for="email">E-mail</label>
                          <input type="email" class="form-control input-custom" name="email" id="email"
                            placeholder="Informe o e-mail"  />
                        </div>
                        <div class="mb-3 col-12 col-md-6">
                          <label class="form-label" for="telefone">Telefone</label>
                          <input type="tel" class="form-control input-custom" name="telefone" id="telefone"
                            placeholder="Informe o telefone"  />
                        </div>
                      </div>

                      <div class="row">
                        <div class="mb-3 col-12 col-md-6">
                          <label class="form-label" for="endereco">Endereço</label>
                          <input type="text" class="form-control input-custom" name="endereco" id="endereco"
                            placeholder="Informe o endereço"  />
                        </div>
                        <div class="mb-3 col-12 col-md-6">
                          <label class="form-label" for="cidade">Cidade</label>
                          <input type="text" class="form-control input-custom" name="cidade" id="cidade"
                            placeholder="Informe a cidade"  />
                        </div>
                      </div>

                      <div class="row mt-3 justify-content-between">
                        <div class="col-6 col-md-3 d-grid mb-3">
                          <button type="button" class="btn btn-secondary prev-step">Voltar</button>
                        </div>
                        <div class="col-6 col-md-3 d-grid mb-3 ms-md-auto">
                          <button type="submit" class="btn btn-primary">Finalizar</button>
                        </div>
                      </div>
                    </div>
                  </form>

                  <script>
                    // Espera o DOM carregar completamente antes de executar o script
                    document.addEventListener("DOMContentLoaded", function() {
                      // Seleciona os botões de navegação entre etapas
                      const nextButtons = document.querySelectorAll('.next-step');
                      const prevButtons = document.querySelectorAll('.prev-step');
                      const steps = document.querySelectorAll('.step'); // Todas as etapas do formulário

                      let currentStep = 0; // Etapa inicial

                      // Função para mostrar a etapa atual e esconder as outras
                      function showStep(stepIndex) {
                        steps.forEach((step, index) => {
                          if (index === stepIndex) {
                            step.classList.remove('d-none');
                          } else {
                            step.classList.add('d-none');
                          }
                        });
                      }

                      // Mostrar a etapa inicial
                      showStep(currentStep);

                      // Navegar para a próxima etapa
                      nextButtons.forEach(button => {
                        button.addEventListener('click', function() {
                          if (currentStep < steps.length - 1) {
                            currentStep++;
                            showStep(currentStep);
                          }
                        });
                      });

                      // Navegar para a etapa anterior
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
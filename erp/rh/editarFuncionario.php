<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../assets/php/conexao.php';

// ✅ Parâmetros
$idSelecionado  = $_GET['idSelecionado'] ?? '';
$funcionario_id = $_GET['id'] ?? '';

if (!$idSelecionado) {
  header("Location: .././login.php");
  exit;
}

// ✅ Verifica login
if (
  !isset($_SESSION['usuario_logado']) ||
  !isset($_SESSION['empresa_id']) ||
  !isset($_SESSION['tipo_empresa']) ||
  !isset($_SESSION['usuario_id'])
) {
  header("Location: .././login.php?id=" . urlencode($idSelecionado));
  exit;
}

// ✅ Controle de acesso por tipo
$acessoPermitido  = false;
$idEmpresaSession = $_SESSION['empresa_id'];
$tipoSession      = $_SESSION['tipo_empresa'];

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
          window.location.href = '.././login.php?id=" . addslashes($idSelecionado) . "';
        </script>";
  exit;
}

// ✅ Buscar usuário logado
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id  = $_SESSION['usuario_id'];

try {
  $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
  $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
  $stmt->execute();
  $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($usuario) {
    $nomeUsuario = $usuario['usuario'];
    $tipoUsuario = ucfirst($usuario['nivel']);
  } else {
    echo "<script>alert('Usuário não encontrado.'); window.location.href = '.././login.php?id=" . addslashes($idSelecionado) . "';</script>";
    exit;
  }
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar usuário: " . addslashes($e->getMessage()) . "'); history.back();</script>";
  exit;
}

// ✅ Logo da empresa
try {
  $stmt = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id LIMIT 1");
  $stmt->bindParam(':id', $idSelecionado, PDO::PARAM_STR);
  $stmt->execute();
  $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);
  $logoEmpresa = !empty($empresaSobre['imagem'])
    ? "../../assets/img/empresa/" . $empresaSobre['imagem']
    : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
  $logoEmpresa = "../../assets/img/favicon/logo.png";
}

// ✅ Funcionário (edição)
$funcionario = [];
if (!empty($funcionario_id)) {
  try {
    $stmtFuncionario = $pdo->prepare("SELECT * FROM funcionarios WHERE id = :id AND empresa_id = :empresa_id");
    $stmtFuncionario->bindParam(':id', $funcionario_id, PDO::PARAM_INT);
    $stmtFuncionario->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
    $stmtFuncionario->execute();
    $funcionario = $stmtFuncionario->fetch(PDO::FETCH_ASSOC) ?: [];
  } catch (PDOException $e) {
    echo "Erro ao carregar funcionário: " . htmlspecialchars($e->getMessage());
    exit;
  }
}

// ✅ Setores
$setores = [];
try {
  $stmtSetores = $pdo->prepare("SELECT nome FROM setores WHERE id_selecionado = :empresa_id");
  $stmtSetores->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
  $stmtSetores->execute();
  $setores = $stmtSetores->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  echo "Erro ao carregar setores: " . htmlspecialchars($e->getMessage());
  exit;
}

// ✅ Escalas
$escalas = [];
$escala_funcionario = $funcionario['escala'] ?? null;

try {
  $stmtEscalas = $pdo->prepare("SELECT nome_escala FROM escalas WHERE empresa_id = :empresa_id");
  $stmtEscalas->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
  $stmtEscalas->execute();
  $escalas = $stmtEscalas->fetchAll(PDO::FETCH_ASSOC);

  if ($escala_funcionario) {
    $existe = false;
    foreach ($escalas as $e) {
      if (($e['nome_escala'] ?? '') === $escala_funcionario) {
        $existe = true;
        break;
      }
    }
    if (!$existe) {
      $escalas[] = ['nome_escala' => $escala_funcionario];
    }
  }
} catch (PDOException $e) {
  echo "Erro ao carregar escalas: " . htmlspecialchars($e->getMessage());
  exit;
}

// ✅ Definições do formulário
$empresa_id = $idSelecionado;
$ehEdicao   = !empty($funcionario) && !empty($funcionario['id']);
$formAction = $ehEdicao
  ? '../../assets/php/rh/atualizarFuncionario.php'
  : '../../assets/php/rh/cadastrarFuncionario.php'; // ajuste se usar outro arquivo de insert

// Helpers de saída segura
function h($v)
{
  return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}
$dias = ["domingo", "segunda", "terca", "quarta", "quinta", "sexta", "sabado"];
?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
  <title>ERP - Recursos Humanos</title>
  <meta name="description" content="" />
  <link rel="icon" type="image/x-icon" href="<?= h($logoEmpresa) ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
  <link rel="stylesheet" href="../../assets/vendor/css/core.css" class="template-customizer-core-css" />
  <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
  <link rel="stylesheet" href="../../assets/css/demo.css" />
  <link rel="stylesheet" href="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="../../assets/vendor/libs/apex-charts/apex-charts.css" />
  <script src="../../assets/vendor/js/helpers.js"></script>
  <script src="../../assets/js/config.js"></script>
  <style>
    .d-none {
      display: none !important
    }
  </style>
</head>

<body>
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
      <!-- Menu -->
      <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
        <div class="app-brand demo">
          <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="app-brand-link">
            <span class="app-brand-text demo menu-text fw-bolder ms-2" style="text-transform: capitalize;">Açaínhadinhos</span>
          </a>
          <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
            <i class="bx bx-chevron-left bx-sm align-middle"></i>
          </a>
        </div>
        <div class="menu-inner-shadow"></div>

        <ul class="menu-inner py-1">
          <li class="menu-item">
            <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
              <i class="menu-icon tf-icons bx bx-home-circle"></i>
              <div data-i18n="Analytics">Dashboard</div>
            </a>
          </li>

          <li class="menu-header small text-uppercase"><span class="menu-header-text">Recursos Humanos</span></li>

          <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-buildings"></i>
              <div data-i18n="Authentications">Setores</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item"><a href="./setoresAdicionados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Adicionados</div>
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
              <li class="menu-item"><a href="./funcionarioAdicionados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Adicionados</div>
                </a>
              </li>
            </ul>
            <ul class="menu-sub">
              <li class="menu-item active"><a href="#" class="menu-link">
                  <div><?= $ehEdicao ? 'Editar Funcionário' : 'Adicionar Funcionário' ?></div>
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
              <li class="menu-item"><a href="./escalaAdicionadas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Escalas Adicionadas</div>
                </a>
              </li>
              <li class="menu-item"><a href="./adicionarPonto.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Adicionar Ponto</div>
                </a>
              </li>
              <li class="menu-item"><a href="./ajustePonto.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Ajuste de Ponto</div>
                </a>
              </li>
              <li class="menu-item"><a href="./ajusteFolga.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Ajuste de Folga</div>
                </a>
              </li>
              <li class="menu-item"><a href="./atestadosFuncionarios.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Atestados</div>
                </a>
              </li>
            </ul>
          </li>

          <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-trending-up"></i>
              <div data-i18n="Relatórios">Relatórios</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item"><a href="./relatorio.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Visualização Geral</div>
                </a>
              </li>
              <li class="menu-item"><a href="./bancoHoras.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Banco de Horas</div>
                </a>
              </li>
              <li class="menu-item"><a href="./frequencia.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Frequência</div>
                </a>
              </li>
              <li class="menu-item"><a href="./frequenciaGeral.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div>Frequência Geral</div>
                </a>
              </li>
            </ul>
          </li>

          <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>

          <li class="menu-item"><a href="../financas/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-dollar"></i>
              <div>Finanças</div>
            </a>
          </li>

          <li class="menu-item"><a href="../pdv/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-desktop"></i>
              <div>PDV</div>
            </a>
          </li>

          <li class="menu-item"><a href="../empresa/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-briefcase"></i>
              <div>Empresa</div>
            </a>
          </li>

          <li class="menu-item"><a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-box"></i>
              <div>Estoque</div>
            </a>
          </li>

          <?php
          $tipoLogado = $_SESSION['tipo_empresa'] ?? '';
          $idLogado = $_SESSION['empresa_id'] ?? '';
          if ($tipoLogado === 'principal') { ?>
            <li class="menu-item"><a href="../filial/index.php?id=principal_1" class="menu-link"><i class="menu-icon tf-icons bx bx-building"></i>
                <div>Filial</div>
              </a></li>
            <li class="menu-item"><a href="../franquia/index.php?id=principal_1" class="menu-link"><i class="menu-icon tf-icons bx bx-store"></i>
                <div>Franquias</div>
              </a></li>
          <?php } elseif (in_array($tipoLogado, ['filial', 'franquia', 'unidade'])) { ?>
            <li class="menu-item"><a href="../matriz/index.php?id=<?= urlencode($idLogado) ?>" class="menu-link"><i class="menu-icon tf-icons bx bx-cog"></i>
                <div>Matriz</div>
              </a></li>
          <?php } ?>

          <li class="menu-item"><a href="../usuarios/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link "><i class="menu-icon tf-icons bx bx-group"></i>
              <div>Usuários</div>
            </a></li>
          <li class="menu-item"><a href="https://wa.me/92991515710" target="_blank" class="menu-link"><i class="menu-icon tf-icons bx bx-support"></i>
              <div>Suporte</div>
            </a></li>
        </ul>
      </aside>
      <!-- / Menu -->

      <!-- Layout container -->
      <div class="layout-page">
        <!-- Navbar -->
        <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
          <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
            <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)"><i class="bx bx-menu bx-sm"></i></a>
          </div>
          <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
            <ul class="navbar-nav flex-row align-items-center ms-auto">
              <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false">
                  <div class="avatar avatar-online"><img src="<?= h($logoEmpresa) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" /></div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li>
                    <a class="dropdown-item" href="#">
                      <div class="d-flex">
                        <div class="flex-shrink-0 me-3">
                          <div class="avatar avatar-online"><img src="<?= h($logoEmpresa) ?>" alt="Avatar" class="w-px-40 h-auto rounded-circle" /></div>
                        </div>
                        <div class="flex-grow-1">
                          <span class="fw-semibold d-block"><?= h($nomeUsuario); ?></span>
                          <small class="text-muted"><?= h($tipoUsuario); ?></small>
                        </div>
                      </div>
                    </a>
                  </li>
                  <li>
                    <div class="dropdown-divider"></div>
                  </li>
                  <li><a class="dropdown-item" href="./contaUsuario.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-user me-2"></i><span class="align-middle">Minha Conta</span></a></li>
                  <li><a class="dropdown-item" href="#"><i class="bx bx-cog me-2"></i><span class="align-middle">Configurações</span></a></li>
                  <li>
                    <div class="dropdown-divider"></div>
                  </li>
                  <li><a class="dropdown-item" href="../logout.php?id=<?= urlencode($idSelecionado); ?>"><i class="bx bx-power-off me-2"></i><span class="align-middle">Sair</span></a></li>
                </ul>
              </li>
            </ul>
          </div>
        </nav>
        <!-- / Navbar -->

        <div class="container-xxl flex-grow-1 container-p-y">
          <h4 class="fw-bold mb-0">
            <span class="text-muted fw-light">
              <a href="./funcionarioAdicionados.php?id=<?= urlencode($idSelecionado); ?>">Funcionários</a> /
            </span>
            <?= $ehEdicao ? 'Editar Funcionário' : 'Adicionar Funcionário' ?>
          </h4>
          <h5 class="fw-bold mt-3 mb-3 custor-font">
            <span class="text-muted fw-light">Preencha os dados do funcionário</span>
          </h5>

          <div class="row">
            <div class="col-xl">
              <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h5 class="mb-0"><?= $ehEdicao ? 'Edição' : 'Cadastro' ?> de Funcionário</h5>
                </div>
                <div class="card-body">

                  <form id="formFuncionario" autocomplete="off" action="<?= h($formAction) ?>" method="POST">
                    <input type="hidden" name="empresa_id" value="<?= h($empresa_id) ?>" />
                    <input type="hidden" name="id" value="<?= h($funcionario['id'] ?? '') ?>" />

                    <!-- Etapa 1 - Dados Pessoais -->
                    <div class="step step-1">
                      <h6>Dados Pessoais</h6>
                      <div class="row">
                        <div class="mb-3 col-md-6">
                          <label class="form-label">Nome Completo</label>
                          <input type="text" class="form-control" name="nome" value="<?= h($funcionario['nome'] ?? '') ?>" required />
                        </div>
                        <div class="mb-3 col-md-6">
                          <label class="form-label">Data de Nascimento</label>
                          <input type="date" class="form-control" name="data_nascimento" value="<?= h($funcionario['data_nascimento'] ?? '') ?>" required />
                        </div>
                      </div>
                      <div class="row">
                        <div class="mb-3 col-md-6">
                          <label class="form-label">CPF</label>
                          <input type="text" class="form-control" name="cpf" id="cpf" value="<?= h($funcionario['cpf'] ?? '') ?>" required maxlength="14" />
                        </div>
                        <div class="mb-3 col-md-6">
                          <label class="form-label">RG</label>
                          <input type="text" class="form-control" name="rg" value="<?= h($funcionario['rg'] ?? '') ?>" />
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
                          <label class="form-label" for="data_admissao">Data de Admissão</label>
                          <input type="date" class="form-control input-custom" name="data_admissao" id="data_admissao" value="<?= h($funcionario['data_admissao'] ?? '') ?>" />
                        </div>
                      </div>

                      <div class="row">
                        <div class="col-12 col-md-6 mb-3">
                          <label class="form-label" for="cargo">Cargo</label>
                          <input type="text" class="form-control input-custom" name="cargo" id="cargo" value="<?= h($funcionario['cargo'] ?? '') ?>" placeholder="Informe o cargo" required />
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                          <label class="form-label" for="setor">Setor</label>
                          <select class="form-control input-custom" name="setor" id="setor" required>
                            <option value="" disabled <?= empty($funcionario['setor']) ? 'selected' : '' ?>>Selecione o Setor</option>
                            <?php foreach ($setores as $setor):
                              $nomeSetor = $setor['nome'] ?? '';
                              $sel = (!empty($funcionario['setor']) && $funcionario['setor'] === $nomeSetor) ? 'selected' : '';
                            ?>
                              <option value="<?= h($nomeSetor) ?>" <?= $sel ?>><?= h($nomeSetor) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                      </div>

                      <div class="row">
                        <div class="col-12 col-md-6 mb-3">
                          <label class="form-label" for="salario">Salário</label>
                          <input type="text" class="form-control input-custom" name="salario" id="salario" value="<?= h($funcionario['salario'] ?? '') ?>" placeholder="Informe o salário" required />
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                          <label class="form-label" for="pis">Número do PIS</label>
                          <input type="text" class="form-control input-custom" name="pis" id="pis" value="<?= h($funcionario['pis'] ?? '') ?>" placeholder="Informe o PIS" />
                        </div>
                      </div>

                      <div class="row">
                        <div class="col-12 col-md-6 mb-3">
                          <label class="form-label" for="matricula">N° Matrícula</label>
                          <input type="text" class="form-control input-custom" name="matricula" id="matricula" placeholder="Informe o número de matrícula" value="<?= h($funcionario['matricula'] ?? '') ?>" />
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                          <label class="form-label" for="escala">Escala</label>
                          <select class="form-control input-custom" name="escala" id="escala" required>
                            <option value="" disabled <?= empty($funcionario['escala']) ? 'selected' : '' ?>>Selecione a escala</option>
                            <?php foreach ($escalas as $escala):
                              $nomeEsc = $escala['nome_escala'] ?? '';
                              $sel = (!empty($funcionario['escala']) && $funcionario['escala'] === $nomeEsc) ? 'selected' : '';
                            ?>
                              <option value="<?= h($nomeEsc) ?>" <?= $sel ?>><?= h($nomeEsc) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                      </div>

                      <div class="row">
                        <div class="col-md-6 col-12 mb-3">
                          <label for="dia_inicio" class="form-label">Início</label>
                          <select id="dia_inicio" name="dia_inicio" class="form-control" required>
                            <option value="" disabled <?= empty($funcionario['dia_inicio']) ? 'selected' : '' ?>>Selecione um dia</option>
                            <?php foreach ($dias as $d):
                              $sel = (!empty($funcionario['dia_inicio']) && $funcionario['dia_inicio'] === $d) ? 'selected' : '';
                            ?>
                              <option value="<?= h($d) ?>" <?= $sel ?>><?= ucfirst($d) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="col-md-6 col-12 mb-3">
                          <label for="dia_folga" class="form-label">Folga da Semana</label>
                          <select id="dia_folga" name="dia_folga" class="form-control" required>
                            <option value="" disabled <?= empty($funcionario['dia_folga']) ? 'selected' : '' ?>>Selecione um dia</option>
                            <?php foreach ($dias as $d):
                              $sel = (!empty($funcionario['dia_folga']) && $funcionario['dia_folga'] === $d) ? 'selected' : '';
                            ?>
                              <option value="<?= h($d) ?>" <?= $sel ?>><?= ucfirst($d) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                      </div>

                      <div class="row">
                        <div class="col-md-6 col-12 mb-3">
                          <label for="entrada" class="form-label">Início / Entrada</label>
                          <input type="time" id="entrada" name="entrada" class="form-control" value="<?= h($funcionario['entrada'] ?? '') ?>" />
                        </div>
                        <div class="col-md-6 col-12 mb-3">
                          <label for="saida_intervalo" class="form-label">Saída para Intervalo</label>
                          <input type="time" id="saida_intervalo" name="saida_intervalo" class="form-control" value="<?= h($funcionario['saida_intervalo'] ?? '') ?>" />
                        </div>
                      </div>

                      <div class="row">
                        <div class="col-md-6 col-12 mb-3">
                          <label for="retorno_intervalo" class="form-label">Retorno do Intervalo</label>
                          <input type="time" id="retorno_intervalo" name="retorno_intervalo" class="form-control" value="<?= h($funcionario['retorno_intervalo'] ?? '') ?>" />
                        </div>
                        <div class="col-md-6 col-12 mb-3">
                          <label for="saida_final" class="form-label">Fim / Saída</label>
                          <input type="time" id="saida_final" name="saida_final" class="form-control" value="<?= h($funcionario['saida_final'] ?? '') ?>" />
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
                          <input type="email" class="form-control" name="email" value="<?= h($funcionario['email'] ?? '') ?>" />
                        </div>
                        <div class="mb-3 col-md-6">
                          <label class="form-label">Telefone</label>
                          <input type="tel" class="form-control" name="telefone" value="<?= h($funcionario['telefone'] ?? '') ?>" />
                        </div>
                      </div>
                      <div class="row">
                        <div class="mb-3 col-md-6">
                          <label class="form-label">Endereço</label>
                          <input type="text" class="form-control" name="endereco" value="<?= h($funcionario['endereco'] ?? '') ?>" />
                        </div>
                        <div class="mb-3 col-md-6">
                          <label class="form-label">Cidade</label>
                          <input type="text" class="form-control" name="cidade" value="<?= h($funcionario['cidade'] ?? '') ?>" />
                        </div>
                      </div>
                      <div class="row justify-content-between mt-3">
                        <div class="col-6 col-md-3 d-grid mb-3">
                          <button type="button" class="btn btn-secondary prev-step">Voltar</button>
                        </div>
                        <div class="col-6 col-md-3 d-grid mb-3 ms-md-auto">
                          <button type="submit" class="btn btn-primary"><?= $ehEdicao ? 'Atualizar' : 'Cadastrar' ?></button>
                        </div>
                      </div>
                    </div>
                  </form>

                  <!-- Scripts do formulário em etapas -->
                  <script>
                    document.addEventListener("DOMContentLoaded", function() {
                      // Máscara CPF
                      const cpfInput = document.getElementById('cpf');
                      if (cpfInput) {
                        cpfInput.addEventListener('input', function() {
                          let v = cpfInput.value.replace(/\D/g, '');
                          if (v.length > 11) v = v.slice(0, 11);
                          v = v.replace(/(\d{3})(\d)/, '$1.$2');
                          v = v.replace(/(\d{3})(\d)/, '$1.$2');
                          v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                          cpfInput.value = v;
                        });
                      }

                      const steps = [...document.querySelectorAll('.step')];
                      const nextButtons = document.querySelectorAll('.next-step');
                      const prevButtons = document.querySelectorAll('.prev-step');
                      let currentStep = 0;

                      function showStep(i) {
                        steps.forEach((s, idx) => s.classList.toggle('d-none', idx !== i));
                      }
                      showStep(currentStep);

                      function etapaValida(container) {
                        const fields = container.querySelectorAll('input, select, textarea');
                        for (const f of fields) {
                          if (f.disabled) continue;
                          if (!f.checkValidity()) {
                            f.reportValidity();
                            return false;
                          }
                        }
                        return true;
                      }

                      nextButtons.forEach(btn => {
                        btn.addEventListener('click', () => {
                          if (!etapaValida(steps[currentStep])) return;
                          if (currentStep < steps.length - 1) {
                            currentStep++;
                            showStep(currentStep);
                          }
                        });
                      });

                      prevButtons.forEach(btn => {
                        btn.addEventListener('click', () => {
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

        </div><!-- /container -->
      </div><!-- /layout-page -->
    </div><!-- /layout-container -->
  </div><!-- /layout-wrapper -->

  <script src="../../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../../assets/vendor/libs/popper/popper.js"></script>
  <script src="../../assets/vendor/js/bootstrap.js"></script>
  <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="../../assets/vendor/js/menu.js"></script>
  <script src="../../assets/vendor/libs/apex-charts/apexcharts.js"></script>
  <script src="../../assets/js/main.js"></script>
  <script src="../../assets/js/dashboards-analytics.js"></script>
  <script async defer src="https://buttons.github.io/buttons.js"></script>
</body>

</html>
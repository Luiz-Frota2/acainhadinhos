<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../../assets/php/conexao.php';

$idSelecionado = $_GET['id'] ?? '';
if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id'])
) {
    header("Location: ../login.php?id=$idSelecionado");
    exit;
}

// Validação da empresa
if (str_starts_with($idSelecionado, 'principal_')) {
    if ($_SESSION['tipo_empresa'] !== 'principal' || $_SESSION['empresa_id'] != 1) {
        header("Location: ../login.php?id=$idSelecionado");
        exit;
    }
} elseif (str_starts_with($idSelecionado, 'filial_')) {
    $empresa_id = (int) str_replace('filial_', '', $idSelecionado);
    if ($_SESSION['tipo_empresa'] !== 'filial' || $_SESSION['empresa_id'] != $empresa_id) {
        header("Location: ../login.php?id=$idSelecionado");
        exit;
    }
} else {
    header("Location: ../login.php?id=$idSelecionado");
    exit;
}

// Buscar imagem da empresa
try {
    $sql = "SELECT imagem FROM sobre_empresa WHERE id_selecionado = :id_selecionado LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id_selecionado', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $empresaSobre = $stmt->fetch(PDO::FETCH_ASSOC);

    $logoEmpresa = !empty($empresaSobre['imagem'])
        ? "../../assets/img/empresa/" . $empresaSobre['imagem']
        : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png";
}

// Buscar dados do usuário
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

// Funções auxiliares para cálculo de horas
function timeToSeconds($time)
{
    if (!$time || $time === '00:00:00')
        return 0;
    list($h, $m, $s) = explode(':', $time);
    return $h * 3600 + $m * 60 + $s;
}

function secondsToHM($seconds)
{
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    return sprintf('%02dh %02dm', $h, $m);
}

function calcularHorasTrabalhadas($entrada, $saida_intervalo, $retorno_intervalo, $saida_final)
{
    $total = 0;

    // Período da manhã (entrada até saída para intervalo)
    if ($entrada && $saida_intervalo) {
        $total += timeToSeconds($saida_intervalo) - timeToSeconds($entrada);
    }

    // Período da tarde (retorno do intervalo até saída final)
    if ($retorno_intervalo && $saida_final) {
        $total += timeToSeconds($saida_final) - timeToSeconds($retorno_intervalo);
    }

    // Se não houve intervalo registrado, calcula direto da entrada até saída final
    if ($entrada && $saida_final && (!$saida_intervalo || !$retorno_intervalo)) {
        $total = timeToSeconds($saida_final) - timeToSeconds($entrada);
    }

    return $total;
}

// Processar requisição para visualizar frequência individual
if (isset($_GET['cpf']) && !empty($_GET['cpf'])) {
    $cpfBusca = preg_replace('/[^0-9]/', '', $_GET['cpf']);

    try {
        // Buscar nome do funcionário
        $stmt = $pdo->prepare("SELECT nome FROM pontos WHERE REPLACE(REPLACE(cpf, '.', ''), '-', '') = :cpf AND empresa_id = :empresa_id LIMIT 1");
        $stmt->bindParam(':cpf', $cpfBusca, PDO::PARAM_STR);
        $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
        $stmt->execute();
        $funcionario = $stmt->fetch();

        if (!$funcionario) {
            throw new Exception("Funcionário não encontrado");
        }

        $nomeFuncionario = $funcionario['nome'];

        // Consulta para agrupar por mês/ano
        $sql = "SELECT 
                YEAR(data) as ano,
                MONTH(data) as mes_numero,
                cpf as cpf
            FROM pontos 
            WHERE REPLACE(REPLACE(cpf, '.', ''), '-', '') = :cpf
            AND empresa_id = :empresa_id
            GROUP BY YEAR(data), MONTH(data)
            ORDER BY ano DESC, mes_numero DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':cpf', $cpfBusca, PDO::PARAM_STR);
        $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
        $stmt->execute();
        $mesesAnos = $stmt->fetchAll();

        // Para cada mês/ano, calcular totais
        $registros = [];
        foreach ($mesesAnos as $ma) {
            // Buscar todos os registros do mês
            $sqlDias = "SELECT 
                    entrada, saida_intervalo, retorno_intervalo, saida_final,
                    horas_pendentes, hora_extra
                FROM pontos 
                WHERE REPLACE(REPLACE(cpf, '.', ''), '-', '') = :cpf
                AND empresa_id = :empresa_id
                AND YEAR(data) = :ano
                AND MONTH(data) = :mes
                ORDER BY data";

            $stmtDias = $pdo->prepare($sqlDias);
            $stmtDias->bindParam(':cpf', $cpfBusca, PDO::PARAM_STR);
            $stmtDias->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
            $stmtDias->bindParam(':ano', $ma['ano'], PDO::PARAM_INT);
            $stmtDias->bindParam(':mes', $ma['mes_numero'], PDO::PARAM_INT);
            $stmtDias->execute();
            $dias = $stmtDias->fetchAll();

            // Calcular totais
            $totalSegundos = 0;
            $pendentesSegundos = 0;
            $extrasSegundos = 0;

            foreach ($dias as $dia) {
                $totalSegundos += calcularHorasTrabalhadas(
                    $dia['entrada'],
                    $dia['saida_intervalo'],
                    $dia['retorno_intervalo'],
                    $dia['saida_final']
                );

                $pendentesSegundos += timeToSeconds($dia['horas_pendentes']);
                $extrasSegundos += timeToSeconds($dia['hora_extra']);
            }

            $registros[] = [
                'ano' => $ma['ano'],
                'mes_numero' => $ma['mes_numero'],
                'total_segundos' => $totalSegundos,
                'pendentes_segundos' => $pendentesSegundos,
                'extras_segundos' => $extrasSegundos,
                'cpf' => $ma['cpf']
            ];
        }
    } catch (PDOException $e) {
        die("Erro no banco de dados: " . $e->getMessage());
    } catch (Exception $e) {
        die("Erro: " . $e->getMessage());
    }
}

function mesPortugues($mesNumero)
{
    $meses = [
        1 => 'Janeiro',
        2 => 'Fevereiro',
        3 => 'Março',
        4 => 'Abril',
        5 => 'Maio',
        6 => 'Junho',
        7 => 'Julho',
        8 => 'Agosto',
        9 => 'Setembro',
        10 => 'Outubro',
        11 => 'Novembro',
        12 => 'Dezembro'
    ];
    return $meses[$mesNumero] ?? '';
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

                    <a href="javascript:void(0);"
                        class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
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
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Recursos Humanos</span>
                    </li>

                    <li class="menu-item  ">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-buildings"></i>
                            <div data-i18n="Authentications">Setores</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item ">
                                <a href="./setoresAdicionados.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
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
                                <a href="./funcionarioAdicionados.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
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
                                <a href="./escalaAdicionadas.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div data-i18n="Escalas e Configuração"> Escalas Adicionadas</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./adicionarPonto.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Registro de Ponto Eletrônico">Adicionar Ponto</div>
                                </a>
                            </li>
                            <li class="menu-item active">
                                <a href="./ajustePonto.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div data-i18n="Registro de Ponto Eletrônico">Ajuste de Ponto</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="./atestadosFuncionarios.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
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
                                <a href="./frequenciaGeral.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
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

                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
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
                                <input type="text" id="searchInput" class="form-control border-0 shadow-none"
                                    placeholder="Pesquisar funcionário..." aria-label="Pesquisar..." />
                            </div>
                        </div>
                        <!-- /Search -->

                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <!-- Place this tag where you want the button to render. -->
                            <!-- User -->
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);"
                                    data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <img src="<?= htmlspecialchars($logoEmpresa) ?>" alt
                                            class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="<?= htmlspecialchars($logoEmpresa) ?>" alt
                                                            class="w-px-40 h-auto rounded-circle" />
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
                                        <a class="dropdown-item"
                                            href="../logout.php?id=<?= urlencode($idSelecionado); ?>">
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
                                Ponto</a>/</span>Frequência Individual</h4>
                    <h5 class="fw-bold mt-3 mb-3 custor-font"><span class="text-muted fw-light">Visualize a Frequência
                            do Funcionário</span></h5>

           
                        <div class="container mt-4">
                            <div class="card mt-3">
                                <h5 class="card-header">Frequência do Funcionário: <?= htmlspecialchars($nomeFuncionario) ?>
                                 
                                </h5>
                                <div class="table-responsive text-nowrap">
                                    <table class="table table-hover" id="tabelaBancoHoras">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Data</th>
                                                <th>Entrada</th>
                                                <th>Saída Int.</th>
                                                <th>Entrada Int.</th>
                                                <th>Saída</th>
                                                <th>Carga Horária</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>01/01/2024</td>
                                                <td>08:00</td>
                                                <td>12:00</td>
                                                <td>13:00</td>
                                                <td>17:00</td>
                                                <td>08h 00m</td>
                                                <td>
                                                      <a href="#"  data-bs-toggle="modal" data-bs-target="#editarPontoModal">
                                                        <i class="fas fa-edit"></i>                                       
                                                    </a>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>02/01/2024</td>
                                                <td>08:10</td>
                                                <td>12:05</td>
                                                <td>13:10</td>
                                                <td>17:05</td>
                                                <td>07h 55m</td>
                                                <td>
                                                    <a href="#"  data-bs-toggle="modal" data-bs-target="#editarPontoModal">
                                                        <i class="fas fa-edit"></i>                                       
                                                    </a>
                                                </td>
                                            </tr>
                                            <!-- Adicione mais linhas conforme necessário -->
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Modal de Edição -->
                                <div class="modal fade" id="editarPontoModal" tabindex="-1" aria-labelledby="editarPontoModalLabel" aria-hidden="true">
                                  <div class="modal-dialog">
                                    <div class="modal-content">
                                      <form>
                                        <div class="modal-header">
                                          <h5 class="modal-title" id="editarPontoModalLabel">Editar Ponto</h5>
                                          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                        </div>
                                        <div class="modal-body">
                                          <div class="mb-3">
                                            <label for="editEntrada" class="form-label">Entrada</label>
                                            <input type="time" class="form-control" id="editEntrada" name="entrada">
                                          </div>
                                          <div class="mb-3">
                                            <label for="editSaidaIntervalo" class="form-label">Saída Intervalo</label>
                                            <input type="time" class="form-control" id="editSaidaIntervalo" name="saida_intervalo">
                                          </div>
                                          <div class="mb-3">
                                            <label for="editRetornoIntervalo" class="form-label">Entrada Intervalo</label>
                                            <input type="time" class="form-control" id="editRetornoIntervalo" name="retorno_intervalo">
                                          </div>
                                          <div class="mb-3">
                                            <label for="editSaidaFinal" class="form-label">Saída</label>
                                            <input type="time" class="form-control" id="editSaidaFinal" name="saida_final">
                                          </div>
                                          <div class="mb-3">
                                            <label for="editCarga" class="form-label">Carga Horária</label>
                                            <input type="text" class="form-control" id="editCarga" name="carga" disabled value="08h 00m">
                                          </div>
                                        </div>
                                        <div class="modal-footer">
                                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                          <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                                        </div>
                                      </form>
                                    </div>
                                  </div>
                                </div>

                               

                                <div class="d-flex gap-2 m-3">
                                    <button id="prevPageHoras" class="btn btn-outline-primary btn-sm">&laquo;
                                        Anterior</button>
                                    <div id="paginacaoHoras" class="d-flex gap-1"></div>
                                    <button id="nextPageHoras" class="btn btn-outline-primary btn-sm">Próxima
                                        &raquo;</button>
                                </div>

                            </div>
                        </div>

                    
                   
                </div>

                <script>
                    const searchInput = document.getElementById('searchInput');
                    const allRows = Array.from(document.querySelectorAll('#tabelaBancoHoras tbody tr'));
                    const prevBtn = document.getElementById('prevPageHoras');
                    const nextBtn = document.getElementById('nextPageHoras');
                    const pageContainer = document.getElementById('paginacaoHoras');
                    const perPage = 10;
                    let currentPage = 1;

                    function renderTable() {
                        const filter = searchInput.value.trim().toLowerCase();
                        const filteredRows = allRows.filter(row => {
                            if (!filter) return true;
                            return Array.from(row.cells).some(td =>
                                td.textContent.toLowerCase().includes(filter)
                            );
                        });

                        const totalPages = Math.ceil(filteredRows.length / perPage) || 1;
                        currentPage = Math.min(Math.max(1, currentPage), totalPages);

                        // Hide all, then show slice
                        allRows.forEach(r => r.style.display = 'none');
                        filteredRows.slice((currentPage - 1) * perPage, currentPage * perPage)
                            .forEach(r => r.style.display = '');

                        // Render page buttons
                        pageContainer.innerHTML = '';
                        for (let i = 1; i <= totalPages; i++) {
                            const btn = document.createElement('button');
                            btn.textContent = i;
                            btn.className = 'btn btn-sm ' + (i === currentPage ? 'btn-primary' : 'btn-outline-primary');
                            btn.style.marginRight = '4px';
                            btn.onclick = () => {
                                currentPage = i;
                                renderTable();
                            };
                            pageContainer.appendChild(btn);
                        }

                        prevBtn.disabled = currentPage === 1;
                        nextBtn.disabled = currentPage === totalPages;
                    }

                    prevBtn.addEventListener('click', () => {
                        if (currentPage > 1) {
                            currentPage--;
                            renderTable();
                        }
                    });
                    nextBtn.addEventListener('click', () => {
                        currentPage++;
                        renderTable();
                    });
                    searchInput.addEventListener('input', () => {
                        currentPage = 1;
                        renderTable();
                    });

                    document.addEventListener('DOMContentLoaded', renderTable);
                </script>

            </div>
        </div>
    </div>
    </div>
    </div>



    <script src="../../js/saudacao.js"></script>
    <script src="../../assets/vendor/libs/popper/popper.js"></script>
    <script src="../../assets/vendor/js/bootstrap.js"></script>
    <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>

    <script src="../../assets/vendor/js/menu.js"></script>
    <!-- endbuild -->

    <!-- Vendors JS -->
    <script src="../../assets/vendor/libs/apex-charts/apexcharts.js"></script>

    <!-- Main JS -->
    <script src="../../assets/js/main.js"></script>


    <!-- Place this tag in your head or just before your close body tag. -->
    <script async defer src="https://buttons.github.io/buttons.js"></script>

</body>

</html>
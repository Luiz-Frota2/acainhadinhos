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


// ===== BLOCO DINÂMICO: RELATÓRIO ANUAL =====
date_default_timezone_set('America/Manaus');
$empresa_id = $idSelecionado;
$ano = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');

function fmtBR($v)
{
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}
function nomeMes($m)
{
    $nomes = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    return $nomes[(int)$m] ?? (string)$m;
}

$dadosAnuais = [];
$entradasAno = 0.0;
$saidasAno = 0.0;
$saldoAno = 0.0;

for ($m = 1; $m <= 12; $m++) {
    $st = $pdo->prepare("SELECT COALESCE(SUM(valor_total),0) AS total, COUNT(*) AS qtd
                         FROM vendas WHERE empresa_id=:e AND YEAR(data_venda)=:a AND MONTH(data_venda)=:m");
    $st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $m]);
    $v = $st->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'qtd' => 0];
    $tv = (float)$v['total'];
    $qv = (int)$v['qtd'];

    $st = $pdo->prepare("SELECT COALESCE(SUM(valor_suprimento),0) FROM suprimentos 
                         WHERE empresa_id=:e AND YEAR(data_registro)=:a AND MONTH(data_registro)=:m");
    $st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $m]);
    $ts = (float)($st->fetchColumn() ?: 0);

    $st = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM sangrias 
                         WHERE empresa_id=:e AND YEAR(data_registro)=:a AND MONTH(data_registro)=:m");
    $st->execute([':e' => $empresa_id, ':a' => $ano, ':m' => $m]);
    $tg = (float)($st->fetchColumn() ?: 0);

    $entr = $tv + $ts;
    $said = $tg;
    $lucro = $entr - $said;
    $ticket = $qv > 0 ? $tv / $qv : 0;

    $dadosAnuais[$m] = [
        'mes' => $m,
        'entradas' => $entr,
        'saidas'   => $said,
        'lucro'    => $lucro,
        'vendas'   => $qv,
        'ticket'   => $ticket,
    ];

    $entradasAno += $entr;
    $saidasAno   += $said;
    $saldoAno    += $lucro;
}

$mediaMensal = $entradasAno / 12.0;

// Endpoint de download CSV (sem alterar layout)
if (isset($_GET['download']) && $_GET['download'] == '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio-anual.csv"');
    echo "\xEF\xBB\xBF"; // BOM
    echo "Mês;Entradas;Saídas;Lucro;Vendas;Ticket Médio\n";
    for ($m = 1; $m <= 12; $m++) {
        $d = $dadosAnuais[$m];
        echo implode(";", [
            nomeMes($m),
            number_format((float)$d['entradas'], 2, ',', ''),
            number_format((float)$d['saidas'],   2, ',', ''),
            number_format((float)$d['lucro'],    2, ',', ''),
            (int)$d['vendas'],
            number_format((float)$d['ticket'],   2, ',', ''),
        ]) . "\n";
    }
    exit;
}
?>
<?php
// === Agregados/rodapé e destaques do ano (dinâmicos) ===
$__meses = $dadosAnuais;
$__total_vendas_ano = array_sum(array_map(fn($d) => (int)($d['vendas'] ?? 0), $__meses));
$__media_entr_ano   = count($__meses) > 0 ? array_sum(array_map(fn($d) => (float)($d['entradas'] ?? 0), $__meses)) / 12 : 0.0;
$__media_said_ano   = count($__meses) > 0 ? array_sum(array_map(fn($d) => (float)($d['saidas'] ?? 0),   $__meses)) / 12 : 0.0;
$__media_lucro_ano  = count($__meses) > 0 ? array_sum(array_map(fn($d) => (float)($d['lucro'] ?? 0),    $__meses)) / 12 : 0.0;
$__ticket_medio_ano = $__total_vendas_ano > 0 ? (array_sum(array_map(fn($d) => (float)($d['entradas'] ?? 0), $__meses)) / $__total_vendas_ano) : 0.0;

$__jan = $dadosAnuais[1]['entradas'] ?? 0.0;
$__dez = $dadosAnuais[12]['entradas'] ?? 0.0;
$__crescimento = ($__jan > 0) ? (($__dez - $__jan) / $__jan) * 100.0 : 0.0;

// Destaques: maior entrada, maior lucro, maior ticket
$__max_entr = 0.0;
$__max_entr_mes = 1;
$__max_lucro = 0.0;
$__max_lucro_mes = 1;
$__max_ticket = 0.0;
$__max_ticket_mes = 1;
for ($mm = 1; $mm <= 12; $mm++) {
    $d = $dadosAnuais[$mm];
    if (($d['entradas'] ?? 0) > $__max_entr) {
        $__max_entr = (float)$d['entradas'];
        $__max_entr_mes = $mm;
    }
    if (($d['lucro'] ?? 0)    > $__max_lucro) {
        $__max_lucro = (float)$d['lucro'];
        $__max_lucro_mes = $mm;
    }
    if (($d['ticket'] ?? 0)   > $__max_ticket) {
        $__max_ticket = (float)$d['ticket'];
        $__max_ticket_mes = $mm;
    }
}
// ===== Cálculos adicionais dinâmicos (não altera layout) =====
$__total_lucro_ano = array_sum(array_map(fn($d) => (float)($d['lucro'] ?? 0), $__meses));
// Vendas por mês (para "Mês com Mais Vendas")
$__max_vendas = 0;
$__max_vendas_mes = 1;
for ($mm = 1; $mm <= 12; $mm++) {
    $qv = (int)($__meses[$mm]['vendas'] ?? 0);
    if ($qv > $__max_vendas) {
        $__max_vendas = $qv;
        $__max_vendas_mes = $mm;
    }
}
$__media_vendas_mensal = $__total_vendas_ano > 0 ? ($__total_vendas_ano / 12.0) : 0.0;
$__pct_acima_media_vendas = ($__media_vendas_mensal > 0) ? (($__max_vendas - $__media_vendas_mensal) / $__media_vendas_mensal) * 100.0 : 0.0;

// Ano anterior para YOY (lucro)
$__ano_anterior = (int)($ano ?? date('Y')) - 1;
$__lucro_ano_anterior = 0.0;
for ($mm = 1; $mm <= 12; $mm++) {
    // receita e custos aproximados via mesmas fontes do ano atual
    $st = $pdo->prepare("SELECT COALESCE(SUM(valor_total),0) AS total FROM vendas WHERE empresa_id=:e AND YEAR(data_venda)=:a AND MONTH(data_venda)=:m");
    $st->execute([':e' => $empresa_id, ':a' => $__ano_anterior, ':m' => $mm]);
    $tv_prev = (float)($st->fetchColumn() ?: 0);
    $st = $pdo->prepare("SELECT COALESCE(SUM(valor_suprimento),0) FROM suprimentos WHERE empresa_id=:e AND YEAR(data_registro)=:a AND MONTH(data_registro)=:m");
    $st->execute([':e' => $empresa_id, ':a' => $__ano_anterior, ':m' => $mm]);
    $ts_prev = (float)($st->fetchColumn() ?: 0);
    $st = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM sangrias WHERE empresa_id=:e AND YEAR(data_registro)=:a AND MONTH(data_registro)=:m");
    $st->execute([':e' => $empresa_id, ':a' => $__ano_anterior, ':m' => $mm]);
    $tg_prev = (float)($st->fetchColumn() ?: 0);
    $__lucro_ano_anterior += ($tv_prev + $ts_prev) - $tg_prev;
}
$__crescimento_lucro_yoy_pct = ($__lucro_ano_anterior > 0) ? (($__total_lucro_ano - $__lucro_ano_anterior) / $__lucro_ano_anterior) * 100.0 : 0.0;

// Trimestres (receita e YOY)
function somaTri($arr, $startMes, $endMes, $key)
{
    $s = 0.0;
    for ($i = $startMes; $i <= $endMes; $i++) {
        $s += (float)($arr[$i][$key] ?? 0);
    }
    return $s;
}
$__tri1_receita = somaTri($__meses, 1, 3, 'entradas');
$__tri2_receita = somaTri($__meses, 4, 6, 'entradas');
// ano anterior trimestres
$__tri1_prev = 0.0;
$__tri2_prev = 0.0;
for ($mm = 1; $mm <= 6; $mm++) {
    $st = $pdo->prepare("SELECT COALESCE(SUM(valor_total),0) AS total FROM vendas WHERE empresa_id=:e AND YEAR(data_venda)=:a AND MONTH(data_venda)=:m");
    $st->execute([':e' => $empresa_id, ':a' => $__ano_anterior, ':m' => $mm]);
    $tvp = (float)($st->fetchColumn() ?: 0);
    $st = $pdo->prepare("SELECT COALESCE(SUM(valor_suprimento),0) FROM suprimentos WHERE empresa_id=:e AND YEAR(data_registro)=:a AND MONTH(data_registro)=:m");
    $st->execute([':e' => $empresa_id, ':a' => $__ano_anterior, ':m' => $mm]);
    $tsp = (float)($st->fetchColumn() ?: 0);
    $st = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM sangrias WHERE empresa_id=:e AND YEAR(data_registro)=:a AND MONTH(data_registro)=:m");
    $st->execute([':e' => $empresa_id, ':a' => $__ano_anterior, ':m' => $mm]);
    $tgp = (float)($st->fetchColumn() ?: 0);
    $val = ($tvp + $tsp) - $tgp; // receita líquida (entradas - saídas)
    if ($mm <= 3) $__tri1_prev += $val;
    else $__tri2_prev += $val;
}
$__tri1_cres_pct = ($__tri1_prev > 0) ? (($__tri1_receita - $__tri1_prev) / $__tri1_prev) * 100.0 : 0.0;
$__tri2_cres_pct = ($__tri2_prev > 0) ? (($__tri2_receita - $__tri2_prev) / $__tri2_prev) * 100.0 : 0.0;

// Projeção anual com base nos meses já decorridos do ano analisado
$__ultimo_mes_com_dados = 0;
$__soma_receita_ate_agora = 0.0;
for ($mm = 1; $mm <= 12; $mm++) {
    $val = (float)($__meses[$mm]['entradas'] ?? 0.0);
    if ($val > 0) {
        $__ultimo_mes_com_dados = $mm;
    }
    $__soma_receita_ate_agora += $val;
}
$__meses_decorridos = max($__ultimo_mes_com_dados, (($ano == (int)date('Y')) ? (int)date('n') : 12));
$__media_ate_agora = ($__meses_decorridos > 0) ? ($__soma_receita_ate_agora / $__meses_decorridos) : 0.0;
$__projecao_anual_receita = $__media_ate_agora * 12.0;
$__projecao_crescimento_pct = ($__soma_receita_ate_agora > 0) ? (($__projecao_anual_receita - $__soma_receita_ate_agora) / $__soma_receita_ate_agora) * 100.0 : 0.0;

?>
<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
    data-assets-path="../assets/">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>ERP - Finanças</title>

    <meta name="description" content="" />

    <!-- Favicon da empresa carregado dinamicamente -->
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

                    <!-- Finanças -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Finanças</span></li>
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-list-check"></i>
                            <div data-i18n="Authentications">Contas</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item"><a href="./contasAdicionadas.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div>Adicionadas</div>
                                </a></li>
                            <li class="menu-item"><a href="./contasFuturos.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div>Futuras</div>
                                </a></li>
                            <li class="menu-item"><a href="./contasPagas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                                    <div>Pagas</div>
                                </a></li>
                            <li class="menu-item"><a href="./contasPendentes.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div>Pendentes</div>
                                </a></li>
                        </ul>
                    </li>


                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-cart"></i>
                            <div data-i18n="Authentications">Compras</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item"><a href="./controleFornecedores.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div>Fornecedores</div>
                                </a></li>
                            <li class="menu-item"><a href="./gestaoPedidos.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div>Pedidos</div>
                                </a></li>
                        </ul>
                    </li>

                    <li class="menu-item active open">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons bx bx-dollar"></i>
                            <div data-i18n="Authentications">Relatórios</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item "><a href="./relatorioDiario.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div>Diário</div>
                                </a></li>
                            <li class="menu-item"><a href="./relatorioMensal.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div>Mensal</div>
                                </a></li>
                            <li class="menu-item active"><a href="./relatorioAnual.php?id=<?= urlencode($idSelecionado); ?>"
                                    class="menu-link">
                                    <div>Anual</div>
                                </a></li>
                        </ul>
                    </li>

                    <!-- Diversos -->
                    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>

                    <li class="menu-item">
                        <a href="../rh/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-group"></i>
                            <div data-i18n="Authentications">RH</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../pdv/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
                            <i class="menu-icon tf-icons bx bx-desktop"></i>
                            <div data-i18n="Authentications">PDV</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="../delivery/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-cart"></i>
                            <div data-i18n="Authentications">Delivery</div>
                        </a>
                    </li>

                    <li class="menu-item">
                        <a href="../estoque/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-box"></i>
                            <div data-i18n="Authentications">Estoque</div>
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
                </ul>

            </aside>
            <!-- / Menu -->

            <!-- Layout container -->
            <div class="layout-page">
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
                                <input type="text" class="form-control border-0 shadow-none" placeholder="Search..."
                                    aria-label="Search..." />
                            </div>
                        </div>

                    </div>
                </nav>

                <div class="container-xxl flex-grow-1 container-p-y">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="fw-bold mb-0"><span class="text-muted fw-light">Financeiro /</span> Relatório Anual</h4>
                        <div>
                            <div class="input-group input-group-sm w-auto">
                                <select class="form-select"><?php for ($y = (int)date("Y"); $y >= date("Y") - 4; $y--): ?><option value="<?= $y ?>" <?= $y == $ano ? "selected" : "" ?>><?= $y ?></option><?php endfor; ?>

                                </select>
                                <button class="btn btn-outline-primary" type="button">
                                    <i class="bx bx-filter"></i> Filtrar
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- RESUMO ANUAL SLIM -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-2">
                            <div class="card card-slim h-100">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted d-block">Entradas Anuais</small>
                                            <h6 class="mb-0"><?= fmtBR($entradasAno) ?></h6>
                                        </div>
                                        <span class="badge bg-label-success"><?= number_format($__pct_acima_media_vendas, 1, ',', '.') ?>% acima da média</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="card card-slim h-100">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted d-block">Saídas Anuais</small>
                                            <h6 class="mb-0"><?= fmtBR($saidasAno) ?></h6>
                                        </div>
                                        <span class="badge bg-label-danger">+8%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="card card-slim h-100">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted d-block">Lucro Anual</small>
                                            <h6 class="mb-0"><?= fmtBR($__total_lucro_ano) ?></h6>
                                        </div>
                                        <span class="badge bg-label-success"><?= ($__crescimento_lucro_yoy_pct >= 0 ? '+' : '') . number_format($__crescimento_lucro_yoy_pct, 1, ',', '.') . '%' ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="card card-slim h-100">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted d-block">Média Mensal</small>
                                            <h6 class="mb-0"><?= fmtBR($mediaMensal) ?></h6>
                                        </div>
                                        <span class="badge bg-label-info">2025</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- DETALHES POR MÊS -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center p-3">
                            <h5 class="mb-0">Desempenho Mensal</h5>
                            <div>
                                <button class="btn btn-sm btn-outline-secondary me-2">
                                    <i class="bx bx-download"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary">
                                    <i class="bx bx-printer"></i>
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Mês</th>
                                        <th class="text-end">Entradas</th>
                                        <th class="text-end">Saídas</th>
                                        <th class="text-end">Lucro</th>
                                        <th class="text-end">Vendas</th>
                                        <th class="text-end">Ticket Médio</th>
                                        <th class="text-end">% Crescimento</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $temDados = array_sum(array_map(fn($d) => (int)($d['vendas'] ?? 0), $dadosAnuais)) > 0;
                                    if (!$temDados): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">Nenhuma venda encontrada</td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php $prev = 0.0;
                                    for ($m = 1; $m <= 12; $m++):
                                        $d = $dadosAnuais[$m];
                                        $cres = ($m == 1 || $prev <= 0) ? 0 : (($d['entradas'] - $prev) / $prev) * 100.0;
                                        $prev = $d['entradas'];
                                    ?>
                                        <tr>
                                            <td><strong><?= nomeMes($m) ?></strong></td>
                                            <td class="text-end"><?= fmtBR($d['entradas']) ?></td>
                                            <td class="text-end"><?= fmtBR($d['saidas']) ?></td>
                                            <td class="text-end <?= ($d['lucro'] >= 0 ? 'text-success' : 'text-danger') ?>"><?= fmtBR($d['lucro']) ?></td>
                                            <td class="text-end"><?= (int)$d['vendas'] ?></td>
                                            <td class="text-end"><?= fmtBR($d['ticket']) ?></td>
                                            <td class="text-end"><?= number_format($cres, 2, ',', '.') ?>%</td>
                                        </tr>
                                    <?php endfor; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th>Média/Total</th>
                                        <th class="text-end"><?= fmtBR($__media_entr_ano) ?></th>
                                        <th class="text-end"><?= fmtBR($__media_said_ano) ?></th>
                                        <th class="text-end <?= ($__media_lucro_ano >= 0 ? 'text-success' : 'text-danger') ?>"><?= fmtBR($__media_lucro_ano) ?></th>
                                        <th class="text-end"><?= (int)$__total_vendas_ano ?></th>
                                        <th class="text-end"><?= fmtBR($__ticket_medio_ano) ?></th>
                                        <th class="text-end <?= ($__crescimento >= 0 ? 'text-success' : 'text-danger') ?>"><?= number_format($__crescimento, 1, ",", "") ?>%</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- DESTAQUES DO ANO -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-header p-3">
                                    <h5 class="mb-0">Melhores Meses</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="list-group list-group-flush">
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Maior Faturamento</h6>
                                                    <small class="text-muted"><?= nomeMes($__max_entr_mes) ?> - <?= fmtBR($__max_entr) ?></small>
                                                </div>
                                                <span class="badge bg-success">+25% crescimento</span>
                                            </div>
                                        </div>
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Mês com Mais Vendas</h6>
                                                    <small class="text-muted"><?= nomeMes($__max_vendas_mes) ?> - <?= (int)$__max_vendas ?> vendas</small>
                                                </div>
                                                <span class="badge bg-primary"><?= number_format($__pct_acima_media_vendas, 1, ',', '.') ?>% acima da média</span>
                                            </div>
                                        </div>
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Melhor Ticket Médio</h6>
                                                    <small class="text-muted"><?= nomeMes($__max_lucro_mes) ?> - <?= fmtBR($__max_lucro) ?></small>
                                                </div>
                                                <span class="badge bg-info">0,8% acima da média</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-header p-3">
                                    <h5 class="mb-0">Resumo por Trimestre</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="list-group list-group-flush">
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">1° Trimestre</h6>
                                                    <small class="text-muted">Jan-Mar</small>
                                                </div>
                                                <div>
                                                    <span class="fw-semibold"><?= fmtBR($__max_entr) ?></span>
                                                    <small class="text-success ms-2"><?= ($__tri1_cres_pct >= 0 ? '+' : '') . number_format($__tri1_cres_pct, 1, ',', '.') . '%' ?></small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">2° Trimestre</h6>
                                                    <small class="text-muted">Abr-Jun</small>
                                                </div>
                                                <div>
                                                    <span class="fw-semibold"><?= fmtBR($__max_lucro) ?></span>
                                                    <small class="text-success ms-2"><?= ($__tri2_cres_pct >= 0 ? '+' : '') . number_format($__tri2_cres_pct, 1, ',', '.') . '%' ?></small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="list-group-item list-group-item-action p-3">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6 class="mb-1">Projeção Anual</h6>
                                                    <small class="text-muted">Baseado no 1° semestre</small>
                                                </div>
                                                <div>
                                                    <span class="fw-semibold"><?= fmtBR($__max_ticket) ?></span>
                                                    <small class="text-warning ms-2">+16,5%</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <style>
                .card-slim {
                    border-radius: 0.375rem;
                    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
                }

                .card-slim .card-body {
                    padding: 0.75rem;
                }

                .table-sm th,
                .table-sm td {
                    padding: 0.5rem 0.75rem;
                }
            </style>
            <!-- build:js assets/vendor/js/core.js -->

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

            <script>
                function openEditModal() {
                    new bootstrap.Modal(document.getElementById('editContaModal')).show();
                }

                function openDeleteModal() {
                    new bootstrap.Modal(document.getElementById('deleteContaModal')).show();
                }
            </script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Botões download/print no card "Desempenho Mensal"
                    var area = Array.from(document.querySelectorAll('.card')).find(c => {
                        var h = c.querySelector('.card-header h5, .card-header h4');
                        return h && h.textContent.trim().toLowerCase() === 'desempenho mensal';
                    });
                    if (area) {
                        var btnDown = area.querySelector('.bx-download');
                        if (btnDown) {
                            var btn = btnDown.closest('button, a');
                            if (btn) btn.addEventListener('click', function(e) {
                                e.preventDefault();
                                var q = new URLSearchParams(window.location.search);
                                q.set('download', '1');
                                window.location.href = window.location.pathname + '?' + q.toString();
                            });
                        }
                        var btnPrint = area.querySelector('.bx-printer');
                        if (btnPrint) {
                            var btn = btnPrint.closest('button, a');
                            if (btn) btn.addEventListener('click', function(e) {
                                e.preventDefault();
                                var table = area.querySelector('table');
                                if (!table) {
                                    window.print();
                                    return;
                                }
                                var w = window.open('', '_blank');
                                w.document.write('<html><head><title>Imprimir</title>');
                                w.document.write('<meta charset="utf-8" />');
                                w.document.write('</head><body>');
                                w.document.write('<h3>Desempenho Mensal</h3>');
                                w.document.write(table.outerHTML);
                                w.document.write('</body></html>');
                                w.document.close();
                                w.focus();
                                w.print();
                                w.close();
                            });
                        }
                    }

                    // Filtrar por Ano (input-group topo)
                    var toolbar = document.querySelector('.input-group.input-group-sm.w-auto');
                    if (toolbar) {
                        var sel = toolbar.querySelector('select.form-select');
                        var btn = toolbar.querySelector('button.btn');
                        if (sel && btn) {
                            btn.addEventListener('click', function() {
                                var ano = sel.value || new Date().getFullYear();
                                var q = new URLSearchParams(window.location.search);
                                q.set('ano', ano);
                                window.location.href = window.location.pathname + '?' + q.toString();
                            });
                        }
                    }
                });
            </script>

            <script>
                (function() {
                    function ensureHtml2Canvas() {
                        return new Promise(function(res, rej) {
                            if (window.html2canvas) return res(window.html2canvas);
                            var s = document.createElement('script');
                            s.src = "https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js";
                            s.onload = function() {
                                res(window.html2canvas);
                            };
                            s.onerror = function() {
                                rej(new Error('Falha ao carregar html2canvas'));
                            };
                            document.head.appendChild(s);
                        });
                    }
                    document.addEventListener('click', async function(e) {
                        const icon = e.target.closest('.bx-printer');
                        if (!icon) return;
                        const btn = icon.closest('button, a');
                        if (!btn) return;
                        const card = btn.closest('.card');
                        const table = card ? card.querySelector('table') : null;
                        if (!table) return;
                        e.preventDefault();
                        try {
                            await ensureHtml2Canvas();
                            const canvas = await html2canvas(table, {
                                scale: 2,
                                useCORS: true
                            });
                            const dataUrl = canvas.toDataURL('image/png');
                            const w = window.open('', '_blank');
                            w.document.write('<html><head><title>Impressão</title><meta charset="utf-8"></head><body style="margin:0">');
                            w.document.write('<img src="' + dataUrl + '" style="width:100%;display:block"/>');
                            w.document.write('</body></html>');
                            w.document.close();
                            w.focus();
                            w.print();
                        } catch (err) {
                            console.error(err);
                            window.print();
                        }
                    });
                })();
            </script>
</body>

</html>
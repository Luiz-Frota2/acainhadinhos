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


// Buscar imagem da tabela sobre_empresa
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

// Buscar nome e nível do usuário logado
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

// Processar filtro de período
$filtro_periodo = $_GET['filtro_periodo'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';

// Definir condições de data com base no filtro
$condicao_data = '';
$condicao_itens = '';
$parametros = [':empresa_id' => $idSelecionado];
$parametros_itens = [':empresa_id_itens' => $idSelecionado];

if ($filtro_periodo === 'personalizar' && !empty($data_inicio) && !empty($data_fim)) {
  $condicao_data = " AND data_venda BETWEEN :data_inicio AND :data_fim";
  $condicao_itens = " AND v.data_venda BETWEEN :data_inicio_itens AND :data_fim_itens";
  $parametros[':data_inicio'] = $data_inicio;
  $parametros[':data_fim'] = $data_fim;
  $parametros_itens[':data_inicio_itens'] = $data_inicio;
  $parametros_itens[':data_fim_itens'] = $data_fim;
} elseif ($filtro_periodo === 'dia') {
  $condicao_data = " AND DATE(data_venda) = CURDATE()";
  $condicao_itens = " AND DATE(v.data_venda) = CURDATE()";
} elseif ($filtro_periodo === 'semana') {
  $condicao_data = " AND YEARWEEK(data_venda, 1) = YEARWEEK(CURDATE(), 1)";
  $condicao_itens = " AND YEARWEEK(v.data_venda, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($filtro_periodo === 'mes') {
  $condicao_data = " AND MONTH(data_venda) = MONTH(CURDATE()) AND YEAR(data_venda) = YEAR(CURDATE())";
  $condicao_itens = " AND MONTH(v.data_venda) = MONTH(CURDATE()) AND YEAR(v.data_venda) = YEAR(CURDATE())";
} elseif ($filtro_periodo === 'ano') {
  $condicao_data = " AND YEAR(data_venda) = YEAR(CURDATE())";
  $condicao_itens = " AND YEAR(v.data_venda) = YEAR(CURDATE())";
}

// Buscar dados para o resumo operacional
$totalVendas = 0;
$vendasRealizadas = 0;
$produtosMaisVendidos = [];
$dadosGrafico = [];
$tituloGrafico = 'Vendas nos Últimos 7 Dias';

try {
  // Total de vendas
  $sqlVendas = "SELECT SUM(valor_total) as total FROM vendas WHERE empresa_id = :empresa_id $condicao_data";
  $stmt = $pdo->prepare($sqlVendas);
  foreach ($parametros as $key => $value) {
    $stmt->bindValue($key, $value);
  }
  $stmt->execute();
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  $totalVendas = $result['total'] ?? 0;

  // Vendas realizadas (total de registros na tabela venda_rapida)
  $sqlVendasRealizadas = "SELECT COUNT(*) as total FROM vendas WHERE empresa_id = :empresa_id $condicao_data";
  $stmt = $pdo->prepare($sqlVendasRealizadas);
  foreach ($parametros as $key => $value) {
    $stmt->bindValue($key, $value);
  }
  $stmt->execute();
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  $vendasRealizadas = $result['total'] ?? 0;

  // Produtos mais vendidos
  $sqlProdutos = "SELECT iv.produto_nome, SUM(iv.quantidade) AS total_quantidade
                 FROM itens_venda iv
                 INNER JOIN vendas v ON v.id = iv.venda_id
                 WHERE v.empresa_id = :empresa_id_itens $condicao_itens
                 GROUP BY iv.produto_nome
                 ORDER BY total_quantidade DESC
                 LIMIT 5";
  $stmt = $pdo->prepare($sqlProdutos);
  foreach ($parametros_itens as $key => $value) {
    $stmt->bindValue($key, $value);
  }
  $stmt->execute();
  $produtosMaisVendidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Dados para o gráfico
  if ($filtro_periodo === 'dia') {
    $tituloGrafico = 'Vendas por Hora - Hoje';
    $sqlGrafico = "SELECT HOUR(data_venda) as hora, SUM(valor_total) as total 
                  FROM vendas 
                  WHERE empresa_id = :empresa_id 
                  AND DATE(data_venda) = CURDATE()
                  GROUP BY HOUR(data_venda) 
                  ORDER BY hora";

    $horasDia = array_fill(0, 24, 0);
    $stmt = $pdo->prepare($sqlGrafico);
    $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dados as $item) {
      $horasDia[$item['hora']] = (float) $item['total'];
    }

    $categoriasGrafico = array_map(function ($h) {
      return sprintf("%02d:00", $h);
    }, range(0, 23));
    $valoresGrafico = $horasDia;
  } elseif ($filtro_periodo === 'semana') {
    $tituloGrafico = 'Vendas por Dia - Esta Semana';
    $sqlGrafico = "SELECT DATE(data_venda) as data, DAYNAME(data_venda) as dia_semana, SUM(valor_total) as total 
                  FROM vendas
                  WHERE empresa_id = :empresa_id 
                  AND YEARWEEK(data_venda, 1) = YEARWEEK(CURDATE(), 1)
                  GROUP BY DATE(data_venda), dia_semana
                  ORDER BY data_venda";

    $diasSemana = [
      'Monday' => 'Segunda',
      'Tuesday' => 'Terça',
      'Wednesday' => 'Quarta',
      'Thursday' => 'Quinta',
      'Friday' => 'Sexta',
      'Saturday' => 'Sábado',
      'Sunday' => 'Domingo'
    ];

    $stmt = $pdo->prepare($sqlGrafico);
    $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $categoriasGrafico = [];
    $valoresGrafico = [];

    foreach ($dados as $item) {
      $categoriasGrafico[] = $diasSemana[$item['dia_semana']] ?? $item['dia_semana'];
      $valoresGrafico[] = (float) $item['total'];
    }
  } elseif ($filtro_periodo === 'mes') {
    $tituloGrafico = 'Vendas por Dia - Este Mês';
    $sqlGrafico = "SELECT DATE(data_venda) as data, DAY(data_venda) as dia, SUM(valor_total) as total 
                  FROM vendas
                  WHERE empresa_id = :empresa_id 
                  AND MONTH(data_venda) = MONTH(CURDATE()) 
                  AND YEAR(data_venda) = YEAR(CURDATE())
                  GROUP BY DATE(data_venda), dia
                  ORDER BY data_venda";

    $diasNoMes = date('t');
    $diasMes = array_fill(1, $diasNoMes, 0);

    $stmt = $pdo->prepare($sqlGrafico);
    $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dados as $item) {
      $diasMes[$item['dia']] = (float) $item['total'];
    }

    $categoriasGrafico = range(1, $diasNoMes);
    $valoresGrafico = array_values($diasMes);
  } elseif ($filtro_periodo === 'ano') {
    $tituloGrafico = 'Vendas por Mês - Este Ano';
    $sqlGrafico = "SELECT MONTH(data_venda) as mes, SUM(valor_total) as total 
                  FROM vendas
                  WHERE empresa_id = :empresa_id 
                  AND YEAR(data_venda) = YEAR(CURDATE())
                  GROUP BY MONTH(data_venda)
                  ORDER BY mes";

    $meses = [
      1 => 'Jan',
      2 => 'Fev',
      3 => 'Mar',
      4 => 'Abr',
      5 => 'Mai',
      6 => 'Jun',
      7 => 'Jul',
      8 => 'Ago',
      9 => 'Set',
      10 => 'Out',
      11 => 'Nov',
      12 => 'Dez'
    ];

    $mesesAno = array_fill(1, 12, 0);

    $stmt = $pdo->prepare($sqlGrafico);
    $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dados as $item) {
      $mesesAno[$item['mes']] = (float) $item['total'];
    }

    $categoriasGrafico = array_values($meses);
    $valoresGrafico = array_values($mesesAno);
  } elseif ($filtro_periodo === 'personalizar' && !empty($data_inicio) && !empty($data_fim)) {
    $tituloGrafico = 'Vendas no Período Selecionado';

    $datetime1 = new DateTime($data_inicio);
    $datetime2 = new DateTime($data_fim);
    $interval = $datetime1->diff($datetime2);
    $diasDiferenca = $interval->days;

    if ($diasDiferenca <= 7) {
      $sqlGrafico = "SELECT DATE(data_venda) as data, SUM(valor_total) as total 
                    FROM vendas
                    WHERE empresa_id = :empresa_id 
                    AND data_venda BETWEEN :data_inicio AND :data_fim
                    GROUP BY DATE(data_venda)
                    ORDER BY data_venda";

      $periodo = new DatePeriod(
        new DateTime($data_inicio),
        new DateInterval('P1D'),
        (new DateTime($data_fim))->modify('+1 day')
      );

      $dadosPeriodo = [];
      foreach ($periodo as $date) {
        $dataStr = $date->format('Y-m-d');
        $dadosPeriodo[$dataStr] = 0;
      }

      $stmt = $pdo->prepare($sqlGrafico);
      $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
      $stmt->bindParam(':data_inicio', $data_inicio);
      $stmt->bindParam(':data_fim', $data_fim);
      $stmt->execute();
      $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

      foreach ($dados as $item) {
        $dadosPeriodo[$item['data']] = (float) $item['total'];
      }

      $categoriasGrafico = [];
      $valoresGrafico = [];

      foreach ($dadosPeriodo as $data => $valor) {
        $dateObj = DateTime::createFromFormat('Y-m-d', $data);
        $categoriasGrafico[] = $dateObj->format('d/m');
        $valoresGrafico[] = $valor;
      }
    } elseif ($diasDiferenca <= 31) {
      $sqlGrafico = "SELECT DATE(data_venda) as data, SUM(valor_total) as total 
                    FROM vendas
                    WHERE empresa_id = :empresa_id 
                    AND data_venda BETWEEN :data_inicio AND :data_fim
                    GROUP BY DATE(data_venda)
                    ORDER BY data_venda";

      $stmt = $pdo->prepare($sqlGrafico);
      $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
      $stmt->bindParam(':data_inicio', $data_inicio);
      $stmt->bindParam(':data_fim', $data_fim);
      $stmt->execute();
      $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

      $categoriasGrafico = [];
      $valoresGrafico = [];

      foreach ($dados as $item) {
        $dateObj = DateTime::createFromFormat('Y-m-d', $item['data']);
        $categoriasGrafico[] = $dateObj->format('d/m');
        $valoresGrafico[] = (float) $item['total'];
      }
    } elseif ($diasDiferenca <= 365) {
      $sqlGrafico = "SELECT MONTH(data_venda) as mes, SUM(valor_total) as total 
                    FROM vendas
                    WHERE empresa_id = :empresa_id 
                    AND data_venda BETWEEN :data_inicio AND :data_fim
                    GROUP BY MONTH(data_venda)
                    ORDER BY mes";

      $meses = [
        1 => 'Jan',
        2 => 'Fev',
        3 => 'Mar',
        4 => 'Abr',
        5 => 'Mai',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Ago',
        9 => 'Set',
        10 => 'Out',
        11 => 'Nov',
        12 => 'Dez'
      ];

      $stmt = $pdo->prepare($sqlGrafico);
      $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
      $stmt->bindParam(':data_inicio', $data_inicio);
      $stmt->bindParam(':data_fim', $data_fim);
      $stmt->execute();
      $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

      $categoriasGrafico = [];
      $valoresGrafico = [];

      foreach ($dados as $item) {
        $categoriasGrafico[] = $meses[$item['mes']] ?? $item['mes'];
        $valoresGrafico[] = (float) $item['total'];
      }
    } else {
      $tituloGrafico = 'Vendas por Trimestre - Período Selecionado';
      $sqlGrafico = "SELECT 
                      QUARTER(data_venda) as trimestre, 
                      YEAR(data_venda) as ano,
                      SUM(valor_total) as total 
                    FROM vendas 
                    WHERE empresa_id = :empresa_id 
                    AND data_venda BETWEEN :data_inicio AND :data_fim
                    GROUP BY YEAR(data_venda), QUARTER(data_venda)
                    ORDER BY ano, trimestre";

      $stmt = $pdo->prepare($sqlGrafico);
      $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
      $stmt->bindParam(':data_inicio', $data_inicio);
      $stmt->bindParam(':data_fim', $data_fim);
      $stmt->execute();
      $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

      $categoriasGrafico = [];
      $valoresGrafico = [];

      foreach ($dados as $item) {
        $categoriasGrafico[] = $item['ano'] . ' - T' . $item['trimestre'];
        $valoresGrafico[] = (float) $item['total'];
      }
    }
  } else {
    // Padrão: últimos 7 dias
    $tituloGrafico = 'Vendas nos Últimos 7 Dias';
    $sqlGrafico = "SELECT DATE(data_venda) as data, DAYNAME(data_venda) as dia_semana, SUM(valor_total) as total 
                  FROM vendas
                  WHERE empresa_id = :empresa_id 
                  AND data_venda >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                  GROUP BY DATE(data_venda), dia_semana
                  ORDER BY data_venda";

    $diasSemana = [
      'Monday' => 'Seg',
      'Tuesday' => 'Ter',
      'Wednesday' => 'Qua',
      'Thursday' => 'Qui',
      'Friday' => 'Sex',
      'Saturday' => 'Sáb',
      'Sunday' => 'Dom'
    ];

    $ultimos7Dias = [];
    for ($i = 6; $i >= 0; $i--) {
      $data = date('Y-m-d', strtotime("-$i days"));
      $ultimos7Dias[$data] = ['dia_semana' => date('l', strtotime($data)), 'total' => 0];
    }

    $stmt = $pdo->prepare($sqlGrafico);
    $stmt->bindParam(':empresa_id', $idSelecionado, PDO::PARAM_STR);
    $stmt->execute();
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dados as $item) {
      $ultimos7Dias[$item['data']] = $item;
    }

    $categoriasGrafico = [];
    $valoresGrafico = [];

    foreach ($ultimos7Dias as $data => $item) {
      $categoriasGrafico[] = $diasSemana[$item['dia_semana']] ?? substr($item['dia_semana'], 0, 3);
      $valoresGrafico[] = (float) $item['total'];
    }
  }
} catch (PDOException $e) {
  error_log("Erro ao buscar dados operacionais: " . $e->getMessage());
  $tituloGrafico = 'Vendas';
  $categoriasGrafico = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
  $valoresGrafico = [0, 0, 0, 0, 0, 0, 0];
}

// Formatar produtos mais vendidos
$produtosFormatados = [];
foreach ($produtosMaisVendidos as $item) {
  if (!empty($item['produto_nome'])) {
    $produtosFormatados[] = htmlspecialchars($item['produto_nome']) . ' (' . $item['total_quantidade'] . ' un)';
  }
}
$listaProdutos = !empty($produtosFormatados) ? implode(', ', $produtosFormatados) : 'Nenhum dado disponível';

?>

<!DOCTYPE html>
<html lang="pt-br" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default"
  data-assets-path="../assets/">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
    content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>ERP - PDV</title>

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

          <!-- DASHBOARD -->
          <li class="menu-item">
            <a href="./index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
              <i class="menu-icon tf-icons bx bx-home-circle"></i>
              <div data-i18n="Analytics">Dashboard</div>
            </a>
          </li>

          <!-- SEÇÃO ADMINISTRATIVO -->
          <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Administrativo</span>
          </li>

          <!-- SUBMENU: SEFAZ -->
          <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-file"></i>
              <div data-i18n="Authentications">SEFAZ</div>
            </a>
            <ul class="menu-sub">
              <li class="menu-item">
                <a href="./adicionarNFCe.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">NFC-e</div>
                </a>
              </li>
              <li class="menu-item">
                <a href="./sefazStatus.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Status</div>
                </a>
              </li>
              <li class="menu-item">
                <a href="./sefazConsulta.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Consulta</div>
                </a>
              </li>
            </ul>
          </li>

          <!-- SUBMENU: CAIXA -->
          <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-user"></i>
              <div data-i18n="Authentications">Caixas</div>
            </a>
            <ul class="menu-sub">
              <!-- Caixa Aberto: Visualização de caixas abertos -->
              <li class="menu-item">
                <a href="./caixasAberto.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Caixas Aberto</div>
                </a>
              </li>
              <!-- Caixa Fechado: Histórico ou controle de caixas encerrados -->
              <li class="menu-item">
                <a href="./caixasFechado.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Caixas Fechado</div>
                </a>
              </li>
            </ul>
          </li>
          <!-- ESTOQUE COM SUBMENU -->
          <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-box"></i>
              <div data-i18n="Basic">Estoque</div>
            </a>
            <ul class="menu-sub">
              <!-- Produtos Adicionados: Cadastro ou listagem de produtos adicionados -->
              <li class="menu-item">
                <a href="./produtosAdicionados.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Produtos Adicionados</div>
                </a>
              </li>
              <!-- Estoque Baixo -->
              <li class="menu-item">
                <a href="./estoqueBaixo.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Estoque Baixo</div>
                </a>
              </li>
              <!-- Estoque Alto -->
              <li class="menu-item">
                <a href="./estoqueAlto.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Estoque Alto</div>
                </a>
              </li>
            </ul>
          </li>


          <!-- SUBMENU: RELATÓRIOS -->
          <li class="menu-item active open">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
              <i class="menu-icon tf-icons bx bx-file"></i>
              <div data-i18n="Authentications">Relatórios</div>
            </a>
            <ul class="menu-sub">
              <!-- Relatório Operacional: Desempenho de operações -->
              <li class="menu-item active">
                <a href="./relatorioOperacional.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Operacional</div>
                </a>
              </li>
              <!-- Relatório de Vendas: Estatísticas e resumo de vendas -->
              <li class="menu-item">
                <a href="./relatorioVendas.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link">
                  <div data-i18n="Basic">Vendas</div>
                </a>
              </li>
            </ul>

            <!-- Misc -->
          <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>
          <li class="menu-item">
            <a href="../rh/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-group"></i>
              <div data-i18n="Authentications">RH</div>
            </a>
          </li>
          <li class="menu-item">
            <a href="../financas/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-dollar"></i>
              <div data-i18n="Authentications">Finanças</div>
            </a>
          </li>
          <li class="menu-item">
            <a href="../delivery/index.php?id=<?= urlencode($idSelecionado); ?>" class="menu-link ">
              <i class="menu-icon tf-icons bx bx-cart"></i>
              <div data-i18n="Authentications">Delivery</div>
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

        <!-- Content -->
        <div class="container-xxl flex-grow-1 container-p-y">

          <div class="row">
            <div class="col-12 mb-2">
              <h4 class="fw-bold py-3 mb-2">Relatório Operacional</h4>
              <p class="mb-4">Relatório com dados de vendas, produtos mais vendidos e desempenho geral.</p>
            </div>
          </div>

          <div class="row">
            <!-- Card Resumo Operacional -->
            <div class="col-lg-12 mb-4">
              <div class="card">
                <div class="card-body">
                  <div class="row align-items-center mb-3">
                    <div class="col-md-8 col-7 mb-2 mb-md-0">
                      <h5 class="card-title mb-0">Resumo Operacional</h5>
                    </div>
                    <div class="col-md-4 col-5 text-md-end">
                      <form method="get" action="" id="form-filtro-periodo" class="d-inline-block w-100 w-md-auto">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($idSelecionado) ?>">
                        <div class="input-group">
                          <select class="form-select" id="filtro_periodo" name="filtro_periodo">
                            <option value="">Selecione o período</option>
                            <option value="dia" <?= ($filtro_periodo === 'dia') ? 'selected' : '' ?>>Hoje</option>
                            <option value="semana" <?= ($filtro_periodo === 'semana') ? 'selected' : '' ?>>Esta Semana
                            </option>
                            <option value="mes" <?= ($filtro_periodo === 'mes') ? 'selected' : '' ?>>Este Mês</option>
                            <option value="ano" <?= ($filtro_periodo === 'ano') ? 'selected' : '' ?>>Este Ano</option>
                            <option value="personalizar" <?= ($filtro_periodo === 'personalizar') ? 'selected' : '' ?>>
                              Personalizar</option>
                          </select>
                        </div>
                      </form>
                    </div>
                  </div>

                  <!-- Modal Personalizar -->
                  <div class="modal fade" id="modalPersonalizar" tabindex="-1" aria-labelledby="modalPersonalizarLabel"
                    aria-hidden="true">
                    <div class="modal-dialog">
                      <form method="get" action="" class="modal-content" id="form-personalizar">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($idSelecionado) ?>">
                        <input type="hidden" name="filtro_periodo" value="personalizar">
                        <div class="modal-header">
                          <h5 class="modal-title" id="modalPersonalizarLabel">Personalizar Período</h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                        </div>
                        <div class="modal-body">
                          <div class="mb-3">
                            <label for="data_inicio" class="form-label">Data Início</label>
                            <input type="date" class="form-control" id="data_inicio" name="data_inicio"
                              value="<?= htmlspecialchars($data_inicio) ?>" required>
                          </div>
                          <div class="mb-3">
                            <label for="data_fim" class="form-label">Data Fim</label>
                            <input type="date" class="form-control" id="data_fim" name="data_fim"
                              value="<?= htmlspecialchars($data_fim) ?>" required>
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="submit" class="btn btn-primary">Filtrar</button>
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        </div>
                      </form>
                    </div>
                  </div>

                  <div class="table-responsive text-nowrap mt-4">
                    <table class="table table-hover align-middle mb-0">
                      <thead class="table-light">
                        <tr>
                          <th style="width: 60%;">Indicador</th>
                          <th style="width: 40%;">Valor</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td>Total de Vendas</td>
                          <td><span class="fw-bold text-success">R$
                              <?= number_format($totalVendas, 2, ',', '.') ?></span></td>
                        </tr>
                        <tr>
                          <td>Produtos Mais Vendidos</td>
                          <td><?= $listaProdutos ?></td>
                        </tr>
                        <tr>
                          <td>Vendas Realizadas</td>
                          <td><span class="fw-bold"><?= $vendasRealizadas ?></span></td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <!-- Card Gráfico -->
            <div class="col-lg-12 mb-4">
              <div class="card">
                <div class="card-body">
                  <h5 class="card-title"><?= htmlspecialchars($tituloGrafico) ?></h5>
                  <div id="grafico-vendas-operacional" style="min-height: 300px;"></div>
                </div>
              </div>
            </div>
          </div>

          <script>
            document.addEventListener('DOMContentLoaded', function() {
              var modalPersonalizar = new bootstrap.Modal(document.getElementById('modalPersonalizar'));

              // Ao mudar o select, se for personalizar, abre modal, senão faz submit normal
              document.getElementById('filtro_periodo').addEventListener('change', function(e) {
                if (this.value === 'personalizar') {
                  e.preventDefault();
                  modalPersonalizar.show();
                } else if (this.value !== '') {
                  document.getElementById('form-filtro-periodo').submit();
                }
              });

              // Validar datas antes de submeter
              document.getElementById('form-personalizar').addEventListener('submit', function(e) {
                var dataInicio = document.getElementById('data_inicio').value;
                var dataFim = document.getElementById('data_fim').value;

                if (!dataInicio || !dataFim) {
                  e.preventDefault();
                  alert('Por favor, preencha ambas as datas.');
                  return false;
                }

                if (new Date(dataFim) < new Date(dataInicio)) {
                  e.preventDefault();
                  alert('A data final não pode ser anterior à data inicial.');
                  return false;
                }

                modalPersonalizar.hide();
                return true;
              });

              // Configurar gráfico
              var options = {
                chart: {
                  type: 'line',
                  height: 300,
                  toolbar: {
                    show: true,
                    tools: {
                      download: true,
                      selection: true,
                      zoom: true,
                      zoomin: true,
                      zoomout: true,
                      pan: true,
                      reset: true
                    }
                  }
                },
                series: [{
                  name: 'Vendas (R$)',
                  data: <?= json_encode($valoresGrafico) ?>
                }],
                xaxis: {
                  categories: <?= json_encode($categoriasGrafico) ?>,
                  labels: {
                    style: {
                      fontSize: '12px'
                    }
                  }
                },
                yaxis: {
                  labels: {
                    formatter: function(value) {
                      return 'R$ ' + value.toLocaleString('pt-BR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                      });
                    }
                  }
                },
                colors: ['#696cff'],
                stroke: {
                  width: 3
                },
                markers: {
                  size: 5
                },
                dataLabels: {
                  enabled: false
                },
                grid: {
                  borderColor: '#e0e0e0'
                },
                tooltip: {
                  y: {
                    formatter: function(value) {
                      return 'R$ ' + value.toLocaleString('pt-BR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                      });
                    }
                  }
                }
              };

              var chart = new ApexCharts(document.querySelector("#grafico-vendas-operacional"), options);
              chart.render();
            });
          </script>

        </div>
        <!-- / Content -->

        <div class="content-backdrop fade"></div>

      </div>
      <!-- Content wrapper -->

    </div>
    <!-- / Layout page -->

  </div>

  <!-- Overlay -->
  <div class="layout-overlay layout-menu-toggle"></div>
  </div>
  <!-- / Layout wrapper -->

  <!-- Core JS -->
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
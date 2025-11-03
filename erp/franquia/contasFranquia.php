<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

/* ==================== Sessão & parâmetros ==================== */
$idSelecionado = $_GET['id'] ?? '';
if (!$idSelecionado) {
  header("Location: .././login.php");
  exit;
}

/* ==================== Login obrigatório ==================== */
if (!isset($_SESSION['usuario_logado'], $_SESSION['empresa_id'], $_SESSION['tipo_empresa'], $_SESSION['usuario_id'])) {
  header("Location: .././login.php?id=" . urlencode($idSelecionado));
  exit;
}

/* ==================== Conexão ==================== */
require '../../assets/php/conexao.php';

/* ==================== Usuário logado ==================== */
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
$usuario_id  = (int)$_SESSION['usuario_id'];
try {
  $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
  $stmt->execute([':id' => $usuario_id]);
  if ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $nomeUsuario = $u['usuario'];
    $tipoUsuario = ucfirst($u['nivel']);
  } else {
    echo "<script>alert('Usuário não encontrado.'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
    exit;
  }
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar usuário: " . $e->getMessage() . "'); history.back();</script>";
  exit;
}

/* ==================== Permissão ==================== */
$acessoPermitido = false;
$idEmpresaSession  = $_SESSION['empresa_id'];
$tipoSession       = $_SESSION['tipo_empresa'];
if (str_starts_with($idSelecionado, 'principal_')) $acessoPermitido = ($tipoSession === 'principal' && $idEmpresaSession === $idSelecionado);
elseif (str_starts_with($idSelecionado, 'filial_')) $acessoPermitido = ($tipoSession === 'filial' && $idEmpresaSession === $idSelecionado);
elseif (str_starts_with($idSelecionado, 'unidade_')) $acessoPermitido = ($tipoSession === 'unidade' && $idEmpresaSession === $idSelecionado);
elseif (str_starts_with($idSelecionado, 'franquia_')) $acessoPermitido = ($tipoSession === 'franquia' && $idEmpresaSession === $idSelecionado);
if (!$acessoPermitido) {
  echo "<script>alert('Acesso negado!'); window.location.href = '.././login.php?id=" . urlencode($idSelecionado) . "';</script>";
  exit;
}

/* ==================== Logo ==================== */
try {
  $s = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :i LIMIT 1");
  $s->execute([':i' => $idSelecionado]);
  $sobre = $s->fetch(PDO::FETCH_ASSOC);
  $logoEmpresa = !empty($sobre['imagem']) ? "../../assets/img/empresa/" . $sobre['imagem'] : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
  $logoEmpresa = "../../assets/img/favicon/logo.png";
}

/* ==================== CSRF (mudar status) ==================== */
if (empty($_SESSION['csrf_pagto_status'])) $_SESSION['csrf_pagto_status'] = bin2hex(random_bytes(32));
$csrfStatus = $_SESSION['csrf_pagto_status'];

/* ----------------------- Autocomplete AJAX handler ----------------------- */
if (isset($_GET['ajax_search']) && $_GET['ajax_search'] == '1') {
  $term = trim((string)($_GET['term'] ?? ''));
  $out = [];
  if ($term !== '') {
    $sqlA = "
        SELECT sp.ID as id, sp.fornecedor, sp.documento, sp.descricao, COALESCE(u.nome,'') as unidade_nome, sp.valor, sp.id_solicitante
        FROM solicitacoes_pagamento sp
        LEFT JOIN unidades u ON u.id = CAST(SUBSTRING_INDEX(sp.id_solicitante,'_',-1) AS UNSIGNED)
        WHERE sp.id_matriz=:id_matriz AND u.tipo=:tipo
          AND (sp.fornecedor LIKE :t OR sp.documento LIKE :t OR sp.descricao LIKE :t OR u.nome LIKE :t OR sp.id_solicitante LIKE :t)
        ORDER BY sp.created_at DESC LIMIT 15";
    try {
      $stm = $pdo->prepare($sqlA);
      $like = "%{$term}%";
      $stm->execute([':id_matriz' => $idSelecionado, ':tipo' => 'Franquia', ':t' => $like]);
      foreach ($stm->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $label = trim(sprintf(
          "%s · %s · %s · %s",
          $r['id_solicitante'],
          $r['unidade_nome'] ?: '—',
          $r['fornecedor'] ?: '—',
          $r['documento'] ?: '—'
        ));
        $out[] = [
          'id' => (int)$r['id'],
          'label' => $label,
          'fornecedor' => $r['fornecedor'],
          'documento' => $r['documento'],
          'unidade' => $r['unidade_nome'],
          'solicitante' => $r['id_solicitante'], // usado hidden
          'display' => sprintf("%s · %s · %s · %s", $r['unidade_nome'], $r['fornecedor'], $r['documento'], $r['descricao'] ? $r['descricao'] : '—')
        ];
      }
    } catch (PDOException $e) {
      $out = [];
    }
  }
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($out);
  exit;
}

/* ==================== Filtros ==================== */
$status = $_GET['status'] ?? '';
$dtIni  = $_GET['venc_ini'] ?? '';
$dtFim  = $_GET['venc_fim'] ?? '';
$q      = trim($_GET['q'] ?? '');
$params = [':id_matriz' => $idSelecionado, ':tipo' => 'Franquia'];
$where  = ["sp.id_matriz=:id_matriz", "u.tipo=:tipo"];
if ($status !== '' && in_array($status, ['pendente', 'aprovado', 'reprovado'], true)) {
  $where[] = "sp.status=:status";
  $params[':status'] = $status;
}
if ($dtIni !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dtIni)) {
  $where[] = "sp.vencimento>=:vini";
  $params[':vini'] = $dtIni;
}
if ($dtFim !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dtFim)) {
  $where[] = "sp.vencimento<=:vfim";
  $params[':vfim'] = $dtFim;
}
if ($q !== '') {
  $where[] = "(sp.fornecedor LIKE :q OR sp.documento LIKE :q OR sp.descricao LIKE :q OR sp.id_solicitante LIKE :q OR u.nome LIKE :q)";
  $params[':q'] = "%$q%";
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

/* ==================== Consulta principal ==================== */
$sql = "
SELECT sp.ID as id_solicitacao, sp.id_solicitante, sp.status, sp.fornecedor, sp.documento, sp.descricao, sp.vencimento, sp.valor, sp.comprovante_url, sp.created_at as criado_em,
u.id AS unidade_id, u.nome AS unidade_nome, u.tipo AS unidade_tipo
FROM solicitacoes_pagamento sp
LEFT JOIN unidades u ON u.id = CAST(SUBSTRING_INDEX(sp.id_solicitante,'_',-1) AS UNSIGNED)
$whereSql
ORDER BY sp.created_at DESC, sp.ID DESC
";
$rows = [];
try {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  echo "<script>alert('Erro ao carregar solicitações: " . htmlspecialchars($e->getMessage(), ENT_QUOTES) . "'); history.back();</script>";
  exit;
}

function badgeStatus(string $s): string
{
  $s = strtolower($s);
  if ($s === 'aprovado') return '<span class="badge bg-label-success status-badge">APROVADO</span>';
  if ($s === 'reprovado') return '<span class="badge bg-label-danger status-badge">REPROVADO</span>';
  return '<span class="badge bg-label-warning status-badge">PENDENTE</span>';
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8" />
  <title>Pagamentos Solicitados</title>
  <link rel="stylesheet" href="../../assets/vendor/css/core.css" />
  <style>
    .autocomplete-list {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      max-height: 260px;
      overflow: auto;
      background: #fff;
      border: 1px solid #e6e9ef;
      border-radius: .5rem;
      z-index: 2060;
    }

    .autocomplete-item {
      padding: .5rem .75rem;
      cursor: pointer;
      display: flex;
      justify-content: space-between;
      gap: .5rem;
    }

    .autocomplete-item:hover,
    .autocomplete-item.active {
      background: #f5f7fb;
    }

    .autocomplete-item .left {
      flex: 1;
      overflow: hidden;
      white-space: nowrap;
      text-overflow: ellipsis;
    }

    .autocomplete-item .right {
      flex: 0 0 auto;
      text-align: right;
      font-size: .75rem;
      color: #6b7280;
    }
  </style>
</head>

<body>
  <form id="formFiltro" method="get">
    <input type="hidden" name="id" value="<?= htmlspecialchars($idSelecionado, ENT_QUOTES) ?>">
    <input type="hidden" id="hiddenSolicitante" name="solicitante" value="">
    <div class="autocomplete">
      <input type="text" id="q" name="q" autocomplete="off" placeholder="Buscar..." value="<?= htmlspecialchars($q, ENT_QUOTES) ?>">
      <div id="autocomplete-list" class="autocomplete-list d-none"></div>
    </div>
  </form>

  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Unidade</th>
        <th>Fornecedor</th>
        <th>Documento</th>
        <th>Anexo</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r):
        $comprovante = $r['comprovante_url'] ?? '';
        $comprovante_nome = $comprovante ? preg_replace('/^\d{12}_\d{6}_[a-f0-9]{8}_/', '', $comprovante) : '—'; // remove timestamp
      ?>
        <tr>
          <td><?= (int)$r['id_solicitacao'] ?></td>
          <td><?= htmlspecialchars($r['unidade_nome'] ?? '—') ?></td>
          <td><?= htmlspecialchars($r['fornecedor'] ?? '—') ?></td>
          <td><?= htmlspecialchars($r['documento'] ?? '—') ?></td>
          <td><?php if ($comprovante): ?><a href="<?= $comprovante ?>" target="_blank"><?= htmlspecialchars($comprovante_nome) ?></a><?php else: ?>—<?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <script>
    (function() {
      const inputQ = document.getElementById('q');
      const hidden = document.getElementById('hiddenSolicitante');
      const listBox = document.getElementById('autocomplete-list');
      let debounceTimer = null;
      inputQ.addEventListener('input', function() {
        const v = this.value.trim();
        if (debounceTimer) clearTimeout(debounceTimer);
        if (v.length === 0) {
          listBox.classList.add('d-none');
          listBox.innerHTML = '';
          return;
        }
        debounceTimer = setTimeout(() => fetchSuggestions(v), 250);
      });

      function fetchSuggestions(term) {
        const url = new URL(window.location.href);
        url.searchParams.set('ajax_search', '1');
        url.searchParams.set('term', term);
        fetch(url.toString(), {
            credentials: 'same-origin'
          })
          .then(r => r.json()).then(data => renderSuggestions(data)).catch(e => {
            listBox.classList.add('d-none');
            listBox.innerHTML = '';
          });
      }

      function renderSuggestions(list) {
        listBox.innerHTML = '';
        if (!list || !list.length) {
          listBox.classList.add('d-none');
          return;
        }
        list.forEach(it => {
          const row = document.createElement('div');
          row.className = 'autocomplete-item';
          row.tabIndex = 0;
          const left = document.createElement('div');
          left.className = 'left';
          left.textContent = it.display || it.label;
          const right = document.createElement('div');
          right.className = 'right autocomplete-tag';
          right.textContent = it.unidade || '';
          row.appendChild(left);
          row.appendChild(right);
          row.addEventListener('click', () => {
            inputQ.value = it.display || it.label;
            hidden.value = it.solicitante;
            listBox.classList.add('d-none');
            document.getElementById('formFiltro').submit();
          });
          row.addEventListener('keydown', ev => {
            if (ev.key === 'Enter') row.click();
          });
          listBox.appendChild(row);
        });
        listBox.classList.remove('d-none');
      }
      document.addEventListener('click', e => {
        if (!e.target.closest('.autocomplete')) listBox.classList.add('d-none');
      });
    })();
  </script>
</body>

</html>
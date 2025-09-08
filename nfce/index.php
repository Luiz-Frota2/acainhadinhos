<?php
declare(strict_types=1);
ini_set('display_errors','1');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
date_default_timezone_set('America/Manaus');

/* ===== Conexão DIRETA ===== */
$host     = 'localhost';
$dbname   = 'u920914488_ERP';
$username = 'u920914488_ERP';
$password = 'N8r=$&Wrs$';

try {
  $pdo = new PDO(
    "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
    $username,
    $password,
    [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ]
  );
} catch (PDOException $e) {
  http_response_code(500);
  exit('Erro na conexão: '.$e->getMessage());
}

/* ===== Entrada ===== */
$vendaId  = isset($_GET['venda_id']) ? (int)$_GET['venda_id'] : 0;
$autoEmit = isset($_GET['auto_emit']) ? (int)$_GET['auto_emit'] : 0;

$venda = null;
$itens = [];
$total = 0.0;
$cpfConsumidor = '';

if ($vendaId > 0) {
  // Cabeçalho da venda
  $st = $pdo->prepare("SELECT id, responsavel, cpf_responsavel, cpf_cliente, forma_pagamento, valor_total, valor_recebido, troco, empresa_id, id_caixa, data_venda
                       FROM vendas WHERE id=:id LIMIT 1");
  $st->execute([':id'=>$vendaId]);
  $venda = $st->fetch();

  if ($venda) {
    $cpfConsumidor = preg_replace('/\D+/', '', (string)($venda['cpf_cliente'] ?? ''));
    // Itens
    $sti = $pdo->prepare("SELECT produto_id, produto_nome, quantidade, preco_unitario, ncm, cfop, unidade, informacoes_adicionais, cest, origem, tributacao
                          FROM itens_venda WHERE venda_id=:id ORDER BY id");
    $sti->execute([':id'=>$vendaId]);
    while ($r = $sti->fetch()) {
      $qtd = (float)$r['quantidade'];
      $vun = (float)$r['preco_unitario'];
      $linTotal = $qtd * $vun;
      $total += $linTotal;

      // estrutura simples para emitir.php (desc, qtd, vun)
      $itens[] = [
        'desc' => (string)$r['produto_nome'],
        'qtd'  => $qtd,
        'vun'  => $vun,
        // extras (não quebram emitir.php; pode ignorar lá ou usar se desejar)
        'ncm'   => $r['ncm'] ?: null,
        'cfop'  => $r['cfop'] ?: null,
        'unid'  => $r['unidade'] ?: null,
        'info'  => $r['informacoes_adicionais'] ?: null,
        'cest'  => $r['cest'] ?: null,
        'origem'=> $r['origem'] ?: null,
        'trib'  => $r['tributacao'] ?: null,
      ];
    }
  }
}

function brl($v){ return number_format((float)$v, 2, ',', '.'); }
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Revisão da Venda #<?= htmlspecialchars((string)$vendaId) ?> • NFC-e</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root { --b:#e8e8e8; --tx:#222; --mut:#666; }
  body{font-family:system-ui,Arial; color:var(--tx); margin:20px; max-width:1000px}
  h1,h2,h3{margin:.2rem 0}
  .head{display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap}
  .card{border:1px solid var(--b); border-radius:10px; padding:14px; margin:12px 0; background:#fff}
  table{width:100%; border-collapse:collapse}
  th,td{border:1px solid var(--b); padding:8px; text-align:left; vertical-align:top}
  th{background:#fafafa}
  .right{text-align:right}
  .mut{color:var(--mut); font-size:13px}
  .row{display:flex; gap:12px; flex-wrap:wrap}
  .col{flex:1 1 280px}
  .btn{padding:10px 14px; border:1px solid #333; background:#fff; border-radius:8px; cursor:pointer; font-weight:600}
  .btn:hover{background:#f5f5f5}
  .total{font-size:18px; font-weight:700}
  .badge{display:inline-block; padding:2px 8px; border:1px solid var(--b); border-radius:999px; background:#f9f9f9}
  .hidden{display:none}
</style>
</head>
<body>

<div class="head">
  <div>
    <h2>NFC-e • Revisão da Venda</h2>
    <div class="mut">Confirme os itens e emita a nota fiscal.</div>
  </div>
  <?php if ($vendaId): ?>
    <div class="badge">Venda #<?= htmlspecialchars((string)$vendaId) ?></div>
  <?php endif; ?>
</div>

<?php if (!$vendaId || !$venda): ?>
  <div class="card">
    <p><strong>Nenhuma venda carregada.</strong></p>
    <p class="mut">Acesse esta página com <code>?venda_id=123</code> (e opcionalmente <code>&auto_emit=1</code>).</p>
  </div>
<?php else: ?>

  <div class="card row">
    <div class="col">
      <h3>Dados da venda</h3>
      <div><strong>Data:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$venda['data_venda']))) ?></div>
      <div><strong>Responsável:</strong> <?= htmlspecialchars((string)$venda['responsavel']) ?></div>
      <div><strong>Forma de Pagamento (cód.):</strong> <?= htmlspecialchars((string)$venda['forma_pagamento']) ?></div>
      <div><strong>Empresa ID:</strong> <?= htmlspecialchars((string)$venda['empresa_id']) ?> &middot; <strong>Caixa ID:</strong> <?= htmlspecialchars((string)$venda['id_caixa']) ?></div>
    </div>
    <div class="col">
      <h3>Consumidor</h3>
      <div><strong>CPF:</strong> <?= $cpfConsumidor ? htmlspecialchars($cpfConsumidor) : '<span class="mut">não informado</span>' ?></div>
      <div><strong>Recebido:</strong> R$ <?= brl($venda['valor_recebido']) ?> &middot; <strong>Troco:</strong> R$ <?= brl($venda['troco']) ?></div>
      <div class="total" style="margin-top:6px;">Total venda: R$ <?= brl($total ?: $venda['valor_total']) ?></div>
    </div>
  </div>

  <div class="card">
    <h3 style="margin-bottom:8px;">Itens</h3>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Descrição</th>
          <th class="right">Qtd</th>
          <th class="right">V. Unit</th>
          <th class="right">V. Total</th>
          <th>NCM</th>
          <th>CFOP</th>
          <th>UN</th>
          <th>Info</th>
        </tr>
      </thead>
      <tbody>
      <?php
        if (empty($itens)) {
          echo '<tr><td colspan="9" class="mut">Sem itens encontrados para esta venda.</td></tr>';
        } else {
          $i=1;
          foreach ($itens as $linha) {
            $linTot = ((float)$linha['qtd']) * ((float)$linha['vun']);
            echo '<tr>';
            echo '<td>'.($i++).'</td>';
            echo '<td>'.htmlspecialchars((string)$linha['desc']).'</td>';
            echo '<td class="right">'.htmlspecialchars((string)(float)$linha['qtd']).'</td>';
            echo '<td class="right">R$ '.brl($linha['vun']).'</td>';
            echo '<td class="right">R$ '.brl($linTot).'</td>';
            echo '<td>'.htmlspecialchars((string)($linha['ncm'] ?? '')).'</td>';
            echo '<td>'.htmlspecialchars((string)($linha['cfop'] ?? '')).'</td>';
            echo '<td>'.htmlspecialchars((string)($linha['unid'] ?? '')).'</td>';
            echo '<td>'.htmlspecialchars((string)($linha['info'] ?? '')).'</td>';
            echo '</tr>';
          }
        }
      ?>
      </tbody>
    </table>
  </div>

  <form id="fEmit" class="card" method="post" action="emitir.php">
    <input type="hidden" name="itens" id="itens" value="<?= htmlspecialchars(json_encode($itens, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?>">
    <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap">
      <label for="cpf"><strong>CPF do consumidor (opcional):</strong></label>
      <input id="cpf" name="cpf" type="text" inputmode="numeric" pattern="\d*" placeholder="Somente dígitos" value="<?= htmlspecialchars($cpfConsumidor) ?>" style="padding:8px; min-width:220px;">
      <button class="btn" type="submit">Emitir NFC-e</button>
      <span class="mut">A emissão abrirá a resposta do SEFAZ (ou DANFE/QR-Code, conforme seu <code>emitir.php</code>).</span>
    </div>
  </form>

<?php endif; ?>

<script>
(function(){
  const autoEmit = <?= (int)$autoEmit ?>;
  const temItens = <?= json_encode(!empty($itens)) ?>;
  if (autoEmit && temItens) {
    // pequena espera só para garantir renderização na tela antes do envio
    setTimeout(()=>{ document.getElementById('fEmit')?.submit(); }, 200);
  }
})();
</script>

</body>
</html>

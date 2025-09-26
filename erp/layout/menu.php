<?php
/**
 * autoErp/public/dashboard/partials/menu_Aside.php
 *
 * Componente <aside> extraído para include.
 * - Requer $idSelecionado definido na página que inclui (caso não, tenta buscar do GET)
 * - Opcional: $empresaNome (exibe o nome no cabeçalho do menu). Default: "Açaínhadinhos".
 * - Mantém os mesmos ícones/classes do HTML original.
 */

if (!isset($idSelecionado) || $idSelecionado === '') {
  $idSelecionado = isset($_GET['id']) ? (string)$_GET['id'] : '';
}

$empresaNome = isset($empresaNome) && $empresaNome !== '' ? $empresaNome : 'Açaínhadinhos';

// Helpers simples
$__buildUrl = function (string $path) use ($idSelecionado): string {
  $sep = (strpos($path, '?') === false) ? '?' : '&';
  return $path . $sep . 'id=' . urlencode($idSelecionado);
};

$__isActive = function (array $matches) : bool {
  $current = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
  if ($current === '' || $current === false) {
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
  }
  foreach ($matches as $m) {
    if (strcasecmp(trim($current), trim($m)) === 0) return true;
  }
  return false;
};

$__activeClass = function (array $files) use ($__isActive): string {
  return $__isActive($files) ? ' active' : '';
};

$__openClass = function (array $files) use ($__isActive): string {
  return $__isActive($files) ? ' open' : '';
};
?>

<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
  <div class="app-brand demo">
    <a href="<?= htmlspecialchars($__buildUrl('./index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="app-brand-link">
      <span class="app-brand-text demo menu-text fw-bolder ms-2" style="text-transform: capitalize;">
        <?= htmlspecialchars($empresaNome, ENT_QUOTES, 'UTF-8'); ?>
      </span>
    </a>

    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
      <i class="bx bx-chevron-left bx-sm align-middle"></i>
    </a>
  </div>

  <div class="menu-inner-shadow"></div>

  <ul class="menu-inner py-1">
    <!-- Dashboard -->
    <li class="menu-item<?= $__activeClass(['index.php']); ?>">
      <a href="<?= htmlspecialchars($__buildUrl('./index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="menu-link">
        <i class="menu-icon tf-icons bx bx-home-circle"></i>
        <div data-i18n="Analytics">Dashboard</div>
      </a>
    </li>

    <!-- Administração de Filiais -->
    <li class="menu-header small text-uppercase">
      <span class="menu-header-text">Administração Franquias</span>
    </li>

    <?php
      $franquiasChildren = ['franquiaAdicionada.php'];
      $franquiasOpen = $__openClass($franquiasChildren);
      $franquiasActive = $__isActive($franquiasChildren) ? ' active' : '';
    ?>
    <li class="menu-item<?= $franquiasActive . ' ' . $franquiasOpen; ?>">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon tf-icons bx bx-building"></i>
        <div data-i18n="Adicionar">Franquias</div>
      </a>
      <ul class="menu-sub">
        <li class="menu-item<?= $__activeClass(['franquiaAdicionada.php']); ?>">
          <a href="<?= htmlspecialchars($__buildUrl('./franquiaAdicionada.php'), ENT_QUOTES, 'UTF-8'); ?>" class="menu-link">
            <div data-i18n="Filiais">Adicionadas</div>
          </a>
        </li>
      </ul>
    </li>

    <?php
      $b2bChildren = [
        'contasFiliais.php',
        'produtosSolicitados.php',
        'produtosEnviados.php',
        'transferenciasPendentes.php',
        'historicoTransferencias.php',
        'estoqueMatriz.php',
        'politicasEnvio.php',
        'relatoriosB2B.php',
      ];
      $b2bOpen = $__openClass($b2bChildren);
      $b2bActive = $__isActive($b2bChildren) ? ' active' : '';
    ?>
    <li class="menu-item<?= $b2bActive . ' ' . $b2bOpen; ?>">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon tf-icons bx bx-briefcase"></i>
        <div data-i18n="B2B">B2B - Matriz</div>
      </a>
      <ul class="menu-sub">
        <li class="menu-item<?= $__activeClass(['contasFiliais.php']); ?>">
          <a href="<?= htmlspecialchars($__buildUrl('./contasFiliais.php'), ENT_QUOTES, 'UTF-8'); ?>" class="menu-link">
            <div>Pagamentos Solic.</div>
          </a>
        </li>
        <li class="menu-item<?= $__activeClass(['produtosSolicitados.php']); ?>">
          <a href="<?= htmlspecialchars($__buildUrl('./produtosSolicitados.php'), ENT_QUOTES, 'UTF-8'); ?>" class="menu-link">
            <div>Produtos Solicitados</div>
          </a>
        </li>
        <li class="menu-item<?= $__activeClass(['produtosEnviados.php']); ?>">
          <a href="<?= htmlspecialchars($__buildUrl('./produtosEnviados.php'), ENT_QUOTES, 'UTF-8'); ?>" class="menu-link">
            <div>Produtos Enviados</div>
          </a>
        </li>
        <li class="menu-item<?= $__activeClass(['transferenciasPendentes.php']); ?>">
          <a href="<?= htmlspecialchars($__buildUrl('./transferenciasPendentes.php'), ENT_QUOTES, 'UTF-8'); ?>" class="menu-link">
            <div>Transf. Pendentes</div>
          </a>
        </li>
        <li class="menu-item<?= $__activeClass(['historicoTransferencias.php']); ?>">
          <a href="<?= htmlspecialchars($__buildUrl('./historicoTransferencias.php'), ENT_QUOTES, 'UTF-8'); ?>" class="menu-link">
            <div>Histórico Transf.</div>
          </a>
        </li>
        <li class="menu-item<?= $__activeClass(['estoqueMatriz.php']); ?>">
          <a href="<?= htmlspecialchars($__buildUrl('./estoqueMatriz.php'), ENT_QUOTES, 'UTF-8'); ?>" class="menu-link">
            <div>Estoque Matriz</div>
          </a>
        </li>
        <li class="menu-item<?= $__activeClass(['politicasEnvio.php']); ?>">
          <a href="<?= htmlspecialchars($__buildUrl('./politicasEnvio.php'), ENT_QUOTES, 'UTF-8'); ?>" class="menu-link">
            <div>Política de Envio</div>
          </a>
        </li>
        <li class="menu-item<?= $__activeClass(['relatoriosB2B.php']); ?>">
          <a href="<?= htmlspecialchars($__buildUrl('./relatoriosB2B.php'), ENT_QUOTES, 'UTF-8'); ?>" class="menu-link">
            <div>Relatórios B2B</div>
          </a>
        </li>
      </ul>
    </li>

    <!-- Relatórios -->
    <?php
      $relChildren = ['VendasFiliais.php','MaisVendidos.php','vendasPeriodo.php','FinanceiroFranquia.php'];
      $relOpen = $__openClass($relChildren);
      $relActive = $__isActive($relChildren) ? ' active' : '';
    ?>
    <li class="menu-item<?= $relActive . ' ' . $relOpen; ?>">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon tf-icons bx bx-bar-chart-alt-2"></i>
        <div data-i18n="Relatorios">Relatórios</div>
      </a>
      <ul class="menu-sub">
        <li class="menu-item<?= $__activeClass(['VendasFiliais.php']); ?>">
          <a href="<?= htmlspecialchars($__buildUrl('./VendasFiliais.php'), ENT_QUOTES, 'UTF-8'); ?>" class="menu-link">
            <div data-i18n="Vendas">Vendas por Franquias</div>
          </a>
        </li>
        <li class="menu-item<?= $__activeClass(['MaisVendidos.php']); ?>">
          <a href="<?= htmlspecialchars($__buildUrl('./MaisVendidos.php'), ENT_QUOTES, 'UTF-8'); ?>" class="menu-link">
            <div data-i18n="MaisVendidos">Mais Vendidos</div>
          </a>
        </li>
        <li class="menu-item<?= $__activeClass(['vendasPeriodo.php']); ?>">
          <a href="<?= htmlspecialchars($__buildUrl('./vendasPeriodo.php'), ENT_QUOTES, 'UTF-8'); ?>" class="menu-link">
            <div data-i18n="Pedidos">Vendas por Período</div>
          </a>
        </li>
        <li class="menu-item<?= $__activeClass(['FinanceiroFranquia.php']); ?>">
          <a href="<?= htmlspecialchars($__buildUrl('./FinanceiroFranquia.php'), ENT_QUOTES, 'UTF-8'); ?>" class="menu-link">
            <div data-i18n="Financeiro">Financeiro</div>
          </a>
        </li>
      </ul>
    </li>

    <!-- Diversos -->
    <li class="menu-header small text-uppercase"><span class="menu-header-text">Diversos</span></li>

    <li class="menu-item<?= $__activeClass(['index.php']); ?>">
      <a href="<?= htmlspecialchars($__buildUrl('../rh/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="menu-link">
        <i class="menu-icon tf-icons bx bx-group"></i>
        <div data-i18n="Authentications">RH</div>
      </a>
    </li>

    <li class="menu-item">
      <a href="<?= htmlspecialchars($__buildUrl('../financas/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="menu-link">
        <i class="menu-icon tf-icons bx bx-dollar"></i>
        <div data-i18n="Authentications">Finanças</div>
      </a>
    </li>

    <li class="menu-item">
      <a href="<?= htmlspecialchars($__buildUrl('../pdv/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="menu-link">
        <i class="menu-icon tf-icons bx bx-desktop"></i>
        <div data-i18n="Authentications">PDV</div>
      </a>
    </li>

    <li class="menu-item">
      <a href="<?= htmlspecialchars($__buildUrl('../empresa/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="menu-link">
        <i class="menu-icon tf-icons bx bx-briefcase"></i>
        <div data-i18n="Authentications">Empresa</div>
      </a>
    </li>

    <li class="menu-item">
      <a href="<?= htmlspecialchars($__buildUrl('../estoque/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="menu-link">
        <i class="menu-icon tf-icons bx bx-box"></i>
        <div data-i18n="Authentications">Estoque</div>
      </a>
    </li>

    <li class="menu-item">
      <a href="<?= htmlspecialchars($__buildUrl('../filial/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="menu-link">
        <i class="menu-icon tf-icons bx bx-building"></i>
        <div data-i18n="Authentications">Filial</div>
      </a>
    </li>

    <li class="menu-item">
      <a href="<?= htmlspecialchars($__buildUrl('../usuarios/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="menu-link">
        <i class="menu-icon tf-icons bx bx-group"></i>
        <div data-i18n="Authentications">Usuários</div>
      </a>
    </li>

    <li class="menu-item">
      <a href="https://wa.me/92991515710" target="_blank" class="menu-link" rel="noopener noreferrer">
        <i class="menu-icon tf-icons bx bx-support"></i>
        <div data-i18n="Basic">Suporte</div>
      </a>
    </li>
  </ul>
</aside>

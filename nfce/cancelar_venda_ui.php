<?php
// Captura empresa_id e venda_id de forma flexível
$__empresa_id = $_GET['id'] ?? $_GET['empresa_id'] ?? $_REQUEST['id'] ?? $_REQUEST['empresa_id'] ?? '';
$__venda_id   = $_GET['venda_id'] ?? $_REQUEST['venda_id'] ?? ($_SESSION['venda_id'] ?? '');
$__chave      = isset($chave) ? preg_replace('/\D+/', '', (string)$chave) : '';
?>
<style>
  /* ===== Modal básica (sem libs) ===== */
  #cv-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, .45);
    z-index: 9998;
  }

  .cv-modal {
    display: none;
    position: fixed;
    z-index: 9999;
    inset: 0;
    place-items: center;
  }

  .cv-card {
    width: min(560px, 92vw);
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, .25);
    overflow: hidden;
    font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, "Helvetica Neue", Arial;
  }

  .cv-head {
    padding: 14px 18px;
    font-size: 18px;
    font-weight: 700;
    background: #0d6efd;
    color: #fff;
  }

  .cv-body {
    padding: 18px;
  }

  .cv-row {
    margin-bottom: 12px;
  }

  .cv-row label {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
  }

  .cv-row input[type=text],
  .cv-row textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #cfd6df;
    border-radius: 10px;
    outline: 0;
    font-size: 15px;
  }

  .cv-row input[readonly] {
    background: #f6f7fb;
  }

  .cv-foot {
    padding: 14px 18px;
    background: #f9fafc;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    border-top: 1px solid #eef2f6;
  }

  .cv-btn {
    appearance: none;
    border: 0;
    border-radius: 10px;
    padding: 10px 14px;
    font-weight: 700;
    cursor: pointer;
  }

  .cv-btn.primary {
    background: #0d6efd;
    color: #fff;
  }

  .cv-btn.danger {
    background: #dc3545;
    color: #fff;
  }

  .cv-btn.secondary {
    background: #e9ecef;
    color: #111;
  }

  .cv-choices {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
  }

  .cv-choice {
    border: 1px solid #cfd6df;
    border-radius: 12px;
    padding: 14px;
    cursor: pointer;
    transition: transform .08s ease;
  }

  .cv-choice:hover {
    transform: translateY(-1px);
    background: #f8f9ff;
  }

  .cv-choice .title {
    font-weight: 800;
  }

  .cv-choice .desc {
    opacity: .85;
    font-size: 14px;
    margin-top: 4px;
  }

  @media (min-width:680px) {
    .cv-choices {
      grid-template-columns: 1fr 1fr 1fr;
    }
  }

  .cv-note {
    font-size: 12.5px;
    opacity: .85;
    margin-top: 8px;
  }
</style>

<div id="cv-overlay"></div>

<!-- Modal 1: Escolha do modelo -->
<div id="cv-modal-escolha" class="cv-modal" role="dialog" aria-modal="true" aria-labelledby="cv-escolha-title">
  <div class="cv-card">
    <div class="cv-head" id="cv-escolha-title">Cancelar venda</div>
    <div class="cv-body">
      <div class="cv-row" style="margin-top:-6px; margin-bottom:16px;">
        <div style="font-size:14px; opacity:.9">Escolha como deseja cancelar esta NFC-e.</div>
      </div>
      <div class="cv-choices">
        <div class="cv-choice" data-modelo="por_chave">
          <div class="title">Cancelamento Padrão (110111)</div>
          <div class="desc">Cancela a NFC-e já autorizada — confirma com os <b>4 últimos dígitos</b> da chave.</div>
        </div>
        <div class="cv-choice" data-modelo="por_substituicao">
          <div class="title">Por Substituição (110112)</div>
          <div class="desc">Informe a <b>chave da NFC-e substituta</b> (contingência) para cancelar esta NFC-e.</div>
        </div>
        <div class="cv-choice" data-modelo="por_motivo">
          <div class="title">Outro modelo (interno)</div>
          <div class="desc">Cancela apenas no sistema (sem evento fiscal), solicitando o <b>motivo</b>.</div>
        </div>
      </div>
      <div class="cv-note">Dica: use <b>Substituição (110112)</b> quando emitiu outra NFC-e para a mesma operação.</div>
    </div>
    <div class="cv-foot">
      <button type="button" class="cv-btn secondary" data-cv-close>Fechar</button>
    </div>
  </div>
</div>

<!-- Modal 2: Por CHAVE -->
<div id="cv-modal-chave" class="cv-modal" role="dialog" aria-modal="true" aria-labelledby="cv-chave-title">
  <div class="cv-card">
    <div class="cv-head" id="cv-chave-title">Cancelar pela CHAVE</div>
    <form id="cv-form-chave" action="cancelar_venda_processa.php" method="post" class="no-print">
      <div class="cv-body">
        <div class="cv-row">
          <label>Chave (44 dígitos)</label>
          <input type="text" name="chave" id="cv-chave" value="<?= htmlspecialchars($__chave) ?>" readonly>
        </div>
        <div class="cv-row">
          <label>Confirmação</label>
          <input type="text" name="last4" id="cv-last4" maxlength="4" placeholder="Digite os 4 últimos dígitos da chave" autocomplete="off" required>
        </div>
        <input type="hidden" name="modelo" value="por_chave">
        <input type="hidden" name="empresa_id" value="<?= htmlspecialchars($__empresa_id) ?>">
        <input type="hidden" name="venda_id" value="<?= htmlspecialchars($__venda_id) ?>">
      </div>
      <div class="cv-foot">
        <button type="button" class="cv-btn secondary" data-cv-back>Voltar</button>
        <button type="submit" class="cv-btn danger">Cancelar NFC-e (110111)</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal 3: Interno por motivo -->
<div id="cv-modal-motivo" class="cv-modal" role="dialog" aria-modal="true" aria-labelledby="cv-motivo-title">
  <div class="cv-card">
    <div class="cv-head" id="cv-motivo-title">Cancelar — Outro modelo (interno)</div>
    <form id="cv-form-motivo" action="cancelar_venda_processa.php" method="post" class="no-print">
      <div class="cv-body">
        <div class="cv-row">
          <label>Motivo do cancelamento</label>
          <textarea name="motivo" id="cv-motivo" rows="4" placeholder="Ex.: Cliente desistiu; erro de lançamento; etc." required></textarea>
        </div>
        <input type="hidden" name="modelo" value="por_motivo">
        <input type="hidden" name="chave" value="<?= htmlspecialchars($__chave) ?>">
        <input type="hidden" name="empresa_id" value="<?= htmlspecialchars($__empresa_id) ?>">
        <input type="hidden" name="venda_id" value="<?= htmlspecialchars($__venda_id) ?>">
      </div>
      <div class="cv-foot">
        <button type="button" class="cv-btn secondary" data-cv-back>Voltar</button>
        <button type="submit" class="cv-btn danger">Confirmar cancelamento interno</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal 4: Substituição -->
<div id="cv-modal-subst" class="cv-modal" role="dialog" aria-modal="true" aria-labelledby="cv-subst-title">
  <div class="cv-card">
    <div class="cv-head" id="cv-subst-title">Cancelar por Substituição (110112)</div>
    <form id="cv-form-subst" action="cancelar_venda_processa.php" method="post" class="no-print">
      <div class="cv-body">
        <div class="cv-row">
          <label>Chave a cancelar (44 dígitos)</label>
          <input type="text" name="chave" id="cv-chave3" value="<?= htmlspecialchars($__chave) ?>" readonly>
        </div>
        <div class="cv-row">
          <label>Chave substituta (44 dígitos)</label>
          <input type="text" name="chave_substituta" id="cv-chave-subst" maxlength="44" placeholder="Informe a chave da NFC-e substituta" autocomplete="off" required>
        </div>
        <div class="cv-row">
          <label>Motivo (opcional)</label>
          <textarea name="motivo" id="cv-motivo-subst" rows="3" placeholder="Ex.: Duplicidade de emissão/contingência"></textarea>
        </div>
        <input type="hidden" name="modelo" value="por_substituicao">
        <input type="hidden" name="empresa_id" value="<?= htmlspecialchars($__empresa_id) ?>">
        <input type="hidden" name="venda_id" value="<?= htmlspecialchars($__venda_id) ?>">
      </div>
      <div class="cv-foot">
        <button type="button" class="cv-btn secondary" data-cv-back>Voltar</button>
        <button type="submit" class="cv-btn danger">Cancelar por Substituição (110112)</button>
      </div>
    </form>
  </div>
</div>

<script>
  (function() {
    var btnAbrir = document.getElementById('nfce-cancelar');
    var overlay = document.getElementById('cv-overlay');
    var mEscolha = document.getElementById('cv-modal-escolha');
    var mChave = document.getElementById('cv-modal-chave');
    var mMotivo = document.getElementById('cv-modal-motivo');
    var mSubst = document.getElementById('cv-modal-subst');
    var last4Input = document.getElementById('cv-last4');
    var formChave = document.getElementById('cv-form-chave');
    var formMotivo = document.getElementById('cv-form-motivo');
    var formSubst = document.getElementById('cv-form-subst');
    var chaveSubst = document.getElementById('cv-chave-subst');

    function openModal(el) {
      overlay.style.display = 'block';
      el.style.display = 'grid';
    }

    function closeAll() {
      overlay.style.display = 'none';
      mEscolha.style.display = 'none';
      mChave.style.display = 'none';
      mMotivo.style.display = 'none';
      mSubst.style.display = 'none';
    }

    if (btnAbrir) btnAbrir.addEventListener('click', function() {
      openModal(mEscolha);
    });

    document.querySelectorAll('[data-cv-close]').forEach(function(el) {
      el.addEventListener('click', closeAll);
    });
    document.querySelectorAll('[data-cv-back]').forEach(function(el) {
      el.addEventListener('click', function() {
        mChave.style.display = 'none';
        mMotivo.style.display = 'none';
        mSubst.style.display = 'none';
        openModal(mEscolha);
      });
    });

    document.querySelectorAll('.cv-choice').forEach(function(card) {
      card.addEventListener('click', function() {
        var modelo = card.getAttribute('data-modelo');
        mEscolha.style.display = 'none';
        if (modelo === 'por_chave') {
          openModal(mChave);
          setTimeout(function() {
            last4Input && last4Input.focus();
          }, 120);
        } else if (modelo === 'por_motivo') {
          openModal(mMotivo);
          setTimeout(function() {
            var m = document.getElementById('cv-motivo');
            m && m.focus();
          }, 120);
        } else {
          openModal(mSubst);
          setTimeout(function() {
            chaveSubst && chaveSubst.focus();
          }, 120);
        }
      });
    });

    if (formChave) {
      formChave.addEventListener('submit', function(ev) {
        var last4 = (last4Input.value || '').replace(/\D+/g, '');
        var chv = (document.getElementById('cv-chave').value || '').replace(/\D+/g, '');
        if (chv.length !== 44) {
          ev.preventDefault();
          alert('Chave inválida (44 dígitos).');
          return false;
        }
        if (last4.length !== 4) {
          ev.preventDefault();
          alert('Digite os 4 últimos dígitos da chave.');
          return false;
        }
        if (chv.slice(-4) !== last4) {
          ev.preventDefault();
          alert('Os 4 últimos dígitos não conferem.');
          return false;
        }
      });
    }

    if (formMotivo) {
      formMotivo.addEventListener('submit', function(ev) {
        var motivo = (document.getElementById('cv-motivo').value || '').trim();
        if (!motivo) {
          ev.preventDefault();
          alert('Informe o motivo do cancelamento.');
          return false;
        }
      });
    }

    if (formSubst) {
      formSubst.addEventListener('submit', function(ev) {
        var chv = (document.getElementById('cv-chave3').value || '').replace(/\D+/g, '');
        var sub = (document.getElementById('cv-chave-subst').value || '').replace(/\D+/g, '');
        if (chv.length !== 44) {
          ev.preventDefault();
          alert('Chave (original) inválida (44 dígitos).');
          return false;
        }
        if (sub.length !== 44) {
          ev.preventDefault();
          alert('Chave substituta inválida (44 dígitos).');
          return false;
        }
        if (sub === chv) {
          ev.preventDefault();
          alert('A chave substituta deve ser diferente da chave a cancelar.');
          return false;
        }
      });
    }

    overlay.addEventListener('click', closeAll);

    // Expor cvOpen/cvClose para o DANFE chamar se precisar (fallback)
    window.cvOpen = function() {
      openModal(mEscolha);
    };
    window.cvClose = function() {
      closeAll();
    };
  })();
</script>
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
date_default_timezone_set('America/Sao_Paulo');

require '../conexao.php';

/* ========= Entrada (GET ou POST) ========= */
$from = ($_SERVER['REQUEST_METHOD'] === 'POST') ? $_POST : $_GET;

$idSelecionado = $from['id']  ?? '';
$sid           = (int)($from['sid'] ?? 0);
$acao          = trim($from['acao'] ?? ''); // aprovar | enviar | (vazio)

if (!$idSelecionado || $sid <= 0) {
    echo "<script>alert('Parâmetros inválidos.'); history.back();</script>";
    exit;
}

/* ========= Sessão / Permissão ========= */
if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id'])
) {
    echo "<script>alert('Sessão expirada. Faça login novamente.'); history.back();</script>";
    exit;
}

$empresaSessao = $_SESSION['empresa_id'];
$tipoSessao    = $_SESSION['tipo_empresa'];

/* acesso somente quando a empresa da sessão confere com o id selecionado */
$permitido = false;
if (str_starts_with($idSelecionado, 'principal_')) {
    $permitido = ($tipoSessao === 'principal' && $empresaSessao === $idSelecionado);
} elseif (str_starts_with($idSelecionado, 'filial_')) {
    $permitido = ($tipoSessao === 'filial' && $empresaSessao === $idSelecionado);
} elseif (str_starts_with($idSelecionado, 'unidade_')) {
    $permitido = ($tipoSessao === 'unidade' && $empresaSessao === $idSelecionado);
} elseif (str_starts_with($idSelecionado, 'franquia_')) {
    $permitido = ($tipoSessao === 'franquia' && $empresaSessao === $idSelecionado);
}

if (!$permitido) {
    echo "<script>alert('Acesso negado.'); history.back();</script>";
    exit;
}

try {
    /* ========= Carrega solicitação ========= */
    $st = $pdo->prepare("
    SELECT id, id_matriz, id_solicitante, status, aprovada_em, enviada_em
    FROM solicitacoes_b2b
    WHERE id = :sid
    LIMIT 1
  ");
    $st->execute([':sid' => $sid]);
    $sol = $st->fetch(PDO::FETCH_ASSOC);

    if (!$sol) {
        echo "<script>alert('Solicitação não encontrada.'); history.back();</script>";
        exit;
    }
    if ($sol['id_matriz'] !== $idSelecionado) {
        echo "<script>alert('Solicitação não pertence à empresa atual.'); history.back();</script>";
        exit;
    }

    $statusAtual    = $sol['status'];
    $idMatriz       = $sol['id_matriz'];      // ex: principal_1
    $idSolicitante  = $sol['id_solicitante']; // ex: franquia_3 / filial_2 / unidade_1

    /* ====== Regras de quando processar ======
   * - acao=aprovar + status pendente -> aprovar e mover estoque (marcar em_transito)
   * - acao=enviar  + status aprovada -> mover estoque (marcar em_transito)
   * - sem acao     + status aprovada -> mover estoque (compatibilidade)
   */
    $podeProcessar = false;
    $marcarAprovadaAgora = false;

    if ($acao === 'aprovar' && $statusAtual === 'pendente') {
        $podeProcessar = true;
        $marcarAprovadaAgora = true; // grava aprovada_em
    } elseif ($acao === 'enviar' && $statusAtual === 'aprovada') {
        $podeProcessar = true;
    } elseif ($acao === '' && $statusAtual === 'aprovada') {
        $podeProcessar = true;
    } else {
        $msg = "Ação/Status incompatíveis. Ação: " . ($acao ?: '(nenhuma)') . " | Status atual: {$statusAtual}.";
        echo "<script>alert('{$msg}'); history.back();</script>";
        exit;
    }

    /* ========= Carrega itens ========= */
    $it = $pdo->prepare("
    SELECT id, produto_id, codigo_produto, nome_produto, unidade, preco_unitario, quantidade, subtotal
    FROM solicitacoes_b2b_itens
    WHERE solicitacao_id = :sid
    ORDER BY id ASC
  ");
    $it->execute([':sid' => $sid]);
    $itens = $it->fetchAll(PDO::FETCH_ASSOC);

    if (!$itens) {
        echo "<script>alert('Solicitação sem itens para processar.'); history.back();</script>";
        exit;
    }

    /* ========= Transação ========= */
    $pdo->beginTransaction();

    /* Se veio aprovar agora, marque como aprovada antes de mexer no estoque (para consistência de histórico). */
    if ($marcarAprovadaAgora) {
        $upA = $pdo->prepare("
      UPDATE solicitacoes_b2b
      SET status='aprovada', aprovada_em = NOW(), updated_at = NOW()
      WHERE id = :sid AND id_matriz = :matriz AND status='pendente'
    ");
        $upA->execute([':sid' => $sid, ':matriz' => $idMatriz]);
        if ($upA->rowCount() === 0) {
            $pdo->rollBack();
            echo "<script>alert('Falha ao marcar como aprovada (status incompatível).'); history.back();</script>";
            exit;
        }
        $statusAtual = 'aprovada';
    }

    /* ========= Movimenta estoque ========= */
    foreach ($itens as $item) {
        $codigo = $item['codigo_produto'];
        $qtd    = (int)$item['quantidade'];

        if ($qtd <= 0) {
            $pdo->rollBack();
            echo "<script>alert('Quantidade inválida para o item {$codigo}.'); history.back();</script>";
            exit;
        }

        /* 1) Buscar produto na MATRIZ e lockar */
        $src = $pdo->prepare("
      SELECT *
      FROM estoque
      WHERE empresa_id = :emp AND codigo_produto = :cod
      FOR UPDATE
    ");
        $src->execute([':emp' => $idMatriz, ':cod' => $codigo]);
        $prodSrc = $src->fetch(PDO::FETCH_ASSOC);

        if (!$prodSrc) {
            $pdo->rollBack();
            echo "<script>alert('Produto {$codigo} não encontrado no estoque da matriz ({$idMatriz}).'); history.back();</script>";
            exit;
        }

        $qtdAtualSrc = (int)$prodSrc['quantidade_produto'];
        if ($qtdAtualSrc < $qtd) {
            $pdo->rollBack();
            $disp = (int)$qtdAtualSrc;
            echo "<script>alert('Estoque insuficiente do produto {$codigo} na matriz. Disponível: {$disp}, solicitado: {$qtd}.'); history.back();</script>";
            exit;
        }

        /* 2) Baixar da MATRIZ */
        $updSrc = $pdo->prepare("
      UPDATE estoque
      SET quantidade_produto = quantidade_produto - :qtd,
          updated_at = NOW()
      WHERE id = :id
    ");
        $updSrc->execute([':qtd' => $qtd, ':id' => $prodSrc['id']]);

        /* 3) Somar no DESTINO (franquia/filial/unidade) */
        $selDst = $pdo->prepare("
      SELECT *
      FROM estoque
      WHERE empresa_id = :emp AND codigo_produto = :cod
      FOR UPDATE
    ");
        $selDst->execute([':emp' => $idSolicitante, ':cod' => $codigo]);
        $prodDst = $selDst->fetch(PDO::FETCH_ASSOC);

        if ($prodDst) {
            $updDst = $pdo->prepare("
        UPDATE estoque
        SET quantidade_produto = quantidade_produto + :qtd,
            updated_at = NOW()
        WHERE id = :id
      ");
            $updDst->execute([':qtd' => $qtd, ':id' => $prodDst['id']]);
        } else {
            $ins = $pdo->prepare("
        INSERT INTO estoque (
          fornecedor_id, empresa_id, codigo_produto, nome_produto, categoria_produto,
          quantidade_produto, preco_produto, preco_custo, status_produto, ncm, cest, cfop,
          origem, tributacao, unidade, codigo_barras, codigo_anp, informacoes_adicionais,
          peso_bruto, peso_liquido, aliquota_icms, aliquota_pis, aliquota_cofins, created_at, updated_at
        ) VALUES (
          :fornecedor_id, :empresa_id, :codigo_produto, :nome_produto, :categoria_produto,
          :quantidade_produto, :preco_produto, :preco_custo, :status_produto, :ncm, :cest, :cfop,
          :origem, :tributacao, :unidade, :codigo_barras, :codigo_anp, :informacoes_adicionais,
          :peso_bruto, :peso_liquido, :aliquota_icms, :aliquota_pis, :aliquota_cofins, NOW(), NOW()
        )
      ");
            $ins->execute([
                ':fornecedor_id'          => (int)$prodSrc['fornecedor_id'],
                ':empresa_id'             => $idSolicitante,
                ':codigo_produto'         => $prodSrc['codigo_produto'],
                ':nome_produto'           => $prodSrc['nome_produto'],
                ':categoria_produto'      => $prodSrc['categoria_produto'],
                ':quantidade_produto'     => $qtd,
                ':preco_produto'          => $prodSrc['preco_produto'],
                ':preco_custo'            => $prodSrc['preco_custo'],
                ':status_produto'         => $prodSrc['status_produto'],
                ':ncm'                    => $prodSrc['ncm'],
                ':cest'                   => $prodSrc['cest'],
                ':cfop'                   => $prodSrc['cfop'],
                ':origem'                 => $prodSrc['origem'],
                ':tributacao'             => $prodSrc['tributacao'],
                ':unidade'                => $prodSrc['unidade'],
                ':codigo_barras'          => $prodSrc['codigo_barras'],
                ':codigo_anp'             => $prodSrc['codigo_anp'],
                ':informacoes_adicionais' => $prodSrc['informacoes_adicionais'],
                ':peso_bruto'             => $prodSrc['peso_bruto'],
                ':peso_liquido'           => $prodSrc['peso_liquido'],
                ':aliquota_icms'          => $prodSrc['aliquota_icms'],
                ':aliquota_pis'           => $prodSrc['aliquota_pis'],
                ':aliquota_cofins'        => $prodSrc['aliquota_cofins'],
            ]);
        }
    }

    /* ========= Status final: EM TRÂNSITO =========
   * Se você preferir manter como 'aprovada' quando a ação for 'aprovar', comente a parte de status abaixo
   * e deixe apenas atualizar 'aprovada_em'. No fluxo atual, ao mover estoque já marcamos como em_transito.
   */
    $upSol = $pdo->prepare("
    UPDATE solicitacoes_b2b
    SET status = 'em_transito',
        enviada_em = IFNULL(enviada_em, NOW()),
        updated_at = NOW()
    WHERE id = :sid AND id_matriz = :matriz
  ");
    $upSol->execute([':sid' => $sid, ':matriz' => $idMatriz]);

    $pdo->commit();

    $url = "../../../erp/franquia/produtosSolicitados.php?id=" . rawurlencode($idSelecionado);
    $msgSucesso = "Solicitação #{$sid} processada com sucesso! Estoque movido para {$idSolicitante} e status definido para EM TRÂNSITO.";
    echo "<script>alert('{$msgSucesso}'); window.location.href='{$url}';</script>";
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $msg = addslashes($e->getMessage());
    echo "<script>alert('Erro ao processar: {$msg}'); history.back();</script>";
    exit;
}

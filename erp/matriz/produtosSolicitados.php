<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// ====== ID selecionado
$idSelecionado = $_GET['id'] ?? '';
if (!$idSelecionado) {
    header("Location: .././login.php");
    exit;
}

// ====== Login básico
if (!isset($_SESSION['usuario_logado'], $_SESSION['empresa_id'], $_SESSION['tipo_empresa'], $_SESSION['usuario_id'])) {
    header("Location: .././login.php?id=" . urlencode($idSelecionado));
    exit;
}

// ====== Conexão
require '../../assets/php/conexao.php';

// ====== Logo
try {
    $s = $pdo->prepare("SELECT imagem FROM sobre_empresa WHERE id_selecionado = :i LIMIT 1");
    $s->execute([':i' => $idSelecionado]);
    $sobre = $s->fetch(PDO::FETCH_ASSOC);
    $logoEmpresa = !empty($sobre['imagem']) ? "../../assets/img/empresa/" . $sobre['imagem'] : "../../assets/img/favicon/logo.png";
} catch (PDOException $e) {
    $logoEmpresa = "../../assets/img/favicon/logo.png";
}

// ====== Dados do usuário
$usuario_id = (int)$_SESSION['usuario_id'];
$nomeUsuario = 'Usuário';
$tipoUsuario = 'Comum';
try {
    $stmt = $pdo->prepare("SELECT usuario, nivel FROM contas_acesso WHERE id = :id");
    $stmt->execute([':id' => $usuario_id]);
    if ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $nomeUsuario = $u['usuario'];
        $tipoUsuario = ucfirst($u['nivel']);
    }
} catch (PDOException $e) {
}

// ====== Solicitações (franquia / filial / unidade)
$solicitacoes = [];
try {
    $sql = "SELECT id, id_matriz, id_solicitante, status, total_estimado, created_at, aprovada_em, enviada_em, entregue_em
            FROM solicitacoes_b2b
            WHERE id_solicitante = :id
            ORDER BY created_at DESC";
    $st = $pdo->prepare($sql);
    $st->execute([':id' => $idSelecionado]);
    $solicitacoes = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $solicitacoes = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8" />
    <title>ERP - Minhas Solicitações</title>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($logoEmpresa) ?>" />
    <link rel="stylesheet" href="../../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../../assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="../../assets/css/demo.css" />
    <link rel="stylesheet" href="../../assets/vendor/fonts/boxicons.css" />
    <style>
        .card {
            border-radius: 14px;
        }

        .table thead th {
            font-weight: 600;
            color: #6b7280;
            white-space: nowrap;
        }

        .badge {
            border-radius: 10px;
            padding: .25rem .5rem;
            font-size: .8rem;
        }

        .badge-pendente {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-aprovada {
            background: #dcfce7;
            color: #166534;
        }

        .badge-reprovada {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-em_transito {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-entregue {
            background: #e0f2fe;
            color: #075985;
        }

        .badge-cancelada {
            background: #f3f4f6;
            color: #374151;
        }
    </style>
</head>

<body>
    <div class="container-xxl flex-grow-1 container-p-y">
        <h4 class="fw-bold">Minhas Solicitações</h4>

        <div class="card mt-3">
            <div class="card-body table-responsive">
                <table class="table table-hover" id="tabelaSolicitacoes">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Status</th>
                            <th>Total Estimado</th>
                            <th>Criada em</th>
                            <th>Aprovada em</th>
                            <th>Enviada em</th>
                            <th>Entregue em</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($solicitacoes): ?>
                            <?php foreach ($solicitacoes as $s): ?>
                                <tr>
                                    <td><?= (int)$s['id'] ?></td>
                                    <td>
                                        <span class="badge badge-<?= htmlspecialchars($s['status']) ?>">
                                            <?= ucfirst($s['status']) ?>
                                        </span>
                                    </td>
                                    <td>R$ <?= number_format((float)$s['total_estimado'], 2, ',', '.') ?></td>
                                    <td><?= htmlspecialchars($s['created_at']) ?></td>
                                    <td><?= htmlspecialchars($s['aprovada_em'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($s['enviada_em'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($s['entregue_em'] ?? '-') ?></td>
                                    <td>
                                        <a href="./detalheSolicitacao.php?id=<?= urlencode($idSelecionado) ?>&sol=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            Ver Itens
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">Nenhuma solicitação encontrada.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>
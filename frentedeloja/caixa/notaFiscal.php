<?php
// Ativar exibição de erros para depuração
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();

// Verificar se os parâmetros obrigatórios estão presentes
if (!isset($_GET['id']) || !isset($_GET['venda_id'])) {
    die("Parâmetros obrigatórios não fornecidos (id e venda_id)");
}

$idSelecionado = $_GET['id'] ?? '';
$venda_id = $_GET['venda_id'] ?? '';
$danfe_url = $_GET['danfe_url'] ?? null;

$host = 'localhost'; // ou o IP do servidor de banco de dados
$dbname = 'u920914488_ERP'; // Nome do banco de dados
$username = 'u920914488_ERP'; // Seu nome de usuário do banco de dados
$password = 'N8r=$&Wrs$'; // Sua senha do banco de dados

try {
    // Cria uma instância PDO para conexão com o banco de dados
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    
    // Configura o PDO para lançar exceções em caso de erro
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    
    // Corrigido: estava 'empresa_id' quando deveria ser 'empresas'
    $stmt = $pdo->prepare("SELECT v.* 
                          FROM vendas v 
                          WHERE v.id = ? AND v.empresa_id = ?");
    $stmt->execute([$venda_id, $idSelecionado]);
    $venda = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$venda) {
        throw new Exception("Venda não encontrada para o ID $venda_id e empresa $idSelecionado");
    }

    $stmt = $pdo->prepare("SELECT * FROM itens_venda WHERE venda_id = ?");
    $stmt->execute([$venda_id]);
    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro de banco de dados: " . $e->getMessage());
} catch (Exception $e) {
    die($e->getMessage());
}

function formatCpf($cpf) {
    if (strlen($cpf) == 11) {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
    return $cpf;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota Fiscal | Sistema de PDV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .invoice-card {
            border: 1px solid #dee2e6;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            border-radius: 0.375rem;
        }
        .invoice-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .invoice-title {
            color: #2c3e50;
            font-weight: 600;
        }
        .invoice-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .btn-danfe {
            background-color: #dc3545;
            color: white;
            transition: all 0.3s;
        }
        .btn-danfe:hover {
            background-color: #c82333;
            transform: translateY(-2px);
        }
        .total-box {
            background-color: #f8f9fa;
            border-radius: 0.375rem;
            padding: 1rem;
            font-weight: 600;
        }
        .text-small {
            font-size: 0.875rem;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card invoice-card mb-4">
                    <div class="card-header invoice-header py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h1 class="h4 invoice-title mb-0">
                                <i class="bi bi-receipt"></i> Nota Fiscal
                            </h1>
                            <span class="badge bg-success">Venda Concluída</span>
                        </div>
                    </div>
                    
                    <div class="card-body p-4">
                        <!-- Cabeçalho com dados da empresa e venda -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h4 class="mb-3"><?php echo htmlspecialchars($venda['nome_fantasia'] ?? ''); ?></h4>
                                <p class="text-muted mb-1">
                                    <i class="bi bi-building"></i> <?php echo htmlspecialchars($idSelecionado); ?>
                                </p>
                                <p class="text-muted mb-1">
                                    <i class="bi bi-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($venda['data_venda'] ?? 'now')); ?>
                                </p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <h4 class="mb-3">Venda #<?php echo htmlspecialchars($venda_id); ?></h4>
                                <p class="text-muted mb-1">
                                    <i class="bi bi-person"></i> <?php echo htmlspecialchars($venda['responsavel'] ?? ''); ?>
                                </p>
                                <?php if (!empty($venda['cpf_cliente'])): ?>
                                <p class="text-muted mb-1">
                                    <i class="bi bi-person-check"></i> CPF: <?php echo formatCpf($venda['cpf_cliente']); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <!-- Itens da venda -->
                        <div class="table-responsive mb-4">
                            <table class="table invoice-table">
                                <thead>
                                    <tr>
                                        <th width="5%">#</th>
                                        <th width="45%">Produto</th>
                                        <th width="10%" class="text-center">Qtd</th>
                                        <th width="10%" class="text-center">Unid.</th>
                                        <th width="15%" class="text-end">Preço Unit.</th>
                                        <th width="15%" class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($itens as $index => $item): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($item['produto_nome']); ?>
                                            <?php if (!empty($item['informacoes_adicionais'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($item['informacoes_adicionais']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"><?php echo $item['quantidade']; ?></td>
                                        <td class="text-center"><?php echo $item['unidade']; ?></td>
                                        <td class="text-end">R$ <?php echo number_format($item['preco_unitario'], 2, ',', '.'); ?></td>
                                        <td class="text-end">R$ <?php echo number_format($item['preco_unitario'] * $item['quantidade'], 2, ',', '.'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Totais e forma de pagamento -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded mb-3">
                                    <h5 class="mb-3">Forma de Pagamento</h5>
                                    <p class="mb-1">
                                        <strong><?php echo htmlspecialchars($venda['forma_pagamento']); ?></strong>
                                    </p>
                                    <?php if ($venda['valor_recebido'] > 0): ?>
                                    <p class="mb-1 text-small">
                                        Valor recebido: R$ <?php echo number_format($venda['valor_recebido'], 2, ',', '.'); ?>
                                    </p>
                                    <?php endif; ?>
                                    <?php if ($venda['troco'] > 0): ?>
                                    <p class="mb-0 text-small">
                                        Troco: R$ <?php echo number_format($venda['troco'], 2, ',', '.'); ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="total-box text-end">
                                    <p class="mb-1">Subtotal: R$ <?php echo number_format($venda['valor_total'], 2, ',', '.'); ?></p>
                                    <p class="mb-1">Descontos: R$ 0,00</p>
                                    <hr class="my-2">
                                    <h4 class="mb-0">Total: R$ <?php echo number_format($venda['valor_total'], 2, ',', '.'); ?></h4>
                                </div>
                            </div>
                        </div>
                        
                        <!-- DANFE -->
                        <?php if ($danfe_url): ?>
                            <div class="text-center py-4 mt-3">
                                <hr class="my-4">
                                <h4 class="mb-3"><i class="bi bi-file-earmark-pdf"></i> Documento Fiscal</h4>
                                <p class="text-muted mb-4">A NFC-e foi emitida com sucesso e está disponível para visualização ou download</p>
                                <a href="<?php echo htmlspecialchars($danfe_url); ?>" target="_blank" class="btn btn-danfe btn-lg me-2">
                                    <i class="bi bi-eye"></i> Visualizar DANFE
                                </a>
                                <a href="<?php echo htmlspecialchars($danfe_url); ?>" download class="btn btn-outline-secondary btn-lg">
                                    <i class="bi bi-download"></i> Baixar PDF
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning text-center mt-4">
                                <i class="bi bi-exclamation-triangle"></i> Documento fiscal não gerado ou indisponível.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Botões de ação -->
                <div class="d-grid gap-2 d-md-flex justify-content-md-center mb-5">
                    <a href="/frentedeloja/caixa/vendas.php?id=<?php echo urlencode($idSelecionado); ?>" class="btn btn-outline-secondary btn-lg me-md-3">
                        <i class="bi bi-list-ul"></i> Histórico de Vendas
                    </a>
                    <a href="/frentedeloja/caixa/" class="btn btn-primary btn-lg">
                        <i class="bi bi-cash-stack"></i> Nova Venda
                    </a>
                </div>
                
                
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


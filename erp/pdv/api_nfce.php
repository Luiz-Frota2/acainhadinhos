<?php
// api_nfce.php - Processamento direto da NFC-e
require_once __DIR__ . '../../../frentedeloja/caixa/vendor/autoload.php';
require '../../assets/php/conexao.php';

// Se chamado via HTTP, processa como API
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_HOST'])) {
    header('Content-Type: application/json');
    
    try {
        // Valida os dados recebidos
        $required_fields = [
            'empresa_id', 'responsavel', 'cpf_responsavel', 
            'forma_pagamento', 'valor_recebido', 'troco', 'produtos'
        ];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Campo obrigatório faltando: $field");
            }
        }
        
        // Prepara os dados para processamento
        $dados_api = [
            'empresa_id' => 'principal_1',
            'responsavel' => $_POST['responsavel'],
            'cpf_responsavel' => $_POST['cpf_responsavel'],
            'cpf_cliente' => $_POST['cpf_cliente'] ?? '',
            'forma_pagamento' => $_POST['forma_pagamento'],
            'valor_recebido' => (float)$_POST['valor_recebido'],
            'troco' => (float)$_POST['troco'],
            'produtos' => $_POST['produtos']
        ];
        
        // Processa a NFC-e
        $resultado = processarNFCe($dados_api);
        
        // Retorna a resposta
        echo json_encode($resultado);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    
    exit;
}

/**
 * Função principal para processamento da NFC-e
 * Pode ser chamada diretamente ou via HTTP
 * 
 * @param array $dados Dados da venda
 * @return array Resultado do processamento
 */
function processarNFCe($dados) {
    global $pdo;
    
    // Validação básica dos dados
    if (empty($dados['empresa_id'])) {
        throw new Exception('Empresa não especificada');
    }
    
    if (empty($dados['produtos']) || !is_array($dados['produtos'])) {
        throw new Exception('Nenhum produto válido na venda');
    }
    
    // Busca dados da empresa
    $dadosEmpresa = buscarDadosEmpresa($dados['empresa_id']);
    
    // Busca caixa aberto
    $id_caixa = buscarCaixaAberto($dados['empresa_id']);
    
    // Processa os produtos
    $produtos_info = processarProdutos($dados['produtos'], $dados['empresa_id']);
    
    // Calcula total da venda
    $total_venda = calcularTotalVenda($produtos_info);
    
    // Simula a emissão da NFC-e
    $nfce_emitida = simularEmissaoNFCe($dadosEmpresa, $total_venda);
    
    // Registra no banco de dados
    $venda_id = registrarVenda($dados, $produtos_info, $total_venda, $id_caixa);
    registrarNFCe($venda_id, $dados['empresa_id'], $nfce_emitida, $dados);
    
    // Retorna o resultado
    return [
        'success' => true,
        'nfce' => $nfce_emitida,
        'venda' => [
            'id' => $venda_id,
            'total' => number_format($total_venda, 2, ',', '.'),
            'data' => date('d/m/Y H:i:s')
        ]
    ];
}

// Funções auxiliares

function buscarDadosEmpresa($empresa_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM integracao_nfce WHERE empresa_id = :empresa_id");
    $stmt->execute([':empresa_id' => $empresa_id]);
    $dados = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dados) {
        throw new Exception('Configuração de NFC-e não encontrada para esta empresa');
    }
    
    return $dados;
}

function buscarCaixaAberto($empresa_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT id FROM aberturas 
        WHERE empresa_id = :empresa_id AND status = 'aberto' 
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([':empresa_id' => $empresa_id]);
    $caixa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $caixa['id'] ?? 0;
}

function processarProdutos($produtos, $empresa_id) {
    global $pdo;
    
    $produtos_ids = [];
    foreach ($produtos as $produto) {
        if (!empty($produto['id'])) {
            $produtos_ids[] = $produto['id'];
        }
    }
    
    if (empty($produtos_ids)) {
        throw new Exception('Nenhum produto válido na venda');
    }
    
    $placeholders = implode(',', array_fill(0, count($produtos_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT id, nome_produto, preco_venda, ncm, cest, cfop, origem, tributacao, unidade 
        FROM estoque 
        WHERE id IN ($placeholders) AND empresa_id = ?
    ");
    
    $params = array_merge($produtos_ids, [$empresa_id]);
    $stmt->execute($params);
    $produtos_info = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($produtos_info)) {
        throw new Exception('Produtos não encontrados');
    }
    
    // Combina com as quantidades
    $resultado = [];
    foreach ($produtos as $prod_post) {
        foreach ($produtos_info as $prod_info) {
            if ($prod_info['id'] == $prod_post['id']) {
                $resultado[] = [
                    'id' => $prod_info['id'],
                    'nome' => $prod_info['nome_produto'],
                    'quantidade' => (float)($prod_post['quantidade'] ?? 1),
                    'preco' => (float)$prod_info['preco_venda'],
                    'ncm' => $prod_info['ncm'] ?? '',
                    'cest' => $prod_info['cest'] ?? '',
                    'cfop' => $prod_info['cfop'] ?? '5102',
                    'origem' => $prod_info['origem'] ?? '0',
                    'tributacao' => $prod_info['tributacao'] ?? '102',
                    'unidade' => $prod_info['unidade'] ?? 'UN'
                ];
                break;
            }
        }
    }
    
    return $resultado;
}

function calcularTotalVenda($produtos) {
    $total = 0;
    foreach ($produtos as $produto) {
        $total += $produto['quantidade'] * $produto['preco'];
    }
    return $total;
}

function simularEmissaoNFCe($dadosEmpresa, $total_venda) {
    $chave_acesso = str_pad(mt_rand(0, 99999999999999999999), 44, '0', STR_PAD_LEFT);
    $protocolo = str_pad(mt_rand(0, 999999999999999), 15, '0', STR_PAD_LEFT);
    $ultimo_numero = $dadosEmpresa['ultimo_numero_nfce'] + 1;
    
    // Atualiza o último número no banco
    atualizarUltimoNumeroNFCe($dadosEmpresa['empresa_id'], $ultimo_numero);
    
    return [
        'status' => 'autorizado',
        'chave_acesso' => $chave_acesso,
        'numero' => str_pad($ultimo_numero, 9, '0', STR_PAD_LEFT),
        'serie' => str_pad($dadosEmpresa['serie_nfce'], 3, '0', STR_PAD_LEFT),
        'modelo' => '65',
        'protocolo' => $protocolo,
        'data_emissao' => date('Y-m-d H:i:s'),
        'total_venda' => number_format($total_venda, 2, ',', '.'),
        'danfe_url' => 'https://api.sefaz.virtual/nfce/qrcode?p=' . $chave_acesso,
        'qrcode' => 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($chave_acesso)
    ];
}

function atualizarUltimoNumeroNFCe($empresa_id, $ultimo_numero) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE integracao_nfce 
            SET ultimo_numero_nfce = :ultimo_numero 
            WHERE empresa_id = :empresa_id
        ");
        $stmt->execute([
            ':ultimo_numero' => $ultimo_numero,
            ':empresa_id' => $empresa_id
        ]);
    } catch (PDOException $e) {
        error_log("Erro ao atualizar último número da NFC-e: " . $e->getMessage());
    }
}

function registrarVenda($dados, $produtos, $total, $id_caixa) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Registra a venda
        $stmt = $pdo->prepare("
            INSERT INTO vendas 
            (empresa_id, caixa_id, responsavel, cpf_responsavel, cpf_cliente, forma_pagamento, total, criado_em) 
            VALUES 
            (:empresa_id, :caixa_id, :responsavel, :cpf_responsavel, :cpf_cliente, :forma_pagamento, :total, NOW())
        ");
        
        $stmt->execute([
            ':empresa_id' => $dados['empresa_id'],
            ':caixa_id' => $id_caixa,
            ':responsavel' => $dados['responsavel'],
            ':cpf_responsavel' => $dados['cpf_responsavel'],
            ':cpf_cliente' => $dados['cpf_cliente'] ?? '',
            ':forma_pagamento' => $dados['forma_pagamento'],
            ':total' => $total
        ]);
        
        $venda_id = $pdo->lastInsertId();
        
        // Registra os itens da venda
        foreach ($produtos as $produto) {
            $stmt = $pdo->prepare("
                INSERT INTO venda_itens 
                (venda_id, produto_id, quantidade, preco_unitario, subtotal, criado_em) 
                VALUES 
                (:venda_id, :produto_id, :quantidade, :preco_unitario, :subtotal, NOW())
            ");
            
            $stmt->execute([
                ':venda_id' => $venda_id,
                ':produto_id' => $produto['id'],
                ':quantidade' => $produto['quantidade'],
                ':preco_unitario' => $produto['preco'],
                ':subtotal' => $produto['quantidade'] * $produto['preco']
            ]);
        }
        
        $pdo->commit();
        return $venda_id;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception('Erro ao registrar venda: ' . $e->getMessage());
    }
}

function registrarNFCe($venda_id, $empresa_id, $nfce_emitida, $dados_venda) {
    global $pdo;
    
    try {
        $xml_simulado = gerarXmlNfceSimulado($dados_venda, $nfce_emitida, $empresa_id);
        
        $stmt = $pdo->prepare("
            INSERT INTO nfce_emitidas 
            (venda_id, empresa_id, chave_acesso, numero, serie, modelo, protocolo, data_emissao, xml, status, metodo_emissao) 
            VALUES 
            (:venda_id, :empresa_id, :chave_acesso, :numero, :serie, :modelo, :protocolo, :data_emissao, :xml, :status, :metodo_emissao)
        ");
        
        $stmt->execute([
            ':venda_id' => $venda_id,
            ':empresa_id' => $empresa_id,
            ':chave_acesso' => $nfce_emitida['chave_acesso'],
            ':numero' => $nfce_emitida['numero'],
            ':serie' => $nfce_emitida['serie'],
            ':modelo' => $nfce_emitida['modelo'],
            ':protocolo' => $nfce_emitida['protocolo'],
            ':data_emissao' => $nfce_emitida['data_emissao'],
            ':xml' => $xml_simulado,
            ':status' => 'autorizado',
            ':metodo_emissao' => 'api'
        ]);
        
    } catch (PDOException $e) {
        throw new Exception('Erro ao registrar NFC-e: ' . $e->getMessage());
    }
}

function gerarXmlNfceSimulado($venda_data, $nfce_emitida, $empresa_id) {
    global $pdo;
    
    // Busca dados da empresa novamente para garantir que temos todos os dados necessários
    $dadosEmpresa = buscarDadosEmpresa($empresa_id);
    
    $xml = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml .= '<nfeProc versao="4.00" xmlns="http://www.portalfiscal.inf.br/nfe">';
    $xml .= '<NFe xmlns="http://www.portalfiscal.inf.br/nfe">';
    $xml .= '<infNFe Id="NFe' . $nfce_emitida['chave_acesso'] . '" versao="4.00">';
    
    // Dados da empresa
    $xml .= '<emit>';
    $xml .= '<CNPJ>' . $dadosEmpresa['cnpj'] . '</CNPJ>';
    $xml .= '<xNome>' . htmlspecialchars($dadosEmpresa['razao_social']) . '</xNome>';
    $xml .= '<xFant>' . htmlspecialchars($dadosEmpresa['nome_fantasia']) . '</xFant>';
    $xml .= '<IE>' . $dadosEmpresa['inscricao_estadual'] . '</IE>';
    $xml .= '<IM>' . ($dadosEmpresa['inscricao_municipal'] ?? '') . '</IM>';
    $xml .= '<CNAE>6202300</CNAE>';
    $xml .= '<CRT>' . $dadosEmpresa['regime_tributario'] . '</CRT>';
    $xml .= '</emit>';
    
    // Dados do cliente
    $xml .= '<dest>';
    $xml .= '<CPF>' . (!empty($venda_data['cpf_cliente']) ? $venda_data['cpf_cliente'] : '00000000000') . '</CPF>';
    $xml .= '<xNome>CONSUMIDOR FINAL</xNome>';
    $xml .= '<indIEDest>9</indIEDest>';
    $xml .= '</dest>';
    
    // Produtos (simplificado para exemplo)
    $xml .= '<det nItem="1">';
    $xml .= '<prod>';
    $xml .= '<cProd>1</cProd>';
    $xml .= '<xProd>Venda de mercadorias</xProd>';
    $xml .= '<NCM>21069090</NCM>';
    $xml .= '<CFOP>5102</CFOP>';
    $xml .= '<uCom>UN</uCom>';
    $xml .= '<qCom>1.0000</qCom>';
    $xml .= '<vUnCom>' . number_format($venda_data['total'], 2, '.', '') . '</vUnCom>';
    $xml .= '<vProd>' . number_format($venda_data['total'], 2, '.', '') . '</vProd>';
    $xml .= '<indTot>1</indTot>';
    $xml .= '</prod>';
    $xml .= '<imposto>';
    $xml .= '<vTotTrib>0.00</vTotTrib>';
    $xml .= '<ICMS>';
    $xml .= '<ICMS00>';
    $xml .= '<orig>0</orig>';
    $xml .= '<CST>00</CST>';
    $xml .= '<modBC>0</modBC>';
    $xml .= '<vBC>0.00</vBC>';
    $xml .= '<pICMS>0.00</pICMS>';
    $xml .= '<vICMS>0.00</vICMS>';
    $xml .= '</ICMS00>';
    $xml .= '</ICMS>';
    $xml .= '</imposto>';
    $xml .= '</det>';
    
    // Totalizadores
    $xml .= '<total>';
    $xml .= '<ICMSTot>';
    $xml .= '<vBC>0.00</vBC>';
    $xml .= '<vICMS>0.00</vICMS>';
    $xml .= '<vProd>' . number_format($venda_data['total'], 2, '.', '') . '</vProd>';
    $xml .= '<vDesc>0.00</vDesc>';
    $xml .= '<vNF>' . number_format($venda_data['total'], 2, '.', '') . '</vNF>';
    $xml .= '<vTotTrib>0.00</vTotTrib>';
    $xml .= '</ICMSTot>';
    $xml .= '</total>';
    
    // Forma de pagamento
    $xml .= '<pag>';
    $xml .= '<detPag>';
    $xml .= '<tPag>' . $venda_data['forma_pagamento'] . '</tPag>';
    $xml .= '<vPag>' . number_format($venda_data['valor_recebido'], 2, '.', '') . '</vPag>';
    $xml .= '</detPag>';
    $xml .= '<vTroco>' . number_format($venda_data['troco'], 2, '.', '') . '</vTroco>';
    $xml .= '</pag>';
    
    // Informações adicionais
    $xml .= '<infAdic>';
    $xml .= '<infCpl>';
    $xml .= 'Responsável: ' . htmlspecialchars($venda_data['responsavel']) . ' - CPF: ' . $venda_data['cpf_responsavel'];
    $xml .= '</infCpl>';
    $xml .= '</infAdic>';
    
    $xml .= '</infNFe>';
    $xml .= '</NFe>';
    $xml .= '<protNFe versao="4.00">';
    $xml .= '<infProt>';
    $xml .= '<tpAmb>1</tpAmb>';
    $xml .= '<verAplic>1.0</verAplic>';
    $xml .= '<chNFe>' . $nfce_emitida['chave_acesso'] . '</chNFe>';
    $xml .= '<dhRecbto>' . date('Y-m-d\TH:i:sP') . '</dhRecbto>';
    $xml .= '<nProt>' . $nfce_emitida['protocolo'] . '</nProt>';
    $xml .= '<digVal>' . md5($nfce_emitida['chave_acesso']) . '</digVal>';
    $xml .= '<cStat>100</cStat>';
    $xml .= '<xMotivo>Autorizado o uso da NF-e</xMotivo>';
    $xml .= '</infProt>';
    $xml .= '</protNFe>';
    $xml .= '</nfeProc>';
    
    return $xml;
}
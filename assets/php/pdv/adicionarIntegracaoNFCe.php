<?php
session_start();
require_once '../../conexao.php';

try {
    // Conexão PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verifica se é POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    // Prepara os dados
    $dados = [
        'empresa_id' => $_POST['empresa_id'],
        'cnpj' => preg_replace('/[^0-9]/', '', $_POST['cnpj']),
        'razao_social' => $_POST['razao_social'],
        'nome_fantasia' => $_POST['nome_fantasia'],
        'inscricao_estadual' => $_POST['inscricao_estadual'],
        'cep' => preg_replace('/[^0-9]/', '', $_POST['cep']),
        'logradouro' => $_POST['logradouro'],
        'numero_endereco' => $_POST['numero_endereco'],
        'complemento' => $_POST['complemento'] ?? '',
        'bairro' => $_POST['bairro'],
        'cidade' => $_POST['cidade'],
        'uf' => $_POST['uf'],
        'token_api' => $_POST['token_api'],
        'ambiente' => $_POST['ambiente'],
        'serie' => $_POST['serie'],
        'numero' => $_POST['numero'],
        'regime_tributario' => $_POST['regime_tributario'],
        'csc' => $_POST['csc'] ?? '',
        'id_token' => $_POST['id_token'] ?? '000001',
        'timeout' => $_POST['timeout'] ?? 30,
        'contingencia' => isset($_POST['contingencia']) ? 1 : 0,
        'salvar_xml' => isset($_POST['salvar_xml']) ? 1 : 0,
        'envio_email' => isset($_POST['envio_email']) ? 1 : 0
    ];

    // Processa certificado digital se enviado
    $certificadoInfo = [];
    if (!empty($_FILES['certificado_digital']['tmp_name'])) {
        $certificado = $_FILES['certificado_digital'];
        
        // Verifica se é um arquivo .pfx válido
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $certificado['tmp_name']);
        finfo_close($finfo);
        
        if ($mime !== 'application/x-pkcs12') {
            throw new Exception('O certificado deve ser um arquivo .pfx válido');
        }
        
        // Verifica se a senha foi informada
        if (empty($_POST['senha_certificado'])) {
            throw new Exception('A senha do certificado digital é obrigatória');
        }
        
        // Valida o certificado e a senha
        if (!openssl_pkcs12_read(file_get_contents($certificado['tmp_name']), $certificadoInfo, $_POST['senha_certificado'])) {
            throw new Exception('Certificado digital ou senha inválidos');
        }
        
        $dados['certificado_nome'] = $certificado['name'];
        $dados['certificado_conteudo'] = base64_encode(file_get_contents($certificado['tmp_name']));
        $dados['certificado_senha'] = $_POST['senha_certificado'];
    } else {
        // Se não tem certificado, verifica se está usando API (token)
        if (empty($dados['token_api'])) {
            throw new Exception('É necessário fornecer um certificado digital ou token de API');
        }
    }

    // Verifica se já existe configuração
    $stmt = $pdo->prepare("SELECT id FROM configuracao_nfce WHERE empresa_id = ?");
    $stmt->execute([$dados['empresa_id']]);
    
    if ($stmt->rowCount() > 0) {
        // Atualização
        $sql = "UPDATE configuracao_nfce SET 
                cnpj = ?, razao_social = ?, nome_fantasia = ?, inscricao_estadual = ?,
                cep = ?, logradouro = ?, numero_endereco = ?, complemento = ?, bairro = ?,
                cidade = ?, uf = ?, token_api = ?, ambiente = ?, serie = ?, numero = ?,
                regime_tributario = ?, csc = ?, id_token = ?, timeout = ?, contingencia = ?,
                salvar_xml = ?, envio_email = ?, certificado_nome = ?, certificado_conteudo = ?,
                certificado_senha = ? WHERE empresa_id = ?";
        
        $params = [
            $dados['cnpj'], $dados['razao_social'], $dados['nome_fantasia'], $dados['inscricao_estadual'],
            $dados['cep'], $dados['logradouro'], $dados['numero_endereco'], $dados['complemento'], $dados['bairro'],
            $dados['cidade'], $dados['uf'], $dados['token_api'], $dados['ambiente'], $dados['serie'], $dados['numero'],
            $dados['regime_tributario'], $dados['csc'], $dados['id_token'], $dados['timeout'], $dados['contingencia'],
            $dados['salvar_xml'], $dados['envio_email'], $dados['certificado_nome'] ?? null, 
            $dados['certificado_conteudo'] ?? null, $dados['certificado_senha'] ?? null,
            $dados['empresa_id']
        ];
    } else {
        // Inserção
        $sql = "INSERT INTO configuracao_nfce (
                empresa_id, cnpj, razao_social, nome_fantasia, inscricao_estadual,
                cep, logradouro, numero_endereco, complemento, bairro, cidade, uf,
                token_api, ambiente, serie, numero, regime_tributario,
                csc, id_token, timeout, contingencia, salvar_xml, envio_email,
                certificado_nome, certificado_conteudo, certificado_senha
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )";
        
        $params = [
            $dados['empresa_id'], $dados['cnpj'], $dados['razao_social'], $dados['nome_fantasia'], $dados['inscricao_estadual'],
            $dados['cep'], $dados['logradouro'], $dados['numero_endereco'], $dados['complemento'], $dados['bairro'], $dados['cidade'], $dados['uf'],
            $dados['token_api'], $dados['ambiente'], $dados['serie'], $dados['numero'], $dados['regime_tributario'],
            $dados['csc'], $dados['id_token'], $dados['timeout'], $dados['contingencia'], $dados['salvar_xml'], $dados['envio_email'],
            $dados['certificado_nome'] ?? null, $dados['certificado_conteudo'] ?? null, $dados['certificado_senha'] ?? null
        ];
    }

    // Executa a query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Mensagem de sucesso com alert e redirecionamento
    echo "<script>
            alert('Configuração salva com sucesso!');
            location.href='../../../erp/pdv/adicionarNFCe.php?id=" . urlencode($dados['empresa_id']) . "';
        </script>";

} catch (Exception $e) {
    // Mensagem de erro com alert e voltar à página anterior
    echo '<script>alert("Erro: ' . addslashes($e->getMessage()) . '"); history.back();</script>';
}

?>
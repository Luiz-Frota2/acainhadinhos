<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../../frentedeloja/caixa/vendor/autoload.php';
require_once '../conexao.php';

use NFePHP\Common\Certificate;
use Exception;

// Verifica se a requisição é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<script>alert('Método não permitido'); history.back();</script>";
    exit;
}

try {
    // Recebe os dados do formulário
    $empresa_id = $_POST['empresa_id'];
    $cnpj = preg_replace('/[^0-9]/', '', $_POST['cnpj']);
    $razao_social = $_POST['razao_social'];
    $nome_fantasia = $_POST['nome_fantasia'];
    $inscricao_estadual = $_POST['inscricao_estadual'];
    $inscricao_municipal = $_POST['inscricao_municipal'] ?? null;
    $cep = preg_replace('/[^0-9]/', '', $_POST['cep']);
    $logradouro = $_POST['logradouro'];
    $numero_endereco = $_POST['numero_endereco'];
    $complemento = $_POST['complemento'] ?? null;
    $bairro = $_POST['bairro'];
    $cidade = $_POST['cidade'];
    $uf = $_POST['uf'];
    $codigo_municipio = $_POST['codigo_municipio'];
    $telefone = $_POST['telefone'] ?? null;
    $senha_certificado = $_POST['senha_certificado'] ?? null;
    $ambiente = $_POST['ambiente'];
    $regime_tributario = $_POST['regime_tributario'];
    $serie_nfce = $_POST['serie_nfce'] ?? '1';
    $csc = $_POST['csc'] ?? null;
    $csc_id = $_POST['csc_id'] ?? null;
    $tipo_emissao = $_POST['tipo_emissao'] ?? '1'; // Adicionado
    $finalidade = $_POST['finalidade'] ?? '1'; // Adicionado
    $ind_pres = $_POST['ind_pres'] ?? '1'; // Adicionado
    $tipo_impressao = $_POST['tipo_impressao'] ?? '4'; // Adicionado

    // Validações básicas
    $camposObrigatorios = [
        'empresa_id' => $empresa_id,
        'cnpj' => $cnpj,
        'razao_social' => $razao_social,
        'codigo_municipio' => $codigo_municipio,
        'inscricao_estadual' => $inscricao_estadual,
        'ambiente' => $ambiente,
        'regime_tributario' => $regime_tributario,
        'tipo_emissao' => $tipo_emissao,
        'finalidade' => $finalidade,
        'ind_pres' => $ind_pres,
        'tipo_impressao' => $tipo_impressao
    ];

    foreach ($camposObrigatorios as $campo => $valor) {
        if (empty($valor)) {
            echo "<script>alert('O campo " . ucfirst(str_replace('_', ' ', $campo)) . " é obrigatório'); history.back();</script>";
            exit;
        }
    }

    if (!preg_match('/^\d{7}$/', $codigo_municipio)) {
        echo "<script>alert('Código do município deve conter 7 dígitos'); history.back();</script>";
        exit;
    }

    // Tratamento do certificado digital
    $certificado_nome = null;
    $destino = null;
    if (!empty($_FILES['certificado_digital']['tmp_name'])) {
        $certificado = $_FILES['certificado_digital'];
        $extensao = strtolower(pathinfo($certificado['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extensao, ['pfx', 'p12'])) {
            echo "<script>alert('O certificado deve ser um arquivo .pfx ou .p12'); history.back();</script>";
            exit;
        }
        
        if ($certificado['size'] > 5 * 1024 * 1024) {
            echo "<script>alert('Certificado muito grande (máx. 5MB)'); history.back();</script>";
            exit;
        }

        $certificado_dir = '../../../assets/img/certificado/';
        if (!is_dir($certificado_dir)) {
            if (!mkdir($certificado_dir, 0755, true)) {
                echo "<script>alert('Não foi possível criar o diretório para o certificado'); history.back();</script>";
                exit;
            }
        }
        
        if (!is_writable($certificado_dir)) {
            echo "<script>alert('Diretório de certificados não tem permissão de escrita'); history.back();</script>";
            exit;
        }

        $certificado_nome = 'cert_' . $empresa_id . '_' . time() . '.' . $extensao;
        $destino = $certificado_dir . $certificado_nome;
        
        if (!move_uploaded_file($certificado['tmp_name'], $destino)) {
            error_log("Erro ao mover certificado: " . print_r(error_get_last(), true));
            echo "<script>alert('Erro ao salvar o certificado. Verifique logs.'); history.back();</script>";
            exit;
        }

        try {
            // Verifica se a senha foi fornecida
            if (empty($senha_certificado)) {
                throw new Exception("Senha do certificado é obrigatória");
            }
            
            $certificate = Certificate::readPfx(file_get_contents($destino), $senha_certificado);
        } catch (Exception $e) {
            if (file_exists($destino)) {
                unlink($destino);
            }
            echo "<script>alert('Certificado inválido ou senha incorreta: " . addslashes($e->getMessage()) . "'); history.back();</script>";
            exit;
        }
    }

    // Conexão com o banco de dados
    $pdo = new PDO('mysql:host=localhost;dbname=u922223647_erp', 'u922223647_erp', '*V5z7GqLfa~E');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verifica se já existe integração para esta empresa
    $stmt = $pdo->prepare("SELECT id, certificado_digital FROM integracao_nfce WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $existe = $stmt->fetch();

    if ($existe) {
        // Atualização dos dados existentes
        if ($certificado_nome && !empty($existe['certificado_digital'])) {
            $old_file = $certificado_dir . $existe['certificado_digital'];
            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }

        $sql = "UPDATE integracao_nfce SET 
            cnpj = ?, 
            razao_social = ?, 
            nome_fantasia = ?, 
            inscricao_estadual = ?, 
            inscricao_municipal = ?,
            cep = ?, 
            logradouro = ?, 
            numero_endereco = ?, 
            complemento = ?, 
            bairro = ?, 
            cidade = ?, 
            uf = ?,
            codigo_municipio = ?,
            telefone = ?,
            certificado_digital = COALESCE(?, certificado_digital), 
            senha_certificado = COALESCE(?, senha_certificado), 
            ambiente = ?, 
            regime_tributario = ?,
            serie_nfce = ?,
            csc = ?, 
            csc_id = ?,
            tipo_emissao = ?,
            finalidade = ?,
            ind_pres = ?,
            tipo_impressao = ?,
            atualizado_em = NOW() 
            WHERE empresa_id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $cnpj, $razao_social, $nome_fantasia, $inscricao_estadual,
            $inscricao_municipal, $cep, $logradouro, $numero_endereco,
            $complemento, $bairro, $cidade, $uf, $codigo_municipio,
            $telefone, $certificado_nome, $senha_certificado,
            $ambiente, $regime_tributario, $serie_nfce, $csc, $csc_id,
            $tipo_emissao, $finalidade, $ind_pres, $tipo_impressao,
            $empresa_id
        ]);
    } else {
        // Inserção de novo registro
        $sql = "INSERT INTO integracao_nfce (
            empresa_id, cnpj, razao_social, nome_fantasia, 
            inscricao_estadual, inscricao_municipal, cep, logradouro, 
            numero_endereco, complemento, bairro, cidade, uf, 
            codigo_municipio, telefone, certificado_digital, 
            senha_certificado, ambiente, regime_tributario, 
            serie_nfce, csc, csc_id, tipo_emissao, finalidade,
            ind_pres, tipo_impressao, criado_em, atualizado_em
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $empresa_id, $cnpj, $razao_social, $nome_fantasia,
            $inscricao_estadual, $inscricao_municipal, $cep, $logradouro,
            $numero_endereco, $complemento, $bairro, $cidade, $uf,
            $codigo_municipio, $telefone, $certificado_nome,
            $senha_certificado, $ambiente, $regime_tributario,
            $serie_nfce, $csc, $csc_id, $tipo_emissao, $finalidade,
            $ind_pres, $tipo_impressao
        ]);
    }

    echo "<script>alert('Configurações salvas com sucesso!'); window.location.href = '../../../erp/pdv/adicionarNFCe.php?id=$empresa_id';</script>";
    exit;

} catch (Exception $e) {
    if (!empty($destino) && file_exists($destino)) {
        unlink($destino);
    }
    error_log("Erro na integração NFC-e: " . $e->getMessage());
    echo "<script>alert('Erro ao salvar: " . addslashes($e->getMessage()) . "'); history.back();</script>";
    exit;
}
?>
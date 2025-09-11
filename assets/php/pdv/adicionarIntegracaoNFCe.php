<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../../frentedeloja/caixa/vendor/autoload.php';
require_once '../conexao.php'; // usa $pdo existente

use NFePHP\Common\Certificate;
use Exception;

// Verifica se a requisição é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<script>alert('Método não permitido'); history.back();</script>";
    exit;
}

// Mapa IBGE para UF (2 dígitos)
function codigoUfPorUf(string $uf): ?int
{
    static $map = [
        'RO' => 11,
        'AC' => 12,
        'AM' => 13,
        'RR' => 14,
        'PA' => 15,
        'AP' => 16,
        'TO' => 17,
        'MA' => 21,
        'PI' => 22,
        'CE' => 23,
        'RN' => 24,
        'PB' => 25,
        'PE' => 26,
        'AL' => 27,
        'SE' => 28,
        'BA' => 29,
        'MG' => 31,
        'ES' => 32,
        'RJ' => 33,
        'SP' => 35,
        'PR' => 41,
        'SC' => 42,
        'RS' => 43,
        'MS' => 50,
        'MT' => 51,
        'GO' => 52,
        'DF' => 53
    ];
    $uf = strtoupper(trim($uf));
    return $map[$uf] ?? null;
}

try {
    // Recebe os dados do formulário
    $empresa_id          = $_POST['empresa_id'];
    $cnpj                = preg_replace('/\D+/', '', $_POST['cnpj'] ?? '');
    $razao_social        = trim($_POST['razao_social'] ?? '');
    $nome_fantasia       = trim($_POST['nome_fantasia'] ?? '');
    $inscricao_estadual  = trim($_POST['inscricao_estadual'] ?? '');
    $inscricao_municipal = $_POST['inscricao_municipal'] ?? null;
    $cep                 = preg_replace('/\D+/', '', $_POST['cep'] ?? '');
    $logradouro          = trim($_POST['logradouro'] ?? '');
    $numero_endereco     = trim($_POST['numero_endereco'] ?? '');
    $complemento         = $_POST['complemento'] ?? null;
    $bairro              = trim($_POST['bairro'] ?? '');
    $cidade              = trim($_POST['cidade'] ?? '');
    $uf                  = trim($_POST['uf'] ?? '');
    $codigo_municipio    = trim($_POST['codigo_municipio'] ?? '');
    $telefone            = trim($_POST['telefone'] ?? '');

    // IMPORTANTE: senha pode vir vazia para "manter"
    $senha_certificado_informada = isset($_POST['senha_certificado']) ? trim($_POST['senha_certificado']) : null;

    $ambiente            = (int)($_POST['ambiente'] ?? 0);
    $regime_tributario   = (int)($_POST['regime_tributario'] ?? 0);
    $serie_nfce          = (int)($_POST['serie_nfce'] ?? 1);
    $ultimo_numero_nfce  = isset($_POST['ultimo_numero_nfce']) && $_POST['ultimo_numero_nfce'] !== ''
        ? (int)$_POST['ultimo_numero_nfce'] : 1;
    if ($ultimo_numero_nfce < 0) $ultimo_numero_nfce = 0;

    $csc                 = $_POST['csc'] ?? null;
    $csc_id              = $_POST['csc_id'] ?? null;

    $tipo_emissao        = (int)($_POST['tipo_emissao'] ?? 1);
    $finalidade          = (int)($_POST['finalidade'] ?? 1);
    $ind_pres            = (int)($_POST['ind_pres'] ?? 1);
    $tipo_impressao      = (int)($_POST['tipo_impressao'] ?? 4);

    // Validações básicas
    $camposObrigatorios = [
        'empresa_id'         => $empresa_id,
        'cnpj'               => $cnpj,
        'razao_social'       => $razao_social,
        'codigo_municipio'   => $codigo_municipio,
        'inscricao_estadual' => $inscricao_estadual,
        'ambiente'           => $ambiente,
        'regime_tributario'  => $regime_tributario,
        'tipo_emissao'       => $tipo_emissao,
        'finalidade'         => $finalidade,
        'ind_pres'           => $ind_pres,
        'tipo_impressao'     => $tipo_impressao,
        'uf'                 => $uf
    ];

    foreach ($camposObrigatorios as $campo => $valor) {
        if ($valor === '' || $valor === null || $valor === 0) {
            echo "<script>alert('O campo " . addslashes(ucfirst(str_replace('_', ' ', $campo))) . " é obrigatório'); history.back();</script>";
            exit;
        }
    }

    if (!preg_match('/^\d{7}$/', $codigo_municipio)) {
        echo "<script>alert('Código do município deve conter 7 dígitos'); history.back();</script>";
        exit;
    }

    // Confiável: calcular codigo_uf pelo UF (ignora qualquer coisa do front)
    $codigo_uf = codigoUfPorUf($uf);
    if ($codigo_uf === null) {
        echo "<script>alert('UF inválida para cálculo do código IBGE da UF'); history.back();</script>";
        exit;
    }

    // Diretório do certificado
    $certificado_dir = '../../../assets/img/certificado/';
    if (!is_dir($certificado_dir)) {
        if (!mkdir($certificado_dir, 0755, true)) {
            echo "<script>alert('Não foi possível criar o diretório para o certificado'); history.back();</script>";
            exit;
        }
    }
    if (!is_writable($certificado_dir)) {
        echo "<script>alert('Diretório de certificados sem permissão de escrita'); history.back();</script>";
        exit;
    }

    // Tratamento do certificado digital (opcional)
    $certificado_nome = null;
    $destino = null;
    $enviouNovoCertificado = !empty($_FILES['certificado_digital']['tmp_name']);

    if ($enviouNovoCertificado) {
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
        // Se enviou novo certificado, a senha é obrigatória para validar
        if ($senha_certificado_informada === null || $senha_certificado_informada === '') {
            echo "<script>alert('Informe a senha do certificado para validar o novo arquivo.'); history.back();</script>";
            exit;
        }

        $certificado_nome = 'cert_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $empresa_id) . '_' . time() . '.' . $extensao;
        $destino = $certificado_dir . $certificado_nome;

        if (!move_uploaded_file($certificado['tmp_name'], $destino)) {
            error_log("Erro ao mover certificado: " . print_r(error_get_last(), true));
            echo "<script>alert('Erro ao salvar o certificado. Verifique permissões.'); history.back();</script>";
            exit;
        }

        // Valida o PFX com a senha informada
        try {
            $bin = @file_get_contents($destino);
            if ($bin === false) throw new Exception('Falha ao ler o arquivo');
            $certificate = Certificate::readPfx($bin, $senha_certificado_informada);
            unset($certificate);
        } catch (Exception $e) {
            if (file_exists($destino)) unlink($destino);
            echo "<script>alert('Certificado inválido ou senha incorreta: " . addslashes($e->getMessage()) . "'); history.back();</script>";
            exit;
        }
    }

    // Verifica se já existe integração para esta empresa
    $stmt = $pdo->prepare("SELECT id, certificado_digital FROM integracao_nfce WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $existe = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existe) {
        // Se enviou novo certificado, apaga o antigo
        if ($certificado_nome && !empty($existe['certificado_digital'])) {
            $old_file = $certificado_dir . $existe['certificado_digital'];
            if (is_file($old_file)) @unlink($old_file);
        }

        // ATENÇÃO: usamos NULLIF(?, '') para tratar string vazia como NULL;
        // daí COALESCE mantém o valor atual se vier vazio.
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
            codigo_uf = ?,
            codigo_municipio = ?,
            telefone = ?,
            certificado_digital = COALESCE(?, certificado_digital), 
            senha_certificado   = COALESCE(NULLIF(?, ''), senha_certificado), 
            ambiente = ?, 
            regime_tributario = ?,
            serie_nfce = ?,
            ultimo_numero_nfce = ?,
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
            $cnpj,
            $razao_social,
            $nome_fantasia,
            $inscricao_estadual,
            $inscricao_municipal,
            $cep,
            $logradouro,
            $numero_endereco,
            $complemento,
            $bairro,
            $cidade,
            $uf,
            $codigo_uf,
            $codigo_municipio,
            $telefone,
            $certificado_nome,                 // se null -> mantém
            $senha_certificado_informada,      // se '' -> mantém (graças ao NULLIF)
            $ambiente,
            $regime_tributario,
            $serie_nfce,
            $ultimo_numero_nfce,
            $csc,
            $csc_id,
            $tipo_emissao,
            $finalidade,
            $ind_pres,
            $tipo_impressao,
            $empresa_id
        ]);
    } else {
        // Inserção de novo registro
        $sql = "INSERT INTO integracao_nfce (
            empresa_id, cnpj, razao_social, nome_fantasia, 
            inscricao_estadual, inscricao_municipal, cep, logradouro, 
            numero_endereco, complemento, bairro, cidade, uf, 
            codigo_uf, codigo_municipio, telefone, certificado_digital, 
            senha_certificado, ambiente, regime_tributario, 
            serie_nfce, ultimo_numero_nfce, csc, csc_id, tipo_emissao, finalidade,
            ind_pres, tipo_impressao, criado_em, atualizado_em
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $empresa_id,
            $cnpj,
            $razao_social,
            $nome_fantasia,
            $inscricao_estadual,
            $inscricao_municipal,
            $cep,
            $logradouro,
            $numero_endereco,
            $complemento,
            $bairro,
            $cidade,
            $uf,
            $codigo_uf,
            $codigo_municipio,
            $telefone,
            $certificado_nome,                // pode ser null (se ainda não enviou)
            // para INSERT, se não houver certificado, a senha pode ficar null também
            ($enviouNovoCertificado ? $senha_certificado_informada : null),
            $ambiente,
            $regime_tributario,
            $serie_nfce,
            $ultimo_numero_nfce,
            $csc,
            $csc_id,
            $tipo_emissao,
            $finalidade,
            $ind_pres,
            $tipo_impressao
        ]);
    }

    echo "<script>alert('Configurações salvas com sucesso!'); window.location.href='../../../erp/pdv/adicionarNFCe.php?id=$empresa_id';</script>";
    exit;
} catch (Exception $e) {
    if (!empty($destino) && file_exists($destino)) {
        @unlink($destino);
    }
    error_log("Erro na integração NFC-e: " . $e->getMessage());
    echo "<script>alert('Erro ao salvar: " . addslashes($e->getMessage()) . "'); history.back();</script>";
    exit;
}
?>
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// ✅ Recupera o ID da empresa pela URL
$idSelecionado = $_GET['id'] ?? '';

// ✅ Conexão com o banco de dados
require '../../../assets/php/conexao.php';

// ✅ Função para validar CPF
function validarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    return true;
}

// ✅ Recupera e sanitiza os dados do formulário
$nomeFuncionario = isset($_POST['nomeFuncionario']) ? htmlspecialchars($_POST['nomeFuncionario'], ENT_QUOTES, 'UTF-8') : '';
$idSelecionado = isset($_POST['idSelecionado']) ? htmlspecialchars($_POST['idSelecionado'], ENT_QUOTES, 'UTF-8') : $idSelecionado;
$cpfUsuario = isset($_POST['cpfUsuario']) ? preg_replace('/[^0-9]/', '', $_POST['cpfUsuario']) : '';
$dataAtestado = isset($_POST['dataAtestado']) ? htmlspecialchars($_POST['dataAtestado'], ENT_QUOTES, 'UTF-8') : '';
$diasAfastado = isset($_POST['diasAfastado']) ? (int)$_POST['diasAfastado'] : 0;
$medico = isset($_POST['medico']) ? htmlspecialchars($_POST['medico'], ENT_QUOTES, 'UTF-8') : '';
$observacoes = isset($_POST['observacoes']) ? htmlspecialchars($_POST['observacoes'], ENT_QUOTES, 'UTF-8') : '';

// ✅ Validação de campos obrigatórios
$camposObrigatorios = [
    'Nome do Funcionário' => $nomeFuncionario,
    'CPF' => $cpfUsuario,
    'Data do Atestado' => $dataAtestado,
    'Dias Afastado' => $diasAfastado,
    'Médico' => $medico
];

foreach ($camposObrigatorios as $campo => $valor) {
    if (empty($valor)) {
        echo "<script>alert('O campo $campo é obrigatório.'); history.back();</script>";
        exit;
    }
}

// ✅ Validação da data do atestado
$hoje = new DateTime();
$dataAtestadoObj = DateTime::createFromFormat('Y-m-d', $dataAtestado);
if ($dataAtestadoObj === false || $dataAtestadoObj > $hoje) {
    echo "<script>alert('A data do atestado não pode ser futura ou inválida.'); history.back();</script>";
    exit;
}

// ✅ Verifica se o CPF tem 11 dígitos
if (strlen($cpfUsuario) != 11) {
    echo "<script>alert('CPF deve conter 11 dígitos numéricos.'); history.back();</script>";
    exit;
}

// ✅ Valida dias afastados
if ($diasAfastado < 1) {
    echo "<script>alert('Dias afastados deve ser pelo menos 1.'); history.back();</script>";
    exit;
}

// ✅ Define o identificador da empresa corretamente
if (strpos($idSelecionado, 'principal_') === 0 || strpos($idSelecionado, 'filial_') === 0 || strpos($idSelecionado, 'unidade_') === 0) {
    $idEmpresaFinal = $idSelecionado;
} else {
    $idEmpresaFinal = ($idSelecionado == '1') ? "principal_1" : "unidade_" . $idSelecionado;
}

// ✅ Upload da imagem do atestado
$nomeImagem = '';
if (isset($_FILES['imagemAtestado']) && $_FILES['imagemAtestado']['error'] === UPLOAD_ERR_OK) {
    $imagemAtestado = $_FILES['imagemAtestado'];
    $extensao = strtolower(pathinfo($imagemAtestado['name'], PATHINFO_EXTENSION));

    $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    if (!in_array($extensao, $extensoesPermitidas)) {
        echo "<script>alert('Formato de arquivo inválido. Use JPG, PNG, GIF ou PDF.'); history.back();</script>";
        exit;
    }

    // Verifica tamanho máximo (5MB)
    if ($imagemAtestado['size'] > 5 * 1024 * 1024) {
        echo "<script>alert('O arquivo é muito grande. Tamanho máximo permitido: 5MB.'); history.back();</script>";
        exit;
    }

    // Verifica se é imagem (exceto PDF)
    if ($extensao != 'pdf') {
        $check = getimagesize($imagemAtestado['tmp_name']);
        if ($check === false) {
            echo "<script>alert('O arquivo enviado não é uma imagem válida.'); history.back();</script>";
            exit;
        }
    }

    $diretorio = '../../../assets/img/atestados/';
    if (!is_dir($diretorio)) {
        mkdir($diretorio, 0755, true);
    }

    $nomeImagem = uniqid('atestado_') . '.' . $extensao;
    $caminhoCompleto = $diretorio . $nomeImagem;

    if (!move_uploaded_file($imagemAtestado['tmp_name'], $caminhoCompleto)) {
        echo "<script>alert('Erro ao salvar o arquivo do atestado.'); history.back();</script>";
        exit;
    }
} else {
    echo "<script>alert('Arquivo do atestado não enviado ou com erro. Código: " . $_FILES['imagemAtestado']['error'] . "'); history.back();</script>";
    exit;
}

// ✅ Verifica se o CPF existe e busca horários do funcionário
try {
    $stmtFuncionario = $pdo->prepare("SELECT * FROM funcionarios WHERE cpf = :cpf AND empresa_id = :empresa_id");
    $stmtFuncionario->bindParam(':cpf', $cpfUsuario, PDO::PARAM_STR);
    $stmtFuncionario->bindParam(':empresa_id', $idEmpresaFinal, PDO::PARAM_STR);
    $stmtFuncionario->execute();

    $funcionario = $stmtFuncionario->fetch(PDO::FETCH_ASSOC);

    if (!$funcionario) {
        // Remove a imagem salva se o funcionário não existe
        if (!empty($nomeImagem) && file_exists($diretorio . $nomeImagem)) {
            unlink($diretorio . $nomeImagem);
        }
        
        echo "<script>alert('Funcionário não encontrado para esta empresa.'); history.back();</script>";
        exit;
    }
} catch (PDOException $e) {
    // Remove a imagem salva se houve erro
    if (!empty($nomeImagem) && file_exists($diretorio . $nomeImagem)) {
        unlink($diretorio . $nomeImagem);
    }
    
    echo "<script>alert('Erro ao verificar funcionário: " . addslashes($e->getMessage()) . "'); history.back();</script>";
    exit;
}

// ✅ Extrai os horários do funcionário
$entrada = $funcionario['entrada'] ?? '08:00:00';
$saida_intervalo = $funcionario['saida_intervalo'] ?? '12:00:00';
$retorno_intervalo = $funcionario['retorno_intervalo'] ?? '13:00:00';
$saida_final = $funcionario['saida_final'] ?? '17:00:00';

// ✅ Inserção do atestado e criação dos registros de ponto
try {
    $pdo->beginTransaction();

    // 🔹 Inserir o atestado
    $stmt = $pdo->prepare("INSERT INTO atestados (
        nome_funcionario, cpf_usuario, data_envio, data_atestado, dias_afastado, medico, observacoes, imagem_atestado, id_empresa
    ) VALUES (
        :nome_funcionario, :cpf_usuario, NOW(), :data_atestado, :dias_afastado, :medico, :observacoes, :imagem_atestado, :id_empresa
    )");

    $stmt->bindParam(':nome_funcionario', $nomeFuncionario, PDO::PARAM_STR);
    $stmt->bindParam(':cpf_usuario', $cpfUsuario, PDO::PARAM_STR);
    $stmt->bindParam(':data_atestado', $dataAtestado, PDO::PARAM_STR);
    $stmt->bindParam(':dias_afastado', $diasAfastado, PDO::PARAM_INT);
    $stmt->bindParam(':medico', $medico, PDO::PARAM_STR);
    $stmt->bindParam(':observacoes', $observacoes, PDO::PARAM_STR);
    $stmt->bindParam(':imagem_atestado', $nomeImagem, PDO::PARAM_STR);
    $stmt->bindParam(':id_empresa', $idEmpresaFinal, PDO::PARAM_STR);
    
    if (!$stmt->execute()) {
        throw new Exception("Erro ao inserir atestado no banco de dados: " . implode(", ", $stmt->errorInfo()));
    }

    // 🔹 Criar registros de ponto com os horários reais do funcionário
    $dataInicio = new DateTime($dataAtestado);
    $diasAfastadoInt = (int)$diasAfastado;

    for ($i = 0; $i < $diasAfastadoInt; $i++) {
        $dataPonto = $dataInicio->format('Y-m-d');

        // Verifica se já existe registro para este dia
        $stmtVerifica = $pdo->prepare("SELECT id FROM pontos WHERE cpf = :cpf AND data = :data AND empresa_id = :empresa_id");
        $stmtVerifica->bindParam(':cpf', $cpfUsuario, PDO::PARAM_STR);
        $stmtVerifica->bindParam(':data', $dataPonto, PDO::PARAM_STR);
        $stmtVerifica->bindParam(':empresa_id', $idEmpresaFinal, PDO::PARAM_STR);
        $stmtVerifica->execute();

        if (!$stmtVerifica->fetch()) {
            $stmtPonto = $pdo->prepare("INSERT INTO pontos (
                cpf, nome, data, entrada, saida_intervalo, retorno_intervalo, saida_final, empresa_id, justificativa
            ) VALUES (
                :cpf, :nome, :data, :entrada, :saida_intervalo, :retorno_intervalo, :saida_final, :empresa_id, 'Atestado médico'
            )");

            $stmtPonto->bindParam(':cpf', $cpfUsuario, PDO::PARAM_STR);
            $stmtPonto->bindParam(':nome', $nomeFuncionario, PDO::PARAM_STR);
            $stmtPonto->bindParam(':data', $dataPonto, PDO::PARAM_STR);
            $stmtPonto->bindParam(':entrada', $entrada, PDO::PARAM_STR);
            $stmtPonto->bindParam(':saida_intervalo', $saida_intervalo, PDO::PARAM_STR);
            $stmtPonto->bindParam(':retorno_intervalo', $retorno_intervalo, PDO::PARAM_STR);
            $stmtPonto->bindParam(':saida_final', $saida_final, PDO::PARAM_STR);
            $stmtPonto->bindParam(':empresa_id', $idEmpresaFinal, PDO::PARAM_STR);
            
            if (!$stmtPonto->execute()) {
                throw new Exception("Erro ao criar registro de ponto para o dia $dataPonto: " . implode(", ", $stmtPonto->errorInfo()));
            }
        }

        $dataInicio->modify('+1 day');
    }

    $pdo->commit();
    echo "<script>alert('Atestado adicionado e registros de ponto criados com sucesso!'); window.location.href = '../../sistemadeponto/atestadosEnviados.php?id=$idSelecionado';</script>";
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    
    // Remove a imagem salva se houve erro
    if (!empty($nomeImagem) && file_exists($diretorio . $nomeImagem)) {
        unlink($diretorio . $nomeImagem);
    }
    
    echo "<script>alert('Erro ao processar o atestado: " . addslashes($e->getMessage()) . "'); history.back();</script>";
    exit;
}
?>
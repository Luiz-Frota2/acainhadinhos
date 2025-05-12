<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// ✅ Recupera o ID da empresa pela URL
$idSelecionado = $_GET['id'] ?? '';

// ✅ Verifica se o usuário está logado
if (
    !isset($_SESSION['usuario_logado']) ||
    !isset($_SESSION['empresa_id']) ||
    !isset($_SESSION['tipo_empresa']) ||
    !isset($_SESSION['usuario_id'])
) {
    header("Location: ../index.php?id=$idSelecionado");
    exit;
}

// ✅ Conexão com o banco de dados
require '../../../assets/php/conexao.php';

// ✅ Recupera os dados do formulário
$nomeFuncionario = $_POST['nomeFuncionario'] ?? '';
$idSelecionado = $_POST['idSelecionado'] ?? '';
$cpfUsuario = $_POST['cpfUsuario'] ?? '';
$dataAtestado = $_POST['dataAtestado'] ?? '';
$diasAfastado = $_POST['diasAfastado'] ?? '';
$medico = $_POST['medico'] ?? '';
$observacoes = $_POST['observacoes'] ?? '';

// ✅ Validação de campos obrigatórios
if (empty($nomeFuncionario) || empty($cpfUsuario) || empty($dataAtestado) || empty($diasAfastado) || empty($medico)) {
    echo "<script>alert('Preencha todos os campos obrigatórios.'); history.back();</script>";
    exit;
}

// ✅ Define o identificador da empresa (corrigido para lidar com "principal_1" e "filial_X")
if (strpos($idSelecionado, 'principal_') === 0 || strpos($idSelecionado, 'filial_') === 0) {
    $idEmpresaFinal = $idSelecionado;
} else {
    $idEmpresaFinal = ($idSelecionado == '1') ? "principal_1" : "filial_" . $idSelecionado;
}

// ✅ Upload da imagem do atestado
if (isset($_FILES['imagemAtestado']) && $_FILES['imagemAtestado']['error'] === 0) {
    $imagemAtestado = $_FILES['imagemAtestado'];
    $extensao = strtolower(pathinfo($imagemAtestado['name'], PATHINFO_EXTENSION));

    if (!in_array($extensao, ['jpg', 'jpeg', 'png', 'gif'])) {
        echo "<script>alert('Formato de imagem inválido.'); history.back();</script>";
        exit;
    }

    $diretorio = '../../../assets/img/atestados/';
    if (!is_dir($diretorio)) {
        mkdir($diretorio, 0755, true); // Cria o diretório se não existir
    }

    $nomeImagem = uniqid('atestado_') . '.' . $extensao;
    $caminhoCompleto = $diretorio . $nomeImagem;

    if (!move_uploaded_file($imagemAtestado['tmp_name'], $caminhoCompleto)) {
        echo "<script>alert('Erro ao salvar a imagem do atestado.'); history.back();</script>";
        exit;
    }
} else {
    echo "<script>alert('Imagem do atestado não enviada.'); history.back();</script>";
    exit;
}

// ✅ Verifica se o CPF existe
$stmtFuncionario = $pdo->prepare("SELECT * FROM funcionarios WHERE cpf = :cpf AND empresa_id = :empresa_id");
$stmtFuncionario->bindParam(':cpf', $cpfUsuario);
$stmtFuncionario->bindParam(':empresa_id', $idSelecionado);
$stmtFuncionario->execute();

$funcionario = $stmtFuncionario->fetch(PDO::FETCH_ASSOC);

if (!$funcionario) {
    echo "<script>alert('Funcionário não encontrado.'); history.back();</script>";
    exit;
}

// ✅ Inserção do atestado
try {
    $stmt = $pdo->prepare("INSERT INTO atestados (
        nome_funcionario, cpf_usuario, data_envio, data_atestado, dias_afastado, medico, observacoes, imagem_atestado, id_empresa
    ) VALUES (
        :nome_funcionario, :cpf_usuario, NOW(), :data_atestado, :dias_afastado, :medico, :observacoes, :imagem_atestado, :id_empresa
    )");

    $stmt->bindParam(':nome_funcionario', $nomeFuncionario);
    $stmt->bindParam(':cpf_usuario', $cpfUsuario);
    $stmt->bindParam(':data_atestado', $dataAtestado);
    $stmt->bindParam(':dias_afastado', $diasAfastado, PDO::PARAM_INT);
    $stmt->bindParam(':medico', $medico);
    $stmt->bindParam(':observacoes', $observacoes);
    $stmt->bindParam(':imagem_atestado', $nomeImagem); // Apenas o nome da imagem
    $stmt->bindParam(':id_empresa', $idEmpresaFinal);
    $stmt->execute();
} catch (PDOException $e) {
    echo "<script>alert('Erro ao adicionar atestado: " . $e->getMessage() . "'); history.back();</script>";
    exit;
}

// ✅ Inserção no banco de ponto
$entrada = $funcionario['hora_entrada_primeiro_turno'];
$saida = $funcionario['hora_saida_primeiro_turno'];
$entrada2 = $funcionario['hora_entrada_segundo_turno'];
$saida2 = $funcionario['hora_saida_segundo_turno'];

$dataInicio = new DateTime($dataAtestado);

for ($i = 0; $i < (int)$diasAfastado; $i++) {
    $dataRegistro = $dataInicio->format('Y-m-d');

    // Primeiro turno
    $stmtPonto = $pdo->prepare("INSERT INTO registros_ponto (
        empresa_id, cpf, data, entrada, saida, status, horas_pendentes
    ) VALUES (
        :empresa_id, :cpf, :data, :entrada, :saida, 'atestado', '00:00:00'
    )");
    $stmtPonto->execute([
        ':empresa_id' => $idEmpresaFinal,
        ':cpf' => $cpfUsuario,
        ':data' => $dataRegistro,
        ':entrada' => $entrada,
        ':saida' => $saida
    ]);

    // Segundo turno, se existir
    if (!empty($entrada2) && !empty($saida2)) {
        $stmtPonto2 = $pdo->prepare("INSERT INTO registros_ponto (
            empresa_id, cpf, data, entrada, saida, status, horas_pendentes
        ) VALUES (
            :empresa_id, :cpf, :data, :entrada, :saida, 'atestado', '00:00:00'
        )");
        $stmtPonto2->execute([
            ':empresa_id' => $idEmpresaFinal,
            ':cpf' => $cpfUsuario,
            ':data' => $dataRegistro,
            ':entrada' => $entrada2,
            ':saida' => $saida2
        ]);
    }

    $dataInicio->modify('+1 day');
}

echo "<script>alert('Atestado adicionado e registros de ponto criados com sucesso!'); window.location.href = '../../sistemadeponto/atestadosEnviados.php?id=$idSelecionado';</script>";
?>

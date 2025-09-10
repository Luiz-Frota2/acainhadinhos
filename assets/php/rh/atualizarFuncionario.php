<?php
// assets/php/rh/atualizarFuncionario.php

// Exibir erros (evita tela branca)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Conexão
require_once __DIR__ . '/../conexao.php'; // assets/php/rh/../conexao.php => assets/php/conexao.php

// Garante PDO em modo exceção
if (isset($pdo) && $pdo instanceof PDO) {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

function js_alert_back($msg)
{
    $msg = addslashes($msg);
    echo "<script>alert('{$msg}'); history.back();</script>";
    exit;
}

function js_alert_redirect($msg, $url)
{
    $msg = addslashes($msg);
    $url = addslashes($url);
    echo "<script>alert('{$msg}'); window.location.href='{$url}';</script>";
    exit;
}

/**
 * Converte "1.234,56" ou "1234,56" para "1234.56"
 * Retorna NULL se vazio.
 */
function brMoneyToDecimal($s)
{
    if ($s === null) return null;
    $s = trim((string)$s);
    if ($s === '') return null;
    // Mantém apenas dígitos, vírgula, ponto e sinal
    $s = preg_replace('/[^\d,.\-]/', '', $s);
    // Remove separador de milhar e troca vírgula por ponto
    $s = str_replace('.', '', $s);
    $s = str_replace(',', '.', $s);
    if ($s === '' || $s === '-' || $s === '.')
        return null;
    return number_format((float)$s, 2, '.', '');
}

/**
 * Normaliza CPF para apenas dígitos
 */
function normalizeCPF($cpf)
{
    $cpf = preg_replace('/\D+/', '', (string)$cpf);
    return $cpf;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    js_alert_back('Requisição inválida.');
}

try {
    // Coleta segura (com defaults)
    $id             = $_POST['id']             ?? null;
    $empresa_id     = trim($_POST['empresa_id'] ?? '');
    $nome           = trim($_POST['nome']        ?? '');
    $data_nascimento = trim($_POST['data_nascimento'] ?? '');
    $cpf            = normalizeCPF($_POST['cpf'] ?? '');
    $rg             = trim($_POST['rg']          ?? '');
    $pis            = trim($_POST['pis']         ?? '');
    $matricula      = trim($_POST['matricula']   ?? '');
    $data_admissao  = trim($_POST['data_admissao'] ?? '');
    $cargo          = trim($_POST['cargo']       ?? '');
    $setor          = trim($_POST['setor']       ?? '');
    $salario_raw    = $_POST['salario']          ?? '';
    $escala         = trim($_POST['escala']      ?? '');
    $dia_inicio     = trim($_POST['dia_inicio']  ?? '');
    $dia_folga      = trim($_POST['dia_folga']   ?? '');
    $entrada        = trim($_POST['entrada']     ?? '');
    $saida_intervalo = trim($_POST['saida_intervalo'] ?? '');
    $retorno_intervalo = trim($_POST['retorno_intervalo'] ?? '');
    $saida_final    = trim($_POST['saida_final'] ?? '');
    $email          = trim($_POST['email']       ?? '');
    $telefone       = trim($_POST['telefone']    ?? '');
    $endereco       = trim($_POST['endereco']    ?? '');
    $cidade         = trim($_POST['cidade']      ?? '');

    // Valida obrigatórios
    if (empty($id) || empty($nome) || empty($cpf)) {
        js_alert_back('ID, Nome e CPF são obrigatórios.');
    }

    if (strlen($cpf) !== 11 || !ctype_digit($cpf)) {
        js_alert_back('CPF deve conter 11 dígitos numéricos.');
    }

    // Empresa obrigatória (vem do hidden)
    if ($empresa_id === '') {
        js_alert_back('Empresa não informada. Recarregue a página e tente novamente.');
    }

    // Salário normalizado
    $salario = brMoneyToDecimal($salario_raw); // pode ser null

    // Verifica se CPF pertence a outro funcionário
    $sql = "SELECT COUNT(*) FROM funcionarios WHERE cpf = :cpf AND id != :id";
    $st = $pdo->prepare($sql);
    $st->bindValue(':cpf', $cpf, PDO::PARAM_STR);
    $st->bindValue(':id', $id, PDO::PARAM_INT);
    $st->execute();
    if ((int)$st->fetchColumn() > 0) {
        js_alert_back('Este CPF já está cadastrado para outro funcionário.');
    }

    // UPDATE
    $sql = "UPDATE funcionarios SET
                empresa_id        = :empresa_id,
                nome              = :nome,
                data_nascimento   = :data_nascimento,
                cpf               = :cpf,
                rg                = :rg,
                pis               = :pis,
                matricula         = :matricula,
                data_admissao     = :data_admissao,
                cargo             = :cargo,
                setor             = :setor,
                salario           = :salario,
                escala            = :escala,
                dia_inicio        = :dia_inicio,
                dia_folga         = :dia_folga,
                entrada           = :entrada,
                saida_intervalo   = :saida_intervalo,
                retorno_intervalo = :retorno_intervalo,
                saida_final       = :saida_final,
                email             = :email,
                telefone          = :telefone,
                endereco          = :endereco,
                cidade            = :cidade
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);

    // Bind fixos
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->bindValue(':nome', $nome, PDO::PARAM_STR);
    $stmt->bindValue(':cpf', $cpf, PDO::PARAM_STR);
    $stmt->bindValue(':empresa_id', $empresa_id, PDO::PARAM_STR);

    // Helper para bind NULL ou valor
    $opt = function ($param, $val) use ($stmt) {
        if ($val === '' || $val === null) {
            $stmt->bindValue($param, null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue($param, $val);
        }
    };

    $opt(':data_nascimento',   $data_nascimento);
    $opt(':rg',                $rg);
    $opt(':pis',               $pis);
    $opt(':matricula',         $matricula);
    $opt(':data_admissao',     $data_admissao);
    $opt(':cargo',             $cargo);
    $opt(':setor',             $setor);
    $opt(':salario',           $salario); // já vem 1234.56 ou null
    $opt(':escala',            $escala);
    $opt(':dia_inicio',        $dia_inicio);
    $opt(':dia_folga',         $dia_folga);
    $opt(':entrada',           $entrada);
    $opt(':saida_intervalo',   $saida_intervalo);
    $opt(':retorno_intervalo', $retorno_intervalo);
    $opt(':saida_final',       $saida_final);
    $opt(':email',             $email);
    $opt(':telefone',          $telefone);
    $opt(':endereco',          $endereco);
    $opt(':cidade',            $cidade);

    $ok = $stmt->execute();

    if ($ok) {
        // volta para listagem
        $dest = "../../../erp/rh/funcionarioAdicionados.php?id=" . rawurlencode($empresa_id);
        js_alert_redirect('Funcionário atualizado com sucesso!', $dest);
    } else {
        js_alert_back('Erro ao atualizar funcionário.');
    }
} catch (PDOException $e) {
    js_alert_back('Erro no banco de dados: ' . $e->getMessage());
} catch (Throwable $e) {
    js_alert_back('Erro inesperado: ' . $e->getMessage());
}
?>
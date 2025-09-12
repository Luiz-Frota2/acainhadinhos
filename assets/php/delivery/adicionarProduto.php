<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

// Caminho: assets/php/delivery/adicionarProduto.php
require_once __DIR__ . '/../conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit('Método não permitido.');
}

/* ===== Entrada ===== */
$nomeProduto       = trim($_POST['nomeProduto'] ?? '');
$quantidadeProduto = trim($_POST['quantidadeProduto'] ?? '0');
$precoProdutoRaw   = trim($_POST['precoProduto'] ?? '0');
$descricaoProduto  = trim($_POST['descricaoProduto'] ?? '');
$idCategoria       = (int)($_POST['id_categoria'] ?? 0);
$idEmpresa         = trim($_POST['id_empresa'] ?? ''); // slug (ex.: principal_1, unidade_2, filial_1, franquia_3)

/* ===== Validações básicas ===== */
if ($nomeProduto === '' || $idCategoria <= 0 || $idEmpresa === '') {
    echo "<script>alert('Preencha os campos obrigatórios (Nome, Categoria, Empresa).'); history.back();</script>";
    exit;
}

/* Quantidade como inteiro (remove não dígitos) */
$quantidade = (int)preg_replace('/\D+/', '', $quantidadeProduto);

/* Normaliza preço:
   - remove caracteres que não sejam dígitos, vírgula ou ponto
   - se tiver ponto e vírgula, assume ponto como milhar e vírgula como decimal
   - se tiver só vírgula, troca por ponto
*/
$precoSan = preg_replace('/[^\d,\.]/', '', $precoProdutoRaw);
if (strpos($precoSan, ',') !== false && strpos($precoSan, '.') !== false) {
    // remove pontos (milhar), mantém vírgula para virar decimal
    $precoSan = str_replace('.', '', $precoSan);
}
$precoSan = str_replace(',', '.', $precoSan);
$preco    = number_format((float)$precoSan, 2, '.', ''); // formato SQL "12.34"

/* ===== Upload da imagem (opcional) ===== */
$imagemProduto = '';

if (isset($_FILES['imagemProduto']) && $_FILES['imagemProduto']['error'] !== UPLOAD_ERR_NO_FILE) {
    $err = (int)$_FILES['imagemProduto']['error'];
    if ($err !== UPLOAD_ERR_OK) {
        echo "<script>alert('Falha no upload da imagem (código {$err}).'); history.back();</script>";
        exit;
    }

    $ext = strtolower(pathinfo($_FILES['imagemProduto']['name'], PATHINFO_EXTENSION));
    $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($ext, $permitidas, true)) {
        echo "<script>alert('Somente imagens JPG, JPEG, PNG, GIF ou WEBP são permitidas.'); history.back();</script>";
        exit;
    }

    // Diretorio final: assets/img/uploads/
    $destDirFs = __DIR__ . '/../../img/uploads';
    if (!is_dir($destDirFs)) {
        @mkdir($destDirFs, 0755, true);
    }

    // Nome único com empresa no prefixo
    try {
        $uniq = bin2hex(random_bytes(6));
    } catch (Throwable $e) {
        $uniq = substr(sha1(uniqid('', true)), 0, 12);
    }
    $slugEmpresa = preg_replace('/[^a-z0-9_]+/i', '_', $idEmpresa);
    $fileName    = 'empresa_' . $slugEmpresa . '_' . $uniq . '.' . $ext;
    $destPath    = $destDirFs . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($_FILES['imagemProduto']['tmp_name'], $destPath)) {
        echo "<script>alert('Erro ao mover a imagem enviada.'); history.back();</script>";
        exit;
    }

    // Guardamos apenas o nome do arquivo; o front monta "../../assets/img/uploads/{$imagem}"
    $imagemProduto = $fileName;
}

/* ===== Verifica duplicidade por nome dentro da MESMA empresa ===== */
try {
    $sqlDup = "SELECT 1
                 FROM adicionarProdutos
                WHERE nome_produto = :nome
                  AND id_empresa   = :empresa
                LIMIT 1";

    $stDup = $pdo->prepare($sqlDup);
    $stDup->bindValue(':nome',    $nomeProduto, PDO::PARAM_STR);
    $stDup->bindValue(':empresa', $idEmpresa,   PDO::PARAM_STR);
    $stDup->execute();

    if ($stDup->fetchColumn()) {
        echo "<script>alert('Já existe um produto com esse nome para esta empresa.'); history.back();</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao verificar duplicidade: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "'); history.back();</script>";
    exit;
}

/* ===== Insere o produto ===== */
try {
    $sqlIns = "INSERT INTO adicionarProdutos
                  (nome_produto, quantidade_produto, preco_produto, imagem_produto, descricao_produto, id_categoria, id_empresa)
               VALUES
                  (:nome, :qtd, :preco, :img, :descr, :cat, :empresa)";

    $st = $pdo->prepare($sqlIns);
    $st->bindValue(':nome',   $nomeProduto,     PDO::PARAM_STR);
    $st->bindValue(':qtd',    $quantidade,      PDO::PARAM_INT);
    $st->bindValue(':preco',  $preco,           PDO::PARAM_STR); // decimal em string "12.34"
    $st->bindValue(':img',    $imagemProduto,   PDO::PARAM_STR); // pode ser "", tudo bem
    $st->bindValue(':descr',  $descricaoProduto,PDO::PARAM_STR);
    $st->bindValue(':cat',    $idCategoria,     PDO::PARAM_INT);
    $st->bindValue(':empresa',$idEmpresa,       PDO::PARAM_STR); // IMPORTANTE: slug/string

    $st->execute();

    // Redireciona de volta para a listagem
    $redirect = '../../../erp/delivery/produtoAdicionados.php?id=' . rawurlencode($idEmpresa);
    if (!headers_sent()) {
        header('Location: ' . $redirect);
        exit;
    } else {
        echo '<script>window.location.href=' . json_encode($redirect) . ';</script>';
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Erro ao salvar produto: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "'); history.back();</script>";
    exit;
}

?>
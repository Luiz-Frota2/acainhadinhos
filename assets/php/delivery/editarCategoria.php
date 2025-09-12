<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../conexao.php';

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !isset($_POST['id_categoria'], $_POST['nome_categoria'], $_POST['idSelecionado'])
) {
    echo "<script>
            alert('Dados inválidos para atualização!');
            window.history.back();
          </script>";
    exit;
}

$id_categoria        = (int)$_POST['id_categoria'];
$novo_nome_categoria = trim((string)$_POST['nome_categoria']);
$idSelecionado       = (string)$_POST['idSelecionado'];

if ($id_categoria <= 0 || $novo_nome_categoria === '' || $idSelecionado === '') {
    echo "<script>
            alert('Dados incompletos!');
            window.history.back();
          </script>";
    exit;
}

try {
    // 1) Descobre a empresa dona da categoria
    $stmt = $pdo->prepare("SELECT empresa_id FROM adicionarCategoria WHERE id_categoria = :id LIMIT 1");
    $stmt->execute([':id' => $id_categoria]);
    $cat = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cat) {
        echo "<script>
                alert('Categoria não encontrada.');
                window.history.back();
              </script>";
        exit;
    }

    $empresa_id = (string)$cat['empresa_id'];

    // 2) Verifica se já existe categoria com o mesmo nome na MESMA empresa (ignora a própria)
    $sqlDup = "SELECT COUNT(*) 
                 FROM adicionarCategoria
                WHERE empresa_id = :empresa_id
                  AND id_categoria <> :id_categoria
                  AND TRIM(nome_categoria) = TRIM(:nome)";
    $stmtDup = $pdo->prepare($sqlDup);
    $stmtDup->execute([
        ':empresa_id'   => $empresa_id,
        ':id_categoria' => $id_categoria,
        ':nome'         => $novo_nome_categoria
    ]);
    $existe = (int)$stmtDup->fetchColumn();

    if ($existe > 0) {
        echo "<script>
                alert('Erro: Já existe uma categoria com este nome nesta empresa!');
                window.history.back();
              </script>";
        exit;
    }

    // 3) Atualiza a categoria
    $stmtUp = $pdo->prepare("UPDATE adicionarCategoria SET nome_categoria = :nome WHERE id_categoria = :id LIMIT 1");
    $stmtUp->execute([
        ':nome' => $novo_nome_categoria,
        ':id'   => $id_categoria
    ]);

    echo "<script>
            alert('Categoria atualizada com sucesso!');
            window.location.href='../../../erp/delivery/produtoAdicionados.php?id=" . rawurlencode($idSelecionado) . "';
          </script>";
    exit;

} catch (Exception $e) {
    echo "<script>
            alert('Erro ao atualizar categoria: " . addslashes($e->getMessage()) . "');
            window.history.back();
          </script>";
    exit;
}
?>
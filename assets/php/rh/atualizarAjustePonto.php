<?php
require_once '../conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $cpf = $_POST['cpf'];
  $empresa_id = $_POST['empresa_id'];
  $data = $_POST['data'];
  $entrada = $_POST['entrada'] ?: null;
  $saida_intervalo = $_POST['saida_intervalo'] ?: null;
  $retorno_intervalo = $_POST['retorno_intervalo'] ?: null;
  $saida_final = $_POST['saida_final'] ?: null;

  try {
    $sql = "UPDATE pontos SET
              entrada = :entrada,
              saida_intervalo = :saida_intervalo,
              retorno_intervalo = :retorno_intervalo,
              saida_final = :saida_final
            WHERE cpf = :cpf AND data = :data AND empresa_id = :empresa_id";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':entrada', $entrada);
    $stmt->bindParam(':saida_intervalo', $saida_intervalo);
    $stmt->bindParam(':retorno_intervalo', $retorno_intervalo);
    $stmt->bindParam(':saida_final', $saida_final);
    $stmt->bindParam(':cpf', $cpf);
    $stmt->bindParam(':data', $data);
    $stmt->bindParam(':empresa_id', $empresa_id);

    $stmt->execute();

    echo "<script>
            alert('Registro de ponto atualizado com sucesso!');
            window.location.href = '../../../erp/rh/ajustePonto.php?id=" . urlencode($empresa_id) . "';
          </script>";
  } catch (PDOException $e) {
    echo "<script>
            alert('Erro ao atualizar ponto: " . $e->getMessage() . "');
            history.back();
          </script>";
  }
}
?>

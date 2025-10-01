<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once '../conexao.php'; // Deve definir $pdo (PDO conectado)

// [Opcional] Se o driver suportar, garanta que rowCount conte APENAS linhas realmente alteradas
if (defined('PDO::MYSQL_ATTR_FOUND_ROWS')) {
    try { $pdo->setAttribute(PDO::MYSQL_ATTR_FOUND_ROWS, false); } catch (Throwable $e) {}
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido');
}

/* =========================
   Helpers
   ========================= */
function normalizeDate(?string $d): ?string {
    if ($d === null || $d === '') return null;
    $d = trim($d);

    $dt = DateTime::createFromFormat('d/m/Y', $d);
    if ($dt instanceof DateTime) return $dt->format('Y-m-d');

    $ts = strtotime($d);
    return $ts ? date('Y-m-d', $ts) : null;
}

function normalizeTime(?string $t): ?string {
    if ($t === null || $t === '') return null;
    $t = trim($t);

    $dt = DateTime::createFromFormat('H:i:s', $t);
    if ($dt instanceof DateTime) return $dt->format('H:i:s');

    $dt = DateTime::createFromFormat('H:i', $t);
    if ($dt instanceof DateTime) return $dt->format('H:i:s');

    $ts = strtotime($t);
    return $ts ? date('H:i:s', $ts) : null;
}

/**
 * Decide a URL de retorno
 */
function buildReturnUrl(array $post, array $server): string {
    $isAllowedPath = function(string $url): bool {
        if (preg_match('~^(https?:)?//~i', $url)) return false;
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        if ($path === '' || $path[0] !== '/') return false;
        if (strpos($path, '/erp/rh/') !== 0 && strpos($path, '/rh/') !== 0) {
            // ainda permitimos relativo interno; se quiser endurecer, retorne false aqui
        }
        return true;
    };

    if (!empty($post['return_url']) && $isAllowedPath($post['return_url'])) {
        return $post['return_url'];
    }

    if (!empty($server['HTTP_REFERER'])) {
        $ref = $server['HTTP_REFERER'];
        $parts = parse_url($ref);
        if ($parts) {
            $path  = $parts['path']  ?? '/';
            $query = isset($parts['query']) ? '?' . $parts['query'] : '';
            $candidate = $path . $query;
            if ($isAllowedPath($candidate)) return $candidate;
        }
    }

    $id  = $post['id']  ?? $post['empresa_id'] ?? null;
    $cpf = $post['cpf'] ?? null;
    $mes = $post['mes'] ?? null;
    $ano = $post['ano'] ?? null;

    if ($id && $cpf && $mes && $ano) {
        $q = http_build_query([
            'id'  => (string)$id,
            'cpf' => (string)$cpf,
            'mes' => (string)$mes,
            'ano' => (string)$ano,
        ]);
        return "/erp/rh/pontosIndividuasDias.php?{$q}";
    }

    return "/erp/rh/ajustePonto.php";
}

/* =========================
   Entrada de dados
   ========================= */
$cpf         = isset($_POST['cpf']) ? trim((string)$_POST['cpf']) : '';
$empresa_id  = isset($_POST['empresa_id']) ? (string)$_POST['empresa_id'] : '';
$data_raw    = $_POST['data'] ?? null;

$entrada_raw           = $_POST['entrada'] ?? null;
$saida_intervalo_raw   = $_POST['saida_intervalo'] ?? null;
$retorno_intervalo_raw = $_POST['retorno_intervalo'] ?? null;
$saida_final_raw       = $_POST['saida_final'] ?? null;

// Normalizações
$data              = normalizeDate($data_raw);
$entrada           = normalizeTime($entrada_raw);
$saida_intervalo   = normalizeTime($saida_intervalo_raw);
$retorno_intervalo = normalizeTime($retorno_intervalo_raw);
$saida_final       = normalizeTime($saida_final_raw);

// Validações mínimas
$backUrl = buildReturnUrl($_POST, $_SERVER);
if ($cpf === '' || $empresa_id === '' || $data === null) {
    echo "<script>
            alert('Dados insuficientes: verifique CPF, empresa e data.');
            location.href = " . json_encode($backUrl) . ";
          </script>";
    exit;
}

try {
    $pdo->beginTransaction();

    /* =========================
       Tolerância de 10 min na ENTRADA → zera horas_pendentes = '00:00:00'
       ========================= */
    if ($entrada !== null) {
        $sqlFunc = "SELECT entrada FROM funcionarios WHERE cpf = :cpf AND empresa_id = :empresa_id";
        $stFunc = $pdo->prepare($sqlFunc);
        $stFunc->bindValue(':cpf', $cpf, PDO::PARAM_STR);
        $stFunc->bindValue(':empresa_id', $empresa_id, PDO::PARAM_STR);
        $stFunc->execute();
        $func = $stFunc->fetch(PDO::FETCH_ASSOC);

        if ($func && !empty($func['entrada'])) {
            $refDate = '2000-01-01';
            $t1 = strtotime("$refDate {$entrada}");
            $t2 = strtotime("$refDate {$func['entrada']}");

            if ($t1 !== false && $t2 !== false) {
                $diffMin = abs($t1 - $t2) / 60;
                if ($diffMin <= 10) {
                    $sqlPend = "UPDATE pontos 
                                SET horas_pendentes = '00:00:00'
                                WHERE cpf = :cpf AND data = :data AND empresa_id = :empresa_id";
                    $stPend = $pdo->prepare($sqlPend);
                    $stPend->bindValue(':cpf', $cpf, PDO::PARAM_STR);
                    $stPend->bindValue(':data', $data, PDO::PARAM_STR);
                    $stPend->bindValue(':empresa_id', $empresa_id, PDO::PARAM_STR);
                    $stPend->execute();
                }
            }
        }
    }

    /* =========================
       UPDATE principal — só atualiza se houver diferença real
       Usamos NOT (campo <=> :param) para detectar diferença (null-safe).
       Assim, se nada mudar, o WHERE não casa e rowCount() = 0.
       ========================= */
    $sql = "UPDATE pontos SET
                entrada = :entrada,
                saida_intervalo = :saida_intervalo,
                retorno_intervalo = :retorno_intervalo,
                saida_final = :saida_final
            WHERE cpf = :cpf
              AND data = :data
              AND empresa_id = :empresa_id
              AND (
                    NOT (entrada           <=> :entrada)
                 OR NOT (saida_intervalo   <=> :saida_intervalo)
                 OR NOT (retorno_intervalo <=> :retorno_intervalo)
                 OR NOT (saida_final       <=> :saida_final)
              )";

    $st = $pdo->prepare($sql);

    // Binds com NULL correto
    ($entrada === null)
        ? $st->bindValue(':entrada', null, PDO::PARAM_NULL)
        : $st->bindValue(':entrada', $entrada, PDO::PARAM_STR);

    ($saida_intervalo === null)
        ? $st->bindValue(':saida_intervalo', null, PDO::PARAM_NULL)
        : $st->bindValue(':saida_intervalo', $saida_intervalo, PDO::PARAM_STR);

    ($retorno_intervalo === null)
        ? $st->bindValue(':retorno_intervalo', null, PDO::PARAM_NULL)
        : $st->bindValue(':retorno_intervalo', $retorno_intervalo, PDO::PARAM_STR);

    ($saida_final === null)
        ? $st->bindValue(':saida_final', null, PDO::PARAM_NULL)
        : $st->bindValue(':saida_final', $saida_final, PDO::PARAM_STR);

    $st->bindValue(':cpf', $cpf, PDO::PARAM_STR);
    $st->bindValue(':data', $data, PDO::PARAM_STR);
    $st->bindValue(':empresa_id', $empresa_id, PDO::PARAM_STR);

    $st->execute();
    $afetadas = $st->rowCount();

    $pdo->commit();

    if ($afetadas === 0) {
        // Diferencia "não existe" de "sem mudança"
        $chk = $pdo->prepare("SELECT 1 FROM pontos WHERE cpf = :cpf AND data = :data AND empresa_id = :empresa_id LIMIT 1");
        $chk->bindValue(':cpf', $cpf, PDO::PARAM_STR);
        $chk->bindValue(':data', $data, PDO::PARAM_STR);
        $chk->bindValue(':empresa_id', $empresa_id, PDO::PARAM_STR);
        $chk->execute();
        $existe = (bool)$chk->fetchColumn();

        if (!$existe) {
            echo "<script>
                    alert('Registro não encontrado para este CPF, data e empresa.');
                    location.href = " . json_encode($backUrl) . ";
                  </script>";
            exit;
        } else {
            echo "<script>
                    alert('Nenhum dado alterado (os valores já estavam iguais).');
                    location.href = " . json_encode($backUrl) . ";
                  </script>";
            exit;
        }
    }

    // (Opcional) Verificação pós-update para sua paz de espírito
    // Você pode comentar este bloco em produção se quiser.
    /*
    $ver = $pdo->prepare("
        SELECT entrada, saida_intervalo, retorno_intervalo, saida_final
        FROM pontos
        WHERE cpf = :cpf AND data = :data AND empresa_id = :empresa_id
    ");
    $ver->execute([':cpf'=>$cpf, ':data'=>$data, ':empresa_id'=>$empresa_id]);
    $row = $ver->fetch(PDO::FETCH_ASSOC);
    // console.log com os valores finais (apenas para debug em dev):
    echo '<script>console.log(' . json_encode($row, JSON_UNESCAPED_UNICODE) . ');</script>';
    */

    echo "<script>
            alert('Registro de ponto atualizado com sucesso!');
            location.href = " . json_encode($backUrl) . ";
          </script>";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $msg = addslashes($e->getMessage());
    echo "<script>
            alert('Erro ao atualizar ponto: {$msg}');
            location.href = " . json_encode($backUrl) . ";
          </script>";
    exit;
}

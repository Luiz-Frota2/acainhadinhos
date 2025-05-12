<?php
require_once '../conexaoLocal.php';

// Recupera a pesquisa (caso haja)
$searchQuery = isset($_GET['q']) ? $_GET['q'] : '';

// Se o campo de busca não estiver vazio
if (!empty($searchQuery)) {
    try {
        // Buscar categorias e produtos que contenham o termo de pesquisa
        $sqlCategorias = "SELECT id_categoria, nome_categoria FROM adicionarCategoria WHERE nome_categoria LIKE :searchQuery";
        $stmtCategorias = $pdo->prepare($sqlCategorias);
        $stmtCategorias->execute([':searchQuery' => "%$searchQuery%"]);
        $categorias = $stmtCategorias->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($categorias) > 0) {
            foreach ($categorias as $categoria) {
                echo '<div class="accordion" id="categoriasMenu">';
                echo '<div class="card mt-3">';
                echo '<div class="card-drag" id="heading' . $categoria['id_categoria'] . '">';
                echo '<div class="infos">';
                echo '<a href="#" class="name mb-0" data-bs-toggle="collapse" data-bs-target="#collapse' . $categoria['id_categoria'] . '" aria-expanded="true">';
                echo '<span class="me-2"><i class="fa-solid fa-bowl-food"></i></span>';
                echo '<b>' . htmlspecialchars($categoria['nome_categoria']) . '</b>';
                echo '</a></div></div></div>';

                // Buscar produtos dessa categoria
                $sqlProdutos = "SELECT * FROM adicionarProdutos WHERE id_categoria = :id_categoria AND nome_produto LIKE :searchQuery";
                $stmtProdutos = $pdo->prepare($sqlProdutos);
                $stmtProdutos->execute([
                    ':id_categoria' => $categoria['id_categoria'],
                    ':searchQuery' => "%$searchQuery%"
                ]);
                $produtos = $stmtProdutos->fetchAll(PDO::FETCH_ASSOC);

                echo '<div id="collapse' . $categoria['id_categoria'] . '" class="collapse show" data-parent="#categoriasMenu">';
                echo '<div class="card-body">';

                foreach ($produtos as $produto) {
                    echo '<div class="product-card mb-2 p-2 d-flex align-items-center position-relative">';
                    echo '<img src="../../assets/img/uploads/' . htmlspecialchars($produto['imagem_produto']) . '" class="product-img mb-2">';
                    echo '<div class="product-info">';
                    echo '<h6 class="fw-bold mb-1">' . htmlspecialchars($produto['nome_produto']) . '</h6>';
                    echo '<p class="text-muted mb-1">' . htmlspecialchars($produto['descricao_produto']) . '</p>';
                    echo '<p class="price mb-1">R$ ' . number_format($produto['preco_produto'], 2, ',', '.') . '</p>';
                    echo '</div></div>';
                }

                echo '</div></div></div>';
            }
        } else {
            echo '<p>Nenhum resultado encontrado.</p>';
        }
    } catch (PDOException $e) {
        echo "Erro ao buscar categorias e produtos: " . $e->getMessage();
    }
}
?>

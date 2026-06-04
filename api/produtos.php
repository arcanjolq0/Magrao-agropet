<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/product-display.php';

function json_array(?string $value): array
{
    if ($value === null || trim($value) === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function decimal_or_null($value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }

    return (float) $value;
}

try {
    ensure_product_display_schema();

    $pdo = db();

    $categoryStmt = $pdo->prepare(
        'SELECT id, nome, filtro, slug, icone, subcategorias_json, ordem_exibicao
         FROM categorias
         WHERE ativo = 1
         ORDER BY ordem_exibicao ASC, nome ASC'
    );
    $categoryStmt->execute();

    $categories = [];
    foreach ($categoryStmt->fetchAll() as $category) {
        $categories[] = [
            'id' => (int) $category['id'],
            'name' => $category['nome'],
            'filter' => $category['filtro'],
            'slug' => $category['slug'],
            'icon' => $category['icone'] ?: '📦',
            'subs' => json_array($category['subcategorias_json']),
            'order' => (int) $category['ordem_exibicao'],
        ];
    }

    $productStmt = $pdo->prepare(
        'SELECT p.*, c.nome AS categoria_nome, c.filtro AS categoria_filtro, c.icone AS categoria_icone
         FROM produtos p
         INNER JOIN categorias c ON c.id = p.categoria_id
         WHERE p.ativo = 1 AND c.ativo = 1
         ORDER BY p.ordem_exibicao ASC, p.nome ASC'
    );
    $productStmt->execute();

    $products = [];
    $brands = [];

    foreach ($productStmt->fetchAll() as $product) {
        $priceConsult = (int) $product['preco_sob_consulta'] === 1;
        $price = $priceConsult ? null : decimal_or_null($product['preco_atual']);
        $stock = max(0, (int) ($product['estoque'] ?? 0));
        $stockMinimum = max(0, (int) ($product['estoque_minimo'] ?? 0));
        $inStock = $stock > 0 && (int) ($product['em_estoque'] ?? 1) === 1;
        $showStockQuantity = (int) ($product['mostrar_quantidade_estoque'] ?? 0) === 1;
        $oldPrice = $priceConsult ? null : decimal_or_null($product['preco_antigo']);
        $brand = $product['marca'] ?: 'Linha Geral';

        if ($brand !== '' && !isset($brands[$brand])) {
            $brands[$brand] = [
                'name' => $brand,
                'category' => $product['subcategoria'] ?: $product['categoria_filtro'],
                'description' => 'Linha de produtos disponivel no catalogo.',
            ];
        }

        $products[] = [
            'id' => (int) $product['id'],
            'sku' => $product['sku'],
            'name' => $product['nome'],
            'slug' => $product['slug'],
            'category' => $product['categoria_filtro'],
            'categoryName' => $product['categoria_nome'],
            'categoryId' => (int) $product['categoria_id'],
            'subcategory' => $product['subcategoria'] ?: 'Geral',
            'brand' => $brand,
            'weight' => $product['peso'] ?: 'Consultar',
            'description' => $product['descricao'],
            'longDescription' => $product['descricao_longa'] ?: $product['descricao'],
            'price' => $price,
            'oldPrice' => $oldPrice,
            'priceMode' => $priceConsult ? 'consult' : 'price',
            'icon' => $product['icone'] ?: ($product['categoria_icone'] ?: '📦'),
            'tag' => $product['selo'] ?: ($priceConsult ? 'Sob consulta' : 'Produto'),
            'image' => $product['imagem'] ?: '',
            'gallery' => json_array($product['galeria_json']),
            'popular' => (int) $product['popularidade'],
            'featured' => (int) $product['destaque'] === 1,
            'offer' => (int) $product['oferta'] === 1 || $oldPrice !== null,
            'showHome' => (int) ($product['mostrar_home'] ?? 1) === 1,
            'monthlyEnabled' => (int) ($product['compra_mensal'] ?? 0) === 1,
            'stock' => $stock,
            'stockMinimum' => $stockMinimum,
            'showStockQuantity' => $showStockQuantity,
            'stockLabel' => $inStock ? ($showStockQuantity ? 'Em estoque: ' . $stock : 'Em estoque') : 'Sem estoque',
            'inStock' => $inStock,
            'active' => true,
            'idealFor' => $product['ideal_para'] ?: $product['categoria_filtro'],
            'tags' => json_array($product['tags_json']),
            'specs' => json_array($product['especificacoes_json']),
            'benefits' => json_array($product['beneficios_json']),
            'order' => (int) $product['ordem_exibicao'],
        ];
    }

    echo json_encode([
        'success' => true,
        'categories' => $categories,
        'brands' => array_values($brands),
        'products' => $products,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Nao foi possivel carregar os produtos agora.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

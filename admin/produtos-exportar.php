<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

require_login();
ensure_product_display_schema();

function csv_safe_cell($value): string
{
    $value = (string) $value;
    return preg_match('/^[=+\-@\t\r\n]/', $value) === 1 ? "'" . $value : $value;
}

function csv_safe_row(array $row): array
{
    return array_map('csv_safe_cell', $row);
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="produtos-magrao-agro-pet.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['ID','SKU','Produto','Categoria','Subcategoria','Marca','Peso','Preco atual','Preco antigo','Estoque','Estoque minimo','Ativo','Destaque','Home','Compra mensal','Em estoque','Ordem'], ';');

$stmt = db()->query('SELECT p.*, c.nome AS categoria_nome FROM produtos p INNER JOIN categorias c ON c.id = p.categoria_id ORDER BY p.ordem_exibicao ASC, p.nome ASC');
foreach ($stmt->fetchAll() as $product) {
    fputcsv($output, csv_safe_row([
        $product['id'],
        $product['sku'],
        $product['nome'],
        $product['categoria_nome'],
        $product['subcategoria'],
        $product['marca'],
        $product['peso'],
        $product['preco_atual'],
        $product['preco_antigo'],
        max(0, (int) ($product['estoque'] ?? 0)),
        max(0, (int) ($product['estoque_minimo'] ?? 0)),
        (int) $product['ativo'] === 1 ? 'Sim' : 'Nao',
        (int) $product['destaque'] === 1 ? 'Sim' : 'Nao',
        (int) ($product['mostrar_home'] ?? 1) === 1 ? 'Sim' : 'Nao',
        (int) ($product['compra_mensal'] ?? 0) === 1 ? 'Sim' : 'Nao',
        (int) ($product['em_estoque'] ?? 1) === 1 ? 'Sim' : 'Nao',
        $product['ordem_exibicao'],
    ]), ';');
}

fclose($output);
exit;

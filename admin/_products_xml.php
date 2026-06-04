<?php
declare(strict_types=1);

function product_xml_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8');
}

function product_xml_selected_ids(array $source): array
{
    $rawIds = $source['ids'] ?? [];
    if (!is_array($rawIds)) {
        $rawIds = [$rawIds];
    }

    $ids = [];
    foreach ($rawIds as $rawId) {
        $value = trim((string) $rawId);
        if ($value === '') {
            continue;
        }

        if (!ctype_digit($value) || (int) $value <= 0) {
            throw new InvalidArgumentException('IDs de produto invalidos.');
        }

        $ids[] = (int) $value;
    }

    return array_values(array_unique($ids));
}

function product_xml_export_decimal($value): string
{
    if ($value === null || $value === '') {
        return '0.00';
    }

    $normalized = str_replace(',', '.', (string) $value);
    if (!is_numeric($normalized)) {
        return '0.00';
    }

    return number_format((float) $normalized, 2, '.', '');
}

function product_xml_import_decimal(string $value): ?string
{
    $normalized = str_replace(',', '.', trim($value));
    if ($normalized === '' || !is_numeric($normalized)) {
        return null;
    }

    return number_format((float) $normalized, 2, '.', '');
}

function product_xml_import_integer(string $value): ?int
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if (!ctype_digit($value)) {
        return null;
    }

    return max(0, (int) $value);
}

function product_xml_text(SimpleXMLElement $product, array $fields): string
{
    foreach ($fields as $field) {
        if (isset($product->{$field})) {
            return trim((string) $product->{$field});
        }
    }

    return '';
}

function product_xml_clean_text(string $value, int $maxLength): string
{
    $value = trim(strip_tags($value));
    if ($value === '') {
        return '';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    return substr($value, 0, $maxLength);
}

function product_xml_bool(string $value, int $default = 0): int
{
    $value = strtolower(trim($value));
    if ($value === '') {
        return $default;
    }

    return in_array($value, ['1', 'true', 'sim', 'yes', 'on'], true) ? 1 : 0;
}

function product_xml_table_columns(PDO $pdo, string $table): array
{
    $columns = [];
    $stmt = $pdo->query('SHOW COLUMNS FROM ' . $table);
    foreach ($stmt->fetchAll() as $column) {
        $columns[(string) $column['Field']] = true;
    }

    return $columns;
}

function product_xml_category_id(PDO $pdo, string $categoryName): int
{
    $categoryName = product_xml_clean_text($categoryName, 120);

    if ($categoryName !== '') {
        $stmt = $pdo->prepare('SELECT id FROM categorias WHERE nome = ? OR filtro = ? LIMIT 1');
        $stmt->execute([$categoryName, $categoryName]);
        $categoryId = $stmt->fetchColumn();

        if ($categoryId) {
            return (int) $categoryId;
        }

        $insert = $pdo->prepare(
            'INSERT INTO categorias (nome, filtro, slug, icone, subcategorias_json, ordem_exibicao, ativo)
             VALUES (?, ?, ?, NULL, ?, 999, 1)'
        );
        $insert->execute([$categoryName, $categoryName, slugify($categoryName), '[]']);

        return (int) $pdo->lastInsertId();
    }

    $stmt = $pdo->prepare('SELECT id FROM categorias WHERE ativo = 1 ORDER BY ordem_exibicao ASC, nome ASC LIMIT 1');
    $stmt->execute();
    $categoryId = $stmt->fetchColumn();

    if ($categoryId) {
        return (int) $categoryId;
    }

    $fallbackName = 'Importados';
    $insert = $pdo->prepare(
        'INSERT INTO categorias (nome, filtro, slug, icone, subcategorias_json, ordem_exibicao, ativo)
         VALUES (?, ?, ?, NULL, ?, 999, 1)'
    );
    $insert->execute([$fallbackName, $fallbackName, slugify($fallbackName), '[]']);

    return (int) $pdo->lastInsertId();
}

function product_xml_existing_product(PDO $pdo, int $id): bool
{
    if ($id <= 0) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT id FROM produtos WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);

    return (bool) $stmt->fetchColumn();
}

function product_xml_existing_product_id(PDO $pdo, array $columns, int $id, string $sku, string $name): ?int
{
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT id FROM produtos WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $productId = $stmt->fetchColumn();
        if ($productId) {
            return (int) $productId;
        }
    }

    if ($sku !== '' && isset($columns['sku'])) {
        $stmt = $pdo->prepare('SELECT id FROM produtos WHERE sku = ? LIMIT 1');
        $stmt->execute([$sku]);
        $productId = $stmt->fetchColumn();
        if ($productId) {
            return (int) $productId;
        }
    }

    if ($name !== '') {
        $stmt = $pdo->prepare('SELECT id FROM produtos WHERE nome = ? LIMIT 1');
        $stmt->execute([$name]);
        $productId = $stmt->fetchColumn();
        if ($productId) {
            return (int) $productId;
        }
    }

    return null;
}

function product_xml_safe_image(string $value): ?string
{
    $value = product_xml_clean_text($value, 255);
    if ($value === '') {
        return null;
    }

    if (preg_match('/^(uploads\/produtos\/|assets\/|https?:\/\/)/i', $value) !== 1) {
        return null;
    }

    return $value;
}

function product_xml_assign_column(array &$data, array $columns, string $column, $value): void
{
    if (isset($columns[$column])) {
        $data[$column] = $value;
    }
}

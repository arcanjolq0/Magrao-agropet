<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_products_xml.php';

require_login();
ensure_product_display_schema();

try {
    $receivedIds = array_key_exists('ids', $_GET);
    $ids = product_xml_selected_ids($_GET);
    if ($receivedIds && !$ids) {
        throw new InvalidArgumentException('Selecao de produtos vazia.');
    }

    $params = [];

    $sql = 'SELECT p.*, c.nome AS categoria_nome
            FROM produtos p
            INNER JOIN categorias c ON c.id = p.categoria_id';

    if ($ids) {
        $sql .= ' WHERE p.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $params = $ids;
    } else {
        $sql .= ' WHERE p.ativo = 1';
    }

    $sql .= ' ORDER BY p.ordem_exibicao ASC, p.nome ASC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    header('Content-Type: application/xml; charset=UTF-8');
    header('Content-Disposition: attachment; filename="produtos-magrao-agropet.xml"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<produtos>\n";

    foreach ($products as $product) {
        echo "  <produto>\n";
        echo '    <id>' . product_xml_escape($product['id'] ?? '') . "</id>\n";
        echo '    <nome>' . product_xml_escape($product['nome'] ?? '') . "</nome>\n";
        echo '    <valor>' . product_xml_export_decimal($product['preco_atual'] ?? null) . "</valor>\n";
        echo '    <categoria>' . product_xml_escape($product['categoria_nome'] ?? '') . "</categoria>\n";
        echo '    <descricao>' . product_xml_escape($product['descricao'] ?? '') . "</descricao>\n";

        if (array_key_exists('imagem', $product)) {
            echo '    <imagem>' . product_xml_escape($product['imagem'] ?? '') . "</imagem>\n";
        }
        if (array_key_exists('estoque', $product)) {
            echo '    <estoque>' . max(0, (int) ($product['estoque'] ?? 0)) . "</estoque>\n";
        }
        if (array_key_exists('estoque_minimo', $product)) {
            echo '    <estoque_minimo>' . max(0, (int) ($product['estoque_minimo'] ?? 0)) . "</estoque_minimo>\n";
        }
        if (array_key_exists('mostrar_quantidade_estoque', $product)) {
            echo '    <mostrar_quantidade_estoque>' . (int) ($product['mostrar_quantidade_estoque'] ?? 0) . "</mostrar_quantidade_estoque>\n";
        }
        if (array_key_exists('ativo', $product)) {
            echo '    <ativo>' . (int) $product['ativo'] . "</ativo>\n";
        }
        if (array_key_exists('destaque', $product)) {
            echo '    <destaque>' . (int) $product['destaque'] . "</destaque>\n";
        }
        if (array_key_exists('created_at', $product)) {
            echo '    <criado_em>' . product_xml_escape($product['created_at'] ?? '') . "</criado_em>\n";
        }
        if (array_key_exists('updated_at', $product)) {
            echo '    <atualizado_em>' . product_xml_escape($product['updated_at'] ?? '') . "</atualizado_em>\n";
        }

        echo "  </produto>\n";
    }

    echo "</produtos>\n";
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Selecao de produtos invalida. Volte ao painel e tente novamente.';
} catch (Throwable $exception) {
    error_log('Exportacao XML de produtos: ' . $exception->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Nao foi possivel gerar o XML de produtos. Tente novamente pelo painel administrativo.';
}

exit;

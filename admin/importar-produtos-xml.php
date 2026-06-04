<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_products_xml.php';

require_login();
ensure_product_display_schema();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('produtos.php');
}

verify_csrf();

try {
    if (!class_exists('SimpleXMLElement')) {
        throw new RuntimeException('Leitor XML indisponivel.');
    }

    $file = $_FILES['xml_file'] ?? null;
    if (!$file || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Selecione um arquivo XML.');
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Nao foi possivel receber o arquivo XML.');
    }

    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        throw new RuntimeException('O XML deve ter no maximo 2 MB.');
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Upload invalido.');
    }

    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if ($extension !== 'xml') {
        throw new RuntimeException('Envie um arquivo com extensao .xml.');
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($tmp, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
    $xmlErrors = libxml_get_errors();
    libxml_clear_errors();

    if (!$xml || strtolower($xml->getName()) !== 'produtos') {
        throw new RuntimeException('XML de produtos invalido.');
    }

    if (count($xml->produto) === 0) {
        throw new RuntimeException('O XML nao possui produtos para importar.');
    }

    $duplicateAction = (string) ($_POST['duplicate_action'] ?? 'update');
    if (!in_array($duplicateAction, ['update', 'ignore'], true)) {
        $duplicateAction = 'update';
    }

    $pdo = db();
    $columns = product_xml_table_columns($pdo, 'produtos');
    $total = count($xml->produto);
    $created = 0;
    $updated = 0;
    $ignored = 0;
    $errors = [];
    $warnings = [];

    $pdo->beginTransaction();

    $position = 0;
    foreach ($xml->produto as $xmlProduct) {
        $position++;
        $name = product_xml_clean_text(product_xml_text($xmlProduct, ['nome']), 180);
        $priceText = product_xml_text($xmlProduct, ['valor', 'preco_atual', 'preco']);
        $price = product_xml_import_decimal($priceText);
        $sku = product_xml_clean_text(product_xml_text($xmlProduct, ['sku']), 80);
        $productErrors = [];

        if ($name === '') {
            $productErrors[] = 'campo nome esta vazio';
        }

        if (trim($priceText) === '') {
            $productErrors[] = 'campo valor esta vazio';
        } elseif ($price === null) {
            $productErrors[] = 'valor nao e numerico';
        }

        $rawId = product_xml_text($xmlProduct, ['id']);
        if ($rawId !== '' && !ctype_digit($rawId)) {
            $productErrors[] = 'ID invalido';
        }

        if ($productErrors) {
            $ignored++;
            $errors[] = 'Produto ' . $position . ' nao foi importado: ' . implode(', ', $productErrors) . '.';
            continue;
        }

        $productId = $rawId !== '' ? (int) $rawId : 0;
        $existingProductId = product_xml_existing_product_id($pdo, $columns, $productId, $sku, $name);
        $isNewProduct = $existingProductId === null;

        $categoryName = product_xml_text($xmlProduct, ['categoria']);
        $categoryId = $categoryName !== '' ? product_xml_category_id($pdo, $categoryName) : null;
        $descriptionInput = product_xml_clean_text(product_xml_text($xmlProduct, ['descricao']), 5000);
        $description = $descriptionInput !== '' ? $descriptionInput : $name;
        $stockText = product_xml_text($xmlProduct, ['estoque']);
        $minimumStockText = product_xml_text($xmlProduct, ['estoque_minimo']);
        $stock = product_xml_import_integer($stockText);
        $minimumStock = product_xml_import_integer($minimumStockText);
        if (trim($stockText) !== '' && $stock === null) {
            $warnings[] = 'Produto ' . $position . ': estoque invalido, salvo como 0.';
        }
        if (trim($minimumStockText) !== '' && $minimumStock === null) {
            $warnings[] = 'Produto ' . $position . ': estoque minimo invalido, salvo como 0.';
        }
        $image = product_xml_safe_image(product_xml_text($xmlProduct, ['imagem']));

        $data = [
            'nome' => $name,
            'slug' => slugify($name),
            'preco_atual' => $price,
        ];

        if ($categoryId !== null) {
            $data['categoria_id'] = $categoryId;
        } elseif ($isNewProduct) {
            $data['categoria_id'] = product_xml_category_id($pdo, '');
        }

        if ($descriptionInput !== '' || $isNewProduct) {
            $data['descricao'] = $description;
            product_xml_assign_column($data, $columns, 'descricao_longa', $description);
        }

        if ($sku !== '' || $isNewProduct) {
            product_xml_assign_column($data, $columns, 'sku', $sku !== '' ? $sku : null);
        }

        if ($image !== null || $isNewProduct) {
            product_xml_assign_column($data, $columns, 'imagem', $image);
        }

        if (trim($stockText) !== '' || $isNewProduct || $duplicateAction === 'update') {
            product_xml_assign_column($data, $columns, 'estoque', $stock !== null ? $stock : 0);
            product_xml_assign_column($data, $columns, 'em_estoque', ($stock ?? 0) > 0 ? 1 : 0);
        }

        if (trim($minimumStockText) !== '' || $isNewProduct || $duplicateAction === 'update') {
            product_xml_assign_column($data, $columns, 'estoque_minimo', $minimumStock !== null ? $minimumStock : 0);
        }

        if (isset($xmlProduct->mostrar_quantidade_estoque) || $isNewProduct) {
            product_xml_assign_column($data, $columns, 'mostrar_quantidade_estoque', product_xml_bool(product_xml_text($xmlProduct, ['mostrar_quantidade_estoque']), 0));
        }

        if (isset($xmlProduct->ativo) || $isNewProduct) {
            product_xml_assign_column($data, $columns, 'ativo', product_xml_bool(product_xml_text($xmlProduct, ['ativo']), 1));
        }

        if (isset($xmlProduct->destaque) || $isNewProduct) {
            product_xml_assign_column($data, $columns, 'destaque', product_xml_bool(product_xml_text($xmlProduct, ['destaque']), 0));
        }

        product_xml_assign_column($data, $columns, 'preco_sob_consulta', 0);

        if ($existingProductId !== null) {
            if ($duplicateAction === 'ignore') {
                $ignored++;
                $errors[] = 'Produto ' . $position . ' nao foi importado: ja existe produto com o mesmo SKU, ID ou nome.';
                continue;
            }

            $assignments = [];
            foreach (array_keys($data) as $column) {
                $assignments[] = $column . ' = :' . $column;
            }

            if (isset($columns['updated_at'])) {
                $assignments[] = 'updated_at = CURRENT_TIMESTAMP';
            }

            $data['id'] = $existingProductId;
            $stmt = $pdo->prepare('UPDATE produtos SET ' . implode(', ', $assignments) . ' WHERE id = :id');
            $stmt->execute($data);
            $updated++;
            continue;
        }

        product_xml_assign_column($data, $columns, 'subcategoria', 'Geral');
        product_xml_assign_column($data, $columns, 'marca', 'Linha Geral');
        product_xml_assign_column($data, $columns, 'peso', 'Consultar');
        product_xml_assign_column($data, $columns, 'preco_antigo', null);
        product_xml_assign_column($data, $columns, 'selo', 'Importado');
        product_xml_assign_column($data, $columns, 'icone', null);
        product_xml_assign_column($data, $columns, 'oferta', 0);
        product_xml_assign_column($data, $columns, 'mostrar_home', 1);
        product_xml_assign_column($data, $columns, 'compra_mensal', 0);
        if (!array_key_exists('em_estoque', $data)) {
            product_xml_assign_column($data, $columns, 'em_estoque', 0);
        }
        product_xml_assign_column($data, $columns, 'ordem_exibicao', 0);
        product_xml_assign_column($data, $columns, 'beneficios_json', '[]');
        product_xml_assign_column($data, $columns, 'galeria_json', '[]');
        product_xml_assign_column($data, $columns, 'tags_json', '[]');
        product_xml_assign_column($data, $columns, 'especificacoes_json', '[]');
        product_xml_assign_column($data, $columns, 'popularidade', 50);

        $columnsSql = implode(', ', array_keys($data));
        $valuesSql = ':' . implode(', :', array_keys($data));

        $stmt = $pdo->prepare('INSERT INTO produtos (' . $columnsSql . ') VALUES (' . $valuesSql . ')');
        $stmt->execute($data);
        $created++;
    }

    if ($created === 0 && $updated === 0) {
        $pdo->rollBack();
        $detail = $errors ? ' ' . implode(' ', array_slice($errors, 0, 4)) : '';
        throw new RuntimeException('Nenhum produto valido foi importado.' . $detail);
    }

    $pdo->commit();

    $message = $total . ' produtos encontrados. ' . ($created + $updated) . ' produtos importados com sucesso. Novos: ' . $created . '. Atualizados: ' . $updated . '.';
    if ($ignored > 0) {
        $message .= ' Produtos ignorados ou com erro: ' . $ignored . '.';
    }
    if ($errors) {
        $message .= ' Pendencias: ' . implode(' ', array_slice($errors, 0, 5));
        if (count($errors) > 5) {
            $message .= ' E mais ' . (count($errors) - 5) . ' ocorrencias.';
        }
    }
    if ($warnings) {
        $message .= ' Avisos: ' . implode(' ', array_slice($warnings, 0, 4));
        if (count($warnings) > 4) {
            $message .= ' E mais ' . (count($warnings) - 4) . ' avisos.';
        }
    }
    if ($xmlErrors) {
        $message .= ' O arquivo continha pequenos avisos de leitura, mas os produtos validos foram processados.';
    }

    flash('success', $message);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Importacao XML de produtos: ' . $exception->getMessage());
    $safeMessage = ($exception instanceof RuntimeException && !$exception instanceof PDOException)
        ? $exception->getMessage()
        : 'Nao foi possivel importar o XML. Verifique se o arquivo segue o formato de catalogo de produtos.';
    flash('error', $safeMessage);
}

redirect_to('produtos.php');

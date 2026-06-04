<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

require_login();
verify_csrf();

try {
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim((string) ($_POST['nome'] ?? ''));
    $categoryId = (int) ($_POST['categoria_id'] ?? 0);
    $description = trim((string) ($_POST['descricao'] ?? ''));

    if ($name === '' || $categoryId <= 0 || $description === '') {
        throw new RuntimeException('Preencha nome, categoria e descricao.');
    }

    $currentImage = null;
    if ($id > 0) {
        $imageStmt = db()->prepare('SELECT imagem FROM produtos WHERE id = ? LIMIT 1');
        $imageStmt->execute([$id]);
        $currentImage = $imageStmt->fetchColumn() ?: null;
    }
    $image = upload_product_image('imagem', $currentImage);
    $priceConsult = isset($_POST['preco_sob_consulta']) ? 1 : 0;
    $price = $priceConsult ? null : decimal_input('preco_atual');
    $oldPrice = $priceConsult ? null : decimal_input('preco_antigo');
    $stock = integer_input('estoque');
    $minimumStock = integer_input('estoque_minimo');
    $data = [
        'categoria_id' => $categoryId,
        'sku' => trim((string) ($_POST['sku'] ?? '')) ?: null,
        'nome' => $name,
        'slug' => slugify($name),
        'subcategoria' => trim((string) ($_POST['subcategoria'] ?? '')) ?: 'Geral',
        'marca' => trim((string) ($_POST['marca'] ?? '')) ?: 'Linha Geral',
        'peso' => trim((string) ($_POST['peso'] ?? '')) ?: 'Consultar',
        'descricao' => $description,
        'descricao_longa' => trim((string) ($_POST['descricao_longa'] ?? '')) ?: $description,
        'preco_antigo' => $oldPrice,
        'preco_atual' => $price,
        'preco_sob_consulta' => $priceConsult,
        'selo' => trim((string) ($_POST['selo'] ?? '')) ?: ($priceConsult ? 'Sob consulta' : 'Produto'),
        'imagem' => $image,
        'icone' => trim((string) ($_POST['icone'] ?? '')) ?: '📦',
        'estoque' => $stock,
        'estoque_minimo' => $minimumStock,
        'ativo' => isset($_POST['ativo']) ? 1 : 0,
        'destaque' => isset($_POST['destaque']) ? 1 : 0,
        'mostrar_home' => isset($_POST['mostrar_home']) ? 1 : 0,
        'compra_mensal' => isset($_POST['compra_mensal']) ? 1 : 0,
        'em_estoque' => $stock > 0 && isset($_POST['em_estoque']) ? 1 : 0,
        'mostrar_quantidade_estoque' => isset($_POST['mostrar_quantidade_estoque']) ? 1 : 0,
        'oferta' => isset($_POST['oferta']) ? 1 : 0,
        'ordem_exibicao' => (int) ($_POST['ordem_exibicao'] ?? 0),
    ];

    if ($id > 0) {
        $sql = 'UPDATE produtos SET
          categoria_id = :categoria_id, sku = :sku, nome = :nome, slug = :slug,
          subcategoria = :subcategoria, marca = :marca, peso = :peso,
          descricao = :descricao, descricao_longa = :descricao_longa,
          preco_antigo = :preco_antigo, preco_atual = :preco_atual, preco_sob_consulta = :preco_sob_consulta,
          selo = :selo, imagem = :imagem, icone = :icone, estoque = :estoque, estoque_minimo = :estoque_minimo,
          ativo = :ativo, destaque = :destaque,
          mostrar_home = :mostrar_home, compra_mensal = :compra_mensal, em_estoque = :em_estoque,
          mostrar_quantidade_estoque = :mostrar_quantidade_estoque,
          oferta = :oferta, ordem_exibicao = :ordem_exibicao,
          updated_at = CURRENT_TIMESTAMP
          WHERE id = :id';
        $data['id'] = $id;
        db()->prepare($sql)->execute($data);
        flash('success', 'Produto atualizado.');
    } else {
        $sql = 'INSERT INTO produtos
          (categoria_id, sku, nome, slug, subcategoria, marca, peso, descricao, descricao_longa,
           preco_antigo, preco_atual, preco_sob_consulta, selo, imagem, icone, estoque, estoque_minimo, ativo, destaque, mostrar_home, compra_mensal, em_estoque,
           mostrar_quantidade_estoque, oferta, ordem_exibicao, beneficios_json)
          VALUES
          (:categoria_id, :sku, :nome, :slug, :subcategoria, :marca, :peso, :descricao, :descricao_longa,
           :preco_antigo, :preco_atual, :preco_sob_consulta, :selo, :imagem, :icone, :estoque, :estoque_minimo, :ativo, :destaque, :mostrar_home, :compra_mensal, :em_estoque,
           :mostrar_quantidade_estoque, :oferta, :ordem_exibicao, :beneficios_json)';
        $data['beneficios_json'] = '[]';
        db()->prepare($sql)->execute($data);
        flash('success', 'Produto cadastrado.');
    }
} catch (Throwable $exception) {
    flash('error', $exception->getMessage());
    $fallback = ((int) ($_POST['id'] ?? 0)) > 0 ? 'produto-form.php?id=' . (int) $_POST['id'] : 'produto-form.php';
    redirect_to($fallback);
}

redirect_to('produtos.php');

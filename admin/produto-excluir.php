<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

require_login();
verify_csrf();

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
    $stmt = db()->prepare('SELECT imagem FROM produtos WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    $delete = db()->prepare('DELETE FROM produtos WHERE id = ?');
    $delete->execute([$id]);

    if ($product) {
        delete_uploaded_image($product['imagem'] ?? null);
    }

    flash('success', 'Produto excluido.');
}

redirect_to('produtos.php');

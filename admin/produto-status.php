<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

require_login();
verify_csrf();

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
    $stmt = db()->prepare('UPDATE produtos SET ativo = IF(ativo = 1, 0, 1), updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->execute([$id]);
    flash('success', 'Status do produto atualizado.');
}

redirect_to('produtos.php');

<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

require_login();
verify_csrf();

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
    $stmt = db()->prepare('SELECT COUNT(*) FROM produtos WHERE categoria_id = ?');
    $stmt->execute([$id]);

    if ((int) $stmt->fetchColumn() > 0) {
        flash('error', 'Nao e possivel excluir uma categoria com produtos vinculados. Desative ou mova os produtos primeiro.');
    } else {
        db()->prepare('DELETE FROM categorias WHERE id = ?')->execute([$id]);
        flash('success', 'Categoria excluida.');
    }
}

redirect_to('categorias.php');

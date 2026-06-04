<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

require_login();
verify_csrf();

try {
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim((string) ($_POST['nome'] ?? ''));
    $filter = trim((string) ($_POST['filtro'] ?? '')) ?: $name;

    if ($name === '' || $filter === '') {
        throw new RuntimeException('Informe nome e filtro da categoria.');
    }

    $subs = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($_POST['subcategorias'] ?? '')) ?: [])));
    $data = [
        'nome' => $name,
        'filtro' => $filter,
        'slug' => slugify($filter),
        'icone' => trim((string) ($_POST['icone'] ?? '')) ?: '📦',
        'subcategorias_json' => json_encode($subs, JSON_UNESCAPED_UNICODE),
        'ordem_exibicao' => (int) ($_POST['ordem_exibicao'] ?? 0),
        'ativo' => isset($_POST['ativo']) ? 1 : 0,
    ];

    if ($id > 0) {
        $data['id'] = $id;
        db()->prepare('UPDATE categorias SET nome = :nome, filtro = :filtro, slug = :slug, icone = :icone, subcategorias_json = :subcategorias_json, ordem_exibicao = :ordem_exibicao, ativo = :ativo, updated_at = CURRENT_TIMESTAMP WHERE id = :id')->execute($data);
        flash('success', 'Categoria atualizada.');
    } else {
        db()->prepare('INSERT INTO categorias (nome, filtro, slug, icone, subcategorias_json, ordem_exibicao, ativo) VALUES (:nome, :filtro, :slug, :icone, :subcategorias_json, :ordem_exibicao, :ativo)')->execute($data);
        flash('success', 'Categoria cadastrada.');
    }
} catch (Throwable $exception) {
    flash('error', $exception->getMessage());
}

redirect_to('categorias.php');

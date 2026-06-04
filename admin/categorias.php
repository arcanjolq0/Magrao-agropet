<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_layout.php';

require_login();

$editId = (int) ($_GET['editar'] ?? 0);
$editCategory = null;
if ($editId > 0) {
    $stmt = db()->prepare('SELECT * FROM categorias WHERE id = ? LIMIT 1');
    $stmt->execute([$editId]);
    $editCategory = $stmt->fetch();
}

$categories = get_categories();
admin_header('Categorias', 'categorias');
?>
<section class="admin-page-head">
  <div>
    <h1>Categorias</h1>
    <p>Edite as categorias e subcategorias usadas pelos filtros da vitrine.</p>
  </div>
  <a class="outline-btn" href="categorias.php">Nova categoria</a>
</section>

<form class="admin-panel admin-form-grid" method="post" action="categoria-salvar.php">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
  <input type="hidden" name="id" value="<?= (int) ($editCategory['id'] ?? 0) ?>" />
  <label>Nome exibido
    <input name="nome" value="<?= e($editCategory['nome'] ?? '') ?>" required maxlength="120" placeholder="Cachorros" />
  </label>
  <label>Filtro dos produtos
    <input name="filtro" value="<?= e($editCategory['filtro'] ?? '') ?>" required maxlength="120" placeholder="Caes" />
  </label>
  <label>Icone
    <input name="icone" value="<?= e($editCategory['icone'] ?? '📦') ?>" maxlength="20" />
  </label>
  <label>Ordem
    <input type="number" name="ordem_exibicao" value="<?= e($editCategory['ordem_exibicao'] ?? '0') ?>" />
  </label>
  <label class="full">Subcategorias (uma por linha)
    <textarea name="subcategorias"><?= e(implode("\n", json_decode($editCategory['subcategorias_json'] ?? '[]', true) ?: [])) ?></textarea>
  </label>
  <div class="admin-checks full">
    <label><input type="checkbox" name="ativo" value="1" <?= (int) ($editCategory['ativo'] ?? 1) === 1 ? 'checked' : '' ?> /> Categoria ativa</label>
  </div>
  <div class="admin-actions full">
    <button class="primary" type="submit"><?= $editCategory ? 'Salvar categoria' : 'Cadastrar categoria' ?></button>
    <?php if ($editCategory): ?><a class="outline-btn" href="categorias.php">Cancelar edicao</a><?php endif; ?>
  </div>
</form>

<section class="admin-panel">
  <div class="table-wrap">
    <table class="admin-table">
      <thead>
        <tr><th>Ordem</th><th>Categoria</th><th>Filtro</th><th>Subcategorias</th><th>Status</th><th>Acoes</th></tr>
      </thead>
      <tbody>
        <?php foreach ($categories as $category): ?>
          <tr class="<?= (int) $category['ativo'] === 1 ? '' : 'inactive-row' ?>">
            <td><?= (int) $category['ordem_exibicao'] ?></td>
            <td><strong><?= e($category['icone'] ?: '📦') ?> <?= e($category['nome']) ?></strong></td>
            <td><?= e($category['filtro']) ?></td>
            <td><?= e(implode(', ', json_decode($category['subcategorias_json'] ?? '[]', true) ?: [])) ?></td>
            <td><?= (int) $category['ativo'] === 1 ? 'Ativa' : 'Inativa' ?></td>
            <td>
              <div class="admin-table-actions">
                <a class="table-btn" href="categorias.php?editar=<?= (int) $category['id'] ?>">Editar</a>
                <form class="admin-inline" method="post" action="categoria-status.php">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
                  <input type="hidden" name="id" value="<?= (int) $category['id'] ?>" />
                  <button class="table-btn" type="submit"><?= (int) $category['ativo'] === 1 ? 'Desativar' : 'Ativar' ?></button>
                </form>
                <form class="admin-inline" method="post" action="categoria-excluir.php" onsubmit="return confirm('Excluir esta categoria?');">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
                  <input type="hidden" name="id" value="<?= (int) $category['id'] ?>" />
                  <button class="table-btn danger" type="submit">Excluir</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php admin_footer(); ?>

<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_layout.php';

require_login();

$id = (int) ($_GET['id'] ?? 0);
$product = null;

if ($id > 0) {
    $stmt = db()->prepare('SELECT * FROM produtos WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) {
        flash('error', 'Produto nao encontrado.');
        redirect_to('produtos.php');
    }
}

$categories = get_categories(true);
$title = $product ? 'Editar produto' : 'Novo produto';
admin_header($title, 'produtos');
?>
<section class="admin-page-head">
  <div>
    <h1><?= e($title) ?></h1>
    <p>Altere preço, imagem, destaque e exibição do produto no site sem mexer no código.</p>
  </div>
  <a class="outline-btn" href="produtos.php">Voltar</a>
</section>

<form class="admin-panel admin-form-grid" method="post" action="produto-salvar.php" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
  <input type="hidden" name="id" value="<?= (int) ($product['id'] ?? 0) ?>" />
  <input type="hidden" name="imagem_atual" value="<?= e($product['imagem'] ?? '') ?>" />

  <label>Nome do produto
    <input name="nome" value="<?= e($product['nome'] ?? '') ?>" required maxlength="180" />
  </label>
  <label>Categoria
    <select name="categoria_id" required>
      <?php foreach ($categories as $category): ?>
        <option value="<?= (int) $category['id'] ?>" <?= (int) ($product['categoria_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['nome']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Subcategoria
    <input name="subcategoria" value="<?= e($product['subcategoria'] ?? '') ?>" placeholder="Ex: Racoes" maxlength="120" />
  </label>
  <label>SKU
    <input name="sku" value="<?= e($product['sku'] ?? '') ?>" maxlength="80" />
  </label>
  <label>Marca/Linha
    <input name="marca" value="<?= e($product['marca'] ?? '') ?>" maxlength="120" />
  </label>
  <label>Peso/Tamanho
    <input name="peso" value="<?= e($product['peso'] ?? '') ?>" maxlength="80" />
  </label>
  <label>Preco atual
    <input name="preco_atual" value="<?= e($product['preco_atual'] ?? '') ?>" inputmode="decimal" placeholder="189.90" />
  </label>
  <label>Preco antigo
    <input name="preco_antigo" value="<?= e($product['preco_antigo'] ?? '') ?>" inputmode="decimal" placeholder="209.90" />
  </label>
  <label>Estoque atual
    <input type="number" name="estoque" value="<?= e((string) max(0, (int) ($product['estoque'] ?? 0))) ?>" min="0" step="1" inputmode="numeric" />
  </label>
  <label>Estoque minimo
    <input type="number" name="estoque_minimo" value="<?= e((string) max(0, (int) ($product['estoque_minimo'] ?? 0))) ?>" min="0" step="1" inputmode="numeric" />
  </label>
  <label>Selo
    <input name="selo" value="<?= e($product['selo'] ?? '') ?>" placeholder="Oferta, Mais vendido..." maxlength="80" />
  </label>
  <label>Ordem de exibicao
    <input type="number" name="ordem_exibicao" value="<?= e($product['ordem_exibicao'] ?? '0') ?>" />
  </label>
  <label class="full">Descricao curta
    <textarea name="descricao" required><?= e($product['descricao'] ?? '') ?></textarea>
  </label>
  <label class="full">Descricao completa
    <textarea name="descricao_longa"><?= e($product['descricao_longa'] ?? '') ?></textarea>
  </label>
  <label>Imagem do produto
    <input type="file" name="imagem" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" />
  </label>
  <label>Icone fallback
    <input name="icone" value="<?= e($product['icone'] ?? '📦') ?>" maxlength="20" />
  </label>
  <?php if (!empty($product['imagem'])): ?>
    <div class="full">
      <p class="admin-muted">Imagem atual:</p>
      <img class="admin-thumb" src="../<?= e($product['imagem']) ?>" alt="<?= e($product['nome']) ?>" />
    </div>
  <?php endif; ?>
  <div class="admin-checks full">
    <label><input type="checkbox" name="ativo" value="1" <?= (int) ($product['ativo'] ?? 1) === 1 ? 'checked' : '' ?> /> Produto ativo</label>
    <label><input type="checkbox" name="preco_sob_consulta" value="1" <?= (int) ($product['preco_sob_consulta'] ?? 0) === 1 ? 'checked' : '' ?> /> Preço sob consulta</label>
    <label><input type="checkbox" name="destaque" value="1" <?= (int) ($product['destaque'] ?? 0) === 1 ? 'checked' : '' ?> /> Produto em destaque</label>
    <label><input type="checkbox" name="mostrar_home" value="1" <?= (int) ($product['mostrar_home'] ?? 1) === 1 ? 'checked' : '' ?> /> Mostrar na página inicial</label>
    <label><input type="checkbox" name="compra_mensal" value="1" <?= (int) ($product['compra_mensal'] ?? 0) === 1 ? 'checked' : '' ?> /> Permitir na compra mensal</label>
    <label><input type="checkbox" name="em_estoque" value="1" <?= (int) ($product['em_estoque'] ?? 1) === 1 ? 'checked' : '' ?> /> Disponível em estoque</label>
    <label><input type="checkbox" name="mostrar_quantidade_estoque" value="1" <?= (int) ($product['mostrar_quantidade_estoque'] ?? 0) === 1 ? 'checked' : '' ?> /> Mostrar quantidade em estoque no site</label>
    <label><input type="checkbox" name="oferta" value="1" <?= (int) ($product['oferta'] ?? 0) === 1 ? 'checked' : '' ?> /> Oferta</label>
  </div>
  <div class="admin-actions full">
    <button class="primary" type="submit">Salvar produto</button>
    <a class="outline-btn" href="produtos.php">Cancelar</a>
  </div>
</form>
<?php admin_footer(); ?>

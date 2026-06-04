<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_layout.php';

require_login();
ensure_product_display_schema();

$statusOptions = [
    'todos' => 'Todos',
    'ok' => 'Em estoque',
    'baixo' => 'Estoque baixo',
    'sem' => 'Sem estoque',
];

function admin_stock_status(int $stock, int $minimumStock): array
{
    if ($stock <= 0) {
        return ['key' => 'sem', 'label' => 'Sem estoque', 'class' => 'out'];
    }

    if ($stock <= $minimumStock) {
        return ['key' => 'baixo', 'label' => 'Estoque baixo', 'class' => 'low'];
    }

    return ['key' => 'ok', 'label' => 'Em estoque', 'class' => 'ok'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $redirectSearch = trim((string) ($_POST['busca_atual'] ?? ''));
    $redirectCategory = max(0, (int) ($_POST['categoria_atual'] ?? 0));
    $redirectStatus = (string) ($_POST['status_atual'] ?? 'todos');
    if (!isset($statusOptions[$redirectStatus])) {
        $redirectStatus = 'todos';
    }

    try {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Produto inválido.');
        }

        $stock = integer_input('estoque');
        $minimumStock = integer_input('estoque_minimo');

        $showStockQuantity = isset($_POST['mostrar_quantidade_estoque']) ? 1 : 0;

        $stmt = db()->prepare(
            'UPDATE produtos
                SET estoque = ?, estoque_minimo = ?, em_estoque = ?, mostrar_quantidade_estoque = ?, updated_at = CURRENT_TIMESTAMP
              WHERE id = ?'
        );
        $stmt->execute([$stock, $minimumStock, $stock > 0 ? 1 : 0, $showStockQuantity, $id]);

        flash('success', 'Estoque atualizado.');
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }

    $redirectParams = [];
    if ($redirectSearch !== '') {
        $redirectParams['busca'] = $redirectSearch;
    }
    if ($redirectCategory > 0) {
        $redirectParams['categoria'] = $redirectCategory;
    }
    if ($redirectStatus !== 'todos') {
        $redirectParams['status'] = $redirectStatus;
    }

    $query = http_build_query($redirectParams);
    redirect_to('estoque.php' . ($query !== '' ? '?' . $query : ''));
}

$search = trim((string) ($_GET['busca'] ?? ''));
$categoryId = max(0, (int) ($_GET['categoria'] ?? 0));
$status = (string) ($_GET['status'] ?? 'todos');
if (!isset($statusOptions[$status])) {
    $status = 'todos';
}

$summary = [
    'total' => 0,
    'ok' => 0,
    'baixo' => 0,
    'sem' => 0,
];

try {
    $summaryRow = db()->query(
        'SELECT
            COUNT(*) AS total,
            COALESCE(SUM(CASE WHEN estoque > estoque_minimo THEN 1 ELSE 0 END), 0) AS ok_count,
            COALESCE(SUM(CASE WHEN estoque > 0 AND estoque <= estoque_minimo THEN 1 ELSE 0 END), 0) AS low_count,
            COALESCE(SUM(CASE WHEN estoque <= 0 THEN 1 ELSE 0 END), 0) AS out_count
         FROM produtos'
    )->fetch() ?: [];

    $summary = [
        'total' => (int) ($summaryRow['total'] ?? 0),
        'ok' => (int) ($summaryRow['ok_count'] ?? 0),
        'baixo' => (int) ($summaryRow['low_count'] ?? 0),
        'sem' => (int) ($summaryRow['out_count'] ?? 0),
    ];
} catch (Throwable $exception) {
    $summary = ['total' => 0, 'ok' => 0, 'baixo' => 0, 'sem' => 0];
}

$sql = 'SELECT p.*, c.nome AS categoria_nome
        FROM produtos p
        INNER JOIN categorias c ON c.id = p.categoria_id
        WHERE 1=1';
$params = [];

if ($search !== '') {
    $sql .= ' AND (p.nome LIKE ? OR p.sku LIKE ?)';
    $term = '%' . $search . '%';
    $params[] = $term;
    $params[] = $term;
}

if ($categoryId > 0) {
    $sql .= ' AND p.categoria_id = ?';
    $params[] = $categoryId;
}

if ($status === 'ok') {
    $sql .= ' AND p.estoque > p.estoque_minimo';
} elseif ($status === 'baixo') {
    $sql .= ' AND p.estoque > 0 AND p.estoque <= p.estoque_minimo';
} elseif ($status === 'sem') {
    $sql .= ' AND p.estoque <= 0';
}

$sql .= ' ORDER BY
    CASE
      WHEN p.estoque <= 0 THEN 0
      WHEN p.estoque > 0 AND p.estoque <= p.estoque_minimo THEN 1
      ELSE 2
    END ASC,
    p.nome ASC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
$categories = get_categories();

admin_header('Estoque', 'estoque');
?>
<section class="admin-page-head">
  <div>
    <h1>Estoque</h1>
    <p>Controle rapidamente a quantidade disponível de cada produto.</p>
  </div>
  <div class="admin-actions">
    <a class="outline-btn" href="produtos.php">Voltar para produtos</a>
  </div>
</section>

<section class="admin-grid admin-stock-summary-grid" aria-label="Resumo do estoque">
  <article class="admin-stat"><small>Total de produtos</small><strong><?= $summary['total'] ?></strong><span>itens cadastrados</span></article>
  <article class="admin-stat"><small>Produtos em estoque</small><strong><?= $summary['ok'] ?></strong><span>acima do mínimo</span></article>
  <article class="admin-stat"><small>Estoque baixo</small><strong><?= $summary['baixo'] ?></strong><span>precisam de atenção</span></article>
  <article class="admin-stat"><small>Sem estoque</small><strong><?= $summary['sem'] ?></strong><span>indisponíveis para adicionar</span></article>
</section>

<section class="admin-panel admin-stock-filter-panel">
  <div class="admin-section-head">
    <div>
      <h2>Filtrar estoque</h2>
      <p class="admin-muted">Busque por nome ou SKU e encontre rapidamente produtos com baixo estoque.</p>
    </div>
  </div>
  <form class="admin-controls admin-stock-filters" method="get">
    <input name="busca" value="<?= e($search) ?>" placeholder="Buscar produto ou SKU" />
    <select name="categoria">
      <option value="0">Todas as categorias</option>
      <?php foreach ($categories as $category): ?>
        <option value="<?= (int) $category['id'] ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['nome']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status">
      <?php foreach ($statusOptions as $value => $label): ?>
        <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="outline-btn" type="submit">Filtrar</button>
    <a class="outline-btn" href="estoque.php">Limpar</a>
  </form>
</section>

<section class="admin-panel admin-stock-table-panel">
  <div class="admin-section-head">
    <div>
      <h2>Produtos no estoque</h2>
      <p class="admin-muted">Atualize estoque atual e estoque mínimo sem abrir o cadastro completo.</p>
    </div>
  </div>

  <?php if (!$products): ?>
    <div class="admin-empty">
      <strong>Nenhum produto encontrado.</strong>
      Ajuste os filtros ou cadastre novos produtos para controlar o estoque.
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Imagem</th>
            <th>Produto</th>
            <th>Categoria</th>
            <th class="admin-price-col">Preço</th>
            <th>Estoque atual</th>
            <th>Estoque mínimo</th>
            <th>Status</th>
            <th>Exibir qtd. no site</th>
            <th>Ação rápida</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $product): ?>
            <?php
              $productId = (int) $product['id'];
              $stock = max(0, (int) ($product['estoque'] ?? 0));
              $minimumStock = max(0, (int) ($product['estoque_minimo'] ?? 0));
              $stockStatus = admin_stock_status($stock, $minimumStock);
              $formId = 'stockForm' . $productId;
            ?>
            <tr class="<?= (int) $product['ativo'] === 1 ? '' : 'inactive-row' ?>">
              <td>
                <?php if ($product['imagem']): ?>
                  <img class="admin-thumb" src="../<?= e($product['imagem']) ?>" alt="<?= e($product['nome']) ?>" />
                <?php else: ?>
                  <span class="admin-thumb" style="display:grid;place-items:center"><?= e($product['icone'] ?: 'Produto') ?></span>
                <?php endif; ?>
              </td>
              <td>
                <strong><?= e($product['nome']) ?></strong>
                <small><?= e($product['sku'] ?: 'Sem SKU') ?></small>
              </td>
              <td>
                <?= e($product['categoria_nome']) ?>
                <small><?= e($product['subcategoria'] ?: 'Geral') ?></small>
              </td>
              <td class="admin-price-cell">
                <?php if ((int) $product['preco_sob_consulta'] === 1): ?>
                  Sob consulta
                <?php else: ?>
                  R$ <?= number_format((float) $product['preco_atual'], 2, ',', '.') ?>
                <?php endif; ?>
              </td>
              <td>
                <input class="admin-stock-number" form="<?= e($formId) ?>" type="number" name="estoque" value="<?= $stock ?>" min="0" step="1" inputmode="numeric" />
              </td>
              <td>
                <input class="admin-stock-number" form="<?= e($formId) ?>" type="number" name="estoque_minimo" value="<?= $minimumStock ?>" min="0" step="1" inputmode="numeric" />
              </td>
              <td>
                <span class="admin-stock-badge <?= e($stockStatus['class']) ?>"><?= e($stockStatus['label']) ?></span>
                <small>Atual: <?= $stock ?> | Mínimo: <?= $minimumStock ?></small>
              </td>
              <td>
                <label class="admin-stock-toggle">
                  <input form="<?= e($formId) ?>" type="checkbox" name="mostrar_quantidade_estoque" value="1" <?= (int) ($product['mostrar_quantidade_estoque'] ?? 0) === 1 ? 'checked' : '' ?> />
                  Mostrar unidades
                </label>
              </td>
              <td>
                <form id="<?= e($formId) ?>" class="admin-stock-row-form" method="post">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
                  <input type="hidden" name="id" value="<?= $productId ?>" />
                  <input type="hidden" name="busca_atual" value="<?= e($search) ?>" />
                  <input type="hidden" name="categoria_atual" value="<?= $categoryId ?>" />
                  <input type="hidden" name="status_atual" value="<?= e($status) ?>" />
                  <button class="table-btn" type="submit">Salvar estoque</button>
                  <a class="table-btn" href="produto-form.php?id=<?= $productId ?>">Editar produto</a>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php admin_footer(); ?>

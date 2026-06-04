<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_layout.php';

require_login();

$search = trim((string) ($_GET['busca'] ?? ''));
$categoryId = (int) ($_GET['categoria'] ?? 0);

$sql = 'SELECT p.*, c.nome AS categoria_nome, c.filtro AS categoria_filtro
        FROM produtos p
        INNER JOIN categorias c ON c.id = p.categoria_id
        WHERE 1=1';
$params = [];

if ($search !== '') {
    $sql .= ' AND (p.nome LIKE ? OR p.sku LIKE ? OR p.descricao LIKE ?)';
    $term = '%' . $search . '%';
    $params = [$term, $term, $term];
}

if ($categoryId > 0) {
    $sql .= ' AND p.categoria_id = ?';
    $params[] = $categoryId;
}

$sql .= ' ORDER BY p.ordem_exibicao ASC, p.nome ASC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
$categories = get_categories();

admin_header('Produtos', 'produtos');
?>
<section class="admin-page-head">
  <div>
    <h1>Produtos</h1>
    <p>Importe produtos em lote, cadastre itens manualmente e gerencie o catálogo da loja.</p>
  </div>
  <div class="admin-actions">
    <a class="primary" href="produto-form.php">Novo produto</a>
    <a class="outline-btn" href="estoque.php">Gerenciar estoque</a>
  </div>
</section>

<section class="admin-import-hero">
  <div class="admin-import-copy">
    <span>Função principal</span>
    <h2>Importar produtos por XML</h2>
    <p>Envie um arquivo XML para cadastrar vários produtos de uma só vez no catálogo da loja.</p>
  </div>
  <form class="admin-xml-import-form" method="post" action="importar-produtos-xml.php" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
    <div class="admin-upload-box">
      <strong>Selecione seu arquivo XML</strong>
      <small>O XML deve conter pelo menos nome e valor do produto. Campos como SKU, categoria, descrição, imagem, estoque e estoque mínimo podem ser importados se existirem no arquivo.</small>
      <div class="admin-upload-actions">
        <label class="outline-btn admin-file-btn" for="xmlImportFile">Selecionar arquivo XML
          <input id="xmlImportFile" type="file" name="xml_file" accept=".xml,application/xml,text/xml" required />
        </label>
        <span id="xmlImportFileName" class="admin-file-name">Nenhum arquivo selecionado</span>
      </div>
    </div>
    <div class="admin-duplicate-options" aria-label="Tratamento de produtos duplicados">
      <label><input type="radio" name="duplicate_action" value="update" checked /> Atualizar produtos existentes</label>
      <label><input type="radio" name="duplicate_action" value="ignore" /> Ignorar produtos já existentes</label>
    </div>
    <div class="admin-import-actions">
      <button class="primary" type="submit">Importar produtos</button>
      <a class="outline-btn" href="modelo-produtos.xml" download>Baixar modelo XML</a>
    </div>
  </form>
</section>

<section class="admin-panel admin-products-filter-panel">
  <div class="admin-section-head">
    <div>
      <h2>Filtrar produtos</h2>
      <p class="admin-muted">Busque por nome, SKU ou categoria para editar itens cadastrados.</p>
    </div>
  </div>
  <form class="admin-controls" method="get">
    <input name="busca" value="<?= e($search) ?>" placeholder="Buscar produto ou SKU" />
    <select name="categoria">
      <option value="0">Todas as categorias</option>
      <?php foreach ($categories as $category): ?>
        <option value="<?= (int) $category['id'] ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['nome']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="outline-btn" type="submit">Filtrar</button>
  </form>
</section>

<section class="admin-panel admin-products-export-panel">
  <div class="admin-section-head">
    <div>
      <h2>Exportações</h2>
      <p class="admin-muted">Baixe produtos em XML ou CSV para backup e uso comercial.</p>
    </div>
  </div>

  <div id="xmlSelectionNotice" class="admin-flash error admin-xml-notice" hidden>Selecione pelo menos um produto para exportar em XML.</div>

  <form id="xmlExportSelectedForm" method="get" action="exportar-produtos-xml.php"></form>

  <div class="admin-export-actions">
    <button class="outline-btn" type="button" id="exportSelectedXml">Exportar selecionados em XML</button>
    <a class="outline-btn" href="exportar-produtos-xml.php">Exportar todos em XML</a>
    <a class="outline-btn" href="produtos-exportar.php">Exportar CSV</a>
  </div>
</section>

<section class="admin-panel admin-products-table-panel">
  <div class="admin-section-head">
    <div>
      <h2>Produtos cadastrados</h2>
      <p class="admin-muted">Selecione produtos para exportar em XML ou use as ações da tabela para editar.</p>
    </div>
  </div>
  <div class="table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th class="admin-select-col"><input id="selectAllProductsForXml" type="checkbox" aria-label="Selecionar todos os produtos visiveis" /></th>
          <th>Ordem</th>
          <th>Imagem</th>
          <th>Produto</th>
          <th>Categoria</th>
          <th class="admin-price-col">Preço</th>
          <th>Estoque</th>
          <th>Status</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($products as $product): ?>
          <?php
            $stock = max(0, (int) ($product['estoque'] ?? 0));
            $minimumStock = max(0, (int) ($product['estoque_minimo'] ?? 0));
            $isLowStock = $stock > 0 && $minimumStock > 0 && $stock <= $minimumStock;
          ?>
          <tr class="<?= (int) $product['ativo'] === 1 ? '' : 'inactive-row' ?>">
            <td class="admin-select-col"><input class="admin-product-select" type="checkbox" value="<?= (int) $product['id'] ?>" aria-label="Selecionar <?= e($product['nome']) ?>" /></td>
            <td><?= (int) $product['ordem_exibicao'] ?></td>
            <td>
              <?php if ($product['imagem']): ?>
                <img class="admin-thumb" src="../<?= e($product['imagem']) ?>" alt="<?= e($product['nome']) ?>" />
              <?php else: ?>
                <span class="admin-thumb" style="display:grid;place-items:center"><?= e($product['icone'] ?: '📦') ?></span>
              <?php endif; ?>
            </td>
            <td><strong><?= e($product['nome']) ?></strong><small><?= e($product['sku']) ?></small></td>
            <td><?= e($product['categoria_nome']) ?><small><?= e($product['subcategoria']) ?></small></td>
            <td class="admin-price-cell">
              <?php if ((int) $product['preco_sob_consulta'] === 1): ?>
                Sob consulta
              <?php else: ?>
                R$ <?= number_format((float) $product['preco_atual'], 2, ',', '.') ?>
              <?php endif; ?>
            </td>
            <td class="admin-stock-cell">
              <?php if ($stock <= 0): ?>
                <span class="admin-stock-badge out">Sem estoque</span>
              <?php elseif ($isLowStock): ?>
                <span class="admin-stock-badge low">Estoque baixo: <?= $stock ?></span>
              <?php else: ?>
                <span class="admin-stock-badge ok">Em estoque: <?= $stock ?></span>
              <?php endif; ?>
              <?php if ($minimumStock > 0): ?><small>Mínimo: <?= $minimumStock ?></small><?php endif; ?>
            </td>
            <td>
              <div class="admin-badges">
                <span class="admin-badge <?= (int) $product['ativo'] === 1 ? 'ok' : 'muted' ?>"><?= (int) $product['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></span>
                <?php if ((int) ($product['destaque'] ?? 0) === 1): ?><span class="admin-badge">Destaque</span><?php endif; ?>
                <?php if ((int) ($product['mostrar_home'] ?? 1) === 1): ?><span class="admin-badge">Home</span><?php endif; ?>
                <?php if ((int) ($product['compra_mensal'] ?? 0) === 1): ?><span class="admin-badge">Mensal</span><?php endif; ?>
                <?php if ($stock <= 0 || (int) ($product['em_estoque'] ?? 1) !== 1): ?><span class="admin-badge alert">Indisponível</span><?php endif; ?>
              </div>
            </td>
            <td>
              <div class="admin-table-actions">
                <a class="table-btn" href="produto-form.php?id=<?= (int) $product['id'] ?>">Editar</a>
                <form class="admin-inline" method="post" action="produto-status.php">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
                  <input type="hidden" name="id" value="<?= (int) $product['id'] ?>" />
                  <button class="table-btn" type="submit"><?= (int) $product['ativo'] === 1 ? 'Desativar' : 'Ativar' ?></button>
                </form>
                <form class="admin-inline" method="post" action="produto-excluir.php" onsubmit="return confirm('Excluir este produto?');">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
                  <input type="hidden" name="id" value="<?= (int) $product['id'] ?>" />
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
<script>
  (() => {
    const selectedForm = document.getElementById('xmlExportSelectedForm');
    const selectedButton = document.getElementById('exportSelectedXml');
    const notice = document.getElementById('xmlSelectionNotice');
    const selectAll = document.getElementById('selectAllProductsForXml');
    const checkboxes = Array.from(document.querySelectorAll('.admin-product-select'));
    const fileInput = document.getElementById('xmlImportFile');
    const fileName = document.getElementById('xmlImportFileName');

    const updateSelectAll = () => {
      if (!selectAll || checkboxes.length === 0) {
        return;
      }

      const checkedCount = checkboxes.filter((checkbox) => checkbox.checked).length;
      selectAll.checked = checkedCount === checkboxes.length;
      selectAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
    };

    if (selectAll) {
      selectAll.addEventListener('change', () => {
        checkboxes.forEach((checkbox) => {
          checkbox.checked = selectAll.checked;
        });
        updateSelectAll();
      });
    }

    checkboxes.forEach((checkbox) => {
      checkbox.addEventListener('change', updateSelectAll);
    });

    if (selectedButton && selectedForm) {
      selectedButton.addEventListener('click', () => {
        const selectedIds = checkboxes
          .filter((checkbox) => checkbox.checked)
          .map((checkbox) => checkbox.value);

        if (selectedIds.length === 0) {
          if (notice) {
            notice.hidden = false;
            notice.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
          }
          return;
        }

        if (notice) {
          notice.hidden = true;
        }

        while (selectedForm.firstChild) {
          selectedForm.removeChild(selectedForm.firstChild);
        }

        selectedIds.forEach((id) => {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = 'ids[]';
          input.value = id;
          selectedForm.appendChild(input);
        });

        selectedForm.submit();
      });
    }

    if (fileInput && fileName) {
      fileInput.addEventListener('change', () => {
        fileName.textContent = fileInput.files.length > 0 ? fileInput.files[0].name : 'Nenhum arquivo selecionado';
      });
    }
  })();
</script>
<?php admin_footer(); ?>

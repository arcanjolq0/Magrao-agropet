<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_layout.php';

require_login();

$totalProducts = admin_count('produtos');
$activeProducts = admin_active_count('produtos');
$homeProducts = admin_product_flag_count('mostrar_home');
$monthlyProducts = admin_product_flag_count('compra_mensal');
$featuredProducts = admin_product_flag_count('destaque');
$newAppointments = appointment_count('novo');
$confirmedAppointments = appointment_count('confirmado');
$storeSettings = get_store_settings_config();
$storeHours = get_store_hours_config();

admin_header('Dashboard', 'dashboard');
?>
<section class="admin-hero-dashboard">
  <div>
    <span>Painel da loja</span>
    <h1>Controle rápido da Magrão Agro Pet</h1>
    <p>Gerencie produtos, horários, agendamentos, compra mensal e dados da loja sem mexer no código.</p>
  </div>
  <div class="admin-hero-actions">
    <a class="primary" href="produto-form.php">+ Novo produto</a>
    <a class="outline-btn" href="../index.html" target="_blank" rel="noopener">Ver site</a>
  </div>
</section>

<section class="admin-grid admin-grid-dashboard">
  <article class="admin-stat"><small>Produtos cadastrados</small><strong><?= $totalProducts ?></strong><span><?= $activeProducts ?> ativos no catálogo</span></article>
  <article class="admin-stat"><small>Produtos na home</small><strong><?= $homeProducts ?></strong><span><?= $featuredProducts ?> marcados como destaque</span></article>
  <article class="admin-stat"><small>Compra mensal</small><strong><?= $monthlyProducts ?></strong><span>produtos liberados para recorrência</span></article>
  <article class="admin-stat"><small>Agendamentos novos</small><strong><?= $newAppointments ?></strong><span><?= $confirmedAppointments ?> confirmados</span></article>
  <article class="admin-stat wide"><small>Horário atual</small><strong class="admin-stat-text"><?= e(store_hours_summary($storeHours)) ?></strong></article>
  <article class="admin-stat wide"><small>WhatsApp principal</small><strong class="admin-stat-text"><?= e($storeSettings['whatsappDisplay'] ?? $storeSettings['whatsapp'] ?? 'Não informado') ?></strong></article>
</section>

<section class="admin-panel">
  <div class="admin-section-head">
    <div>
      <h2>Atalhos principais</h2>
      <p class="admin-muted">Use estes botões para as tarefas mais comuns do dono da loja.</p>
    </div>
  </div>
  <div class="admin-shortcuts-grid">
    <a href="produto-form.php"><span>➕</span><strong>Novo produto</strong><small>Cadastrar nome, preço, foto e categoria.</small></a>
    <a href="produtos.php"><span>🛒</span><strong>Produtos</strong><small>Editar cadastro, destaque, home e compra mensal.</small></a>
    <a href="estoque.php"><span>Est</span><strong>Estoque</strong><small>Atualizar quantidades, mínimo e indisponíveis.</small></a>
    <a href="agendamentos.php"><span>🗓️</span><strong>Agendamentos</strong><small>Banho, tosa e veterinário.</small></a>
    <a href="horarios.php"><span>🕒</span><strong>Horários</strong><small>Alterar loja aberta/fechada.</small></a>
    <a href="configuracoes.php"><span>⚙️</span><strong>Configurações</strong><small>WhatsApp, endereço, Instagram e textos.</small></a>
    <a href="produtos-exportar.php"><span>📄</span><strong>Backup CSV</strong><small>Exportar lista de produtos.</small></a>
  </div>
</section>

<section class="admin-panel admin-final-tips">
  <h2>Antes de publicar</h2>
  <ul>
    <li>Cadastre imagens reais para os principais produtos.</li>
    <li>Marque como <strong>Home</strong> apenas os produtos que devem aparecer na página inicial.</li>
    <li>Marque como <strong>Compra mensal</strong> apenas rações e itens recorrentes.</li>
    <li>Depois de criar o primeiro administrador na hospedagem, remova ou renomeie o arquivo <code>admin/setup.php</code>.</li>
  </ul>
</section>
<?php admin_footer(); ?>

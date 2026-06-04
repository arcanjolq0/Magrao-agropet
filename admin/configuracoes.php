<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_layout.php';

require_login();

$settings = get_store_settings_config();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    try {
        $posted = [
            'name' => $_POST['name'] ?? '',
            'whatsapp' => $_POST['whatsapp'] ?? '',
            'whatsappDisplay' => $_POST['whatsappDisplay'] ?? '',
            'instagram' => $_POST['instagram'] ?? '',
            'address' => $_POST['address'] ?? '',
            'mapsLink' => $_POST['mapsLink'] ?? '',
            'mapsEmbed' => $_POST['mapsEmbed'] ?? '',
            'footerDescription' => $_POST['footerDescription'] ?? '',
            'contactIntro' => $_POST['contactIntro'] ?? '',
            'checkoutIntro' => $_POST['checkoutIntro'] ?? '',
            'monthlyIntro' => $_POST['monthlyIntro'] ?? '',
        ];

        $normalized = normalize_store_settings($posted);

        if (strlen($normalized['whatsapp']) < 10) {
            throw new RuntimeException('Informe um WhatsApp válido com DDI, DDD e número. Exemplo: 5547996329281.');
        }

        save_store_settings_config($normalized);
        flash('success', 'Configurações da loja atualizadas com sucesso.');
        redirect_to('configuracoes.php');
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
        redirect_to('configuracoes.php');
    }
}

admin_header('Configurações da loja', 'configuracoes');
?>
<section class="admin-page-head">
  <div>
    <h1>Configurações da loja</h1>
    <p>Edite os dados que aparecem no site público, nos botões de WhatsApp, no mapa, no rodapé e nas mensagens automáticas.</p>
  </div>
  <a class="outline-btn" href="../index.html" target="_blank" rel="noopener">Ver site</a>
</section>

<section class="admin-panel">
  <h2>Dados principais</h2>
  <form method="post" class="admin-form-grid admin-settings-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />

    <label>Nome da loja
      <input name="name" value="<?= e($settings['name']) ?>" required />
    </label>

    <label>WhatsApp com DDI e DDD
      <input name="whatsapp" value="<?= e($settings['whatsapp']) ?>" placeholder="Ex: 5547996329281" required />
    </label>

    <label>WhatsApp exibido no site
      <input name="whatsappDisplay" value="<?= e($settings['whatsappDisplay']) ?>" placeholder="Ex: (47) 99632-9281" />
    </label>

    <label>Instagram
      <input name="instagram" value="<?= e($settings['instagram']) ?>" placeholder="https://www.instagram.com/sua_loja/" />
    </label>

    <label class="full">Endereço completo
      <input name="address" value="<?= e($settings['address']) ?>" />
    </label>

    <label class="full">Link do Google Maps / Como chegar
      <input name="mapsLink" value="<?= e($settings['mapsLink']) ?>" />
    </label>

    <label class="full">Link de mapa incorporado
      <input name="mapsEmbed" value="<?= e($settings['mapsEmbed']) ?>" />
    </label>

    <label class="full">Texto curto do rodapé
      <textarea name="footerDescription"><?= e($settings['footerDescription']) ?></textarea>
    </label>

    <label class="full">Mensagem inicial do formulário de contato
      <textarea name="contactIntro"><?= e($settings['contactIntro']) ?></textarea>
    </label>

    <label class="full">Mensagem inicial do carrinho pelo WhatsApp
      <textarea name="checkoutIntro"><?= e($settings['checkoutIntro']) ?></textarea>
    </label>

    <label class="full">Mensagem inicial da compra mensal de ração
      <textarea name="monthlyIntro"><?= e($settings['monthlyIntro']) ?></textarea>
    </label>

    <div class="admin-actions full">
      <button class="primary" type="submit">Salvar configurações</button>
      <a class="outline-btn" href="index.php">Voltar ao dashboard</a>
    </div>
  </form>
</section>

<section class="admin-panel">
  <h2>Prévia rápida</h2>
  <div class="admin-preview-grid">
    <article>
      <small>WhatsApp</small>
      <strong><?= e($settings['whatsappDisplay']) ?></strong>
      <span><?= e($settings['whatsapp']) ?></span>
    </article>
    <article>
      <small>Endereço</small>
      <strong><?= e($settings['name']) ?></strong>
      <span><?= e($settings['address']) ?></span>
    </article>
    <article>
      <small>Instagram</small>
      <strong>Perfil da loja</strong>
      <span><?= e($settings['instagram']) ?></span>
    </article>
  </div>
  <p class="admin-muted">Após salvar, o site público passa a buscar esses dados pela API <code>/api/config.php</code>.</p>
</section>
<?php admin_footer(); ?>

<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_layout.php';

require_login();

$hours = get_store_hours_config();
$days = $hours['days'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    try {
        $postedOpen = $_POST['open'] ?? [];
        $postedFrom = $_POST['from'] ?? [];
        $postedTo = $_POST['to'] ?? [];
        $newDays = [];

        foreach (default_store_hours()['days'] as $defaultDay) {
            $key = $defaultDay['key'];
            $isOpen = is_array($postedOpen) && isset($postedOpen[$key]);
            $from = is_array($postedFrom) ? trim((string) ($postedFrom[$key] ?? $defaultDay['from'])) : $defaultDay['from'];
            $to = is_array($postedTo) ? trim((string) ($postedTo[$key] ?? $defaultDay['to'])) : $defaultDay['to'];

            if ($isOpen) {
                if (!valid_store_time($from) || !valid_store_time($to)) {
                    throw new RuntimeException('Informe horários válidos no formato HH:MM.');
                }
                if (store_time_to_minutes($from) >= store_time_to_minutes($to)) {
                    throw new RuntimeException('O horário de abertura deve ser menor que o horário de fechamento.');
                }
            }

            $newDays[] = [
                'key' => $key,
                'label' => $defaultDay['label'],
                'short' => $defaultDay['short'],
                'open' => $isOpen,
                'from' => valid_store_time($from) ? $from : $defaultDay['from'],
                'to' => valid_store_time($to) ? $to : $defaultDay['to'],
            ];
        }

        save_store_hours_config([
            'timezone' => 'America/Sao_Paulo',
            'days' => $newDays,
        ]);

        flash('success', 'Horários de funcionamento atualizados com sucesso.');
        redirect_to('horarios.php');
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
        redirect_to('horarios.php');
    }
}

admin_header('Horários de funcionamento', 'horarios');
?>
<section class="admin-page-head">
  <div>
    <h1>Horários de funcionamento</h1>
    <p>Altere os horários usados pelo aviso automático de loja fechada e pela página de contato do site.</p>
  </div>
  <a class="outline-btn" href="../index.html" target="_blank" rel="noopener">Ver site</a>
</section>

<section class="admin-panel">
  <h2>Status automático da loja</h2>
  <p class="admin-muted">Resumo atual: <strong><?= e(store_hours_summary($hours)) ?></strong></p>
  <p class="admin-muted">Fuso aplicado: America/Sao_Paulo / Joinville.</p>

  <form method="post" class="admin-hours-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />

    <div class="admin-hours-list">
      <?php foreach ($days as $day): ?>
        <div class="admin-hour-row">
          <label class="admin-hour-toggle">
            <input type="checkbox" name="open[<?= e($day['key']) ?>]" value="1" <?= $day['open'] ? 'checked' : '' ?> />
            <span><?= e($day['label']) ?></span>
          </label>

          <label>
            Abre
            <input type="time" name="from[<?= e($day['key']) ?>]" value="<?= e($day['from']) ?>" />
          </label>

          <label>
            Fecha
            <input type="time" name="to[<?= e($day['key']) ?>]" value="<?= e($day['to']) ?>" />
          </label>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="admin-actions admin-hours-actions">
      <button class="primary" type="submit">Salvar horários</button>
      <a class="outline-btn" href="index.php">Voltar ao dashboard</a>
    </div>
  </form>
</section>

<section class="admin-panel">
  <h2>Como isso aparece para o cliente</h2>
  <p class="admin-muted">Quando a loja estiver aberta, a faixa laranja fica escondida. Quando estiver fechada, o site mostra automaticamente “Loja fechada agora”, o próximo horário de abertura e o botão do WhatsApp.</p>
</section>
<?php admin_footer(); ?>

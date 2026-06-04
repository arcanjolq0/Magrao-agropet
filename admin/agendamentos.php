<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_layout.php';

require_login();
ensure_appointments_table();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    try {
        $id = (int) ($_POST['id'] ?? 0);
        $status = normalize_appointment_status((string) ($_POST['status'] ?? 'novo'));

        if ($id <= 0) {
            throw new RuntimeException('Agendamento inválido.');
        }

        $stmt = db()->prepare('UPDATE agendamentos SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);

        flash('success', 'Status do agendamento atualizado.');
        redirect_to('agendamentos.php');
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
        redirect_to('agendamentos.php');
    }
}

$filter = normalize_appointment_status((string) ($_GET['status'] ?? 'novo'));
$allStatuses = appointment_statuses();

if (isset($_GET['status']) && $_GET['status'] === 'todos') {
    $filter = 'todos';
}

if ($filter === 'todos') {
    $stmt = db()->query('SELECT * FROM agendamentos ORDER BY created_at DESC LIMIT 120');
    $appointments = $stmt->fetchAll();
} else {
    $stmt = db()->prepare('SELECT * FROM agendamentos WHERE status = ? ORDER BY created_at DESC LIMIT 120');
    $stmt->execute([$filter]);
    $appointments = $stmt->fetchAll();
}

admin_header('Agendamentos', 'agendamentos');
?>
<section class="admin-page-head">
  <div>
    <h1>Agendamentos</h1>
    <p>Acompanhe pedidos de banho, tosa e serviços veterinários recebidos pelo site.</p>
  </div>
  <a class="primary" href="../agendamento.html" target="_blank" rel="noopener">Ver página de agendamento</a>
</section>

<section class="admin-grid">
  <article class="admin-stat"><small>Novos</small><strong><?= appointment_count('novo') ?></strong></article>
  <article class="admin-stat"><small>Confirmados</small><strong><?= appointment_count('confirmado') ?></strong></article>
  <article class="admin-stat"><small>Total</small><strong><?= appointment_count() ?></strong></article>
</section>

<section class="admin-panel">
  <div class="admin-section-head">
    <div>
      <h2>Lista de agendamentos</h2>
      <p class="admin-muted">Use os filtros para acompanhar o que ainda precisa de confirmação.</p>
    </div>
    <div class="admin-actions">
      <a class="<?= $filter === 'novo' ? 'primary' : 'outline-btn' ?>" href="agendamentos.php?status=novo">Novos</a>
      <a class="<?= $filter === 'confirmado' ? 'primary' : 'outline-btn' ?>" href="agendamentos.php?status=confirmado">Confirmados</a>
      <a class="<?= $filter === 'todos' ? 'primary' : 'outline-btn' ?>" href="agendamentos.php?status=todos">Todos</a>
    </div>
  </div>

  <?php if (!$appointments): ?>
    <div class="admin-empty">
      <strong>Nenhum agendamento encontrado.</strong>
      <p>Quando um cliente preencher o formulário de serviço, o pedido aparecerá aqui.</p>
    </div>
  <?php else: ?>
    <div class="admin-appointments-list">
      <?php foreach ($appointments as $appointment): ?>
        <article class="admin-appointment-card status-<?= e($appointment['status']) ?>">
          <div class="appointment-main">
            <div>
              <span class="appointment-service"><?= e(appointment_service_label($appointment['servico'])) ?></span>
              <h3><?= e($appointment['nome_cliente']) ?></h3>
              <p><?= e($appointment['whatsapp']) ?></p>
            </div>
            <span class="appointment-status"><?= e(appointment_status_label($appointment['status'])) ?></span>
          </div>

          <div class="appointment-details">
            <span><strong>Pet:</strong> <?= e($appointment['pet_nome'] ?: 'Não informado') ?></span>
            <span><strong>Tipo:</strong> <?= e($appointment['tipo_pet'] ?: 'Não informado') ?></span>
            <span><strong>Porte:</strong> <?= e($appointment['porte_pet'] ?: 'Não informado') ?></span>
            <span><strong>Data:</strong> <?= e($appointment['data_preferida'] ?: 'A combinar') ?></span>
            <span><strong>Período:</strong> <?= e($appointment['periodo_preferido'] ?: 'A combinar') ?></span>
            <span><strong>Recebido:</strong> <?= e(date('d/m/Y H:i', strtotime((string) $appointment['created_at']))) ?></span>
          </div>

          <?php if (!empty($appointment['observacoes'])): ?>
            <p class="appointment-notes"><?= nl2br(e($appointment['observacoes'])) ?></p>
          <?php endif; ?>

          <div class="admin-table-actions appointment-actions">
            <?php foreach ($allStatuses as $statusKey => $statusLabel): ?>
              <?php if ($statusKey !== $appointment['status']): ?>
                <form method="post" class="admin-inline">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
                  <input type="hidden" name="id" value="<?= (int) $appointment['id'] ?>" />
                  <input type="hidden" name="status" value="<?= e($statusKey) ?>" />
                  <button type="submit" class="outline-btn small-btn"><?= e($statusLabel) ?></button>
                </form>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php admin_footer(); ?>

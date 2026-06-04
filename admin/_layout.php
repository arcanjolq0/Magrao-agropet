<?php
declare(strict_types=1);

function admin_header(string $title, string $active = ''): void
{
    $admin = current_admin();
    $messages = consume_flash();
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e($title) ?> | Admin Magrao Agro Pet</title>
  <meta name="robots" content="noindex,nofollow" />
  <link rel="icon" href="../assets/logo-magrao-agropet.png" />
  <link rel="stylesheet" href="../css/style.css" />
  <link rel="stylesheet" href="../css/admin.css" />
</head>
<body class="admin-body">
  <header class="admin-top">
    <div class="admin-top-inner">
      <a class="admin-brand" href="index.php">
        <img src="../assets/logo-magrao-agropet.png" alt="Magrao Agro Pet" />
        <span class="admin-brand-text">Painel administrativo</span>
      </a>
      <?php if ($admin): ?>
        <button class="admin-menu-toggle" type="button" aria-label="Abrir menu do painel" aria-expanded="false" aria-controls="adminNav" data-admin-menu-toggle>
          <span></span>
          <span></span>
          <span></span>
        </button>
        <nav class="admin-nav" id="adminNav" aria-label="Navegação administrativa">
          <a class="admin-nav-link <?= $active === 'dashboard' ? 'active' : '' ?>" href="index.php">Dashboard</a>
          <a class="admin-nav-link <?= $active === 'produtos' ? 'active' : '' ?>" href="produtos.php">Produtos</a>
          <a class="admin-nav-link <?= $active === 'estoque' ? 'active' : '' ?>" href="estoque.php">Estoque</a>
          <a class="admin-nav-link <?= $active === 'categorias' ? 'active' : '' ?>" href="categorias.php">Categorias</a>
          <a class="admin-nav-link <?= $active === 'horarios' ? 'active' : '' ?>" href="horarios.php">Horários</a>
          <a class="admin-nav-link <?= $active === 'agendamentos' ? 'active' : '' ?>" href="agendamentos.php">Agendamentos</a>
          <a class="admin-nav-link <?= $active === 'configuracoes' ? 'active' : '' ?>" href="configuracoes.php">Configurações</a>
          <a class="admin-nav-link admin-nav-external" href="../index.html" target="_blank" rel="noopener">Ver site</a>
          <a class="admin-nav-link admin-nav-logout" href="logout.php">Logout</a>
        </nav>
      <?php endif; ?>
    </div>
  </header>

  <main class="admin-main">
    <?php foreach ($messages as $message): ?>
      <div class="admin-flash <?= e($message['type']) ?>"><?= e($message['message']) ?></div>
    <?php endforeach; ?>
    <?php
}

function admin_footer(): void
{
    ?>
  </main>
  <script>
    (() => {
      const toggle = document.querySelector('[data-admin-menu-toggle]');
      const nav = document.getElementById('adminNav');

      if (!toggle || !nav) {
        return;
      }

      document.body.classList.add('admin-menu-ready');

      const closeMenu = () => {
        toggle.setAttribute('aria-expanded', 'false');
        nav.classList.remove('is-open');
      };

      toggle.addEventListener('click', () => {
        const shouldOpen = toggle.getAttribute('aria-expanded') !== 'true';
        toggle.setAttribute('aria-expanded', String(shouldOpen));
        nav.classList.toggle('is-open', shouldOpen);
      });

      nav.addEventListener('click', (event) => {
        if (event.target.closest('a')) {
          closeMenu();
        }
      });
    })();
  </script>
</body>
</html>
<?php
}

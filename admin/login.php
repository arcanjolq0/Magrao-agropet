<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if (current_admin()) {
    redirect_to('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['senha'] ?? '');

    $stmt = db()->prepare('SELECT * FROM admin_users WHERE email = ? AND ativo = 1 LIMIT 1');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['senha_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['id'];
        redirect_to('index.php');
    }

    $error = 'E-mail ou senha invalidos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login | Admin Magrao Agro Pet</title>
  <meta name="robots" content="noindex,nofollow" />
  <link rel="icon" href="../assets/logo-magrao-agropet.png" />
  <link rel="stylesheet" href="../css/style.css" />
  <link rel="stylesheet" href="../css/admin.css" />
</head>
<body>
  <main class="admin-login">
    <form class="admin-login-card admin-form-grid" method="post" autocomplete="on">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
      <img src="../assets/logo-magrao-agropet.png" alt="Magrao Agro Pet" />
      <h1 class="full">Acesso administrativo</h1>
      <p class="full">Entre para cadastrar, editar e organizar os produtos da loja.</p>
      <?php if ($error): ?><div class="admin-flash error full"><?= e($error) ?></div><?php endif; ?>
      <label class="full">E-mail
        <input type="email" name="email" required autofocus />
      </label>
      <label class="full">Senha
        <input type="password" name="senha" required />
      </label>
      <button class="primary full" type="submit">Entrar</button>
      <a class="outline-btn full" href="../index.html">Voltar ao site</a>
    </form>
  </main>
</body>
</html>

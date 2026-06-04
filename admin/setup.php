<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$hasUsers = (int) db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() > 0;
if ($hasUsers) {
    redirect_to('login.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name = trim((string) ($_POST['nome'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['senha'] ?? '');
    $confirm = (string) ($_POST['confirmar_senha'] ?? '');

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Informe nome e e-mail validos.';
    } elseif (strlen($password) < 8) {
        $error = 'Use uma senha com pelo menos 8 caracteres.';
    } elseif ($password !== $confirm) {
        $error = 'As senhas nao conferem.';
    } else {
        $stmt = db()->prepare('INSERT INTO admin_users (nome, email, senha_hash, ativo) VALUES (?, ?, ?, 1)');
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        flash('success', 'Administrador criado. Faca login para continuar.');
        redirect_to('login.php');
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Primeiro acesso | Admin Magrao Agro Pet</title>
  <meta name="robots" content="noindex,nofollow" />
  <link rel="icon" href="../assets/logo-magrao-agropet.png" />
  <link rel="stylesheet" href="../css/style.css" />
  <link rel="stylesheet" href="../css/admin.css" />
</head>
<body>
  <main class="admin-login">
    <form class="admin-login-card admin-form-grid" method="post" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
      <img src="../assets/logo-magrao-agropet.png" alt="Magrao Agro Pet" />
      <h1 class="full">Criar administrador</h1>
      <p class="full">Este passo aparece apenas enquanto ainda nao existe usuario no banco.</p>
      <?php if ($error): ?><div class="admin-flash error full"><?= e($error) ?></div><?php endif; ?>
      <label class="full">Nome
        <input name="nome" required />
      </label>
      <label class="full">E-mail
        <input type="email" name="email" required />
      </label>
      <label class="full">Senha
        <input type="password" name="senha" minlength="8" required />
      </label>
      <label class="full">Confirmar senha
        <input type="password" name="confirmar_senha" minlength="8" required />
      </label>
      <button class="primary full" type="submit">Criar acesso</button>
    </form>
  </main>
</body>
</html>

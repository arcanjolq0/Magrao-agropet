<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/store-hours.php';
require_once __DIR__ . '/../config/store-settings.php';
require_once __DIR__ . '/../config/appointments.php';
require_once __DIR__ . '/../config/product-display.php';

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('magrao_admin');

    $secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

ensure_product_display_schema();

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect_to(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function starts_with(string $value, string $prefix): bool
{
    return substr($value, 0, strlen($prefix)) === $prefix;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Sessao expirada. Volte e tente novamente.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function consume_flash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, nome, email FROM admin_users WHERE id = ? AND ativo = 1 LIMIT 1');
    $stmt->execute([(int) $_SESSION['admin_id']]);
    $admin = $stmt->fetch();

    return $admin ?: null;
}

function require_login(): array
{
    $admin = current_admin();
    if (!$admin) {
        redirect_to('login.php');
    }

    return $admin;
}

function slugify(string $text): string
{
    $converted = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) : false;
    $text = $converted !== false ? $converted : $text;
    $text = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $text) ?? '');
    $text = trim($text, '-');
    return $text !== '' ? $text : 'item';
}

function decimal_input(string $key): ?string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    if ($value === '') {
        return null;
    }

    $value = str_replace(',', '.', $value);
    if (!is_numeric($value)) {
        throw new RuntimeException('Informe um preco valido.');
    }

    return number_format((float) $value, 2, '.', '');
}

function integer_input(string $key): int
{
    $value = trim((string) ($_POST[$key] ?? ''));
    if ($value === '') {
        return 0;
    }

    if (!ctype_digit($value)) {
        throw new RuntimeException('Informe um valor inteiro valido para estoque.');
    }

    return max(0, (int) $value);
}

function get_categories(bool $onlyActive = false): array
{
    $sql = 'SELECT * FROM categorias';
    if ($onlyActive) {
        $sql .= ' WHERE ativo = 1';
    }
    $sql .= ' ORDER BY ordem_exibicao ASC, nome ASC';

    return db()->query($sql)->fetchAll();
}

function upload_product_image(string $field, ?string $currentImage = null): ?string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $currentImage;
    }

    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Nao foi possivel enviar a imagem.');
    }

    if (($file['size'] ?? 0) > 3 * 1024 * 1024) {
        throw new RuntimeException('A imagem deve ter no maximo 3 MB.');
    }

    $tmp = (string) $file['tmp_name'];
    if (!is_uploaded_file($tmp)) {
        throw new RuntimeException('Upload invalido.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime]) || getimagesize($tmp) === false) {
        throw new RuntimeException('Envie apenas imagens JPG, PNG ou WebP.');
    }

    $uploadDir = realpath(__DIR__ . '/../uploads/produtos');
    if ($uploadDir === false) {
        if (!mkdir(__DIR__ . '/../uploads/produtos', 0755, true) && !is_dir(__DIR__ . '/../uploads/produtos')) {
            throw new RuntimeException('Nao foi possivel criar a pasta de uploads.');
        }
        $uploadDir = realpath(__DIR__ . '/../uploads/produtos');
    }

    if ($uploadDir === false) {
        throw new RuntimeException('Pasta de uploads indisponivel.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $destination = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmp, $destination)) {
        throw new RuntimeException('Nao foi possivel salvar a imagem.');
    }

    if ($currentImage && $currentImage !== 'uploads/produtos/' . $filename) {
        delete_uploaded_image($currentImage);
    }

    return 'uploads/produtos/' . $filename;
}

function delete_uploaded_image(?string $path): void
{
    if (!$path || !starts_with($path, 'uploads/produtos/')) {
        return;
    }

    $baseDir = realpath(__DIR__ . '/../uploads/produtos');
    $file = realpath(__DIR__ . '/../' . $path);

    if ($baseDir && $file && starts_with($file, $baseDir) && is_file($file)) {
        @unlink($file);
    }
}

function admin_count(string $table): int
{
    $allowed = ['produtos', 'categorias', 'admin_users', 'agendamentos'];
    if (!in_array($table, $allowed, true)) {
        return 0;
    }

    return (int) db()->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
}

function admin_active_count(string $table): int
{
    $allowed = ['produtos', 'categorias', 'admin_users', 'agendamentos'];
    if (!in_array($table, $allowed, true)) {
        return 0;
    }

    return (int) db()->query("SELECT COUNT(*) FROM {$table} WHERE ativo = 1")->fetchColumn();
}


function admin_product_flag_count(string $column): int
{
    $allowed = ['destaque', 'oferta', 'mostrar_home', 'compra_mensal', 'em_estoque'];
    if (!in_array($column, $allowed, true)) {
        return 0;
    }

    try {
        return (int) db()->query("SELECT COUNT(*) FROM produtos WHERE ativo = 1 AND {$column} = 1")->fetchColumn();
    } catch (Throwable $exception) {
        return 0;
    }
}

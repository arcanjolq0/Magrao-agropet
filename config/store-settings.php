<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

function default_store_settings(): array
{
    return [
        'name' => 'Magrão Agro Pet',
        'whatsapp' => '5547996329281',
        'whatsappDisplay' => '(47) 99632-9281',
        'instagram' => 'https://www.instagram.com/magraoagropet/',
        'address' => 'R. Ten. Antônio João, 1136 - Bom Retiro, Joinville - SC, 89222-401',
        'mapsLink' => 'https://www.google.com/maps/search/?api=1&query=R.%20Ten.%20Ant%C3%B4nio%20Jo%C3%A3o%2C%201136%20-%20Bom%20Retiro%2C%20Joinville%20-%20SC%2C%2089222-401',
        'mapsEmbed' => 'https://www.google.com/maps?q=R.%20Ten.%20Ant%C3%B4nio%20Jo%C3%A3o%2C%201136%20-%20Bom%20Retiro%2C%20Joinville%20-%20SC%2C%2089222-401&output=embed',
        'footerDescription' => 'Loja online da Magrão Agro Pet para vender produtos da loja física com catálogo, páginas de produto, carrinho e pedidos pelo WhatsApp.',
        'contactIntro' => 'Olá, estou entrando em contato pelo site da Magrão Agro Pet.',
        'checkoutIntro' => 'Olá, gostaria de fazer este pedido pelo site da Magrão Agro Pet:',
        'monthlyIntro' => 'Olá, gostaria de agendar uma compra mensal de ração pela Magrão Agro Pet.',
    ];
}

function ensure_store_settings_table(): void
{
    db()->exec(
        "CREATE TABLE IF NOT EXISTS loja_config (
            chave VARCHAR(80) NOT NULL PRIMARY KEY,
            valor TEXT NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $stmt = db()->prepare('SELECT COUNT(*) FROM loja_config WHERE chave = ?');
    $stmt->execute(['dados_loja']);

    if ((int) $stmt->fetchColumn() === 0) {
        save_store_settings_config(default_store_settings());
    }
}

function clean_whatsapp_number(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?: '';
}

function normalize_public_url(string $value, string $fallback): string
{
    $value = trim($value);
    if ($value === '') {
        return $fallback;
    }

    if (!preg_match('/^https?:\/\//i', $value)) {
        $value = 'https://' . ltrim($value, '/');
    }

    return filter_var($value, FILTER_VALIDATE_URL) ? $value : $fallback;
}

function normalize_store_settings(array $settings): array
{
    $default = default_store_settings();

    $name = trim((string) ($settings['name'] ?? $default['name']));
    $whatsapp = clean_whatsapp_number((string) ($settings['whatsapp'] ?? $default['whatsapp']));
    $whatsappDisplay = trim((string) ($settings['whatsappDisplay'] ?? $default['whatsappDisplay']));
    $address = trim((string) ($settings['address'] ?? $default['address']));
    $footerDescription = trim((string) ($settings['footerDescription'] ?? $default['footerDescription']));
    $contactIntro = trim((string) ($settings['contactIntro'] ?? $default['contactIntro']));
    $checkoutIntro = trim((string) ($settings['checkoutIntro'] ?? $default['checkoutIntro']));
    $monthlyIntro = trim((string) ($settings['monthlyIntro'] ?? $default['monthlyIntro']));

    return [
        'name' => $name !== '' ? $name : $default['name'],
        'whatsapp' => $whatsapp !== '' ? $whatsapp : $default['whatsapp'],
        'whatsappDisplay' => $whatsappDisplay !== '' ? $whatsappDisplay : $default['whatsappDisplay'],
        'instagram' => normalize_public_url((string) ($settings['instagram'] ?? $default['instagram']), $default['instagram']),
        'address' => $address !== '' ? $address : $default['address'],
        'mapsLink' => normalize_public_url((string) ($settings['mapsLink'] ?? $default['mapsLink']), $default['mapsLink']),
        'mapsEmbed' => normalize_public_url((string) ($settings['mapsEmbed'] ?? $default['mapsEmbed']), $default['mapsEmbed']),
        'footerDescription' => $footerDescription !== '' ? $footerDescription : $default['footerDescription'],
        'contactIntro' => $contactIntro !== '' ? $contactIntro : $default['contactIntro'],
        'checkoutIntro' => $checkoutIntro !== '' ? $checkoutIntro : $default['checkoutIntro'],
        'monthlyIntro' => $monthlyIntro !== '' ? $monthlyIntro : $default['monthlyIntro'],
    ];
}

function get_store_settings_config(): array
{
    ensure_store_settings_table();

    $stmt = db()->prepare('SELECT valor FROM loja_config WHERE chave = ? LIMIT 1');
    $stmt->execute(['dados_loja']);
    $raw = $stmt->fetchColumn();

    if (!is_string($raw) || trim($raw) === '') {
        return default_store_settings();
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return default_store_settings();
    }

    return normalize_store_settings($decoded);
}

function save_store_settings_config(array $settings): void
{
    $normalized = normalize_store_settings($settings);
    $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        throw new RuntimeException('Não foi possível salvar os dados da loja.');
    }

    db()->prepare(
        'INSERT INTO loja_config (chave, valor) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE valor = VALUES(valor), updated_at = CURRENT_TIMESTAMP'
    )->execute(['dados_loja', $json]);
}

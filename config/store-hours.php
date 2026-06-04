<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

function default_store_hours(): array
{
    return [
        'timezone' => 'America/Sao_Paulo',
        'days' => [
            ['key' => 'sun', 'label' => 'Domingo', 'short' => 'Dom', 'open' => false, 'from' => '08:00', 'to' => '14:00'],
            ['key' => 'mon', 'label' => 'Segunda-feira', 'short' => 'Seg', 'open' => true, 'from' => '08:00', 'to' => '19:00'],
            ['key' => 'tue', 'label' => 'Terça-feira', 'short' => 'Ter', 'open' => true, 'from' => '08:00', 'to' => '19:00'],
            ['key' => 'wed', 'label' => 'Quarta-feira', 'short' => 'Qua', 'open' => true, 'from' => '08:00', 'to' => '19:00'],
            ['key' => 'thu', 'label' => 'Quinta-feira', 'short' => 'Qui', 'open' => true, 'from' => '08:00', 'to' => '19:00'],
            ['key' => 'fri', 'label' => 'Sexta-feira', 'short' => 'Sex', 'open' => true, 'from' => '08:00', 'to' => '19:00'],
            ['key' => 'sat', 'label' => 'Sábado', 'short' => 'Sáb', 'open' => true, 'from' => '08:00', 'to' => '14:00'],
        ],
    ];
}

function ensure_store_config_table(): void
{
    db()->exec(
        "CREATE TABLE IF NOT EXISTS loja_config (
            chave VARCHAR(80) NOT NULL PRIMARY KEY,
            valor TEXT NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $stmt = db()->prepare('SELECT COUNT(*) FROM loja_config WHERE chave = ?');
    $stmt->execute(['horarios_funcionamento']);

    if ((int) $stmt->fetchColumn() === 0) {
        save_store_hours_config(default_store_hours());
    }
}

function valid_store_time(string $value): bool
{
    return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
}

function store_time_to_minutes(string $value): int
{
    [$hours, $minutes] = array_map('intval', explode(':', $value));
    return ($hours * 60) + $minutes;
}

function format_store_time(string $value): string
{
    if (!valid_store_time($value)) {
        return $value;
    }

    [$hours, $minutes] = explode(':', $value);
    return $minutes === '00' ? ((int) $hours) . 'h' : ((int) $hours) . 'h' . $minutes;
}

function compact_day_range(array $days): string
{
    $names = array_map(static fn (array $day): string => $day['short'], $days);
    $count = count($names);

    if ($count === 1) {
        return $names[0];
    }

    if ($count === 2) {
        return $names[0] . ' e ' . $names[1];
    }

    return $names[0] . ' a ' . $names[$count - 1];
}

function store_hours_summary(array $config): string
{
    $days = normalize_store_hours_config($config)['days'];
    $groups = [];
    $current = null;

    foreach ($days as $day) {
        if (!$day['open']) {
            if ($current) {
                $groups[] = $current;
                $current = null;
            }
            continue;
        }

        $signature = $day['from'] . '-' . $day['to'];
        if (!$current || $current['signature'] !== $signature) {
            if ($current) {
                $groups[] = $current;
            }
            $current = ['signature' => $signature, 'from' => $day['from'], 'to' => $day['to'], 'days' => [$day]];
        } else {
            $current['days'][] = $day;
        }
    }

    if ($current) {
        $groups[] = $current;
    }

    if (!$groups) {
        return 'Atendimento temporariamente fechado';
    }

    $parts = [];
    foreach ($groups as $group) {
        $parts[] = compact_day_range($group['days']) . ': ' . format_store_time($group['from']) . ' às ' . format_store_time($group['to']);
    }

    return implode(' · ', $parts);
}

function normalize_store_hours_config(array $config): array
{
    $default = default_store_hours();
    $timezone = isset($config['timezone']) && is_string($config['timezone']) && trim($config['timezone']) !== ''
        ? trim($config['timezone'])
        : $default['timezone'];

    $receivedDays = [];
    if (isset($config['days']) && is_array($config['days'])) {
        foreach ($config['days'] as $item) {
            if (is_array($item) && isset($item['key'])) {
                $receivedDays[(string) $item['key']] = $item;
            }
        }
    }

    $days = [];
    foreach ($default['days'] as $defaultDay) {
        $item = $receivedDays[$defaultDay['key']] ?? [];
        $from = isset($item['from']) && is_string($item['from']) && valid_store_time($item['from']) ? $item['from'] : $defaultDay['from'];
        $to = isset($item['to']) && is_string($item['to']) && valid_store_time($item['to']) ? $item['to'] : $defaultDay['to'];
        $open = isset($item['open']) ? (bool) $item['open'] : (bool) $defaultDay['open'];

        $days[] = [
            'key' => $defaultDay['key'],
            'label' => $defaultDay['label'],
            'short' => $defaultDay['short'],
            'open' => $open,
            'from' => $from,
            'to' => $to,
        ];
    }

    return ['timezone' => $timezone, 'days' => $days];
}

function get_store_hours_config(): array
{
    ensure_store_config_table();

    $stmt = db()->prepare('SELECT valor FROM loja_config WHERE chave = ? LIMIT 1');
    $stmt->execute(['horarios_funcionamento']);
    $raw = $stmt->fetchColumn();

    if (!is_string($raw) || trim($raw) === '') {
        return default_store_hours();
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return default_store_hours();
    }

    return normalize_store_hours_config($decoded);
}

function save_store_hours_config(array $config): void
{
    $normalized = normalize_store_hours_config($config);
    $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        throw new RuntimeException('Não foi possível salvar os horários.');
    }

    db()->prepare(
        'INSERT INTO loja_config (chave, valor) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE valor = VALUES(valor), updated_at = CURRENT_TIMESTAMP'
    )->execute(['horarios_funcionamento', $json]);
}

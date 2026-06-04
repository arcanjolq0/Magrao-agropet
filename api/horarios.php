<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/store-hours.php';

try {
    $hours = get_store_hours_config();

    echo json_encode([
        'success' => true,
        'hours' => [
            'timezone' => $hours['timezone'],
            'days' => $hours['days'],
            'summary' => store_hours_summary($hours),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(500);
    $fallback = default_store_hours();
    echo json_encode([
        'success' => false,
        'message' => 'Não foi possível carregar os horários agora.',
        'hours' => [
            'timezone' => $fallback['timezone'],
            'days' => $fallback['days'],
            'summary' => store_hours_summary($fallback),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

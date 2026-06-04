<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/store-settings.php';
require_once __DIR__ . '/../config/appointments.php';

function api_input(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $json = json_decode($raw, true);
    if (is_array($json)) {
        return $json;
    }
    return $_POST;
}

function api_clean_text(string $value, int $limit): string
{
    $value = strip_tags($value);
    $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $limit, 'UTF-8');
    }

    return substr($value, 0, $limit);
}

function api_valid_ip(string $value): string
{
    $value = trim(explode(',', $value)[0]);
    return filter_var($value, FILTER_VALIDATE_IP) ? $value : '';
}

function api_client_ip(): string
{
    $cloudflareIp = api_valid_ip((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
    if ($cloudflareIp !== '') {
        return $cloudflareIp;
    }

    $remoteIp = api_valid_ip((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($remoteIp !== '') {
        return $remoteIp;
    }

    return 'unknown';
}

function api_check_rate_limit(): void
{
    $key = hash('sha256', api_client_ip() . '|agendamentos');
    $file = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'magrao_' . $key . '.json';
    $now = time();
    $window = 3600;
    $events = [];

    if (is_file($file)) {
        $decoded = json_decode((string) @file_get_contents($file), true);
        if (is_array($decoded)) {
            $events = array_values(array_filter($decoded, static fn ($timestamp) => is_numeric($timestamp) && (int) $timestamp > ($now - $window)));
        }
    }

    if (count($events) >= 5) {
        throw new InvalidArgumentException('Muitas tentativas em pouco tempo. Tente novamente mais tarde ou chame a loja pelo WhatsApp.');
    }

    $events[] = $now;
    @file_put_contents($file, json_encode($events), LOCK_EX);
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $input = api_input();

    if (trim((string) ($input['empresa'] ?? $input['website'] ?? '')) !== '') {
        throw new InvalidArgumentException('Não foi possível enviar a solicitação.');
    }

    $service = normalize_appointment_service((string) ($input['servico'] ?? 'banho'));
    $name = api_clean_text((string) ($input['nome_cliente'] ?? $input['nome'] ?? ''), 140);
    $whatsapp = api_clean_text((string) ($input['whatsapp'] ?? ''), 40);
    $petName = api_clean_text((string) ($input['pet_nome'] ?? ''), 120);
    $petType = api_clean_text((string) ($input['tipo_pet'] ?? ''), 80);
    $petSize = api_clean_text((string) ($input['porte_pet'] ?? ''), 80);
    $preferredDate = trim((string) ($input['data_preferida'] ?? ''));
    $preferredPeriod = api_clean_text((string) ($input['periodo_preferido'] ?? ''), 80);
    $notes = api_clean_text((string) ($input['observacoes'] ?? ''), 1200);

    if ($name === '') {
        throw new InvalidArgumentException('Informe seu nome.');
    }
    if (strlen(whatsapp_digits($whatsapp)) < 10) {
        throw new InvalidArgumentException('Informe um WhatsApp válido.');
    }
    if ($preferredDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $preferredDate)) {
        throw new InvalidArgumentException('Informe uma data válida.');
    }
    if ($preferredDate !== '') {
        [$year, $month, $day] = array_map('intval', explode('-', $preferredDate));
        if (!checkdate($month, $day, $year)) {
            throw new InvalidArgumentException('Informe uma data válida.');
        }
    }

    api_check_rate_limit();

    $payload = [
        'servico' => $service,
        'nome_cliente' => $name,
        'whatsapp' => $whatsapp,
        'pet_nome' => $petName !== '' ? $petName : null,
        'tipo_pet' => $petType !== '' ? $petType : null,
        'porte_pet' => $petSize !== '' ? $petSize : null,
        'data_preferida' => $preferredDate !== '' ? $preferredDate : null,
        'periodo_preferido' => $preferredPeriod !== '' ? $preferredPeriod : null,
        'observacoes' => $notes !== '' ? $notes : null,
    ];

    ensure_appointments_table();

    $stmt = db()->prepare('INSERT INTO agendamentos (servico, nome_cliente, whatsapp, pet_nome, tipo_pet, porte_pet, data_preferida, periodo_preferido, observacoes, status, origem) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $payload['servico'],
        $payload['nome_cliente'],
        $payload['whatsapp'],
        $payload['pet_nome'],
        $payload['tipo_pet'],
        $payload['porte_pet'],
        $payload['data_preferida'],
        $payload['periodo_preferido'],
        $payload['observacoes'],
        'novo',
        'site-servicos',
    ]);

    $id = (int) db()->lastInsertId();
    $settings = get_store_settings_config();
    $phone = whatsapp_digits((string) ($settings['whatsapp'] ?? '5547996329281'));
    $message = build_appointment_whatsapp_message($payload);
    $whatsappUrl = 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);

    echo json_encode([
        'success' => true,
        'id' => $id,
        'message' => 'Agendamento registrado. Agora confirme pelo WhatsApp.',
        'whatsappUrl' => $whatsappUrl,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Não foi possível registrar o agendamento agora. Tente novamente ou chame a loja pelo WhatsApp.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/store-settings.php';

function ensure_appointments_table(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS agendamentos (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      servico VARCHAR(60) NOT NULL,
      nome_cliente VARCHAR(140) NOT NULL,
      whatsapp VARCHAR(40) NOT NULL,
      pet_nome VARCHAR(120) NULL,
      tipo_pet VARCHAR(80) NULL,
      porte_pet VARCHAR(80) NULL,
      data_preferida DATE NULL,
      periodo_preferido VARCHAR(80) NULL,
      observacoes TEXT NULL,
      status VARCHAR(30) NOT NULL DEFAULT 'novo',
      origem VARCHAR(80) NOT NULL DEFAULT 'site',
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX idx_agendamentos_status (status, created_at),
      INDEX idx_agendamentos_data (data_preferida)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function appointment_services(): array
{
    return [
        'banho' => 'Banho',
        'tosa' => 'Tosa',
        'veterinario' => 'Serviços veterinários',
    ];
}

function appointment_statuses(): array
{
    return [
        'novo' => 'Novo',
        'confirmado' => 'Confirmado',
        'concluido' => 'Concluído',
        'cancelado' => 'Cancelado',
    ];
}

function appointment_service_label(string $service): string
{
    $services = appointment_services();
    return $services[$service] ?? 'Serviço';
}

function appointment_status_label(string $status): string
{
    $statuses = appointment_statuses();
    return $statuses[$status] ?? 'Novo';
}

function normalize_appointment_service(string $service): string
{
    $service = strtolower(trim($service));
    $aliases = [
        'servicos-veterinarios' => 'veterinario',
        'serviços-veterinários' => 'veterinario',
        'veterinarios' => 'veterinario',
        'veterinários' => 'veterinario',
        'veterinaria' => 'veterinario',
        'veterinária' => 'veterinario',
    ];
    $service = $aliases[$service] ?? $service;
    return array_key_exists($service, appointment_services()) ? $service : 'banho';
}

function normalize_appointment_status(string $status): string
{
    $status = strtolower(trim($status));
    return array_key_exists($status, appointment_statuses()) ? $status : 'novo';
}

function appointment_count(?string $status = null): int
{
    ensure_appointments_table();
    if ($status === null || $status === '') {
        return (int) db()->query('SELECT COUNT(*) FROM agendamentos')->fetchColumn();
    }

    $status = normalize_appointment_status($status);
    $stmt = db()->prepare('SELECT COUNT(*) FROM agendamentos WHERE status = ?');
    $stmt->execute([$status]);
    return (int) $stmt->fetchColumn();
}

function whatsapp_digits(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function build_appointment_whatsapp_message(array $data): string
{
    $settings = get_store_settings_config();
    $intro = trim((string) ($settings['contactIntro'] ?? 'Olá, estou entrando em contato pelo site da Magrão Agro Pet.'));

    $lines = [
        $intro,
        '',
        'Quero agendar um atendimento:',
        'Serviço: ' . appointment_service_label((string) ($data['servico'] ?? 'banho')),
        'Nome: ' . trim((string) ($data['nome_cliente'] ?? '')),
        'WhatsApp do cliente: ' . trim((string) ($data['whatsapp'] ?? '')),
    ];

    $optional = [
        'Pet' => $data['pet_nome'] ?? '',
        'Tipo de pet' => $data['tipo_pet'] ?? '',
        'Porte/peso' => $data['porte_pet'] ?? '',
        'Data preferida' => $data['data_preferida'] ?? '',
        'Período preferido' => $data['periodo_preferido'] ?? '',
        'Observações' => $data['observacoes'] ?? '',
    ];

    foreach ($optional as $label => $value) {
        $value = trim((string) $value);
        if ($value !== '') {
            $lines[] = $label . ': ' . $value;
        }
    }

    $lines[] = '';
    $lines[] = 'Pode confirmar disponibilidade para esse horário?';

    return implode("\n", $lines);
}

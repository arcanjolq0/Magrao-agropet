<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

/*
 * Configuração do banco de dados do site Magrão Agro Pet.
 * Preencha os dados abaixo com as credenciais reais do banco MySQL da hospedagem.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'magrao_agropet');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');

    return $pdo;
}

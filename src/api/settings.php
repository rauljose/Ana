<?php declare(strict_types=1);

require_once("../config/config.php");

header('Content-Type: application/json; charset=utf-8');

// Saved settings per page (thresholds, pickers), table user_settings.
// GET ?page=...                  -> { name: value }
// POST {page, values: {name: value}} -> saves them, answers with the page's settings

function fail(int $code, string $message): never
{
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

function settingsOf(mysqli $mysqli, string $page): array
{
    $stmt = $mysqli->prepare("SELECT name, value FROM user_settings WHERE page = ?");
    $stmt->execute([$page]);
    return array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'value', 'name');
}

$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? json_decode(file_get_contents('php://input'), true) : $_GET;
if (!is_array($input)) fail(400, 'Invalid request');

$page = $input['page'] ?? '';
if (!is_string($page) || !preg_match('/^[a-z_]{1,64}$/', $page)) fail(400, 'Invalid page');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $mysqli->prepare("
            INSERT INTO user_settings (page, name, value) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE value = VALUES(value)
        ");
        foreach ((array) ($input['values'] ?? []) as $name => $value) {
            if (!is_string($name) || !preg_match('/^[a-z_]{1,64}$/', $name)) fail(400, 'Invalid setting name');
            if (!is_scalar($value) || mb_strlen((string) $value) > 255) fail(400, "Invalid value for $name");
            $stmt->execute([$page, $name, (string) $value]);
        }
    }

    echo json_encode(settingsOf($mysqli, $page) ?: new stdClass());
} catch (mysqli_sql_exception $e) {
    fail(500, 'Could not use table user_settings, run db/db_schema.sql: ' . $e->getMessage());
}

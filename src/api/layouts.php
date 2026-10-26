<?php declare(strict_types=1);

require_once("../config/config.php");

header('Content-Type: application/json; charset=utf-8');

// Saved table layouts (visible columns and sort order) per page.
// GET ?page=...                 -> the page's layouts
// POST {action, page, ...}      -> save | delete | clear_default | import, answers with the page's layouts

function fail(int $code, string $message): never
{
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

// Keeps only well formed columns and sort rules
function cleanLayout(mixed $layout): array
{
    $hidden = [];
    foreach ((array) ($layout['hidden'] ?? []) as $field) {
        if (is_string($field) && $field !== '') $hidden[] = $field;
    }
    $sort = [];
    foreach ((array) ($layout['sort'] ?? []) as $rule) {
        if (is_array($rule) && is_string($rule['field'] ?? null) && $rule['field'] !== '' && in_array($rule['dir'] ?? '', ['asc', 'desc'], true)) {
            $sort[] = ['field' => $rule['field'], 'dir' => $rule['dir']];
        }
    }
    return [array_values(array_unique($hidden)), array_slice($sort, 0, 10)];
}

function layoutStore(mysqli $mysqli, string $page): array
{
    $stmt = $mysqli->prepare("SELECT name, hidden_columns, sort_rules, is_default FROM user_layouts WHERE page = ? ORDER BY name");
    $stmt->execute([$page]);

    $store = ['default' => null, 'layouts' => []];
    foreach ($stmt->get_result() as $row) {
        $store['layouts'][$row['name']] = [
            'hidden' => json_decode($row['hidden_columns'], true),
            'sort' => json_decode($row['sort_rules'], true),
        ];
        if ((int) $row['is_default'] === 1) $store['default'] = $row['name'];
    }
    return $store;
}

function saveLayout(mysqli $mysqli, string $page, string $name, mixed $layout): void
{
    [$hidden, $sort] = cleanLayout($layout);
    $stmt = $mysqli->prepare("
        INSERT INTO user_layouts (page, name, hidden_columns, sort_rules) VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE hidden_columns = VALUES(hidden_columns), sort_rules = VALUES(sort_rules)
    ");
    $stmt->execute([$page, $name, json_encode($hidden), json_encode($sort)]);
}

function setDefault(mysqli $mysqli, string $page, ?string $name): void
{
    $stmt = $mysqli->prepare("UPDATE user_layouts SET is_default = (name = ?) WHERE page = ?");
    $stmt->execute([$name ?? '', $page]);
}

$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? json_decode(file_get_contents('php://input'), true) : $_GET;
if (!is_array($input)) fail(400, 'Invalid request');

$page = $input['page'] ?? '';
if (!is_string($page) || !preg_match('/^[a-z_]{1,64}$/', $page)) fail(400, 'Invalid page');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($input['name'] ?? ''));
    $action = $input['action'] ?? '';

    $mysqli->begin_transaction();
    switch ($action) {
        case 'save':
            if ($name === '' || mb_strlen($name) > 100) fail(400, 'The layout needs a name of up to 100 characters');
            saveLayout($mysqli, $page, $name, $input['layout'] ?? []);
            if (!empty($input['is_default'])) {
                setDefault($mysqli, $page, $name);
            } else {
                // Saving without the default box unmarks this layout if it was the default
                $stmt = $mysqli->prepare("UPDATE user_layouts SET is_default = 0 WHERE page = ? AND name = ?");
                $stmt->execute([$page, $name]);
            }
            break;

        case 'delete':
            $stmt = $mysqli->prepare("DELETE FROM user_layouts WHERE page = ? AND name = ?");
            $stmt->execute([$page, $name]);
            break;

        case 'clear_default':
            setDefault($mysqli, $page, null);
            break;

        case 'import':
            // Layouts found in the browser's local storage by an earlier version: keep the ones not already saved
            $existing = layoutStore($mysqli, $page)['layouts'];
            foreach ((array) ($input['layouts'] ?? []) as $importName => $layout) {
                $importName = trim((string) $importName);
                if ($importName === '' || mb_strlen($importName) > 100 || isset($existing[$importName])) continue;
                saveLayout($mysqli, $page, $importName, $layout);
            }
            $store = layoutStore($mysqli, $page);
            if (is_string($input['default'] ?? null) && $store['default'] === null && isset($store['layouts'][$input['default']])) {
                setDefault($mysqli, $page, $input['default']);
            }
            break;

        default:
            $mysqli->rollback();
            fail(400, "Unknown action: $action");
    }
    $mysqli->commit();
}

echo json_encode(layoutStore($mysqli, $page));

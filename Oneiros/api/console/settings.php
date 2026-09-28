<?php
/**
 * /api/console/settings
 *   GET                         every setting this role may see, with its source (settings.view)
 *   PATCH {"changes": {k: v}}   save values; null resets a value to the file/default (settings.edit)
 * What each role may change is decided by the setting's tier (Settings::editableTiers).
 */
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/staff.php';

Helpers::only(['GET', 'PATCH', 'POST']);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $staff = Staff::require('settings.view');
    Helpers::respond(['settings' => Settings::describe($staff['role'] === 'admin'), 'storage_error' => Settings::loadError()]);
}

$staff = Staff::require('settings.edit');
$changes = Helpers::input()['changes'] ?? null;
if (!is_array($changes) || !$changes) Helpers::respond(['error' => 'Nothing to save'], 400);
try {
    $audit = Settings::save($changes, $staff);
} catch (InvalidArgumentException $e) {
    Helpers::respond(['error' => $e->getMessage()], 422);
} catch (DomainException $e) {
    Helpers::respond(['error' => $e->getMessage()], 403);
}
foreach ($audit as $entry) {
    $show = fn($v) => is_scalar($v) ? (is_bool($v) ? ($v ? 'on' : 'off') : (string)$v) : json_encode($v, JSON_UNESCAPED_UNICODE);
    Staff::audit($staff, 'settings.update', "Changed “{$entry['label']}”: " . mb_substr($show($entry['from']), 0, 120) . ' → ' . mb_substr($show($entry['to']), 0, 120),
        'setting', $entry['key'], $entry);
}
Helpers::respond(['saved' => array_column($audit, 'key'), 'settings' => Settings::describe($staff['role'] === 'admin')]);

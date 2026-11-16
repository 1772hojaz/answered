<?php
/**
 * Include from every admin page that must be behind the login.
 */

declare(strict_types=1);

require __DIR__ . '/boot.php';

require_admin();

// Editors have an explicit route allowlist as well as record-level checks.
if (!full_admin()) {
    $staffPath = ltrim(substr((string) ($_SERVER['SCRIPT_NAME'] ?? ''), strlen(admin_base_url())), '/');
    if (!in_array($staffPath, ['index.php','notes/index.php','notes/edit.php','notes/preview.php','notes/history.php','actions/autosave.php','actions/save-image.php','profile.php'], true)) {
        staff_forbidden('Editors can manage their own draft notes. Ask an administrator to manage other resources.');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        && (isset($_POST['publish']) || in_array($_POST['action'] ?? '', ['delete','toggle'], true) || ($_POST['form_action'] ?? '') === 'delete')) {
        staff_forbidden('Only administrators can publish or remove content.');
    }
}

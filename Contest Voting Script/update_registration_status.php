<?php
/**
 * Admin Action: Toggle Registration Status
 */

require_once __DIR__ . '/config.php';

Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrf()) {
        $_SESSION['flash_error'] = "Security session expired. Please try again.";
    } else {
        $status = filter_input(INPUT_POST, 'status', FILTER_VALIDATE_INT);
        Settings::set('registration_open', $status === 1 ? '1' : '0');
        file_put_contents(__DIR__ . '/version.txt', time());
        $_SESSION['flash_success'] = "Registration status updated successfully.";
    }
}

header('Location: admin_dashboard.php');
exit();

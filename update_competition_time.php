<?php
/**
 * Admin Action: Update Competition End Time
 */

require_once __DIR__ . '/config.php';

Auth::requireAdmin();
Security::requireCsrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $endTime = filter_input(INPUT_POST, 'competition_end_time', FILTER_DEFAULT);
    
    if (!empty($endTime)) {
        Settings::setCompetitionEndTime($endTime);
        $_SESSION['flash_success'] = "Competition end time updated successfully.";
    } else {
        $_SESSION['flash_error'] = "Invalid date/time provided.";
    }
}

header('Location: admin_dashboard.php');
exit();

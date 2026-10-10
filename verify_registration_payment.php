<?php
/**
 * Contestant Registration Payment Verification Endpoint (AJAX Callback)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit();
}

$reference = filter_input(INPUT_POST, 'reference', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
$userId    = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT) ?? 0;
$email     = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? null;
$name      = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? null;
$phone     = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? null;

// If user is currently logged in, cross-verify session ID
if (Auth::isUserLoggedIn()) {
    $currUser = Auth::getCurrentUser();
    if ($currUser) {
        if ($userId <= 0) {
            $userId = (int)$currUser['id'];
        }
        if (empty($email)) {
            $email = $currUser['email'];
        }
        if (empty($name)) {
            $name = $currUser['full_name'];
        }
        if (empty($phone)) {
            $phone = $currUser['phone_number'];
        }
    }
}

if (empty($reference) || $userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing transaction reference or contestant ID.']);
    exit();
}

$result = RegistrationService::verifyAndCompleteRegistration(
    $reference,
    $userId,
    $email,
    $name,
    $phone
);

if ($result['success']) {
    http_response_code(200);
    echo json_encode($result);
} else {
    http_response_code(422);
    echo json_encode($result);
}
exit();


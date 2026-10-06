<?php
/**
 * Transaction Verification Endpoint (AJAX Callback)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/includes/voting_service.php';
require_once __DIR__ . '/includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit();
}

$reference    = filter_input(INPUT_POST, 'reference', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
$userId       = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT) ?? 0;
$payerEmail   = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? null;
$payerName    = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? null;
$payerPhone   = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? null;

if (empty($reference) || $userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing transaction reference or contestant ID.']);
    exit();
}

$result = VotingService::verifyAndCreditPayment(
    $reference,
    $userId,
    $payerEmail,
    $payerName,
    $payerPhone
);

if ($result['success']) {
    http_response_code(200);
    echo json_encode($result);
} else {
    http_response_code(422);
    echo json_encode($result);
}
exit();

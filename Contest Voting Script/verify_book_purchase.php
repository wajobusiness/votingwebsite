<?php
/**
 * Digital Book & Masterclass Purchase Verification Endpoint (AJAX Callback)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit();
}

$reference = filter_input(INPUT_POST, 'reference', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
$bookId    = filter_input(INPUT_POST, 'book_id', FILTER_VALIDATE_INT) ?? 0;
$email     = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '';
$name      = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
$phone     = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? null;
$userId    = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT) ?? null;

// If user is logged in via session, link the purchase automatically
if (!$userId && Auth::isUserLoggedIn()) {
    $currUser = Auth::getCurrentUser();
    if ($currUser) {
        $userId = (int)$currUser['id'];
        if (empty($email)) {
            $email = $currUser['email'];
        }
        if (empty($name)) {
            $name = $currUser['full_name'];
        }
    }
}

if (empty($reference) || $bookId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing transaction reference or book identifier.']);
    exit();
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid email address is required to deliver your digital access link.']);
    exit();
}

$result = BookstoreService::verifyAndFulfillPurchase(
    $reference,
    $bookId,
    $email,
    $name,
    $phone,
    $userId
);

if ($result['success']) {
    http_response_code(200);
    echo json_encode($result);
} else {
    http_response_code(422);
    echo json_encode($result);
}
exit();

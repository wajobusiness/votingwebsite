<?php
/**
 * Paystack Webhook Handler
 * Asynchronously catches and credits successful payments for both voting and digital bookstore purchases
 */

require_once __DIR__ . '/includes/Env.php';
require_once __DIR__ . '/includes/voting_service.php';
require_once __DIR__ . '/includes/bookstore_service.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit();
}

$input = file_get_contents("php://input");
$secret = Env::get('PAYSTACK_WEBHOOK_SECRET', Env::get('PAYSTACK_SECRET_KEY'));

// Validate Paystack HMAC SHA512 Signature
$paystackSignature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';

if (empty($paystackSignature) || hash_equals(hash_hmac('sha512', $input, $secret), $paystackSignature) === false) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid webhook signature']);
    exit();
}

http_response_code(200); // Acknowledge receipt to Paystack immediately

$event = json_decode($input, true);

if (isset($event['event']) && $event['event'] === 'charge.success') {
    $data = $event['data'] ?? [];
    $reference = $data['reference'] ?? null;
    $metadata = $data['metadata'] ?? [];

    $payerEmail = $data['customer']['email'] ?? null;
    $payerPhone = $data['customer']['phone'] ?? null;
    $payerName = trim(($data['customer']['first_name'] ?? '') . ' ' . ($data['customer']['last_name'] ?? ''));

    // 1. Check if Book Purchase
    $bookId = $metadata['book_id'] ?? null;
    $customFields = $metadata['custom_fields'] ?? [];
    if (!$bookId && is_array($customFields)) {
        foreach ($customFields as $field) {
            if (($field['variable_name'] ?? '') === 'book_id') {
                $bookId = (int)$field['value'];
                break;
            }
        }
    }

    if ($bookId && $reference) {
        BookstoreService::verifyAndFulfillPurchase(
            (string)$reference,
            (int)$bookId,
            (string)($payerEmail ?? 'customer@crownnightstar.com'),
            (string)$payerName,
            (string)$payerPhone,
            isset($metadata['user_id']) ? (int)$metadata['user_id'] : null
        );
    } else {
        // 2. Otherwise process Voting Payment
        $contestantId = $metadata['contestant_id'] ?? $metadata['user_id'] ?? null;
        if ($reference && $contestantId) {
            VotingService::verifyAndCreditPayment(
                (string)$reference,
                (int)$contestantId,
                $payerEmail,
                $payerName,
                $payerPhone
            );
        }
    }
}

echo json_encode(['status' => 'success']);
exit();

<?php
/**
 * Paystack Webhook Handler
 * Asynchronously catches and credits successful payments even if user closes browser
 */

require_once __DIR__ . '/includes/Env.php';
require_once __DIR__ . '/includes/voting_service.php';

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

    // Retrieve contestant ID from metadata or custom fields
    $contestantId = $metadata['contestant_id'] ?? $metadata['user_id'] ?? null;
    $payerEmail = $data['customer']['email'] ?? null;
    $payerPhone = $data['customer']['phone'] ?? null;
    $payerName = trim(($data['customer']['first_name'] ?? '') . ' ' . ($data['customer']['last_name'] ?? ''));

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

echo json_encode(['status' => 'success']);
exit();

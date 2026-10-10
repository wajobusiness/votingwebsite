<?php
/**
 * Paystack Webhook Handler
 * Asynchronously catches and credits successful payments for voting, digital bookstore purchases, and contestant registrations
 */

require_once __DIR__ . '/includes/Env.php';
require_once __DIR__ . '/includes/voting_service.php';
require_once __DIR__ . '/includes/bookstore_service.php';
require_once __DIR__ . '/includes/registration_service.php';

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

    // Check payment_type from metadata or custom fields
    $paymentType = $metadata['payment_type'] ?? null;
    $customFields = $metadata['custom_fields'] ?? [];
    if (!$paymentType && is_array($customFields)) {
        foreach ($customFields as $field) {
            if (($field['variable_name'] ?? '') === 'payment_type') {
                $paymentType = (string)$field['value'];
                break;
            }
        }
    }

    // 1. Check if Registration Payment
    if ($paymentType === 'registration' || str_starts_with((string)$reference, 'REG_')) {
        $regUserId = $metadata['user_id'] ?? null;
        if ($reference && $regUserId) {
            RegistrationService::verifyAndCompleteRegistration(
                (string)$reference,
                (int)$regUserId,
                $payerEmail,
                $payerName,
                $payerPhone
            );
        }
    } else {
        // 2. Check if Book Purchase
        $bookId = $metadata['book_id'] ?? null;
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
            // 3. Otherwise process Voting Payment
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
}

echo json_encode(['status' => 'success']);
exit();

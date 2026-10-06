<?php
/**
 * Core Voting & Payment Reconciliation Service
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/Env.php';

class VotingService {

    /**
     * Verify Paystack transaction reference and atomically credit votes
     */
    public static function verifyAndCreditPayment(
        string $reference,
        int $contestantId,
        ?string $payerEmail = null,
        ?string $payerName = null,
        ?string $payerPhone = null
    ): array {
        $reference = trim($reference);

        if (empty($reference)) {
            return ['success' => false, 'error' => 'Transaction reference is required.'];
        }

        if ($contestantId <= 0) {
            return ['success' => false, 'error' => 'A valid contestant ID must be specified.'];
        }

        $pdo = DB::pdo();

        // 1. Verify contestant exists
        $stmt = $pdo->prepare("SELECT id, username, full_name, vote_count FROM users WHERE id = ? AND is_admin = 0 LIMIT 1");
        $stmt->execute([$contestantId]);
        $contestant = $stmt->fetch();

        if (!$contestant) {
            return ['success' => false, 'error' => 'Specified contestant was not found.'];
        }

        // 2. Check if voting is currently permitted
        if (!Settings::isVotingOpen()) {
            return ['success' => false, 'error' => 'Voting has ended for this competition.'];
        }

        // 3. Idempotency Check: Verify if transaction was already processed
        $checkStmt = $pdo->prepare("SELECT id, amount, status FROM payments WHERE transaction_id = ? LIMIT 1");
        $checkStmt->execute([$reference]);
        $existingPayment = $checkStmt->fetch();

        if ($existingPayment) {
            if ($existingPayment['status'] === 'success') {
                return [
                    'success'       => true,
                    'already_saved' => true,
                    'message'       => 'This transaction has already been verified and credited.',
                    'contestant'    => $contestant
                ];
            }
        }

        // 4. Contact Paystack REST API to verify transaction
        $secretKey = Env::get('PAYSTACK_SECRET_KEY');
        if (empty($secretKey)) {
            return ['success' => false, 'error' => 'Payment gateway is not configured.'];
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$secretKey}",
                "Cache-Control: no-cache"
            ]
        ]);

        $rawResponse = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($rawResponse === false || !empty($curlError)) {
            error_log("Paystack cURL Error: {$curlError}");
            return ['success' => false, 'error' => 'Unable to connect to payment gateway for verification.'];
        }

        $responseData = json_decode($rawResponse, true);

        if ($httpCode !== 200 || !isset($responseData['status']) || $responseData['status'] !== true) {
            $msg = $responseData['message'] ?? 'Transaction verification failed on gateway.';
            return ['success' => false, 'error' => $msg];
        }

        $data = $responseData['data'] ?? [];
        $gatewayStatus = $data['status'] ?? '';

        if ($gatewayStatus !== 'success') {
            return ['success' => false, 'error' => "Gateway status: {$gatewayStatus} (Payment incomplete)."];
        }

        // 5. Server-Side Vote Count Calculation
        $paidAmountKobo = (int)($data['amount'] ?? 0);
        $paidAmount = $paidAmountKobo / 100.0;
        $currency = $data['currency'] ?? 'NGN';
        $channel = $data['channel'] ?? 'card';
        $customer = $data['customer'] ?? [];

        $voterEmail = !empty($payerEmail) ? $payerEmail : ($customer['email'] ?? null);
        $voterPhone = !empty($payerPhone) ? $payerPhone : ($customer['phone'] ?? null);
        $voterName  = !empty($payerName)  ? $payerName  : trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));

        $votePrice = Settings::getVotePrice();
        if ($votePrice <= 0) {
            $votePrice = 50.0;
        }

        $votesAwarded = (int)floor($paidAmount / $votePrice);

        if ($votesAwarded < 1) {
            return ['success' => false, 'error' => 'Paid amount is lower than the minimum price for 1 vote.'];
        }

        $clientIp = Security::getClientIp();
        $currentStage = Settings::getCurrentStage();

        // 6. Atomically Record Payment, Ledger Vote, and Update Contestant Count
        try {
            $pdo->beginTransaction();

            // Insert into payments
            $payStmt = $pdo->prepare("
                INSERT INTO payments (
                    user_id, transaction_id, amount, currency, status, 
                    payment_method, channel, payer_email, payer_name, 
                    payer_phone, ip_address, raw_response
                ) VALUES (
                    :user_id, :transaction_id, :amount, :currency, :status,
                    :payment_method, :channel, :payer_email, :payer_name,
                    :payer_phone, :ip_address, :raw_response
                )
            ");

            $payStmt->execute([
                ':user_id'        => $contestantId,
                ':transaction_id' => $reference,
                ':amount'         => $paidAmount,
                ':currency'       => $currency,
                ':status'         => 'success',
                ':payment_method' => 'paystack',
                ':channel'        => $channel,
                ':payer_email'    => $voterEmail,
                ':payer_name'     => $voterName,
                ':payer_phone'    => $voterPhone,
                ':ip_address'     => $clientIp,
                ':raw_response'   => $rawResponse
            ]);

            $paymentId = (int)$pdo->lastInsertId();

            // Insert into votes ledger
            $voteStmt = $pdo->prepare("
                INSERT INTO votes (
                    user_id, payment_id, vote_count, amount, 
                    voter_email, voter_name, voter_phone, 
                    ip_address, stage_name
                ) VALUES (
                    :user_id, :payment_id, :vote_count, :amount,
                    :voter_email, :voter_name, :voter_phone,
                    :ip_address, :stage_name
                )
            ");

            $voteStmt->execute([
                ':user_id'     => $contestantId,
                ':payment_id'  => $paymentId,
                ':vote_count'  => $votesAwarded,
                ':amount'      => $paidAmount,
                ':voter_email' => $voterEmail,
                ':voter_name'  => $voterName,
                ':voter_phone' => $voterPhone,
                ':ip_address'  => $clientIp,
                ':stage_name'  => $currentStage
            ]);

            // Update users table vote count
            $userStmt = $pdo->prepare("
                UPDATE users 
                SET vote_count = vote_count + :votes 
                WHERE id = :user_id
            ");
            $userStmt->execute([
                ':votes'   => $votesAwarded,
                ':user_id' => $contestantId
            ]);

            $pdo->commit();

            return [
                'success'       => true,
                'votes_awarded' => $votesAwarded,
                'amount_paid'   => $paidAmount,
                'currency'      => $currency,
                'reference'     => $reference,
                'contestant'    => $contestant,
                'message'       => "Successfully credited {$votesAwarded} votes to {$contestant['full_name']}!"
            ];

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Database Transaction Error in Voting: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred while recording votes.'];
        }
    }
}

<?php
/**
 * Registration Payment & Contestant Gate Service
 * Handles registration fee requirements, Paystack verification, status checkpoints, and access gating.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/Env.php';

class RegistrationService {

    private static bool $schemaChecked = false;

    /**
     * Ensure database columns and settings exist for registration payment gating
     */
    public static function ensureSchema(): void {
        if (self::$schemaChecked) {
            return;
        }

        $pdo = DB::pdo();

        try {
            // 1. Ensure users table columns exist
            $userCols = [];
            try {
                $colStmt = $pdo->query("SHOW COLUMNS FROM `users`");
                while ($row = $colStmt->fetch(PDO::FETCH_ASSOC)) {
                    $userCols[] = strtolower($row['Field']);
                }
            } catch (Exception $e) {
                // Table might use different casing or driver
            }

            if (!empty($userCols)) {
                if (!in_array('registration_status', $userCols, true)) {
                    $pdo->exec("ALTER TABLE `users` ADD COLUMN `registration_status` ENUM('pending', 'paid', 'exempt') NOT NULL DEFAULT 'paid' AFTER `is_active`");
                    $pdo->exec("CREATE INDEX `idx_user_reg_status` ON `users` (`registration_status`)");
                }
                if (!in_array('registration_paid_at', $userCols, true)) {
                    $pdo->exec("ALTER TABLE `users` ADD COLUMN `registration_paid_at` DATETIME DEFAULT NULL AFTER `registration_status`");
                }
                if (!in_array('registration_payment_ref', $userCols, true)) {
                    $pdo->exec("ALTER TABLE `users` ADD COLUMN `registration_payment_ref` VARCHAR(100) DEFAULT NULL AFTER `registration_paid_at`");
                }
                if (!in_array('registration_fee_paid', $userCols, true)) {
                    $pdo->exec("ALTER TABLE `users` ADD COLUMN `registration_fee_paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `registration_payment_ref`");
                }
            }

            // 2. Ensure payments table has payment_type column
            $paymentCols = [];
            try {
                $pColStmt = $pdo->query("SHOW COLUMNS FROM `payments`");
                while ($row = $pColStmt->fetch(PDO::FETCH_ASSOC)) {
                    $paymentCols[] = strtolower($row['Field']);
                }
            } catch (Exception $e) {
                // Ignored
            }

            if (!empty($paymentCols) && !in_array('payment_type', $paymentCols, true)) {
                try {
                    $pdo->exec("ALTER TABLE `payments` ADD COLUMN `payment_type` VARCHAR(50) NOT NULL DEFAULT 'vote' AFTER `payment_method`");
                    $pdo->exec("CREATE INDEX `idx_payments_type` ON `payments` (`payment_type`)");
                } catch (Exception $e) {
                    // Ignored
                }
            }

            // 3. Ensure settings entry for registration_fee exists
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM `settings` WHERE `name` = 'registration_fee'");
                $stmt->execute();
                if ((int)$stmt->fetchColumn() === 0) {
                    $pdo->prepare("INSERT INTO `settings` (`name`, `value`) VALUES ('registration_fee', '0')")->execute();
                }
            } catch (Exception $e) {
                // Ignored
            }

            // 4. Ensure any existing users without registration_status are marked as 'paid'
            try {
                $pdo->exec("UPDATE `users` SET `registration_status` = 'paid' WHERE `registration_status` IS NULL OR `registration_status` = ''");
            } catch (Exception $e) {
                // Ignored
            }

            self::$schemaChecked = true;
        } catch (Exception $e) {
            error_log("RegistrationService ensureSchema error: " . $e->getMessage());
        }
    }

    /**
     * Get the active registration fee amount (in Main Currency, e.g. 1000 for ₦1,000)
     */
    public static function getRegistrationFee(): float {
        self::ensureSchema();
        $val = Settings::get('registration_fee', '0');
        $num = (float)$val;
        return max(0.0, $num);
    }

    /**
     * Determine if a registration fee is currently required (> 0)
     */
    public static function isFeeRequired(): bool {
        return self::getRegistrationFee() > 0;
    }

    /**
     * Check if a user's registration is complete and verified
     */
    public static function isUserRegistrationComplete(array|int|null $user): bool {
        self::ensureSchema();

        if ($user === null) {
            return false;
        }

        if (is_numeric($user)) {
            $pdo = DB::pdo();
            $stmt = $pdo->prepare("SELECT id, registration_status, is_admin FROM `users` WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$user]);
            $user = $stmt->fetch();
            if (!$user) {
                return false;
            }
        }

        // Admins are always exempt
        if (!empty($user['is_admin'])) {
            return true;
        }

        // If registration fee is zero, registration is always complete
        if (!self::isFeeRequired()) {
            return true;
        }

        $status = strtolower(trim($user['registration_status'] ?? 'paid'));
        return in_array($status, ['paid', 'exempt'], true);
    }

    /**
     * Verify Paystack transaction reference and complete contestant registration
     */
    public static function verifyAndCompleteRegistration(
        string $reference,
        int $userId,
        ?string $payerEmail = null,
        ?string $payerName = null,
        ?string $payerPhone = null
    ): array {
        self::ensureSchema();
        $reference = trim($reference);

        if (empty($reference)) {
            return ['success' => false, 'error' => 'Transaction reference is required.'];
        }

        if ($userId <= 0) {
            return ['success' => false, 'error' => 'A valid contestant ID must be specified.'];
        }

        $pdo = DB::pdo();

        // 1. Fetch Contestant
        $stmt = $pdo->prepare("SELECT * FROM `users` WHERE id = ? AND is_admin = 0 LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'error' => 'Contestant not found in the system.'];
        }

        // 2. Check if already marked as Paid/Exempt
        if (self::isUserRegistrationComplete($user)) {
            return [
                'success'       => true,
                'already_paid'  => true,
                'message'       => 'Your registration has already been verified and activated.',
                'user'          => $user
            ];
        }

        // 3. Idempotency Check in Payments Table
        $checkStmt = $pdo->prepare("SELECT id, amount, status FROM `payments` WHERE transaction_id = ? LIMIT 1");
        $checkStmt->execute([$reference]);
        $existingPayment = $checkStmt->fetch();

        if ($existingPayment && $existingPayment['status'] === 'success') {
            // Already paid, update user status to be safe
            $updateStmt = $pdo->prepare("
                UPDATE `users` 
                SET `registration_status` = 'paid', 
                    `registration_paid_at` = COALESCE(`registration_paid_at`, NOW()),
                    `registration_payment_ref` = :ref,
                    `registration_fee_paid` = :amt
                WHERE id = :id
            ");
            $updateStmt->execute([
                ':ref' => $reference,
                ':amt' => $existingPayment['amount'],
                ':id'  => $userId
            ]);

            return [
                'success'       => true,
                'already_saved' => true,
                'message'       => 'Transaction previously verified. Registration is fully activated.',
                'user'          => $user
            ];
        }

        // 4. Contact Paystack REST API
        $secretKey = Env::get('PAYSTACK_SECRET_KEY');
        if (empty($secretKey)) {
            return ['success' => false, 'error' => 'Payment gateway configuration is missing.'];
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
            error_log("Registration Paystack cURL Error: {$curlError}");
            return ['success' => false, 'error' => 'Unable to connect to Paystack payment gateway.'];
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

        // 5. Amount & Metadata Verification
        $paidAmountKobo = (int)($data['amount'] ?? 0);
        $paidAmount = $paidAmountKobo / 100.0;
        $currency = $data['currency'] ?? 'NGN';
        $channel = $data['channel'] ?? 'card';
        $customer = $data['customer'] ?? [];

        $expectedFee = self::getRegistrationFee();
        // Allow minor rounding difference or exact/higher amount
        if ($expectedFee > 0 && $paidAmount < ($expectedFee - 0.5)) {
            return [
                'success' => false,
                'error'   => "Paid amount (₦" . number_format($paidAmount, 2) . ") is less than required registration fee (₦" . number_format($expectedFee, 2) . ")."
            ];
        }

        $finalEmail = !empty($payerEmail) ? $payerEmail : ($customer['email'] ?? ($user['email'] ?? ''));
        $finalPhone = !empty($payerPhone) ? $payerPhone : ($customer['phone'] ?? ($user['phone_number'] ?? ''));
        $finalName  = !empty($payerName)  ? $payerName  : trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
        if (empty($finalName)) {
            $finalName = $user['full_name'] ?? $user['username'];
        }

        $ipAddress = Security::getClientIp();

        // 6. Atomically Save Payment Audit & Update User Status
        try {
            $pdo->beginTransaction();

            // Insert into payments table
            $paymentStmt = $pdo->prepare("
                INSERT INTO `payments` (
                    `user_id`, `transaction_id`, `amount`, `currency`, `status`,
                    `payment_method`, `payment_type`, `channel`, `payer_email`, `payer_name`,
                    `payer_phone`, `ip_address`, `raw_response`, `created_at`
                ) VALUES (
                    :user_id, :transaction_id, :amount, :currency, 'success',
                    'paystack', 'registration', :channel, :payer_email, :payer_name,
                    :payer_phone, :ip_address, :raw_response, NOW()
                )
            ");

            $paymentStmt->execute([
                ':user_id'        => $userId,
                ':transaction_id' => $reference,
                ':amount'         => $paidAmount,
                ':currency'       => $currency,
                ':channel'        => $channel,
                ':payer_email'    => $finalEmail,
                ':payer_name'     => $finalName,
                ':payer_phone'    => $finalPhone,
                ':ip_address'     => $ipAddress,
                ':raw_response'   => $rawResponse
            ]);

            // Update user record
            $userUpdateStmt = $pdo->prepare("
                UPDATE `users` 
                SET `registration_status` = 'paid',
                    `registration_paid_at` = NOW(),
                    `registration_payment_ref` = :ref,
                    `registration_fee_paid` = :amt
                WHERE id = :id
            ");

            $userUpdateStmt->execute([
                ':ref' => $reference,
                ':amt' => $paidAmount,
                ':id'  => $userId
            ]);

            $pdo->commit();

            // Refresh user data
            $user['registration_status'] = 'paid';
            $user['registration_paid_at'] = date('Y-m-d H:i:s');
            $user['registration_payment_ref'] = $reference;
            $user['registration_fee_paid'] = $paidAmount;

            return [
                'success' => true,
                'message' => '🎉 Registration payment verified successfully! Your contestant profile is now fully active.',
                'user'    => $user
            ];
        } catch (Exception $dbEx) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Registration DB transaction error: " . $dbEx->getMessage());
            return ['success' => false, 'error' => 'Database error while saving registration payment record.'];
        }
    }

    /**
     * Manually update a contestant's registration status (Admin Action)
     */
    public static function manuallyUpdateStatus(int $userId, string $status, ?float $amount = null, ?string $reference = null): array {
        self::ensureSchema();

        $status = strtolower(trim($status));
        if (!in_array($status, ['paid', 'pending', 'exempt'], true)) {
            return ['success' => false, 'error' => 'Invalid registration status specified.'];
        }

        $pdo = DB::pdo();

        $stmt = $pdo->prepare("SELECT id, full_name, email, phone_number, username, registration_status FROM `users` WHERE id = ? AND is_admin = 0 LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'error' => 'Contestant not found.'];
        }

        $feeAmount = $amount !== null ? $amount : ($status === 'paid' ? self::getRegistrationFee() : 0.0);
        $ref = $reference ?? ($status === 'paid' ? 'MANUAL_ADMIN_' . time() . '_' . $userId : null);
        $paidAt = $status === 'paid' ? date('Y-m-d H:i:s') : null;

        $updateStmt = $pdo->prepare("
            UPDATE `users` 
            SET `registration_status` = :status,
                `registration_paid_at` = :paid_at,
                `registration_payment_ref` = :ref,
                `registration_fee_paid` = :amt
            WHERE id = :id
        ");

        $updateStmt->execute([
            ':status'  => $status,
            ':paid_at' => $paidAt,
            ':ref'     => $ref,
            ':amt'     => $feeAmount,
            ':id'      => $userId
        ]);

        // If marked as paid, also record in payments ledger for financial audit trail if not already present
        if ($status === 'paid' && !empty($ref)) {
            try {
                $checkP = $pdo->prepare("SELECT id FROM `payments` WHERE transaction_id = ? LIMIT 1");
                $checkP->execute([$ref]);
                if (!$checkP->fetch()) {
                    $insertP = $pdo->prepare("
                        INSERT INTO `payments` (
                            `user_id`, `transaction_id`, `amount`, `currency`, `status`,
                            `payment_method`, `payment_type`, `channel`, `payer_email`, `payer_name`,
                            `payer_phone`, `ip_address`, `created_at`
                        ) VALUES (
                            :user_id, :transaction_id, :amount, 'NGN', 'success',
                            'manual_admin', 'registration', 'manual_bank_transfer', :payer_email, :payer_name,
                            :payer_phone, :ip_address, NOW()
                        )
                    ");
                    $insertP->execute([
                        ':user_id'        => $userId,
                        ':transaction_id' => $ref,
                        ':amount'         => $feeAmount,
                        ':payer_email'    => $user['email'] ?? '',
                        ':payer_name'     => $user['full_name'] ?? $user['username'],
                        ':payer_phone'    => $user['phone_number'] ?? '',
                        ':ip_address'     => Security::getClientIp()
                    ]);
                }
            } catch (Exception $payEx) {
                error_log("Manual payment ledger sync error: " . $payEx->getMessage());
            }
        }

        return [
            'success' => true,
            'message' => "Contestant {$user['full_name']} registration approved and set to: " . strtoupper($status)
        ];
    }

    /**
     * Bulk approve and activate multiple contestants (Admin Manual Approval)
     */
    public static function bulkApproveRegistrations(array $userIds, ?string $adminNotes = null): array {
        self::ensureSchema();
        $approvedCount = 0;
        foreach ($userIds as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ref = 'MANUAL_BULK_APPROVED_' . time() . '_' . $id;
                $res = self::manuallyUpdateStatus($id, 'paid', null, $ref);
                if ($res['success']) {
                    $approvedCount++;
                }
            }
        }
        return [
            'success' => true,
            'count'   => $approvedCount,
            'message' => "Successfully approved and activated {$approvedCount} contestant(s)."
        ];
    }

    /**
     * Get all registrations with optional filters for Admin Panel
     */
    public static function getAllRegistrations(?string $status = null, ?string $search = null, int $limit = 100, int $offset = 0): array {
        self::ensureSchema();
        $pdo = DB::pdo();

        try {
            $sql = "
                SELECT 
                    u.id, u.username, u.full_name, u.email, u.phone_number, u.photo, 
                    u.vote_count, u.is_active, u.created_at,
                    u.registration_status, u.registration_paid_at, 
                    u.registration_payment_ref, u.registration_fee_paid
                FROM `users` u
                WHERE u.is_admin = 0
            ";
            $params = [];

            if (!empty($status) && $status !== 'all') {
                $sql .= " AND u.registration_status = :status";
                $params[':status'] = strtolower($status);
            }

            if (!empty($search)) {
                $sql .= " AND (u.full_name LIKE :s OR u.username LIKE :s OR u.email LIKE :s OR u.phone_number LIKE :s OR u.registration_payment_ref LIKE :s)";
                $params[':s'] = '%' . $search . '%';
            }

            $sql .= " ORDER BY u.created_at DESC LIMIT :limit OFFSET :offset";

            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("getAllRegistrations error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get registration metrics for dashboard stat cards
     */
    public static function getRegistrationStats(): array {
        self::ensureSchema();
        $pdo = DB::pdo();

        try {
            $stats = [
                'total_contestants'     => 0,
                'paid_count'            => 0,
                'pending_count'         => 0,
                'exempt_count'          => 0,
                'total_revenue'         => 0.00,
                'current_fee'           => self::getRegistrationFee()
            ];

            $stmt = $pdo->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN registration_status = 'paid' THEN 1 ELSE 0 END) as paid_cnt,
                    SUM(CASE WHEN registration_status = 'pending' THEN 1 ELSE 0 END) as pending_cnt,
                    SUM(CASE WHEN registration_status = 'exempt' THEN 1 ELSE 0 END) as exempt_cnt,
                    SUM(CASE WHEN registration_status = 'paid' THEN registration_fee_paid ELSE 0 END) as total_rev
                FROM `users` 
                WHERE is_admin = 0
            ");

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $stats['total_contestants'] = (int)($row['total'] ?? 0);
                $stats['paid_count']        = (int)($row['paid_cnt'] ?? 0);
                $stats['pending_count']     = (int)($row['pending_cnt'] ?? 0);
                $stats['exempt_count']      = (int)($row['exempt_cnt'] ?? 0);
                $stats['total_revenue']     = (float)($row['total_rev'] ?? 0.00);
            }

            return $stats;
        } catch (Exception $e) {
            error_log("getRegistrationStats error: " . $e->getMessage());
            return [
                'total_contestants' => 0,
                'paid_count'        => 0,
                'pending_count'     => 0,
                'exempt_count'      => 0,
                'total_revenue'     => 0.00,
                'current_fee'       => self::getRegistrationFee()
            ];
        }
    }

    /**
     * Format status HTML badge
     */
    public static function formatStatusBadge(string $status): string {
        $status = strtolower($status);
        switch ($status) {
            case 'paid':
                return '<span class="badge bg-success text-white px-2 py-1"><i class="fas fa-check-circle me-1"></i> Paid & Verified</span>';
            case 'pending':
                return '<span class="badge bg-warning text-dark px-2 py-1"><i class="fas fa-clock me-1"></i> Pending Payment</span>';
            case 'exempt':
                return '<span class="badge bg-info text-white px-2 py-1"><i class="fas fa-gift me-1"></i> Free / Exempt</span>';
            default:
                return '<span class="badge bg-secondary text-white px-2 py-1">' . htmlspecialchars(ucfirst($status)) . '</span>';
        }
    }
}


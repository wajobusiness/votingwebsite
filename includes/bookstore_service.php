<?php
/**
 * Bookstore Service & Database Layer
 * Handles digital publications, auto-migration, seeding, and fulfillment links
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/settings.php';

class BookstoreService {

    /**
     * Ensure books and book_purchases tables exist and are properly indexed
     */
    public static function ensureTable(): void {
        $pdo = DB::pdo();
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `books` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `title` VARCHAR(255) NOT NULL,
                    `author` VARCHAR(255) DEFAULT 'Crown Night Star',
                    `category` VARCHAR(100) DEFAULT 'General',
                    `cover_image` VARCHAR(255) NOT NULL,
                    `description` TEXT NOT NULL,
                    `short_description` VARCHAR(500) DEFAULT NULL,
                    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    `delivery_type` ENUM('pdf', 'link', 'whatsapp') NOT NULL DEFAULT 'whatsapp',
                    `pdf_file` VARCHAR(255) DEFAULT NULL,
                    `download_link` VARCHAR(500) DEFAULT NULL,
                    `whatsapp_number` VARCHAR(50) DEFAULT NULL,
                    `preview_text` TEXT DEFAULT NULL,
                    `pages_count` INT(11) DEFAULT 120,
                    `is_active` TINYINT(1) DEFAULT 1,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_books_active` (`is_active`),
                    KEY `idx_books_category` (`category`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `book_purchases` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `book_id` INT(11) NOT NULL,
                    `user_id` INT(11) DEFAULT NULL,
                    `buyer_name` VARCHAR(255) NOT NULL,
                    `buyer_email` VARCHAR(255) NOT NULL,
                    `buyer_phone` VARCHAR(50) DEFAULT NULL,
                    `amount` DECIMAL(10,2) NOT NULL,
                    `currency` VARCHAR(10) NOT NULL DEFAULT 'NGN',
                    `reference` VARCHAR(100) NOT NULL,
                    `status` ENUM('pending', 'success', 'failed') NOT NULL DEFAULT 'pending',
                    `access_token` VARCHAR(64) NOT NULL,
                    `download_count` INT(11) NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uniq_reference` (`reference`),
                    UNIQUE KEY `uniq_access_token` (`access_token`),
                    KEY `idx_buyer_email` (`buyer_email`),
                    KEY `idx_user_id` (`user_id`),
                    KEY `idx_book_id` (`book_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // Auto-update support phone to 08139188570 if old default is present
            @$pdo->exec("UPDATE settings SET value = '08139188570' WHERE name = 'support_phone' AND value = '09067619370'");
            @$pdo->exec("UPDATE books SET whatsapp_number = '08139188570' WHERE whatsapp_number = '09067619370'");

            self::seedSampleBooksIfEmpty();
        } catch (Exception $e) {
            error_log("Bookstore table init error: " . $e->getMessage());
        }
    }

    /**
     * Seed 4 professional sample books if table has zero records
     */
    public static function seedSampleBooksIfEmpty(): void {
        $pdo = DB::pdo();
        try {
            $count = (int)$pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
            if ($count === 0) {
                $supportPhone = Settings::get('support_phone', '08139188570');

                $sampleBooks = [
                    [
                        'title'             => 'The Crown Within: The Ultimate Guide to Winning Pageantry & Public Contests',
                        'author'            => 'Crown Night Star Editorial Team',
                        'category'          => 'Pageantry & Leadership',
                        'cover_image'       => 'assets2/images/book1.jpg',
                        'description'       => "The Crown Within is the definitive roadmap for aspiring models, beauty queens, and contest finalists. Written by industry judges and former titleholders, this comprehensive guide covers everything from building an irresistible personal story, mastering on-stage interview questions, runway composure, and running high-conversion social media voting campaigns that capture thousands of supporter votes.\n\nInside, you'll discover:\n- The 7 psychological triggers that turn casual followers into passionate voters.\n- Step-by-step templates for interview answers that leave lasting impressions.\n- Stage presence, poise, and posture secrets used by international runway coaches.\n- Crisis management and confidence reinforcement during intense contest rounds.",
                        'short_description' => 'Master stage presence, public voting strategy, judge interview mastery, and personal branding to claim your title.',
                        'price'             => 3500.00,
                        'delivery_type'     => 'whatsapp',
                        'pdf_file'          => null,
                        'download_link'     => null,
                        'whatsapp_number'   => $supportPhone,
                        'preview_text'      => "Chapter 1: The Anatomy of a Winning Mindset\nEvery crown is won in the mind before it is placed on the head. Confidence is not the absence of doubt, but the mastery of purpose. In this chapter, we explore the mental architecture of champions who command attention from the moment they step onto the stage...",
                        'pages_count'       => 164
                    ],
                    [
                        'title'             => 'Unstoppable Grace: Building a High-Impact Personal Brand & Confidence',
                        'author'            => 'Dr. Sophia Adeleke',
                        'category'          => 'Personal Branding',
                        'cover_image'       => 'assets2/images/book2.jpg',
                        'description'       => "In today's digital age, your personal brand is your most valuable currency. Unstoppable Grace is a masterclass in establishing magnetic charisma, crafting an authentic digital identity, and turning your passion into commercial brand sponsorships and community leadership.",
                        'short_description' => 'Transform your digital presence, command executive charisma, and attract high-value brand partnerships.',
                        'price'             => 2500.00,
                        'delivery_type'     => 'link',
                        'pdf_file'          => null,
                        'download_link'     => 'https://crownnightstar.com/about-us.php',
                        'whatsapp_number'   => $supportPhone,
                        'preview_text'      => "Introduction: The Magnetic Identity\nYour brand is not what you say it is; it is the emotional imprint you leave in any room you enter. Discover the three pillars of sustainable influence...",
                        'pages_count'       => 138
                    ],
                    [
                        'title'             => 'Viral Mobilization: Social Media Audience Growth for Creators & Contestants',
                        'author'            => 'David O. Vance',
                        'category'          => 'Digital Marketing',
                        'cover_image'       => 'assets2/images/book3.jpg',
                        'description'       => "Unlock the algorithmic blueprints behind viral reels, TikTok trends, and broadcast WhatsApp marketing. Designed specifically for influencers, public figures, and voting contestants who need rapid, ethical mobilization of supporter networks.",
                        'short_description' => 'Algorithmic strategies to scale engagement, engineer viral short-form video hooks, and convert views into real daily votes.',
                        'price'             => 4000.00,
                        'delivery_type'     => 'whatsapp',
                        'pdf_file'          => null,
                        'download_link'     => null,
                        'whatsapp_number'   => $supportPhone,
                        'preview_text'      => "Chapter 3: The First Three Seconds\nAttention on short-form video is won or lost in 180 frames. Here are the 12 psychological opening hooks tested across over 10 million organic views...",
                        'pages_count'       => 192
                    ],
                    [
                        'title'             => 'The Model’s Playbook: High-Fashion Posing, Runway & Portfolio Secrets',
                        'author'            => 'Elena Rostova & Fashion Guild',
                        'category'          => 'Modeling & Fashion',
                        'cover_image'       => 'assets2/images/book4.jpg',
                        'description'       => "A visual master guidebook detailing geometric posing angles, editorial lighting interaction, facial micro-expressions, and compiling an international-standard model comp card and portfolio.",
                        'short_description' => 'Insider techniques from runway directors covering high-fashion posing, editorial photography, and agency scouting.',
                        'price'             => 3000.00,
                        'delivery_type'     => 'whatsapp',
                        'pdf_file'          => null,
                        'download_link'     => null,
                        'whatsapp_number'   => $supportPhone,
                        'preview_text'      => "Section 2: Geometric Body Angles\nThe camera captures two dimensions; your job as a model is to create the illusion of infinite dynamic depth. Learn the shoulder-tilt and hip-axis principles...",
                        'pages_count'       => 150
                    ]
                ];

                $stmt = $pdo->prepare("
                    INSERT INTO books (
                        title, author, category, cover_image, description, short_description, 
                        price, delivery_type, pdf_file, download_link, whatsapp_number, preview_text, pages_count, is_active
                    ) VALUES (
                        :title, :author, :category, :cover_image, :description, :short_description, 
                        :price, :delivery_type, :pdf_file, :download_link, :whatsapp_number, :preview_text, :pages_count, 1
                    )
                ");

                foreach ($sampleBooks as $book) {
                    $stmt->execute([
                        ':title'             => $book['title'],
                        ':author'            => $book['author'],
                        ':category'          => $book['category'],
                        ':cover_image'       => $book['cover_image'],
                        ':description'       => $book['description'],
                        ':short_description' => $book['short_description'],
                        ':price'             => $book['price'],
                        ':delivery_type'     => $book['delivery_type'],
                        ':pdf_file'          => $book['pdf_file'],
                        ':download_link'     => $book['download_link'],
                        ':whatsapp_number'   => $book['whatsapp_number'],
                        ':preview_text'      => $book['preview_text'],
                        ':pages_count'       => $book['pages_count']
                    ]);
                }
            }
        } catch (Exception $e) {
            error_log("Seed books error: " . $e->getMessage());
        }
    }

    /**
     * Get all active books for the storefront
     */
    public static function getActiveBooks(?string $category = null): array {
        self::ensureTable();
        $pdo = DB::pdo();
        try {
            if (!empty($category) && $category !== 'all') {
                $stmt = $pdo->prepare("SELECT * FROM books WHERE is_active = 1 AND category = :cat ORDER BY id DESC");
                $stmt->execute([':cat' => $category]);
            } else {
                $stmt = $pdo->query("SELECT * FROM books WHERE is_active = 1 ORDER BY id DESC");
            }
            return $stmt->fetchAll() ?: [];
        } catch (Exception $e) {
            error_log("Get active books error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all books for admin dashboard management
     */
    public static function getAllBooks(): array {
        self::ensureTable();
        $pdo = DB::pdo();
        try {
            $stmt = $pdo->query("SELECT * FROM books ORDER BY id DESC");
            return $stmt->fetchAll() ?: [];
        } catch (Exception $e) {
            error_log("Get all books error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get single book by ID
     */
    public static function getBookById(int $id): ?array {
        self::ensureTable();
        $pdo = DB::pdo();
        try {
            $stmt = $pdo->prepare("SELECT * FROM books WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $book = $stmt->fetch();
            return $book ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Verify Paystack transaction reference and record book purchase
     */
    public static function verifyAndFulfillPurchase(
        string $reference,
        int $bookId,
        string $buyerEmail,
        string $buyerName,
        ?string $buyerPhone = null,
        ?int $userId = null
    ): array {
        self::ensureTable();
        $reference = trim($reference);

        if (empty($reference)) {
            return ['success' => false, 'error' => 'Transaction reference is required.'];
        }

        if ($bookId <= 0) {
            return ['success' => false, 'error' => 'A valid book ID must be specified.'];
        }

        $book = self::getBookById($bookId);
        if (!$book) {
            return ['success' => false, 'error' => 'Requested book publication could not be found.'];
        }

        $pdo = DB::pdo();

        // 1. Idempotency Check: Verify if purchase was already recorded
        $checkStmt = $pdo->prepare("SELECT * FROM book_purchases WHERE reference = ? LIMIT 1");
        $checkStmt->execute([$reference]);
        $existing = $checkStmt->fetch();

        if ($existing && $existing['status'] === 'success') {
            return [
                'success'       => true,
                'already_saved' => true,
                'access_token'  => $existing['access_token'],
                'purchase'      => $existing,
                'book'          => $book,
                'redirect_url'  => 'order_success.php?token=' . urlencode($existing['access_token'])
            ];
        }

        // 2. Query Paystack REST API to verify payment
        $secretKey = Env::get('PAYSTACK_SECRET_KEY');
        if (empty($secretKey)) {
            return ['success' => false, 'error' => 'Payment gateway secret key is not configured.'];
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
            error_log("Paystack Verification cURL Error: " . $curlError);
            return ['success' => false, 'error' => 'Unable to connect to Paystack payment gateway.'];
        }

        $responseData = json_decode($rawResponse, true);
        if ($httpCode !== 200 || !isset($responseData['status']) || $responseData['status'] !== true) {
            $msg = $responseData['message'] ?? 'Transaction verification failed on gateway.';
            return ['success' => false, 'error' => $msg];
        }

        $data = $responseData['data'] ?? [];
        if (($data['status'] ?? '') !== 'success') {
            return ['success' => false, 'error' => 'Payment was not marked successful by gateway.'];
        }

        // 3. Verify Amount
        $paidAmountKobo = (int)($data['amount'] ?? 0);
        $paidAmount = $paidAmountKobo / 100.0;
        $currency = $data['currency'] ?? 'NGN';
        $customer = $data['customer'] ?? [];

        $finalEmail = !empty($buyerEmail) ? $buyerEmail : ($customer['email'] ?? 'customer@crownnightstar.com');
        $finalName  = !empty($buyerName) ? $buyerName : trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
        if (empty($finalName)) {
            $finalName = 'Valued Customer';
        }
        $finalPhone = !empty($buyerPhone) ? $buyerPhone : ($customer['phone'] ?? null);

        // Generate cryptographically unique access token
        $accessToken = bin2hex(random_bytes(24));

        // 4. Save Purchase Record
        try {
            $insertStmt = $pdo->prepare("
                INSERT INTO book_purchases (
                    book_id, user_id, buyer_name, buyer_email, buyer_phone,
                    amount, currency, reference, status, access_token
                ) VALUES (
                    :book_id, :user_id, :buyer_name, :buyer_email, :buyer_phone,
                    :amount, :currency, :reference, 'success', :access_token
                )
            ");

            $insertStmt->execute([
                ':book_id'      => $bookId,
                ':user_id'      => ($userId && $userId > 0) ? $userId : null,
                ':buyer_name'   => $finalName,
                ':buyer_email'  => $finalEmail,
                ':buyer_phone'  => $finalPhone,
                ':amount'       => $paidAmount,
                ':currency'     => $currency,
                ':reference'    => $reference,
                ':access_token' => $accessToken
            ]);

            $purchaseId = (int)$pdo->lastInsertId();

            // Also record in payments table for consolidated financial reporting
            try {
                $payStmt = $pdo->prepare("
                    INSERT INTO payments (user_id, amount, transaction_id, status, payment_method, type)
                    VALUES (:user_id, :amount, :transaction_id, 'success', 'paystack', 'book_purchase')
                ");
                $payStmt->execute([
                    ':user_id'        => ($userId && $userId > 0) ? $userId : null,
                    ':amount'         => $paidAmount,
                    ':transaction_id' => $reference
                ]);
            } catch (Exception $pe) {
                // If payments table has different schema or type column, fail silently
            }

            return [
                'success'      => true,
                'access_token' => $accessToken,
                'purchase_id'  => $purchaseId,
                'book'         => $book,
                'redirect_url' => 'order_success.php?token=' . urlencode($accessToken)
            ];
        } catch (Exception $e) {
            error_log("Save book purchase error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error while saving purchase: ' . $e->getMessage()];
        }
    }

    /**
     * Retrieve a purchase along with its book info using an access token
     */
    public static function getPurchaseByToken(string $token): ?array {
        self::ensureTable();
        $pdo = DB::pdo();
        try {
            $stmt = $pdo->prepare("
                SELECT p.*, b.title AS book_title, b.author AS book_author, b.category AS book_category,
                       b.cover_image, b.description AS book_description, b.short_description AS book_short_desc,
                       b.delivery_type, b.pdf_file, b.download_link, b.whatsapp_number, b.pages_count
                FROM book_purchases p
                JOIN books b ON p.book_id = b.id
                WHERE p.access_token = :token AND p.status = 'success'
                LIMIT 1
            ");
            $stmt->execute([':token' => $token]);
            $purchase = $stmt->fetch();
            return $purchase ?: null;
        } catch (Exception $e) {
            error_log("Get purchase by token error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Retrieve all purchases made by a specific user (by ID or email)
     */
    public static function getPurchasesByUser(int $userId, ?string $email = null): array {
        self::ensureTable();
        $pdo = DB::pdo();
        try {
            $stmt = $pdo->prepare("
                SELECT p.*, b.title AS book_title, b.author AS book_author, b.category AS book_category,
                       b.cover_image, b.delivery_type, b.pdf_file, b.download_link, b.whatsapp_number, b.pages_count
                FROM book_purchases p
                JOIN books b ON p.book_id = b.id
                WHERE (p.user_id = :uid OR (p.buyer_email = :email AND :email != '')) AND p.status = 'success'
                ORDER BY p.id DESC
            ");
            $stmt->execute([
                ':uid'   => $userId,
                ':email' => $email ?? ''
            ]);
            return $stmt->fetchAll() ?: [];
        } catch (Exception $e) {
            error_log("Get purchases by user error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Retrieve all recent purchases for admin catalog view
     */
    public static function getAllPurchases(int $limit = 100): array {
        self::ensureTable();
        $pdo = DB::pdo();
        try {
            $stmt = $pdo->prepare("
                SELECT p.*, b.title AS book_title, b.delivery_type, b.cover_image
                FROM book_purchases p
                JOIN books b ON p.book_id = b.id
                ORDER BY p.id DESC
                LIMIT :lim
            ");
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll() ?: [];
        } catch (Exception $e) {
            error_log("Get all purchases error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Increment download count for a purchase
     */
    public static function incrementDownloadCount(int $purchaseId): void {
        $pdo = DB::pdo();
        try {
            $stmt = $pdo->prepare("UPDATE book_purchases SET download_count = download_count + 1 WHERE id = ?");
            $stmt->execute([$purchaseId]);
        } catch (Exception $e) {
            // Ignored
        }
    }

    /**
     * Generate Post-Purchase WhatsApp Access URL
     */
    public static function getWhatsAppAccessUrl(array $book, array $purchase): string {
        $rawPhone = !empty($book['whatsapp_number']) ? $book['whatsapp_number'] : Settings::get('support_phone', '08139188570');
        
        $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);
        if (substr($cleanPhone, 0, 1) === '0') {
            $cleanPhone = '234' . substr($cleanPhone, 1);
        }

        $currency = Settings::getCurrencySymbol();
        $amountStr = $currency . number_format((float)($purchase['amount'] ?? $book['price']), 2);
        $title = $book['book_title'] ?? $book['title'] ?? 'Digital Publication';
        $ref = $purchase['reference'] ?? 'ONLINE-ORDER';
        $buyerEmail = $purchase['buyer_email'] ?? '';

        $message = "Hello Crown Night Star! 🌟\n\nI have successfully completed payment for:\n📖 *{$title}*\n💰 Amount Paid: {$amountStr}\n🔖 Order Reference: {$ref}\n📧 My Email: {$buyerEmail}\n\nPlease grant me immediate access / add me to the masterclass channel. Thank you!";
        
        return "https://api.whatsapp.com/send?phone=" . urlencode($cleanPhone) . "&text=" . urlencode($message);
    }

    /**
     * Generate pre-order WhatsApp redirect URL (fallback)
     */
    public static function getWhatsAppUrl(array $book, ?string $phone = null): string {
        $currency = Settings::getCurrencySymbol();
        $rawPhone = !empty($book['whatsapp_number']) ? $book['whatsapp_number'] : ($phone ?: Settings::get('support_phone', '08139188570'));
        
        $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);
        if (substr($cleanPhone, 0, 1) === '0') {
            $cleanPhone = '234' . substr($cleanPhone, 1);
        }

        $priceStr = $currency . number_format((float)$book['price'], 2);
        $message = "Hello Crown Night Star, I would like to purchase the digital book:\n\n📖 *{$book['title']}*\n💰 Price: {$priceStr}\n\nPlease guide me on the payment and instant delivery. Thank you!";
        
        return "https://api.whatsapp.com/send?phone=" . urlencode($cleanPhone) . "&text=" . urlencode($message);
    }
}

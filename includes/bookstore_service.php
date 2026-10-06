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
     * Ensure books table exists and is properly indexed
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
                $supportPhone = Settings::get('support_phone', '09067619370');

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
     * Generate WhatsApp order redirect URL
     */
    public static function getWhatsAppUrl(array $book, ?string $phone = null): string {
        $currency = Settings::getCurrencySymbol();
        $rawPhone = !empty($book['whatsapp_number']) ? $book['whatsapp_number'] : ($phone ?: Settings::get('support_phone', '09067619370'));
        
        // Sanitize phone number to international format
        $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);
        if (substr($cleanPhone, 0, 1) === '0') {
            $cleanPhone = '234' . substr($cleanPhone, 1);
        }

        $priceStr = $currency . number_format((float)$book['price'], 2);
        $message = "Hello Crown Night Star, I would like to purchase the digital book:\n\n📖 *{$book['title']}*\n💰 Price: {$priceStr}\n\nPlease guide me on the payment and instant delivery. Thank you!";
        
        return "https://api.whatsapp.com/send?phone=" . urlencode($cleanPhone) . "&text=" . urlencode($message);
    }
}

<?php
/**
 * Contest Battles Service & Database Layer
 * Handles head-to-head battles, online/physical venue systems, auto-migration, seeding, and CRUD
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/settings.php';

class BattlesService {

    /**
     * Ensure contest_battles table exists and is properly indexed
     */
    public static function ensureTable(): void {
        $pdo = DB::pdo();
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `contest_battles` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `title` VARCHAR(255) NOT NULL,
                    `category` VARCHAR(100) NOT NULL DEFAULT 'Talent Battle',
                    `description` TEXT DEFAULT NULL,
                    `contestant_one_name` VARCHAR(255) NOT NULL,
                    `contestant_one_image` VARCHAR(255) NOT NULL,
                    `contestant_two_name` VARCHAR(255) NOT NULL,
                    `contestant_two_image` VARCHAR(255) NOT NULL,
                    `banner_image` VARCHAR(255) DEFAULT NULL,
                    `battle_date` DATE NOT NULL,
                    `battle_time` VARCHAR(50) NOT NULL,
                    `venue_type` ENUM('online', 'physical') NOT NULL DEFAULT 'online',
                    `platform` VARCHAR(100) DEFAULT 'Instagram Live',
                    `live_url` VARCHAR(500) DEFAULT NULL,
                    `venue_name` VARCHAR(255) DEFAULT NULL,
                    `venue_address` VARCHAR(255) DEFAULT NULL,
                    `venue_city` VARCHAR(100) DEFAULT NULL,
                    `venue_state` VARCHAR(100) DEFAULT NULL,
                    `maps_url` VARCHAR(500) DEFAULT NULL,
                    `status` ENUM('upcoming', 'live', 'ended', 'cancelled') NOT NULL DEFAULT 'upcoming',
                    `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
                    `is_published` TINYINT(1) NOT NULL DEFAULT 1,
                    `display_order` INT(11) NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_battles_published` (`is_published`),
                    KEY `idx_battles_status` (`status`),
                    KEY `idx_battles_order` (`display_order` ASC, `battle_date` ASC)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            self::seedSampleBattlesIfEmpty();
        } catch (Exception $e) {
            error_log("Contest Battles table init error: " . $e->getMessage());
        }
    }

    /**
     * Seed realistic sample battles if table has zero records
     */
    public static function seedSampleBattlesIfEmpty(): void {
        $pdo = DB::pdo();
        try {
            $count = (int)$pdo->query("SELECT COUNT(*) FROM contest_battles")->fetchColumn();
            if ($count === 0) {
                $sampleBattles = [
                    [
                        'title'                => 'Crown Rap Clash: Midnight Freestyle Showdown',
                        'category'             => 'Rap Battle',
                        'description'          => 'The two top-ranked lyrical titans go head-to-head in a 3-round live freestyle and flow battle. Who will capture the audience vote and walk away crowned the undisputed lyrical king?',
                        'contestant_one_name'  => 'Queen Bella',
                        'contestant_one_image' => 'uploads/IMG_4539.JPG',
                        'contestant_two_name'  => 'King Draco',
                        'contestant_two_image' => 'uploads/IMG_4573.jpg',
                        'banner_image'         => 'assets2/images/login-bg.jpg',
                        'battle_date'          => date('Y-m-d', strtotime('+2 days')),
                        'battle_time'          => '08:00 PM WAT',
                        'venue_type'           => 'online',
                        'platform'             => 'Instagram Live',
                        'live_url'             => 'https://instagram.com/crownnightstar',
                        'venue_name'           => null,
                        'venue_address'        => null,
                        'venue_city'           => null,
                        'venue_state'          => null,
                        'maps_url'             => null,
                        'status'               => 'live',
                        'is_featured'          => 1,
                        'is_published'         => 1,
                        'display_order'        => 1
                    ],
                    [
                        'title'                => 'Grand Stage Dance-Off: Afrobeats & Hip-Hop Fusion',
                        'category'             => 'Dance Battle',
                        'description'          => 'An electrifying live choreography and street dance duel featuring high-energy moves, stamina tests, and crowd-judged rhythm rounds.',
                        'contestant_one_name'  => 'Zara Lynx',
                        'contestant_one_image' => 'uploads/IMG_5458.JPG',
                        'contestant_two_name'  => 'Maya Cruz',
                        'contestant_two_image' => 'uploads/IMG_7512.jpg',
                        'banner_image'         => 'assets2/images/login-bg.jpg',
                        'battle_date'          => date('Y-m-d', strtotime('+4 days')),
                        'battle_time'          => '07:30 PM WAT',
                        'venue_type'           => 'online',
                        'platform'             => 'TikTok Live',
                        'live_url'             => 'https://tiktok.com/@crownnightstar',
                        'venue_name'           => null,
                        'venue_address'        => null,
                        'venue_city'           => null,
                        'venue_state'          => null,
                        'maps_url'             => null,
                        'status'               => 'upcoming',
                        'is_featured'          => 1,
                        'is_published'         => 1,
                        'display_order'        => 2
                    ],
                    [
                        'title'                => 'Acoustic Vocal Showcase: High Notes Duel',
                        'category'             => 'Singing Battle',
                        'description'          => 'Pure vocals, no autotune! Two extraordinary powerhouse vocalists battle it out across classic soul, RnB, and powerhouse ballad serenades in a physical VIP venue.',
                        'contestant_one_name'  => 'David Praise',
                        'contestant_one_image' => 'uploads/IMG_9440.jpg',
                        'contestant_two_name'  => 'Cynthia Cole',
                        'contestant_two_image' => 'uploads/IMG_9441.jpg',
                        'banner_image'         => 'assets2/images/login-bg.jpg',
                        'battle_date'          => date('Y-m-d', strtotime('+7 days')),
                        'battle_time'          => '06:00 PM WAT',
                        'venue_type'           => 'physical',
                        'platform'             => null,
                        'live_url'             => null,
                        'venue_name'           => 'The Crown Grand Ballroom & Lounge',
                        'venue_address'        => 'Plot 14, Adetokunbo Ademola Street',
                        'venue_city'           => 'Victoria Island',
                        'venue_state'          => 'Lagos State, Nigeria',
                        'maps_url'             => 'https://maps.google.com/?q=Victoria+Island+Lagos',
                        'status'               => 'upcoming',
                        'is_featured'          => 0,
                        'is_published'         => 1,
                        'display_order'        => 3
                    ]
                ];

                $stmt = $pdo->prepare("
                    INSERT INTO contest_battles (
                        title, category, description,
                        contestant_one_name, contestant_one_image,
                        contestant_two_name, contestant_two_image,
                        banner_image, battle_date, battle_time,
                        venue_type, platform, live_url,
                        venue_name, venue_address, venue_city, venue_state, maps_url,
                        status, is_featured, is_published, display_order
                    ) VALUES (
                        :title, :category, :description,
                        :contestant_one_name, :contestant_one_image,
                        :contestant_two_name, :contestant_two_image,
                        :banner_image, :battle_date, :battle_time,
                        :venue_type, :platform, :live_url,
                        :venue_name, :venue_address, :venue_city, :venue_state, :maps_url,
                        :status, :is_featured, :is_published, :display_order
                    )
                ");

                foreach ($sampleBattles as $b) {
                    $stmt->execute([
                        ':title'                => $b['title'],
                        ':category'             => $b['category'],
                        ':description'          => $b['description'],
                        ':contestant_one_name'  => $b['contestant_one_name'],
                        ':contestant_one_image' => $b['contestant_one_image'],
                        ':contestant_two_name'  => $b['contestant_two_name'],
                        ':contestant_two_image' => $b['contestant_two_image'],
                        ':banner_image'         => $b['banner_image'],
                        ':battle_date'          => $b['battle_date'],
                        ':battle_time'          => $b['battle_time'],
                        ':venue_type'           => $b['venue_type'],
                        ':platform'             => $b['platform'],
                        ':live_url'             => $b['live_url'],
                        ':venue_name'           => $b['venue_name'],
                        ':venue_address'        => $b['venue_address'],
                        ':venue_city'           => $b['venue_city'],
                        ':venue_state'          => $b['venue_state'],
                        ':maps_url'             => $b['maps_url'],
                        ':status'               => $b['status'],
                        ':is_featured'          => $b['is_featured'],
                        ':is_published'         => $b['is_published'],
                        ':display_order'        => $b['display_order']
                    ]);
                }
            }
        } catch (Exception $e) {
            error_log("Seed sample battles error: " . $e->getMessage());
        }
    }

    /**
     * Get published battles for public views
     * Ordered by: Live battles first, then Featured, then Display Order ASC, then Date ASC
     */
    public static function getPublishedBattles(?string $status = null, ?string $category = null, int $limit = 50): array {
        self::ensureTable();
        $pdo = DB::pdo();
        try {
            $sql = "SELECT * FROM contest_battles WHERE is_published = 1";
            $params = [];

            if (!empty($status) && $status !== 'all') {
                $sql .= " AND status = :status";
                $params[':status'] = $status;
            }

            if (!empty($category) && $category !== 'all') {
                $sql .= " AND category = :category";
                $params[':category'] = $category;
            }

            // Custom ordering: LIVE first, then display_order, then battle_date
            $sql .= " ORDER BY 
                CASE status 
                    WHEN 'live' THEN 1 
                    WHEN 'upcoming' THEN 2 
                    WHEN 'ended' THEN 3 
                    ELSE 4 
                END ASC,
                display_order ASC,
                is_featured DESC,
                battle_date ASC,
                battle_time ASC
                LIMIT " . (int)$limit;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (Exception $e) {
            error_log("Get published battles error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all battles for admin management
     */
    public static function getAllBattles(): array {
        self::ensureTable();
        $pdo = DB::pdo();
        try {
            $stmt = $pdo->query("SELECT * FROM contest_battles ORDER BY display_order ASC, id DESC");
            return $stmt->fetchAll() ?: [];
        } catch (Exception $e) {
            error_log("Get all battles error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get single battle by ID
     */
    public static function getBattleById(int $id): ?array {
        self::ensureTable();
        $pdo = DB::pdo();
        try {
            $stmt = $pdo->prepare("SELECT * FROM contest_battles WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $battle = $stmt->fetch();
            return $battle ?: null;
        } catch (Exception $e) {
            error_log("Get battle by ID error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create a new Contest Battle
     */
    public static function createBattle(array $data): array {
        self::ensureTable();
        $pdo = DB::pdo();

        $title = trim($data['title'] ?? '');
        $category = trim($data['category'] ?? 'Talent Battle');
        $description = trim($data['description'] ?? '');
        $contestantOneName = trim($data['contestant_one_name'] ?? '');
        $contestantOneImage = trim($data['contestant_one_image'] ?? 'uploads/default_avatar.png');
        $contestantTwoName = trim($data['contestant_two_name'] ?? '');
        $contestantTwoImage = trim($data['contestant_two_image'] ?? 'uploads/default_avatar.png');
        $bannerImage = !empty($data['banner_image']) ? trim($data['banner_image']) : null;
        $battleDate = trim($data['battle_date'] ?? date('Y-m-d'));
        $battleTime = trim($data['battle_time'] ?? '08:00 PM');
        $venueType = in_array($data['venue_type'] ?? '', ['online', 'physical'], true) ? $data['venue_type'] : 'online';
        $platform = trim($data['platform'] ?? 'Instagram Live');
        $liveUrl = trim($data['live_url'] ?? '');
        $venueName = trim($data['venue_name'] ?? '');
        $venueAddress = trim($data['venue_address'] ?? '');
        $venueCity = trim($data['venue_city'] ?? '');
        $venueState = trim($data['venue_state'] ?? '');
        $mapsUrl = trim($data['maps_url'] ?? '');
        $status = in_array($data['status'] ?? '', ['upcoming', 'live', 'ended', 'cancelled'], true) ? $data['status'] : 'upcoming';
        $isFeatured = !empty($data['is_featured']) ? 1 : 0;
        $isPublished = isset($data['is_published']) ? (int)$data['is_published'] : 1;
        $displayOrder = (int)($data['display_order'] ?? 0);

        if (empty($title)) {
            return ['success' => false, 'error' => 'Battle title is required.'];
        }
        if (empty($contestantOneName) || empty($contestantTwoName)) {
            return ['success' => false, 'error' => 'Both contestant names are required for a battle.'];
        }
        if (empty($battleDate)) {
            return ['success' => false, 'error' => 'Battle date is required.'];
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO contest_battles (
                    title, category, description,
                    contestant_one_name, contestant_one_image,
                    contestant_two_name, contestant_two_image,
                    banner_image, battle_date, battle_time,
                    venue_type, platform, live_url,
                    venue_name, venue_address, venue_city, venue_state, maps_url,
                    status, is_featured, is_published, display_order
                ) VALUES (
                    :title, :category, :description,
                    :contestant_one_name, :contestant_one_image,
                    :contestant_two_name, :contestant_two_image,
                    :banner_image, :battle_date, :battle_time,
                    :venue_type, :platform, :live_url,
                    :venue_name, :venue_address, :venue_city, :venue_state, :maps_url,
                    :status, :is_featured, :is_published, :display_order
                )
            ");

            $stmt->execute([
                ':title'                => $title,
                ':category'             => $category,
                ':description'          => $description,
                ':contestant_one_name'  => $contestantOneName,
                ':contestant_one_image' => $contestantOneImage,
                ':contestant_two_name'  => $contestantTwoName,
                ':contestant_two_image' => $contestantTwoImage,
                ':banner_image'         => $bannerImage,
                ':battle_date'          => $battleDate,
                ':battle_time'          => $battleTime,
                ':venue_type'           => $venueType,
                ':platform'             => $venueType === 'online' ? $platform : null,
                ':live_url'             => $venueType === 'online' ? $liveUrl : null,
                ':venue_name'           => $venueType === 'physical' ? $venueName : null,
                ':venue_address'        => $venueType === 'physical' ? $venueAddress : null,
                ':venue_city'           => $venueType === 'physical' ? $venueCity : null,
                ':venue_state'          => $venueType === 'physical' ? $venueState : null,
                ':maps_url'             => $venueType === 'physical' ? $mapsUrl : null,
                ':status'               => $status,
                ':is_featured'          => $isFeatured,
                ':is_published'         => $isPublished,
                ':display_order'        => $displayOrder
            ]);

            return ['success' => true, 'id' => (int)$pdo->lastInsertId()];
        } catch (Exception $e) {
            error_log("Create battle error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * Update an existing Contest Battle
     */
    public static function updateBattle(int $id, array $data): array {
        self::ensureTable();
        $pdo = DB::pdo();

        $existing = self::getBattleById($id);
        if (!$existing) {
            return ['success' => false, 'error' => 'Battle not found.'];
        }

        $title = trim($data['title'] ?? $existing['title']);
        $category = trim($data['category'] ?? $existing['category']);
        $description = trim($data['description'] ?? $existing['description']);
        $contestantOneName = trim($data['contestant_one_name'] ?? $existing['contestant_one_name']);
        $contestantOneImage = !empty($data['contestant_one_image']) ? trim($data['contestant_one_image']) : $existing['contestant_one_image'];
        $contestantTwoName = trim($data['contestant_two_name'] ?? $existing['contestant_two_name']);
        $contestantTwoImage = !empty($data['contestant_two_image']) ? trim($data['contestant_two_image']) : $existing['contestant_two_image'];
        $bannerImage = isset($data['banner_image']) && !empty($data['banner_image']) ? trim($data['banner_image']) : $existing['banner_image'];
        $battleDate = trim($data['battle_date'] ?? $existing['battle_date']);
        $battleTime = trim($data['battle_time'] ?? $existing['battle_time']);
        $venueType = in_array($data['venue_type'] ?? '', ['online', 'physical'], true) ? $data['venue_type'] : $existing['venue_type'];
        $platform = trim($data['platform'] ?? $existing['platform']);
        $liveUrl = trim($data['live_url'] ?? $existing['live_url']);
        $venueName = trim($data['venue_name'] ?? $existing['venue_name']);
        $venueAddress = trim($data['venue_address'] ?? $existing['venue_address']);
        $venueCity = trim($data['venue_city'] ?? $existing['venue_city']);
        $venueState = trim($data['venue_state'] ?? $existing['venue_state']);
        $mapsUrl = trim($data['maps_url'] ?? $existing['maps_url']);
        $status = in_array($data['status'] ?? '', ['upcoming', 'live', 'ended', 'cancelled'], true) ? $data['status'] : $existing['status'];
        $isFeatured = isset($data['is_featured']) ? (int)$data['is_featured'] : (int)$existing['is_featured'];
        $isPublished = isset($data['is_published']) ? (int)$data['is_published'] : (int)$existing['is_published'];
        $displayOrder = isset($data['display_order']) ? (int)$data['display_order'] : (int)$existing['display_order'];

        if (empty($title) || empty($contestantOneName) || empty($contestantTwoName)) {
            return ['success' => false, 'error' => 'Title and both contestant names are required.'];
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE contest_battles SET
                    title = :title,
                    category = :category,
                    description = :description,
                    contestant_one_name = :contestant_one_name,
                    contestant_one_image = :contestant_one_image,
                    contestant_two_name = :contestant_two_name,
                    contestant_two_image = :contestant_two_image,
                    banner_image = :banner_image,
                    battle_date = :battle_date,
                    battle_time = :battle_time,
                    venue_type = :venue_type,
                    platform = :platform,
                    live_url = :live_url,
                    venue_name = :venue_name,
                    venue_address = :venue_address,
                    venue_city = :venue_city,
                    venue_state = :venue_state,
                    maps_url = :maps_url,
                    status = :status,
                    is_featured = :is_featured,
                    is_published = :is_published,
                    display_order = :display_order
                WHERE id = :id
            ");

            $stmt->execute([
                ':title'                => $title,
                ':category'             => $category,
                ':description'          => $description,
                ':contestant_one_name'  => $contestantOneName,
                ':contestant_one_image' => $contestantOneImage,
                ':contestant_two_name'  => $contestantTwoName,
                ':contestant_two_image' => $contestantTwoImage,
                ':banner_image'         => $bannerImage,
                ':battle_date'          => $battleDate,
                ':battle_time'          => $battleTime,
                ':venue_type'           => $venueType,
                ':platform'             => $venueType === 'online' ? $platform : null,
                ':live_url'             => $venueType === 'online' ? $liveUrl : null,
                ':venue_name'           => $venueType === 'physical' ? $venueName : null,
                ':venue_address'        => $venueType === 'physical' ? $venueAddress : null,
                ':venue_city'           => $venueType === 'physical' ? $venueCity : null,
                ':venue_state'          => $venueType === 'physical' ? $venueState : null,
                ':maps_url'             => $venueType === 'physical' ? $mapsUrl : null,
                ':status'               => $status,
                ':is_featured'          => $isFeatured,
                ':is_published'         => $isPublished,
                ':display_order'        => $displayOrder,
                ':id'                   => $id
            ]);

            return ['success' => true];
        } catch (Exception $e) {
            error_log("Update battle error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * Delete a Contest Battle
     */
    public static function deleteBattle(int $id): bool {
        self::ensureTable();
        $pdo = DB::pdo();
        try {
            $stmt = $pdo->prepare("DELETE FROM contest_battles WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            error_log("Delete battle error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Toggle Publish status
     */
    public static function togglePublish(int $id): bool {
        self::ensureTable();
        $pdo = DB::pdo();
        try {
            $stmt = $pdo->prepare("UPDATE contest_battles SET is_published = NOT is_published WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            error_log("Toggle battle publish error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Toggle Featured status
     */
    public static function toggleFeatured(int $id): bool {
        self::ensureTable();
        $pdo = DB::pdo();
        try {
            $stmt = $pdo->prepare("UPDATE contest_battles SET is_featured = NOT is_featured WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            error_log("Toggle battle featured error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reorder battles
     */
    public static function updateDisplayOrder(int $id, int $order): bool {
        self::ensureTable();
        $pdo = DB::pdo();
        try {
            $stmt = $pdo->prepare("UPDATE contest_battles SET display_order = ? WHERE id = ?");
            return $stmt->execute([$order, $id]);
        } catch (Exception $e) {
            error_log("Update battle order error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Helper: Format status badge HTML
     */
    public static function getStatusBadgeHtml(string $status): string {
        switch ($status) {
            case 'live':
                return '<span class="badge battle-badge-live"><span class="live-dot"></span> LIVE NOW</span>';
            case 'upcoming':
                return '<span class="badge battle-badge-upcoming"><i class="fas fa-bolt me-1"></i> UPCOMING</span>';
            case 'ended':
                return '<span class="badge battle-badge-ended"><i class="fas fa-flag-checkered me-1"></i> CONCLUDED</span>';
            case 'cancelled':
                return '<span class="badge battle-badge-cancelled"><i class="fas fa-ban me-1"></i> CANCELLED</span>';
            default:
                return '<span class="badge bg-secondary">' . htmlspecialchars(ucfirst($status)) . '</span>';
        }
    }

    /**
     * Helper: Format platform icon HTML
     */
    public static function getPlatformIconHtml(?string $platform): string {
        $p = strtolower((string)$platform);
        if (strpos($p, 'tiktok') !== false) {
            return '<i class="fab fa-tiktok text-danger me-1"></i>';
        } elseif (strpos($p, 'instagram') !== false) {
            return '<i class="fab fa-instagram text-danger me-1"></i>';
        } elseif (strpos($p, 'youtube') !== false) {
            return '<i class="fab fa-youtube text-danger me-1"></i>';
        } elseif (strpos($p, 'facebook') !== false) {
            return '<i class="fab fa-facebook text-primary me-1"></i>';
        } elseif (strpos($p, 'x') !== false || strpos($p, 'twitter') !== false) {
            return '<i class="fab fa-x-twitter text-light me-1"></i>';
        } elseif (strpos($p, 'zoom') !== false) {
            return '<i class="fas fa-video text-info me-1"></i>';
        }
        return '<i class="fas fa-broadcast-tower text-warning me-1"></i>';
    }

    /**
     * Helper: Format battle date into human readable string
     */
    public static function formatBattleDateTime(string $date, string $time): string {
        try {
            $dt = new DateTime($date);
            $formattedDate = $dt->format('D, M j, Y');
            return $formattedDate . (!empty($time) ? ' • ' . $time : '');
        } catch (Exception $e) {
            return $date . ' ' . $time;
        }
    }
}


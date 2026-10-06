<?php
/**
 * System Settings and Business Rules Configuration Service
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Env.php';

class Settings {
    private static array $cache = [];
    private static bool $loaded = false;

    /**
     * Pre-load all settings from the database into memory
     */
    private static function loadAll(): void {
        if (self::$loaded) {
            return;
        }

        try {
            $pdo = DB::pdo();
            $stmt = $pdo->query("SELECT name, value FROM settings");
            while ($row = $stmt->fetch()) {
                self::$cache[$row['name']] = $row['value'];
            }
            self::$loaded = true;
        } catch (Exception $e) {
            error_log("Settings load error: " . $e->getMessage());
        }
    }

    /**
     * Get setting value by name with fallback
     */
    public static function get(string $name, $default = null) {
        self::loadAll();

        if (array_key_exists($name, self::$cache)) {
            return self::$cache[$name];
        }

        // Check environment variable fallback
        $envKey = strtoupper($name);
        $envVal = Env::get($envKey);
        if ($envVal !== null) {
            return $envVal;
        }

        return $default;
    }

    /**
     * Set or update a setting value
     */
    public static function set(string $name, string $value): bool {
        try {
            $pdo = DB::pdo();
            $stmt = $pdo->prepare("
                INSERT INTO settings (name, value) 
                VALUES (:name, :value) 
                ON DUPLICATE KEY UPDATE value = :value_update
            ");
            $result = $stmt->execute([
                ':name'         => $name,
                ':value'        => $value,
                ':value_update' => $value
            ]);

            self::$cache[$name] = $value;
            return $result;
        } catch (Exception $e) {
            error_log("Settings set error: " . $e->getMessage());
            return false;
        }
    }

    public static function isRegistrationOpen(): bool {
        $val = self::get('registration_open', '1');
        return $val === '1' || $val === 'true' || $val === 1 || $val === true;
    }

    public static function isVotingOpen(): bool {
        $val = self::get('voting_open', '1');
        $endTime = self::getCompetitionEndTime();

        if ($endTime) {
            $endTimestamp = strtotime($endTime);
            if ($endTimestamp !== false && time() > $endTimestamp) {
                return false;
            }
        }

        return $val === '1' || $val === 'true' || $val === 1 || $val === true;
    }

    public static function getCurrentStage(): string {
        // First check settings table, then current_stage table
        $stage = self::get('current_stage');
        if (!empty($stage)) {
            return $stage;
        }

        try {
            $pdo = DB::pdo();
            $stmt = $pdo->query("SELECT stage_name FROM current_stage WHERE id = 1 LIMIT 1");
            $row = $stmt->fetch();
            return $row['stage_name'] ?? 'Stage One';
        } catch (Exception $e) {
            return 'Stage One';
        }
    }

    public static function setCurrentStage(string $stageName): bool {
        self::set('current_stage', $stageName);
        try {
            $pdo = DB::pdo();
            $stmt = $pdo->prepare("UPDATE current_stage SET stage_name = ? WHERE id = 1");
            return $stmt->execute([$stageName]);
        } catch (Exception $e) {
            return true;
        }
    }

    public static function getCompetitionEndTime(): string {
        return (string)self::get('competition_end_time', '2026-12-31T23:59');
    }

    public static function setCompetitionEndTime(string $endTime): bool {
        return self::set('competition_end_time', $endTime);
    }

    public static function getVotePrice(): float {
        return (float)self::get('vote_price', Env::get('VOTE_PRICE_NGN', 50));
    }

    public static function getCurrencySymbol(): string {
        return (string)self::get('currency_symbol', Env::get('VOTE_CURRENCY_SYMBOL', '₦'));
    }

    public static function getCurrencyCode(): string {
        return (string)self::get('currency_code', Env::get('VOTE_CURRENCY', 'NGN'));
    }
}

// Global functions for backward compatibility
function isRegistrationOpen(): bool {
    return Settings::isRegistrationOpen();
}

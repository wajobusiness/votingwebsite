<?php
/**
 * Database Connection Management (PDO & MySQLi Bridge)
 */

require_once __DIR__ . '/Env.php';

class DB {
    private static ?PDO $pdo = null;
    private static ?mysqli $mysqli = null;

    public static function pdo(): PDO {
        if (self::$pdo === null) {
            $host = Env::get('DB_HOST', 'localhost');
            $port = Env::get('DB_PORT', 3306);
            $dbname = Env::get('DB_NAME', 'theusers_contest6532');
            $user = Env::get('DB_USER', 'theusers_contest6532');
            $pass = Env::get('DB_PASS', '@Contest1234');
            $charset = Env::get('DB_CHARSET', 'utf8mb4');

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$charset}_unicode_ci",
            ];

            try {
                self::$pdo = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                error_log("Database Connection Error (PDO): " . $e->getMessage());
                if (Env::get('APP_DEBUG', false)) {
                    die("Database Connection Error: " . $e->getMessage());
                } else {
                    die("Database connection failed. Please check system configuration.");
                }
            }
        }

        return self::$pdo;
    }

    public static function mysqli(): mysqli {
        if (self::$mysqli === null) {
            $host = Env::get('DB_HOST', 'localhost');
            $user = Env::get('DB_USER', 'theusers_contest6532');
            $pass = Env::get('DB_PASS', '@Contest1234');
            $dbname = Env::get('DB_NAME', 'theusers_contest6532');
            $port = (int)Env::get('DB_PORT', 3306);

            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            try {
                self::$mysqli = new mysqli($host, $user, $pass, $dbname, $port);
                self::$mysqli->set_charset(Env::get('DB_CHARSET', 'utf8mb4'));
            } catch (Exception $e) {
                error_log("Database Connection Error (MySQLi): " . $e->getMessage());
                if (Env::get('APP_DEBUG', false)) {
                    die("MySQLi Connection Error: " . $e->getMessage());
                } else {
                    die("Database connection failed. Please contact support.");
                }
            }
        }

        return self::$mysqli;
    }
}

// Global legacy connector variable for backward compatibility
$conn = DB::mysqli();
$pdo = DB::pdo();

<?php
/**
 * Authentication & Access Control Service
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

class Auth {

    /**
     * Authenticate contestant by username or email
     */
    public static function attemptUserLogin(string $login, string $password): array {
        Security::startSession();

        $pdo = DB::pdo();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (email = :login OR username = :login) AND is_admin = 0 LIMIT 1");
        $stmt->execute([':login' => trim($login)]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_full_name'] = $user['full_name'];

            return ['success' => true, 'user' => $user];
        }

        return ['success' => false, 'error' => 'Invalid username/email or password.'];
    }

    /**
     * Authenticate administrator
     */
    public static function attemptAdminLogin(string $login, string $password): array {
        Security::startSession();

        $pdo = DB::pdo();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (email = :login OR username = :login) AND is_admin = 1 LIMIT 1");
        $stmt->execute([':login' => trim($login)]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_email'] = $admin['email'];

            return ['success' => true, 'admin' => $admin];
        }

        return ['success' => false, 'error' => 'Invalid admin credentials.'];
    }

    public static function isUserLoggedIn(): bool {
        Security::startSession();
        return !empty($_SESSION['user_id']);
    }

    public static function isAdminLoggedIn(): bool {
        Security::startSession();
        return !empty($_SESSION['admin_id']);
    }

    public static function requireUser(): void {
        if (!self::isUserLoggedIn()) {
            header('Location: login.php');
            exit();
        }
    }

    public static function requireAdmin(): void {
        if (!self::isAdminLoggedIn()) {
            header('Location: adminlogin.php');
            exit();
        }
    }

    public static function getCurrentUser(): ?array {
        if (!self::isUserLoggedIn()) {
            return null;
        }

        $pdo = DB::pdo();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_admin = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function getCurrentAdmin(): ?array {
        if (!self::isAdminLoggedIn()) {
            return null;
        }

        $pdo = DB::pdo();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_admin = 1");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch();

        return $admin ?: null;
    }

    public static function logoutUser(): void {
        Security::startSession();
        unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['user_email'], $_SESSION['user_full_name']);
        header('Location: login.php');
        exit();
    }

    public static function logoutAdmin(): void {
        Security::startSession();
        unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_email']);
        header('Location: adminlogin.php');
        exit();
    }
}

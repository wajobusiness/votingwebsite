<?php
/**
 * Administrator Account Provisioning Tool
 * CLI & Web-based utility to create or reset admin credentials safely
 */

require_once __DIR__ . '/config.php';

$message = '';
$error = '';
$isCli = (php_sapi_name() === 'cli');

// 1. Handle CLI Invocation (php create_admin.php [username] [email] [password])
if ($isCli) {
    global $argv;
    $username = $argv[1] ?? 'admin';
    $email    = $argv[2] ?? 'admin@crownnightstar.com';
    $password = $argv[3] ?? 'Admin@2026!';
    $fullName = 'System Administrator';

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo = DB::pdo();

    try {
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, full_name, phone_number, is_admin, is_active)
            VALUES (:username, :email, :password, :full_name, '08000000000', 1, 1)
            ON DUPLICATE KEY UPDATE 
                password = VALUES(password), 
                is_admin = 1, 
                is_active = 1, 
                full_name = VALUES(full_name)
        ");

        $stmt->execute([
            ':username'  => $username,
            ':email'     => $email,
            ':password'  => $hash,
            ':full_name' => $fullName
        ]);

        echo "\n========================================\n";
        echo "  ADMIN ACCOUNT CREATED / UPDATED\n";
        echo "========================================\n";
        echo "  Username : {$username}\n";
        echo "  Email    : {$email}\n";
        echo "  Password : {$password}\n";
        echo "  Login URL: /adminlogin.php\n";
        echo "========================================\n\n";
        exit(0);
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// 2. Handle Web POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $fullName = trim($_POST['full_name'] ?? 'System Administrator');

    if (empty($username) || empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo = DB::pdo();

        try {
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, full_name, phone_number, is_admin, is_active)
                VALUES (:username, :email, :password, :full_name, '08000000000', 1, 1)
                ON DUPLICATE KEY UPDATE 
                    password = VALUES(password), 
                    is_admin = 1, 
                    is_active = 1, 
                    full_name = VALUES(full_name)
            ");

            $stmt->execute([
                ':username'  => $username,
                ':email'     => $email,
                ':password'  => $hash,
                ':full_name' => $fullName
            ]);

            $message = "Admin account for '{$username}' created/updated successfully! You can now log in.";
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Admin Account - Crown Night Star</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets2/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets2/css/all.min.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #090714 0%, #130f26 50%, #0d0a1b 100%);
            color: #e2e8f0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card-custom {
            background: rgba(22, 17, 44, 0.9);
            border: 1px solid rgba(255, 215, 0, 0.3);
            border-radius: 20px;
            padding: 35px 30px;
            max-width: 480px;
            width: 100%;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.6);
        }
        .form-control {
            background: #110d24;
            border: 1px solid #362e54;
            color: #fff;
        }
        .form-control:focus {
            background: #110d24;
            border-color: #ffd700;
            color: #fff;
            box-shadow: 0 0 0 0.25rem rgba(255, 215, 0, 0.2);
        }
        .btn-gold {
            background: linear-gradient(135deg, #ffd700 0%, #d4af37 100%);
            color: #0d1117;
            font-weight: 700;
            border: none;
            padding: 12px;
            border-radius: 8px;
        }
        .btn-gold:hover {
            background: linear-gradient(135deg, #ffe033 0%, #e5bd3b 100%);
            color: #0d1117;
        }
    </style>
</head>
<body>

<div class="card-custom">
    <div class="text-center mb-4">
        <span class="badge bg-warning text-dark px-3 py-2 fw-bold text-uppercase mb-2"><i class="fas fa-key me-1"></i> Admin Provisioning</span>
        <h3 class="fw-bold text-white mb-1">Create Admin User</h3>
        <p class="text-secondary small">Set up administrative login credentials</p>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success border-0 small py-2 px-3 mb-3" style="background: rgba(40, 167, 69, 0.2); color: #2ecc71;">
            <i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($message) ?>
            <div class="mt-2">
                <a href="adminlogin.php" class="btn btn-warning btn-sm fw-bold">Go to Admin Login &rarr;</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger border-0 small py-2 px-3 mb-3" style="background: rgba(220, 53, 69, 0.2); color: #ff6b7d;">
            <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label text-light small fw-semibold">Full Name</label>
            <input type="text" name="full_name" class="form-control" value="Administrator" required>
        </div>

        <div class="mb-3">
            <label class="form-label text-light small fw-semibold">Admin Username</label>
            <input type="text" name="username" class="form-control" placeholder="e.g. admin" required>
        </div>

        <div class="mb-3">
            <label class="form-label text-light small fw-semibold">Admin Email</label>
            <input type="email" name="email" class="form-control" placeholder="admin@example.com" required>
        </div>

        <div class="mb-4">
            <label class="form-label text-light small fw-semibold">Password</label>
            <input type="password" name="password" class="form-control" placeholder="Create a strong password" required>
        </div>

        <button type="submit" class="btn btn-gold w-100 mb-3">
            <i class="fas fa-shield-alt me-1"></i> Create / Update Admin Account
        </button>

        <div class="text-center">
            <a href="adminlogin.php" class="text-secondary small text-decoration-none">Return to Admin Login</a>
        </div>
    </form>
</div>

</body>
</html>

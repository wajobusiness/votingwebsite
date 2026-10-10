<?php
require_once __DIR__ . '/config.php';

// Redirect if already logged in as contestant
if (Auth::isUserLoggedIn()) {
    $curr = Auth::getCurrentUser();
    if ($curr && !RegistrationService::isUserRegistrationComplete($curr)) {
        header('Location: complete_registration.php');
    } else {
        header('Location: dashboard.php');
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrf()) {
        $error = 'Security session expired. Please try signing in again.';
    } else {
        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($login) || empty($password)) {
            $error = 'Please enter both username/email and password.';
        } else {
            $authResult = Auth::attemptUserLogin($login, $password);
            if ($authResult['success']) {
                $user = $authResult['user'];
                if (!RegistrationService::isUserRegistrationComplete($user)) {
                    header('Location: complete_registration.php');
                } else {
                    header('Location: dashboard.php');
                }
                exit();
            } else {
                $error = $authResult['error'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Contestant Sign In - <?= e(Settings::get('site_title', 'Voting Platform')) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets2/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets2/css/all.min.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #0f0c20 0%, #1a162b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .login-card {
            background: rgba(30, 26, 50, 0.85);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 215, 0, 0.2);
            border-radius: 20px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.6);
            width: 100%;
            max-width: 440px;
            padding: 35px 30px;
        }
        .brand-badge {
            display: inline-block;
            background: rgba(255, 215, 0, 0.15);
            color: #ffd700;
            border: 1px solid rgba(255, 215, 0, 0.3);
            border-radius: 50px;
            padding: 4px 14px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }
        .form-control {
            background-color: #151124;
            border: 1px solid #3d3559;
            color: #e0daf5;
            padding: 12px 14px;
            border-radius: 8px;
        }
        .form-control:focus {
            background-color: #151124;
            border-color: #ffd700;
            box-shadow: 0 0 0 0.2rem rgba(255, 215, 0, 0.2);
            color: #fff;
        }
        .btn-gold {
            background: linear-gradient(135deg, #ffd700 0%, #d4af37 100%);
            color: #0d1117;
            font-weight: 700;
            padding: 12px;
            border-radius: 8px;
            border: none;
            transition: all 0.3s ease;
        }
        .btn-gold:hover {
            background: linear-gradient(135deg, #ffe033 0%, #e5bd3b 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(255, 215, 0, 0.3);
            color: #0d1117;
        }
    </style>
</head>
<body>

<div class="login-card text-center">
    <div class="brand-badge"><i class="fas fa-crown me-1"></i> Contestant Portal</div>
    <h3 class="fw-bold text-white mb-1">Welcome Back</h3>
    <p class="text-secondary mb-4 small">Sign in to track your votes & manage your profile</p>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger text-start py-2 px-3 small border-0" style="background-color: rgba(220, 53, 69, 0.2); color: #ff6b7d;">
            <i class="fas fa-exclamation-circle me-1"></i> <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php" class="text-start">
        <?= Security::csrfField() ?>

        <div class="mb-3">
            <label class="form-label text-light small fw-semibold" for="login">Username or Email</label>
            <div class="input-group">
                <span class="input-group-text bg-dark border-secondary text-secondary"><i class="fas fa-user"></i></span>
                <input type="text" id="login" name="login" required class="form-control" placeholder="Enter username or email" autofocus value="<?= e($_POST['login'] ?? '') ?>">
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label text-light small fw-semibold" for="password">Password</label>
            <div class="input-group">
                <span class="input-group-text bg-dark border-secondary text-secondary"><i class="fas fa-lock"></i></span>
                <input type="password" id="password" name="password" required class="form-control" placeholder="Enter your password">
            </div>
        </div>

        <div class="d-grid mb-3">
            <button class="btn btn-gold" type="submit"><i class="fas fa-sign-in-alt me-2"></i> Sign In to My Dashboard</button>
        </div>

        <p class="text-center text-secondary small mb-3">
            Don't have an account? <a href="register.php" class="text-warning text-decoration-none fw-bold">Register Here</a>
        </p>

        <div class="text-center">
            <a href="index.php" class="text-secondary text-decoration-none small"><i class="fas fa-arrow-left me-1"></i> Back to Homepage</a>
        </div>
    </form>
</div>

</body>
</html>
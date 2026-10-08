<?php
require_once __DIR__ . '/config.php';

$siteTitle = Settings::get('site_title', 'Crown Night Star');
$supportEmail = Settings::get('support_email', 'hello@theusersportal.cloud');
$supportPhone = Settings::get('support_phone', '08139188570');
$isRegistrationOpen = Settings::isRegistrationOpen();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - <?= e($siteTitle) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets2/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets2/css/all.min.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #090714 0%, #130f26 50%, #0d0a1b 100%);
            color: #e2e8f0;
            min-height: 100vh;
        }
        .navbar-custom {
            background: rgba(14, 11, 30, 0.9);
            backdrop-filter: blur(14px);
            border-bottom: 1px solid rgba(255, 215, 0, 0.2);
        }
        .content-card {
            background: rgba(22, 17, 44, 0.85);
            border: 1px solid rgba(255, 215, 0, 0.2);
            border-radius: 20px;
            padding: 40px 30px;
            margin: 40px auto;
            max-width: 760px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.5);
        }
        .contact-box {
            background: rgba(15, 12, 32, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        footer {
            background: #070510;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding: 40px 0 20px;
            margin-top: 60px;
        }
    </style>
</head>
<body>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom sticky-top py-3">
    <div class="container">
        <a class="navbar-brand fw-bold text-white d-flex align-items-center gap-2 fs-4" href="index.php">
            <i class="fas fa-crown text-warning"></i> <?= e($siteTitle) ?>
        </a>
        <div class="d-flex align-items-center gap-2">
            <a href="index.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i> Home</a>
            <a href="bookstore.php" class="btn btn-outline-warning btn-sm"><i class="fas fa-book-open me-1"></i> Bookstore</a>
            <?php if ($isRegistrationOpen): ?>
                <a href="register.php" class="btn btn-warning btn-sm fw-bold">Register</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="container">
    <div class="content-card">
        <div class="text-center mb-4">
            <span class="badge bg-warning text-dark fw-bold px-3 py-2 text-uppercase mb-2">Get in Touch</span>
            <h1 class="fw-bold text-white">Contact Us</h1>
            <p class="text-secondary">Have questions or need assistance with your voting registration?</p>
        </div>

        <div class="contact-box">
            <div class="p-3 rounded-circle" style="background: rgba(255, 215, 0, 0.15); color: #ffd700;">
                <i class="fas fa-envelope fa-2x"></i>
            </div>
            <div>
                <h6 class="text-secondary text-uppercase fw-bold small mb-1">Email Support</h6>
                <a href="mailto:<?= e($supportEmail) ?>" class="text-white fw-bold fs-5 text-decoration-none">
                    <?= e($supportEmail) ?>
                </a>
            </div>
        </div>

        <div class="contact-box">
            <div class="p-3 rounded-circle" style="background: rgba(40, 167, 69, 0.15); color: #2ecc71;">
                <i class="fas fa-phone-alt fa-2x"></i>
            </div>
            <div>
                <h6 class="text-secondary text-uppercase fw-bold small mb-1">Direct Phone & WhatsApp</h6>
                <a href="tel:<?= e($supportPhone) ?>" class="text-white fw-bold fs-5 text-decoration-none">
                    <?= e($supportPhone) ?>
                </a>
            </div>
        </div>

        <div class="text-center mt-4 pt-2">
            <a href="https://api.whatsapp.com/send?phone=<?= preg_replace('/[^0-9]/', '', $supportPhone) ?>&text=<?= urlencode("Hello, I need assistance with " . $siteTitle) ?>" target="_blank" class="btn btn-success fw-bold px-4 py-2">
                <i class="fab fa-whatsapp me-2"></i> Chat with Us on WhatsApp
            </a>
        </div>
    </div>
</div>

<footer>
    <div class="container text-center text-secondary small">
        &copy; <?= date('Y') ?> <strong><?= e($siteTitle) ?></strong>. All Rights Reserved.
    </div>
</footer>

</body>
</html>
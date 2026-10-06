<?php
require_once __DIR__ . '/config.php';

$siteTitle = Settings::get('site_title', 'Most Beautiful Discovery');
$isRegistrationOpen = Settings::isRegistrationOpen();
$stageName = Settings::getCurrentStage();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - <?= e($siteTitle) ?></title>
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
            max-width: 860px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.5);
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
        <div class="d-flex align-items-center gap-3">
            <a href="index.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i> Home</a>
            <?php if ($isRegistrationOpen): ?>
                <a href="register.php" class="btn btn-warning btn-sm fw-bold">Register</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="container">
    <div class="content-card">
        <div class="text-center mb-4">
            <span class="badge bg-warning text-dark fw-bold px-3 py-2 text-uppercase mb-2">Our Mission</span>
            <h1 class="fw-bold text-white">About <?= e($siteTitle) ?></h1>
            <p class="text-secondary">Empowering voices, celebrating beauty, and inspiring future leaders</p>
        </div>

        <div class="text-light opacity-90 lh-lg">
            <p class="fs-5">
                <strong><?= e($siteTitle) ?></strong> is a premier online contest platform dedicated to discovering, launching, and celebrating excellence and creativity.
            </p>

            <h4 class="text-warning fw-bold mt-4 mb-2"><i class="fas fa-bullseye me-2"></i> Vision & Purpose</h4>
            <p>
                We believe in creating opportunities and providing an equal platform for participants to showcase their charm, intellect, and leadership. Through an authentic, community-driven voting model, we give public supporters direct power to determine the winners.
            </p>

            <h4 class="text-warning fw-bold mt-4 mb-2"><i class="fas fa-shield-alt me-2"></i> Fair & Transparent Voting</h4>
            <p>
                Every single vote cast through our platform is backed by direct, verified online transactions. We employ modern fraud protection, anti-bot filtering, and verified payment gateways to ensure that every participant is judged with absolute transparency and integrity.
            </p>

            <div class="text-center mt-5">
                <a href="index.php#contestants" class="btn btn-warning fw-bold px-4 py-2 me-2">
                    <i class="fas fa-vote-yea me-1"></i> View & Vote Contestants
                </a>
                <a href="contact-us.php" class="btn btn-outline-light px-4 py-2">
                    <i class="fas fa-envelope me-1"></i> Contact Support
                </a>
            </div>
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
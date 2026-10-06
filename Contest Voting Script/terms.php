<?php
require_once __DIR__ . '/config.php';

$siteTitle = Settings::get('site_title', 'Most Beautiful Discovery');
$votePrice = Settings::getVotePrice();
$currencySymbol = Settings::getCurrencySymbol();
$isRegistrationOpen = Settings::isRegistrationOpen();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms & Conditions - <?= e($siteTitle) ?></title>
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
            <span class="badge bg-warning text-dark fw-bold px-3 py-2 text-uppercase mb-2">Competition Guidelines</span>
            <h1 class="fw-bold text-white">Terms & Conditions</h1>
            <p class="text-secondary">Official rules and guidelines governing participation and voting</p>
        </div>

        <div class="text-light opacity-90 lh-lg">
            <h5 class="text-warning fw-bold mt-4"><i class="fas fa-gavel me-2"></i> 1. Eligibility & Registration</h5>
            <p class="small">
                Participation in <?= e($siteTitle) ?> is open to all qualified applicants. Contestants must provide accurate personal information and genuine profile photographs during registration. False representation or uploading copyrighted/inappropriate imagery will result in immediate disqualification without notice.
            </p>

            <h5 class="text-warning fw-bold mt-4"><i class="fas fa-vote-yea me-2"></i> 2. Voting Process & Pricing</h5>
            <p class="small">
                All votes are cast through our secure online payment processor (Paystack) at the rate of <strong><?= $currencySymbol ?><?= number_format($votePrice, 0) ?> per vote</strong>. Votes are credited immediately upon successful gateway authorization. All payments are final and non-refundable once processed.
            </p>

            <h5 class="text-warning fw-bold mt-4"><i class="fas fa-shield-alt me-2"></i> 3. Anti-Fraud & Fair Play</h5>
            <p class="small">
                The use of automated bots, chargeback fraud, or manipulative scripts to inflate vote counts is strictly prohibited. Our monitoring systems log all transaction references, IP addresses, and gateway signatures. Suspicious activity will lead to a freeze of the contestant profile pending manual audit.
            </p>

            <h5 class="text-warning fw-bold mt-4"><i class="fas fa-trophy me-2"></i> 4. Stages & Winner Determination</h5>
            <p class="small">
                The competition runs in sequential stages. Contestants must achieve the required vote thresholds and rank requirements to qualify for subsequent rounds. In the final stage, the contestant with the highest cumulative votes at the closing deadline will be declared the official winner.
            </p>

            <div class="text-center mt-5">
                <a href="index.php" class="btn btn-warning fw-bold px-4 py-2">
                    <i class="fas fa-check-circle me-1"></i> I Understand & Agree
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
<?php
/**
 * Digital Book Order Success & Access Hub
 * Delivers digital publications, course links, and WhatsApp VIP onboarding
 */

require_once __DIR__ . '/config.php';

$token = trim($_GET['token'] ?? '');
$purchase = null;

if (!empty($token)) {
    $purchase = BookstoreService::getPurchaseByToken($token);
}

$siteTitle = Settings::get('site_title', 'Crown Night Star');
$currency = Settings::getCurrencySymbol();
$isLoggedIn = Auth::isUserLoggedIn();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed & Digital Access - <?= e($siteTitle) ?></title>
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
        .success-card {
            background: rgba(22, 17, 44, 0.9);
            border: 1px solid rgba(255, 215, 0, 0.25);
            border-radius: 24px;
            padding: 40px 30px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
            margin: 40px auto;
            max-width: 780px;
        }
        .check-badge {
            width: 76px;
            height: 76px;
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin: 0 auto 20px;
            box-shadow: 0 10px 25px rgba(46, 204, 113, 0.4);
        }
        .book-preview-box {
            background: rgba(13, 10, 28, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 20px;
        }
        .delivery-cta-box {
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.1) 0%, rgba(212, 175, 55, 0.05) 100%);
            border: 1px solid rgba(255, 215, 0, 0.3);
            border-radius: 20px;
            padding: 28px;
            margin-top: 30px;
        }
        .btn-gold {
            background: linear-gradient(135deg, #ffd700 0%, #d4af37 100%);
            color: #0d1117;
            font-weight: 800;
            border-radius: 12px;
            border: none;
            padding: 14px 28px;
            font-size: 16px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-gold:hover {
            background: linear-gradient(135deg, #ffe033 0%, #e5bd3b 100%);
            color: #0d1117;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 215, 0, 0.3);
        }
        .access-link-box {
            background: #090714;
            border: 1px dashed rgba(255, 215, 0, 0.4);
            border-radius: 12px;
            padding: 12px 16px;
            font-family: monospace;
            font-size: 13px;
            color: #ffd700;
            word-break: break-all;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom sticky-top py-3">
    <div class="container">
        <a class="navbar-brand fw-bold text-white d-flex align-items-center gap-2 fs-4" href="index.php">
            <i class="fas fa-crown text-warning"></i> <?= e($siteTitle) ?>
        </a>
        <div class="d-flex align-items-center gap-2">
            <a href="bookstore.php" class="btn btn-outline-warning btn-sm"><i class="fas fa-book-open me-1"></i> Bookstore</a>
            <?php if ($isLoggedIn): ?>
                <a href="dashboard.php" class="btn btn-warning btn-sm fw-bold"><i class="fas fa-user-circle me-1"></i> Dashboard</a>
            <?php else: ?>
                <a href="index.php" class="btn btn-outline-light btn-sm"><i class="fas fa-home me-1"></i> Home</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="container px-3 py-4">

    <?php if (!$purchase): ?>
        <div class="success-card text-center py-5">
            <div class="text-warning mb-3"><i class="fas fa-exclamation-triangle fa-3x"></i></div>
            <h3 class="fw-bold text-white mb-2">Order Not Found or Invalid Token</h3>
            <p class="text-secondary mb-4">We could not locate the specified digital book order. Please check the access link sent to your email or return to the bookstore.</p>
            <a href="bookstore.php" class="btn btn-gold"><i class="fas fa-book-open me-2"></i> Return to Bookstore</a>
        </div>
    <?php else: ?>
        <?php
            $deliveryType = $purchase['delivery_type'] ?? 'whatsapp';
            $coverPath = $purchase['cover_image'] ?? 'assets2/images/book1.jpg';
            if (!file_exists(__DIR__ . '/' . $coverPath) && file_exists(__DIR__ . '/assets2/images/book1.jpg')) {
                $coverPath = 'assets2/images/book1.jpg';
            }
            $downloadUrl = 'download_book.php?token=' . urlencode($purchase['access_token']);
            $currentAccessUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        ?>

        <div class="success-card text-center">
            <div class="check-badge">
                <i class="fas fa-check"></i>
            </div>
            <span class="badge bg-success px-3 py-2 fw-bold text-uppercase mb-2">Payment Verified</span>
            <h2 class="fw-bold text-white mb-2">Thank You for Your Order!</h2>
            <p class="text-secondary mb-4">Your payment of <strong class="text-warning"><?= $currency . number_format((float)$purchase['amount'], 2) ?></strong> was processed successfully. Your digital publication is ready for instant access below.</p>

            <!-- Book & Order Details Summary -->
            <div class="book-preview-box text-start mb-4">
                <div class="row align-items-center g-3">
                    <div class="col-auto">
                        <img src="<?= e($coverPath) ?>" alt="<?= e($purchase['book_title']) ?>" class="rounded-3 shadow border border-secondary" style="width: 75px; height: 105px; object-fit: cover;">
                    </div>
                    <div class="col">
                        <div class="badge bg-warning text-dark mb-1 small"><?= e($purchase['book_category'] ?? 'Digital Book') ?></div>
                        <h5 class="fw-bold text-white mb-1"><?= e($purchase['book_title']) ?></h5>
                        <div class="text-secondary small mb-2"><i class="fas fa-user-edit me-1"></i> Author: <?= e($purchase['book_author']) ?></div>
                        <div class="d-flex flex-wrap gap-3 small text-secondary">
                            <span><strong>Buyer:</strong> <?= e($purchase['buyer_name']) ?></span>
                            <span><strong>Email:</strong> <?= e($purchase['buyer_email']) ?></span>
                            <span><strong>Ref:</strong> <code class="text-warning"><?= e($purchase['reference']) ?></code></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dynamic Fulfillment Action -->
            <div class="delivery-cta-box">
                <?php if ($deliveryType === 'pdf'): ?>
                    <div class="mb-3">
                        <i class="fas fa-file-pdf fa-3x text-danger mb-2"></i>
                        <h4 class="fw-bold text-white">Your Digital PDF is Ready</h4>
                        <p class="text-secondary small mb-3">Click below to download your full digital copy to your phone, tablet, or computer.</p>
                        <a href="<?= e($downloadUrl) ?>" class="btn btn-gold btn-lg">
                            <i class="fas fa-download me-2"></i> Download Digital Book (PDF)
                        </a>
                    </div>
                    <div class="small text-secondary mt-2">
                        <i class="fas fa-shield-alt text-warning me-1"></i> Download limit: Unlimited &bull; Total downloads: <?= (int)$purchase['download_count'] ?>
                    </div>

                <?php elseif ($deliveryType === 'link' && !empty($purchase['download_link'])): ?>
                    <div class="mb-3">
                        <i class="fas fa-graduation-cap fa-3x text-warning mb-2"></i>
                        <h4 class="fw-bold text-white">Access Your Course & Masterclass</h4>
                        <p class="text-secondary small mb-3">Your private masterclass materials and portal have been unlocked.</p>
                        <a href="<?= e($purchase['download_link']) ?>" target="_blank" class="btn btn-gold btn-lg">
                            <i class="fas fa-external-link-alt me-2"></i> Enter Masterclass Portal
                        </a>
                    </div>
                    <div class="small text-secondary mt-2">
                        <i class="fas fa-info-circle text-info me-1"></i> Please bookmark your course access link or keep this page saved.
                    </div>

                <?php else: ?>
                    <!-- WhatsApp Delivery & VIP Concierge -->
                    <?php $waUrl = BookstoreService::getWhatsAppAccessUrl($purchase, $purchase); ?>
                    <div class="mb-3">
                        <i class="fab fa-whatsapp fa-3x text-success mb-2"></i>
                        <h4 class="fw-bold text-white">Instant VIP WhatsApp Delivery</h4>
                        <p class="text-secondary small mb-3">Click below to connect with our official support team on WhatsApp. Your payment reference and order details are already prefilled for immediate delivery.</p>
                        <a href="<?= e($waUrl) ?>" target="_blank" class="btn btn-success btn-lg fw-bold px-4 py-3" style="border-radius: 12px;">
                            <i class="fab fa-whatsapp me-2 fs-5"></i> Connect on WhatsApp for Instant Access
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Permanent Bookmark Link -->
            <div class="mt-4 pt-3 text-start">
                <label class="small text-secondary fw-semibold mb-1"><i class="fas fa-bookmark text-warning me-1"></i> Your Permanent Lifetime Access URL:</label>
                <div class="access-link-box d-flex justify-content-between align-items-center gap-2">
                    <span id="accessUrlText"><?= e($currentAccessUrl) ?></span>
                    <button class="btn btn-sm btn-outline-warning py-1 px-2" onclick="copyAccessUrl()">
                        <i class="fas fa-copy me-1"></i> Copy
                    </button>
                </div>
                <div class="small text-secondary mt-2" style="font-size: 11px;">
                    A copy of your access token has been sent to <strong><?= e($purchase['buyer_email']) ?></strong>.
                    <?php if ($isLoggedIn): ?>
                        You can also view this book at any time inside your <a href="dashboard.php" class="text-warning text-decoration-none fw-bold">Contestant Dashboard</a>.
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex justify-content-center gap-3 mt-4 pt-3 border-top border-secondary">
                <a href="bookstore.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i> Back to Bookstore</a>
                <?php if ($isLoggedIn): ?>
                    <a href="dashboard.php" class="btn btn-warning btn-sm fw-bold"><i class="fas fa-user-circle me-1"></i> Go to Dashboard</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<script>
function copyAccessUrl() {
    const text = document.getElementById('accessUrlText').innerText;
    navigator.clipboard.writeText(text).then(() => {
        alert('Permanent access link copied to your clipboard!');
    }).catch(() => {
        alert('Please select and copy the link manually.');
    });
}
</script>

</body>
</html>

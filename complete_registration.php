<?php
/**
 * Complete Contestant Registration & Payment Checkpoint Page
 * Mandatory gate preventing unverified/unpaid contestants from accessing dashboard or leaderboard
 */

require_once __DIR__ . '/config.php';

Auth::requireUser();

$user = Auth::getCurrentUser();
if (!$user) {
    Auth::logoutUser();
}

$userId = (int)$user['id'];
$regFee = RegistrationService::getRegistrationFee();
$currency = Settings::getCurrencySymbol();
$currencyCode = Settings::getCurrencyCode();
$paystackPublicKey = Env::get('PAYSTACK_PUBLIC_KEY');
$siteTitle = Settings::get('site_title', 'Voting Platform');

// If fee is 0, auto-mark as exempt and redirect to dashboard
if ($regFee <= 0) {
    RegistrationService::manuallyUpdateStatus($userId, 'exempt', 0.0);
    $_SESSION['flash_success'] = "🎉 Registration completed successfully! Welcome to " . $siteTitle . ".";
    header('Location: dashboard.php');
    exit();
}

// If already paid or exempt, redirect to dashboard
if (RegistrationService::isUserRegistrationComplete($user)) {
    header('Location: dashboard.php');
    exit();
}

$userAvatar = Auth::getAvatarUrl($user['photo'] ?? null);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Complete Registration - <?= e($siteTitle) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets2/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets2/css/all.min.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #090714 0%, #17112c 100%);
            color: #e2e8f0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 15px;
            margin: 0;
        }
        .gate-card {
            background: rgba(26, 21, 50, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 215, 0, 0.25);
            border-radius: 22px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.7);
            width: 100%;
            max-width: 580px;
            padding: 40px 32px;
            position: relative;
            overflow: hidden;
        }
        .gate-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #ffd700, #ff8c00, #ffd700);
        }
        .brand-badge {
            display: inline-block;
            background: rgba(255, 215, 0, 0.12);
            color: #ffd700;
            border: 1px solid rgba(255, 215, 0, 0.3);
            border-radius: 50px;
            padding: 5px 16px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin-bottom: 12px;
        }
        .avatar-wrap {
            width: 84px;
            height: 84px;
            border-radius: 50%;
            border: 3px solid #ffd700;
            padding: 3px;
            background: #100b24;
            margin: 0 auto 12px;
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.25);
        }
        .avatar-wrap img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        .fee-highlight-box {
            background: rgba(13, 10, 28, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            padding: 20px;
            margin: 20px 0;
        }
        .fee-amount {
            font-size: 34px;
            font-weight: 800;
            color: #ffd700;
            letter-spacing: -0.5px;
        }
        .btn-gold-pay {
            background: linear-gradient(135deg, #ffd700 0%, #d4af37 100%);
            color: #0d1117;
            font-weight: 800;
            font-size: 16px;
            padding: 14px 24px;
            border-radius: 12px;
            border: none;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(255, 215, 0, 0.35);
        }
        .btn-gold-pay:hover {
            background: linear-gradient(135deg, #ffe033 0%, #e5bd3b 100%);
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(255, 215, 0, 0.5);
            color: #0d1117;
        }
        .btn-gold-pay:disabled {
            opacity: 0.7;
            transform: none;
            cursor: not-allowed;
        }
        .feature-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: #cbd5e1;
            margin-bottom: 8px;
        }
        .feature-item i {
            color: #2ecc71;
            font-size: 14px;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 193, 7, 0.15);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-radius: 50px;
            padding: 4px 14px;
            font-size: 12px;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="gate-card">
    <div class="text-center">
        <div class="brand-badge"><i class="fas fa-lock me-1"></i> Registration Checkpoint</div>
        
        <div class="avatar-wrap">
            <img src="<?= e($userAvatar) ?>" alt="<?= e($user['full_name']) ?>">
        </div>

        <h3 class="fw-bold text-white mb-1">Welcome, <?= e($user['full_name']) ?>!</h3>
        <p class="text-secondary small mb-2">@<?= e($user['username']) ?> &bull; <?= e($user['email']) ?></p>

        <div class="mb-3">
            <span class="status-pill">
                <i class="fas fa-clock fa-spin"></i> Registration Pending Payment
            </span>
        </div>

        <p class="text-secondary small px-2">
            Your contestant profile has been created! To activate your profile, publish your entry to the official leaderboard, and start receiving public votes, please complete your one-time registration fee below.
        </p>
    </div>

    <!-- Fee Breakdown Box -->
    <div class="fee-highlight-box">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-secondary small text-uppercase fw-bold">Official Registration Fee</span>
            <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50">One-Time Fee</span>
        </div>
        
        <div class="d-flex justify-content-between align-items-baseline mb-3">
            <div class="fee-amount"><?= $currency ?><?= number_format($regFee, 2) ?></div>
            <span class="text-secondary small"><?= e($currencyCode) ?></span>
        </div>

        <div class="border-top border-secondary border-opacity-25 pt-3">
            <div class="feature-item">
                <i class="fas fa-check-circle"></i>
                <span>Instant activation of your public voting profile & personalized link</span>
            </div>
            <div class="feature-item">
                <i class="fas fa-check-circle"></i>
                <span>Full access to contestant dashboard, vote stats & video showcase</span>
            </div>
            <div class="feature-item">
                <i class="fas fa-check-circle"></i>
                <span>Immediate eligibility to receive supporter votes and advance stages</span>
            </div>
            <div class="feature-item">
                <i class="fas fa-check-circle"></i>
                <span>Official contestant verification badge</span>
            </div>
        </div>
    </div>

    <!-- Alert / Status Notice Area -->
    <div id="noticeArea" class="mb-3 d-none"></div>

    <!-- Pay Now Button -->
    <div class="d-grid mb-3">
        <button type="button" class="btn-gold-pay" id="payFeeBtn" onclick="initiateRegistrationPayment()">
            <i class="fas fa-shield-alt me-2"></i> Pay <?= $currency ?><?= number_format($regFee, 2) ?> & Activate Profile
        </button>
    </div>

    <div class="text-center text-secondary small mb-3" style="font-size: 11px;">
        <i class="fas fa-lock text-warning me-1"></i> Secured by 256-bit SSL encrypted Paystack payment gateway.
    </div>

    <!-- Action Links -->
    <div class="d-flex justify-content-between align-items-center pt-3 border-top border-secondary border-opacity-25 small">
        <a href="logout.php" class="text-danger text-decoration-none">
            <i class="fas fa-sign-out-alt me-1"></i> Sign Out
        </a>
        <a href="https://api.whatsapp.com/send?phone=<?= urlencode(Settings::get('support_phone', '08139188570')) ?>&text=<?= urlencode("Hello, I need assistance with my contestant registration fee on " . $siteTitle . " (Username: " . $user['username'] . ").") ?>" target="_blank" class="text-warning text-decoration-none">
            <i class="fab fa-whatsapp me-1"></i> Need Help? Contact Support
        </a>
    </div>
</div>

<!-- Paystack Inline Popup SDK -->
<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
const USER_ID = <?= (int)$userId ?>;
const REG_FEE = <?= (float)$regFee ?>;
const REG_FEE_KOBO = Math.round(REG_FEE * 100);
const USER_EMAIL = '<?= e($user['email']) ?>';
const USER_NAME = '<?= e($user['full_name']) ?>';
const USER_PHONE = '<?= e($user['phone_number']) ?>';
const PAYSTACK_KEY = '<?= e($paystackPublicKey) ?>';
const CURRENCY_SYMBOL = '<?= e($currency) ?>';

function showNotice(msg, type = 'danger') {
    const box = document.getElementById('noticeArea');
    box.className = `alert alert-${type} py-2 px-3 small border-0 mb-3`;
    box.style.background = type === 'success' ? 'rgba(40, 167, 69, 0.2)' : 'rgba(220, 53, 69, 0.2)';
    box.style.color = type === 'success' ? '#2ecc71' : '#ff6b7d';
    box.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-1"></i> ${msg}`;
    box.classList.remove('d-none');
}

function initiateRegistrationPayment() {
    if (!PAYSTACK_KEY) {
        showNotice("Payment gateway is temporarily unavailable. Please contact administration.");
        return;
    }

    const btn = document.getElementById('payFeeBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Initializing Secure Gateway...';

    const handler = PaystackPop.setup({
        key: PAYSTACK_KEY,
        email: USER_EMAIL,
        amount: REG_FEE_KOBO,
        currency: 'NGN',
        ref: 'REG_' + USER_ID + '_' + Math.floor((Math.random() * 1000000000) + 1),
        metadata: {
            user_id: USER_ID,
            payment_type: 'registration',
            custom_fields: [
                { display_name: "Contestant ID", variable_name: "user_id", value: USER_ID },
                { display_name: "Contestant Name", variable_name: "contestant_name", value: USER_NAME },
                { display_name: "Payment Type", variable_name: "payment_type", value: "registration" }
            ]
        },
        callback: function(response) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Verifying & Activating Profile...';
            showNotice("Payment received! Activating your contestant profile...", "success");

            const formData = new FormData();
            formData.append('reference', response.reference);
            formData.append('user_id', USER_ID);
            formData.append('email', USER_EMAIL);
            formData.append('name', USER_NAME);
            formData.append('phone', USER_PHONE);

            fetch('verify_registration_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    btn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Registration Activated!';
                    showNotice("🎉 " + data.message + " Redirecting to your dashboard...", "success");
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 1500);
                } else {
                    btn.disabled = false;
                    btn.innerHTML = `<i class="fas fa-redo me-2"></i> Retry Payment (${CURRENCY_SYMBOL}${REG_FEE.toLocaleString()})`;
                    showNotice(data.error || "Verification issue occurred. Please try again or contact support.");
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = `<i class="fas fa-redo me-2"></i> Retry Payment (${CURRENCY_SYMBOL}${REG_FEE.toLocaleString()})`;
                showNotice("Network connectivity interrupted during confirmation. Your reference is: " + response.reference);
            });
        },
        onClose: function() {
            btn.disabled = false;
            btn.innerHTML = `<i class="fas fa-shield-alt me-2"></i> Pay ${CURRENCY_SYMBOL}${REG_FEE.toLocaleString()} & Activate Profile`;
        }
    });

    handler.openIframe();
}
</script>

</body>
</html>


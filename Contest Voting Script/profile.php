<?php
require_once __DIR__ . '/config.php';

$userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$userId || $userId <= 0) {
    header('Location: index.php');
    exit();
}

$pdo = DB::pdo();

// Fetch Contestant Data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id AND is_admin = 0 LIMIT 1");
$stmt->execute([':id' => $userId]);
$contestant = $stmt->fetch();

if (!$contestant) {
    header('Location: index.php');
    exit();
}

// Calculate Dynamic Position on Leaderboard
$rankStmt = $pdo->prepare("
    SELECT id, vote_count, 
           RANK() OVER (ORDER BY vote_count DESC) AS position 
    FROM users 
    WHERE is_admin = 0
");
$rankStmt->execute();
$allUsers = $rankStmt->fetchAll();

$position = 1;
foreach ($allUsers as $row) {
    if ((int)$row['id'] === $userId) {
        $position = (int)$row['position'];
        break;
    }
}

$siteTitle = Settings::get('site_title', 'Most Beautiful Discovery');
$currentStage = Settings::getCurrentStage();
$competitionEndTime = Settings::getCompetitionEndTime();
$isVotingOpen = Settings::isVotingOpen();
$votePrice = Settings::getVotePrice();
$currencySymbol = Settings::getCurrencySymbol();
$currencyCode = Settings::getCurrencyCode();
$paystackPublicKey = Env::get('PAYSTACK_PUBLIC_KEY');

$siteUrl = Env::get('APP_URL', 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
$profileUrl = rtrim($siteUrl, '/') . '/profile.php?id=' . $userId;
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vote for <?= e($contestant['full_name']) ?> - <?= e($siteTitle) ?></title>
    <meta name="description" content="Vote for <?= e($contestant['full_name']) ?> in <?= e($siteTitle) ?>. Help them advance to the next stage!">
    
    <!-- OpenGraph & Social Cards -->
    <meta property="og:title" content="Vote for <?= e($contestant['full_name']) ?> on <?= e($siteTitle) ?>">
    <meta property="og:description" content="Cast your vote to support <?= e($contestant['full_name']) ?>. Every vote counts!">
    <meta property="og:image" content="<?= rtrim($siteUrl, '/') ?>/uploads/<?= e($contestant['photo']) ?>">
    <meta property="og:url" content="<?= e($profileUrl) ?>">

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets2/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets2/css/all.min.css">

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #0a0815 0%, #15102a 50%, #0d0a1a 100%);
            color: #f1f5f9;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        .header-bar {
            background: rgba(18, 14, 38, 0.9);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 215, 0, 0.2);
            padding: 16px 0;
        }
        .profile-container {
            max-width: 760px;
            margin: 30px auto;
            padding: 0 15px;
        }
        .profile-card {
            background: rgba(26, 21, 53, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 215, 0, 0.25);
            border-radius: 24px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
            padding: 35px 25px;
            position: relative;
            text-align: center;
        }
        .avatar-wrap {
            width: 170px;
            height: 170px;
            border-radius: 50%;
            border: 4px solid #ffd700;
            padding: 4px;
            background: #110d24;
            margin: 0 auto 20px;
            position: relative;
            box-shadow: 0 0 30px rgba(255, 215, 0, 0.35);
        }
        .avatar-wrap img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        .rank-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #ffd700 0%, #d4af37 100%);
            color: #0d1117;
            font-size: 14px;
            font-weight: 800;
            padding: 6px 16px;
            border-radius: 50px;
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.4);
        }
        .stage-pill {
            display: inline-block;
            background: rgba(255, 215, 0, 0.12);
            border: 1px solid rgba(255, 215, 0, 0.3);
            color: #ffd700;
            font-size: 13px;
            font-weight: 700;
            padding: 4px 16px;
            border-radius: 50px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }
        .vote-box {
            background: rgba(15, 12, 32, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 18px;
            padding: 24px;
            margin-top: 24px;
            text-align: left;
        }
        .quick-btn {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #f1f5f9;
            border-radius: 10px;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 13px;
            transition: all 0.2s ease;
            cursor: pointer;
            text-align: center;
        }
        .quick-btn:hover, .quick-btn.active {
            background: #ffd700;
            color: #0d1117;
            border-color: #ffd700;
            transform: translateY(-2px);
        }
        .btn-vote-now {
            background: linear-gradient(135deg, #ffd700 0%, #ffaa00 100%);
            color: #0d1117;
            font-weight: 800;
            font-size: 18px;
            padding: 14px;
            border-radius: 12px;
            border: none;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(255, 170, 0, 0.35);
        }
        .btn-vote-now:hover {
            background: linear-gradient(135deg, #ffe033 0%, #ffbb11 100%);
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(255, 170, 0, 0.5);
            color: #0d1117;
        }
        .countdown-timer {
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 215, 0, 0.3);
            border-radius: 12px;
            padding: 12px;
            margin: 16px 0;
            color: #ffd700;
            font-weight: 800;
            font-size: 18px;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="header-bar text-center">
    <div class="container">
        <a href="index.php" class="text-white text-decoration-none fw-bold fs-5">
            <i class="fas fa-crown text-warning me-2"></i> <?= e($siteTitle) ?>
        </a>
    </div>
</div>

<div class="profile-container">

    <div class="profile-card">
        <!-- Rank Badge -->
        <div class="rank-badge">
            <i class="fas fa-trophy me-1"></i> Rank #<?= $position ?>
        </div>

        <div class="stage-pill">
            <i class="fas fa-layer-group me-1"></i> <?= e($currentStage) ?>
        </div>

        <!-- Contestant Avatar -->
        <div class="avatar-wrap">
            <img src="uploads/<?= e($contestant['photo']) ?>" alt="<?= e($contestant['full_name']) ?>">
        </div>

        <h2 class="fw-bold text-white mb-1"><?= e($contestant['full_name']) ?></h2>
        <p class="text-secondary small mb-3">@<?= e($contestant['username']) ?></p>

        <div class="d-inline-flex align-items-center gap-2 px-4 py-2 rounded-pill bg-dark border border-secondary mb-3">
            <i class="fas fa-vote-yea text-warning fs-5"></i>
            <span class="fs-5 fw-bold text-white"><?= number_format((int)$contestant['vote_count']) ?></span>
            <span class="text-secondary small text-uppercase">Total Votes</span>
        </div>

        <?php if (!empty($contestant['bio'])): ?>
            <p class="text-light opacity-75 small px-md-4 mb-3"><?= nl2br(e($contestant['bio'])) ?></p>
        <?php endif; ?>

        <!-- Countdown -->
        <div class="countdown-timer" id="countdownBox">
            <span class="text-secondary small d-block mb-1">Voting Closes In:</span>
            <span id="timerText">Calculating time...</span>
        </div>

        <!-- Voting Interface -->
        <?php if (!$isVotingOpen): ?>
            <div class="alert alert-warning mt-4 border-0" style="background: rgba(255, 193, 7, 0.15); color: #ffd700;">
                <i class="fas fa-clock fa-2x mb-2 d-block"></i>
                <h5 class="fw-bold">Voting is Closed</h5>
                <p class="small mb-0">The voting period for this stage has concluded. Stay tuned for results!</p>
            </div>
        <?php else: ?>

            <div class="vote-box">
                <h5 class="fw-bold text-white mb-1"><i class="fas fa-bolt text-warning me-2"></i> Support <?= e($contestant['full_name']) ?></h5>
                <p class="text-secondary small mb-3">Select vote quantity (<?= $currencySymbol ?><?= number_format($votePrice, 0) ?> per vote) to cast instantly via Paystack.</p>

                <!-- Quick Vote Buttons -->
                <div class="row g-2 mb-3">
                    <div class="col-6 col-sm-3">
                        <div class="quick-btn active" onclick="selectQuickVotes(10, this)">
                            10 Votes<br><span class="text-warning small"><?= $currencySymbol ?><?= number_format(10 * $votePrice) ?></span>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="quick-btn" onclick="selectQuickVotes(20, this)">
                            20 Votes<br><span class="text-warning small"><?= $currencySymbol ?><?= number_format(20 * $votePrice) ?></span>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="quick-btn" onclick="selectQuickVotes(50, this)">
                            50 Votes<br><span class="text-warning small"><?= $currencySymbol ?><?= number_format(50 * $votePrice) ?></span>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="quick-btn" onclick="selectQuickVotes(100, this)">
                            100 Votes<br><span class="text-warning small"><?= $currencySymbol ?><?= number_format(100 * $votePrice) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Custom Input -->
                <div class="row g-2 mb-3">
                    <div class="col-12 col-sm-6">
                        <label class="form-label text-light small fw-semibold">Number of Votes</label>
                        <input type="number" id="customVoteCount" class="form-control bg-dark border-secondary text-white" min="1" value="10" oninput="updateTotalAmount()">
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label text-light small fw-semibold">Total Amount (<?= $currencyCode ?>)</label>
                        <div class="form-control bg-dark border-secondary text-warning fw-bold fs-5" id="totalAmountDisplay">
                            <?= $currencySymbol ?><?= number_format(10 * $votePrice, 2) ?>
                        </div>
                    </div>
                </div>

                <!-- Voter Information -->
                <div class="row g-2 mb-4">
                    <div class="col-12 col-sm-6">
                        <label class="form-label text-light small fw-semibold">Your Email Address *</label>
                        <input type="email" id="voterEmail" class="form-control bg-dark border-secondary text-white" placeholder="receipt@example.com" required>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label text-light small fw-semibold">Your Name (Optional)</label>
                        <input type="text" id="voterName" class="form-control bg-dark border-secondary text-white" placeholder="Supporter Name">
                    </div>
                </div>

                <button class="btn-vote-now" id="submitVoteBtn" onclick="initiatePaystackPayment()">
                    <i class="fas fa-lock me-2"></i> Pay & Cast Votes Now
                </button>
            </div>

        <?php endif; ?>

        <!-- Social Share Bar -->
        <div class="mt-4 pt-3 border-top border-secondary opacity-75">
            <span class="text-secondary small d-block mb-2">Share this contestant's profile:</span>
            <div class="d-flex justify-content-center gap-2 flex-wrap">
                <button class="btn btn-outline-secondary btn-sm" onclick="copyLink()">
                    <i class="fas fa-link me-1"></i> Copy Link
                </button>
                <a href="https://api.whatsapp.com/send?text=<?= urlencode("Vote for " . $contestant['full_name'] . " on " . $siteTitle . "! " . $profileUrl) ?>" target="_blank" class="btn btn-outline-success btn-sm">
                    <i class="fab fa-whatsapp me-1"></i> WhatsApp
                </a>
                <a href="https://twitter.com/intent/tweet?text=<?= urlencode("Vote for " . $contestant['full_name'] . " on " . $siteTitle . "! " . $profileUrl) ?>" target="_blank" class="btn btn-outline-info btn-sm">
                    <i class="fab fa-twitter me-1"></i> Post
                </a>
            </div>
        </div>

        <div class="mt-3">
            <a href="index.php" class="text-secondary small text-decoration-none"><i class="fas fa-arrow-left me-1"></i> Return to Main Leaderboard</a>
        </div>
    </div>

</div>

<!-- Paystack Pop SDK -->
<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
const CONTESTANT_ID = <?= (int)$userId ?>;
const VOTE_PRICE = <?= (float)$votePrice ?>;
const CURRENCY_SYMBOL = '<?= e($currencySymbol) ?>';
const PAYSTACK_KEY = '<?= e($paystackPublicKey) ?>';

function selectQuickVotes(count, el) {
    document.querySelectorAll('.quick-btn').forEach(btn => btn.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('customVoteCount').value = count;
    updateTotalAmount();
}

function updateTotalAmount() {
    const countInput = document.getElementById('customVoteCount');
    let count = parseInt(countInput.value, 10);
    if (isNaN(count) || count < 1) {
        count = 1;
    }
    const total = count * VOTE_PRICE;
    document.getElementById('totalAmountDisplay').innerText = CURRENCY_SYMBOL + total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function initiatePaystackPayment() {
    const voteCount = parseInt(document.getElementById('customVoteCount').value, 10);
    const email = document.getElementById('voterEmail').value.trim();
    const name = document.getElementById('voterName').value.trim();

    if (isNaN(voteCount) || voteCount < 1) {
        alert("Please enter a valid number of votes (minimum 1).");
        return;
    }

    if (!email || !email.includes('@')) {
        alert("Please provide a valid email address for your payment receipt.");
        document.getElementById('voterEmail').focus();
        return;
    }

    const totalAmountMain = voteCount * VOTE_PRICE;
    const totalAmountKobo = Math.round(totalAmountMain * 100);

    const btn = document.getElementById('submitVoteBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Connecting to Secure Gateway...';

    const handler = PaystackPop.setup({
        key: PAYSTACK_KEY,
        email: email,
        amount: totalAmountKobo,
        currency: 'NGN',
        ref: 'VOTE_' + CONTESTANT_ID + '_' + Math.floor((Math.random() * 1000000000) + 1),
        metadata: {
            contestant_id: CONTESTANT_ID,
            vote_count: voteCount,
            custom_fields: [
                { display_name: "Contestant ID", variable_name: "contestant_id", value: CONTESTANT_ID },
                { display_name: "Voter Name", variable_name: "voter_name", value: name }
            ]
        },
        callback: function(response) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Verifying & Recording Votes...';

            const formData = new FormData();
            formData.append('reference', response.reference);
            formData.append('user_id', CONTESTANT_ID);
            formData.append('email', email);
            formData.append('name', name);

            fetch('verify_transaction.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert("🎉 Success! " + data.message);
                    window.location.reload();
                } else {
                    alert("⚠️ Verification Notice: " + (data.error || "Unable to confirm votes."));
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-lock me-2"></i> Pay & Cast Votes Now';
                }
            })
            .catch(err => {
                alert("Network error occurred while confirming payment. Your payment reference is: " + response.reference);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-lock me-2"></i> Pay & Cast Votes Now';
            });
        },
        onClose: function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-lock me-2"></i> Pay & Cast Votes Now';
        }
    });

    handler.openIframe();
}

function copyLink() {
    navigator.clipboard.writeText(window.location.href).then(() => {
        alert("Contestant profile link copied!");
    });
}

// Countdown Engine
function initCountdown(endTimeStr) {
    const timerText = document.getElementById('timerText');
    const endDate = new Date(endTimeStr).getTime();

    if (isNaN(endDate)) {
        timerText.innerText = "Active Stage";
        return;
    }

    const timer = setInterval(() => {
        const now = new Date().getTime();
        const diff = endDate - now;

        if (diff <= 0) {
            clearInterval(timer);
            timerText.innerText = "Voting has ended.";
            return;
        }

        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);

        timerText.innerText = `${days}d ${hours}h ${minutes}m ${seconds}s`;
    }, 1000);
}

initCountdown('<?= e($competitionEndTime) ?>');
</script>

</body>
</html>

<?php
require_once __DIR__ . '/config.php';

Auth::requireUser();

$user = Auth::getCurrentUser();
if (!$user) {
    Auth::logoutUser();
}

$pdo = DB::pdo();
$userId = (int)$user['id'];

$successMessage = '';
$errorMessage = '';

// Handle Photo Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    if (!Security::validateCsrf()) {
        $errorMessage = 'Security session expired. Please refresh the page and try again.';
    } else {
        $upload = Security::handleFileUpload($_FILES['photo'], __DIR__ . '/uploads/', ['jpg', 'jpeg', 'png', 'webp'], 5);

        if ($upload['success']) {
            $newPhoto = $upload['filename'];

            // Optionally delete old photo if not default
            if (!empty($user['photo']) && $user['photo'] !== 'default_avatar.png') {
                $oldPath = __DIR__ . '/uploads/' . $user['photo'];
                if (file_exists($oldPath) && is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }

            $stmt = $pdo->prepare("UPDATE users SET photo = :photo WHERE id = :id");
            $stmt->execute([':photo' => $newPhoto, ':id' => $userId]);

            $user['photo'] = $newPhoto;
            $successMessage = "Profile photo updated successfully!";
        } else {
            $errorMessage = $upload['error'];
        }
    }
}

// Calculate Leaderboard Position (Cross-Version Safe)
$userRank = 1;
$totalContestants = 0;
try {
    $rankStmt = $pdo->query("SELECT id, vote_count FROM users WHERE is_admin = 0 ORDER BY vote_count DESC");
    $rankings = $rankStmt->fetchAll();
    $totalContestants = count($rankings);
    $posCounter = 1;
    foreach ($rankings as $r) {
        if ((int)$r['id'] === $userId) {
            $userRank = $posCounter;
            break;
        }
        $posCounter++;
    }
} catch (Exception $e) {
    $userRank = 1;
}

// Recent Supporter Votes (Fail-Safe)
$recentVotes = [];
try {
    $votesStmt = $pdo->prepare("
        SELECT vote_count, amount, voter_name, voter_email, created_at 
        FROM votes 
        WHERE user_id = :id 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $votesStmt->execute([':id' => $userId]);
    $recentVotes = $votesStmt->fetchAll();
} catch (Exception $e) {
    $recentVotes = [];
}

$currentStage = Settings::getCurrentStage();
$endTime = Settings::getCompetitionEndTime();
$currency = Settings::getCurrencySymbol();
$siteUrl = Env::get('APP_URL', 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
$profileUrl = rtrim($siteUrl, '/') . '/profile.php?id=' . $userId;
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Contestant Dashboard - <?= e($user['full_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets2/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets2/css/all.min.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #0a0814 0%, #15102a 100%);
            color: #e2e8f0;
            min-height: 100vh;
        }
        .navbar-custom {
            background: rgba(18, 14, 38, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 215, 0, 0.15);
        }
        .dashboard-card {
            background: rgba(26, 21, 53, 0.8);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            padding: 24px;
            margin-bottom: 24px;
            transition: transform 0.2s ease, border-color 0.2s ease;
        }
        .dashboard-card:hover {
            border-color: rgba(255, 215, 0, 0.3);
        }
        .avatar-container {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            border: 3px solid #ffd700;
            padding: 4px;
            background: #110d24;
            margin: 0 auto 16px;
            position: relative;
            box-shadow: 0 0 25px rgba(255, 215, 0, 0.25);
        }
        .avatar-container img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        .stat-badge {
            font-size: 32px;
            font-weight: 800;
            color: #ffd700;
            letter-spacing: -0.5px;
        }
        .btn-gold {
            background: linear-gradient(135deg, #ffd700 0%, #d4af37 100%);
            color: #0d1117;
            font-weight: 700;
            border-radius: 8px;
            border: none;
            padding: 10px 20px;
        }
        .btn-gold:hover {
            background: linear-gradient(135deg, #ffe033 0%, #e5bd3b 100%);
            color: #0d1117;
        }
        .share-btn {
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            transition: transform 0.2s ease;
        }
        .share-btn:hover {
            transform: translateY(-2px);
        }
        .share-whatsapp { background: #25D366; color: #fff; }
        .share-twitter { background: #1DA1F2; color: #fff; }
        .share-facebook { background: #1877F2; color: #fff; }
        .table-custom {
            background: transparent;
            color: #cbd5e1;
        }
        .table-custom th {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: #94a3b8;
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 700;
        }
        .table-custom td {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 14px;
            padding: 12px 8px;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom sticky-top py-3">
    <div class="container">
        <a class="navbar-brand fw-bold text-white d-flex align-items-center gap-2" href="index.php">
            <i class="fas fa-crown text-warning"></i> <?= e(Settings::get('site_title', 'Voting Platform')) ?>
        </a>
        <div class="d-flex align-items-center gap-3">
            <a href="profile.php?id=<?= $userId ?>" target="_blank" class="btn btn-outline-warning btn-sm">
                <i class="fas fa-eye me-1"></i> View Public Profile
            </a>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">
                <i class="fas fa-sign-out-alt me-1"></i> Logout
            </a>
        </div>
    </div>
</nav>

<div class="container py-4">

    <!-- Flash Messages -->
    <?php if (!empty($successMessage)): ?>
        <div class="alert alert-success alert-dismissible fade show border-0" style="background: rgba(40, 167, 69, 0.2); color: #2ecc71;">
            <i class="fas fa-check-circle me-1"></i> <?= e($successMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0" style="background: rgba(220, 53, 69, 0.2); color: #ff6b7d;">
            <i class="fas fa-exclamation-circle me-1"></i> <?= e($errorMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Contestant Profile Card -->
        <div class="col-lg-4">
            <div class="dashboard-card text-center">
                <div class="avatar-container">
                    <img src="uploads/<?= e($user['photo']) ?>" alt="<?= e($user['username']) ?>">
                </div>
                <h4 class="fw-bold text-white mb-1"><?= e($user['full_name']) ?></h4>
                <p class="text-secondary small mb-3">@<?= e($user['username']) ?></p>

                <div class="d-flex justify-content-center gap-2 mb-4">
                    <span class="badge bg-warning text-dark px-3 py-2 fw-bold">
                        <i class="fas fa-layer-group me-1"></i> <?= e($currentStage) ?>
                    </span>
                    <span class="badge bg-secondary px-3 py-2 fw-semibold">
                        Rank #<?= $userRank ?> of <?= $totalContestants ?>
                    </span>
                </div>

                <hr class="border-secondary opacity-25">

                <!-- Update Photo Form -->
                <form method="POST" action="dashboard.php" enctype="multipart/form-data" class="mt-3">
                    <?= Security::csrfField() ?>
                    <label class="form-label text-light small fw-semibold d-block text-start">Change Profile Photo</label>
                    <div class="input-group mb-2">
                        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required class="form-control form-control-sm">
                        <button type="submit" class="btn btn-warning btn-sm fw-bold">Upload</button>
                    </div>
                    <div class="form-text text-secondary text-start" style="font-size: 11px;">Max 5MB (JPG, PNG, WEBP)</div>
                </form>
            </div>
        </div>

        <!-- Metrics & Share Links -->
        <div class="col-lg-8">
            <!-- Stat Tiles -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="dashboard-card p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-secondary text-uppercase fw-bold small">Total Votes Received</span>
                                <div class="stat-badge mt-1"><?= number_format((int)$user['vote_count']) ?></div>
                            </div>
                            <div class="p-3 rounded-circle" style="background: rgba(255, 215, 0, 0.15); color: #ffd700;">
                                <i class="fas fa-vote-yea fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="dashboard-card p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-secondary text-uppercase fw-bold small">Leaderboard Position</span>
                                <div class="stat-badge mt-1 text-white">#<?= $userRank ?></div>
                            </div>
                            <div class="p-3 rounded-circle" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6;">
                                <i class="fas fa-trophy fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Share & Promotion Hub -->
            <div class="dashboard-card">
                <h5 class="fw-bold text-white mb-2"><i class="fas fa-share-alt text-warning me-2"></i> Share Your Voting Link</h5>
                <p class="text-secondary small mb-3">Share your personalized link on social media and WhatsApp groups to get more votes!</p>

                <div class="input-group mb-3">
                    <input type="text" id="profileLinkInput" class="form-control" readonly value="<?= e($profileUrl) ?>">
                    <button class="btn btn-gold" type="button" onclick="copyProfileLink()">
                        <i class="fas fa-copy me-1"></i> Copy Link
                    </button>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <a href="https://api.whatsapp.com/send?text=<?= urlencode("Vote for " . $user['full_name'] . " in the " . Settings::get('site_title') . "! Support me by casting your vote here: " . $profileUrl) ?>" target="_blank" class="share-btn share-whatsapp">
                        <i class="fab fa-whatsapp"></i> Share on WhatsApp
                    </a>
                    <a href="https://twitter.com/intent/tweet?text=<?= urlencode("Vote for " . $user['full_name'] . " in the " . Settings::get('site_title') . "! " . $profileUrl) ?>" target="_blank" class="share-btn share-twitter">
                        <i class="fab fa-twitter"></i> Post to X / Twitter
                    </a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($profileUrl) ?>" target="_blank" class="share-btn share-facebook">
                        <i class="fab fa-facebook"></i> Share on Facebook
                    </a>
                </div>
            </div>

            <!-- Recent Supporter Votes -->
            <div class="dashboard-card">
                <h5 class="fw-bold text-white mb-3"><i class="fas fa-history text-warning me-2"></i> Recent Supporter Contributions</h5>
                <?php if (empty($recentVotes)): ?>
                    <p class="text-secondary small mb-0">No recorded votes yet. Share your profile link to start collecting votes!</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-custom mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Supporter</th>
                                    <th>Votes</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentVotes as $v): ?>
                                    <tr>
                                        <td><?= date('M d, Y h:i A', strtotime($v['created_at'])) ?></td>
                                        <td><?= !empty($v['voter_name']) ? e($v['voter_name']) : (!empty($v['voter_email']) ? e($v['voter_email']) : 'Anonymous Voter') ?></td>
                                        <td><span class="badge bg-success">+<?= (int)$v['vote_count'] ?> votes</span></td>
                                        <td><?= $currency . number_format((float)$v['amount'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script src="assets2/js/bootstrap.bundle.min.js"></script>
<script>
function copyProfileLink() {
    const linkInput = document.getElementById('profileLinkInput');
    linkInput.select();
    linkInput.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(linkInput.value).then(() => {
        alert("Voting profile link copied to clipboard!");
    });
}
</script>

</body>
</html>

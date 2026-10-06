<?php
require_once __DIR__ . '/config.php';

Auth::requireAdmin();

$pdo = DB::pdo();
$admin = Auth::getCurrentAdmin();

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// =========================================================================
// Unified Admin Action Router (Single POST Dispatcher with CSRF Protection)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrf()) {
        $_SESSION['flash_error'] = 'Security session expired. Please refresh and try again.';
    } else {
        $action = $_POST['admin_action'] ?? '';

        try {
        switch ($action) {
            case 'update_vote_count':
                $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
                $voteCount = filter_input(INPUT_POST, 'vote_count', FILTER_VALIDATE_INT);
                if ($userId && $voteCount !== null && $voteCount >= 0) {
                    $stmt = $pdo->prepare("UPDATE users SET vote_count = :votes WHERE id = :id AND is_admin = 0");
                    $stmt->execute([':votes' => $voteCount, ':id' => $userId]);
                    $_SESSION['flash_success'] = "Vote count updated successfully!";
                } else {
                    $_SESSION['flash_error'] = "Invalid user ID or vote count.";
                }
                break;

            case 'delete_user':
                $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
                if ($userId) {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id AND is_admin = 0");
                    $stmt->execute([':id' => $userId]);
                    $_SESSION['flash_success'] = "Contestant deleted successfully.";
                }
                break;

            case 'delete_selected_users':
                $selectedIds = $_POST['selected_users'] ?? [];
                if (!empty($selectedIds) && is_array($selectedIds)) {
                    $validIds = array_filter(array_map('intval', $selectedIds));
                    if (!empty($validIds)) {
                        $inClause = implode(',', array_fill(0, count($validIds), '?'));
                        $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($inClause) AND is_admin = 0");
                        $stmt->execute(array_values($validIds));
                        $_SESSION['flash_success'] = count($validIds) . " contestants deleted successfully.";
                    }
                } else {
                    $_SESSION['flash_error'] = "No contestants selected for deletion.";
                }
                break;

            case 'clear_user_votes':
                $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
                if ($userId) {
                    $stmt = $pdo->prepare("UPDATE users SET vote_count = 0 WHERE id = :id AND is_admin = 0");
                    $stmt->execute([':id' => $userId]);
                    $_SESSION['flash_success'] = "Contestant votes reset to zero.";
                }
                break;

            case 'clear_all_votes':
                $pdo->exec("UPDATE users SET vote_count = 0 WHERE is_admin = 0");
                $_SESSION['flash_success'] = "All contestant votes have been reset to zero.";
                break;

            case 'update_stage':
                $stageName = trim($_POST['stage_name'] ?? '');
                if (!empty($stageName)) {
                    Settings::setCurrentStage($stageName);
                    $_SESSION['flash_success'] = "Competition stage updated to: " . e($stageName);
                }
                break;

            case 'toggle_registration':
                $status = (int)($_POST['registration_status'] ?? 0);
                Settings::set('registration_open', $status === 1 ? '1' : '0');
                file_put_contents(__DIR__ . '/version.txt', time());
                $_SESSION['flash_success'] = $status === 1 ? "Registration is now OPEN." : "Registration is now CLOSED.";
                break;

            case 'update_competition_time':
                $endTime = trim($_POST['competition_end_time'] ?? '');
                if (!empty($endTime)) {
                    Settings::setCompetitionEndTime($endTime);
                    $_SESSION['flash_success'] = "Competition end countdown updated.";
                }
                break;

            case 'update_vote_price':
                $price = filter_input(INPUT_POST, 'vote_price', FILTER_VALIDATE_FLOAT);
                if ($price && $price > 0) {
                    Settings::set('vote_price', (string)$price);
                    $_SESSION['flash_success'] = "Vote unit price updated to: " . Settings::getCurrencySymbol() . number_format($price, 2);
                }
                break;

            case 'upload_banner':
                if (isset($_FILES['banner_image'])) {
                    $upload = Security::handleFileUpload($_FILES['banner_image'], __DIR__ . '/uploads/', ['jpg', 'jpeg', 'png', 'webp'], 5);
                    if ($upload['success']) {
                        $targetPath = 'uploads/' . $upload['filename'];
                        $pdo->exec("UPDATE banner SET is_active = 0");
                        $stmt = $pdo->prepare("INSERT INTO banner (image_path, is_active) VALUES (?, 1)");
                        $stmt->execute([$targetPath]);
                        $_SESSION['flash_success'] = "Website hero banner updated successfully!";
                    } else {
                        $_SESSION['flash_error'] = "Banner upload failed: " . $upload['error'];
                    }
                }
                break;

            case 'add_competition':
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                if (!empty($title) && !empty($description) && isset($_FILES['image'])) {
                    $upload = Security::handleFileUpload($_FILES['image'], __DIR__ . '/uploads/', ['jpg', 'jpeg', 'png', 'webp'], 5);
                    if ($upload['success']) {
                        $targetPath = 'uploads/' . $upload['filename'];
                        $stmt = $pdo->prepare("INSERT INTO competitions (title, description, image_path, status) VALUES (?, ?, ?, 'active')");
                        $stmt->execute([$title, $description, $targetPath]);
                        $_SESSION['flash_success'] = "New competition card added!";
                    } else {
                        $_SESSION['flash_error'] = "Competition image upload failed: " . $upload['error'];
                    }
                }
                break;

            case 'delete_competition':
                $compId = filter_input(INPUT_POST, 'competition_id', FILTER_VALIDATE_INT);
                if ($compId) {
                    $stmt = $pdo->prepare("DELETE FROM competitions WHERE id = ?");
                    $stmt->execute([$compId]);
                    $_SESSION['flash_success'] = "Competition card deleted.";
                }
                break;

            default:
                $_SESSION['flash_error'] = "Unrecognized administrative action.";
                break;
        }
        } catch (Exception $e) {
            error_log("Admin action exception: " . $e->getMessage());
            $_SESSION['flash_error'] = "An error occurred: " . $e->getMessage();
        }
    }

    header('Location: admin_dashboard.php');
    exit();
}

// =========================================================================
// Fetch Dashboard Metrics & Datasets
// =========================================================================

// 1. Total Metrics (Fail-Safe)
$totalUsers = 0;
$totalVotes = 0;
$totalRevenue = 0.0;
$totalTransactions = 0;

try {
    $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_admin = 0")->fetchColumn();
    $totalVotes = (int)$pdo->query("SELECT COALESCE(SUM(vote_count), 0) FROM users WHERE is_admin = 0")->fetchColumn();
} catch (Exception $e) {
    error_log("Users query error: " . $e->getMessage());
}

try {
    $totalRevenue = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'success'")->fetchColumn();
    $totalTransactions = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'success'")->fetchColumn();
} catch (Exception $e) {
    // If payments table does not exist yet
    $totalRevenue = 0.0;
    $totalTransactions = 0;
}

// 2. Contestant Leaderboard (Cross-version compatible without RANK OVER)
$contestants = [];
try {
    $contestantsStmt = $pdo->query("
        SELECT id, username, full_name, email, phone_number, photo, vote_count
        FROM users 
        WHERE is_admin = 0 
        ORDER BY vote_count DESC
    ");
    $rawContestants = $contestantsStmt->fetchAll();
    $rankNum = 1;
    foreach ($rawContestants as $row) {
        $row['ranking'] = $rankNum++;
        $contestants[] = $row;
    }
} catch (Exception $e) {
    error_log("Contestants query error: " . $e->getMessage());
}

// 3. Recent Verified Payments (Fail-Safe)
$recentPayments = [];
try {
    $paymentsStmt = $pdo->query("
        SELECT p.*, u.full_name AS contestant_name, u.username AS contestant_username
        FROM payments p
        LEFT JOIN users u ON p.user_id = u.id
        ORDER BY p.created_at DESC 
        LIMIT 25
    ");
    $recentPayments = $paymentsStmt->fetchAll();
} catch (Exception $e) {
    $recentPayments = [];
}

// 4. Competitions (Fail-Safe)
$competitions = [];
try {
    $competitions = $pdo->query("SELECT * FROM competitions ORDER BY created_at DESC")->fetchAll();
} catch (Exception $e) {
    $competitions = [];
}

$currentStage = Settings::getCurrentStage();
$isRegOpen = Settings::isRegistrationOpen();
$competitionEndTime = Settings::getCompetitionEndTime();
$votePrice = Settings::getVotePrice();
$currency = Settings::getCurrencySymbol();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Admin Dashboard - <?= e(Settings::get('site_title', 'Voting Platform')) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets2/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets2/css/all.min.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #0b0e14;
            color: #cbd5e1;
            min-height: 100vh;
        }
        .admin-nav {
            background: #11151e;
            border-bottom: 1px solid rgba(255, 215, 0, 0.2);
        }
        .stat-card {
            background: #161b26;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 22px;
            height: 100%;
            transition: border-color 0.2s;
        }
        .stat-card:hover {
            border-color: rgba(255, 215, 0, 0.3);
        }
        .stat-value {
            font-size: 28px;
            font-weight: 800;
            color: #fff;
        }
        .content-panel {
            background: #161b26;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .table-custom {
            color: #e2e8f0;
            font-size: 14px;
            vertical-align: middle;
        }
        .table-custom th {
            background: #10141d;
            border-bottom: 2px solid #232a3b;
            color: #94a3b8;
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 700;
            padding: 12px 10px;
        }
        .table-custom td {
            border-bottom: 1px solid #232a3b;
            padding: 12px 10px;
        }
        .table-custom tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }
        .btn-gold {
            background: linear-gradient(135deg, #ffd700 0%, #d4af37 100%);
            color: #0d1117;
            font-weight: 700;
            border-radius: 8px;
            border: none;
        }
        .btn-gold:hover {
            background: linear-gradient(135deg, #ffe033 0%, #e5bd3b 100%);
            color: #0d1117;
        }
        .nav-tabs .nav-link {
            color: #94a3b8;
            font-weight: 600;
            border: none;
            border-bottom: 2px solid transparent;
            padding: 12px 20px;
        }
        .nav-tabs .nav-link.active {
            background: transparent;
            color: #ffd700;
            border-bottom: 2px solid #ffd700;
        }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-dark admin-nav sticky-top py-3">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold text-white d-flex align-items-center gap-2" href="admin_dashboard.php">
            <i class="fas fa-shield-alt text-warning"></i> <?= e(Settings::get('site_title')) ?> <span class="badge bg-warning text-dark ms-2 small">Admin</span>
        </a>
        <div class="d-flex align-items-center gap-3">
            <a href="index.php" target="_blank" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-external-link-alt me-1"></i> Public Site
            </a>
            <span class="text-secondary small d-none d-md-inline">Logged in as <strong><?= e($admin['username'] ?? 'Admin') ?></strong></span>
            <a href="admin_logout.php" class="btn btn-danger btn-sm">
                <i class="fas fa-sign-out-alt me-1"></i> Logout
            </a>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 py-4">

    <!-- Flash Notifications -->
    <?php if (!empty($flashSuccess)): ?>
        <div class="alert alert-success alert-dismissible fade show border-0" style="background: rgba(40, 167, 69, 0.2); color: #2ecc71;">
            <i class="fas fa-check-circle me-1"></i> <?= e($flashSuccess) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($flashError)): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0" style="background: rgba(220, 53, 69, 0.2); color: #ff6b7d;">
            <i class="fas fa-exclamation-circle me-1"></i> <?= e($flashError) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Metric Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Total Revenue</div>
                <div class="stat-value text-success"><?= $currency . number_format($totalRevenue, 2) ?></div>
                <div class="text-secondary small mt-1"><?= number_format($totalTransactions) ?> verified transactions</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Total Votes Cast</div>
                <div class="stat-value text-warning"><?= number_format($totalVotes) ?></div>
                <div class="text-secondary small mt-1">Across all active stages</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Contestants</div>
                <div class="stat-value text-info"><?= number_format($totalUsers) ?></div>
                <div class="text-secondary small mt-1">Registered participants</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="text-secondary small fw-bold text-uppercase mb-1">Current Stage</div>
                <div class="stat-value text-white fs-4"><?= e($currentStage) ?></div>
                <div class="text-secondary small mt-1">
                    Registration: <span class="badge <?= $isRegOpen ? 'bg-success' : 'bg-danger' ?>"><?= $isRegOpen ? 'OPEN' : 'CLOSED' ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-4" id="adminTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" id="contestants-tab" data-bs-toggle="tab" data-bs-target="#contestantsTab">
                <i class="fas fa-users me-1"></i> Contestants (<?= count($contestants) ?>)
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#paymentsTab">
                <i class="fas fa-receipt me-1"></i> Payment & Vote Ledger
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settingsTab">
                <i class="fas fa-sliders-h me-1"></i> Stages & Rules Settings
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="media-tab" data-bs-toggle="tab" data-bs-target="#mediaTab">
                <i class="fas fa-images me-1"></i> Banners & Showcase
            </button>
        </li>
    </ul>

    <div class="tab-content" id="adminTabsContent">

        <!-- ==================== TAB 1: CONTESTANTS ==================== -->
        <div class="tab-pane fade show active" id="contestantsTab">
            <div class="content-panel">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                    <h5 class="fw-bold text-white mb-0">Contestant Leaderboard & Management</h5>
                    <div class="d-flex gap-2">
                        <!-- Reset All Votes Form -->
                        <form method="POST" onsubmit="return confirm('CRITICAL: Are you sure you want to reset ALL contestant votes to zero?');">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="admin_action" value="clear_all_votes">
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="fas fa-undo-alt me-1"></i> Reset All Votes to 0
                            </button>
                        </form>
                    </div>
                </div>

                <form method="POST" id="batchForm">
                    <?= Security::csrfField() ?>
                    <input type="hidden" name="admin_action" value="delete_selected_users">

                    <div class="table-responsive">
                        <table class="table table-custom table-hover">
                            <thead>
                                <tr>
                                    <th width="40"><input type="checkbox" id="selectAllCheckbox" onclick="toggleSelectAll(this)"></th>
                                    <th>Rank</th>
                                    <th>Photo</th>
                                    <th>Contestant</th>
                                    <th>Contact Info</th>
                                    <th>Votes</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($contestants)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-secondary">No contestants currently registered.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($contestants as $c): ?>
                                        <tr>
                                            <td><input type="checkbox" name="selected_users[]" value="<?= $c['id'] ?>" class="user-chk"></td>
                                            <td><span class="badge bg-warning text-dark fw-bold">#<?= (int)$c['ranking'] ?></span></td>
                                            <td>
                                                <img src="uploads/<?= e($c['photo']) ?>" alt="<?= e($c['username']) ?>" width="45" height="45" class="rounded-circle object-fit-cover border border-secondary">
                                            </td>
                                            <td>
                                                <div class="fw-bold text-white"><?= e($c['full_name']) ?></div>
                                                <div class="text-secondary small">
                                                    @<?= e($c['username']) ?>
                                                    <?php if (!empty($c['video_url'])): ?>
                                                        <a href="<?= e($c['video_url']) ?>" target="_blank" class="badge bg-danger text-white ms-1 text-decoration-none" title="Watch Video">
                                                            <i class="fas fa-play me-1"></i> Video
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div><?= e($c['email']) ?></div>
                                                <div class="text-secondary small"><?= e($c['phone_number']) ?></div>
                                            </td>
                                            <td>
                                                <!-- Inline Vote Adjustment -->
                                                <form method="POST" class="d-inline-flex align-items-center gap-1">
                                                    <?= Security::csrfField() ?>
                                                    <input type="hidden" name="admin_action" value="update_vote_count">
                                                    <input type="hidden" name="user_id" value="<?= $c['id'] ?>">
                                                    <input type="number" name="vote_count" value="<?= (int)$c['vote_count'] ?>" class="form-control form-control-sm bg-dark border-secondary text-warning fw-bold" style="width: 90px;" min="0">
                                                    <button type="submit" class="btn btn-outline-warning btn-sm" title="Save Vote Count"><i class="fas fa-save"></i></button>
                                                </form>
                                            </td>
                                            <td class="text-end">
                                                <div class="d-inline-flex gap-1">
                                                    <a href="profile.php?id=<?= $c['id'] ?>" target="_blank" class="btn btn-outline-info btn-sm" title="View Public Profile">
                                                        <i class="fas fa-eye"></i>
                                                    </a>

                                                    <!-- Reset Single Contestant Votes -->
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Reset votes for this contestant to 0?');">
                                                        <?= Security::csrfField() ?>
                                                        <input type="hidden" name="admin_action" value="clear_user_votes">
                                                        <input type="hidden" name="user_id" value="<?= $c['id'] ?>">
                                                        <button type="submit" class="btn btn-outline-secondary btn-sm" title="Reset Votes"><i class="fas fa-undo"></i></button>
                                                    </form>

                                                    <!-- Delete Single Contestant -->
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this contestant?');">
                                                        <?= Security::csrfField() ?>
                                                        <input type="hidden" name="admin_action" value="delete_user">
                                                        <input type="hidden" name="user_id" value="<?= $c['id'] ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete"><i class="fas fa-trash-alt"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!empty($contestants)): ?>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete all selected contestants?');">
                                <i class="fas fa-trash-alt me-1"></i> Delete Selected Contestants
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- ==================== TAB 2: FINANCIAL & VOTE LEDGER ==================== -->
        <div class="tab-pane fade" id="paymentsTab">
            <div class="content-panel">
                <h5 class="fw-bold text-white mb-3"><i class="fas fa-receipt text-warning me-2"></i> Verified Paystack Transactions</h5>
                <p class="text-secondary small mb-4">Complete audit trail of verified gateway transactions and associated vote allocations.</p>

                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>Date / Time</th>
                                <th>Reference</th>
                                <th>Contestant Credited</th>
                                <th>Amount Paid</th>
                                <th>Channel</th>
                                <th>Payer / Supporter</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentPayments)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-secondary">No transaction records logged yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentPayments as $p): ?>
                                    <tr>
                                        <td><?= date('M d, Y h:i A', strtotime($p['created_at'])) ?></td>
                                        <td><code><?= e($p['transaction_id']) ?></code></td>
                                        <td>
                                            <?php if (!empty($p['contestant_name'])): ?>
                                                <a href="profile.php?id=<?= $p['user_id'] ?>" target="_blank" class="text-warning text-decoration-none fw-bold">
                                                    <?= e($p['contestant_name']) ?> (@<?= e($p['contestant_username']) ?>)
                                                </a>
                                            <?php else: ?>
                                                <span class="text-secondary">Contestant #<?= (int)$p['user_id'] ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-bold text-success"><?= $currency . number_format((float)$p['amount'], 2) ?></td>
                                        <td><span class="badge bg-secondary"><?= e($p['channel'] ?? 'card') ?></span></td>
                                        <td>
                                            <div><?= e($p['payer_name'] ?: 'N/A') ?></div>
                                            <div class="text-secondary small"><?= e($p['payer_email'] ?: 'No email') ?></div>
                                        </td>
                                        <td><span class="badge bg-success">VERIFIED</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==================== TAB 3: STAGES & RULES SETTINGS ==================== -->
        <div class="tab-pane fade" id="settingsTab">
            <div class="row g-4">
                <!-- Stage Controller -->
                <div class="col-lg-6">
                    <div class="content-panel">
                        <h5 class="fw-bold text-white mb-3"><i class="fas fa-layer-group text-warning me-2"></i> Current Competition Stage</h5>
                        <form method="POST">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="admin_action" value="update_stage">
                            
                            <div class="mb-3">
                                <label class="form-label text-light small fw-semibold">Select Active Stage</label>
                                <select name="stage_name" class="form-select bg-dark border-secondary text-white">
                                    <option value="Stage One" <?= $currentStage === 'Stage One' ? 'selected' : '' ?>>Stage One (Open Qualifiers)</option>
                                    <option value="Stage Two" <?= $currentStage === 'Stage Two' ? 'selected' : '' ?>>Stage Two (Semi-Finals)</option>
                                    <option value="Stage Three" <?= $currentStage === 'Stage Three' ? 'selected' : '' ?>>Stage Three (Grand Finale)</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-gold btn-sm"><i class="fas fa-check me-1"></i> Save Stage</button>
                        </form>
                    </div>

                    <!-- Countdown Timer Config -->
                    <div class="content-panel">
                        <h5 class="fw-bold text-white mb-3"><i class="fas fa-clock text-warning me-2"></i> Stage End Countdown</h5>
                        <form method="POST">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="admin_action" value="update_competition_time">

                            <div class="mb-3">
                                <label class="form-label text-light small fw-semibold">Target End Date & Time</label>
                                <input type="datetime-local" name="competition_end_time" class="form-control bg-dark border-secondary text-white" value="<?= e($competitionEndTime) ?>" required>
                            </div>

                            <button type="submit" class="btn btn-gold btn-sm"><i class="fas fa-calendar-check me-1"></i> Update Timer</button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-6">
                    <!-- Registration Toggle -->
                    <div class="content-panel">
                        <h5 class="fw-bold text-white mb-3"><i class="fas fa-user-plus text-warning me-2"></i> Contestant Registration Status</h5>
                        <p class="text-secondary small">Enable or disable new contestant registrations on the public website.</p>

                        <form method="POST">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="admin_action" value="toggle_registration">
                            <input type="hidden" name="registration_status" value="<?= $isRegOpen ? 0 : 1 ?>">

                            <button type="submit" class="btn <?= $isRegOpen ? 'btn-danger' : 'btn-success' ?>">
                                <i class="fas <?= $isRegOpen ? 'fa-lock' : 'fa-lock-open' ?> me-1"></i>
                                <?= $isRegOpen ? 'Click to CLOSE Registration' : 'Click to OPEN Registration' ?>
                            </button>
                        </form>
                    </div>

                    <!-- Vote Unit Pricing -->
                    <div class="content-panel">
                        <h5 class="fw-bold text-white mb-3"><i class="fas fa-tag text-warning me-2"></i> Vote Unit Price</h5>
                        <form method="POST">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="admin_action" value="update_vote_price">

                            <div class="mb-3">
                                <label class="form-label text-light small fw-semibold">Price per Single Vote (<?= $currency ?>)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-dark border-secondary text-warning"><?= $currency ?></span>
                                    <input type="number" step="1" min="1" name="vote_price" class="form-control bg-dark border-secondary text-white" value="<?= (int)$votePrice ?>" required>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-gold btn-sm"><i class="fas fa-save me-1"></i> Update Pricing</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== TAB 4: BANNERS & SHOWCASE ==================== -->
        <div class="tab-pane fade" id="mediaTab">
            <div class="row g-4">
                <!-- Banner Upload -->
                <div class="col-lg-6">
                    <div class="content-panel">
                        <h5 class="fw-bold text-white mb-3"><i class="fas fa-image text-warning me-2"></i> Update Hero Banner</h5>
                        <form method="POST" enctype="multipart/form-data">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="admin_action" value="upload_banner">

                            <div class="mb-3">
                                <label class="form-label text-light small fw-semibold">Select New Banner Image</label>
                                <input type="file" name="banner_image" accept="image/jpeg,image/png,image/webp" class="form-control bg-dark border-secondary text-white" required>
                                <div class="form-text text-secondary" style="font-size: 11px;">Recommended size: 1200x500px (JPG, PNG, WEBP)</div>
                            </div>

                            <button type="submit" class="btn btn-gold btn-sm"><i class="fas fa-upload me-1"></i> Upload Banner</button>
                        </form>
                    </div>
                </div>

                <!-- Add Showcase Competition -->
                <div class="col-lg-6">
                    <div class="content-panel">
                        <h5 class="fw-bold text-white mb-3"><i class="fas fa-plus-circle text-warning me-2"></i> Add Showcase Competition</h5>
                        <form method="POST" enctype="multipart/form-data">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="admin_action" value="add_competition">

                            <div class="mb-3">
                                <label class="form-label text-light small fw-semibold">Competition Title</label>
                                <input type="text" name="title" class="form-control bg-dark border-secondary text-white" placeholder="e.g. Summer Edition 2026" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-light small fw-semibold">Description</label>
                                <textarea name="description" rows="2" class="form-control bg-dark border-secondary text-white" placeholder="Brief summary" required></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-light small fw-semibold">Cover Image</label>
                                <input type="file" name="image" accept="image/*" class="form-control bg-dark border-secondary text-white" required>
                            </div>

                            <button type="submit" class="btn btn-gold btn-sm"><i class="fas fa-plus me-1"></i> Publish Competition</button>
                        </form>
                    </div>
                </div>

                <!-- List Showcase Competitions -->
                <div class="col-12">
                    <div class="content-panel">
                        <h5 class="fw-bold text-white mb-3">Published Showcase Competitions</h5>
                        <div class="table-responsive">
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>Image</th>
                                        <th>Title</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($competitions)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-3 text-secondary">No showcase competitions added yet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($competitions as $comp): ?>
                                            <tr>
                                                <td><img src="<?= e($comp['image_path']) ?>" width="80" height="50" class="rounded object-fit-cover border border-secondary"></td>
                                                <td class="fw-bold text-white"><?= e($comp['title']) ?></td>
                                                <td class="small text-secondary"><?= e($comp['description']) ?></td>
                                                <td><span class="badge bg-success"><?= strtoupper(e($comp['status'])) ?></span></td>
                                                <td class="text-end">
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this competition card?');">
                                                        <?= Security::csrfField() ?>
                                                        <input type="hidden" name="admin_action" value="delete_competition">
                                                        <input type="hidden" name="competition_id" value="<?= $comp['id'] ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i></button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="assets2/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSelectAll(master) {
    const checkboxes = document.querySelectorAll('.user-chk');
    checkboxes.forEach(chk => chk.checked = master.checked);
}
</script>

</body>
</html>

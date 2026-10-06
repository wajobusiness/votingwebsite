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

            case 'add_book':
                $title = trim($_POST['title'] ?? '');
                $author = trim($_POST['author'] ?? 'Crown Night Star');
                $category = trim($_POST['category'] ?? 'General');
                $price = (float)($_POST['price'] ?? 0);
                $shortDesc = trim($_POST['short_description'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $previewText = trim($_POST['preview_text'] ?? '');
                $pagesCount = (int)($_POST['pages_count'] ?? 100);
                $deliveryType = $_POST['delivery_type'] ?? 'whatsapp';
                $downloadLink = trim($_POST['download_link'] ?? '');
                $whatsappNumber = trim($_POST['whatsapp_number'] ?? '');

                if (empty($title) || empty($description)) {
                    $_SESSION['flash_error'] = "Book title and description are required.";
                    break;
                }

                $coverImage = 'assets2/images/book1.jpg';
                if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                    $upload = Security::handleFileUpload($_FILES['cover_image'], __DIR__ . '/uploads/books/', ['jpg', 'jpeg', 'png', 'webp'], 10);
                    if ($upload['success']) {
                        $coverImage = 'uploads/books/' . $upload['filename'];
                    } else {
                        $_SESSION['flash_error'] = "Cover image upload failed: " . $upload['error'];
                        break;
                    }
                }

                $pdfFile = null;
                if ($deliveryType === 'pdf' && isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
                    $pdfUpload = Security::handleFileUpload($_FILES['pdf_file'], __DIR__ . '/uploads/books/', ['pdf'], 50);
                    if ($pdfUpload['success']) {
                        $pdfFile = $pdfUpload['filename'];
                    }
                }

                $stmt = $pdo->prepare("
                    INSERT INTO books (
                        title, author, category, cover_image, description, short_description,
                        price, delivery_type, pdf_file, download_link, whatsapp_number, preview_text, pages_count, is_active
                    ) VALUES (
                        :title, :author, :category, :cover_image, :description, :short_description,
                        :price, :delivery_type, :pdf_file, :download_link, :whatsapp_number, :preview_text, :pages_count, 1
                    )
                ");

                $stmt->execute([
                    ':title'             => $title,
                    ':author'            => $author,
                    ':category'          => $category,
                    ':cover_image'       => $coverImage,
                    ':description'       => $description,
                    ':short_description' => $shortDesc,
                    ':price'             => $price,
                    ':delivery_type'     => $deliveryType,
                    ':pdf_file'          => $pdfFile,
                    ':download_link'     => !empty($downloadLink) ? $downloadLink : null,
                    ':whatsapp_number'   => !empty($whatsappNumber) ? $whatsappNumber : null,
                    ':preview_text'      => $previewText,
                    ':pages_count'       => $pagesCount
                ]);

                $_SESSION['flash_success'] = "Digital book publication added successfully!";
                break;

            case 'update_book':
                $bookId = filter_input(INPUT_POST, 'book_id', FILTER_VALIDATE_INT);
                if (!$bookId) {
                    $_SESSION['flash_error'] = "Invalid book ID.";
                    break;
                }

                $title = trim($_POST['title'] ?? '');
                $author = trim($_POST['author'] ?? 'Crown Night Star');
                $category = trim($_POST['category'] ?? 'General');
                $price = (float)($_POST['price'] ?? 0);
                $shortDesc = trim($_POST['short_description'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $previewText = trim($_POST['preview_text'] ?? '');
                $pagesCount = (int)($_POST['pages_count'] ?? 100);
                $deliveryType = $_POST['delivery_type'] ?? 'whatsapp';
                $downloadLink = trim($_POST['download_link'] ?? '');
                $whatsappNumber = trim($_POST['whatsapp_number'] ?? '');
                $isActive = isset($_POST['is_active']) ? 1 : 0;

                $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
                $stmt->execute([$bookId]);
                $existingBook = $stmt->fetch();
                if (!$existingBook) {
                    $_SESSION['flash_error'] = "Book not found.";
                    break;
                }

                $coverImage = $existingBook['cover_image'];
                if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                    $upload = Security::handleFileUpload($_FILES['cover_image'], __DIR__ . '/uploads/books/', ['jpg', 'jpeg', 'png', 'webp'], 10);
                    if ($upload['success']) {
                        $coverImage = 'uploads/books/' . $upload['filename'];
                    }
                }

                $pdfFile = $existingBook['pdf_file'];
                if ($deliveryType === 'pdf' && isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
                    $pdfUpload = Security::handleFileUpload($_FILES['pdf_file'], __DIR__ . '/uploads/books/', ['pdf'], 50);
                    if ($pdfUpload['success']) {
                        $pdfFile = $pdfUpload['filename'];
                    }
                }

                $stmt = $pdo->prepare("
                    UPDATE books SET
                        title = :title,
                        author = :author,
                        category = :category,
                        cover_image = :cover_image,
                        description = :description,
                        short_description = :short_description,
                        price = :price,
                        delivery_type = :delivery_type,
                        pdf_file = :pdf_file,
                        download_link = :download_link,
                        whatsapp_number = :whatsapp_number,
                        preview_text = :preview_text,
                        pages_count = :pages_count,
                        is_active = :is_active
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':title'             => $title,
                    ':author'            => $author,
                    ':category'          => $category,
                    ':cover_image'       => $coverImage,
                    ':description'       => $description,
                    ':short_description' => $shortDesc,
                    ':price'             => $price,
                    ':delivery_type'     => $deliveryType,
                    ':pdf_file'          => $pdfFile,
                    ':download_link'     => !empty($downloadLink) ? $downloadLink : null,
                    ':whatsapp_number'   => !empty($whatsappNumber) ? $whatsappNumber : null,
                    ':preview_text'      => $previewText,
                    ':pages_count'       => $pagesCount,
                    ':is_active'         => $isActive,
                    ':id'                => $bookId
                ]);

                $_SESSION['flash_success'] = "Book details updated successfully!";
                break;

            case 'delete_book':
                $bookId = filter_input(INPUT_POST, 'book_id', FILTER_VALIDATE_INT);
                if ($bookId) {
                    $stmt = $pdo->prepare("DELETE FROM books WHERE id = ?");
                    $stmt->execute([$bookId]);
                    $_SESSION['flash_success'] = "Book removed from catalog.";
                }
                break;

            case 'toggle_book_status':
                $bookId = filter_input(INPUT_POST, 'book_id', FILTER_VALIDATE_INT);
                if ($bookId) {
                    $stmt = $pdo->prepare("UPDATE books SET is_active = NOT is_active WHERE id = ?");
                    $stmt->execute([$bookId]);
                    $_SESSION['flash_success'] = "Book visibility status updated.";
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

// 5. Digital Books Catalog (Fail-Safe)
$books = [];
try {
    $books = BookstoreService::getAllBooks();
} catch (Exception $e) {
    error_log("Books query error: " . $e->getMessage());
    $books = [];
}

// 6. Recent Digital Book Purchases (Fail-Safe)
$bookPurchases = [];
$totalBookSales = 0.0;
try {
    $bookPurchases = BookstoreService::getAllPurchases(50);
    $totalBookSales = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM book_purchases WHERE status = 'success'")->fetchColumn();
} catch (Exception $e) {
    $bookPurchases = [];
    $totalBookSales = 0.0;
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
        <li class="nav-item">
            <button class="nav-link" id="bookstore-tab" data-bs-toggle="tab" data-bs-target="#bookstoreTab">
                <i class="fas fa-book-open me-1"></i> Digital Bookstore (<?= count($books) ?>)
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

        <!-- ==================== TAB 5: DIGITAL BOOKSTORE ==================== -->
        <div class="tab-pane fade" id="bookstoreTab">
            <div class="row g-4">
                <!-- Header & Quick Actions -->
                <div class="col-12">
                    <div class="content-panel d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <h4 class="fw-bold text-white mb-1"><i class="fas fa-book-open text-warning me-2"></i> Digital Bookstore Catalog</h4>
                            <p class="text-secondary small mb-0">Manage digital publications, masterclasses, guides, pricing, and automated fulfillment.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="bookstore.php" target="_blank" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-external-link-alt me-1"></i> Preview Storefront
                            </a>
                            <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#addBookModal">
                                <i class="fas fa-plus-circle me-1"></i> Add New Book
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Books Table -->
                <div class="col-12">
                    <div class="content-panel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold text-white mb-0">Publications (<?= count($books) ?>)</h5>
                            <span class="badge bg-warning text-dark"><?= count(array_filter($books, function($b) { return !empty($b['is_active']); })) ?> Active for Sale</span>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th style="width: 70px;">Cover</th>
                                        <th>Title & Details</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Delivery Type</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($books)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-secondary">
                                                <i class="fas fa-book-open fa-2x mb-2 d-block opacity-50"></i>
                                                No publications in catalog yet. Click "Add New Book" to create one.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($books as $b): ?>
                                            <tr>
                                                <td>
                                                    <img src="<?= e($b['cover_image']) ?>" alt="Book Cover" class="rounded border border-secondary shadow-sm object-fit-cover" style="width: 50px; height: 70px;">
                                                </td>
                                                <td>
                                                    <div class="fw-bold text-white fs-6"><?= e($b['title']) ?></div>
                                                    <div class="small text-secondary">
                                                        <i class="fas fa-user-edit me-1"></i> <?= e($b['author'] ?? 'Crown Night Star') ?> &bull; 
                                                        <i class="fas fa-file-alt ms-1 me-1"></i> <?= (int)$b['pages_count'] ?> pages
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-dark border border-secondary text-info"><?= e($b['category']) ?></span>
                                                </td>
                                                <td>
                                                    <span class="fw-bold text-warning fs-6"><?= $currency . number_format((float)$b['price'], 2) ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($b['delivery_type'] === 'whatsapp'): ?>
                                                        <span class="badge bg-success"><i class="fab fa-whatsapp me-1"></i> WhatsApp Order</span>
                                                    <?php elseif ($b['delivery_type'] === 'pdf'): ?>
                                                        <span class="badge bg-danger"><i class="fas fa-file-pdf me-1"></i> Direct PDF</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-primary"><i class="fas fa-link me-1"></i> External Link</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <?= Security::csrfField() ?>
                                                        <input type="hidden" name="admin_action" value="toggle_book_status">
                                                        <input type="hidden" name="book_id" value="<?= (int)$b['id'] ?>">
                                                        <button type="submit" class="btn btn-sm <?= !empty($b['is_active']) ? 'btn-success' : 'btn-outline-secondary' ?> py-0 px-2" style="font-size: 11px;">
                                                            <?= !empty($b['is_active']) ? '<i class="fas fa-check-circle me-1"></i> Active' : '<i class="fas fa-eye-slash me-1"></i> Draft' ?>
                                                        </button>
                                                    </form>
                                                </td>
                                                <td class="text-end">
                                                    <div class="d-flex justify-content-end gap-1">
                                                        <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editBookModal_<?= (int)$b['id'] ?>" title="Edit Book">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Permanently delete \'<?= addslashes(e($b['title'])) ?>\'?');">
                                                            <?= Security::csrfField() ?>
                                                            <input type="hidden" name="admin_action" value="delete_book">
                                                            <input type="hidden" name="book_id" value="<?= (int)$b['id'] ?>">
                                                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Book">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Book Sales & Customer Delivery Ledger -->
                <div class="col-12">
                    <div class="content-panel">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                            <div>
                                <h5 class="fw-bold text-white mb-0"><i class="fas fa-receipt text-warning me-2"></i> Verified Book Orders & Customer Access (<?= count($bookPurchases) ?>)</h5>
                                <p class="text-secondary small mb-0">Total Book Sales Revenue: <strong class="text-success"><?= $currency . number_format($totalBookSales, 2) ?></strong></p>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Publication</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Reference</th>
                                        <th>Access Hub</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($bookPurchases)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-secondary">
                                                <i class="fas fa-shopping-cart fa-2x mb-2 d-block opacity-50"></i>
                                                No book orders recorded yet. Once customers order via Paystack, their transactions and access links will appear here.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($bookPurchases as $bp): ?>
                                            <tr>
                                                <td class="small text-secondary"><?= date('M d, Y h:i A', strtotime($bp['created_at'])) ?></td>
                                                <td>
                                                    <div class="fw-bold text-white small"><?= e($bp['book_title']) ?></div>
                                                    <span class="badge bg-dark text-secondary border border-secondary" style="font-size: 10px;"><?= strtoupper(e($bp['delivery_type'])) ?></span>
                                                </td>
                                                <td>
                                                    <div class="text-white small fw-bold"><?= e($bp['buyer_name']) ?></div>
                                                    <div class="text-secondary small" style="font-size: 11px;"><i class="fas fa-envelope me-1"></i> <?= e($bp['buyer_email']) ?></div>
                                                    <?php if (!empty($bp['buyer_phone'])): ?>
                                                        <div class="text-secondary small" style="font-size: 11px;"><i class="fas fa-phone me-1"></i> <?= e($bp['buyer_phone']) ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="fw-bold text-warning"><?= $currency . number_format((float)$bp['amount'], 2) ?></span>
                                                </td>
                                                <td>
                                                    <code class="text-info small"><?= e($bp['reference']) ?></code>
                                                </td>
                                                <td>
                                                    <a href="order_success.php?token=<?= urlencode($bp['access_token']) ?>" target="_blank" class="btn btn-outline-warning btn-sm py-1 px-2" style="font-size: 11px;" title="Open Customer Access Hub">
                                                        <i class="fas fa-external-link-alt me-1"></i> View Access Hub
                                                    </a>
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

<!-- ==================== ADD BOOK MODAL ==================== -->
<div class="modal fade" id="addBookModal" tabindex="-1" aria-labelledby="addBookModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content bg-dark text-white border border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-warning" id="addBookModalLabel"><i class="fas fa-plus-circle me-2"></i> Add Digital Publication</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <?= Security::csrfField() ?>
                <input type="hidden" name="admin_action" value="add_book">

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold text-light">Book Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control bg-dark border-secondary text-white" placeholder="e.g. The Crown Within" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-light">Author</label>
                            <input type="text" name="author" class="form-control bg-dark border-secondary text-white" value="Crown Night Star" placeholder="Author name">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-light">Category</label>
                            <select name="category" class="form-select bg-dark border-secondary text-white">
                                <option value="Pageantry & Leadership">Pageantry & Leadership</option>
                                <option value="Personal Branding">Personal Branding</option>
                                <option value="Digital Marketing">Digital Marketing</option>
                                <option value="Modeling & Fashion">Modeling & Fashion</option>
                                <option value="Self Development">Self Development</option>
                                <option value="General">General</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-light">Price (<?= $currency ?>) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-warning"><?= $currency ?></span>
                                <input type="number" step="100" min="0" name="price" class="form-control bg-dark border-secondary text-white" placeholder="3500" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-light">Estimated Pages</label>
                            <input type="number" name="pages_count" class="form-control bg-dark border-secondary text-white" value="120" min="1">
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-light">Cover Image</label>
                            <input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp" class="form-control bg-dark border-secondary text-white">
                            <div class="form-text text-secondary" style="font-size: 11px;">Recommended: 600x850px (JPG, PNG, WEBP). If omitted, default cover is used.</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-light">Short Catchy Summary (1-2 sentences)</label>
                            <input type="text" name="short_description" class="form-control bg-dark border-secondary text-white" placeholder="Brief tagline shown on book cards">
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-light">Full Synopsis / Description <span class="text-danger">*</span></label>
                            <textarea name="description" rows="4" class="form-control bg-dark border-secondary text-white" placeholder="Detailed book breakdown, what readers will learn, chapter breakdown..." required></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-light">Sample Excerpt / Preview Text (Optional)</label>
                            <textarea name="preview_text" rows="3" class="form-control bg-dark border-secondary text-white" placeholder="First chapter sneak peek or introductory excerpt..."></textarea>
                        </div>

                        <!-- Fulfillment Options -->
                        <div class="col-12 pt-2 border-top border-secondary">
                            <h6 class="fw-bold text-warning mb-2"><i class="fas fa-truck-loading me-1"></i> Delivery & Fulfillment Mode</h6>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-light">Fulfillment Channel</label>
                            <select name="delivery_type" class="form-select bg-dark border-secondary text-white" id="add_delivery_type" onchange="updateDeliveryFields('add')">
                                <option value="whatsapp">WhatsApp Order (Direct Chat)</option>
                                <option value="pdf">Direct PDF Download</option>
                                <option value="link">External Access Link</option>
                            </select>
                        </div>

                        <div class="col-md-8" id="add_whatsapp_box">
                            <label class="form-label small fw-semibold text-light">WhatsApp Order Number</label>
                            <input type="text" name="whatsapp_number" class="form-control bg-dark border-secondary text-white" value="<?= e(Settings::get('support_phone', '09067619370')) ?>" placeholder="e.g. 09067619370">
                            <div class="form-text text-secondary" style="font-size: 11px;">Buyers will be redirected to WhatsApp with prefilled title and price.</div>
                        </div>

                        <div class="col-md-8 d-none" id="add_pdf_box">
                            <label class="form-label small fw-semibold text-light">Upload Digital PDF File</label>
                            <input type="file" name="pdf_file" accept="application/pdf" class="form-control bg-dark border-secondary text-white">
                            <div class="form-text text-secondary" style="font-size: 11px;">Securely delivered via authenticated download link.</div>
                        </div>

                        <div class="col-md-8 d-none" id="add_link_box">
                            <label class="form-label small fw-semibold text-light">External Download / Access URL</label>
                            <input type="url" name="download_link" class="form-control bg-dark border-secondary text-white" placeholder="https://drive.google.com/... or Gumroad/Selar URL">
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-gold btn-sm"><i class="fas fa-cloud-upload-alt me-1"></i> Publish Digital Book</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== EDIT BOOK MODALS ==================== -->
<?php foreach ($books as $b): ?>
<div class="modal fade" id="editBookModal_<?= (int)$b['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content bg-dark text-white border border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-warning"><i class="fas fa-edit me-2"></i> Edit Publication: <?= e($b['title']) ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <?= Security::csrfField() ?>
                <input type="hidden" name="admin_action" value="update_book">
                <input type="hidden" name="book_id" value="<?= (int)$b['id'] ?>">

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold text-light">Book Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control bg-dark border-secondary text-white" value="<?= e($b['title']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-light">Author</label>
                            <input type="text" name="author" class="form-control bg-dark border-secondary text-white" value="<?= e($b['author']) ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-light">Category</label>
                            <select name="category" class="form-select bg-dark border-secondary text-white">
                                <?php 
                                $cats = ['Pageantry & Leadership', 'Personal Branding', 'Digital Marketing', 'Modeling & Fashion', 'Self Development', 'General'];
                                foreach ($cats as $cat): ?>
                                    <option value="<?= e($cat) ?>" <?= $b['category'] === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-light">Price (<?= $currency ?>) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-warning"><?= $currency ?></span>
                                <input type="number" step="100" min="0" name="price" class="form-control bg-dark border-secondary text-white" value="<?= (float)$b['price'] ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-light">Estimated Pages</label>
                            <input type="number" name="pages_count" class="form-control bg-dark border-secondary text-white" value="<?= (int)$b['pages_count'] ?>" min="1">
                        </div>

                        <div class="col-12">
                            <div class="d-flex align-items-center gap-3">
                                <img src="<?= e($b['cover_image']) ?>" width="45" height="60" class="rounded border border-secondary object-fit-cover">
                                <div class="flex-grow-1">
                                    <label class="form-label small fw-semibold text-light">Replace Cover Image (Optional)</label>
                                    <input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp" class="form-control bg-dark border-secondary text-white">
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-light">Short Catchy Summary</label>
                            <input type="text" name="short_description" class="form-control bg-dark border-secondary text-white" value="<?= e($b['short_description'] ?? '') ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-light">Full Synopsis / Description <span class="text-danger">*</span></label>
                            <textarea name="description" rows="4" class="form-control bg-dark border-secondary text-white" required><?= e($b['description']) ?></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-light">Sample Excerpt / Preview Text</label>
                            <textarea name="preview_text" rows="3" class="form-control bg-dark border-secondary text-white"><?= e($b['preview_text'] ?? '') ?></textarea>
                        </div>

                        <!-- Fulfillment Options -->
                        <div class="col-12 pt-2 border-top border-secondary">
                            <h6 class="fw-bold text-warning mb-2"><i class="fas fa-truck-loading me-1"></i> Delivery & Fulfillment Mode</h6>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-light">Fulfillment Channel</label>
                            <select name="delivery_type" class="form-select bg-dark border-secondary text-white" id="edit_delivery_type_<?= $b['id'] ?>" onchange="updateDeliveryFields('edit_<?= $b['id'] ?>')">
                                <option value="whatsapp" <?= $b['delivery_type'] === 'whatsapp' ? 'selected' : '' ?>>WhatsApp Order</option>
                                <option value="pdf" <?= $b['delivery_type'] === 'pdf' ? 'selected' : '' ?>>Direct PDF Download</option>
                                <option value="link" <?= $b['delivery_type'] === 'link' ? 'selected' : '' ?>>External Access Link</option>
                            </select>
                        </div>

                        <div class="col-md-8 <?= $b['delivery_type'] !== 'whatsapp' ? 'd-none' : '' ?>" id="edit_<?= $b['id'] ?>_whatsapp_box">
                            <label class="form-label small fw-semibold text-light">WhatsApp Order Number</label>
                            <input type="text" name="whatsapp_number" class="form-control bg-dark border-secondary text-white" value="<?= e($b['whatsapp_number'] ?? Settings::get('support_phone', '09067619370')) ?>">
                        </div>

                        <div class="col-md-8 <?= $b['delivery_type'] !== 'pdf' ? 'd-none' : '' ?>" id="edit_<?= $b['id'] ?>_pdf_box">
                            <label class="form-label small fw-semibold text-light">Replace PDF File (<?= !empty($b['pdf_file']) ? 'Current: ' . e($b['pdf_file']) : 'No PDF attached' ?>)</label>
                            <input type="file" name="pdf_file" accept="application/pdf" class="form-control bg-dark border-secondary text-white">
                        </div>

                        <div class="col-md-8 <?= $b['delivery_type'] !== 'link' ? 'd-none' : '' ?>" id="edit_<?= $b['id'] ?>_link_box">
                            <label class="form-label small fw-semibold text-light">External Download / Access URL</label>
                            <input type="url" name="download_link" class="form-control bg-dark border-secondary text-white" value="<?= e($b['download_link'] ?? '') ?>">
                        </div>

                        <div class="col-12 mt-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="active_<?= $b['id'] ?>" <?= !empty($b['is_active']) ? 'checked' : '' ?>>
                                <label class="form-check-label text-light fw-semibold" for="active_<?= $b['id'] ?>">Active & Available in Bookstore</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-gold btn-sm"><i class="fas fa-save me-1"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script src="assets2/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSelectAll(master) {
    const checkboxes = document.querySelectorAll('.user-chk');
    checkboxes.forEach(chk => chk.checked = master.checked);
}

function updateDeliveryFields(prefix) {
    const select = document.getElementById(prefix === 'add' ? 'add_delivery_type' : prefix + '_delivery_type' || (prefix.startsWith('edit_') ? 'edit_delivery_type_' + prefix.replace('edit_', '') : ''));
    if (!select) return;
    const type = select.value;
    
    const waBox = document.getElementById(prefix + '_whatsapp_box');
    const pdfBox = document.getElementById(prefix + '_pdf_box');
    const linkBox = document.getElementById(prefix + '_link_box');

    if (waBox) waBox.classList.toggle('d-none', type !== 'whatsapp');
    if (pdfBox) pdfBox.classList.toggle('d-none', type !== 'pdf');
    if (linkBox) linkBox.classList.toggle('d-none', type !== 'link');
}
</script>

</body>
</html>

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

            case 'update_registration_fee':
                $fee = filter_input(INPUT_POST, 'registration_fee', FILTER_VALIDATE_FLOAT);
                if ($fee !== false && $fee >= 0) {
                    Settings::set('registration_fee', (string)$fee);
                    $_SESSION['flash_success'] = "Contestant Registration Fee updated to: " . Settings::getCurrencySymbol() . number_format($fee, 2) . ($fee == 0 ? ' (Free Registration)' : '');
                } else {
                    $_SESSION['flash_error'] = "Invalid registration fee amount.";
                }
                break;

            case 'update_user_registration_status':
                $targetUserId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
                $regStatus = trim($_POST['registration_status'] ?? '');
                $regAmount = isset($_POST['fee_paid']) ? (float)$_POST['fee_paid'] : null;
                $regRef = trim($_POST['payment_ref'] ?? '');
                if ($targetUserId && in_array($regStatus, ['paid', 'pending', 'exempt'], true)) {
                    $res = RegistrationService::manuallyUpdateStatus($targetUserId, $regStatus, $regAmount, !empty($regRef) ? $regRef : null);
                    if ($res['success']) {
                        $_SESSION['flash_success'] = $res['message'];
                    } else {
                        $_SESSION['flash_error'] = $res['error'];
                    }
                } else {
                    $_SESSION['flash_error'] = "Invalid parameters for updating registration status.";
                }
                break;

            case 'export_registrations':
                $regs = RegistrationService::getAllRegistrations('all', null, 5000);
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=contestants_registration_report_' . date('Y_m_d_His') . '.csv');
                $outCsv = fopen('php://output', 'w');
                fputcsv($outCsv, ['ID', 'Full Name', 'Username', 'Email', 'Phone Number', 'Registration Status', 'Fee Paid', 'Payment Ref', 'Paid Date', 'Votes', 'Registered Date']);
                foreach ($regs as $r) {
                    fputcsv($outCsv, [
                        $r['id'],
                        $r['full_name'],
                        $r['username'],
                        $r['email'],
                        $r['phone_number'],
                        strtoupper($r['registration_status'] ?? 'PAID'),
                        $r['registration_fee_paid'] ?? '0.00',
                        $r['registration_payment_ref'] ?? '',
                        $r['registration_paid_at'] ?? '',
                        $r['vote_count'],
                        $r['created_at']
                    ]);
                }
                fclose($outCsv);
                exit();

            case 'update_vote_price':
                $price = filter_input(INPUT_POST, 'vote_price', FILTER_VALIDATE_FLOAT);
                if ($price && $price > 0) {
                    Settings::set('vote_price', (string)$price);
                    $_SESSION['flash_success'] = "Vote unit price updated to: " . Settings::getCurrencySymbol() . number_format($price, 2);
                }
                break;

            case 'upload_banner':
                if (isset($_FILES['banner_image'])) {
                    $upload = Security::handleFileUpload($_FILES['banner_image'], __DIR__ . '/uploads/', ['jpg', 'jpeg', 'png', 'webp'], 10);
                    if ($upload['success']) {
                        $targetPath = 'uploads/' . $upload['filename'];
                        $linkUrl = trim($_POST['link_url'] ?? '');
                        $pdo->exec("UPDATE banner SET is_active = 0");
                        $stmt = $pdo->prepare("INSERT INTO banner (image_path, link_url, is_active) VALUES (?, ?, 1)");
                        $stmt->execute([$targetPath, !empty($linkUrl) ? $linkUrl : null]);
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

            // ==================== CONTEST BATTLES ACTIONS ====================
            case 'add_battle':
                $title = trim($_POST['title'] ?? '');
                $category = trim($_POST['category'] ?? 'Talent Battle');
                $description = trim($_POST['description'] ?? '');
                $c1Name = trim($_POST['contestant_one_name'] ?? '');
                $c2Name = trim($_POST['contestant_two_name'] ?? '');
                $battleDate = trim($_POST['battle_date'] ?? date('Y-m-d'));
                $battleTime = trim($_POST['battle_time'] ?? '08:00 PM');
                $venueType = in_array($_POST['venue_type'] ?? '', ['online', 'physical'], true) ? $_POST['venue_type'] : 'online';
                $platform = trim($_POST['platform'] ?? 'Instagram Live');
                $liveUrl = trim($_POST['live_url'] ?? '');
                $venueName = trim($_POST['venue_name'] ?? '');
                $venueAddress = trim($_POST['venue_address'] ?? '');
                $venueCity = trim($_POST['venue_city'] ?? '');
                $venueState = trim($_POST['venue_state'] ?? '');
                $mapsUrl = trim($_POST['maps_url'] ?? '');
                $status = in_array($_POST['status'] ?? '', ['upcoming', 'live', 'ended', 'cancelled'], true) ? $_POST['status'] : 'upcoming';
                $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
                $isPublished = isset($_POST['is_published']) ? 1 : 0;
                $displayOrder = (int)($_POST['display_order'] ?? 0);

                if (empty($title) || empty($c1Name) || empty($c2Name)) {
                    $_SESSION['flash_error'] = "Battle title and both contestant names are required.";
                    break;
                }

                $c1Image = 'uploads/default_avatar.png';
                if (isset($_FILES['contestant_one_image']) && $_FILES['contestant_one_image']['error'] === UPLOAD_ERR_OK) {
                    $upload1 = Security::handleFileUpload($_FILES['contestant_one_image'], __DIR__ . '/uploads/battles/', ['jpg', 'jpeg', 'png', 'webp'], 10);
                    if ($upload1['success']) {
                        $c1Image = 'uploads/battles/' . $upload1['filename'];
                    }
                }

                $c2Image = 'uploads/default_avatar.png';
                if (isset($_FILES['contestant_two_image']) && $_FILES['contestant_two_image']['error'] === UPLOAD_ERR_OK) {
                    $upload2 = Security::handleFileUpload($_FILES['contestant_two_image'], __DIR__ . '/uploads/battles/', ['jpg', 'jpeg', 'png', 'webp'], 10);
                    if ($upload2['success']) {
                        $c2Image = 'uploads/battles/' . $upload2['filename'];
                    }
                }

                $bannerImage = null;
                if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
                    $uploadBanner = Security::handleFileUpload($_FILES['banner_image'], __DIR__ . '/uploads/battles/', ['jpg', 'jpeg', 'png', 'webp'], 10);
                    if ($uploadBanner['success']) {
                        $bannerImage = 'uploads/battles/' . $uploadBanner['filename'];
                    }
                }

                $res = BattlesService::createBattle([
                    'title'                => $title,
                    'category'             => $category,
                    'description'          => $description,
                    'contestant_one_name'  => $c1Name,
                    'contestant_one_image' => $c1Image,
                    'contestant_two_name'  => $c2Name,
                    'contestant_two_image' => $c2Image,
                    'banner_image'         => $bannerImage,
                    'battle_date'          => $battleDate,
                    'battle_time'          => $battleTime,
                    'venue_type'           => $venueType,
                    'platform'             => $platform,
                    'live_url'             => $liveUrl,
                    'venue_name'           => $venueName,
                    'venue_address'        => $venueAddress,
                    'venue_city'           => $venueCity,
                    'venue_state'          => $venueState,
                    'maps_url'             => $mapsUrl,
                    'status'               => $status,
                    'is_featured'          => $isFeatured,
                    'is_published'         => $isPublished,
                    'display_order'        => $displayOrder
                ]);

                if ($res['success']) {
                    $_SESSION['flash_success'] = "Contest Battle created successfully!";
                } else {
                    $_SESSION['flash_error'] = "Failed to create battle: " . ($res['error'] ?? 'Unknown error');
                }
                break;

            case 'update_battle':
                $battleId = filter_input(INPUT_POST, 'battle_id', FILTER_VALIDATE_INT);
                if (!$battleId) {
                    $_SESSION['flash_error'] = "Invalid battle ID.";
                    break;
                }

                $existingBattle = BattlesService::getBattleById($battleId);
                if (!$existingBattle) {
                    $_SESSION['flash_error'] = "Battle not found.";
                    break;
                }

                $c1Image = $existingBattle['contestant_one_image'];
                if (isset($_FILES['contestant_one_image']) && $_FILES['contestant_one_image']['error'] === UPLOAD_ERR_OK) {
                    $upload1 = Security::handleFileUpload($_FILES['contestant_one_image'], __DIR__ . '/uploads/battles/', ['jpg', 'jpeg', 'png', 'webp'], 10);
                    if ($upload1['success']) {
                        $c1Image = 'uploads/battles/' . $upload1['filename'];
                    }
                }

                $c2Image = $existingBattle['contestant_two_image'];
                if (isset($_FILES['contestant_two_image']) && $_FILES['contestant_two_image']['error'] === UPLOAD_ERR_OK) {
                    $upload2 = Security::handleFileUpload($_FILES['contestant_two_image'], __DIR__ . '/uploads/battles/', ['jpg', 'jpeg', 'png', 'webp'], 10);
                    if ($upload2['success']) {
                        $c2Image = 'uploads/battles/' . $upload2['filename'];
                    }
                }

                $bannerImage = $existingBattle['banner_image'];
                if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
                    $uploadBanner = Security::handleFileUpload($_FILES['banner_image'], __DIR__ . '/uploads/battles/', ['jpg', 'jpeg', 'png', 'webp'], 10);
                    if ($uploadBanner['success']) {
                        $bannerImage = 'uploads/battles/' . $uploadBanner['filename'];
                    }
                }

                $res = BattlesService::updateBattle($battleId, [
                    'title'                => trim($_POST['title'] ?? ''),
                    'category'             => trim($_POST['category'] ?? 'Talent Battle'),
                    'description'          => trim($_POST['description'] ?? ''),
                    'contestant_one_name'  => trim($_POST['contestant_one_name'] ?? ''),
                    'contestant_one_image' => $c1Image,
                    'contestant_two_name'  => trim($_POST['contestant_two_name'] ?? ''),
                    'contestant_two_image' => $c2Image,
                    'banner_image'         => $bannerImage,
                    'battle_date'          => trim($_POST['battle_date'] ?? ''),
                    'battle_time'          => trim($_POST['battle_time'] ?? ''),
                    'venue_type'           => in_array($_POST['venue_type'] ?? '', ['online', 'physical'], true) ? $_POST['venue_type'] : 'online',
                    'platform'             => trim($_POST['platform'] ?? ''),
                    'live_url'             => trim($_POST['live_url'] ?? ''),
                    'venue_name'           => trim($_POST['venue_name'] ?? ''),
                    'venue_address'        => trim($_POST['venue_address'] ?? ''),
                    'venue_city'           => trim($_POST['venue_city'] ?? ''),
                    'venue_state'          => trim($_POST['venue_state'] ?? ''),
                    'maps_url'             => trim($_POST['maps_url'] ?? ''),
                    'status'               => in_array($_POST['status'] ?? '', ['upcoming', 'live', 'ended', 'cancelled'], true) ? $_POST['status'] : 'upcoming',
                    'is_featured'          => isset($_POST['is_featured']) ? 1 : 0,
                    'is_published'         => isset($_POST['is_published']) ? 1 : 0,
                    'display_order'        => (int)($_POST['display_order'] ?? 0)
                ]);

                if ($res['success']) {
                    $_SESSION['flash_success'] = "Battle details updated successfully!";
                } else {
                    $_SESSION['flash_error'] = "Failed to update battle: " . ($res['error'] ?? 'Unknown error');
                }
                break;

            case 'delete_battle':
                $battleId = filter_input(INPUT_POST, 'battle_id', FILTER_VALIDATE_INT);
                if ($battleId) {
                    BattlesService::deleteBattle($battleId);
                    $_SESSION['flash_success'] = "Battle removed successfully.";
                }
                break;

            case 'toggle_battle_publish':
                $battleId = filter_input(INPUT_POST, 'battle_id', FILTER_VALIDATE_INT);
                if ($battleId) {
                    BattlesService::togglePublish($battleId);
                    $_SESSION['flash_success'] = "Battle publish status updated.";
                }
                break;

            case 'toggle_battle_featured':
                $battleId = filter_input(INPUT_POST, 'battle_id', FILTER_VALIDATE_INT);
                if ($battleId) {
                    BattlesService::toggleFeatured($battleId);
                    $_SESSION['flash_success'] = "Battle featured status updated.";
                }
                break;

            case 'reorder_battle':
                $battleId = filter_input(INPUT_POST, 'battle_id', FILTER_VALIDATE_INT);
                $order = filter_input(INPUT_POST, 'display_order', FILTER_VALIDATE_INT);
                if ($battleId && $order !== false) {
                    BattlesService::updateDisplayOrder($battleId, $order);
                    $_SESSION['flash_success'] = "Display order updated.";
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

// 2. Contestant Leaderboard & Registration Gate Data
$contestants = [];
$regFilter = $_GET['reg_filter'] ?? 'all';
$contestantSearch = trim($_GET['c_search'] ?? '');
$regFee = RegistrationService::getRegistrationFee();
$regStats = RegistrationService::getRegistrationStats();

try {
    $cSql = "
        SELECT id, username, full_name, email, phone_number, photo, vote_count,
               registration_status, registration_paid_at, registration_payment_ref, registration_fee_paid
        FROM users 
        WHERE is_admin = 0
    ";
    $cParams = [];
    if (!empty($regFilter) && $regFilter !== 'all') {
        $cSql .= " AND registration_status = :reg_stat";
        $cParams[':reg_stat'] = $regFilter;
    }
    if (!empty($contestantSearch)) {
        $cSql .= " AND (full_name LIKE :c_s OR username LIKE :c_s OR email LIKE :c_s OR phone_number LIKE :c_s OR registration_payment_ref LIKE :c_s)";
        $cParams[':c_s'] = '%' . $contestantSearch . '%';
    }
    $cSql .= " ORDER BY vote_count DESC";

    $cStmt = $pdo->prepare($cSql);
    $cStmt->execute($cParams);
    $rawContestants = $cStmt->fetchAll();
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

// 4b. Current Active Hero Banner (Fail-Safe)
$currentBanner = null;
try {
    $currentBanner = $pdo->query("SELECT * FROM banner WHERE is_active = 1 ORDER BY id DESC LIMIT 1")->fetch();
} catch (Exception $e) {
    $currentBanner = null;
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

// 7. Contest Battles (Fail-Safe)
$battles = [];
try {
    $battles = BattlesService::getAllBattles();
} catch (Exception $e) {
    error_log("Battles query error: " . $e->getMessage());
    $battles = [];
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
        <li class="nav-item">
            <button class="nav-link" id="battles-tab" data-bs-toggle="tab" data-bs-target="#battlesTab">
                <i class="fas fa-bolt text-danger me-1"></i> Contest Battles (<?= count($battles) ?>)
            </button>
        </li>
    </ul>

    <div class="tab-content" id="adminTabsContent">

        <!-- ==================== TAB 1: CONTESTANTS & REGISTRATION AUDIT ==================== -->
        <div class="tab-pane fade show active" id="contestantsTab">
            <!-- Registration Key Metrics Row -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="stat-card p-3">
                        <div class="stat-label text-secondary small text-uppercase fw-bold">Total Contestants</div>
                        <div class="stat-value text-white"><?= number_format($regStats['total_contestants']) ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card p-3">
                        <div class="stat-label text-success small text-uppercase fw-bold">Verified & Paid</div>
                        <div class="stat-value text-success"><?= number_format($regStats['paid_count']) ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card p-3">
                        <div class="stat-label text-warning small text-uppercase fw-bold">Pending Payment</div>
                        <div class="stat-value text-warning"><?= number_format($regStats['pending_count']) ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card p-3">
                        <div class="stat-label text-info small text-uppercase fw-bold">Reg Fees Revenue</div>
                        <div class="stat-value text-info"><?= $currency ?><?= number_format($regStats['total_revenue'], 2) ?></div>
                    </div>
                </div>
            </div>

            <div class="content-panel">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                    <div>
                        <h5 class="fw-bold text-white mb-1">Contestant Leaderboard & Registration Gate</h5>
                        <p class="text-secondary small mb-0">Audit verified contestants, manage registration fees, and review payments.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <!-- Export CSV Form -->
                        <form method="POST">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="admin_action" value="export_registrations">
                            <button type="submit" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-file-csv me-1"></i> Export Records (CSV)
                            </button>
                        </form>
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

                <!-- Filters & Search Toolbar -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4 p-3 rounded bg-darker border border-secondary border-opacity-25">
                    <div class="d-flex flex-wrap gap-1">
                        <a href="admin_dashboard.php?reg_filter=all" class="btn btn-sm <?= ($regFilter === 'all') ? 'btn-gold' : 'btn-outline-secondary' ?>">
                            All (<?= $regStats['total_contestants'] ?>)
                        </a>
                        <a href="admin_dashboard.php?reg_filter=paid" class="btn btn-sm <?= ($regFilter === 'paid') ? 'btn-success' : 'btn-outline-success' ?>">
                            <i class="fas fa-check-circle me-1"></i> Paid & Active (<?= $regStats['paid_count'] ?>)
                        </a>
                        <a href="admin_dashboard.php?reg_filter=pending" class="btn btn-sm <?= ($regFilter === 'pending') ? 'btn-warning' : 'btn-outline-warning' ?>">
                            <i class="fas fa-clock me-1"></i> Pending Payment (<?= $regStats['pending_count'] ?>)
                        </a>
                        <a href="admin_dashboard.php?reg_filter=exempt" class="btn btn-sm <?= ($regFilter === 'exempt') ? 'btn-info' : 'btn-outline-info' ?>">
                            <i class="fas fa-gift me-1"></i> Exempt (<?= $regStats['exempt_count'] ?>)
                        </a>
                    </div>

                    <form method="GET" class="d-flex gap-2">
                        <input type="hidden" name="reg_filter" value="<?= e($regFilter) ?>">
                        <input type="text" name="c_search" class="form-control form-control-sm bg-dark border-secondary text-white" placeholder="Search name, phone, ref..." value="<?= e($contestantSearch) ?>" style="width: 220px;">
                        <button type="submit" class="btn btn-outline-warning btn-sm"><i class="fas fa-search"></i></button>
                        <?php if (!empty($contestantSearch) || $regFilter !== 'all'): ?>
                            <a href="admin_dashboard.php" class="btn btn-outline-secondary btn-sm" title="Clear Filters"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </form>
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
                                    <th>Registration Status</th>
                                    <th>Votes</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($contestants)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-secondary">No contestants match the current filter or search criteria.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($contestants as $c): ?>
                                        <?php 
                                            $cStatus = strtolower($c['registration_status'] ?? 'paid');
                                            $cAvatar = Auth::getAvatarUrl($c['photo'] ?? null);
                                        ?>
                                        <tr>
                                            <td><input type="checkbox" name="selected_users[]" value="<?= $c['id'] ?>" class="user-chk"></td>
                                            <td><span class="badge bg-warning text-dark fw-bold">#<?= (int)$c['ranking'] ?></span></td>
                                            <td>
                                                <img src="<?= e($cAvatar) ?>" alt="<?= e($c['username']) ?>" width="45" height="45" class="rounded-circle object-fit-cover border border-secondary">
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
                                                <div class="mb-1"><?= RegistrationService::formatStatusBadge($cStatus) ?></div>
                                                <?php if ($cStatus === 'paid' && !empty($c['registration_fee_paid'])): ?>
                                                    <div class="small text-secondary" style="font-size: 11px;">
                                                        <?= $currency ?><?= number_format((float)$c['registration_fee_paid'], 2) ?>
                                                        <?php if (!empty($c['registration_payment_ref'])): ?>
                                                            &bull; <code><?= e(substr($c['registration_payment_ref'], 0, 14)) ?>...</code>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <!-- Inline Vote Adjustment -->
                                                <form method="POST" class="d-inline-flex align-items-center gap-1">
                                                    <?= Security::csrfField() ?>
                                                    <input type="hidden" name="admin_action" value="update_vote_count">
                                                    <input type="hidden" name="user_id" value="<?= $c['id'] ?>">
                                                    <input type="number" name="vote_count" value="<?= (int)$c['vote_count'] ?>" class="form-control form-control-sm bg-dark border-secondary text-warning fw-bold" style="width: 80px;" min="0">
                                                    <button type="submit" class="btn btn-outline-warning btn-sm" title="Save Vote Count"><i class="fas fa-save"></i></button>
                                                </form>
                                            </td>
                                            <td class="text-end">
                                                <div class="d-inline-flex gap-1">
                                                    <!-- Change Registration Status Modal Trigger -->
                                                    <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#statusModal_<?= (int)$c['id'] ?>" title="Manage Registration Status">
                                                        <i class="fas fa-user-check"></i>
                                                    </button>

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

                    <!-- Contestant Registration Fee Configuration -->
                    <div class="content-panel">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="fw-bold text-white mb-0"><i class="fas fa-shield-alt text-warning me-2"></i> Contestant Registration Fee</h5>
                            <span class="badge <?= $regFee > 0 ? 'bg-warning text-dark' : 'bg-success' ?>">
                                <?= $regFee > 0 ? $currency . number_format($regFee, 2) : 'Free Registration' ?>
                            </span>
                        </div>
                        <p class="text-secondary small mb-3">Set mandatory entry fee. If > ₦0, new contestants are blocked at the payment checkpoint until verified.</p>

                        <form method="POST">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="admin_action" value="update_registration_fee">

                            <div class="mb-2">
                                <label class="form-label text-light small fw-semibold">Registration Fee Amount (<?= $currency ?>)</label>
                                <div class="input-group mb-2">
                                    <span class="input-group-text bg-dark border-secondary text-warning"><?= $currency ?></span>
                                    <input type="number" step="100" min="0" name="registration_fee" id="regFeeInput" class="form-control bg-dark border-secondary text-white" value="<?= (float)$regFee ?>" required>
                                    <button type="submit" class="btn btn-gold"><i class="fas fa-save me-1"></i> Save Fee</button>
                                </div>
                            </div>

                            <!-- Quick Presets -->
                            <div class="d-flex flex-wrap gap-1 mb-2">
                                <span class="text-secondary small me-1 align-self-center">Presets:</span>
                                <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size: 11px;" onclick="document.getElementById('regFeeInput').value='0'">₦0 (Free)</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size: 11px;" onclick="document.getElementById('regFeeInput').value='500'">₦500</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size: 11px;" onclick="document.getElementById('regFeeInput').value='1000'">₦1,000</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size: 11px;" onclick="document.getElementById('regFeeInput').value='2500'">₦2,500</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size: 11px;" onclick="document.getElementById('regFeeInput').value='5000'">₦5,000</button>
                            </div>
                            <div class="form-text text-secondary" style="font-size: 11px;">
                                Set to <code>0</code> for free registration. Changes immediately apply to all future contestant sign-ups.
                            </div>
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
                        
                        <?php if (!empty($currentBanner['image_path'])): ?>
                            <div class="mb-3 p-3 rounded bg-dark border border-secondary text-center">
                                <div class="text-secondary small mb-2 fw-semibold text-uppercase d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-check-circle text-success me-1"></i> Live Active Hero Banner</span>
                                    <span class="badge bg-success">Active</span>
                                </div>
                                <img src="<?= e($currentBanner['image_path']) ?>" alt="Current Banner" style="max-height: 180px; width: 100%; object-fit: contain; border-radius: 8px; background: rgba(0,0,0,0.5);">
                                <?php if (!empty($currentBanner['link_url'])): ?>
                                    <div class="small text-secondary mt-1 text-truncate">
                                        <i class="fas fa-link me-1"></i> <?= e($currentBanner['link_url']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="admin_action" value="upload_banner">

                            <div class="mb-3">
                                <label class="form-label text-light small fw-semibold">Select New Banner Image</label>
                                <input type="file" name="banner_image" accept="image/jpeg,image/png,image/webp" class="form-control bg-dark border-secondary text-white" required>
                                <div class="form-text text-secondary" style="font-size: 11px;">Supports all formats and dimensions (Landscape, 16:9, or Event Flyers). Displays 100% in full without cropping. Max: 10MB.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-light small fw-semibold">Optional Click Destination URL</label>
                                <input type="url" name="link_url" class="form-control bg-dark border-secondary text-white" placeholder="https://... (Leave blank if none)">
                            </div>

                            <button type="submit" class="btn btn-gold btn-sm"><i class="fas fa-upload me-1"></i> Upload & Set Live Banner</button>
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

        <!-- ==================== TAB 6: CONTEST BATTLES ==================== -->
        <div class="tab-pane fade" id="battlesTab">
            <?php
                $liveBattlesCount = count(array_filter($battles, fn($b) => $b['status'] === 'live'));
                $upcomingBattlesCount = count(array_filter($battles, fn($b) => $b['status'] === 'upcoming'));
                $endedBattlesCount = count(array_filter($battles, fn($b) => $b['status'] === 'ended'));
            ?>
            <!-- Stats Metric Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="stat-card">
                        <div class="text-secondary small fw-bold text-uppercase mb-1">Total Battles</div>
                        <div class="stat-value text-white"><?= count($battles) ?></div>
                        <div class="text-secondary small mt-1">Scheduled & archived</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card">
                        <div class="text-secondary small fw-bold text-uppercase mb-1">Live Now</div>
                        <div class="stat-value text-danger"><?= $liveBattlesCount ?></div>
                        <div class="text-secondary small mt-1">Actively streaming</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card">
                        <div class="text-secondary small fw-bold text-uppercase mb-1">Upcoming Showdowns</div>
                        <div class="stat-value text-warning"><?= $upcomingBattlesCount ?></div>
                        <div class="text-secondary small mt-1">On schedule</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card">
                        <div class="text-secondary small fw-bold text-uppercase mb-1">Concluded Battles</div>
                        <div class="stat-value text-info"><?= $endedBattlesCount ?></div>
                        <div class="text-secondary small mt-1">Completed matches</div>
                    </div>
                </div>
            </div>

            <!-- Battles Management Panel -->
            <div class="content-panel">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                    <div>
                        <h5 class="fw-bold text-white mb-1"><i class="fas fa-bolt text-danger me-2"></i> Contest Battles Arena</h5>
                        <p class="text-secondary small mb-0">Create, schedule, and manage rap battles, dance clashes, and live talent showdowns.</p>
                    </div>
                    <div>
                        <button type="button" class="btn btn-danger btn-sm fw-bold px-3 py-2" data-bs-toggle="modal" data-bs-target="#addBattleModal">
                            <i class="fas fa-plus-circle me-1"></i> Create New Battle
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th style="width: 70px;">Order</th>
                                <th>Matchup (Contestant 1 VS 2)</th>
                                <th>Battle Details</th>
                                <th>Schedule</th>
                                <th>Venue</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($battles)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-secondary">
                                        <i class="fas fa-bolt fa-3x mb-3 text-secondary opacity-50 d-block"></i>
                                        <h5>No contest battles created yet.</h5>
                                        <p class="small mb-3">Create your first head-to-head showdown to display on the homepage.</p>
                                        <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#addBattleModal">
                                            <i class="fas fa-plus me-1"></i> Add First Battle
                                        </button>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($battles as $bat): ?>
                                    <tr>
                                        <!-- Display Order Form -->
                                        <td>
                                            <form method="POST" class="d-flex align-items-center gap-1">
                                                <?= Security::csrfField() ?>
                                                <input type="hidden" name="admin_action" value="reorder_battle">
                                                <input type="hidden" name="battle_id" value="<?= (int)$bat['id'] ?>">
                                                <input type="number" name="display_order" value="<?= (int)$bat['display_order'] ?>" class="form-control form-control-sm bg-dark border-secondary text-white text-center px-1" style="width: 48px;" onchange="this.form.submit()" title="Change order and press Enter">
                                            </form>
                                        </td>

                                        <!-- Matchup Avatars -->
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="text-center">
                                                    <img src="<?= e($bat['contestant_one_image']) ?>" width="40" height="40" class="rounded-circle border border-warning object-fit-cover shadow" alt="<?= e($bat['contestant_one_name']) ?>" title="<?= e($bat['contestant_one_name']) ?>">
                                                    <div class="small fw-bold text-white text-truncate mt-1" style="max-width: 80px; font-size: 11px;"><?= e($bat['contestant_one_name']) ?></div>
                                                </div>
                                                <span class="badge bg-danger rounded-pill px-2 py-1 small fw-bold" style="font-size: 10px;">VS</span>
                                                <div class="text-center">
                                                    <img src="<?= e($bat['contestant_two_image']) ?>" width="40" height="40" class="rounded-circle border border-warning object-fit-cover shadow" alt="<?= e($bat['contestant_two_name']) ?>" title="<?= e($bat['contestant_two_name']) ?>">
                                                    <div class="small fw-bold text-white text-truncate mt-1" style="max-width: 80px; font-size: 11px;"><?= e($bat['contestant_two_name']) ?></div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Battle Details -->
                                        <td>
                                            <div class="fw-bold text-white small mb-1"><?= e($bat['title']) ?></div>
                                            <div class="d-flex align-items-center gap-1">
                                                <span class="badge bg-dark border border-secondary text-warning" style="font-size: 10px;"><?= e($bat['category']) ?></span>
                                                <?php if (!empty($bat['is_featured'])): ?>
                                                    <span class="badge bg-warning text-dark" style="font-size: 9px;"><i class="fas fa-star me-1"></i>FEATURED</span>
                                                <?php endif; ?>
                                                <?php if (empty($bat['is_published'])): ?>
                                                    <span class="badge bg-secondary text-light" style="font-size: 9px;">HIDDEN</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- Schedule -->
                                        <td>
                                            <div class="small text-white fw-semibold"><?= date('M d, Y', strtotime($bat['battle_date'])) ?></div>
                                            <div class="small text-secondary"><?= e($bat['battle_time']) ?></div>
                                        </td>

                                        <!-- Venue -->
                                        <td>
                                            <?php if ($bat['venue_type'] === 'online'): ?>
                                                <div class="small text-white fw-bold">
                                                    <?= BattlesService::getPlatformIconHtml($bat['platform']) ?>
                                                    <?= e($bat['platform'] ?? 'Online Live') ?>
                                                </div>
                                                <?php if (!empty($bat['live_url'])): ?>
                                                    <a href="<?= e($bat['live_url']) ?>" target="_blank" class="small text-info text-truncate d-inline-block" style="max-width: 140px;" title="<?= e($bat['live_url']) ?>">
                                                        <i class="fas fa-link me-1"></i> Link
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <div class="small text-white fw-bold">
                                                    <i class="fas fa-map-marker-alt text-danger me-1"></i>
                                                    <?= e($bat['venue_name'] ?? 'Physical Venue') ?>
                                                </div>
                                                <div class="small text-secondary text-truncate" style="max-width: 140px;">
                                                    <?= e($bat['venue_city'] ?? '') ?><?= !empty($bat['venue_state']) ? ', ' . e($bat['venue_state']) : '' ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Status -->
                                        <td>
                                            <?= BattlesService::getStatusBadgeHtml($bat['status']) ?>
                                        </td>

                                        <!-- Actions -->
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-1">
                                                <!-- Toggle Featured -->
                                                <form method="POST" class="d-inline">
                                                    <?= Security::csrfField() ?>
                                                    <input type="hidden" name="admin_action" value="toggle_battle_featured">
                                                    <input type="hidden" name="battle_id" value="<?= (int)$bat['id'] ?>">
                                                    <button type="submit" class="btn btn-sm <?= !empty($bat['is_featured']) ? 'btn-warning text-dark' : 'btn-outline-secondary' ?>" title="<?= !empty($bat['is_featured']) ? 'Unfeature' : 'Mark as Featured' ?>">
                                                        <i class="fas fa-star"></i>
                                                    </button>
                                                </form>

                                                <!-- Toggle Published -->
                                                <form method="POST" class="d-inline">
                                                    <?= Security::csrfField() ?>
                                                    <input type="hidden" name="admin_action" value="toggle_battle_publish">
                                                    <input type="hidden" name="battle_id" value="<?= (int)$bat['id'] ?>">
                                                    <button type="submit" class="btn btn-sm <?= !empty($bat['is_published']) ? 'btn-outline-success' : 'btn-outline-secondary' ?>" title="<?= !empty($bat['is_published']) ? 'Hide from public' : 'Publish live' ?>">
                                                        <i class="fas <?= !empty($bat['is_published']) ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
                                                    </button>
                                                </form>

                                                <!-- Edit Button -->
                                                <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#editBattleModal_<?= (int)$bat['id'] ?>" title="Edit Battle">
                                                    <i class="fas fa-edit"></i>
                                                </button>

                                                <!-- Delete Button -->
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Permanently delete this contest battle?');">
                                                    <?= Security::csrfField() ?>
                                                    <input type="hidden" name="admin_action" value="delete_battle">
                                                    <input type="hidden" name="battle_id" value="<?= (int)$bat['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Battle">
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

    </div>
</div>


<!-- ==================== CONTESTANT REGISTRATION STATUS MODALS ==================== -->
<?php foreach ($contestants as $c): ?>
<div class="modal fade" id="statusModal_<?= (int)$c['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border border-secondary shadow-lg">
            <div class="modal-header border-secondary bg-darker">
                <h5 class="modal-title fw-bold text-warning">
                    <i class="fas fa-user-check me-2"></i> Registration Status: <?= e($c['full_name']) ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= Security::csrfField() ?>
                <input type="hidden" name="admin_action" value="update_user_registration_status">
                <input type="hidden" name="user_id" value="<?= (int)$c['id'] ?>">

                <div class="modal-body p-4">
                    <div class="mb-3 text-center">
                        <img src="<?= e(Auth::getAvatarUrl($c['photo'] ?? null)) ?>" width="64" height="64" class="rounded-circle border border-warning mb-2 object-fit-cover">
                        <h6 class="fw-bold text-white mb-0"><?= e($c['full_name']) ?></h6>
                        <span class="text-secondary small">@<?= e($c['username']) ?> &bull; <?= e($c['email']) ?></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-light">Registration & Verification Status <span class="text-danger">*</span></label>
                        <select name="registration_status" class="form-select bg-dark border-secondary text-white" required>
                            <option value="paid" <?= ($c['registration_status'] ?? 'paid') === 'paid' ? 'selected' : '' ?>>🟢 Paid & Verified (Full Access & Public Listing)</option>
                            <option value="pending" <?= ($c['registration_status'] ?? '') === 'pending' ? 'selected' : '' ?>>🟡 Pending Payment (Blocked at Checkpoint)</option>
                            <option value="exempt" <?= ($c['registration_status'] ?? '') === 'exempt' ? 'selected' : '' ?>>🔵 Free / Exempt (Active Without Payment)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-light">Amount Recorded (<?= $currency ?>)</label>
                        <input type="number" step="100" min="0" name="fee_paid" class="form-control bg-dark border-secondary text-white" value="<?= (float)($c['registration_fee_paid'] ?? $regFee) ?>">
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold text-light">Payment Reference / Note (Optional)</label>
                        <input type="text" name="payment_ref" class="form-control bg-dark border-secondary text-white" value="<?= e($c['registration_payment_ref'] ?? '') ?>" placeholder="e.g. MANUAL_ADMIN_VERIFIED or Paystack ref">
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-gold btn-sm"><i class="fas fa-save me-1"></i> Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

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
                            <input type="text" name="whatsapp_number" class="form-control bg-dark border-secondary text-white" value="<?= e(Settings::get('support_phone', '08139188570')) ?>" placeholder="e.g. 08139188570">
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
                            <input type="text" name="whatsapp_number" class="form-control bg-dark border-secondary text-white" value="<?= e($b['whatsapp_number'] ?? Settings::get('support_phone', '08139188570')) ?>">
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

<!-- ==================== ADD BATTLE MODAL ==================== -->
<div class="modal fade" id="addBattleModal" tabindex="-1" aria-labelledby="addBattleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content bg-dark text-white border border-secondary shadow-lg">
            <div class="modal-header border-secondary bg-darker">
                <h5 class="modal-title fw-bold text-warning" id="addBattleModalLabel">
                    <i class="fas fa-fire me-2 text-danger"></i> Schedule New Contest Battle
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <?= Security::csrfField() ?>
                <input type="hidden" name="admin_action" value="add_battle">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- Battle Title & Category -->
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold text-light">Battle Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control bg-dark border-secondary text-white" placeholder="e.g. Crown Rap Clash: Midnight Freestyle Showdown" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-light">Category <span class="text-danger">*</span></label>
                            <select name="category" class="form-select bg-dark border-secondary text-white">
                                <option value="Rap Battle">🎤 Rap Battle</option>
                                <option value="Dance Battle">💃 Dance Battle</option>
                                <option value="Singing Battle">🎵 Singing Battle</option>
                                <option value="Comedy Battle">🎭 Comedy Battle</option>
                                <option value="Talent Battle" selected>⭐ Talent Battle</option>
                                <option value="Modeling Clash">👑 Modeling Clash</option>
                                <option value="Gaming Clash">🎮 Gaming Clash</option>
                                <option value="Other">🔥 Other Showcase</option>
                            </select>
                        </div>

                        <!-- Date & Time -->
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-light">Battle Date <span class="text-danger">*</span></label>
                            <input type="date" name="battle_date" class="form-control bg-dark border-secondary text-white" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-light">Battle Time <span class="text-danger">*</span></label>
                            <input type="text" name="battle_time" class="form-control bg-dark border-secondary text-white" placeholder="e.g. 08:00 PM WAT" value="08:00 PM WAT" required>
                        </div>

                        <!-- Contestant One & Two Section -->
                        <div class="col-12 mt-3 pt-2 border-top border-secondary">
                            <h6 class="fw-bold text-warning mb-2"><i class="fas fa-users me-1 text-info"></i> Contestants Match-up Setup</h6>
                        </div>

                        <div class="col-md-6 p-3 rounded" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-primary text-white">Contestant 1 (Side A)</span>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold text-light">Full Name / Stage Name <span class="text-danger">*</span></label>
                                <input type="text" name="contestant_one_name" class="form-control bg-dark border-secondary text-white" placeholder="e.g. Queen Bella" required>
                            </div>
                            <div>
                                <label class="form-label small fw-semibold text-light">Contestant 1 Photo</label>
                                <input type="file" name="contestant_one_image" accept="image/jpeg,image/png,image/webp" class="form-control bg-dark border-secondary text-white">
                                <div class="form-text text-secondary" style="font-size: 11px;">Square portrait recommended (JPG, PNG, WEBP).</div>
                            </div>
                        </div>

                        <div class="col-md-6 p-3 rounded" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-danger text-white">Contestant 2 (Side B)</span>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold text-light">Full Name / Stage Name <span class="text-danger">*</span></label>
                                <input type="text" name="contestant_two_name" class="form-control bg-dark border-secondary text-white" placeholder="e.g. King Draco" required>
                            </div>
                            <div>
                                <label class="form-label small fw-semibold text-light">Contestant 2 Photo</label>
                                <input type="file" name="contestant_two_image" accept="image/jpeg,image/png,image/webp" class="form-control bg-dark border-secondary text-white">
                                <div class="form-text text-secondary" style="font-size: 11px;">Square portrait recommended (JPG, PNG, WEBP).</div>
                            </div>
                        </div>

                        <!-- Banner & Description -->
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-light">Battle Banner Image (Optional)</label>
                            <input type="file" name="banner_image" accept="image/jpeg,image/png,image/webp" class="form-control bg-dark border-secondary text-white">
                            <div class="form-text text-secondary" style="font-size: 11px;">Wide banner header for the match card (JPG, PNG, WEBP).</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-light">Battle Description / Rules / Showdown Details</label>
                            <textarea name="description" rows="3" class="form-control bg-dark border-secondary text-white" placeholder="Explain the format, rounds, audience voting rules, or special guests..."></textarea>
                        </div>

                        <!-- Venue System -->
                        <div class="col-12 mt-3 pt-2 border-top border-secondary">
                            <h6 class="fw-bold text-warning mb-2"><i class="fas fa-map-marker-alt me-1 text-danger"></i> Venue & Stream Setup</h6>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-light">Venue Format <span class="text-danger">*</span></label>
                            <select name="venue_type" id="add_battle_venue_type" class="form-select bg-dark border-secondary text-white" onchange="updateBattleVenueFields('add_battle')">
                                <option value="online" selected>🌐 Online Livestream</option>
                                <option value="physical">📍 Physical Event Venue</option>
                            </select>
                        </div>

                        <!-- Online Fields -->
                        <div class="col-md-8" id="add_battle_online_box">
                            <div class="row g-2">
                                <div class="col-md-5">
                                    <label class="form-label small fw-semibold text-light">Streaming Platform</label>
                                    <select name="platform" class="form-select bg-dark border-secondary text-white">
                                        <option value="Instagram Live">Instagram Live</option>
                                        <option value="TikTok Live">TikTok Live</option>
                                        <option value="YouTube Live">YouTube Live</option>
                                        <option value="Facebook Live">Facebook Live</option>
                                        <option value="X (Twitter) Live">X (Twitter) Live</option>
                                        <option value="Twitch">Twitch</option>
                                        <option value="Zoom">Zoom</option>
                                        <option value="Custom Stream">Custom Stream URL</option>
                                    </select>
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label small fw-semibold text-light">Live Stream URL</label>
                                    <input type="url" name="live_url" class="form-control bg-dark border-secondary text-white" placeholder="https://instagram.com/crownnightstar or live link">
                                </div>
                            </div>
                        </div>

                        <!-- Physical Fields -->
                        <div class="col-12 d-none" id="add_battle_physical_box">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-light">Venue Name</label>
                                    <input type="text" name="venue_name" class="form-control bg-dark border-secondary text-white" placeholder="e.g. Crown Grand Ballroom & Lounge">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-light">Street Address</label>
                                    <input type="text" name="venue_address" class="form-control bg-dark border-secondary text-white" placeholder="e.g. Plot 14, Adetokunbo Ademola St">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold text-light">City</label>
                                    <input type="text" name="venue_city" class="form-control bg-dark border-secondary text-white" placeholder="e.g. Victoria Island">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold text-light">State / Region</label>
                                    <input type="text" name="venue_state" class="form-control bg-dark border-secondary text-white" placeholder="e.g. Lagos State, Nigeria">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold text-light">Google Maps URL</label>
                                    <input type="url" name="maps_url" class="form-control bg-dark border-secondary text-white" placeholder="https://maps.google.com/?q=...">
                                </div>
                            </div>
                        </div>

                        <!-- Status, Order, Toggles -->
                        <div class="col-12 mt-3 pt-2 border-top border-secondary">
                            <h6 class="fw-bold text-warning mb-2"><i class="fas fa-sliders-h me-1 text-primary"></i> Status & Display Settings</h6>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-light">Battle Status</label>
                            <select name="status" class="form-select bg-dark border-secondary text-white">
                                <option value="upcoming" selected>⚡ Upcoming</option>
                                <option value="live">🔴 Live Now</option>
                                <option value="ended">🏁 Ended / Concluded</option>
                                <option value="cancelled">❌ Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-light">Display Order</label>
                            <input type="number" name="display_order" class="form-control bg-dark border-secondary text-white" value="0" min="0">
                            <div class="form-text text-secondary" style="font-size: 11px;">Lower numbers appear first.</div>
                        </div>
                        <div class="col-md-4 d-flex flex-column justify-content-center pt-2">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" name="is_featured" id="add_battle_featured">
                                <label class="form-check-label text-warning small fw-semibold" for="add_battle_featured">⭐ Featured Battle</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_published" id="add_battle_published" checked>
                                <label class="form-check-label text-light small fw-semibold" for="add_battle_published">Published & Visible</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-gold btn-sm"><i class="fas fa-plus-circle me-1"></i> Create Contest Battle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== EDIT BATTLE MODALS ==================== -->
<?php foreach ($battles as $bat): ?>
<div class="modal fade" id="editBattleModal_<?= (int)$bat['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content bg-dark text-white border border-secondary shadow-lg">
            <div class="modal-header border-secondary bg-darker">
                <h5 class="modal-title fw-bold text-warning">
                    <i class="fas fa-edit me-2 text-info"></i> Edit Battle: <?= e($bat['title']) ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <?= Security::csrfField() ?>
                <input type="hidden" name="admin_action" value="update_battle">
                <input type="hidden" name="battle_id" value="<?= (int)$bat['id'] ?>">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- Battle Title & Category -->
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold text-light">Battle Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control bg-dark border-secondary text-white" value="<?= e($bat['title']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-light">Category <span class="text-danger">*</span></label>
                            <select name="category" class="form-select bg-dark border-secondary text-white">
                                <?php 
                                $battleCategories = ['Rap Battle', 'Dance Battle', 'Singing Battle', 'Comedy Battle', 'Talent Battle', 'Modeling Clash', 'Gaming Clash', 'Other'];
                                foreach ($battleCategories as $bcat): ?>
                                    <option value="<?= e($bcat) ?>" <?= $bat['category'] === $bcat ? 'selected' : '' ?>><?= e($bcat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Date & Time -->
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-light">Battle Date <span class="text-danger">*</span></label>
                            <input type="date" name="battle_date" class="form-control bg-dark border-secondary text-white" value="<?= e($bat['battle_date']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-light">Battle Time <span class="text-danger">*</span></label>
                            <input type="text" name="battle_time" class="form-control bg-dark border-secondary text-white" value="<?= e($bat['battle_time']) ?>" required>
                        </div>

                        <!-- Contestants Section -->
                        <div class="col-12 mt-3 pt-2 border-top border-secondary">
                            <h6 class="fw-bold text-warning mb-2"><i class="fas fa-users me-1 text-info"></i> Contestants Match-up Setup</h6>
                        </div>

                        <div class="col-md-6 p-3 rounded" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-primary text-white">Contestant 1 (Side A)</span>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold text-light">Full Name / Stage Name <span class="text-danger">*</span></label>
                                <input type="text" name="contestant_one_name" class="form-control bg-dark border-secondary text-white" value="<?= e($bat['contestant_one_name']) ?>" required>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?= e($bat['contestant_one_image']) ?>" width="45" height="45" class="rounded-circle border border-primary object-fit-cover">
                                    <div class="flex-grow-1">
                                        <label class="form-label small fw-semibold text-light">Replace Photo</label>
                                        <input type="file" name="contestant_one_image" accept="image/jpeg,image/png,image/webp" class="form-control bg-dark border-secondary text-white">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 p-3 rounded" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-danger text-white">Contestant 2 (Side B)</span>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold text-light">Full Name / Stage Name <span class="text-danger">*</span></label>
                                <input type="text" name="contestant_two_name" class="form-control bg-dark border-secondary text-white" value="<?= e($bat['contestant_two_name']) ?>" required>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?= e($bat['contestant_two_image']) ?>" width="45" height="45" class="rounded-circle border border-danger object-fit-cover">
                                    <div class="flex-grow-1">
                                        <label class="form-label small fw-semibold text-light">Replace Photo</label>
                                        <input type="file" name="contestant_two_image" accept="image/jpeg,image/png,image/webp" class="form-control bg-dark border-secondary text-white">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Banner & Description -->
                        <div class="col-12">
                            <div class="d-flex align-items-center gap-3">
                                <?php if (!empty($bat['banner_image'])): ?>
                                    <img src="<?= e($bat['banner_image']) ?>" width="80" height="45" class="rounded border border-secondary object-fit-cover">
                                <?php endif; ?>
                                <div class="flex-grow-1">
                                    <label class="form-label small fw-semibold text-light">Replace Battle Banner Image (Optional)</label>
                                    <input type="file" name="banner_image" accept="image/jpeg,image/png,image/webp" class="form-control bg-dark border-secondary text-white">
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-light">Battle Description / Rules / Showdown Details</label>
                            <textarea name="description" rows="3" class="form-control bg-dark border-secondary text-white"><?= e($bat['description'] ?? '') ?></textarea>
                        </div>

                        <!-- Venue System -->
                        <div class="col-12 mt-3 pt-2 border-top border-secondary">
                            <h6 class="fw-bold text-warning mb-2"><i class="fas fa-map-marker-alt me-1 text-danger"></i> Venue & Stream Setup</h6>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-light">Venue Format <span class="text-danger">*</span></label>
                            <select name="venue_type" id="edit_battle_<?= $bat['id'] ?>_venue_type" class="form-select bg-dark border-secondary text-white" onchange="updateBattleVenueFields('edit_battle_<?= $bat['id'] ?>')">
                                <option value="online" <?= $bat['venue_type'] === 'online' ? 'selected' : '' ?>>🌐 Online Livestream</option>
                                <option value="physical" <?= $bat['venue_type'] === 'physical' ? 'selected' : '' ?>>📍 Physical Event Venue</option>
                            </select>
                        </div>

                        <!-- Online Fields -->
                        <div class="col-md-8 <?= $bat['venue_type'] !== 'online' ? 'd-none' : '' ?>" id="edit_battle_<?= $bat['id'] ?>_online_box">
                            <div class="row g-2">
                                <div class="col-md-5">
                                    <label class="form-label small fw-semibold text-light">Streaming Platform</label>
                                    <select name="platform" class="form-select bg-dark border-secondary text-white">
                                        <?php 
                                        $platforms = ['Instagram Live', 'TikTok Live', 'YouTube Live', 'Facebook Live', 'X (Twitter) Live', 'Twitch', 'Zoom', 'Custom Stream'];
                                        foreach ($platforms as $plat): ?>
                                            <option value="<?= e($plat) ?>" <?= $bat['platform'] === $plat ? 'selected' : '' ?>><?= e($plat) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label small fw-semibold text-light">Live Stream URL</label>
                                    <input type="url" name="live_url" class="form-control bg-dark border-secondary text-white" value="<?= e($bat['live_url'] ?? '') ?>" placeholder="https://...">
                                </div>
                            </div>
                        </div>

                        <!-- Physical Fields -->
                        <div class="col-12 <?= $bat['venue_type'] !== 'physical' ? 'd-none' : '' ?>" id="edit_battle_<?= $bat['id'] ?>_physical_box">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-light">Venue Name</label>
                                    <input type="text" name="venue_name" class="form-control bg-dark border-secondary text-white" value="<?= e($bat['venue_name'] ?? '') ?>" placeholder="e.g. Crown Grand Ballroom & Lounge">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-light">Street Address</label>
                                    <input type="text" name="venue_address" class="form-control bg-dark border-secondary text-white" value="<?= e($bat['venue_address'] ?? '') ?>" placeholder="e.g. Plot 14, Adetokunbo Ademola St">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold text-light">City</label>
                                    <input type="text" name="venue_city" class="form-control bg-dark border-secondary text-white" value="<?= e($bat['venue_city'] ?? '') ?>" placeholder="e.g. Victoria Island">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold text-light">State / Region</label>
                                    <input type="text" name="venue_state" class="form-control bg-dark border-secondary text-white" value="<?= e($bat['venue_state'] ?? '') ?>" placeholder="e.g. Lagos State, Nigeria">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold text-light">Google Maps URL</label>
                                    <input type="url" name="maps_url" class="form-control bg-dark border-secondary text-white" value="<?= e($bat['maps_url'] ?? '') ?>" placeholder="https://maps.google.com/?q=...">
                                </div>
                            </div>
                        </div>

                        <!-- Status, Order, Toggles -->
                        <div class="col-12 mt-3 pt-2 border-top border-secondary">
                            <h6 class="fw-bold text-warning mb-2"><i class="fas fa-sliders-h me-1 text-primary"></i> Status & Display Settings</h6>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-light">Battle Status</label>
                            <select name="status" class="form-select bg-dark border-secondary text-white">
                                <option value="upcoming" <?= $bat['status'] === 'upcoming' ? 'selected' : '' ?>>⚡ Upcoming</option>
                                <option value="live" <?= $bat['status'] === 'live' ? 'selected' : '' ?>>🔴 Live Now</option>
                                <option value="ended" <?= $bat['status'] === 'ended' ? 'selected' : '' ?>>🏁 Ended / Concluded</option>
                                <option value="cancelled" <?= $bat['status'] === 'cancelled' ? 'selected' : '' ?>>❌ Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-light">Display Order</label>
                            <input type="number" name="display_order" class="form-control bg-dark border-secondary text-white" value="<?= (int)$bat['display_order'] ?>" min="0">
                        </div>
                        <div class="col-md-4 d-flex flex-column justify-content-center pt-2">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" name="is_featured" id="edit_battle_featured_<?= $bat['id'] ?>" <?= !empty($bat['is_featured']) ? 'checked' : '' ?>>
                                <label class="form-check-label text-warning small fw-semibold" for="edit_battle_featured_<?= $bat['id'] ?>">⭐ Featured Battle</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_published" id="edit_battle_published_<?= $bat['id'] ?>" <?= !empty($bat['is_published']) ? 'checked' : '' ?>>
                                <label class="form-check-label text-light small fw-semibold" for="edit_battle_published_<?= $bat['id'] ?>">Published & Visible</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-gold btn-sm"><i class="fas fa-save me-1"></i> Save Battle Changes</button>
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

function updateBattleVenueFields(prefix) {
    const select = document.getElementById(prefix + '_venue_type');
    if (!select) return;
    const type = select.value;
    const onlineBox = document.getElementById(prefix + '_online_box');
    const physBox = document.getElementById(prefix + '_physical_box');
    if (onlineBox) onlineBox.classList.toggle('d-none', type !== 'online');
    if (physBox) physBox.classList.toggle('d-none', type !== 'physical');
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

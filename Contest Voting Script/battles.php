<?php
require_once __DIR__ . '/config.php';

BattlesService::ensureTable();

$category = trim($_GET['category'] ?? 'all');
$status   = trim($_GET['status'] ?? 'all');
$search   = trim($_GET['search'] ?? '');

$allBattles = BattlesService::getPublishedBattles($status, $category);

if (!empty($search)) {
    $searchLower = strtolower($search);
    $allBattles = array_filter($allBattles, function($b) use ($searchLower) {
        return strpos(strtolower($b['title']), $searchLower) !== false ||
               strpos(strtolower($b['contestant_one_name']), $searchLower) !== false ||
               strpos(strtolower($b['contestant_two_name']), $searchLower) !== false ||
               strpos(strtolower($b['category']), $searchLower) !== false ||
               strpos(strtolower($b['venue_name'] ?? ''), $searchLower) !== false ||
               strpos(strtolower($b['platform'] ?? ''), $searchLower) !== false;
    });
}

$siteTitle = Settings::get('site_title', 'Crown Night Star');
$isRegistrationOpen = Settings::isRegistrationOpen();

// Extract unique categories for filter
$pdo = DB::pdo();
$categories = [];
try {
    $catStmt = $pdo->query("SELECT DISTINCT category FROM contest_battles WHERE is_published = 1");
    $categories = $catStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Exception $e) {
    $categories = ['Rap Battle', 'Dance Battle', 'Singing Battle', 'Comedy Battle', 'Talent Battle'];
}

$liveCount = count(array_filter($allBattles, fn($b) => $b['status'] === 'live'));
$upcomingCount = count(array_filter($allBattles, fn($b) => $b['status'] === 'upcoming'));
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔥 Contest Battles & Showdowns - <?= e($siteTitle) ?></title>
    <meta name="description" content="Discover official head-to-head rap battles, dance clashes, and live talent showdowns on <?= e($siteTitle) ?>. Watch online or attend in person!">

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Cabinet+Grotesk:wght@800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets2/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets2/css/all.min.css">

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #080612 0%, #110d24 50%, #0a0718 100%);
            color: #e2e8f0;
            min-height: 100vh;
        }
        .navbar-custom {
            background: rgba(14, 11, 30, 0.95);
            backdrop-filter: blur(14px);
            border-bottom: 1px solid rgba(255, 215, 0, 0.2);
        }
        .hero-banner {
            padding: 60px 0 40px;
            text-align: center;
            position: relative;
            background: radial-gradient(circle at 50% 30%, rgba(255, 69, 0, 0.15), transparent 70%);
        }
        .battle-card {
            background: rgba(22, 17, 44, 0.85);
            border: 1px solid rgba(255, 215, 0, 0.22);
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
            height: 100%;
            position: relative;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }
        .battle-card:hover {
            transform: translateY(-8px);
            border-color: rgba(255, 107, 0, 0.6);
            box-shadow: 0 20px 45px rgba(255, 69, 0, 0.25);
        }
        .battle-card.card-live {
            border-color: rgba(255, 59, 48, 0.6);
            box-shadow: 0 0 35px rgba(255, 59, 48, 0.25);
        }
        .battle-banner-wrap {
            height: 140px;
            position: relative;
            background: #15102a;
            overflow: hidden;
        }
        .battle-banner-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.55;
            transition: transform 0.4s ease;
        }
        .battle-card:hover .battle-banner-wrap img {
            transform: scale(1.08);
            opacity: 0.75;
        }
        .battle-category-pill {
            position: absolute;
            top: 12px;
            left: 12px;
            background: rgba(14, 11, 30, 0.85);
            border: 1px solid rgba(255, 215, 0, 0.4);
            color: #ffd700;
            font-size: 11px;
            font-weight: 800;
            padding: 4px 12px;
            border-radius: 50px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            backdrop-filter: blur(8px);
        }
        .battle-status-wrap {
            position: absolute;
            top: 12px;
            right: 12px;
        }
        .battle-badge-live {
            background: linear-gradient(135deg, #ff2a2a, #ff5e3a);
            color: #fff;
            font-weight: 800;
            font-size: 11px;
            letter-spacing: 0.5px;
            padding: 5px 12px;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 0 15px rgba(255, 42, 42, 0.6);
            animation: pulse-live 1.8s infinite;
        }
        @keyframes pulse-live {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.9; }
        }
        .live-dot {
            width: 7px;
            height: 7px;
            background: #fff;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 8px #fff;
        }
        .battle-badge-upcoming {
            background: rgba(255, 215, 0, 0.15);
            border: 1px solid rgba(255, 215, 0, 0.4);
            color: #ffd700;
            font-weight: 800;
            font-size: 11px;
            padding: 4px 12px;
            border-radius: 50px;
        }
        .battle-badge-ended {
            background: rgba(100, 116, 139, 0.25);
            border: 1px solid rgba(148, 163, 184, 0.3);
            color: #94a3b8;
            font-weight: 700;
            font-size: 11px;
            padding: 4px 12px;
            border-radius: 50px;
        }
        .battle-badge-cancelled {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
            font-weight: 700;
            font-size: 11px;
            padding: 4px 12px;
            border-radius: 50px;
        }

        /* Matchup Arena Display */
        .matchup-arena {
            padding: 20px 16px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            margin-top: -45px;
            z-index: 2;
        }
        .contestant-fighter {
            flex: 1;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .fighter-avatar-wrap {
            width: 82px;
            height: 82px;
            border-radius: 50%;
            border: 3px solid #ffd700;
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.35);
            overflow: hidden;
            background: #090714;
            margin-bottom: 8px;
            position: relative;
            transition: transform 0.3s ease;
        }
        .battle-card:hover .fighter-avatar-wrap {
            transform: scale(1.08);
            border-color: #ff6b00;
            box-shadow: 0 0 22px rgba(255, 107, 0, 0.5);
        }
        .fighter-avatar-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .fighter-name {
            font-weight: 800;
            font-size: 14px;
            color: #ffffff;
            margin-bottom: 2px;
            max-width: 110px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .fighter-tag {
            font-size: 10px;
            color: #ffd700;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .vs-badge-epic {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ff4500, #ff8c00);
            color: #fff;
            font-family: 'Cabinet Grotesk', sans-serif;
            font-weight: 900;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 25px rgba(255, 69, 0, 0.7);
            border: 2px solid #fff;
            flex-shrink: 0;
            margin: 0 8px;
            position: relative;
            z-index: 3;
            animation: pulse-vs 2.5s infinite;
        }
        @keyframes pulse-vs {
            0%, 100% { transform: scale(1); box-shadow: 0 0 15px rgba(255, 69, 0, 0.6); }
            50% { transform: scale(1.1); box-shadow: 0 0 25px rgba(255, 140, 0, 0.9); }
        }

        .battle-content {
            padding: 0 18px 18px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .battle-title {
            font-size: 16px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 8px;
            line-height: 1.35;
        }
        .battle-desc {
            font-size: 13px;
            color: #94a3b8;
            line-height: 1.5;
            margin-bottom: 14px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .battle-info-row {
            background: rgba(14, 11, 30, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 10px 12px;
            font-size: 12px;
            margin-bottom: 14px;
        }
        .battle-info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #cbd5e1;
            margin-bottom: 4px;
        }
        .battle-info-item:last-child {
            margin-bottom: 0;
        }
        .battle-info-item i {
            width: 16px;
            text-align: center;
        }
        .btn-battle-action {
            background: linear-gradient(135deg, #ff4500 0%, #ff8c00 100%);
            color: #fff;
            font-weight: 800;
            border-radius: 12px;
            border: none;
            padding: 11px 18px;
            font-size: 14px;
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 8px 20px rgba(255, 69, 0, 0.35);
        }
        .btn-battle-action:hover {
            background: linear-gradient(135deg, #ff5714 0%, #ffa01a 100%);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(255, 69, 0, 0.5);
        }
        .btn-battle-location {
            background: linear-gradient(135deg, #ffd700 0%, #d4af37 100%);
            color: #0d1117;
            font-weight: 800;
            border-radius: 12px;
            border: none;
            padding: 11px 18px;
            font-size: 14px;
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 8px 20px rgba(255, 215, 0, 0.25);
        }
        .btn-battle-location:hover {
            background: linear-gradient(135deg, #ffe033 0%, #e5bd3b 100%);
            color: #0d1117;
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(255, 215, 0, 0.4);
        }
        .btn-battle-concluded {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #94a3b8;
            font-weight: 700;
            border-radius: 12px;
            padding: 11px 18px;
            font-size: 14px;
            text-align: center;
            display: block;
        }

        .filter-chip {
            display: inline-block;
            background: rgba(22, 17, 44, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #cbd5e1;
            padding: 7px 18px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.25s ease;
            margin: 0 4px 8px;
        }
        .filter-chip:hover, .filter-chip.active {
            background: linear-gradient(135deg, #ff4500, #ff8c00);
            border-color: #ff8c00;
            color: #fff;
            box-shadow: 0 6px 18px rgba(255, 69, 0, 0.35);
        }
        .search-bar {
            background: rgba(22, 17, 44, 0.9);
            border: 1px solid rgba(255, 215, 0, 0.3);
            border-radius: 50px;
            padding: 12px 24px;
            color: #fff;
            width: 100%;
            max-width: 500px;
        }
        .search-bar:focus {
            background: rgba(22, 17, 44, 1);
            border-color: #ffd700;
            box-shadow: 0 0 0 0.25rem rgba(255, 215, 0, 0.2);
            color: #fff;
            outline: none;
        }
        footer {
            background: #070510;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding: 50px 0 30px;
            margin-top: 80px;
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
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navContent">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navContent">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0 gap-3">
                <li class="nav-item"><a class="nav-link text-light" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link text-light" href="index.php#contestants">Contestants</a></li>
                <li class="nav-item"><a class="nav-link text-warning fw-bold active" href="battles.php"><i class="fas fa-bolt me-1"></i> Contest Battles</a></li>
                <li class="nav-item"><a class="nav-link text-light" href="bookstore.php"><i class="fas fa-book-open me-1"></i> Bookstore</a></li>
                <li class="nav-item"><a class="nav-link text-light" href="about-us.php">About Contest</a></li>
                <li class="nav-item"><a class="nav-link text-light" href="contact-us.php">Contact Us</a></li>
            </ul>

            <div class="d-flex align-items-center gap-2">
                <?php if (Auth::isUserLoggedIn()): ?>
                    <a href="dashboard.php" class="btn btn-warning btn-sm fw-bold"><i class="fas fa-user-circle me-1"></i> My Dashboard</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline-light btn-sm"><i class="fas fa-sign-in-alt me-1"></i> Contestant Login</a>
                    <?php if ($isRegistrationOpen): ?>
                        <a href="register.php" class="btn btn-warning btn-sm fw-bold"><i class="fas fa-sparkles me-1"></i> Join Contest</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero-banner">
    <div class="container">
        <span class="badge bg-danger px-3 py-2 fw-bold text-uppercase mb-2" style="letter-spacing: 1px;">
            <i class="fas fa-fire me-1"></i> Official Head-to-Head Arena
        </span>
        <h1 class="display-4 fw-bold text-white mb-2">🔥 Contest Battles</h1>
        <p class="text-secondary fs-5 mx-auto mb-4" style="max-width: 680px;">
            Experience explosive rap battles, dance clashes, and live talent showdowns. Catch the livestreams online or secure your spot at the physical venue!
        </p>

        <!-- Stats Bar -->
        <div class="d-inline-flex flex-wrap justify-content-center gap-3 p-2 px-4 rounded-pill bg-dark border border-secondary mb-4">
            <span class="text-secondary small d-flex align-items-center gap-2">
                <span class="live-dot" style="background: #ff2a2a; box-shadow: 0 0 8px #ff2a2a;"></span>
                <strong class="text-white"><?= $liveCount ?></strong> Live Now
            </span>
            <span class="text-secondary opacity-25">|</span>
            <span class="text-secondary small d-flex align-items-center gap-2">
                <i class="fas fa-bolt text-warning"></i>
                <strong class="text-white"><?= $upcomingCount ?></strong> Upcoming Battles
            </span>
            <span class="text-secondary opacity-25">|</span>
            <span class="text-secondary small d-flex align-items-center gap-2">
                <i class="fas fa-layer-group text-info"></i>
                <strong class="text-white"><?= count($allBattles) ?></strong> Total Showdowns
            </span>
        </div>

        <!-- Filter Chips & Search -->
        <div class="mt-2">
            <div class="d-flex flex-wrap justify-content-center mb-3">
                <a href="battles.php" class="filter-chip <?= $category === 'all' && $status === 'all' ? 'active' : '' ?>">All Battles</a>
                <a href="battles.php?status=live" class="filter-chip <?= $status === 'live' ? 'active' : '' ?>">🔴 Live Now</a>
                <a href="battles.php?status=upcoming" class="filter-chip <?= $status === 'upcoming' ? 'active' : '' ?>">⚡ Upcoming</a>
                <a href="battles.php?category=Rap+Battle" class="filter-chip <?= $category === 'Rap Battle' ? 'active' : '' ?>">🎤 Rap Battles</a>
                <a href="battles.php?category=Dance+Battle" class="filter-chip <?= $category === 'Dance Battle' ? 'active' : '' ?>">💃 Dance Battles</a>
                <a href="battles.php?category=Singing+Battle" class="filter-chip <?= $category === 'Singing Battle' ? 'active' : '' ?>">🎵 Singing Duels</a>
                <a href="battles.php?status=ended" class="filter-chip <?= $status === 'ended' ? 'active' : '' ?>">🏁 Past Battles</a>
            </div>

            <form method="GET" class="d-flex justify-content-center">
                <?php if ($category !== 'all'): ?><input type="hidden" name="category" value="<?= e($category) ?>"><?php endif; ?>
                <?php if ($status !== 'all'): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
                <input type="text" name="search" value="<?= e($search) ?>" class="search-bar" placeholder="🔍 Search battles by contestant name, category, or venue...">
            </form>
        </div>
    </div>
</section>

<!-- Battles Grid Section -->
<section class="py-5">
    <div class="container">
        <?php if (empty($allBattles)): ?>
            <div class="text-center py-5">
                <i class="fas fa-bolt fa-3x text-secondary opacity-50 mb-3"></i>
                <h4 class="text-white fw-bold">No battles found matching your criteria</h4>
                <p class="text-secondary small mb-4">Try clearing your filters or search keywords to view all scheduled battles.</p>
                <a href="battles.php" class="btn btn-outline-warning btn-sm px-4">View All Battles</a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($allBattles as $b): ?>
                    <?php
                        $isLive = ($b['status'] === 'live');
                        $isEnded = ($b['status'] === 'ended');
                        $isCancelled = ($b['status'] === 'cancelled');
                        $bannerImg = !empty($b['banner_image']) ? $b['banner_image'] : 'assets2/images/login-bg.jpg';
                        $dateTimeStr = BattlesService::formatBattleDateTime($b['battle_date'], $b['battle_time']);
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="battle-card <?= $isLive ? 'card-live' : '' ?>">
                            <!-- Top Banner Image -->
                            <div class="battle-banner-wrap">
                                <img src="<?= e($bannerImg) ?>" alt="<?= e($b['title']) ?>" loading="lazy">
                                <span class="battle-category-pill"><?= e($b['category']) ?></span>
                                <div class="battle-status-wrap">
                                    <?= BattlesService::getStatusBadgeHtml($b['status']) ?>
                                </div>
                            </div>

                            <!-- Matchup Arena -->
                            <div class="matchup-arena">
                                <div class="contestant-fighter">
                                    <div class="fighter-avatar-wrap">
                                        <img src="<?= e($b['contestant_one_image']) ?>" alt="<?= e($b['contestant_one_name']) ?>" loading="lazy">
                                    </div>
                                    <div class="fighter-name" title="<?= e($b['contestant_one_name']) ?>"><?= e($b['contestant_one_name']) ?></div>
                                    <span class="fighter-tag">Contestant 1</span>
                                </div>

                                <div class="vs-badge-epic">VS</div>

                                <div class="contestant-fighter">
                                    <div class="fighter-avatar-wrap">
                                        <img src="<?= e($b['contestant_two_image']) ?>" alt="<?= e($b['contestant_two_name']) ?>" loading="lazy">
                                    </div>
                                    <div class="fighter-name" title="<?= e($b['contestant_two_name']) ?>"><?= e($b['contestant_two_name']) ?></div>
                                    <span class="fighter-tag">Contestant 2</span>
                                </div>
                            </div>

                            <!-- Battle Details & Metadata -->
                            <div class="battle-content">
                                <div>
                                    <h5 class="battle-title text-truncate-2" title="<?= e($b['title']) ?>"><?= e($b['title']) ?></h5>
                                    <?php if (!empty($b['description'])): ?>
                                        <p class="battle-desc"><?= e($b['description']) ?></p>
                                    <?php endif; ?>

                                    <div class="battle-info-row">
                                        <div class="battle-info-item">
                                            <i class="far fa-calendar-alt text-warning"></i>
                                            <span><strong>Schedule:</strong> <?= e($dateTimeStr) ?></span>
                                        </div>

                                        <?php if ($b['venue_type'] === 'online'): ?>
                                            <div class="battle-info-item">
                                                <?= BattlesService::getPlatformIconHtml($b['platform']) ?>
                                                <span><strong>Venue:</strong> <?= e($b['platform'] ?? 'Online Livestream') ?></span>
                                            </div>
                                        <?php else: ?>
                                            <div class="battle-info-item">
                                                <i class="fas fa-map-marker-alt text-danger"></i>
                                                <span class="text-truncate" title="<?= e(($b['venue_name'] ?? '') . ', ' . ($b['venue_city'] ?? '')) ?>">
                                                    <strong>Venue:</strong> <?= e($b['venue_name'] ?? 'Physical Stage') ?><?= !empty($b['venue_city']) ? ', ' . e($b['venue_city']) : '' ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Action CTA -->
                                <div>
                                    <?php if ($b['venue_type'] === 'online'): ?>
                                        <?php if (!empty($b['live_url']) && !$isEnded && !$isCancelled): ?>
                                            <a href="<?= e($b['live_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn-battle-action">
                                                <?= BattlesService::getPlatformIconHtml($b['platform']) ?>
                                                <?= $isLive ? 'Watch Live Stream' : 'Watch Battle on ' . e($b['platform']) ?>
                                                <i class="fas fa-external-link-alt ms-1 small"></i>
                                            </a>
                                        <?php elseif ($isEnded): ?>
                                            <div class="btn-battle-concluded"><i class="fas fa-flag-checkered me-1"></i> Battle Concluded</div>
                                        <?php else: ?>
                                            <a href="https://instagram.com/crownnightstar" target="_blank" rel="noopener" class="btn-battle-action">
                                                <i class="fas fa-broadcast-tower me-1"></i> Watch Official Live
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if (!empty($b['maps_url']) && !$isEnded && !$isCancelled): ?>
                                            <a href="<?= e($b['maps_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn-battle-location">
                                                <i class="fas fa-map-marked-alt me-1"></i> View Location & Directions
                                                <i class="fas fa-external-link-alt ms-1 small"></i>
                                            </a>
                                        <?php elseif (!$isEnded && !$isCancelled): ?>
                                            <button type="button" class="btn-battle-location" onclick="showVenueModal(<?= htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8') ?>)">
                                                <i class="fas fa-building me-1"></i> View Venue Details
                                            </button>
                                        <?php else: ?>
                                            <div class="btn-battle-concluded"><i class="fas fa-flag-checkered me-1"></i> Battle Concluded</div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Venue Information Modal -->
<div class="modal fade" id="venueModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border border-warning">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-warning"><i class="fas fa-map-marker-alt me-2"></i> Event Venue Information</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="venueModalBody">
                <!-- Populated via JavaScript -->
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<footer>
    <div class="container text-center">
        <p class="text-secondary small mb-2">&copy; <?= date('Y') ?> <?= e($siteTitle) ?>. All rights reserved.</p>
        <p class="text-secondary small mb-0">Empowering talents, models, and community voices through transparent online contests.</p>
    </div>
</footer>

<script src="assets2/js/bootstrap.bundle.min.js"></script>
<script>
function showVenueModal(battle) {
    let body = `
        <h5 class="fw-bold text-white mb-2">${battle.title}</h5>
        <div class="p-3 bg-black rounded border border-secondary mb-3">
            <div class="fw-bold text-warning fs-5 mb-1">${battle.venue_name || 'Event Venue'}</div>
            <div class="text-light small mb-1">${battle.venue_address || ''}</div>
            <div class="text-secondary small">${battle.venue_city || ''} ${battle.venue_state ? ', ' + battle.venue_state : ''}</div>
        </div>
        <div class="small text-secondary mb-2"><strong>Date & Time:</strong> ${battle.battle_date} • ${battle.battle_time}</div>
    `;
    if (battle.maps_url) {
        body += `<a href="${battle.maps_url}" target="_blank" class="btn btn-warning btn-sm w-100 fw-bold"><i class="fas fa-map-marked-alt me-1"></i> Open in Google Maps</a>`;
    }
    document.getElementById('venueModalBody').innerHTML = body;
    new bootstrap.Modal(document.getElementById('venueModal')).show();
}
</script>
</body>
</html>


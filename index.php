<?php
require_once __DIR__ . '/config.php';

$pdo = DB::pdo();

// 1. Fetch Dynamic Stage & Settings
$stageName = Settings::getCurrentStage();
$competitionEndTime = Settings::getCompetitionEndTime();
$isRegistrationOpen = Settings::isRegistrationOpen();
$isVotingOpen = Settings::isVotingOpen();
$votePrice = Settings::getVotePrice();
$currencySymbol = Settings::getCurrencySymbol();
$siteTitle = Settings::get('site_title', 'Crown Night Star');
$siteTagline = Settings::get('site_tagline', 'Most Anticipated Online Contest');

// 2. Fetch Latest Active Hero Banner
$bannerStmt = $pdo->query("SELECT image_path FROM banner WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
$bannerRow = $bannerStmt->fetch();
$bannerImage = $bannerRow['image_path'] ?? 'assets/images/banner.jpg';

// 3. Fetch Featured Competitions
$compStmt = $pdo->query("SELECT * FROM competitions WHERE status = 'active' ORDER BY created_at DESC");
$competitions = $compStmt->fetchAll();

// 4. Fetch All Contestants Ordered by Vote Count Descending (Fail-Safe & Cross-Version)
$contestants = [];
try {
    $contestantsStmt = $pdo->query("
        SELECT id, username, full_name, photo, vote_count
        FROM users 
        WHERE is_admin = 0 AND is_active = 1
        ORDER BY vote_count DESC
    ");
    $rawList = $contestantsStmt->fetchAll();
    $pos = 1;
    foreach ($rawList as $item) {
        $item['position'] = $pos++;
        $contestants[] = $item;
    }
} catch (Exception $e) {
    error_log("Contestants query error on index: " . $e->getMessage());
}

// 5. Fetch Featured Bookstore Publications
$featuredBooks = BookstoreService::getActiveBooks();
$paystackPublicKey = Env::get('PAYSTACK_PUBLIC_KEY', '');
$currentUser = Auth::getCurrentUser();
$buyerDefaultName = $currentUser['full_name'] ?? '';
$buyerDefaultEmail = $currentUser['email'] ?? '';
$buyerDefaultPhone = $currentUser['phone_number'] ?? '';
$buyerUserId = $currentUser ? (int)$currentUser['id'] : 0;
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($siteTitle) ?> - <?= e($siteTagline) ?></title>
    <meta name="description" content="Vote and support your favorite contestants in <?= e($siteTitle) ?>.">

    <!-- OpenGraph -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($siteTitle) ?> - <?= e($siteTagline) ?>">
    <meta property="og:description" content="Vote and support your favorite contestants in <?= e($siteTitle) ?>.">
    <meta property="og:image" content="<?= e($bannerImage) ?>">

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
        .hero-section {
            padding: 60px 0 40px;
            text-align: center;
            position: relative;
        }
        .stage-pill {
            display: inline-block;
            background: rgba(255, 215, 0, 0.12);
            border: 1px solid rgba(255, 215, 0, 0.3);
            color: #ffd700;
            font-size: 13px;
            font-weight: 700;
            padding: 6px 20px;
            border-radius: 50px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 16px;
        }
        .hero-title {
            font-size: clamp(32px, 5vw, 54px);
            font-weight: 800;
            background: linear-gradient(135deg, #ffffff 0%, #ffd700 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 16px;
        }
        .countdown-box {
            background: rgba(25, 20, 50, 0.8);
            border: 1px solid rgba(255, 215, 0, 0.3);
            border-radius: 16px;
            padding: 16px 24px;
            display: inline-block;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            margin-bottom: 30px;
        }
        .countdown-digits {
            font-size: 26px;
            font-weight: 800;
            color: #ffd700;
            letter-spacing: 1px;
        }
        .banner-wrapper {
            max-width: 960px;
            margin: 0 auto 50px;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(255, 215, 0, 0.2);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
        }
        .banner-wrapper img {
            width: 100%;
            max-height: 420px;
            object-fit: cover;
            display: block;
        }
        .stage-card {
            background: rgba(22, 17, 44, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 24px;
            height: 100%;
            transition: all 0.3s ease;
        }
        .stage-card:hover {
            border-color: rgba(255, 215, 0, 0.4);
            transform: translateY(-4px);
        }
        .contestant-card {
            background: rgba(22, 17, 44, 0.85);
            border: 1px solid rgba(255, 215, 0, 0.2);
            border-radius: 18px;
            overflow: hidden;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .contestant-card:hover {
            transform: translateY(-6px);
            border-color: #ffd700;
            box-shadow: 0 15px 35px rgba(255, 215, 0, 0.2);
        }
        .card-img-wrap {
            position: relative;
            width: 100%;
            padding-top: 100%; /* 1:1 Aspect ratio */
            background: #0f0c20;
            overflow: hidden;
        }
        .card-img-wrap img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        .contestant-card:hover .card-img-wrap img {
            transform: scale(1.05);
        }
        .rank-tag {
            position: absolute;
            top: 12px;
            left: 12px;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(8px);
            color: #ffd700;
            border: 1px solid rgba(255, 215, 0, 0.4);
            font-size: 12px;
            font-weight: 800;
            padding: 4px 12px;
            border-radius: 50px;
        }
        .btn-gold {
            background: linear-gradient(135deg, #ffd700 0%, #d4af37 100%);
            color: #0d1117;
            font-weight: 700;
            border-radius: 10px;
            border: none;
            padding: 10px 16px;
            text-decoration: none;
            text-align: center;
            display: block;
            transition: all 0.3s ease;
        }
        .btn-gold:hover {
            background: linear-gradient(135deg, #ffe033 0%, #e5bd3b 100%);
            color: #0d1117;
            transform: translateY(-1px);
        }
        .search-bar {
            background: rgba(22, 17, 44, 0.9);
            border: 1px solid rgba(255, 215, 0, 0.3);
            border-radius: 50px;
            padding: 12px 24px;
            color: #fff;
            width: 100%;
            max-width: 480px;
            margin: 0 auto 30px;
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
                <li class="nav-item"><a class="nav-link text-white fw-semibold" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link text-light" href="#contestants">Contestants</a></li>
                <li class="nav-item"><a class="nav-link text-warning fw-semibold" href="bookstore.php"><i class="fas fa-book-open me-1"></i> Bookstore</a></li>
                <li class="nav-item"><a class="nav-link text-light" href="about-us.php">About Contest</a></li>
                <li class="nav-item"><a class="nav-link text-light" href="terms.php">Terms & Rules</a></li>
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
<section class="hero-section">
    <div class="container">
        <div class="stage-pill">
            <i class="fas fa-layer-group me-1"></i> <?= e($stageName) ?> is Live
        </div>

        <h1 class="hero-title"><?= e($siteTitle) ?></h1>
        <p class="text-secondary fs-5 mb-4 mx-auto" style="max-width: 680px;">
            Empowering participants through beauty, talent, and public community votes. Support your favorite contestant today!
        </p>

        <!-- Countdown Timer Box -->
        <div class="countdown-box">
            <div class="text-secondary small fw-bold text-uppercase mb-1">Voting Period Closes In:</div>
            <div class="countdown-digits" id="liveTimer">Calculating remaining time...</div>
        </div>

        <!-- Banner Image -->
        <?php if (!empty($bannerImage)): ?>
            <div class="banner-wrapper">
                <img src="<?= e($bannerImage) ?>" alt="<?= e($siteTitle) ?> Banner">
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Contest Rules & Stages -->
<section class="py-5" style="background: rgba(10, 8, 22, 0.6);">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold text-white mb-2">How The Contest Works</h2>
            <p class="text-secondary">Official 3-stage competition structure and rules</p>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="stage-card">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="badge bg-warning text-dark fw-bold px-3 py-2">Stage 1</span>
                        <i class="fas fa-users text-warning fs-4"></i>
                    </div>
                    <h5 class="fw-bold text-white mb-2">Open Stage (7 Days)</h5>
                    <p class="text-secondary small mb-0">
                        Top 50 contestants with a minimum of 150 votes qualify and advance to the next stage.
                    </p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stage-card">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="badge bg-info text-dark fw-bold px-3 py-2">Stage 2</span>
                        <i class="fas fa-medal text-info fs-4"></i>
                    </div>
                    <h5 class="fw-bold text-white mb-2">Semi-Finals (7 Days)</h5>
                    <p class="text-secondary small mb-0">
                        Top 30 contestants with a minimum of 250 votes advance to the grand final stage.
                    </p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stage-card">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="badge bg-success text-white fw-bold px-3 py-2">Stage 3</span>
                        <i class="fas fa-trophy text-success fs-4"></i>
                    </div>
                    <h5 class="fw-bold text-white mb-2">Grand Finale (7 Days)</h5>
                    <p class="text-secondary small mb-0">
                        Final voting round. Contestant with the highest cumulative votes is crowned champion!
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contestants Leaderboard -->
<section class="py-5" id="contestants">
    <div class="container">
        <div class="text-center mb-4">
            <span class="badge bg-warning text-dark fw-bold px-3 py-2 mb-2 text-uppercase">Live Standings</span>
            <h2 class="fw-bold text-white mb-2">Meet Our Contestants</h2>
            <p class="text-secondary">Vote for your favorite contestant to help them advance</p>

            <!-- Real-time Filter / Search Input -->
            <input type="text" id="contestantSearch" class="search-bar" placeholder="🔍 Search contestant by name or username..." onkeyup="filterContestants()">
        </div>

        <?php if (empty($contestants)): ?>
            <div class="text-center py-5">
                <i class="fas fa-user-friends fa-3x text-secondary mb-3"></i>
                <h5 class="text-secondary">No contestants registered yet.</h5>
                <?php if ($isRegistrationOpen): ?>
                    <a href="register.php" class="btn btn-warning mt-2 fw-bold">Be the First to Register!</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="row g-4" id="contestantsGrid">
                <?php foreach ($contestants as $c): ?>
                    <div class="col-6 col-md-4 col-lg-3 contestant-item" data-name="<?= strtolower(e($c['full_name'] . ' ' . $c['username'])) ?>">
                        <div class="contestant-card">
                            <div class="card-img-wrap">
                                <div class="rank-tag">#<?= (int)$c['position'] ?></div>
                                <img src="uploads/<?= e($c['photo']) ?>" alt="<?= e($c['full_name']) ?>" loading="lazy">
                            </div>
                            <div class="p-3 d-flex flex-column flex-grow-1 justify-content-between">
                                <div>
                                    <h6 class="fw-bold text-white mb-1 text-truncate" title="<?= e($c['full_name']) ?>"><?= e($c['full_name']) ?></h6>
                                    <p class="text-secondary small mb-2 text-truncate">@<?= e($c['username']) ?></p>
                                </div>
                                <div class="pt-2 border-top border-secondary border-opacity-25">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small text-secondary fw-semibold">Votes</span>
                                        <span class="fw-bold text-warning"><?= number_format((int)$c['vote_count']) ?></span>
                                    </div>
                                    <a href="profile.php?id=<?= $c['id'] ?>" class="btn-gold">
                                        <i class="fas fa-vote-yea me-1"></i> Vote Now
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Bookstore Showcase Slider Section -->
<?php if (!empty($featuredBooks)): ?>
<section class="py-5" style="background: rgba(18, 14, 38, 0.75); border-top: 1px solid rgba(255, 215, 0, 0.15); border-bottom: 1px solid rgba(255, 215, 0, 0.15);">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end mb-4 gap-3">
            <div>
                <span class="badge bg-warning text-dark px-3 py-2 fw-bold text-uppercase mb-2"><i class="fas fa-book-open me-1"></i> Digital Publications</span>
                <h2 class="fw-bold text-white mb-1">Official Spiritual Bookstore</h2>
                <p class="text-secondary small mb-0">Masterclasses and digital playbooks to supercharge your performance, charisma, and brand</p>
            </div>
            <div>
                <a href="bookstore.php" class="btn btn-outline-warning btn-sm px-3 fw-bold">
                    View All Books <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>

        <!-- Carousel Slider -->
        <div id="booksCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="4500">
            <div class="carousel-inner">
                <?php
                    $chunks = array_chunk($featuredBooks, 3);
                    foreach ($chunks as $chunkIndex => $chunk):
                ?>
                    <div class="carousel-item <?= $chunkIndex === 0 ? 'active' : '' ?>">
                        <div class="row g-4">
                            <?php foreach ($chunk as $b): ?>
                                <?php
                                    $priceStr = $currencySymbol . number_format((float)$b['price'], 2);
                                    $whatsAppUrl = BookstoreService::getWhatsAppUrl($b);
                                    $coverPath = $b['cover_image'];
                                    if (!file_exists(__DIR__ . '/' . $coverPath) && file_exists(__DIR__ . '/assets2/images/book1.jpg')) {
                                        $coverPath = 'assets2/images/book1.jpg';
                                    }
                                ?>
                                <div class="col-md-4">
                                    <div class="card bg-dark border-secondary border-opacity-50 rounded-4 overflow-hidden h-100 shadow-lg">
                                        <div class="p-3 text-center" style="background: #0f0c20;">
                                            <img src="<?= e($coverPath) ?>" alt="<?= e($b['title']) ?>" class="img-fluid rounded-3 shadow" style="max-height: 220px;">
                                        </div>
                                        <div class="p-3 d-flex flex-column flex-grow-1 justify-content-between">
                                            <div>
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="badge bg-warning text-dark" style="font-size: 10px;"><?= e($b['category'] ?? 'Guidebook') ?></span>
                                                    <span class="fw-bold text-warning small"><?= $priceStr ?></span>
                                                </div>
                                                <h6 class="fw-bold text-white mb-1 text-truncate" title="<?= e($b['title']) ?>"><?= e($b['title']) ?></h6>
                                                <p class="text-secondary small mb-2">By <?= e($b['author']) ?></p>
                                                <p class="text-secondary small mb-3" style="font-size: 12px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                                    <?= e($b['short_description'] ?? substr($b['description'], 0, 80) . '...') ?>
                                                </p>
                                            </div>
                                            <div>
                                                <button type="button" class="btn btn-gold btn-sm w-100 fw-bold py-2" onclick="startIndexBookCheckout(<?= htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8') ?>)">
                                                    <i class="fas fa-shopping-bag me-1"></i> Order Now (<?= $priceStr ?>)
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (count($chunks) > 1): ?>
                <div class="d-flex justify-content-center gap-2 mt-4">
                    <button class="btn btn-outline-warning btn-sm rounded-circle px-3 py-2" type="button" data-bs-target="#booksCarousel" data-bs-slide="prev">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="btn btn-outline-warning btn-sm rounded-circle px-3 py-2" type="button" data-bs-target="#booksCarousel" data-bs-slide="next">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Footer -->
<footer>
    <div class="container">
        <div class="row gy-4">
            <div class="col-lg-4">
                <h5 class="text-white fw-bold mb-3"><i class="fas fa-crown text-warning me-2"></i> <?= e($siteTitle) ?></h5>
                <p class="text-secondary small mb-0">
                    Transparent, secure online voting competition celebrating excellence, talent, and beauty.
                </p>
            </div>
            <div class="col-6 col-lg-2">
                <h6 class="text-white fw-bold mb-3">Quick Links</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="index.php" class="text-secondary text-decoration-none">Home</a></li>
                    <li class="mb-2"><a href="#contestants" class="text-secondary text-decoration-none">Contestants</a></li>
                    <li class="mb-2"><a href="bookstore.php" class="text-warning text-decoration-none"><i class="fas fa-book-open me-1"></i> Bookstore</a></li>
                    <li class="mb-2"><a href="about-us.php" class="text-secondary text-decoration-none">About Us</a></li>
                    <li class="mb-2"><a href="terms.php" class="text-secondary text-decoration-none">Terms & Rules</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <h6 class="text-white fw-bold mb-3">Contestants</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="login.php" class="text-secondary text-decoration-none">Sign In</a></li>
                    <?php if ($isRegistrationOpen): ?>
                        <li class="mb-2"><a href="register.php" class="text-secondary text-decoration-none">Register</a></li>
                    <?php endif; ?>
                    <li class="mb-2"><a href="dashboard.php" class="text-secondary text-decoration-none">My Dashboard</a></li>
                </ul>
            </div>
            <div class="col-lg-4">
                <h6 class="text-white fw-bold mb-3">Contact Support</h6>
                <p class="text-secondary small mb-1"><i class="fas fa-envelope text-warning me-2"></i> <?= e(Settings::get('support_email', 'hello@theusersportal.cloud')) ?></p>
                <p class="text-secondary small mb-0"><i class="fas fa-phone text-warning me-2"></i> <?= e(Settings::get('support_phone', '09067619370')) ?></p>
            </div>
        </div>
        <hr class="border-secondary opacity-25 my-4">
        <div class="text-center text-secondary small">
            &copy; <?= date('Y') ?> <strong><?= e($siteTitle) ?></strong>. All Rights Reserved.
        </div>
    </div>
</footer>

<!-- Index Book Checkout Modal -->
<div class="modal fade" id="indexCheckoutBookModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border border-secondary">
            <div class="modal-header border-secondary border-opacity-25">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-lock text-warning me-2"></i> Digital Book Checkout</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex align-items-center gap-3 p-3 rounded-3 bg-black border border-secondary mb-3">
                    <img id="idxCheckoutCover" src="" width="55" height="75" class="rounded object-fit-cover shadow">
                    <div class="flex-grow-1">
                        <span class="badge bg-warning text-dark mb-1 small" id="idxCheckoutCategory"></span>
                        <h6 class="fw-bold text-white mb-0" id="idxCheckoutTitle"></h6>
                        <span class="text-warning fw-bold fs-6" id="idxCheckoutPrice"></span>
                    </div>
                </div>

                <div class="alert alert-info border-0 p-2 small mb-3" style="background: rgba(13, 110, 253, 0.15); color: #70b8ff;">
                    <i class="fas fa-info-circle me-1"></i> Your instant access link and download credentials will be delivered immediately upon payment.
                </div>

                <form id="idxCheckoutForm" onsubmit="event.preventDefault(); processIndexBookPayment();">
                    <input type="hidden" id="idxCheckoutBookId" value="">
                    <input type="hidden" id="idxCheckoutAmountNumber" value="0">

                    <div class="mb-3">
                        <label class="form-label text-light small fw-semibold">Your Full Name *</label>
                        <input type="text" id="idxCheckoutBuyerName" class="form-control bg-dark border-secondary text-white" placeholder="e.g. Jane Doe" value="<?= e($buyerDefaultName) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-light small fw-semibold">Your Email Address * <span class="text-secondary">(Required for delivery)</span></label>
                        <input type="email" id="idxCheckoutBuyerEmail" class="form-control bg-dark border-secondary text-white" placeholder="youremail@example.com" value="<?= e($buyerDefaultEmail) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-light small fw-semibold">Phone Number / WhatsApp (Optional)</label>
                        <input type="tel" id="idxCheckoutBuyerPhone" class="form-control bg-dark border-secondary text-white" placeholder="090..." value="<?= e($buyerDefaultPhone) ?>">
                    </div>

                    <div id="idxCheckoutStatusMessage" class="d-none alert mb-3 small"></div>

                    <button type="submit" id="idxPaystackPayBtn" class="btn btn-gold w-100 py-3 fw-bold">
                        <i class="fas fa-shield-alt me-1"></i> Pay with Paystack
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="assets2/js/bootstrap.bundle.min.js"></script>
<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
const CURRENCY_SYMBOL = '<?= e($currencySymbol) ?>';
const PAYSTACK_PUBLIC_KEY = '<?= e($paystackPublicKey) ?>';
const LOGGED_IN_USER_ID = <?= $buyerUserId ?>;

let activeIdxSelectedBook = null;

function startIndexBookCheckout(book) {
    activeIdxSelectedBook = book;
    document.getElementById('idxCheckoutBookId').value = book.id;
    document.getElementById('idxCheckoutAmountNumber').value = book.price;
    document.getElementById('idxCheckoutCover').src = book.cover_image;
    document.getElementById('idxCheckoutTitle').innerText = book.title;
    document.getElementById('idxCheckoutCategory').innerText = book.category || 'Digital Publication';
    document.getElementById('idxCheckoutPrice').innerText = CURRENCY_SYMBOL + parseFloat(book.price).toLocaleString(undefined, {minimumFractionDigits: 2});

    const statusBox = document.getElementById('idxCheckoutStatusMessage');
    statusBox.className = 'd-none alert mb-3 small';
    statusBox.innerText = '';

    const modal = new bootstrap.Modal(document.getElementById('indexCheckoutBookModal'));
    modal.show();
}

function processIndexBookPayment() {
    const bookId = parseInt(document.getElementById('idxCheckoutBookId').value, 10);
    const amount = parseFloat(document.getElementById('idxCheckoutAmountNumber').value);
    const email = document.getElementById('idxCheckoutBuyerEmail').value.trim();
    const name = document.getElementById('idxCheckoutBuyerName').value.trim();
    const phone = document.getElementById('idxCheckoutBuyerPhone').value.trim();
    const statusBox = document.getElementById('idxCheckoutStatusMessage');
    const payBtn = document.getElementById('idxPaystackPayBtn');

    if (!email) {
        statusBox.className = 'alert alert-danger mb-3 small';
        statusBox.innerText = 'Please enter a valid email address.';
        return;
    }

    if (!PAYSTACK_PUBLIC_KEY) {
        statusBox.className = 'alert alert-danger mb-3 small';
        statusBox.innerText = 'Payment gateway is not configured. Please contact administrator.';
        return;
    }

    const handler = PaystackPop.setup({
        key: PAYSTACK_PUBLIC_KEY,
        email: email,
        amount: Math.round(amount * 100),
        currency: 'NGN',
        metadata: {
            custom_fields: [
                { display_name: "Type", variable_name: "purchase_type", value: "digital_book" },
                { display_name: "Book ID", variable_name: "book_id", value: bookId },
                { display_name: "Book Title", variable_name: "book_title", value: activeIdxSelectedBook ? activeIdxSelectedBook.title : "" },
                { display_name: "Buyer Name", variable_name: "buyer_name", value: name },
                { display_name: "Buyer Phone", variable_name: "buyer_phone", value: phone }
            ]
        },
        callback: function(response) {
            statusBox.className = 'alert alert-warning mb-3 small';
            statusBox.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Verifying payment and generating your digital access...';
            payBtn.disabled = true;

            const formData = new FormData();
            formData.append('reference', response.reference);
            formData.append('book_id', bookId);
            formData.append('email', email);
            formData.append('name', name);
            formData.append('phone', phone);
            if (LOGGED_IN_USER_ID > 0) {
                formData.append('user_id', LOGGED_IN_USER_ID);
            }

            fetch('verify_book_purchase.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    statusBox.className = 'alert alert-success mb-3 small';
                    statusBox.innerHTML = '<i class="fas fa-check-circle me-1"></i> Success! Redirecting to your digital access hub...';
                    setTimeout(() => {
                        window.location.href = data.redirect_url;
                    }, 1200);
                } else {
                    statusBox.className = 'alert alert-danger mb-3 small';
                    statusBox.innerText = data.error || 'Payment verification failed.';
                    payBtn.disabled = false;
                }
            })
            .catch(err => {
                console.error(err);
                statusBox.className = 'alert alert-danger mb-3 small';
                statusBox.innerText = 'Network error while verifying payment. Please refresh and contact support.';
                payBtn.disabled = false;
            });
        },
        onClose: function() {
            statusBox.className = 'alert alert-secondary mb-3 small';
            statusBox.innerText = 'Payment window was closed.';
        }
    });

    handler.openIframe();
}

// Search Filter
function filterContestants() {
    const query = document.getElementById('contestantSearch').value.toLowerCase().trim();
    const items = document.querySelectorAll('.contestant-item');

    items.forEach(item => {
        const name = item.getAttribute('data-name');
        if (name.includes(query)) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
}

// Countdown Engine
function initCountdown(endTimeStr) {
    const timerEl = document.getElementById('liveTimer');
    const endDate = new Date(endTimeStr).getTime();

    if (isNaN(endDate)) {
        timerEl.innerText = "Active Stage";
        return;
    }

    const timer = setInterval(() => {
        const now = new Date().getTime();
        const diff = endDate - now;

        if (diff <= 0) {
            clearInterval(timer);
            timerEl.innerText = "Voting has concluded.";
            return;
        }

        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);

        timerEl.innerText = `${days}d ${hours}h ${minutes}m ${seconds}s`;
    }, 1000);
}

initCountdown('<?= e($competitionEndTime) ?>');
</script>

</body>
</html>
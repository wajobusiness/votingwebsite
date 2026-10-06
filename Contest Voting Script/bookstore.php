<?php
require_once __DIR__ . '/config.php';

BookstoreService::ensureTable();

$category = trim($_GET['category'] ?? 'all');
$search   = trim($_GET['search'] ?? '');

$allBooks = BookstoreService::getActiveBooks($category);

if (!empty($search)) {
    $searchLower = strtolower($search);
    $allBooks = array_filter($allBooks, function($b) use ($searchLower) {
        return strpos(strtolower($b['title']), $searchLower) !== false ||
               strpos(strtolower($b['author']), $searchLower) !== false ||
               strpos(strtolower($b['description']), $searchLower) !== false;
    });
}

$siteTitle = Settings::get('site_title', 'Crown Night Star');
$currency = Settings::getCurrencySymbol();
$isRegistrationOpen = Settings::isRegistrationOpen();
$paystackPublicKey = Env::get('PAYSTACK_PUBLIC_KEY', '');

$currentUser = Auth::getCurrentUser();
$buyerDefaultName = $currentUser['full_name'] ?? '';
$buyerDefaultEmail = $currentUser['email'] ?? '';
$buyerDefaultPhone = $currentUser['phone_number'] ?? '';
$buyerUserId = $currentUser ? (int)$currentUser['id'] : 0;

// Get unique categories
$pdo = DB::pdo();
$categories = [];
try {
    $catStmt = $pdo->query("SELECT DISTINCT category FROM books WHERE is_active = 1");
    $categories = $catStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Exception $e) {
    $categories = ['Pageantry & Leadership', 'Personal Branding', 'Digital Marketing', 'Modeling & Fashion'];
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Bookstore & Publications - <?= e($siteTitle) ?></title>
    <meta name="description" content="Discover premium ebooks, contest preparation guidebooks, and personal branding masterclasses on <?= e($siteTitle) ?>.">

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
        .hero-banner {
            padding: 60px 0 40px;
            text-align: center;
            position: relative;
        }
        .store-pill {
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
            font-size: clamp(32px, 5vw, 50px);
            font-weight: 800;
            background: linear-gradient(135deg, #ffffff 0%, #ffd700 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 16px;
        }
        .book-card {
            background: rgba(22, 17, 44, 0.85);
            border: 1px solid rgba(255, 215, 0, 0.18);
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
        }
        .book-card:hover {
            transform: translateY(-8px);
            border-color: #ffd700;
            box-shadow: 0 20px 40px rgba(255, 215, 0, 0.25);
        }
        .book-cover-wrap {
            position: relative;
            background: #0f0c20;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .book-cover-img {
            max-height: 280px;
            width: auto;
            max-width: 100%;
            border-radius: 10px;
            box-shadow: 0 12px 25px rgba(0,0,0,0.6);
            transition: transform 0.3s ease;
        }
        .book-card:hover .book-cover-img {
            transform: scale(1.04);
        }
        .price-tag {
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(135deg, #ffd700 0%, #d4af37 100%);
            color: #0d1117;
            font-weight: 800;
            font-size: 14px;
            padding: 6px 14px;
            border-radius: 50px;
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.4);
        }
        .delivery-badge {
            position: absolute;
            bottom: 15px;
            left: 15px;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 6px;
            backdrop-filter: blur(8px);
        }
        .badge-pdf { background: rgba(220, 53, 69, 0.85); color: #fff; }
        .badge-link { background: rgba(13, 110, 253, 0.85); color: #fff; }
        .badge-whatsapp { background: rgba(37, 211, 102, 0.9); color: #fff; }
        .btn-gold {
            background: linear-gradient(135deg, #ffd700 0%, #d4af37 100%);
            color: #0d1117;
            font-weight: 800;
            border-radius: 10px;
            border: none;
            padding: 10px 18px;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .btn-gold:hover {
            background: linear-gradient(135deg, #ffe033 0%, #e5bd3b 100%);
            color: #0d1117;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(255, 215, 0, 0.3);
        }
        .category-pill {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 50px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            background: rgba(255, 255, 255, 0.05);
            color: #cbd5e1;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .category-pill:hover, .category-pill.active {
            background: #ffd700;
            color: #0d1117;
            border-color: #ffd700;
        }
        .modal-content {
            background: #15102a;
            border: 1px solid rgba(255, 215, 0, 0.3);
            border-radius: 20px;
            color: #e2e8f0;
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
                <li class="nav-item"><a class="nav-link text-warning fw-bold active" href="bookstore.php"><i class="fas fa-book-open me-1"></i> Bookstore</a></li>
                <li class="nav-item"><a class="nav-link text-light" href="about-us.php">About Contest</a></li>
                <li class="nav-item"><a class="nav-link text-light" href="terms.php">Terms & Rules</a></li>
                <li class="nav-item"><a class="nav-link text-light" href="contact-us.php">Contact Us</a></li>
            </ul>

            <div class="d-flex align-items-center gap-2">
                <?php if (Auth::isUserLoggedIn()): ?>
                    <a href="dashboard.php" class="btn btn-warning btn-sm fw-bold"><i class="fas fa-user-circle me-1"></i> My Dashboard</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline-light btn-sm"><i class="fas fa-sign-in-alt me-1"></i> Login</a>
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
        <div class="store-pill">
            <i class="fas fa-book-reader me-1"></i> Official Digital Publications & Masterclasses
        </div>

        <h1 class="hero-title">Knowledge, Growth & Masterclasses</h1>
        <p class="text-secondary fs-5 mb-4 mx-auto" style="max-width: 680px;">
            Elevate your personal brand, stage performance, and public campaign strategy with instant access to our curated digital books and guides.
        </p>

        <!-- Search Bar -->
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-6">
                <form method="GET" action="bookstore.php" class="input-group input-group-lg shadow-lg">
                    <span class="input-group-text bg-dark border-secondary text-warning"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control bg-dark text-white border-secondary" placeholder="Search books by title, author, or keyword..." value="<?= e($search) ?>">
                    <button class="btn btn-gold px-4" type="submit">Search</button>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- Main Store Section -->
<section class="py-4">
    <div class="container">
        <!-- Category Filters -->
        <div class="d-flex flex-wrap gap-2 justify-content-center mb-5">
            <a href="bookstore.php" class="category-pill <?= ($category === 'all' || empty($category)) ? 'active' : '' ?>">All Books</a>
            <?php foreach ($categories as $cat): ?>
                <a href="bookstore.php?category=<?= urlencode($cat) ?>" class="category-pill <?= ($category === $cat) ? 'active' : '' ?>">
                    <?= e($cat) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Books Grid -->
        <?php if (empty($allBooks)): ?>
            <div class="text-center py-5">
                <i class="fas fa-book fa-3x text-secondary mb-3"></i>
                <h4 class="text-white fw-bold">No Books Found</h4>
                <p class="text-secondary small">Try adjusting your search criteria or category filter.</p>
                <a href="bookstore.php" class="btn btn-outline-warning btn-sm mt-2">Reset Filters</a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($allBooks as $b): ?>
                    <?php
                        $priceStr = $currency . number_format((float)$b['price'], 2);
                        $coverPath = $b['cover_image'];
                        if (!file_exists(__DIR__ . '/' . $coverPath) && file_exists(__DIR__ . '/assets2/images/book1.jpg')) {
                            $coverPath = 'assets2/images/book1.jpg';
                        }
                    ?>
                    <div class="col-md-6 col-lg-4 col-xl-3">
                        <div class="book-card">
                            <div class="book-cover-wrap">
                                <span class="price-tag"><?= $priceStr ?></span>
                                <?php if ($b['delivery_type'] === 'pdf'): ?>
                                    <span class="delivery-badge badge-pdf"><i class="fas fa-file-pdf me-1"></i> Instant PDF</span>
                                <?php elseif ($b['delivery_type'] === 'link'): ?>
                                    <span class="delivery-badge badge-link"><i class="fas fa-graduation-cap me-1"></i> Course Portal</span>
                                <?php else: ?>
                                    <span class="delivery-badge badge-whatsapp"><i class="fab fa-whatsapp me-1"></i> VIP Channel</span>
                                <?php endif; ?>
                                
                                <img src="<?= e($coverPath) ?>" alt="<?= e($b['title']) ?>" class="book-cover-img" loading="lazy">
                            </div>

                            <div class="p-3 d-flex flex-column flex-grow-1 justify-content-between">
                                <div>
                                    <div class="text-warning small fw-bold text-uppercase mb-1" style="font-size: 11px;">
                                        <?= e($b['category'] ?? 'Guidebook') ?>
                                    </div>
                                    <h5 class="fw-bold text-white mb-1" style="font-size: 16px; line-height: 1.4;">
                                        <?= e($b['title']) ?>
                                    </h5>
                                    <p class="text-secondary small mb-2">By <span class="text-light"><?= e($b['author']) ?></span> &bull; <span class="text-secondary"><?= (int)$b['pages_count'] ?> pages</span></p>
                                    <p class="text-secondary small mb-3" style="font-size: 12px; line-height: 1.5;">
                                        <?= e($b['short_description'] ?? substr($b['description'], 0, 100) . '...') ?>
                                    </p>
                                </div>

                                <div class="pt-3 border-top border-secondary border-opacity-25">
                                    <div class="d-grid gap-2">
                                        <!-- Instant Paystack Purchase Trigger -->
                                        <button type="button" class="btn btn-gold btn-sm py-2" onclick="startBookCheckout(<?= htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8') ?>)">
                                            <i class="fas fa-shopping-bag me-1"></i> Order Now (<?= $priceStr ?>)
                                        </button>

                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="openBookModal(<?= htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8') ?>)">
                                            <i class="fas fa-eye me-1"></i> Preview Synopsis & Excerpt
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Book Details / Excerpt Preview Modal -->
<div class="modal fade" id="bookModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-secondary border-opacity-25">
                <h5 class="modal-title fw-bold text-white" id="modalBookTitle">Book Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <div class="col-md-4 text-center">
                        <img id="modalBookCover" src="" alt="Book Cover" class="img-fluid rounded-3 shadow mb-3" style="max-height: 280px;">
                        <div class="p-2 rounded-3 bg-dark border border-secondary mb-3">
                            <span class="text-secondary small d-block">Price</span>
                            <span class="fs-5 fw-bold text-warning" id="modalBookPrice"></span>
                        </div>
                        <div id="modalOrderBtnContainer" class="d-grid gap-2"></div>
                    </div>
                    <div class="col-md-8">
                        <span class="badge bg-warning text-dark fw-bold mb-2" id="modalBookCategory"></span>
                        <h4 class="fw-bold text-white mb-1" id="modalBookHeading"></h4>
                        <p class="text-secondary small mb-3">By <span class="text-light fw-semibold" id="modalBookAuthor"></span></p>

                        <!-- Nav tabs -->
                        <ul class="nav nav-tabs border-secondary border-opacity-25 mb-3" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active text-white" data-bs-toggle="tab" data-bs-target="#tabDesc" type="button">Synopsis</button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link text-white" data-bs-toggle="tab" data-bs-target="#tabPreview" type="button">Sample Excerpt</button>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <div class="tab-pane fade show active text-light opacity-90 small" id="tabDesc" style="white-space: pre-line; line-height: 1.6; max-height: 260px; overflow-y: auto;"></div>
                            <div class="tab-pane fade text-light opacity-90 small font-monospace p-3 bg-dark rounded border border-secondary" id="tabPreview" style="white-space: pre-line; line-height: 1.6; max-height: 260px; overflow-y: auto;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Checkout / Order Modal -->
<div class="modal fade" id="checkoutBookModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-secondary border-opacity-25">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-lock text-warning me-2"></i> Digital Book Checkout</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Book Info Card -->
                <div class="d-flex align-items-center gap-3 p-3 rounded-3 bg-dark border border-secondary mb-3">
                    <img id="checkoutCover" src="" width="55" height="75" class="rounded object-fit-cover shadow">
                    <div class="flex-grow-1">
                        <span class="badge bg-warning text-dark mb-1 small" id="checkoutCategory"></span>
                        <h6 class="fw-bold text-white mb-0" id="checkoutTitle"></h6>
                        <span class="text-warning fw-bold fs-6" id="checkoutPrice"></span>
                    </div>
                </div>

                <div class="alert alert-info border-0 p-2 small mb-3" style="background: rgba(13, 110, 253, 0.15); color: #70b8ff;">
                    <i class="fas fa-info-circle me-1"></i> Your instant access link and download credentials will be delivered immediately upon payment.
                </div>

                <!-- Buyer Form -->
                <form id="checkoutForm" onsubmit="event.preventDefault(); processPaystackPayment();">
                    <input type="hidden" id="checkoutBookId" value="">
                    <input type="hidden" id="checkoutAmountNumber" value="0">

                    <div class="mb-3">
                        <label class="form-label text-light small fw-semibold">Your Full Name *</label>
                        <input type="text" id="checkoutBuyerName" class="form-control bg-dark border-secondary text-white" placeholder="e.g. Jane Doe" value="<?= e($buyerDefaultName) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-light small fw-semibold">Your Email Address * <span class="text-secondary">(Required for delivery)</span></label>
                        <input type="email" id="checkoutBuyerEmail" class="form-control bg-dark border-secondary text-white" placeholder="youremail@example.com" value="<?= e($buyerDefaultEmail) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-light small fw-semibold">Phone Number / WhatsApp (Optional)</label>
                        <input type="tel" id="checkoutBuyerPhone" class="form-control bg-dark border-secondary text-white" placeholder="090..." value="<?= e($buyerDefaultPhone) ?>">
                    </div>

                    <div id="checkoutStatusMessage" class="d-none alert mb-3 small"></div>

                    <button type="submit" id="paystackPayBtn" class="btn btn-gold w-100 py-3 fw-bold">
                        <i class="fas fa-shield-alt me-1"></i> Pay with Paystack
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="py-5 mt-5" style="background: #080611; border-top: 1px solid rgba(255, 215, 0, 0.15);">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <h5 class="text-white fw-bold mb-3"><i class="fas fa-crown text-warning me-2"></i> <?= e($siteTitle) ?></h5>
                <p class="text-secondary small mb-0">
                    Transparent, secure online voting competition and digital bookstore celebrating excellence, talent, and leadership.
                </p>
            </div>
            <div class="col-6 col-lg-2">
                <h6 class="text-white fw-bold mb-3">Quick Links</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="index.php" class="text-secondary text-decoration-none">Home</a></li>
                    <li class="mb-2"><a href="bookstore.php" class="text-warning text-decoration-none">Bookstore</a></li>
                    <li class="mb-2"><a href="about-us.php" class="text-secondary text-decoration-none">About Us</a></li>
                    <li class="mb-2"><a href="terms.php" class="text-secondary text-decoration-none">Terms & Rules</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <h6 class="text-white fw-bold mb-3">Contestants</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="login.php" class="text-secondary text-decoration-none">Sign In</a></li>
                    <li class="mb-2"><a href="register.php" class="text-secondary text-decoration-none">Register</a></li>
                    <li class="mb-2"><a href="dashboard.php" class="text-secondary text-decoration-none">Dashboard</a></li>
                </ul>
            </div>
            <div class="col-lg-4">
                <h6 class="text-white fw-bold mb-3">Need Assistance?</h6>
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

<script src="assets2/js/bootstrap.bundle.min.js"></script>
<script src="https://js.paystack.co/v1/inline.js"></script>

<script>
const CURRENCY_SYMBOL = '<?= e($currency) ?>';
const PAYSTACK_PUBLIC_KEY = '<?= e($paystackPublicKey) ?>';
const LOGGED_IN_USER_ID = <?= $buyerUserId ?>;

let activeSelectedBook = null;

function openBookModal(book) {
    activeSelectedBook = book;
    document.getElementById('modalBookHeading').innerText = book.title;
    document.getElementById('modalBookAuthor').innerText = book.author;
    document.getElementById('modalBookCategory').innerText = book.category || 'General';
    document.getElementById('modalBookPrice').innerText = CURRENCY_SYMBOL + parseFloat(book.price).toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('modalBookCover').src = book.cover_image;
    document.getElementById('tabDesc').innerText = book.description;
    document.getElementById('tabPreview').innerText = book.preview_text || "No preview excerpt available for this publication.";

    const priceFormatted = CURRENCY_SYMBOL + parseFloat(book.price).toLocaleString(undefined, {minimumFractionDigits: 2});
    const btnContainer = document.getElementById('modalOrderBtnContainer');
    btnContainer.innerHTML = `
        <button type="button" class="btn btn-gold fw-bold btn-sm py-2" onclick="bootstrap.Modal.getInstance(document.getElementById('bookModal')).hide(); startBookCheckout(activeSelectedBook);">
            <i class="fas fa-shopping-bag me-1"></i> Order Now (${priceFormatted})
        </button>
    `;

    const modal = new bootstrap.Modal(document.getElementById('bookModal'));
    modal.show();
}

function startBookCheckout(book) {
    activeSelectedBook = book;
    document.getElementById('checkoutBookId').value = book.id;
    document.getElementById('checkoutAmountNumber').value = book.price;
    document.getElementById('checkoutCover').src = book.cover_image;
    document.getElementById('checkoutTitle').innerText = book.title;
    document.getElementById('checkoutCategory').innerText = book.category || 'Digital Publication';
    document.getElementById('checkoutPrice').innerText = CURRENCY_SYMBOL + parseFloat(book.price).toLocaleString(undefined, {minimumFractionDigits: 2});

    const statusBox = document.getElementById('checkoutStatusMessage');
    statusBox.className = 'd-none alert mb-3 small';
    statusBox.innerText = '';

    const modal = new bootstrap.Modal(document.getElementById('checkoutBookModal'));
    modal.show();
}

function processPaystackPayment() {
    const bookId = parseInt(document.getElementById('checkoutBookId').value, 10);
    const amount = parseFloat(document.getElementById('checkoutAmountNumber').value);
    const email = document.getElementById('checkoutBuyerEmail').value.trim();
    const name = document.getElementById('checkoutBuyerName').value.trim();
    const phone = document.getElementById('checkoutBuyerPhone').value.trim();
    const statusBox = document.getElementById('checkoutStatusMessage');
    const payBtn = document.getElementById('paystackPayBtn');

    if (!email) {
        statusBox.className = 'alert alert-danger mb-3 small';
        statusBox.innerText = 'Please enter a valid email address.';
        return;
    }

    if (!PAYSTACK_PUBLIC_KEY) {
        statusBox.className = 'alert alert-danger mb-3 small';
        statusBox.innerText = 'Payment gateway public key is not configured. Please contact administrator.';
        return;
    }

    const handler = PaystackPop.setup({
        key: PAYSTACK_PUBLIC_KEY,
        email: email,
        amount: Math.round(amount * 100), // in kobo
        currency: 'NGN',
        metadata: {
            custom_fields: [
                { display_name: "Type", variable_name: "purchase_type", value: "digital_book" },
                { display_name: "Book ID", variable_name: "book_id", value: bookId },
                { display_name: "Book Title", variable_name: "book_title", value: activeSelectedBook ? activeSelectedBook.title : "" },
                { display_name: "Buyer Name", variable_name: "buyer_name", value: name },
                { display_name: "Buyer Phone", variable_name: "buyer_phone", value: phone }
            ]
        },
        callback: function(response) {
            statusBox.className = 'alert alert-warning mb-3 small';
            statusBox.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Payment received! Verifying and generating your access token...';
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
                    statusBox.innerText = data.error || 'Payment verification failed. Please contact support with Ref: ' + response.reference;
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
</script>

</body>
</html>

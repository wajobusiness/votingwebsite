<?php
require_once __DIR__ . '/config.php';

// If already logged in, redirect based on registration status
if (Auth::isUserLoggedIn()) {
    $curr = Auth::getCurrentUser();
    if ($curr && !RegistrationService::isUserRegistrationComplete($curr)) {
        header('Location: complete_registration.php');
    } else {
        header('Location: dashboard.php');
    }
    exit();
}

$isRegistrationOpen = Settings::isRegistrationOpen();
$regFee = RegistrationService::getRegistrationFee();
$currency = Settings::getCurrencySymbol();
$error = '';
$success = '';

// Check if request is AJAX
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || isset($_POST['is_ajax'])
    || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

// Handle Registration Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isRegistrationOpen) {
        $error = 'Contest registration is currently closed.';
    } else {
        $fullName    = trim($_POST['full_name'] ?? '');
        $username    = trim($_POST['username'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $phoneNumber = trim($_POST['phone_number'] ?? '');
        $password    = $_POST['password'] ?? '';
        $bio         = trim($_POST['bio'] ?? '');
        
        $rawVideo = trim($_POST['video_url'] ?? '');
        $videoUrl = null;
        if (!empty($rawVideo)) {
            if (!preg_match('~^(?:f|ht)tps?://~i', $rawVideo)) {
                $rawVideo = 'https://' . $rawVideo;
            }
            if (filter_var($rawVideo, FILTER_VALIDATE_URL)) {
                $videoUrl = $rawVideo;
            }
        }

        // Validation
        if (empty($fullName) || empty($username) || empty($email) || empty($password)) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            $error = 'Username must be at least 3 characters and contain only letters, numbers, hyphens, and underscores.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters in length.';
        } elseif (!isset($_FILES['photo']) || $_FILES['photo']['error'] === UPLOAD_ERR_NO_FILE) {
            $error = 'Please upload a profile photo for the competition.';
        } else {
            $pdo = DB::pdo();

            // Auto-ensure video_url and registration columns exist
            try {
                RegistrationService::ensureSchema();
            } catch (Exception $e) {
                // Handled in service
            }

            try {
                $pdo->query("SELECT video_url FROM users LIMIT 1");
            } catch (Exception $e) {
                try {
                    $pdo->exec("ALTER TABLE users ADD COLUMN video_url VARCHAR(500) DEFAULT NULL");
                } catch (Exception $e2) {
                    // Ignored
                }
            }

            // Check if username or email is already taken
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1");
            $checkStmt->execute([':u' => $username, ':e' => $email]);
            if ($checkStmt->fetch()) {
                $error = 'Username or email is already registered. Please sign in or use another.';
            } else {
                // Handle Secure Photo Upload
                $upload = Security::handleFileUpload($_FILES['photo'], __DIR__ . '/uploads/', ['jpg', 'jpeg', 'png', 'webp'], 5);

                if (!$upload['success']) {
                    $error = 'Photo upload failed: ' . $upload['error'];
                } else {
                    $photoFilename = $upload['filename'];
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                    $isFeeRequired = RegistrationService::isFeeRequired();
                    $initialRegStatus = $isFeeRequired ? 'pending' : 'exempt';

                    try {
                        try {
                            $insertStmt = $pdo->prepare("
                                INSERT INTO users (
                                    username, email, password, full_name, phone_number, photo, bio, video_url, 
                                    vote_count, is_admin, is_active, registration_status, registration_fee_paid
                                ) VALUES (
                                    :username, :email, :password, :full_name, :phone_number, :photo, :bio, :video_url, 
                                    0, 0, 1, :reg_status, 0.00
                                )
                            ");

                            $insertStmt->execute([
                                ':username'     => $username,
                                ':email'        => $email,
                                ':password'     => $passwordHash,
                                ':full_name'    => $fullName,
                                ':phone_number' => $phoneNumber,
                                ':photo'        => $photoFilename,
                                ':bio'          => $bio,
                                ':video_url'    => $videoUrl,
                                ':reg_status'   => $initialRegStatus
                            ]);
                        } catch (Exception $subEx) {
                            // Fallback if schema variations exist
                            $insertStmt = $pdo->prepare("
                                INSERT INTO users (
                                    username, email, password, full_name, photo, vote_count, is_admin, registration_status
                                ) VALUES (
                                    :username, :email, :password, :full_name, :photo, 0, 0, :reg_status
                                )
                            ");

                            $insertStmt->execute([
                                ':username'   => $username,
                                ':email'      => $email,
                                ':password'   => $passwordHash,
                                ':full_name'  => $fullName,
                                ':photo'      => $photoFilename,
                                ':reg_status' => $initialRegStatus
                            ]);
                        }

                        $newUserId = (int)$pdo->lastInsertId();

                        // Automatically log in newly registered contestant
                        Security::startSession();
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $newUserId;
                        $_SESSION['username'] = $username;
                        $_SESSION['user_email'] = $email;
                        $_SESSION['user_full_name'] = $fullName;

                        $redirectTarget = $isFeeRequired ? 'complete_registration.php' : 'dashboard.php';

                        if ($isAjax) {
                            header('Content-Type: application/json; charset=utf-8');
                            echo json_encode([
                                'status'   => 'success',
                                'message'  => $isFeeRequired ? 'Account created! Redirecting to payment...' : 'Registration successful! Redirecting to your dashboard...',
                                'redirect' => $redirectTarget
                            ]);
                            exit();
                        }

                        header("Location: {$redirectTarget}");
                        exit();

                    } catch (Exception $e) {
                        error_log("Registration DB Error: " . $e->getMessage());
                        $error = 'Database error occurred during registration: ' . $e->getMessage();
                    }
                }
            }
        }
    }

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        // Return status 200 with error payload so WAFs don't intercept with 403 page
        echo json_encode([
            'status'  => 'error',
            'message' => !empty($error) ? $error : 'An unexpected error occurred. Please try again.'
        ]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Join Contest - Register as Contestant</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets2/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets2/css/all.min.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #0f0c20 0%, #1a162b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 30px 15px;
        }
        .register-card {
            background: rgba(30, 26, 50, 0.9);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 215, 0, 0.2);
            border-radius: 20px;
            box-shadow: 0 16px 50px rgba(0, 0, 0, 0.6);
            width: 100%;
            max-width: 540px;
            padding: 40px 32px;
        }
        .brand-badge {
            display: inline-block;
            background: rgba(255, 215, 0, 0.15);
            color: #ffd700;
            border: 1px solid rgba(255, 215, 0, 0.3);
            border-radius: 50px;
            padding: 4px 14px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }
        .form-control, .form-select {
            background-color: #151124;
            border: 1px solid #3d3559;
            color: #e0daf5;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-control:focus, .form-select:focus {
            background-color: #151124;
            border-color: #ffd700;
            box-shadow: 0 0 0 0.2rem rgba(255, 215, 0, 0.2);
            color: #fff;
        }
        .btn-gold {
            background: linear-gradient(135deg, #ffd700 0%, #d4af37 100%);
            color: #0d1117;
            font-weight: 700;
            padding: 12px;
            border-radius: 8px;
            border: none;
            transition: all 0.3s ease;
        }
        .btn-gold:hover {
            background: linear-gradient(135deg, #ffe033 0%, #e5bd3b 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(255, 215, 0, 0.3);
            color: #0d1117;
        }
        .btn-gold:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        .preview-box {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            border: 2px dashed #ffd700;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #151124;
            margin: 0 auto 10px;
        }
        .preview-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: none;
        }
    </style>
</head>
<body>

<div class="register-card">
    <div class="text-center">
        <div class="brand-badge"><i class="fas fa-sparkles me-1"></i> Official Registration</div>
        <h3 class="fw-bold text-white mb-1">Enter Competition</h3>
        <p class="text-secondary mb-3 small">Create your profile to start receiving public votes</p>
        <?php if ($regFee > 0): ?>
            <div class="alert alert-info py-2 px-3 small border-0 mb-3 d-inline-flex align-items-center gap-2" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa;">
                <i class="fas fa-info-circle"></i>
                <span>Official Contestant Registration Fee: <strong class="text-warning"><?= $currency ?><?= number_format($regFee, 2) ?></strong></span>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$isRegistrationOpen): ?>
        <div class="alert alert-warning text-center border-0 p-4" style="background-color: rgba(255, 193, 7, 0.15); color: #ffd700;">
            <i class="fas fa-lock fa-2x mb-2 d-block"></i>
            <h5 class="fw-bold mb-1">Registration Closed</h5>
            <p class="small mb-3">Registration for the current stage has ended. You can still vote for your favorite contestants!</p>
            <a href="index.php" class="btn btn-gold btn-sm"><i class="fas fa-arrow-left me-1"></i> View Contestants</a>
        </div>
    <?php else: ?>

        <!-- Client Alert Message Container -->
        <div id="clientAlert" class="alert alert-danger py-2 px-3 small border-0 mb-3 <?= empty($error) ? 'd-none' : '' ?>" style="background-color: rgba(220, 53, 69, 0.2); color: #ff6b7d;">
            <i class="fas fa-exclamation-circle me-1"></i> <span id="alertMsg"><?= e($error) ?></span>
        </div>

        <form id="registerForm" method="POST" action="register.php" enctype="multipart/form-data">
            <?= Security::csrfField() ?>
            <input type="hidden" name="is_ajax" value="1">

            <!-- Photo Upload with Live Preview -->
            <div class="text-center mb-3">
                <div class="preview-box" id="previewContainer">
                    <i class="fas fa-camera text-secondary fa-lg" id="uploadPlaceholder"></i>
                    <img id="photoPreview" alt="Contestant Preview">
                </div>
                <label for="photo" class="btn btn-outline-secondary btn-sm" style="font-size: 12px; cursor: pointer;">
                    <i class="fas fa-upload me-1"></i> Choose Profile Photo *
                </label>
                <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" required class="d-none" onchange="previewImage(event)">
                <div class="form-text text-secondary" style="font-size: 11px;">Supported: JPG, PNG, WEBP (Max 5MB)</div>
            </div>

            <div class="row g-2">
                <div class="col-12 col-md-6 mb-2">
                    <label class="form-label text-light small fw-semibold" for="full_name">Full Name *</label>
                    <input type="text" id="full_name" name="full_name" required class="form-control" placeholder="e.g. Jane Doe" value="<?= e($_POST['full_name'] ?? '') ?>">
                </div>

                <div class="col-12 col-md-6 mb-2">
                    <label class="form-label text-light small fw-semibold" for="username">Username *</label>
                    <input type="text" id="username" name="username" required class="form-control" placeholder="e.g. janedoe" value="<?= e($_POST['username'] ?? '') ?>">
                </div>
            </div>

            <div class="row g-2">
                <div class="col-12 col-md-6 mb-2">
                    <label class="form-label text-light small fw-semibold" for="email">Email Address *</label>
                    <input type="email" id="email" name="email" required class="form-control" placeholder="jane@example.com" value="<?= e($_POST['email'] ?? '') ?>">
                </div>

                <div class="col-12 col-md-6 mb-2">
                    <label class="form-label text-light small fw-semibold" for="phone_number">Phone Number *</label>
                    <input type="tel" id="phone_number" name="phone_number" required class="form-control" placeholder="08012345678" value="<?= e($_POST['phone_number'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label text-light small fw-semibold" for="password">Password *</label>
                <input type="password" id="password" name="password" required class="form-control" placeholder="Create a secure password (min 6 characters)">
            </div>

            <div class="mb-3">
                <label class="form-label text-light small fw-semibold" for="video_url">
                    Performance Video Link <span class="text-secondary fw-normal">(Optional)</span>
                </label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-warning"><i class="fas fa-video"></i></span>
                    <input type="url" id="video_url" name="video_url" class="form-control" placeholder="https://youtube.com/watch?v=... or Reel URL" value="<?= e($_POST['video_url'] ?? '') ?>">
                </div>
                <div class="form-text text-secondary" style="font-size: 11px;">You can also add or update this anytime on your dashboard.</div>
            </div>

            <div class="d-grid mb-3">
                <button class="btn btn-gold" type="submit" id="submitBtn">
                    <i class="fas fa-check-circle me-2"></i> Continue to Complete Registration
                </button>
            </div>

            <p class="text-center text-secondary small mb-0">
                Already registered? <a href="login.php" class="text-warning text-decoration-none fw-bold">Sign In Here</a>
            </p>
        </form>

    <?php endif; ?>
</div>

<script>
function previewImage(event) {
    const input = event.target;
    const alertBox = document.getElementById('clientAlert');
    const alertMsg = document.getElementById('alertMsg');

    if (input.files && input.files[0]) {
        const file = input.files[0];

        // Validate size (max 5MB)
        if (file.size > 5 * 1024 * 1024) {
            alertMsg.textContent = 'Selected photo exceeds the 5MB size limit. Please choose a smaller image.';
            alertBox.classList.remove('d-none');
            input.value = '';
            return;
        }

        // Validate extension / MIME
        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!allowedTypes.includes(file.type) && !file.name.match(/\.(jpg|jpeg|png|webp)$/i)) {
            alertMsg.textContent = 'Invalid file format. Please upload a JPG, PNG, or WEBP image.';
            alertBox.classList.remove('d-none');
            input.value = '';
            return;
        }

        alertBox.classList.add('d-none');

        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('photoPreview');
            const placeholder = document.getElementById('uploadPlaceholder');
            preview.src = e.target.result;
            preview.style.display = 'block';
            placeholder.style.display = 'none';
        }
        reader.readAsDataURL(file);
    }
}

// Resilient AJAX Form Submission (Bypasses WAF & LiteSpeed 403 page trips)
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('registerForm');
    if (!form) return;

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const btn = document.getElementById('submitBtn');
        const alertBox = document.getElementById('clientAlert');
        const alertMsg = document.getElementById('alertMsg');

        // Client-side quick checks
        const photoInput = document.getElementById('photo');
        if (!photoInput.files || !photoInput.files[0]) {
            alertMsg.textContent = 'Please choose a profile photo for the competition.';
            alertBox.classList.remove('d-none');
            alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        const passInput = document.getElementById('password');
        if (passInput.value.length < 6) {
            alertMsg.textContent = 'Password must be at least 6 characters in length.';
            alertBox.classList.remove('d-none');
            passInput.focus();
            return;
        }

        alertBox.classList.add('d-none');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Creating Account & Uploading...';

        try {
            const formData = new FormData(form);
            formData.set('is_ajax', '1');

            const response = await fetch('register.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData
            });

            let data;
            const textResponse = await response.text();
            try {
                data = JSON.parse(textResponse);
            } catch (jsonErr) {
                // If response is HTML / redirect
                if (response.status === 200) {
                    window.location.href = 'complete_registration.php';
                    return;
                }
                throw new Error('Server returned an unexpected response. Please try again.');
            }

            if (data.status === 'success') {
                btn.className = 'btn btn-success fw-bold';
                btn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Account Created! Redirecting...';
                setTimeout(function() {
                    window.location.href = data.redirect || 'complete_registration.php';
                }, 500);
            } else {
                throw new Error(data.message || 'Registration failed. Please verify your details.');
            }

        } catch (err) {
            btn.disabled = false;
            btn.className = 'btn btn-gold';
            btn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Continue to Complete Registration';
            alertMsg.textContent = err.message || 'An error occurred during submission. Please try again.';
            alertBox.classList.remove('d-none');
            alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
});
</script>

</body>
</html>

<?php
/**
 * Global Configuration & Initialization Bridge
 */

require_once __DIR__ . '/includes/Env.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/VideoHelper.php';
require_once __DIR__ . '/includes/bookstore_service.php';

// Ensure secure session is initialized
Security::startSession();
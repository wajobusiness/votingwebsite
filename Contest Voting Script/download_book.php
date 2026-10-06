<?php
/**
 * Secure Digital PDF Download Controller
 * Validates purchase access token and streams file safely
 */

require_once __DIR__ . '/config.php';

$token  = trim($_GET['token'] ?? '');
$bookId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$pdfFileName = null;
$bookTitle   = 'digital_publication';

if (!empty($token)) {
    $purchase = BookstoreService::getPurchaseByToken($token);
    if (!$purchase || empty($purchase['pdf_file'])) {
        die("Invalid access token or digital file is not available.");
    }
    
    $pdfFileName = $purchase['pdf_file'];
    $bookTitle   = $purchase['book_title'] ?? 'digital_book';
    
    // Increment download counter
    BookstoreService::incrementDownloadCount((int)$purchase['id']);
} elseif ($bookId && Auth::isAdminLoggedIn()) {
    // Admin direct preview
    $book = BookstoreService::getBookById($bookId);
    if (!$book || empty($book['pdf_file'])) {
        die("Digital file not found in catalog.");
    }
    $pdfFileName = $book['pdf_file'];
    $bookTitle   = $book['title'];
} else {
    header('Location: bookstore.php');
    exit();
}

$filePath = __DIR__ . '/uploads/books/' . basename($pdfFileName);
if (!file_exists($filePath)) {
    $filePath = __DIR__ . '/uploads/' . basename($pdfFileName);
}

if (!file_exists($filePath) || !is_file($filePath)) {
    die("Digital publication file could not be located on the server. Please contact support.");
}

// Clean title for download filename
$safeFilename = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $bookTitle) . '.pdf';

// Stream PDF file securely
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Output in chunks for memory efficiency
$handle = fopen($filePath, 'rb');
if ($handle) {
    while (!feof($handle)) {
        echo fread($handle, 8192);
        flush();
    }
    fclose($handle);
}
exit();

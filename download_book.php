<?php
require_once __DIR__ . '/config.php';

$bookId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$bookId) {
    header('Location: bookstore.php');
    exit();
}

$book = BookstoreService::getBookById($bookId);
if (!$book || empty($book['pdf_file'])) {
    die("Digital file not found or unavailable.");
}

$filePath = __DIR__ . '/uploads/books/' . basename($book['pdf_file']);
if (!file_exists($filePath)) {
    $filePath = __DIR__ . '/uploads/' . basename($book['pdf_file']);
}

if (!file_exists($filePath) || !is_file($filePath)) {
    die("File could not be located on the server.");
}

// Stream PDF file securely
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $book['title']) . '.pdf"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
readfile($filePath);
exit();

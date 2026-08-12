<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);

$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$message = trim($input['message'] ?? '');

if (!$name || !$email || !$message) {
    echo json_encode(['success' => false, 'message' => 'Συμπλήρωσε όλα τα πεδία.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Μη έγκυρο email.']);
    exit;
}

send_notification_email(
    SITE_ADMIN_EMAIL,
    'Νέο μήνυμα επικοινωνίας',
    "Από: {$name} ({$email})\n\n{$message}"
);

echo json_encode(['success' => true]);

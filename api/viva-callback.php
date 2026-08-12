<?php
require_once __DIR__ . '/../includes/functions.php';

// Η Viva Wallet κάνει redirect εδώ μετά την πληρωμή με ?s=orderCode&t=transactionId&lang=...
$order_code = $_GET['s'] ?? '';
$transaction_id = $_GET['t'] ?? '';
$event_type = $_GET['eventId'] ?? '';

if (!$order_code) {
    header('Location: ../services.php?payment=error');
    exit;
}

$pdo = get_db();
$stmt = $pdo->prepare("SELECT * FROM orders WHERE viva_order_code = ?");
$stmt->execute([$order_code]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: ../services.php?payment=error');
    exit;
}

// ΣΗΜΑΝΤΙΚΟ: Σε πραγματικό περιβάλλον πρέπει να επαληθεύσεις την πληρωμή
// καλώντας το Viva Transactions API (GET /checkout/v2/transactions/{transactionId})
// πριν επισημάνεις την παραγγελία ως πληρωμένη. Εδώ γίνεται απλοποιημένα.

$stmt = $pdo->prepare("UPDATE orders SET status = 'paid', viva_transaction_id = ? WHERE id = ?");
$stmt->execute([$transaction_id, $order['id']]);

$service = get_service($order['service_id']);

// Αν είναι πακέτο συνεδριών, δημιούργησε εγγραφή πακέτου για τον πελάτη
create_package_from_order($pdo, $order);

send_notification_email(
    SITE_ADMIN_EMAIL,
    'Νέα Πληρωμή',
    "Νέα παραγγελία #{$order['id']} για την υπηρεσία \"{$service['name']}\" ολοκληρώθηκε επιτυχώς."
);

header('Location: ../services.php?payment=success');
exit;

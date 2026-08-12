<?php
require_once __DIR__ . '/init.php';
require_client_login();
$me = current_client();
$cid = (int)$me['id']; $email = $me['email'];

$order_id = (int)($_GET['order'] ?? 0);
$o = q("SELECT o.*, s.file_path sfile, s.type stype, s.name sname
        FROM orders o LEFT JOIN services s ON s.id=o.service_id
        WHERE o.id=? AND (o.client_id=? OR (o.client_email=? AND o.client_email<>''))",
        [$order_id, $cid, $email])->fetch(PDO::FETCH_ASSOC);

if (!$o || $o['status'] !== 'paid' || $o['stype'] !== 'ebook' || !$o['sfile']) {
    http_response_code(403);
    die('Το αρχείο δεν είναι διαθέσιμο.');
}

// Το file_path ορίζεται στην υπηρεσία. Απότρεψε traversal και δέξου
// είτε απόλυτη διαδρομή είτε σχετική προς τη ρίζα του site.
$rel = str_replace(['..','\\'], ['', '/'], $o['sfile']);
$candidates = [
    __DIR__ . '/../' . ltrim($rel, '/'),        // σχετικά με τη ρίζα του site
    __DIR__ . '/../files/' . basename($rel),      // προτεινόμενος ιδιωτικός φάκελος /files
];
$path = null;
foreach ($candidates as $c) { if (is_file($c)) { $path = $c; break; } }

if (!$path) {
    http_response_code(404);
    die('Το αρχείο δεν βρέθηκε. Επικοινώνησε με το γραφείο.');
}

$name = basename($path);
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;

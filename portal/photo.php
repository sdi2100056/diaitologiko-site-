<?php
require_once __DIR__ . '/init.php';
require_client_login();
$me = current_client();
$cid = (int)$me['id'];

$id = (int)($_GET['id'] ?? 0);
$p = q("SELECT * FROM client_photos WHERE id=? AND client_id=?", [$id, $cid])->fetch(PDO::FETCH_ASSOC);
if (!$p) { http_response_code(404); die('Δεν βρέθηκε.'); }

$abs = realpath(__DIR__ . '/../' . $p['file_path']);
$base = realpath(__DIR__ . '/../uploads');
if (!$abs || strpos($abs, $base) !== 0 || !is_file($abs)) { http_response_code(404); die('Δεν βρέθηκε.'); }

$ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
$mime = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'][$ext] ?? 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($abs));
header('Cache-Control: private, max-age=3600');
readfile($abs);

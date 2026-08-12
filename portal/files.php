<?php
require_once __DIR__ . '/init.php';
require_client_login();
$me = current_client();
$cid = (int)$me['id'];

// Ασφαλής λήψη αρχείου πελάτη
if (isset($_GET['file'])) {
    $fid = (int)$_GET['file'];
    $f = q("SELECT * FROM client_files WHERE id=? AND client_id=?", [$fid, $cid])->fetch(PDO::FETCH_ASSOC);
    if (!$f) { http_response_code(403); die('Το αρχείο δεν είναι διαθέσιμο.'); }
    $rel = str_replace(['..','\\'], ['', '/'], $f['file_path']);
    $candidates = [ __DIR__ . '/../' . ltrim($rel,'/'), __DIR__ . '/../uploads/' . basename($rel) ];
    $path = null; foreach ($candidates as $c) { if (is_file($c)) { $path=$c; break; } }
    if (!$path) { http_response_code(404); die('Το αρχείο δεν βρέθηκε.'); }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path); exit;
}

$files = q("SELECT * FROM client_files WHERE client_id=? ORDER BY uploaded_at DESC", [$cid])->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Τα αρχεία μου';
$active = 'files';
require __DIR__ . '/layout_top.php';
?>
<?php if (!$files): ?>
  <div class="p-panel"><p class="p-empty">Δεν υπάρχουν αρχεία ακόμη. Τα διατροφικά σου πλάνα θα εμφανιστούν εδώ.</p></div>
<?php else: ?>
<div class="p-panel">
  <ul class="p-files">
    <?php foreach ($files as $f): ?>
      <li>
        <span class="p-file-ico">📄</span>
        <span class="p-file-name"><?= e($f['title']) ?></span>
        <span class="p-file-meta"><?= gr_date($f['uploaded_at']) ?></span>
        <a class="btn btn-outline btn-sm" href="files.php?file=<?= (int)$f['id'] ?>">Λήψη</a>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>
<?php require __DIR__ . '/layout_bottom.php'; ?>

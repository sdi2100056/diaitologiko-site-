<?php
require_once __DIR__ . '/init.php';
require_login();

$has_table = true;
try { get_db()->query("SELECT 1 FROM gallery LIMIT 1"); } catch (Throwable $e) { $has_table = false; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $has_table) {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    if ($act === 'upload') {
        $caption = trim($_POST['caption'] ?? '');
        $sort = (int)($_POST['sort_order'] ?? 0);
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'], true) && $_FILES['image']['size'] <= 8*1024*1024) {
                $dir = __DIR__ . '/../assets/img/gallery';
                if (!is_dir($dir)) @mkdir($dir, 0775, true);
                $fname = 'g_' . date('Ymd') . '_' . substr(bin2hex(random_bytes(3)),0,6) . '.' . $ext;
                if (@move_uploaded_file($_FILES['image']['tmp_name'], $dir . '/' . $fname)) {
                    q("INSERT INTO gallery (image_path,caption,sort_order) VALUES (?,?,?)", ['assets/img/gallery/'.$fname, $caption?:null, $sort]);
                    flash_set('ok','Η φωτογραφία ανέβηκε.');
                } else { flash_set('bad','Αποτυχία αποθήκευσης.'); }
            } else { flash_set('bad','Επιτρέπονται JPG/PNG/WEBP έως 8MB.'); }
        }
        redirect('gallery.php');
    }
    if ($act === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $p = q("SELECT image_path FROM gallery WHERE id=?", [$id])->fetchColumn();
        if ($p) { $abs = realpath(__DIR__ . '/../' . $p); if ($abs && strpos($abs, realpath(__DIR__.'/../assets'))===0) @unlink($abs); }
        q("DELETE FROM gallery WHERE id=?", [$id]);
        flash_set('ok','Διαγράφηκε.');
        redirect('gallery.php');
    }
}

$photos = $has_table ? q("SELECT * FROM gallery ORDER BY sort_order ASC, id DESC")->fetchAll(PDO::FETCH_ASSOC) : [];
$page_title = 'Ο χώρος μας';
$active = 'gallery';
require __DIR__ . '/layout_top.php';
?>
<?php if (!$has_table): ?>
  <div class="card"><div class="flash flash-bad" style="margin:0">Χρειάζεται αναβάθμιση βάσης για τη gallery.</div><div style="margin-top:14px"><a class="btn btn-primary" href="migrate.php">Αναβάθμιση/έλεγχος βάσης →</a></div></div>
<?php require __DIR__ . '/layout_bottom.php'; return; endif; ?>

<div class="card">
  <div class="card-head"><h2>Ανέβασμα φωτογραφίας</h2></div>
  <form method="post" enctype="multipart/form-data" class="subform" style="border:0;padding:0">
    <?= csrf_field() ?><input type="hidden" name="action" value="upload">
    <div class="subform-row">
      <input type="file" name="image" accept="image/*" required>
      <input type="text" name="caption" placeholder="Λεζάντα (προαιρετικό)">
      <input type="number" name="sort_order" placeholder="Σειρά" class="w-70" value="0">
      <button class="btn btn-primary" type="submit">Ανέβασμα</button>
    </div>
  </form>
</div>

<div class="card">
  <div class="card-head"><h2>Φωτογραφίες (<?= count($photos) ?>)</h2></div>
  <?php if ($photos): ?>
    <div class="admin-gallery">
      <?php foreach ($photos as $ph): ?>
        <figure>
          <img src="../<?= e($ph['image_path']) ?>" alt="" loading="lazy">
          <figcaption><?= e($ph['caption'] ?: '—') ?>
            <form method="post" onsubmit="return confirm('Διαγραφή;')" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$ph['id'] ?>"><button class="chip chip-bad" style="margin-left:6px">🗑</button></form>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  <?php else: ?><p class="empty">Καμία φωτογραφία ακόμη.</p><?php endif; ?>
</div>
<?php require __DIR__ . '/layout_bottom.php'; ?>

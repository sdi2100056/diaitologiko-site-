<?php
require_once __DIR__ . '/init.php';
require_client_login();
$me = current_client();
$cid = (int)$me['id'];
$target = isset($me['target_weight_kg']) && $me['target_weight_kg'] !== null ? (float)$me['target_weight_kg'] : null;

// ---- Ανέβασμα φωτογραφίας προόδου -----------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'photo') {
    csrf_verify();
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES['photo'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) {
            flash_set('bad', 'Επιτρέπονται μόνο εικόνες JPG, PNG, WEBP.');
        } elseif ($f['size'] > 10 * 1024 * 1024) {
            flash_set('bad', 'Η εικόνα ξεπερνά τα 10MB.');
        } else {
            $dir = __DIR__ . '/../uploads/clients/' . $cid . '/photos';
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $ht = __DIR__ . '/../uploads/clients/.htaccess';
            if (!is_file($ht)) @file_put_contents($ht, "Deny from all\nRequire all denied\n");
            $fname = 'ph_' . date('Ymd') . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $ext;
            if (@move_uploaded_file($f['tmp_name'], $dir . '/' . $fname)) {
                $on = $_POST['taken_on'] ?: date('Y-m-d');
                q("INSERT INTO client_photos (client_id,file_path,taken_on) VALUES (?,?,?)",
                  [$cid, 'uploads/clients/' . $cid . '/photos/' . $fname, $on]);
                flash_set('ok', 'Η φωτογραφία ανέβηκε.');
            } else { flash_set('bad', 'Αποτυχία αποθήκευσης.'); }
        }
    }
    redirect('progress.php#photos');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'del_photo') {
    csrf_verify();
    $p = q("SELECT * FROM client_photos WHERE id=? AND client_id=?", [(int)$_POST['pid'], $cid])->fetch(PDO::FETCH_ASSOC);
    if ($p) {
        $abs = realpath(__DIR__ . '/../' . $p['file_path']);
        if ($abs && strpos($abs, realpath(__DIR__ . '/../uploads')) === 0) @unlink($abs);
        q("DELETE FROM client_photos WHERE id=? AND client_id=?", [$p['id'], $cid]);
        flash_set('ok', 'Η φωτογραφία διαγράφηκε.');
    }
    redirect('progress.php#photos');
}

$rows = q("SELECT * FROM client_measurements WHERE client_id=? ORDER BY measured_on ASC", [$cid])->fetchAll(PDO::FETCH_ASSOC);
$photos = q("SELECT * FROM client_photos WHERE client_id=? ORDER BY taken_on DESC, id DESC", [$cid])->fetchAll(PDO::FETCH_ASSOC);

$labels = []; $weights = []; $bmis = [];
foreach ($rows as $r) {
    $labels[] = date('d/m/y', strtotime($r['measured_on']));
    $w = $r['weight_kg'] !== null ? (float)$r['weight_kg'] : null;
    $h = $r['height_cm'] !== null ? (float)$r['height_cm'] : null;
    $weights[] = $w;
    $bmis[] = ($h && $w) ? round($w / (($h/100)*($h/100)), 1) : null;
}
$latest = $rows ? end($rows) : null;
$targetLine = ($target !== null && $rows) ? array_fill(0, count($rows), $target) : null;

$page_title = 'Η πρόοδός μου';
$active = 'progress';
$use_charts = !empty($rows);
require __DIR__ . '/layout_top.php';
?>
<?php foreach (flash_all() as $f): ?><div class="p-flash <?= $f['type']==='ok'?'ok':'bad' ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>

<?php if ($target !== null): ?>
  <div class="p-target">🎯 Στόχος βάρους: <strong><?= e(number_format($target,1,',','.')) ?> kg</strong>
    <?php if ($latest && $latest['weight_kg']!==null): $diff = round((float)$latest['weight_kg'] - $target, 1);
      if (abs($diff) < 0.05): ?><span class="p-tag ok">Επιτεύχθηκε!</span>
      <?php else: ?><span class="p-tag"><?= $diff>0?'+':'' ?><?= e(number_format($diff,1,',','.')) ?> kg</span><?php endif; endif; ?>
  </div>
<?php endif; ?>

<?php if (!$rows): ?>
  <div class="p-panel"><p class="p-empty">Δεν υπάρχουν καταχωρημένες μετρήσεις ακόμη. Θα προστεθούν από το γραφείο στις συνεδρίες σου.</p></div>
<?php else: ?>
  <?php if ($latest): ?>
  <section class="p-cards">
    <div class="p-card"><span class="p-num"><?= $latest['weight_kg']!==null ? e($latest['weight_kg']) : '—' ?></span><span class="p-cap">Βάρος (kg)</span></div>
    <div class="p-card"><span class="p-num"><?= end($bmis)!==null ? e(end($bmis)) : '—' ?></span><span class="p-cap">ΔΜΣ (BMI)</span></div>
    <div class="p-card"><span class="p-num"><?= $latest['waist_cm']!==null ? e($latest['waist_cm']) : '—' ?></span><span class="p-cap">Μέση (cm)</span></div>
  </section>
  <?php endif; ?>

  <section class="p-panel">
    <div class="p-panel-head"><h2>Εξέλιξη</h2></div>
    <div class="chart-wrap"><canvas id="progressChart" height="150"></canvas></div>
  </section>

  <section class="p-panel">
    <div class="p-panel-head"><h2>Ιστορικό μετρήσεων</h2></div>
    <div class="p-table-scroll">
    <table class="p-table">
      <thead><tr><th>Ημ/νία</th><th>Βάρος</th><th>ΔΜΣ</th><th>Μέση</th><th>Ισχία</th><th>Στήθος</th><th>Μπράτσο</th><th>Μηρός</th><th>Λίπος %</th></tr></thead>
      <tbody>
        <?php foreach (array_reverse($rows) as $i=>$r): $idx=count($rows)-1-$i; ?>
        <tr>
          <td><?= gr_date($r['measured_on']) ?></td>
          <td class="mono"><?= $r['weight_kg']!==null?e($r['weight_kg']):'—' ?></td>
          <td class="mono"><?= $bmis[$idx]!==null?e($bmis[$idx]):'—' ?></td>
          <td class="mono"><?= $r['waist_cm']!==null?e($r['waist_cm']):'—' ?></td>
          <td class="mono"><?= $r['hip_cm']!==null?e($r['hip_cm']):'—' ?></td>
          <td class="mono"><?= isset($r['chest_cm'])&&$r['chest_cm']!==null?e($r['chest_cm']):'—' ?></td>
          <td class="mono"><?= isset($r['arm_cm'])&&$r['arm_cm']!==null?e($r['arm_cm']):'—' ?></td>
          <td class="mono"><?= isset($r['thigh_cm'])&&$r['thigh_cm']!==null?e($r['thigh_cm']):'—' ?></td>
          <td class="mono"><?= $r['body_fat']!==null?e($r['body_fat']):'—' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </section>
<?php endif; ?>

<section class="p-panel" id="photos">
  <div class="p-panel-head"><h2>Φωτογραφίες προόδου</h2></div>
  <p class="p-muted" style="margin-bottom:14px">Ιδιωτικές — τις βλέπεις μόνο εσύ και το γραφείο.</p>
  <form method="post" enctype="multipart/form-data" class="p-photo-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="photo">
    <input type="file" name="photo" accept="image/*" required>
    <input type="date" name="taken_on" value="<?= date('Y-m-d') ?>">
    <button class="btn btn-primary btn-sm" type="submit">Ανέβασμα</button>
  </form>
  <?php if ($photos): ?>
    <div class="p-gallery">
      <?php foreach ($photos as $p): ?>
        <figure class="p-shot">
          <img src="photo.php?id=<?= (int)$p['id'] ?>" alt="Φωτογραφία προόδου" loading="lazy">
          <figcaption><?= gr_date($p['taken_on']) ?>
            <form method="post" onsubmit="return confirm('Διαγραφή φωτογραφίας;')">
              <?= csrf_field() ?><input type="hidden" name="action" value="del_photo"><input type="hidden" name="pid" value="<?= (int)$p['id'] ?>">
              <button class="p-shot-del" type="submit" aria-label="Διαγραφή">×</button>
            </form>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="p-empty">Δεν έχεις ανεβάσει φωτογραφίες ακόμη.</p>
  <?php endif; ?>
</section>

<?php if ($rows): ?>
<?php $inline_js = "window.__progress=" . json_encode(['labels'=>$labels,'weights'=>$weights,'bmis'=>$bmis,'target'=>$targetLine], JSON_UNESCAPED_UNICODE) . ";"; ?>
<?php endif; ?>
<?php require __DIR__ . '/layout_bottom.php'; ?>

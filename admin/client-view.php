<?php
require_once __DIR__ . '/init.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$c = q("SELECT * FROM clients WHERE id=?", [$id])->fetch(PDO::FETCH_ASSOC);
if (!$c) { flash_set('bad','Ο πελάτης δεν βρέθηκε.'); redirect('clients.php'); }

$UPLOAD_BASE = __DIR__ . '/../uploads/clients';
function ensure_upload_dir($dir) {
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $ht = dirname($dir) . '/.htaccess';
    if (!is_file($ht)) { @file_put_contents($ht, "Deny from all\nRequire all denied\n"); }
}

// ---- GDPR export (stream JSON) ----------------------------------------
if (isset($_GET['export'])) {
    $data = [
        'client' => $c,
        'appointments' => q("SELECT * FROM appointments WHERE client_id=?", [$id])->fetchAll(PDO::FETCH_ASSOC),
        'orders' => q("SELECT * FROM orders WHERE client_id=?", [$id])->fetchAll(PDO::FETCH_ASSOC),
        'measurements' => q("SELECT * FROM client_measurements WHERE client_id=?", [$id])->fetchAll(PDO::FETCH_ASSOC),
        'files' => q("SELECT * FROM client_files WHERE client_id=?", [$id])->fetchAll(PDO::FETCH_ASSOC),
        'requests' => q("SELECT * FROM appointment_requests WHERE client_id=?", [$id])->fetchAll(PDO::FETCH_ASSOC),
        'exported_at' => date('c'),
    ];
    unset($data['client']['password_hash'], $data['client']['invite_token'], $data['client']['reset_token']);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="client_' . $id . '_data.json"');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['action'] ?? '';

    if ($act === 'resend_invite') {
        $token = make_token();
        q("UPDATE clients SET status='invited', invite_token=?, invite_expires=? WHERE id=?",
          [hash_token($token), date('Y-m-d H:i:s', time()+7*86400), $id]);
        $link = portal_link('activate.php?token=' . $token);
        send_notification_email($c['email'], 'Πρόσκληση στον λογαριασμό σας',
            "Γεια σου {$c['name']},\n\nΌρισε τον κωδικό σου εδώ (ισχύει 7 ημέρες):\n$link");
        $_SESSION['action_link'] = $link;
        flash_set('ok','Στάλθηκε νέα πρόσκληση.');
        redirect('client-view.php?id='.$id.'&link=1');
    }
    if ($act === 'reset_link') {
        $token = make_token();
        q("UPDATE clients SET reset_token=?, reset_expires=? WHERE id=?",
          [hash_token($token), date('Y-m-d H:i:s', time()+3600), $id]);
        $link = portal_link('reset.php?token=' . $token);
        send_notification_email($c['email'], 'Επαναφορά κωδικού', "Σύνδεσμος (1 ώρα):\n$link");
        $_SESSION['action_link'] = $link;
        flash_set('ok','Δημιουργήθηκε σύνδεσμος επαναφοράς.');
        redirect('client-view.php?id='.$id.'&link=1');
    }
    if ($act === 'add_measurement') {
        $on = $_POST['measured_on'] ?: date('Y-m-d');
        $num = fn($k) => ($_POST[$k] ?? '') !== '' ? (float)str_replace(',','.',$_POST[$k]) : null;
        q("INSERT INTO client_measurements (client_id,measured_on,weight_kg,height_cm,waist_cm,hip_cm,chest_cm,arm_cm,thigh_cm,body_fat,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
          [$id,$on,$num('weight_kg'),$num('height_cm'),$num('waist_cm'),$num('hip_cm'),$num('chest_cm'),$num('arm_cm'),$num('thigh_cm'),$num('body_fat'),trim($_POST['notes']??'') ?: null]);
        flash_set('ok','Η μέτρηση καταχωρήθηκε.');
        redirect('client-view.php?id='.$id.'#progress');
    }
    if ($act === 'del_measurement') {
        q("DELETE FROM client_measurements WHERE id=? AND client_id=?", [(int)$_POST['mid'],$id]);
        flash_set('ok','Η μέτρηση διαγράφηκε.');
        redirect('client-view.php?id='.$id.'#progress');
    }
    if ($act === 'set_target') {
        $t = ($_POST['target_weight_kg'] ?? '') !== '' ? (float)str_replace(',','.',$_POST['target_weight_kg']) : null;
        q("UPDATE clients SET target_weight_kg=? WHERE id=?", [$t,$id]);
        flash_set('ok','Ο στόχος βάρους ενημερώθηκε.');
        redirect('client-view.php?id='.$id.'#progress');
    }
    if ($act === 'add_package') {
        $title = trim($_POST['title'] ?? '') ?: 'Πακέτο συνεδριών';
        $tot = max(0,(int)($_POST['total_sessions'] ?? 0));
        $used = min($tot,max(0,(int)($_POST['used_sessions'] ?? 0)));
        q("INSERT INTO client_packages (client_id,title,total_sessions,used_sessions) VALUES (?,?,?,?)", [$id,$title,$tot,$used]);
        flash_set('ok','Το πακέτο προστέθηκε.');
        redirect('client-view.php?id='.$id.'#packages');
    }
    if ($act === 'use_session' || $act === 'unuse_session') {
        $pid = (int)($_POST['pid'] ?? 0);
        $p = q("SELECT * FROM client_packages WHERE id=? AND client_id=?", [$pid,$id])->fetch(PDO::FETCH_ASSOC);
        if ($p) {
            $u = (int)$p['used_sessions'] + ($act==='use_session' ? 1 : -1);
            $u = max(0, min((int)$p['total_sessions'], $u));
            q("UPDATE client_packages SET used_sessions=? WHERE id=?", [$u,$pid]);
        }
        redirect('client-view.php?id='.$id.'#packages');
    }
    if ($act === 'del_package') {
        q("DELETE FROM client_packages WHERE id=? AND client_id=?", [(int)$_POST['pid'],$id]);
        flash_set('ok','Το πακέτο διαγράφηκε.');
        redirect('client-view.php?id='.$id.'#packages');
    }
    if ($act === 'send_message') {
        $body = trim($_POST['body'] ?? '');
        if ($body !== '') {
            if (mb_strlen($body) > 4000) $body = mb_substr($body, 0, 4000);
            q("INSERT INTO messages (client_id,sender,body) VALUES (?,'admin',?)", [$id, $body]);
            add_client_notification($id, 'message', 'Νέο μήνυμα από το γραφείο', 'messages.php');
            if (!empty($c['email'])) {
                send_notification_email($c['email'], 'Νέο μήνυμα από το γραφείο',
                    "Έχεις νέο μήνυμα από το διαιτολογικό γραφείο. Μπες στον λογαριασμό σου για να το δεις:\n\n" . site_base_url() . '/portal/messages.php');
            }
            flash_set('ok','Το μήνυμα στάλθηκε.');
        }
        redirect('client-view.php?id='.$id.'#messages');
    }
    if ($act === 'set_active_plan') {
        $pid = (int)($_POST['pid'] ?? 0);
        $own = q("SELECT id FROM diet_plans WHERE id=? AND client_id=?", [$pid,$id])->fetchColumn();
        if ($own) {
            q("UPDATE diet_plans SET active=0 WHERE client_id=?", [$id]);
            q("UPDATE diet_plans SET active=1 WHERE id=?", [$pid]);
            add_client_notification($id, 'plan', 'Ενημερώθηκε το διατροφικό σου πλάνο', 'plan.php');
            flash_set('ok','Το ενεργό πλάνο ενημερώθηκε.');
        }
        redirect('client-view.php?id='.$id.'#plans');
    }
    if ($act === 'del_plan') {
        $pid = (int)($_POST['pid'] ?? 0);
        $own = q("SELECT id FROM diet_plans WHERE id=? AND client_id=?", [$pid,$id])->fetchColumn();
        if ($own) {
            q("DELETE FROM diet_plan_meals WHERE plan_id=?", [$pid]);
            q("DELETE FROM diet_plan_items WHERE plan_id=?", [$pid]);
            q("DELETE FROM diet_plans WHERE id=?", [$pid]);
            flash_set('ok','Το πλάνο διαγράφηκε.');
        }
        redirect('client-view.php?id='.$id.'#plans');
    }
    if ($act === 'upload_file') {
        $title = trim($_POST['title'] ?? '');
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            flash_set('bad','Απέτυχε το ανέβασμα του αρχείου.');
        } else {
            $f = $_FILES['file'];
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf','doc','docx','jpg','jpeg','png'];
            if (!in_array($ext, $allowed, true)) {
                flash_set('bad','Επιτρέπονται μόνο: PDF, DOC, DOCX, JPG, PNG.');
            } elseif ($f['size'] > 15*1024*1024) {
                flash_set('bad','Το αρχείο ξεπερνά τα 15MB.');
            } else {
                $dir = $UPLOAD_BASE . '/' . $id;
                ensure_upload_dir($dir);
                $safe = preg_replace('/[^A-Za-z0-9._-]/','_', pathinfo($f['name'], PATHINFO_FILENAME));
                $fname = $safe . '_' . substr(bin2hex(random_bytes(4)),0,8) . '.' . $ext;
                if (@move_uploaded_file($f['tmp_name'], $dir . '/' . $fname)) {
                    q("INSERT INTO client_files (client_id,title,file_path) VALUES (?,?,?)",
                      [$id, $title ?: $f['name'], 'uploads/clients/'.$id.'/'.$fname]);
                    flash_set('ok','Το αρχείο ανέβηκε.');
                } else { flash_set('bad','Αποτυχία αποθήκευσης αρχείου.'); }
            }
        }
        redirect('client-view.php?id='.$id.'#files');
    }
    if ($act === 'del_file') {
        $fid = (int)$_POST['fid'];
        $f = q("SELECT * FROM client_files WHERE id=? AND client_id=?", [$fid,$id])->fetch(PDO::FETCH_ASSOC);
        if ($f) {
            $p = __DIR__ . '/../' . $f['file_path'];
            if (is_file($p)) @unlink($p);
            q("DELETE FROM client_files WHERE id=?", [$fid]);
            flash_set('ok','Το αρχείο διαγράφηκε.');
        }
        redirect('client-view.php?id='.$id.'#files');
    }
    if ($act === 'gdpr_delete') {
        // Ανωνυμοποίηση συναλλαγών (κράτηση οικονομικού ιστορικού χωρίς προσωπικά)
        q("UPDATE appointments SET client_name='(διαγράφηκε)', client_email='', client_phone='', notes=NULL, client_id=NULL WHERE client_id=?", [$id]);
        q("UPDATE orders SET client_name='(διαγράφηκε)', client_email='', client_id=NULL WHERE client_id=?", [$id]);
        // Διαγραφή αρχείων από τον δίσκο
        foreach (q("SELECT file_path FROM client_files WHERE client_id=?", [$id])->fetchAll(PDO::FETCH_COLUMN) as $fp) {
            $p = __DIR__ . '/../' . $fp; if (is_file($p)) @unlink($p);
        }
        q("DELETE FROM client_files WHERE client_id=?", [$id]);
        q("DELETE FROM client_measurements WHERE client_id=?", [$id]);
        q("DELETE FROM appointment_requests WHERE client_id=?", [$id]);
        q("DELETE FROM admin_notifications WHERE client_id=?", [$id]);
        q("DELETE FROM clients WHERE id=?", [$id]);
        audit('gdpr_delete','client',$id);
        flash_set('ok','Ο πελάτης και τα προσωπικά του δεδομένα διαγράφηκαν.');
        redirect('clients.php');
    }
}

$action_link = null;
if (isset($_GET['link']) && !empty($_SESSION['action_link'])) { $action_link = $_SESSION['action_link']; unset($_SESSION['action_link']); }

$appts = q("SELECT * FROM appointments WHERE client_id=? ORDER BY appointment_date DESC, appointment_time DESC", [$id])->fetchAll(PDO::FETCH_ASSOC);
$orders = q("SELECT o.*, s.name sname FROM orders o LEFT JOIN services s ON s.id=o.service_id WHERE o.client_id=? ORDER BY o.created_at DESC", [$id])->fetchAll(PDO::FETCH_ASSOC);
$meas = q("SELECT * FROM client_measurements WHERE client_id=? ORDER BY measured_on DESC", [$id])->fetchAll(PDO::FETCH_ASSOC);
$packages = q("SELECT * FROM client_packages WHERE client_id=? ORDER BY created_at DESC", [$id])->fetchAll(PDO::FETCH_ASSOC);
$intake = q("SELECT * FROM client_intake WHERE client_id=?", [$id])->fetch(PDO::FETCH_ASSOC) ?: null;
$photos = q("SELECT * FROM client_photos WHERE client_id=? ORDER BY taken_on DESC, id DESC", [$id])->fetchAll(PDO::FETCH_ASSOC);
$pkg_tot = array_sum(array_column($packages,'total_sessions'));
$pkg_used = array_sum(array_column($packages,'used_sessions'));
// μηνύματα: σήμανση πελάτη→γραφείου ως αναγνωσμένα + φόρτωση νήματος
q("UPDATE messages SET read_at=NOW() WHERE client_id=? AND sender='client' AND read_at IS NULL", [$id]);
$messages = q("SELECT * FROM messages WHERE client_id=? ORDER BY created_at ASC, id ASC", [$id])->fetchAll(PDO::FETCH_ASSOC);
$plans = q("SELECT * FROM diet_plans WHERE client_id=? ORDER BY active DESC, created_at DESC", [$id])->fetchAll(PDO::FETCH_ASSOC);
$files = q("SELECT * FROM client_files WHERE client_id=? ORDER BY uploaded_at DESC", [$id])->fetchAll(PDO::FETCH_ASSOC);
$last_h = null; foreach ($meas as $m) { if ($m['height_cm']!==null) { $last_h=$m['height_cm']; break; } }
$st_lbl = ['invited'=>'Προσκεκλημένος','active'=>'Ενεργός','disabled'=>'Ανενεργός'];
$appt_lbl = $GR_STATUS_APPT; $order_lbl = $GR_STATUS_ORDER;

$page_title = $c['name'];
$active = 'clients';
require __DIR__ . '/layout_top.php';
?>
<div class="breadcrumb"><a href="clients.php">← Πελάτες</a></div>

<?php if ($action_link): ?>
<div class="card"><div class="card-head"><h2>Σύνδεσμος</h2></div>
  <div class="copy-link"><input type="text" readonly value="<?= e($action_link) ?>" onclick="this.select()">
  <button class="btn btn-outline" type="button" onclick="navigator.clipboard.writeText('<?= e($action_link) ?>');this.textContent='Αντιγράφηκε ✓'">Αντιγραφή</button></div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <h2><?= e($c['name']) ?> <span class="client-status <?= e($c['status']) ?>"><?= e($st_lbl[$c['status']]??$c['status']) ?></span></h2>
    <div class="actions">
      <a class="btn btn-outline" href="client-view.php?id=<?= $id ?>&export=1">Εξαγωγή δεδομένων (GDPR)</a>
    </div>
  </div>
  <dl class="kv">
    <dt>Email</dt><dd><?= e($c['email']) ?></dd>
    <dt>Τηλέφωνο</dt><dd><?= e($c['phone'] ?: '—') ?></dd>
    <dt>Εγγραφή</dt><dd><?= gr_datetime($c['created_at']) ?></dd>
    <dt>Συγκατάθεση GDPR</dt><dd><?= $c['gdpr_consent'] ? 'Ναι — '.gr_datetime($c['gdpr_consent_at']) : 'Όχι' ?></dd>
  </dl>
  <div class="actions" style="justify-content:flex-start;margin-top:14px;gap:10px">
    <form method="post" class="inline-form"><?= csrf_field() ?><button name="action" value="resend_invite" class="btn btn-outline"><?= $c['status']==='invited'?'Επαναποστολή πρόσκλησης':'Νέα πρόσκληση' ?></button></form>
    <?php if ($c['status']!=='invited'): ?><form method="post" class="inline-form"><?= csrf_field() ?><button name="action" value="reset_link" class="btn btn-outline">Σύνδεσμος επαναφοράς κωδικού</button></form><?php endif; ?>
    <form method="post" class="inline-form" data-confirm="ΔΙΑΓΡΑΦΗ όλων των προσωπικών δεδομένων του πελάτη; Δεν αναιρείται."><?= csrf_field() ?><button name="action" value="gdpr_delete" class="btn btn-danger">Διαγραφή δεδομένων</button></form>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head"><h2>Ραντεβού (<?= count($appts) ?>)</h2></div>
    <?php if ($appts): ?>
    <table class="table compact"><thead><tr><th>Ημ/νία</th><th>Ώρα</th><th>Κατάσταση</th></tr></thead><tbody>
      <?php foreach ($appts as $a): [$l,$cl]=$appt_lbl[$a['status']]; ?>
      <tr><td><a href="appointment-edit.php?id=<?= (int)$a['id'] ?>"><?= gr_date($a['appointment_date']) ?></a></td><td class="mono"><?= hhmm($a['appointment_time']) ?></td><td><span class="badge <?= $cl ?>"><?= $l ?></span></td></tr>
      <?php endforeach; ?>
    </tbody></table>
    <?php else: ?><p class="empty">Κανένα ραντεβού.</p><?php endif; ?>
  </div>
  <div class="card">
    <div class="card-head"><h2>Αγορές (<?= count($orders) ?>)</h2></div>
    <?php if ($orders): ?>
    <table class="table compact"><thead><tr><th>Ημ/νία</th><th>Υπηρεσία</th><th>Ποσό</th><th>Κατάσταση</th><th></th></tr></thead><tbody>
      <?php foreach ($orders as $o): [$l,$cl]=$order_lbl[$o['status']]??['—','muted']; ?>
      <tr><td><?= gr_date($o['created_at']) ?></td><td><?= e($o['sname']??'—') ?></td><td class="mono"><?= eur($o['amount']) ?></td><td><span class="badge <?= $cl ?>"><?= $l ?></span></td><td class="ta-right"><?php if($o['status']==='paid'):?><a class="chip" href="receipt.php?order=<?= (int)$o['id'] ?>" target="_blank" title="Απόδειξη">🧾</a><?php endif;?></td></tr>
      <?php endforeach; ?>
    </tbody></table>
    <?php else: ?><p class="empty">Καμία αγορά.</p><?php endif; ?>
  </div>
</div>

<div class="card" id="packages">
  <div class="card-head"><h2>Πακέτα συνεδριών</h2>
    <?php if ($pkg_tot): ?><span class="badge ok"><?= max(0,$pkg_tot-$pkg_used) ?> / <?= $pkg_tot ?> διαθέσιμες</span><?php endif; ?>
  </div>
  <?php if ($packages): ?>
    <div class="table-scroll">
    <table class="table"><thead><tr><th>Πακέτο</th><th>Χρήση</th><th>Απομένουν</th><th class="ta-right">Ενέργειες</th></tr></thead><tbody>
      <?php foreach ($packages as $p): $rem=max(0,(int)$p['total_sessions']-(int)$p['used_sessions']); ?>
      <tr>
        <td><?= e($p['title']) ?></td>
        <td class="mono"><?= (int)$p['used_sessions'] ?> / <?= (int)$p['total_sessions'] ?></td>
        <td class="mono"><strong><?= $rem ?></strong></td>
        <td class="ta-right">
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="pid" value="<?= (int)$p['id'] ?>"><button name="action" value="use_session" class="chip" title="Χρήση συνεδρίας" <?= $rem<=0?'disabled':'' ?>>−1 συνεδρία</button></form>
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="pid" value="<?= (int)$p['id'] ?>"><button name="action" value="unuse_session" class="chip" title="Αναίρεση">+1</button></form>
          <form method="post" class="inline-form" data-confirm="Διαγραφή πακέτου;"><?= csrf_field() ?><input type="hidden" name="pid" value="<?= (int)$p['id'] ?>"><button name="action" value="del_package" class="chip chip-bad">🗑</button></form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody></table>
    </div>
  <?php else: ?><p class="empty">Κανένα πακέτο. Δημιουργείται αυτόματα σε αγορά πακέτου συνεδριών, ή πρόσθεσέ το χειροκίνητα:</p><?php endif; ?>
  <form method="post" class="subform" style="margin-top:12px;border:0;padding:0">
    <?= csrf_field() ?><input type="hidden" name="action" value="add_package">
    <div class="subform-row">
      <input type="text" name="title" placeholder="Τίτλος (π.χ. Πακέτο 4 συνεδριών)">
      <input type="number" name="total_sessions" placeholder="Σύνολο" class="w-70" min="0">
      <input type="number" name="used_sessions" placeholder="Χρησιμ." class="w-70" min="0" value="0">
      <button class="btn btn-outline" type="submit">Προσθήκη πακέτου</button>
    </div>
  </form>
</div>

<div class="card" id="progress">
  <div class="card-head"><h2>Μετρήσεις προόδου</h2></div>
  <form method="post" class="subform" style="margin-top:0;border:0;padding:12px 0;border-bottom:1px solid var(--line,#E1EAE5)">
    <?= csrf_field() ?><input type="hidden" name="action" value="set_target">
    <div class="subform-row">
      <label style="align-self:center;font-weight:600;color:#3B4A46">🎯 Στόχος βάρους (kg)</label>
      <input type="text" name="target_weight_kg" class="w-70" inputmode="decimal" value="<?= e($c['target_weight_kg'] ?? '') ?>" placeholder="π.χ. 68">
      <button class="btn btn-outline" type="submit">Αποθήκευση στόχου</button>
    </div>
  </form>
  <form method="post" class="subform" style="margin-top:12px;border:0;padding:0">
    <?= csrf_field() ?><input type="hidden" name="action" value="add_measurement">
    <div class="subform-row">
      <input type="date" name="measured_on" value="<?= date('Y-m-d') ?>" title="Ημερομηνία">
      <input type="text" name="weight_kg" placeholder="Βάρος kg" class="w-70" inputmode="decimal">
      <input type="text" name="height_cm" placeholder="Ύψος cm" class="w-70" inputmode="decimal" value="<?= e($last_h ?? '') ?>">
      <input type="text" name="waist_cm" placeholder="Μέση cm" class="w-70" inputmode="decimal">
      <input type="text" name="hip_cm" placeholder="Ισχία cm" class="w-70" inputmode="decimal">
      <input type="text" name="chest_cm" placeholder="Στήθος cm" class="w-70" inputmode="decimal">
      <input type="text" name="arm_cm" placeholder="Μπράτσο cm" class="w-70" inputmode="decimal">
      <input type="text" name="thigh_cm" placeholder="Μηρός cm" class="w-70" inputmode="decimal">
      <input type="text" name="body_fat" placeholder="Λίπος %" class="w-70" inputmode="decimal">
      <button class="btn btn-primary" type="submit">Καταχώρηση</button>
    </div>
  </form>
  <?php if ($meas): ?>
  <div class="table-scroll" style="margin-top:14px">
  <table class="table"><thead><tr><th>Ημ/νία</th><th>Βάρος</th><th>Ύψος</th><th>Μέση</th><th>Ισχία</th><th>Στήθος</th><th>Μπράτσο</th><th>Μηρός</th><th>Λίπος%</th><th class="ta-right"></th></tr></thead><tbody>
    <?php foreach ($meas as $m): ?>
    <tr>
      <td><?= gr_date($m['measured_on']) ?></td>
      <td class="mono"><?= $m['weight_kg']!==null?e($m['weight_kg']):'—' ?></td>
      <td class="mono"><?= $m['height_cm']!==null?e($m['height_cm']):'—' ?></td>
      <td class="mono"><?= $m['waist_cm']!==null?e($m['waist_cm']):'—' ?></td>
      <td class="mono"><?= $m['hip_cm']!==null?e($m['hip_cm']):'—' ?></td>
      <td class="mono"><?= isset($m['chest_cm'])&&$m['chest_cm']!==null?e($m['chest_cm']):'—' ?></td>
      <td class="mono"><?= isset($m['arm_cm'])&&$m['arm_cm']!==null?e($m['arm_cm']):'—' ?></td>
      <td class="mono"><?= isset($m['thigh_cm'])&&$m['thigh_cm']!==null?e($m['thigh_cm']):'—' ?></td>
      <td class="mono"><?= $m['body_fat']!==null?e($m['body_fat']):'—' ?></td>
      <td class="ta-right"><form method="post" class="inline-form" data-confirm="Διαγραφή μέτρησης;"><?= csrf_field() ?><input type="hidden" name="action" value="del_measurement"><input type="hidden" name="mid" value="<?= (int)$m['id'] ?>"><button class="chip chip-bad">🗑</button></form></td>
    </tr>
    <?php endforeach; ?>
  </tbody></table>
  </div>
  <?php else: ?><p class="empty">Καμία μέτρηση ακόμη.</p><?php endif; ?>
</div>

<div class="card" id="intake">
  <div class="card-head"><h2>Ιατρικό ιστορικό</h2>
    <?php if ($intake && !empty($intake['submitted_at'])): ?><span class="badge ok">Υποβλήθηκε <?= gr_date($intake['submitted_at']) ?></span><?php else: ?><span class="badge muted">Δεν έχει συμπληρωθεί</span><?php endif; ?>
  </div>
  <?php if ($intake): ?>
    <div class="kv">
      <?php
        $imap = [
          'birth_date'=>'Ημ. γέννησης','height_cm'=>'Ύψος (cm)','weight_kg'=>'Βάρος (kg)','activity_level'=>'Δραστηριότητα',
          'goals'=>'Στόχοι','medical_conditions'=>'Παθήσεις','medications'=>'Φαρμακευτική αγωγή','allergies'=>'Αλλεργίες',
          'dietary_restrictions'=>'Διατροφικοί περιορισμοί','smoking'=>'Κάπνισμα','alcohol'=>'Αλκοόλ','notes'=>'Σημειώσεις'
        ];
        foreach ($imap as $k=>$lab):
          $val = $intake[$k] ?? '';
          if ($val === '' || $val === null) continue;
          if ($k==='birth_date') $val = gr_date($val);
      ?>
        <div class="kv-row"><span class="kv-k"><?= $lab ?></span><span class="kv-v"><?= nl2br(e($val)) ?></span></div>
      <?php endforeach; ?>
    </div>
  <?php else: ?><p class="empty">Ο πελάτης δεν έχει συμπληρώσει ακόμη το ιστορικό του.</p><?php endif; ?>
</div>

<div class="card" id="photos">
  <div class="card-head"><h2>Φωτογραφίες προόδου (<?= count($photos) ?>)</h2></div>
  <?php if ($photos): ?>
    <div class="admin-gallery">
      <?php foreach ($photos as $p): ?>
        <figure><img src="photo.php?id=<?= (int)$p['id'] ?>" alt="" loading="lazy"><figcaption><?= gr_date($p['taken_on']) ?></figcaption></figure>
      <?php endforeach; ?>
    </div>
  <?php else: ?><p class="empty">Ο πελάτης δεν έχει ανεβάσει φωτογραφίες.</p><?php endif; ?>
</div>

<div class="card" id="messages">
  <div class="card-head"><h2>Μηνύματα</h2></div>
  <div class="chat chat-admin">
    <?php if (!$messages): ?><p class="empty">Δεν υπάρχουν μηνύματα.</p>
    <?php else: foreach ($messages as $m): $fromAdmin = $m['sender']==='admin'; ?>
      <div class="chat-row <?= $fromAdmin?'mine':'theirs' ?>">
        <div class="chat-bubble"><?= nl2br(e($m['body'])) ?><span class="chat-time"><?= gr_datetime($m['created_at']) ?></span></div>
      </div>
    <?php endforeach; endif; ?>
  </div>
  <form method="post" class="chat-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="send_message">
    <textarea name="body" rows="2" placeholder="Απάντηση προς τον πελάτη…" required maxlength="4000"></textarea>
    <button class="btn btn-primary" type="submit">Αποστολή</button>
  </form>
</div>

<div class="card" id="plans">
  <div class="card-head"><h2>Διατροφικά πλάνα</h2>
    <a class="btn btn-primary btn-sm" href="diet-plan-edit.php?client=<?= $id ?>">+ Νέο πλάνο</a>
  </div>
  <?php if ($plans): ?>
    <table class="table"><thead><tr><th>Τίτλος</th><th>Έναρξη</th><th>Κατάσταση</th><th class="ta-right">Ενέργειες</th></tr></thead><tbody>
      <?php foreach ($plans as $p): ?>
      <tr>
        <td><a href="diet-plan-edit.php?client=<?= $id ?>&plan=<?= (int)$p['id'] ?>"><?= e($p['title']) ?></a></td>
        <td><?= $p['start_date']?gr_date($p['start_date']):'—' ?></td>
        <td><?php if ($p['active']): ?><span class="badge ok">Ενεργό</span><?php else: ?><span class="badge muted">Ανενεργό</span><?php endif; ?></td>
        <td class="ta-right">
          <a class="chip" href="diet-plan-edit.php?client=<?= $id ?>&plan=<?= (int)$p['id'] ?>">✎</a>
          <?php if (!$p['active']): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="pid" value="<?= (int)$p['id'] ?>"><button name="action" value="set_active_plan" class="chip" title="Ενεργοποίηση">✔ Ενεργό</button></form><?php endif; ?>
          <form method="post" class="inline-form" data-confirm="Διαγραφή πλάνου;"><?= csrf_field() ?><input type="hidden" name="pid" value="<?= (int)$p['id'] ?>"><button name="action" value="del_plan" class="chip chip-bad">🗑</button></form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody></table>
  <?php else: ?><p class="empty">Κανένα δομημένο πλάνο ακόμη. Πάτησε «Νέο πλάνο» για να φτιάξεις ένα (ημέρα × γεύμα + λίστα αγορών).</p><?php endif; ?>
</div>

<div class="card" id="files">
  <div class="card-head"><h2>Αρχεία / διατροφικά πλάνα (PDF)</h2></div>
  <form method="post" enctype="multipart/form-data" class="subform" style="margin-top:0;border:0;padding:0">
    <?= csrf_field() ?><input type="hidden" name="action" value="upload_file">
    <div class="subform-row">
      <input type="text" name="title" placeholder="Τίτλος (π.χ. Πλάνο Ιανουαρίου)">
      <input type="file" name="file" required>
      <button class="btn btn-primary" type="submit">Ανέβασμα</button>
    </div>
    <p class="hint-inline" style="margin-top:8px">Επιτρέπονται PDF, DOC, DOCX, JPG, PNG (έως 15MB). Αποθηκεύονται ιδιωτικά και τα βλέπει μόνο ο πελάτης.</p>
  </form>
  <?php if ($files): ?>
  <table class="table" style="margin-top:14px"><thead><tr><th>Τίτλος</th><th>Ημ/νία</th><th class="ta-right"></th></tr></thead><tbody>
    <?php foreach ($files as $f): ?>
    <tr><td><?= e($f['title']) ?></td><td><?= gr_date($f['uploaded_at']) ?></td>
      <td class="ta-right"><form method="post" class="inline-form" data-confirm="Διαγραφή αρχείου;"><?= csrf_field() ?><input type="hidden" name="action" value="del_file"><input type="hidden" name="fid" value="<?= (int)$f['id'] ?>"><button class="chip chip-bad">🗑</button></form></td>
    </tr>
    <?php endforeach; ?>
  </tbody></table>
  <?php else: ?><p class="empty">Κανένα αρχείο.</p><?php endif; ?>
</div>

<?php require __DIR__ . '/layout_bottom.php'; ?>

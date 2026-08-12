<?php
require_once __DIR__ . '/init.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$editing = $id > 0;
$appt = [
    'client_name'=>'', 'client_email'=>'', 'client_phone'=>'',
    'appointment_date'=>date('Y-m-d'), 'appointment_time'=>'09:00',
    'status'=>'confirmed', 'notes'=>'', 'mode'=>'in_person', 'meeting_link'=>'', 'practitioner_id'=>null, 'appt_type'=>'new',
];

if ($editing) {
    $row = q("SELECT * FROM appointments WHERE id=?", [$id])->fetch(PDO::FETCH_ASSOC);
    if (!$row) { flash_set('bad','Το ραντεβού δεν βρέθηκε.'); redirect('appointments.php'); }
    $row['appointment_time'] = substr($row['appointment_time'], 0, 5);
    $appt = $row;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $appt['client_name']  = trim($_POST['client_name'] ?? '');
    $appt['client_email'] = trim($_POST['client_email'] ?? '');
    $appt['client_phone'] = trim($_POST['client_phone'] ?? '');
    $appt['appointment_date'] = $_POST['appointment_date'] ?? '';
    $appt['appointment_time'] = $_POST['appointment_time'] ?? '';
    $appt['status'] = $_POST['status'] ?? 'confirmed';
    $appt['notes']  = trim($_POST['notes'] ?? '');
    $appt['mode'] = ($_POST['mode'] ?? 'in_person')==='online' ? 'online' : 'in_person';
    $appt['meeting_link'] = trim($_POST['meeting_link'] ?? '');
    $appt['practitioner_id'] = ctype_digit((string)($_POST['practitioner_id'] ?? '')) ? (int)$_POST['practitioner_id'] : null;
    $appt['appt_type'] = ($_POST['appt_type'] ?? 'new')==='followup' ? 'followup' : 'new';

    if ($appt['client_name']==='')  $errors[] = 'Το όνομα είναι υποχρεωτικό.';
    if (!filter_var($appt['client_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Μη έγκυρο email.';
    if ($appt['client_phone']==='') $errors[] = 'Το τηλέφωνο είναι υποχρεωτικό.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $appt['appointment_date'])) $errors[] = 'Μη έγκυρη ημερομηνία.';
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $appt['appointment_time'])) $errors[] = 'Μη έγκυρη ώρα.';
    if (!in_array($appt['status'], ['pending','confirmed','cancelled'], true)) $appt['status']='confirmed';

    if (!$errors) {
        $time = $appt['appointment_time'] . ':00';
        try {
            if ($editing) {
                q("UPDATE appointments SET client_name=?, client_email=?, client_phone=?, appointment_date=?, appointment_time=?, status=?, notes=?, mode=?, meeting_link=?, practitioner_id=?, appt_type=?, duration_min=? WHERE id=?",
                  [$appt['client_name'],$appt['client_email'],$appt['client_phone'],$appt['appointment_date'],$time,$appt['status'],$appt['notes'],$appt['mode'],$appt['meeting_link']?:null,$appt['practitioner_id'],$appt['appt_type'],appt_duration($appt['appt_type']),$id]);
                flash_set('ok','Το ραντεβού ενημερώθηκε.');
            } else {
                q("INSERT INTO appointments (client_name, client_email, client_phone, appointment_date, appointment_time, status, notes, mode, meeting_link, practitioner_id, appt_type, duration_min) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
                  [$appt['client_name'],$appt['client_email'],$appt['client_phone'],$appt['appointment_date'],$time,$appt['status'],$appt['notes'],$appt['mode'],$appt['meeting_link']?:null,$appt['practitioner_id'],$appt['appt_type'],appt_duration($appt['appt_type'])]);
                flash_set('ok','Το ραντεβού δημιουργήθηκε.');
            }
            redirect('appointments.php');
        } catch (PDOException $ex) {
            if ($ex->getCode() == 23000) {
                $errors[] = 'Υπάρχει ήδη ραντεβού σε αυτή την ημερομηνία και ώρα (επίλεξε άλλη ώρα ή ακύρωσε το άλλο).';
            } else {
                $errors[] = 'Σφάλμα αποθήκευσης.';
            }
        }
    }
}

$page_title = $editing ? 'Επεξεργασία ραντεβού' : 'Νέο ραντεβού';
$active = 'appts';
require __DIR__ . '/layout_top.php';
?>
<div class="breadcrumb"><a href="appointments.php">← Ραντεβού</a></div>

<?php if ($errors): ?>
<div class="flash flash-bad"><ul><?php foreach($errors as $er) echo '<li>'.e($er).'</li>'; ?></ul></div>
<?php endif; ?>

<form method="post" class="card form-card">
  <?= csrf_field() ?>
  <div class="form-grid">
    <label class="fld"><span>Ονοματεπώνυμο πελάτη *</span>
      <input type="text" name="client_name" value="<?= e($appt['client_name']) ?>" required>
    </label>
    <label class="fld"><span>Email *</span>
      <input type="email" name="client_email" value="<?= e($appt['client_email']) ?>" required>
    </label>
    <label class="fld"><span>Τηλέφωνο *</span>
      <input type="text" name="client_phone" value="<?= e($appt['client_phone']) ?>" required>
    </label>
    <label class="fld"><span>Κατάσταση</span>
      <select name="status">
        <option value="confirmed" <?= $appt['status']==='confirmed'?'selected':'' ?>>Επιβεβαιωμένο</option>
        <option value="pending"   <?= $appt['status']==='pending'?'selected':'' ?>>Σε αναμονή</option>
        <option value="cancelled" <?= $appt['status']==='cancelled'?'selected':'' ?>>Ακυρωμένο</option>
      </select>
    </label>
    <label class="fld"><span>Ημερομηνία *</span>
      <input type="date" name="appointment_date" value="<?= e($appt['appointment_date']) ?>" required>
    </label>
    <label class="fld"><span>Ώρα *</span>
      <input type="time" name="appointment_time" value="<?= e($appt['appointment_time']) ?>" required>
    </label>
    <label class="fld"><span>Τρόπος</span>
      <select name="mode" id="apptMode">
        <option value="in_person" <?= ($appt['mode']??'in_person')==='in_person'?'selected':'' ?>>Δια ζώσης</option>
        <option value="online" <?= ($appt['mode']??'')==='online'?'selected':'' ?>>Online (τηλεδιάσκεψη)</option>
      </select>
    </label>
    <label class="fld"><span>Θεραπευτής</span>
      <select name="practitioner_id">
        <option value="">—</option>
        <?php foreach (get_practitioners(false) as $pr): ?><option value="<?= (int)$pr['id'] ?>" <?= (int)($appt['practitioner_id']??0)===(int)$pr['id']?'selected':'' ?>><?= e($pr['name']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label class="fld"><span>Τύπος συνεδρίας</span>
      <select name="appt_type">
        <option value="new" <?= ($appt['appt_type']??'new')==='new'?'selected':'' ?>>Νέο ραντεβού (60′)</option>
        <option value="followup" <?= ($appt['appt_type']??'')==='followup'?'selected':'' ?>>Επαναληπτικό (30′)</option>
      </select>
    </label>
    <label class="fld fld-full" id="linkFld"><span>Σύνδεσμος τηλεδιάσκεψης (για online)</span>
      <input type="url" name="meeting_link" value="<?= e($appt['meeting_link'] ?? '') ?>" placeholder="https://meet.google.com/… ή Zoom/Whereby">
    </label>
    <label class="fld fld-full"><span>Σημειώσεις</span>
      <textarea name="notes" rows="4"><?= e($appt['notes']) ?></textarea>
    </label>
  </div>
  <script>
  (function(){var m=document.getElementById('apptMode'),l=document.getElementById('linkFld');
   function t(){l.style.display=m.value==='online'?'':'none';} if(m&&l){t();m.addEventListener('change',t);}})();
  </script>
  <div class="form-actions">
    <a href="appointments.php" class="btn btn-ghost">Άκυρο</a>
    <button type="submit" class="btn btn-primary"><?= $editing ? 'Αποθήκευση' : 'Δημιουργία' ?></button>
  </div>
</form>

<?php require __DIR__ . '/layout_bottom.php'; ?>

<?php
require_once __DIR__ . '/init.php';
require_client_login();
$me = current_client();
$cid = (int)$me['id'];

function own_appt($cid, $id) {
    return q("SELECT * FROM appointments WHERE id=? AND client_id=?", [$id, $cid])->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);
    $appt = $id ? own_appt($cid, $id) : null;

    if (!$appt) {
        flash_set('bad', 'Το ραντεβού δεν βρέθηκε.');
    } elseif ($act === 'cancel') {
        if (!appt_is_modifiable($appt)) {
            flash_set('bad', 'Η ακύρωση επιτρέπεται έως ' . CANCEL_WINDOW_HOURS . ' ώρες πριν. Επικοινώνησε με το γραφείο.');
        } else {
            q("UPDATE appointments SET status='cancelled' WHERE id=?", [$id]);
            notify_admin('appt_cancelled', $cid, $id, $me['name'] . ' ακύρωσε το ραντεβού της ' . gr_date($appt['appointment_date']) . ' ' . hhmm($appt['appointment_time']));
            send_notification_email(SITE_ADMIN_EMAIL, 'Ακύρωση ραντεβού',
                $me['name'] . ' ακύρωσε το ραντεβού της ' . gr_date($appt['appointment_date']) . ' στις ' . hhmm($appt['appointment_time']) . '.');
            flash_set('ok', 'Το ραντεβού ακυρώθηκε και ενημερώθηκε το γραφείο.');
        }
    } elseif ($act === 'reschedule') {
        $date = $_POST['date'] ?? '';
        $time = $_POST['time'] ?? '';
        if (!appt_is_modifiable($appt)) {
            flash_set('bad', 'Η αλλαγή επιτρέπεται έως ' . CANCEL_WINDOW_HOURS . ' ώρες πριν.');
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$time)) {
            flash_set('bad', 'Συμπλήρωσε έγκυρη ημερομηνία και ώρα.');
        } elseif (!in_array($time, portal_free_slots($date), true)) {
            flash_set('bad', 'Η ώρα δεν είναι διαθέσιμη. Δες τις διαθέσιμες ώρες και δοκίμασε ξανά.');
        } else {
            // ακύρωσε τυχόν προηγούμενο εκκρεμές αίτημα για το ίδιο ραντεβού
            q("UPDATE appointment_requests SET status='rejected', decided_at=NOW() WHERE appointment_id=? AND status='pending'", [$id]);
            q("INSERT INTO appointment_requests (appointment_id, client_id, requested_date, requested_time, status) VALUES (?,?,?,?, 'pending')",
              [$id, $cid, $date, $time.':00']);
            notify_admin('reschedule_request', $cid, $id, $me['name'] . ' ζητά αλλαγή του ραντεβού της ' . gr_date($appt['appointment_date']) . ' ' . hhmm($appt['appointment_time']) . ' → ' . gr_date($date) . ' ' . $time);
            send_notification_email(SITE_ADMIN_EMAIL, 'Αίτημα αλλαγής ραντεβού',
                $me['name'] . ' ζητά αλλαγή:\nΑπό: ' . gr_date($appt['appointment_date']) . ' ' . hhmm($appt['appointment_time']) . '\nΣε: ' . gr_date($date) . ' ' . $time);
            flash_set('ok', 'Το αίτημα αλλαγής στάλθηκε. Θα ενημερωθείς μόλις εγκριθεί.');
        }
    }
    redirect('appointments.php');
}

$appts = q("SELECT * FROM appointments WHERE client_id=? ORDER BY appointment_date DESC, appointment_time DESC", [$cid])->fetchAll(PDO::FETCH_ASSOC);
// εκκρεμή αιτήματα ανά appointment_id
$reqs = [];
foreach (q("SELECT * FROM appointment_requests WHERE client_id=? AND status='pending'", [$cid])->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $reqs[(int)$r['appointment_id']] = $r;
}
$now = time();
$st_lbl = ['pending'=>['Σε αναμονή','warn'],'confirmed'=>['Επιβεβαιωμένο','ok'],'cancelled'=>['Ακυρωμένο','bad']];

$page_title = 'Τα ραντεβού μου';
$active = 'appts';
require __DIR__ . '/layout_top.php';
?>
<p class="p-note">Μπορείς να ακυρώσεις ή να ζητήσεις αλλαγή ώρας έως <?= CANCEL_WINDOW_HOURS ?> ώρες πριν το ραντεβού. Τα αιτήματα αλλαγής εγκρίνονται από το γραφείο.</p>

<?php if (!$appts): ?>
  <div class="p-panel"><p class="p-empty">Δεν έχεις ραντεβού. <a href="../booking.php">Κλείσε το πρώτο σου ραντεβού →</a></p></div>
<?php else: ?>
  <div class="p-appts">
    <?php foreach ($appts as $a): [$lbl,$cl]=$st_lbl[$a['status']]; $when=strtotime($a['appointment_date'].' '.$a['appointment_time']); $future=$when>=$now; $mod=appt_is_modifiable($a); $pending=$reqs[(int)$a['id']]??null; ?>
    <div class="p-appt <?= $future?'':'is-past' ?>">
      <div class="p-appt-when">
        <span class="p-appt-date"><?= gr_date($a['appointment_date']) ?></span>
        <span class="p-appt-time mono"><?= hhmm($a['appointment_time']) ?></span>
      </div>
      <div class="p-appt-body">
        <span class="badge <?= $cl ?>"><?= $lbl ?></span>
        <?php if ($pending): ?><span class="badge warn">Αίτημα αλλαγής: <?= gr_date($pending['requested_date']) ?> <?= hhmm($pending['requested_time']) ?></span><?php endif; ?>
        <?php if (($a['mode']??'')==='online'): ?>
          <span class="badge ok">💻 Online</span>
          <?php if ($future && $a['status']!=='cancelled' && !empty($a['meeting_link'])): ?><a class="p-cal-link" href="<?= e($a['meeting_link']) ?>" target="_blank" rel="noopener">🔗 Σύνδεσμος τηλεδιάσκεψης</a><?php endif; ?>
        <?php else: ?><span class="badge muted">📍 Δια ζώσης</span><?php endif; ?>
        <?php if ($a['notes']): ?><p class="p-appt-notes"><?= e($a['notes']) ?></p><?php endif; ?>
        <?php if ($future && $a['status']!=='cancelled'): ?><a class="p-cal-link" href="ics.php?appt=<?= (int)$a['id'] ?>">📅 Προσθήκη στο ημερολόγιο</a><?php endif; ?>
      </div>
      <?php if ($future && $a['status']!=='cancelled' && $mod && !$pending): ?>
      <div class="p-appt-actions">
        <details class="p-resched">
          <summary class="btn btn-outline btn-sm">Αλλαγή ώρας</summary>
          <form method="post" class="p-resched-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reschedule">
            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
            <label>Νέα ημερομηνία<input type="date" name="date" required></label>
            <label>Νέα ώρα<input type="time" name="time" required></label>
            <button class="btn btn-primary btn-sm" type="submit">Αποστολή αιτήματος</button>
          </form>
        </details>
        <form method="post" class="inline-form" data-confirm="Σίγουρα θέλεις να ακυρώσεις αυτό το ραντεβού;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="cancel">
          <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit">Ακύρωση</button>
        </form>
      </div>
      <?php elseif ($future && $a['status']!=='cancelled' && !$mod): ?>
        <div class="p-appt-actions"><span class="p-lock">Κλειδωμένο (<?= CANCEL_WINDOW_HOURS ?>ω όριο)</span></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/layout_bottom.php'; ?>

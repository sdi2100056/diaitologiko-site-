<?php
require_once __DIR__ . '/init.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['action'] ?? '';

    if ($act === 'mark_read') {
        q("UPDATE admin_notifications SET is_read=1 WHERE id=?", [(int)$_POST['nid']]);
        redirect('notifications.php');
    }
    if ($act === 'mark_all') {
        q("UPDATE admin_notifications SET is_read=1 WHERE is_read=0");
        flash_set('ok','Όλες οι ειδοποιήσεις σημειώθηκαν ως διαβασμένες.');
        redirect('notifications.php');
    }

    if ($act === 'approve' || $act === 'reject') {
        $rid = (int)($_POST['rid'] ?? 0);
        $r = q("SELECT * FROM appointment_requests WHERE id=? AND status='pending'", [$rid])->fetch(PDO::FETCH_ASSOC);
        if (!$r) { flash_set('bad','Το αίτημα δεν βρέθηκε ή έχει ήδη απαντηθεί.'); redirect('notifications.php'); }
        $appt = q("SELECT * FROM appointments WHERE id=?", [$r['appointment_id']])->fetch(PDO::FETCH_ASSOC);
        $client = q("SELECT * FROM clients WHERE id=?", [$r['client_id']])->fetch(PDO::FETCH_ASSOC);

        if ($act === 'approve') {
            // Έλεγχος ότι το νέο slot δεν είναι πιασμένο από άλλο ραντεβού
            $taken = q("SELECT COUNT(*) FROM appointments WHERE appointment_date=? AND appointment_time=? AND status!='cancelled' AND id<>?",
                       [$r['requested_date'], $r['requested_time'], $r['appointment_id']])->fetchColumn();
            if ($taken) {
                flash_set('bad','Η ζητούμενη ώρα είναι πλέον κλεισμένη. Το αίτημα δεν εγκρίθηκε — επικοινώνησε με τον πελάτη.');
                redirect('notifications.php');
            }
            try {
                q("UPDATE appointments SET appointment_date=?, appointment_time=? WHERE id=?",
                  [$r['requested_date'], $r['requested_time'], $r['appointment_id']]);
            } catch (PDOException $e) {
                flash_set('bad','Σύγκρουση ώρας — το αίτημα δεν εγκρίθηκε.');
                redirect('notifications.php');
            }
            q("UPDATE appointment_requests SET status='approved', decided_at=NOW() WHERE id=?", [$rid]);
            add_client_notification($r['client_id'],'reschedule','Το αίτημα αλλαγής ραντεβού εγκρίθηκε','appointments.php');
            if ($client) send_notification_email($client['email'], 'Έγκριση αλλαγής ραντεβού',
                "Γεια σου {$client['name']},\n\nΤο αίτημα αλλαγής εγκρίθηκε. Νέο ραντεβού: " . gr_date($r['requested_date']) . ' ' . hhmm($r['requested_time']) . ".");
            flash_set('ok','Το αίτημα εγκρίθηκε και το ραντεβού ενημερώθηκε.');
        } else {
            q("UPDATE appointment_requests SET status='rejected', decided_at=NOW() WHERE id=?", [$rid]);
            add_client_notification($r['client_id'],'reschedule','Το αίτημα αλλαγής ραντεβού απορρίφθηκε','appointments.php');
            if ($client) send_notification_email($client['email'], 'Αίτημα αλλαγής ραντεβού',
                "Γεια σου {$client['name']},\n\nΔυστυχώς το αίτημα αλλαγής για τις " . gr_date($r['requested_date']) . ' ' . hhmm($r['requested_time']) . " δεν έγινε δεκτό. Επικοινώνησε μαζί μας για εναλλακτική ώρα.");
            flash_set('ok','Το αίτημα απορρίφθηκε και ενημερώθηκε ο πελάτης.');
        }
        // σημείωσε τη σχετική ειδοποίηση ως διαβασμένη
        q("UPDATE admin_notifications SET is_read=1 WHERE type='reschedule_request' AND appointment_id=? AND is_read=0", [$r['appointment_id']]);
        redirect('notifications.php');
    }
}

// Εκκρεμή αιτήματα αλλαγής
$requests = q("SELECT r.*, c.name cname, c.email cemail, a.appointment_date cur_date, a.appointment_time cur_time
               FROM appointment_requests r
               JOIN clients c ON c.id=r.client_id
               JOIN appointments a ON a.id=r.appointment_id
               WHERE r.status='pending'
               ORDER BY r.created_at ASC")->fetchAll(PDO::FETCH_ASSOC);

// Ειδοποιήσεις
$notifs = q("SELECT n.*, c.name cname FROM admin_notifications n
             LEFT JOIN clients c ON c.id=n.client_id
             ORDER BY n.is_read ASC, n.created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
$unread = 0; foreach ($notifs as $n) { if (!$n['is_read']) $unread++; }

$page_title = 'Ειδοποιήσεις';
$active = 'notifications';
require __DIR__ . '/layout_top.php';
?>
<?php if ($requests): ?>
<div class="card">
  <div class="card-head"><h2>Εκκρεμή αιτήματα αλλαγής ώρας (<?= count($requests) ?>)</h2></div>
  <?php foreach ($requests as $r): ?>
  <div class="req-box">
    <div style="flex:1;min-width:220px">
      <strong><?= e($r['cname']) ?></strong> ζητά αλλαγή:<br>
      <span class="muted-cell">Από <?= gr_date($r['cur_date']) ?> <?= hhmm($r['cur_time']) ?></span>
      &nbsp;→&nbsp;
      <strong><?= gr_date($r['requested_date']) ?> <?= hhmm($r['requested_time']) ?></strong>
      <div class="notif-time"><?= gr_datetime($r['created_at']) ?></div>
    </div>
    <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="rid" value="<?= (int)$r['id'] ?>"><button name="action" value="approve" class="btn btn-primary">Έγκριση</button></form>
    <form method="post" class="inline-form" data-confirm="Απόρριψη του αιτήματος;"><?= csrf_field() ?><input type="hidden" name="rid" value="<?= (int)$r['id'] ?>"><button name="action" value="reject" class="btn btn-danger">Απόρριψη</button></form>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <h2>Ειδοποιήσεις<?php if ($unread): ?> <span class="client-status invited"><?= $unread ?> νέες</span><?php endif; ?></h2>
    <?php if ($unread): ?><form method="post" class="inline-form"><?= csrf_field() ?><button name="action" value="mark_all" class="btn btn-outline">Όλες ως διαβασμένες</button></form><?php endif; ?>
  </div>
  <?php if ($notifs): ?>
    <?php foreach ($notifs as $n): ?>
    <div class="notif <?= $n['is_read']?'':'unread' ?>">
      <span class="notif-dot <?= $n['is_read']?'read':'' ?>"></span>
      <div class="notif-body">
        <div><?= e($n['message']) ?></div>
        <div class="notif-time"><?= gr_datetime($n['created_at']) ?><?php if($n['client_id']):?> · <a href="client-view.php?id=<?= (int)$n['client_id'] ?>">Καρτέλα πελάτη</a><?php endif;?></div>
      </div>
      <?php if (!$n['is_read']): ?>
      <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="nid" value="<?= (int)$n['id'] ?>"><button name="action" value="mark_read" class="chip" title="Διαβάστηκε">✓</button></form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  <?php else: ?>
    <p class="empty">Καμία ειδοποίηση.</p>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/layout_bottom.php'; ?>

<?php
require_once __DIR__ . '/init.php';
require_client_login();
$me = current_client();
$cid = (int)$me['id'];
$email = $me['email'];

$next = q("SELECT * FROM appointments WHERE client_id=? AND status!='cancelled'
           AND CONCAT(appointment_date,' ',appointment_time) >= NOW()
           ORDER BY appointment_date ASC, appointment_time ASC LIMIT 1", [$cid])->fetch(PDO::FETCH_ASSOC);

$upcoming = (int) q("SELECT COUNT(*) FROM appointments WHERE client_id=? AND status!='cancelled'
                     AND CONCAT(appointment_date,' ',appointment_time) >= NOW()", [$cid])->fetchColumn();
$past = (int) q("SELECT COUNT(*) FROM appointments WHERE client_id=? AND status!='cancelled'
                 AND CONCAT(appointment_date,' ',appointment_time) < NOW()", [$cid])->fetchColumn();
$purchases = (int) q("SELECT COUNT(*) FROM orders WHERE (client_id=? OR (client_email=? AND client_email<>'')) AND status='paid'", [$cid,$email])->fetchColumn();

$recent = q("SELECT o.*, s.name sname FROM orders o LEFT JOIN services s ON s.id=o.service_id
             WHERE (o.client_id=? OR (o.client_email=? AND o.client_email<>'')) AND o.status='paid'
             ORDER BY o.created_at DESC LIMIT 4", [$cid,$email])->fetchAll(PDO::FETCH_ASSOC);

$pkg = get_package_summary($cid);
$intake_done = (int) q("SELECT COUNT(*) FROM client_intake WHERE client_id=? AND submitted_at IS NOT NULL", [$cid])->fetchColumn();

$page_title = 'Καλώς ήρθες, ' . $me['name'];
$active = 'home';
require __DIR__ . '/layout_top.php';
?>
<section class="p-hero">
  <?php if ($next): ?>
    <div class="p-hero-label">Επόμενο ραντεβού</div>
    <div class="p-hero-main"><?= e($GR_DAYS[(int)date('w', strtotime($next['appointment_date']))]) ?>, <?= gr_date($next['appointment_date']) ?> · <span class="mono"><?= hhmm($next['appointment_time']) ?></span></div>
    <a href="appointments.php" class="btn btn-light">Διαχείριση ραντεβού</a>
  <?php else: ?>
    <div class="p-hero-label">Ραντεβού</div>
    <div class="p-hero-main">Δεν έχεις προγραμματισμένο ραντεβού.</div>
    <a href="../booking.php" class="btn btn-light">Κλείσε ραντεβού</a>
  <?php endif; ?>
</section>

<?php if (!$intake_done): ?>
<a href="intake.php" class="p-notice">
  <strong>Συμπλήρωσε το ιστορικό σου</strong> — βοηθά τη διαιτολόγο να σε καθοδηγήσει σωστά πριν το πρώτο ραντεβού. <span class="p-notice-cta">Συμπλήρωση →</span>
</a>
<?php endif; ?>

<section class="p-cards">
  <a class="p-card" href="appointments.php"><span class="p-num"><?= $upcoming ?></span><span class="p-cap">Επερχόμενα ραντεβού</span></a>
  <a class="p-card" href="appointments.php"><span class="p-num"><?= $past ?></span><span class="p-cap">Ολοκληρωμένα ραντεβού</span></a>
  <?php if ($pkg['has']): ?>
    <div class="p-card"><span class="p-num"><?= $pkg['remaining'] ?></span><span class="p-cap">Συνεδρίες που απομένουν</span></div>
  <?php else: ?>
    <a class="p-card" href="orders.php"><span class="p-num"><?= $purchases ?></span><span class="p-cap">Αγορές</span></a>
  <?php endif; ?>
</section>

<section class="p-panel">
  <div class="p-panel-head"><h2>Πρόσφατες αγορές</h2><a href="orders.php">Όλες →</a></div>
  <?php if ($recent): ?>
    <ul class="p-list">
      <?php foreach ($recent as $o): ?>
        <li><span><?= e($o['sname'] ?? 'Υπηρεσία') ?></span><span class="p-list-meta"><?= gr_date($o['created_at']) ?> · <strong><?= eur($o['amount']) ?></strong></span></li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p class="p-empty">Δεν έχεις αγορές ακόμη.</p>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/layout_bottom.php'; ?>

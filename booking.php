<?php
require_once __DIR__ . '/includes/functions.php';

$waitlist_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'waitlist') {
    if (trim($_POST['website'] ?? '') === '') { // honeypot κατά spam
        $wname = trim($_POST['w_name'] ?? '');
        $wemail = trim($_POST['w_email'] ?? '');
        $wphone = trim($_POST['w_phone'] ?? '');
        $wdate = ($_POST['w_date'] ?? '') ?: null;
        $wnote = trim($_POST['w_note'] ?? '');
        if ($wname !== '' && filter_var($wemail, FILTER_VALIDATE_EMAIL)) {
            try {
                $pdo = get_db();
                $cid = null;
                $cs = $pdo->prepare("SELECT id FROM clients WHERE email=?"); $cs->execute([$wemail]); $cid = $cs->fetchColumn() ?: null;
                $pdo->prepare("INSERT INTO waitlist (client_id,name,email,phone,requested_date,note) VALUES (?,?,?,?,?,?)")
                    ->execute([$cid, $wname, $wemail, $wphone ?: null, $wdate, $wnote ?: null]);
                if (function_exists('notify_admin')) notify_admin('waitlist', $cid ?: null, null, 'Νέα εγγραφή λίστας αναμονής: ' . $wname . ($wdate ? " ($wdate)" : ''));
                if (defined('SITE_ADMIN_EMAIL')) send_notification_email(SITE_ADMIN_EMAIL, 'Νέα εγγραφή στη λίστα αναμονής',
                    "$wname <$wemail> " . ($wphone ? "τηλ. $wphone " : '') . ($wdate ? "για $wdate" : '') . "\n" . $wnote);
                $waitlist_msg = 'ok';
            } catch (Throwable $e) { $waitlist_msg = 'err'; }
        } else { $waitlist_msg = 'err'; }
    }
}

$page_title = 'Ραντεβού';
include __DIR__ . '/includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<main>
    <section class="section">
        <div class="container">
            <div class="section-head reveal">
                <span class="eyebrow">Ραντεβού</span>
                <h1>Κλείσε το ραντεβού σου</h1>
                <p class="lede">Επίλεξε ημερομηνία στο ημερολόγιο και μετά διαθέσιμη ώρα.</p>
            </div>

            <div class="booking-steps reveal">
                <span class="bstep on"><i>1</i> Ημερομηνία</span>
                <span class="bstep"><i>2</i> Ώρα</span>
                <span class="bstep"><i>3</i> Στοιχεία</span>
            </div>

            <?php $practs = get_practitioners(); ?>
            <div class="booking-options reveal">
              <?php if ($practs): ?>
              <label class="bopt">
                <span>Με ποιον/ποια;</span>
                <select id="practitioner">
                  <?php foreach ($practs as $pr): ?><option value="<?= (int)$pr['id'] ?>"><?= e($pr['name']) ?></option><?php endforeach; ?>
                </select>
              </label>
              <?php endif; ?>
              <label class="bopt">
                <span>Τύπος συνεδρίας</span>
                <select id="appt-type">
                  <option value="new">Νέο ραντεβού — 60′</option>
                  <option value="followup">Επαναληπτικό — 30′</option>
                </select>
              </label>
            </div>

            <div class="booking-layout reveal">
                <div id="calendar"></div>

                <div class="slots-panel">
                    <h3 id="slots-title">Επίλεξε πρώτα ημερομηνία</h3>
                    <div class="slots-list" id="slots-list"></div>

                    <form class="booking-form" id="booking-form">
                        <label for="client_name">Ονοματεπώνυμο</label>
                        <input type="text" id="client_name" name="client_name" required>

                        <label for="client_email">Email</label>
                        <input type="email" id="client_email" name="client_email" required>

                        <label for="client_phone">Τηλέφωνο</label>
                        <input type="tel" id="client_phone" name="client_phone" required>

                        <label for="notes">Σημειώσεις (προαιρετικό)</label>
                        <textarea id="notes" name="notes" rows="3"></textarea>

                        <button type="submit" class="btn btn-primary">Επιβεβαίωση Ραντεβού</button>
                        <div class="form-msg" id="form-msg"></div>
                    </form>
                </div>
            </div>

            <div class="waitlist-box reveal" id="waitlist">
              <h3>Δεν βρίσκεις διαθέσιμη ώρα;</h3>
              <p class="lede">Μπες στη λίστα αναμονής και θα σε ειδοποιήσουμε μόλις ελευθερωθεί μια θέση.</p>
              <?php if ($waitlist_msg === 'ok'): ?>
                <div class="flash flash-ok">Μπήκες στη λίστα αναμονής! Θα επικοινωνήσουμε μαζί σου σύντομα.</div>
              <?php elseif ($waitlist_msg === 'err'): ?>
                <div class="flash flash-bad">Έλεγξε τα στοιχεία (όνομα &amp; έγκυρο email) και δοκίμασε ξανά.</div>
              <?php endif; ?>
              <form method="post" class="waitlist-form" action="booking.php#waitlist">
                <input type="hidden" name="form" value="waitlist">
                <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
                <div class="wl-row">
                  <input type="text" name="w_name" placeholder="Ονοματεπώνυμο" required>
                  <input type="email" name="w_email" placeholder="Email" required>
                  <input type="tel" name="w_phone" placeholder="Τηλέφωνο">
                </div>
                <div class="wl-row">
                  <input type="date" name="w_date" aria-label="Επιθυμητή ημερομηνία">
                  <input type="text" name="w_note" placeholder="Σημείωση (π.χ. προτίμηση απόγευμα)">
                  <button type="submit" class="btn btn-primary">Εγγραφή</button>
                </div>
              </form>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="assets/js/booking.js" defer></script>

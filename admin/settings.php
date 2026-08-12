<?php
require_once __DIR__ . '/init.php';
require_login();

$hash = '';
$plain = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (($_POST['action'] ?? '') === 'gen') {
        $plain = $_POST['newpass'] ?? '';
        if (strlen($plain) < 6) {
            flash_set('bad', 'Ο κωδικός πρέπει να έχει τουλάχιστον 6 χαρακτήρες.');
        } else {
            $hash = password_hash($plain, PASSWORD_DEFAULT);
        }
    }
    if (($_POST['action'] ?? '') === 'save_mail') {
        $cfg = [
            'MAIL_METHOD'      => in_array($_POST['method'] ?? 'mail', ['mail','smtp'], true) ? $_POST['method'] : 'mail',
            'MAIL_SMTP_HOST'   => trim($_POST['host'] ?? ''),
            'MAIL_SMTP_PORT'   => (int)($_POST['port'] ?? 587),
            'MAIL_SMTP_SECURE' => in_array($_POST['secure'] ?? 'tls', ['tls','ssl',''], true) ? $_POST['secure'] : 'tls',
            'MAIL_SMTP_USER'   => trim($_POST['user'] ?? ''),
            'MAIL_SMTP_PASS'   => (string)($_POST['pass'] ?? ''),
            'MAIL_FROM'        => trim($_POST['from'] ?? ''),
            'MAIL_FROM_NAME'   => trim($_POST['from_name'] ?? ''),
            'CRON_KEY'         => trim($_POST['cron_key'] ?? ''),
        ];
        $php = "<?php\n// Ρυθμίσεις email — δημιουργήθηκαν από το admin panel.\n";
        foreach ($cfg as $k => $v) {
            $php .= is_int($v) ? "define('$k', $v);\n" : "define('$k', '" . str_replace("'", "\\'", $v) . "');\n";
        }
        $target = __DIR__ . '/../includes/mail_config.php';
        if (@file_put_contents($target, $php) !== false) {
            flash_set('ok', 'Οι ρυθμίσεις email αποθηκεύτηκαν.');
        } else {
            flash_set('bad', 'Δεν ήταν δυνατή η εγγραφή του includes/mail_config.php — άλλαξέ το χειροκίνητα.');
        }
        redirect('settings.php#mail');
    }
    if (($_POST['action'] ?? '') === 'test_mail') {
        require_once __DIR__ . '/../includes/mailer.php';
        $to = trim($_POST['test_to'] ?? '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash_set('bad', 'Δώσε έγκυρο email για τη δοκιμή.');
        } else {
            $ok = send_test_mail($to);
            flash_set($ok ? 'ok' : 'bad', $ok ? "Το δοκιμαστικό email στάλθηκε στο $to." : 'Η αποστολή απέτυχε — έλεγξε τις ρυθμίσεις SMTP.');
        }
        redirect('settings.php#mail');
    }
    if (($_POST['action'] ?? '') === 'save_business') {
        foreach (['business_name','contact_email','contact_phone','address','hours'] as $k) {
            set_setting($k, trim($_POST[$k] ?? ''));
        }
        audit('settings_update', 'business');
        flash_set('ok', 'Τα στοιχεία επιχείρησης αποθηκεύτηκαν.');
        redirect('settings.php#business');
    }
    if (($_POST['action'] ?? '') === '2fa_init') {
        // δημιουργία νέου secret (δεν ενεργοποιείται ακόμη)
        $_SESSION['2fa_setup_secret'] = totp_secret();
        redirect('settings.php#twofa');
    }
    if (($_POST['action'] ?? '') === '2fa_enable') {
        $secret = $_SESSION['2fa_setup_secret'] ?? '';
        $code = trim($_POST['code'] ?? '');
        if ($secret && totp_verify($secret, $code)) {
            set_setting('admin_totp_secret', $secret);
            set_setting('admin_2fa_enabled', '1');
            unset($_SESSION['2fa_setup_secret']);
            audit('2fa_enable', 'admin');
            flash_set('ok', 'Το 2FA ενεργοποιήθηκε.');
        } else {
            flash_set('bad', 'Λάθος κωδικός — δοκίμασε ξανά με τον τρέχοντα 6ψήφιο.');
        }
        redirect('settings.php#twofa');
    }
    if (($_POST['action'] ?? '') === '2fa_disable') {
        set_setting('admin_2fa_enabled', '0');
        set_setting('admin_totp_secret', '');
        audit('2fa_disable', 'admin');
        flash_set('ok', 'Το 2FA απενεργοποιήθηκε.');
        redirect('settings.php#twofa');
    }
}
// τρέχουσες τιμές email για τη φόρμα
if (is_file(__DIR__ . '/../includes/mail_config.php')) require_once __DIR__ . '/../includes/mail_config.php';
$mailcur = [
    'method' => defined('MAIL_METHOD') ? MAIL_METHOD : 'mail',
    'host'   => defined('MAIL_SMTP_HOST') ? MAIL_SMTP_HOST : '',
    'port'   => defined('MAIL_SMTP_PORT') ? MAIL_SMTP_PORT : 587,
    'secure' => defined('MAIL_SMTP_SECURE') ? MAIL_SMTP_SECURE : 'tls',
    'user'   => defined('MAIL_SMTP_USER') ? MAIL_SMTP_USER : '',
    'pass'   => defined('MAIL_SMTP_PASS') ? MAIL_SMTP_PASS : '',
    'from'   => defined('MAIL_FROM') ? MAIL_FROM : '',
    'from_name' => defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Διαιτολογικό Γραφείο',
    'cron_key'  => defined('CRON_KEY') ? CRON_KEY : '',
];
$mail_writable = is_writable(__DIR__ . '/../includes/mail_config.php') || is_writable(__DIR__ . '/../includes/');

$using_hash = defined('ADMIN_PASS_HASH') && ADMIN_PASS_HASH !== '';

// έλεγχος σύνδεσης βάσης
$db_ok = true; $db_msg = '';
try { get_db()->query('SELECT 1'); } catch (Throwable $t) { $db_ok=false; $db_msg=$t->getMessage(); }

$page_title = 'Ρυθμίσεις';
$active = 'settings';
require __DIR__ . '/layout_top.php';
?>
<div class="grid-2">
  <div class="card">
    <div class="card-head"><h2>Αλλαγή κωδικού</h2></div>
    <p class="prose">Γράψε νέο κωδικό. Θα δημιουργηθεί ένα κρυπτογραφημένο <em>hash</em> — αντέγραψέ το στο αρχείο
      <code>admin/config.php</code>, στη γραμμή <code>ADMIN_PASS_HASH</code>.</p>
    <form method="post" class="subform">
      <?= csrf_field() ?><input type="hidden" name="action" value="gen">
      <div class="subform-row">
        <input type="text" name="newpass" value="<?= e($plain) ?>" placeholder="Νέος κωδικός (≥ 6 χαρακτήρες)" required>
        <button class="btn btn-primary" type="submit">Δημιουργία hash</button>
      </div>
    </form>
    <?php if ($hash): ?>
      <p class="prose" style="margin-top:14px">Αντικατέστησε τη γραμμή στο <code>config.php</code> με:</p>
      <pre class="code-out">define('ADMIN_PASS_HASH', '<?= e($hash) ?>');</pre>
      <p class="hint-inline">Μετά την αποθήκευση, μπες ξανά με τον νέο κωδικό.</p>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><h2>Κατάσταση</h2></div>
    <ul class="status-list">
      <li><span>Χρήστης διαχειριστή</span><strong><?= e(ADMIN_USER) ?></strong></li>
      <li><span>Κωδικός</span><strong><?= $using_hash ? 'Κρυπτογραφημένος ✓' : 'Απλός (άλλαξέ τον)' ?></strong></li>
      <li><span>Σύνδεση βάσης</span><strong class="<?= $db_ok?'txt-ok':'txt-bad' ?>"><?= $db_ok ? 'Ενεργή ✓' : 'Σφάλμα' ?></strong></li>
      <li><span>PHP</span><strong><?= e(PHP_VERSION) ?></strong></li>
    </ul>
    <?php if (!$db_ok): ?><p class="hint-inline txt-bad"><?= e($db_msg) ?></p><?php endif; ?>
    <p class="prose" style="margin-top:12px">Τα στοιχεία σύνδεσης της βάσης και του Viva Wallet ρυθμίζονται στο <code>includes/db.php</code>.</p>
  </div>
</div>

<div class="card" id="business">
  <div class="card-head"><h2>Στοιχεία επιχείρησης</h2></div>
  <p class="prose">Χρησιμοποιούνται σε τίτλους, emails, footer και structured data (SEO).</p>
  <form method="post" class="p-form" style="max-width:none">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_business">
    <div class="subform-row">
      <label class="fld"><span>Επωνυμία</span><input type="text" name="business_name" value="<?= e(setting('business_name','')) ?>" placeholder="Διαιτολογικό Γραφείο"></label>
      <label class="fld"><span>Email επικοινωνίας</span><input type="email" name="contact_email" value="<?= e(setting('contact_email','')) ?>"></label>
      <label class="fld"><span>Τηλέφωνο</span><input type="text" name="contact_phone" value="<?= e(setting('contact_phone','')) ?>"></label>
    </div>
    <div class="subform-row">
      <label class="fld"><span>Διεύθυνση</span><input type="text" name="address" value="<?= e(setting('address','')) ?>"></label>
      <label class="fld"><span>Ωράριο</span><input type="text" name="hours" value="<?= e(setting('hours','')) ?>" placeholder="Δευ–Παρ 9:00–17:00"></label>
    </div>
    <div class="form-actions" style="border:0;padding:0;justify-content:flex-start"><button class="btn btn-primary" type="submit">Αποθήκευση</button></div>
  </form>
</div>

<div class="card" id="twofa">
  <div class="card-head"><h2>Έλεγχος ταυτότητας 2 παραγόντων (2FA)</h2>
    <?php if (setting('admin_2fa_enabled','')==='1'): ?><span class="badge ok">Ενεργό</span><?php else: ?><span class="badge muted">Ανενεργό</span><?php endif; ?>
  </div>
  <?php if (setting('admin_2fa_enabled','')==='1'): ?>
    <p class="prose">Το 2FA είναι ενεργό. Στη σύνδεση θα ζητείται 6ψήφιος κωδικός από την εφαρμογή authenticator.</p>
    <form method="post" data-confirm="Σίγουρα απενεργοποίηση 2FA;"><?= csrf_field() ?><input type="hidden" name="action" value="2fa_disable"><button class="btn btn-danger" type="submit">Απενεργοποίηση 2FA</button></form>
  <?php else: ?>
    <?php $setup = $_SESSION['2fa_setup_secret'] ?? ''; ?>
    <?php if (!$setup): ?>
      <p class="prose">Πρόσθεσε ένα δεύτερο επίπεδο ασφάλειας με εφαρμογή authenticator (Google Authenticator, Authy, κ.λπ.).</p>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="2fa_init"><button class="btn btn-primary" type="submit">Ρύθμιση 2FA</button></form>
    <?php else: $issuer = rawurlencode(biz_name()); $label = rawurlencode(biz_name().':admin');
      $uri = "otpauth://totp/$label?secret=$setup&issuer=$issuer&digits=6&period=30"; ?>
      <ol class="prose bullet">
        <li>Πρόσθεσε στον authenticator το κλειδί: <code style="font-size:1rem;letter-spacing:.05em"><?= e($setup) ?></code></li>
        <li>Ή χρησιμοποίησε το URI: <code style="word-break:break-all"><?= e($uri) ?></code></li>
        <li>Εισήγαγε τον 6ψήφιο κωδικό για επιβεβαίωση:</li>
      </ol>
      <form method="post" class="subform" style="border:0;padding:0">
        <?= csrf_field() ?><input type="hidden" name="action" value="2fa_enable">
        <div class="subform-row">
          <input type="text" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6" placeholder="123456" required class="w-70" style="letter-spacing:.2em;text-align:center">
          <button class="btn btn-primary" type="submit">Ενεργοποίηση</button>
        </div>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-head"><h2>Αρχείο ενεργειών</h2></div>
  <p class="prose">Ιστορικό ενεργειών διαχείρισης (συνδέσεις, αλλαγές, διαγραφές).</p>
  <a class="btn btn-outline" href="audit.php">Άνοιγμα audit log →</a>
</div>

<div class="card" id="mail">
  <div class="card-head"><h2>Email (SMTP)</h2></div>
  <p class="prose">Για αξιόπιστη παράδοση (προσκλήσεις, υπενθυμίσεις, ειδοποιήσεις) χρησιμοποίησε <strong>SMTP</strong> αντί για την προεπιλογή <code>mail()</code>. Οι ρυθμίσεις αποθηκεύονται στο <code>includes/mail_config.php</code>.</p>
  <?php if (!$mail_writable): ?><p class="hint-inline txt-bad">Το <code>includes/mail_config.php</code> δεν είναι εγγράψιμο — μπορείς να το επεξεργαστείς χειροκίνητα.</p><?php endif; ?>
  <form method="post" class="p-form" style="max-width:none">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_mail">
    <div class="subform-row">
      <label class="fld" style="min-width:160px"><span>Μέθοδος</span>
        <select name="method">
          <option value="mail" <?= $mailcur['method']==='mail'?'selected':'' ?>>PHP mail()</option>
          <option value="smtp" <?= $mailcur['method']==='smtp'?'selected':'' ?>>SMTP</option>
        </select>
      </label>
      <label class="fld"><span>SMTP Host</span><input type="text" name="host" value="<?= e($mailcur['host']) ?>" placeholder="smtp.gmail.com"></label>
      <label class="fld" style="min-width:100px"><span>Port</span><input type="number" name="port" value="<?= e($mailcur['port']) ?>"></label>
      <label class="fld" style="min-width:120px"><span>Ασφάλεια</span>
        <select name="secure">
          <option value="tls" <?= $mailcur['secure']==='tls'?'selected':'' ?>>TLS</option>
          <option value="ssl" <?= $mailcur['secure']==='ssl'?'selected':'' ?>>SSL</option>
          <option value="" <?= $mailcur['secure']===''?'selected':'' ?>>Καμία</option>
        </select>
      </label>
    </div>
    <div class="subform-row">
      <label class="fld"><span>SMTP User</span><input type="text" name="user" value="<?= e($mailcur['user']) ?>" autocomplete="off"></label>
      <label class="fld"><span>SMTP Password</span><input type="password" name="pass" value="<?= e($mailcur['pass']) ?>" autocomplete="new-password"></label>
    </div>
    <div class="subform-row">
      <label class="fld"><span>Αποστολέας (From)</span><input type="email" name="from" value="<?= e($mailcur['from']) ?>" placeholder="no-reply@yourdomain.gr"></label>
      <label class="fld"><span>Όνομα αποστολέα</span><input type="text" name="from_name" value="<?= e($mailcur['from_name']) ?>"></label>
    </div>
    <div class="subform-row">
      <label class="fld"><span>CRON_KEY (για υπενθυμίσεις μέσω URL)</span><input type="text" name="cron_key" value="<?= e($mailcur['cron_key']) ?>" placeholder="π.χ. ένα τυχαίο μυστικό"></label>
    </div>
    <div class="form-actions" style="border:0;padding:0;justify-content:flex-start"><button class="btn btn-primary" type="submit">Αποθήκευση</button></div>
  </form>

  <form method="post" class="subform" style="margin-top:14px">
    <?= csrf_field() ?><input type="hidden" name="action" value="test_mail">
    <div class="subform-row">
      <input type="email" name="test_to" placeholder="email για δοκιμή" required>
      <button class="btn btn-outline" type="submit">Αποστολή δοκιμαστικού</button>
    </div>
  </form>
</div>

<div class="card">
  <div class="card-head"><h2>Υπενθυμίσεις ραντεβού</h2></div>
  <p class="prose">Το script <code>cron/send-reminders.php</code> στέλνει υπενθύμιση ~24 ώρες πριν από κάθε ραντεβού (με συνημμένο ημερολογίου). Στήσε το να τρέχει καθημερινά:</p>
  <ul class="prose bullet">
    <li><strong>Γραμμή εντολών / cron:</strong> <code>php <?= e(realpath(__DIR__ . '/../cron/send-reminders.php') ?: 'cron/send-reminders.php') ?></code></li>
    <li><strong>Windows Task Scheduler:</strong> ημερήσια εργασία που εκτελεί το παραπάνω με το <code>php.exe</code> του XAMPP.</li>
    <li><strong>Χωρίς cron (μέσω URL):</strong> όρισε <code>CRON_KEY</code> παραπάνω και κάλεσε <code><?= e(site_base_url()) ?>/cron/send-reminders.php?key=ΤΟ_KEY</code> από μια online υπηρεσία cron.</li>
  </ul>
</div>

<div class="card">
  <div class="card-head"><h2>Πύλη Πελατών</h2></div>
  <p class="prose">Οι πελάτες βλέπουν τα ραντεβού και τις αγορές τους στο <code>/portal/</code>. Πριν χρησιμοποιηθεί για πρώτη φορά, τρέξε την αναβάθμιση της βάσης (δημιουργεί τους νέους πίνακες και συνδέει τα υπάρχοντα δεδομένα).</p>
  <a class="btn btn-primary" href="migrate.php">Αναβάθμιση / έλεγχος βάσης</a>
  <a class="btn btn-outline" href="clients.php" style="margin-left:8px">Διαχείριση πελατών</a>
</div>

<div class="card">
  <div class="card-head"><h2>Οδηγίες ασφάλειας</h2></div>
  <ul class="prose bullet">
    <li>Άλλαξε αμέσως τον προεπιλεγμένο κωδικό <code>admin1234</code>.</li>
    <li>Ο φάκελος <code>/admin</code> δεν εμφανίζεται στις μηχανές αναζήτησης (noindex), αλλά για production καλό είναι να προστεθεί και προστασία σε επίπεδο server (π.χ. <code>.htaccess</code> ή IP restriction).</li>
    <li>Σε πραγματικό περιβάλλον, όρισε <code>VIVA_DEMO</code> σε <code>false</code> και συμπλήρωσε τα production κλειδιά.</li>
  </ul>
</div>

<?php require __DIR__ . '/layout_bottom.php'; ?>

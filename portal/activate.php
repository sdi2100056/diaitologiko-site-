<?php
require_once __DIR__ . '/init.php';
if (is_client_logged_in()) redirect('index.php');

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$errors = [];
$client = null;

if ($token) {
    $client = q("SELECT * FROM clients WHERE invite_token=? AND status='invited'", [hash_token($token)])->fetch(PDO::FETCH_ASSOC);
    if ($client && $client['invite_expires'] && strtotime($client['invite_expires']) < time()) {
        $client = null; $errors[] = 'Ο σύνδεσμος πρόσκλησης έληξε. Ζήτησε νέα πρόσκληση από το γραφείο.';
    }
}
if (!$client && !$errors) $errors[] = 'Μη έγκυρος σύνδεσμος πρόσκλησης.';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $client) {
    csrf_verify();
    $p1 = $_POST['password'] ?? '';
    $p2 = $_POST['password2'] ?? '';
    $gdpr = isset($_POST['gdpr']);
    if (strlen($p1) < 8) $errors[] = 'Ο κωδικός πρέπει να έχει τουλάχιστον 8 χαρακτήρες.';
    if ($p1 !== $p2) $errors[] = 'Οι κωδικοί δεν ταιριάζουν.';
    if (!$gdpr) $errors[] = 'Απαιτείται η συγκατάθεση για την επεξεργασία των δεδομένων.';
    if (!$errors) {
        q("UPDATE clients SET password_hash=?, status='active', invite_token=NULL, invite_expires=NULL, gdpr_consent=1, gdpr_consent_at=NOW() WHERE id=?",
          [password_hash($p1, PASSWORD_DEFAULT), $client['id']]);
        session_regenerate_id(true);
        $_SESSION['client_id'] = (int)$client['id'];
        flash_set('ok', 'Ο λογαριασμός σου ενεργοποιήθηκε. Καλώς ήρθες!');
        redirect('index.php');
    }
}

$hide_nav = true;
$page_title = 'Ενεργοποίηση λογαριασμού';
require __DIR__ . '/layout_top.php';
?>
<div class="auth-wrap">
  <div class="auth-card">
    <h1>Ενεργοποίηση λογαριασμού</h1>
    <?php if ($client): ?><p class="auth-sub">Γεια σου <?= e($client['name']) ?>! Όρισε τον κωδικό σου.</p><?php endif; ?>
    <?php foreach ($errors as $er): ?><div class="flash flash-bad"><?= e($er) ?></div><?php endforeach; ?>
    <?php if ($client): ?>
    <form method="post" class="auth-form">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <label><span>Νέος κωδικός (≥ 8 χαρακτήρες)</span><input type="password" name="password" required></label>
      <label><span>Επανάληψη κωδικού</span><input type="password" name="password2" required></label>
      <label class="check"><input type="checkbox" name="gdpr"> <span>Συναινώ στην επεξεργασία των προσωπικών μου δεδομένων (συμπεριλαμβανομένων δεδομένων υγείας) για την παροχή των υπηρεσιών, σύμφωνα με την <a href="../privacy.php" target="_blank">Πολιτική Απορρήτου</a>.</span></label>
      <button type="submit" class="btn btn-primary btn-block">Ενεργοποίηση</button>
    </form>
    <?php else: ?>
      <p class="auth-links"><a href="login.php">← Επιστροφή στην είσοδο</a></p>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/layout_bottom.php'; ?>

<?php
require_once __DIR__ . '/init.php';
if (is_client_logged_in()) redirect('index.php');

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$errors = [];
$client = null;
if ($token) {
    $client = q("SELECT * FROM clients WHERE reset_token=? AND status='active'", [hash_token($token)])->fetch(PDO::FETCH_ASSOC);
    if ($client && (!$client['reset_expires'] || strtotime($client['reset_expires']) < time())) {
        $client = null; $errors[] = 'Ο σύνδεσμος επαναφοράς έληξε.';
    }
}
if (!$client && !$errors) $errors[] = 'Μη έγκυρος σύνδεσμος.';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $client) {
    csrf_verify();
    $p1 = $_POST['password'] ?? '';
    $p2 = $_POST['password2'] ?? '';
    if (strlen($p1) < 8) $errors[] = 'Ο κωδικός πρέπει να έχει τουλάχιστον 8 χαρακτήρες.';
    if ($p1 !== $p2) $errors[] = 'Οι κωδικοί δεν ταιριάζουν.';
    if (!$errors) {
        q("UPDATE clients SET password_hash=?, reset_token=NULL, reset_expires=NULL WHERE id=?",
          [password_hash($p1, PASSWORD_DEFAULT), $client['id']]);
        flash_set('ok', 'Ο κωδικός άλλαξε. Μπες με τον νέο κωδικό.');
        redirect('login.php');
    }
}

$hide_nav = true;
$page_title = 'Νέος κωδικός';
require __DIR__ . '/layout_top.php';
?>
<div class="auth-wrap">
  <div class="auth-card">
    <h1>Όρισε νέο κωδικό</h1>
    <?php foreach ($errors as $er): ?><div class="flash flash-bad"><?= e($er) ?></div><?php endforeach; ?>
    <?php if ($client): ?>
    <form method="post" class="auth-form">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <label><span>Νέος κωδικός</span><input type="password" name="password" required autofocus></label>
      <label><span>Επανάληψη</span><input type="password" name="password2" required></label>
      <button type="submit" class="btn btn-primary btn-block">Αποθήκευση</button>
    </form>
    <?php else: ?>
      <p class="auth-links"><a href="forgot.php">Ζήτησε νέο σύνδεσμο</a></p>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/layout_bottom.php'; ?>

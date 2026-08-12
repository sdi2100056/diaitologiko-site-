<?php
require_once __DIR__ . '/init.php';
require_client_login();
$me = current_client();
$cid = (int)$me['id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    if ($act === 'details') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if ($name === '') { $errors[] = 'Το όνομα είναι υποχρεωτικό.'; }
        if (!$errors) {
            q("UPDATE clients SET name=?, phone=? WHERE id=?", [$name, $phone, $cid]);
            flash_set('ok', 'Τα στοιχεία σου ενημερώθηκαν.');
            redirect('profile.php');
        }
    } elseif ($act === 'password') {
        $cur = $_POST['current'] ?? '';
        $p1 = $_POST['password'] ?? '';
        $p2 = $_POST['password2'] ?? '';
        if (!password_verify($cur, $me['password_hash'])) $errors[] = 'Ο τρέχων κωδικός είναι λάθος.';
        if (strlen($p1) < 8) $errors[] = 'Ο νέος κωδικός πρέπει να έχει τουλάχιστον 8 χαρακτήρες.';
        if ($p1 !== $p2) $errors[] = 'Οι νέοι κωδικοί δεν ταιριάζουν.';
        if (!$errors) {
            q("UPDATE clients SET password_hash=? WHERE id=?", [password_hash($p1, PASSWORD_DEFAULT), $cid]);
            flash_set('ok', 'Ο κωδικός σου άλλαξε.');
            redirect('profile.php');
        }
    } elseif ($act === 'prefs') {
        $nr = isset($_POST['notify_reminders']) ? 1 : 0;
        $nn = isset($_POST['notify_news']) ? 1 : 0;
        q("UPDATE clients SET notify_reminders=?, notify_news=? WHERE id=?", [$nr, $nn, $cid]);
        flash_set('ok', 'Οι προτιμήσεις ειδοποιήσεων αποθηκεύτηκαν.');
        redirect('profile.php');
    }
}

$page_title = 'Το προφίλ μου';
$active = 'profile';
require __DIR__ . '/layout_top.php';
?>
<?php foreach ($errors as $er): ?><div class="flash flash-bad"><?= e($er) ?></div><?php endforeach; ?>

<div class="p-grid2">
  <div class="p-panel">
    <div class="p-panel-head"><h2>Στοιχεία</h2></div>
    <form method="post" class="p-form">
      <?= csrf_field() ?><input type="hidden" name="action" value="details">
      <label>Ονοματεπώνυμο<input type="text" name="name" value="<?= e($me['name']) ?>" required></label>
      <label>Email<input type="email" value="<?= e($me['email']) ?>" disabled></label>
      <label>Τηλέφωνο<input type="text" name="phone" value="<?= e($me['phone']) ?>"></label>
      <button class="btn btn-primary" type="submit">Αποθήκευση</button>
    </form>
  </div>

  <div class="p-panel">
    <div class="p-panel-head"><h2>Αλλαγή κωδικού</h2></div>
    <form method="post" class="p-form">
      <?= csrf_field() ?><input type="hidden" name="action" value="password">
      <label>Τρέχων κωδικός<input type="password" name="current" required></label>
      <label>Νέος κωδικός<input type="password" name="password" required></label>
      <label>Επανάληψη<input type="password" name="password2" required></label>
      <button class="btn btn-primary" type="submit">Αλλαγή κωδικού</button>
    </form>
  </div>

  <div class="p-panel">
    <div class="p-panel-head"><h2>Ειδοποιήσεις</h2></div>
    <form method="post" class="p-form">
      <?= csrf_field() ?><input type="hidden" name="action" value="prefs">
      <label class="check"><input type="checkbox" name="notify_reminders" <?= !isset($me['notify_reminders'])||$me['notify_reminders']?'checked':'' ?>> <span>Υπενθυμίσεις ραντεβού (email 24ω πριν)</span></label>
      <label class="check"><input type="checkbox" name="notify_news" <?= !isset($me['notify_news'])||$me['notify_news']?'checked':'' ?>> <span>Νέα &amp; ενημερώσεις από το γραφείο</span></label>
      <button class="btn btn-primary" type="submit">Αποθήκευση προτιμήσεων</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/layout_bottom.php'; ?>

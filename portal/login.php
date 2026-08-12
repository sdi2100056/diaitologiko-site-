<?php
require_once __DIR__ . '/init.php';
if (is_client_logged_in()) redirect('index.php');

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $locked = login_is_locked('portal', $email);
    if ($locked) {
        $error = "Πάρα πολλές προσπάθειες. Δοκίμασε ξανά σε $locked λεπτά.";
    } else {
        usleep(300000);
        $c = q("SELECT * FROM clients WHERE email=? AND status='active'", [$email])->fetch(PDO::FETCH_ASSOC);
        if ($c && $c['password_hash'] && password_verify($pass, $c['password_hash'])) {
            login_record_success('portal', $email);
            session_regenerate_id(true);
            $_SESSION['client_id'] = (int)$c['id'];
            redirect('index.php');
        } else {
            login_record_fail('portal', $email);
            $error = 'Λάθος email ή κωδικός.';
        }
    }
}
$hide_nav = true;
$page_title = 'Είσοδος';
require __DIR__ . '/layout_top.php';
?>
<div class="auth-wrap">
  <div class="auth-card">
    <h1>Ο λογαριασμός μου</h1>
    <p class="auth-sub">Συνδέσου για να δεις τα ραντεβού και τις αγορές σου.</p>
    <?php if ($error): ?><div class="flash flash-bad"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="auth-form">
      <?= csrf_field() ?>
      <label><span>Email</span><input type="email" name="email" value="<?= e($email) ?>" required autofocus></label>
      <label><span>Κωδικός</span><input type="password" name="password" required></label>
      <button type="submit" class="btn btn-primary btn-block">Είσοδος</button>
    </form>
    <p class="auth-links"><a href="forgot.php">Ξέχασα τον κωδικό μου</a></p>
    <p class="auth-hint">Δεν έχεις λογαριασμό; Θα λάβεις πρόσκληση από το γραφείο με email.</p>
  </div>
</div>
<?php require __DIR__ . '/layout_bottom.php'; ?>

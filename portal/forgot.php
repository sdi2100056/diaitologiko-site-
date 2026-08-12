<?php
require_once __DIR__ . '/init.php';
if (is_client_logged_in()) redirect('index.php');

$done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');
    if ($email) {
        $c = q("SELECT * FROM clients WHERE email=? AND status='active'", [$email])->fetch(PDO::FETCH_ASSOC);
        if ($c) {
            $token = make_token();
            q("UPDATE clients SET reset_token=?, reset_expires=? WHERE id=?",
              [hash_token($token), date('Y-m-d H:i:s', time()+3600), $c['id']]);
            $link = portal_link('reset.php?token=' . $token);
            send_notification_email($c['email'], 'Επαναφορά κωδικού',
                "Γεια σου {$c['name']},\n\nΓια να ορίσεις νέο κωδικό, άνοιξε τον σύνδεσμο (ισχύει για 1 ώρα):\n$link\n\nΑν δεν το ζήτησες εσύ, αγνόησε το μήνυμα.");
        }
    }
    $done = true; // πάντα γενικό μήνυμα
}

$hide_nav = true;
$page_title = 'Επαναφορά κωδικού';
require __DIR__ . '/layout_top.php';
?>
<div class="auth-wrap">
  <div class="auth-card">
    <h1>Επαναφορά κωδικού</h1>
    <?php if ($done): ?>
      <div class="flash flash-ok">Αν υπάρχει λογαριασμός με αυτό το email, στάλθηκε σύνδεσμος επαναφοράς.</div>
      <p class="auth-links"><a href="login.php">← Επιστροφή στην είσοδο</a></p>
    <?php else: ?>
      <p class="auth-sub">Γράψε το email σου και θα σου στείλουμε σύνδεσμο για νέο κωδικό.</p>
      <form method="post" class="auth-form">
        <?= csrf_field() ?>
        <label><span>Email</span><input type="email" name="email" required autofocus></label>
        <button type="submit" class="btn btn-primary btn-block">Αποστολή συνδέσμου</button>
      </form>
      <p class="auth-links"><a href="login.php">← Επιστροφή στην είσοδο</a></p>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/layout_bottom.php'; ?>

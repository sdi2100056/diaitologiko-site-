<?php
require_once __DIR__ . '/init.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = '';
$stage = 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (isset($_POST['code']) && !empty($_SESSION['2fa_pending'])) {
        // ---- Φάση 2: κωδικός 2FA ----
        $secret = setting('admin_totp_secret', '');
        if ($secret && totp_verify($secret, trim($_POST['code']))) {
            session_regenerate_id(true);
            $_SESSION['admin_ok'] = true;
            $_SESSION['admin_user'] = ADMIN_USER;
            unset($_SESSION['2fa_pending']);
            audit('login', 'admin', null, '2FA');
            redirect('index.php');
        } else {
            $error = 'Λάθος κωδικός επαλήθευσης.';
            $stage = '2fa';
        }
    } else {
        // ---- Φάση 1: όνομα χρήστη + κωδικός ----
        $u = trim($_POST['username'] ?? '');
        $p = $_POST['password'] ?? '';
        usleep(300000);
        if (admin_check_credentials($u, $p)) {
            if (setting('admin_2fa_enabled', '') === '1' && setting('admin_totp_secret', '')) {
                $_SESSION['2fa_pending'] = true;
                $stage = '2fa';
            } else {
                session_regenerate_id(true);
                $_SESSION['admin_ok'] = true;
                $_SESSION['admin_user'] = ADMIN_USER;
                audit('login', 'admin');
                redirect('index.php');
            }
        } else {
            $error = 'Λάθος όνομα χρήστη ή κωδικός.';
        }
    }
}
if (!empty($_SESSION['2fa_pending']) && $stage === 'login' && !$error) $stage = '2fa';
?><!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Είσοδος · Διαχείριση</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/admin.css">
</head>
<body class="login-body">
  <div class="login-card">
    <div class="login-brand">
      <span class="brand-mark"></span>
      <span><?= e(ADMIN_BRAND) ?></span>
    </div>
    <h1>Πίνακας Διαχείρισης</h1>

    <?php if ($error): ?>
      <div class="flash flash-bad"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($stage === '2fa'): ?>
      <p class="login-sub">Εισήγαγε τον 6ψήφιο κωδικό από την εφαρμογή authenticator.</p>
      <form method="post" class="login-form" autocomplete="off">
        <?= csrf_field() ?>
        <label>
          <span>Κωδικός επαλήθευσης</span>
          <input type="text" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6" required autofocus placeholder="123456" style="letter-spacing:.3em;text-align:center;font-size:1.2rem">
        </label>
        <button type="submit" class="btn btn-primary btn-block">Επαλήθευση</button>
      </form>
      <p class="login-hint"><a href="login.php">← Πίσω</a></p>
    <?php else: ?>
      <p class="login-sub">Συνδέσου για να διαχειριστείς ραντεβού, πωλήσεις και υπηρεσίες.</p>
      <form method="post" class="login-form" autocomplete="off">
        <?= csrf_field() ?>
        <label>
          <span>Όνομα χρήστη</span>
          <input type="text" name="username" required autofocus>
        </label>
        <label>
          <span>Κωδικός</span>
          <input type="password" name="password" required>
        </label>
        <button type="submit" class="btn btn-primary btn-block">Είσοδος</button>
      </form>
      <p class="login-hint">Προεπιλογή: <code>admin</code> / <code>admin1234</code> — άλλαξέ τα από τις Ρυθμίσεις.</p>
    <?php endif; ?>
  </div>
</body>
</html>

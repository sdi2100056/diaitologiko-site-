<?php
if (!isset($page_title)) $page_title = 'Ο λογαριασμός μου';
if (!isset($active)) $active = '';
$hide_nav = !empty($hide_nav);
$me = $hide_nav ? null : current_client();
$portal_unread = ($me && !$hide_nav) ? client_unread_messages((int)$me['id']) : 0;
$portal_notif = ($me && !$hide_nav) ? client_unread_notifications((int)$me['id']) : 0;
$nav = [
    ['index.php','Πίνακας','home'],
    ['appointments.php','Ραντεβού','appts'],
    ['orders.php','Αγορές','orders'],
    ['progress.php','Πρόοδος','progress'],
    ['plan.php','Διατροφή','plan'],
    ['messages.php','Μηνύματα','messages'],
    ['notifications.php','Ειδοποιήσεις','notifications'],
    ['intake.php','Ιστορικό','intake'],
    ['files.php','Αρχεία','files'],
    ['profile.php','Προφίλ','profile'],
];
?><!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= e($page_title) ?> · <?= e(PORTAL_BRAND) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/portal.css">
</head>
<body class="<?= $hide_nav ? 'is-auth' : '' ?>">
<?php if (!$hide_nav): ?>
<header class="pnav">
  <div class="pnav-in">
    <a class="pbrand" href="index.php"><span class="pbrand-mark"></span><?= e(PORTAL_BRAND) ?></a>
    <button class="pnav-toggle" id="pnavToggle" aria-label="Μενού">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
    </button>
    <nav class="pnav-links" id="pnavLinks">
      <?php foreach ($nav as [$href,$label,$slug]): ?>
        <a href="<?= $href ?>" class="<?= $active===$slug?'is-active':'' ?>"><?= e($label) ?><?php
          if ($slug==='messages' && $portal_unread>0): ?> <span class="pnav-badge"><?= $portal_unread ?></span><?php
          elseif ($slug==='notifications' && $portal_notif>0): ?> <span class="pnav-badge"><?= $portal_notif ?></span><?php endif; ?></a>
      <?php endforeach; ?>
      <span class="pnav-sep"></span>
      <span class="pnav-me"><?= e($me['name'] ?? '') ?></span>
      <a href="logout.php" class="pnav-out">Έξοδος</a>
    </nav>
  </div>
</header>
<?php endif; ?>

<main class="<?= $hide_nav ? 'pauth' : 'pmain' ?>">
  <?php if (!$hide_nav): ?><h1 class="pmain-title"><?= e($page_title) ?></h1><?php endif; ?>
  <?php foreach (flash_all() as $f): ?>
    <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
  <?php endforeach; ?>

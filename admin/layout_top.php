<?php
/**
 * Κοινό layout (κορυφή). Απαιτεί: $page_title, $active (slug ενεργού menu).
 * Πρέπει να έχει προηγηθεί require_login().
 */
if (!isset($active)) $active = '';
if (!isset($page_title)) $page_title = 'Πίνακας Διαχείρισης';

$nav = [
    ['index.php',        'Επισκόπηση',    'dashboard', 'M3 12h4l2 6 4-12 2 6h4'],
    ['appointments.php', 'Ραντεβού',      'appts',     'M8 2v3M16 2v3M3 8h18M5 5h14v16H5z'],
    ['calendar.php',     'Ημερολόγιο',    'calendar',  'M8 2v3M16 2v3M3 8h18M5 5h14v16H5zM8 13h3v3H8z'],
    ['waitlist.php',     'Λίστα αναμονής','waitlist',  'M12 6v6l4 2M12 22a10 10 0 1 1 0-20 10 10 0 0 1 0 20z'],
    ['orders.php',       'Πωλήσεις',      'orders',    'M6 2 3 6v14h18V6l-3-4zM3 6h18M16 10a4 4 0 0 1-8 0'],
    ['clients.php',      'Πελάτες',       'clients',   'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8'],
    ['analytics.php',    'Στατιστικά',    'analytics', 'M3 3v18h18M7 15l4-4 3 3 5-6'],
    ['posts.php',        'Blog',          'posts',     'M4 3h16v18l-4-3-4 3-4-3-4 3zM8 8h8M8 12h8'],
    ['notifications.php','Ειδοποιήσεις',  'notifications','M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0'],
    ['services.php',     'Υπηρεσίες',     'services',  'M12 2 2 7l10 5 10-5zM2 17l10 5 10-5M2 12l10 5 10-5'],
    ['practitioners.php','Θεραπευτές',    'practitioners','M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 7a4 4 0 1 0 0 8 4 4 0 0 0 0-8z'],
    ['gallery.php',      'Ο χώρος μας',   'gallery',   'M3 5h18v14H3zM3 15l5-5 4 4 3-3 6 6'],
    ['availability.php', 'Διαθεσιμότητα', 'avail',     'M12 6v6l4 2M12 22a10 10 0 1 1 0-20 10 10 0 0 1 0 20z'],
    ['settings.php',     'Ρυθμίσεις',     'settings',  'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19 12a7 7 0 0 0-.1-1l2-1.6-2-3.4-2.4 1a7 7 0 0 0-1.7-1L14.5 3h-4l-.3 2.6a7 7 0 0 0-1.7 1l-2.4-1-2 3.4L4 11a7 7 0 0 0 0 2l-2 1.6 2 3.4 2.4-1a7 7 0 0 0 1.7 1l.3 2.4h4l.3-2.6a7 7 0 0 0 1.7-1l2.4 1 2-3.4-2-1.6a7 7 0 0 0 .1-1z'],
];
$unread = function_exists('admin_unread_count') ? admin_unread_count() : 0;
?><!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= e($page_title) ?> · Διαχείριση</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/admin.css?v=3">
</head>
<body>
<div class="admin-shell">
  <aside class="sidebar" id="sidebar">
  
    <nav class="side-nav">
      <?php foreach ($nav as [$href, $label, $slug, $icon]): ?>
        <a href="<?= $href ?>" class="side-link<?= $active === $slug ? ' is-active' : '' ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="<?= $icon ?>"/></svg>
          <span><?= e($label) ?></span>
          <?php if ($slug === 'notifications' && $unread > 0): ?><span class="nav-badge"><?= $unread ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <a href="../index.php" target="_blank" class="side-link ghost">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14 21 3"/></svg>
        <span>Προβολή site</span>
      </a>
      <a href="logout.php" class="side-link ghost">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
        <span>Αποσύνδεση</span>
      </a>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="menu-btn" id="menuBtn" aria-label="Μενού">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
      </button>
      <h1 class="page-title"><?= e($page_title) ?></h1>
      <div class="top-right">
        <span class="today"><?= date('d/m/Y') ?></span>
        <span class="avatar"><?= e(function_exists('mb_substr') ? mb_substr(ADMIN_USER, 0, 1, 'UTF-8') : substr(ADMIN_USER, 0, 1)) ?></span>
      </div>
    </header>

    <main class="content">
      <?php foreach (flash_all() as $f): ?>
        <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
      <?php endforeach; ?>
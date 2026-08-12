<?php
$current = basename($_SERVER['PHP_SELF']);
$meta_description = $meta_description ?? 'Εξατομικευμένη διατροφική καθοδήγηση — online ραντεβού, πακέτα συνεδριών, ψηφιακοί οδηγοί και εργαλεία διατροφής.';
$canonical = $canonical ?? (base_url() . '/' . $current);
$og_title = $og_title ?? ((isset($page_title) ? $page_title . ' — ' : '') . 'Διαιτολογικό Γραφείο');
$og_image = $og_image ?? (base_url() . '/assets/img/og-default.jpg');
$og_type = $og_type ?? 'website';
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($page_title) ? e($page_title) . ' — ' : '' ?>Διαιτολογικό Γραφείο</title>
<meta name="description" content="<?= e($meta_description) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta name="theme-color" content="#0A3B2E">
<link rel="manifest" href="manifest.webmanifest">
<link rel="apple-touch-icon" href="assets/img/icons/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="192x192" href="assets/img/icons/icon-192.png">
<!-- Open Graph -->
<meta property="og:type" content="<?= e($og_type) ?>">
<meta property="og:title" content="<?= e($og_title) ?>">
<meta property="og:description" content="<?= e($meta_description) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($og_image) ?>">
<meta property="og:locale" content="el_GR">
<meta property="og:site_name" content="Διαιτολογικό Γραφείο">
<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($og_title) ?>">
<meta name="twitter:description" content="<?= e($meta_description) ?>">
<meta name="twitter:image" content="<?= e($og_image) ?>">
<?php if (!empty($json_ld)): ?>
<script type="application/ld+json"><?= $json_ld ?></script>
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700;12..96,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: '1' ?>">
<style>
    .nav-links li a {
        white-space: nowrap;
    }
</style>
</head>
<body>

<div class="scroll-progress" id="scrollProgress"></div>

<header class="site-header">
    <nav class="nav">
        <ul class="nav-links">
            <li><a href="index.php" class="<?= $current === 'index.php' ? 'active' : '' ?>">Αρχική</a></li>
            <li><a href="booking.php" class="<?= $current === 'booking.php' ? 'active' : '' ?>">Ραντεβού</a></li>
            <li><a href="services.php" class="<?= $current === 'services.php' ? 'active' : '' ?>">Οι Υπηρεσίες μας</a></li>
            <li><a href="bios.php" class="<?= $current === 'bios.php' ? 'active' : '' ?>">Βιογραφικά</a></li>
            <li><a href="blog.php" class="<?= in_array($current,['blog.php','post.php'],true) ? 'active' : '' ?>">Blog</a></li>
            <li><a href="space.php" class="<?= $current === 'space.php' ? 'active' : '' ?>">Ο χώρος μας</a></li>
            <li><a href="contact.php" class="<?= $current === 'contact.php' ? 'active' : '' ?>">Επικοινωνία</a></li>
            <li><a href="portal/login.php">Ο λογαριασμός μου</a></li>
        </ul>
        <div class="nav-right">
            <a href="booking.php" class="nav-cta">Κλείσε Ραντεβού</a>
            <button type="button" class="nav-toggle" aria-label="Μενού" aria-expanded="false">
                <span></span><span></span><span></span>
            </button>
        </div>
    </nav>
</header>
<script src="assets/js/main.js" defer></script>
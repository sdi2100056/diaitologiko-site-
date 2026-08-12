<?php
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: application/xml; charset=utf-8');
$base = base_url();
$urls = [
    ['loc'=>$base.'/index.php','pri'=>'1.0','freq'=>'weekly'],
    ['loc'=>$base.'/about.php','pri'=>'0.7','freq'=>'monthly'],
    ['loc'=>$base.'/services.php','pri'=>'0.8','freq'=>'monthly'],
    ['loc'=>$base.'/blog.php','pri'=>'0.7','freq'=>'weekly'],
    ['loc'=>$base.'/contact.php','pri'=>'0.6','freq'=>'yearly'],
    ['loc'=>$base.'/booking.php','pri'=>'0.8','freq'=>'monthly'],
    ['loc'=>$base.'/privacy.php','pri'=>'0.3','freq'=>'yearly'],
    ['loc'=>$base.'/terms.php','pri'=>'0.3','freq'=>'yearly'],
];
try {
    foreach (get_db()->query("SELECT slug,updated_at FROM posts WHERE status='published' AND published_at<=NOW() ORDER BY published_at DESC")->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $urls[] = ['loc'=>$base.'/post.php?slug='.rawurlencode($p['slug']),'pri'=>'0.6','freq'=>'monthly','mod'=>$p['updated_at']];
    }
} catch (Throwable $e) {}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo "  <url><loc>" . htmlspecialchars($u['loc'], ENT_XML1) . "</loc>";
    if (!empty($u['mod'])) echo "<lastmod>" . date('Y-m-d', strtotime($u['mod'])) . "</lastmod>";
    echo "<changefreq>{$u['freq']}</changefreq><priority>{$u['pri']}</priority></url>\n";
}
echo '</urlset>';

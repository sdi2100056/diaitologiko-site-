<?php
require_once __DIR__ . '/includes/functions.php';

$slug = trim($_GET['slug'] ?? '');
$post = null;
if ($slug !== '') {
    $s = get_db()->prepare("SELECT * FROM posts WHERE slug=? AND status='published' AND published_at IS NOT NULL AND published_at<=NOW()");
    $s->execute([$slug]);
    $post = $s->fetch(PDO::FETCH_ASSOC);
}
if (!$post) {
    http_response_code(404);
    $page_title = 'Το άρθρο δεν βρέθηκε';
    include __DIR__ . '/includes/header.php';
    echo '<main><section class="container" style="padding:80px 20px;text-align:center"><h1>404 — Το άρθρο δεν βρέθηκε</h1><p><a href="blog.php">← Επιστροφή στο blog</a></p></section></main>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$page_title = $post['title'];
$meta_description = $post['excerpt'] ?: mb_substr(trim(strip_tags($post['body'] ?? '')), 0, 155);
$canonical = base_url() . '/post.php?slug=' . rawurlencode($post['slug']);
$og_type = 'article';
if ($post['image_path']) $og_image = base_url() . '/' . $post['image_path'];

$json_ld = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $post['title'],
    'description' => $meta_description,
    'image' => $post['image_path'] ? base_url() . '/' . $post['image_path'] : null,
    'datePublished' => date('c', strtotime($post['published_at'])),
    'dateModified' => date('c', strtotime($post['updated_at'] ?: $post['published_at'])),
    'author' => ['@type' => 'Organization', 'name' => biz_name()],
    'publisher' => ['@type' => 'Organization', 'name' => biz_name()],
    'mainEntityOfPage' => $canonical,
    'articleSection' => $post['category'] ?: null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

include __DIR__ . '/includes/header.php';
?>
<main>
  <article class="post">
    <div class="container post-head">
      <p class="post-crumb"><a href="blog.php">← Blog</a><?php if ($post['category']): ?> · <a href="blog.php?cat=<?= urlencode($post['category']) ?>"><?= e($post['category']) ?></a><?php endif; ?></p>
      <h1><?= e($post['title']) ?></h1>
      <p class="post-meta"><?= gr_date($post['published_at']) ?></p>
    </div>
    <?php if ($post['image_path']): ?><div class="container"><img class="post-cover" src="<?= e($post['image_path']) ?>" alt="<?= e($post['title']) ?>"></div><?php endif; ?>
    <div class="container post-body">
      <?php
        $body = $post['body'] ?? '';
        // Αν δεν φαίνεται να περιέχει HTML, μετέτρεψε τις αλλαγές γραμμής σε παραγράφους
        if (strip_tags($body) === $body) {
            foreach (preg_split('/\n{2,}/', trim($body)) as $para) {
                if (trim($para) !== '') echo '<p>' . nl2br(e(trim($para))) . '</p>';
            }
        } else {
            echo $body; // ο admin έβαλε HTML εσκεμμένα
        }
      ?>
    </div>
    <div class="container post-cta">
      <a href="booking.php" class="btn btn-primary">Κλείσε ραντεβού</a>
      <a href="blog.php" class="btn btn-ghost">Περισσότερα άρθρα</a>
    </div>
  </article>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>

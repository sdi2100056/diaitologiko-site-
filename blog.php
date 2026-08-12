<?php
require_once __DIR__ . '/includes/functions.php';

$cat = trim($_GET['cat'] ?? '');
$page = max(1, (int)($_GET['p'] ?? 1));
$per = 6; $off = ($page - 1) * $per;

$db = get_db();
$where = "status='published' AND published_at IS NOT NULL AND published_at<=NOW()";
$args = [];
if ($cat !== '') { $where .= " AND category=?"; $args[] = $cat; }

$total = 0;
try {
    $cs = $db->prepare("SELECT COUNT(*) FROM posts WHERE $where"); $cs->execute($args); $total = (int)$cs->fetchColumn();
    $ps = $db->prepare("SELECT * FROM posts WHERE $where ORDER BY published_at DESC LIMIT $per OFFSET $off");
    $ps->execute($args); $posts = $ps->fetchAll(PDO::FETCH_ASSOC);
    $cats = $db->query("SELECT DISTINCT category FROM posts WHERE status='published' AND category IS NOT NULL AND category<>'' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) { $posts = []; $cats = []; }
$pages = max(1, (int)ceil($total / $per));

$page_title = 'Blog' . ($cat ? " — $cat" : '');
$meta_description = 'Άρθρα διατροφής, συμβουλές και συνταγές από το ' . biz_name() . '.';
include __DIR__ . '/includes/header.php';
?>
<main>
  <section class="blog-hero container">
    <span class="eyebrow">Blog</span>
    <h1>Άρθρα &amp; συμβουλές διατροφής</h1>
    <p>Επιστημονικά τεκμηριωμένες συμβουλές, συνταγές και έμπνευση για μια υγιεινή σχέση με το φαγητό.</p>
    <?php if ($cats): ?>
    <div class="blog-cats">
      <label class="blog-cat-select">
        <span>Κατηγορία:</span>
        <select onchange="if(this.value)location.href=this.value">
          <option value="blog.php" <?= $cat===''?'selected':'' ?>>Όλα τα άρθρα</option>
          <?php foreach ($cats as $c): ?>
            <option value="blog.php?cat=<?= urlencode($c) ?>" <?= $cat===$c?'selected':'' ?>><?= e($c) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <?php endif; ?>
  </section>

  <section class="container blog-list">
    <?php if (!$posts): ?>
      <p class="blog-empty">Δεν υπάρχουν δημοσιευμένα άρθρα<?= $cat?' σε αυτή την κατηγορία':'' ?> ακόμη.</p>
    <?php else: ?>
    <div class="blog-grid">
      <?php foreach ($posts as $p): ?>
      <article class="blog-card">
        <a href="post.php?slug=<?= e($p['slug']) ?>" class="blog-card-link">
          <?php if ($p['image_path']): ?><div class="blog-card-img" style="background-image:url('<?= e($p['image_path']) ?>')"></div><?php else: ?><div class="blog-card-img blog-card-img--ph"></div><?php endif; ?>
          <div class="blog-card-body">
            <?php if ($p['category']): ?><span class="blog-card-cat"><?= e($p['category']) ?></span><?php endif; ?>
            <h2><?= e($p['title']) ?></h2>
            <?php if ($p['excerpt']): ?><p><?= e($p['excerpt']) ?></p><?php endif; ?>
            <span class="blog-card-date"><?= gr_date($p['published_at']) ?></span>
          </div>
        </a>
      </article>
      <?php endforeach; ?>
    </div>
    <?php if ($pages > 1): ?>
      <nav class="blog-pager">
        <?php if ($page > 1): $prev = 'p=' . ($page-1) . ($cat ? '&cat='.urlencode($cat) : ''); ?>
          <a class="pager-btn" href="blog.php?<?= $prev ?>">← Προηγούμενη</a>
        <?php else: ?><span class="pager-btn is-disabled">← Προηγούμενη</span><?php endif; ?>
        <span class="pager-info">Σελίδα <?= $page ?> από <?= $pages ?></span>
        <?php if ($page < $pages): $next = 'p=' . ($page+1) . ($cat ? '&cat='.urlencode($cat) : ''); ?>
          <a class="pager-btn" href="blog.php?<?= $next ?>">Επόμενη →</a>
        <?php else: ?><span class="pager-btn is-disabled">Επόμενη →</span><?php endif; ?>
      </nav>
    <?php endif; ?>
    <?php endif; ?>
  </section>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
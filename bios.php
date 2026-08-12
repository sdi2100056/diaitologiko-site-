<?php
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Βιογραφικά';
$meta_description = 'Γνώρισε την ομάδα του ' . biz_name() . ' — εξειδίκευση, σπουδές και φιλοσοφία.';
$team = get_practitioners();
include __DIR__ . '/includes/header.php';
?>
<main>
  <section class="section container">
    <div class="section-head reveal">
      <span class="eyebrow">Η ομάδα μας</span>
      <h1>Βιογραφικά</h1>
      <p class="lede">Οι άνθρωποι που θα σε συνοδεύσουν στη διατροφική σου πορεία.</p>
    </div>

    <?php if (!$team): ?>
      <p class="blog-empty">Τα βιογραφικά θα προστεθούν σύντομα.</p>
    <?php else: ?>
    <div class="team-grid">
      <?php foreach ($team as $p): ?>
        <article class="team-card reveal">
          <div class="team-photo">
            <?php if ($p['photo_path']): ?><img src="<?= e($p['photo_path']) ?>" alt="<?= e($p['name']) ?>">
            <?php else: ?><span class="team-ph"><?= mb_substr($p['name'],0,1) ?></span><?php endif; ?>
          </div>
          <div class="team-info">
            <h2><?= e($p['name']) ?></h2>
            <?php if ($p['title']): ?><p class="team-title"><?= e($p['title']) ?></p><?php endif; ?>
            <?php if ($p['bio']): ?><p class="team-bio"><?= nl2br(e($p['bio'])) ?></p><?php endif; ?>
            <a href="booking.php" class="btn btn-outline btn-sm">Κλείσε ραντεβού</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>

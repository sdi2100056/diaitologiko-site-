<?php
require_once __DIR__ . '/init.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);
    if ($id && $act === 'delete') {
        audit('post_delete','post',$id);
        q("DELETE FROM posts WHERE id=?", [$id]);
        flash_set('ok', 'Το άρθρο διαγράφηκε.');
    } elseif ($id && $act === 'toggle') {
        $st = q("SELECT status FROM posts WHERE id=?", [$id])->fetchColumn();
        if ($st !== false) {
            $new = $st === 'published' ? 'draft' : 'published';
            $pub = $new === 'published' ? 'COALESCE(published_at, NOW())' : 'published_at';
            q("UPDATE posts SET status=?, published_at=$pub WHERE id=?", [$new, $id]);
            audit($new==='published'?'post_publish':'post_unpublish','post',$id);
            flash_set('ok', $new === 'published' ? 'Το άρθρο δημοσιεύτηκε.' : 'Το άρθρο έγινε πρόχειρο.');
        }
    }
    redirect('posts.php');
}

$posts = q("SELECT * FROM posts ORDER BY COALESCE(published_at, created_at) DESC")->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Blog';
$active = 'posts';
require __DIR__ . '/layout_top.php';
?>
<div class="toolbar">
  <div></div>
  <div class="toolbar-actions"><a class="btn btn-primary" href="post-edit.php">+ Νέο άρθρο</a></div>
</div>

<div class="card">
  <?php if ($posts): ?>
  <table class="table"><thead><tr><th>Τίτλος</th><th>Κατηγορία</th><th>Κατάσταση</th><th>Ημ/νία</th><th class="ta-right">Ενέργειες</th></tr></thead><tbody>
    <?php foreach ($posts as $p): ?>
    <tr>
      <td><a class="strong" href="post-edit.php?id=<?= (int)$p['id'] ?>"><?= e($p['title']) ?></a></td>
      <td><?= e($p['category'] ?: '—') ?></td>
      <td><?php if ($p['status']==='published'): ?><span class="badge ok">Δημοσιευμένο</span><?php else: ?><span class="badge muted">Πρόχειρο</span><?php endif; ?></td>
      <td><?= $p['published_at'] ? gr_date($p['published_at']) : gr_date($p['created_at']) ?></td>
      <td class="ta-right actions">
        <?php if ($p['status']==='published'): ?><a class="chip" href="../post.php?slug=<?= e($p['slug']) ?>" target="_blank" title="Προβολή">👁</a><?php endif; ?>
        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button name="action" value="toggle" class="chip" title="Εναλλαγή κατάστασης"><?= $p['status']==='published'?'↧ Πρόχειρο':'↥ Δημοσίευση' ?></button></form>
        <a class="chip" href="post-edit.php?id=<?= (int)$p['id'] ?>" title="Επεξεργασία">✎</a>
        <form method="post" class="inline-form" data-confirm="Διαγραφή άρθρου;"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button name="action" value="delete" class="chip chip-bad" title="Διαγραφή">🗑</button></form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody></table>
  <?php else: ?><p class="empty">Δεν υπάρχουν άρθρα ακόμη. Πάτησε «Νέο άρθρο».</p><?php endif; ?>
</div>
<?php require __DIR__ . '/layout_bottom.php'; ?>

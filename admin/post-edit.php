<?php
require_once __DIR__ . '/init.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$editing = $id > 0;
$P = ['title'=>'', 'slug'=>'', 'excerpt'=>'', 'body'=>'', 'image_path'=>'', 'category'=>'', 'status'=>'draft', 'published_at'=>null];

if ($editing) {
    $row = q("SELECT * FROM posts WHERE id=?", [$id])->fetch(PDO::FETCH_ASSOC);
    if (!$row) { flash_set('bad','Το άρθρο δεν βρέθηκε.'); redirect('posts.php'); }
    $P = $row;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $title = trim($_POST['title'] ?? '');
    $slug  = trim($_POST['slug'] ?? '');
    $excerpt = trim($_POST['excerpt'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';

    if ($title === '') $errors[] = 'Ο τίτλος είναι υποχρεωτικός.';
    if ($slug === '') $slug = slugify($title);
    else $slug = slugify($slug);

    // μοναδικότητα slug
    $dup = q("SELECT id FROM posts WHERE slug=? AND id<>?", [$slug, $id])->fetchColumn();
    if ($dup) $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 4);

    // εικόνα (δημόσια — αποθηκεύεται στο assets/img/blog)
    $image_path = $P['image_path'];
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp'], true) && $_FILES['image']['size'] <= 8*1024*1024) {
            $dir = __DIR__ . '/../assets/img/blog';
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $fname = $slug . '_' . substr(bin2hex(random_bytes(3)),0,6) . '.' . $ext;
            if (@move_uploaded_file($_FILES['image']['tmp_name'], $dir . '/' . $fname)) {
                $image_path = 'assets/img/blog/' . $fname;
            }
        } else { $errors[] = 'Η εικόνα πρέπει να είναι JPG/PNG/WEBP έως 8MB.'; }
    }

    if (!$errors) {
        if ($editing) {
            $pub = $status === 'published' ? 'COALESCE(published_at, NOW())' : 'published_at';
            q("UPDATE posts SET title=?, slug=?, excerpt=?, body=?, image_path=?, category=?, status=?, published_at=$pub WHERE id=?",
              [$title, $slug, $excerpt ?: null, $body ?: null, $image_path ?: null, $category ?: null, $status, $id]);
            audit($status==='published'?'post_publish':'post_unpublish','post',$id);
            flash_set('ok','Το άρθρο αποθηκεύτηκε.');
        } else {
            $pub = $status === 'published' ? date('Y-m-d H:i:s') : null;
            q("INSERT INTO posts (title,slug,excerpt,body,image_path,category,status,published_at) VALUES (?,?,?,?,?,?,?,?)",
              [$title, $slug, $excerpt ?: null, $body ?: null, $image_path ?: null, $category ?: null, $status, $pub]);
            $id = (int)get_db()->lastInsertId();
            flash_set('ok','Το άρθρο δημιουργήθηκε.');
            audit('post_'.($status==='published'?'publish':'create'),'post',$id);
        }
        redirect('post-edit.php?id=' . $id);
    }
    // κράτα τιμές φόρμας σε σφάλμα
    $P = array_merge($P, compact('title','slug','excerpt','body','category','status') + ['image_path'=>$image_path]);
}

$page_title = ($editing ? 'Επεξεργασία' : 'Νέο') . ' άρθρο';
$active = 'posts';
require __DIR__ . '/layout_top.php';
?>
<p class="crumbs"><a href="posts.php">← Πίσω στα άρθρα</a></p>
<?php foreach ($errors as $e): ?><div class="flash flash-bad"><?= e($e) ?></div><?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="plan-editor">
  <?= csrf_field() ?>
  <div class="grid-2">
    <div class="card">
      <div class="card-head"><h2>Περιεχόμενο</h2></div>
      <label class="fld" style="display:block;margin-bottom:12px"><span>Τίτλος *</span><input type="text" name="title" value="<?= e($P['title']) ?>" required></label>
      <label class="fld" style="display:block;margin-bottom:12px"><span>Slug (URL)</span><input type="text" name="slug" value="<?= e($P['slug']) ?>" placeholder="αφήνεται κενό → από τον τίτλο"></label>
      <label class="fld" style="display:block;margin-bottom:12px"><span>Περίληψη (για λίστα & SEO)</span><textarea name="excerpt" rows="2" maxlength="400"><?= e($P['excerpt']) ?></textarea></label>
      <label class="fld" style="display:block"><span>Κείμενο άρθρου</span><textarea name="body" rows="16" placeholder="Μπορείς να χρησιμοποιήσεις απλό κείμενο ή HTML."><?= e($P['body']) ?></textarea></label>
    </div>
    <div class="card">
      <div class="card-head"><h2>Ρυθμίσεις</h2></div>
      <label class="fld" style="display:block;margin-bottom:12px"><span>Κατάσταση</span>
        <select name="status">
          <option value="draft" <?= $P['status']==='draft'?'selected':'' ?>>Πρόχειρο</option>
          <option value="published" <?= $P['status']==='published'?'selected':'' ?>>Δημοσιευμένο</option>
        </select>
      </label>
      <label class="fld" style="display:block;margin-bottom:12px"><span>Κατηγορία</span><input type="text" name="category" value="<?= e($P['category']) ?>" placeholder="π.χ. Διατροφή, Συνταγές"></label>
      <label class="fld" style="display:block;margin-bottom:12px"><span>Εικόνα εξωφύλλου</span><input type="file" name="image" accept="image/*"></label>
      <?php if (!empty($P['image_path'])): ?><img src="../<?= e($P['image_path']) ?>" alt="" style="width:100%;border-radius:10px;border:1px solid var(--line,#E1EAE5)"><?php endif; ?>
      <?php if ($editing && $P['status']==='published'): ?><p class="hint-inline" style="margin-top:12px"><a href="../post.php?slug=<?= e($P['slug']) ?>" target="_blank">Προβολή δημόσιου άρθρου →</a></p><?php endif; ?>
    </div>
  </div>
  <div class="form-actions">
    <a class="btn btn-ghost" href="posts.php">Ακύρωση</a>
    <button class="btn btn-primary" type="submit">Αποθήκευση</button>
  </div>
</form>
<?php require __DIR__ . '/layout_bottom.php'; ?>

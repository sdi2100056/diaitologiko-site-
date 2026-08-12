<?php
require_once __DIR__ . '/init.php';
require_login();

// έλεγχος πίνακα
$has_table = true;
try { get_db()->query("SELECT 1 FROM practitioners LIMIT 1"); } catch (Throwable $e) { $has_table = false; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $has_table) {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($act === 'delete' && $id) {
        q("DELETE FROM practitioners WHERE id=?", [$id]);
        flash_set('ok', 'Ο θεραπευτής διαγράφηκε.');
        redirect('practitioners.php');
    }
    if ($act === 'save') {
        $name = trim($_POST['name'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;
        $sort = (int)($_POST['sort_order'] ?? 0);
        if ($name === '') { flash_set('bad','Το όνομα είναι υποχρεωτικό.'); redirect('practitioners.php' . ($id?"?id=$id":'')); }
        $slug = slugify($_POST['slug'] ?? $name);
        $dup = q("SELECT id FROM practitioners WHERE slug=? AND id<>?", [$slug, $id])->fetchColumn();
        if ($dup) $slug .= '-' . substr(bin2hex(random_bytes(2)),0,3);

        // φωτογραφία (δημόσια)
        $photo = null;
        if ($id) $photo = q("SELECT photo_path FROM practitioners WHERE id=?", [$id])->fetchColumn() ?: null;
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'], true) && $_FILES['photo']['size'] <= 6*1024*1024) {
                $dir = __DIR__ . '/../assets/img/team';
                if (!is_dir($dir)) @mkdir($dir, 0775, true);
                $fname = $slug . '_' . substr(bin2hex(random_bytes(3)),0,6) . '.' . $ext;
                if (@move_uploaded_file($_FILES['photo']['tmp_name'], $dir . '/' . $fname)) $photo = 'assets/img/team/' . $fname;
            }
        }
        if ($id) {
            q("UPDATE practitioners SET name=?, slug=?, title=?, bio=?, photo_path=?, active=?, sort_order=? WHERE id=?",
              [$name,$slug,$title?:null,$bio?:null,$photo,$active,$sort,$id]);
            flash_set('ok','Αποθηκεύτηκε.');
        } else {
            q("INSERT INTO practitioners (name,slug,title,bio,photo_path,active,sort_order) VALUES (?,?,?,?,?,?,?)",
              [$name,$slug,$title?:null,$bio?:null,$photo,$active,$sort]);
            $id = (int)get_db()->lastInsertId();
            flash_set('ok','Δημιουργήθηκε.');
        }
        redirect('practitioners.php?id=' . $id);
    }
}

$editId = (int)($_GET['id'] ?? 0);
$edit = null;
if ($has_table && $editId) $edit = q("SELECT * FROM practitioners WHERE id=?", [$editId])->fetch(PDO::FETCH_ASSOC) ?: null;
$list = $has_table ? q("SELECT * FROM practitioners ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) : [];

$page_title = 'Θεραπευτές';
$active = 'practitioners';
require __DIR__ . '/layout_top.php';
?>
<?php if (!$has_table): ?>
  <div class="card"><div class="flash flash-bad" style="margin:0">Χρειάζεται αναβάθμιση βάσης για τους θεραπευτές.</div><div style="margin-top:14px"><a class="btn btn-primary" href="migrate.php">Αναβάθμιση/έλεγχος βάσης →</a></div></div>
<?php require __DIR__ . '/layout_bottom.php'; return; endif; ?>

<div class="grid-2">
  <div class="card">
    <div class="card-head"><h2>Θεραπευτές</h2><a class="btn btn-primary btn-sm" href="practitioners.php">+ Νέος</a></div>
    <table class="table"><tbody>
    <?php foreach ($list as $p): ?>
      <tr>
        <td style="width:46px"><?php if ($p['photo_path']): ?><img src="../<?= e($p['photo_path']) ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover"><?php else: ?><span class="avatar-ph"><?= mb_substr($p['name'],0,1) ?></span><?php endif; ?></td>
        <td><a href="practitioners.php?id=<?= (int)$p['id'] ?>" class="strong"><?= e($p['name']) ?></a><br><span class="muted"><?= e($p['title'] ?: '') ?></span></td>
        <td><?php if ($p['active']): ?><span class="badge ok">Ενεργός</span><?php else: ?><span class="badge muted">Ανενεργός</span><?php endif; ?></td>
        <td class="ta-right actions"><a class="chip" href="practitioners.php?id=<?= (int)$p['id'] ?>" title="Επεξεργασία">✎</a><form method="post" class="inline-form" data-confirm="Διαγραφή θεραπευτή;"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="chip chip-bad">🗑</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table>
  </div>

  <div class="card">
    <div class="card-head"><h2><?= $edit ? 'Επεξεργασία' : 'Νέος θεραπευτής' ?></h2></div>
    <form method="post" enctype="multipart/form-data" class="plan-editor">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $edit?(int)$edit['id']:0 ?>">
      <label class="fld" style="display:block;margin-bottom:12px"><span>Όνομα *</span><input type="text" name="name" value="<?= e($edit['name'] ?? '') ?>" required></label>
      <label class="fld" style="display:block;margin-bottom:12px"><span>Τίτλος / ειδικότητα</span><input type="text" name="title" value="<?= e($edit['title'] ?? '') ?>" placeholder="π.χ. Κλινική Διαιτολόγος"></label>
      <label class="fld" style="display:block;margin-bottom:12px"><span>Βιογραφικό</span><textarea name="bio" rows="6"><?= e($edit['bio'] ?? '') ?></textarea></label>
      <label class="fld" style="display:block;margin-bottom:12px"><span>Φωτογραφία</span><input type="file" name="photo" accept="image/*"></label>
      <?php if (!empty($edit['photo_path'])): ?><img src="../<?= e($edit['photo_path']) ?>" style="width:90px;height:90px;border-radius:12px;object-fit:cover;margin-bottom:12px"><?php endif; ?>
      <div class="subform-row">
        <label class="fld"><span>Σειρά</span><input type="number" name="sort_order" value="<?= (int)($edit['sort_order'] ?? 0) ?>" class="w-70"></label>
        <label class="check" style="align-self:end"><input type="checkbox" name="active" <?= (!$edit || $edit['active'])?'checked':'' ?>> Ενεργός</label>
      </div>
      <div class="form-actions" style="border:0;padding:0;justify-content:flex-start"><button class="btn btn-primary" type="submit">Αποθήκευση</button></div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/layout_bottom.php'; ?>
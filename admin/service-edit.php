<?php
require_once __DIR__ . '/init.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$editing = $id > 0;
$svc = [
    'name'=>'', 'description'=>'', 'price'=>'', 'type'=>'session_package',
    'sessions_count'=>'', 'file_path'=>'', 'image_path'=>'', 'active'=>1, 'sort_order'=>0, 'duration_min'=>45, 'audience'=>'individual',
];

if ($editing) {
    $row = q("SELECT * FROM services WHERE id=?", [$id])->fetch(PDO::FETCH_ASSOC);
    if (!$row) { flash_set('bad','Η υπηρεσία δεν βρέθηκε.'); redirect('services.php'); }
    $svc = $row;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $svc['name'] = trim($_POST['name'] ?? '');
    $svc['description'] = trim($_POST['description'] ?? '');
    $svc['price'] = str_replace(',', '.', trim($_POST['price'] ?? ''));
    $svc['type'] = $_POST['type'] ?? 'session_package';
    $svc['sessions_count'] = trim($_POST['sessions_count'] ?? '');
    $svc['duration_min'] = (int)($_POST['duration_min'] ?? 45) ?: 45;
    $svc['audience'] = ($_POST['audience'] ?? 'individual')==='corporate' ? 'corporate' : 'individual';
    $svc['file_path'] = trim($_POST['file_path'] ?? '');
    $svc['image_path'] = trim($_POST['image_path'] ?? '');
    $svc['active'] = isset($_POST['active']) ? 1 : 0;
    $svc['sort_order'] = (int)($_POST['sort_order'] ?? 0);

    if ($svc['name']==='') $errors[] = 'Η ονομασία είναι υποχρεωτική.';
    if (!is_numeric($svc['price']) || (float)$svc['price'] < 0) $errors[] = 'Μη έγκυρη τιμή.';
    if (!in_array($svc['type'], ['session_package','ebook'], true)) $svc['type']='session_package';
    $sessions = ($svc['type']==='session_package' && $svc['sessions_count'] !== '') ? (int)$svc['sessions_count'] : null;

    if (!$errors) {
        if ($editing) {
            q("UPDATE services SET name=?, description=?, price=?, type=?, sessions_count=?, duration_min=?, audience=?, file_path=?, image_path=?, active=?, sort_order=? WHERE id=?",
              [$svc['name'],$svc['description'],(float)$svc['price'],$svc['type'],$sessions,(int)$svc['duration_min'],$svc['audience'],$svc['file_path']?:null,$svc['image_path']?:null,$svc['active'],$svc['sort_order'],$id]);
            flash_set('ok','Η υπηρεσία ενημερώθηκε.');
        } else {
            if (!$svc['sort_order']) {
                $svc['sort_order'] = 1 + (int) q("SELECT COALESCE(MAX(sort_order),0) FROM services")->fetchColumn();
            }
            q("INSERT INTO services (name, description, price, type, sessions_count, duration_min, audience, file_path, image_path, active, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
              [$svc['name'],$svc['description'],(float)$svc['price'],$svc['type'],$sessions,(int)$svc['duration_min'],$svc['audience'],$svc['file_path']?:null,$svc['image_path']?:null,$svc['active'],$svc['sort_order']]);
            flash_set('ok','Η υπηρεσία δημιουργήθηκε.');
        }
        redirect('services.php');
    }
}

$page_title = $editing ? 'Επεξεργασία υπηρεσίας' : 'Νέα υπηρεσία';
$active = 'services';
require __DIR__ . '/layout_top.php';
?>
<div class="breadcrumb"><a href="services.php">← Υπηρεσίες</a></div>

<?php if ($errors): ?>
<div class="flash flash-bad"><ul><?php foreach($errors as $er) echo '<li>'.e($er).'</li>'; ?></ul></div>
<?php endif; ?>

<form method="post" class="card form-card">
  <?= csrf_field() ?>
  <div class="form-grid">
    <label class="fld fld-full"><span>Ονομασία *</span>
      <input type="text" name="name" value="<?= e($svc['name']) ?>" required>
    </label>
    <label class="fld fld-full"><span>Περιγραφή</span>
      <textarea name="description" rows="4"><?= e($svc['description']) ?></textarea>
    </label>
    <label class="fld"><span>Τύπος</span>
      <select name="type" id="typeSel">
        <option value="session_package" <?= $svc['type']==='session_package'?'selected':'' ?>>Πακέτο συνεδριών</option>
        <option value="ebook" <?= $svc['type']==='ebook'?'selected':'' ?>>E-book</option>
      </select>
    </label>
    <label class="fld"><span>Τιμή (€) *</span>
      <input type="text" name="price" value="<?= e($svc['price']) ?>" inputmode="decimal" required>
    </label>
    <label class="fld" id="sessionsFld"><span>Αριθμός συνεδριών</span>
      <input type="number" name="sessions_count" value="<?= e($svc['sessions_count']) ?>" min="1">
    </label>
    <label class="fld"><span>Διάρκεια συνεδρίας (λεπτά)</span>
      <input type="number" name="duration_min" value="<?= e($svc['duration_min'] ?? 45) ?>" min="5" step="5">
    </label>
    <label class="fld"><span>Κοινό</span>
      <select name="audience">
        <option value="individual" <?= ($svc['audience']??'individual')==='individual'?'selected':'' ?>>Ιδιώτες</option>
        <option value="corporate" <?= ($svc['audience']??'')==='corporate'?'selected':'' ?>>Εταιρίες</option>
      </select>
    </label>
    <label class="fld" id="fileFld"><span>Path αρχείου e-book</span>
      <input type="text" name="file_path" value="<?= e($svc['file_path']) ?>" placeholder="π.χ. files/odigos.pdf">
    </label>
    <label class="fld"><span>Path εικόνας</span>
      <input type="text" name="image_path" value="<?= e($svc['image_path']) ?>" placeholder="π.χ. assets/img/paketo.jpg">
    </label>
    <label class="fld"><span>Σειρά εμφάνισης</span>
      <input type="number" name="sort_order" value="<?= e($svc['sort_order']) ?>">
    </label>
    <label class="fld checkbox-fld">
      <input type="checkbox" name="active" <?= $svc['active']?'checked':'' ?>>
      <span>Ορατή στο site</span>
    </label>
  </div>
  <div class="form-actions">
    <a href="services.php" class="btn btn-ghost">Άκυρο</a>
    <button type="submit" class="btn btn-primary"><?= $editing ? 'Αποθήκευση' : 'Δημιουργία' ?></button>
  </div>
</form>

<?php
$inline_js = <<<JS
(function(){
  var sel=document.getElementById('typeSel');
  var sess=document.getElementById('sessionsFld');
  var file=document.getElementById('fileFld');
  function upd(){var e=sel.value==='ebook';file.style.display=e?'':'none';sess.style.display=e?'none':'';}
  sel.addEventListener('change',upd);upd();
})();
JS;
require __DIR__ . '/layout_bottom.php';

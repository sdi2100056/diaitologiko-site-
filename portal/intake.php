<?php
require_once __DIR__ . '/init.php';
require_client_login();
$me = current_client();
$cid = (int)$me['id'];

$row = q("SELECT * FROM client_intake WHERE client_id=?", [$cid])->fetch(PDO::FETCH_ASSOC) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $num = fn($k) => ($_POST[$k] ?? '') !== '' ? (float)str_replace(',', '.', $_POST[$k]) : null;
    $str = fn($k) => trim($_POST[$k] ?? '') ?: null;

    $data = [
        'birth_date'          => ($_POST['birth_date'] ?? '') ?: null,
        'height_cm'           => $num('height_cm'),
        'weight_kg'           => $num('weight_kg'),
        'activity_level'      => $str('activity_level'),
        'goals'               => $str('goals'),
        'medical_conditions'  => $str('medical_conditions'),
        'medications'         => $str('medications'),
        'allergies'           => $str('allergies'),
        'dietary_restrictions'=> $str('dietary_restrictions'),
        'smoking'             => $str('smoking'),
        'alcohol'             => $str('alcohol'),
        'notes'               => $str('notes'),
    ];
    $cols = array_keys($data);
    $vals = array_values($data);
    $exists = q("SELECT 1 FROM client_intake WHERE client_id=?", [$cid])->fetchColumn();
    if ($exists) {
        $set = implode(',', array_map(fn($c) => "$c=?", $cols));
        q("UPDATE client_intake SET $set, submitted_at=COALESCE(submitted_at,NOW()) WHERE client_id=?", array_merge($vals, [$cid]));
    } else {
        $collist = implode(',', $cols);
        $ph = implode(',', array_fill(0, count($cols), '?'));
        q("INSERT INTO client_intake (client_id,$collist,submitted_at) VALUES (?,{$ph},NOW())", array_merge([$cid], $vals));
    }
    flash_set('ok', 'Το ιστορικό σου αποθηκεύτηκε. Ευχαριστούμε!');
    redirect('intake.php');
}

$v = fn($k) => e($row[$k] ?? '');
$sel = fn($k, $opt) => (($row[$k] ?? '') === $opt) ? 'selected' : '';

$page_title = 'Ιατρικό ιστορικό';
$active = 'intake';
require __DIR__ . '/layout_top.php';
?>
<?php foreach (flash_all() as $f): ?><div class="p-flash <?= $f['type']==='ok'?'ok':'bad' ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>

<div class="p-panel">
  <div class="p-panel-head"><h2>Το ιστορικό μου</h2>
    <?php if (!empty($row['submitted_at'])): ?><span class="p-muted">Υποβλήθηκε: <?= gr_date($row['submitted_at']) ?></span><?php endif; ?>
  </div>
  <p class="p-muted" style="margin-bottom:18px">Τα στοιχεία αυτά είναι εμπιστευτικά και βοηθούν στη σωστή διατροφική καθοδήγηση. Μπορείς να τα επεξεργαστείς όποτε θέλεις.</p>

  <form method="post" class="p-form intake-form">
    <?= csrf_field() ?>
    <div class="intake-grid">
      <label class="fld"><span>Ημ. γέννησης</span><input type="date" name="birth_date" value="<?= $v('birth_date') ?>"></label>
      <label class="fld"><span>Ύψος (cm)</span><input type="number" step="0.1" name="height_cm" value="<?= $v('height_cm') ?>"></label>
      <label class="fld"><span>Βάρος (kg)</span><input type="number" step="0.1" name="weight_kg" value="<?= $v('weight_kg') ?>"></label>
      <label class="fld"><span>Επίπεδο δραστηριότητας</span>
        <select name="activity_level">
          <option value="">—</option>
          <option value="Καθιστική" <?= $sel('activity_level','Καθιστική') ?>>Καθιστική</option>
          <option value="Ελαφριά" <?= $sel('activity_level','Ελαφριά') ?>>Ελαφριά</option>
          <option value="Μέτρια" <?= $sel('activity_level','Μέτρια') ?>>Μέτρια</option>
          <option value="Έντονη" <?= $sel('activity_level','Έντονη') ?>>Έντονη</option>
        </select>
      </label>
      <label class="fld"><span>Κάπνισμα</span>
        <select name="smoking">
          <option value="">—</option>
          <option value="Όχι" <?= $sel('smoking','Όχι') ?>>Όχι</option>
          <option value="Περιστασιακά" <?= $sel('smoking','Περιστασιακά') ?>>Περιστασιακά</option>
          <option value="Ναι" <?= $sel('smoking','Ναι') ?>>Ναι</option>
        </select>
      </label>
      <label class="fld"><span>Αλκοόλ</span>
        <select name="alcohol">
          <option value="">—</option>
          <option value="Όχι" <?= $sel('alcohol','Όχι') ?>>Όχι</option>
          <option value="Περιστασιακά" <?= $sel('alcohol','Περιστασιακά') ?>>Περιστασιακά</option>
          <option value="Συχνά" <?= $sel('alcohol','Συχνά') ?>>Συχνά</option>
        </select>
      </label>
    </div>

    <label class="fld"><span>Στόχοι</span><textarea name="goals" rows="2" placeholder="π.χ. απώλεια βάρους, βελτίωση διατροφικών συνηθειών…"><?= $v('goals') ?></textarea></label>
    <label class="fld"><span>Παθήσεις / ιατρικές καταστάσεις</span><textarea name="medical_conditions" rows="2"><?= $v('medical_conditions') ?></textarea></label>
    <label class="fld"><span>Φαρμακευτική αγωγή</span><textarea name="medications" rows="2"><?= $v('medications') ?></textarea></label>
    <label class="fld"><span>Αλλεργίες / δυσανεξίες</span><textarea name="allergies" rows="2"><?= $v('allergies') ?></textarea></label>
    <label class="fld"><span>Διατροφικοί περιορισμοί / προτιμήσεις</span><textarea name="dietary_restrictions" rows="2" placeholder="π.χ. χορτοφαγία, νηστεία, τρόφιμα που αποφεύγεις…"><?= $v('dietary_restrictions') ?></textarea></label>
    <label class="fld"><span>Άλλες σημειώσεις</span><textarea name="notes" rows="2"><?= $v('notes') ?></textarea></label>

    <div class="form-actions" style="border:0;padding:0;justify-content:flex-start">
      <button class="btn btn-primary" type="submit">Αποθήκευση</button>
    </div>
  </form>
</div>
<?php require __DIR__ . '/layout_bottom.php'; ?>

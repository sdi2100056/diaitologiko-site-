<?php
require_once __DIR__ . '/init.php';
require_login();

$client_id = (int)($_GET['client'] ?? 0);
$client = q("SELECT * FROM clients WHERE id=?", [$client_id])->fetch(PDO::FETCH_ASSOC);
if (!$client) { flash_set('bad','Ο πελάτης δεν βρέθηκε.'); redirect('clients.php'); }

$plan_id = (int)($_GET['plan'] ?? 0);
$plan = null;
if ($plan_id) {
    $plan = q("SELECT * FROM diet_plans WHERE id=? AND client_id=?", [$plan_id, $client_id])->fetch(PDO::FETCH_ASSOC);
    if (!$plan) { flash_set('bad','Το πλάνο δεν βρέθηκε.'); redirect('client-view.php?id='.$client_id.'#plans'); }
}

$days = diet_days();
$mtypes = diet_meal_types();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $title = trim($_POST['title'] ?? '') ?: 'Διατροφικό πλάνο';
    $start = ($_POST['start_date'] ?? '') ?: null;
    $notes = trim($_POST['notes'] ?? '') ?: null;
    $active = isset($_POST['active']) ? 1 : 0;

    if ($plan_id) {
        q("UPDATE diet_plans SET title=?, start_date=?, notes=?, active=? WHERE id=? AND client_id=?",
          [$title, $start, $notes, $active, $plan_id, $client_id]);
    } else {
        q("INSERT INTO diet_plans (client_id,title,start_date,notes,active) VALUES (?,?,?,?,?)",
          [$client_id, $title, $start, $notes, $active]);
        $plan_id = (int)get_db()->lastInsertId();
    }
    if ($active) {
        q("UPDATE diet_plans SET active=0 WHERE client_id=? AND id<>?", [$client_id, $plan_id]);
    }

    // Γεύματα (upsert· κενά → διαγραφή)
    $mealIn = $_POST['meal'] ?? [];
    foreach (array_keys($mtypes) as $type) {
        for ($d = 0; $d < 7; $d++) {
            $content = trim($mealIn[$d][$type] ?? '');
            if ($content === '') {
                q("DELETE FROM diet_plan_meals WHERE plan_id=? AND day_of_week=? AND meal_type=?", [$plan_id, $d, $type]);
            } else {
                q("INSERT INTO diet_plan_meals (plan_id,day_of_week,meal_type,content) VALUES (?,?,?,?)
                   ON DUPLICATE KEY UPDATE content=VALUES(content)", [$plan_id, $d, $type, $content]);
            }
        }
    }

    // Λίστα αγορών (πλήρης αντικατάσταση)
    q("DELETE FROM diet_plan_items WHERE plan_id=?", [$plan_id]);
    $names = $_POST['item_name'] ?? [];
    $qtys  = $_POST['item_qty'] ?? [];
    $cats  = $_POST['item_cat'] ?? [];
    $ord = 0;
    foreach ($names as $i => $nm) {
        $nm = trim($nm);
        if ($nm === '') continue;
        q("INSERT INTO diet_plan_items (plan_id,name,qty,category,sort_order) VALUES (?,?,?,?,?)",
          [$plan_id, mb_substr($nm,0,150), trim($qtys[$i] ?? '') ?: null, trim($cats[$i] ?? '') ?: null, $ord++]);
    }

    flash_set('ok','Το πλάνο αποθηκεύτηκε.');
    redirect('diet-plan-edit.php?client='.$client_id.'&plan='.$plan_id);
}

// Δεδομένα για φόρμα
$P = $plan ?: ['title'=>'', 'start_date'=>'', 'notes'=>'', 'active'=>1];
$grid = [];
$items = [];
if ($plan_id) {
    foreach (q("SELECT * FROM diet_plan_meals WHERE plan_id=?", [$plan_id])->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $grid[(int)$m['day_of_week']][$m['meal_type']] = $m['content'];
    }
    $items = q("SELECT * FROM diet_plan_items WHERE plan_id=? ORDER BY sort_order ASC, id ASC", [$plan_id])->fetchAll(PDO::FETCH_ASSOC);
}
while (count($items) < 3) $items[] = ['name'=>'','qty'=>'','category'=>''];

$page_title = ($plan_id ? 'Επεξεργασία' : 'Νέο') . ' πλάνο — ' . $client['name'];
$active = 'clients';
require __DIR__ . '/layout_top.php';
?>
<p class="crumbs"><a href="client-view.php?id=<?= $client_id ?>#plans">← Πίσω στην καρτέλα πελάτη</a></p>

<form method="post" class="plan-editor">
  <?= csrf_field() ?>
  <div class="card">
    <div class="card-head"><h2>Στοιχεία πλάνου</h2></div>
    <div class="subform-row" style="flex-wrap:wrap;gap:12px">
      <label class="fld" style="flex:2;min-width:220px"><span>Τίτλος</span><input type="text" name="title" value="<?= e($P['title']) ?>" placeholder="π.χ. Πλάνο απώλειας βάρους — εβδομάδα 1"></label>
      <label class="fld"><span>Ημ. έναρξης</span><input type="date" name="start_date" value="<?= e($P['start_date']) ?>"></label>
      <label class="fld" style="align-self:flex-end"><span>&nbsp;</span><label class="check"><input type="checkbox" name="active" <?= !empty($P['active'])?'checked':'' ?>> Ενεργό (ορατό στον πελάτη)</label></label>
    </div>
    <label class="fld" style="display:block;margin-top:8px"><span>Σημειώσεις</span><textarea name="notes" rows="2" placeholder="Γενικές οδηγίες, ενυδάτωση, κ.λπ."><?= e($P['notes']) ?></textarea></label>
  </div>

  <div class="card">
    <div class="card-head"><h2>Γεύματα ανά ημέρα</h2></div>
    <div class="day-tabs" id="dayTabs">
      <?php foreach ($days as $di=>$dn): ?><button type="button" class="day-tab <?= $di===0?'is-active':'' ?>" data-day="<?= $di ?>"><?= e($dn) ?></button><?php endforeach; ?>
    </div>
    <?php foreach ($days as $di=>$dn): ?>
      <div class="day-panel <?= $di===0?'is-active':'' ?>" data-day="<?= $di ?>">
        <?php foreach ($mtypes as $key=>$label): ?>
          <label class="fld meal-fld"><span><?= e($label) ?></span><textarea name="meal[<?= $di ?>][<?= $key ?>]" rows="2" placeholder="—"><?= e($grid[$di][$key] ?? '') ?></textarea></label>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <div class="card-head"><h2>Λίστα αγορών</h2></div>
    <table class="table" id="shopTable"><thead><tr><th>Προϊόν</th><th>Ποσότητα</th><th>Κατηγορία</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($items as $it): ?>
        <tr class="shop-row">
          <td><input type="text" name="item_name[]" value="<?= e($it['name']) ?>" placeholder="π.χ. Κοτόπουλο"></td>
          <td><input type="text" name="item_qty[]" value="<?= e($it['qty']) ?>" placeholder="π.χ. 500 g"></td>
          <td><input type="text" name="item_cat[]" value="<?= e($it['category']) ?>" placeholder="π.χ. Κρέας"></td>
          <td class="ta-right"><button type="button" class="chip chip-bad row-del">×</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <button type="button" class="btn btn-outline btn-sm" id="addRow">+ Γραμμή</button>
  </div>

  <div class="form-actions">
    <a class="btn btn-ghost" href="client-view.php?id=<?= $client_id ?>#plans">Ακύρωση</a>
    <button class="btn btn-primary" type="submit">Αποθήκευση πλάνου</button>
  </div>
</form>

<?php $inline_js = <<<'JS'
(function(){
  var tabs=document.querySelectorAll('.day-tab'), panels=document.querySelectorAll('.day-panel');
  tabs.forEach(function(t){ t.addEventListener('click',function(){
    var d=this.dataset.day;
    tabs.forEach(x=>x.classList.toggle('is-active',x===this));
    panels.forEach(p=>p.classList.toggle('is-active',p.dataset.day===d));
  });});
  var add=document.getElementById('addRow'), tb=document.querySelector('#shopTable tbody');
  if(add&&tb){ add.addEventListener('click',function(){
    var tr=document.createElement('tr'); tr.className='shop-row';
    tr.innerHTML='<td><input type="text" name="item_name[]" placeholder="Προϊόν"></td>'+
      '<td><input type="text" name="item_qty[]" placeholder="Ποσότητα"></td>'+
      '<td><input type="text" name="item_cat[]" placeholder="Κατηγορία"></td>'+
      '<td class="ta-right"><button type="button" class="chip chip-bad row-del">×</button></td>';
    tb.appendChild(tr);
  });}
  document.addEventListener('click',function(e){ if(e.target.classList.contains('row-del')){ var r=e.target.closest('tr'); if(r) r.remove(); }});
})();
JS;
?>
<?php require __DIR__ . '/layout_bottom.php'; ?>

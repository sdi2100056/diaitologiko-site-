<?php
require_once __DIR__ . '/init.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['action'] ?? '';

    if ($act === 'add_slot') {
        $dow = (int)($_POST['day_of_week'] ?? 1);
        $st  = $_POST['start_time'] ?? '';
        $et  = $_POST['end_time'] ?? '';
        $sm  = max(5, (int)($_POST['slot_minutes'] ?? 45));
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$st) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$et) && $st < $et && $dow>=0 && $dow<=6) {
            q("INSERT INTO availability (day_of_week,start_time,end_time,slot_minutes,active) VALUES (?,?,?,?,1)", [$dow,$st.':00',$et.':00',$sm]);
            flash_set('ok','Προστέθηκε ωράριο διαθεσιμότητας.');
        } else {
            flash_set('bad','Έλεγξε τις ώρες (η έναρξη πρέπει να είναι πριν τη λήξη).');
        }
    } elseif ($act === 'update_slot') {
        $id=(int)$_POST['id']; $st=$_POST['start_time']??''; $et=$_POST['end_time']??''; $sm=max(5,(int)($_POST['slot_minutes']??45));
        if ($id && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$st) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$et) && $st<$et) {
            q("UPDATE availability SET start_time=?, end_time=?, slot_minutes=? WHERE id=?", [$st.':00',$et.':00',$sm,$id]);
            flash_set('ok','Το ωράριο ενημερώθηκε.');
        } else { flash_set('bad','Μη έγκυρες ώρες.'); }
    } elseif ($act === 'toggle_slot') {
        $id=(int)$_POST['id']; if($id){ q("UPDATE availability SET active=1-active WHERE id=?",[$id]); }
    } elseif ($act === 'del_slot') {
        $id=(int)$_POST['id']; if($id){ q("DELETE FROM availability WHERE id=?",[$id]); flash_set('ok','Το ωράριο διαγράφηκε.'); }
    } elseif ($act === 'add_block') {
        $d=$_POST['blocked_date']??''; $r=trim($_POST['reason']??'');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/',$d)) {
            q("INSERT INTO blocked_dates (blocked_date,reason) VALUES (?,?)", [$d, $r ?: null]);
            flash_set('ok','Η ημερομηνία μπλοκαρίστηκε.');
        } else { flash_set('bad','Μη έγκυρη ημερομηνία.'); }
    } elseif ($act === 'del_block') {
        $id=(int)$_POST['id']; if($id){ q("DELETE FROM blocked_dates WHERE id=?",[$id]); flash_set('ok','Η ημερομηνία ξεμπλοκαρίστηκε.'); }
    }
    redirect('availability.php');
}

$slots = q("SELECT * FROM availability ORDER BY day_of_week ASC, start_time ASC")->fetchAll(PDO::FETCH_ASSOC);
$blocks = q("SELECT * FROM blocked_dates WHERE blocked_date >= CURDATE() - INTERVAL 1 DAY ORDER BY blocked_date ASC")->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Διαθεσιμότητα';
$active = 'avail';
require __DIR__ . '/layout_top.php';
?>
<div class="grid-2">
  <div class="card">
    <div class="card-head"><h2>Εβδομαδιαίο ωράριο</h2></div>
    <?php if ($slots): ?>
    <div class="table-scroll">
    <table class="table">
      <thead><tr><th>Ημέρα</th><th>Από</th><th>Έως</th><th>Λεπτά/ραντεβού</th><th>Ενεργό</th><th class="ta-right"></th></tr></thead>
      <tbody>
        <?php foreach ($slots as $sl): $fid = 'sf'.(int)$sl['id']; ?>
        <tr class="<?= $sl['active']?'':'row-off' ?>">
          <td><?= e($GR_DAYS[$sl['day_of_week']]) ?></td>
          <td><input type="time" name="start_time" value="<?= hhmm($sl['start_time']) ?>" form="<?= $fid ?>"></td>
          <td><input type="time" name="end_time" value="<?= hhmm($sl['end_time']) ?>" form="<?= $fid ?>"></td>
          <td><input type="number" class="w-70" name="slot_minutes" value="<?= (int)$sl['slot_minutes'] ?>" min="5" form="<?= $fid ?>"></td>
          <td>
            <button type="submit" name="action" value="toggle_slot" form="<?= $fid ?>" class="switch <?= $sl['active']?'on':'' ?>" title="Εναλλαγή"><span></span></button>
          </td>
          <td class="ta-right actions">
            <button type="submit" name="action" value="update_slot" form="<?= $fid ?>" class="chip chip-ok" title="Αποθήκευση">💾</button>
            <button type="submit" name="action" value="del_slot" form="<?= $fid ?>" class="chip chip-bad" title="Διαγραφή" data-fconfirm="Διαγραφή ωραρίου;">🗑</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php foreach ($slots as $sl): ?>
      <form id="sf<?= (int)$sl['id'] ?>" method="post" hidden><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$sl['id'] ?>"></form>
    <?php endforeach; ?>
    <?php else: ?><p class="empty">Δεν έχει οριστεί ωράριο.</p><?php endif; ?>

    <form method="post" class="subform">
      <?= csrf_field() ?><input type="hidden" name="action" value="add_slot">
      <h3>Προσθήκη ωραρίου</h3>
      <div class="subform-row">
        <select name="day_of_week">
          <?php foreach ($GR_DAYS as $i=>$dn): ?><option value="<?= $i ?>" <?= $i===1?'selected':'' ?>><?= e($dn) ?></option><?php endforeach; ?>
        </select>
        <input type="time" name="start_time" value="09:00" required>
        <input type="time" name="end_time" value="17:00" required>
        <input type="number" name="slot_minutes" value="45" min="5" class="w-70" title="Λεπτά ανά ραντεβού">
        <button class="btn btn-primary" type="submit">Προσθήκη</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><h2>Μπλοκαρισμένες ημέρες</h2></div>
    <?php if ($blocks): ?>
    <table class="table">
      <thead><tr><th>Ημερομηνία</th><th>Αιτία</th><th class="ta-right"></th></tr></thead>
      <tbody>
        <?php foreach ($blocks as $b): ?>
        <tr>
          <td><?= gr_date($b['blocked_date']) ?></td>
          <td class="muted-cell"><?= e($b['reason'] ?: '—') ?></td>
          <td class="ta-right actions">
            <form method="post" class="inline-form" data-confirm="Ξεμπλοκάρισμα ημέρας;"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><button name="action" value="del_block" class="chip chip-bad">🗑</button></form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?><p class="empty">Καμία μπλοκαρισμένη ημέρα.</p><?php endif; ?>

    <form method="post" class="subform">
      <?= csrf_field() ?><input type="hidden" name="action" value="add_block">
      <h3>Μπλοκάρισμα ημέρας (αργία / άδεια)</h3>
      <div class="subform-row">
        <input type="date" name="blocked_date" required>
        <input type="text" name="reason" placeholder="Αιτία (προαιρετικό)">
        <button class="btn btn-primary" type="submit">Μπλοκάρισμα</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/layout_bottom.php'; ?>

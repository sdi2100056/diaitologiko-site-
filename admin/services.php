<?php
require_once __DIR__ . '/init.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);
    if ($id) {
        if ($act === 'toggle') {
            q("UPDATE services SET active = 1 - active WHERE id=?", [$id]);
            flash_set('ok', 'Η κατάσταση της υπηρεσίας άλλαξε.');
        } elseif ($act === 'up' || $act === 'down') {
            $cur = q("SELECT id, sort_order FROM services WHERE id=?", [$id])->fetch(PDO::FETCH_ASSOC);
            if ($cur) {
                $op = $act === 'up' ? '<' : '>';
                $ord = $act === 'up' ? 'DESC' : 'ASC';
                $nb = q("SELECT id, sort_order FROM services WHERE sort_order $op ? ORDER BY sort_order $ord LIMIT 1", [$cur['sort_order']])->fetch(PDO::FETCH_ASSOC);
                if ($nb) {
                    q("UPDATE services SET sort_order=? WHERE id=?", [$nb['sort_order'], $cur['id']]);
                    q("UPDATE services SET sort_order=? WHERE id=?", [$cur['sort_order'], $nb['id']]);
                }
            }
        } elseif ($act === 'delete') {
            try {
                q("DELETE FROM services WHERE id=?", [$id]);
                flash_set('ok', 'Η υπηρεσία διαγράφηκε.');
            } catch (PDOException $ex) {
                flash_set('bad', 'Δεν διαγράφεται: υπάρχουν συνδεδεμένες παραγγελίες. Απενεργοποίησέ την αντ\' αυτού.');
            }
        }
    }
    redirect('services.php');
}

$services = q("SELECT * FROM services ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
$type_lbl = ['session_package'=>'Πακέτο συνεδριών', 'ebook'=>'E-book'];

$page_title = 'Υπηρεσίες';
$active = 'services';
require __DIR__ . '/layout_top.php';
?>
<div class="toolbar">
  <div></div>
  <div class="toolbar-actions">
    <a class="btn btn-primary" href="service-edit.php">+ Νέα υπηρεσία</a>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2><?= count($services) ?> υπηρεσίες</h2><span class="hint-inline">Η σειρά εδώ καθορίζει τη σειρά στο site.</span></div>
  <?php if ($services): ?>
  <div class="table-scroll">
  <table class="table">
    <thead><tr><th>Σειρά</th><th>Ονομασία</th><th>Τύπος</th><th>Συνεδρίες</th><th>Τιμή</th><th>Ορατή</th><th class="ta-right">Ενέργειες</th></tr></thead>
    <tbody>
      <?php foreach ($services as $s): ?>
      <tr class="<?= $s['active'] ? '' : 'row-off' ?>">
        <td class="reorder">
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button name="action" value="up" class="chip" title="Πάνω">▲</button></form>
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button name="action" value="down" class="chip" title="Κάτω">▼</button></form>
        </td>
        <td><a class="strong" href="service-edit.php?id=<?= (int)$s['id'] ?>"><?= e($s['name']) ?></a></td>
        <td><?= e($type_lbl[$s['type']] ?? $s['type']) ?></td>
        <td><?= $s['sessions_count'] !== null ? (int)$s['sessions_count'] : '—' ?></td>
        <td class="mono"><?= eur($s['price']) ?></td>
        <td>
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <button name="action" value="toggle" class="switch <?= $s['active']?'on':'' ?>" title="Εναλλαγή ορατότητας"><span></span></button>
          </form>
        </td>
        <td class="ta-right actions">
          <a class="chip" href="service-edit.php?id=<?= (int)$s['id'] ?>" title="Επεξεργασία">✎</a>
          <form method="post" class="inline-form" data-confirm="Διαγραφή υπηρεσίας;"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button name="action" value="delete" class="chip chip-bad" title="Διαγραφή">🗑</button></form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?><p class="empty">Δεν υπάρχουν υπηρεσίες. Πρόσθεσε την πρώτη.</p><?php endif; ?>
</div>

<?php require __DIR__ . '/layout_bottom.php'; ?>

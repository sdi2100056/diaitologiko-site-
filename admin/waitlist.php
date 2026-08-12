<?php
require_once __DIR__ . '/init.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($id && in_array($act, ['notify','convert','cancel','delete'], true)) {
        if ($act === 'delete') {
            q("DELETE FROM waitlist WHERE id=?", [$id]);
            flash_set('ok', 'Η εγγραφή διαγράφηκε.');
        } elseif ($act === 'notify') {
            $w = q("SELECT * FROM waitlist WHERE id=?", [$id])->fetch(PDO::FETCH_ASSOC);
            if ($w) {
                q("UPDATE waitlist SET status='notified' WHERE id=?", [$id]);
                send_notification_email($w['email'], 'Διαθέσιμη θέση για ραντεβού',
                    "Γεια σου {$w['name']},\n\nΆνοιξε θέση για ραντεβού! Μπες στο site για να κλείσεις: " . site_base_url() . "/booking.php\n\nΜε εκτίμηση,\n" . biz_name());
                flash_set('ok', 'Ο πελάτης ειδοποιήθηκε.');
            }
        } else {
            $map = ['convert'=>'converted', 'cancel'=>'cancelled'];
            q("UPDATE waitlist SET status=? WHERE id=?", [$map[$act], $id]);
            flash_set('ok', 'Η κατάσταση ενημερώθηκε.');
        }
    }
    redirect('waitlist.php');
}

$filter = $_GET['status'] ?? 'waiting';
$valid = ['waiting','notified','converted','cancelled','all'];
if (!in_array($filter, $valid, true)) $filter = 'waiting';
$wsql = $filter === 'all' ? '' : "WHERE status=?";
$args = $filter === 'all' ? [] : [$filter];

$needs_migration = false;
$rows = [];
$counts = [];
try {
    $rows = q("SELECT * FROM waitlist $wsql ORDER BY created_at DESC", $args)->fetchAll(PDO::FETCH_ASSOC);
    foreach (q("SELECT status, COUNT(*) c FROM waitlist GROUP BY status")->fetchAll(PDO::FETCH_ASSOC) as $r) $counts[$r['status']] = $r['c'];
} catch (Throwable $e) {
    $needs_migration = true;
}
$stLbl = ['waiting'=>['Σε αναμονή','warn'],'notified'=>['Ειδοποιήθηκε','ok'],'converted'=>['Έκλεισε','ok'],'cancelled'=>['Ακυρώθηκε','muted']];

$page_title = 'Λίστα αναμονής';
$active = 'waitlist';
require __DIR__ . '/layout_top.php';
?>
<?php if ($needs_migration): ?>
<div class="card">
  <div class="flash flash-bad" style="margin:0">
    Ο πίνακας της λίστας αναμονής δεν υπάρχει ακόμη. Τρέξε την αναβάθμιση της βάσης για να ενεργοποιηθεί.
  </div>
  <div style="margin-top:14px"><a class="btn btn-primary" href="migrate.php">Αναβάθμιση/έλεγχος βάσης →</a></div>
</div>
<?php require __DIR__ . '/layout_bottom.php'; return; ?>
<?php endif; ?>
<div class="filters">
  <?php foreach (['waiting'=>'Σε αναμονή','notified'=>'Ειδοποιημένοι','converted'=>'Έκλεισαν','cancelled'=>'Ακυρωμένοι','all'=>'Όλοι'] as $k=>$lbl): ?>
    <a href="waitlist.php?status=<?= $k ?>" class="filter-chip <?= $filter===$k?'is-active':'' ?>"><?= $lbl ?><?php if ($k!=='all' && !empty($counts[$k])): ?> <span class="fc-count"><?= $counts[$k] ?></span><?php endif; ?></a>
  <?php endforeach; ?>
</div>

<div class="card">
  <?php if ($rows): ?>
  <div class="table-scroll">
  <table class="table"><thead><tr><th>Ημ/νία</th><th>Όνομα</th><th>Επικοινωνία</th><th>Επιθυμητή</th><th>Σημείωση</th><th>Κατάσταση</th><th class="ta-right">Ενέργειες</th></tr></thead><tbody>
    <?php foreach ($rows as $w): [$lbl,$cl]=$stLbl[$w['status']]; ?>
    <tr>
      <td><?= gr_date($w['created_at']) ?></td>
      <td class="strong"><?= e($w['name']) ?></td>
      <td><a href="mailto:<?= e($w['email']) ?>"><?= e($w['email']) ?></a><?php if ($w['phone']): ?><br><span class="muted"><?= e($w['phone']) ?></span><?php endif; ?></td>
      <td><?= $w['requested_date'] ? gr_date($w['requested_date']) : '—' ?></td>
      <td><?= e($w['note'] ?: '—') ?></td>
      <td><span class="badge <?= $cl ?>"><?= $lbl ?></span></td>
      <td class="ta-right actions">
        <?php if ($w['status']==='waiting'): ?>
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$w['id'] ?>"><button name="action" value="notify" class="chip chip-ok" title="Ειδοποίηση ότι άνοιξε θέση">✉ Ειδοποίηση</button></form>
        <?php endif; ?>
        <?php if ($w['status']!=='converted'): ?>
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$w['id'] ?>"><button name="action" value="convert" class="chip" title="Έκλεισε ραντεβού">✔</button></form>
        <?php endif; ?>
        <?php if ($w['status']!=='cancelled'): ?>
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$w['id'] ?>"><button name="action" value="cancel" class="chip chip-warn" title="Ακύρωση">⦸</button></form>
        <?php endif; ?>
        <form method="post" class="inline-form" data-confirm="Διαγραφή εγγραφής;"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$w['id'] ?>"><button name="action" value="delete" class="chip chip-bad" title="Διαγραφή">🗑</button></form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody></table>
  </div>
  <?php else: ?><p class="empty">Καμία εγγραφή σε αυτή την κατηγορία.</p><?php endif; ?>
</div>
<?php require __DIR__ . '/layout_bottom.php'; ?>

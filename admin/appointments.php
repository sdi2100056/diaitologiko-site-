<?php
require_once __DIR__ . '/init.php';
require_login();

// ---- POST ενέργειες ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);
    if ($id && in_array($act, ['confirm','cancel','pending','delete','noshow','unnoshow'], true)) {
        if ($act === 'delete') {
            audit('appointment_delete','appointment',$id);
            q("DELETE FROM appointments WHERE id=?", [$id]);
            flash_set('ok', 'Το ραντεβού διαγράφηκε.');
        } elseif ($act === 'noshow' || $act === 'unnoshow') {
            q("UPDATE appointments SET no_show=? WHERE id=?", [$act==='noshow'?1:0, $id]);
            flash_set('ok', $act==='noshow' ? 'Σημειώθηκε ως μη προσέλευση.' : 'Αναιρέθηκε.');
        } else {
            $map = ['confirm'=>'confirmed','cancel'=>'cancelled','pending'=>'pending'];
            q("UPDATE appointments SET status=? WHERE id=?", [$map[$act], $id]);
            flash_set('ok', 'Η κατάσταση ενημερώθηκε.');
        }
    }
    // διατήρησε τα φίλτρα στο redirect
    redirect('appointments.php?' . http_build_query($_GET));
}

// ---- Φίλτρα ------------------------------------------------------------
$status = $_GET['status'] ?? '';
$from   = $_GET['from'] ?? '';
$to     = $_GET['to'] ?? '';
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['p'] ?? 1));
$per    = 20;

$where = []; $args = [];
if (in_array($status, ['pending','confirmed','cancelled'], true)) { $where[]='status=?'; $args[]=$status; }
if ($from) { $where[]='appointment_date>=?'; $args[]=$from; }
if ($to)   { $where[]='appointment_date<=?'; $args[]=$to; }
if ($search) {
    $where[]='(client_name LIKE ? OR client_email LIKE ? OR client_phone LIKE ?)';
    $like = "%$search%"; array_push($args, $like, $like, $like);
}
$wsql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total = (int) q("SELECT COUNT(*) FROM appointments $wsql", $args)->fetchColumn();
$pages = max(1, (int)ceil($total / $per));
$page  = min($page, $pages);
$offset = ($page - 1) * $per;

$list = q("SELECT a.*, p.name AS practitioner_name FROM appointments a LEFT JOIN practitioners p ON p.id=a.practitioner_id $wsql ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT $per OFFSET $offset", $args)->fetchAll(PDO::FETCH_ASSOC);

$qs = $_GET; unset($qs['p']);
$base_qs = http_build_query($qs);

$page_title = 'Ραντεβού';
$active = 'appts';
require __DIR__ . '/layout_top.php';
?>
<div class="toolbar">
  <form class="filters" method="get">
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="Αναζήτηση ονόματος / email / τηλ.">
    <select name="status">
      <option value="">Όλες οι καταστάσεις</option>
      <option value="pending"   <?= $status==='pending'?'selected':'' ?>>Σε αναμονή</option>
      <option value="confirmed" <?= $status==='confirmed'?'selected':'' ?>>Επιβεβαιωμένα</option>
      <option value="cancelled" <?= $status==='cancelled'?'selected':'' ?>>Ακυρωμένα</option>
    </select>
    <label class="inline">Από <input type="date" name="from" value="<?= e($from) ?>"></label>
    <label class="inline">Έως <input type="date" name="to" value="<?= e($to) ?>"></label>
    <button class="btn btn-outline" type="submit">Φιλτράρισμα</button>
    <?php if ($status||$from||$to||$search): ?><a class="btn btn-ghost" href="appointments.php">Καθαρισμός</a><?php endif; ?>
  </form>
  <div class="toolbar-actions">
    <a class="btn btn-outline" href="export.php?type=appointments&<?= e($base_qs) ?>">Εξαγωγή CSV</a>
    <a class="btn btn-primary" href="appointment-edit.php">+ Νέο ραντεβού</a>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2><?= $total ?> ραντεβού</h2></div>
  <?php if ($list): ?>
  <div class="table-scroll">
  <table class="table">
    <thead><tr><th>Ημ/νία</th><th>Ώρα</th><th>Πελάτης</th><th>Επικοινωνία</th><th>Κατάσταση</th><th class="ta-right">Ενέργειες</th></tr></thead>
    <tbody>
      <?php foreach ($list as $a): [$lbl,$cl]=$GR_STATUS_APPT[$a['status']]; ?>
      <tr>
        <td><?= gr_date($a['appointment_date']) ?></td>
        <td class="mono"><?= hhmm($a['appointment_time']) ?><?php if (($a['mode']??'')==='online'): ?> <span class="badge muted" title="Online">💻</span><?php endif; ?></td>
        <td>
          <a class="strong" href="appointment-edit.php?id=<?= (int)$a['id'] ?>"><?= e($a['client_name']) ?></a>
          <?php if (!empty($a['practitioner_name']) || !empty($a['appt_type'])): ?><br><span class="muted" style="font-size:.8rem"><?php if(!empty($a['practitioner_name'])): ?>👤 <?= e($a['practitioner_name']) ?><?php endif; ?><?php if(($a['appt_type']??'')==='followup'): ?> · επαναληπτικό 30′<?php elseif(!empty($a['appt_type'])): ?> · νέο 60′<?php endif; ?></span><?php endif; ?>
          <?php if ($a['notes']): ?><span class="note-dot" title="<?= e($a['notes']) ?>">✎</span><?php endif; ?>
        </td>
        <td class="muted-cell"><?= e($a['client_email']) ?><br><?= e($a['client_phone']) ?></td>
        <td><span class="badge <?= $cl ?>"><?= $lbl ?></span><?php if (!empty($a['no_show'])): ?> <span class="badge bad" title="Δεν προσήλθε">Μη προσέλευση</span><?php endif; ?></td>
        <td class="ta-right actions">
          <?php $isPast = strtotime($a['appointment_date'].' '.$a['appointment_time']) < time(); ?>
          <?php if ($a['status']!=='confirmed'): ?>
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button name="action" value="confirm" class="chip chip-ok" title="Επιβεβαίωση">✓</button></form>
          <?php endif; ?>
          <?php if ($a['status']!=='cancelled'): ?>
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button name="action" value="cancel" class="chip chip-warn" title="Ακύρωση">⦸</button></form>
          <?php endif; ?>
          <?php if ($isPast && $a['status']!=='cancelled'): ?>
            <?php if (empty($a['no_show'])): ?>
            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button name="action" value="noshow" class="chip" title="Δεν προσήλθε">✗ no-show</button></form>
            <?php else: ?>
            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button name="action" value="unnoshow" class="chip" title="Αναίρεση no-show">↺</button></form>
            <?php endif; ?>
          <?php endif; ?>
          <a class="chip" href="appointment-edit.php?id=<?= (int)$a['id'] ?>" title="Επεξεργασία">✎</a>
          <form method="post" class="inline-form" data-confirm="Οριστική διαγραφή ραντεβού;"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button name="action" value="delete" class="chip chip-bad" title="Διαγραφή">🗑</button></form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
    <p class="empty">Δεν βρέθηκαν ραντεβού με αυτά τα κριτήρια.</p>
  <?php endif; ?>
</div>

<?php if ($pages > 1): ?>
<nav class="pager">
  <?php for ($i=1;$i<=$pages;$i++): ?>
    <a class="page-num<?= $i===$page?' is-active':'' ?>" href="?<?= e($base_qs) ?>&p=<?= $i ?>"><?= $i ?></a>
  <?php endfor; ?>
</nav>
<?php endif; ?>

<?php require __DIR__ . '/layout_bottom.php'; ?>

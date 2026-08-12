<?php
require_once __DIR__ . '/init.php';
require_login();

$ym = $_GET['m'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $ym)) $ym = date('Y-m');
$first = DateTime::createFromFormat('Y-m-d', $ym . '-01');
if (!$first) $first = new DateTime('first day of this month');
$first->modify('first day of this month');

$year = (int)$first->format('Y');
$month = (int)$first->format('n');
$daysIn = (int)$first->format('t');
$startDow = ((int)$first->format('N')) - 1; // 0=Δευτέρα

$prev = (clone $first)->modify('-1 month')->format('Y-m');
$next = (clone $first)->modify('+1 month')->format('Y-m');

// ραντεβού του μήνα
$monthStart = $first->format('Y-m-d');
$monthEnd = (clone $first)->modify('last day of this month')->format('Y-m-d');
$appts = q("SELECT * FROM appointments WHERE appointment_date BETWEEN ? AND ? ORDER BY appointment_time ASC", [$monthStart, $monthEnd])->fetchAll(PDO::FETCH_ASSOC);
$byDay = [];
foreach ($appts as $a) { $byDay[(int)date('j', strtotime($a['appointment_date']))][] = $a; }

$mNames = [1=>'Ιανουάριος','Φεβρουάριος','Μάρτιος','Απρίλιος','Μάιος','Ιούνιος','Ιούλιος','Αύγουστος','Σεπτέμβριος','Οκτώβριος','Νοέμβριος','Δεκέμβριος'];
$dowNames = ['Δε','Τρ','Τε','Πε','Πα','Σα','Κυ'];
$todayJ = (date('Y-m') === $first->format('Y-m')) ? (int)date('j') : -1;
$statusCl = ['pending'=>'warn','confirmed'=>'ok','cancelled'=>'bad'];

$page_title = 'Ημερολόγιο';
$active = 'calendar';
require __DIR__ . '/layout_top.php';
?>
<div class="cal-head">
  <a class="btn btn-outline btn-sm" href="calendar.php?m=<?= $prev ?>">← Προηγ.</a>
  <h2><?= $mNames[$month] ?> <?= $year ?></h2>
  <a class="btn btn-outline btn-sm" href="calendar.php?m=<?= $next ?>">Επόμ. →</a>
  <a class="btn btn-primary btn-sm" href="calendar.php?m=<?= date('Y-m') ?>">Σήμερα</a>
</div>

<div class="cal-grid">
  <?php foreach ($dowNames as $dn): ?><div class="cal-dow"><?= $dn ?></div><?php endforeach; ?>
  <?php for ($i=0; $i<$startDow; $i++): ?><div class="cal-cell cal-empty"></div><?php endfor; ?>
  <?php for ($day=1; $day<=$daysIn; $day++): $ds = sprintf('%04d-%02d-%02d', $year, $month, $day); $items = $byDay[$day] ?? []; ?>
    <div class="cal-cell <?= $day===$todayJ?'is-today':'' ?>">
      <div class="cal-daynum"><?= $day ?><?php if ($items): ?><span class="cal-count"><?= count($items) ?></span><?php endif; ?></div>
      <div class="cal-events">
        <?php foreach (array_slice($items, 0, 4) as $a): $cl=$statusCl[$a['status']]??'muted'; ?>
          <a class="cal-ev cal-ev-<?= $cl ?>" href="appointment-edit.php?id=<?= (int)$a['id'] ?>" title="<?= e($a['client_name']) ?> — <?= hhmm($a['appointment_time']) ?>">
            <span class="cal-ev-t"><?= hhmm($a['appointment_time']) ?></span> <?= e(mb_strimwidth($a['client_name'],0,12,'…')) ?><?php if(($a['mode']??'')==='online'):?> 💻<?php endif; ?>
          </a>
        <?php endforeach; ?>
        <?php if (count($items) > 4): ?><span class="cal-more">+<?= count($items)-4 ?> ακόμη</span><?php endif; ?>
      </div>
    </div>
  <?php endfor; ?>
</div>
<?php require __DIR__ . '/layout_bottom.php'; ?>

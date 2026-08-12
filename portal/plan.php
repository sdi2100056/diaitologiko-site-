<?php
require_once __DIR__ . '/init.php';
require_client_login();
$me = current_client();
$cid = (int)$me['id'];

// AJAX: toggle αντικειμένου λίστας αγορών
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_item') {
    csrf_verify();
    header('Content-Type: application/json; charset=utf-8');
    $iid = (int)($_POST['item_id'] ?? 0);
    // επιβεβαίωση ότι το item ανήκει σε πλάνο του πελάτη
    $ok = q("SELECT i.id FROM diet_plan_items i JOIN diet_plans p ON p.id=i.plan_id WHERE i.id=? AND p.client_id=?", [$iid, $cid])->fetchColumn();
    if (!$ok) { http_response_code(404); echo json_encode(['ok'=>false]); exit; }
    $checked = ($_POST['checked'] ?? '0') === '1' ? 1 : 0;
    q("UPDATE diet_plan_items SET checked=? WHERE id=?", [$checked, $iid]);
    echo json_encode(['ok'=>true, 'checked'=>$checked]); exit;
}

$plan = active_diet_plan($cid);
$days = diet_days();
$mtypes = diet_meal_types();

$grid = []; $items = [];
if ($plan) {
    foreach (q("SELECT * FROM diet_plan_meals WHERE plan_id=?", [$plan['id']])->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $grid[(int)$m['day_of_week']][$m['meal_type']] = $m['content'];
    }
    $items = q("SELECT * FROM diet_plan_items WHERE plan_id=? ORDER BY category IS NULL, category ASC, sort_order ASC, id ASC", [$plan['id']])->fetchAll(PDO::FETCH_ASSOC);
}
// ομαδοποίηση αγορών ανά κατηγορία
$byCat = [];
foreach ($items as $it) { $byCat[$it['category'] ?: 'Άλλα'][] = $it; }

$page_title = 'Το διατροφικό μου πλάνο';
$active = 'plan';
require __DIR__ . '/layout_top.php';
?>
<?php if (!$plan): ?>
  <div class="p-panel"><p class="p-empty">Δεν υπάρχει ενεργό διατροφικό πλάνο ακόμη. Θα εμφανιστεί εδώ μόλις το ετοιμάσει η διαιτολόγος σου.</p></div>
<?php else: ?>
  <div class="p-panel">
    <div class="p-panel-head"><h2><?= e($plan['title']) ?></h2>
      <?php if ($plan['start_date']): ?><span class="p-muted">Έναρξη: <?= gr_date($plan['start_date']) ?></span><?php endif; ?>
    </div>
    <?php if ($plan['notes']): ?><p class="plan-notes"><?= nl2br(e($plan['notes'])) ?></p><?php endif; ?>
  </div>

  <?php $anyMeal = false; foreach ($days as $di => $dname): if (empty($grid[$di])) continue; $anyMeal = true; ?>
    <section class="p-panel plan-day">
      <div class="p-panel-head"><h3><?= e($dname) ?></h3></div>
      <div class="plan-meals">
        <?php foreach ($mtypes as $key => $label): if (empty($grid[$di][$key])) continue; ?>
          <div class="plan-meal">
            <span class="plan-meal-type"><?= e($label) ?></span>
            <span class="plan-meal-content"><?= nl2br(e($grid[$di][$key])) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
  <?php if (!$anyMeal): ?><div class="p-panel"><p class="p-empty">Το πλάνο δεν έχει ακόμη καταχωρημένα γεύματα.</p></div><?php endif; ?>

  <?php if ($items): ?>
  <section class="p-panel" id="shopping">
    <div class="p-panel-head"><h2>🛒 Λίστα αγορών</h2></div>
    <?php foreach ($byCat as $cat => $list): ?>
      <div class="shop-cat">
        <h4><?= e($cat) ?></h4>
        <ul class="shop-list">
          <?php foreach ($list as $it): ?>
            <li>
              <label class="shop-item <?= $it['checked']?'done':'' ?>">
                <input type="checkbox" class="shop-check" data-id="<?= (int)$it['id'] ?>" <?= $it['checked']?'checked':'' ?>>
                <span class="shop-name"><?= e($it['name']) ?></span>
                <?php if ($it['qty']): ?><span class="shop-qty"><?= e($it['qty']) ?></span><?php endif; ?>
              </label>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <?php $inline_js = "window.__csrf=" . json_encode(csrf_token()) . ";"; ?>
<?php endif; ?>
<?php require __DIR__ . '/layout_bottom.php'; ?>

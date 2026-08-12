<?php
require_once __DIR__ . '/init.php';
require_login();

$invite_link = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['action'] ?? '';

    if ($act === 'create') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_set('bad', 'Συμπλήρωσε έγκυρο όνομα και email.');
            redirect('clients.php');
        }
        $exists = q("SELECT id FROM clients WHERE email=?", [$email])->fetchColumn();
        if ($exists) {
            flash_set('bad', 'Υπάρχει ήδη πελάτης με αυτό το email.');
            redirect('clients.php');
        }
        $token = make_token();
        q("INSERT INTO clients (name,email,phone,status,invite_token,invite_expires) VALUES (?,?,?, 'invited', ?, ?)",
          [$name, $email, $phone ?: null, hash_token($token), date('Y-m-d H:i:s', time()+7*86400)]);
        $cid = (int) get_db()->lastInsertId();
        // Σύνδεσε υπάρχοντα ραντεβού/παραγγελίες με το ίδιο email
        try { q("UPDATE appointments SET client_id=? WHERE client_email=? AND client_id IS NULL", [$cid,$email]); } catch (Throwable $e) {}
        try { q("UPDATE orders SET client_id=? WHERE client_email=? AND client_id IS NULL AND client_email<>''", [$cid,$email]); } catch (Throwable $e) {}

        $link = portal_link('activate.php?token=' . $token);
        send_notification_email($email, 'Πρόσκληση στον λογαριασμό σας',
            "Γεια σου $name,\n\nΤο διαιτολογικό γραφείο δημιούργησε λογαριασμό για σένα. Όρισε τον κωδικό σου εδώ (ισχύει 7 ημέρες):\n$link\n\nΜε εκτίμηση.");
        flash_set('ok', 'Ο πελάτης δημιουργήθηκε και στάλθηκε πρόσκληση.');
        audit('client_create','client',(int)get_db()->lastInsertId(),$email);
        // δείξε και τον σύνδεσμο για αντιγραφή
        $_SESSION['last_invite_link'] = $link;
        redirect('clients.php?created=1');
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id && $act === 'toggle') {
        $cur = q("SELECT status FROM clients WHERE id=?", [$id])->fetchColumn();
        $new = ($cur === 'disabled') ? 'active' : 'disabled';
        // μην ενεργοποιείς λογαριασμό που δεν έχει ορίσει κωδικό
        if ($new === 'active') {
            $has = q("SELECT password_hash FROM clients WHERE id=?", [$id])->fetchColumn();
            if (!$has) { flash_set('bad', 'Ο πελάτης δεν έχει ενεργοποιήσει τον λογαριασμό (κωδικό) ακόμη.'); redirect('clients.php'); }
        }
        q("UPDATE clients SET status=? WHERE id=?", [$new, $id]);
        flash_set('ok', 'Η κατάσταση του πελάτη ενημερώθηκε.');
    }
    redirect('clients.php');
}

if (isset($_GET['created']) && !empty($_SESSION['last_invite_link'])) {
    $invite_link = $_SESSION['last_invite_link'];
    unset($_SESSION['last_invite_link']);
}

$search = trim($_GET['q'] ?? '');
$where=''; $args=[];
if ($search) { $where='WHERE c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?'; $l="%$search%"; $args=[$l,$l,$l]; }
$clients = q("SELECT c.*,
    (SELECT COUNT(*) FROM appointments a WHERE a.client_id=c.id) appts,
    (SELECT COUNT(*) FROM orders o WHERE o.client_id=c.id AND o.status='paid') orders
    FROM clients c $where ORDER BY c.created_at DESC", $args)->fetchAll(PDO::FETCH_ASSOC);
$st_lbl = ['invited'=>'Προσκεκλημένος','active'=>'Ενεργός','disabled'=>'Ανενεργός'];

$page_title = 'Πελάτες';
$active = 'clients';
require __DIR__ . '/layout_top.php';
?>
<?php if ($invite_link): ?>
<div class="card">
  <div class="card-head"><h2>Σύνδεσμος πρόσκλησης</h2></div>
  <p class="prose">Στάλθηκε με email. Αν το email δεν είναι ρυθμισμένο, δώσε χειροκίνητα αυτόν τον σύνδεσμο στον πελάτη:</p>
  <div class="copy-link">
    <input type="text" readonly value="<?= e($invite_link) ?>" onclick="this.select()">
    <button class="btn btn-outline" type="button" onclick="navigator.clipboard.writeText('<?= e($invite_link) ?>');this.textContent='Αντιγράφηκε ✓'">Αντιγραφή</button>
  </div>
</div>
<?php endif; ?>

<div class="toolbar">
  <form class="filters" method="get">
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="Αναζήτηση ονόματος / email / τηλ.">
    <button class="btn btn-outline" type="submit">Αναζήτηση</button>
    <?php if ($search): ?><a class="btn btn-ghost" href="clients.php">Καθαρισμός</a><?php endif; ?>
  </form>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head"><h2><?= count($clients) ?> πελάτες</h2></div>
    <?php if ($clients): ?>
    <div class="table-scroll">
    <table class="table">
      <thead><tr><th>Όνομα</th><th>Επικοινωνία</th><th>Ραντεβού</th><th>Αγορές</th><th>Κατάσταση</th><th class="ta-right"></th></tr></thead>
      <tbody>
        <?php foreach ($clients as $c): ?>
        <tr>
          <td><a class="strong" href="client-view.php?id=<?= (int)$c['id'] ?>"><?= e($c['name']) ?></a></td>
          <td class="muted-cell"><?= e($c['email']) ?><?php if($c['phone']):?><br><?= e($c['phone']) ?><?php endif;?></td>
          <td><?= (int)$c['appts'] ?></td>
          <td><?= (int)$c['orders'] ?></td>
          <td><span class="client-status <?= e($c['status']) ?>"><?= e($st_lbl[$c['status']] ?? $c['status']) ?></span></td>
          <td class="ta-right actions">
            <a class="chip" href="client-view.php?id=<?= (int)$c['id'] ?>" title="Καρτέλα">↗</a>
            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <button name="action" value="toggle" class="chip <?= $c['status']==='disabled'?'chip-ok':'chip-warn' ?>" title="<?= $c['status']==='disabled'?'Ενεργοποίηση':'Απενεργοποίηση' ?>"><?= $c['status']==='disabled'?'✓':'⦸' ?></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php else: ?><p class="empty">Δεν υπάρχουν πελάτες ακόμη.</p><?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><h2>Νέος πελάτης</h2></div>
    <p class="hint-inline">Δημιουργείς λογαριασμό και στέλνεται πρόσκληση. Ο πελάτης ορίζει μόνος του κωδικό.</p>
    <form method="post" class="p-form" style="max-width:none;margin-top:12px">
      <?= csrf_field() ?><input type="hidden" name="action" value="create">
      <label class="fld"><span>Ονοματεπώνυμο *</span><input type="text" name="name" required></label>
      <label class="fld"><span>Email *</span><input type="email" name="email" required></label>
      <label class="fld"><span>Τηλέφωνο</span><input type="text" name="phone"></label>
      <div class="form-actions" style="border:0;padding:0;margin-top:8px;justify-content:flex-start">
        <button class="btn btn-primary" type="submit">Δημιουργία & πρόσκληση</button>
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/layout_bottom.php'; ?>

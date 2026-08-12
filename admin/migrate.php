<?php
require_once __DIR__ . '/init.php';
require_login();

$log = [];
$pdo = get_db();

function mig_run(PDO $pdo, $label, $sql, array &$log) {
    try { $pdo->exec($sql); $log[] = ['ok', $label]; }
    catch (PDOException $e) {
        // 42S21 duplicate column, 42000 dup key/constraint, HY000 fk exists → treat as skip
        $log[] = ['skip', $label . ' — ' . $e->getMessage()];
    }
}
function col_exists(PDO $pdo, $table, $col) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $s->execute([$table, $col]);
    return (int)$s->fetchColumn() > 0;
}
function fk_exists(PDO $pdo, $name) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND CONSTRAINT_NAME=? AND CONSTRAINT_TYPE='FOREIGN KEY'");
    $s->execute([$name]);
    return (int)$s->fetchColumn() > 0;
}

$run = ($_SERVER['REQUEST_METHOD'] === 'POST');
if ($run) {
    csrf_verify();

    // 1) Νέοι πίνακες ---------------------------------------------------
    mig_run($pdo, 'Πίνακας clients', "
        CREATE TABLE IF NOT EXISTS clients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(150) NOT NULL,
            phone VARCHAR(30) DEFAULT NULL,
            password_hash VARCHAR(255) DEFAULT NULL,
            status ENUM('invited','active','disabled') NOT NULL DEFAULT 'invited',
            invite_token CHAR(64) DEFAULT NULL,
            invite_expires DATETIME DEFAULT NULL,
            reset_token CHAR(64) DEFAULT NULL,
            reset_expires DATETIME DEFAULT NULL,
            gdpr_consent TINYINT(1) NOT NULL DEFAULT 0,
            gdpr_consent_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $log);

    mig_run($pdo, 'Πίνακας admin_notifications', "
        CREATE TABLE IF NOT EXISTS admin_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(40) NOT NULL,
            client_id INT DEFAULT NULL,
            appointment_id INT DEFAULT NULL,
            message VARCHAR(255) NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY k_read (is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $log);

    mig_run($pdo, 'Πίνακας appointment_requests', "
        CREATE TABLE IF NOT EXISTS appointment_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            appointment_id INT NOT NULL,
            client_id INT NOT NULL,
            requested_date DATE NOT NULL,
            requested_time TIME NOT NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            note VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            decided_at DATETIME DEFAULT NULL,
            KEY k_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $log);

    mig_run($pdo, 'Πίνακας client_measurements', "
        CREATE TABLE IF NOT EXISTS client_measurements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            measured_on DATE NOT NULL,
            weight_kg DECIMAL(5,2) DEFAULT NULL,
            height_cm DECIMAL(5,2) DEFAULT NULL,
            waist_cm DECIMAL(5,2) DEFAULT NULL,
            hip_cm DECIMAL(5,2) DEFAULT NULL,
            body_fat DECIMAL(5,2) DEFAULT NULL,
            notes VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY k_client (client_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $log);

    mig_run($pdo, 'Πίνακας client_files', "
        CREATE TABLE IF NOT EXISTS client_files (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY k_client (client_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $log);

    mig_run($pdo, 'Πίνακας login_attempts', "
        CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            scope VARCHAR(20) NOT NULL,
            identifier VARCHAR(190) NOT NULL,
            ip VARCHAR(45) NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            locked_until DATETIME DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_scope_id_ip (scope, identifier, ip)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $log);

    // 2) Στήλες client_id ---------------------------------------------
    if (!col_exists($pdo, 'appointments', 'client_id')) {
        mig_run($pdo, 'appointments.client_id', "ALTER TABLE appointments ADD COLUMN client_id INT DEFAULT NULL", $log);
    } else { $log[] = ['skip', 'appointments.client_id υπάρχει ήδη']; }

    if (!col_exists($pdo, 'orders', 'client_id')) {
        mig_run($pdo, 'orders.client_id', "ALTER TABLE orders ADD COLUMN client_id INT DEFAULT NULL", $log);
    } else { $log[] = ['skip', 'orders.client_id υπάρχει ήδη']; }

    if (!col_exists($pdo, 'appointments', 'reminder_sent')) {
        mig_run($pdo, 'appointments.reminder_sent', "ALTER TABLE appointments ADD COLUMN reminder_sent TINYINT(1) NOT NULL DEFAULT 0", $log);
    } else { $log[] = ['skip', 'appointments.reminder_sent υπάρχει ήδη']; }

    // ---- Επίπεδο 2: πακέτα, ιστορικό, φωτο, στόχος, περίμετροι ----
    mig_run($pdo, 'Πίνακας client_packages', "
        CREATE TABLE IF NOT EXISTS client_packages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT DEFAULT NULL,
            service_id INT DEFAULT NULL,
            order_id INT DEFAULT NULL,
            title VARCHAR(150) NOT NULL,
            total_sessions INT NOT NULL DEFAULT 0,
            used_sessions INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY k_pkg_client (client_id), KEY k_pkg_order (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $log);

    mig_run($pdo, 'Πίνακας client_intake', "
        CREATE TABLE IF NOT EXISTS client_intake (
            client_id INT PRIMARY KEY,
            birth_date DATE DEFAULT NULL,
            height_cm DECIMAL(5,1) DEFAULT NULL,
            weight_kg DECIMAL(5,1) DEFAULT NULL,
            activity_level VARCHAR(30) DEFAULT NULL,
            goals TEXT, medical_conditions TEXT, medications TEXT,
            allergies TEXT, dietary_restrictions TEXT,
            smoking VARCHAR(20) DEFAULT NULL, alcohol VARCHAR(20) DEFAULT NULL,
            notes TEXT,
            submitted_at TIMESTAMP NULL DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $log);

    mig_run($pdo, 'Πίνακας client_photos', "
        CREATE TABLE IF NOT EXISTS client_photos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            taken_on DATE DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY k_photo_client (client_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $log);

    if (!col_exists($pdo, 'clients', 'target_weight_kg')) {
        mig_run($pdo, 'clients.target_weight_kg', "ALTER TABLE clients ADD COLUMN target_weight_kg DECIMAL(5,1) DEFAULT NULL", $log);
    } else { $log[] = ['skip', 'clients.target_weight_kg υπάρχει ήδη']; }
    foreach (['chest_cm','arm_cm','thigh_cm'] as $c) {
        if (!col_exists($pdo, 'client_measurements', $c)) {
            mig_run($pdo, "client_measurements.$c", "ALTER TABLE client_measurements ADD COLUMN $c DECIMAL(5,1) DEFAULT NULL", $log);
        } else { $log[] = ['skip', "client_measurements.$c υπάρχει ήδη"]; }
    }

    // ---- Επίπεδο 2b: μηνύματα + διατροφικά πλάνα ----
    mig_run($pdo, 'Πίνακας messages', "
        CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            sender ENUM('client','admin') NOT NULL,
            body TEXT NOT NULL,
            read_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY k_msg_client (client_id), KEY k_msg_read (client_id, sender, read_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $log);

    mig_run($pdo, 'Πίνακας diet_plans', "
        CREATE TABLE IF NOT EXISTS diet_plans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            title VARCHAR(150) NOT NULL,
            start_date DATE DEFAULT NULL,
            notes TEXT,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY k_plan_client (client_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $log);

    mig_run($pdo, 'Πίνακας diet_plan_meals', "
        CREATE TABLE IF NOT EXISTS diet_plan_meals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            plan_id INT NOT NULL,
            day_of_week TINYINT NOT NULL,
            meal_type VARCHAR(20) NOT NULL,
            content TEXT,
            UNIQUE KEY u_meal (plan_id, day_of_week, meal_type),
            KEY k_meal_plan (plan_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $log);

    mig_run($pdo, 'Πίνακας diet_plan_items', "
        CREATE TABLE IF NOT EXISTS diet_plan_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            plan_id INT NOT NULL,
            name VARCHAR(150) NOT NULL,
            qty VARCHAR(60) DEFAULT NULL,
            category VARCHAR(60) DEFAULT NULL,
            checked TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT DEFAULT 0,
            KEY k_item_plan (plan_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $log);

    // ---- Επίπεδο 3: no-show tracking ----
    if (!col_exists($pdo, 'appointments', 'no_show')) {
        mig_run($pdo, 'appointments.no_show', "ALTER TABLE appointments ADD COLUMN no_show TINYINT(1) NOT NULL DEFAULT 0", $log);
    } else { $log[] = ['skip', 'appointments.no_show υπάρχει ήδη']; }

    // ---- Επίπεδο 3b: Blog ----
    mig_run($pdo, 'Πίνακας posts', "
        CREATE TABLE IF NOT EXISTS posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            slug VARCHAR(220) NOT NULL UNIQUE,
            excerpt VARCHAR(400) DEFAULT NULL,
            body MEDIUMTEXT,
            image_path VARCHAR(255) DEFAULT NULL,
            category VARCHAR(80) DEFAULT NULL,
            status ENUM('draft','published') NOT NULL DEFAULT 'draft',
            published_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY k_post_status (status, published_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $log);

    // ---- Επίπεδο 3c: ρυθμίσεις επιχείρησης + audit log ----
    mig_run($pdo, 'Πίνακας app_settings', "
        CREATE TABLE IF NOT EXISTS app_settings (
            k VARCHAR(64) PRIMARY KEY,
            v TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $log);

    mig_run($pdo, 'Πίνακας audit_log', "
        CREATE TABLE IF NOT EXISTS audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_user VARCHAR(80) DEFAULT NULL,
            action VARCHAR(80) NOT NULL,
            entity VARCHAR(40) DEFAULT NULL,
            entity_id INT DEFAULT NULL,
            details VARCHAR(255) DEFAULT NULL,
            ip VARCHAR(45) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY k_audit_time (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $log);

    // ---- Επίπεδο 3d: in-app notifications + προτιμήσεις ----
    mig_run($pdo, 'Πίνακας client_notifications', "
        CREATE TABLE IF NOT EXISTS client_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            type VARCHAR(40) NOT NULL,
            message VARCHAR(255) NOT NULL,
            link VARCHAR(120) DEFAULT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY k_cn_client (client_id, is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $log);

    foreach (['notify_reminders','notify_news'] as $col) {
        if (!col_exists($pdo, 'clients', $col)) {
            mig_run($pdo, "clients.$col", "ALTER TABLE clients ADD COLUMN $col TINYINT(1) NOT NULL DEFAULT 1", $log);
        } else { $log[] = ['skip', "clients.$col υπάρχει ήδη"]; }
    }

    // ---- Επίπεδο 4a: booking pro ----
    if (!col_exists($pdo, 'appointments', 'mode')) {
        mig_run($pdo, 'appointments.mode', "ALTER TABLE appointments ADD COLUMN mode ENUM('in_person','online') NOT NULL DEFAULT 'in_person'", $log);
    } else { $log[] = ['skip', 'appointments.mode υπάρχει ήδη']; }
    if (!col_exists($pdo, 'appointments', 'meeting_link')) {
        mig_run($pdo, 'appointments.meeting_link', "ALTER TABLE appointments ADD COLUMN meeting_link VARCHAR(255) DEFAULT NULL", $log);
    } else { $log[] = ['skip', 'appointments.meeting_link υπάρχει ήδη']; }
    if (!col_exists($pdo, 'services', 'duration_min')) {
        mig_run($pdo, 'services.duration_min', "ALTER TABLE services ADD COLUMN duration_min INT DEFAULT 45", $log);
    } else { $log[] = ['skip', 'services.duration_min υπάρχει ήδη']; }

    mig_run($pdo, 'Πίνακας waitlist', "
        CREATE TABLE IF NOT EXISTS waitlist (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT DEFAULT NULL,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(150) NOT NULL,
            phone VARCHAR(30) DEFAULT NULL,
            requested_date DATE DEFAULT NULL,
            note VARCHAR(255) DEFAULT NULL,
            status ENUM('waiting','notified','converted','cancelled') NOT NULL DEFAULT 'waiting',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY k_wait_status (status, requested_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $log);

    // ---- Επίπεδο 4a+: θεραπευτές + τύπος ραντεβού ----
    mig_run($pdo, 'Πίνακας practitioners', "
        CREATE TABLE IF NOT EXISTS practitioners (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(140) NOT NULL UNIQUE,
            title VARCHAR(160) DEFAULT NULL,
            bio TEXT,
            photo_path VARCHAR(255) DEFAULT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $log);

    // seed δύο θεραπευτών αν είναι άδειο
    try {
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM practitioners")->fetchColumn();
        if ($cnt === 0) {
            $pdo->exec("INSERT INTO practitioners (name,slug,title,bio,active,sort_order) VALUES
                ('Άννα','anna','Διαιτολόγος – Διατροφολόγος','[Σύντομο βιογραφικό της Άννας — σπουδές, εξειδίκευση, φιλοσοφία.]',1,1),
                ('Ειρήνη','eirini','Διαιτολόγος – Διατροφολόγος','[Σύντομο βιογραφικό — σπουδές, εξειδίκευση, φιλοσοφία.]',1,2)");
            $log[] = ['ok', 'Προστέθηκαν 2 θεραπευτές (Άννα, Ειρήνη)'];
        }
    } catch (Throwable $e) { $log[] = ['skip', 'Seed practitioners — ' . $e->getMessage()]; }

    if (!col_exists($pdo, 'appointments', 'practitioner_id')) {
        mig_run($pdo, 'appointments.practitioner_id', "ALTER TABLE appointments ADD COLUMN practitioner_id INT DEFAULT NULL", $log);
    } else { $log[] = ['skip', 'appointments.practitioner_id υπάρχει ήδη']; }
    if (!col_exists($pdo, 'appointments', 'appt_type')) {
        mig_run($pdo, 'appointments.appt_type', "ALTER TABLE appointments ADD COLUMN appt_type ENUM('new','followup') NOT NULL DEFAULT 'new'", $log);
    } else { $log[] = ['skip', 'appointments.appt_type υπάρχει ήδη']; }
    if (!col_exists($pdo, 'appointments', 'duration_min')) {
        mig_run($pdo, 'appointments.duration_min', "ALTER TABLE appointments ADD COLUMN duration_min INT NOT NULL DEFAULT 60", $log);
    } else { $log[] = ['skip', 'appointments.duration_min υπάρχει ήδη']; }
    if (!col_exists($pdo, 'availability', 'practitioner_id')) {
        mig_run($pdo, 'availability.practitioner_id', "ALTER TABLE availability ADD COLUMN practitioner_id INT DEFAULT NULL", $log);
    } else { $log[] = ['skip', 'availability.practitioner_id υπάρχει ήδη']; }

    // Αλλαγή unique key: (date,time) → (date,time,practitioner_id) ώστε κάθε θεραπευτής να έχει ανεξάρτητα slots
    $idxExists = function($t,$i) use ($pdo) {
        $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=?");
        $s->execute([$t,$i]); return (int)$s->fetchColumn() > 0;
    };
    try {
        if ($idxExists('appointments','unique_slot')) { $pdo->exec("ALTER TABLE appointments DROP INDEX unique_slot"); $log[]=['ok','Αφαιρέθηκε το παλιό unique_slot']; }
        if (!$idxExists('appointments','uniq_slot_pract')) { $pdo->exec("ALTER TABLE appointments ADD UNIQUE KEY uniq_slot_pract (appointment_date, appointment_time, practitioner_id)"); $log[]=['ok','Νέο unique key ανά θεραπευτή']; }
    } catch (Throwable $e) { $log[] = ['skip', 'Unique key ραντεβού — ' . $e->getMessage()]; }

    // ---- Gallery «Ο χώρος μας» ----
    mig_run($pdo, 'Πίνακας gallery', "
        CREATE TABLE IF NOT EXISTS gallery (
            id INT AUTO_INCREMENT PRIMARY KEY,
            image_path VARCHAR(255) NOT NULL,
            caption VARCHAR(200) DEFAULT NULL,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $log);

    // ---- Κατηγορία υπηρεσίας (ιδιώτες/εταιρίες) ----
    if (!col_exists($pdo, 'services', 'audience')) {
        mig_run($pdo, 'services.audience', "ALTER TABLE services ADD COLUMN audience ENUM('individual','corporate') NOT NULL DEFAULT 'individual'", $log);
    } else { $log[] = ['skip', 'services.audience υπάρχει ήδη']; }

    // 3) Foreign keys (προαιρετικά, αγνόησε αν αποτύχουν) --------------
    if (!fk_exists($pdo, 'fk_appt_client')) {
        mig_run($pdo, 'FK appointments→clients', "ALTER TABLE appointments ADD CONSTRAINT fk_appt_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL", $log);
    }
    if (!fk_exists($pdo, 'fk_order_client')) {
        mig_run($pdo, 'FK orders→clients', "ALTER TABLE orders ADD CONSTRAINT fk_order_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL", $log);
    }

    // 4) Backfill μέσω email ------------------------------------------
    try {
        $n1 = $pdo->exec("UPDATE appointments a JOIN clients c ON c.email=a.client_email SET a.client_id=c.id WHERE a.client_id IS NULL");
        $log[] = ['ok', "Σύνδεση ραντεβού με πελάτες: $n1 εγγραφές"];
    } catch (PDOException $e) { $log[] = ['skip', 'Backfill ραντεβού — ' . $e->getMessage()]; }
    try {
        $n2 = $pdo->exec("UPDATE orders o JOIN clients c ON c.email=o.client_email SET o.client_id=c.id WHERE o.client_id IS NULL AND o.client_email<>''");
        $log[] = ['ok', "Σύνδεση παραγγελιών με πελάτες: $n2 εγγραφές"];
    } catch (PDOException $e) { $log[] = ['skip', 'Backfill παραγγελιών — ' . $e->getMessage()]; }

    // 5) Δημιουργία πακέτων από πληρωμένες παραγγελίες συνεδριών ----------
    try {
        $n3 = $pdo->exec("
            INSERT INTO client_packages (client_id, service_id, order_id, title, total_sessions, used_sessions)
            SELECT COALESCE(o.client_id,(SELECT id FROM clients c WHERE c.email=o.client_email LIMIT 1)),
                   o.service_id, o.id, s.name, s.sessions_count, 0
            FROM orders o JOIN services s ON s.id=o.service_id
            WHERE o.status='paid' AND s.type='session_package' AND s.sessions_count>0
              AND NOT EXISTS (SELECT 1 FROM client_packages cp WHERE cp.order_id=o.id)");
        $log[] = ['ok', "Δημιουργία πακέτων συνεδριών: $n3 εγγραφές"];
    } catch (PDOException $e) { $log[] = ['skip', 'Πακέτα συνεδριών — ' . $e->getMessage()]; }

    flash_set('ok', 'Η αναβάθμιση της βάσης ολοκληρώθηκε.');
}

// Κατάσταση (τι υπάρχει ήδη)
$have = [];
foreach (['clients','admin_notifications','appointment_requests','client_measurements','client_files','login_attempts'] as $t) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $s->execute([$t]); $have[$t] = (int)$s->fetchColumn() > 0;
}
$have_appt_col = col_exists($pdo, 'appointments', 'client_id');
$have_order_col = col_exists($pdo, 'orders', 'client_id');
$all_ready = !in_array(false, $have, true) && $have_appt_col && $have_order_col;

$page_title = 'Αναβάθμιση βάσης';
$active = 'settings';
require __DIR__ . '/layout_top.php';
?>
<div class="breadcrumb"><a href="settings.php">← Ρυθμίσεις</a></div>

<div class="card">
  <div class="card-head"><h2>Αναβάθμιση για την Πύλη Πελατών</h2></div>
  <p class="prose">Αυτό δημιουργεί τους νέους πίνακες (πελάτες, ειδοποιήσεις, αιτήματα, μετρήσεις, αρχεία) και συνδέει τα υπάρχοντα ραντεβού/παραγγελίες με πελάτες μέσω email. Είναι ασφαλές να εκτελεστεί ξανά — δεν χαλάει υπάρχοντα δεδομένα.</p>

  <ul class="status-list" style="margin:14px 0">
    <?php foreach ($have as $t=>$ok): ?>
      <li><span>Πίνακας <code><?= e($t) ?></code></span><strong class="<?= $ok?'txt-ok':'txt-bad' ?>"><?= $ok?'OK ✓':'λείπει' ?></strong></li>
    <?php endforeach; ?>
    <li><span><code>appointments.client_id</code></span><strong class="<?= $have_appt_col?'txt-ok':'txt-bad' ?>"><?= $have_appt_col?'OK ✓':'λείπει' ?></strong></li>
    <li><span><code>orders.client_id</code></span><strong class="<?= $have_order_col?'txt-ok':'txt-bad' ?>"><?= $have_order_col?'OK ✓':'λείπει' ?></strong></li>
  </ul>

  <form method="post">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-primary"><?= $all_ready ? 'Επανεκτέλεση αναβάθμισης' : 'Εκτέλεση αναβάθμισης' ?></button>
    <?php if ($all_ready): ?><span class="hint-inline" style="margin-left:10px">Όλα έτοιμα — η πύλη πελατών μπορεί να χρησιμοποιηθεί.</span><?php endif; ?>
  </form>
</div>

<?php if ($run && $log): ?>
<div class="card">
  <div class="card-head"><h2>Αποτέλεσμα</h2></div>
  <ul class="prose bullet" style="padding-left:18px">
    <?php foreach ($log as [$st,$msg]): ?>
      <li><strong class="<?= $st==='ok'?'txt-ok':'txt-bad' ?>"><?= $st==='ok'?'✓':'•' ?></strong> <?= e($msg) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<?php require __DIR__ . '/layout_bottom.php'; ?>

<?php
require_once __DIR__ . '/db.php';

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function get_active_services() {
    $pdo = get_db();
    $stmt = $pdo->query("SELECT * FROM services WHERE active = 1 ORDER BY sort_order ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_service($id) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ? AND active = 1");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_weekly_availability() {
    $pdo = get_db();
    $stmt = $pdo->query("SELECT * FROM availability WHERE active = 1");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_booked_slots($date) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT appointment_time FROM appointments WHERE appointment_date = ? AND status != 'cancelled'");
    $stmt->execute([$date]);
    return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'appointment_time');
}

function is_date_blocked($date) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM blocked_dates WHERE blocked_date = ?");
    $stmt->execute([$date]);
    return $stmt->fetchColumn() > 0;
}

function send_notification_email($to, $subject, $body, $opts = []) {
    require_once __DIR__ . '/mailer.php';
    $html = mail_brand_html($subject, $body);
    return send_mail($to, $subject, $html, array_merge(['html' => true, 'alt' => $body], $opts));
}

/* ---- Επίπεδο 2: πακέτα συνεδριών ---- */

/** Σύνοψη συνεδριών πελάτη: total, used, remaining. */
function get_package_summary($client_id) {
    if (!$client_id) return ['total'=>0,'used'=>0,'remaining'=>0,'has'=>false];
    try {
        $s = get_db()->prepare("SELECT COALESCE(SUM(total_sessions),0) t, COALESCE(SUM(used_sessions),0) u FROM client_packages WHERE client_id=?");
        $s->execute([$client_id]);
        $x = $s->fetch(PDO::FETCH_ASSOC);
        $t = (int)$x['t']; $u = (int)$x['u'];
        return ['total'=>$t,'used'=>$u,'remaining'=>max(0,$t-$u),'has'=>$t>0];
    } catch (Throwable $e) { return ['total'=>0,'used'=>0,'remaining'=>0,'has'=>false]; }
}

/** Δημιουργεί πακέτο συνεδριών από πληρωμένη παραγγελία (idempotent ανά order_id). */
function create_package_from_order(PDO $pdo, array $order) {
    if (empty($order['service_id']) || empty($order['id'])) return false;
    $svc = get_service($order['service_id']);
    if (!$svc || ($svc['type'] ?? '') !== 'session_package') return false;
    $n = (int)($svc['sessions_count'] ?? 0);
    if ($n <= 0) return false;
    try {
        $chk = $pdo->prepare("SELECT id FROM client_packages WHERE order_id=?");
        $chk->execute([$order['id']]);
        if ($chk->fetch()) return false;
        $cid = $order['client_id'] ?? null;
        if (!$cid && !empty($order['client_email'])) {
            $c = $pdo->prepare("SELECT id FROM clients WHERE email=?");
            $c->execute([$order['client_email']]);
            $cid = $c->fetchColumn() ?: null;
        }
        $pdo->prepare("INSERT INTO client_packages (client_id,service_id,order_id,title,total_sessions,used_sessions) VALUES (?,?,?,?,?,0)")
            ->execute([$cid, $order['service_id'], $order['id'], $svc['name'], $n]);
        return true;
    } catch (Throwable $e) { error_log('create_package_from_order: '.$e->getMessage()); return false; }
}

/* ---- Επίπεδο 2b: μηνύματα & διατροφικά πλάνα ---- */

/** Ετικέτες ημερών (0=Δευτέρα..6=Κυριακή) και τύπων γεύματος. */
function diet_days() { return ['Δευτέρα','Τρίτη','Τετάρτη','Πέμπτη','Παρασκευή','Σάββατο','Κυριακή']; }
function diet_meal_types() {
    return ['breakfast'=>'Πρωινό','snack1'=>'Δεκατιανό','lunch'=>'Μεσημεριανό','snack2'=>'Απογευματινό','dinner'=>'Βραδινό'];
}

/** Μη αναγνωσμένα μηνύματα προς τον πελάτη (από το γραφείο). */
function client_unread_messages($client_id) {
    if (!$client_id) return 0;
    try {
        $s = get_db()->prepare("SELECT COUNT(*) FROM messages WHERE client_id=? AND sender='admin' AND read_at IS NULL");
        $s->execute([$client_id]); return (int)$s->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

/** Μη αναγνωσμένα μηνύματα προς το γραφείο (από πελάτες) — σύνολο. */
function admin_unread_messages() {
    try {
        return (int) get_db()->query("SELECT COUNT(*) FROM messages WHERE sender='client' AND read_at IS NULL")->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

/** Ενεργό διατροφικό πλάνο πελάτη (ή null). */
function active_diet_plan($client_id) {
    if (!$client_id) return null;
    try {
        $s = get_db()->prepare("SELECT * FROM diet_plans WHERE client_id=? AND active=1 ORDER BY created_at DESC LIMIT 1");
        $s->execute([$client_id]); return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) { return null; }
}

/* ---- SEO / URL helpers ---- */
function base_url() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $dir = rtrim($dir, '/');
    return $scheme . '://' . $host . $dir;
}
function current_url() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
}
/** Δημιουργία slug από τίτλο (υποστηρίζει ελληνικά → λατινικά). */
function slugify($text) {
    $text = trim(mb_strtolower($text, 'UTF-8'));
    $gr = ['α','ά','β','γ','δ','ε','έ','ζ','η','ή','θ','ι','ί','ϊ','ΐ','κ','λ','μ','ν','ξ','ο','ό','π','ρ','σ','ς','τ','υ','ύ','ϋ','ΰ','φ','χ','ψ','ω','ώ'];
    $en = ['a','a','v','g','d','e','e','z','i','i','th','i','i','i','i','k','l','m','n','x','o','o','p','r','s','s','t','y','y','y','y','f','ch','ps','o','o'];
    $text = str_replace($gr, $en, $text);
    $text = preg_replace('/[^a-z0-9]+/u', '-', $text);
    $text = trim($text, '-');
    return $text ?: 'post';
}

/* ---- Επίπεδο 3c/3d: settings, audit, TOTP, notifications ---- */

/** Ρυθμίσεις επιχείρησης (key/value) με cache + fallback. */
function setting($key, $default = '') {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try { foreach (get_db()->query("SELECT k,v FROM app_settings")->fetchAll(PDO::FETCH_ASSOC) as $r) $cache[$r['k']] = $r['v']; }
        catch (Throwable $e) { $cache = []; }
    }
    return array_key_exists($key, $cache) && $cache[$key] !== '' && $cache[$key] !== null ? $cache[$key] : $default;
}
function set_setting($key, $value) {
    q("INSERT INTO app_settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)", [$key, $value]);
}
/** Όνομα επιχείρησης (χρησιμοποιείται σε τίτλους/emails/JSON-LD). */
function biz_name() { return setting('business_name', 'Διαιτολογικό Γραφείο'); }

/** Καταγραφή ενέργειας admin. */
function audit($action, $entity = null, $entity_id = null, $details = null) {
    try {
        $user = $_SESSION['admin_user'] ?? ($_SESSION['is_admin'] ?? false ? 'admin' : null);
        get_db()->prepare("INSERT INTO audit_log (admin_user,action,entity,entity_id,details,ip) VALUES (?,?,?,?,?,?)")
            ->execute([$user, $action, $entity, $entity_id, $details ? mb_substr($details,0,255) : null, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $e) { /* μη κρίσιμο */ }
}

/** In-app ειδοποίηση πελάτη. */
function add_client_notification($client_id, $type, $message, $link = null) {
    if (!$client_id) return;
    try {
        get_db()->prepare("INSERT INTO client_notifications (client_id,type,message,link) VALUES (?,?,?,?)")
            ->execute([$client_id, $type, mb_substr($message,0,255), $link]);
    } catch (Throwable $e) {}
}
function client_unread_notifications($client_id) {
    if (!$client_id) return 0;
    try {
        $s = get_db()->prepare("SELECT COUNT(*) FROM client_notifications WHERE client_id=? AND is_read=0");
        $s->execute([$client_id]); return (int)$s->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

/* ---- TOTP (RFC 6238) για 2FA admin ---- */
function base32_decode($b32) {
    $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
    $bits = ''; $out = '';
    for ($i = 0; $i < strlen($b32); $i++) $bits .= str_pad(decbin(strpos($map, $b32[$i])), 5, '0', STR_PAD_LEFT);
    for ($i = 0; $i + 8 <= strlen($bits); $i += 8) $out .= chr(bindec(substr($bits, $i, 8)));
    return $out;
}
function base32_encode($data) {
    $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = ''; $out = '';
    for ($i = 0; $i < strlen($data); $i++) $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
    for ($i = 0; $i < strlen($bits); $i += 5) { $chunk = substr($bits, $i, 5); $out .= $map[bindec(str_pad($chunk, 5, '0'))]; }
    return $out;
}
function totp_secret() { return base32_encode(random_bytes(20)); }
function totp_code($secret, $timeSlice = null) {
    if ($timeSlice === null) $timeSlice = floor(time() / 30);
    $key = base32_decode($secret);
    $bin = pack('N*', 0) . pack('N*', $timeSlice);
    $hash = hash_hmac('sha1', $bin, $key, true);
    $offset = ord($hash[19]) & 0xf;
    $val = ((ord($hash[$offset]) & 0x7f) << 24) | ((ord($hash[$offset+1]) & 0xff) << 16)
         | ((ord($hash[$offset+2]) & 0xff) << 8) | (ord($hash[$offset+3]) & 0xff);
    return str_pad($val % 1000000, 6, '0', STR_PAD_LEFT);
}
function totp_verify($secret, $code, $window = 1) {
    if (!preg_match('/^\d{6}$/', $code)) return false;
    $cur = floor(time() / 30);
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totp_code($secret, $cur + $i), $code)) return true;
    }
    return false;
}

/* ---- Κοινοί formatters (διαθέσιμοι παντού: public/admin/portal) ---- */
if (!function_exists('eur')) {
    function eur($n) { return number_format((float)$n, 2, ',', '.') . ' €'; }
}
if (!function_exists('gr_date')) {
    function gr_date($d) { if (!$d) return '—'; $ts = strtotime($d); return $ts ? date('d/m/Y', $ts) : e($d); }
}
if (!function_exists('gr_datetime')) {
    function gr_datetime($d) { if (!$d) return '—'; $ts = strtotime($d); return $ts ? date('d/m/Y H:i', $ts) : e($d); }
}
if (!function_exists('hhmm')) {
    function hhmm($t) { return $t ? substr($t, 0, 5) : '—'; }
}

/** Ειδοποίηση προς admin (διαθέσιμη και σε δημόσιες σελίδες). */
if (!function_exists('notify_admin')) {
    function notify_admin($type, $client_id, $appointment_id, $message) {
        try {
            get_db()->prepare("INSERT INTO admin_notifications (type,client_id,appointment_id,message) VALUES (?,?,?,?)")
                ->execute([$type, $client_id, $appointment_id, $message]);
        } catch (Throwable $e) {}
    }
}

/* ---- Θεραπευτές & τύπος ραντεβού ---- */
function get_practitioners($active_only = true) {
    try {
        $sql = "SELECT * FROM practitioners" . ($active_only ? " WHERE active=1" : "") . " ORDER BY sort_order ASC, id ASC";
        return get_db()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { return []; }
}
function get_practitioner($id_or_slug) {
    try {
        $col = ctype_digit((string)$id_or_slug) ? 'id' : 'slug';
        $s = get_db()->prepare("SELECT * FROM practitioners WHERE $col=?");
        $s->execute([$id_or_slug]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) { return null; }
}
/** Διάρκεια σε λεπτά ανά τύπο ραντεβού. */
function appt_duration($type) { return $type === 'followup' ? 30 : 60; }
function appt_type_label($type) { return $type === 'followup' ? 'Επαναληπτικό (30′)' : 'Νέο ραντεβού (60′)'; }

<?php
/**
 * Portal bootstrap — client authentication & helpers.
 * Εντελώς ξεχωριστό session/auth από το admin.
 */
require_once __DIR__ . '/../includes/functions.php'; // get_db(), e()

define('PORTAL_BRAND', 'Διαιτολογικό Γραφείο');
define('CANCEL_WINDOW_HOURS', 24);   // ελάχιστο περιθώριο ακύρωσης/αλλαγής
define('LOGIN_MAX_TRIES', 5);
define('LOGIN_LOCK_MINUTES', 15);

if (session_status() === PHP_SESSION_NONE) {
    session_name('diet_portal');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','httponly'=>true,'samesite'=>'Lax']);
    session_start();
}

// ---- Query helper ------------------------------------------------------
function q($sql, $params = []) {
    $st = get_db()->prepare($sql);
    $st->execute($params);
    return $st;
}

// ---- Navigation / URLs -------------------------------------------------
function redirect($p) { header('Location: ' . $p); exit; }
function site_base_url() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $root = rtrim(str_replace('\\','/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
    return $scheme . '://' . $host . $root;
}
function portal_link($p) { return site_base_url() . '/portal/' . ltrim($p, '/'); }

// ---- Auth --------------------------------------------------------------
function is_client_logged_in() { return !empty($_SESSION['client_id']); }
function require_client_login() { if (!is_client_logged_in()) redirect('login.php'); }
function current_client() {
    static $c = null;
    if ($c === null && is_client_logged_in()) {
        $c = q("SELECT * FROM clients WHERE id=? AND status='active'", [$_SESSION['client_id']])->fetch(PDO::FETCH_ASSOC);
        if (!$c) { $_SESSION = []; }
    }
    return $c ?: null;
}

// ---- CSRF --------------------------------------------------------------
function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_verify() {
    $ok = isset($_POST['_csrf']) && is_string($_POST['_csrf']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['_csrf']);
    if (!$ok) { http_response_code(400); die('Άκυρο αίτημα (CSRF).'); }
}

// ---- Flash -------------------------------------------------------------
function flash_set($t,$m){ $_SESSION['flash'][] = ['type'=>$t,'msg'=>$m]; }
function flash_all(){ $f=$_SESSION['flash']??[]; unset($_SESSION['flash']); return $f; }

// ---- Tokens ------------------------------------------------------------
function make_token() { return bin2hex(random_bytes(32)); }
function hash_token($t) { return hash('sha256', $t); }

// ---- Lockout (login_attempts) -----------------------------------------
function client_ip() { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }
function login_is_locked($scope, $id) {
    try {
        $r = q("SELECT attempts, locked_until FROM login_attempts WHERE scope=? AND identifier=? AND ip=?",
               [$scope, $id, client_ip()])->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { return false; }
    if ($r && $r['locked_until'] && strtotime($r['locked_until']) > time()) {
        return (int)ceil((strtotime($r['locked_until']) - time()) / 60);
    }
    return false;
}
function login_record_fail($scope, $id) {
    try {
        q("INSERT INTO login_attempts (scope,identifier,ip,attempts) VALUES (?,?,?,1)
           ON DUPLICATE KEY UPDATE attempts=attempts+1", [$scope,$id,client_ip()]);
        $r = q("SELECT attempts FROM login_attempts WHERE scope=? AND identifier=? AND ip=?",
               [$scope,$id,client_ip()])->fetch(PDO::FETCH_ASSOC);
        if ($r && (int)$r['attempts'] >= LOGIN_MAX_TRIES) {
            $until = date('Y-m-d H:i:s', time() + LOGIN_LOCK_MINUTES*60);
            q("UPDATE login_attempts SET locked_until=?, attempts=0 WHERE scope=? AND identifier=? AND ip=?",
              [$until,$scope,$id,client_ip()]);
        }
    } catch (PDOException $e) { /* table may not exist yet */ }
}
function login_record_success($scope, $id) {
    try { q("DELETE FROM login_attempts WHERE scope=? AND identifier=? AND ip=?", [$scope,$id,client_ip()]); }
    catch (PDOException $e) {}
}

// ---- Μορφοποίηση -------------------------------------------------------
// eur/gr_date/gr_datetime/hhmm ορίζονται πλέον στο includes/functions.php (κοινά)
$GR_DAYS = ['Κυριακή','Δευτέρα','Τρίτη','Τετάρτη','Πέμπτη','Παρασκευή','Σάββατο'];

// ---- Ελεύθερα slots για μια ημερομηνία (mirror του get-slots) ----------
function portal_free_slots($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) return [];
    if ($d < new DateTime('today')) return [];
    if (is_date_blocked($date)) return [];
    $dow = (int)$d->format('w');
    $rules = array_filter(get_weekly_availability(), fn($a)=>(int)$a['day_of_week']===$dow);
    if (!$rules) return [];
    $booked = array_map(fn($t)=>substr($t,0,5), get_booked_slots($date));
    $out = [];
    $now = new DateTime();
    $isToday = $d->format('Y-m-d') === (new DateTime('today'))->format('Y-m-d');
    foreach ($rules as $r) {
        $s = DateTime::createFromFormat('H:i:s', $r['start_time']);
        $e = DateTime::createFromFormat('H:i:s', $r['end_time']);
        $iv = new DateInterval('PT'.(int)$r['slot_minutes'].'M');
        while ($s < $e) {
            $ts = $s->format('H:i');
            $past = $isToday && DateTime::createFromFormat('Y-m-d H:i', "$date $ts") < $now;
            if (!in_array($ts, $booked) && !$past) $out[] = $ts;
            $s->add($iv);
        }
    }
    sort($out);
    return $out;
}

// ---- Μπορεί να τροποποιηθεί ένα ραντεβού; (περιθώριο ωρών) --------------
function appt_is_modifiable($appt) {
    if ($appt['status'] === 'cancelled') return false;
    $when = strtotime($appt['appointment_date'].' '.$appt['appointment_time']);
    return $when >= time() + CANCEL_WINDOW_HOURS*3600;
}

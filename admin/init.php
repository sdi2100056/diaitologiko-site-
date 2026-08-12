<?php
/**
 * Admin bootstrap — session, authentication, CSRF, helpers.
 * Φορτώνεται στην κορυφή ΚΑΘΕ σελίδας του admin.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php'; // φέρνει get_db(), e()

// ---- Session (ασφαλές cookie) ------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_name('diet_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---- Helpers -----------------------------------------------------------
function admin_base_url() {
    // path του φακέλου /admin/ σε σχέση με το domain
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    return rtrim($dir, '/');
}

function redirect($path) {
    header('Location: ' . $path);
    exit;
}

function is_logged_in() {
    return !empty($_SESSION['admin_ok']);
}

function require_login() {
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

function admin_check_credentials($user, $pass) {
    if (!hash_equals(ADMIN_USER, (string)$user)) {
        return false;
    }
    if (defined('ADMIN_PASS_HASH') && ADMIN_PASS_HASH !== '') {
        return password_verify((string)$pass, ADMIN_PASS_HASH);
    }
    return hash_equals(ADMIN_PASS, (string)$pass);
}

// ---- CSRF --------------------------------------------------------------
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_field() {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}
function csrf_verify() {
    $ok = isset($_POST['_csrf']) && is_string($_POST['_csrf'])
        && hash_equals($_SESSION['csrf'] ?? '', $_POST['_csrf']);
    if (!$ok) {
        http_response_code(400);
        die('Άκυρο αίτημα (CSRF). Επίστρεψε πίσω και δοκίμασε ξανά.');
    }
}

// ---- Flash μηνύματα ----------------------------------------------------
function flash_set($type, $msg) {
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}
function flash_all() {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ---- Μορφοποίηση -------------------------------------------------------
// eur/gr_date/gr_datetime/hhmm ορίζονται πλέον στο includes/functions.php (κοινά)

$GR_DAYS = ['Κυριακή','Δευτέρα','Τρίτη','Τετάρτη','Πέμπτη','Παρασκευή','Σάββατο'];
$GR_STATUS_APPT = [
    'pending'   => ['Σε αναμονή', 'warn'],
    'confirmed' => ['Επιβεβαιωμένο', 'ok'],
    'cancelled' => ['Ακυρωμένο', 'bad'],
];
$GR_STATUS_ORDER = [
    'pending'   => ['Εκκρεμεί', 'warn'],
    'paid'      => ['Πληρωμένη', 'ok'],
    'failed'    => ['Απέτυχε', 'bad'],
    'cancelled' => ['Ακυρωμένη', 'muted'],
];

/**
 * Μικρός βοηθός: τρέχει query με παραμέτρους και επιστρέφει το statement.
 */
function q($sql, $params = []) {
    $stmt = get_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

// ======================================================================
//  Επεκτάσεις για την Πύλη Πελατών (clients portal)
// ======================================================================

function make_token() { return bin2hex(random_bytes(32)); }
function hash_token($t) { return hash('sha256', $t); }

function site_base_url() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $root = rtrim(str_replace('\\','/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
    return $scheme . '://' . $host . $root;
}
function portal_link($p) { return site_base_url() . '/portal/' . ltrim($p, '/'); }

/** Αριθμός αδιάβαστων ειδοποιήσεων (0 αν δεν έχει γίνει ακόμη migration). */
function admin_unread_count() {
    static $n = null;
    if ($n === null) {
        try { $n = (int) get_db()->query("SELECT COUNT(*) FROM admin_notifications WHERE is_read=0")->fetchColumn(); }
        catch (Throwable $e) { $n = 0; }
    }
    return $n;
}

/** Επιστρέφει client_id για δεδομένο email ή null. */
function client_id_for_email($email) {
    if (!$email) return null;
    try {
        $id = q("SELECT id FROM clients WHERE email=?", [$email])->fetchColumn();
        return $id ? (int)$id : null;
    } catch (Throwable $e) { return null; }
}

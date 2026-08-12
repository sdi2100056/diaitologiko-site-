<?php
// Ρυθμίσεις βάσης δεδομένων — ΣΥΜΠΛΗΡΩΣΕ τα στοιχεία του cPanel hosting εδώ
define('DB_HOST', 'localhost');
define('DB_NAME', 'diaitologio_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Στοιχεία Viva Wallet (Smart Checkout API) — ΣΥΜΠΛΗΡΩΣΕ μετά τη δημιουργία λογαριασμού
define('VIVA_CLIENT_ID', '');
define('VIVA_CLIENT_SECRET', '');
define('VIVA_MERCHANT_ID', '');
define('VIVA_API_KEY', '');
define('VIVA_DEMO', true); // true = demo.vivapayments.com, false = production

// Email αποστολέα ειδοποιήσεων
define('SITE_EMAIL_FROM', 'no-reply@yourdomain.gr');
define('SITE_ADMIN_EMAIL', 'you@yourdomain.gr');

function get_db() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            die('Σφάλμα σύνδεσης με τη βάση δεδομένων.');
        }
    }
    return $pdo;
}

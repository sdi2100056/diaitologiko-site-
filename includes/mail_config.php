<?php
/**
 * Ρυθμίσεις αποστολής email.
 * Μπορείς να τις αλλάξεις εδώ ή από το admin → Ρυθμίσεις → «Email (SMTP)».
 *
 * MAIL_METHOD: 'smtp' (συνιστάται για αξιόπιστη παράδοση) ή 'mail' (PHP mail()).
 */
define('MAIL_METHOD',      'mail');           // 'smtp' ή 'mail'
define('MAIL_SMTP_HOST',   '');               // π.χ. smtp.gmail.com ή mail.yourdomain.gr
define('MAIL_SMTP_PORT',   587);              // 587 (TLS) ή 465 (SSL)
define('MAIL_SMTP_SECURE', 'tls');            // 'tls', 'ssl' ή '' (χωρίς)
define('MAIL_SMTP_USER',   '');               // όνομα χρήστη SMTP
define('MAIL_SMTP_PASS',   '');               // κωδικός SMTP (ή app password)
define('MAIL_FROM',        'no-reply@yourdomain.gr');
define('MAIL_FROM_NAME',   'Διαιτολογικό Γραφείο');

/** Μυστικό κλειδί για ενεργοποίηση των υπενθυμίσεων μέσω URL (αν δεν υπάρχει cron). */
define('CRON_KEY', '');

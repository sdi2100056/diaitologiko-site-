<?php
/**
 * Κεντρικό layer αποστολής email μέσω PHPMailer.
 * Υποστηρίζει SMTP ή PHP mail(), HTML πρότυπο, συνημμένα και .ics.
 */
require_once __DIR__ . '/lib/PHPMailer/Exception.php';
require_once __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/lib/PHPMailer/SMTP.php';
if (is_file(__DIR__ . '/mail_config.php')) require_once __DIR__ . '/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;

/** Ασφαλής ανάγνωση σταθεράς ρυθμίσεων. */
function mailcfg($k, $d = null) { return defined($k) ? constant($k) : $d; }

/** Τυλίγει απλό κείμενο σε επώνυμο HTML πρότυπο. */
function mail_brand_html($title, $bodyText) {
    $brand = htmlspecialchars(mailcfg('MAIL_FROM_NAME', 'Διαιτολογικό Γραφείο'), ENT_QUOTES, 'UTF-8');
    $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $b = nl2br(htmlspecialchars($bodyText, ENT_QUOTES, 'UTF-8'));
    return '<!DOCTYPE html><html lang="el"><body style="margin:0;background:#F3F8F4;font-family:Segoe UI,Arial,sans-serif;color:#0C1F1A">'
      . '<div style="max-width:560px;margin:0 auto;padding:24px">'
      . '<div style="background:#fff;border-radius:16px;overflow:hidden;border:1px solid #E1EAE5">'
      . '<div style="background:linear-gradient(135deg,#0E9488,#04795B);padding:20px 26px;color:#fff">'
      . '<div style="font-size:18px;font-weight:800">' . $brand . '</div></div>'
      . '<div style="padding:26px">'
      . '<h1 style="font-size:19px;margin:0 0 12px;color:#04795B">' . $t . '</h1>'
      . '<div style="font-size:15px;line-height:1.6;color:#3B4A46">' . $b . '</div>'
      . '</div></div>'
      . '<div style="text-align:center;color:#6C7B76;font-size:12px;padding:16px">Αυτό το μήνυμα στάλθηκε αυτόματα. Παρακαλούμε μην απαντάς σε αυτή τη διεύθυνση.</div>'
      . '</div></body></html>';
}

/**
 * Αποστολή email.
 * $opts: html(bool), alt(string), ics(string), attachments([['path'=>..,'name'=>..]])
 * Επιστρέφει true/false.
 */
function send_mail($to, $subject, $body, $opts = []) {
    $mail = new PHPMailer(true);
    try {
        $method = mailcfg('MAIL_METHOD', 'mail');
        if ($method === 'smtp' && mailcfg('MAIL_SMTP_HOST')) {
            $mail->isSMTP();
            $mail->Host = mailcfg('MAIL_SMTP_HOST');
            $mail->Port = (int) mailcfg('MAIL_SMTP_PORT', 587);
            $sec = mailcfg('MAIL_SMTP_SECURE', 'tls');
            if ($sec) $mail->SMTPSecure = $sec;
            if (mailcfg('MAIL_SMTP_USER')) {
                $mail->SMTPAuth = true;
                $mail->Username = mailcfg('MAIL_SMTP_USER');
                $mail->Password = mailcfg('MAIL_SMTP_PASS');
            }
        } else {
            $mail->isMail();
        }
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $from = mailcfg('MAIL_FROM', defined('SITE_EMAIL_FROM') ? SITE_EMAIL_FROM : 'no-reply@localhost');
        $mail->setFrom($from, mailcfg('MAIL_FROM_NAME', 'Διαιτολογικό Γραφείο'));
        $mail->addAddress($to);
        $mail->Subject = $subject;

        if (!empty($opts['html'])) {
            $mail->isHTML(true);
            $mail->Body = $body;
            $mail->AltBody = $opts['alt'] ?? trim(strip_tags($body));
        } else {
            $mail->Body = $body;
        }
        if (!empty($opts['ics'])) {
            $mail->addStringAttachment($opts['ics'], 'appointment.ics', 'base64', 'text/calendar; charset=UTF-8; method=REQUEST');
        }
        if (!empty($opts['attachments'])) {
            foreach ($opts['attachments'] as $a) {
                if (!empty($a['path']) && is_file($a['path'])) $mail->addAttachment($a['path'], $a['name'] ?? basename($a['path']));
                elseif (!empty($a['data'])) $mail->addStringAttachment($a['data'], $a['name'] ?? 'file.pdf', 'base64', $a['type'] ?? 'application/octet-stream');
            }
        }
        return $mail->send();
    } catch (\Throwable $e) {
        error_log('send_mail failed: ' . $e->getMessage());
        return false;
    }
}

/** Δοκιμαστικό email για τη σελίδα ρυθμίσεων. */
function send_test_mail($to) {
    return send_mail($to, 'Δοκιμαστικό email — ρυθμίσεις OK',
        mail_brand_html('Λειτουργεί! ✓', "Αν βλέπεις αυτό το μήνυμα, οι ρυθμίσεις email είναι σωστές.\n\nΜέθοδος: " . mailcfg('MAIL_METHOD', 'mail')),
        ['html' => true]);
}

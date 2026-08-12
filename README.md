# Site Διαιτολογικού Γραφείου

## Δομή
- `index.php`, `about.php`, `services.php`, `booking.php`, `contact.php` — οι 5 σελίδες
- `includes/` — κοινά κομμάτια (header, footer, σύνδεση DB, helper συναρτήσεις)
- `api/` — PHP endpoints που καλούνται από JavaScript (AJAX)
- `assets/` — CSS, JS, εικόνες
- `db/schema.sql` — δομή βάσης δεδομένων + ενδεικτικά δεδομένα

## Βήματα εγκατάστασης στο cPanel hosting

1. **Ανέβασε τα αρχεία** στον φάκελο `public_html` (ή στον φάκελο του domain/subdomain) μέσω File Manager ή FTP.

2. **Δημιούργησε βάση δεδομένων MySQL** από το cPanel (MySQL Databases):
   - Δημιούργησε database, database user, και δώσε στον χρήστη όλα τα δικαιώματα (All Privileges) πάνω στη βάση.

3. **Εισήγαγε το schema**: cPanel → phpMyAdmin → επίλεξε τη βάση → tab "Import" → ανέβασε το `db/schema.sql`.

4. **Συμπλήρωσε τα στοιχεία** στο `includes/db.php`:
   - `DB_NAME`, `DB_USER`, `DB_PASS` (τα στοιχεία που δημιούργησες στο βήμα 2)
   - `SITE_EMAIL_FROM`, `SITE_ADMIN_EMAIL`

5. **Viva Wallet** (για τις πληρωμές):
   - Δημιούργησε λογαριασμό στο vivawallet.com
   - Πήγαινε στο Settings → API Access και δημιούργησε "Smart Checkout Credentials" (Client ID / Secret)
   - Συμπλήρωσε `VIVA_CLIENT_ID`, `VIVA_CLIENT_SECRET`, `VIVA_MERCHANT_ID`, `VIVA_API_KEY` στο `includes/db.php`
   - Δοκίμασε πρώτα με `VIVA_DEMO = true` (demo περιβάλλον), μετά βάλε `false` όταν είσαι έτοιμος για live πληρωμές
   - Στο Viva dashboard, όρισε ως "Redirect URL μετά την πληρωμή": `https://yourdomain.gr/api/viva-callback.php`

6. **Επεξεργάσου το περιεχόμενο**:
   - `includes/header.php`: άλλαξε "Ονοματεπώνυμο" στο λογότυπο
   - `about.php`: συμπλήρωσε το πραγματικό βιογραφικό
   - `contact.php` & `includes/footer.php`: πραγματικά στοιχεία επικοινωνίας
   - `db/schema.sql` ή απευθείας από phpMyAdmin: πρόσθεσε/επεξεργάσου τις πραγματικές υπηρεσίες στον πίνακα `services`

7. **Ρύθμισε τη διαθεσιμότητα ραντεβού** στον πίνακα `availability` (μέρες/ώρες που δέχεσαι ραντεβού) και προαιρετικά πρόσθεσε ημερομηνίες σε `blocked_dates` για διακοπές/ρεπό.

## Σημειώσεις

- Το site χρησιμοποιεί την **PHP `mail()`** συνάρτηση για emails. Αν δεν παραδίδονται (καταλήγουν σε spam), σκέψου να προσθέσεις PHPMailer με SMTP — μπορώ να το προσθέσω αν χρειαστεί.
- Το calendar χρησιμοποιεί τη βιβλιοθήκη **FullCalendar** μέσω CDN — δεν χρειάζεται installation.
- Πριν πάει live η πληρωμή, στο `api/viva-callback.php` πρέπει να προστεθεί πραγματική επαλήθευση συναλλαγής μέσω του Viva Transactions API (υπάρχει σημείωση στο αρχείο).

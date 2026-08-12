<?php
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Επικοινωνία';
include __DIR__ . '/includes/header.php';
?>

<main>
    <section class="section">
        <div class="container contact-grid">
            <div class="reveal">
                <span class="eyebrow">Επικοινωνία</span>
                <h1>Μίλησέ μας</h1>
                <p class="lede">Για ερωτήσεις ή απορίες πριν κλείσεις ραντεβού, στείλε μας μήνυμα.</p>

                <div class="contact-cards">
                    <div class="contact-card">
                        <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg></div>
                        <div><small>Email</small><b>info@yourdomain.gr</b></div>
                    </div>
                    <div class="contact-card">
                        <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.9v3a2 2 0 01-2.2 2 19.8 19.8 0 01-8.6-3 19.5 19.5 0 01-6-6 19.8 19.8 0 01-3-8.6A2 2 0 014.1 2h3a2 2 0 012 1.7c.1 1 .4 1.9.7 2.8a2 2 0 01-.5 2.1L8.1 9.9a16 16 0 006 6l1.3-1.2a2 2 0 012.1-.5c.9.3 1.8.6 2.8.7a2 2 0 011.7 2z"/></svg></div>
                        <div><small>Τηλέφωνο</small><b>+30 210 000 0000</b></div>
                    </div>
                    <div class="contact-card">
                        <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 6-9 12-9 12s-9-6-9-12a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg></div>
                        <div><small>Διεύθυνση</small><b>[Οδός, Αριθμός, Πόλη]</b></div>
                    </div>
                    <div class="contact-card">
                        <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg></div>
                        <div><small>Ωράριο</small><b>Δευ–Παρ 09:00–17:00</b></div>
                    </div>
                </div>
            </div>

            <div class="reveal">
                <form id="contact-form" class="booking-form show" style="max-width: 480px; background: var(--surface); border: 1px solid var(--line); border-radius: var(--r-lg); padding: 30px; box-shadow: var(--sh-1); margin-top: 0;">
                    <label for="cname">Ονοματεπώνυμο</label>
                    <input type="text" id="cname" name="cname" required>

                    <label for="cemail">Email</label>
                    <input type="email" id="cemail" name="cemail" required>

                    <label for="cmessage">Μήνυμα</label>
                    <textarea id="cmessage" name="cmessage" rows="5" required></textarea>

                    <button type="submit" class="btn btn-primary">Αποστολή Μηνύματος</button>
                    <div class="form-msg" id="contact-msg"></div>
                </form>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
document.getElementById('contact-form').addEventListener('submit', function (e) {
    e.preventDefault();
    const msg = document.getElementById('contact-msg');
    const payload = {
        name: document.getElementById('cname').value,
        email: document.getElementById('cemail').value,
        message: document.getElementById('cmessage').value
    };
    fetch('api/send-contact.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        msg.style.display = 'flex';
        if (data.success) {
            msg.className = 'form-msg success';
            msg.textContent = 'Το μήνυμά σου στάλθηκε!';
            e.target.reset();
        } else {
            msg.className = 'form-msg error';
            msg.textContent = data.message || 'Κάτι πήγε στραβά.';
        }
    })
    .catch(() => {
        msg.style.display = 'flex';
        msg.className = 'form-msg error';
        msg.textContent = 'Σφάλμα σύνδεσης. Δοκίμασε ξανά.';
    });
});
</script>

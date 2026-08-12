<footer class="site-footer">
    <div class="container">
        <div class="footer-top">
            <div>
                
                <p style="max-width: 34ch;">Εξατομικευμένη διατροφική καθοδήγηση, βασισμένη σε δεδομένα και στον δικό σου ρυθμό.</p>
                <div class="socials">
                    <a href="#" aria-label="Instagram"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg></a>
                    <a href="#" aria-label="Facebook"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 9h3V6h-3c-2 0-3 1.3-3 3.2V11H8v3h3v7h3v-7h2.5l.5-3H14V9.5c0-.3.2-.5.6-.5H14z"/></svg></a>
                    <a href="#" aria-label="TikTok"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 3c.3 2.2 1.7 3.8 4 4v3c-1.5 0-2.9-.5-4-1.3V15a6 6 0 11-6-6c.3 0 .7 0 1 .1V12a3 3 0 103 3V3h2z"/></svg></a>
                </div>
            </div>
            <div>
                <h3>Πλοήγηση</h3>
                <p><a href="about.php">Σχετικά</a></p>
                <p><a href="services.php">Υπηρεσίες</a></p>
                <p><a href="booking.php">Ραντεβού</a></p>
                <p><a href="contact.php">Επικοινωνία</a></p>
            </div>
            <div>
                <h3>Επικοινωνία</h3>
                <p><a href="mailto:info@yourdomain.gr">info@yourdomain.gr</a></p>
                <p><a href="tel:+302100000000">+30 210 000 0000</a></p>
                <p>[Οδός, Αριθμός, Πόλη]</p>
                <p>Δευ–Παρ 09:00–17:00</p>
            </div>
            <div>
                <h3>Newsletter</h3>
                <p style="max-width: 30ch;">Συμβουλές διατροφής & νέα, μία φορά τον μήνα.</p>
                <form class="newsletter" onsubmit="event.preventDefault(); this.reset(); this.querySelector('button').textContent='✓';">
                    <input type="email" placeholder="Το email σου" required aria-label="Email">
                    <button type="submit" aria-label="Εγγραφή">→</button>
                </form>
            </div>
        </div>
        <div class="footer-bottom">
            <span>© <?= date('Y') ?> Διαιτολογικό Γραφείο. Με επιφύλαξη παντός δικαιώματος.</span>
            <span class="footer-legal">
                <a href="privacy.php">Πολιτική Απορρήτου</a> ·
                <a href="terms.php">Όροι Χρήσης</a> ·
                <a href="portal/login.php">Ο λογαριασμός μου</a>
            </span>
        </div>
    </div>
</footer>

<div class="cookie-bar" id="cookieBar">
    <p>Χρησιμοποιούμε απαραίτητα cookies για τη λειτουργία του ιστότοπου. Δες την <a href="privacy.php">Πολιτική Απορρήτου</a>.</p>
    <div class="cookie-actions">
        <button type="button" id="cookieAccept" class="cookie-btn cookie-accept">Αποδοχή</button>
        <button type="button" id="cookieDecline" class="cookie-btn">Μόνο απαραίτητα</button>
    </div>
</div>
<script>
(function(){
  var bar=document.getElementById('cookieBar');
  if(!bar) return;
  function has(n){return document.cookie.split('; ').some(function(r){return r.indexOf(n+'=')===0;});}
  if(!has('cookie_consent')) bar.classList.add('show');
  function set(v){
    document.cookie='cookie_consent='+v+';path=/;max-age='+(60*60*24*365)+';SameSite=Lax';
    bar.classList.remove('show');
  }
  var a=document.getElementById('cookieAccept'),d=document.getElementById('cookieDecline');
  if(a)a.addEventListener('click',function(){set('all');});
  if(d)d.addEventListener('click',function(){set('essential');});
})();
</script>

<button type="button" class="to-top" id="toTop" aria-label="Πάνω">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
</button>
<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function(){ navigator.serviceWorker.register('sw.js').catch(function(){}); });
}
</script>
</body>
</html>

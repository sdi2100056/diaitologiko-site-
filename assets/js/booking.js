document.addEventListener('DOMContentLoaded', function () {
    const slotsTitle = document.getElementById('slots-title');
    const slotsList = document.getElementById('slots-list');
    const bookingForm = document.getElementById('booking-form');
    const formMsg = document.getElementById('form-msg');
    const practEl = document.getElementById('practitioner');
    const typeEl = document.getElementById('appt-type');
    function pracVal(){ return practEl ? practEl.value : ''; }
    function typeVal(){ return typeEl ? typeEl.value : 'new'; }

    let selectedDate = null;
    let selectedTime = null;

    const steps = document.querySelectorAll('.bstep');
    function setStep(n) {
        steps.forEach((s, i) => s.classList.toggle('on', i < n));
    }

    const calendarEl = document.getElementById('calendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'el',
        height: 'auto',
        selectable: true,
        validRange: { start: new Date().toISOString().split('T')[0] },
        dateClick: function (info) {
            selectedDate = info.dateStr;
            selectedTime = null;
            setStep(2);
            loadSlots(selectedDate);
        }
    });
    calendar.render();

    if (practEl) practEl.addEventListener('change', function(){ if (selectedDate) loadSlots(selectedDate); });
    if (typeEl) typeEl.addEventListener('change', function(){ if (selectedDate) loadSlots(selectedDate); });

    function loadSlots(date) {
        slotsTitle.textContent = 'Φόρτωση ωρών...';
        slotsList.innerHTML = '';
        bookingForm.classList.remove('show');
        formMsg.style.display = 'none';

        fetch('api/get-slots.php?date=' + encodeURIComponent(date) + '&type=' + encodeURIComponent(typeVal()) + (pracVal() ? '&practitioner=' + encodeURIComponent(pracVal()) : ''))
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    slotsTitle.textContent = data.message || 'Δεν είναι διαθέσιμη αυτή η ημέρα.';
                    return;
                }
                if (data.slots.length === 0) {
                    slotsTitle.textContent = 'Δεν υπάρχουν διαθέσιμες ώρες για ' + formatDate(date);
                    return;
                }
                slotsTitle.textContent = 'Διαθέσιμες ώρες — ' + formatDate(date);
                data.slots.forEach(slot => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'slot-btn';
                    btn.textContent = slot.time;
                    if (!slot.available) {
                        btn.disabled = true;
                    } else {
                        btn.addEventListener('click', () => selectSlot(btn, slot.time));
                    }
                    slotsList.appendChild(btn);
                });
            })
            .catch(() => {
                slotsTitle.textContent = 'Σφάλμα φόρτωσης ωρών. Δοκίμασε ξανά.';
            });
    }

    function selectSlot(btn, time) {
        document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
        selectedTime = time;
        bookingForm.classList.add('show');
        setStep(3);
        formMsg.style.display = 'none';
    }

    function formatDate(dateStr) {
        const d = new Date(dateStr + 'T00:00:00');
        return d.toLocaleDateString('el-GR', { day: 'numeric', month: 'long', year: 'numeric' });
    }

    bookingForm.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!selectedDate || !selectedTime) return;

        const payload = {
            appointment_date: selectedDate,
            appointment_time: selectedTime,
            client_name: document.getElementById('client_name').value,
            client_email: document.getElementById('client_email').value,
            client_phone: document.getElementById('client_phone').value,
            notes: document.getElementById('notes').value,
            type: typeVal(),
            practitioner: pracVal()
        };

        const submitBtn = bookingForm.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Αποστολή...';

        fetch('api/book-appointment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Επιβεβαίωση Ραντεβού';
                formMsg.style.display = 'block';
                if (data.success) {
                    formMsg.className = 'form-msg success';
                    formMsg.textContent = 'Το ραντεβού σου επιβεβαιώθηκε! Θα λάβεις email επιβεβαίωσης.';
                    bookingForm.reset();
                    loadSlots(selectedDate); // refresh slots
                } else {
                    formMsg.className = 'form-msg error';
                    formMsg.textContent = data.message || 'Κάτι πήγε στραβά. Δοκίμασε ξανά.';
                }
            })
            .catch(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Επιβεβαίωση Ραντεβού';
                formMsg.style.display = 'block';
                formMsg.className = 'form-msg error';
                formMsg.textContent = 'Σφάλμα σύνδεσης. Δοκίμασε ξανά.';
            });
    });
});

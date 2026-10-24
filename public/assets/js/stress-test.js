/**
 * Concurrency Stress Test driver (vanilla JS, no framework — see GROUNDRULES §5).
 *
 * Fires N booking attempts at the SAME doctor/date/slot truly simultaneously
 * via Promise.all(N fetch() calls), each carrying a distinct dummy patient.
 * The server-side race guard is entirely in core/SlotEngine.php's book() —
 * this script's only job is to prove the guarantee holds by generating real
 * concurrent HTTP requests and rendering however many of them the database
 * actually let through. See FEATURES.md's "Concurrency Stress Test" section.
 */
(function () {
    const doctorSelect = document.getElementById('doctorSelect');
    const dateInput = document.getElementById('dateInput');
    const slotsArea = document.getElementById('slotsArea');
    const chosenSlotEl = document.getElementById('chosenSlot');
    const runBtn = document.getElementById('runBtn');
    const nInput = document.getElementById('nInput');
    const summaryEl = document.getElementById('summary');
    const resultsGrid = document.getElementById('resultsGrid');

    let chosen = null; // {doctorId, date, start, end, label}

    document.getElementById('loadSlotsBtn').addEventListener('click', async () => {
        const doctorId = doctorSelect.value;
        const date = dateInput.value;
        if (!doctorId || !date) { alert('Choose a doctor and date first.'); return; }
        slotsArea.innerHTML = '<span class="text-muted">Loading&hellip;</span>';
        chosen = null;
        chosenSlotEl.textContent = '';
        runBtn.disabled = true;

        const res = await fetch(`${window.TC_URLS.slots}?doctor_id=${doctorId}&date=${date}`);
        const data = await res.json();
        slotsArea.innerHTML = '';

        if (data.full_day_leave) {
            slotsArea.innerHTML = `<span class="text-muted">On leave: ${data.full_day_leave}</span>`;
            return;
        }
        if (!data.slots || data.slots.length === 0) {
            slotsArea.innerHTML = '<span class="text-muted">No open slots this day — try another date.</span>';
            return;
        }
        data.slots.forEach(slot => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-tc';
            btn.textContent = slot.label;
            btn.addEventListener('click', () => {
                document.querySelectorAll('#slotsArea button').forEach(b => b.classList.remove('btn-tc'));
                btn.classList.add('btn-tc');
                chosen = { doctorId: Number(doctorId), date, start: slot.start, end: slot.end, label: slot.label };
                chosenSlotEl.textContent = `Target: ${doctorSelect.options[doctorSelect.selectedIndex].text} — ${date} ${slot.label}`;
                runBtn.disabled = false;
                resultsGrid.innerHTML = '';
                summaryEl.innerHTML = '';
            });
            slotsArea.appendChild(btn);
        });
    });

    function attemptCard(index) {
        const col = document.createElement('div');
        col.className = 'col-6 col-md-3 col-lg-2';
        col.innerHTML = `<div class="attempt-card pending" id="attempt-${index}">
            <div class="small text-muted">Request #${index}</div>
            <div class="fw-semibold">Pending&hellip;</div>
        </div>`;
        return col;
    }

    function renderOutcome(index, result) {
        const card = document.getElementById(`attempt-${index}`);
        if (!card) return;
        if (result.outcome === 'booked') {
            card.classList.remove('pending');
            card.classList.add('won');
            card.innerHTML = `<div class="small text-muted">Request #${index}</div>
                <div class="fw-semibold text-success">Booked ✓</div>
                <div class="small">${result.patient_name}</div>
                <div class="small text-muted">appointment #${result.appointment_id}</div>`;
        } else if (result.outcome === 'slot_taken') {
            card.classList.remove('pending');
            card.classList.add('lost');
            card.innerHTML = `<div class="small text-muted">Request #${index}</div>
                <div class="fw-semibold text-danger">Slot already taken</div>
                <div class="small">${result.patient_name}</div>`;
        } else {
            card.classList.remove('pending');
            card.innerHTML = `<div class="small text-muted">Request #${index}</div><div class="fw-semibold text-danger">Error</div>`;
        }
    }

    async function fireAttempt(index) {
        try {
            const res = await fetch(window.TC_URLS.attempt, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    doctor_id: chosen.doctorId,
                    date: chosen.date,
                    start_time: chosen.start,
                    end_time: chosen.end,
                    dummy_index: index,
                    csrf_token: window.TC_CSRF,
                }),
            });
            return await res.json();
        } catch (e) {
            return { outcome: 'error' };
        }
    }

    runBtn.addEventListener('click', async () => {
        if (!chosen) return;
        const n = Math.max(2, Math.min(50, parseInt(nInput.value, 10) || 10));
        resultsGrid.innerHTML = '';
        summaryEl.innerHTML = '<span class="text-muted">Firing ' + n + ' simultaneous requests&hellip;</span>';
        runBtn.disabled = true;

        for (let i = 1; i <= n; i++) {
            resultsGrid.appendChild(attemptCard(i));
        }

        const startedAt = performance.now();
        // The whole point: build every request first, THEN fire them together
        // via Promise.all — this is what makes them genuinely concurrent
        // instead of a sequential loop that would never actually race.
        const promises = [];
        for (let i = 1; i <= n; i++) {
            promises.push(fireAttempt(i).then(result => ({ index: i, result })));
        }
        const outcomes = await Promise.all(promises);
        const elapsed = Math.round(performance.now() - startedAt);

        outcomes.sort((a, b) => a.index - b.index);
        outcomes.forEach(({ index, result }) => renderOutcome(index, result));

        const booked = outcomes.filter(o => o.result.outcome === 'booked');
        const taken = outcomes.filter(o => o.result.outcome === 'slot_taken');
        const winner = booked[0];

        const verdictClass = booked.length === 1 ? 'text-success' : 'text-danger';
        summaryEl.innerHTML = `<div class="alert ${booked.length === 1 ? 'alert-success' : 'alert-danger'} mb-0">
            <strong>${n} requests fired in parallel, resolved in ${elapsed}ms.</strong>
            <span class="${verdictClass}">${booked.length} booked</span>, ${taken.length} rejected as "slot already taken".
            ${booked.length === 1 ? 'Exactly one winner — the unique-key guarantee held.' : 'Unexpected result — see below.'}
            ${winner ? `Winning appointment: <strong>#${winner.result.appointment_id}</strong> (${winner.result.patient_name}).` : ''}
        </div>`;

        runBtn.disabled = false;

        await fetch(window.TC_URLS.log, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                doctor_id: chosen.doctorId,
                date: chosen.date,
                start_time: chosen.start,
                n: n,
                booked_count: booked.length,
                winner_appointment_id: winner ? winner.result.appointment_id : null,
                csrf_token: window.TC_CSRF,
            }),
        });
    });
})();

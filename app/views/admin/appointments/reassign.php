<?php use Core\Csrf; use Core\Router; ?>
<p class="eyebrow mb-1">Reassign</p>
<h1 class="h3 mb-1">Appointment #<?= str_pad($appointment['id'], 6, '0', STR_PAD_LEFT) ?></h1>
<p class="text-muted mb-4">Currently: <?= htmlspecialchars($appointment['patient_name'], ENT_QUOTES) ?> with <?= htmlspecialchars($appointment['doctor_name'], ENT_QUOTES) ?> on <?= htmlspecialchars($appointment['appointment_date'], ENT_QUOTES) ?> at <?= date('g:i A', strtotime($appointment['start_time'])) ?></p>

<div class="card card-tc">
    <div class="card-header">Pick a new doctor, date &amp; slot</div>
    <div class="card-body">
        <div class="row g-2 mb-3">
            <div class="col-md-5">
                <label class="form-label small">Doctor</label>
                <select id="doctorSelect" class="form-select">
                    <option value="">Choose a doctor&hellip;</option>
                    <?php foreach ($doctors as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name'], ENT_QUOTES) ?> &mdash; <?= htmlspecialchars($d['specialization_name'], ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small">Date</label>
                <input type="date" id="dateInput" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button id="loadSlotsBtn" type="button" class="btn btn-outline-tc w-100">Load slots</button>
            </div>
        </div>
        <div id="slotsArea" class="d-flex flex-wrap gap-2"></div>

        <form method="post" action="<?= Router::url('/admin/appointments/' . $appointment['id'] . '/reassign') ?>" id="reassignForm" class="mt-3">
            <?= Csrf::field() ?>
            <input type="hidden" name="doctor_id" id="fDoctorId">
            <input type="hidden" name="date" id="fDate">
            <input type="hidden" name="start_time" id="fStart">
            <input type="hidden" name="end_time" id="fEnd">
        </form>
    </div>
</div>

<script>
const doctorSelect = document.getElementById('doctorSelect');
const dateInput = document.getElementById('dateInput');
const slotsArea = document.getElementById('slotsArea');

document.getElementById('loadSlotsBtn').addEventListener('click', async () => {
    const doctorId = doctorSelect.value;
    const date = dateInput.value;
    if (!doctorId || !date) { alert('Choose a doctor and date first.'); return; }
    slotsArea.innerHTML = '<span class="text-muted">Loading&hellip;</span>';
    const res = await fetch(`<?= Router::url('/api/slots') ?>?doctor_id=${doctorId}&date=${date}`);
    const data = await res.json();
    slotsArea.innerHTML = '';
    if (data.full_day_leave) {
        slotsArea.innerHTML = `<span class="text-muted">On leave: ${data.full_day_leave}</span>`;
        return;
    }
    if (!data.slots || data.slots.length === 0) {
        slotsArea.innerHTML = '<span class="text-muted">No open slots this day.</span>';
        return;
    }
    data.slots.forEach(slot => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-outline-tc';
        btn.textContent = slot.label;
        btn.addEventListener('click', () => {
            document.getElementById('fDoctorId').value = doctorId;
            document.getElementById('fDate').value = date;
            document.getElementById('fStart').value = slot.start;
            document.getElementById('fEnd').value = slot.end;
            if (confirm(`Reassign to ${slot.label} on ${date}?`)) {
                document.getElementById('reassignForm').submit();
            }
        });
        slotsArea.appendChild(btn);
    });
});
</script>

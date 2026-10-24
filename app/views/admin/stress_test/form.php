<?php use Core\Csrf; use Core\Router; ?>
<p class="eyebrow mb-1">Viva demo</p>
<h1 class="h3 mb-1">Concurrency Stress Test</h1>
<p class="text-muted mb-4">Fires N simulated simultaneous booking requests at the exact same doctor/date/slot, each from a distinct dummy patient. The <code>UNIQUE KEY (doctor_id, appointment_date, start_time)</code> on <code>slot_reservations</code> is what decides the winner &mdash; not this page. Expect exactly one <span class="text-success fw-semibold">booked</span> and the rest cleanly rejected as <span class="text-danger fw-semibold">slot already taken</span>.</p>

<div class="card card-tc mb-4">
    <div class="card-header">1. Pick a target slot</div>
    <div class="card-body">
        <div class="row g-2 mb-3">
            <div class="col-md-4">
                <label class="form-label small">Doctor</label>
                <select id="doctorSelect" class="form-select">
                    <option value="">Choose a doctor&hellip;</option>
                    <?php foreach ($doctors as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name'], ENT_QUOTES) ?> &mdash; <?= htmlspecialchars($d['specialization_name'], ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Date</label>
                <input type="date" id="dateInput" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button id="loadSlotsBtn" type="button" class="btn btn-outline-tc w-100">Load open slots</button>
            </div>
        </div>
        <div id="slotsArea" class="d-flex flex-wrap gap-2"></div>
        <div id="chosenSlot" class="mt-3 fw-semibold"></div>
    </div>
</div>

<div class="card card-tc mb-4">
    <div class="card-header">2. Fire N simultaneous requests</div>
    <div class="card-body">
        <div class="row g-2 align-items-end mb-3">
            <div class="col-md-3">
                <label class="form-label small">N (simultaneous patients)</label>
                <input type="number" id="nInput" class="form-control" value="10" min="2" max="50">
            </div>
            <div class="col-md-3">
                <button id="runBtn" type="button" class="btn btn-tc w-100" disabled>Run stress test</button>
            </div>
        </div>
        <div id="summary" class="mb-3"></div>
        <div id="resultsGrid" class="row g-2"></div>
    </div>
</div>

<script>
window.TC_CSRF = <?= json_encode(Csrf::token()) ?>;
window.TC_URLS = {
    slots: <?= json_encode(Router::url('/api/slots')) ?>,
    attempt: <?= json_encode(Router::url('/admin/stress-test/attempt')) ?>,
    log: <?= json_encode(Router::url('/admin/stress-test/log')) ?>
};
</script>
<script src="<?= Router::url('/assets/js/stress-test.js') ?>"></script>

<?php use Core\Router; ?>
<p class="eyebrow mb-1">Dashboard</p>
<h1 class="h3 mb-4">Welcome back</h1>

<?php if (!empty($todayAlerts)): ?>
    <?php foreach ($todayAlerts as $a): ?>
        <div class="alert alert-<?= $a['status'] === 'no_show' ? 'danger' : 'warning' ?> d-flex justify-content-between align-items-center">
            <div>
                <?php if ($a['status'] === 'no_show'): ?>
                    Your <?= date('g:i A', strtotime($a['start_time'])) ?> appointment today with <strong><?= htmlspecialchars($a['doctor_name'], ENT_QUOTES) ?></strong> was marked as a no-show.
                <?php else: ?>
                    Your <?= date('g:i A', strtotime($a['start_time'])) ?> appointment today with <strong><?= htmlspecialchars($a['doctor_name'], ENT_QUOTES) ?></strong> was cancelled by <?= $a['cancelled_by'] === 'admin' ? 'the clinic' : 'the doctor' ?>.
                <?php endif; ?>
            </div>
            <a href="<?= Router::url('/patient/appointments/' . $a['id'] . '/confirmation') ?>" class="btn btn-sm btn-outline-dark ms-3">Details</a>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Upcoming appointments</h5>
    <a href="<?= Router::url('/patient/doctors') ?>" class="btn btn-tc btn-sm">Book a new appointment</a>
</div>

<?php if (empty($upcoming)): ?>
    <div class="card card-tc"><div class="card-body text-center text-muted py-5">
        No upcoming appointments yet. <a href="<?= Router::url('/patient/doctors') ?>">Find a doctor &rarr;</a>
    </div></div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($upcoming as $a): ?>
            <div class="col-md-6">
                <div class="card card-tc h-100">
                    <div class="card-body">
                        <h6 class="mb-1"><?= htmlspecialchars($a['doctor_name'], ENT_QUOTES) ?></h6>
                        <p class="text-muted small mb-2"><?= htmlspecialchars($a['specialization_name'], ENT_QUOTES) ?></p>
                        <?php if (($a['doctor_status'] ?? 'active') !== 'active'): ?>
                            <p class="small text-danger mb-2">This doctor is no longer taking bookings — your appointment still stands, but it can't be rescheduled. Call the clinic to move it.</p>
                        <?php endif; ?>
                        <p class="mb-2 mono"><?= date('D, j M Y', strtotime($a['appointment_date'])) ?> at <?= date('g:i A', strtotime($a['start_time'])) ?></p>
                        <a href="<?= Router::url('/patient/appointments/' . $a['id'] . '/confirmation') ?>" class="btn btn-sm btn-outline-tc">View details</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php use Core\Router; ?>
<p class="eyebrow mb-1">Admin</p>
<h1 class="h3 mb-4">Clinic dashboard</h1>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat-tile"><div class="stat-value"><?= $activeDoctors ?></div><div class="stat-label">Active doctors</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-tile"><div class="stat-value"><?= $totalPatients ?></div><div class="stat-label">Patients (<?= $suspendedPatients ?> suspended)</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-tile"><div class="stat-value"><?= $todayCount ?></div><div class="stat-label">Appointments today</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-tile"><div class="stat-value"><?= $bookedCount ?></div><div class="stat-label">Currently booked</div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4"><div class="stat-tile"><div class="stat-value"><?= $completedCount ?></div><div class="stat-label">Completed</div></div></div>
    <div class="col-6 col-md-4"><div class="stat-tile"><div class="stat-value"><?= $cancelledCount ?></div><div class="stat-label">Cancelled</div></div></div>
    <div class="col-6 col-md-4"><div class="stat-tile"><div class="stat-value"><?= $noShowCount ?></div><div class="stat-label">No-shows</div></div></div>
</div>

<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="<?= Router::url('/admin/board') ?>" class="btn btn-tc btn-sm">Today's board &rarr;</a>
    <a href="<?= Router::url('/admin/doctors') ?>" class="btn btn-outline-tc btn-sm">Manage doctors</a>
    <a href="<?= Router::url('/admin/appointments') ?>" class="btn btn-outline-tc btn-sm">All appointments</a>
    <a href="<?= Router::url('/admin/stress-test') ?>" class="btn btn-outline-tc btn-sm">Concurrency Stress Test</a>
</div>

<h5 class="mb-3">Today across all doctors</h5>
<?php if (empty($todaysBoard)): ?>
    <p class="text-muted">No appointments today.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Time</th><th>Doctor</th><th>Patient</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($todaysBoard, 0, 10) as $a): ?>
                <tr>
                    <td class="mono"><?= date('g:i A', strtotime($a['start_time'])) ?></td>
                    <td><?= htmlspecialchars($a['doctor_name'], ENT_QUOTES) ?></td>
                    <td><?= htmlspecialchars($a['patient_name'], ENT_QUOTES) ?></td>
                    <td class="text-capitalize"><?= htmlspecialchars(str_replace('_', ' ', $a['status']), ENT_QUOTES) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <a href="<?= Router::url('/admin/board') ?>">See full board &rarr;</a>
<?php endif; ?>

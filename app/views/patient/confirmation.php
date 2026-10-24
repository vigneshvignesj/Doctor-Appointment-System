<?php use Core\Router; ?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <p class="eyebrow mb-1">Appointment <?= $appointment['status'] === 'cancelled' ? 'cancelled' : 'confirmation' ?></p>
        <h1 class="h3 mb-4">Booking receipt</h1>

        <div class="card card-tc">
            <div class="card-header d-flex justify-content-between">
                <span>Appointment #<?= str_pad($appointment['id'], 6, '0', STR_PAD_LEFT) ?></span>
                <?php
                $badges = ['booked' => 'primary', 'completed' => 'success', 'cancelled' => 'secondary', 'no_show' => 'danger'];
                $cls = $badges[$appointment['status']] ?? 'secondary';
                ?>
                <span class="badge text-bg-<?= $cls ?> text-capitalize"><?= htmlspecialchars(str_replace('_', ' ', $appointment['status']), ENT_QUOTES) ?></span>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-4">Doctor</dt><dd class="col-8"><?= htmlspecialchars($appointment['doctor_name'], ENT_QUOTES) ?></dd>
                    <dt class="col-4">Specialization</dt><dd class="col-8"><?= htmlspecialchars($appointment['specialization_name'], ENT_QUOTES) ?></dd>
                    <dt class="col-4">Date</dt><dd class="col-8 mono"><?= date('l, j F Y', strtotime($appointment['appointment_date'])) ?></dd>
                    <dt class="col-4">Time</dt><dd class="col-8 mono"><?= date('g:i A', strtotime($appointment['start_time'])) ?> &ndash; <?= date('g:i A', strtotime($appointment['end_time'])) ?></dd>
                    <dt class="col-4">Patient</dt><dd class="col-8"><?= htmlspecialchars($appointment['patient_name'], ENT_QUOTES) ?></dd>
                </dl>
            </div>
        </div>

        <div class="mt-3 d-flex gap-2">
            <a href="<?= Router::url('/patient/appointments') ?>" class="btn btn-tc">My appointments</a>
            <a href="<?= Router::url('/patient/dashboard') ?>" class="btn btn-outline-tc">Dashboard</a>
        </div>
    </div>
</div>

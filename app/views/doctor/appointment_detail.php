<?php use Core\Csrf; use Core\Router; ?>
<p class="eyebrow mb-1">Appointment</p>
<h1 class="h3 mb-1"><?= htmlspecialchars($appointment['patient_name'], ENT_QUOTES) ?>
    <?= $history['is_returning'] ? '<span class="badge-returning">Returning · ' . $history['visit_count'] . ' visits</span>' : '<span class="badge-new">New patient</span>' ?>
</h1>
<p class="text-muted mb-4"><?= date('l, j F Y', strtotime($appointment['appointment_date'])) ?> at <?= date('g:i A', strtotime($appointment['start_time'])) ?></p>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card card-tc mb-4">
            <div class="card-header">Patient</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-4">Name</dt><dd class="col-8"><?= htmlspecialchars($appointment['patient_name'], ENT_QUOTES) ?></dd>
                    <dt class="col-4">Email</dt><dd class="col-8"><?= htmlspecialchars($appointment['patient_email'], ENT_QUOTES) ?></dd>
                    <dt class="col-4">Phone</dt><dd class="col-8"><?= htmlspecialchars($appointment['patient_phone'] ?? '—', ENT_QUOTES) ?></dd>
                    <dt class="col-4">Status</dt><dd class="col-8 text-capitalize"><?= htmlspecialchars(str_replace('_', ' ', $appointment['status']), ENT_QUOTES) ?></dd>
                </dl>
            </div>
        </div>

        <?php if ($appointment['status'] === 'booked'):
            $isFuture = $appointment['appointment_date'] > date('Y-m-d');
        ?>
        <div class="card card-tc mb-4">
            <div class="card-header">Actions</div>
            <div class="card-body d-flex flex-column gap-2">
                <?php if ($isFuture): ?>
                    <p class="text-muted small mb-1">This appointment is in the future — it can only be cancelled until the day it's scheduled.</p>
                <?php else: ?>
                    <form method="post" action="<?= Router::url('/doctor/appointments/' . $appointment['id'] . '/complete') ?>">
                        <?= Csrf::field() ?>
                        <label class="form-label small">Visit notes (optional)</label>
                        <textarea name="notes" class="form-control form-control-sm mb-2" rows="3" placeholder="e.g. Prescribed medication, advised follow-up..."></textarea>
                        <button class="btn btn-tc btn-sm w-100">Mark completed</button>
                    </form>
                    <form method="post" action="<?= Router::url('/doctor/appointments/' . $appointment['id'] . '/no-show') ?>" onsubmit="return confirm('Mark this patient as a no-show?');">
                        <?= Csrf::field() ?>
                        <button class="btn btn-outline-secondary btn-sm w-100">Mark no-show</button>
                    </form>
                <?php endif; ?>
                <form method="post" action="<?= Router::url('/doctor/appointments/' . $appointment['id'] . '/cancel') ?>" onsubmit="return confirm('Cancel this appointment? The slot will be freed immediately.');">
                    <?= Csrf::field() ?>
                    <button class="btn btn-outline-danger btn-sm w-100">Cancel appointment</button>
                </form>
            </div>
        </div>
        <?php elseif ($appointment['notes']): ?>
        <div class="card card-tc mb-4">
            <div class="card-header">Visit notes</div>
            <div class="card-body"><?= nl2br(htmlspecialchars($appointment['notes'], ENT_QUOTES)) ?></div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-7">
        <div class="card card-tc">
            <div class="card-header">Visit history with this doctor</div>
            <div class="card-body p-0">
                <?php if (empty($pastVisits)): ?>
                    <p class="text-muted p-3 mb-0">No previous visits — this is a new patient.</p>
                <?php else: ?>
                    <table class="table mb-0 align-middle">
                        <thead><tr><th>Date</th><th>Status</th><th>Notes</th></tr></thead>
                        <tbody>
                        <?php foreach ($pastVisits as $v): ?>
                            <tr>
                                <td class="mono"><?= htmlspecialchars($v['appointment_date'], ENT_QUOTES) ?></td>
                                <td class="text-capitalize"><?= htmlspecialchars(str_replace('_', ' ', $v['status']), ENT_QUOTES) ?></td>
                                <td class="small"><?= $v['notes'] ? nl2br(htmlspecialchars($v['notes'], ENT_QUOTES)) : '<span class="text-muted">—</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php use Core\Csrf; use Core\Router; ?>
<p class="eyebrow mb-1">My appointments</p>
<h1 class="h3 mb-4">Appointments</h1>

<h5 class="mb-3">Upcoming</h5>
<?php if (empty($upcoming)): ?>
    <p class="text-muted">No upcoming appointments.</p>
<?php else: ?>
    <div class="table-responsive mb-5">
        <table class="table table-tc align-middle">
            <thead><tr><th>Doctor</th><th>Date</th><th>Time</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($upcoming as $a): $doctorInactive = ($a['doctor_status'] ?? 'active') !== 'active'; ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($a['doctor_name'], ENT_QUOTES) ?>
                        <div class="text-muted small"><?= htmlspecialchars($a['specialization_name'], ENT_QUOTES) ?></div>
                        <?php if ($doctorInactive): ?>
                            <div class="small text-danger mt-1">No longer taking bookings — can't be rescheduled</div>
                        <?php endif; ?>
                    </td>
                    <td class="mono"><?= htmlspecialchars($a['appointment_date'], ENT_QUOTES) ?></td>
                    <td class="mono"><?= date('g:i A', strtotime($a['start_time'])) ?></td>
                    <td class="d-flex gap-2">
                        <a href="<?= Router::url('/patient/appointments/' . $a['id'] . '/confirmation') ?>" class="btn btn-sm btn-outline-tc">Details</a>
                        <?php if (!$doctorInactive): ?>
                            <a href="<?= Router::url('/patient/appointments/' . $a['id'] . '/reschedule') ?>" class="btn btn-sm btn-outline-secondary">Reschedule</a>
                        <?php endif; ?>
                        <form method="post" action="<?= Router::url('/patient/appointments/' . $a['id'] . '/cancel') ?>" onsubmit="return confirm('Cancel this appointment?');">
                            <?= Csrf::field() ?>
                            <button class="btn btn-sm btn-outline-danger">Cancel</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<h5 class="mb-3">Past</h5>
<?php if (empty($past)): ?>
    <p class="text-muted">No past appointments yet.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Doctor</th><th>Date</th><th>Time</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($past as $a):
                $badges = ['completed' => 'success', 'cancelled' => 'secondary', 'no_show' => 'danger', 'booked' => 'warning'];
                $cls = $badges[$a['status']] ?? 'secondary';
                // A still-`booked` row in this list is a past date the doctor never
                // closed out — say so rather than labelling it "booked" alongside
                // genuinely resolved visits.
                $label = $a['status'] === 'booked' ? 'not recorded' : str_replace('_', ' ', $a['status']);
            ?>
                <tr>
                    <td><?= htmlspecialchars($a['doctor_name'], ENT_QUOTES) ?></td>
                    <td class="mono"><?= htmlspecialchars($a['appointment_date'], ENT_QUOTES) ?></td>
                    <td class="mono"><?= date('g:i A', strtotime($a['start_time'])) ?></td>
                    <td><span class="badge text-bg-<?= $cls ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

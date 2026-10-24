<?php use Core\Router; ?>
<p class="eyebrow mb-1">Day view</p>
<h1 class="h3 mb-1"><?= htmlspecialchars($doctor['name'], ENT_QUOTES) ?> &mdash; <?= date('l, j F Y', strtotime($date)) ?></h1>
<p class="text-muted"><?= htmlspecialchars($doctor['specialization_name'], ENT_QUOTES) ?></p>
<a href="<?= Router::url($backUrl) ?>" class="btn btn-sm btn-outline-tc mb-3">&larr; Back to week view</a>

<div class="card card-tc">
    <div class="card-body p-0">
        <?php if ($data['full_day_leave']): ?>
            <div class="p-4 text-center text-muted">On leave all day &mdash; <?= htmlspecialchars($data['full_day_leave'], ENT_QUOTES) ?></div>
        <?php elseif (empty($data['slots']) && empty($data['leave_windows'])): ?>
            <div class="p-4 text-center text-muted">No availability set for this day.</div>
        <?php else: ?>
            <table class="table mb-0 align-middle">
                <thead><tr><th style="width:160px;">Time</th><th>Status</th><th>Patient</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($data['leave_windows'] as $lw): ?>
                    <tr class="table-light">
                        <td class="mono"><?= date('g:i A', strtotime($lw['start'])) ?>–<?= date('g:i A', strtotime($lw['end'])) ?></td>
                        <td colspan="3">Blocked &mdash; <?= htmlspecialchars($lw['reason'], ENT_QUOTES) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php foreach ($data['slots'] as $slot): ?>
                    <tr>
                        <td class="mono"><?= date('g:i A', strtotime($slot['start'])) ?>–<?= date('g:i A', strtotime($slot['end'])) ?>
                            <?php if ($slot['source'] === 'extra_hours'): ?><span class="tag-extra">Extra</span><?php endif; ?>
                        </td>
                        <?php if ($slot['status'] === 'booked'): $appt = $slot['appointment']; ?>
                            <td><span class="badge text-bg-success">Booked</span></td>
                            <td>
                                <?= htmlspecialchars($appt['patient_name'], ENT_QUOTES) ?>
                                <?= $appt['is_returning'] ? '<span class="badge-returning ms-1">Returning · ' . (int) $appt['visit_count'] . '</span>' : '<span class="badge-new ms-1">New</span>' ?>
                            </td>
                            <td>
                                <?php if (!$isAdminView): ?>
                                    <a href="<?= Router::url('/doctor/appointments/' . $appt['id']) ?>" class="btn btn-sm btn-outline-tc">Open</a>
                                <?php endif; ?>
                            </td>
                        <?php elseif ($slot['status'] === 'past'): ?>
                            <td><span class="badge text-bg-secondary">Past</span></td><td colspan="2" class="text-muted">Slot has elapsed</td>
                        <?php else: ?>
                            <td><span class="badge text-bg-light text-dark border">Open</span></td><td colspan="2" class="text-muted">Not yet booked</td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

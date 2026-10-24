<?php use Core\Router; ?>
<p class="eyebrow mb-1">Operational oversight</p>
<h1 class="h3 mb-1">Today's board</h1>
<p class="text-muted mb-3">All doctors, one day, side by side.</p>

<div class="d-flex justify-content-between align-items-center mb-3">
    <a class="btn btn-sm btn-outline-tc" href="<?= Router::url('/admin/board?date=' . $prevDate) ?>">&larr; Previous day</a>
    <form method="get" action="<?= Router::url('/admin/board') ?>" class="d-flex gap-2 align-items-center">
        <input type="date" name="date" class="form-control form-control-sm" value="<?= htmlspecialchars($date, ENT_QUOTES) ?>">
        <button class="btn btn-sm btn-tc">Go</button>
    </form>
    <a class="btn btn-sm btn-outline-tc" href="<?= Router::url('/admin/board?date=' . $nextDate) ?>">Next day &rarr;</a>
</div>
<h6 class="mb-3"><?= date('l, j F Y', strtotime($date)) ?></h6>

<div class="d-flex flex-wrap gap-3 mb-3 small">
    <span><span class="legend-swatch" style="background:var(--paper);border:1px solid var(--teal-400);"></span>Open</span>
    <span><span class="legend-swatch" style="background:var(--teal-600);"></span>Booked</span>
    <span><span class="legend-swatch" style="background:repeating-linear-gradient(135deg,#efece4,#efece4 3px,#e6e2d6 3px,#e6e2d6 6px);border:1px solid #d8d3c4;"></span>Leave</span>
    <span><span class="legend-swatch" style="background:#fff;border-left:3px solid var(--amber);border-top:1px solid var(--line);border-bottom:1px solid var(--line);border-right:1px solid var(--line);"></span>Extra hours</span>
</div>

<?php if (empty($board)): ?>
    <p class="text-muted">No active doctors.</p>
<?php endif; ?>

<?php foreach ($board as $row): $doctor = $row['doctor']; $data = $row['data']; ?>
    <div class="board-row">
        <div class="board-doctor">
            <div class="name"><a href="<?= Router::url('/admin/schedule/' . $doctor['id'] . '?week=' . $date) ?>" class="text-decoration-none"><?= htmlspecialchars($doctor['name'], ENT_QUOTES) ?></a></div>
            <div class="spec"><?= htmlspecialchars($doctor['specialization_name'], ENT_QUOTES) ?></div>
        </div>
        <div class="board-strip">
            <?php if ($data['full_day_leave']): ?>
                <div class="slot-chip slot-blocked">On leave &mdash; <?= htmlspecialchars($data['full_day_leave'], ENT_QUOTES) ?></div>
            <?php elseif (empty($data['slots']) && empty($data['leave_windows'])): ?>
                <div class="day-empty">No hours today</div>
            <?php else: ?>
                <?php foreach ($data['leave_windows'] as $lw): ?>
                    <div class="slot-chip slot-blocked">
                        <span class="slot-time"><?= date('g:i A', strtotime($lw['start'])) ?>–<?= date('g:i A', strtotime($lw['end'])) ?></span>
                        <?= htmlspecialchars($lw['reason'], ENT_QUOTES) ?>
                    </div>
                <?php endforeach; ?>
                <?php foreach ($data['slots'] as $slot):
                    $stateClass = $slot['status'] === 'booked' ? 'slot-booked' : ($slot['status'] === 'past' ? 'slot-past' : 'slot-open');
                    $extraClass = $slot['source'] === 'extra_hours' ? ' slot-extra' : '';
                ?>
                    <div class="slot-chip <?= $stateClass . $extraClass ?>">
                        <span class="slot-time"><?= date('g:i A', strtotime($slot['start'])) ?><?= $extraClass ? '<span class="tag-extra">Extra</span>' : '' ?></span>
                        <?php if ($slot['status'] === 'booked'): $appt = $slot['appointment']; ?>
                            <span class="slot-patient"><?= htmlspecialchars($appt['patient_name'], ENT_QUOTES) ?></span>
                            <?= $appt['is_returning'] ? '<span class="badge-returning">Ret. · ' . (int) $appt['visit_count'] . '</span>' : '<span class="badge-new">New</span>' ?>
                        <?php else: ?>
                            <?= $slot['status'] === 'past' ? 'Past' : 'Open' ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>

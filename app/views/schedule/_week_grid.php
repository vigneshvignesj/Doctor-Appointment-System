<?php
/**
 * Shared weekly schedule grid — reused by the doctor's own "My Schedule" view
 * and the admin doctor drill-down (per FEATURES.md: "don't build two bespoke
 * renderers"). Expects:
 *   $days         array<{date: Y-m-d, data: SlotEngine::daySlots() result}>
 *   $weekDates    the 7 Y-m-d dates covered (Mon..Sun)
 *   $prevWeek/$nextWeek  Y-m-d anchors for the nav arrows
 *   $weekUrlBase  e.g. '/doctor/dashboard' or "/admin/schedule/{$doctor['id']}"
 *   $dayUrlBase   e.g. '/doctor/schedule/day' or "/admin/schedule/{$doctor['id']}/day"
 *   $apptLinkBase string|null — booked chips link to "{$apptLinkBase}{id}" when set (doctor view); plain text when null (admin view, which manages appointments from the global list instead)
 */
use Core\Router;
$today = date('Y-m-d');
?>
<div class="d-flex justify-content-between align-items-center mb-2">
    <a class="btn btn-sm btn-outline-tc" href="<?= Router::url($weekUrlBase . '?week=' . $prevWeek) ?>">&larr; Previous week</a>
    <div class="fw-semibold"><?= htmlspecialchars(date('d M', strtotime($weekDates[0])) . ' – ' . date('d M Y', strtotime($weekDates[6])), ENT_QUOTES) ?></div>
    <a class="btn btn-sm btn-outline-tc" href="<?= Router::url($weekUrlBase . '?week=' . $nextWeek) ?>">Next week &rarr;</a>
</div>

<div class="d-flex flex-wrap gap-3 mb-3 small">
    <span><span class="legend-swatch" style="background:var(--paper);border:1px solid var(--teal-400);"></span>Open</span>
    <span><span class="legend-swatch" style="background:var(--teal-600);"></span>Booked</span>
    <span><span class="legend-swatch" style="background:repeating-linear-gradient(135deg,#efece4,#efece4 3px,#e6e2d6 3px,#e6e2d6 6px);border:1px solid #d8d3c4;"></span>Leave / blocked</span>
    <span><span class="legend-swatch" style="background:#fff;border-left:3px solid var(--amber);border-top:1px solid var(--line);border-bottom:1px solid var(--line);border-right:1px solid var(--line);"></span>Extra hours (one-off)</span>
</div>

<div class="week-grid">
<?php foreach ($days as $day):
    $date = $day['date'];
    $data = $day['data'];
    $isToday = $date === $today;
?>
    <div class="week-day-col<?= $isToday ? ' is-today' : '' ?>">
        <a class="week-day-head text-decoration-none" href="<?= Router::url($dayUrlBase . '/' . $date) ?>">
            <div class="dow"><?= date('D', strtotime($date)) ?></div>
            <div class="dom"><?= date('j M', strtotime($date)) ?></div>
        </a>
        <div class="week-day-body">
            <?php if ($data['full_day_leave']): ?>
                <div class="slot-chip slot-blocked text-center">On leave<br><span class="small"><?= htmlspecialchars($data['full_day_leave'], ENT_QUOTES) ?></span></div>
            <?php else: ?>
                <?php foreach ($data['leave_windows'] as $lw): ?>
                    <div class="slot-chip slot-blocked">
                        <span class="slot-time"><?= date('g:i A', strtotime($lw['start'])) ?>–<?= date('g:i A', strtotime($lw['end'])) ?></span>
                        <?= htmlspecialchars($lw['reason'], ENT_QUOTES) ?>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($data['slots']) && empty($data['leave_windows'])): ?>
                    <div class="day-empty">No hours set</div>
                <?php endif; ?>

                <?php foreach ($data['slots'] as $slot):
                    $stateClass = $slot['status'] === 'booked' ? 'slot-booked' : ($slot['status'] === 'past' ? 'slot-past' : 'slot-open');
                    $extraClass = $slot['source'] === 'extra_hours' ? ' slot-extra' : '';
                    $timeLabel = date('g:i A', strtotime($slot['start']));
                ?>
                    <?php if ($slot['status'] === 'booked'):
                        $appt = $slot['appointment'];
                        $badge = $appt['is_returning']
                            ? '<span class="badge-returning">Returning · ' . (int) $appt['visit_count'] . '</span>'
                            : '<span class="badge-new">New</span>';
                        $inner = '<span class="slot-time">' . $timeLabel . ($extraClass ? '<span class="tag-extra">Extra</span>' : '') . '</span>'
                            . '<span class="slot-patient">' . htmlspecialchars($appt['patient_name'], ENT_QUOTES) . '</span>' . $badge;
                    ?>
                        <?php if ($apptLinkBase): ?>
                            <a class="slot-chip <?= $stateClass . $extraClass ?>" href="<?= Router::url($apptLinkBase . $appt['id']) ?>"><?= $inner ?></a>
                        <?php else: ?>
                            <div class="slot-chip <?= $stateClass . $extraClass ?>"><?= $inner ?></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="slot-chip <?= $stateClass . $extraClass ?>">
                            <span class="slot-time"><?= $timeLabel ?><?= $extraClass ? '<span class="tag-extra">Extra</span>' : '' ?></span>
                            <?= $slot['status'] === 'past' ? 'Past' : 'Open' ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>

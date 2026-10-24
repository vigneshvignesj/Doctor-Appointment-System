<?php use Core\Csrf; use Core\Router; ?>
<p class="eyebrow mb-1">Schedule drill-down</p>
<h1 class="h3 mb-1"><?= htmlspecialchars($doctor['name'], ENT_QUOTES) ?></h1>
<p class="text-muted mb-4"><?= htmlspecialchars($doctor['specialization_name'], ENT_QUOTES) ?> &middot; Admin override view</p>

<?php
$weekUrlBase = '/admin/schedule/' . $doctor['id'];
$dayUrlBase = '/admin/schedule/' . $doctor['id'] . '/day';
$apptLinkBase = null;
include __DIR__ . '/../../schedule/_week_grid.php';
?>

<?php $days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday']; ?>

<div class="row g-4 mt-2">
    <div class="col-lg-6">
        <div class="card card-tc mb-4">
            <div class="card-header">Weekly template (admin override)</div>
            <div class="card-body">
                <?php if (empty($availability)): ?>
                    <p class="text-muted">No weekly hours set.</p>
                <?php else: ?>
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Day</th><th>Hours</th><th>Slot</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($availability as $row): ?>
                            <tr>
                                <td><?= $days[(int) $row['day_of_week']] ?></td>
                                <td class="mono"><?= date('g:i A', strtotime($row['start_time'])) ?>–<?= date('g:i A', strtotime($row['end_time'])) ?></td>
                                <td><?= (int) $row['slot_duration_minutes'] ?> min</td>
                                <td>
                                    <?php $locked = $availabilityLocks['windows'][(int) $row['id']] ?? 0; ?>
                                    <?php if ($locked > 0): ?>
                                        <span class="text-muted small" title="Cancel or reassign them first."><?= $locked ?> booked</span>
                                    <?php else: ?>
                                        <form method="post" action="<?= Router::url('/admin/schedule/availability/' . $row['id'] . '/delete') ?>" onsubmit="return confirm('Remove this window?');">
                                            <?= Csrf::field() ?><button class="btn btn-sm btn-outline-danger">Remove</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="text-muted small mb-0">Admin override stops short of stranding a patient: a window with live bookings inside it can't be removed until those appointments are cancelled or reassigned.</p>
                <?php endif; ?>
                <hr>
                <form method="post" action="<?= Router::url('/admin/schedule/' . $doctor['id'] . '/availability') ?>" class="row g-2">
                    <?= Csrf::field() ?>
                    <div class="col-6">
                        <label class="form-label small">Day</label>
                        <select name="day_of_week" class="form-select form-select-sm" required>
                            <?php foreach ($days as $i => $name): ?><option value="<?= $i ?>"><?= $name ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Slot length (min)</label>
                        <input type="number" name="slot_duration_minutes" class="form-control form-control-sm" value="20" min="5" max="120" required>
                    </div>
                    <div class="col-6"><label class="form-label small">Start</label><input type="time" name="start_time" class="form-control form-control-sm" required></div>
                    <div class="col-6"><label class="form-label small">End</label><input type="time" name="end_time" class="form-control form-control-sm" required></div>
                    <div class="col-12"><button class="btn btn-tc btn-sm mt-2">Add window</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-tc mb-4">
            <div class="card-header">Exceptions (admin override)</div>
            <div class="card-body">
                <?php if (empty($exceptions)): ?>
                    <p class="text-muted">No upcoming exceptions.</p>
                <?php else: ?>
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Date</th><th>Type</th><th>Hours</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($exceptions as $row): ?>
                            <tr>
                                <td class="mono"><?= htmlspecialchars($row['exception_date'], ENT_QUOTES) ?></td>
                                <td><?= $row['type'] === 'leave' ? '<span class="badge text-bg-secondary">Leave</span>' : '<span class="tag-extra">Extra hours</span>' ?></td>
                                <td class="mono small"><?= $row['start_time'] ? date('g:i A', strtotime($row['start_time'])) . '–' . date('g:i A', strtotime($row['end_time'])) : 'Full day' ?></td>
                                <td>
                                    <?php $locked = $availabilityLocks['exceptions'][(int) $row['id']] ?? 0; ?>
                                    <?php if ($locked > 0): ?>
                                        <span class="text-muted small" title="Cancel or reassign them first."><?= $locked ?> booked</span>
                                    <?php else: ?>
                                        <form method="post" action="<?= Router::url('/admin/schedule/exception/' . $row['id'] . '/delete') ?>" onsubmit="return confirm('Remove this exception?');">
                                            <?= Csrf::field() ?><button class="btn btn-sm btn-outline-danger">Remove</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                <hr>
                <form method="post" action="<?= Router::url('/admin/schedule/' . $doctor['id'] . '/exception') ?>" class="row g-2">
                    <?= Csrf::field() ?>
                    <div class="col-6"><label class="form-label small">Date</label><input type="date" name="exception_date" class="form-control form-control-sm" required></div>
                    <div class="col-6">
                        <label class="form-label small">Type</label>
                        <select name="type" class="form-select form-select-sm" required>
                            <option value="leave">Leave</option>
                            <option value="extra_hours">Extra hours</option>
                        </select>
                    </div>
                    <div class="col-6"><label class="form-label small">Start (blank = full day)</label><input type="time" name="start_time" class="form-control form-control-sm"></div>
                    <div class="col-6"><label class="form-label small">End</label><input type="time" name="end_time" class="form-control form-control-sm"></div>
                    <div class="col-12"><label class="form-label small">Reason</label><input type="text" name="reason" class="form-control form-control-sm"></div>
                    <div class="col-12"><p class="text-muted small mb-0">Leave can't be marked over time patients are already booked into — cancel or reassign those appointments first.</p></div>
                    <div class="col-12"><button class="btn btn-tc btn-sm mt-2">Add exception</button></div>
                </form>
            </div>
        </div>
    </div>
</div>

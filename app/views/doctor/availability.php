<?php use Core\Csrf; use Core\Router; ?>
<p class="eyebrow mb-1">My availability</p>
<h1 class="h3 mb-4">Weekly hours &amp; exceptions</h1>

<?php $days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday']; ?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card card-tc mb-4">
            <div class="card-header">Recurring weekly template</div>
            <div class="card-body">
                <?php if (empty($availability)): ?>
                    <p class="text-muted">No weekly hours set yet — add your first window below.</p>
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
                                        <span class="text-muted small" title="Cancel or reschedule them first — removing this window would drop them off the schedule."><?= $locked ?> booked</span>
                                    <?php else: ?>
                                        <form method="post" action="<?= Router::url('/doctor/availability/' . $row['id'] . '/delete') ?>" onsubmit="return confirm('Remove this window?');">
                                            <?= Csrf::field() ?>
                                            <button class="btn btn-sm btn-outline-danger">Remove</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="text-muted small mb-0">A window with live bookings inside it can't be removed — the appointments would disappear from the schedule while the patients still hold a confirmation. Cancel or reschedule them first.</p>
                <?php endif; ?>
                <hr>
                <form method="post" action="<?= Router::url('/doctor/availability') ?>" class="row g-2">
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
                    <div class="col-6">
                        <label class="form-label small">Start time</label>
                        <input type="time" name="start_time" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small">End time</label>
                        <input type="time" name="end_time" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-tc btn-sm mt-2">Add window</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card card-tc mb-4">
            <div class="card-header">Upcoming exceptions</div>
            <div class="card-body">
                <?php if (empty($exceptions)): ?>
                    <p class="text-muted">No leave or extra-hours exceptions coming up.</p>
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
                                        <span class="text-muted small" title="Cancel or reschedule them first — removing this clinic would drop them off the schedule."><?= $locked ?> booked</span>
                                    <?php else: ?>
                                        <form method="post" action="<?= Router::url('/doctor/exceptions/' . $row['id'] . '/delete') ?>" onsubmit="return confirm('Remove this exception?');">
                                            <?= Csrf::field() ?>
                                            <button class="btn btn-sm btn-outline-danger">Remove</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                <hr>
                <form method="post" action="<?= Router::url('/doctor/exceptions') ?>" class="row g-2" id="exceptionForm">
                    <?= Csrf::field() ?>
                    <div class="col-6">
                        <label class="form-label small">Date</label>
                        <input type="date" name="exception_date" class="form-control form-control-sm" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Type</label>
                        <select name="type" class="form-select form-select-sm" id="exceptionType" required>
                            <option value="leave">Leave (block time)</option>
                            <option value="extra_hours">Extra hours (add time)</option>
                        </select>
                    </div>
                    <div class="col-6 exception-time"><label class="form-label small">Start (leave blank = full day)</label><input type="time" name="start_time" class="form-control form-control-sm"></div>
                    <div class="col-6 exception-time"><label class="form-label small">End</label><input type="time" name="end_time" class="form-control form-control-sm"></div>
                    <div class="col-12">
                        <label class="form-label small">Reason</label>
                        <input type="text" name="reason" class="form-control form-control-sm" placeholder="e.g. Conference, Weekend clinic">
                    </div>
                    <div class="col-12">
                        <p class="text-muted small mb-0">Leave can't be marked over time patients are already booked into — cancel or reschedule those appointments first.</p>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-tc btn-sm mt-2">Add exception</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php use Core\Csrf; use Core\Router; ?>
<p class="eyebrow mb-1"><?= htmlspecialchars($doctor['specialization_name'], ENT_QUOTES) ?></p>
<h1 class="h3 mb-1"><?= htmlspecialchars($doctor['name'], ENT_QUOTES) ?></h1>
<p class="text-muted"><?= htmlspecialchars($doctor['qualification'], ENT_QUOTES) ?> &middot; Fee: &#8377;<?= number_format((float) $doctor['consultation_fee']) ?></p>
<?php if ($doctor['bio']): ?><p><?= htmlspecialchars($doctor['bio'], ENT_QUOTES) ?></p><?php endif; ?>

<div class="card card-tc mt-3">
    <div class="card-header">Choose a date</div>
    <div class="card-body">
        <form method="get" action="<?= Router::url('/patient/doctors/' . $doctor['id']) ?>" class="row g-2 mb-4">
            <div class="col-auto">
                <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date, ENT_QUOTES) ?>" min="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-auto"><button class="btn btn-outline-tc">View slots</button></div>
        </form>

        <h6 class="mb-3"><?= date('l, j F Y', strtotime($date)) ?></h6>

        <?php if ($data['full_day_leave']): ?>
            <p class="text-muted">Dr. <?= htmlspecialchars(explode(' ', $doctor['name'])[1] ?? $doctor['name'], ENT_QUOTES) ?> is on leave this day &mdash; <?= htmlspecialchars($data['full_day_leave'], ENT_QUOTES) ?>. Try another date.</p>
        <?php else: ?>
            <?php $openSlots = array_filter($data['slots'], fn($s) => $s['status'] === 'open'); ?>
            <?php if (empty($openSlots)): ?>
                <p class="text-muted">No open slots on this date. Try another day.</p>
            <?php else: ?>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($openSlots as $slot): ?>
                        <form method="post" action="<?= Router::url('/patient/book') ?>">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="doctor_id" value="<?= $doctor['id'] ?>">
                            <input type="hidden" name="date" value="<?= htmlspecialchars($date, ENT_QUOTES) ?>">
                            <input type="hidden" name="start_time" value="<?= $slot['start'] ?>">
                            <input type="hidden" name="end_time" value="<?= $slot['end'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-tc" onclick="return confirm('Book <?= date('g:i A', strtotime($slot['start'])) ?> on <?= htmlspecialchars($date, ENT_QUOTES) ?>?');">
                                <?= date('g:i A', strtotime($slot['start'])) ?>
                                <?= $slot['source'] === 'extra_hours' ? '<span class="tag-extra">Extra</span>' : '' ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

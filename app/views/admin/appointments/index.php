<?php use Core\Csrf; use Core\Router; ?>
<p class="eyebrow mb-1">Clinic-wide</p>
<h1 class="h3 mb-4">Appointments</h1>

<form method="get" action="<?= Router::url('/admin/appointments') ?>" class="row g-2 mb-3">
    <div class="col-md-3"><input type="text" name="q" class="form-control" placeholder="Patient or doctor name" value="<?= htmlspecialchars($filters['search'], ENT_QUOTES) ?>"></div>
    <div class="col-md-2">
        <select name="doctor_id" class="form-select">
            <option value="0">All doctors</option>
            <?php foreach ($doctors as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $filters['doctor_id'] == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name'], ENT_QUOTES) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select name="status" class="form-select">
            <option value="">Any status</option>
            <?php foreach (['booked','completed','cancelled','no_show'] as $st): ?>
                <option value="<?= $st ?>" <?= $filters['status'] === $st ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$st)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2"><input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filters['date_from'], ENT_QUOTES) ?>" title="From"></div>
    <div class="col-md-2"><input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filters['date_to'], ENT_QUOTES) ?>" title="To"></div>
    <div class="col-md-1"><button class="btn btn-outline-tc w-100">Go</button></div>
</form>

<div class="table-responsive">
    <table class="table table-tc align-middle">
        <thead><tr><th>Date</th><th>Time</th><th>Doctor</th><th>Patient</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($appointments as $a):
            $badges = ['booked' => 'primary', 'completed' => 'success', 'cancelled' => 'secondary', 'no_show' => 'danger'];
            $cls = $badges[$a['status']] ?? 'secondary';
        ?>
            <tr>
                <td class="mono"><?= htmlspecialchars($a['appointment_date'], ENT_QUOTES) ?></td>
                <td class="mono"><?= date('g:i A', strtotime($a['start_time'])) ?></td>
                <td><?= htmlspecialchars($a['doctor_name'], ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars($a['patient_name'], ENT_QUOTES) ?></td>
                <td><span class="badge text-bg-<?= $cls ?>"><?= htmlspecialchars(str_replace('_',' ',$a['status']), ENT_QUOTES) ?></span></td>
                <td class="d-flex gap-1">
                    <?php if ($a['status'] === 'booked'): ?>
                        <a href="<?= Router::url('/admin/appointments/' . $a['id'] . '/reassign') ?>" class="btn btn-sm btn-outline-tc">Reassign</a>
                        <form method="post" action="<?= Router::url('/admin/appointments/' . $a['id'] . '/cancel') ?>" onsubmit="return confirm('Cancel this appointment?');">
                            <?= Csrf::field() ?><button class="btn btn-sm btn-outline-danger">Cancel</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($appointments)): ?><tr><td colspan="6" class="text-muted text-center py-4">No appointments match.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$totalPages = (int) ceil($total / $perPage);
if ($totalPages > 1):
?>
<nav><ul class="pagination pagination-sm">
    <?php for ($p = 1; $p <= min($totalPages, 20); $p++): ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="?q=<?= urlencode($filters['search']) ?>&doctor_id=<?= $filters['doctor_id'] ?>&status=<?= $filters['status'] ?>&date_from=<?= $filters['date_from'] ?>&date_to=<?= $filters['date_to'] ?>&page=<?= $p ?>"><?= $p ?></a></li>
    <?php endfor; ?>
</ul></nav>
<?php endif; ?>

<?php use Core\Router; ?>
<p class="eyebrow mb-1">Oversight</p>
<h1 class="h3 mb-4">Audit log &amp; reports</h1>

<h5 class="mb-3">Per-doctor volume &amp; outcomes</h5>
<div class="table-responsive mb-5">
    <table class="table table-tc align-middle">
        <thead><tr><th>Doctor</th><th>Specialization</th><th>Total</th><th>Booked</th><th>Completed</th><th>Cancelled</th><th>No-show</th><th>No-show rate</th></tr></thead>
        <tbody>
        <?php foreach ($report as $r): $total = (int) $r['total']; $rate = $total ? round(($r['no_show'] / $total) * 100) : 0; ?>
            <tr>
                <td><?= htmlspecialchars($r['doctor_name'], ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars($r['specialization_name'], ENT_QUOTES) ?></td>
                <td><?= $total ?></td>
                <td><?= (int) $r['booked'] ?></td>
                <td><?= (int) $r['completed'] ?></td>
                <td><?= (int) $r['cancelled'] ?></td>
                <td><?= (int) $r['no_show'] ?></td>
                <td><?= $rate ?>%</td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($report)): ?><tr><td colspan="8" class="text-muted text-center py-4">No data yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Audit log</h5>
    <form method="get" action="<?= Router::url('/admin/audit-log') ?>" class="d-flex gap-2">
        <input type="text" name="action" class="form-control form-control-sm" placeholder="Filter by action" value="<?= htmlspecialchars($action, ENT_QUOTES) ?>">
        <button class="btn btn-sm btn-outline-tc">Filter</button>
    </form>
</div>

<div class="table-responsive">
    <table class="table table-sm align-middle">
        <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
            <tr>
                <td class="mono small"><?= htmlspecialchars($log['created_at'], ENT_QUOTES) ?></td>
                <td class="small"><?= htmlspecialchars($log['actor_name'] ?? 'system', ENT_QUOTES) ?> <span class="text-muted">(<?= htmlspecialchars($log['actor_role'] ?? '—', ENT_QUOTES) ?>)</span></td>
                <td class="small"><span class="badge text-bg-light text-dark border"><?= htmlspecialchars($log['action'], ENT_QUOTES) ?></span></td>
                <td class="small"><?= htmlspecialchars($log['details'] ?? '', ENT_QUOTES) ?></td>
                <td class="small mono"><?= htmlspecialchars($log['ip_address'] ?? '', ENT_QUOTES) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($logs)): ?><tr><td colspan="5" class="text-muted text-center py-4">No log entries.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$totalPages = (int) ceil($total / $perPage);
if ($totalPages > 1):
?>
<nav><ul class="pagination pagination-sm">
    <?php for ($p = 1; $p <= min($totalPages, 20); $p++): ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="?action=<?= urlencode($action) ?>&page=<?= $p ?>"><?= $p ?></a></li>
    <?php endfor; ?>
</ul></nav>
<?php endif; ?>

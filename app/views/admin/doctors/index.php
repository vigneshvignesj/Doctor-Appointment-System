<?php use Core\Csrf; use Core\Router; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <p class="eyebrow mb-1">Roster</p>
        <h1 class="h3 mb-0">Doctors</h1>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= Router::url('/admin/doctors/import') ?>" class="btn btn-outline-tc btn-sm">Bulk import (Excel)</a>
        <a href="<?= Router::url('/admin/doctors/create') ?>" class="btn btn-tc btn-sm">Add doctor</a>
    </div>
</div>

<form method="get" action="<?= Router::url('/admin/doctors') ?>" class="row g-2 mb-3">
    <div class="col-md-4"><input type="text" name="q" class="form-control" placeholder="Search name, email, staff code" value="<?= htmlspecialchars($search, ENT_QUOTES) ?>"></div>
    <div class="col-md-3">
        <select name="specialization_id" class="form-select">
            <option value="0">All specializations</option>
            <?php foreach ($specializations as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $specializationId == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name'], ENT_QUOTES) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <select name="status" class="form-select">
            <option value="">Any status</option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
    </div>
    <div class="col-md-2"><button class="btn btn-outline-tc w-100">Filter</button></div>
</form>

<div class="table-responsive">
    <table class="table table-tc align-middle">
        <thead><tr><th>Name</th><th>Staff code</th><th>Specialization</th><th>Fee</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($doctors as $doc): ?>
            <tr>
                <td><?= htmlspecialchars($doc['name'], ENT_QUOTES) ?><div class="text-muted small"><?= htmlspecialchars($doc['email'], ENT_QUOTES) ?></div></td>
                <td class="mono"><?= htmlspecialchars($doc['staff_code'], ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars($doc['specialization_name'], ENT_QUOTES) ?></td>
                <td>&#8377;<?= number_format((float) $doc['consultation_fee']) ?></td>
                <td><span class="badge text-bg-<?= $doc['status'] === 'active' ? 'success' : 'secondary' ?>"><?= htmlspecialchars($doc['status'], ENT_QUOTES) ?></span></td>
                <td class="d-flex gap-2 flex-wrap">
                    <a href="<?= Router::url('/admin/schedule/' . $doc['id']) ?>" class="btn btn-sm btn-outline-tc">Schedule</a>
                    <a href="<?= Router::url('/admin/doctors/' . $doc['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                    <?php if ($doc['status'] === 'active'): ?>
                        <form method="post" action="<?= Router::url('/admin/doctors/' . $doc['id'] . '/deactivate') ?>" onsubmit="return confirm('Deactivate this doctor?');">
                            <?= Csrf::field() ?><button class="btn btn-sm btn-outline-danger">Deactivate</button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="<?= Router::url('/admin/doctors/' . $doc['id'] . '/activate') ?>">
                            <?= Csrf::field() ?><button class="btn btn-sm btn-outline-success">Activate</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($doctors)): ?><tr><td colspan="6" class="text-muted text-center py-4">No doctors found.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$totalPages = (int) ceil($total / $perPage);
if ($totalPages > 1):
?>
<nav><ul class="pagination pagination-sm">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="?q=<?= urlencode($search) ?>&specialization_id=<?= $specializationId ?>&status=<?= $status ?>&page=<?= $p ?>"><?= $p ?></a></li>
    <?php endfor; ?>
</ul></nav>
<?php endif; ?>

<?php use Core\Csrf; use Core\Router; ?>
<p class="eyebrow mb-1">Accounts</p>
<h1 class="h3 mb-4">Patients</h1>

<form method="get" action="<?= Router::url('/admin/patients') ?>" class="row g-2 mb-3">
    <div class="col-md-5"><input type="text" name="q" class="form-control" placeholder="Search name, email, phone" value="<?= htmlspecialchars($search, ENT_QUOTES) ?>"></div>
    <div class="col-md-3">
        <select name="status" class="form-select">
            <option value="">Any status</option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
        </select>
    </div>
    <div class="col-md-2"><button class="btn btn-outline-tc w-100">Filter</button></div>
</form>

<div class="table-responsive">
    <table class="table table-tc align-middle">
        <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($patients as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['name'], ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars($p['email'], ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars($p['phone'] ?? '—', ENT_QUOTES) ?></td>
                <td><span class="badge text-bg-<?= $p['status'] === 'active' ? 'success' : 'secondary' ?>"><?= htmlspecialchars($p['status'], ENT_QUOTES) ?></span></td>
                <td>
                    <?php if ($p['status'] === 'active'): ?>
                        <form method="post" action="<?= Router::url('/admin/patients/' . $p['id'] . '/suspend') ?>" onsubmit="return confirm('Suspend this patient?');">
                            <?= Csrf::field() ?><button class="btn btn-sm btn-outline-danger">Suspend</button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="<?= Router::url('/admin/patients/' . $p['id'] . '/reactivate') ?>">
                            <?= Csrf::field() ?><button class="btn btn-sm btn-outline-success">Reactivate</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($patients)): ?><tr><td colspan="5" class="text-muted text-center py-4">No patients found.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$totalPages = (int) ceil($total / $perPage);
if ($totalPages > 1):
?>
<nav><ul class="pagination pagination-sm">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="?q=<?= urlencode($search) ?>&status=<?= $status ?>&page=<?= $p ?>"><?= $p ?></a></li>
    <?php endfor; ?>
</ul></nav>
<?php endif; ?>

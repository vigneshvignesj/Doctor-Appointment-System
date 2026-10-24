<?php use Core\Csrf; use Core\Router; ?>
<p class="eyebrow mb-1">Roster</p>
<h1 class="h3 mb-4">Specializations</h1>

<div class="row g-4">
    <div class="col-lg-7">
        <?php foreach ($specializations as $s): ?>
            <form id="spec-form-<?= $s['id'] ?>" method="post" action="<?= Router::url('/admin/specializations/' . $s['id']) ?>"></form>
        <?php endforeach; ?>
        <div class="table-responsive">
            <table class="table table-tc align-middle">
                <thead><tr><th>Name</th><th>Description</th><th>Doctors</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($specializations as $s): $fid = 'spec-form-' . $s['id']; ?>
                    <tr>
                        <td>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Core\Csrf::token(), ENT_QUOTES) ?>" form="<?= $fid ?>">
                            <input type="text" name="name" form="<?= $fid ?>" class="form-control form-control-sm" value="<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>">
                        </td>
                        <td><input type="text" name="description" form="<?= $fid ?>" class="form-control form-control-sm" value="<?= htmlspecialchars($s['description'] ?? '', ENT_QUOTES) ?>"></td>
                        <td><?= (int) $s['doctor_count'] ?></td>
                        <td class="d-flex gap-1">
                            <button class="btn btn-sm btn-outline-tc" form="<?= $fid ?>">Save</button>
                            <?php if ((int) $s['doctor_count'] === 0): ?>
                                <form method="post" action="<?= Router::url('/admin/specializations/' . $s['id'] . '/delete') ?>" class="d-inline" onsubmit="return confirm('Delete <?= htmlspecialchars($s['name'], ENT_QUOTES) ?>?');">
                                    <?= Csrf::field() ?>
                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($specializations)): ?><tr><td colspan="4" class="text-muted text-center py-4">None yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card card-tc">
            <div class="card-header">Add specialization</div>
            <div class="card-body">
                <form method="post" action="<?= Router::url('/admin/specializations') ?>">
                    <?= Csrf::field() ?>
                    <div class="mb-2">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control">
                    </div>
                    <button class="btn btn-tc">Add</button>
                </form>
            </div>
        </div>
    </div>
</div>

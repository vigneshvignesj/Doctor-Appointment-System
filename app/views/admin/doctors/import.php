<?php use Core\Csrf; use Core\Router; ?>
<p class="eyebrow mb-1">Roster</p>
<h1 class="h3 mb-4">Bulk import doctors</h1>

<div class="card card-tc mb-4">
    <div class="card-body">
        <p>Upload an <code>.xlsx</code> file with a header row followed by one doctor per row, in this column order:</p>
        <p class="mono small">name | email | specialization | qualification | consultation_fee</p>
        <p class="text-muted small mb-0">Specialization must match an existing specialization name exactly (see <a href="<?= Router::url('/admin/specializations') ?>">Specializations</a>). Rows with an email that's already registered are skipped, not duplicated.</p>
    </div>
</div>

<form method="post" action="<?= Router::url('/admin/doctors/import') ?>" enctype="multipart/form-data" class="card card-tc mb-4">
    <div class="card-body d-flex gap-2 align-items-end flex-wrap">
        <?= Csrf::field() ?>
        <div>
            <label class="form-label">Roster file (.xlsx)</label>
            <input type="file" name="roster" accept=".xlsx" class="form-control" required>
        </div>
        <button class="btn btn-tc">Import</button>
    </div>
</form>

<?php if ($result): ?>
    <div class="card card-tc mb-4">
        <div class="card-header">Import results &mdash; <?= count($result['created']) ?> created, <?= count($result['skipped']) ?> skipped</div>
        <div class="card-body">
            <?php if (!empty($result['created'])): ?>
                <table class="table table-sm">
                    <thead><tr><th>Name</th><th>Email</th><th>Staff code</th><th>Initial password</th></tr></thead>
                    <tbody>
                    <?php foreach ($result['created'] as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['name'], ENT_QUOTES) ?></td>
                            <td><?= htmlspecialchars($row['email'], ENT_QUOTES) ?></td>
                            <td class="mono"><?= htmlspecialchars($row['staff_code'], ENT_QUOTES) ?></td>
                            <td class="mono"><?= htmlspecialchars($row['password'], ENT_QUOTES) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="small text-muted">These passwords are shown once — share them with each doctor directly.</p>
            <?php endif; ?>
            <?php if (!empty($result['skipped'])): ?>
                <p class="fw-semibold mb-1">Skipped rows</p>
                <ul class="small text-muted">
                    <?php foreach ($result['skipped'] as $s): ?><li><?= htmlspecialchars($s, ENT_QUOTES) ?></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

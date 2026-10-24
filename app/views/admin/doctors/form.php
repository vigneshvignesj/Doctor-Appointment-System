<?php use Core\Csrf; use Core\Router; use Core\View; ?>
<p class="eyebrow mb-1">Roster</p>
<h1 class="h3 mb-4"><?= $doctor ? 'Edit doctor' : 'Add doctor' ?></h1>

<div class="card card-tc">
    <div class="card-body">
        <form method="post" action="<?= $doctor ? Router::url('/admin/doctors/' . $doctor['id']) : Router::url('/admin/doctors') ?>">
            <?= Csrf::field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Full name</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($doctor['name'] ?? View::old('name'), ENT_QUOTES) ?>" <?= $doctor ? '' : 'required' ?>>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($doctor['email'] ?? View::old('email'), ENT_QUOTES) ?>" <?= $doctor ? 'disabled' : 'required' ?>>
                    <?php if ($doctor): ?><input type="hidden" name="email" value="<?= htmlspecialchars($doctor['email'], ENT_QUOTES) ?>"><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($doctor['phone'] ?? View::old('phone'), ENT_QUOTES) ?>" <?= $doctor ? '' : 'required' ?>>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Specialization</label>
                    <select name="specialization_id" class="form-select" required>
                        <?php foreach ($specializations as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= (($doctor['specialization_id'] ?? null) == $s['id']) ? 'selected' : '' ?>><?= htmlspecialchars($s['name'], ENT_QUOTES) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Qualification</label>
                    <input type="text" name="qualification" class="form-control" value="<?= htmlspecialchars($doctor['qualification'] ?? View::old('qualification'), ENT_QUOTES) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Consultation fee (&#8377;)</label>
                    <input type="number" step="1" min="0" name="consultation_fee" class="form-control" value="<?= htmlspecialchars((string) ($doctor['consultation_fee'] ?? View::old('consultation_fee', '500')), ENT_QUOTES) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Default slot length (min)</label>
                    <input type="number" min="5" max="120" name="slot_duration" class="form-control" value="<?= htmlspecialchars((string) ($doctor['default_slot_duration_minutes'] ?? '20'), ENT_QUOTES) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Bio (shown to patients)</label>
                    <textarea name="bio" class="form-control" rows="2"><?= htmlspecialchars($doctor['bio'] ?? '', ENT_QUOTES) ?></textarea>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-tc"><?= $doctor ? 'Save changes' : 'Add doctor' ?></button>
                <a href="<?= Router::url('/admin/doctors') ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
            <?php if (!$doctor): ?>
                <p class="small text-muted mt-3">A staff code and initial password will be generated automatically and shown once after saving — share them with the doctor directly.</p>
            <?php endif; ?>
        </form>
    </div>
</div>

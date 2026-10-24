<?php use Core\Router; ?>
<p class="eyebrow mb-1">Find a doctor</p>
<h1 class="h3 mb-4">Browse doctors</h1>

<form method="get" action="<?= Router::url('/patient/doctors') ?>" class="row g-2 mb-4">
    <div class="col-md-6">
        <input type="text" name="q" class="form-control" placeholder="Search by name or specialization" value="<?= htmlspecialchars($search, ENT_QUOTES) ?>">
    </div>
    <div class="col-md-4">
        <select name="specialization_id" class="form-select">
            <option value="0">All specializations</option>
            <?php foreach ($specializations as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $specializationId == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name'], ENT_QUOTES) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2"><button class="btn btn-tc w-100">Search</button></div>
</form>

<div class="row g-3">
    <?php if (empty($doctors)): ?>
        <p class="text-muted">No doctors match your search.</p>
    <?php endif; ?>
    <?php foreach ($doctors as $doc): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card card-tc h-100">
                <div class="card-body">
                    <h5 class="mb-0"><?= htmlspecialchars($doc['name'], ENT_QUOTES) ?></h5>
                    <p class="text-muted small mb-2"><?= htmlspecialchars($doc['specialization_name'], ENT_QUOTES) ?> &middot; <?= htmlspecialchars($doc['qualification'], ENT_QUOTES) ?></p>
                    <p class="small mb-3">Consultation fee: <strong>&#8377;<?= number_format((float) $doc['consultation_fee']) ?></strong></p>
                    <a href="<?= Router::url('/patient/doctors/' . $doc['id']) ?>" class="btn btn-tc btn-sm">View profile &amp; book</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

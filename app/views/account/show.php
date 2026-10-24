<?php use Core\Csrf; use Core\Router; ?>
<p class="eyebrow mb-1">My account</p>
<h1 class="h3 mb-4">Account settings</h1>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card card-tc">
            <div class="card-header">Profile</div>
            <div class="card-body">
                <form method="post" action="<?= Router::url('/account/profile') ?>">
                    <?= Csrf::field() ?>
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name'], ENT_QUOTES) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" value="<?= htmlspecialchars($user['email'], ENT_QUOTES) ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '', ENT_QUOTES) ?>">
                    </div>
                    <?php if ($user['staff_code']): ?>
                        <div class="mb-3">
                            <label class="form-label">Staff code</label>
                            <input type="text" class="form-control mono" value="<?= htmlspecialchars($user['staff_code'], ENT_QUOTES) ?>" disabled>
                        </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-tc">Save changes</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card card-tc">
            <div class="card-header">Change password</div>
            <div class="card-body">
                <form method="post" action="<?= Router::url('/account/password') ?>">
                    <?= Csrf::field() ?>
                    <div class="mb-3">
                        <label class="form-label">Current password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New password</label>
                        <input type="password" name="new_password" class="form-control" minlength="8" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm new password</label>
                        <input type="password" name="new_password_confirm" class="form-control" minlength="8" required>
                    </div>
                    <button type="submit" class="btn btn-outline-tc">Update password</button>
                </form>
            </div>
        </div>
    </div>
</div>

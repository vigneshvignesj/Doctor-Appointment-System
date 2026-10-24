<?php use Core\Csrf; use Core\Router; use Core\View; ?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <p class="eyebrow mb-1">New patient</p>
        <h1 class="h3 mb-4">Create your account</h1>
        <div class="card card-tc">
            <div class="card-body p-4">
                <form method="post" action="<?= Router::url('/register') ?>">
                    <?= Csrf::field() ?>
                    <div class="mb-3">
                        <label class="form-label">Full name</label>
                        <input type="text" name="name" class="form-control" value="<?= View::old('name') ?>" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= View::old('email') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= View::old('phone') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" minlength="8" required>
                        <div class="form-text">At least 8 characters.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm password</label>
                        <input type="password" name="password_confirm" class="form-control" minlength="8" required>
                    </div>
                    <button type="submit" class="btn btn-tc w-100">Create account &amp; sign in</button>
                </form>
            </div>
        </div>
        <p class="text-center mt-3 text-muted">Already have an account? <a href="<?= Router::url('/login') ?>">Sign in</a></p>
    </div>
</div>

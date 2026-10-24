<?php use Core\Csrf; use Core\Router; use Core\View; ?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <p class="eyebrow mb-1">Welcome back</p>
        <h1 class="h3 mb-4">Sign in to TealCare</h1>
        <div class="card card-tc">
            <div class="card-body p-4">
                <form method="post" action="<?= Router::url('/login') ?>">
                    <?= Csrf::field() ?>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= View::old('email') ?>" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-tc w-100">Sign in</button>
                </form>
            </div>
        </div>
        <p class="text-center mt-3 text-muted">New patient? <a href="<?= Router::url('/register') ?>">Register here</a></p>
        <div class="card card-tc mt-4">
            <div class="card-body small">
                <p class="fw-semibold mb-2">Demo credentials</p>
                <table class="table table-sm mb-0">
                    <tr><td>Super Admin</td><td class="mono">admin@tealcare.test</td><td class="mono">Admin@123</td></tr>
                    <tr><td>Doctor</td><td class="mono">doctor1@tealcare.test</td><td class="mono">Doctor@123</td></tr>
                    <tr><td>Patient</td><td class="mono">patient1@tealcare.test</td><td class="mono">Patient@123</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

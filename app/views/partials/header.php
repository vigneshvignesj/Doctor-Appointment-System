<?php
use Core\Auth;
use Core\Router;
use Core\View;
$currentUser = Auth::user();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TealCare — Doctor Appointment Booking</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&amp;family=Inter:wght@400;500;600;700&amp;family=IBM+Plex+Mono:wght@400;500;600&amp;display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= Router::url('/assets/css/style.css') ?>" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-tc">
    <div class="container-fluid px-3 px-lg-4">
        <a class="navbar-brand" href="<?= Router::url($currentUser ? \App\Controllers\AuthController::roleHome($currentUser['role']) : '/login') ?>">
            <span class="brand-mark">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M6 3v6a6 6 0 0 0 12 0V3" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/>
                    <circle cx="18" cy="15" r="3" stroke="#fff" stroke-width="1.8"/>
                    <path d="M6 9v2a2 2 0 0 0 2 2h1" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </span>
            <span>TealCare<small>Doctor Appointment Booking</small></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= Router::url('/admin/dashboard') ?>">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= Router::url('/admin/doctors') ?>">Doctors</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= Router::url('/admin/specializations') ?>">Specializations</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= Router::url('/admin/board') ?>">Today's Board</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= Router::url('/admin/appointments') ?>">Appointments</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= Router::url('/admin/patients') ?>">Patients</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= Router::url('/admin/stress-test') ?>">Stress Test</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= Router::url('/admin/audit-log') ?>">Audit &amp; Reports</a></li>
                <?php elseif ($currentUser && $currentUser['role'] === 'doctor'): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= Router::url('/doctor/dashboard') ?>">My Schedule</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= Router::url('/doctor/availability') ?>">My Availability</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= Router::url('/doctor/appointments') ?>">Appointment History</a></li>
                <?php elseif ($currentUser): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= Router::url('/patient/dashboard') ?>">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= Router::url('/patient/doctors') ?>">Find a Doctor</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= Router::url('/patient/appointments') ?>">My Appointments</a></li>
                <?php endif; ?>
                <?php if ($currentUser): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= Router::url('/account') ?>"><?= htmlspecialchars($currentUser['name'], ENT_QUOTES) ?></a></li>
                    <li class="nav-item">
                        <form method="post" action="<?= Router::url('/logout') ?>" class="d-inline">
                            <?= \Core\Csrf::field() ?>
                            <button type="submit" class="btn btn-link nav-link" style="border:0;">Logout</button>
                        </form>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= Router::url('/login') ?>">Login</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= Router::url('/register') ?>">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<div class="container py-4">
    <?php $flashSuccess = View::flash('success'); $flashError = View::flash('error'); ?>
    <?php if ($flashSuccess): ?>
        <div class="alert alert-success"><?= htmlspecialchars($flashSuccess, ENT_QUOTES) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($flashError, ENT_QUOTES) ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['errors'])): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($_SESSION['errors'] as $err): ?>
                    <li><?= htmlspecialchars($err, ENT_QUOTES) ?></li>
                <?php endforeach; unset($_SESSION['errors']); ?>
            </ul>
        </div>
    <?php endif; ?>

<?php

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Core\Auth;
use Core\Csrf;
use Core\Router;
use Core\View;

class AccountController
{
    public function show(): void
    {
        Auth::requireLogin();
        View::render('account/show', ['user' => Auth::user()]);
    }

    public function updateProfile(): void
    {
        Auth::requireLogin();
        if (!Csrf::verifyRequest()) {
            Router::redirect('/account');
        }

        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if ($name === '') {
            View::flash('error', 'Name is required.');
            Router::redirect('/account');
        }

        User::updateProfile(Auth::id(), $name, $phone);
        AuditLog::record(Auth::id(), Auth::role(), 'profile_updated');
        View::flash('success', 'Profile updated.');
        Router::redirect('/account');
    }

    public function updatePassword(): void
    {
        Auth::requireLogin();
        if (!Csrf::verifyRequest()) {
            Router::redirect('/account');
        }

        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['new_password_confirm'] ?? '';
        $user = Auth::user();

        if (!password_verify($current, $user['password_hash'])) {
            View::flash('error', 'Current password is incorrect.');
            Router::redirect('/account');
        }
        if (strlen($new) < 8 || $new !== $confirm) {
            View::flash('error', 'New password must be at least 8 characters and match the confirmation.');
            Router::redirect('/account');
        }

        User::updatePassword(Auth::id(), password_hash($new, PASSWORD_DEFAULT));
        AuditLog::record(Auth::id(), Auth::role(), 'password_changed');
        View::flash('success', 'Password updated.');
        Router::redirect('/account');
    }
}

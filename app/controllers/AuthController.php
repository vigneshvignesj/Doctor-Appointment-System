<?php

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Core\Auth;
use Core\Csrf;
use Core\Router;
use Core\Validator;
use Core\View;

class AuthController
{
    public static function roleHome(string $role): string
    {
        return match ($role) {
            'admin' => '/admin/dashboard',
            'doctor' => '/doctor/dashboard',
            default => '/patient/dashboard',
        };
    }

    public function showLogin(): void
    {
        if (Auth::check()) {
            Router::redirect(self::roleHome(Auth::role()));
        }
        View::render('auth/login');
    }

    public function login(): void
    {
        if (!Csrf::verifyRequest()) {
            View::flash('error', 'Your session expired, please try again.');
            Router::redirect('/login');
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $user = Auth::attempt($email, $password);

        if (!$user) {
            View::flash('error', 'Incorrect email/password, or this account has been suspended.');
            $_SESSION['old'] = ['email' => $email];
            Router::redirect('/login');
        }

        AuditLog::record((int) $user['id'], $user['role'], 'login');
        Router::redirect(self::roleHome($user['role']));
    }

    public function logout(): void
    {
        if (Auth::check()) {
            AuditLog::record(Auth::id(), Auth::role(), 'logout');
        }
        Auth::logout();
        Router::redirect('/login');
    }

    public function showRegister(): void
    {
        if (Auth::check()) {
            Router::redirect(self::roleHome(Auth::role()));
        }
        View::render('auth/register');
    }

    public function register(): void
    {
        if (!Csrf::verifyRequest()) {
            Router::redirect('/register');
        }

        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'password_confirm' => $_POST['password_confirm'] ?? '',
        ];

        $validator = new Validator($data);
        $validator->required('name')
            ->required('email')->email('email')->unique('email', 'users', 'email')
            ->required('phone')
            ->required('password')->minLength('password', 8)
            ->matches('password_confirm', 'password', 'Passwords do not match.');

        if ($validator->fails()) {
            $_SESSION['errors'] = $validator->firstErrors();
            $_SESSION['old'] = $data;
            Router::redirect('/register');
        }

        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $id = User::createPatient($data['name'], $data['email'], $hash, $data['phone']);
        AuditLog::record($id, 'patient', 'self_registered');

        $user = User::find($id);
        Auth::login($user);
        View::flash('success', 'Welcome to TealCare! Your account is ready — you can book an appointment right away.');
        Router::redirect('/patient/dashboard');
    }
}

<?php

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Core\Auth;
use Core\Csrf;
use Core\Router;
use Core\View;

class AdminPatientController
{
    public function index(): void
    {
        Auth::requireRole('admin');
        $search = trim($_GET['q'] ?? '');
        $status = $_GET['status'] ?? '';
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = User::listPatients($search, $status, $page, 15);

        View::render('admin/patients/index', [
            'patients' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 15,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function suspend(string $id): void
    {
        Auth::requireRole('admin');
        if (!Csrf::verifyRequest()) {
            Router::redirect('/admin/patients');
        }
        User::setStatus((int) $id, 'suspended');
        AuditLog::record(Auth::id(), 'admin', 'patient_suspended', "Patient #{$id}");
        View::flash('success', 'Patient account suspended.');
        Router::redirect('/admin/patients');
    }

    public function reactivate(string $id): void
    {
        Auth::requireRole('admin');
        if (!Csrf::verifyRequest()) {
            Router::redirect('/admin/patients');
        }
        User::setStatus((int) $id, 'active');
        AuditLog::record(Auth::id(), 'admin', 'patient_reactivated', "Patient #{$id}");
        View::flash('success', 'Patient account reactivated.');
        Router::redirect('/admin/patients');
    }
}

<?php

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\Specialization;
use Core\Auth;
use Core\Csrf;
use Core\Router;
use Core\View;

class AdminSpecializationController
{
    public function index(): void
    {
        Auth::requireRole('admin');
        View::render('admin/specializations/index', ['specializations' => Specialization::allWithDoctorCounts()]);
    }

    public function store(): void
    {
        Auth::requireRole('admin');
        if (!Csrf::verifyRequest()) {
            Router::redirect('/admin/specializations');
        }
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($name === '') {
            View::flash('error', 'Name is required.');
            Router::redirect('/admin/specializations');
        }
        Specialization::create($name, $description);
        AuditLog::record(Auth::id(), 'admin', 'specialization_created', $name);
        View::flash('success', 'Specialization added.');
        Router::redirect('/admin/specializations');
    }

    public function update(string $id): void
    {
        Auth::requireRole('admin');
        if (!Csrf::verifyRequest()) {
            Router::redirect('/admin/specializations');
        }
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($name === '') {
            View::flash('error', 'Name is required.');
            Router::redirect('/admin/specializations');
        }
        Specialization::update((int) $id, $name, $description);
        AuditLog::record(Auth::id(), 'admin', 'specialization_updated', $name);
        View::flash('success', 'Specialization updated.');
        Router::redirect('/admin/specializations');
    }

    public function delete(string $id): void
    {
        Auth::requireRole('admin');
        if (!Csrf::verifyRequest()) {
            Router::redirect('/admin/specializations');
        }
        if (Specialization::inUse((int) $id)) {
            View::flash('error', 'Cannot delete — doctors are still assigned to this specialization.');
            Router::redirect('/admin/specializations');
        }
        Specialization::delete((int) $id);
        AuditLog::record(Auth::id(), 'admin', 'specialization_deleted', "#{$id}");
        View::flash('success', 'Specialization removed.');
        Router::redirect('/admin/specializations');
    }
}

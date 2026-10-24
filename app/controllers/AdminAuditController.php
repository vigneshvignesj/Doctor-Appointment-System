<?php

namespace App\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use Core\Auth;
use Core\View;

class AdminAuditController
{
    public function index(): void
    {
        Auth::requireRole('admin');
        $action = trim($_GET['action'] ?? '');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = AuditLog::search($action, $page, 30);

        View::render('admin/audit', [
            'logs' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 30,
            'action' => $action,
            'report' => Appointment::reportByDoctor(),
        ]);
    }
}

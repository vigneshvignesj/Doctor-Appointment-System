<?php

namespace App\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\User;
use Core\Auth;
use Core\View;

class AdminController
{
    public function dashboard(): void
    {
        Auth::requireRole('admin');

        View::render('admin/dashboard', [
            'activeDoctors' => Doctor::countActive(),
            'totalPatients' => User::countByRole('patient'),
            'suspendedPatients' => User::countByStatus('patient', 'suspended'),
            'todayCount' => Appointment::countToday(),
            'bookedCount' => Appointment::countByStatus('booked'),
            'completedCount' => Appointment::countByStatus('completed'),
            'cancelledCount' => Appointment::countByStatus('cancelled'),
            'noShowCount' => Appointment::countByStatus('no_show'),
            'todaysBoard' => Appointment::todaysBoard(),
            'doctors' => Doctor::allActive(),
        ]);
    }
}

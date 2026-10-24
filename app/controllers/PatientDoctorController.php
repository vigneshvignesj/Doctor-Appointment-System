<?php

namespace App\Controllers;

use App\Models\Doctor;
use App\Models\Specialization;
use Core\Auth;
use Core\Router;
use Core\SlotEngine;
use Core\View;

class PatientDoctorController
{
    public function index(): void
    {
        Auth::requireRole('patient');
        $search = trim($_GET['q'] ?? '');
        $specializationId = (int) ($_GET['specialization_id'] ?? 0);

        View::render('patient/doctors', [
            'doctors' => Doctor::browse($search, $specializationId),
            'specializations' => Specialization::all(),
            'search' => $search,
            'specializationId' => $specializationId,
        ]);
    }

    public function show(string $id): void
    {
        Auth::requireRole('patient');
        $doctor = Doctor::find((int) $id);
        if (!$doctor || $doctor['status'] !== 'active') {
            View::flash('error', 'That doctor is not currently accepting bookings.');
            Router::redirect('/patient/doctors');
        }

        $date = $_GET['date'] ?? date('Y-m-d');
        if ($date < date('Y-m-d')) {
            $date = date('Y-m-d');
        }

        View::render('patient/doctor_profile', [
            'doctor' => $doctor,
            'date' => $date,
            'data' => SlotEngine::daySlots((int) $id, $date),
        ]);
    }
}

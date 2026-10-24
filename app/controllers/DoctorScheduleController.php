<?php

namespace App\Controllers;

use App\Models\Doctor;
use Core\Auth;
use Core\Router;
use Core\SlotEngine;
use Core\View;

class DoctorScheduleController
{
    private function currentDoctor(): array
    {
        Auth::requireRole('doctor');
        $doctor = Doctor::findByUserId(Auth::id());
        if (!$doctor) {
            http_response_code(403);
            require __DIR__ . '/../views/errors/403.php';
            exit;
        }
        return $doctor;
    }

    public function week(): void
    {
        $doctor = $this->currentDoctor();
        $anchor = $_GET['week'] ?? date('Y-m-d');
        $weekDates = SlotEngine::weekDates($anchor);
        $days = [];
        foreach ($weekDates as $date) {
            $days[] = ['date' => $date, 'data' => SlotEngine::daySlots((int) $doctor['id'], $date)];
        }

        View::render('doctor/week', [
            'doctor' => $doctor,
            'days' => $days,
            'weekDates' => $weekDates,
            'prevWeek' => date('Y-m-d', strtotime($weekDates[0] . ' -7 days')),
            'nextWeek' => date('Y-m-d', strtotime($weekDates[0] . ' +7 days')),
            'isAdminView' => false,
        ]);
    }

    public function day(string $date): void
    {
        $doctor = $this->currentDoctor();
        View::render('schedule/day', [
            'doctor' => $doctor,
            'date' => $date,
            'data' => SlotEngine::daySlots((int) $doctor['id'], $date),
            'backUrl' => "/doctor/dashboard?week={$date}",
            'isAdminView' => false,
        ]);
    }
}

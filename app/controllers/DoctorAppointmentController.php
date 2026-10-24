<?php

namespace App\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Doctor;
use Core\Auth;
use Core\Csrf;
use Core\Router;
use Core\SlotEngine;
use Core\View;

class DoctorAppointmentController
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

    public function history(): void
    {
        $doctor = $this->currentDoctor();
        View::render('doctor/history', [
            'doctor' => $doctor,
            'appointments' => Appointment::historyForDoctor((int) $doctor['id']),
        ]);
    }

    public function show(string $id): void
    {
        $doctor = $this->currentDoctor();
        $appointment = Appointment::find((int) $id);
        if (!$appointment || (int) $appointment['doctor_id'] !== (int) $doctor['id']) {
            Router::redirect('/doctor/dashboard');
        }

        $history = SlotEngine::visitHistory((int) $doctor['id'], (int) $appointment['patient_id'], $appointment['appointment_date']);
        $pastVisits = Appointment::visitHistoryRows((int) $doctor['id'], (int) $appointment['patient_id'], (int) $id);

        View::render('doctor/appointment_detail', [
            'appointment' => $appointment,
            'history' => $history,
            'pastVisits' => $pastVisits,
        ]);
    }

    public function complete(string $id): void
    {
        $doctor = $this->currentDoctor();
        $appointment = Appointment::find((int) $id);
        if (!$appointment || (int) $appointment['doctor_id'] !== (int) $doctor['id']) {
            Router::redirect('/doctor/dashboard');
        }
        if (!Csrf::verifyRequest()) {
            Router::redirect("/doctor/appointments/{$id}");
        }

        if ($appointment['appointment_date'] > date('Y-m-d')) {
            View::flash('error', 'This appointment is in the future — it can only be cancelled, not marked completed.');
            Router::redirect("/doctor/appointments/{$id}");
        }

        $notes = trim($_POST['notes'] ?? '');
        if (Appointment::markCompleted((int) $id, $notes)) {
            AuditLog::record(Auth::id(), 'doctor', 'appointment_completed', "Appointment #{$id}");
            View::flash('success', 'Marked as completed.');
        } else {
            View::flash('error', 'Could not mark this appointment as completed.');
        }
        Router::redirect("/doctor/appointments/{$id}");
    }

    public function noShow(string $id): void
    {
        $doctor = $this->currentDoctor();
        $appointment = Appointment::find((int) $id);
        if (!$appointment || (int) $appointment['doctor_id'] !== (int) $doctor['id']) {
            Router::redirect('/doctor/dashboard');
        }
        if (!Csrf::verifyRequest()) {
            Router::redirect("/doctor/appointments/{$id}");
        }

        if ($appointment['appointment_date'] > date('Y-m-d')) {
            View::flash('error', 'This appointment is in the future — it can only be cancelled, not marked no-show.');
            Router::redirect("/doctor/appointments/{$id}");
        }

        if (Appointment::markNoShow((int) $id)) {
            AuditLog::record(Auth::id(), 'doctor', 'appointment_no_show', "Appointment #{$id}");
            View::flash('success', 'Marked as no-show.');
        } else {
            View::flash('error', 'Could not mark this appointment as no-show.');
        }
        Router::redirect("/doctor/appointments/{$id}");
    }

    public function cancel(string $id): void
    {
        $doctor = $this->currentDoctor();
        $appointment = Appointment::find((int) $id);
        if (!$appointment || (int) $appointment['doctor_id'] !== (int) $doctor['id']) {
            Router::redirect('/doctor/dashboard');
        }
        if (!Csrf::verifyRequest()) {
            Router::redirect("/doctor/appointments/{$id}");
        }

        $ok = SlotEngine::cancel((int) $id, 'doctor');
        if ($ok) {
            AuditLog::record(Auth::id(), 'doctor', 'appointment_cancelled', "Appointment #{$id}");
            View::flash('success', 'Appointment cancelled.');
            Router::redirect('/doctor/dashboard');
        }
        View::flash('error', 'Could not cancel this appointment.');
        Router::redirect("/doctor/appointments/{$id}");
    }
}

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

class AdminAppointmentController
{
    public function index(): void
    {
        Auth::requireRole('admin');
        $filters = [
            'search' => trim($_GET['q'] ?? ''),
            'doctor_id' => (int) ($_GET['doctor_id'] ?? 0),
            'status' => $_GET['status'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = Appointment::search($filters, $page, 20);

        View::render('admin/appointments/index', [
            'appointments' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 20,
            'filters' => $filters,
            'doctors' => Doctor::allActive(),
        ]);
    }

    public function cancel(string $id): void
    {
        Auth::requireRole('admin');
        if (!Csrf::verifyRequest()) {
            Router::redirect('/admin/appointments');
        }
        $ok = SlotEngine::cancel((int) $id, 'admin');
        if ($ok) {
            AuditLog::record(Auth::id(), 'admin', 'appointment_cancelled', "Appointment #{$id}");
            View::flash('success', 'Appointment cancelled and the slot freed for rebooking.');
        } else {
            View::flash('error', 'Could not cancel — it may already be cancelled or completed.');
        }
        Router::redirect('/admin/appointments');
    }

    public function reassignForm(string $id): void
    {
        Auth::requireRole('admin');
        $appointment = Appointment::find((int) $id);
        if (!$appointment || $appointment['status'] !== 'booked') {
            View::flash('error', 'Only currently booked appointments can be reassigned.');
            Router::redirect('/admin/appointments');
        }
        View::render('admin/appointments/reassign', [
            'appointment' => $appointment,
            'doctors' => Doctor::allActive(),
        ]);
    }

    public function reassign(string $id): void
    {
        Auth::requireRole('admin');
        $appointment = Appointment::find((int) $id);
        if (!$appointment || $appointment['status'] !== 'booked') {
            Router::redirect('/admin/appointments');
        }
        if (!Csrf::verifyRequest()) {
            Router::redirect("/admin/appointments/{$id}/reassign");
        }

        $newDoctorId = (int) ($_POST['doctor_id'] ?? 0);
        $date = $_POST['date'] ?? '';
        $start = $_POST['start_time'] ?? '';
        $end = $_POST['end_time'] ?? '';
        if ($newDoctorId <= 0 || $date === '' || $start === '' || $end === '') {
            View::flash('error', 'Pick a doctor, date and slot.');
            Router::redirect("/admin/appointments/{$id}/reassign");
        }

        // The appointment being moved is excluded from the one-per-doctor-per-day
        // rule, so reassigning a patient to a different slot with the *same*
        // doctor on the same date isn't rejected by their own existing booking.
        $bookResult = SlotEngine::book($newDoctorId, (int) $appointment['patient_id'], $date, $start, $end, (int) $id);
        if (!$bookResult['success']) {
            View::flash('error', $bookResult['error'] === 'already_booked'
                ? 'That patient already has another appointment with this doctor on that date — pick a different date or doctor.'
                : 'That slot was just taken — pick another.');
            Router::redirect("/admin/appointments/{$id}/reassign");
        }

        // 'admin_reassign' rather than plain 'admin' so the patient dashboard's
        // "the clinic cancelled on you" alert doesn't fire for a move that
        // immediately replaced the appointment with a new one.
        SlotEngine::cancel((int) $id, 'admin_reassign');
        AuditLog::record(Auth::id(), 'admin', 'appointment_reassigned', "Appointment #{$id} -> new appointment #{$bookResult['appointment_id']} with doctor #{$newDoctorId}");
        View::flash('success', 'Appointment reassigned to the new doctor/slot.');
        Router::redirect('/admin/appointments');
    }
}

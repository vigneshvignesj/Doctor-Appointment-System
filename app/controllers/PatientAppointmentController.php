<?php

namespace App\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Doctor;
use Core\Auth;
use Core\Csrf;
use Core\Mailer;
use Core\Router;
use Core\SlotEngine;
use Core\View;

class PatientAppointmentController
{
    public function dashboard(): void
    {
        Auth::requireRole('patient');
        View::render('patient/dashboard', [
            'upcoming' => Appointment::upcomingForPatient(Auth::id()),
            'todayAlerts' => Appointment::todayAlertsForPatient(Auth::id()),
        ]);
    }

    public function myAppointments(): void
    {
        Auth::requireRole('patient');
        View::render('patient/appointments', [
            'upcoming' => Appointment::upcomingForPatient(Auth::id()),
            'past' => Appointment::pastForPatient(Auth::id()),
        ]);
    }

    public function book(): void
    {
        Auth::requireRole('patient');
        if (!Csrf::verifyRequest()) {
            Router::redirect('/patient/doctors');
        }

        $doctorId = (int) ($_POST['doctor_id'] ?? 0);
        $date = $_POST['date'] ?? '';
        $start = $_POST['start_time'] ?? '';
        $end = $_POST['end_time'] ?? '';

        if ($doctorId <= 0 || $date === '' || $start === '' || $end === '') {
            View::flash('error', 'Choose a date and slot to book.');
            Router::redirect("/patient/doctors/{$doctorId}");
        }

        // Re-checked here, not just in PatientDoctorController::show(): the
        // doctor may have been deactivated while this booking page was open.
        $doctor = Doctor::find($doctorId);
        if (!$doctor || $doctor['status'] !== 'active') {
            View::flash('error', 'That doctor is no longer accepting bookings — please choose another.');
            Router::redirect('/patient/doctors');
        }

        $result = SlotEngine::book($doctorId, Auth::id(), $date, $start, $end);
        if (!$result['success']) {
            $message = $result['error'] === 'already_booked'
                ? 'You already have an appointment with this doctor on that day — cancel or reschedule it first.'
                : 'That slot was just booked by someone else — pick another below.';
            View::flash('error', $message);
            Router::redirect("/patient/doctors/{$doctorId}?date={$date}");
        }

        AuditLog::record(Auth::id(), 'patient', 'appointment_booked', "Appointment #{$result['appointment_id']} with {$doctor['name']} on {$date} {$start}");

        $patient = Auth::user();
        Mailer::send(
            $patient['email'],
            $patient['name'],
            'Appointment confirmed — TealCare Clinic',
            "<p>Your appointment with <strong>{$doctor['name']}</strong> ({$doctor['specialization_name']}) is confirmed.</p>"
            . "<p>Date: {$date}<br>Time: " . date('g:i A', strtotime($start)) . "</p>"
        );

        View::flash('success', 'Appointment booked.');
        Router::redirect("/patient/appointments/{$result['appointment_id']}/confirmation");
    }

    public function confirmation(string $id): void
    {
        Auth::requireRole('patient');
        $appointment = Appointment::find((int) $id);
        if (!$appointment || (int) $appointment['patient_id'] !== Auth::id()) {
            Router::redirect('/patient/dashboard');
        }
        View::render('patient/confirmation', ['appointment' => $appointment]);
    }

    public function cancel(string $id): void
    {
        Auth::requireRole('patient');
        $appointment = Appointment::find((int) $id);
        if (!$appointment || (int) $appointment['patient_id'] !== Auth::id()) {
            Router::redirect('/patient/appointments');
        }
        if (!Csrf::verifyRequest()) {
            Router::redirect('/patient/appointments');
        }

        $ok = SlotEngine::cancel((int) $id, 'patient');
        if ($ok) {
            AuditLog::record(Auth::id(), 'patient', 'appointment_cancelled', "Appointment #{$id}");
            $patient = Auth::user();
            Mailer::send(
                $patient['email'],
                $patient['name'],
                'Appointment cancelled — TealCare Clinic',
                "<p>Your appointment on {$appointment['appointment_date']} at " . date('g:i A', strtotime($appointment['start_time'])) . " with {$appointment['doctor_name']} has been cancelled.</p>"
            );
            View::flash('success', 'Appointment cancelled.');
        } else {
            View::flash('error', 'Could not cancel this appointment.');
        }
        Router::redirect('/patient/appointments');
    }

    public function rescheduleForm(string $id): void
    {
        Auth::requireRole('patient');
        $appointment = Appointment::find((int) $id);
        if (!$appointment || (int) $appointment['patient_id'] !== Auth::id() || $appointment['status'] !== 'booked') {
            Router::redirect('/patient/appointments');
        }
        if ($appointment['doctor_status'] !== 'active') {
            View::flash('error', 'This doctor is no longer taking bookings, so this appointment can\'t be moved — cancel it and book with another doctor, or call the clinic.');
            Router::redirect('/patient/appointments');
        }
        $date = $_GET['date'] ?? $appointment['appointment_date'];
        if ($date < date('Y-m-d')) {
            $date = date('Y-m-d');
        }
        View::render('patient/reschedule', [
            'appointment' => $appointment,
            'date' => $date,
            'data' => SlotEngine::daySlots((int) $appointment['doctor_id'], $date),
        ]);
    }

    public function reschedule(string $id): void
    {
        Auth::requireRole('patient');
        $appointment = Appointment::find((int) $id);
        if (!$appointment || (int) $appointment['patient_id'] !== Auth::id() || $appointment['status'] !== 'booked') {
            Router::redirect('/patient/appointments');
        }
        if ($appointment['doctor_status'] !== 'active') {
            View::flash('error', 'This doctor is no longer taking bookings, so this appointment can\'t be moved — cancel it and book with another doctor, or call the clinic.');
            Router::redirect('/patient/appointments');
        }
        if (!Csrf::verifyRequest()) {
            Router::redirect("/patient/appointments/{$id}/reschedule");
        }

        $date = $_POST['date'] ?? '';
        $start = $_POST['start_time'] ?? '';
        $end = $_POST['end_time'] ?? '';
        if ($date === '' || $start === '' || $end === '') {
            View::flash('error', 'Choose a new date and slot.');
            Router::redirect("/patient/appointments/{$id}/reschedule");
        }

        $result = SlotEngine::reschedule((int) $id, $date, $start, $end);
        if (!$result['success']) {
            $message = $result['error'] === 'already_booked'
                ? 'You already have another appointment with this doctor on that day — pick a different date.'
                : 'That slot was just taken — pick another.';
            View::flash('error', $message);
            Router::redirect("/patient/appointments/{$id}/reschedule?date={$date}");
        }

        AuditLog::record(Auth::id(), 'patient', 'appointment_rescheduled', "Appointment #{$id} -> {$date} {$start}");
        View::flash('success', 'Appointment rescheduled.');
        Router::redirect("/patient/appointments/{$id}/confirmation");
    }
}

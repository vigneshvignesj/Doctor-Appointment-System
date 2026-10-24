<?php

namespace App\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\DoctorAvailabilityException;
use App\Models\DoctorWeeklyAvailability;
use Core\Auth;
use Core\Csrf;
use Core\Router;
use Core\SlotEngine;
use Core\View;

class AdminScheduleController
{
    public function show(string $id): void
    {
        Auth::requireRole('admin');
        $doctor = Doctor::find((int) $id);
        if (!$doctor) {
            Router::redirect('/admin/doctors');
        }

        $anchor = $_GET['week'] ?? date('Y-m-d');
        $weekDates = SlotEngine::weekDates($anchor);
        $days = [];
        foreach ($weekDates as $date) {
            $days[] = ['date' => $date, 'data' => SlotEngine::daySlots((int) $id, $date)];
        }

        $availability = DoctorWeeklyAvailability::forDoctor((int) $id);
        $exceptions = DoctorAvailabilityException::forDoctor((int) $id, true);

        View::render('admin/doctors/schedule', [
            'doctor' => $doctor,
            'days' => $days,
            'weekDates' => $weekDates,
            'prevWeek' => date('Y-m-d', strtotime($weekDates[0] . ' -7 days')),
            'nextWeek' => date('Y-m-d', strtotime($weekDates[0] . ' +7 days')),
            'availability' => $availability,
            'exceptions' => $exceptions,
            'availabilityLocks' => DoctorAvailabilityController::lockCounts((int) $id, $availability, $exceptions),
            'isAdminView' => true,
        ]);
    }

    public function day(string $id, string $date): void
    {
        Auth::requireRole('admin');
        $doctor = Doctor::find((int) $id);
        if (!$doctor) {
            Router::redirect('/admin/doctors');
        }
        View::render('schedule/day', [
            'doctor' => $doctor,
            'date' => $date,
            'data' => SlotEngine::daySlots((int) $id, $date),
            'backUrl' => "/admin/schedule/{$id}?week={$date}",
            'isAdminView' => true,
        ]);
    }

    public function board(): void
    {
        Auth::requireRole('admin');
        $date = $_GET['date'] ?? date('Y-m-d');
        $doctors = Doctor::allActive();
        $board = [];
        foreach ($doctors as $doctor) {
            $board[] = ['doctor' => $doctor, 'data' => SlotEngine::daySlots((int) $doctor['id'], $date)];
        }

        View::render('admin/board', [
            'date' => $date,
            'prevDate' => date('Y-m-d', strtotime($date . ' -1 day')),
            'nextDate' => date('Y-m-d', strtotime($date . ' +1 day')),
            'board' => $board,
        ]);
    }

    public function addAvailability(string $id): void
    {
        Auth::requireRole('admin');
        if (!Csrf::verifyRequest()) {
            Router::redirect("/admin/schedule/{$id}");
        }
        $doctorId = (int) $id;
        $dow = (int) ($_POST['day_of_week'] ?? -1);
        $start = $_POST['start_time'] ?? '';
        $end = $_POST['end_time'] ?? '';
        $duration = (int) ($_POST['slot_duration_minutes'] ?? 20);

        if ($dow < 0 || $dow > 6 || $start === '' || $end === '' || $start >= $end || $duration < 5) {
            View::flash('error', 'Enter a valid day, time range (start before end) and slot length.');
            Router::redirect("/admin/schedule/{$doctorId}");
        }

        DoctorWeeklyAvailability::create($doctorId, $dow, $start, $end, $duration);
        AuditLog::record(Auth::id(), 'admin', 'availability_added', "Doctor #{$doctorId}, override");
        View::flash('success', 'Availability window added.');
        Router::redirect("/admin/schedule/{$doctorId}");
    }

    public function deleteAvailability(string $availabilityId): void
    {
        Auth::requireRole('admin');
        if (!Csrf::verifyRequest()) {
            Router::redirect('/admin/doctors');
        }
        $row = DoctorWeeklyAvailability::find((int) $availabilityId);
        if (!$row) {
            Router::redirect('/admin/doctors');
        }
        $doctorId = (int) $row['doctor_id'];

        $conflicts = Appointment::bookedInWeeklyWindow($doctorId, (int) $row['day_of_week'], $row['start_time'], $row['end_time']);
        if ($conflicts) {
            View::flash('error', count($conflicts) . ' appointment(s) are already booked inside this window — ' . Appointment::describeConflicts($conflicts) . '. Cancel or reassign them before removing it.');
            Router::redirect("/admin/schedule/{$doctorId}");
        }

        if (DoctorWeeklyAvailability::delete((int) $availabilityId)) {
            AuditLog::record(Auth::id(), 'admin', 'availability_removed', "Availability #{$availabilityId}");
            View::flash('success', 'Availability window removed.');
        } else {
            View::flash('error', 'That window could not be removed — it still has bookings inside it.');
        }
        Router::redirect("/admin/schedule/{$doctorId}");
    }

    public function addException(string $id): void
    {
        Auth::requireRole('admin');
        if (!Csrf::verifyRequest()) {
            Router::redirect("/admin/schedule/{$id}");
        }
        $doctorId = (int) $id;
        $date = $_POST['exception_date'] ?? '';
        $type = $_POST['type'] ?? '';
        $start = ($_POST['start_time'] ?? '') ?: null;
        $end = ($_POST['end_time'] ?? '') ?: null;
        $reason = trim($_POST['reason'] ?? '');

        if ($date === '' || !in_array($type, ['leave', 'extra_hours'], true)) {
            View::flash('error', 'Enter a valid date and type.');
            Router::redirect("/admin/schedule/{$doctorId}");
        }
        if ($type === 'extra_hours' && (!$start || !$end || $start >= $end)) {
            View::flash('error', 'Extra hours need a valid start/end time.');
            Router::redirect("/admin/schedule/{$doctorId}");
        }

        // Admin has override authority over a doctor's calendar, but not the
        // authority to silently strand a patient's confirmed booking — same
        // guard as the doctor's own screen (DoctorAvailabilityController).
        if ($type === 'leave') {
            $conflicts = Appointment::bookedOnDate($doctorId, $date, $start, $end);
            if ($conflicts) {
                View::flash('error', count($conflicts) . ' appointment(s) are already booked in that time — ' . Appointment::describeConflicts($conflicts) . '. Cancel or reassign them before marking leave.');
                Router::redirect("/admin/schedule/{$doctorId}");
            }
            if (DoctorAvailabilityException::createLeave($doctorId, $date, $start, $end, $reason ?: null) === 0) {
                View::flash('error', 'Leave could not be added — a patient is already booked in that time.');
                Router::redirect("/admin/schedule/{$doctorId}");
            }
        } else {
            DoctorAvailabilityException::create($doctorId, $date, $type, $start, $end, $reason ?: null);
        }

        AuditLog::record(Auth::id(), 'admin', 'exception_added', "Doctor #{$doctorId}, {$type} on {$date} (override)");
        View::flash('success', 'Exception saved.');
        Router::redirect("/admin/schedule/{$doctorId}");
    }

    public function deleteException(string $exceptionId): void
    {
        Auth::requireRole('admin');
        if (!Csrf::verifyRequest()) {
            Router::redirect('/admin/doctors');
        }
        $row = DoctorAvailabilityException::find((int) $exceptionId);
        if (!$row) {
            Router::redirect('/admin/doctors');
        }
        $doctorId = (int) $row['doctor_id'];

        if ($row['type'] === 'extra_hours') {
            $conflicts = Appointment::bookedOnDate($doctorId, $row['exception_date'], $row['start_time'], $row['end_time']);
            if ($conflicts) {
                View::flash('error', count($conflicts) . ' appointment(s) are booked in this extra-hours clinic — ' . Appointment::describeConflicts($conflicts) . '. Cancel or reassign them before removing it.');
                Router::redirect("/admin/schedule/{$doctorId}");
            }
        }

        if (DoctorAvailabilityException::delete((int) $exceptionId)) {
            AuditLog::record(Auth::id(), 'admin', 'exception_removed', "Exception #{$exceptionId}");
            View::flash('success', 'Exception removed.');
        } else {
            View::flash('error', 'That exception could not be removed — it still has bookings inside it.');
        }
        Router::redirect("/admin/schedule/{$doctorId}");
    }
}

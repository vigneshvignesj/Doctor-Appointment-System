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
use Core\View;

class DoctorAvailabilityController
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

    public function index(): void
    {
        $doctor = $this->currentDoctor();
        $availability = DoctorWeeklyAvailability::forDoctor((int) $doctor['id']);
        $exceptions = DoctorAvailabilityException::forDoctor((int) $doctor['id'], true);

        View::render('doctor/availability', [
            'doctor' => $doctor,
            'availability' => $availability,
            'exceptions' => $exceptions,
            'availabilityLocks' => self::lockCounts((int) $doctor['id'], $availability, $exceptions),
        ]);
    }

    /**
     * How many live bookings sit inside each weekly window / extra-hours
     * exception, so the view can hide its Remove control (and say why) instead
     * of offering an action the controller and the SQL guard will both refuse.
     *
     * @return array{windows: array<int,int>, exceptions: array<int,int>}
     */
    public static function lockCounts(int $doctorId, array $availability, array $exceptions): array
    {
        $locks = ['windows' => [], 'exceptions' => []];
        foreach ($availability as $row) {
            $locks['windows'][(int) $row['id']] = count(Appointment::bookedInWeeklyWindow(
                $doctorId,
                (int) $row['day_of_week'],
                $row['start_time'],
                $row['end_time']
            ));
        }
        foreach ($exceptions as $row) {
            $locks['exceptions'][(int) $row['id']] = $row['type'] === 'extra_hours'
                ? count(Appointment::bookedOnDate($doctorId, $row['exception_date'], $row['start_time'], $row['end_time']))
                : 0;
        }
        return $locks;
    }

    public function addAvailability(): void
    {
        $doctor = $this->currentDoctor();
        if (!Csrf::verifyRequest()) {
            Router::redirect('/doctor/availability');
        }

        $dow = (int) ($_POST['day_of_week'] ?? -1);
        $start = $_POST['start_time'] ?? '';
        $end = $_POST['end_time'] ?? '';
        $duration = (int) ($_POST['slot_duration_minutes'] ?? 20);

        if ($dow < 0 || $dow > 6 || $start === '' || $end === '' || $start >= $end || $duration < 5) {
            View::flash('error', 'Enter a valid day, time range (start before end) and slot length.');
            Router::redirect('/doctor/availability');
        }

        DoctorWeeklyAvailability::create((int) $doctor['id'], $dow, $start, $end, $duration);
        AuditLog::record(Auth::id(), 'doctor', 'availability_added', "Doctor #{$doctor['id']}");
        View::flash('success', 'Weekly availability window added.');
        Router::redirect('/doctor/availability');
    }

    public function deleteAvailability(string $id): void
    {
        $doctor = $this->currentDoctor();
        if (!Csrf::verifyRequest()) {
            Router::redirect('/doctor/availability');
        }
        $row = DoctorWeeklyAvailability::find((int) $id);
        if ($row && (int) $row['doctor_id'] === (int) $doctor['id']) {
            // Removing a window stops SlotEngine computing its slots, which would
            // strand any confirmed booking sitting inside it — the patient would
            // still hold a confirmation for an appointment that no longer shows
            // on anyone's schedule. Cancel/reschedule those first.
            $conflicts = Appointment::bookedInWeeklyWindow((int) $doctor['id'], (int) $row['day_of_week'], $row['start_time'], $row['end_time']);
            if ($conflicts) {
                View::flash('error', count($conflicts) . ' appointment(s) are already booked inside this window — ' . Appointment::describeConflicts($conflicts) . '. Cancel or reschedule them before removing it.');
                Router::redirect('/doctor/availability');
            }

            if (DoctorWeeklyAvailability::delete((int) $id)) {
                AuditLog::record(Auth::id(), 'doctor', 'availability_removed', "Availability #{$id}");
                View::flash('success', 'Availability window removed.');
            } else {
                View::flash('error', 'That window could not be removed — it still has bookings inside it.');
            }
        }
        Router::redirect('/doctor/availability');
    }

    public function addException(): void
    {
        $doctor = $this->currentDoctor();
        if (!Csrf::verifyRequest()) {
            Router::redirect('/doctor/availability');
        }

        $date = $_POST['exception_date'] ?? '';
        $type = $_POST['type'] ?? '';
        $start = ($_POST['start_time'] ?? '') ?: null;
        $end = ($_POST['end_time'] ?? '') ?: null;
        $reason = trim($_POST['reason'] ?? '');

        if ($date === '' || !in_array($type, ['leave', 'extra_hours'], true)) {
            View::flash('error', 'Enter a valid date and type.');
            Router::redirect('/doctor/availability');
        }
        if ($type === 'extra_hours' && (!$start || !$end || $start >= $end)) {
            View::flash('error', 'Extra hours need a valid start/end time.');
            Router::redirect('/doctor/availability');
        }

        if ($type === 'leave') {
            // Same problem from the other direction: blocking time patients are
            // already booked into would drop their appointments off the
            // schedule while their confirmation still stands.
            $conflicts = Appointment::bookedOnDate((int) $doctor['id'], $date, $start, $end);
            if ($conflicts) {
                View::flash('error', count($conflicts) . ' appointment(s) are already booked in that time — ' . Appointment::describeConflicts($conflicts) . '. Cancel or reschedule them before marking leave.');
                Router::redirect('/doctor/availability');
            }
            if (DoctorAvailabilityException::createLeave((int) $doctor['id'], $date, $start, $end, $reason ?: null) === 0) {
                View::flash('error', 'Leave could not be added — a patient is already booked in that time.');
                Router::redirect('/doctor/availability');
            }
        } else {
            DoctorAvailabilityException::create((int) $doctor['id'], $date, $type, $start, $end, $reason ?: null);
        }

        AuditLog::record(Auth::id(), 'doctor', 'exception_added', "Doctor #{$doctor['id']}, {$type} on {$date}");
        View::flash('success', $type === 'leave' ? 'Leave added.' : 'Extra-hours clinic added.');
        Router::redirect('/doctor/availability');
    }

    public function deleteException(string $id): void
    {
        $doctor = $this->currentDoctor();
        if (!Csrf::verifyRequest()) {
            Router::redirect('/doctor/availability');
        }
        $row = DoctorAvailabilityException::find((int) $id);
        if ($row && (int) $row['doctor_id'] === (int) $doctor['id']) {
            // Removing extra hours takes those slots away; removing leave only
            // ever gives slots back, so only the former can strand a booking.
            if ($row['type'] === 'extra_hours') {
                $conflicts = Appointment::bookedOnDate((int) $doctor['id'], $row['exception_date'], $row['start_time'], $row['end_time']);
                if ($conflicts) {
                    View::flash('error', count($conflicts) . ' appointment(s) are booked in this extra-hours clinic — ' . Appointment::describeConflicts($conflicts) . '. Cancel or reschedule them before removing it.');
                    Router::redirect('/doctor/availability');
                }
            }

            if (DoctorAvailabilityException::delete((int) $id)) {
                AuditLog::record(Auth::id(), 'doctor', 'exception_removed', "Exception #{$id}");
                View::flash('success', 'Exception removed.');
            } else {
                View::flash('error', 'That exception could not be removed — it still has bookings inside it.');
            }
        }
        Router::redirect('/doctor/availability');
    }
}

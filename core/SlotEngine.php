<?php

namespace Core;

use PDOException;

/**
 * Turns a doctor's recurring weekly template + one-off exceptions into
 * concrete, on-demand computed slots — nothing is pre-generated into a
 * calendar table for future dates, so an availability change is reflected
 * immediately for every patient browsing slots (see FEATURES.md's "Slot
 * computation" section). Also owns the concurrency-safe booking/cancel/
 * reschedule transactions built on slot_reservations' UNIQUE KEY mutex.
 */
class SlotEngine
{
    /**
     * The doctor's available time windows for one date, after subtracting
     * `leave` exceptions and adding `extra_hours` exceptions. Not yet
     * expanded into individual slots — see daySlots().
     *
     * @return array{full_day_leave: ?string, windows: array, leave_windows: array}
     */
    public static function windowsForDate(int $doctorId, string $date): array
    {
        $pdo = Database::connection();
        $dayOfWeek = (int) date('w', strtotime($date)); // 0 = Sunday .. 6 = Saturday

        $stmt = $pdo->prepare(
            'SELECT start_time, end_time, slot_duration_minutes FROM doctor_weekly_availability
             WHERE doctor_id = ? AND day_of_week = ? ORDER BY start_time'
        );
        $stmt->execute([$doctorId, $dayOfWeek]);
        $windows = array_map(fn($r) => [
            'start' => $r['start_time'],
            'end' => $r['end_time'],
            'duration' => (int) $r['slot_duration_minutes'],
            'source' => 'template',
        ], $stmt->fetchAll());

        $stmt = $pdo->prepare(
            'SELECT type, start_time, end_time, reason FROM doctor_availability_exceptions
             WHERE doctor_id = ? AND exception_date = ? ORDER BY start_time'
        );
        $stmt->execute([$doctorId, $date]);
        $exceptions = $stmt->fetchAll();

        $fullDayLeave = null;
        $leaveWindows = [];

        // Leave first: it can carve a template window in two, or wipe the
        // whole day, before extra_hours windows are ever added.
        foreach ($exceptions as $exception) {
            if ($exception['type'] !== 'leave') {
                continue;
            }
            if ($exception['start_time'] === null || $exception['end_time'] === null) {
                $fullDayLeave = $exception['reason'] !== null && $exception['reason'] !== '' ? $exception['reason'] : 'Leave';
                $windows = [];
                $leaveWindows[] = ['start' => '00:00:00', 'end' => '23:59:59', 'reason' => $fullDayLeave];
                continue;
            }
            $leaveWindows[] = [
                'start' => $exception['start_time'],
                'end' => $exception['end_time'],
                'reason' => $exception['reason'] !== null && $exception['reason'] !== '' ? $exception['reason'] : 'Leave',
            ];
            $windows = self::subtractInterval($windows, $exception['start_time'], $exception['end_time']);
        }

        $defaultDuration = self::defaultSlotDuration($doctorId);
        foreach ($exceptions as $exception) {
            if ($exception['type'] === 'extra_hours' && $fullDayLeave === null) {
                $windows[] = [
                    'start' => $exception['start_time'],
                    'end' => $exception['end_time'],
                    'duration' => $defaultDuration,
                    'source' => 'extra_hours',
                ];
            }
        }

        usort($windows, fn($a, $b) => strcmp($a['start'], $b['start']));

        return ['full_day_leave' => $fullDayLeave, 'windows' => $windows, 'leave_windows' => $leaveWindows];
    }

    /** Subtracts [subStart, subEnd) from a list of windows, splitting where needed. */
    private static function subtractInterval(array $windows, string $subStart, string $subEnd): array
    {
        $result = [];
        foreach ($windows as $w) {
            if ($subEnd <= $w['start'] || $subStart >= $w['end']) {
                $result[] = $w; // no overlap
                continue;
            }
            if ($subStart > $w['start']) {
                $result[] = ['start' => $w['start'], 'end' => $subStart, 'duration' => $w['duration'], 'source' => $w['source']];
            }
            if ($subEnd < $w['end']) {
                $result[] = ['start' => $subEnd, 'end' => $w['end'], 'duration' => $w['duration'], 'source' => $w['source']];
            }
            // else: window fully consumed by the leave interval, dropped.
        }
        return $result;
    }

    public static function defaultSlotDuration(int $doctorId): int
    {
        $stmt = Database::connection()->prepare('SELECT default_slot_duration_minutes FROM doctors WHERE id = ?');
        $stmt->execute([$doctorId]);
        $val = $stmt->fetchColumn();
        return $val ? (int) $val : (int) Config::get('app.default_slot_duration_minutes', 20);
    }

    /**
     * Expands windows into concrete slots, marks any slot already in the
     * past (today only) as unbookable, and joins against active/completed
     * bookings so the caller (booking page, weekly grid, today's board) gets
     * one fully-described picture of the day.
     *
     * @return array{full_day_leave: ?string, leave_windows: array, slots: array}
     */
    public static function daySlots(int $doctorId, string $date): array
    {
        $computed = self::windowsForDate($doctorId, $date);
        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');

        $slots = [];
        foreach ($computed['windows'] as $window) {
            $duration = max(5, (int) $window['duration']);
            $cursor = strtotime($date . ' ' . $window['start']);
            $end = strtotime($date . ' ' . $window['end']);
            while ($cursor !== false && $end !== false && $cursor + $duration * 60 <= $end) {
                $slotStart = date('H:i:s', $cursor);
                $slotEndTs = $cursor + $duration * 60;
                $isPast = ($date < $today) || ($date === $today && date('Y-m-d H:i:s', $cursor) < $now);
                $slots[] = [
                    'start' => $slotStart,
                    'end' => date('H:i:s', $slotEndTs),
                    'duration' => $duration,
                    'source' => $window['source'],
                    'status' => $isPast ? 'past' : 'open',
                    'appointment' => null,
                ];
                $cursor = $slotEndTs;
            }
        }

        usort($slots, fn($a, $b) => strcmp($a['start'], $b['start']));

        if (!empty($slots)) {
            $stmt = Database::connection()->prepare(
                "SELECT a.*, u.name AS patient_name, u.phone AS patient_phone, u.email AS patient_email
                 FROM appointments a
                 JOIN users u ON u.id = a.patient_id
                 WHERE a.doctor_id = ? AND a.appointment_date = ? AND a.status IN ('booked','completed','no_show')"
            );
            $stmt->execute([$doctorId, $date]);
            $byStart = [];
            foreach ($stmt->fetchAll() as $appt) {
                $byStart[$appt['start_time']] = $appt;
            }

            foreach ($slots as &$slot) {
                if (isset($byStart[$slot['start']])) {
                    $appt = $byStart[$slot['start']];
                    $history = self::visitHistory($doctorId, (int) $appt['patient_id'], $date);
                    $appt['is_returning'] = $history['is_returning'];
                    $appt['visit_count'] = $history['visit_count'];
                    $appt['last_visit_date'] = $history['last_visit_date'];
                    $slot['status'] = 'booked';
                    $slot['appointment'] = $appt;
                }
            }
            unset($slot);
        }

        return [
            'full_day_leave' => $computed['full_day_leave'],
            'leave_windows' => $computed['leave_windows'],
            'slots' => $slots,
        ];
    }

    /**
     * New-vs-returning + visit-count for a patient with this doctor, counted
     * strictly before a given date (so a patient's own future appointment
     * never makes them "returning" to themselves). Only completed/no_show
     * visits count as a real past encounter — a still-upcoming `booked`
     * appointment isn't a "visit" yet.
     */
    public static function visitHistory(int $doctorId, int $patientId, string $beforeDate): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) AS cnt, MAX(appointment_date) AS last_date FROM appointments
             WHERE doctor_id = ? AND patient_id = ? AND appointment_date < ? AND status IN ('completed', 'no_show')"
        );
        $stmt->execute([$doctorId, $patientId, $beforeDate]);
        $row = $stmt->fetch();
        $count = (int) ($row['cnt'] ?? 0);

        return [
            'is_returning' => $count > 0,
            'visit_count' => $count,
            'last_visit_date' => $row['last_date'] ?? null,
        ];
    }

    /**
     * The concurrency-safe core (see FEATURES.md): attempt to INSERT the
     * reservation row first. MySQL's UNIQUE KEY (doctor_id, appointment_date,
     * start_time) is the actual mutex — a concurrent duplicate INSERT is
     * rejected by the database engine itself, not by a PHP-level
     * check-then-act race. Only on a successful reservation insert does the
     * appointment row get created, in the same transaction.
     *
     * One-appointment-per-doctor-per-day is enforced here too, in the same
     * transaction (SELECT ... FOR UPDATE), so a double-submit can't slip two
     * through. Two deliberate exclusions from that rule:
     *   - `is_stress_test` rows, which are simulated traffic, not real clinic
     *     bookings — without this the Concurrency Stress Test would start
     *     returning zero winners once its recycled dummy patients had each
     *     accumulated a booking with the demo doctor on the demo date.
     *   - $excludeAppointmentId, the appointment an admin reassignment is
     *     moving, so reassigning a patient to a different slot with the *same*
     *     doctor on the same date isn't rejected by the patient's own booking.
     *
     * @return array{success: bool, appointment_id?: int, error?: string}
     */
    public static function book(int $doctorId, int $patientId, string $date, string $startTime, string $endTime, int $excludeAppointmentId = 0): array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                "SELECT id FROM appointments
                 WHERE patient_id = ? AND doctor_id = ? AND appointment_date = ? AND status = 'booked'
                   AND is_stress_test = 0 AND id != ? FOR UPDATE"
            );
            $stmt->execute([$patientId, $doctorId, $date, $excludeAppointmentId]);
            if ($stmt->fetch()) {
                $pdo->rollBack();
                return ['success' => false, 'error' => 'already_booked'];
            }

            $stmt = $pdo->prepare(
                'INSERT INTO slot_reservations (doctor_id, appointment_date, start_time, end_time) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$doctorId, $date, $startTime, $endTime]);
            $reservationId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare(
                "INSERT INTO appointments (patient_id, doctor_id, reservation_id, appointment_date, start_time, end_time, status)
                 VALUES (?, ?, ?, ?, ?, ?, 'booked')"
            );
            $stmt->execute([$patientId, $doctorId, $reservationId, $date, $startTime, $endTime]);
            $appointmentId = (int) $pdo->lastInsertId();

            $pdo->commit();
            return ['success' => true, 'appointment_id' => $appointmentId];
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ((int) $e->errorInfo[1] === 1062) {
                return ['success' => false, 'error' => 'slot_taken'];
            }
            throw $e;
        }
    }

    /**
     * Frees the slot_reservations row in the same transaction as the status
     * change, so the exact slot is immediately rebookable.
     */
    public static function cancel(int $appointmentId, string $cancelledBy): bool
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = ? FOR UPDATE');
            $stmt->execute([$appointmentId]);
            $appt = $stmt->fetch();

            if (!$appt || $appt['status'] !== 'booked') {
                $pdo->rollBack();
                return false;
            }

            $pdo->prepare("UPDATE appointments SET status = 'cancelled', cancelled_by = ? WHERE id = ?")
                ->execute([$cancelledBy, $appointmentId]);
            $pdo->prepare('DELETE FROM slot_reservations WHERE id = ?')->execute([$appt['reservation_id']]);

            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Frees the old slot_reservations row and creates the new one in one
     * transaction, so a reschedule can never transiently double-book the new
     * slot or lose the old one to someone else mid-move.
     *
     * @return array{success: bool, error?: string}
     */
    public static function reschedule(int $appointmentId, string $newDate, string $newStart, string $newEnd): array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = ? FOR UPDATE');
            $stmt->execute([$appointmentId]);
            $appt = $stmt->fetch();

            if (!$appt || $appt['status'] !== 'booked') {
                $pdo->rollBack();
                return ['success' => false, 'error' => 'not_found'];
            }

            if ($newDate !== $appt['appointment_date']) {
                $dupStmt = $pdo->prepare(
                    "SELECT id FROM appointments WHERE patient_id = ? AND doctor_id = ? AND appointment_date = ? AND status = 'booked' AND is_stress_test = 0 AND id != ? FOR UPDATE"
                );
                $dupStmt->execute([$appt['patient_id'], $appt['doctor_id'], $newDate, $appointmentId]);
                if ($dupStmt->fetch()) {
                    $pdo->rollBack();
                    return ['success' => false, 'error' => 'already_booked'];
                }
            }

            $pdo->prepare('DELETE FROM slot_reservations WHERE id = ?')->execute([$appt['reservation_id']]);

            $stmt = $pdo->prepare(
                'INSERT INTO slot_reservations (doctor_id, appointment_date, start_time, end_time) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$appt['doctor_id'], $newDate, $newStart, $newEnd]);
            $newReservationId = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'UPDATE appointments SET appointment_date = ?, start_time = ?, end_time = ?, reservation_id = ? WHERE id = ?'
            )->execute([$newDate, $newStart, $newEnd, $newReservationId, $appointmentId]);

            $pdo->commit();
            return ['success' => true];
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ((int) $e->errorInfo[1] === 1062) {
                return ['success' => false, 'error' => 'slot_taken'];
            }
            throw $e;
        }
    }

    /**
     * The 7 Y-m-d dates (Monday..Sunday) of the week containing $anyDateInWeek.
     * Shared by the doctor's own weekly-grid view and the admin drill-down —
     * both render the same grid component, so both need the same week math.
     */
    public static function weekDates(string $anyDateInWeek): array
    {
        $dow = (int) date('N', strtotime($anyDateInWeek)); // 1=Mon..7=Sun
        $monday = date('Y-m-d', strtotime($anyDateInWeek . ' -' . ($dow - 1) . ' days'));
        $dates = [];
        for ($i = 0; $i < 7; $i++) {
            $dates[] = date('Y-m-d', strtotime($monday . " +{$i} days"));
        }
        return $dates;
    }
}

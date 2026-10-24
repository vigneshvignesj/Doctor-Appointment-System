<?php

namespace App\Models;

use Core\Database;

class DoctorAvailabilityException
{
    public static function forDoctor(int $doctorId, bool $upcomingOnly = false): array
    {
        $sql = 'SELECT * FROM doctor_availability_exceptions WHERE doctor_id = ?';
        if ($upcomingOnly) {
            $sql .= ' AND exception_date >= CURDATE()';
        }
        $sql .= ' ORDER BY exception_date';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([$doctorId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM doctor_availability_exceptions WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(int $doctorId, string $date, string $type, ?string $start, ?string $end, ?string $reason): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO doctor_availability_exceptions (doctor_id, exception_date, type, start_time, end_time, reason) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$doctorId, $date, $type, $start, $end, $reason]);
        return (int) Database::connection()->lastInsertId();
    }

    /**
     * `leave` removes slots, so it can strand a patient who is already booked
     * inside the blocked range. Defence in depth alongside the controller
     * check: the INSERT ... SELECT only produces a row when no `booked`
     * appointment overlaps, so a leave exception can never be written over a
     * live booking. Returns 0 when the insert was refused.
     *
     * A NULL start/end means a full-day leave, in which case *any* booking on
     * that date conflicts.
     */
    public static function createLeave(int $doctorId, string $date, ?string $start, ?string $end, ?string $reason): int
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO doctor_availability_exceptions (doctor_id, exception_date, type, start_time, end_time, reason)
             SELECT :doctor_id, :exception_date, 'leave', :start_time, :end_time, :reason
             FROM DUAL
             WHERE NOT EXISTS (
                 SELECT 1 FROM appointments a
                 WHERE a.doctor_id = :doctor_id2 AND a.appointment_date = :exception_date2 AND a.status = 'booked'
                   AND (:start_time2 IS NULL OR :end_time2 IS NULL
                        OR (a.start_time >= :start_time3 AND a.start_time < :end_time3))
             )"
        );
        $stmt->execute([
            'doctor_id' => $doctorId,
            'doctor_id2' => $doctorId,
            'exception_date' => $date,
            'exception_date2' => $date,
            'start_time' => $start,
            'start_time2' => $start,
            'start_time3' => $start,
            'end_time' => $end,
            'end_time2' => $end,
            'end_time3' => $end,
            'reason' => $reason,
        ]);
        return $stmt->rowCount() > 0 ? (int) Database::connection()->lastInsertId() : 0;
    }

    /**
     * Same guard on the way out: removing an `extra_hours` exception takes its
     * slots off the calendar, so it can't be removed while a booking sits
     * inside it. Removing a `leave` exception only ever adds slots back, so it
     * is always safe and the NOT EXISTS clause is a no-op for that type.
     */
    public static function delete(int $id): bool
    {
        $row = self::find($id);
        if (!$row) {
            return false;
        }
        if ($row['type'] !== 'extra_hours') {
            $stmt = Database::connection()->prepare('DELETE FROM doctor_availability_exceptions WHERE id = ?');
            $stmt->execute([$id]);
            return $stmt->rowCount() > 0;
        }
        $stmt = Database::connection()->prepare(
            "DELETE FROM doctor_availability_exceptions
             WHERE id = :id
               AND NOT EXISTS (
                   SELECT 1 FROM appointments a
                   WHERE a.doctor_id = :doctor_id AND a.appointment_date = :exception_date AND a.status = 'booked'
                     AND a.start_time >= :start_time AND a.start_time < :end_time
               )"
        );
        $stmt->execute([
            'id' => $id,
            'doctor_id' => (int) $row['doctor_id'],
            'exception_date' => $row['exception_date'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
        ]);
        return $stmt->rowCount() > 0;
    }
}

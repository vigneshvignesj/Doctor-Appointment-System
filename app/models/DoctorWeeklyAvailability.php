<?php

namespace App\Models;

use Core\Database;

class DoctorWeeklyAvailability
{
    public static function forDoctor(int $doctorId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM doctor_weekly_availability WHERE doctor_id = ? ORDER BY day_of_week, start_time'
        );
        $stmt->execute([$doctorId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM doctor_weekly_availability WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(int $doctorId, int $dayOfWeek, string $start, string $end, int $duration): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO doctor_weekly_availability (doctor_id, day_of_week, start_time, end_time, slot_duration_minutes) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$doctorId, $dayOfWeek, $start, $end, $duration]);
        return (int) Database::connection()->lastInsertId();
    }

    /**
     * Defence in depth for the "orphaned booking" guard the controllers apply:
     * the DELETE itself refuses to fire if any today-or-future `booked`
     * appointment falls inside this window, so even a code path that forgot to
     * check first can't silently strand a patient's confirmed slot. Returns
     * false (rowCount 0) when the delete was refused, so callers can tell the
     * difference between "removed" and "no-op".
     */
    public static function delete(int $id): bool
    {
        $row = self::find($id);
        if (!$row) {
            return false;
        }
        $stmt = Database::connection()->prepare(
            "DELETE FROM doctor_weekly_availability
             WHERE id = :id
               AND NOT EXISTS (
                   SELECT 1 FROM appointments a
                   WHERE a.doctor_id = :doctor_id AND a.status = 'booked'
                     AND a.appointment_date >= CURDATE()
                     AND (DAYOFWEEK(a.appointment_date) - 1) = :dow
                     AND a.start_time >= :start_time AND a.start_time < :end_time
               )"
        );
        $stmt->execute([
            'id' => $id,
            'doctor_id' => (int) $row['doctor_id'],
            'dow' => (int) $row['day_of_week'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
        ]);
        return $stmt->rowCount() > 0;
    }
}

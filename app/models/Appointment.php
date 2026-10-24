<?php

namespace App\Models;

use Core\Database;

class Appointment
{
    private const BASE_SELECT = "SELECT a.*, pu.name AS patient_name, pu.email AS patient_email, pu.phone AS patient_phone,
                du.name AS doctor_name, d.id AS doctor_table_id, d.status AS doctor_status, s.name AS specialization_name
         FROM appointments a
         JOIN users pu ON pu.id = a.patient_id
         JOIN doctors d ON d.id = a.doctor_id
         JOIN users du ON du.id = d.user_id
         JOIN specializations s ON s.id = d.specialization_id";

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(self::BASE_SELECT . ' WHERE a.id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * "Upcoming" is date-bounded as well as status-bounded: a `booked` row whose
     * date has already passed (the doctor never marked it completed/no-show)
     * is not upcoming, it's an unresolved past visit — it belongs in the Past
     * list, not stuck at the top of the dashboard forever.
     */
    public static function upcomingForPatient(int $patientId): array
    {
        $stmt = Database::connection()->prepare(
            self::BASE_SELECT . " WHERE a.patient_id = ? AND a.status = 'booked' AND a.appointment_date >= CURDATE()
             ORDER BY a.appointment_date, a.start_time"
        );
        $stmt->execute([$patientId]);
        return $stmt->fetchAll();
    }

    /**
     * Today's appointments somebody else ended — surfaced on the patient
     * dashboard so they don't just vanish. Covers doctor *and* admin
     * cancellations (both are "the clinic cancelled on you"); the patient's own
     * cancellations are excluded because they already know, and so are the
     * admin's reassignment cancellations, which are immediately replaced by a
     * new booked appointment in the same list.
     */
    public static function todayAlertsForPatient(int $patientId): array
    {
        $stmt = Database::connection()->prepare(
            self::BASE_SELECT . " WHERE a.patient_id = ? AND a.appointment_date = CURDATE()
             AND ((a.status = 'cancelled' AND a.cancelled_by IN ('doctor','admin')) OR a.status = 'no_show')
             ORDER BY a.start_time"
        );
        $stmt->execute([$patientId]);
        return $stmt->fetchAll();
    }

    /** Mirror of upcomingForPatient(): resolved appointments, plus any still-`booked` row whose date has passed. */
    public static function pastForPatient(int $patientId): array
    {
        $stmt = Database::connection()->prepare(
            self::BASE_SELECT . " WHERE a.patient_id = ?
             AND (a.status IN ('completed','cancelled','no_show') OR a.appointment_date < CURDATE())
             ORDER BY a.appointment_date DESC, a.start_time DESC"
        );
        $stmt->execute([$patientId]);
        return $stmt->fetchAll();
    }

    public static function forDoctorOnDate(int $doctorId, string $date): array
    {
        $stmt = Database::connection()->prepare(
            self::BASE_SELECT . " WHERE a.doctor_id = ? AND a.appointment_date = ? AND a.status IN ('booked','completed','no_show')
             ORDER BY a.start_time"
        );
        $stmt->execute([$doctorId, $date]);
        return $stmt->fetchAll();
    }

    public static function upcomingForDoctor(int $doctorId): array
    {
        $stmt = Database::connection()->prepare(
            self::BASE_SELECT . " WHERE a.doctor_id = ? AND a.status = 'booked'
             ORDER BY a.appointment_date, a.start_time"
        );
        $stmt->execute([$doctorId]);
        return $stmt->fetchAll();
    }

    public static function historyForDoctor(int $doctorId): array
    {
        $stmt = Database::connection()->prepare(
            self::BASE_SELECT . ' WHERE a.doctor_id = ? ORDER BY a.appointment_date DESC, a.start_time DESC'
        );
        $stmt->execute([$doctorId]);
        return $stmt->fetchAll();
    }

    /** Past completed/no_show visits this patient had with this doctor, excluding $excludeApptId, most recent first. */
    public static function visitHistoryRows(int $doctorId, int $patientId, int $excludeApptId = 0): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM appointments
             WHERE doctor_id = ? AND patient_id = ? AND id != ? AND status IN ('completed','no_show')
             ORDER BY appointment_date DESC, start_time DESC"
        );
        $stmt->execute([$doctorId, $patientId, $excludeApptId]);
        return $stmt->fetchAll();
    }

    /** Only today's or past appointments can be resolved — a future booking can only be cancelled, not marked completed/no-show. */
    public static function markCompleted(int $id, string $notes): bool
    {
        $stmt = Database::connection()->prepare(
            "UPDATE appointments SET status = 'completed', notes = ?
             WHERE id = ? AND status = 'booked' AND appointment_date <= CURDATE()"
        );
        $stmt->execute([$notes, $id]);
        return $stmt->rowCount() > 0;
    }

    public static function markNoShow(int $id): bool
    {
        $stmt = Database::connection()->prepare(
            "UPDATE appointments SET status = 'no_show'
             WHERE id = ? AND status = 'booked' AND appointment_date <= CURDATE()"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Booked appointments that sit inside a recurring weekly window, on today
     * or any future date. Deleting that window would stop SlotEngine from ever
     * computing those slots again, so the appointments would silently vanish
     * from every schedule view while the patient still holds a confirmation —
     * see DoctorAvailabilityController::deleteAvailability().
     *
     * DAYOFWEEK() is 1=Sun..7=Sat, so -1 lines it up with the 0=Sun..6=Sat
     * convention stored in doctor_weekly_availability.day_of_week.
     */
    public static function bookedInWeeklyWindow(int $doctorId, int $dayOfWeek, string $start, string $end): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT a.id, a.appointment_date, a.start_time, pu.name AS patient_name
             FROM appointments a JOIN users pu ON pu.id = a.patient_id
             WHERE a.doctor_id = ? AND a.status = 'booked' AND a.appointment_date >= CURDATE()
               AND (DAYOFWEEK(a.appointment_date) - 1) = ?
               AND a.start_time >= ? AND a.start_time < ?
             ORDER BY a.appointment_date, a.start_time"
        );
        $stmt->execute([$doctorId, $dayOfWeek, $start, $end]);
        return $stmt->fetchAll();
    }

    /**
     * Booked appointments on one date, optionally narrowed to a time range
     * (null start/end = the whole day). Used to block a `leave` exception being
     * added over patients who are already booked, and an `extra_hours`
     * exception being removed out from under bookings made inside it.
     */
    public static function bookedOnDate(int $doctorId, string $date, ?string $start = null, ?string $end = null): array
    {
        $sql = "SELECT a.id, a.appointment_date, a.start_time, pu.name AS patient_name
                FROM appointments a JOIN users pu ON pu.id = a.patient_id
                WHERE a.doctor_id = ? AND a.appointment_date = ? AND a.status = 'booked'";
        $params = [$doctorId, $date];
        if ($start !== null && $end !== null) {
            $sql .= ' AND a.start_time >= ? AND a.start_time < ?';
            $params[] = $start;
            $params[] = $end;
        }
        $sql .= ' ORDER BY a.start_time';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Renders a conflict list from bookedInWeeklyWindow()/bookedOnDate() into one flash-message sentence. */
    public static function describeConflicts(array $rows): string
    {
        $parts = [];
        foreach (array_slice($rows, 0, 3) as $row) {
            $parts[] = date('j M', strtotime($row['appointment_date'])) . ' at ' . date('g:i A', strtotime($row['start_time'])) . ' (' . $row['patient_name'] . ')';
        }
        $more = count($rows) - count($parts);
        return implode(', ', $parts) . ($more > 0 ? " and {$more} more" : '');
    }

    /** @return array{rows: array, total: int} */
    public static function search(array $filters, int $page = 1, int $perPage = 20): array
    {
        $pdo = Database::connection();
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['doctor_id'])) {
            $where[] = 'a.doctor_id = ?';
            $params[] = (int) $filters['doctor_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'a.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'a.appointment_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'a.appointment_date <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(pu.name LIKE ? OR du.name LIKE ? OR pu.email LIKE ?)';
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }
        if (empty($filters['include_stress_test'])) {
            $where[] = 'a.is_stress_test = 0';
        }
        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM appointments a
                     JOIN users pu ON pu.id = a.patient_id
                     JOIN doctors d ON d.id = a.doctor_id
                     JOIN users du ON du.id = d.user_id
                     WHERE {$whereSql}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $stmt = $pdo->prepare(self::BASE_SELECT . " WHERE {$whereSql} ORDER BY a.appointment_date DESC, a.start_time DESC LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute($params);

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    public static function todaysBoard(): array
    {
        $stmt = Database::connection()->prepare(
            self::BASE_SELECT . " WHERE a.appointment_date = CURDATE() AND a.status IN ('booked','completed','no_show')
             ORDER BY a.doctor_id, a.start_time"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function countToday(): int
    {
        $stmt = Database::connection()->query(
            "SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE() AND status IN ('booked','completed','no_show')"
        );
        return (int) $stmt->fetchColumn();
    }

    public static function countByStatus(string $status): int
    {
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM appointments WHERE status = ? AND is_stress_test = 0");
        $stmt->execute([$status]);
        return (int) $stmt->fetchColumn();
    }

    /** Per-doctor volume / cancellation / no-show rates for the admin reports screen. */
    public static function reportByDoctor(): array
    {
        $sql = "SELECT du.name AS doctor_name, s.name AS specialization_name,
                    COUNT(*) AS total,
                    SUM(a.status = 'completed') AS completed,
                    SUM(a.status = 'cancelled') AS cancelled,
                    SUM(a.status = 'no_show') AS no_show,
                    SUM(a.status = 'booked') AS booked
                FROM appointments a
                JOIN doctors d ON d.id = a.doctor_id
                JOIN users du ON du.id = d.user_id
                JOIN specializations s ON s.id = d.specialization_id
                WHERE a.is_stress_test = 0
                GROUP BY d.id ORDER BY total DESC";
        return Database::connection()->query($sql)->fetchAll();
    }
}

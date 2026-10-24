<?php

namespace App\Models;

use Core\Database;

class Doctor
{
    private const BASE_SELECT = "SELECT d.*, u.name, u.email, u.phone, u.staff_code, u.status AS account_status,
                s.name AS specialization_name
         FROM doctors d
         JOIN users u ON u.id = d.user_id
         JOIN specializations s ON s.id = d.specialization_id";

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(self::BASE_SELECT . ' WHERE d.id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByUserId(int $userId): ?array
    {
        $stmt = Database::connection()->prepare(self::BASE_SELECT . ' WHERE d.user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    /** @return array{rows: array, total: int} */
    public static function all(string $search = '', int $specializationId = 0, string $status = '', int $page = 1, int $perPage = 15): array
    {
        $pdo = Database::connection();
        $where = ['1=1'];
        $params = [];
        if ($search !== '') {
            $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.staff_code LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if ($specializationId > 0) {
            $where[] = 'd.specialization_id = ?';
            $params[] = $specializationId;
        }
        if ($status !== '') {
            $where[] = 'd.status = ?';
            $params[] = $status;
        }
        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM doctors d JOIN users u ON u.id = d.user_id WHERE {$whereSql}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $stmt = $pdo->prepare(self::BASE_SELECT . " WHERE {$whereSql} ORDER BY u.name LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute($params);

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    /** For patient-facing browse/search — active doctors only. */
    public static function browse(string $search = '', int $specializationId = 0): array
    {
        $pdo = Database::connection();
        $where = ["d.status = 'active'"];
        $params = [];
        if ($search !== '') {
            $where[] = '(u.name LIKE ? OR s.name LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if ($specializationId > 0) {
            $where[] = 'd.specialization_id = ?';
            $params[] = $specializationId;
        }
        $whereSql = implode(' AND ', $where);
        $stmt = $pdo->prepare(self::BASE_SELECT . " WHERE {$whereSql} ORDER BY u.name");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function allActive(): array
    {
        return Database::connection()->query(self::BASE_SELECT . " WHERE d.status = 'active' ORDER BY u.name")->fetchAll();
    }

    public static function create(int $userId, int $specializationId, string $qualification, float $fee, int $duration, string $bio): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO doctors (user_id, specialization_id, qualification, consultation_fee, default_slot_duration_minutes, bio, status)
             VALUES (?, ?, ?, ?, ?, ?, \'active\')'
        );
        $stmt->execute([$userId, $specializationId, $qualification, $fee, $duration, $bio]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, int $specializationId, string $qualification, float $fee, int $duration, string $bio): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE doctors SET specialization_id = ?, qualification = ?, consultation_fee = ?, default_slot_duration_minutes = ?, bio = ? WHERE id = ?'
        );
        return $stmt->execute([$specializationId, $qualification, $fee, $duration, $bio, $id]);
    }

    public static function setStatus(int $id, string $status): bool
    {
        $stmt = Database::connection()->prepare('UPDATE doctors SET status = ? WHERE id = ?');
        return $stmt->execute([$status, $id]);
    }

    public static function countActive(): int
    {
        $stmt = Database::connection()->query("SELECT COUNT(*) FROM doctors WHERE status = 'active'");
        return (int) $stmt->fetchColumn();
    }
}

<?php

namespace App\Models;

use Core\Database;

class User
{
    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function emailExists(string $email): bool
    {
        return self::findByEmail($email) !== null;
    }

    public static function createPatient(string $name, string $email, string $passwordHash, string $phone): int
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO users (role, name, email, password_hash, phone, status) VALUES ('patient', ?, ?, ?, ?, 'active')"
        );
        $stmt->execute([$name, $email, $passwordHash, $phone]);
        return (int) Database::connection()->lastInsertId();
    }

    /** Admin-created staff account. staff_code is set by the caller once the id is known. */
    public static function createDoctorAccount(string $name, string $email, string $passwordHash, string $phone): int
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO users (role, name, email, password_hash, phone, status) VALUES ('doctor', ?, ?, ?, ?, 'active')"
        );
        $stmt->execute([$name, $email, $passwordHash, $phone]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function setStaffCode(int $id, string $code): void
    {
        Database::connection()->prepare('UPDATE users SET staff_code = ? WHERE id = ?')->execute([$code, $id]);
    }

    public static function setStatus(int $id, string $status): bool
    {
        $stmt = Database::connection()->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'patient'");
        return $stmt->execute([$status, $id]);
    }

    public static function updatePassword(int $id, string $passwordHash): void
    {
        Database::connection()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$passwordHash, $id]);
    }

    public static function updateProfile(int $id, string $name, string $phone): void
    {
        Database::connection()->prepare('UPDATE users SET name = ?, phone = ? WHERE id = ?')->execute([$name, $phone, $id]);
    }

    /** @return array{rows: array, total: int} */
    public static function listPatients(string $search = '', string $status = '', int $page = 1, int $perPage = 15): array
    {
        $pdo = Database::connection();
        $where = ["role = 'patient'"];
        $params = [];
        if ($search !== '') {
            $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if ($status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        $whereSql = implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $stmt = $pdo->prepare("SELECT * FROM users WHERE {$whereSql} ORDER BY name LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute($params);

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }

    public static function countByRole(string $role): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM users WHERE role = ?');
        $stmt->execute([$role]);
        return (int) $stmt->fetchColumn();
    }

    public static function countByStatus(string $role, string $status): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM users WHERE role = ? AND status = ?');
        $stmt->execute([$role, $status]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Dummy patients for the admin Concurrency Stress Test (see FEATURES.md —
     * N simulated simultaneous booking requests, each carrying a distinct
     * patient so the "which N-of-N request actually won" result is legible).
     * Idempotent by email so re-running the test reuses the same pool instead
     * of growing the users table indefinitely.
     */
    public static function findOrCreateStressTestPatient(int $index): array
    {
        $email = "stress.test.{$index}@clinic.test";
        $existing = self::findByEmail($email);
        if ($existing) {
            return $existing;
        }
        $hash = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        $id = self::createPatient("Stress Test Patient {$index}", $email, $hash, '9000009' . str_pad((string) $index, 3, '0', STR_PAD_LEFT));
        return self::find($id);
    }
}

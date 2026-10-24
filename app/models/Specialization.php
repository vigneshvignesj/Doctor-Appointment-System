<?php

namespace App\Models;

use Core\Database;

class Specialization
{
    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM specializations ORDER BY name')->fetchAll();
    }

    /** Specializations plus a count of active doctors in each, for the CRUD list. */
    public static function allWithDoctorCounts(): array
    {
        $sql = "SELECT s.*, COUNT(d.id) AS doctor_count
                FROM specializations s
                LEFT JOIN doctors d ON d.specialization_id = s.id AND d.status = 'active'
                GROUP BY s.id ORDER BY s.name";
        return Database::connection()->query($sql)->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM specializations WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(string $name, string $description): int
    {
        $stmt = Database::connection()->prepare('INSERT INTO specializations (name, description) VALUES (?, ?)');
        $stmt->execute([$name, $description]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, string $name, string $description): bool
    {
        $stmt = Database::connection()->prepare('UPDATE specializations SET name = ?, description = ? WHERE id = ?');
        return $stmt->execute([$name, $description, $id]);
    }

    public static function inUse(int $id): bool
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM doctors WHERE specialization_id = ?');
        $stmt->execute([$id]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public static function delete(int $id): bool
    {
        $stmt = Database::connection()->prepare('DELETE FROM specializations WHERE id = ?');
        return $stmt->execute([$id]);
    }
}

<?php

namespace App\Models;

use Core\Database;

class AuditLog
{
    public static function record(?int $actorId, ?string $actorRole, string $action, ?string $details = null): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = Database::connection()->prepare(
            'INSERT INTO audit_log (actor_id, actor_role, action, details, ip_address) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$actorId, $actorRole, $action, $details, $ip]);
    }

    /** @return array{rows: array, total: int} */
    public static function search(string $action = '', int $page = 1, int $perPage = 30): array
    {
        $pdo = Database::connection();
        $where = '1=1';
        $params = [];
        if ($action !== '') {
            $where = 'al.action LIKE ?';
            $params[] = "%{$action}%";
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_log al WHERE {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT al.*, u.name AS actor_name FROM audit_log al
                LEFT JOIN users u ON u.id = al.actor_id
                WHERE {$where} ORDER BY al.created_at DESC, al.id DESC LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return ['rows' => $stmt->fetchAll(), 'total' => $total];
    }
}

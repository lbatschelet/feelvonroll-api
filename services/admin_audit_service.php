<?php
/**
 * Admin audit service for paginated audit logs.
 * Exports: admin_audit_list.
 */

/**
 * Returns paginated audit log entries.
 *
 * @param PDO $pdo
 * @param int $limit
 * @param int $offset
 * @return array
 */
function admin_audit_list(PDO $pdo, int $limit, int $offset): array
{
    $stmt = $pdo->prepare(
        'SELECT admin_audit_logs.*, admin_users.email AS user_email
         FROM admin_audit_logs
         LEFT JOIN admin_users ON admin_users.id = admin_audit_logs.user_id
         ORDER BY admin_audit_logs.created_at DESC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $countStmt = $pdo->query('SELECT COUNT(*) as total FROM admin_audit_logs');
    $total = intval($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    return [
        'items' => $rows,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
    ];
}

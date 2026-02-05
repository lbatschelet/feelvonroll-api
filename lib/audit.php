<?php
/**
 * Audit helpers for logging admin actions.
 * Exports: log_admin_action.
 */

/**
 * Writes an admin audit log entry.
 *
 * @param PDO $pdo
 * @param int|null $userId
 * @param string $action
 * @param string $target
 * @param array|null $payload
 * @return void
 */
function log_admin_action(PDO $pdo, ?int $userId, string $action, string $target, ?array $payload = null): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO admin_audit_logs (user_id, action, target, payload) VALUES (:user_id, :action, :target, :payload)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'action' => $action,
        'target' => $target,
        'payload' => $payload ? json_encode($payload) : null,
    ]);
}

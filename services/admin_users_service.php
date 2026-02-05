<?php
/**
 * Admin users service for CRUD and reset flows.
 * Exports: admin_users_list, admin_users_create, admin_users_reset, admin_users_update, admin_users_delete.
 */

/**
 * Returns admin users list.
 *
 * @param PDO $pdo
 * @return array
 */
function admin_users_list(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, email, name, must_set_password, last_login_at, created_at FROM admin_users ORDER BY created_at ASC');
    return $stmt->fetchAll();
}

/**
 * Creates an admin user or reset token.
 *
 * @param PDO $pdo
 * @param int|null $userId
 * @param string $role
 * @param array $data
 * @return array
 * @throws ApiError
 */
function admin_users_create(PDO $pdo, ?int $userId, string $role, array $data): array
{
    $email = isset($data['email']) ? trim($data['email']) : '';
    $name = isset($data['name']) ? trim($data['name']) : '';
    $password = isset($data['password']) ? $data['password'] : '';
    if (!$email || !$name) {
        json_error('Missing name or email', 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('Invalid email', 400);
    }
    if ($role === 'bootstrap') {
        $countStmt = $pdo->query('SELECT COUNT(*) FROM admin_users');
        if (intval($countStmt->fetchColumn()) > 0) {
            json_error('Bootstrap disabled', 400);
        }
    }
    $resetToken = null;
    $expires = null;
    $hash = null;
    $mustSet = 1;
    if ($password) {
        if (strlen($password) < 8) {
            json_error('Password too short', 400);
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $mustSet = 0;
    } else {
        $resetToken = base64url_encode(random_bytes(32));
        $resetHash = hash('sha256', $resetToken);
        $expires = date('Y-m-d H:i:s', time() + 24 * 3600);
    }
    $stmt = $pdo->prepare(
        'INSERT INTO admin_users (email, name, password_hash, must_set_password, reset_token_hash, reset_token_expires)
         VALUES (:email, :name, :password_hash, :must_set, :hash, :expires)'
    );
    $stmt->execute([
        'email' => $email,
        'name' => $name,
        'password_hash' => $hash,
        'must_set' => $mustSet,
        'hash' => $resetToken ? $resetHash : null,
        'expires' => $expires,
    ]);
    $newId = intval($pdo->lastInsertId());
    log_admin_action($pdo, $userId, 'admin_user_create', 'admin_users', [
        'id' => $newId,
        'email' => $email,
    ]);
    return [
        'id' => $newId,
        'reset_token' => $resetToken,
        'reset_expires' => $expires,
    ];
}

/**
 * Generates a reset token and invalidates tokens for a user.
 *
 * @param PDO $pdo
 * @param int|null $userId
 * @param int $targetId
 * @return array
 */
function admin_users_reset(PDO $pdo, ?int $userId, int $targetId): array
{
    $resetToken = base64url_encode(random_bytes(32));
    $resetHash = hash('sha256', $resetToken);
    $expires = date('Y-m-d H:i:s', time() + 24 * 3600);
    $stmt = $pdo->prepare(
        'UPDATE admin_users
         SET must_set_password = 1,
             reset_token_hash = :hash,
             reset_token_expires = :expires,
             token_version = token_version + 1
         WHERE id = :id'
    );
    $stmt->execute([
        'hash' => $resetHash,
        'expires' => $expires,
        'id' => $targetId,
    ]);
    log_admin_action($pdo, $userId, 'admin_user_reset', 'admin_users', ['id' => $targetId]);
    return ['id' => $targetId, 'reset_token' => $resetToken, 'reset_expires' => $expires];
}

/**
 * Updates user name and email.
 *
 * @param PDO $pdo
 * @param int|null $userId
 * @param int $targetId
 * @param string $email
 * @param string $name
 * @return array
 * @throws ApiError
 */
function admin_users_update(PDO $pdo, ?int $userId, int $targetId, string $email, string $name): array
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('Invalid email', 400);
    }
    $check = $pdo->prepare('SELECT id FROM admin_users WHERE email = :email AND id != :id');
    $check->execute(['email' => $email, 'id' => $targetId]);
    if ($check->fetch()) {
        json_error('Email already exists', 400);
    }
    $stmt = $pdo->prepare('UPDATE admin_users SET email = :email, name = :name WHERE id = :id');
    $stmt->execute(['email' => $email, 'name' => $name, 'id' => $targetId]);
    log_admin_action($pdo, $userId, 'admin_user_update', 'admin_users', ['id' => $targetId]);
    return ['ok' => true];
}

/**
 * Deletes a user.
 *
 * @param PDO $pdo
 * @param int|null $userId
 * @param int $targetId
 * @return array
 */
function admin_users_delete(PDO $pdo, ?int $userId, int $targetId): array
{
    $stmt = $pdo->prepare('DELETE FROM admin_users WHERE id = :id');
    $stmt->execute(['id' => $targetId]);
    log_admin_action($pdo, $userId, 'admin_user_delete', 'admin_users', ['id' => $targetId]);
    return ['ok' => true];
}
